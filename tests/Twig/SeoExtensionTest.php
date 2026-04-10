<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Tests\Twig;

use MulerTech\SeoBundle\Model\BlogPostingSeoInterface;
use MulerTech\SeoBundle\Model\SeoCompanyInfoProviderInterface;
use MulerTech\SeoBundle\Service\SchemaOrgService;
use MulerTech\SeoBundle\Twig\SeoExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class SeoExtensionTest extends TestCase
{
    private SeoExtension $extension;

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
        $companyInfo->method('getSocialUrls')->willReturn([]);

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://example.com/page'));

        $schemaOrgService = new SchemaOrgService($companyInfo, $requestStack);
        $this->extension = new SeoExtension($schemaOrgService, $requestStack);
    }

    public function testGetFunctionsRegistersSchemaOrgJsonLd(): void
    {
        $functions = $this->extension->getFunctions();

        self::assertCount(1, $functions);
        self::assertSame('schema_org_json_ld', $functions[0]->getName());
    }

    public function testOrganizationReturnsJsonLdScript(): void
    {
        $result = $this->extension->schemaOrgJsonLd('organization');

        self::assertStringStartsWith('<script type="application/ld+json">', $result);
        self::assertStringEndsWith('</script>', $result);
        self::assertStringContainsString('"@type": "LocalBusiness"', $result);
        self::assertStringContainsString('TestCompany', $result);
    }

    public function testWebSiteReturnsJsonLdScript(): void
    {
        $result = $this->extension->schemaOrgJsonLd('webSite');

        self::assertStringContainsString('"@type": "WebSite"', $result);
        self::assertStringContainsString('https://example.com', $result);
    }

    public function testBlogPostingWithInterface(): void
    {
        $post = $this->createStub(BlogPostingSeoInterface::class);
        $post->method('getSeoTitle')->willReturn('Test Post');
        $post->method('getSeoExcerpt')->willReturn(null);
        $post->method('getSeoAuthorName')->willReturn('Author');
        $post->method('getSeoPublishedAt')->willReturn(null);
        $post->method('getSeoUpdatedAt')->willReturn(null);

        $result = $this->extension->schemaOrgJsonLd('blogPosting', $post);

        self::assertStringContainsString('"@type": "BlogPosting"', $result);
        self::assertStringContainsString('Test Post', $result);
    }

    public function testBlogPostingWithNonInterfaceReturnsEmpty(): void
    {
        $result = $this->extension->schemaOrgJsonLd('blogPosting', 'not-a-post');

        self::assertSame('', $result);
    }

    public function testServiceWithArray(): void
    {
        $result = $this->extension->schemaOrgJsonLd('service', ['title' => 'Dev', 'description' => 'Web dev']);

        self::assertStringContainsString('"@type": "Service"', $result);
        self::assertStringContainsString('Dev', $result);
    }

    public function testServiceWithNonArrayReturnsEmpty(): void
    {
        $result = $this->extension->schemaOrgJsonLd('service', 'invalid');

        self::assertSame('', $result);
    }

    public function testBreadcrumbListWithArray(): void
    {
        $items = [
            ['label' => 'Home', 'url' => 'https://example.com'],
            ['label' => 'Page', 'url' => null],
        ];

        $result = $this->extension->schemaOrgJsonLd('breadcrumbList', $items);

        self::assertStringContainsString('"@type": "BreadcrumbList"', $result);
        self::assertStringContainsString('Home', $result);
    }

    public function testUnknownTypeReturnsEmpty(): void
    {
        $result = $this->extension->schemaOrgJsonLd('unknown');

        self::assertSame('', $result);
    }

    public function testWebSiteThrowsWithoutRequest(): void
    {
        $companyInfo = $this->createStub(SeoCompanyInfoProviderInterface::class);
        $companyInfo->method('getName')->willReturn('TestCompany');

        $schemaOrgService = new SchemaOrgService($companyInfo, new RequestStack());
        $extension = new SeoExtension($schemaOrgService, new RequestStack());

        $this->expectException(\LogicException::class);
        $extension->schemaOrgJsonLd('webSite');
    }

    public function testBlogPostingThrowsWithoutRequest(): void
    {
        $companyInfo = $this->createStub(SeoCompanyInfoProviderInterface::class);
        $companyInfo->method('getName')->willReturn('TestCompany');
        $companyInfo->method('getWebsite')->willReturn('https://example.com');

        $post = $this->createStub(BlogPostingSeoInterface::class);
        $post->method('getSeoTitle')->willReturn('Title');
        $post->method('getSeoExcerpt')->willReturn(null);
        $post->method('getSeoAuthorName')->willReturn('Author');
        $post->method('getSeoPublishedAt')->willReturn(null);
        $post->method('getSeoUpdatedAt')->willReturn(null);

        $schemaOrgService = new SchemaOrgService($companyInfo, new RequestStack());
        $extension = new SeoExtension($schemaOrgService, new RequestStack());

        $this->expectException(\LogicException::class);
        $extension->schemaOrgJsonLd('blogPosting', $post);
    }

    public function testServiceThrowsWithoutRequest(): void
    {
        $companyInfo = $this->createStub(SeoCompanyInfoProviderInterface::class);
        $companyInfo->method('getName')->willReturn('TestCompany');
        $companyInfo->method('getWebsite')->willReturn('https://example.com');

        $schemaOrgService = new SchemaOrgService($companyInfo, new RequestStack());
        $extension = new SeoExtension($schemaOrgService, new RequestStack());

        $this->expectException(\LogicException::class);
        $extension->schemaOrgJsonLd('service', ['title' => 'Dev', 'description' => 'Web']);
    }

    public function testBreadcrumbListWithNonArrayReturnsEmpty(): void
    {
        $result = $this->extension->schemaOrgJsonLd('breadcrumbList', 'invalid');

        self::assertSame('', $result);
    }
}
