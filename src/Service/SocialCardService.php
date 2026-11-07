<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Service;

use MulerTech\SeoBundle\Model\SocialCardSource;
use MulerTech\SeoBundle\Model\SocialCardSourceProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Derives the image a social platform shows when a page is shared, from the illustration
 * the page already carries.
 *
 * The format is dictated by what the platforms accept rather than by what the site
 * stores: JPEG because LinkedIn refuses WebP, 1200x630 because Facebook falls back to a
 * square thumbnail below 600x315. Transparency is flattened onto the configured
 * background, since a platform composites it against whatever it likes, usually black.
 *
 * Cards are derived and disposable: they hold no row, belong to no one, and are rebuilt
 * on demand, so deleting the whole directory costs nothing but the next render.
 */
final class SocialCardService
{
    public const int WIDTH = 1200;
    public const int HEIGHT = 630;
    public const string ROUTE = 'mulertech_seo_social_card';

    private const int QUALITY = 82;

    /**
     * Enough to make an accidental collision irrelevant (a stale card would only be
     * served for one image whose replacement happened to hash the same way) while staying
     * short enough to read at a glance in an `og:image`.
     */
    private const int FINGERPRINT_LENGTH = 8;

    /**
     * @var array<string, SocialCardSourceProviderInterface>|null
     */
    private ?array $providersByType = null;

    /**
     * @param iterable<SocialCardSourceProviderInterface> $providers
     * @param int                                         $background RGB packed as GD expects on a truecolor image
     */
    public function __construct(
        private readonly iterable $providers,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly string $directory,
        private readonly int $background,
    ) {
    }

    /**
     * Options for `MetaTagService::generateMetaTags()` declaring the card of a page.
     *
     * The dimensions and type travel with the address so Facebook settles on the large
     * card during the first crawl, without downloading the image to decide.
     *
     * @return array{image: string, imageWidth: int, imageHeight: int, imageType: string}
     */
    public function metaTagOptions(string $type, string|int $id, SocialCardSource $source): array
    {
        $this->provider($type) ?? throw new \InvalidArgumentException(sprintf('No social card provider declares the type "%s". Declared types: %s. Implement SocialCardSourceProviderInterface for it.', $type, [] === $this->types() ? 'none' : '"'.implode('", "', $this->types()).'"'));

        return [
            'image' => $this->url($type, (string) $id, $this->fingerprint($source)),
            'imageWidth' => self::WIDTH,
            'imageHeight' => self::HEIGHT,
            'imageType' => 'image/jpeg',
        ];
    }

    /**
     * Changes exactly when the source image changes, and never otherwise.
     *
     * It travels in the card's URL, which is what makes a replaced illustration visible:
     * platforms cache a preview against its address, LinkedIn effectively forever, so a
     * stable URL would keep showing the old picture.
     */
    public function fingerprint(SocialCardSource $source): string
    {
        return substr(hash('xxh3', $source->version), 0, self::FINGERPRINT_LENGTH);
    }

    public function url(string $type, string $id, string $fingerprint): string
    {
        return $this->urlGenerator->generate(self::ROUTE, [
            'type' => $type,
            'id' => $id,
            'fingerprint' => $fingerprint,
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function provider(string $type): ?SocialCardSourceProviderInterface
    {
        return $this->providersByType()[$type] ?? null;
    }

    public function path(string $type, string $id, string $fingerprint): string
    {
        return sprintf('%s/%s/image-%s-%s.jpg', $this->directory, $type, $id, $fingerprint);
    }

    /**
     * Writes the card and reports whether it now exists. A failure is logged rather than
     * swallowed: the caller still has an image to fall back on, so nothing would otherwise
     * reveal that every share is showing the generic one.
     */
    public function generate(SocialCardSource $source, string $type, string $id): bool
    {
        $fingerprint = $this->fingerprint($source);
        $target = $this->path($type, $id, $fingerprint);
        $directory = \dirname($target);

        if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory)) {
            $this->logger->error('Failed to create the social card directory', [
                'directory' => $directory,
            ]);

            return false;
        }

        $contents = @file_get_contents($source->path);
        $image = false === $contents || '' === $contents ? false : @imagecreatefromstring($contents);

        if (false === $image) {
            $this->logger->error('Failed to read the source image of a social card', [
                'source' => $source->path,
                'type' => $type,
                'id' => $id,
            ]);

            return false;
        }

        if (!@imagejpeg($this->cover($image), $target, self::QUALITY)) {
            $this->logger->error('Failed to write a social card', [
                'target' => $target,
            ]);

            return false;
        }

        $this->discardStaleCards($type, $id, $fingerprint);

        return true;
    }

    /**
     * Scales the source until it covers the card, then takes the middle. Fitting it inside
     * instead would leave bands of background on most images, since almost nothing is
     * authored at 1.9:1.
     */
    private function cover(\GdImage $source): \GdImage
    {
        $card = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefill($card, 0, 0, $this->background);
        imagealphablending($card, true);

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = max(self::WIDTH / $sourceWidth, self::HEIGHT / $sourceHeight);
        $cropWidth = (int) round(self::WIDTH / $scale);
        $cropHeight = (int) round(self::HEIGHT / $scale);

        imagecopyresampled(
            $card,
            $source,
            0,
            0,
            (int) round(($sourceWidth - $cropWidth) / 2),
            (int) round(($sourceHeight - $cropHeight) / 2),
            self::WIDTH,
            self::HEIGHT,
            $cropWidth,
            $cropHeight,
        );

        return $card;
    }

    /**
     * One live card per page. Without this the directory keeps every illustration a page
     * ever had, and nothing would ever reclaim them.
     */
    private function discardStaleCards(string $type, string $id, string $fingerprint): void
    {
        $current = $this->path($type, $id, $fingerprint);
        $pattern = sprintf('%s/%s/image-%s-*.jpg', $this->directory, $type, $id);
        // The glob also matches the cards of an id extending this one ("12-ab" for "12"),
        // which must survive: only the id followed by a bare fingerprint is this page.
        $ownCard = sprintf('/^image-%s-[0-9a-f]{%d}\.jpg$/', preg_quote($id, '/'), self::FINGERPRINT_LENGTH);

        foreach (glob($pattern) ?: [] as $card) {
            if ($card !== $current && 1 === preg_match($ownCard, basename($card))) {
                @unlink($card);
            }
        }
    }

    /**
     * @return array<string, SocialCardSourceProviderInterface>
     */
    private function providersByType(): array
    {
        if (null !== $this->providersByType) {
            return $this->providersByType;
        }

        $providersByType = [];

        foreach ($this->providers as $provider) {
            $type = $provider->getSocialCardType();

            if (1 !== preg_match('/^[a-z0-9-]+$/', $type)) {
                throw new \LogicException(sprintf('%s declares the social card type "%s", which the card route cannot match. Use lowercase letters, digits and dashes only.', $provider::class, $type));
            }

            if (isset($providersByType[$type])) {
                throw new \LogicException(sprintf('Both %s and %s declare the social card type "%s". Give each provider its own type.', $providersByType[$type]::class, $provider::class, $type));
            }

            $providersByType[$type] = $provider;
        }

        return $this->providersByType = $providersByType;
    }

    /**
     * @return list<string>
     */
    private function types(): array
    {
        return array_keys($this->providersByType());
    }
}
