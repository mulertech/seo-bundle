<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Tests\Service;

use MulerTech\SeoBundle\Model\SitemapUrl;
use MulerTech\SeoBundle\Model\SitemapUrlProviderInterface;
use MulerTech\SeoBundle\Service\SitemapService;
use PHPUnit\Framework\TestCase;

final class SitemapServiceTest extends TestCase
{
    public function testGenerateProducesValidXml(): void
    {
        $provider = $this->createProvider([
            new SitemapUrl('https://example.com/', '1.0', 'monthly'),
            new SitemapUrl('https://example.com/blog', '0.8', 'weekly'),
        ]);

        $service = new SitemapService([$provider]);
        $xml = $service->generate();

        self::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        self::assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);
        self::assertStringContainsString('<loc>https://example.com/</loc>', $xml);
        self::assertStringContainsString('<loc>https://example.com/blog</loc>', $xml);
        self::assertStringContainsString('<priority>1.0</priority>', $xml);
        self::assertStringContainsString('<changefreq>weekly</changefreq>', $xml);
    }

    public function testGenerateWithLastmod(): void
    {
        $provider = $this->createProvider([
            new SitemapUrl('https://example.com/post', '0.6', 'monthly', '2025-01-15T00:00:00+00:00'),
        ]);

        $service = new SitemapService([$provider]);
        $xml = $service->generate();

        self::assertStringContainsString('<lastmod>2025-01-15T00:00:00+00:00</lastmod>', $xml);
    }

    public function testGenerateWithoutLastmod(): void
    {
        $provider = $this->createProvider([
            new SitemapUrl('https://example.com/page', '0.5', 'monthly'),
        ]);

        $service = new SitemapService([$provider]);
        $xml = $service->generate();

        self::assertStringNotContainsString('<lastmod>', $xml);
    }

    public function testGenerateWithMultipleProviders(): void
    {
        $provider1 = $this->createProvider([
            new SitemapUrl('https://example.com/', '1.0', 'monthly'),
        ]);
        $provider2 = $this->createProvider([
            new SitemapUrl('https://example.com/blog', '0.8', 'weekly'),
        ]);

        $service = new SitemapService([$provider1, $provider2]);
        $xml = $service->generate();

        self::assertStringContainsString('<loc>https://example.com/</loc>', $xml);
        self::assertStringContainsString('<loc>https://example.com/blog</loc>', $xml);
    }

    public function testGenerateWithNoProviders(): void
    {
        $service = new SitemapService([]);
        $xml = $service->generate();

        self::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        self::assertStringContainsString('<urlset', $xml);
        self::assertStringContainsString('</urlset>', $xml);
        self::assertStringNotContainsString('<url>', $xml);
    }

    public function testGenerateEscapesSpecialCharacters(): void
    {
        $provider = $this->createProvider([
            new SitemapUrl('https://example.com/page?foo=1&bar=2', '0.5', 'monthly'),
        ]);

        $service = new SitemapService([$provider]);
        $xml = $service->generate();

        self::assertStringContainsString('&amp;', $xml);
        self::assertStringNotContainsString('&bar', $xml);
    }

    /**
     * @param array<SitemapUrl> $urls
     */
    private function createProvider(array $urls): SitemapUrlProviderInterface
    {
        $provider = $this->createStub(SitemapUrlProviderInterface::class);
        $provider->method('getUrls')->willReturn($urls);

        return $provider;
    }
}
