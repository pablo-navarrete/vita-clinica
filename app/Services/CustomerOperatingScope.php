<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerOperatingDeclaration;
use App\Models\CustomerOperatingReview;
use App\Models\User;
use App\Support\ClinicBranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomerOperatingScope
{
    public const COUNTRY = 'CL';
    public const VERSION = 'chile-operation-v1';
    public const DECLARATION = 'Declaro expresamente que esta clínica prestará sus servicios de salud bajo el ámbito operativo de Chile. Registro esta declaración como usuario responsable de su administración. La aceptación no acredita por sí sola cumplimiento normativo, identidad verificada ni habilitación profesional.';
    public const STATUSES = ['pending' => 'Pendiente de revisión', 'reviewed' => 'Revisado administrativamente', 'needs_information' => 'Requiere antecedentes'];

    public function validate(Request $request, bool $creating): void
    {
        abort_unless($request->user() && (int) $request->user()->status === 1 && ClinicBranchContext::isSuperAdministrator($request->user()), 403);
        $request->validate([
            'operating_country_code' => ['sometimes', 'required', 'string', 'in:CL'],
            'operating_declaration_accepted' => $creating ? ['required', 'accepted'] : ['sometimes', 'boolean'],
            'operating_review_status' => ['sometimes', 'required', 'string', 'in:pending,reviewed,needs_information'],
            'operating_review_note' => ['nullable', 'string', 'max:2000'],
        ], ['operating_country_code.in' => 'VITA solo permite operar en Chile.',
            'operating_declaration_accepted.accepted' => 'Debes aceptar expresamente la declaración de operación en Chile.']);
    }

    /** Called inside the same customer creation/edit transaction. */
    public function record(Customer $customer, User $actor, Request $request): void
    {
        abort_unless(DB::transactionLevel() > 0 && (int) $actor->status === 1 && ClinicBranchContext::isSuperAdministrator($actor), 403);
        $customer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
        abort_unless($customer->operating_country_code === self::COUNTRY, 409);
        $declaration = $customer->operatingDeclaration()->first();
        if ($request->boolean('operating_declaration_accepted')) {
            $declaration = CustomerOperatingDeclaration::firstOrCreate(['customer_id' => $customer->id, 'version' => self::VERSION], [
                'operating_country_code' => self::COUNTRY, 'declaration_text' => self::DECLARATION,
                'content_hash' => hash('sha256', self::VERSION."\n".self::COUNTRY."\n".self::DECLARATION),
                'accepted' => true, 'accepted_by' => $actor->id, 'accepted_at' => now('UTC'),
            ]);
        }
        $previous = $customer->operatingReview()->first();
        $status = $request->input('operating_review_status');
        if ($status === null) {
            if (! $declaration || ($previous && $previous->declaration_id === $declaration->id)) return;
            $status = 'pending';
        }
        if (! $declaration) {
            if ($status !== 'pending' || filled($request->input('operating_review_note'))) {
                throw ValidationException::withMessages(['operating_review_status' => 'Registra primero una declaración explícita. La clínica existente conserva su acceso.']);
            }
            return;
        }
        $note = filled($request->input('operating_review_note')) ? trim($request->input('operating_review_note')) : null;
        if ($previous && $previous->declaration_id === $declaration->id && $previous->status === $status && $previous->note === $note) return;
        CustomerOperatingReview::create(['customer_id' => $customer->id, 'declaration_id' => $declaration->id,
            'status' => $status, 'note' => $note, 'reviewed_by' => $actor->id, 'reviewed_at' => now('UTC')]);
    }
}
