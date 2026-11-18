<?php
namespace App\Http\Requests\Billing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class PlanRequest extends FormRequest
{
    public function authorize(): bool { return app(\App\Policies\BillingPlanPolicy::class)->manage($this->user()); }
    public function rules(): array
    {
        $rules = ['code' => ['required','alpha_dash','max:80',Rule::unique('billing_plans','code')->ignore($this->route('plan')?->id)],
            'name' => 'required|string|max:255', 'description' => 'nullable|string|max:5000',
            'active' => 'required|boolean', 'position' => 'required|integer|min:0|max:2147483647'];
        if ($this->has('definition')) {
            $rules['definition']='required|array';
            $rules['revision']='required|integer|min:0';
        }
        return $rules;
    }
}
