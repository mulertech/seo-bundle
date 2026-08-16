<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Service;

use MulerTech\SeoBundle\Model\SeoCompanyInfoProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class MetaTagService
{
    /**
     * Query parameters that name where a visitor came from rather than what they asked
     * for. Advertising platforms and social networks append these on their own, so a page
     * gets them without anyone linking to it that way.
     *
     * @var list<string>
     */
    public const array TRACKING_PARAMETERS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id',
        'gclid', 'gbraid', 'wbraid', 'fbclid', 'msclkid', 'ttclid', 'twclid', 'igshid',
        'mc_cid', 'mc_eid', 'yclid', '_ga', 'ref_src',
    ];

    /**
     * @param list<string> $ignoredParameters
     */
    public function __construct(
        private RequestStack $requestStack,
        private SeoCompanyInfoProviderInterface $companyInfoProvider,
        private ?string $defaultImage = null,
        private string $defaultLocale = 'fr_FR',
        private array $ignoredParameters = self::TRACKING_PARAMETERS,
    ) {
    }

    /**
     * @param array<string, string|null> $options
     *
     * @return array<string, ?string>
     */
    public function generateMetaTags(array $options = []): array
    {
        $title = isset($options['title']) ? $this->truncate($options['title'], 60) : null;
        $description = isset($options['description']) ? $this->truncate($options['description'], 160) : null;
        $url = $options['url'] ?? $this->getCurrentUrl();
        $type = $options['type'] ?? 'website';
        $image = $options['image'] ?? $this->resolveDefaultImage();

        $meta = [
            'title' => $title,
            'description' => $description,
            'canonical' => $url,
            'og:title' => $title,
            'og:description' => $description,
            'og:url' => $url,
            'og:type' => $type,
            'og:locale' => $this->defaultLocale,
            'og:site_name' => $this->companyInfoProvider->getName(),
            'og:image' => $image,
            'twitter:card' => null !== $image ? 'summary_large_image' : 'summary',
            'twitter:title' => $title,
            'twitter:description' => $description,
            'twitter:image' => $image,
        ];

        if ('article' === $type) {
            $meta['article:published_time'] = $options['publishedTime'] ?? null;
            $meta['article:modified_time'] = $options['modifiedTime'] ?? null;
            $meta['article:author'] = $options['author'] ?? null;
        }

        return $meta;
    }

    private function truncate(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength - 3).'...';
    }

    /**
     * The current address, minus the parameters that only say where the visitor came from.
     *
     * This value becomes both `canonical` and `og:url`, which is why the distinction
     * matters: a tracking parameter left in place makes the tracked address the one search
     * engines treat as official, and the one carried by anyone resharing the page — so a
     * campaign ends up credited with visits that came from elsewhere.
     *
     * Parameters that change what the page shows are kept. Dropping them all would be
     * simpler and wrong: a paginated page canonicalised to its first page is declared a
     * duplicate of it, and drops out of the index.
     */
    private function getCurrentUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest()
            ?? throw new \LogicException('MetaTagService requires an active HTTP request — cannot be used in CLI context');

        $parameters = $request->query->all();

        foreach ($this->ignoredParameters as $parameter) {
            unset($parameters[$parameter]);
        }

        $url = $request->getUriForPath($request->getPathInfo());

        return [] === $parameters ? $url : $url.'?'.http_build_query($parameters);
    }

    private function resolveDefaultImage(): ?string
    {
        if (null !== $this->defaultImage) {
            return $this->defaultImage;
        }

        return null;
    }
}
