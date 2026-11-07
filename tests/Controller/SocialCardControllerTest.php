<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Tests\Controller;

use MulerTech\SeoBundle\Controller\SocialCardController;
use MulerTech\SeoBundle\Model\SocialCardSource;
use MulerTech\SeoBundle\Model\SocialCardSourceProviderInterface;
use MulerTech\SeoBundle\Service\SocialCardService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\RequestContext;

final class SocialCardControllerTest extends TestCase
{
    private string $directory = '';
    private string $sourcePath = '';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/social_card_controller_'.uniqid('', true);
        mkdir($this->directory, 0o775, true);

        $this->sourcePath = $this->directory.'/cover.webp';
        $image = imagecreatetruecolor(1600, 900);
        imagewebp($image, $this->sourcePath);
    }

    protected function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            \assert($item instanceof \SplFileInfo);
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($this->directory);
    }

    public function testTheCardIsDerivedAndServedForAsLongAsAPlatformCachesIt(): void
    {
        $service = $this->service(new SocialCardSource($this->sourcePath, 'v1'));
        $fingerprint = $service->fingerprint(new SocialCardSource($this->sourcePath, 'v1'));

        $response = new SocialCardController($service, '/images/og.jpg')('blog', '7', $fingerprint);

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame($service->path('blog', '7', $fingerprint), $response->getFile()->getPathname());
        self::assertSame('image/jpeg', $response->headers->get('Content-Type'));
        self::assertSame('true', $response->headers->get(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertTrue($response->headers->hasCacheControlDirective('immutable'));
        self::assertSame('31536000', $response->headers->getCacheControlDirective('max-age'));
    }

    /**
     * An address shared before the illustration changed points the platform at the
     * current card rather than rebuilding the old one.
     */
    public function testAnOutdatedAddressRedirectsPermanentlyToTheCurrentCard(): void
    {
        $source = new SocialCardSource($this->sourcePath, 'v2');
        $service = $this->service($source);

        $response = new SocialCardController($service, '/images/og.jpg')('blog', '7', '00000000');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(301, $response->getStatusCode());
        self::assertSame('https://example.com/social-card/blog/image-7-'.$service->fingerprint($source).'.jpg', $response->getTargetUrl());
    }

    public function testAPageWithoutIllustrationAnswersNotFound(): void
    {
        $response = new SocialCardController($this->service(null), '/images/og.jpg')('blog', '7', '00000000');

        self::assertSame(404, $response->getStatusCode());
    }

    public function testAnUndeclaredTypeAnswersNotFound(): void
    {
        $response = new SocialCardController($this->service(null), '/images/og.jpg')('news', '7', '00000000');

        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * The card's address is cached for good, so the generic image is reached through a
     * redirect nobody keeps: once the cause is fixed, the next crawl gets the real card.
     */
    public function testACardThatCannotBeDerivedFallsBackToTheDefaultImageWithoutCaching(): void
    {
        $source = new SocialCardSource($this->directory.'/missing.webp', 'v1');
        $service = $this->service($source);

        $response = new SocialCardController($service, '/images/og.jpg')('blog', '7', $service->fingerprint($source));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/images/og.jpg', $response->getTargetUrl());
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function testACardThatCannotBeDerivedWithoutDefaultImageFailsLoudly(): void
    {
        $source = new SocialCardSource($this->directory.'/missing.webp', 'v1');
        $service = $this->service($source);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('mulertech_seo.default_image is not configured');

        new SocialCardController($service, null)('blog', '7', $service->fingerprint($source));
    }

    private function service(?SocialCardSource $source): SocialCardService
    {
        $provider = $this->createStub(SocialCardSourceProviderInterface::class);
        $provider->method('getSocialCardType')->willReturn('blog');
        $provider->method('findSocialCardSource')->willReturn($source);

        $routes = new YamlFileLoader(new FileLocator(\dirname(__DIR__, 2).'/config'))->load('routes.yaml');

        return new SocialCardService(
            [$provider],
            new UrlGenerator($routes, new RequestContext(host: 'example.com', scheme: 'https')),
            new NullLogger(),
            $this->directory.'/cards',
            0xFFFFFF,
        );
    }
}
