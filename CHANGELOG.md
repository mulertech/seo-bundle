# Release notes for seo-bundle

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
