<?php

namespace App\Services;

use App\Models\{DirectoryReview, DirectoryReviewReply, Encounter, PortalAccount, User};
use App\Policies\CustomerDirectoryPolicy;
use App\Support\ClinicBranchContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DirectoryReviewService
{
    // Explicit booking authorization is evidence; a document-match candidate is not.
    public function eligibleEncounters(PortalAccount $account, int $customerId)
    {
        return Encounter::query()->where('encounters.customer_id', $customerId)->where('encounters.status', 2)
            ->whereExists(function ($query) use ($account) {
                $query->selectRaw('1')->from('portal_booking_authorizations as authorization')
                    ->join('patient_registration_attempts as registration', 'registration.operation_id', '=', 'authorization.registration_operation')
                    ->where('authorization.portal_account_id', $account->id)
                    ->whereNotNull('authorization.authorized_at')->whereNotNull('authorization.completed_at')
                    ->where('registration.status', 'completed')
                    ->whereColumn('authorization.customer_id', 'encounters.customer_id')
                    ->whereColumn('authorization.patient_id', 'encounters.patient_id')
                    ->whereColumn('registration.customer_id', 'encounters.customer_id')
                    ->whereColumn('registration.patient_id', 'encounters.patient_id')
                    ->whereColumn('registration.appointment_id', 'encounters.appointment_id');
            });
    }

    public function publish(PortalAccount $account, int $customerId, array $data): DirectoryReview
    {
        return DB::transaction(function () use ($account, $customerId, $data) {
            $account = PortalAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            abort_unless($account->active && $account->email_verified_at, 403);
            app(CustomerDirectoryService::class)->eligibleQuery()->where('customer_id', $customerId)->firstOrFail();
            $encounter = $this->eligibleEncounters($account, $customerId)->whereKey($data['encounter_id'])->firstOrFail();
            $professionalId = $data['target'] === 'professional' ? $encounter->professional_id : null;
            if ($data['target'] === 'professional' && (!$professionalId || !User::where('customer_id', $customerId)->where('status', 1)->whereKey($professionalId)->exists())) {
                throw ValidationException::withMessages(['target'=>'El profesional no está disponible para recibir esta reseña.']);
            }
            $review = DirectoryReview::where('portal_account_id', $account->id)->where('customer_id', $customerId)->first();
            if ($review && !$review->withdrawn_at) {
                throw ValidationException::withMessages(['review'=>'Ya publicaste una reseña de esta clínica. Puedes retirarla desde esta página.']);
            }
            // Withdrawn reviews stay withdrawn; no silent replacement of replies/history.
            if ($review) throw ValidationException::withMessages(['review'=>'Tu reseña fue retirada. No se puede publicar otra para esta misma clínica.']);
            return DirectoryReview::create([
                'customer_id'=>$customerId, 'portal_account_id'=>$account->id, 'encounter_id'=>$encounter->id,
                'professional_id'=>$professionalId, 'public_name'=>trim($data['public_name']),
                'rating'=>$data['rating'], 'comment'=>trim($data['comment']),
                'publication_consented_at'=>now(), 'consent_version'=>'directory-review-v1',
            ]);
        });
    }

    public function canReply(User $user, DirectoryReview $review): bool
    {
        if ((int)$user->customer_id !== (int)$review->customer_id || (int)$user->status !== 1 || $review->withdrawn_at) return false;
        $branch = ClinicBranchContext::defaultBranch($user, (int)$review->customer_id);
        if (!$branch || !$branch->status || !$user->branches()->whereKey($branch->id)->wherePivot('status', true)->exists()) return false;
        $encounter = $review->encounter;
        if (!$encounter || (int)$encounter->clinic_branch_id !== (int)$branch->id) return false;
        return app(CustomerDirectoryPolicy::class)->manage($user, (int)$review->customer_id)
            || ((int)$encounter->professional_id === (int)$user->id && $user->hasClinicRole('Doctor', (int)$review->customer_id, (int)$branch->id));
    }

    public function reply(User $user, DirectoryReview $review, string $body): void
    {
        DB::transaction(function () use ($user, $review, $body) {
            $review = DirectoryReview::whereKey($review->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->canReply($user, $review), 403);
            if (DirectoryReviewReply::where('directory_review_id', $review->id)->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['body'=>'Ya respondiste esta reseña.']);
            }
            $asClinic = app(CustomerDirectoryPolicy::class)->manage($user, (int)$review->customer_id);
            DirectoryReviewReply::create(['directory_review_id'=>$review->id, 'user_id'=>$user->id,
                'author_name'=>$asClinic ? 'La clínica' : $user->name,
                'author_role'=>$asClinic ? 'clinic' : 'professional', 'body'=>trim($body)]);
        });
    }
}
