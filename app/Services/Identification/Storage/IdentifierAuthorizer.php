<?php

namespace App\Services\Identification\Storage;

use App\Models\User;

interface IdentifierAuthorizer
{
    public function authorize(User $actor, IdentifierSubject $subject, object $owner, bool $write): void;
}
