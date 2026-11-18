<?php

namespace App\Services\MiSalud;

use App\Models\PortalAccount;
use App\Mail\PortalSecurityLink;
use App\Services\Identification\{IdentificationService, InvalidIdentification};
use App\Services\Identification\Storage\IdentifierCrypto;
use Illuminate\Support\Facades\{DB, Hash, Mail};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PortalAccountService
{
    public function audit(?int $account, string $event): void
    {
        DB::table('portal_audit_events')->insert(['portal_account_id'=>$account,'event'=>$event,'created_at'=>now()]);
    }

    public function document(array $data): \App\Services\Identification\DocumentIdentifier
    {
        try {
            return app(IdentificationService::class)->identify($data['identification_country'], $data['identification_type'], $data['identification_number'], 'natural_person');
        } catch (InvalidIdentification $error) {
            throw ValidationException::withMessages(['identification_number'=>$error->getMessage()]);
        }
    }

    private function crypto(): IdentifierCrypto { return new IdentifierCrypto(config('identification')); }
    private function binding(int $account, string $record): array { return ['purpose'=>'mi-salud-declared-identifier','account'=>$account,'record'=>$record]; }

    private function storeDocument(PortalAccount $account, $document): void
    {
        $crypto=$this->crypto(); $record=(string)Str::uuid();
        $context=['kind'=>'portal_account','scope'=>'mi-salud','owner'=>$account->id,'country'=>$document->definition->country,
            'namespace'=>$document->definition->namespace,'normalized'=>$document->number->normalized];
        $hashes=$crypto->hashes($context);
        DB::table('portal_account_identifiers')->where('portal_account_id',$account->id)->whereNull('retired_at')->update(['retired_at'=>now(),'updated_at'=>now()]);
        DB::table('portal_account_identifiers')->insert([
            'id'=>$record,'portal_account_id'=>$account->id,'issuing_country_code'=>$document->definition->country,
            'document_type'=>$document->definition->type->value,'identifier_namespace'=>$document->definition->namespace,
            'encrypted_payload'=>$crypto->encrypt(['original'=>$document->original,'normalized'=>$document->number->normalized,'display'=>$document->number->display],$this->binding($account->id,$record)),
            'lookup_hash'=>$hashes[$crypto->lookupVersion],'lookup_key_version'=>$crypto->lookupVersion,
            'normalization_version'=>$document->number->ruleVersion,'validation_status'=>$document->number->level->value,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        app(PortalPatientLinkService::class)->request('account',(int)$account->id);
    }

    public function identifier(PortalAccount $account): ?array
    {
        $row=DB::table('portal_account_identifiers')->where('portal_account_id',$account->id)->whereNull('retired_at')->first();
        if (!$row) return null;
        $payload=$this->crypto()->decrypt($row->encrypted_payload,$this->binding($account->id,$row->id));
        return ['country'=>$row->issuing_country_code,'type'=>$row->document_type,'display'=>$payload['display'],'identity_verified'=>false];
    }

    public function register(array $data): void
    {
        $document=$this->document($data); $this->crypto();
        $password=Hash::make($data['password']);
        try {
            $account=DB::transaction(function() use($data,$document,$password) {
                if (PortalAccount::where('email',$data['email'])->exists()) return null;
                $account=PortalAccount::create(['name'=>$data['name'],'email'=>$data['email'],'password'=>$password]);
                $this->storeDocument($account,$document); $this->audit($account->id,'account_registered');
                return $account;
            },3);
        } catch (\Illuminate\Database\QueryException $error) {
            if (($error->errorInfo[1]??null) === 1062) return; // Concurrent same-email registration: neutral response.
            throw new \RuntimeException('No se pudo guardar la cuenta de Mi Salud.');
        }
        if ($account) $this->sendLink($account,'verify');
    }

    public function sendLink(PortalAccount $account, string $purpose): void
    {
        if (!in_array($purpose,['verify','reset'],true)) throw new \InvalidArgumentException('Invalid portal purpose.');
        $bearer=bin2hex(random_bytes(32)); $id=(string)Str::uuid();
        DB::transaction(function() use($account,$purpose,$bearer,$id) {
            $owner=PortalAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            if (!$owner->active || ($purpose==='verify' && $owner->email_verified_at)) return;
            DB::table('portal_auth_tokens')->where('portal_account_id',$owner->id)->where('purpose',$purpose)->whereNull('consumed_at')->update(['consumed_at'=>now(),'updated_at'=>now()]);
            DB::table('portal_auth_tokens')->insert(['id'=>$id,'portal_account_id'=>$owner->id,'purpose'=>$purpose,'token_hash'=>hash('sha256',$bearer),
                'expires_at'=>now()->addMinutes(30),'created_at'=>now(),'updated_at'=>now()]);
            $this->audit($owner->id,$purpose.'_requested');
            DB::afterCommit(function() use($owner,$purpose,$bearer,$id) {
                try {
                    $transport=config('mail.mailers.'.config('mail.default').'.transport');
                    if (!app()->environment('testing') && !in_array($transport,['smtp','ses','postmark','mailgun','sendmail'],true)) throw new \RuntimeException;
                    $url=route('mi-salud.token',['purpose'=>$purpose]).'#'.$bearer;
                    Mail::to($owner->email)->send(new PortalSecurityLink($url,$purpose));
                } catch (\Throwable) {
                    DB::table('portal_auth_tokens')->where('id',$id)->whereNull('consumed_at')->update(['consumed_at'=>now(),'updated_at'=>now()]);
                    $this->audit($owner->id,'security_mail_delivery_failed');
                }
            });
        },3);
    }

    public function consume(string $purpose, string $bearer, ?string $password=null): bool
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/D',$bearer)) return false;
        $candidate=DB::table('portal_auth_tokens')->where('token_hash',hash('sha256',$bearer))->where('purpose',$purpose)->first();
        if (!$candidate) return false;
        return DB::transaction(function() use($candidate,$purpose,$password) {
            $account=PortalAccount::whereKey($candidate->portal_account_id)->lockForUpdate()->firstOrFail();
            $token=DB::table('portal_auth_tokens')->where('id',$candidate->id)->lockForUpdate()->first();
            if (!$account->active || !$token || $token->consumed_at || now()->gte($token->expires_at)) return false;
            if ($purpose==='verify') {
                $account->email_verified_at ??= now();
            } else {
                if (!$password) return false;
                $account->password=Hash::make($password); $account->session_version++;
            }
            $account->save();
            DB::table('portal_auth_tokens')->where('portal_account_id',$account->id)->where('purpose',$purpose)->whereNull('consumed_at')->update(['consumed_at'=>now(),'updated_at'=>now()]);
            $this->audit($account->id,$purpose==='verify'?'email_verified':'password_reset');
            return true;
        },3);
    }

    public function profile(PortalAccount $account, array $data): void
    {
        $document=filled($data['identification_number']??null) ? $this->document($data) : null;
        try { DB::transaction(function() use($account,$data,$document) {
            $owner=PortalAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            if (!Hash::check($data['current_password'],$owner->password)) throw ValidationException::withMessages(['current_password'=>'La contraseña actual no es correcta.']);
            $owner->name=$data['name'];
            if (filled($data['password']??null)) { $owner->password=Hash::make($data['password']); $owner->session_version++; }
            $owner->save();
            if (isset($data['sharing_settings_present'])) app(PortalProfileSharingService::class)->save($owner,$data);
            if ($document) {
                // Equivalent input keeps the current reference; retired document reservations stay historical.
                $current=$this->identifier($owner);
                if (!$current || $current['country']!==$document->definition->country || $current['type']!==$document->definition->type->value || $current['display']!==$document->number->display) {
                    $this->storeDocument($owner,$document); $this->audit($owner->id,'declared_identifier_changed');
                }
            }
            $this->audit($owner->id,filled($data['password']??null)?'password_changed':'profile_updated');
        },3); } catch (\Illuminate\Database\QueryException) { throw new \RuntimeException('No se pudo actualizar el perfil.'); }
    }
}
