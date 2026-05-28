<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Model;

final readonly class LlmsSection
{
    /**
     * @param array<int, LlmsLink> $links
     */
    public function __construct(
        private string $heading,
        private array $links,
        private int $priority = 0,
    ) {
    }

    public function getHeading(): string
    {
        return $this->heading;
    }

    /**
     * @return array<int, LlmsLink>
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }
}
