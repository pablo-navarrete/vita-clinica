<?php

namespace App\Services\Identification;

enum DocumentType: string
{
    case NationalId = 'national_id';
    case Passport = 'passport';
    case TaxId = 'tax_id';
}
