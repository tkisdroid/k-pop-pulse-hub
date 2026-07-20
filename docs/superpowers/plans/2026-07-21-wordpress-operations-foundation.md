# WordPress Operations Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Provide a reproducible WordPress runtime and an administrator-only K-pop Pulse Hub operations dashboard backed by idempotent plugin installation and verifiable capabilities.

**Architecture:** Docker Compose runs WordPress 7.x on PHP 8.2 with MariaDB 11.4 and an official WP-CLI companion. The plugin owns named lifecycle functions, a schema version, an audit table, explicit capabilities, an administrator menu, and an authenticated health endpoint; PowerShell smoke tests drive the real WordPress runtime.

**Tech Stack:** WordPress 7.x, PHP 8.2 runtime with PHP 7.4-compatible plugin syntax, MariaDB 11.4, WP-CLI, Docker Compose, PowerShell 7, React/Vite package build.

## Global Constraints

- Preserve the existing uncommitted change in `wordpress-plugin/kpopblog/templates/app-shell.php`.
- Add no npm, Composer, WordPress.org plugin, or third-party PHP dependency.
- Keep the plugin source compatible with its declared PHP 7.4 minimum.
- Use `manage_options` for the operations dashboard and health API.
- Use dedicated plugin capabilities for later moderation, automation, notification, and advertising operations.
- Keep Docker credentials local-only and deterministic; they must not be suitable for production.
- Do not delete WordPress volumes, plugin data, or user content in ordinary bootstrap or verification commands.
- The rolling `wordpress:7-php8.2-apache` tag is intentional because WordPress 7.0.2 is the current security release but an exact `7.0.2-php8.2-apache` manifest is not available.
- Production is `https://thekpopblog.com` on WordPress 7.0.2 with KpopBlog 1.0.0; foundation implementation occurs locally and does not mutate production.
- The future production deployment must coexist with Betheme, WP Super Cache, NinjaFirewall, Login Lockdown, Two Factor, Contact Form 7, Make Connector, the existing AI trend screen, the existing AdSense loader/menu, and host must-use plugins.
- Foundation health output must report the known service-worker MIME failure and demo-data runtime mode as warnings until their owning delivery units replace them.

---

### Task 1: Reproducible WordPress Runtime

**Files:**
- Create: `.env.wordpress.example`
- Create: `docker-compose.wordpress.yml`
- Create: `scripts/wordpress/bootstrap.ps1`
- Create: `scripts/wordpress/runtime-smoke.ps1`
- Modify: `.gitignore`

**Interfaces:**
- Consumes: Docker Desktop, Docker Compose v5, `wordpress-plugin/kpopblog`.
- Produces: WordPress at `http://localhost:8088`, administrator `admin`, member `member`, active `kpopblog` plugin, and a configured full-page app homepage.

- [ ] **Step 1: Write the failing runtime smoke test**

Create `scripts/wordpress/runtime-smoke.ps1` with strict error handling. It must fail until Compose and bootstrap files exist:

```powershell
[CmdletBinding()]
param([int]$Port = 8088)

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$compose = Join-Path $repoRoot 'docker-compose.wordpress.yml'

if (-not (Test-Path -LiteralPath $compose)) {
    throw "Missing $compose"
}

docker compose --env-file (Join-Path $repoRoot '.env.wordpress.example') -f $compose config --quiet
if ($LASTEXITCODE -ne 0) { throw 'Docker Compose configuration is invalid.' }

$status = Invoke-RestMethod -Uri "http://localhost:$Port/wp-json/" -TimeoutSec 15
if ($status.name -ne 'K-pop Pulse Hub Local') { throw "Unexpected site name: $($status.name)" }

$bundle = Invoke-RestMethod -Uri "http://localhost:$Port/wp-json/kpopblog/v1/bundle" -TimeoutSec 15
if ($null -eq $bundle.articles) { throw 'KpopBlog bundle route is unavailable.' }

docker compose --env-file (Join-Path $repoRoot '.env.wordpress.example') -f $compose run --rm cli plugin is-active kpopblog
if ($LASTEXITCODE -ne 0) { throw 'KpopBlog plugin is not active.' }

Write-Host 'WordPress runtime smoke test passed.'
```

- [ ] **Step 2: Run the test and verify the expected failure**

Run:

```powershell
pwsh -File scripts/wordpress/runtime-smoke.ps1
```

Expected: FAIL with `Missing ...docker-compose.wordpress.yml`.

- [ ] **Step 3: Add deterministic local environment configuration**

Create `.env.wordpress.example`:

