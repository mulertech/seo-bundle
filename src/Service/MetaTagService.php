<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Service;

use MulerTech\SeoBundle\Model\SeoCompanyInfoProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class MetaTagService
{
    use AbsoluteUrlTrait;
    use CurrentUrlTrait;

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
        private ?int $defaultImageWidth = null,
        private ?int $defaultImageHeight = null,
        private ?string $defaultImageAlt = null,
    ) {
    }

    /**
     * @param array<string, string|int|null> $options
     *
     * @return array<string, ?string>
     */
    public function generateMetaTags(array $options = []): array
    {
        $title = isset($options['title']) ? $this->truncate((string) $options['title'], 60) : null;
        $description = isset($options['description']) ? $this->truncate((string) $options['description'], 160) : null;
        $url = isset($options['url']) ? (string) $options['url'] : $this->getCurrentUrl();
        $type = isset($options['type']) ? (string) $options['type'] : 'website';

        if (!isset($options['image'])) {
            $options['image'] = $this->defaultImage;
            $options['imageWidth'] ??= $this->defaultImageWidth;
            $options['imageHeight'] ??= $this->defaultImageHeight;
            $options['imageAlt'] ??= $this->defaultImageAlt;
        }

        $image = $this->absolutize($options['image']);

        $meta = array_merge([
            'title' => $title,
            'description' => $description,
            'canonical' => $url,
            'og:title' => $title,
            'og:description' => $description,
            'og:url' => $url,
            'og:type' => $type,
            'og:locale' => $this->defaultLocale,
            'og:site_name' => $this->companyInfoProvider->getName(),
            'twitter:card' => null !== $image ? 'summary_large_image' : 'summary',
            'twitter:title' => $title,
            'twitter:description' => $description,
        ], $this->imageMeta($image, $options));

        if ('article' === $type) {
            $meta['article:published_time'] = isset($options['publishedTime']) ? (string) $options['publishedTime'] : null;
            $meta['article:modified_time'] = isset($options['modifiedTime']) ? (string) $options['modifiedTime'] : null;
            $meta['article:author'] = isset($options['author']) ? (string) $options['author'] : null;
        }

        return $meta;
    }

    /**
     * @param array<string, string|int|null> $options
     *
     * @return array<string, ?string>
     */
    private function imageMeta(?string $image, array $options): array
    {
        if (null === $image) {
            return [
                'og:image' => null,
                'og:image:width' => null,
                'og:image:height' => null,
                'og:image:type' => null,
                'og:image:alt' => null,
                'twitter:image' => null,
                'twitter:image:alt' => null,
            ];
        }

        $alt = isset($options['imageAlt']) ? (string) $options['imageAlt'] : null;

        return [
            'og:image' => $image,
            'og:image:width' => isset($options['imageWidth']) ? (string) $options['imageWidth'] : null,
            'og:image:height' => isset($options['imageHeight']) ? (string) $options['imageHeight'] : null,
            'og:image:type' => isset($options['imageType']) ? (string) $options['imageType'] : $this->guessImageType($image),
            'og:image:alt' => $alt,
            'twitter:image' => $image,
            'twitter:image:alt' => $alt,
        ];
    }

    private function truncate(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength - 3).'...';
    }

    private function absolutize(string|int|null $image): ?string
    {
        if (null === $image || '' === $image) {
            return null;
        }

        return $this->absolutizeUrl((string) $image, $this->requestStack, 'The Open Graph image');
    }

    /**
     * `og:image:type` is a hint, so an unrecognised extension yields no tag rather than a
     * guess: announcing the wrong MIME type is worse than announcing none.
     */
    private function guessImageType(string $image): ?string
    {
        $path = parse_url($image, \PHP_URL_PATH);

        if (!\is_string($path)) {
            return null;
        }

        return match (strtolower(pathinfo($path, \PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => null,
        };
    }

    private function getCurrentUrl(): string
    {
        return $this->currentUrlWithout($this->ignoredParameters, $this->getRequest());
    }

    private function getRequest(): Request
    {
        return $this->requestStack->getCurrentRequest()
            ?? throw new \LogicException('MetaTagService requires an active HTTP request — cannot be used in CLI context');
    }
}
