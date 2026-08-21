<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Service;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolves a configured path against the host of the current request, so a single
 * configured value serves every environment.
 *
 * Open Graph and Schema.org both want a fully qualified URL. Facebook resolves a bare path
 * out of leniency, but LinkedIn, WhatsApp and Slack drop the image altogether, and Google
 * skips a relative logo. The omission is invisible from the markup, which looks correct.
 */
trait AbsoluteUrlTrait
{
    /**
     * The request is read only once the URL turns out to need a host, so a value already
     * absolute resolves outside an HTTP context too.
     *
     * @param string $origin what the URL was configured as, named in the error so a value
     *                       that cannot be resolved says which setting to fix
     */
    private function absolutizeUrl(string $url, RequestStack $requestStack, string $origin): string
    {
        if (null !== parse_url($url, \PHP_URL_SCHEME)) {
            return $url;
        }

        $request = $requestStack->getCurrentRequest() ?? throw new \LogicException(sprintf('%s holds the path "%s", which needs an active HTTP request to resolve into an absolute URL. Configure an absolute URL to use it outside an HTTP context.', $origin, $url));

        if (str_starts_with($url, '//')) {
            return $request->getScheme().':'.$url;
        }

        return $request->getSchemeAndHttpHost().'/'.ltrim($url, '/');
    }
}
