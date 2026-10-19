<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\WebsiteBanner;
use App\Models\WebsitePage;
use App\Support\ClinicActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WebsiteBannerController extends Controller
{
    private const MAX_BANNERS_PER_CLINIC = 5;

    public function index(): View
    {
        $customerId = (int) app('customer')->id;
        $banners = WebsiteBanner::query()
            ->where('customer_id', $customerId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
        $pages = $this->availablePages($customerId);

        return view('customer.banner.index', compact('banners', 'pages'));
    }

    public function store(Request $request): RedirectResponse
    {
        $customerId = (int) app('customer')->id;
        $bannerCount = WebsiteBanner::query()->where('customer_id', $customerId)->count();

        if ($bannerCount >= self::MAX_BANNERS_PER_CLINIC) {
            throw ValidationException::withMessages([
                'banner_limit' => 'Cada clínica puede tener un máximo de ' . self::MAX_BANNERS_PER_CLINIC . ' banners. Elimina uno para agregar otro.',
            ]);
        }

        $data = $this->validatedData($request, true);
        $data['customer_id'] = $customerId;
        $data['position'] = ((int) WebsiteBanner::query()->where('customer_id', $customerId)->max('position')) + 1;
        $data['image_path'] = $request->file('image')->store("website-banners/customer_{$customerId}", 'public');
        unset($data['image']);

        WebsiteBanner::create($data);

        return redirect()->route('customers.banners.index', ['customer' => request()->route('customer')])
            ->with('success', 'Banner creado correctamente.');
    }

    public function edit(string $customer, int $id): View
    {
        $banner = $this->findBanner($id);
        $pages = $this->availablePages((int) app('customer')->id);

        return view('customer.banner.edit', compact('banner', 'pages'));
    }

    public function update(Request $request, string $customer, int $id): RedirectResponse
    {
        $banner = $this->findBanner($id);
        $data = $this->validatedData($request, false);

        if ($request->hasFile('image')) {
            if ($banner->image_path) {
                Storage::disk('public')->delete($banner->image_path);
            }
            $customerId = (int) app('customer')->id;
            $data['image_path'] = $request->file('image')->store("website-banners/customer_{$customerId}", 'public');
        }

        unset($data['image']);
        $banner->update($data);

        return redirect()->route('customers.banners.index', ['customer' => request()->route('customer')])
            ->with('success', 'Banner actualizado correctamente.');
    }

    public function toggleStatus(string $customer, int $id): JsonResponse
    {
        $banner = $this->findBanner($id);
        $banner->update(['status' => ! $banner->status]);

        return response()->json([
            'success' => true,
            'status' => (bool) $banner->fresh()->status,
            'message' => 'Estado del banner actualizado.',
        ]);
    }

    public function reorder(Request $request, string $customer): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'integer', 'distinct'],
        ]);
        $customerId = (int) app('customer')->id;
        $storedIds = WebsiteBanner::query()->where('customer_id', $customerId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $submittedIds = array_map('intval', $data['items']);
        $expectedIds = $storedIds;
        sort($expectedIds);
        $checkIds = $submittedIds;
        sort($checkIds);

        if ($expectedIds !== $checkIds) {
            return response()->json(['success' => false, 'message' => 'La lista de banners cambió. Actualiza la página e inténtalo nuevamente.'], 422);
        }

        DB::transaction(function () use ($customerId, $submittedIds) {
            foreach ($submittedIds as $index => $id) {
                WebsiteBanner::query()
                    ->where('customer_id', $customerId)
                    ->whereKey($id)
                    ->update(['position' => $index + 1]);
            }
        });

        ClinicActivityLogger::recordManual(
            $customerId,
            'Administración web',
            'updated',
            'banners',
            'Banners',
            'Reordenó los banners de la página pública.',
            ['posición'],
            WebsiteBanner::class
        );

        return response()->json(['success' => true, 'message' => 'Orden de los banners actualizado.']);
    }

    public function destroy(string $customer, int $id): RedirectResponse
    {
        $banner = $this->findBanner($id);
        if ($banner->image_path) {
            Storage::disk('public')->delete($banner->image_path);
        }
        $banner->delete();

        return redirect()->route('customers.banners.index', ['customer' => request()->route('customer')])
            ->with('success', 'Banner eliminado correctamente.');
    }

    private function findBanner(int $id): WebsiteBanner
    {
        return WebsiteBanner::query()
            ->where('customer_id', app('customer')->id)
            ->findOrFail($id);
    }

    private function validatedData(Request $request, bool $imageRequired): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'button_text' => ['nullable', 'string', 'max:60'],
            'button_destination' => ['nullable', 'string', 'max:255'],
            'button_url_custom' => ['nullable', 'string', 'max:255'],
            'image' => [$imageRequired ? 'required' : 'nullable', 'image', 'max:10240'],
            'image_alt' => ['nullable', 'string', 'max:160'],
            'status' => ['required', 'boolean'],
        ]);

        $destination = trim((string) ($data['button_destination'] ?? ''));
        if ($destination === '__custom__') {
            $destination = trim((string) ($data['button_url_custom'] ?? ''));
            if ($destination === '') {
                throw ValidationException::withMessages(['button_url_custom' => 'Escribe el enlace personalizado.']);
            }
        }

        $buttonText = trim((string) ($data['button_text'] ?? ''));
        if ($destination !== '' && $buttonText === '') {
            throw ValidationException::withMessages(['button_text' => 'Escribe el texto que se mostrará en el botón.']);
        }
        if ($destination === '') {
            $buttonText = '';
        }

        $url = $destination;
        if ($url !== '') {
            $isAnchor = preg_match('/^#[A-Za-z][A-Za-z0-9_-]*$/', $url) === 1;
            $isLocalPath = Str::startsWith($url, '/') && ! Str::startsWith($url, '//');
            $scheme = parse_url($url, PHP_URL_SCHEME);
            $isWebUrl = filter_var($url, FILTER_VALIDATE_URL)
                && in_array(strtolower((string) $scheme), ['http', 'https'], true);

            if (! $isAnchor && ! $isLocalPath && ! $isWebUrl) {
                throw ValidationException::withMessages(['button_url_custom' => 'Usa una ruta local, un ancla o una dirección https:// válida.']);
            }
        }

        $data['button_text'] = $buttonText !== '' ? $buttonText : null;
        $data['button_url'] = $url !== '' ? $url : null;
        unset($data['button_destination'], $data['button_url_custom']);

        return $data;
    }

    private function availablePages(int $customerId)
    {
        return WebsitePage::query()
            ->where('customer_id', $customerId)
            ->where('status', true)
            ->orderBy('title')
            ->get(['title', 'slug']);
    }
}
