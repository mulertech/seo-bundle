<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Model;

interface BlogPostingSeoInterface
{
    public function getSeoTitle(): string;

    public function getSeoExcerpt(): ?string;

    public function getSeoAuthorName(): string;

    public function getSeoPublishedAt(): ?string;

    public function getSeoUpdatedAt(): ?string;
}
