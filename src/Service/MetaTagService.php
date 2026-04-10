<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Service;

use MulerTech\SeoBundle\Model\SeoCompanyInfoProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class MetaTagService
{
    public function __construct(
        private RequestStack $requestStack,
        private SeoCompanyInfoProviderInterface $companyInfoProvider,
        private ?string $defaultImage = null,
        private string $defaultLocale = 'fr_FR',
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

    private function getCurrentUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest()
            ?? throw new \LogicException('MetaTagService requires an active HTTP request — cannot be used in CLI context');

        return $request->getUri();
    }

    private function resolveDefaultImage(): ?string
    {
        if (null !== $this->defaultImage) {
            return $this->defaultImage;
        }

        return null;
    }
}
