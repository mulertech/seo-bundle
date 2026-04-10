<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Tests\Model;

use MulerTech\SeoBundle\Model\SitemapUrl;
use PHPUnit\Framework\TestCase;

final class SitemapUrlTest extends TestCase
{
    public function testConstructWithAllParameters(): void
    {
        $url = new SitemapUrl('https://example.com/', '1.0', 'daily', '2025-01-15');

        self::assertSame('https://example.com/', $url->getLoc());
        self::assertSame('1.0', $url->getPriority());
        self::assertSame('daily', $url->getChangefreq());
        self::assertSame('2025-01-15', $url->getLastmod());
    }

    public function testConstructWithDefaults(): void
    {
        $url = new SitemapUrl('https://example.com/page');

        self::assertSame('https://example.com/page', $url->getLoc());
        self::assertSame('0.5', $url->getPriority());
        self::assertSame('monthly', $url->getChangefreq());
        self::assertNull($url->getLastmod());
    }
}
