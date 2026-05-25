<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

enum TaxonomyType: string
{
    case Topic   = 'topic';
    case Speaker = 'speaker';

    public function routePrefix(): string
    {
        return match ($this) {
            self::Topic   => 'topic',
            self::Speaker => 'speaker',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Topic   => 'Topic',
            self::Speaker => 'Speaker',
        };
    }
}
