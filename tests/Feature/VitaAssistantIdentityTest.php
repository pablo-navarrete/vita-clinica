<?php
namespace Tests\Feature;

use App\Http\Controllers\customer\VitaAssistantController;
use App\Models\Customer;
use App\Models\User;
use App\Support\ClinicBranchContext;
use Illuminate\Http\Request;
use Tests\TestCase;

class VitaAssistantIdentityTest extends TestCase
{
    public function test_viti_uses_authenticated_identity_instead_of_browser_supplied_identity(): void
    {
        $fixture = null;
        foreach (User::whereNotNull('customer_id')->where('status', 1)->get() as $user) {
            foreach (ClinicBranchContext::availableBranches($user, (int) $user->customer_id) as $branch) {
                if ($user->hasClinicRole('Doctor', (int) $user->customer_id, $branch->id)) {
                    $fixture = [$user, $branch];
                    break 2;
                }
            }
        }
        if (!$fixture) $this->markTestSkipped('Requires an existing doctor and clinic branch.');
        [$user, $branch] = $fixture;
        $clinic = Customer::findOrFail($user->customer_id);
        $this->app->instance('customer', $clinic);
        $this->actingAs($user);
        session(['active_branch_customer_id' => $clinic->id, 'active_branch_id' => $branch->id]);
        $request = Request::create('/assistant/ask', 'POST', ['message' => 'quien soy', 'user_id' => -1, 'name' => 'Fake identity']);
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session.store'));
        $this->app->instance('request', $request);
        $controller = new VitaAssistantController();
        $reply = $controller->ask($request, $clinic->domain)->getData(true)['reply'];
        $this->assertStringContainsString($user->name, $reply);
        $this->assertStringContainsString('Doctor', $reply);
        $this->assertStringContainsString($clinic->name, $reply);
        $this->assertStringContainsString($branch->name, $reply);
        $this->assertStringNotContainsString('Fake identity', $reply);
        $request->merge(['message' => 'hola viti']);
        $greeting = $controller->ask($request, $clinic->domain)->getData(true)['reply'];
        $this->assertStringContainsString('Hola, ' . $user->name, $greeting);
    }
}
