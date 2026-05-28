<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Model;

final readonly class LlmsLink
{
    public function __construct(
        private string $url,
        private string $title,
        private string $description = '',
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
