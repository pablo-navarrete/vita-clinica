<?php
namespace App\Services\MiSalud;

use App\Models\{PortalAccount,User};
use App\Services\Identification\Storage\IdentifierCrypto;
use App\Services\Patients\{PatientRegistrationService,PatientIdentityResolver};
use Illuminate\Support\Facades\{DB,RateLimiter};

final class PortalProfileSharingService
{
    private function binding(int $id): array {return ['purpose'=>'portal-basic-profile-v1','account'=>$id];}
    public function save(PortalAccount $account,array $data): void
    {
        $crypto=new IdentifierCrypto(config('identification'));
        $enabled=(bool)($data['share_basic_profile']??false);
        $fields=array_intersect_key($data,array_flip(['gender','birth_date','phone','address']));
        DB::table('portal_profile_sharing')->updateOrInsert(['portal_account_id'=>$account->id],[
            'encrypted_profile'=>$crypto->encrypt($fields,$this->binding($account->id)),'enabled'=>$enabled,
            'consent_version'=>$enabled?'basic-profile-v1':null,'accepted_at'=>$enabled?now():null,'updated_at'=>now(),
        ]);
        app(PortalAccountService::class)->audit($account->id,$enabled?'basic_sharing_v1_enabled':'basic_sharing_v1_disabled');
    }
    public function profile(PortalAccount $account): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('portal_profile_sharing')) return [];
        $row=DB::table('portal_profile_sharing')->where('portal_account_id',$account->id)->first();
        return $row? (new IdentifierCrypto(config('identification')))->decrypt($row->encrypted_profile,$this->binding($account->id))+['share_basic_profile'=>(bool)$row->enabled]:[];
    }
    public function lookup(User $actor,array $data): ?array
    {
        $registration=app(PatientRegistrationService::class);$branch=(int)session('active_branch_id');
        $registration->authorize($actor,$branch,'patients.create');
        $document=$registration->document($data);
        if($document->definition->namespace!=='CL:RUT') return null;
        $customer=(int)app('customer')->id;
        $clinicLimit='portal-profile-clinic:'.$customer;
        abort_if(RateLimiter::tooManyAttempts($clinicLimit,100),429);RateLimiter::hit($clinicLimit,60);
        $key='portal-profile-lookup:'.$customer.':'.$actor->id;
        abort_if(RateLimiter::tooManyAttempts($key,20),429);RateLimiter::hit($key,60);
        $hashes=app(PortalPatientLinkService::class)->documentHashes($document);
        $ids=DB::table('portal_document_lookup_keys')->whereNotNull('portal_account_id')->where(function($q)use($hashes){
            foreach($hashes as $version=>$hash)$q->orWhere(fn($x)=>$x->where('key_version',$version)->where('document_hash',$hash));
        })->distinct()->pluck('portal_account_id');
        $accounts=PortalAccount::whereIn('id',$ids)->where('active',true)->get();$result=null;
        $indexReady=!DB::table('portal_link_sync_requests')->where('subject_kind','account')->whereNull('completed_at')->exists();
        if($indexReady && $accounts->count()===1 && app(PatientIdentityResolver::class)->resolve($customer,$document)['state']==='new') {
            $account=$accounts->first();$sharing=DB::table('portal_profile_sharing')->where('portal_account_id',$account->id)->first();
            $identity=app(PortalAccountService::class)->identifier($account);
            if($sharing?->enabled && $sharing->accepted_at && $sharing->consent_version==='basic-profile-v1' && $account->email_verified_at
                && $identity && $identity['country']==='CL' && $identity['type']==='national_id' && $identity['display']===$document->number->display) {
                $result=array_intersect_key($this->profile($account),array_flip(['gender','birth_date','phone','address']))+
                    ['name'=>$account->name,'email'=>$account->email,'document'=>$identity['display']];
            }
        }
        DB::table('portal_profile_lookup_events')->insert(['customer_id'=>$customer,'user_id'=>$actor->id,'clinic_branch_id'=>$branch,
            'result'=>$result?'available':'unavailable','created_at'=>now()]);
        return $result;
    }
}
