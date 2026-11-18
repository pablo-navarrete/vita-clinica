<?php

namespace App\Http\Controllers\superadmin;

use App\Http\Controllers\Controller;
use App\Models\VitaWebsiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WebsiteMenuController extends Controller
{
    public function index()
    {
        $site = VitaWebsiteSetting::content();
        $menus = collect(VitaWebsiteSetting::menus($site))->map(function ($menu) {
            $menu['websitePage'] = $menu['slug'] ? (object) ['id' => $menu['id'], 'slug' => $menu['slug']] : null;
            return (object) $menu;
        });
        return view('superadmin.website.menus.index', ['menus' => $menus, 'navigationButton' => (object) $site['navigation_button'], 'headerBrandDisplay' => $site['header_brand_display']]);
    }

    public function updateBrand(Request $request)
    {
        $data = $request->validate(['brand_display' => ['required', 'in:both,logo,title']]);
        $this->mutate(function (&$site, &$menus) use ($data) {
            $site['header_brand_display'] = $data['brand_display'];
        });
        return $this->back('Presentación de VITA actualizada.');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:80', 'status' => 'required|boolean']);
        $id = null;
        $this->mutate(function (&$site, &$menus) use ($data, &$id) {
            $id = max((int) $site['menu_next_id'], max(array_column($menus, 'id')) + 1);
            $site['menu_next_id'] = $id + 1;
            $base = Str::slug($data['name']) ?: 'pagina';
            $slug = $base;
            $suffix = 2;
            while (in_array($slug, array_column($menus, 'slug'), true)) $slug = $base . '-' . $suffix++;
            $menus[] = ['id' => $id, 'name' => $data['name'], 'slug' => $slug, 'url' => '/pages/' . $slug, 'status' => (bool) $data['status'], 'position' => count($menus) + 1, 'sections' => []];
        });
        return redirect()->route('superadmin.website-pages.edit', $id)->with('success', 'Página creada. Ahora puedes agregar secciones, textos e imágenes.');
    }

    public function edit(int $id)
    {
        $menu = $this->find($id);
        $menu['websitePage'] = $menu['slug'] ? (object) ['id' => $id, 'slug' => $menu['slug']] : null;
        return view('superadmin.website.menus.edit', ['menu' => (object) $menu]);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate(['name' => 'required|string|max:80', 'status' => 'required|boolean', 'url' => 'nullable|string|max:255']);
        $this->mutate(function (&$site, &$menus) use ($id, $data) {
            $index = $this->indexOf($menus, $id);
            $menu = &$menus[$index];
            $menu['name'] = $data['name'];
            $menu['status'] = $menu['slug'] === 'inicio' || (bool) $data['status'];
            if (!$menu['slug']) {
                $aliases = ['inicio' => '/', 'funcionalidades' => '#funcionalidades', 'preguntas frecuentes' => '#preguntas', 'contacto' => '#contacto'];
                $url = trim($data['url'] ?? '');
                $url = $aliases[Str::lower(Str::ascii($url))] ?? $url;
                $this->validateUrl($url, 'url');
                $menu['url'] = $url;
            }
        });
        return $this->back('Elemento del menú actualizado.');
    }

    public function toggle(int $id)
    {
        $status = false;
        $this->mutate(function (&$site, &$menus) use ($id, &$status) {
            $index = $this->indexOf($menus, $id);
            if ($menus[$index]['slug'] === 'inicio') throw ValidationException::withMessages(['status' => 'La página Inicio siempre debe permanecer activa.']);
            $menus[$index]['status'] = $status = !$menus[$index]['status'];
        });
        return response()->json(['success' => true, 'status' => $status, 'message' => 'Estado del enlace actualizado.']);
    }

    public function reorder(Request $request)
    {
        $data = $request->validate(['items' => 'required|array|min:1', 'items.*' => 'required|integer|distinct']);
        $this->mutate(function (&$site, &$menus) use ($data) {
            $ids = array_map('intval', $data['items']);
            $expected = array_column($menus, 'id'); $check = $ids;
            sort($expected); sort($check);
            if ($expected !== $check) throw ValidationException::withMessages(['items' => 'La lista de páginas cambió. Actualiza e inténtalo nuevamente.']);
            $home = collect($menus)->firstWhere('slug', 'inicio')['id'];
            $ids = array_values(array_filter($ids, fn ($id) => $id !== $home));
            array_unshift($ids, $home);
            $menus = array_map(function ($id, $index) use ($menus) {
                $menu = collect($menus)->firstWhere('id', $id);
                $menu['position'] = $index + 1;
                return $menu;
            }, $ids, array_keys($ids));
        });
        return response()->json(['success' => true, 'message' => 'Orden del menú actualizado.']);
    }

    public function destroy(int $id)
    {
        $paths = [];
        $this->mutate(function (&$site, &$menus) use ($id, &$paths) {
            $index = $this->indexOf($menus, $id);
            if ($menus[$index]['slug'] === 'inicio') throw ValidationException::withMessages(['menu' => 'La página Inicio es obligatoria y no se puede eliminar.']);
            $paths = array_column($menus[$index]['sections'], 'image_path');
            unset($menus[$index]);
            $menus = array_values($menus);
            if ((int) $site['navigation_button']['menu_id'] === $id) $site['navigation_button']['enabled'] = false;
        });
        $this->deleteImages($paths);
        return $this->back('Página eliminada del menú.');
    }

    public function navigationButton(Request $request)
    {
        $data = $request->validate(['enabled' => 'required|boolean', 'label' => 'required|string|max:60', 'destination' => 'required|in:contact,whatsapp,page,custom', 'menu_id' => 'nullable|integer', 'whatsapp_phone' => 'nullable|string|max:40', 'custom_url' => 'nullable|string|max:500']);
        $this->mutate(function (&$site, &$menus) use ($data) {
            if ($data['destination'] === 'page' && !collect($menus)->contains(fn ($m) => $m['id'] === (int) ($data['menu_id'] ?? 0) && $m['status'])) throw ValidationException::withMessages(['menu_id' => 'Selecciona una página pública activa.']);
            if ($data['destination'] === 'custom') $this->validateUrl($data['custom_url'] ?? '', 'custom_url');
            $phone = preg_replace('/[^0-9]/', '', $data['whatsapp_phone'] ?? '');
            if ($data['destination'] === 'whatsapp' && !preg_match('/^\d{8,15}$/', $phone)) throw ValidationException::withMessages(['whatsapp_phone' => 'Ingresa el número con código de país (8 a 15 dígitos).']);
            $site['navigation_button'] = array_replace(config('vita_website.navigation_button'), $data, ['enabled' => (bool) $data['enabled'], 'menu_id' => $data['destination'] === 'page' ? (int) $data['menu_id'] : null, 'whatsapp_phone' => $data['destination'] === 'whatsapp' ? $phone : null, 'custom_url' => $data['destination'] === 'custom' ? $data['custom_url'] : null]);
        });
        return $this->back('Botón de navegación actualizado.');
    }

    public function editPage(int $id)
    {
        $page = $this->find($id);
        if ($page['slug'] === 'inicio') {
            $definition = config('vita_website_sections.menus');
            $definition['title'] = 'Contenido de Inicio';
            return view('superadmin.website.section', ['section' => 'menus', 'site' => VitaWebsiteSetting::content(), 'definition' => $definition]);
        }
        abort_unless($page['slug'], 404);
        return view('superadmin.website.menus.page', compact('page'));
    }

    public function updatePage(Request $request, int $id)
    {
        $data = $request->validate(['title' => 'required|string|max:120', 'sections' => 'nullable|array|max:30', 'sections.*.id' => 'required|uuid|distinct', 'sections.*.title' => 'nullable|string|max:160', 'sections.*.body' => 'nullable|string|max:20000', 'sections.*.image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240', 'sections.*.image_alt' => 'nullable|string|max:160', 'sections.*.remove_image' => 'nullable|boolean']);
        $created = []; $removed = [];
        try {
            $this->mutate(function (&$site, &$menus) use ($id, $data, &$created, &$removed) {
                $index = $this->indexOf($menus, $id);
                abort_unless($menus[$index]['slug'] && $menus[$index]['slug'] !== 'inicio', 404);
                $old = collect($menus[$index]['sections'])->keyBy('id');
                $sections = [];
                foreach ($data['sections'] ?? [] as $section) {
                    $path = $old->get($section['id'])['image_path'] ?? null;
                    if (!empty($section['remove_image'])) $path = null;
                    if (!empty($section['image'])) $created[] = $path = $section['image']->store('vita/website/pages', 'public');
                    $sections[] = ['id' => $section['id'], 'title' => $section['title'] ?? '', 'body' => $section['body'] ?? '', 'image_path' => $path, 'image_alt' => $section['image_alt'] ?? ''];
                }
                $removed = array_diff($old->pluck('image_path')->filter()->all(), array_column($sections, 'image_path'));
                $menus[$index]['sections'] = $sections;
                $menus[$index]['name'] = $data['title'];
            });
        } catch (\Throwable $error) {
            $this->deleteImages($created);
            throw $error;
        }
        $this->deleteImages($removed);
        return redirect()->route('superadmin.website-pages.edit', $id)->with('success', 'Página actualizada.');
    }

    public function publicPage(string $slug)
    {
        $site = VitaWebsiteSetting::content();
        if (!$site['published']) return response()->view('vita.website-unavailable', [], 503);
        $publicPage = collect(VitaWebsiteSetting::menus($site))->first(fn ($menu) => $menu['slug'] === $slug && $menu['status']);
        abort_unless($publicPage && $slug !== 'inicio', 404);
        return view('vita.website', compact('site', 'publicPage'));
    }

    private function find(int $id): array
    {
        $menus = VitaWebsiteSetting::menus();
        return $menus[$this->indexOf($menus, $id)];
    }

    private function indexOf(array $menus, int $id): int
    {
        $index = array_search($id, array_column($menus, 'id'), true);
        abort_if($index === false, 404);
        return $index;
    }

    private function mutate(callable $callback): void
    {
        DB::transaction(function () use ($callback) {
            VitaWebsiteSetting::firstOrCreate(['id' => 1], ['content' => config('vita_website')]);
            $setting = VitaWebsiteSetting::whereKey(1)->lockForUpdate()->firstOrFail();
            $site = array_replace(config('vita_website'), $setting->content);
            $menus = VitaWebsiteSetting::menus($site);
            $callback($site, $menus);
            $site['menu_entries'] = $menus;
            $setting->update(['content' => $site]);
        });
    }

    private function validateUrl(string $url, string $field): void
    {
        $local = preg_match('/\A(?:\/(?!\/)|#[a-zA-Z])[^\s\\\\]*\z/', $url);
        $web = filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true);
        $contact = preg_match('/\Amailto:[^\s<>]+@[^\s<>]+\z/', $url) || preg_match('/\Atel:\+?[0-9()-]+\z/', $url);
        if (!$local && !$web && !$contact) throw ValidationException::withMessages([$field => 'Usa una ruta local, ancla o enlace HTTP/HTTPS, correo o teléfono válido.']);
    }

    private function deleteImages(array $paths): void
    {
        foreach ($paths as $path) if ($path && str_starts_with($path, 'vita/website/pages/')) Storage::disk('public')->delete($path);
    }

    private function back(string $message)
    {
        return redirect()->route('superadmin.website.section', ['section' => 'menus'])->with('success', $message);
    }
}
