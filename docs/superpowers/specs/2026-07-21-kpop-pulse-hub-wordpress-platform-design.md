# K-pop Pulse Hub WordPress Platform Design

## Purpose

K-pop Pulse Hub is a WordPress-operated K-pop news and community site. WordPress is the source of truth for users, editorial content, artists, schedules, community activity, subscriptions, notifications, advertising configuration, and automation status. The React application is the public presentation layer and must not fall back to demo or browser-only persistence when it is running inside WordPress.

This design preserves the existing React 19, TanStack Router, TypeScript, Vite, and WordPress plugin structure. It replaces incomplete demo-backed behavior through focused additions instead of rewriting the application.

## Confirmed Current State

- `npm run build:wordpress` succeeds.
- `npm run lint` succeeds.
- The repository has a WordPress plugin with custom post types, REST read/write routes, native cookie authentication, a newsletter implementation, and an event poller.
- The public `/admin`, `/community`, `/submit`, and `/moderation` pages still contain demo data, no-op forms, or placeholder controls.
- Notifications are ultimately persisted in browser `localStorage`; the WordPress event log is a capped global option rather than a durable per-user inbox.
- AI scripts generate static JSON during development. No WordPress scheduler currently discovers, validates, and publishes current news, concerts, or comeback schedules.
- There is no PHP executable or WP-CLI on the host. Docker is available and will be the reproducible WordPress runtime.
- `wordpress-plugin/kpopblog/templates/app-shell.php` contains an existing uncommitted user change. This work must preserve it unless a later task specifically requires a compatible edit.

## Product Boundaries

The platform is divided into five independently testable delivery units.

1. **WordPress operations foundation**: local WordPress runtime, plugin lifecycle, schema versioning, capabilities, a single K-pop Pulse Hub admin menu, health checks, audit records, and repeatable smoke tests.
2. **Identity and community**: native registration/login/password reset, profiles, article comments, community posts, forum threads/replies, reports, moderation actions, and WordPress-admin management.
3. **Automated content**: configurable sources, scheduled ingestion, normalized evidence, deduplication, artist matching, server-side AI transformation, article/comeback/concert upserts, review policy, job controls, and provenance.
4. **Subscriptions and notifications**: double-opt-in newsletter records, topic/artist subscriptions, durable per-user notifications, admin broadcasts, delivery logs, unsubscribe/privacy workflows, and public inbox APIs.
5. **Monetization and release quality**: consent-aware AdSense settings and slots, route metadata and structured data, performance budgets, accessibility checks, backup-safe uninstall behavior, and full browser/runtime verification.

Each unit receives its own implementation plan. A unit is complete only after it runs in the Docker WordPress environment and its public and administrator workflows are verified.

## Chosen Architecture

### Decision

Use a WordPress-native plugin architecture. External AI and search providers are adapters called only by WordPress on the server. No required business workflow depends on a separate Supabase project, browser-local state, or an independently deployed Node service.

### Rejected alternatives

- A serverless automation service would scale independently but adds another control plane and prevents WordPress from being the complete operational console.
- A Node headless backend would duplicate WordPress users, capabilities, storage, and job management and would require a broad rewrite of the existing plugin.

### WordPress data ownership

- WordPress core users, roles, sessions, password reset, posts, terms, media, and comments remain authoritative.
- Existing custom post types remain authoritative for artists, members, comebacks, charts, forum threads, and polls.
- Community posts use a dedicated WordPress content type because they require independent moderation, retention, and administrator filters.
- Structured operational datasets use plugin-owned database tables created with `dbDelta`. These datasets are sources, source items, jobs, subscribers, notification inbox entries, reports, and audit events.
- Plugin schema changes use a stored schema version and idempotent migrations. Activation never deletes existing content.

### Administrator experience

The plugin adds one top-level **K-pop Pulse Hub** menu. Its subpages are:

