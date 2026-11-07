<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Service;

use MulerTech\SeoBundle\Model\BlogPostingSeoInterface;
use MulerTech\SeoBundle\Model\SeoCompanyInfoProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class SchemaOrgService
{
    use AbsoluteUrlTrait;
    use CurrentUrlTrait;

    /**
     * @param array<int, array{type: string, name: string}> $areasServed
     * @param array<int, string>                            $offerNames
     * @param list<string>                                  $ignoredParameters query parameters
     *                                                                         stripped from the
     *                                                                         address a breadcrumb
     *                                                                         falls back to
     */
    public function __construct(
        private SeoCompanyInfoProviderInterface $companyInfoProvider,
        private RequestStack $requestStack,
        private string $organizationDescription = '',
        private string $organizationType = 'LocalBusiness',
        private string $priceRange = '',
        private string $addressRegion = '',
        private string $logo = '',
        private string $founderName = '',
        private string $founderUrl = '',
        private array $areasServed = [],
        private array $offerNames = [],
        private string $searchActionPathTemplate = '',
        private array $ignoredParameters = MetaTagService::TRACKING_PARAMETERS,
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
            ...$this->organizationId(),
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

        if ('' !== $this->logo) {
            $schema['logo'] = $this->absolutizeUrl($this->logo, $this->requestStack, 'mulertech_seo.schema_org.logo');
        }

        if ('' !== $this->founderName) {
            $schema['founder'] = [
                '@type' => 'Person',
                ...$this->founderReference(),
                'name' => $this->founderName,
            ];
        }

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
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $siteUrl.$this->searchActionPathTemplate,
                ],
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
                'item' => $item['url'] ?? $this->currentUrl(),
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $listItems,
        ];
    }

    /**
     * @param list<array{question: string, answer: string}> $items `answer` may hold the
     *                                                             restricted HTML Google
     *                                                             reads in `Answer.text`
     *
     * @return array<string, mixed>
     */
    public function faqPage(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(
                static fn (array $item): array => [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $item['answer'],
                    ],
                ],
                $items,
            ),
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
                ...($post->getSeoAuthorName() === $this->founderName ? $this->founderReference() : []),
                'name' => $post->getSeoAuthorName(),
            ],
            'publisher' => [
                '@type' => 'Organization',
                ...$this->organizationId(),
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
            'url' => $url,
            'provider' => [
                '@type' => 'Organization',
                ...$this->organizationId(),
                'name' => $this->companyInfoProvider->getName(),
                'url' => $this->companyInfoProvider->getWebsite(),
            ],
        ];

        $description = $serviceData['description'] ?? '';
        if ('' !== $description) {
            $schema['description'] = $description;
        }

        if ([] !== $this->areasServed) {
            $schema['areaServed'] = array_map(
                static fn (array $area): array => ['@type' => $area['type'], 'name' => $area['name']],
                $this->areasServed,
            );
        }

        return $schema;
    }

    /**
     * The founder described on their own page, linked to the organization both ways: the
     * organization names them as `founder`, they name it as `worksFor`, and both carry the
     * same `@id`. Search engines and language models then read one person and one company
     * rather than two homonyms to reconcile.
     *
     * @param array{jobTitle: string, description?: string, image?: string, knowsAbout?: list<string>, sameAs?: list<string>} $personData
     *
     * @return array<string, mixed>
     */
    public function person(array $personData): array
    {
        if ('' === $this->founderName || '' === $this->founderUrl) {
            throw new \LogicException('schema_org_json_ld("person") describes the founder: configure both mulertech_seo.schema_org.founder_name and mulertech_seo.schema_org.founder_url (the page that presents them, e.g. /about).');
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            ...$this->founderReference(),
            'name' => $this->founderName,
            'jobTitle' => $personData['jobTitle'],
        ];

        if (isset($personData['description']) && '' !== $personData['description']) {
            $schema['description'] = $personData['description'];
        }
        if (isset($personData['image']) && '' !== $personData['image']) {
            $schema['image'] = $this->absolutizeUrl($personData['image'], $this->requestStack, 'schema_org_json_ld("person") image');
        }
        if (isset($personData['knowsAbout']) && [] !== $personData['knowsAbout']) {
            $schema['knowsAbout'] = $personData['knowsAbout'];
        }
        if (isset($personData['sameAs']) && [] !== $personData['sameAs']) {
            $schema['sameAs'] = $personData['sameAs'];
        }

        $schema['worksFor'] = [
            '@type' => $this->organizationType,
            ...$this->organizationId(),
            'name' => $this->companyInfoProvider->getName(),
            'url' => $this->companyInfoProvider->getWebsite(),
        ];

        return $schema;
    }

    /**
     * Every block naming the organization carries the same identifier, which is what lets
     * a reader merge them into one entity. Without a website there is nothing stable to
     * build it from, and no identifier is better than a relative one.
     *
     * @return array{'@id'?: string}
     */
    private function organizationId(): array
    {
        $website = $this->companyInfoProvider->getWebsite();

        return '' === $website ? [] : ['@id' => rtrim($website, '/').'/#organization'];
    }

    /**
     * @return array{'@id'?: string, url?: string}
     */
    private function founderReference(): array
    {
        if ('' === $this->founderUrl) {
            return [];
        }

        $url = $this->absolutizeUrl($this->founderUrl, $this->requestStack, 'mulertech_seo.schema_org.founder_url');

        return ['@id' => $url.'#person', 'url' => $url];
    }

    private function currentUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        return null === $request ? '' : $this->currentUrlWithout($this->ignoredParameters, $request);
    }

    /**
     * `JSON_HEX_TAG` is what keeps the payload inside its `<script>` element: an entry
     * holding `</script>` would otherwise close the element early, and the rest of the
     * data would land in the page as markup, executable in the case of `<script>`.
     *
     * @param array<string, mixed> $schema
     */
    public function toJsonLd(array $schema): string
    {
        return json_encode($schema, \JSON_HEX_TAG | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT) ?: '{}';
    }
}
