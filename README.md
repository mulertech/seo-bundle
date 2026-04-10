# MulerTech SEO Bundle

___
[![Latest Version on Packagist](https://img.shields.io/packagist/v/mulertech/seo-bundle.svg?style=flat-square)](https://packagist.org/packages/mulertech/seo-bundle)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/mulertech/seo-bundle/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mulertech/seo-bundle/actions/workflows/tests.yml)
[![GitHub PHPStan Action Status](https://img.shields.io/github/actions/workflow/status/mulertech/seo-bundle/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/mulertech/seo-bundle/actions/workflows/phpstan.yml)
[![GitHub Security Action Status](https://img.shields.io/github/actions/workflow/status/mulertech/seo-bundle/security.yml?branch=main&label=security&style=flat-square)](https://github.com/mulertech/seo-bundle/actions/workflows/security.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/mulertech/seo-bundle.svg?style=flat-square)](https://packagist.org/packages/mulertech/seo-bundle)
[![Test Coverage](https://raw.githubusercontent.com/mulertech/seo-bundle/badge/badge-coverage.svg)](https://packagist.org/packages/mulertech/seo-bundle)
___

Symfony bundle for SEO management: meta tags (OpenGraph, Twitter Cards), Schema.org JSON-LD structured data, sitemap XML generation, and robots.txt.

## Requirements

- PHP 8.4+
- Symfony 6.4+ or 7.0+

## Installation

```bash
composer require mulertech/seo-bundle
```

## Configuration

```yaml
# config/packages/mulertech_seo.yaml
mulertech_seo:
    default_image: '/images/og-default.webp'  # Default OG/Twitter image
    default_locale: 'fr_FR'                    # Default og:locale
    schema_org:
        organization_type: 'LocalBusiness'
        organization_description: 'Your company description'
        price_range: '€€'
        address_region: 'Normandie'
        search_action_path_template: '/blog?q={search_term_string}'
        areas_served:
            - { type: 'City', name: 'Caen' }
            - { type: 'AdministrativeArea', name: 'Normandie' }
            - { type: 'Country', name: 'France' }
        offer_names:
            - 'Web Development'
            - 'Hosting'
            - 'Maintenance'
    robots:
        disallow_paths:
            - '/admin'
            - '/login'
```

## Usage

### 1. Implement SeoCompanyInfoProviderInterface

The bundle needs company information for meta tags and Schema.org data:

```php
use MulerTech\SeoBundle\Model\SeoCompanyInfoProviderInterface;

class CompanyInfoProvider implements SeoCompanyInfoProviderInterface
{
    public function getName(): string { return 'My Company'; }
    public function getWebsite(): string { return 'https://mycompany.com'; }
    public function getEmail(): string { return 'contact@mycompany.com'; }
    public function getPhone(): string { return '+33 1 23 45 67 89'; }
    public function getPostalCode(): string { return '14000'; }
    public function getCity(): string { return 'Caen'; }
    public function getCountry(): string { return 'France'; }
    public function getSocialUrls(): array {
        return [
            'linkedin' => 'https://linkedin.com/company/mycompany',
            'github' => 'https://github.com/mycompany',
        ];
    }
}
```

Register it as a service aliased to the interface:

```yaml
# config/services.yaml
MulerTech\SeoBundle\Model\SeoCompanyInfoProviderInterface:
    class: App\Seo\CompanyInfoProvider
```

### 2. Generate meta tags in controllers

```php
use MulerTech\SeoBundle\Service\MetaTagService;

class HomeController extends AbstractController
{
    public function index(MetaTagService $metaTagService): Response
    {
        $seo = $metaTagService->generateMetaTags([
            'title' => 'Welcome to My Company',
            'description' => 'We build amazing web applications.',
        ]);

        return $this->render('home/index.html.twig', ['seo' => $seo]);
    }
}
```

Include the meta tags template in your `<head>`:

```twig
{% block seo_meta %}
    {% include '@MulerTechSeo/seo_meta.html.twig' with { seo: seo } %}
{% endblock %}
```

### 3. Schema.org JSON-LD in Twig (requires twig/twig)

```twig
{# Organization + WebSite (global, in base.html.twig) #}
{{ schema_org_json_ld('organization') }}
{{ schema_org_json_ld('webSite') }}

{# Blog posting (in blog/show.html.twig) #}
{{ schema_org_json_ld('blogPosting', post) }}

{# Service (in service/show.html.twig) #}
{{ schema_org_json_ld('service', { title: 'Web Dev', description: 'Custom apps' }) }}

{# Breadcrumbs #}
{{ schema_org_json_ld('breadcrumbList', [
    { label: 'Home', url: path('app_home') },
    { label: 'Blog', url: null }
]) }}
```

For `blogPosting`, your entity must implement `BlogPostingSeoInterface`:

```php
use MulerTech\SeoBundle\Model\BlogPostingSeoInterface;

class BlogPost implements BlogPostingSeoInterface
{
    public function getSeoTitle(): string { return $this->title; }
    public function getSeoExcerpt(): ?string { return $this->excerpt; }
    public function getSeoAuthorName(): string { return $this->author->getFullName(); }
    public function getSeoPublishedAt(): ?string { return $this->publishedAt?->toIso8601String(); }
    public function getSeoUpdatedAt(): ?string { return $this->updatedAt?->toIso8601String(); }
}
```

### 4. Sitemap (provider pattern)

Implement `SitemapUrlProviderInterface` for each content type:

```php
use MulerTech\SeoBundle\Model\SitemapUrl;
use MulerTech\SeoBundle\Model\SitemapUrlProviderInterface;

class BlogSitemapProvider implements SitemapUrlProviderInterface
{
    public function __construct(
        private readonly BlogPostRepository $repository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function getUrls(): iterable
    {
        foreach ($this->repository->findPublished() as $post) {
            yield new SitemapUrl(
                loc: $this->urlGenerator->generate('app_blog_show', ['slug' => $post->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                priority: '0.6',
                changefreq: 'monthly',
                lastmod: $post->getUpdatedAt()?->toIso8601String(),
            );
        }
    }
}
```

Providers implementing `SitemapUrlProviderInterface` are auto-tagged and collected by the sitemap service.

### Routes

The bundle provides routes for `/sitemap.xml` and `/robots.txt`. Import them in your application:

```yaml
# config/routes/mulertech_seo.yaml
mulertech_seo:
    resource: "@MulerTechSeoBundle/config/routes.yaml"
```

### 5. SEO fields trait (optional)

Add `metaDescription` and `metaKeywords` fields to any entity:

```php
use MulerTech\SeoBundle\Model\SeoFieldsTrait;

class BlogPost
{
    use SeoFieldsTrait;
    // Adds: metaDescription, metaKeywords with getters/setters
}
```

## Testing

```bash
./vendor/bin/mtdocker test-ai
```

## License

MIT
