# Type quality and SEO audit — 2026-10-01

Target: https://thekpopblog.com (WordPress plugin, primary production runtime).
Reference: [claude-seo](https://github.com/AgriciDaniel/claude-seo), inspected at the revision recorded below. Applied technical, schema, and sitemap guidance to this publisher; no third-party installer or paid API was run.

## Observed baseline

- TypeScript: eight errors. The runtime bundle was forced into inferred demo types, losing the optional video slug; the personalization link used an invalid typed route.
- ESLint: 1,146 findings (1,105 formatting errors, explicit any casts, empty catch blocks, React refresh exports and hook dependencies). No lint rule was disabled for this work.
- Production HTTP sample: `/videos`, `/charts`, `/polls`, `/community`, `/member/...` and informational routes lacked complete initial metadata/content. An unknown URL returned HTTP 200. The sitemap contained the token-bearing newsletter management page.
- WordPress HTML metadata and client route metadata had separate owners; client navigation could replace a descriptive server title with generic route metadata or leave conflicting tags.

## Changes

- Correct runtime video types, remove unsafe `any` casts, type navigation parameters, repair quiz state updates, and separate hooks/providers and exported style helpers for React refresh. Normalize existing first-party formatting.
- `npm run check`: strict TypeScript over application, WordPress build config and AI scripts; ESLint with zero-warning policy; static-page SEO parity check. GitHub Actions also builds both targets, checks PHP syntax, and runs disposable WordPress integration tests.
- Public routes now return complete first-response titles, descriptions, canonical URLs, Open Graph/Twitter previews, readable body content and JSON-LD. Static informational content is exported directly from the React source instead of maintained twice.
- WordPress metadata remains authoritative after SPA navigation. Initial metadata is injected with the page; later navigation fetches the same public metadata generator with cancellation and duplicate removal.
- Missing pages return 404 + noindex. Account/search/newsletter management pages remain usable but are noindexed and omitted from the sitemap. Password-protected content is excluded. WordPress's own search visibility setting is respected.
- Add visible primary H1 headings, absolute canonical/schema URLs and breadcrumbs. NewsArticle uses a real publisher/logo and author link; YouTube embeds no longer claim a watch page as a media file. Release schedules use CreativeWork rather than claiming an attended event without a location. Existing factual FAQ markup is retained without promising rich-result benefits.
- Sitemap covers all current published public records rather than silently truncating each content type at 2,000; UTC lastmod values are emitted correctly. General and News sitemap formats and robots declarations are checked.
- About page explains source collection and AI-assisted publishing and links corrections, contact and community standards. No invented credentials, human-review claims, reviews or scores are added.
- React runtime is split into a reusable vendor chunk; both build targets finish without the previous oversized-chunk warning.

## Validation

- `npm run check`: zero type errors and zero ESLint errors/warnings.
- `npm run build` and `bash wordpress-plugin/build-plugin.sh`: successful.
- PHP syntax checks: all plugin PHP files successful.
- Disposable WordPress SEO integration: 122 assertions, including initial HTML, missing/protected content, public metadata API and validation of local path arguments.
- Existing analytics integration: consent, deduplication, windows/timezone, retention, role checks and icon behavior pass.
- Local sitemap: all 217 URLs checked, no failures. Chrome confirms one canonical, one description, one JSON-LD block and the current title after SPA navigation; no console errors in tested flows.
- Read-only operating-site verification: `python3 scripts/seo/verify-site.py https://thekpopblog.com --all --output /tmp/kpop-production-seo.json`.

- Public PageSpeed Insights request returned HTTP 429 (quota); no lab/field performance score is claimed.

## Evidence limits and ongoing work

No search ranking or indexing score is invented. Search Console property verification, URL Inspection/coverage, ranking changes and 75th-percentile real-user Core Web Vitals require the corresponding production data and monitoring over time. Language selection currently translates UI labels on the same URL; it is not a set of translated article URLs, so hreflang is deliberately not fabricated. llms.txt is a discovery aid, not a promised ranking/citation signal. Local-business and ecommerce schema do not apply to this publisher.

Primary references: [Google JavaScript SEO](https://developers.google.com/search/docs/crawling-indexing/javascript/javascript-seo-basics), [Article structured data](https://developers.google.com/search/docs/appearance/structured-data/article), [Video structured data](https://developers.google.com/search/docs/appearance/structured-data/video), [Sitemap guidance](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap).

Reference repository revision: `ff87fcee0734845d3f59128c8c905799ee2298da`.
