<?php

namespace App\Services\Identification;

interface DocumentNumberRule
{
    public function validate(string $number): ValidatedNumber;
}
