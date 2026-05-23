<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\ValueObjects\ResourceItem;
use PHPUnit\Framework\TestCase;

final class ResourceItemTest extends TestCase
{
    public function testCreatesValidResourceItem(): void
    {
        $item = new ResourceItem(
            type: 'book',
            name: 'Clean Architecture',
            url: 'https://example.com',
        );

        $this->assertSame('book', $item->type);
        $this->assertSame('Clean Architecture', $item->name);
        $this->assertSame('https://example.com', $item->url);
    }

    public function testUrlCanBeNull(): void
    {
        $item = new ResourceItem(
            type: 'person',
            name: 'Robert C. Martin',
            url: null,
        );

        $this->assertNull($item->url);
    }

    public function testToArrayOutput(): void
    {
        $item = new ResourceItem('tool', 'Laravel', 'https://laravel.com');

        $this->assertSame([
            'type' => 'tool',
            'name' => 'Laravel',
            'url'  => 'https://laravel.com',
        ], $item->toArray());
    }

    public function testFromArrayHydration(): void
    {
        $data = ['type' => 'book', 'name' => 'Domain-Driven Design', 'url' => null];
        $item = ResourceItem::fromArray($data);

        $this->assertSame('book', $item->type);
        $this->assertSame('Domain-Driven Design', $item->name);
        $this->assertNull($item->url);
    }
}
