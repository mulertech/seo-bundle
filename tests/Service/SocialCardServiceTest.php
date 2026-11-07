<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Tests\Service;

use MulerTech\SeoBundle\Model\SocialCardSource;
use MulerTech\SeoBundle\Model\SocialCardSourceProviderInterface;
use MulerTech\SeoBundle\Service\SocialCardService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\RequestContext;

#[AllowMockObjectsWithoutExpectations]
final class SocialCardServiceTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/social_cards_'.uniqid('', true);
        mkdir($this->directory, 0o775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    /**
     * The address a platform caches is built from this, so it must move when the picture
     * moves and stay put otherwise.
     */
    public function testTheFingerprintFollowsTheVersion(): void
    {
        $service = $this->service();

        self::assertSame($service->fingerprint(new SocialCardSource('/a.webp', 'v1')), $service->fingerprint(new SocialCardSource('/b.webp', 'v1')));
        self::assertNotSame($service->fingerprint(new SocialCardSource('/a.webp', 'v1')), $service->fingerprint(new SocialCardSource('/a.webp', 'v2')));
    }

    public function testTheFingerprintIsShortAndUrlSafe(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $this->service()->fingerprint(new SocialCardSource('/a.webp', 'v1')));
    }

    public function testMetaTagOptionsDeclareAnAbsoluteJpegAddressWithItsSize(): void
    {
        $service = $this->service([$this->provider('blog')]);
        $source = new SocialCardSource('/a.webp', 'v1');

        self::assertSame([
            'image' => 'https://example.com/social-card/blog/image-6304-'.$service->fingerprint($source).'.jpg',
            'imageWidth' => 1200,
            'imageHeight' => 630,
            'imageType' => 'image/jpeg',
        ], $service->metaTagOptions('blog', 6304, $source));
    }

    /**
     * A page declaring a card no route can serve would publish a broken image with nothing
     * to show for it in the markup, so the mistake surfaces while rendering the page.
     */
    public function testMetaTagOptionsRejectAnUndeclaredTypeListingTheDeclaredOnes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No social card provider declares the type "news". Declared types: "blog", "portfolio".');

        $this->service([$this->provider('blog'), $this->provider('portfolio')])->metaTagOptions('news', 1, new SocialCardSource('/a.webp', 'v1'));
    }

    public function testTwoProvidersCannotShareAType(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('declare the social card type "blog"');

        $this->service([$this->provider('blog'), $this->provider('blog')])->provider('blog');
    }

    public function testATypeTheRouteCannotMatchIsRejected(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('declares the social card type "Blog posts"');

        $this->service([$this->provider('Blog posts')])->provider('blog');
    }

    public function testAProviderIsFoundByItsType(): void
    {
        $blog = $this->provider('blog');

        self::assertSame($blog, $this->service([$blog])->provider('blog'));
        self::assertNull($this->service([$blog])->provider('portfolio'));
    }

    /**
     * Whatever the shape of the source, the card comes out at the one size the platforms
     * lay out a large card with.
     *
     * @param positive-int $width
     * @param positive-int $height
     */
    #[DataProvider('sourceShapes')]
    public function testAnySourceYieldsACardOfTheExpectedSize(int $width, int $height): void
    {
        $service = $this->service();
        $source = new SocialCardSource($this->writeSource($width, $height), 'v1');

        self::assertTrue($service->generate($source, 'blog', '6304'));

        $size = getimagesize($this->directory.'/blog/image-6304-'.$service->fingerprint($source).'.jpg');

        self::assertIsArray($size);
        self::assertSame(SocialCardService::WIDTH, $size[0]);
        self::assertSame(SocialCardService::HEIGHT, $size[1]);
        self::assertSame('image/jpeg', $size['mime']);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function sourceShapes(): iterable
    {
        yield 'landscape' => [1600, 900];
        yield 'portrait' => [900, 1600];
        yield 'square' => [1000, 1000];
        yield 'smaller than the card' => [400, 300];
        yield 'panoramic' => [3000, 500];
    }

    public function testTransparencyIsFlattenedOntoTheBackground(): void
    {
        $path = $this->directory.'/transparent.png';
        $image = imagecreatetruecolor(1200, 630);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagepng($image, $path);

        $service = $this->service(background: 0x002C54);
        $source = new SocialCardSource($path, 'v1');
        $service->generate($source, 'blog', '1');

        $card = imagecreatefromjpeg($this->directory.'/blog/image-1-'.$service->fingerprint($source).'.jpg');
        self::assertInstanceOf(\GdImage::class, $card);
        $colour = imagecolorsforindex($card, (int) imagecolorat($card, 600, 315));

        self::assertEqualsWithDelta(0x00, $colour['red'], 4);
        self::assertEqualsWithDelta(0x2C, $colour['green'], 4);
        self::assertEqualsWithDelta(0x54, $colour['blue'], 4);
    }

    /**
     * A card that cannot be derived leaves every share falling back to the generic image.
     * Nothing on the page would reveal it, so the failure has to be reported.
     */
    public function testAnUnreadableSourceIsReported(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Failed to read the source image of a social card');

        self::assertFalse($this->service(logger: $logger)->generate(new SocialCardSource($this->directory.'/nowhere.webp', 'v1'), 'blog', '6304'));
    }

    public function testASourceThatIsNotAnImageIsReported(): void
    {
        file_put_contents($this->directory.'/notes.txt', 'not an image');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Failed to read the source image of a social card');

        self::assertFalse($this->service(logger: $logger)->generate(new SocialCardSource($this->directory.'/notes.txt', 'v1'), 'blog', '6304'));
    }

    public function testAnUnusableTargetDirectoryIsReported(): void
    {
        file_put_contents($this->directory.'/blog', 'not a directory');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Failed to create the social card directory');

        self::assertFalse($this->service(logger: $logger)->generate(new SocialCardSource($this->writeSource(800, 600), 'v1'), 'blog', '6304'));
    }

    public function testATargetThatCannotBeWrittenIsReported(): void
    {
        $service = $this->service();
        $source = new SocialCardSource($this->writeSource(800, 600), 'v1');
        mkdir($service->path('blog', '6304', $service->fingerprint($source)), 0o775, true);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Failed to write a social card');

        self::assertFalse($this->service(logger: $logger)->generate($source, 'blog', '6304'));
    }

    /**
     * One live card per page: without this the directory keeps every illustration a page
     * ever had, and nothing ever reclaims them.
     */
    public function testReplacingAnIllustrationDiscardsThePreviousCard(): void
    {
        $service = $this->service();
        $path = $this->writeSource(1600, 900);
        $first = new SocialCardSource($path, 'v1');
        $second = new SocialCardSource($path, 'v2');

        $service->generate($first, 'blog', '6304');
        $service->generate($second, 'blog', '6304');

        self::assertFileDoesNotExist($service->path('blog', '6304', $service->fingerprint($first)));
        self::assertFileExists($service->path('blog', '6304', $service->fingerprint($second)));
    }

    /**
     * Ids may hold dashes, so "12" is a prefix of "12-ab": discarding the cards of one must
     * leave the other alone, and so must a page of another type sharing the id.
     */
    public function testTheCardsOfOtherPagesAreKept(): void
    {
        $service = $this->service();
        $source = new SocialCardSource($this->writeSource(1600, 900), 'v1');
        $replacement = new SocialCardSource($source->path, 'v2');

        $service->generate($source, 'blog', '12-ab');
        $service->generate($source, 'portfolio', '12');
        $service->generate($source, 'blog', '12');
        $service->generate($replacement, 'blog', '12');

        self::assertFileExists($service->path('blog', '12-ab', $service->fingerprint($source)));
        self::assertFileExists($service->path('portfolio', '12', $service->fingerprint($source)));
        self::assertFileDoesNotExist($service->path('blog', '12', $service->fingerprint($source)));
    }

    /**
     * @param list<SocialCardSourceProviderInterface> $providers
     */
    private function service(array $providers = [], ?LoggerInterface $logger = null, int $background = 0xFFFFFF): SocialCardService
    {
        $routes = new YamlFileLoader(new FileLocator(\dirname(__DIR__, 2).'/config'))->load('routes.yaml');

        return new SocialCardService(
            $providers,
            new UrlGenerator($routes, new RequestContext(host: 'example.com', scheme: 'https')),
            $logger ?? $this->createStub(LoggerInterface::class),
            $this->directory,
            $background,
        );
    }

    private function provider(string $type): SocialCardSourceProviderInterface
    {
        $provider = $this->createStub(SocialCardSourceProviderInterface::class);
        $provider->method('getSocialCardType')->willReturn($type);

        return $provider;
    }

    private function writeSource(int $width, int $height): string
    {
        \assert($width > 0 && $height > 0);
        $path = $this->directory.'/source-'.$width.'x'.$height.'.webp';
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, 0x3366CC);
        imagewebp($image, $path);

        return $path;
    }

    private function removeDirectory(string $directory): void
    {
        if ('' === $directory || !is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            \assert($item instanceof \SplFileInfo);
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