- Dashboard: system health, recent jobs, content counts, moderation queue, delivery failures, and configuration warnings.
- Content: links and counts for WordPress Posts, Artists, Members, Comebacks, Charts, Threads, Community Posts, and Polls.
- Automation: enable switch, interval, publication policy, provider selection, run-now action, pause action, and job history.
- Sources: create, edit, enable, disable, validate, and test RSS/Atom, JSON Feed, and configured search-provider sources.
- Moderation: reports, pending comments/posts, warnings, hide/restore, lock/unlock, and immutable action history.
- Subscribers: status/topic filters, consent timestamps, export, suppression, resend confirmation, and unsubscribe.
- Notifications: compose broadcasts, inspect per-user delivery state, and retry failed email delivery.
- Advertising: AdSense client ID, per-slot IDs, enable switch, consent requirement, test mode, and placement health.
- Settings: registration behavior, rate limits, retention, locale, webhook settings, and integration health.

WordPress core Users, Posts, Comments, Privacy Export, and Privacy Erase screens remain in place and are linked from the plugin dashboard rather than reimplemented.

## Public Application Contracts

### Runtime mode

When `window.kpopblogConfig` exists, all interactive features use WordPress APIs. WordPress mode must never silently report success after storing only in `localStorage`, and must never display demo users, posts, notifications, or administrator counts. Demo providers remain available only for standalone design preview.

### Authentication and authorization

- WordPress cookie sessions and REST nonces are used on same-origin requests.
- Registration, login, logout, current-user, and password-reset responses return a new REST nonce after session changes.
- Public registration follows the plugin setting and applies username, email, password, IP, and account throttles.
- REST permissions are capability-based. A React role label is presentation data, never the authorization decision.
- Community creation requires login. Moderation requires an explicit moderation capability. Automation and settings require `manage_options`.

### Community behavior

- Logged-in users can create community posts and forum threads, reply, edit their own content within policy, delete their own unpublished content, react once, and report content.
- Server responses determine pending/published/hidden state; the UI shows loading, success, validation, rate-limit, and permission errors.
- Administrator and moderator actions are written to an audit trail with actor, target, action, optional reason, and timestamp.
- User-generated HTML is sanitized by WordPress. Public React rendering does not trust arbitrary HTML.

### Content reads

- Articles, artists, members, schedules, charts, threads, polls, community posts, profiles, and notifications come from paginated WordPress endpoints.
- List endpoints return totals and stable ordering. Detail endpoints return 404 for missing or non-public content.
- Front-end filters are sent to WordPress rather than applied to only the first fetched page.
- Public routes render explicit loading, empty, error, and retry states.

## Automated Content Pipeline

### Source policy

Only an administrator can register a source. Every source has a type, URL or provider query, locale, enabled state, trust level, covered artists, content kinds, fetch interval, publication policy, and last health result. Fetches use WordPress safe HTTP functions, bounded redirects, bounded response size, explicit timeouts, and reject unsafe URLs.

The initial supported types are RSS/Atom, JSON Feed, and one configured news-search adapter. A provider adapter is unavailable until its server-side credential is configured. API credentials are never localized into JavaScript or exposed by REST responses.

### Processing states

Each discovered item moves through these states:

`discovered -> normalized -> matched -> transformed -> validated -> published`

An item can instead become `duplicate`, `needs_review`, or `failed`. State changes are recorded with timestamps and an error code. Retrying a failed item is idempotent.

### Normalization and deduplication

- Persist the source ID, external identifier, canonical URL, original title, original publication time, fetched time, content hash, and a bounded evidence excerpt.
- Prefer a source-provided stable ID, then canonical URL, then a normalized title/date hash.
- Enforce a database unique key so simultaneous jobs cannot publish the same item twice.
- When a newer source item describes an existing comeback or concert, update the existing WordPress item and append provenance instead of creating a duplicate.

### AI transformation

- WordPress calls the configured AI adapter server-side.
- The prompt receives only the normalized evidence, target content type, known artist records, locale, and required output schema.
- Output is parsed and validated before persistence. It must not introduce names, dates, venues, prices, quotations, or claims absent from the evidence.
- Generated articles keep source links, generated timestamp, model/provider identifier, prompt schema version, and source-item IDs in post metadata.
- A provider timeout, quota failure, invalid output, or missing credential creates a reviewable failed job; it never publishes partial content.

### Publication policy

Automatic publication is allowed only when all of the following are true:

- the source is enabled and marked trusted for the content kind;
- the item passes deduplication and required-field validation;
- the artist match is unambiguous or explicitly mapped by the source;
- all dates include a valid timezone or are stored as date-only values intentionally;
- AI output passes schema and evidence checks;
- the source publication policy is `publish`.

