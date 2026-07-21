# AI Content Discoverability Design

## Objective

Make every automatically published K-pop article, comeback, and concert easy for search and AI crawlers to discover and understand without requiring JavaScript. Preserve the existing WordPress publishing pipeline, React user experience, public URL structure, and administration workflows.

The site intentionally permits search, user-directed retrieval, and model-training crawlers to access public content. Drafts, private content, administrative screens, authentication endpoints, and mutation APIs remain excluded from public discovery surfaces.

## Verified Baseline

The local WordPress runtime already passes the deterministic automation smoke test. The existing workflow validates OpenAI structured output and HTTPS sources, publishes verified `post` and `kb_comeback` records, prevents duplicates, stores provenance metadata, and registers its WP-Cron event.

The existing SEO runtime smoke test confirms that `/robots.txt`, `/sitemap.xml`, and `/rss.xml` return valid response types and include at least one current WordPress article. It does not verify automatically published comeback or concert records, crawler-readable article bodies, canonical URLs, metadata, structured data, drafts, or missing-content responses.

A direct request to an existing `/news/{slug}` page returns the React application shell with a WordPress title but no article body, description metadata, or `NewsArticle` JSON-LD. WordPress also emits its native post permalink as the canonical URL instead of the public `/news/{slug}` URL.

## Selected Approach

Keep WordPress as the source of truth and retain the current React SPA. Add a server-rendered public-content representation to the WordPress response so the same URL works for browsers with JavaScript, browsers without JavaScript, search crawlers, AI search crawlers, user-directed AI fetchers, and training crawlers.

This is not crawler-specific rendering. Every requester receives the same initial semantic HTML. When JavaScript loads, the existing React entry point replaces the initial mount content with the interactive application. This avoids cloaking and keeps the crawler-visible content tied to the exact WordPress record used by the application.

A full TanStack SSR migration is outside this change. The standalone non-WordPress build continues using its existing route implementation.

## Public Article Rendering

For a request matching `/news/{slug}`, WordPress resolves a published native `post` by its exact slug before rendering the application shell.

When a published article exists, the server response includes:

- an HTTP `200` status;
- a document title derived from the post title and site name;
- a bounded plain-text meta description derived from the excerpt or post body;
- a canonical URL produced with `home_url( '/news/' . $slug )`;
- Open Graph article metadata using the same title, description, canonical URL, publication time, modification time, and featured image when available;
- a `NewsArticle` JSON-LD object with headline, description, publication and modification times, canonical main entity URL, image when available, publisher identity, author identity, and the public source links stored by automation;
- a semantic fallback `<article>` inside the existing React mount element containing the headline, publication and modification times, sanitized body, and source links.

All values come from the resolved WordPress post and its existing metadata. The implementation does not copy values from client-side demo data and does not create a second content store.

The fallback uses normal WordPress content sanitization and formatting. It must not expose automation response IDs, model credentials, internal confidence values, unpublished metadata, or administrator-only information. The source section exposes only validated public HTTPS source URLs and human-readable source titles.

When no published article matches the slug, the response remains `404`, includes a `noindex, nofollow` robots directive, and does not emit article structured data or fallback body content. Other known React routes retain their current application-shell behavior.

For `/comebacks`, WordPress emits a server-rendered calendar fallback from published `kb_comeback` records. Each entry has a stable `event-{post ID}` fragment, title, event type, event date, artist slug when present, description, and public source link. The page emits a `/comebacks` canonical URL and `CollectionPage` JSON-LD containing `Event` items. The React comeback calendar replaces this fallback after JavaScript starts.

## Machine-Readable Discovery Endpoints

WordPress continues to own the production versions of the following root endpoints.

### `/robots.txt`

The general crawler group allows public content and excludes administrative, moderation, onboarding, login, and authenticated or mutating API paths. Separate explicit allow groups document the site's opt-in for current AI search, user-directed retrieval, and model-training controls, including:

- `OAI-SearchBot` and `GPTBot`;
- `ClaudeBot`, `Claude-SearchBot`, and `Claude-User`;
- `PerplexityBot` and `Perplexity-User`;
- `Google-Extended`.

The file includes absolute links to `/sitemap.xml`, `/rss.xml`, and `/llms.txt`. The wildcard group remains the fallback for other legitimate crawlers. Robots rules are advisory; authorization and privacy boundaries continue to be enforced by WordPress rather than by `robots.txt`.

### `/sitemap.xml`

The sitemap is a UTF-8 XML URL set generated from WordPress data. It includes the existing public static routes plus published articles, artists, members, videos, polls, and forum threads.

Each content type uses the same public SPA path as the user interface. Article locations use `/news/{slug}`. The existing `/comebacks` sitemap entry receives a `lastmod` value from the newest published `kb_comeback` modification because the application has one calendar route rather than individual schedule routes. Other published content includes a valid ISO 8601 `lastmod` value based on the WordPress GMT modification time.

Draft, private, pending, trashed, password-protected, empty-slug, and otherwise non-public records are excluded. XML values are escaped with the existing XML-safe helper. A malformed individual record is skipped without invalidating the full sitemap.

