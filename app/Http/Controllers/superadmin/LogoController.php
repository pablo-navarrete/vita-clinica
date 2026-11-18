<?php

namespace App\Http\Controllers\superadmin;

use App\Http\Controllers\Controller;
use App\Models\VitaWebsiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class LogoController extends Controller
{
    public function index()
    {
        return view('superadmin.website.logos', ['logosCount' => count(VitaWebsiteSetting::logos())]);
    }

    public function data()
    {
        $rows = collect(VitaWebsiteSetting::logos())->map(function ($logo) {
            $logo['locations_array'] = $logo['locations'];
            $logo['locations'] = collect($logo['locations'])->map(fn ($location) => '<span class="inline-block px-2 py-1 mr-1 text-xs bg-blue-100 text-blue-700 rounded">' . e($location) . '</span>')->implode(' ');
            $logo['actions'] = '';
            return $logo;
        });
        return DataTables::of($rows)->rawColumns(['locations', 'actions'])->make(true);
    }

    public function store(Request $request)
    {
        return $this->save($request);
    }

    public function update(Request $request, string $id)
    {
        return $this->save($request, $id);
    }

    private function save(Request $request, ?string $id = null)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120', 'image' => ($id ? 'nullable' : 'required') . '|image|mimes:jpg,jpeg,png,webp|max:4096',
            'status' => 'required|boolean', 'locations' => 'required|array|min:1|max:4', 'locations.*' => 'required|distinct|in:header,footer,panel,login',
        ]);
        $newPath = null;
        try {
            $this->mutate(function ($logos) use ($request, $data, $id, &$newPath) {
                $index = $id ? array_search($id, array_column($logos, 'id'), true) : false;
                if ($id) abort_if($index === false, 404);
                if (!$id && count($logos) >= 5) throw ValidationException::withMessages(['image' => 'Solo se permiten como máximo 5 logos.']);
                $record = ['id' => $id ?? (string) Str::uuid(), 'name' => $data['name'], 'status' => (int) $data['status'], 'locations' => array_values($data['locations']), 'url' => $id ? $logos[$index]['url'] : ''];
                $this->assertNoConflict($logos, $record);
                if ($request->hasFile('image')) $record['url'] = $newPath = $request->file('image')->store('vita/website/logos', 'public');
                if ($id) $logos[$index] = $record;
                else $logos[] = $record;
                return $logos;
            });
        } catch (\Throwable $exception) {
            if ($newPath) Storage::disk('public')->delete($newPath);
            throw $exception;
        }
        return response()->json(['success' => true]);
    }

    public function toggle(Request $request, string $id)
    {
        $this->mutate(function ($logos) use ($id) {
            $index = array_search($id, array_column($logos, 'id'), true);
            abort_if($index === false, 404);
            $logos[$index]['status'] = $logos[$index]['status'] ? 0 : 1;
            $this->assertNoConflict($logos, $logos[$index]);
            return $logos;
        });
        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, string $id)
    {
        $path = null;
        $this->mutate(function ($logos) use ($id, &$path) {
            $index = array_search($id, array_column($logos, 'id'), true);
            abort_if($index === false, 404);
            $path = $logos[$index]['url'];
            unset($logos[$index]);
            return array_values($logos);
        });
        if ($path && str_starts_with($path, 'vita/website/')) Storage::disk('public')->delete($path);
        return response()->json(['success' => true]);
    }

    private function assertNoConflict(array $logos, array $record): void
    {
        if (!$record['status']) return;
        foreach ($logos as $other) {
            if ($other['id'] !== $record['id'] && $other['status'] && array_intersect($record['locations'], $other['locations'])) {
                throw ValidationException::withMessages(['locations' => 'Ya existe otro logo activo en una de las ubicaciones seleccionadas.']);
            }
        }
    }

    private function mutate(callable $callback): void
    {
        DB::transaction(function () use ($callback) {
            VitaWebsiteSetting::firstOrCreate(['id' => 1], ['content' => config('vita_website')]);
            $setting = VitaWebsiteSetting::whereKey(1)->lockForUpdate()->firstOrFail();
            $content = array_replace(config('vita_website'), $setting->content);
            $content['logos'] = $callback(VitaWebsiteSetting::logos($content));
            $setting->update(['content' => $content]);
        });
    }
}
