<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Service;

use MulerTech\SeoBundle\Model\BlogPostingSeoInterface;
use MulerTech\SeoBundle\Model\SeoCompanyInfoProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class SchemaOrgService
{
    /**
     * @param array<int, array{type: string, name: string}> $areasServed
     * @param array<int, string>                            $offerNames
     */
    public function __construct(
        private SeoCompanyInfoProviderInterface $companyInfoProvider,
        private RequestStack $requestStack,
        private string $organizationDescription = '',
        private string $organizationType = 'LocalBusiness',
        private string $priceRange = '',
        private string $addressRegion = '',
        private array $areasServed = [],
        private array $offerNames = [],
        private string $searchActionPathTemplate = '',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function organization(): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $this->organizationType,
            'name' => $this->companyInfoProvider->getName(),
            'url' => $this->companyInfoProvider->getWebsite(),
            'email' => $this->companyInfoProvider->getEmail(),
            'telephone' => $this->companyInfoProvider->getPhone(),
            'description' => $this->organizationDescription,
            'address' => [
                '@type' => 'PostalAddress',
                'postalCode' => $this->companyInfoProvider->getPostalCode(),
                'addressLocality' => $this->companyInfoProvider->getCity(),
                'addressRegion' => $this->addressRegion,
                'addressCountry' => $this->companyInfoProvider->getCountry(),
            ],
        ];

        if ([] !== $this->areasServed) {
            $schema['areaServed'] = array_map(
                static fn (array $area): array => ['@type' => $area['type'], 'name' => $area['name']],
                $this->areasServed,
            );
        }

        if ('' !== $this->priceRange) {
            $schema['priceRange'] = $this->priceRange;
        }

        if ([] !== $this->offerNames) {
            $schema['hasOfferCatalog'] = [
                '@type' => 'OfferCatalog',
                'name' => 'Services',
                'itemListElement' => array_map(
                    static fn (string $name): array => [
                        '@type' => 'Offer',
                        'itemOffered' => ['@type' => 'Service', 'name' => $name],
                    ],
                    $this->offerNames,
                ),
            ];
        }

        $socialUrls = array_values(array_filter($this->companyInfoProvider->getSocialUrls()));
        if ([] !== $socialUrls) {
            $schema['sameAs'] = $socialUrls;
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    public function webSite(string $siteUrl): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $this->companyInfoProvider->getName(),
            'url' => $siteUrl,
        ];

        if ('' !== $this->searchActionPathTemplate) {
            $schema['potentialAction'] = [
                '@type' => 'SearchAction',
                'target' => $siteUrl.$this->searchActionPathTemplate,
                'query-input' => 'required name=search_term_string',
            ];
        }

        return $schema;
    }

    /**
     * @param array<int, array{label: string, url: ?string}> $items
     *
     * @return array<string, mixed>
     */
    public function breadcrumbList(array $items): array
    {
        $listItems = [];
        foreach ($items as $index => $item) {
            $listItems[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['label'],
                'item' => $item['url'] ?? $this->requestStack->getCurrentRequest()?->getUri() ?? '',
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $listItems,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function blogPosting(BlogPostingSeoInterface $post, string $url): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post->getSeoTitle(),
            'url' => $url,
            'author' => [
                '@type' => 'Person',
                'name' => $post->getSeoAuthorName(),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $this->companyInfoProvider->getName(),
                'url' => $this->companyInfoProvider->getWebsite(),
            ],
        ];

        if (null !== $post->getSeoPublishedAt()) {
            $schema['datePublished'] = $post->getSeoPublishedAt();
        }
        if (null !== $post->getSeoUpdatedAt()) {
            $schema['dateModified'] = $post->getSeoUpdatedAt();
        }
        if (null !== $post->getSeoExcerpt()) {
            $schema['description'] = $post->getSeoExcerpt();
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $serviceData
     *
     * @return array<string, mixed>
     */
    public function service(array $serviceData, string $url): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => $serviceData['title'] ?? '',
            'description' => $serviceData['description'] ?? '',
            'url' => $url,
            'provider' => [
                '@type' => 'Organization',
                'name' => $this->companyInfoProvider->getName(),
                'url' => $this->companyInfoProvider->getWebsite(),
            ],
        ];

        if ([] !== $this->areasServed) {
            $schema['areaServed'] = array_map(
                static fn (array $area): array => ['@type' => $area['type'], 'name' => $area['name']],
                $this->areasServed,
            );
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $schema
     */
    public function toJsonLd(array $schema): string
    {
        return json_encode($schema, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT) ?: '{}';
    }
}
