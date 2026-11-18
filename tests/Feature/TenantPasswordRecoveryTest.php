<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\ResetLoginPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TenantPasswordRecoveryTest extends TestCase
{
    public function test_recovery_is_scoped_to_clinic_and_tokens_are_single_use(): void
    {
        $clinics = Customer::limit(2)->get();
        if ($clinics->count() < 2 || !User::exists()) $this->markTestSkipped('Requires two clinics and a user fixture.');
        Notification::fake();
        DB::beginTransaction();
        try {
            $clinic = $clinics[0];
            $user = User::firstOrFail()->replicate();
            $user->fill(['customer_id' => $clinic->id, 'email' => 'recovery-' . bin2hex(random_bytes(6)) . '@example.invalid', 'password' => Hash::make('Original-pass-123')])->save();
            $requestFor = function (Customer $tenant, array $data) {
                $this->app->instance('customer', $tenant);
                $url = 'https://' . $tenant->domain . '.' . config('app.domain');
                URL::forceRootUrl($url);
                URL::forceScheme('https');
                $request = Request::create($url . '/forgot-password', 'POST', $data);
                $request->setLaravelSession(app('session.store'));
                $this->app->instance('request', $request);
                return $request;
            };
            $controller = new PasswordResetLinkController();
            $controller->store($requestFor($clinics[1], ['email' => $user->email]));
            Notification::assertNothingSent();
            $controller->store($requestFor($clinic, ['email' => $user->email]));
            $token = null;
            Notification::assertSentTo($user, ResetLoginPassword::class, function ($notification) use (&$token, $user, $clinic) {
                $token = $notification->token;
                $mail = $notification->toMail($user);
                $this->assertSame($clinic->domain . '.' . config('app.domain'), parse_url($mail->actionUrl, PHP_URL_HOST));
                $this->assertStringContainsString(rawurlencode($user->email), $mail->actionUrl);
                $this->assertSame($user->email, $user->routeNotificationFor('mail', $notification));
                return true;
            });
            $this->assertTrue(Password::tokenExists($user, $token));
            $otherIdentity = clone $user;
            $otherIdentity->customer_id = $clinics[1]->id;
            $this->assertFalse(Password::tokenExists($otherIdentity, $token));
            $payload = ['email' => $user->email, 'token' => $token, 'password' => 'New-secure-pass-123', 'password_confirmation' => 'New-secure-pass-123'];
            (new NewPasswordController())->store($requestFor($clinics[1], $payload));
            $this->assertTrue(Hash::check('Original-pass-123', $user->fresh()->password));
            (new NewPasswordController())->store($requestFor($clinic, $payload));
            $this->assertTrue(Hash::check('New-secure-pass-123', $user->fresh()->password));
            $this->assertFalse(Password::tokenExists($user, $token));
            $payload['password'] = $payload['password_confirmation'] = 'Reused-token-pass-123';
            (new NewPasswordController())->store($requestFor($clinic, $payload));
            $this->assertTrue(Hash::check('New-secure-pass-123', $user->fresh()->password));
        } finally {
            DB::rollBack();
            URL::forceRootUrl(null);
            URL::forceScheme(null);
        }
    }
}
