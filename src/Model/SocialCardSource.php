<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Model;

/**
 * The illustration a social card is derived from.
 */
final readonly class SocialCardSource
{
    /**
     * @param string $path    absolute path of the image on disk, in any format GD reads
     * @param string $version changes exactly when the image changes, and never otherwise:
     *                        a stored filename, a size and an upload time, for instance.
     *                        It ends up in the card's address, so it should be read from
     *                        what the page already holds rather than from the disk
     */
    public function __construct(
        public string $path,
        public string $version,
    ) {
    }
}
