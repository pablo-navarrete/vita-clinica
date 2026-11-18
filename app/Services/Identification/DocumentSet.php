<?php

namespace App\Services\Identification;

use DateTimeImmutable;
use InvalidArgumentException;

/** Immutable set belonging to one future owner. No persistence, tenancy or authorization. */
final class DocumentSet
{
    /** @var array<string, DocumentRecord> */
    private array $records = [];

    private ?string $primaryReference = null;

    public function __construct(public readonly HolderKind $holderKind = HolderKind::NaturalPerson) {}

    public function add(string $reference, DocumentIdentifier $identifier, bool $primary = false): self
    {
        if ($reference === '' || strlen($reference) > 100 || isset($this->records[$reference])) {
            throw new InvalidArgumentException('La referencia del documento debe ser única y no vacía.');
        }
        if ($identifier->definition->holderKind !== $this->holderKind) {
            throw new InvalidArgumentException('El documento no corresponde al tipo de titular.');
        }
        foreach ($this->records as $record) {
            if ($record->identifier->sameIdentifierAs($identifier)) {
                throw new InvalidArgumentException('El documento ya está en este conjunto, activo o retirado.');
            }
        }
        $next = clone $this;
        $next->records[$reference] = new DocumentRecord($reference, $identifier);
        if ($primary) {
            $next->primaryReference = $reference;
        }

        return $next;
    }

    public function withPrimary(string $reference): self
    {
        $record = $this->record($reference);
        if (! $record->isActive()) {
            throw new InvalidArgumentException('Un documento retirado no puede ser principal.');
        }
        $next = clone $this;
        $next->primaryReference = $reference;

        return $next;
    }

    public function retire(string $reference, DateTimeImmutable $at): self
    {
        $record = $this->record($reference);
        if (! $record->isActive()) {
            throw new InvalidArgumentException('El documento ya está retirado.');
        }
        $next = clone $this;
        $next->records[$reference] = new DocumentRecord($reference, $record->identifier, $at);
        // Never silently select another primary document.
        if ($next->primaryReference === $reference) {
            $next->primaryReference = null;
        }

        return $next;
    }

    public function primary(): ?DocumentRecord
    {
        return $this->primaryReference === null ? null : $this->records[$this->primaryReference];
    }

    public function all(): array
    {
        return array_values($this->records);
    }

    public function active(): array
    {
        return array_values(array_filter($this->records, fn ($record) => $record->isActive()));
    }

    private function record(string $reference): DocumentRecord
    {
        return $this->records[$reference] ?? throw new InvalidArgumentException('No existe el documento en este conjunto.');
    }
}