### `/rss.xml`

The RSS 2.0 feed includes the newest published articles and published comeback or concert schedule records in descending relevant-date order. Every item has an absolute public URL, stable GUID, title, publication date, bounded description, and content category. Article descriptions use the excerpt or a safe text summary. Schedule item links use `/comebacks#event-{post ID}`, matching the server-rendered fallback entry, and identify their event type and event date without inventing missing details.

The feed exposes public source attribution already stored on the content record. It never includes drafts, internal automation metadata, or raw model output.

### `/llms.txt`

The plain-text discovery document provides a concise site description, the canonical site URL, and absolute links to the sitemap, RSS feed, latest news, artists, and comeback calendar. It also lists a bounded set of the newest public article URLs and `/comebacks#event-{post ID}` schedule links with short descriptions.

`llms.txt` is supplementary. The sitemap, RSS feed, semantic HTML, canonical metadata, and structured data remain the authoritative discovery mechanisms.

## Publication and Cache Flow

The automation pipeline remains unchanged through `wp_insert_post()` or `wp_update_post()`. Once WordPress changes a record to `publish`, subsequent server HTML and machine endpoint requests read that same published record.

Machine endpoints send explicit UTF-8 content types. They use short public cache windows and revalidation validators derived from the latest relevant WordPress modification time. A cached representation may remain valid only for the documented short window; clients can revalidate rather than downloading unchanged documents repeatedly.

No job attempts to push content directly to an AI vendor. Discovery remains standards-based and vendor-neutral. Production CDN or WAF configuration is a separate operational concern; it must not challenge or block legitimate crawler traffic that the site has elected to allow.

## Error Handling and Safety

- Endpoint routing compares normalized root paths and ignores query strings when selecting the handler.
- Only exact published WordPress records can produce public article metadata or fallback content.
- Missing and non-public article slugs return `404` with `noindex, nofollow`.
- XML and plain-text formats use format-appropriate escaping and never interpolate untrusted markup.
- Public HTML uses WordPress content sanitization and URL escaping.
- A malformed item is omitted from a feed or sitemap rather than corrupting the entire document.
- Empty result sets still return valid RSS, sitemap, and llms documents.
- API credentials, authorization headers, OpenAI response IDs, internal errors, and database details never appear in public responses.
- The implementation does not add a new library, database table, remote service, or background job.

## Compatibility Boundaries

The React application mount ID, WordPress REST response contracts, CMS TypeScript types, content automation schema, database schema, and administrator screens remain unchanged.

The existing user-owned modifications in `src/styles.css` and `wordpress-plugin/kpopblog/templates/app-shell.php` are preserved. Implementation must integrate with the current app-shell changes instead of overwriting or reverting them.

The standalone TanStack routes for `/robots.txt`, `/sitemap.xml`, and `/rss.xml` remain valid for the non-WordPress build. Production WordPress requests continue to be served by the plugin's machine-endpoint handler.

## Verification Strategy

### Deterministic automation integration

Extend the deterministic WordPress automation coverage so a fake Responses API result creates one article and one schedule record through the real automation entry point. While those records exist, issue HTTP requests to the public application and machine endpoints, then remove all fixture records and restore prior settings in a `finally` path.

Assertions cover:

- automatic publication and provenance metadata;
- second-run deduplication;
- scheduled hook registration;
- public article response status and content;
- canonical, description, Open Graph metadata, and `NewsArticle` JSON-LD;
- article headline, body, dates, and source links in HTML without executing JavaScript;
- comeback calendar entries and `Event` structured data in HTML without executing JavaScript;
- article URLs and the schedule-aware `/comebacks` modification time in the sitemap;
- article and schedule links in RSS and llms output;
- explicit AI crawler allow rules;
- exclusion of a deterministic draft fixture;
- a missing news slug returning `404` and `noindex`.

The HTTP checks use representative OpenAI, Anthropic, Perplexity, and Google crawler user agents. They verify response parity rather than user-agent-specific bodies.

### Static and runtime gates

Run the repository's existing verification commands that cover the touched surfaces:

- PHP syntax checks in the Docker WordPress runtime;
- `scripts/wordpress/automation-smoke.ps1`;
- `scripts/wordpress/seo-runtime-smoke.ps1` with the expanded assertions;
- `npm run lint`;
- `npm run build`;
- `npm run build:wordpress`.

### Browser regression

Open a published `/news/{slug}` route and `/comebacks` in a real browser and verify that the React views replace the fallback content, remain interactive, produce no console error, and do not duplicate content. Verify responsive navigation and direct route refresh because both depend on the shared app shell.

## Completion Criteria

The change is complete when deterministic automation publishes fixture content, every public discovery endpoint exposes that content, raw HTML exposes a complete and correctly attributed article without JavaScript, all supported AI crawler groups are allowed, non-public content remains absent, missing content returns a real non-indexable `404`, the React article experience remains unchanged, and all specified static, integration, and browser gates pass.
