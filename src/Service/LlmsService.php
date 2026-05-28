<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Service;

use MulerTech\SeoBundle\Model\LlmsLink;
use MulerTech\SeoBundle\Model\LlmsSection;
use MulerTech\SeoBundle\Model\LlmsSectionProviderInterface;
use MulerTech\SeoBundle\Model\SeoCompanyInfoProviderInterface;

final readonly class LlmsService
{
    /**
     * @param array<string, array{priority: int, links: array<int, array{url: string, title: string, description: string}>}> $staticSections
     * @param iterable<LlmsSectionProviderInterface>                                                                         $sectionProviders
     */
    public function __construct(
        private SeoCompanyInfoProviderInterface $companyInfoProvider,
        private ?string $title,
        private string $summary,
        private string $notes,
        private array $staticSections,
        private iterable $sectionProviders,
    ) {
    }

    public function generate(): string
    {
        $title = (null === $this->title || '' === $this->title)
            ? $this->companyInfoProvider->getName()
            : $this->title;

        $content = '# '.$title."\n";

        if ('' !== $this->summary) {
            $content .= "\n> ".$this->summary."\n";
        }

        if ('' !== $this->notes) {
            $content .= "\n".$this->notes."\n";
        }

        $sections = iterator_to_array($this->collectSections(), false);
        usort($sections, static fn (LlmsSection $a, LlmsSection $b): int => $b->getPriority() <=> $a->getPriority());

        foreach ($sections as $section) {
            $content .= "\n## ".$section->getHeading()."\n";

            foreach ($section->getLinks() as $link) {
                $content .= '- ['.$link->getTitle().']('.$link->getUrl().')';

                if ('' !== $link->getDescription()) {
                    $content .= ': '.$link->getDescription();
                }

                $content .= "\n";
            }
        }

        return $content;
    }

    /**
     * @return iterable<LlmsSection>
     */
    private function collectSections(): iterable
    {
        foreach ($this->staticSections as $heading => $section) {
            $linkObjects = array_map(
                static fn (array $link): LlmsLink => new LlmsLink($link['url'], $link['title'], $link['description']),
                $section['links'],
            );

            yield new LlmsSection($heading, $linkObjects, $section['priority']);
        }

        foreach ($this->sectionProviders as $provider) {
            yield from $provider->getSections();
        }
    }
}
