<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class WebsitePage extends Model
{
    protected $fillable = [
        'customer_id',
        'title',
        'slug',
        'status',
        'banner_path',
        'banner_alt',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public static function ensureHomeForCustomer(int $customerId): self
    {
        return DB::transaction(function () use ($customerId) {
            $page = self::query()->firstOrCreate(
                ['customer_id' => $customerId, 'slug' => 'inicio'],
                ['title' => 'Inicio', 'status' => true]
            );

            if (! $page->status) {
                $page->update(['status' => true]);
            }

            $menu = Menu::query()
                ->where('customer_id', $customerId)
                ->where('website_page_id', $page->id)
                ->first();

            if (! $menu) {
                Menu::query()->where('customer_id', $customerId)->increment('position');
                $menu = Menu::create([
                    'customer_id' => $customerId,
                    'website_page_id' => $page->id,
                    'name' => $page->title,
                    'url' => '/',
                    'position' => 1,
                    'status' => true,
                ]);
            } elseif ($menu->name !== $page->title || $menu->url !== '/' || ! $menu->status) {
                $menu->update([
                    'name' => $page->title,
                    'url' => '/',
                    'status' => true,
                ]);
            }

            if ((int) $menu?->position !== 1) {
                Menu::query()
                    ->where('customer_id', $customerId)
                    ->where('id', '!=', $menu->id)
                    ->increment('position');
                $menu->update(['position' => 1]);
            }

            return $page;
        });
    }

    public function sections(): HasMany
    {
        return $this->hasMany(WebsitePageSection::class)
            ->orderBy('position')
            ->orderBy('id');
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(Menu::class);
    }
}
