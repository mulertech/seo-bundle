<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Controller;

use MulerTech\SeoBundle\Service\LlmsService;
use Symfony\Component\HttpFoundation\Response;

final readonly class LlmsController
{
    public function __construct(
        private LlmsService $llmsService,
        private bool $enabled = true,
    ) {
    }

    public function __invoke(): Response
    {
        if (!$this->enabled) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        return new Response($this->llmsService->generate(), Response::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
