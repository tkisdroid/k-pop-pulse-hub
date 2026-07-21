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
