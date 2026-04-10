<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Tests\Service;

use MulerTech\SeoBundle\Model\SeoCompanyInfoProviderInterface;
use MulerTech\SeoBundle\Service\MetaTagService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class MetaTagServiceTest extends TestCase
{
    private MetaTagService $service;

    protected function setUp(): void
    {
        $requestStack = new RequestStack();
        $request = Request::create('https://example.com/test');
        $requestStack->push($request);

        $companyInfo = $this->createStub(SeoCompanyInfoProviderInterface::class);
        $companyInfo->method('getName')->willReturn('TestCompany');

        $this->service = new MetaTagService($requestStack, $companyInfo, 'https://example.com/image.jpg');
    }

    public function testGenerateMetaTagsBasic(): void
    {
        $result = $this->service->generateMetaTags([
            'title' => 'Test Page',
            'description' => 'A test description',
            'type' => 'website',
        ]);

        self::assertSame('Test Page', $result['title']);
        self::assertSame('A test description', $result['description']);
        self::assertSame('website', $result['og:type']);
        self::assertSame('Test Page', $result['og:title']);
        self::assertSame('A test description', $result['og:description']);
        self::assertSame('fr_FR', $result['og:locale']);
        self::assertSame('TestCompany', $result['og:site_name']);
        self::assertSame('summary_large_image', $result['twitter:card']);
    }

    public function testTitleTruncation(): void
    {
        $longTitle = str_repeat('A', 100);
        $result = $this->service->generateMetaTags(['title' => $longTitle]);

        self::assertSame(60, mb_strlen($result['title']));
        self::assertStringEndsWith('...', $result['title']);
    }

    public function testDescriptionTruncation(): void
    {
        $longDesc = str_repeat('B', 200);
        $result = $this->service->generateMetaTags(['description' => $longDesc]);

        self::assertSame(160, mb_strlen($result['description']));
        self::assertStringEndsWith('...', $result['description']);
    }

    public function testShortTitleNotTruncated(): void
    {
        $result = $this->service->generateMetaTags(['title' => 'Short']);

        self::assertSame('Short', $result['title']);
    }

    public function testArticleType(): void
    {
        $result = $this->service->generateMetaTags([
            'title' => 'Article Title',
            'type' => 'article',
            'publishedTime' => '2025-01-15T10:00:00+00:00',
            'author' => 'john@example.com',
        ]);

        self::assertSame('article', $result['og:type']);
        self::assertSame('2025-01-15T10:00:00+00:00', $result['article:published_time']);
        self::assertSame('john@example.com', $result['article:author']);
    }

    public function testCustomImage(): void
    {
        $result = $this->service->generateMetaTags([
            'title' => 'Page',
            'image' => 'https://example.com/custom.jpg',
        ]);

        self::assertSame('https://example.com/custom.jpg', $result['og:image']);
        self::assertSame('summary_large_image', $result['twitter:card']);
    }

    public function testCanonicalUrlDefaultsToCurrentRequest(): void
    {
        $result = $this->service->generateMetaTags(['title' => 'Page']);

        self::assertStringContainsString('example.com/test', $result['canonical']);
    }

    public function testNoImageReturnsSummaryCard(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://example.com/test'));

        $companyInfo = $this->createStub(SeoCompanyInfoProviderInterface::class);
        $companyInfo->method('getName')->willReturn('TestCompany');

        $service = new MetaTagService($requestStack, $companyInfo);
        $result = $service->generateMetaTags(['title' => 'Page']);

        self::assertNull($result['og:image']);
        self::assertSame('summary', $result['twitter:card']);
    }

    public function testCustomLocale(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://example.com/test'));

        $companyInfo = $this->createStub(SeoCompanyInfoProviderInterface::class);
        $companyInfo->method('getName')->willReturn('TestCompany');

        $service = new MetaTagService($requestStack, $companyInfo, defaultLocale: 'en_US');
        $result = $service->generateMetaTags(['title' => 'Page']);

        self::assertSame('en_US', $result['og:locale']);
    }

    public function testEmptyOptionsProducesMinimalMeta(): void
    {
        $result = $this->service->generateMetaTags();

        self::assertNull($result['title']);
        self::assertNull($result['description']);
        self::assertSame('website', $result['og:type']);
        self::assertNotNull($result['canonical']);
    }

    public function testNoRequestThrowsLogicException(): void
    {
        $companyInfo = $this->createStub(SeoCompanyInfoProviderInterface::class);
        $companyInfo->method('getName')->willReturn('TestCompany');

        $service = new MetaTagService(new RequestStack(), $companyInfo);

        $this->expectException(\LogicException::class);
        $service->generateMetaTags(['title' => 'Page']);
    }
}