```dotenv
WORDPRESS_PORT=8088
WORDPRESS_DB_NAME=kpopblog_test
WORDPRESS_DB_USER=kpopblog
WORDPRESS_DB_PASSWORD=kpopblog_local_only
WORDPRESS_DB_ROOT_PASSWORD=kpopblog_root_local_only
WORDPRESS_ADMIN_USER=admin
WORDPRESS_ADMIN_PASSWORD=kpopblog_admin_local_only
WORDPRESS_ADMIN_EMAIL=admin@kpopblog.test
WORDPRESS_MEMBER_USER=member
WORDPRESS_MEMBER_PASSWORD=kpopblog_member_local_only
WORDPRESS_MEMBER_EMAIL=member@kpopblog.test
```

Append `.env.wordpress` to `.gitignore`; bootstrap uses `.env.wordpress` when present and the tracked example otherwise.

- [ ] **Step 4: Add the Docker Compose runtime**

Create `docker-compose.wordpress.yml` with `mariadb:11.4.12`, `wordpress:7-php8.2-apache`, and `wordpress:cli-php8.2`. Use named volumes `kpopblog_db_data` and `kpopblog_wordpress_data`; mount `./wordpress-plugin/kpopblog` at `/var/www/html/wp-content/plugins/kpopblog` in both WordPress and CLI services. Configure `WORDPRESS_DEBUG: '1'`, database health checks with `healthcheck.sh --connect --innodb_initialized`, and `depends_on` health conditions. The CLI service must use `user: "33:33"`, share the WordPress volume, and depend on both healthy services.

- [ ] **Step 5: Add idempotent bootstrap automation**

Create `scripts/wordpress/bootstrap.ps1`. It must:

1. resolve the repository and selected env file with literal paths;
2. start Docker Desktop hidden when the engine is absent and wait up to 120 seconds;
3. run `docker compose up -d db wordpress`;
4. wait up to 180 seconds for `wp core is-installed` to succeed;
5. install WordPress once with the exact site title and administrator environment values;
6. activate `kpopblog`, set `/%postname%/`, enable user registration, and assign the new-user role `subscriber`;
7. create the `member` user only when absent;
8. create a published Home page only when no `page_on_front` exists, set `_wp_page_template=kpopblog-app`, `show_on_front=page`, and `page_on_front`;
9. print the public and administrator URLs without printing passwords.

All WP-CLI calls use `docker compose ... run --rm cli`. A failed command must terminate the script.

- [ ] **Step 6: Start and verify the runtime**

Run:

```powershell
pwsh -File scripts/wordpress/bootstrap.ps1
pwsh -File scripts/wordpress/runtime-smoke.ps1
```

Expected: both commands exit 0; the smoke test prints `WordPress runtime smoke test passed.`

- [ ] **Step 7: Commit the runtime**

```powershell
git add .env.wordpress.example .gitignore docker-compose.wordpress.yml scripts/wordpress/bootstrap.ps1 scripts/wordpress/runtime-smoke.ps1
git commit -m "test: add reproducible WordPress runtime"
```

---

### Task 2: Idempotent Plugin Installation and Capabilities

**Files:**
- Create: `wordpress-plugin/kpopblog/includes/install.php`
- Create: `scripts/wordpress/plugin-foundation-smoke.ps1`
- Modify: `wordpress-plugin/kpopblog/kpopblog.php`

**Interfaces:**
- Consumes: WordPress `$wpdb`, roles API, activation/deactivation hooks.
- Produces: `KPOPBLOG_SCHEMA_VERSION`, `kpopblog_install_or_upgrade(): void`, `kpopblog_audit(string $action, string $object_type = '', int $object_id = 0, array $details = array()): bool`, the `{prefix}kb_audit_log` table, and four plugin capabilities.

- [ ] **Step 1: Write the failing foundation smoke test**

Create `scripts/wordpress/plugin-foundation-smoke.ps1` that runs these WP-CLI assertions through the same Compose/env selection used by the runtime script:

```php
global $wpdb;
$table = $wpdb->prefix . 'kb_audit_log';
if ( get_option( 'kpopblog_schema_version' ) !== KPOPBLOG_SCHEMA_VERSION ) { throw new Exception( 'schema version mismatch' ); }
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { throw new Exception( 'audit table missing' ); }
$admin = get_role( 'administrator' );
foreach ( array( 'kb_moderate_community', 'kb_manage_automation', 'kb_manage_notifications', 'kb_manage_ads' ) as $cap ) {
    if ( ! $admin || ! $admin->has_cap( $cap ) ) { throw new Exception( 'administrator capability missing: ' . $cap ); }
}
```

The script must also execute `find /var/www/html/wp-content/plugins/kpopblog -name '*.php' -print0 | xargs -0 -n1 php -l` inside the `wordpress` service and fail on any non-zero exit code.

- [ ] **Step 2: Run the test and verify the expected failure**

Run:

