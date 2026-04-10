<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class RobotsController
{
    /**
     * @param array<int, string> $disallowPaths
     */
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private string $environment,
        private array $disallowPaths = ['/admin', '/login'],
    ) {
    }

    public function __invoke(): Response
    {
        if ('prod' === $this->environment) {
            $sitemapUrl = $this->urlGenerator->generate('mulertech_seo_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);
            $disallow = implode("\n", array_map(static fn (string $path): string => 'Disallow: '.$path, $this->disallowPaths));
            $content = "User-agent: *\nAllow: /\n{$disallow}\nSitemap: {$sitemapUrl}\n";
        } else {
            $content = "User-agent: *\nDisallow: /\n";
        }

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'text/plain',
        ]);
    }
}
