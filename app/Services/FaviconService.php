<?php

namespace App\Services;

use App\Models\{Logo, LogoLocation, VitaWebsiteSetting};
use Illuminate\Support\Facades\Storage;

class FaviconService
{
    public function current(): array
    {
        $customer = app()->bound('customer') ? app('customer') : null;
        if ($customer) {
            $logos = Logo::where('customer_id', $customer->id)->where('status', 1)->orderBy('id')->get(['id','url']);
            $locations = LogoLocation::where('customer_id', $customer->id)->whereIn('logo_id', $logos->pluck('id'))->get();
            $ordered = collect();
            foreach (['panel','header','login','footer'] as $location) {
                foreach ($locations->where('location', $location) as $assignment) {
                    if ($logo = $logos->firstWhere('id', $assignment->logo_id)) $ordered->push($logo);
                }
            }
            foreach ($ordered->merge($logos)->unique('id') as $logo) {
                if ($icon = $this->image($logo->url, ['logos/customer_'.$customer->id.'/'])) return $icon;
            }
        }

        $content = VitaWebsiteSetting::content();
        $logos = collect(VitaWebsiteSetting::logos($content))->filter(fn($logo)=>!empty($logo['status']));
        $ordered = collect();
        foreach (['header','panel','login','footer'] as $location) {
            $ordered = $ordered->merge($logos->filter(fn($logo)=>in_array($location, $logo['locations'] ?? [], true)));
        }
        $paths = $ordered->merge($logos)->pluck('url')->push('admin/logos/logo-vita-3.png')->unique();
        foreach ($paths as $path) {
            if ($icon = $this->image($path, ['vita/','admin/logos/'])) return $icon;
        }
        return ['url'=>asset('favicon-vita.svg').'?v='.filemtime(public_path('favicon-vita.svg')), 'type'=>'image/svg+xml'];
    }

    private function image(?string $path, array $prefixes): ?array
    {
        if (!$path || str_contains($path, '..') || str_contains($path, '\\')
            || !collect($prefixes)->contains(fn($prefix)=>str_starts_with($path, $prefix))
            || !preg_match('/\.(?:png|jpe?g|webp|gif)\z/i', $path)) return null;
        $disk = Storage::disk('public');
        if (!$disk->exists($path)) return null;
        $file = $disk->path($path);
        $info = @getimagesize($file);
        if (!$info || !in_array($info['mime'] ?? '', ['image/png','image/jpeg','image/webp','image/gif'], true)) return null;
        $version = substr(hash('sha256', $path.'|'.filemtime($file).'|'.filesize($file)), 0, 12);
        return ['url'=>asset('storage/'.$path).'?v='.$version, 'type'=>$info['mime']];
    }
}
