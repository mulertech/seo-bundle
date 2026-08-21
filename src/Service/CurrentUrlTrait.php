<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * The current address, minus the parameters that only say where the visitor came from.
 *
 * Advertising platforms and social networks append these on their own, so a page collects
 * them without anyone ever linking to it that way. Left in, they turn the tracked address
 * into the one the page declares as its own, whether that declaration is a canonical, a
 * `Service` url or the last entry of a breadcrumb.
 *
 * Parameters that change what the page shows are kept. Dropping them all would be simpler
 * and wrong: a paginated page reduced to its first page declares itself a duplicate of it.
 */
trait CurrentUrlTrait
{
    /**
     * @param list<string> $ignoredParameters
     */
    private function currentUrlWithout(array $ignoredParameters, Request $request): string
    {
        $parameters = $request->query->all();

        foreach ($ignoredParameters as $parameter) {
            unset($parameters[$parameter]);
        }

        $url = $request->getUriForPath($request->getPathInfo());

        return [] === $parameters ? $url : $url.'?'.http_build_query($parameters);
    }
}
