<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Twig;

use MulerTech\SeoBundle\Model\BlogPostingSeoInterface;
use MulerTech\SeoBundle\Service\SchemaOrgService;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class SeoExtension extends AbstractExtension
{
    public function __construct(
        private readonly SchemaOrgService $schemaOrgService,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return array<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('schema_org_json_ld', $this->schemaOrgJsonLd(...), ['is_safe' => ['html']]),
        ];
    }

    public function schemaOrgJsonLd(string $type, mixed $data = null): string
    {
        $schema = match ($type) {
            'organization' => $this->schemaOrgService->organization(),
            'webSite' => $this->schemaOrgService->webSite($this->getSiteUrl()),
            'blogPosting' => $data instanceof BlogPostingSeoInterface ? $this->schemaOrgService->blogPosting($data, $this->getCurrentUrl()) : [],
            'service' => $this->resolveService($data),
            'breadcrumbList' => $this->resolveBreadcrumbList($data),
            default => [],
        };

        if ([] === $schema) {
            return '';
        }

        return '<script type="application/ld+json">'.$this->schemaOrgService->toJsonLd($schema).'</script>';
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveService(mixed $data): array
    {
        if (!\is_array($data)) {
            return [];
        }

        /** @var array<string, mixed> $serviceData */
        $serviceData = $data;

        return $this->schemaOrgService->service($serviceData, $this->getCurrentUrl());
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveBreadcrumbList(mixed $data): array
    {
        if (!\is_array($data)) {
            return [];
        }

        /** @var array<int, array{label: string, url: ?string}> $items */
        $items = $data;

        return $this->schemaOrgService->breadcrumbList($items);
    }

    private function getSiteUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest()
            ?? throw new \LogicException('SeoExtension requires an active HTTP request — cannot be used in CLI context');

        return $request->getSchemeAndHttpHost();
    }

    private function getCurrentUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest()
            ?? throw new \LogicException('SeoExtension requires an active HTTP request — cannot be used in CLI context');

        return $request->getUri();
    }
}
