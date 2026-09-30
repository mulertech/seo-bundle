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

    public function testBlogPostingRejectsAnObjectOutsideTheInterface(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('schema_org_json_ld("blogPosting") expects an object implementing MulerTech\SeoBundle\Model\BlogPostingSeoInterface, got string.');

        $this->extension->schemaOrgJsonLd('blogPosting', 'not-a-post');
    }

    public function testServiceWithArray(): void
    {
        $result = $this->extension->schemaOrgJsonLd('service', ['title' => 'Dev', 'description' => 'Web dev']);

        self::assertStringContainsString('"@type": "Service"', $result);
        self::assertStringContainsString('Dev', $result);
    }

    public function testServiceRejectsDataThatIsNotAnArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('schema_org_json_ld("service") expects {title: string, description?: string}, got string.');

        $this->extension->schemaOrgJsonLd('service', 'invalid');
    }

    public function testServiceRejectsAMissingTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('schema_org_json_ld("service") expects a string "title", got null.');

        $this->extension->schemaOrgJsonLd('service', ['description' => 'Web dev']);
    }

    public function testServiceRejectsADescriptionThatIsNotAString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('schema_org_json_ld("service") expects a string or null "description", got array.');

        $this->extension->schemaOrgJsonLd('service', ['title' => 'Dev', 'description' => ['Web dev']]);
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

    public function testUnknownTypeThrowsListingTheValidTypes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown schema.org type "faq" passed to schema_org_json_ld(); valid types: "organization", "webSite", "blogPosting", "service", "breadcrumbList", "faqPage".');

        $this->extension->schemaOrgJsonLd('faq');
    }

    public function testFaqPageReturnsJsonLdScript(): void
    {
        $result = $this->extension->schemaOrgJsonLd('faqPage', [
            ['question' => 'Quels délais ?', 'answer' => '<p>Deux semaines.</p>'],
        ], 'r4nd0m');

        self::assertStringStartsWith('<script type="application/ld+json" nonce="r4nd0m">', $result);
        self::assertStringContainsString('"@type": "FAQPage"', $result);
        self::assertStringContainsString('"name": "Quels délais ?"', $result);
        self::assertStringContainsString('"text": "\u003Cp\u003EDeux semaines.\u003C/p\u003E"', $result);
    }

    public function testFaqPageWithEmptyListReturnsEmpty(): void
    {
        self::assertSame('', $this->extension->schemaOrgJsonLd('faqPage', []));
    }

    public function testFaqPageRejectsDataThatIsNotAList(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('schema_org_json_ld("faqPage") expects a list of {question: string, answer: string}, got string.');

        $this->extension->schemaOrgJsonLd('faqPage', 'invalid');
    }

    public function testFaqPageRejectsAnItemThatIsNotAnArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('schema_org_json_ld("faqPage") expects item 0 to be {question: string, answer: string}, got string.');

        $this->extension->schemaOrgJsonLd('faqPage', ['invalid']);
    }

    public function testFaqPageRejectsAnItemWithoutAnswer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('schema_org_json_ld("faqPage") expects item 1 to hold a string "answer", got null.');

        $this->extension->schemaOrgJsonLd('faqPage', [
            ['question' => 'Q1', 'answer' => 'A1'],
            ['question' => 'Q2'],
        ]);
    }

    public function testFaqPageRejectsAQuestionThatIsNotAString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('schema_org_json_ld("faqPage") expects item 0 to hold a string "question", got int.');

        $this->extension->schemaOrgJsonLd('faqPage', [['question' => 42, 'answer' => 'A']]);
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

    public function testBreadcrumbListRejectsDataThatIsNotAList(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('schema_org_json_ld("breadcrumbList") expects a list of {label: string, url: ?string}, got string.');

        $this->extension->schemaOrgJsonLd('breadcrumbList', 'invalid');
    }

    public function testBreadcrumbListRejectsAnItemWithoutLabel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('schema_org_json_ld("breadcrumbList") expects item 0 to hold a string "label", got null.');

        $this->extension->schemaOrgJsonLd('breadcrumbList', [['url' => 'https://example.com']]);
    }

    public function testBreadcrumbListRejectsAnUrlThatIsNeitherStringNorNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('schema_org_json_ld("breadcrumbList") expects item 1 to hold a string or null "url", got int.');

        $this->extension->schemaOrgJsonLd('breadcrumbList', [
            ['label' => 'Home', 'url' => 'https://example.com'],
            ['label' => 'Page', 'url' => 42],
        ]);
    }

    public function testBreadcrumbListWithEmptyListReturnsEmpty(): void
    {
        self::assertSame('', $this->extension->schemaOrgJsonLd('breadcrumbList', []));
    }

    public function testJsonLdCarriesNoNonceAttributeByDefault(): void
    {
        $result = $this->extension->schemaOrgJsonLd('organization');

        self::assertStringStartsWith('<script type="application/ld+json">', $result);
    }

    public function testJsonLdCarriesTheGivenNonce(): void
    {
        $result = $this->extension->schemaOrgJsonLd('organization', nonce: 'r4nd0m');

        self::assertStringStartsWith('<script type="application/ld+json" nonce="r4nd0m">', $result);
    }

    public function testJsonLdEscapesTheNonce(): void
    {
        $result = $this->extension->schemaOrgJsonLd('organization', nonce: 'a"><script>alert(1)</script>');

        self::assertStringNotContainsString('<script>alert(1)</script>', $result);
        self::assertStringContainsString('nonce="a&quot;&gt;&lt;script&gt;', $result);
    }

    public function testEmptyNonceAddsNoAttribute(): void
    {
        $result = $this->extension->schemaOrgJsonLd('organization', nonce: '');

        self::assertStringStartsWith('<script type="application/ld+json">', $result);
    }

    /**
     * The canonical drops tracking parameters, and the addresses inside the structured data
     * have to follow: a `Service` url or a breadcrumb entry naming the tracked address
     * contradicts the canonical sitting a few lines above it.
     */
    public function testStructuredDataAddressesDropTrackingParameters(): void
    {
        $companyInfo = $this->createStub(SeoCompanyInfoProviderInterface::class);
        $companyInfo->method('getName')->willReturn('TestCompany');
        $companyInfo->method('getSocialUrls')->willReturn([]);

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://example.com/page?fbclid=IwAR123&page=2'));

        $extension = new SeoExtension(new SchemaOrgService($companyInfo, $requestStack), $requestStack);

        $service = $extension->schemaOrgJsonLd('service', ['title' => 'Audit', 'description' => 'Audit complet']);
        self::assertStringContainsString('"url": "https://example.com/page?page=2"', $service);

        $breadcrumb = $extension->schemaOrgJsonLd('breadcrumbList', [
            ['label' => 'Home', 'url' => 'https://example.com/'],
            ['label' => 'Audit', 'url' => null],
        ]);
        self::assertStringContainsString('"item": "https://example.com/page?page=2"', $breadcrumb);
    }

    public function testIgnoredParametersFollowTheConfiguredList(): void
    {
        $companyInfo = $this->createStub(SeoCompanyInfoProviderInterface::class);
        $companyInfo->method('getName')->willReturn('TestCompany');
        $companyInfo->method('getSocialUrls')->willReturn([]);

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://example.com/page?s=instagram&fbclid=IwAR123'));

        $extension = new SeoExtension(new SchemaOrgService($companyInfo, $requestStack), $requestStack, ['s']);

        $service = $extension->schemaOrgJsonLd('service', ['title' => 'Audit']);

        self::assertStringContainsString('"url": "https://example.com/page?fbclid=IwAR123"', $service);
    }
}
