<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VitaWebsiteSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VitaLogosTest extends TestCase
{
    public function test_global_logos_have_clinic_equivalent_crud_limits_and_location_rules(): void
    {
        $user = User::whereNull('customer_id')->whereHas('roles', fn ($q) => $q->where('name', 'Super Administrador'))->first();
        if (!$user) $this->markTestSkipped('Requires a superadministrator fixture.');
        $root = 'http://' . config('app.domain');
        Storage::fake('public');
        DB::beginTransaction();
        try {
            VitaWebsiteSetting::updateOrCreate(['id' => 1], ['content' => array_replace(config('vita_website'), ['logos' => []])]);
            $this->actingAs($user)->get($root . '/website/logo' . '?_vita_content=1')->assertOk()->assertSee('Gestión de Logos')->assertSee('Ubicaciones');
            $make = fn ($name, $status, $locations) => $this->postJson($root . '/website/logos', ['name' => $name, 'status' => $status, 'locations' => $locations, 'image' => UploadedFile::fake()->image('logo.png')]);
            $make('Principal', 1, ['header', 'panel', 'login'])->assertOk();
            $first = VitaWebsiteSetting::logos()[0];
            Storage::disk('public')->assertExists($first['url']);
            $make('Conflicto', 1, ['header'])->assertUnprocessable();
            $this->assertCount(1, VitaWebsiteSetting::logos());
            $make('Alternativo', 0, ['header'])->assertOk();
            $second = VitaWebsiteSetting::logos()[1];
            $this->patchJson($root . '/website/logos/' . $second['id'] . '/toggle-status')->assertUnprocessable();
            $this->get($root . '/')->assertOk()->assertSee('/storage/' . $first['url'], false);
            \Illuminate\Support\Facades\Auth::logout();
            $this->get($root . '/login')->assertOk()->assertSee('/storage/' . $first['url'], false);
            $this->actingAs($user);
            $this->postJson($root . '/website/logos/' . $first['id'], ['name' => 'Actualizado', 'status' => 1, 'locations' => ['footer']])->assertOk();
            $this->patchJson($root . '/website/logos/' . $second['id'] . '/toggle-status')->assertOk();
            $this->assertSame($second['url'], VitaWebsiteSetting::logosByLocation()['header']->url);
            $make('Tercero', 0, ['footer'])->assertOk();
            $make('Cuarto', 0, ['panel'])->assertOk();
            $make('Quinto', 0, ['login'])->assertOk();
            $make('Sexto', 0, ['login'])->assertUnprocessable();
            $this->getJson($root . '/website/logos/data')->assertOk()->assertJsonPath('recordsTotal', 5);
            $this->deleteJson($root . '/website/logos/' . $first['id'])->assertOk();
            $this->assertCount(4, VitaWebsiteSetting::logos());
            Storage::disk('public')->assertMissing($first['url']);
            $this->deleteJson($root . '/website/logos/missing')->assertNotFound();
            $this->assertNotEmpty(VitaWebsiteSetting::content()['hero_title']);
        } finally {
            DB::rollBack();
        }
    }
}
