<?php
namespace App\Policies;
use App\Models\User;
class CustomerDirectoryPolicy
{
    public function manage(User $user,int $customerId): bool {
        // Explicit clinic administrator in the selected active branch; no global impersonation.
        return app(ClinicBillingPolicy::class)->manage($user,$customerId);
    }
    public function moderate(User $user): bool {return app(BillingPlanPolicy::class)->manage($user);}
}