```powershell
pwsh -File scripts/wordpress/plugin-foundation-smoke.ps1
```

Expected: FAIL because `KPOPBLOG_SCHEMA_VERSION` is undefined or the audit table is missing.

- [ ] **Step 3: Implement the installer and audit table**

Create `includes/install.php` with PHP 7.4 syntax only. Define `KPOPBLOG_SCHEMA_VERSION` as `1.0.0`. `kpopblog_install_or_upgrade()` loads `wp-admin/includes/upgrade.php`, calls `dbDelta()` for this exact logical schema, adds capabilities idempotently, and stores the schema version with autoload disabled:

```sql
CREATE TABLE {prefix}kb_audit_log (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
  action varchar(64) NOT NULL,
  object_type varchar(32) NOT NULL DEFAULT '',
  object_id bigint(20) unsigned NOT NULL DEFAULT 0,
  details_json longtext NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY action_created (action,created_at),
  KEY object_lookup (object_type,object_id),
  KEY actor_created (actor_id,created_at)
)
```

The administrator role receives all four capabilities. The editor role receives only `kb_moderate_community`. `kpopblog_audit()` sanitizes action/object identifiers, JSON-encodes details with `wp_json_encode`, uses current UTC time, inserts through `$wpdb->insert`, and returns whether one row was inserted.

- [ ] **Step 4: Replace anonymous lifecycle hooks**

In `kpopblog.php`, require `includes/install.php` before feature files. Replace the anonymous activation hook with `kpopblog_activate()`, which calls CPT registration, installation/upgrade, rewrite flushing, and an activation audit event. Add `kpopblog_maybe_upgrade()` on `plugins_loaded` when the stored version differs. Keep deactivation non-destructive and limit it to rewrite flushing and later scheduler cleanup.

- [ ] **Step 5: Run the foundation checks**

Deactivate and reactivate once to exercise both paths, then run:

```powershell
pwsh -File scripts/wordpress/plugin-foundation-smoke.ps1
npm run lint
npm run build:wordpress
```

Expected: PHP syntax, schema, capabilities, lint, and build all pass.

- [ ] **Step 6: Commit the plugin foundation**

```powershell
git add wordpress-plugin/kpopblog/kpopblog.php wordpress-plugin/kpopblog/includes/install.php scripts/wordpress/plugin-foundation-smoke.ps1
git commit -m "feat: add plugin installation foundation"
```

---

### Task 3: Administrator Dashboard and Health API

**Files:**
- Create: `wordpress-plugin/kpopblog/includes/admin.php`
- Create: `scripts/wordpress/admin-smoke.ps1`
- Modify: `wordpress-plugin/kpopblog/kpopblog.php`
- Modify: `wordpress-plugin/kpopblog/includes/shortcode.php`

**Interfaces:**
- Consumes: WordPress admin menu API, core counts, plugin schema/version, asset manifest, app-shell settings.
- Produces: `kpopblog-admin` top-level menu, `kpopblog_get_health_checks(): array`, `GET /wp-json/kpopblog/v1/admin/health`, and `window.kpopblogConfig.adminUrl` for administrators.

- [ ] **Step 1: Write the failing administrator smoke test**

Create `scripts/wordpress/admin-smoke.ps1`. Through WP-CLI, set the current user to the local administrator, run `do_action( 'admin_menu' )`, and assert a top-level menu item whose slug is `kpopblog-admin`. Then obtain a REST nonce through WP-CLI, request `/wp-json/kpopblog/v1/admin/health` with a logged-in WordPress cookie created by `wp_set_auth_cookie`, and assert:

- HTTP 200 for the administrator;
- `pluginVersion`, `schemaVersion`, `wordpressVersion`, `phpVersion`, and `checks` exist;
- a logged-out request returns HTTP 401 or 403;
- `checks` contains `assets`, `homepage`, `permalinks`, `registration`, and `cron` IDs.

- [ ] **Step 2: Run the test and verify the expected failure**

Run:

```powershell
pwsh -File scripts/wordpress/admin-smoke.ps1
```

Expected: FAIL because `kpopblog-admin` and `/admin/health` do not exist.

- [ ] **Step 3: Implement administrator health checks**

Create `includes/admin.php`. `kpopblog_get_health_checks()` returns arrays with exact keys `id`, `status`, `label`, `message`, and `actionUrl`; status is one of `good`, `warning`, or `critical`. Checks cover:

- a readable `assets/manifest.json` whose JS and CSS files exist;
- a configured static homepage using `KPOPBLOG_APP_TEMPLATE`;
- a non-plain permalink structure;
- WordPress user registration state;
- `DISABLE_WP_CRON` state.

Add two non-blocking production-readiness checks: `runtime_data`, which warns while the WordPress public app can use demo data, and `service_worker`, which warns until the plugin serves or disables a valid WordPress-scoped worker.

