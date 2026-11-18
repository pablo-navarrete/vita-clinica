<?php

namespace App\Services\Identification;

final class ValidatedNumber
{
    public function __construct(
        public readonly string $normalized,
        public readonly string $display,
        public readonly ValidationLevel $level,
        public readonly string $ruleVersion,
    ) {}
}
