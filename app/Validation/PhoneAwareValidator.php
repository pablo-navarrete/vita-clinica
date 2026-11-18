<?php

namespace App\Validation;

use App\Services\PhoneNumbers;
use Illuminate\Validation\Validator;

/** Apply the same phone rule to every declared phone attribute, including nested forms. */
class PhoneAwareValidator extends Validator
{
    public function __construct($translator, array $data, array $rules, array $messages = [], array $attributes = [])
    {
        parent::__construct($translator, (new PhoneNumbers)->normalizeFields($data), $rules, $messages, $attributes);
    }

    public function addRules($rules)
    {
        foreach ($rules as $attribute => &$rule) {
            if (!PhoneNumbers::isField($attribute)) continue;
            $rule = is_string($rule) ? explode('|', $rule) : (is_array($rule) ? $rule : [$rule]);
            if (!in_array('international_phone', $rule, true)) $rule[] = 'international_phone';
        }
        unset($rule);
        parent::addRules($rules);
    }
}
