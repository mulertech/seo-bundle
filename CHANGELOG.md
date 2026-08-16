# Release notes for seo-bundle

## v1.3.0 - 2026-08-16

Canonical and `og:url` no longer carry tracking parameters.
They were built from the full request URI, so a page reached through `?utm_source=…` or `?fbclid=…` declared the tracked address as its canonical one.
Search engines then treat that address as the page's official URL, and anyone resharing from it propagates the parameter, crediting a campaign with visits that came from somewhere else.
Only parameters naming a *source* are removed. Parameters that change what the page shows are kept: canonicalising `/blog?page=2` to `/blog` would declare page 2 a duplicate of page 1 and drop it from the index.

- Defaults, exposed as `MetaTagService::TRACKING_PARAMETERS`: `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `utm_id`, `gclid`, `gbraid`, `wbraid`, `fbclid`, `msclkid`, `ttclid`, `twclid`, `igshid`, `mc_cid`, `mc_eid`, `yclid`, `_ga`, `ref_src`
- New `canonical_ignored_parameters` config key for site-specific parameters. Declaring it **replaces** the default list rather than adding to it, so repeat the defaults you still want
- No breaking change: the constructor argument is optional, the config key defaults to the list above, and an application that declares neither behaves as before on clean URLs

## v1.2.0 - 2026-06-04

Add Symfony 8 support — the bundle now allows `^8.0` (tested against Symfony 8.1) alongside the existing `^6.4 || ^7.0` constraints. No breaking changes.

## v1.1.0 - 2026-05-28

Add `/llms.txt` support — environment-agnostic, provider pattern like the sitemap.

- Curated link sections configurable under `llms` (title, summary, notes, sections)
- Dynamic sections via auto-tagged collectors (`LlmsSectionProviderInterface`)
- Per-section `priority` so curated and provider sections can be interleaved freely
- Route toggleable through `llms.enabled`

## v1.0.3 - 2026-04-20

SearchAction `target` is now an `EntryPoint` object with `urlTemplate`, per schema.org spec — prevents search engines from crawling the literal `{search_term_string}` placeholder URL.

## v1.0.2 - 2026-04-10

Register Sitemap and Robots controllers as public services in MulerTechSeoBundle

## v1.0.1 - 2026-04-10

Remove export-ignore for templates in .gitattributes

## v1.0.0 - 2026-04-10

Features

- Meta tags — OpenGraph, Twitter Cards, canonical URL, with automatic title/description truncation
- Schema.org JSON-LD — LocalBusiness/Organization, WebSite with SearchAction, BreadcrumbList, BlogPosting, Service
- Sitemap XML — provider pattern with auto-tagged collectors (SitemapUrlProviderInterface)
- Robots.txt — environment-aware (prod: allow + sitemap reference, non-prod: disallow all)
- Twig integration — schema_org_json_ld() function and seo_meta.html.twig partial
- Fully configurable — organization type, areas served, offers, disallow paths, default image/locale
- Decoupled — interfaces for company info (SeoCompanyInfoProviderInterface) and blog posts (BlogPostingSeoInterface)
- Optional SEO fields — SeoFieldsTrait adds metaDescription/metaKeywords to any Doctrine entity
