<?php

namespace App\Services\MiSalud;

use App\Models\{Customer, Encounter, Patient, PortalAccount, PortalClinicalGrant, User};
use App\Policies\PortalClinicalAccessPolicy;
use App\Services\Identification\{IdentificationService, InvalidIdentification};
use App\Services\Identification\Storage\{IdentifierCrypto, IdentifierRepositoryFactory};
use App\Support\ClinicBranchContext;
use Illuminate\Support\Facades\{DB, Hash, Schema};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PortalClinicalAccessService
{
    private function identity(PortalAccount $account, Patient $patient): ?array
    {
        if (!$account->active || !$account->email_verified_at || (int)$patient->status !== 1) return null;
        $accountReference=DB::table('portal_account_identifiers')->where('portal_account_id',$account->id)->whereNull('retired_at')->value('id');
        $declared=app(PortalAccountService::class)->identifier($account);
        if (!$accountReference || !$declared) return null;
        $primary=app(IdentifierRepositoryFactory::class)->make()->documentForPortalLinking($patient);
        try {
            $left=app(IdentificationService::class)->identify($declared['country'],$declared['type'],$declared['display'],'natural_person');
            if ($primary) { $right=$primary['document']; $patientReference=$primary['reference']; }
            else {
                if (DB::table('patient_identifiers')->where('patient_id',$patient->id)->exists()) return null;
                $right=app(IdentificationService::class)->identify('CL','national_id',$patient->rut,'natural_person');
                $patientReference='legacy:'.$patient->id;
            }
        } catch (InvalidIdentification) { return null; }
        if ($left->definition->namespace !== $right->definition->namespace || !hash_equals($left->number->normalized,$right->number->normalized)) return null;
        $context=['purpose'=>'portal-clinical-grant-v1','account'=>(int)$account->id,'patient'=>(int)$patient->id,
            'customer'=>(int)$patient->customer_id,'account_reference'=>$accountReference,'patient_reference'=>$patientReference,
            'document'=>[$left->definition->namespace,$left->number->normalized]];
        $crypto=new IdentifierCrypto(config('identification'));
        return ['account_reference'=>$accountReference,'patient_reference'=>$patientReference,
            'hashes'=>$crypto->hashes($context),'key_version'=>$crypto->lookupVersion];
    }

    private function pending(PortalAccount $account, Patient $patient): bool
    {
        return DB::table('portal_link_sync_requests')->whereNull('completed_at')->where(fn($q)=>$q
            ->where(fn($q)=>$q->where('subject_kind','account')->where('subject_id',$account->id))
            ->orWhere(fn($q)=>$q->where('subject_kind','patient')->where('subject_id',$patient->id)))->exists();
    }

    public function available(PortalAccount $account)
    {
        $account=$account->fresh();
        if (!$account || !$account->active || !$account->email_verified_at || !Schema::hasTable('portal_clinical_grants')) return collect();
        return PortalClinicalGrant::where('portal_account_id',$account->id)->whereNull('revoked_at')
            ->with(['patient','customer','link'])->orderByDesc('approved_at')->get()
            ->filter(fn($grant)=>$this->valid($grant,$account))->values();
    }

    private function valid(PortalClinicalGrant $grant, PortalAccount $account): bool
    {
        $patient=$grant->patient; $link=$grant->link;
        if ((int)$grant->portal_account_id !== (int)$account->id || $grant->revoked_at || !$patient || !$grant->customer
            || (int)$patient->customer_id !== (int)$grant->customer_id || !$link || $link->status !== 'candidate'
            || (int)$link->customer_id !== (int)$grant->customer_id || (int)$link->patient_id !== (int)$patient->id
            || (int)$link->portal_account_id !== (int)$account->id || $grant->authorization_version !== 'portal-clinical-v1'
            || $this->pending($account,$patient)) return false;
        $identity=$this->identity($account,$patient);
        return $identity && $identity['account_reference'] === $grant->account_reference
            && $identity['patient_reference'] === $grant->patient_reference
            && $link->account_reference === $grant->account_reference && $link->patient_reference === $grant->patient_reference
            && isset($identity['hashes'][$grant->lookup_key_version])
            && hash_equals($grant->identity_hash,$identity['hashes'][$grant->lookup_key_version]);
    }

    public function authorize(PortalAccount $account, string $id): PortalClinicalGrant
    {
        $account=$account->fresh();
        abort_unless($account && $account->active && $account->email_verified_at,403);
        $grant=PortalClinicalGrant::where('portal_account_id',$account->id)->whereNull('revoked_at')
            ->with(['patient','customer','link'])->findOrFail($id);
        abort_unless($this->valid($grant,$account),404);
        return $grant;
    }

    public function approve(User $actor, Patient $patient, array $data): PortalClinicalGrant
    {
        abort_unless(app(PortalClinicalAccessPolicy::class)->manage($actor,$patient),403);
        if (!Hash::check($data['password'],$actor->getAuthPassword())) throw ValidationException::withMessages(['password'=>'La contraseña no es correcta.']);
        return DB::transaction(function()use($actor,$patient,$data){
            Customer::whereKey($patient->customer_id)->lockForUpdate()->firstOrFail();
            $patient=Patient::whereKey($patient->id)->lockForUpdate()->firstOrFail();
            abort_unless(app(PortalClinicalAccessPolicy::class)->manage($actor->fresh(),$patient),403);
            $account=PortalAccount::where('email',mb_strtolower(trim($data['email'])))->lockForUpdate()->first();
            if (!$account || !$account->active || !$account->email_verified_at || $this->pending($account,$patient)) $this->unavailable();
            $identity=$this->identity($account,$patient);
            $link=DB::table('portal_patient_links')->where('customer_id',$patient->customer_id)->where('patient_id',$patient->id)
                ->where('portal_account_id',$account->id)->where('status','candidate')->lockForUpdate()->first();
            if (!$identity || !$link || $identity['account_reference'] !== $link->account_reference || $identity['patient_reference'] !== $link->patient_reference) $this->unavailable();
            if (PortalClinicalGrant::where('portal_account_id',$account->id)->where('patient_id',$patient->id)->whereNull('revoked_at')->exists()) {
                throw ValidationException::withMessages(['email'=>'Ya existe una habilitación activa para esta cuenta. Revócala antes de habilitarla nuevamente.']);
            }
            $grant=PortalClinicalGrant::create(['id'=>(string)Str::uuid(),'customer_id'=>$patient->customer_id,'patient_id'=>$patient->id,
                'portal_account_id'=>$account->id,'link_id'=>$link->id,'approved_by'=>$actor->id,
                'clinic_branch_id'=>ClinicBranchContext::defaultBranch($actor,(int)$patient->customer_id)->id,
                'account_reference'=>$identity['account_reference'],'patient_reference'=>$identity['patient_reference'],
                'lookup_key_version'=>$identity['key_version'],'identity_hash'=>$identity['hashes'][$identity['key_version']],
                'verification_method'=>$data['verification_method'],'authorization_version'=>'portal-clinical-v1','approved_at'=>now()]);
            $this->audit($grant,'granted',$actor->id);
            return $grant;
        },3);
    }

    private function unavailable(): never
    {
        throw ValidationException::withMessages(['email'=>'No se pudo habilitar el acceso. Confirma con el paciente su correo verificado y su identificación en Mi Salud. Si hay coincidencias pendientes o ambiguas, deben resolverse primero.']);
    }

    public function revoke(User $actor, Patient $patient, string $id): void
    {
        abort_unless(app(PortalClinicalAccessPolicy::class)->manage($actor,$patient),403);
        DB::transaction(function()use($actor,$patient,$id){
            $grant=PortalClinicalGrant::where('customer_id',$patient->customer_id)->where('patient_id',$patient->id)->lockForUpdate()->findOrFail($id);
            if ($grant->revoked_at) return;
            $grant->update(['revoked_at'=>now(),'revoked_by'=>$actor->id]);
            $this->audit($grant,'revoked',$actor->id);
        });
    }

    public function encounters(PortalClinicalGrant $grant)
    {
        return Encounter::where('customer_id',$grant->customer_id)->where('patient_id',$grant->patient_id)
            ->where('status',2)->whereHas('signature')->with('signature.documentVersion');
    }

    public function summary(PortalAccount $account, int $year, ?int $month = null): array
    {
        $rows=[];
        foreach ($this->available($account) as $center) {
            foreach ($this->encounters($center)->get() as $encounter) {
                abort_unless((int)$encounter->signature->documentVersion?->patient_id===(int)$center->patient_id,409);
                $snapshot=app(\App\Services\Signatures\EncounterSignatureVerifier::class)
                    ->verifyForCustomer($encounter->signature,(int)$center->customer_id,false);
                abort_unless((int)($snapshot['customer_id']??0)===(int)$center->customer_id
                    && (int)($snapshot['encounter_id']??0)===(int)$encounter->id,409);
                $rows[]=['date'=>$snapshot['started_at'],'clinic_id'=>$center->customer_id,'clinic'=>$center->customer->name,
                    'sort_at'=>\Carbon\Carbon::parse($snapshot['started_at'])->getTimestamp(),
                    'grant_id'=>$center->id,'encounter_id'=>$encounter->id,
                    'specialty'=>$snapshot['specialty']['name']??'Atención clínica',
                    'professional_name'=>$snapshot['professional']['name']??'Profesional',
                    'professional_id'=>$center->customer_id.':'.$encounter->professional_id,
                    'professional'=>($snapshot['professional']['name']??'Profesional').' · '.$center->customer->name];
            }
            $this->audit($center,'history_listed');
        }
        return \App\Support\PatientAttentionSummary::make($rows,$year,$month)+[
            'latest'=>collect($rows)->sortBy([['sort_at','desc'],['encounter_id','desc']])->take(3)->values()->all(),
        ];
    }

    public function audit(PortalClinicalGrant $grant, string $action, ?int $actor=null, ?int $encounter=null, ?int $document=null): void
    {
        DB::table('portal_clinical_events')->insert(['grant_id'=>$grant->id,'portal_account_id'=>$grant->portal_account_id,
            'actor_id'=>$actor,'action'=>$action,'encounter_id'=>$encounter,'document_id'=>$document,'created_at'=>now()]);
    }
}
