<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Tests\Service;

use MulerTech\SeoBundle\Model\SeoCompanyInfoProviderInterface;
use MulerTech\SeoBundle\Service\MetaTagService;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * The canonical is what search engines treat as the page's official address, and what
     * a reshare carries. Leaving a tracking parameter in it credits a campaign with
     * visits that came from elsewhere.
     */
    public function testTrackingParametersAreStrippedFromTheCanonical(): void
    {
        $result = $this->metaTagsFor('https://example.com/article?utm_source=newsletter&fbclid=abc123');

        self::assertSame('https://example.com/article', $result['canonical']);
        self::assertSame('https://example.com/article', $result['og:url']);
    }

    /**
     * Dropping every parameter would be simpler and wrong: a paginated page canonicalised
     * to its first page is declared a duplicate of it, and drops out of the index.
     */
    public function testParametersThatChangeThePageAreKept(): void
    {
        $result = $this->metaTagsFor('https://example.com/blog?page=2');

        self::assertSame('https://example.com/blog?page=2', $result['canonical']);
    }

    public function testTrackingParametersAreRemovedWithoutLosingTheOthers(): void
    {
        $result = $this->metaTagsFor('https://example.com/blog?page=2&utm_medium=social&q=symfony');

        self::assertSame('https://example.com/blog?page=2&q=symfony', $result['canonical']);
    }

    public function testSiteSpecificParametersCanBeDeclared(): void
    {
        $result = $this->metaTagsFor('https://example.com/article?s=li', ['s']);

        self::assertSame('https://example.com/article', $result['canonical']);
    }

    /**
     * @param list<string> $ignoredParameters
     *
     * @return array<string, ?string>
     */
    private function metaTagsFor(string $uri, ?array $ignoredParameters = null): array
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create($uri));

        $companyInfo = $this->createStub(SeoCompanyInfoProviderInterface::class);
        $companyInfo->method('getName')->willReturn('TestCompany');

        $service = null === $ignoredParameters
            ? new MetaTagService($requestStack, $companyInfo)
            : new MetaTagService($requestStack, $companyInfo, ignoredParameters: $ignoredParameters);

        return $service->generateMetaTags(['title' => 'Page']);
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

    /**
     * Open Graph needs a fully qualified URL. Facebook resolves a bare path out of
     * leniency, which is what makes the omission so easy to miss: the markup looks right
     * and the preview works on the one platform most people test with, while LinkedIn,
     * WhatsApp and Slack show no image at all.
     */
    public function testRelativeDefaultImageIsMadeAbsolute(): void
    {
        $result = $this->serviceWithImage('/images/card.jpg')->generateMetaTags(['title' => 'Page']);

        self::assertSame('https://example.com/images/card.jpg', $result['og:image']);
        self::assertSame('https://example.com/images/card.jpg', $result['twitter:image']);
    }

    public function testRelativeImageOptionIsMadeAbsolute(): void
    {
        $result = $this->service->generateMetaTags([
            'title' => 'Page',
            'image' => 'social-cards/blog/image-6304-a3f9c1e2.jpg',
        ]);

        self::assertSame('https://example.com/social-cards/blog/image-6304-a3f9c1e2.jpg', $result['og:image']);
    }

    public function testAbsoluteImageIsLeftUntouched(): void
    {
        $result = $this->service->generateMetaTags([
            'title' => 'Page',
            'image' => 'https://cdn.example.net/card.jpg',
        ]);

        self::assertSame('https://cdn.example.net/card.jpg', $result['og:image']);
    }

    public function testProtocolRelativeImageKeepsItsHost(): void
    {
        $result = $this->service->generateMetaTags([
            'title' => 'Page',
            'image' => '//cdn.example.net/card.jpg',
        ]);

        self::assertSame('https://cdn.example.net/card.jpg', $result['og:image']);
    }

    /**
     * Declaring the dimensions lets Facebook decide on the large card during the first
     * crawl. Without them it must fetch the image before deciding, and the first share of
     * a freshly published page is the one that matters most.
     */
    public function testImageDimensionsAndAltAreDeclared(): void
    {
        $result = $this->service->generateMetaTags([
            'title' => 'Page',
            'image' => 'https://example.com/card.jpg',
            'imageWidth' => 1200,
            'imageHeight' => 630,
            'imageAlt' => 'Couverture de l\'article',
        ]);

        self::assertSame('1200', $result['og:image:width']);
        self::assertSame('630', $result['og:image:height']);
        self::assertSame('Couverture de l\'article', $result['og:image:alt']);
        self::assertSame('Couverture de l\'article', $result['twitter:image:alt']);
    }

    /**
     * @param non-empty-string $extension
     */
    #[DataProvider('imageExtensions')]
    public function testImageTypeIsGuessedFromTheExtension(string $extension, ?string $expected): void
    {
        $result = $this->service->generateMetaTags([
            'title' => 'Page',
            'image' => 'https://example.com/card.'.$extension,
        ]);

        self::assertSame($expected, $result['og:image:type']);
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function imageExtensions(): iterable
    {
        yield 'jpg' => ['jpg', 'image/jpeg'];
        yield 'jpeg' => ['jpeg', 'image/jpeg'];
        yield 'png' => ['png', 'image/png'];
        yield 'gif' => ['gif', 'image/gif'];
        yield 'webp' => ['webp', 'image/webp'];
        yield 'uppercase' => ['JPG', 'image/jpeg'];
        yield 'unknown' => ['avif', null];
    }

    public function testImageTypeSurvivesAQueryString(): void
    {
        $result = $this->service->generateMetaTags([
            'title' => 'Page',
            'image' => 'https://example.com/card.png?v=2',
        ]);

        self::assertSame('image/png', $result['og:image:type']);
    }

    public function testImageTypeIsAbsentWhenTheUrlHasNoPath(): void
    {
        $result = $this->service->generateMetaTags([
            'title' => 'Page',
            'image' => 'https://example.com',
        ]);

        self::assertSame('https://example.com', $result['og:image']);
        self::assertNull($result['og:image:type']);
    }

    public function testImageTypeCanBeDeclared(): void
    {
        $result = $this->service->generateMetaTags([
            'title' => 'Page',
            'image' => 'https://example.com/card',
            'imageType' => 'image/jpeg',
        ]);

        self::assertSame('image/jpeg', $result['og:image:type']);
    }

    public function testImageDetailsAreNullWithoutAnImage(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://example.com/test'));

        $companyInfo = $this->createStub(SeoCompanyInfoProviderInterface::class);
        $companyInfo->method('getName')->willReturn('TestCompany');

        $result = (new MetaTagService($requestStack, $companyInfo))->generateMetaTags([
            'title' => 'Page',
            'imageWidth' => 1200,
            'imageAlt' => 'ignoré',
        ]);

        self::assertNull($result['og:image']);
        self::assertNull($result['og:image:width']);
        self::assertNull($result['og:image:height']);
        self::assertNull($result['og:image:type']);
        self::assertNull($result['og:image:alt']);
        self::assertNull($result['twitter:image:alt']);
    }

    public function testEmptyDefaultImageIsTreatedAsNone(): void
    {
        $result = $this->serviceWithImage('')->generateMetaTags(['title' => 'Page']);

        self::assertNull($result['og:image']);
        self::assertSame('summary', $result['twitter:card']);
    }

    public function testTheDefaultImageDeclaresItsConfiguredDimensions(): void
    {
        $result = $this->serviceWithDefaults()->generateMetaTags(['title' => 'Page']);

        self::assertSame('https://example.com/images/og-default.jpg', $result['og:image']);
        self::assertSame('1200', $result['og:image:width']);
        self::assertSame('630', $result['og:image:height']);
        self::assertSame('MulerTech', $result['og:image:alt']);
        self::assertSame('MulerTech', $result['twitter:image:alt']);
    }

    /**
     * The configured dimensions describe the default image alone. Carrying them over to a
     * page that supplies its own would announce the wrong size, and a declared size that
     * contradicts the file is worse than none: the platform lays out the card from the
     * declaration and discovers the mismatch after fetching.
     */
    public function testAPageWithItsOwnImageDoesNotInheritTheDefaultDimensions(): void
    {
        $result = $this->serviceWithDefaults()->generateMetaTags([
            'title' => 'Page',
            'image' => 'https://example.com/card.jpg',
        ]);

        self::assertNull($result['og:image:width']);
        self::assertNull($result['og:image:height']);
        self::assertNull($result['og:image:alt']);
    }

    public function testDeclaredDimensionsWinOverTheConfiguredOnes(): void
    {
        $result = $this->serviceWithDefaults()->generateMetaTags([
            'title' => 'Page',
            'imageWidth' => 800,
            'imageAlt' => 'Autre chose',
        ]);

        self::assertSame('800', $result['og:image:width']);
        self::assertSame('630', $result['og:image:height']);
        self::assertSame('Autre chose', $result['og:image:alt']);
    }

    private function serviceWithDefaults(): MetaTagService
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://example.com/test'));

        $companyInfo = $this->createStub(SeoCompanyInfoProviderInterface::class);
        $companyInfo->method('getName')->willReturn('TestCompany');

        return new MetaTagService(
            $requestStack,
            $companyInfo,
            '/images/og-default.jpg',
            defaultImageWidth: 1200,
            defaultImageHeight: 630,
            defaultImageAlt: 'MulerTech',
        );
    }

    private function serviceWithImage(string $defaultImage): MetaTagService
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://example.com/test'));

        $companyInfo = $this->createStub(SeoCompanyInfoProviderInterface::class);
        $companyInfo->method('getName')->willReturn('TestCompany');

        return new MetaTagService($requestStack, $companyInfo, $defaultImage);
    }
}
