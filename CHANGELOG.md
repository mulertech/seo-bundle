# Release notes for seo-bundle

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