Messages contain no filesystem paths, secrets, or credentials. The REST route uses `current_user_can( 'manage_options' )`, returns core/plugin versions plus checks and counts, and sets `Cache-Control: no-store`.

- [ ] **Step 4: Implement the WordPress dashboard**

Register `K-pop Pulse Hub` as a top-level menu with Dashicon `dashicons-chart-area`, position 3, and `manage_options`. Render:

- content cards for posts and all existing KpopBlog post types;
- user, pending comment, and confirmed subscriber counts;
- health check rows with escaped labels/messages and safe action links;
- links to WordPress Posts, Users, Comments, each custom post list, Newsletter settings, KpopBlog settings, and the public homepage.

Use core admin classes and a small escaped inline style scoped under `.kpopblog-admin`; do not enqueue a front-end framework in WordPress administration. Record `admin_dashboard_viewed` through `kpopblog_audit()` no more than once per user per hour using a user-specific transient.

- [ ] **Step 5: Expose the administrator link to the React shell**

In `shortcode.php`, add `adminUrl` to `kpopblogConfig` only when `current_user_can( 'manage_options' )`; otherwise provide an empty string. Keep the existing nonce and moderation fields unchanged.

- [ ] **Step 6: Run administrator checks**

```powershell
pwsh -File scripts/wordpress/admin-smoke.ps1
pwsh -File scripts/wordpress/runtime-smoke.ps1
npm run lint
npm run build:wordpress
```

Expected: all commands exit 0 and the health response contains no critical check after the packaged assets are present.

- [ ] **Step 7: Commit the administrator dashboard**

```powershell
git add wordpress-plugin/kpopblog/kpopblog.php wordpress-plugin/kpopblog/includes/admin.php wordpress-plugin/kpopblog/includes/shortcode.php scripts/wordpress/admin-smoke.ps1
git commit -m "feat: add WordPress operations dashboard"
```

---

### Task 4: Packaging and Foundation Verification

**Files:**
- Create: `scripts/wordpress/verify-foundation.ps1`
- Modify: `wordpress-plugin/README.md`
- Modify: `wordpress-plugin/kpopblog/readme.txt`

**Interfaces:**
- Consumes: all Task 1-3 smoke scripts and existing npm scripts.
- Produces: one foundation verification command and current WordPress administrator documentation.

- [ ] **Step 1: Write the verification orchestrator**

Create `scripts/wordpress/verify-foundation.ps1` with strict error handling. It runs, in order:

```powershell
npm run lint
npm run build
npm run build:wordpress
pwsh -File scripts/wordpress/bootstrap.ps1
pwsh -File scripts/wordpress/runtime-smoke.ps1
pwsh -File scripts/wordpress/plugin-foundation-smoke.ps1
pwsh -File scripts/wordpress/admin-smoke.ps1
bash wordpress-plugin/build-plugin.sh
```

After packaging, open `wordpress-plugin/kpopblog.zip` with .NET `System.IO.Compression.ZipFile` and assert the archive contains `kpopblog/kpopblog.php`, `kpopblog/includes/install.php`, `kpopblog/includes/admin.php`, `kpopblog/assets/manifest.json`, the manifest JS path, and the manifest CSS path. The script prints `WordPress operations foundation verified.` only after all assertions pass.

- [ ] **Step 2: Update operator documentation**

Document these exact commands in `wordpress-plugin/README.md`:

```powershell
Copy-Item .env.wordpress.example .env.wordpress
pwsh -File scripts/wordpress/bootstrap.ps1
pwsh -File scripts/wordpress/verify-foundation.ps1
```

Document local URLs, the fact that local credentials are test-only, the K-pop Pulse Hub dashboard location, the five health checks, data-preserving deactivation behavior, and that Docker volumes are not removed by any repository script. Update `readme.txt` tested-up-to metadata to WordPress 7.0 and describe the operations dashboard without claiming later automation/community units are complete.

- [ ] **Step 3: Run the full verification**

```powershell
pwsh -File scripts/wordpress/verify-foundation.ps1
git status --short
```

Expected: verification prints `WordPress operations foundation verified.`; Git status shows only planned foundation files plus the pre-existing `app-shell.php` modification.

- [ ] **Step 4: Commit documentation and verification**

```powershell
git add scripts/wordpress/verify-foundation.ps1 wordpress-plugin/README.md wordpress-plugin/kpopblog/readme.txt
git commit -m "docs: document WordPress operations verification"
```

- [ ] **Step 5: Review the delivery unit against the design**

Confirm that the current state proves only delivery unit 1. Keep the overall platform goal active, then write the identity/community design and plan before implementing delivery unit 2.
