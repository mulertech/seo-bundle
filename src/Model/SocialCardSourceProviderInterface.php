<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Model;

/**
 * Hands the social card route the illustration of one kind of page.
 */
interface SocialCardSourceProviderInterface
{
    /**
     * Names the kind of page in the card's address and storage directory: `blog` serves
     * `/social-card/blog/image-{id}-{fingerprint}.jpg`. Lowercase letters, digits and dashes.
     */
    public function getSocialCardType(): string;

    /**
     * Null when the page does not exist, has no illustration, or must not be shared yet,
     * a draft for instance: the route then answers 404.
     */
    public function findSocialCardSource(string $id): ?SocialCardSource;
}
