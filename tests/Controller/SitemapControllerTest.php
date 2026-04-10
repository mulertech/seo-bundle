<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Tests\Controller;

use MulerTech\SeoBundle\Controller\SitemapController;
use MulerTech\SeoBundle\Model\SitemapUrl;
use MulerTech\SeoBundle\Model\SitemapUrlProviderInterface;
use MulerTech\SeoBundle\Service\SitemapService;
use PHPUnit\Framework\TestCase;

final class SitemapControllerTest extends TestCase
{
    public function testInvokeReturnsXmlResponse(): void
    {
        $provider = $this->createStub(SitemapUrlProviderInterface::class);
        $provider->method('getUrls')->willReturn([
            new SitemapUrl('https://example.com/', '1.0', 'monthly'),
        ]);

        $sitemapService = new SitemapService([$provider]);
        $controller = new SitemapController($sitemapService);

        $response = $controller();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/xml', $response->headers->get('Content-Type'));
        self::assertStringContainsString('<urlset', $response->getContent());
        self::assertStringContainsString('https://example.com/', $response->getContent());
    }
}
