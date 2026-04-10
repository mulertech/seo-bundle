<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Tests\Controller;

use MulerTech\SeoBundle\Controller\RobotsController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RobotsControllerTest extends TestCase
{
    public function testProdEnvironmentAllowsCrawling(): void
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://example.com/sitemap.xml');

        $controller = new RobotsController($urlGenerator, 'prod', ['/admin', '/login']);

        $response = $controller();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/plain', $response->headers->get('Content-Type'));
        self::assertStringContainsString('Allow: /', $response->getContent());
        self::assertStringContainsString('Disallow: /admin', $response->getContent());
        self::assertStringContainsString('Disallow: /login', $response->getContent());
        self::assertStringContainsString('Sitemap: https://example.com/sitemap.xml', $response->getContent());
    }

    public function testNonProdEnvironmentDisallowsAll(): void
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);

        $controller = new RobotsController($urlGenerator, 'dev');

        $response = $controller();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Disallow: /', $response->getContent());
        self::assertStringNotContainsString('Allow', $response->getContent());
        self::assertStringNotContainsString('Sitemap', $response->getContent());
    }

    public function testCustomDisallowPaths(): void
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://example.com/sitemap.xml');

        $controller = new RobotsController($urlGenerator, 'prod', ['/admin', '/api', '/private']);

        $response = $controller();

        self::assertStringContainsString('Disallow: /admin', $response->getContent());
        self::assertStringContainsString('Disallow: /api', $response->getContent());
        self::assertStringContainsString('Disallow: /private', $response->getContent());
    }
}
