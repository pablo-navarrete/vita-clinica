<?php
namespace App\Http\Requests\Billing;
use Illuminate\Foundation\Http\FormRequest;
class SubscriptionRequest extends FormRequest
{
    public function authorize(): bool { return app(\App\Policies\BillingPlanPolicy::class)->manage($this->user()); }
    public function rules(): array
    {
        return ['customer_id'=>'required|integer|exists:customers,id','billing_plan_price_id'=>'required|integer|exists:billing_plan_prices,id',
            'branch_quantity'=>'required|integer|min:1|max:2147483647','starts_on'=>'required|date_format:Y-m-d',
            'use_trial'=>'required|boolean','note'=>'nullable|string|max:2000',
            'payment_mode'=>'sometimes|required|in:automatic,manual'];
    }
}
