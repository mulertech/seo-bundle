<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Model;

interface LlmsSectionProviderInterface
{
    /**
     * @return iterable<LlmsSection>
     */
    public function getSections(): iterable;
}
