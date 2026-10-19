<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\WebsitePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(): View
    {
        $customerId = (int) app('customer')->id;
        WebsitePage::ensureHomeForCustomer($customerId);
        $this->normalizePositions($customerId);

        $menus = Menu::query()
            ->where('customer_id', $customerId)
            ->with('websitePage')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
        return view('customer.menu.index', compact('menus'));
    }

    public function store(Request $request): RedirectResponse
    {
        $customerId = (int) app('customer')->id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'status' => ['required', 'boolean'],
        ]);

        $this->normalizePositions($customerId);

        $menu = DB::transaction(function () use ($data, $customerId) {
            $position = ((int) Menu::query()->where('customer_id', $customerId)->max('position')) + 1;
            $page = WebsitePage::create([
                'customer_id' => $customerId,
                'title' => $data['name'],
                'slug' => $this->uniquePageSlug($data['name'], $customerId),
                'status' => $data['status'],
            ]);

            return Menu::create([
                'customer_id' => $customerId,
                'website_page_id' => $page->id,
                'name' => $data['name'],
                'url' => $page->slug === 'inicio' ? '/' : '/pages/' . $page->slug,
                'position' => $position,
                'status' => $data['status'],
            ]);
        });

        return redirect()->route('customers.website-pages.edit', [
            'customer' => request()->route('customer'),
            'id' => $menu->website_page_id,
        ])->with('success', 'Pagina creada. Ahora puedes agregar secciones, textos e imagenes.');
    }

    public function edit(string $customer, int $id): View
    {
        $menu = Menu::query()
            ->where('customer_id', app('customer')->id)
            ->findOrFail($id);

        return view('customer.menu.edit', compact('menu'));
    }

    public function toggleStatus(string $customer, int $id): JsonResponse
    {
        $menu = Menu::query()
            ->where('customer_id', app('customer')->id)
            ->with('websitePage')
            ->findOrFail($id);

        if ($menu->websitePage?->slug === 'inicio') {
            return response()->json([
                'success' => false,
                'message' => 'La página Inicio siempre debe permanecer activa.',
            ], 422);
        }

        DB::transaction(function () use ($menu) {
            $menu->status = ! $menu->status;
            $menu->save();

            if ($menu->websitePage) {
                $menu->websitePage->update(['status' => $menu->status]);
            }
        });

        return response()->json([
            'success' => true,
            'status' => (bool) $menu->fresh()->status,
            'message' => 'Estado del enlace actualizado.',
        ]);
    }

    public function reorder(Request $request, string $customer): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'integer', 'distinct'],
        ]);

        $customerId = (int) app('customer')->id;
        WebsitePage::ensureHomeForCustomer($customerId);
        $menuIds = Menu::query()->where('customer_id', $customerId)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $submittedIds = array_map('intval', $data['items']);

        sort($menuIds);
        $checkIds = $submittedIds;
        sort($checkIds);
        if ($menuIds !== $checkIds) {
            return response()->json([
                'success' => false,
                'message' => 'La lista de páginas cambió. Actualiza la página e inténtalo nuevamente.',
            ], 422);
        }

        $homeMenuId = Menu::query()
            ->where('customer_id', $customerId)
            ->whereHas('websitePage', fn ($query) => $query->where('slug', 'inicio'))
            ->value('id');
        if ($homeMenuId) {
            $submittedIds = array_values(array_filter($submittedIds, fn ($id) => $id !== (int) $homeMenuId));
            array_unshift($submittedIds, (int) $homeMenuId);
        }

        DB::transaction(function () use ($customerId, $submittedIds) {
            $menus = Menu::query()
                ->where('customer_id', $customerId)
                ->whereIn('id', $submittedIds)
                ->get()
                ->keyBy('id');

            foreach ($submittedIds as $index => $id) {
                $menus->get($id)?->update(['position' => $index + 1]);
            }
        });

        return response()->json(['success' => true, 'message' => 'Orden del menú actualizado.']);
    }

    public function update(Request $request, string $customer, int $id): RedirectResponse
    {
        $menu = Menu::query()
            ->where('customer_id', app('customer')->id)
            ->findOrFail($id);

        if ($menu->websitePage) {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:80'],
                'status' => ['required', 'boolean'],
            ]);

            DB::transaction(function () use ($menu, $data) {
                if ($menu->websitePage->slug === 'inicio') {
                    $data['status'] = true;
                }
                $menu->update($data);
                $menu->websitePage->update([
                    'title' => $data['name'],
                    'status' => $data['status'],
                ]);
            });
        } else {
            $menu->update($this->validatedLegacyLink($request, $menu));
        }

        return redirect()->route('customers.menus.index', ['customer' => request()->route('customer')])
            ->with('success', 'Elemento del menu actualizado.');
    }

    public function destroy(string $customer, int $id): RedirectResponse
    {
        $menu = Menu::query()
            ->where('customer_id', app('customer')->id)
            ->with('websitePage.sections')
            ->findOrFail($id);

        if ($menu->websitePage?->slug === 'inicio') {
            return redirect()->route('customers.menus.index', ['customer' => request()->route('customer')])
                ->with('error', 'La página Inicio es obligatoria y no se puede eliminar.');
        }

        DB::transaction(function () use ($menu) {
            if ($menu->websitePage) {
                foreach ($menu->websitePage->sections as $section) {
                    if ($section->image_path) {
                        Storage::disk('public')->delete($section->image_path);
                    }
                }

                $menu->websitePage->delete();
            }

            $menu->delete();
        });

        return redirect()->route('customers.menus.index', ['customer' => request()->route('customer')])
            ->with('success', 'Elemento eliminado del menu.');
    }

    private function validatedLegacyLink(Request $request, Menu $menu): array
    {
        $aliases = [
            'inicio' => '/',
            'quienes somos' => '#quienes-somos',
            'servicios' => '#servicios',
            'especialidades' => '#especialidades',
            'contacto' => '#contacto',
        ];
        $enteredUrl = trim((string) $request->input('url'));
        $normalizedUrl = Str::lower(Str::ascii($enteredUrl));

        if (isset($aliases[$normalizedUrl])) {
            $request->merge(['url' => $aliases[$normalizedUrl]]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'url' => ['required', 'string', 'max:255'],
            'status' => ['required', 'boolean'],
        ]);

        $url = trim($data['url']);
        $isAnchor = preg_match('/^#[A-Za-z][A-Za-z0-9_-]*$/', $url) === 1;
        $isLocalPath = Str::startsWith($url, '/') && !Str::startsWith($url, '//');
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $isWebUrl = filter_var($url, FILTER_VALIDATE_URL)
            && in_array(strtolower((string) $scheme), ['http', 'https'], true);

        if (!$isAnchor && !$isLocalPath && !$isWebUrl) {
            throw ValidationException::withMessages([
                'url' => 'Usa una ruta como / o una direccion http:// o https:// valida.',
            ]);
        }

        $data['url'] = $url;

        return $data;
    }

    private function uniquePageSlug(string $title, int $customerId): string
    {
        $baseSlug = Str::slug($title) ?: 'pagina';
        $slug = $baseSlug;
        $suffix = 2;

        while (WebsitePage::query()
            ->where('customer_id', $customerId)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $baseSlug . '-' . $suffix++;
        }

        return $slug;
    }

    private function normalizePositions(int $customerId): void
    {
        $menus = Menu::query()
            ->where('customer_id', $customerId)
            ->orderByRaw('CASE WHEN position < 1 THEN 1 ELSE 0 END')
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'position']);

        $needsNormalization = $menus->values()->contains(
            fn (Menu $menu, int $index) => (int) $menu->position !== $index + 1
        );

        if (! $needsNormalization) {
            return;
        }

        DB::transaction(function () use ($menus) {
            foreach ($menus->values() as $index => $menu) {
                Menu::query()->whereKey($menu->id)->update(['position' => $index + 1]);
            }
        });
    }
}
