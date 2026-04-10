<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Model;

interface SeoCompanyInfoProviderInterface
{
    public function getName(): string;

    public function getWebsite(): string;

    public function getEmail(): string;

    public function getPhone(): string;

    public function getPostalCode(): string;

    public function getCity(): string;

    public function getCountry(): string;

    /**
     * @return array<string, string>
     */
    public function getSocialUrls(): array;
}
