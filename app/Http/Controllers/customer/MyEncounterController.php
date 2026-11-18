<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\Encounter;
use App\Models\Appointment;
use App\Support\ClinicBranchContext;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class MyEncounterController extends Controller
{
    public function index(Request $request)
    {
        $customerId = (int) app('customer')->id;
        $user = $request->user();
        $isSuperAdmin = ClinicBranchContext::isSuperAdministrator($user);
        $branch = ClinicBranchContext::defaultBranch($user, $customerId);
        $isActive = (int) $user->status === 1 || ($isSuperAdmin && $user->status === null);
        abort_unless($isActive && ($isSuperAdmin || ((int) $user->customer_id === $customerId
            && $branch && ($user->hasClinicRole('Doctor', $customerId, (int) $branch->id)
                || $user->hasClinicRole('Administrador', $customerId, (int) $branch->id)))), 403);

        $allBranches = $isSuperAdmin || (ClinicBranchContext::isHeadquartersActive($customerId)
            && ClinicBranchContext::canAdministerAllBranches($user, $customerId));
        $filterBranches = $allBranches ? ClinicBranchContext::availableBranches($user, $customerId) : collect([$branch]);
        $branchIds = $filterBranches->pluck('id');

        if (!$request->ajax()) {
            return view('customer.encounters.mine', ['branch' => $branch, 'allBranches' => $allBranches, 'filterBranches' => $filterBranches, 'isSuperAdmin' => $isSuperAdmin]);
        }

        $request->validate([
            'status' => ['nullable', 'in:1,2,3,scheduled'],
            'branch_id' => ['nullable', 'integer'],
        ]);
        if ($request->filled('branch_id')) {
            abort_unless($allBranches && $branchIds->contains($request->integer('branch_id')), 403);
            $branchIds = collect([$request->integer('branch_id')]);
        }

        if ($request->input('status') === 'scheduled') {
            $query = Appointment::withoutGlobalScope('active_clinic_branch')->where('appointments.customer_id', $customerId)
                ->whereIn('appointments.clinic_branch_id', $branchIds)->when(!$isSuperAdmin, fn ($query) => $query->where('professional_id', $user->id))
                ->whereIn('status', [0, 1])->whereDoesntHave('encounter')
                ->with(['patient:id,customer_id,clinic_branch_id,name,rut', 'specialty:id,name', 'clinicBranch:id,name']);

            return DataTables::eloquent($query)
                ->addColumn('is_scheduled', fn () => true)
                ->editColumn('patient', fn ($row) => $row->patient ? app(\App\Services\Identification\PatientIdentification::class)->decorate($row->patient)->toArray() : null)
                ->addColumn('status_label', fn ($appointment) => (int) $appointment->status === 1 ? 'Confirmada' : 'Agendada')
                ->addColumn('modality_label', fn ($appointment) => $appointment->modality === 'telemedicine' ? 'Telemedicina' : 'Presencial')
                ->addColumn('started_display', fn ($appointment) => $appointment->start_at?->timezone('America/Santiago')->format('d/m/Y H:i') ?? 'Sin fecha')
                ->addColumn('record_url', fn ($appointment) => route('patients.medical-record.show', [
                    'customer' => $request->route('customer'), 'id' => $appointment->patient_id,
                    'start_encounter' => $isSuperAdmin ? null : $appointment->id,
                ]) . '#current-encounter-section')
                ->toJson();
        }

        $query = Encounter::query()->where('encounters.customer_id', $customerId)
            ->when($request->filled('status'), fn ($query) => $query->where('encounters.status', $request->integer('status')))
            ->whereIn('encounters.clinic_branch_id', $branchIds)
            ->when(!$isSuperAdmin, fn ($query) => $query->where(function ($query) use ($user, $customerId) {
                $query->where('professional_id', $user->id)->orWhere(function ($legacy) use ($user, $customerId) {
                    $legacy->whereNull('professional_id')->whereHas('appointment', fn ($appointment) => $appointment
                        ->withoutGlobalScope('active_clinic_branch')->where('customer_id', $customerId)->where('professional_id', $user->id));
                });
            }))
            ->with(['patient:id,customer_id,clinic_branch_id,name,rut', 'specialty:id,name', 'clinicBranch:id,name', 'signature']);

        return DataTables::eloquent($query)
            ->editColumn('patient', fn ($row) => $row->patient ? app(\App\Services\Identification\PatientIdentification::class)->decorate($row->patient)->toArray() : null)
                ->addColumn('status_label', fn ($encounter) => match ((int) $encounter->status) {
                1 => 'En curso', 2 => $encounter->signature ? 'Finalizada · FES VITA · ' . $encounter->signature->identity['name'] . ' · ' . $encounter->signature->signed_at->timezone('America/Santiago')->format('d/m/Y H:i') : 'Finalizada · Sin firma histórica', 3 => 'Cancelada', default => 'Sin estado',
            })
            ->addColumn('modality_label', fn ($encounter) => $encounter->modality === 'telemedicine' ? 'Telemedicina' : 'Presencial')
            ->addColumn('started_display', fn ($encounter) => $encounter->started_at?->timezone('America/Santiago')->format('d/m/Y H:i') ?? 'Sin fecha')
            ->addColumn('record_url', fn ($encounter) => route('patients.medical-record.show', [
                'customer' => $request->route('customer'), 'id' => $encounter->patient_id,
                'encounter_id' => (int) $encounter->status === 1 ? $encounter->id : null,
                'history_encounter_id' => (int) $encounter->status !== 1 ? $encounter->id : null,
            ]) . ((int) $encounter->status === 1 ? '#current-encounter-section' : '#encounter-history'))
            ->toJson();
    }
}
