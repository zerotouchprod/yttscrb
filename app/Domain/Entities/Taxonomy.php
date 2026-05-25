<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\ValueObjects\TaxonomyType;

final class Taxonomy
{
    public function __construct(
        private readonly string $id,
        private readonly TaxonomyType $type,
        private readonly string $name,
        private readonly string $slug,
        private int $videoCount = 0,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function type(): TaxonomyType
    {
        return $this->type;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function videoCount(): int
    {
        return $this->videoCount;
    }

    public function incrementVideoCount(): void
    {
        $this->videoCount++;
    }
}
