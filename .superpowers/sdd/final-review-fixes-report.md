# Final Branch Review Fixes Report

## Implemented

- Machine endpoint validators now hash the rendered payload together with a renderer-version marker. Public responses use `max-age=0, must-revalidate`; `If-None-Match` has precedence over `If-Modified-Since`, supports weak/list/gzip validators, and 304 paths retain validators and public cache policy. The runtime test publishes, caches, makes an article private, and proves an old validator returns `200` without the private URL and with a changed ETag.
- Added `includes/validation.php` as the shared strict ISO 8601 validator. Automation and discovery both reject relative dates and timezone-less date-times while accepting date-only or timezone-qualified date-times.
- Article head output now emits exact `article:published_time`, `article:modified_time`, and optional `og:image` values sourced from the same post values used by NewsArticle JSON-LD.
- Comeback fallback output emits a sanitized `/artist/{kb_artist_slug}` link when artist metadata is present.
- Robots exclusions are generated from one shared list for every crawler group and cover verified private frontend routes plus auth, profile, submission, report, newsletter mutation, vote, follow, engagement, comment, community, subscription, notification, event, moderation, and admin REST surfaces.
- RSS now combines articles and schedules, sorts globally by the relevant timestamp, emits at most 50 items, and bounds every final description to 700 characters even when a manual excerpt is oversized.
- `llms.txt` entries are bounded JSON-line records with Markdown metacharacters escaped in stored titles and descriptions.
- Added one Compose context resolver used by bootstrap, automation smoke, SEO smoke, and the full verification gate. Smoke tests inspect the running WordPress bind mount and stop without mutating the container if it is not sourced from the checkout under test. The full gate passes the same project name to children through `COMPOSE_PROJECT_NAME` and explicit parameters.
- Release gates now include automation failure injection and an expected SEO parse-failure check that requires a unique pre-parse marker and then reruns the normal SEO test to prove cleanup.
- Bumped plugin header, `KPOPBLOG_VERSION`, and stable tag from `1.3.0` to `1.3.1`. Schema version remains `1.3.0` because this release has no schema migration.

## Exact verification evidence

- PowerShell parser: `compose-context.ps1`, `bootstrap.ps1`, `automation-smoke.ps1`, `seo-runtime-smoke.ps1`, and `verify-foundation.ps1` all parsed with zero errors.
- PHP lint: every plugin PHP file reported `No syntax errors detected`, including `validation.php`, `automation.php`, and `discoverability.php`.
- `npm.cmd run build:wordpress`: exit 0, Vite completed 2,136 modules.
- `pwsh -File scripts/wordpress/automation-smoke.ps1 -ComposeProjectName k-pop-pulse-hub`: `AI automation smoke test passed.`
- The same normal automation command was run again successfully by the full foundation gate.
- `pwsh -File scripts/wordpress/automation-smoke.ps1 -InjectAutomationFailure -ComposeProjectName k-pop-pulse-hub`: `AI automation smoke test passed.` after proving the injected OpenAI 500 returned `WP_Error` and the subsequent run/cleanup succeeded.
- `pwsh -File scripts/wordpress/seo-runtime-smoke.ps1 -ComposeProjectName k-pop-pulse-hub`: `WordPress SEO runtime smoke test passed.`
- SEO parse injection: exit 1 with `Fault injection confirmed outbound_requests=0 before PowerShell parse failure.`; immediate normal SEO rerun passed, proving marker fixture and notification-state cleanup.
- `pwsh -File scripts/wordpress/verify-foundation.ps1 -ComposeProjectName k-pop-pulse-hub`: exit 0 with `WordPress operations foundation verified.` This included application and WordPress builds, asset budget, bootstrap, all existing runtime/plugin/admin/identity/community/interaction/subscription/ads/content smokes, both automation modes, SEO expected-failure plus normal mode, PHP lint, archive creation, and required `validation.php`/`discoverability.php` entries.
- Browser article check: one `#kpopblog-root`, zero `[data-kpopblog-fallback]`, no horizontal overflow, direct refresh retained the same `/news/{slug}` path, and console errors/warnings were zero.
- Browser comeback check: one `#kpopblog-root`, zero fallback nodes, no horizontal overflow, rendered Comeback Schedule React view, and console errors/warnings were zero.
- `git diff --check`: exit 0.

Apache omits the entity `Content-Type` header from the wire representation of a standards-compliant 304 response even though the PHP handler sets the same endpoint content type after selecting status 304. The test therefore requires exact endpoint content type on every 200 response and requires Cache-Control, ETag, and Last-Modified on 304 responses.

## Fresh re-review follow-up

- Added a monotonic `kpopblog_discovery_revision` validator timestamp. Public post publication, update, unpublication, deletion, public metadata changes, and site identity URL changes advance it beyond both its previous value and the newest public content timestamp. Renderer file modification time is also part of Last-Modified. `If-Modified-Since` can now return 304 only when `If-None-Match` is absent; an INM mismatch always takes precedence and returns the current 200 representation. SEO fixtures snapshot and restore this option exactly.
- Replaced the RSS schedule `post_date` pre-limit with a paged 500-row scan that validates and parses `kb_release_at`, retains only the newest 50 candidates in memory, then globally sorts them with article candidates and applies the final 50-item bound.
- The SEO regression creates 51 schedules whose creation-date order conflicts with release order. It proves the newest release is the first RSS item even though that schedule has the oldest `post_date`, and removes all 51 records through marker- and title-checked cleanup.
- Added `/bookmarks` and `/cookie-settings` to the one shared robots exclusion list and to the independent per-crawler test inventory.
- Re-ran PHP lint, PowerShell parsing, focused SEO, automation with exact revision-option restoration, and the complete foundation gate after these follow-up changes. The final full run again ended with `WordPress operations foundation verified.`

## Final atomic revision follow-up

- Discovery revision writes now use one prepared `INSERT ... ON DUPLICATE KEY UPDATE` against the options table. The duplicate branch applies `GREATEST(CAST(option_value AS UNSIGNED) + 1, floor)`, then invalidates the option, notoptions, and alloptions caches and reads back the persisted value. This keeps concurrent updates monotonic and handles an initially absent option without a read-modify-write race.
- Post revision floors are later than both before/after `post_modified_gmt` values. Event tokens include post ID, resulting status, and modified GMT, which coalesces the `transition_post_status`/`post_updated` pair without suppressing a second same-second status transition.
- Post-meta hooks now inspect the exact meta key. Only rendered discovery dependencies advance the validator: `kb_category_slug`, `kb_source_url`, `kb_source_urls`, `kb_source_title`, `kb_release_at`, `kb_type`, `kb_artist_slug`, and `_thumbnail_id`. View/reaction counters do not advance it. Site identity dependencies now include `WPLANG`.
- The SEO runtime test proves `kb_view_count` and `kb_reaction_count` leave the revision unchanged while source and release metadata each advance it. A deterministic option-absent fixture forces identical modified timestamps for publish and private transitions, requires the first revision to equal `modified_gmt + 1`, requires the private revision to equal the published revision plus exactly one, and proves an IMS-only request returns `200` without the private URL.
- Final focused verification passed: plugin PHP lint, PowerShell parsing, `git diff --check`, automation normal/failure-injection modes, SEO normal mode, expected SEO parse failure followed by a normal pass, and a cleanup audit with zero SEO fixture posts and zero SEO fixture notification jobs.
- Final full verification passed again with `WordPress operations foundation verified.` It included both builds, asset budget, Docker bootstrap, every WordPress runtime smoke, both automation modes, expected SEO fault injection, normal SEO runtime, full PHP lint, and plugin archive generation.
