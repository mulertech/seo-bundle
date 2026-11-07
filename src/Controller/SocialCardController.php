<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Controller;

use MulerTech\SeoBundle\Service\SocialCardService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;

/**
 * Serves the image a social platform fetches when a page is shared.
 *
 * Cards are derived here rather than while rendering the page: the only client that ever
 * asks for one is a crawler, so a human visitor never pays for an image they will not
 * see. Rendering a page only computes the fingerprint that goes in the URL.
 */
final readonly class SocialCardController
{
    public function __construct(
        private SocialCardService $socialCardService,
        private ?string $defaultImage,
    ) {
    }

    public function __invoke(string $type, string $id, string $fingerprint): Response
    {
        $source = $this->socialCardService->provider($type)?->findSocialCardSource($id);

        if (null === $source) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $current = $this->socialCardService->fingerprint($source);

        // An address shared before the illustration changed. Redirecting rather than
        // rebuilding the old card keeps one card per page, and hands the platform the
        // address it should have cached.
        if ($current !== $fingerprint) {
            return new RedirectResponse($this->socialCardService->url($type, $id, $current), Response::HTTP_MOVED_PERMANENTLY);
        }

        $card = $this->socialCardService->path($type, $id, $current);

        if (!is_file($card) && !$this->socialCardService->generate($source, $type, $id)) {
            return $this->fallback();
        }

        return $this->serve($card);
    }

    /**
     * The fingerprint makes the address change whenever the picture does, so the answer can
     * be cached for as long as a platform is willing to.
     *
     * A card holds nothing session-dependent, which has to be said explicitly: behind a
     * stateful firewall Symfony would otherwise stamp the response
     * `private, max-age=0, must-revalidate` on the grounds that the session was touched,
     * and that header wins over the one below. Every intermediary would then refetch an
     * image built never to change.
     */
    private function serve(string $path): Response
    {
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'image/jpeg');
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->setImmutable();

        return $response;
    }

    /**
     * A temporary redirect, never cached: the card's address is immutable, so answering it
     * with the generic image would pin that image to the page for good once the cause of
     * the failure is fixed. The failure itself has already been logged.
     */
    private function fallback(): Response
    {
        if (null === $this->defaultImage) {
            throw new \RuntimeException('A social card could not be derived (the cause is logged just before) and mulertech_seo.default_image is not configured, so there is no image to fall back on. Configure default_image.');
        }

        $response = new RedirectResponse($this->defaultImage, Response::HTTP_FOUND);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
