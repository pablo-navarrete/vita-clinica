<?php
namespace App\Http\Requests\Billing;
use Illuminate\Foundation\Http\FormRequest;
class VersionRequest extends FormRequest
{
    public function authorize(): bool { return app(\App\Policies\BillingPlanPolicy::class)->manage($this->user()); }
    public function rules(): array { return app(\App\Services\Billing\PlanDefinitionValidator::class)->rules(); }
}
