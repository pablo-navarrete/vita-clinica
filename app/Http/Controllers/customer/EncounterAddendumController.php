<?php
namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\Encounter;
use App\Services\Signatures\{EncounterAddendumPolicy, EncounterAddendumService, EncounterAddendumSnapshotBuilder, EncounterSignatureVerifier};
use Illuminate\Http\Request;

class EncounterAddendumController extends Controller
{
    public function create(Request $request, string $customer, int $id, int $encounter)
    {
        $record = Encounter::where('customer_id', app('customer')->id)->where('patient_id', $id)->with('signature.documentVersion', 'addendumSignatures.documentVersion')->findOrFail($encounter);
        app(EncounterAddendumPolicy::class)->authorize($request->user(), $record);
        $signatures = collect([$record->signature])->concat($record->addendumSignatures);
        app(EncounterSignatureVerifier::class)->verifyMany($signatures, true);
        $targets = $signatures->map(fn ($signature) => [
            'id' => $signature->document_version_id, 'version' => $signature->documentVersion->version,
            'kind' => $signature->documentVersion->kind,
            'fields' => $signature->documentVersion->kind === 'original' ? ['chief_complaint', 'clinical_assessment', 'treatment_plan'] : ['content'],
        ])->sortBy('version')->values();
        $data = ['encounter' => $record, 'targets' => $targets,
            'types' => EncounterAddendumSnapshotBuilder::TYPES, 'fields' => EncounterAddendumSnapshotBuilder::FIELDS];
        if ($request->expectsJson()) {
            return response()->json(['html' => view('customer.patient.record-workspace.addendum-modal', $data)->render()])
                ->header('Cache-Control', 'private, no-store');
        }
        return view('customer.patient.addendum', $data);
    }

    public function prepare(Request $request, string $customer, int $id, int $encounter, EncounterAddendumService $service)
    {
        $operation = $service->prepare($request->user(), $id, $encounter, $request->only('type', 'content', 'rectifies_version_id', 'target_field', 'reason'));
        return response()->json(['operation_id' => $operation->id, 'hash' => $operation->content_hash,
            'html' => view('customer.patient.record-workspace.addendum-preview', ['snapshot' => json_decode($operation->payload, true)])->render(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, string $customer, int $id, int $encounter, EncounterAddendumService $service)
    {
        $data = $request->validate(['operation_id' => 'required|uuid', 'password' => 'required|string|max:1024', 'acceptance' => 'required|accepted']);
        $service->sign($request->user(), $id, $encounter, $data['operation_id'], $data['password'], $request->boolean('acceptance'));
        $request->session()->flash('success', 'La adenda quedó guardada y firmada.');
        return response()->json(['redirect' => route('patients.medical-record.show', [
            'customer' => $customer, 'id' => $id, 'record_tab' => 'history', 'history_encounter_id' => $encounter,
        ])])->header('Cache-Control', 'private, no-store');
    }
}
