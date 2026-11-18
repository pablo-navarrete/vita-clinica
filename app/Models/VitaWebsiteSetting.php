<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VitaWebsiteSetting extends Model
{
    protected $fillable = ['id', 'content'];
    protected $casts = ['content' => 'array'];
    public $incrementing = false;

    public static function content(): array
    {
        return array_replace(config('vita_website'), static::find(1)?->content ?? []);
    }

    public static function logos(?array $content = null): array
    {
        $content ??= static::content();
        if (is_array($content['logos'] ?? null)) return $content['logos'];
        return empty($content['logo_path']) ? [] : [[
            'id' => 'legacy', 'name' => 'Logo VITA', 'url' => $content['logo_path'], 'status' => 1, 'locations' => ['header'],
        ]];
    }

    public static function footer(?array $content = null): array
    {
        $content ??= static::content();
        return array_replace([
            'enabled' => true, 'show_social_links' => true, 'brand_display' => 'both',
            'title' => $content['brand'], 'description' => $content['footer'],
            'phone' => $content['contact_phone'], 'email' => $content['contact_email'], 'address' => null,
        ], $content['footer_settings'] ?? []);
    }

    public static function topbar(?array $content = null): array
    {
        $content ??= static::content();
        $footer = static::footer($content);
        return array_replace([
            'enabled' => (bool) $content['topbar_enabled'], 'show_social_links' => false,
            'phone' => $footer['phone'], 'email' => $footer['email'],
        ], $content['topbar_settings'] ?? []);
    }

    public static function socialLinks(?array $content = null): array
    {
        $content ??= static::content();
        if (is_array($content['social_links'] ?? null)) return $content['social_links'];
        $links = [];
        foreach (WebsiteSocialSetting::networks() as $network => $definition) {
            if (!empty($content['social_' . $network])) $links[] = ['network' => $network, 'url' => $content['social_' . $network]];
        }
        return $links;
    }

    public static function menus(?array $content = null): array
    {
        $content ??= static::content();
        if (is_array($content['menu_entries'] ?? null)) return $content['menu_entries'];
        $menus = [['id' => 1, 'name' => 'Inicio', 'url' => '/', 'slug' => 'inicio', 'status' => true, 'position' => 1, 'sections' => []]];
        foreach ($content['menus'] as $menu) {
            if ($menu['url'] === '/') continue;
            $menus[] = ['id' => count($menus) + 1, 'name' => $menu['label'], 'url' => $menu['url'], 'slug' => null, 'status' => true, 'position' => count($menus) + 1, 'sections' => []];
        }
        return $menus;
    }

    public static function logosByLocation(?array $content = null): array
    {
        $locations = [];
        foreach (static::logos($content) as $logo) {
            if (!$logo['status']) continue;
            foreach ($logo['locations'] as $location) $locations[$location] = (object) $logo;
        }
        return $locations;
    }

    public static function banners(?array $content = null): array
    {
        $content ??= static::content();
        if (is_array($content['banners'] ?? null)) return $content['banners'];
        return empty($content['banner_path']) ? [] : [[
            'id' => 1, 'title' => $content['hero_title'], 'subtitle' => $content['hero_label'],
            'description' => $content['hero_description'], 'button_text' => $content['cta_label'], 'button_url' => '#contacto',
            'image_path' => $content['banner_path'], 'image_alt' => $content['hero_title'], 'status' => true, 'position' => 1,
        ]];
    }
}
