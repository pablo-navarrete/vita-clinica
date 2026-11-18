<?php

namespace App\Services\Identification;

use InvalidArgumentException;

final class InvalidIdentification extends InvalidArgumentException
{
    public function __construct(public readonly string $field, string $message)
    {
        // Do not include the document number in exceptions or logs.
        parent::__construct($message);
    }
}
