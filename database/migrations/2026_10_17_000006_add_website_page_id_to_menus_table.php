<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->foreignId('website_page_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('website_pages')
                ->nullOnDelete();
        });

        DB::table('menus')->orderBy('id')->get()->each(function ($menu) {
            $baseSlug = Str::slug($menu->name) ?: 'pagina';
            $slug = $baseSlug;
            $suffix = 2;

            while (DB::table('website_pages')
                ->where('customer_id', $menu->customer_id)
                ->where('slug', $slug)
                ->exists()) {
                $slug = $baseSlug . '-' . $suffix++;
            }

            $pageId = DB::table('website_pages')->insertGetId([
                'customer_id' => $menu->customer_id,
                'title' => $menu->name,
                'slug' => $slug,
                'status' => $menu->status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('menus')->where('id', $menu->id)->update([
                'website_page_id' => $pageId,
                'url' => $slug === 'inicio' ? '/' : '/pages/' . $slug,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropConstrainedForeignId('website_page_id');
        });
    }
};
