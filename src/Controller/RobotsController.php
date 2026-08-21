<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class RobotsController
{
    /**
     * @param list<string>                                                                        $disallowPaths
     * @param list<string>                                                                        $allowPaths
     * @param list<array{user_agents: list<string>, allow: list<string>, disallow: list<string>}> $groups
     */
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private string $environment,
        private array $disallowPaths = ['/admin', '/login'],
        private array $allowPaths = [],
        private array $groups = [],
    ) {
    }

    public function __invoke(): Response
    {
        return new Response($this->content(), Response::HTTP_OK, [
            'Content-Type' => 'text/plain',
        ]);
    }

    private function content(): string
    {
        if ('prod' !== $this->environment) {
            return "User-agent: *\nDisallow: /\n";
        }

        $blocks = [
            $this->group(['*'], $this->rootGroupAllow(), $this->disallowPaths),
        ];

        foreach ($this->groups as $group) {
            $blocks[] = $this->group($group['user_agents'], $group['allow'], $group['disallow']);
        }

        $sitemapUrl = $this->urlGenerator->generate('mulertech_seo_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return implode("\n\n", $blocks)."\n\nSitemap: {$sitemapUrl}\n";
    }

    /**
     * `Allow: /` and `Disallow: /` are the same length, and a tie goes to the least
     * restrictive rule, so a site closing itself through `disallow_paths` would stay
     * crawlable with both lines present. The blanket allow is dropped in that case: it
     * carries no meaning of its own, since a path no rule names is crawlable anyway.
     *
     * @return list<string>
     */
    private function rootGroupAllow(): array
    {
        $allow = \in_array('/', $this->disallowPaths, true) ? $this->allowPaths : ['/', ...$this->allowPaths];

        return array_values(array_unique($allow));
    }

    /**
     * A crawler obeys the one group whose `User-agent` matches it best and ignores every
     * other, `*` included. So a rule meant for a single robot needs a group of its own, and
     * that group repeats whatever of the `*` rules should keep binding it.
     *
     * @param list<string> $userAgents
     * @param list<string> $allow
     * @param list<string> $disallow
     */
    private function group(array $userAgents, array $allow, array $disallow): string
    {
        $lines = array_map(static fn (string $agent): string => 'User-agent: '.$agent, $userAgents);

        foreach ($allow as $path) {
            $lines[] = 'Allow: '.$path;
        }

        foreach ($disallow as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        return implode("\n", $lines);
    }
}
