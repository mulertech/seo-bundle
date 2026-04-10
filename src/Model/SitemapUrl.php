<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Model;

final readonly class SitemapUrl
{
    public function __construct(
        private string $loc,
        private string $priority = '0.5',
        private string $changefreq = 'monthly',
        private ?string $lastmod = null,
    ) {
    }

    public function getLoc(): string
    {
        return $this->loc;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function getChangefreq(): string
    {
        return $this->changefreq;
    }

    public function getLastmod(): ?string
    {
        return $this->lastmod;
    }
}
