<?php

namespace App\Services\Identification;

enum ValidationLevel: string
{
    case Basic = 'basic';
    case Checksum = 'checksum';
}
