<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $user = User::factory()->create(['customer_id' => null, 'status' => 1]);
        $role = \App\Models\Role::firstOrCreate(['customer_id' => null, 'name' => 'Super Administrador']);
        $user->roles()->attach($role->id);
        return $user;
    }

    public function test_profile_page_is_displayed(): void
    {
        $user = $this->administrator();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = $this->administrator();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = $this->administrator();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_superadmin_account_deletion_is_not_exposed(): void
    {
        $user = $this->administrator();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response->assertStatus(405);
        $this->assertAuthenticated();
        $this->assertNotNull($user->fresh());
    }

    public function test_password_changes_require_current_password(): void
    {
        $user = $this->administrator();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => $user->name, 'email' => $user->email,
                'current_password' => 'wrong-password',
                'password' => 'new-password-test', 'password_confirmation' => 'new-password-test',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/profile');

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password', $user->fresh()->password));
    }
}
