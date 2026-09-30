<?php

declare(strict_types=1);

namespace MulerTech\SeoBundle\Twig;

use MulerTech\SeoBundle\Model\BlogPostingSeoInterface;
use MulerTech\SeoBundle\Service\CurrentUrlTrait;
use MulerTech\SeoBundle\Service\MetaTagService;
use MulerTech\SeoBundle\Service\SchemaOrgService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class SeoExtension extends AbstractExtension
{
    use CurrentUrlTrait;

    private const array TYPES = ['organization', 'webSite', 'blogPosting', 'service', 'breadcrumbList', 'faqPage'];

    /**
     * @param list<string> $ignoredParameters query parameters stripped from the addresses
     *                                        the structured data declares, kept in step
     *                                        with the canonical through the same config key
     */
    public function __construct(
        private readonly SchemaOrgService $schemaOrgService,
        private readonly RequestStack $requestStack,
        private readonly array $ignoredParameters = MetaTagService::TRACKING_PARAMETERS,
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

    /**
     * @param ?string $nonce CSP nonce for the generated `<script>` element. A JSON-LD block
     *                       is data rather than code, so browsers rarely report it as a
     *                       violation, but enforcement is not uniform and a policy naming a
     *                       nonce for `script-src` is written for `<script>` elements
     *                       whatever their type
     */
    public function schemaOrgJsonLd(string $type, mixed $data = null, ?string $nonce = null): string
    {
        $schema = match ($type) {
            'organization' => $this->schemaOrgService->organization(),
            'webSite' => $this->schemaOrgService->webSite($this->getSiteUrl()),
            'blogPosting' => $this->resolveBlogPosting($data),
            'service' => $this->resolveService($data),
            'breadcrumbList' => $this->resolveBreadcrumbList($data),
            'faqPage' => $this->resolveFaqPage($data),
            default => throw new \InvalidArgumentException(\sprintf('Unknown schema.org type "%s" passed to schema_org_json_ld(); valid types: "%s".', $type, implode('", "', self::TYPES))),
        };

        if ([] === $schema) {
            return '';
        }

        $nonceAttribute = null !== $nonce && '' !== $nonce
            ? ' nonce="'.htmlspecialchars($nonce, \ENT_QUOTES, 'UTF-8').'"'
            : '';

        return '<script type="application/ld+json"'.$nonceAttribute.'>'.$this->schemaOrgService->toJsonLd($schema).'</script>';
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveBlogPosting(mixed $data): array
    {
        if (!$data instanceof BlogPostingSeoInterface) {
            throw new \InvalidArgumentException(\sprintf('schema_org_json_ld("blogPosting") expects an object implementing %s, got %s.', BlogPostingSeoInterface::class, get_debug_type($data)));
        }

        return $this->schemaOrgService->blogPosting($data, $this->getCurrentUrl());
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveService(mixed $data): array
    {
        if (!\is_array($data)) {
            throw new \InvalidArgumentException('schema_org_json_ld("service") expects {title: string, description?: string}, got '.get_debug_type($data).'.');
        }

        $serviceData = ['title' => $this->requiredString('service', $data, 'title')];
        $description = $this->optionalString('service', $data, 'description');
        if (null !== $description) {
            $serviceData['description'] = $description;
        }

        return $this->schemaOrgService->service($serviceData, $this->getCurrentUrl());
    }

    /**
     * An empty list renders nothing: Google rejects a `BreadcrumbList` without entries.
     *
     * @return array<string, mixed>
     */
    private function resolveBreadcrumbList(mixed $data): array
    {
        $items = [];
        foreach ($this->listItems('breadcrumbList', $data, '{label: string, url: ?string}') as $index => $item) {
            $items[] = [
                'label' => $this->requiredString('breadcrumbList', $item, 'label', $index),
                'url' => $this->optionalString('breadcrumbList', $item, 'url', $index),
            ];
        }

        return [] === $items ? [] : $this->schemaOrgService->breadcrumbList($items);
    }

    /**
     * An empty list renders nothing, a site without questions having nothing to declare.
     *
     * @return array<string, mixed>
     */
    private function resolveFaqPage(mixed $data): array
    {
        $items = [];
        foreach ($this->listItems('faqPage', $data, '{question: string, answer: string}') as $index => $item) {
            $items[] = [
                'question' => $this->requiredString('faqPage', $item, 'question', $index),
                'answer' => $this->requiredString('faqPage', $item, 'answer', $index),
            ];
        }

        return [] === $items ? [] : $this->schemaOrgService->faqPage($items);
    }

    /**
     * Malformed data throws rather than rendering nothing: a structured data block that
     * silently disappears is only noticed at the next SEO audit.
     *
     * @return list<array<mixed>>
     */
    private function listItems(string $type, mixed $data, string $itemShape): array
    {
        if (!\is_array($data) || !array_is_list($data)) {
            throw new \InvalidArgumentException(\sprintf('schema_org_json_ld("%s") expects a list of %s, got %s.', $type, $itemShape, get_debug_type($data)));
        }

        foreach ($data as $index => $item) {
            if (!\is_array($item)) {
                throw new \InvalidArgumentException(\sprintf('schema_org_json_ld("%s") expects item %d to be %s, got %s.', $type, $index, $itemShape, get_debug_type($item)));
            }
        }

        /* @var list<array<mixed>> $data */
        return $data;
    }

    /**
     * @param array<mixed> $data
     */
    private function requiredString(string $type, array $data, string $key, ?int $index = null): string
    {
        $value = $data[$key] ?? null;
        if (!\is_string($value)) {
            throw new \InvalidArgumentException(\sprintf('%s a string "%s", got %s.', self::expectation($type, $index), $key, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     */
    private function optionalString(string $type, array $data, string $key, ?int $index = null): ?string
    {
        $value = $data[$key] ?? null;
        if (null !== $value && !\is_string($value)) {
            throw new \InvalidArgumentException(\sprintf('%s a string or null "%s", got %s.', self::expectation($type, $index), $key, get_debug_type($value)));
        }

        return $value;
    }

    private static function expectation(string $type, ?int $index): string
    {
        return null === $index
            ? \sprintf('schema_org_json_ld("%s") expects', $type)
            : \sprintf('schema_org_json_ld("%s") expects item %d to hold', $type, $index);
    }

    private function getSiteUrl(): string
    {
        return $this->getRequest()->getSchemeAndHttpHost();
    }

    private function getCurrentUrl(): string
    {
        return $this->currentUrlWithout($this->ignoredParameters, $this->getRequest());
    }

    private function getRequest(): Request
    {
        return $this->requestStack->getCurrentRequest()
            ?? throw new \LogicException('SeoExtension requires an active HTTP request — cannot be used in CLI context');
    }
}
