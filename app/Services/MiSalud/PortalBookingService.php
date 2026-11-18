<?php
namespace App\Services\MiSalud;

use App\Models\{Customer, PortalAccount};
use App\Services\Identification\Storage\IdentifierCrypto;
use App\Services\Patients\PatientRegistrationService;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;

/** Server-only bridge. A booking authorization never grants access to clinical records. */
final class PortalBookingService
{
    private function crypto(): IdentifierCrypto { return new IdentifierCrypto(config('identification')); }
    private function binding(object $row): array { return ['purpose'=>'portal-booking-v1','id'=>$row->id,'customer'=>(int)$row->customer_id]; }
    public function payload(object $row): array { return $this->crypto()->decrypt($row->encrypted_payload, $this->binding($row)); }
    public function begin(int $customer, string $session, array $selection): string
    {
        app(PatientRegistrationService::class)->enabled('public_booking');
        abort_unless(Schema::hasTable('portal_booking_authorizations'), 503);
        $row=(object)['id'=>(string)Str::uuid(),'customer_id'=>$customer];
        // No personal information or caller-provided destinations enter the intent.
        $selection=array_intersect_key($selection,array_flip(['specialty_id','professional_id','clinic_branch_id','date','time','modality','reason','whatsapp_consent']));
        DB::table('portal_booking_authorizations')->insert((array)$row+['session_hash'=>hash('sha256',$session),
            'encrypted_payload'=>$this->crypto()->encrypt(['selection'=>$selection],$this->binding($row)),
            'expires_at'=>now()->addMinutes(15),'created_at'=>now(),'updated_at'=>now()]);
        return $row->id;
    }
    public function intent(string $id): object
    {
        app(PatientRegistrationService::class)->enabled('public_booking');
        $row=DB::table('portal_booking_authorizations')->where('id',$id)->first();
        abort_unless($row && now()->lt($row->expires_at),410);
        abort_unless(Customer::whereKey($row->customer_id)->where('status',1)->exists(),410);
        return $row;
    }
    private function personal(PortalAccount $account): array
    {
        abort_unless($account->active && $account->email_verified_at,403);
        $identity=app(PortalAccountService::class)->identifier($account);
        abort_unless($identity,403);
        return array_intersect_key(app(PortalProfileSharingService::class)->profile($account),array_flip(['gender','birth_date','phone','address']))+
            ['name'=>$account->name,'email'=>$account->email,'identification_country'=>$identity['country'],
                'identification_type'=>$identity['type'],'identification_number'=>$identity['display']];
    }
    private function fingerprint(PortalAccount $account, array $personal): string
    {
        $reference=DB::table('portal_account_identifiers')->where('portal_account_id',$account->id)->whereNull('retired_at')->value('id');
        return hash('sha256',json_encode([$reference,$account->session_version,$personal],JSON_THROW_ON_ERROR));
    }
    public function authorize(string $id, PortalAccount $account): string
    {
        return DB::transaction(function()use($id,$account){
            $account=PortalAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $row=DB::table('portal_booking_authorizations')->where('id',$id)->lockForUpdate()->first();
            $this->intent($id); abort_unless(!$row->authorized_at,410);
            $code=bin2hex(random_bytes(32));
            $personal=$this->personal($account);$payload=$this->payload($row);$payload['personal']=$personal;
            // The code stays encrypted on the server; the browser carries only a non-bearer intent ID.
            $payload['authorization_code']=$code;
            DB::table('portal_booking_authorizations')->where('id',$id)->update(['portal_account_id'=>$account->id,
                'code_hash'=>hash('sha256',$code),'identity_hash'=>$this->fingerprint($account,$personal),
                'encrypted_payload'=>$this->crypto()->encrypt($payload,$this->binding($row)),
                'authorized_at'=>now(),'expires_at'=>now()->addMinutes(5),'updated_at'=>now()]);
            app(PortalAccountService::class)->audit($account->id,'booking_authorized_v1');
            return $code;
        },3);
    }
    public function redeem(string $id,string $session,string $code,int $customer): array
    {
        return DB::transaction(function()use($id,$session,$code,$customer){
            $row=DB::table('portal_booking_authorizations')->where('id',$id)->lockForUpdate()->first();
            $this->intent($id);
            abort_unless((int)$row->customer_id===$customer && !$row->redeemed_at && $row->authorized_at
                && hash_equals($row->session_hash,hash('sha256',$session)) && preg_match('/\A[a-f0-9]{64}\z/',$code)
                && hash_equals((string)$row->code_hash,hash('sha256',$code)),403);
            $payload=$this->current($row);
            unset($payload['authorization_code']);
            DB::table('portal_booking_authorizations')->where('id',$id)->update(['redeemed_at'=>now(),'code_hash'=>null,
                'encrypted_payload'=>$this->crypto()->encrypt($payload,$this->binding($row)),
                'expires_at'=>now()->addMinutes(15),'updated_at'=>now()]);
            return $payload;
        },3);
    }
    public function redeemForSession(string $id,string $session,int $customer): array
    {
        $row=$this->intent($id);
        abort_unless((int)$row->customer_id===$customer && hash_equals($row->session_hash,hash('sha256',$session)),403);
        $code=$this->payload($row)['authorization_code']??'';
        return $this->redeem($id,$session,$code,$customer);
    }
    private function current(object $row): array
    {
        $account=PortalAccount::findOrFail($row->portal_account_id);$personal=$this->personal($account);
        abort_unless(hash_equals((string)$row->identity_hash,$this->fingerprint($account,$personal)),409);
        return $this->payload($row);
    }
    public function grant(string $id,string $session,int $customer): array
    {
        $row=$this->intent($id);
        abort_unless((int)$row->customer_id===$customer && $row->redeemed_at && !$row->completed_at
            && hash_equals($row->session_hash,hash('sha256',$session)),403);
        $payload=$this->current($row);
        $document=app(PatientRegistrationService::class)->document($payload['personal']);
        $hashes=app(PortalPatientLinkService::class)->documentHashes($document);
        $accounts=DB::table('portal_document_lookup_keys')->whereNotNull('portal_account_id')->where(function($q)use($hashes){
            foreach($hashes as $version=>$hash)$q->orWhere(fn($x)=>$x->where('key_version',$version)->where('document_hash',$hash));
        })->distinct()->pluck('portal_account_id');
        abort_unless($accounts->count()===1 && (int)$accounts->first()===(int)$row->portal_account_id
            && !DB::table('portal_link_sync_requests')->where('subject_kind','account')->whereNull('completed_at')->exists(),409);
        return $payload;
    }
    public function callback(object $row): string
    {
        $customer=Customer::findOrFail($row->customer_id);
        // Destination derives exclusively from the configured tenant, never from browser input.
        return route('customer.public-booking.portal.return',['customer'=>$customer->domain,'intent'=>$row->id]);
    }
    public function completed(string $id,int $patient): void
    {
        $row=DB::table('portal_booking_authorizations')->where('id',$id)->lockForUpdate()->first();
        abort_unless($row && $row->redeemed_at && !$row->completed_at && DB::transactionLevel()>0,403);
        abort_unless(DB::table('patients')->where('id',$patient)->where('customer_id',$row->customer_id)->where('status',1)->exists(),403);
        $payload=$this->payload($row);
        // Keep only the encrypted document evidence needed for later booking reuse.
        $payload=['personal'=>array_intersect_key($payload['personal'],array_flip(['identification_country','identification_type','identification_number']))];
        DB::table('portal_booking_authorizations')->where('id',$id)->update(['patient_id'=>$patient,'completed_at'=>now(),
            'encrypted_payload'=>$this->crypto()->encrypt($payload,$this->binding($row)),'updated_at'=>now()]);
    }
    public function purgeExpired(): int
    {
        if (!Schema::hasTable('portal_booking_authorizations')) return 0;
        return DB::table('portal_booking_authorizations')->whereNull('completed_at')
            ->where('expires_at','<',now()->subDays(config('patient_registration.pending_retention_days')))->delete();
    }
}