Otherwise the pipeline creates or updates a WordPress draft and adds the item to the review queue. Administrators can choose `draft` for any source even while global automation is enabled.

### Scheduling and concurrency

- A custom WordPress cron interval runs due sources in bounded batches.
- A lock prevents overlapping dispatcher runs.
- Each source and item uses bounded exponential retry with a maximum attempt count.
- A run-now action uses a nonce and capability check, queues the job, and returns immediately.
- Job logs record duration, counts, result, and a sanitized error message. Secrets and raw authorization headers are never logged.

## Subscriptions and Notifications

- Newsletter subscriptions use double opt-in by default, token hashes, consent/source/locale timestamps, topic selections, confirmed/unsubscribed state, and suppression after unsubscribe.
- Public subscribe endpoints do not reveal whether an email already exists.
- Confirmation and unsubscribe tokens expire and are one-way hashed in storage.
- WordPress privacy export and erase hooks include plugin subscriber and community data.
- User notification inbox entries are durable server records targeted to a user or generated from a broadcast. Read state is updated through authenticated REST endpoints.
- Publishing an article, comeback, or concert fans out notifications only to eligible followers/subscribers. Delivery jobs are batched.
- Browser notifications are an optional presentation channel. Failure or denial never removes the durable in-app notification.

## AdSense, SEO, and Performance

- AdSense loads only when enabled, configured, and permitted by the selected consent policy.
- The plugin owns the single AdSense loader. Theme/plugin Auto Ads scripts remain excluded from the full-page shell to avoid React DOM mutation conflicts.
- React `AdSlot` components receive only administrator-configured public client and slot IDs. Test mode uses Google's test-ad attribute and is visible in the administrator health screen.
- Article and schedule routes expose server-produced title, description, canonical URL, Open Graph metadata, and applicable JSON-LD from WordPress data before the React bundle executes.
- The WordPress build is code-split by route and enforces a documented initial JavaScript budget. Images keep dimensions, lazy loading, and responsive sources.
- Keyboard navigation, focus visibility, form labels, error association, contrast, and reduced-motion behavior are verified on public workflows.

## Failure Handling and Observability

- Public mutations return structured WordPress errors with HTTP status and stable error codes.
- UI actions disable duplicate submissions and retain user input on recoverable failure.
- Background failures remain isolated to a job or item and do not disable public reads.
- The administrator dashboard distinguishes configuration errors, source health errors, transient provider errors, validation failures, and delivery failures.
- Audit and job data have configurable retention. Deletion is scheduled in bounded batches.
- Plugin deactivation unschedules hooks but preserves data. Uninstall deletes data only after an explicit administrator opt-in stored before uninstall.

## Verification Strategy

### Static gates

- `npm run lint`
- `npm run build`
- `npm run build:wordpress`
- PHP syntax checks inside the Docker WordPress/PHP runtime
- Plugin package build and manifest verification

### WordPress integration gates

- Fresh activation and repeat activation complete without PHP warnings or schema loss.
- Administrator pages load for administrators and reject unauthorized users.
- Native registration, login, logout, current-user, and password reset work with nonce rotation.
- Users can create and retrieve community/forum content; ownership and moderation permissions are enforced.
- Subscriber confirmation and unsubscribe flows are idempotent and privacy-safe.
- Source test, scheduled ingestion, deduplication, failed retry, draft policy, and automatic publication are exercised with deterministic fixture feeds and a deterministic fake AI adapter.
- Notifications are created for eligible users, listed, marked read, and filtered by target user.
- Ad settings render the configured slot only under the permitted consent state.

### Browser gates

- A real browser verifies signup, login, posting, commenting, following, notification reading, logout, and responsive navigation.
- WordPress administrator browser checks cover CRUD, moderation, automation run-now, subscriber filters, notification broadcast, and AdSense configuration.
- No workflow is accepted based solely on a demo provider or mocked success response. Deterministic fixtures are allowed only for external feed and AI boundaries.

## Completion Criteria

The platform is complete only when every delivery unit has an implemented plan, the Docker WordPress runtime passes all static/integration/browser gates, WordPress mode contains no user-visible demo-backed or no-op workflow, all named operations are manageable from WordPress administration, automatic content updates are proven with fixture and configured live-source runs, and no existing user-owned worktree changes are overwritten.
