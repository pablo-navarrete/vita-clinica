<?php

namespace App\Services\Identification\Storage;

use App\Services\Identification\DocumentIdentifier;
use InvalidArgumentException;

final class IdentifierSubject
{
    public function __construct(public readonly string $kind, public readonly int $ownerId, public readonly ?int $customerId)
    {
        if (! in_array($kind, ['customer', 'user', 'patient'], true) || $ownerId < 1 || ($customerId !== null && $customerId < 1)
            || ($kind !== 'user' && $customerId === null) || ($kind === 'customer' && $ownerId !== $customerId)) {
            throw new InvalidArgumentException('Contexto de identificación no válido.');
        }
    }

    public function table(): string
    {
        return $this->kind.'_identifiers';
    }

    public function ownerColumn(): string
    {
        return $this->kind === 'customer' ? 'customer_id' : $this->kind.'_id';
    }

    public function ownersTable(): string
    {
        return match ($this->kind) {
            'customer' => 'customers','user' => 'users','patient' => 'patients'
        };
    }

    public function scopeKey(): string
    {
        return $this->customerId === null ? 'global' : 'customer:'.$this->customerId;
    }

    public function lookupContext(DocumentIdentifier $document): array
    {
        return ['kind' => $this->kind, 'scope' => $this->scopeKey(),
            // Patients reserve a number throughout the clinic. Users/customers only within their own record.
            'owner' => $this->kind === 'patient' ? null : $this->ownerId,
            'country' => $document->definition->country, 'namespace' => $document->definition->namespace,
            'holder' => $document->definition->holderKind->value, 'normalized' => $document->number->normalized];
    }
}
