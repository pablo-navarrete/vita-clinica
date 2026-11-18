<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SuperAdminBranchEditTest extends TestCase
{
    public function test_superadministrator_can_edit_branch_details_only_for_the_selected_clinic(): void
    {
        $user = User::whereNull('customer_id')->whereHas('roles', fn ($q) => $q->where('name', 'Super Administrador'))->first();
        $customers = Customer::query()->limit(2)->get();
        if (!$user || $customers->count() < 2) $this->markTestSkipped('Requires a superadministrator and two clinics.');
        $root = 'http://' . config('app.domain');
        DB::beginTransaction();
        try {
            $customer = $customers[0];
            $branch = $customer->branches()->create(['name' => 'Sede para editar', 'address' => 'Dirección original', 'phone' => '12345678', 'status' => false]);
            $url = $root . '/customers/' . $customer->id . '/branches';
            $this->actingAs($user)->get($url . '?_vita_content=1')->assertOk()->assertSee('Editar sede')->assertSee('Sede para editar');
            $data = ['name' => 'Sede actualizada', 'address' => 'Nueva dirección 123', 'phone' => '+56 9 8765 4321', 'status' => 1, 'customer_id' => $customers[1]->id];
            $this->put($url . '/' . $branch->id, $data)->assertRedirect($url)->assertSessionHasNoErrors();
            $branch->refresh();
            $this->assertSame('Sede actualizada', $branch->name);
            $this->assertSame('Nueva dirección 123', $branch->address);
            $this->assertSame('+56987654321', $branch->phone);
            $this->assertSame($customer->id, $branch->customer_id);
            $this->assertFalse($branch->status);
            $this->putJson($root . '/customers/' . $customers[1]->id . '/branches/' . $branch->id, $data)->assertNotFound();
            $this->from($url)->put($url . '/' . $branch->id, ['name' => '', '_editing_branch_id' => $branch->id])->assertRedirect($url)->assertSessionHasErrors(['name'], null, 'editBranch');
            $this->get($url . '?_vita_content=1')->assertOk()->assertSee('Editar sede')->assertSee('name="_editing_branch_id"', false);
            $this->assertSame('Sede actualizada', $branch->fresh()->name);
            $this->put($url . '/' . $branch->id, ['name' => 'Solo nombre', 'address' => '', 'phone' => ''])->assertSessionHasNoErrors();
            $this->assertNull($branch->fresh()->address);
            $this->assertNull($branch->fresh()->phone);
            $this->app['auth']->forgetGuards();
            $this->putJson($url . '/' . $branch->id, ['name' => 'Sin acceso'])->assertUnauthorized();
        } finally {
            DB::rollBack();
        }
    }
}
