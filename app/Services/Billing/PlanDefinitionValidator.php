<?php
namespace App\Services\Billing;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\{Rule, ValidationException};
class PlanDefinitionValidator
{
    public function rules(): array
    {
        $integer = 'nullable|integer|min:0|max:2147483647';
        $rules = ['included_branches' => 'required|integer|min:0|max:2147483647',
            'trial_days' => 'required|integer|min:0|max:2147483647', 'grace_days' => 'required|integer|min:0|max:2147483647',
            'terms' => 'required|string|max:20000', 'prices' => 'required|array:month,year',
            'limits' => 'required|array:users,professionals,branches',
            'features' => 'sometimes|array', 'features.*' => ['required','distinct',Rule::in(array_keys(BillingCapabilities::FEATURES))],
            'quotas' => 'sometimes|array:ai_tokens,storage_bytes'];
        foreach (['month','year'] as $period) {
            $rules["prices.$period"] = 'required|array:enabled,amount,additional_branch_amount';
            $rules["prices.$period.enabled"] = 'required|boolean';
            foreach (['amount','additional_branch_amount'] as $key) $rules["prices.$period.$key"] = "$integer|required_if:prices.$period.enabled,1";
        }
        foreach (BillingCapabilities::LIMITS as $code => $label) {
            $rules["limits.$code"] = 'required|array:unlimited,value,scope';
            $rules["limits.$code.unlimited"] = 'required|boolean';
            $rules["limits.$code.value"] = "$integer|required_if:limits.$code.unlimited,0";
            $rules["limits.$code.scope"] = ['required', Rule::in($code === 'branches' ? ['customer'] : ['customer','branch'])];
        }
        foreach (BillingCapabilities::QUOTAS as $metric => $definition) {
            $rules["quotas.$metric"] = 'sometimes|array:enabled,unlimited,quantity,reset_period,overage_policy';
            $rules["quotas.$metric.enabled"] = 'required_with:quotas.' . $metric . '|boolean';
            $rules["quotas.$metric.unlimited"] = 'required_if:quotas.' . $metric . '.enabled,1|nullable|boolean';
            $rules["quotas.$metric.quantity"] = 'nullable|integer|min:0|max:9007199254740991';
            $rules["quotas.$metric.reset_period"] = ['nullable',Rule::in($definition['periods'])];
            $rules["quotas.$metric.overage_policy"] = ['nullable',Rule::in(['notify','block'])];
        }
        return $rules;
    }
    public function validate(array $data): array
    {
        $data = Validator::make($data, $this->rules())->validate();
        if (!collect($data['prices'])->contains(fn ($p) => (bool) $p['enabled'])) throw ValidationException::withMessages(['prices' => 'Habilita al menos una periodicidad.']);
        foreach ($data['quotas'] ?? [] as $metric => $quota) {
            if (!($quota['enabled'] ?? false)) continue;
            if ((!($quota['unlimited'] ?? false) && !isset($quota['quantity'])) || empty($quota['reset_period']) || empty($quota['overage_policy'])) {
                throw ValidationException::withMessages(["quotas.$metric" => 'Completa la cantidad (o ilimitada), período y política de la cuota.']);
            }
        }
        $maximum = $data['limits']['branches'];
        if (!$maximum['unlimited'] && (int) $maximum['value'] < (int) $data['included_branches']) {
            throw ValidationException::withMessages(['included_branches' => 'Las sedes incluidas no pueden superar el máximo de sedes.']);
        }
        return $data;
    }
}
