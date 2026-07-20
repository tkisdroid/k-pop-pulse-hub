# Identity and Community Implementation Plan

> **Execution:** Implement sequentially with test-driven development. Do not
> modify production or the existing uncommitted `templates/app-shell.php`.

**Goal:** Make WordPress the complete runtime source for accounts, profiles,
community posts, forum discussions, article comments, reports, and moderation.

**Runtime:** WordPress 7.x, PHP 8.2 with PHP 7.4-compatible plugin syntax,
React 19, TanStack Router, TypeScript, Vite, PowerShell smoke tests.

## Task 1: Authentication and Profile Contracts

**Files:**
- Create: `scripts/wordpress/identity-smoke.ps1`
- Modify: `wordpress-plugin/kpopblog/includes/auth.php`
- Modify: `wordpress-plugin/kpopblog/includes/shortcode.php`
- Modify: `src/services/auth/wordpressAuthProvider.ts`
- Modify: `src/routes/login.tsx`
- Modify: `src/routes/signup.tsx`

1. Write failing runtime assertions for disabled registration, public profile
   email privacy, authenticated self-update, unauthenticated rejection, and
   registration throttling.
2. Add salted transient rate-limit helpers and enforce the core registration
   option before account creation.
3. Add public profile and authenticated profile-update routes with an explicit
   safe field allowlist.
4. Return capability flags separately from presentation role metadata.
5. Update WordPress login/signup UI validation and submission states without
   adding dependencies.
6. Run identity smoke, PHP lint, and both front-end builds; commit.

## Task 2: Community Storage and REST APIs

**Files:**
- Create: `wordpress-plugin/kpopblog/includes/community.php`
- Create: `scripts/wordpress/community-smoke.ps1`
- Modify: `wordpress-plugin/kpopblog/includes/install.php`
- Modify: `wordpress-plugin/kpopblog/includes/cpt.php`
- Modify: `wordpress-plugin/kpopblog/includes/meta.php`
- Modify: `wordpress-plugin/kpopblog/kpopblog.php`
- Modify: `wordpress-plugin/kpopblog/includes/rest.php`
- Modify: `wordpress-plugin/kpopblog/includes/rest-write.php`

1. Write failing assertions for CPT/taxonomy/schema registration, pagination,
   member post/thread/reply creation, locked thread rejection, comment reads,
   report validation/deduplication, and moderation-only resolution.
2. Add `kb_community`, `kb_forum_category`, and the idempotent reports table;
   raise the plugin schema version.
3. Implement mapper helpers whose output exactly matches TypeScript types and
   never includes private user fields.
4. Implement paginated reads and authenticated writes with length validation,
   sanitization, rate limiting, ownership, server-selected status, and stable
   errors.
5. Audit report and moderation state changes; run smoke/PHP/build checks;
   commit.

## Task 3: WordPress Community Front-end

**Files:**
- Create: `src/services/community/wordpressCommunityProvider.ts`
- Create: `src/services/community/demoCommunityProvider.ts`
- Create: `src/services/community/index.ts`
- Modify: `src/types/index.ts`
- Modify: `src/routes/community.tsx`
- Modify: `src/routes/forum.tsx`
- Modify: `src/routes/forum.$categorySlug.tsx`
- Modify: `src/routes/thread.$threadSlug.tsx`
- Modify: `src/routes/profile.$username.tsx`
- Modify: `src/routes/moderation.tsx`

1. Define typed paginated API results and an environment-selected provider
   that forbids demo fallback in WordPress mode.
2. Replace demo reads and no-op mutations route by route, preserving existing
   visual components while adding loading, empty, error, retry, pending, and
   disabled states.
3. Use server capability flags for moderator affordances and PHP capability
   checks for enforcement.
4. Build standalone and WordPress bundles; exercise runtime flows; commit.

## Task 4: WordPress Moderation Operations

**Files:**
- Create: `wordpress-plugin/kpopblog/includes/moderation-admin.php`
- Create: `scripts/wordpress/moderation-smoke.ps1`
- Modify: `wordpress-plugin/kpopblog/includes/admin.php`
- Modify: `wordpress-plugin/kpopblog/kpopblog.php`
- Modify: `wordpress-plugin/README.md`

1. Write failing admin-menu, capability, nonce, report-action, audit, and count
   assertions.
2. Add a Moderation submenu under K-pop Pulse Hub using core admin tables and
   escaped output; link native pending comments/community items.
3. Resolve/dismiss reports through nonce-protected admin actions, record audit
   entries, and expose open-report counts on the dashboard.
4. Update operator docs and the integrated verification script.
5. Run all identity/community/runtime smoke tests, production builds, package
   inspection, and a real browser workflow; commit.
