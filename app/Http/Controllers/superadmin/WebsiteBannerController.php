<?php

namespace App\Http\Controllers\superadmin;

use App\Http\Controllers\Controller;
use App\Models\VitaWebsiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class WebsiteBannerController extends Controller
{
    public function index()
    {
        return view('superadmin.website.banners.index', ['banners' => collect(VitaWebsiteSetting::banners())->map(fn ($item) => (object) $item), 'pages' => $this->pages()]);
    }

    public function edit(int $id)
    {
        $banner = collect(VitaWebsiteSetting::banners())->firstWhere('id', $id);
        abort_unless($banner, 404);
        return view('superadmin.website.banners.edit', ['banner' => (object) $banner, 'pages' => $this->pages()]);
    }

    public function store(Request $request)
    {
        return $this->save($request);
    }

    public function update(Request $request, int $id)
    {
        return $this->save($request, $id);
    }

    private function save(Request $request, ?int $id = null)
    {
        $data = $request->validate([
            'title' => 'required_unless:image_only,1|nullable|string|max:120', 'subtitle' => 'nullable|string|max:160', 'description' => 'nullable|string|max:2000',
            'button_text' => 'nullable|string|max:60', 'button_destination' => 'nullable|string|max:255', 'button_url_custom' => 'nullable|string|max:255',
            'image' => ($id ? 'nullable' : 'required') . '|image|mimes:jpg,jpeg,png,webp|max:10240',
            'image_alt' => 'nullable|string|max:160', 'status' => 'required|boolean',
            'dark_overlay' => 'sometimes|required|boolean',
            'image_only' => 'sometimes|required|boolean',
            'text_alignment' => 'sometimes|required|in:left,center,right',
            'button_alignment' => 'sometimes|required|in:left,center,right',
        ]);
        $data['title'] = ($data['title'] ?? null) ?: 'Banner sin texto';
        $url = trim((string) ($data['button_destination'] ?? ''));
        if ($url === '__custom__') {
            $url = trim((string) ($data['button_url_custom'] ?? ''));
            if ($url === '') throw ValidationException::withMessages(['button_url_custom' => 'Escribe el enlace personalizado.']);
        }
        if ($url !== '') {
            $local = preg_match('/\A(?:\/(?!\/)|#[A-Za-z])[^\s\\\\]*\z/', $url);
            $external = filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https']);
            if (!$local && !$external) throw ValidationException::withMessages(['button_url_custom' => 'Usa una ruta local, un ancla o una dirección HTTP/HTTPS válida.']);
            if (!trim($data['button_text'] ?? '')) throw ValidationException::withMessages(['button_text' => 'Escribe el texto del botón.']);
        }
        unset($data['image'], $data['button_destination'], $data['button_url_custom']);
        $data['button_url'] = $url ?: null;
        $data['button_text'] = $url ? trim($data['button_text']) : null;
        $data['status'] = $request->boolean('status');
        foreach (['subtitle', 'description', 'image_alt'] as $optional) $data[$optional] ??= null;
        $newPath = $oldPath = null;
        try {
            $this->mutate(function ($banners, &$content) use ($id, $data, $request, &$newPath, &$oldPath) {
                $index = $id ? array_search($id, array_column($banners, 'id'), true) : false;
                if ($id) abort_if($index === false, 404);
                if (!$id && count($banners) >= 5) throw ValidationException::withMessages(['banner_limit' => 'VITA puede tener un máximo de 5 banners. Elimina uno para agregar otro.']);
                $next = max($content['banner_next_id'] ?? 1, (max(array_column($banners, 'id') ?: [0]) + 1));
                $record = array_merge($data, ['id' => $id ?? $next, 'position' => $id ? $banners[$index]['position'] : max(array_column($banners, 'position') ?: [0]) + 1, 'image_path' => $id ? $banners[$index]['image_path'] : null]);
                $record['dark_overlay'] = array_key_exists('dark_overlay', $data) ? (bool) $data['dark_overlay'] : ($id ? ($banners[$index]['dark_overlay'] ?? true) : true);
                $record['image_only'] = array_key_exists('image_only', $data) ? (bool) $data['image_only'] : ($id ? ($banners[$index]['image_only'] ?? false) : false);
                foreach (['text_alignment', 'button_alignment'] as $field) $record[$field] = $data[$field] ?? ($id ? ($banners[$index][$field] ?? 'center') : 'center');
                if ($request->hasFile('image')) {
                    $oldPath = $record['image_path'];
                    $record['image_path'] = $newPath = $request->file('image')->store('vita/website/banners', 'public');
                }
                if ($id) $banners[$index] = $record;
                else { $banners[] = $record; $content['banner_next_id'] = $next + 1; }
                return $banners;
            });
        } catch (\Throwable $exception) {
            if ($newPath) Storage::disk('public')->delete($newPath);
            throw $exception;
        }
        $this->deleteFile($oldPath);
        return redirect()->route('superadmin.website.section', ['section' => 'banners'])->with('success', $id ? 'Banner actualizado correctamente.' : 'Banner creado correctamente.');
    }

    public function toggle(int $id)
    {
        $status = false;
        $this->mutate(function ($banners) use ($id, &$status) {
            $index = array_search($id, array_column($banners, 'id'), true);
            abort_if($index === false, 404);
            $banners[$index]['status'] = $status = !$banners[$index]['status'];
            return $banners;
        });
        return response()->json(['success' => true, 'status' => $status, 'message' => 'Estado del banner actualizado.']);
    }

    public function reorder(Request $request)
    {
        $data = $request->validate(['items' => 'required|array|min:1|max:5', 'items.*' => 'required|integer|distinct']);
        $ids = array_map('intval', $data['items']);
        $this->mutate(function ($banners) use ($ids) {
            $expected = array_column($banners, 'id'); $submitted = $ids;
            sort($expected); sort($submitted);
            if ($expected !== $submitted) throw ValidationException::withMessages(['items' => 'La lista de banners cambió. Actualiza la página e inténtalo nuevamente.']);
            return array_map(function ($id, $index) use ($banners) {
                $banner = collect($banners)->firstWhere('id', $id);
                $banner['position'] = $index + 1;
                return $banner;
            }, $ids, array_keys($ids));
        });
        return response()->json(['success' => true, 'message' => 'Orden de los banners actualizado.']);
    }

    public function destroy(int $id)
    {
        $path = null;
        $this->mutate(function ($banners) use ($id, &$path) {
            $index = array_search($id, array_column($banners, 'id'), true);
            abort_if($index === false, 404);
            $path = $banners[$index]['image_path'];
            unset($banners[$index]);
            return array_values($banners);
        });
        $this->deleteFile($path);
        return redirect()->route('superadmin.website.section', ['section' => 'banners'])->with('success', 'Banner eliminado correctamente.');
    }

    private function mutate(callable $callback): void
    {
        DB::transaction(function () use ($callback) {
            VitaWebsiteSetting::firstOrCreate(['id' => 1], ['content' => config('vita_website')]);
            $setting = VitaWebsiteSetting::whereKey(1)->lockForUpdate()->firstOrFail();
            $content = array_replace(config('vita_website'), $setting->content);
            $content['banners'] = $callback(VitaWebsiteSetting::banners($content), $content);
            $setting->update(['content' => $content]);
        });
    }

    private function deleteFile(?string $path): void
    {
        if ($path && str_starts_with($path, 'vita/website/')) Storage::disk('public')->delete($path);
    }

    private function pages()
    {
        $site = VitaWebsiteSetting::content();
        $pages = collect(VitaWebsiteSetting::menus($site))->filter(fn ($page) => $page['status'] && $page['slug'] && $page['slug'] !== 'inicio')
            ->map(fn ($page) => (object) ['slug' => $page['slug'], 'title' => $page['name'], 'url' => $page['url']]);
        return $pages;
    }
}
