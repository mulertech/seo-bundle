<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Tests\Service;

use MulerTech\SeoBundle\Model\BlogPostingSeoInterface;
use MulerTech\SeoBundle\Model\SeoCompanyInfoProviderInterface;
use MulerTech\SeoBundle\Service\SchemaOrgService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class SchemaOrgServiceTest extends TestCase
{
    private SchemaOrgService $service;

    protected function setUp(): void
    {
        $companyInfo = $this->createStub(SeoCompanyInfoProviderInterface::class);
        $companyInfo->method('getName')->willReturn('TestCompany');
        $companyInfo->method('getWebsite')->willReturn('https://example.com');
        $companyInfo->method('getEmail')->willReturn('contact@example.com');
        $companyInfo->method('getPhone')->willReturn('+33 1 23 45 67 89');
        $companyInfo->method('getPostalCode')->willReturn('75001');
        $companyInfo->method('getCity')->willReturn('Paris');
        $companyInfo->method('getCountry')->willReturn('France');
        $companyInfo->method('getSocialUrls')->willReturn([
            'linkedin' => 'https://linkedin.com/company/test',
            'github' => 'https://github.com/test',
            'facebook' => '',
        ]);

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://example.com/current-page'));

        $this->service = new SchemaOrgService(
            $companyInfo,
            $requestStack,
            organizationDescription: 'Test company description',
            organizationType: 'LocalBusiness',
            priceRange: '€€',
            addressRegion: 'Ile-de-France',
            areasServed: [
                ['type' => 'City', 'name' => 'Paris'],
                ['type' => 'Country', 'name' => 'France'],
            ],
            offerNames: ['Web Development', 'Hosting'],
            searchActionPathTemplate: '/blog?q={search_term_string}',
        );
    }

    public function testOrganization(): void
    {
        $result = $this->service->organization();

        self::assertSame('https://schema.org', $result['@context']);
        self::assertSame('LocalBusiness', $result['@type']);
        self::assertSame('TestCompany', $result['name']);
        self::assertSame('https://example.com', $result['url']);
        self::assertSame('contact@example.com', $result['email']);
        self::assertSame('+33 1 23 45 67 89', $result['telephone']);
        self::assertSame('Test company description', $result['description']);
        self::assertIsArray($result['address']);
        self::assertSame('PostalAddress', $result['address']['@type']);
        self::assertSame('75001', $result['address']['postalCode']);
        self::assertSame('Paris', $result['address']['addressLocality']);
        self::assertSame('Ile-de-France', $result['address']['addressRegion']);
        self::assertSame('€€', $result['priceRange']);
        self::assertCount(2, $result['areaServed']);
        self::assertCount(2, $result['sameAs']);
        self::assertArrayHasKey('hasOfferCatalog', $result);
        self::assertCount(2, $result['hasOfferCatalog']['itemListElement']);
    }

    public function testWebSite(): void
    {
        $result = $this->service->webSite('https://example.com');

        self::assertSame('WebSite', $result['@type']);
        self::assertSame('https://example.com', $result['url']);
        self::assertSame('TestCompany', $result['name']);
        self::assertArrayHasKey('potentialAction', $result);
        self::assertSame('SearchAction', $result['potentialAction']['@type']);
        self::assertSame('EntryPoint', $result['potentialAction']['target']['@type']);
        self::assertSame('https://example.com/blog?q={search_term_string}', $result['potentialAction']['target']['urlTemplate']);
    }

    public function testWebSiteWithoutSearchAction(): void
    {
        $companyInfo = $this->createStub(SeoCompanyInfoProviderInterface::class);
        $companyInfo->method('getName')->willReturn('TestCompany');

        $service = new SchemaOrgService($companyInfo, new RequestStack());
        $result = $service->webSite('https://example.com');

        self::assertArrayNotHasKey('potentialAction', $result);
    }

    public function testBreadcrumbList(): void
    {
        $items = [
            ['label' => 'Home', 'url' => 'https://example.com'],
            ['label' => 'Blog', 'url' => null],
        ];

        $result = $this->service->breadcrumbList($items);

        self::assertSame('BreadcrumbList', $result['@type']);
        self::assertCount(2, $result['itemListElement']);
        self::assertSame(1, $result['itemListElement'][0]['position']);
        self::assertSame('Home', $result['itemListElement'][0]['name']);
        self::assertSame('https://example.com', $result['itemListElement'][0]['item']);
        self::assertSame(2, $result['itemListElement'][1]['position']);
        self::assertSame('Blog', $result['itemListElement'][1]['name']);
        self::assertSame('https://example.com/current-page', $result['itemListElement'][1]['item']);
    }

    public function testBlogPosting(): void
    {
        $post = $this->createStub(BlogPostingSeoInterface::class);
        $post->method('getSeoTitle')->willReturn('Test Article');
        $post->method('getSeoExcerpt')->willReturn('Test excerpt');
        $post->method('getSeoAuthorName')->willReturn('John Doe');
        $post->method('getSeoPublishedAt')->willReturn('2025-01-15T00:00:00+00:00');
        $post->method('getSeoUpdatedAt')->willReturn(null);

        $result = $this->service->blogPosting($post, 'https://example.com/blog/test');

        self::assertSame('BlogPosting', $result['@type']);
        self::assertSame('Test Article', $result['headline']);
        self::assertSame('https://example.com/blog/test', $result['url']);
        self::assertSame('John Doe', $result['author']['name']);
        self::assertSame('TestCompany', $result['publisher']['name']);
        self::assertSame('2025-01-15T00:00:00+00:00', $result['datePublished']);
        self::assertArrayNotHasKey('dateModified', $result);
        self::assertSame('Test excerpt', $result['description']);
    }

    public function testBlogPostingMinimal(): void
    {
        $post = $this->createStub(BlogPostingSeoInterface::class);
        $post->method('getSeoTitle')->willReturn('Minimal Post');
        $post->method('getSeoExcerpt')->willReturn(null);
        $post->method('getSeoAuthorName')->willReturn('Author');
        $post->method('getSeoPublishedAt')->willReturn(null);
        $post->method('getSeoUpdatedAt')->willReturn(null);

        $result = $this->service->blogPosting($post, 'https://example.com/blog/minimal');

        self::assertSame('Minimal Post', $result['headline']);
        self::assertArrayNotHasKey('datePublished', $result);
        self::assertArrayNotHasKey('dateModified', $result);
        self::assertArrayNotHasKey('description', $result);
    }

    public function testService(): void
    {
        $serviceData = [
            'title' => 'Web Development',
            'description' => 'Custom web applications',
        ];

        $result = $this->service->service($serviceData, 'https://example.com/services/dev');

        self::assertSame('Service', $result['@type']);
        self::assertSame('Web Development', $result['name']);
        self::assertSame('Custom web applications', $result['description']);
        self::assertSame('https://example.com/services/dev', $result['url']);
        self::assertSame('TestCompany', $result['provider']['name']);
        self::assertCount(2, $result['areaServed']);
    }

    public function testToJsonLd(): void
    {
        $schema = ['@type' => 'Test', 'name' => 'Éléphant'];
        $json = $this->service->toJsonLd($schema);

        self::assertStringContainsString('"@type": "Test"', $json);
        self::assertStringContainsString('Éléphant', $json);
    }

    public function testBlogPostingWithUpdatedAt(): void
    {
        $post = $this->createStub(BlogPostingSeoInterface::class);
        $post->method('getSeoTitle')->willReturn('Updated Post');
        $post->method('getSeoExcerpt')->willReturn(null);
        $post->method('getSeoAuthorName')->willReturn('Author');
        $post->method('getSeoPublishedAt')->willReturn('2025-01-15T00:00:00+00:00');
        $post->method('getSeoUpdatedAt')->willReturn('2025-02-20T00:00:00+00:00');

        $result = $this->service->blogPosting($post, 'https://example.com/blog/updated');

        self::assertSame('2025-02-20T00:00:00+00:00', $result['dateModified']);
    }

    public function testServiceWithoutAreasServed(): void
    {
        $companyInfo = $this->createStub(SeoCompanyInfoProviderInterface::class);
        $companyInfo->method('getName')->willReturn('Minimal');
        $companyInfo->method('getWebsite')->willReturn('https://minimal.com');

        $service = new SchemaOrgService($companyInfo, new RequestStack());
        $result = $service->service(['title' => 'Dev', 'description' => 'Web'], 'https://minimal.com/dev');

        self::assertArrayNotHasKey('areaServed', $result);
    }

    public function testOrganizationMinimal(): void
    {
        $companyInfo = $this->createStub(SeoCompanyInfoProviderInterface::class);
        $companyInfo->method('getName')->willReturn('Minimal');
        $companyInfo->method('getWebsite')->willReturn('https://minimal.com');
        $companyInfo->method('getEmail')->willReturn('hi@minimal.com');
        $companyInfo->method('getPhone')->willReturn('');
        $companyInfo->method('getPostalCode')->willReturn('');
        $companyInfo->method('getCity')->willReturn('');
        $companyInfo->method('getCountry')->willReturn('');
        $companyInfo->method('getSocialUrls')->willReturn([]);

        $service = new SchemaOrgService($companyInfo, new RequestStack());
        $result = $service->organization();

        self::assertSame('LocalBusiness', $result['@type']);
        self::assertSame('Minimal', $result['name']);
        self::assertArrayNotHasKey('priceRange', $result);
        self::assertArrayNotHasKey('areaServed', $result);
        self::assertArrayNotHasKey('hasOfferCatalog', $result);
        self::assertArrayNotHasKey('sameAs', $result);
    }
}
