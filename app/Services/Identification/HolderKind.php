<?php

namespace App\Services\Identification;

enum HolderKind: string
{
    case NaturalPerson = 'natural_person';
    case LegalEntity = 'legal_entity';
}
