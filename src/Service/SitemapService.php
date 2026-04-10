<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Service;

use MulerTech\SeoBundle\Model\SitemapUrlProviderInterface;

final readonly class SitemapService
{
    /**
     * @param iterable<SitemapUrlProviderInterface> $urlProviders
     */
    public function __construct(
        private iterable $urlProviders,
    ) {
    }

    public function generate(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($this->urlProviders as $provider) {
            foreach ($provider->getUrls() as $sitemapUrl) {
                $xml .= '  <url>'."\n";
                $xml .= '    <loc>'.htmlspecialchars($sitemapUrl->getLoc(), \ENT_XML1, 'UTF-8').'</loc>'."\n";
                if (null !== $sitemapUrl->getLastmod()) {
                    $xml .= '    <lastmod>'.htmlspecialchars($sitemapUrl->getLastmod(), \ENT_XML1, 'UTF-8').'</lastmod>'."\n";
                }
                $xml .= '    <changefreq>'.$sitemapUrl->getChangefreq().'</changefreq>'."\n";
                $xml .= '    <priority>'.$sitemapUrl->getPriority().'</priority>'."\n";
                $xml .= '  </url>'."\n";
            }
        }

        $xml .= '</urlset>';

        return $xml;
    }
}
