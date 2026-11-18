<?php

namespace App\Http\Controllers\MiSalud;

use App\Http\Controllers\Controller;
use App\Models\{Encounter, MedicalRecord, PatientDocument, PortalClinicalGrant};
use App\Services\MiSalud\PortalClinicalAccessService;
use App\Services\Signatures\{EncounterSignatureVerifier, SignedAttachmentStore};
use Illuminate\Support\Facades\Auth;

class ClinicalHistoryController extends Controller
{
    private function account() { return Auth::guard('mi_salud')->user(); }

    public function index(PortalClinicalAccessService $access)
    {
        $account=$this->account();
        $localPreview=\App\Http\Middleware\PortalAccess::localPreviewAllowed()
            && (int)session('portal_local_preview_account')===(int)$account->id;
        if (!$account->email_verified_at && !$localPreview) return redirect()->route('mi-salud.verify.notice');
        $centers=$access->available($account);
        foreach ($centers as $center) {
            $center->setAttribute('attention_count',$access->encounters($center)->count());
            $center->setAttribute('last_attention_at',$access->encounters($center)->max('started_at'));
            $access->audit($center,'history_listed');

        }
        return view('mi-salud.attentions.index',['page'=>'attentions','account'=>$account,'centers'=>$centers]);
    }

    public function record(string $grant, PortalClinicalAccessService $access)
    {
        $account=$this->account();$center=$access->authorize($account,$grant);
        $record=MedicalRecord::where('customer_id',$center->customer_id)->where('patient_id',$center->patient_id)
            ->with([
                'allergies'=>fn($q)=>$q->where('customer_id',$center->customer_id),
                'chronicDiseases'=>fn($q)=>$q->where('customer_id',$center->customer_id),
                'regularMedications'=>fn($q)=>$q->where('customer_id',$center->customer_id),
                'previousSurgeries'=>fn($q)=>$q->where('customer_id',$center->customer_id),
            ])->first();
        $encounters=$access->encounters($center)->orderByDesc('started_at')->orderByDesc('id')->paginate(12);
        $snapshots=[];
        foreach ($encounters as $encounter) $snapshots[$encounter->id]=$this->snapshot($encounter,$center);
        $access->audit($center,'record_viewed');
        return view('mi-salud.attentions.record',compact('account','center','record','encounters','snapshots')+['page'=>'attentions']);
    }

    private function snapshot(Encounter $encounter, PortalClinicalGrant $center): array
    {
        abort_unless($encounter->signature && (int)$encounter->signature->documentVersion?->patient_id === (int)$center->patient_id,409);
        $snapshot=app(EncounterSignatureVerifier::class)->verifyForCustomer($encounter->signature,(int)$center->customer_id,false);
        abort_unless((int)($snapshot['customer_id']??0)===(int)$center->customer_id
            && (int)($snapshot['encounter_id']??0)===(int)$encounter->id,409);
        return $snapshot;
    }

    public function encounter(string $grant, int $encounter, PortalClinicalAccessService $access)
    {
        $account=$this->account();$center=$access->authorize($account,$grant);
        $attention=$access->encounters($center)->findOrFail($encounter);
        $snapshot=$this->snapshot($attention,$center);
        $addenda=[];
        foreach ($attention->addendumSignatures()->with('documentVersion')->get() as $signature) {
            $addenda[]=['snapshot'=>app(EncounterSignatureVerifier::class)->verifyForCustomer($signature,(int)$center->customer_id,false),
                'signature'=>$signature];
        }
        $access->audit($center,'encounter_viewed',null,$attention->id);
        return view('mi-salud.attentions.encounter',compact('account','center','attention','snapshot','addenda')+['page'=>'attentions']);
    }

    public function download(string $grant, int $encounter, int $document, PortalClinicalAccessService $access)
    {
        $center=$access->authorize($this->account(),$grant);
        $attention=$access->encounters($center)->findOrFail($encounter);
        $snapshot=$this->snapshot($attention,$center);
        abort_unless(collect($snapshot['attachments']??[])->contains(fn($entry)=>(int)$entry['id']===$document),404);
        $file=PatientDocument::where('customer_id',$center->customer_id)->where('patient_id',$center->patient_id)
            ->where('encounter_id',$attention->id)->findOrFail($document);
        $file->setRelation('encounter',$attention);
        $response=app(SignedAttachmentStore::class)->responseForCustomerDocument($file,(int)$center->customer_id);
        abort_unless($response,404);
        $access->audit($center,'document_downloaded',null,$attention->id,$file->id);
        return $response;
    }
}
