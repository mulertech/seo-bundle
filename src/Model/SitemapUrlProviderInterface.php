<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Model;

interface SitemapUrlProviderInterface
{
    /**
     * @return iterable<SitemapUrl>
     */
    public function getUrls(): iterable;
}
