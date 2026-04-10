<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Controller;

use MulerTech\SeoBundle\Service\SitemapService;
use Symfony\Component\HttpFoundation\Response;

final readonly class SitemapController
{
    public function __construct(
        private SitemapService $sitemapService,
    ) {
    }

    public function __invoke(): Response
    {
        return new Response($this->sitemapService->generate(), Response::HTTP_OK, [
            'Content-Type' => 'application/xml',
        ]);
    }
}
