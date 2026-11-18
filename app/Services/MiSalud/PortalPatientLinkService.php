<?php

namespace App\Services\MiSalud;

use App\Models\{Patient, PortalAccount};
use App\Services\Identification\{IdentificationService, InvalidIdentification};
use App\Services\Identification\Storage\{IdentifierCrypto, IdentifierRepositoryFactory};
use Illuminate\Support\Facades\{DB, Schema};

/** Internal reconciliation only. No discovery endpoint and no clinical capability. */
final class PortalPatientLinkService
{
    public function documentHashes(\App\Services\Identification\DocumentIdentifier $document): array
    {
        return (new IdentifierCrypto(config('identification')))->hashes(['purpose'=>'portal-patient-link-v1',
            'country'=>$document->definition->country,'namespace'=>$document->definition->namespace,
            'holder'=>$document->definition->holderKind->value,'normalized'=>$document->number->normalized]);
    }
    public function request(string $kind, int $id): void
    {
        if (!in_array($kind,['account','patient'],true)) throw new \InvalidArgumentException('Invalid link subject.');
        if (!Schema::hasTable('portal_link_sync_requests')) return; // Rolling deployment: old schema stays usable.
        $request = DB::table('portal_link_sync_requests')->insertGetId(['subject_kind'=>$kind,'subject_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);
        DB::afterCommit(function () use ($request) {
            try { $this->process($request); }
            catch (\Throwable) {
                // No exception text: database/crypto exceptions can contain personal data.
                \Illuminate\Support\Facades\Log::warning('Mi Salud link reconciliation pending.', ['request_id'=>$request]);
            }
        });
    }

    public function drain(int $limit = 100): int
    {
        $count=0;
        foreach (DB::table('portal_link_sync_requests')->whereNull('completed_at')->orderBy('id')->limit($limit)->pluck('id') as $id) {
            $this->process($id); $count++;
        }
        return $count;
    }

    public function process(int $request): void
    {
        DB::transaction(function () use ($request) {
            // Consistent integration-only serialization; source writers never wait for this mutex.
            if (!DB::table('portal_link_mutex')->where('id',1)->lockForUpdate()->first()) throw new \RuntimeException('Link mutex missing.');
            $work=DB::table('portal_link_sync_requests')->where('id',$request)->lockForUpdate()->first();
            if (!$work) throw new \RuntimeException('Link request missing.');
            if ($work->completed_at) return;
            $column=$work->subject_kind==='account'?'portal_account_id':'patient_id';
            DB::table('portal_document_lookup_keys')->where($column,$work->subject_id)->delete();
            $identity=$this->identity($work->subject_kind,(int)$work->subject_id);
            if ($identity) {
                [$document,$reference,$customer]=$identity;
                $hashes=$this->documentHashes($document);
                foreach ($hashes as $version=>$hash) DB::table('portal_document_lookup_keys')->insert([
                    $column=>$work->subject_id,'customer_id'=>$customer,'source_reference'=>$reference,'key_version'=>$version,
                    'document_hash'=>$hash,'created_at'=>now(),'updated_at'=>now()]);
            }
            $this->reconcile();
            DB::table('portal_link_sync_requests')->where('id',$request)->update(['completed_at'=>now(),'updated_at'=>now()]);
        },3);
    }

    private function identity(string $kind, int $id): ?array
    {
        if ($kind==='account') {
            $owner=PortalAccount::find($id);
            if (!$owner || !$owner->active) return null;
            $row=DB::table('portal_account_identifiers')->where('portal_account_id',$id)->whereNull('retired_at')->first();
            if (!$row) return null;
            $payload=(new IdentifierCrypto(config('identification')))->decrypt($row->encrypted_payload,
                ['purpose'=>'mi-salud-declared-identifier','account'=>$id,'record'=>$row->id]);
            $country=$row->issuing_country_code; $type=$row->document_type; $number=$payload['original'];
            $reference=$row->id; $customer=null;
        } else {
            $owner=Patient::withoutGlobalScopes()->find($id);
            if (!$owner || (int)$owner->status!==1 || $owner->deleted_at) return null;
            $document=app(IdentifierRepositoryFactory::class)->make()->documentForPortalLinking($owner);
            if ($document) return [$document['document'],$document['reference'],(int)$owner->customer_id];
            if (DB::table('patient_identifiers')->where('patient_id',$id)->exists()) return null;
            $country='CL'; $type='national_id'; $number=$owner->rut;
            $reference='legacy:'.$id; $customer=(int)$owner->customer_id;
        }
        try { $document=app(IdentificationService::class)->identify($country,$type,$number,'natural_person'); }
        catch (InvalidIdentification) { return null; }
        return [$document,$reference,$customer];
    }

    private function reconcile(): void
    {
        $matches=DB::table('portal_document_lookup_keys as a')->join('portal_document_lookup_keys as p',function($join) {
            $join->on('a.key_version','=','p.key_version')->on('a.document_hash','=','p.document_hash');
        })->whereNotNull('a.portal_account_id')->whereNotNull('p.patient_id')
            ->select(['a.portal_account_id','p.patient_id','p.customer_id','a.source_reference as account_reference','p.source_reference as patient_reference'])->distinct()->get();
        $accountCounts=$matches->groupBy('patient_id')->map(fn($rows)=>$rows->pluck('portal_account_id')->unique()->count());
        $patientCounts=$matches->groupBy(fn($row)=>$row->portal_account_id.':'.$row->customer_id)->map(fn($rows)=>$rows->pluck('patient_id')->unique()->count());
        $seen=[];
        foreach ($matches as $match) {
            $key=$match->portal_account_id.':'.$match->patient_id; $seen[$key]=true;
            $state=$accountCounts[$match->patient_id]===1 && $patientCounts[$match->portal_account_id.':'.$match->customer_id]===1?'candidate':'review_required';
            $this->transition((array)$match,$state);
        }
        foreach (DB::table('portal_patient_links')->where('status','<>','revoked')->get() as $link) {
            if (!isset($seen[$link->portal_account_id.':'.$link->patient_id])) $this->transition((array)$link,'revoked');
        }
    }

    private function transition(array $match, string $state): void
    {
        $pair=['portal_account_id'=>$match['portal_account_id'],'patient_id'=>$match['patient_id']];
        $existing=DB::table('portal_patient_links')->where($pair)->first();
        if ($existing && $existing->status===$state && $existing->account_reference===$match['account_reference'] && $existing->patient_reference===$match['patient_reference']) return;
        $values=['customer_id'=>$match['customer_id'],'status'=>$state,'account_reference'=>$match['account_reference'],
            'patient_reference'=>$match['patient_reference'],'updated_at'=>now()];
        if ($existing) { $id=$existing->id; DB::table('portal_patient_links')->where('id',$id)->update($values); }
        else $id=DB::table('portal_patient_links')->insertGetId($pair+$values+['created_at'=>now()]);
        DB::table('portal_patient_link_events')->insert(['link_id'=>$id,'event'=>$existing?'state_changed':'matched',
            'previous_status'=>$existing?->status,'status'=>$state,'account_reference'=>$match['account_reference'],
            'patient_reference'=>$match['patient_reference'],'created_at'=>now()]);
    }
}
