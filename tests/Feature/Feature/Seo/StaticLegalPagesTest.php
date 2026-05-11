<?php

declare(strict_types=1);

namespace Tests\Feature\Feature\Seo;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Smoke tests for static legal/informational pages.
 * Verifies 200 OK and basic content presence.
 */
final class StaticLegalPagesTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function staticPagesProvider(): array
    {
        return [
            'terms'   => ['/terms',   'Terms of Service', 'h1'],
            'privacy' => ['/privacy', 'Privacy Policy', 'h1'],
            'pricing' => ['/pricing', 'Pricing', 'h1'],
            'contact' => ['/contact', 'Contact', 'h1'],
        ];
    }

    #[DataProvider('staticPagesProvider')]
    public function testStaticPageReturns200WithContent(string $url, string $expectedText, string $expectedTag): void
    {
        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee($expectedText, false);
    }

    public function testStaticPagesIncludeFooter(): void
    {
        foreach (['/terms', '/privacy', '/pricing', '/contact'] as $url) {
            $response = $this->get($url);

            $response->assertOk();
            $response->assertSee('TubeSum', false);
            $response->assertSee('/pricing', false);
            $response->assertSee('/terms', false);
            $response->assertSee('/privacy', false);
            $response->assertSee('/dmca', false);
            $response->assertSee('/contact', false);
        }
    }
}
