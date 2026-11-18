<?php
namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Services\Signatures\EncounterSigningService;
use Illuminate\Http\Request;

class EncounterSigningController extends Controller
{
    public function prepare(Request $request, string $customer, int $id, int $encounter, EncounterSigningService $service)
    {
        $request->merge(['clinical_assessment' => trim((string) $request->input('clinical_assessment', ''))]);
        $data = $request->validate(['clinical_assessment' => 'required|string|max:20000', 'treatment_plan' => 'nullable|string|max:20000']);
        $op = $service->prepare($request->user(), $id, $encounter, $data);
        return response()->json(['operation_id' => $op->id, 'hash' => $op->content_hash,
            'html' => view('customer.patient.record-workspace.signature-preview', [
                'snapshot' => json_decode($op->payload, true), 'acceptance' => EncounterSigningService::ACCEPTANCE,
            ])->render()])->header('Cache-Control', 'private, no-store');
    }
}
