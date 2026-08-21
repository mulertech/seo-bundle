# MulerTech SEO Bundle

___
[![Latest Version on Packagist](https://img.shields.io/packagist/v/mulertech/seo-bundle.svg?style=flat-square)](https://packagist.org/packages/mulertech/seo-bundle)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/mulertech/seo-bundle/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mulertech/seo-bundle/actions/workflows/tests.yml)
[![GitHub PHPStan Action Status](https://img.shields.io/github/actions/workflow/status/mulertech/seo-bundle/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/mulertech/seo-bundle/actions/workflows/phpstan.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/mulertech/seo-bundle.svg?style=flat-square)](https://packagist.org/packages/mulertech/seo-bundle)
[![Test Coverage](https://raw.githubusercontent.com/mulertech/seo-bundle/badge/badge-coverage.svg)](https://packagist.org/packages/mulertech/seo-bundle)
___

Symfony bundle for SEO management: meta tags (OpenGraph, Twitter Cards), Schema.org JSON-LD structured data, sitemap XML generation, robots.txt, and llms.txt.

## Requirements

- PHP 8.4+
- Symfony 6.4+, 7.0+ or 8.0+

## Installation

```bash
composer require mulertech/seo-bundle
```

## Configuration

```yaml
# config/packages/mulertech_seo.yaml
mulertech_seo:
    # Fallback for og:image / twitter:image. A path is resolved against the host of the
    # current request, so one value serves every environment. Use JPEG or PNG at
    # 1200x630: LinkedIn refuses WebP outright, and Facebook downgrades to a square
    # thumbnail below 600x315.
    default_image: '/images/og-default.jpg'
    default_image_width: 1200
    default_image_height: 630
    default_image_alt: 'MulerTech'
    default_locale: 'fr_FR'  # Default og:locale

    # Query parameters stripped from canonical and og:url. Omit the key to keep the
    # defaults (utm_*, gclid, fbclid, msclkid, ttclid, twclid, igshid, mc_cid, mc_eid,
    # yclid, _ga, ref_src, gbraid, wbraid).
    #
    # Declaring it REPLACES that list rather than adding to it, so repeat the defaults
    # you still want. Never list a parameter that changes what the page shows:
    # canonicalising /blog?page=2 to /blog declares page 2 a duplicate of page 1, and
    # search engines drop it from the index.
    canonical_ignored_parameters: ['utm_source', 'utm_medium', 'utm_campaign', 'fbclid', 's']
    schema_org:
        organization_type: 'LocalBusiness'
        organization_description: 'Your company description'
        price_range: '€€'
        address_region: 'Normandie'
        # Declared as schema.org logo, which Google reads for the knowledge panel. A path is
        # resolved against the host of the current request; a relative URL is ignored, so
        # outside an HTTP context (a console command rendering a template) configure an
        # absolute one.
        logo: '/images/logo.png'
        founder_name: 'Jane Doe'
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
        # Disallow lines of the '*' group, obeyed by every crawler without a group of its own.
        disallow_paths:
            - '/admin'
            - '/login'
        # Extra Allow lines for the '*' group. The longest matching rule wins, so this one
        # reopens a single path inside a disallowed section.
        allow_paths:
            - '/admin/help'
        # A crawler obeys the single group whose User-agent matches it best and ignores every
        # other, '*' included. A rule aimed at one robot therefore needs its own group, and
        # that group repeats whatever of disallow_paths should keep binding that robot.
        groups:
            - user_agents: ['HTTrack', 'WebCopier', 'WebZIP']
              disallow: ['/']
    llms:
        enabled: true                             # Serve the /llms.txt route
        title: 'My Company'                       # H1 (defaults to company name)
        summary: 'We build amazing web apps.'     # Blockquote summary
        notes: 'Optional intro prose.'            # Prose below the summary
        sections:                                 # Curated links, keyed by H2 heading
            Documentation:
                - { url: '/docs', title: 'Docs', description: 'Guides and references' }
            Services:
                - { url: '/services/web', title: 'Web Development' }
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

| Option | Effect |
|---|---|
| `title`, `description` | Truncated to 60 and 160 characters |
| `url` | Overrides the canonical, which otherwise comes from the request |
| `type` | `website` by default; `article` unlocks the three `article:*` options below |
| `image` | Overrides `default_image`. A path is resolved against the current host |
| `imageWidth`, `imageHeight` | Declared as `og:image:width` / `og:image:height`. Worth passing: without them Facebook must download the image before deciding whether it fills a large card, and the first share of a freshly published page is the one that matters |
| | A page that supplies its own `image` never inherits `default_image_width` / `default_image_height` / `default_image_alt`: they describe the fallback image only |
| `imageAlt` | Declared as `og:image:alt` and `twitter:image:alt` |
| `imageType` | Overrides the MIME type, which is otherwise read from the extension |
| `publishedTime`, `modifiedTime`, `author` | `article` type only |

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

A JSON-LD block is data rather than code, so a browser never executes it and usually raises no
Content Security Policy violation. Enforcement is not uniform, though, and a policy naming a nonce
for `script-src` is written for `<script>` elements whatever their type. Passing the nonce settles
the question and keeps the violation reports clean:

```twig
{{ schema_org_json_ld('organization', nonce=csp_nonce('main')) }}
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

### 5. llms.txt (provider pattern)

The `/llms.txt` route serves a Markdown index for LLMs ([llmstxt.org](https://llmstxt.org/)). Static sections come from the `llms.sections` config; dynamic sections are contributed by implementing `LlmsSectionProviderInterface`:

```php
use MulerTech\SeoBundle\Model\LlmsLink;
use MulerTech\SeoBundle\Model\LlmsSection;
use MulerTech\SeoBundle\Model\LlmsSectionProviderInterface;

class BlogLlmsProvider implements LlmsSectionProviderInterface
{
    public function __construct(
        private readonly BlogPostRepository $repository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function getSections(): iterable
    {
        $links = [];
        foreach ($this->repository->findPublished() as $post) {
            $links[] = new LlmsLink(
                url: $this->urlGenerator->generate('app_blog_show', ['slug' => $post->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                title: $post->getTitle(),
                description: $post->getExcerpt(),
            );
        }

        yield new LlmsSection('Blog', $links);
    }
}
```

Providers implementing `LlmsSectionProviderInterface` are auto-tagged; their sections are appended after the static config sections. Set `llms.enabled: false` to return a 404 for the route.

### Routes

The bundle provides routes for `/sitemap.xml`, `/robots.txt`, and `/llms.txt`. Import them in your application:

```yaml
# config/routes/mulertech_seo.yaml
mulertech_seo:
    resource: "@MulerTechSeoBundle/config/routes.yaml"
```

### 6. SEO fields trait (optional)

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
