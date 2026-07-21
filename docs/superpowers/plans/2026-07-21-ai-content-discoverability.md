# AI Content Discoverability Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make automatically published articles, comebacks, and concerts discoverable as complete server-rendered HTML and through WordPress-owned robots, sitemap, RSS, and llms endpoints without changing the React application contracts.

**Architecture:** Add one focused WordPress module, `includes/discoverability.php`, that resolves public request context, emits metadata and semantic fallback HTML, and renders the four machine endpoints from published WordPress records. Keep `template.php` responsible for app-shell routing, `shortcode.php` responsible for the React mount, and `app-shell.php` responsible for filtering foreign theme/plugin output while preserving KpopBlog structured data.

**Tech Stack:** WordPress 6.2+/7.0 runtime APIs, PHP 7.4 syntax, React 19 client mount with `createRoot`, PowerShell 7 smoke tests, Docker Compose WordPress/PHP 8.2 test runtime.

## Global Constraints

- Do not add a library, database table, remote service, background job, or crawler-specific response body.
- Preserve the React mount ID `kpopblog-root`, WordPress REST response contracts, CMS TypeScript types, automation schema, database schema, and administrator screens.
- Use only published, non-password-protected WordPress records in public HTML, sitemap, RSS, and llms output.
- Keep `/news/{slug}` and `/comebacks` as the public canonical paths; do not expose WordPress-native post or CPT permalinks.
- Permit AI search, user-directed retrieval, and model-training crawlers while excluding administrative, authentication, and private API paths.
- Preserve the current mobile overflow fix and foreign-script filtering behavior in `src/styles.css` and `wordpress-plugin/kpopblog/templates/app-shell.php`.
- Do not expose OpenAI credentials, response IDs, internal confidence values, database errors, or unpublished metadata.
- Every implementation task follows red-green testing and ends with a focused commit.

## File Structure

- Create `wordpress-plugin/kpopblog/includes/discoverability.php`: request-path normalization, published-content queries, semantic page context, metadata/JSON-LD, fallback HTML, and robots/sitemap/RSS/llms renderers.
- Modify `wordpress-plugin/kpopblog/kpopblog.php`: load the discoverability module before shortcode and template integration.
- Modify `wordpress-plugin/kpopblog/includes/template.php`: remove machine endpoint ownership and preserve real `404` responses for missing news slugs.
- Modify `wordpress-plugin/kpopblog/includes/shortcode.php`: place the semantic fallback inside the existing React mount.
- Modify `wordpress-plugin/kpopblog/templates/app-shell.php`: retain KpopBlog JSON-LD while continuing to strip unrelated scripts.
- Modify `scripts/wordpress/seo-runtime-smoke.ps1`: assert current-content HTML, metadata, machine endpoints, crawler rules, and response status.
- Modify `scripts/wordpress/automation-smoke.ps1`: prove deterministic auto-published fixtures reach every discovery surface and drafts do not.
- Modify `scripts/wordpress/verify-foundation.ps1`: require the discoverability module in the packaged plugin.
- Modify `wordpress-plugin/README.md` and `wordpress-plugin/kpopblog/readme.txt`: document the public discovery endpoints and crawler-readable fallback.

---

### Task 1: WordPress Machine Discovery Endpoints

**Files:**
- Create: `wordpress-plugin/kpopblog/includes/discoverability.php`
- Modify: `wordpress-plugin/kpopblog/kpopblog.php:26-45`
- Modify: `wordpress-plugin/kpopblog/includes/template.php:25-95`
- Test: `scripts/wordpress/seo-runtime-smoke.ps1:1-33`

**Interfaces:**
- Produces: `kpopblog_request_path(): string`
- Produces: `kpopblog_public_posts( string $post_type, int $limit, string $orderby = 'modified', array $extra_args = array() ): array`
- Produces: `kpopblog_render_robots(): string`
- Produces: `kpopblog_render_sitemap(): string`
- Produces: `kpopblog_render_rss(): string`
- Produces: `kpopblog_render_llms(): string`
- Produces: `kpopblog_discovery_last_modified(): string`
- Produces: `kpopblog_serve_machine_endpoint(): void`
- Consumes: existing `kpopblog_meta()`, WordPress post types, `home_url()`, `get_bloginfo()`, and `kpopblog_xml_escape()` moved from `template.php`.

- [ ] **Step 1: Expand the runtime test before changing PHP**

Replace the endpoint assertions in `seo-runtime-smoke.ps1` with explicit checks for AI crawler rules, llms discovery, published schedules, and XML parseability:

```powershell
$baseUrl = 'http://localhost:8088'
$bundle = Invoke-RestMethod -Uri "$baseUrl/wp-json/kpopblog/v1/bundle" -TimeoutSec 30
$article = @($bundle.articles) | Select-Object -First 1
$schedule = @($bundle.comebacks) | Select-Object -First 1
if (-not $article) { throw 'SEO smoke test requires one published WordPress article.' }
if (-not $schedule) { throw 'SEO smoke test requires one published WordPress schedule.' }

$articlePath = '/news/' + [string]$article.slug
$articlePattern = [regex]::Escape($articlePath)
$scheduleAnchorPattern = [regex]::Escape('/comebacks#event-' + [string]$schedule.id)

$rss = Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/rss.xml" -TimeoutSec 30
if ($rss.StatusCode -ne 200 -or $rss.Headers['Content-Type'] -notmatch 'application/rss\+xml') {
    throw 'WordPress RSS endpoint did not return RSS XML.'
}
try { [xml]$rssXml = $rss.Content } catch { throw 'WordPress RSS endpoint returned malformed XML.' }
if ($rss.Content -notmatch $articlePattern -or $rss.Content -notmatch $scheduleAnchorPattern) {
    throw 'WordPress RSS endpoint is missing published article or schedule content.'
}

$sitemap = Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/sitemap.xml" -TimeoutSec 30
if ($sitemap.StatusCode -ne 200 -or $sitemap.Headers['Content-Type'] -notmatch 'application/xml') {
    throw 'WordPress sitemap endpoint did not return XML.'
}
try { [xml]$sitemapXml = $sitemap.Content } catch { throw 'WordPress sitemap endpoint returned malformed XML.' }
if ($sitemap.Content -notmatch $articlePattern -or $sitemap.Content -notmatch '<loc>http://localhost:8088/comebacks</loc>') {
    throw 'WordPress sitemap is missing public article or comeback calendar URLs.'
}
if (-not $sitemap.Headers['ETag'] -or -not $sitemap.Headers['Last-Modified']) {
    throw 'WordPress sitemap is missing cache validators.'
}
$notModified = Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/sitemap.xml" -Headers @{ 'If-None-Match' = [string]$sitemap.Headers['ETag'] } -SkipHttpErrorCheck -TimeoutSec 30
if ($notModified.StatusCode -ne 304) { throw 'WordPress sitemap did not honor its ETag.' }

$robots = Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/robots.txt" -TimeoutSec 30
foreach ($token in @('OAI-SearchBot', 'GPTBot', 'ClaudeBot', 'Claude-SearchBot', 'Claude-User', 'PerplexityBot', 'Perplexity-User', 'Google-Extended')) {
    if ($robots.Content -notmatch [regex]::Escape("User-agent: $token")) {
        throw "WordPress robots endpoint does not explicitly allow $token."
    }
}
foreach ($endpoint in @('sitemap.xml', 'rss.xml', 'llms.txt')) {
    if ($robots.Content -notmatch [regex]::Escape("http://localhost:8088/$endpoint")) {
        throw "WordPress robots endpoint does not advertise $endpoint."
    }
}

$llms = Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/llms.txt" -TimeoutSec 30
if ($llms.StatusCode -ne 200 -or $llms.Headers['Content-Type'] -notmatch 'text/plain') {
    throw 'WordPress llms endpoint did not return plain text.'
}
if ($llms.Content -notmatch $articlePattern -or $llms.Content -notmatch $scheduleAnchorPattern) {
    throw 'WordPress llms endpoint is missing published article or schedule links.'
}
```

- [ ] **Step 2: Run the test and confirm the missing behavior**

Run:

```powershell
pwsh -NoProfile -File scripts/wordpress/seo-runtime-smoke.ps1
```

Expected: FAIL because `/llms.txt` is not handled, AI user-agent groups are not explicit, and schedule entries are absent from RSS.

- [ ] **Step 3: Extract and implement the endpoint module**

Create `includes/discoverability.php` with independently renderable functions. Use this exact public-query guard in every content query:

```php
function kpopblog_public_posts( $post_type, $limit, $orderby = 'modified', array $extra_args = array() ) {
	$args = array(
		'post_type'      => $post_type,
		'post_status'    => 'publish',
		'has_password'   => false,
		'numberposts'    => max( 1, (int) $limit ),
		'orderby'        => $orderby,
		'order'          => 'DESC',
		'suppress_filters' => false,
	);
	return get_posts( array_merge( $args, $extra_args ) );
}

function kpopblog_request_path() {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
	$path = wp_parse_url( $request_uri, PHP_URL_PATH );
	return '/' . ltrim( (string) $path, '/' );
}

function kpopblog_xml_escape( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8' );
}
```

Build the crawler groups from one explicit list so the robots test and implementation cannot drift:

```php
function kpopblog_render_robots() {
	$private_paths = array(
		'Disallow: /wp-admin/',
		'Disallow: /wp-login.php',
		'Disallow: /admin',
		'Disallow: /moderation',
		'Disallow: /onboarding',
		'Disallow: /wp-json/kpopblog/v1/notifications',
	);
	$lines = array( 'User-agent: *', 'Allow: /' );
	$lines = array_merge( $lines, $private_paths, array( '' ) );
	$agents = array( 'OAI-SearchBot', 'GPTBot', 'ClaudeBot', 'Claude-SearchBot', 'Claude-User', 'PerplexityBot', 'Perplexity-User', 'Google-Extended' );
	foreach ( $agents as $agent ) {
		$lines[] = 'User-agent: ' . $agent;
		$lines[] = 'Allow: /';
		$lines = array_merge( $lines, $private_paths );
		$lines[] = '';
	}
	$lines[] = 'Sitemap: ' . esc_url_raw( home_url( '/sitemap.xml' ) );
	$lines[] = '# RSS: ' . esc_url_raw( home_url( '/rss.xml' ) );
	$lines[] = '# LLMs: ' . esc_url_raw( home_url( '/llms.txt' ) );
	return implode( "\n", $lines ) . "\n";
}
```

Implement `kpopblog_render_sitemap()` by retaining the existing static paths and published post-type mappings. Set the `/comebacks` entry's `modified` value from the newest published `kb_comeback`; do not add fragment URLs to the sitemap. Skip posts with an empty slug and generate each `lastmod` with `mysql2date( 'c', $post->post_modified_gmt, false )` only when the GMT value is non-empty.

Implement `kpopblog_render_rss()` with at most 50 articles and 50 `kb_comeback` records. Article links are `/news/{slug}` with GUID `post-{ID}`. Schedule links are `/comebacks#event-{ID}` with GUID `comeback-{ID}`. For schedule `pubDate`, use `post_date_gmt`; put the `kb_release_at` and `kb_type` values in the escaped description and category.

Before emitting a schedule RSS item or `Event` JSON-LD object, require a non-empty `kb_release_at` for which `strtotime()` does not return `false`. Skip only that malformed schedule item and continue rendering a valid document.

Implement `kpopblog_render_llms()` with the site title and description, the canonical site/sitemap/RSS/latest/artists/comebacks links, up to 50 newest article links, and up to 50 schedule anchor links. Strip line breaks from every title/description with `sanitize_text_field()` before concatenating plain text.

Add `kpopblog_discovery_last_modified()` that queries the newest published, non-password-protected record for each public post type (`post`, `kb_artist`, `kb_member`, `kb_video`, `kb_poll`, `kb_thread`, `kb_comeback`) and returns the greatest valid GMT modification value, falling back to `gmdate( 'Y-m-d H:i:s' )` only when no public content exists.

Route only exact root paths and emit the documented type, cache policy, and validators. Compute `$etag` from the path and last-modified value; return `304` without a body when `HTTP_IF_NONE_MATCH` equals the ETag or `HTTP_IF_MODIFIED_SINCE` is at least the latest modification timestamp:

```php
function kpopblog_serve_machine_endpoint() {
	$renderers = array(
		'/robots.txt'  => array( 'text/plain; charset=utf-8', 'kpopblog_render_robots' ),
		'/sitemap.xml' => array( 'application/xml; charset=utf-8', 'kpopblog_render_sitemap' ),
		'/rss.xml'     => array( 'application/rss+xml; charset=utf-8', 'kpopblog_render_rss' ),
		'/llms.txt'    => array( 'text/plain; charset=utf-8', 'kpopblog_render_llms' ),
	);
	$path = kpopblog_request_path();
	if ( ! isset( $renderers[ $path ] ) ) { return; }
	$last_modified = kpopblog_discovery_last_modified();
	$last_timestamp = strtotime( $last_modified . ' UTC' );
	$etag = '"' . md5( $path . '|' . $last_modified ) . '"';
	header( 'ETag: ' . $etag );
	header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $last_timestamp ) . ' GMT' );
	$if_none_match = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? trim( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) : '';
	$if_modified_since = isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ? strtotime( wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) : false;
	if ( $etag === $if_none_match || ( false !== $if_modified_since && $if_modified_since >= $last_timestamp ) ) {
		status_header( 304 );
		exit;
	}
	status_header( 200 );
	header( 'Content-Type: ' . $renderers[ $path ][0] );
	header( 'Cache-Control: public, max-age=300, must-revalidate' );
	echo call_user_func( $renderers[ $path ][1] );
	exit;
}
add_action( 'template_redirect', 'kpopblog_serve_machine_endpoint', 0 );
```

Load `discoverability.php` after `rest.php` in `kpopblog.php`, then delete `kpopblog_xml_escape()` and `kpopblog_serve_machine_endpoint()` from `template.php`. Do not change the app-shell routing functions in this task.

- [ ] **Step 4: Run endpoint and PHP checks**

Run:

```powershell
docker compose --env-file .env.wordpress.example -f docker-compose.wordpress.yml exec -T wordpress php -l /var/www/html/wp-content/plugins/kpopblog/includes/discoverability.php
pwsh -NoProfile -File scripts/wordpress/seo-runtime-smoke.ps1
```

Expected: PHP reports `No syntax errors detected`; the smoke test prints `WordPress SEO runtime smoke test passed.`

- [ ] **Step 5: Commit the endpoint unit**

```powershell
git add wordpress-plugin/kpopblog/includes/discoverability.php wordpress-plugin/kpopblog/includes/template.php wordpress-plugin/kpopblog/kpopblog.php scripts/wordpress/seo-runtime-smoke.ps1
git commit -m "feat: expose WordPress content discovery feeds"
```

---

### Task 2: Server-Rendered Article and Comeback Context

**Files:**
- Modify: `wordpress-plugin/kpopblog/includes/discoverability.php`
- Modify: `wordpress-plugin/kpopblog/includes/template.php:116-125`
- Modify: `wordpress-plugin/kpopblog/includes/shortcode.php:64-73`
- Modify: `wordpress-plugin/kpopblog/templates/app-shell.php:30-37`
- Test: `scripts/wordpress/seo-runtime-smoke.ps1`

**Interfaces:**
- Consumes: `kpopblog_request_path()` from Task 1.
- Produces: `kpopblog_prepare_public_context(): void`
- Produces: `kpopblog_get_public_context(): array`
- Produces: `kpopblog_public_context_is_missing(): bool`
- Produces: `kpopblog_public_source_urls( int $post_id ): array`
- Produces: `kpopblog_render_public_sources( int $post_id ): string`
- Produces: `kpopblog_build_public_json_ld( array $context ): array`
- Produces: `kpopblog_render_public_head(): void`
- Produces: `kpopblog_render_public_fallback(): string`
- `shortcode.php` consumes `kpopblog_render_public_fallback()` and keeps `kpopblog-root` unchanged.

- [ ] **Step 1: Add failing raw-HTML and status assertions**

Append these checks to `seo-runtime-smoke.ps1` after loading `$article`:

```powershell
$articleUrl = "$baseUrl/news/$($article.slug)"
$crawlerBodies = @{}
foreach ($agent in @('OAI-SearchBot', 'GPTBot', 'Claude-SearchBot', 'PerplexityBot', 'Googlebot')) {
    $response = Invoke-WebRequest -UseBasicParsing -Uri $articleUrl -Headers @{ 'User-Agent' = $agent } -TimeoutSec 30
    if ($response.StatusCode -ne 200) { throw "Article request failed for $agent." }
    foreach ($pattern in @(
        [regex]::Escape([string]$article.title),
        '<article[^>]+data-kpopblog-fallback="article"',
        '<meta[^>]+name="description"',
        '<link[^>]+rel="canonical"[^>]+/news/',
        'application/ld\+json',
        '"@type":"NewsArticle"'
    )) {
        if ($response.Content -notmatch $pattern) { throw "Article HTML is missing $pattern for $agent." }
    }
    $crawlerBodies[$agent] = $response.Content
}
$referenceBody = $crawlerBodies['OAI-SearchBot']
foreach ($body in $crawlerBodies.Values) {
    if ($body -ne $referenceBody) { throw 'Crawler user agents received different article HTML.' }
}

$comebacks = Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/comebacks" -Headers @{ 'User-Agent' = 'Claude-SearchBot' } -TimeoutSec 30
if ($comebacks.Content -notmatch '<section[^>]+data-kpopblog-fallback="comebacks"' -or $comebacks.Content -notmatch '"@type":"Event"') {
    throw 'Comeback calendar is missing semantic fallback content or Event JSON-LD.'
}

$missingSlug = 'automation-discovery-missing-' + [guid]::NewGuid().ToString('N')
$missing = Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/news/$missingSlug" -SkipHttpErrorCheck -TimeoutSec 30
if ($missing.StatusCode -ne 404 -or $missing.Content -notmatch 'noindex, nofollow') {
    throw 'Missing article did not return a non-indexable 404.'
}
```

- [ ] **Step 2: Run the test and confirm semantic rendering is absent**

Run:

```powershell
pwsh -NoProfile -File scripts/wordpress/seo-runtime-smoke.ps1
```

Expected: FAIL because the raw app shell has no fallback `<article>`, description, KpopBlog canonical, or JSON-LD.

- [ ] **Step 3: Resolve one public context before template rendering**

Add a request-scoped global context in `discoverability.php`. Use exact path matching and never resolve drafts or password-protected posts:

```php
function kpopblog_get_public_context() {
	return isset( $GLOBALS['kpopblog_public_context'] ) && is_array( $GLOBALS['kpopblog_public_context'] )
		? $GLOBALS['kpopblog_public_context']
		: array( 'kind' => 'none' );
}

function kpopblog_prepare_public_context() {
	$path = kpopblog_request_path();
	if ( preg_match( '#^/news/([^/]+)/?$#', $path, $matches ) ) {
		$slug = sanitize_title( rawurldecode( $matches[1] ) );
		$post = $slug !== '' ? get_page_by_path( $slug, OBJECT, 'post' ) : null;
		if ( ! ( $post instanceof WP_Post ) || 'publish' !== $post->post_status || post_password_required( $post ) ) {
			$GLOBALS['kpopblog_public_context'] = array( 'kind' => 'missing_article' );
			status_header( 404 );
			return;
		}
		$GLOBALS['kpopblog_public_context'] = array( 'kind' => 'article', 'post' => $post );
		status_header( 200 );
		return;
	}
	if ( '/comebacks' === untrailingslashit( $path ) ) {
		$GLOBALS['kpopblog_public_context'] = array(
			'kind'  => 'comebacks',
			'posts' => kpopblog_public_posts( 'kb_comeback', 200, 'meta_value', array(
				'meta_key' => 'kb_release_at',
				'order'    => 'ASC',
			) ),
		);
	}
}
add_action( 'template_redirect', 'kpopblog_prepare_public_context', 1 );

function kpopblog_public_context_is_missing() {
	return 'missing_article' === ( kpopblog_get_public_context()['kind'] ?? 'none' );
}
```

For comeback ordering, call `kpopblog_public_posts()` with `meta_key => kb_release_at`, `orderby => meta_value`, and `order => ASC`; pass only fixed internal arguments, never request input.

Modify `kpopblog_template_include()` so its generic SPA `200` conversion does not overwrite a missing article:

```php
if ( is_404() && ! kpopblog_public_context_is_missing() ) {
	status_header( 200 );
}
```

- [ ] **Step 4: Emit canonical metadata and structured data from the same context**

Add a bounded description helper using existing WordPress text functions:

```php
function kpopblog_public_description( WP_Post $post ) {
	$text = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_strip_all_tags( $post->post_content, true );
	return wp_html_excerpt( preg_replace( '/\s+/', ' ', trim( $text ) ), 300, '…' );
}
```

Register `pre_get_document_title`, `wp_robots`, and `wp_head` filters/actions. For article JSON-LD use `NewsArticle` with `headline`, `description`, `datePublished`, `dateModified`, `mainEntityOfPage`, `author`, `publisher`, optional `image`, and `citation` from validated `kb_source_urls`. For `/comebacks`, use `CollectionPage` with a `mainEntity` `ItemList`; each list item wraps an `Event` whose URL is `home_url( '/comebacks#event-' . $post->ID )`, name is the post title, start date is `kb_release_at`, event status is `https://schema.org/EventScheduled`, and description is stripped post content.

Use this exact hook behavior so missing content cannot inherit an indexable title or canonical:

```php
function kpopblog_filter_public_title( $title ) {
	$context = kpopblog_get_public_context();
	if ( 'article' === ( $context['kind'] ?? '' ) ) {
		return get_the_title( $context['post'] ) . ' — ' . get_bloginfo( 'name' );
	}
	if ( 'comebacks' === ( $context['kind'] ?? '' ) ) {
		return 'Comeback Schedule — ' . get_bloginfo( 'name' );
	}
	if ( 'missing_article' === ( $context['kind'] ?? '' ) ) {
		return 'Article not found — ' . get_bloginfo( 'name' );
	}
	return $title;
}
add_filter( 'pre_get_document_title', 'kpopblog_filter_public_title' );

function kpopblog_filter_public_robots( $robots ) {
	if ( kpopblog_public_context_is_missing() ) {
		$robots['noindex'] = true;
		$robots['nofollow'] = true;
		unset( $robots['index'], $robots['follow'] );
	}
	return $robots;
}
add_filter( 'wp_robots', 'kpopblog_filter_public_robots' );
```

Build article structured data from the resolved post and comeback structured data from the context array:

```php
function kpopblog_build_public_json_ld( array $context ) {
	if ( 'article' === ( $context['kind'] ?? '' ) ) {
		$post = $context['post'];
		$author = get_userdata( $post->post_author );
		$canonical = home_url( '/news/' . $post->post_name );
		$data = array(
			'@context'         => 'https://schema.org',
			'@type'            => 'NewsArticle',
			'headline'         => get_the_title( $post ),
			'description'      => kpopblog_public_description( $post ),
			'datePublished'    => mysql2date( 'c', $post->post_date_gmt, false ),
			'dateModified'     => mysql2date( 'c', $post->post_modified_gmt, false ),
			'mainEntityOfPage' => array( '@type' => 'WebPage', '@id' => $canonical ),
			'author'           => array( '@type' => 'Person', 'name' => $author ? $author->display_name : get_bloginfo( 'name' ) ),
			'publisher'        => array( '@type' => 'Organization', 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) ),
			'citation'         => kpopblog_public_source_urls( $post->ID ),
		);
		$image = kpopblog_thumb_url( $post->ID );
		if ( $image ) { $data['image'] = array( $image ); }
		return $data;
	}
	$items = array();
	foreach ( $context['posts'] ?? array() as $post ) {
		$release = (string) get_post_meta( $post->ID, 'kb_release_at', true );
		if ( '' === $release || false === strtotime( $release ) ) { continue; }
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => count( $items ) + 1,
			'item'     => array(
				'@type'       => 'Event',
				'name'        => get_the_title( $post ),
				'url'         => home_url( '/comebacks#event-' . $post->ID ),
				'startDate'   => $release,
				'eventStatus' => 'https://schema.org/EventScheduled',
				'description' => wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 ),
			),
		);
	}
	return array(
		'@context'   => 'https://schema.org',
		'@type'      => 'CollectionPage',
		'name'       => 'Comeback Schedule',
		'url'        => home_url( '/comebacks' ),
		'mainEntity' => array( '@type' => 'ItemList', 'itemListElement' => $items ),
	);
}
```

In `kpopblog_prepare_public_context()`, call `remove_action( 'wp_head', 'rel_canonical' )` after setting an `article`, `comebacks`, or `missing_article` context. Implement `kpopblog_render_public_head()` to return immediately for `none` or `missing_article`, select article/comeback title, description, canonical, and Open Graph type, call `kpopblog_build_public_json_ld()`, and emit the escaped tags below.

Render metadata with escaping and a stable script marker:

```php
echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
echo '<meta property="og:url" content="' . esc_url( $canonical ) . '" />' . "\n";
echo '<script id="kpopblog-discovery-jsonld" type="application/ld+json">' . wp_json_encode( $json_ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
```

Remove WordPress's `rel_canonical` action only for article or comeback context before `wp_head()` runs. For `missing_article`, add `noindex` and `nofollow` through the `wp_robots` filter and emit no canonical or JSON-LD.

- [ ] **Step 5: Render fallback content inside the existing mount**

Implement `kpopblog_render_public_fallback()` with two exact branches:

```php
function kpopblog_render_public_fallback() {
	$context = kpopblog_get_public_context();
	if ( 'article' === ( $context['kind'] ?? '' ) ) {
		$post = $context['post'];
		$published = mysql2date( 'c', $post->post_date_gmt, false );
		$modified = mysql2date( 'c', $post->post_modified_gmt, false );
		$html  = '<article data-kpopblog-fallback="article">';
		$html .= '<h1>' . esc_html( get_the_title( $post ) ) . '</h1>';
		$html .= '<p><time datetime="' . esc_attr( $published ) . '">' . esc_html( $published ) . '</time>';
		if ( $modified !== $published ) { $html .= ' · Updated <time datetime="' . esc_attr( $modified ) . '">' . esc_html( $modified ) . '</time>'; }
		$html .= '</p><div>' . wp_kses_post( apply_filters( 'the_content', $post->post_content ) ) . '</div>';
		$html .= kpopblog_render_public_sources( $post->ID );
		return $html . '</article>';
	}
	if ( 'comebacks' === ( $context['kind'] ?? '' ) ) {
		$html = '<section data-kpopblog-fallback="comebacks"><h1>Comeback Schedule</h1>';
		foreach ( $context['posts'] as $post ) {
			$release = (string) get_post_meta( $post->ID, 'kb_release_at', true );
			$html .= '<article id="event-' . (int) $post->ID . '"><h2>' . esc_html( get_the_title( $post ) ) . '</h2>';
			$html .= '<p>' . esc_html( (string) get_post_meta( $post->ID, 'kb_type', true ) ) . ' · <time datetime="' . esc_attr( $release ) . '">' . esc_html( $release ) . '</time></p>';
			$html .= '<div>' . wp_kses_post( apply_filters( 'the_content', $post->post_content ) ) . '</div>';
			$html .= kpopblog_render_public_sources( $post->ID ) . '</article>';
		}
		return $html . '</section>';
	}
	return '';
}
```

Implement source validation and rendering once, then reuse the URL list for JSON-LD, HTML, RSS, and llms output:

```php
function kpopblog_public_source_urls( $post_id ) {
	$raw_urls = get_post_meta( $post_id, 'kb_source_urls', true );
	$raw_urls = is_array( $raw_urls ) ? $raw_urls : array();
	$single = (string) get_post_meta( $post_id, 'kb_source_url', true );
	if ( $single !== '' ) { array_unshift( $raw_urls, $single ); }
	$urls = array();
	foreach ( $raw_urls as $raw_url ) {
		$url = esc_url_raw( trim( (string) $raw_url ), array( 'https' ) );
		if ( '' === $url || 0 !== stripos( $url, 'https://' ) || ! wp_http_validate_url( $url ) || in_array( $url, $urls, true ) ) { continue; }
		$urls[] = $url;
		if ( count( $urls ) >= 5 ) { break; }
	}
	return $urls;
}

function kpopblog_render_public_sources( $post_id ) {
	$urls = kpopblog_public_source_urls( $post_id );
	if ( empty( $urls ) ) { return ''; }
	$primary_title = sanitize_text_field( (string) get_post_meta( $post_id, 'kb_source_title', true ) );
	$html = '<section aria-labelledby="kpopblog-source-heading"><h2 id="kpopblog-source-heading">Sources</h2><ul>';
	foreach ( $urls as $index => $url ) {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		$label = 0 === $index && '' !== $primary_title ? $primary_title : $host;
		$html .= '<li><a href="' . esc_url( $url ) . '" rel="noopener noreferrer nofollow">' . esc_html( $label ) . '</a></li>';
	}
	return $html . '</ul></section>';
}
```

Change the shortcode return value without changing its ID or route attribute:

```php
return sprintf(
	'<div id="kpopblog-root" data-initial-route="%s">%s</div>',
	esc_attr( $atts['route'] ),
	function_exists( 'kpopblog_render_public_fallback' ) ? kpopblog_render_public_fallback() : ''
);
```

Update the app-shell script filter to preserve only the application bundle and the KpopBlog JSON-LD marker:

```php
$html = preg_replace_callback( '/<script\b[^>]*>.*?<\/script>/is', function ( $m ) {
	$allowed = strpos( $m[0], 'kpopblog-app' ) !== false || strpos( $m[0], 'kpopblog-discovery-jsonld' ) !== false;
	return $allowed ? $m[0] : '';
}, $html );
```

- [ ] **Step 6: Run semantic HTML, PHP, and WordPress checks**

Run:

```powershell
docker compose --env-file .env.wordpress.example -f docker-compose.wordpress.yml exec -T wordpress php -l /var/www/html/wp-content/plugins/kpopblog/includes/discoverability.php
docker compose --env-file .env.wordpress.example -f docker-compose.wordpress.yml exec -T wordpress php -l /var/www/html/wp-content/plugins/kpopblog/includes/shortcode.php
docker compose --env-file .env.wordpress.example -f docker-compose.wordpress.yml exec -T wordpress php -l /var/www/html/wp-content/plugins/kpopblog/templates/app-shell.php
pwsh -NoProfile -File scripts/wordpress/seo-runtime-smoke.ps1
```

Expected: all PHP files report no syntax errors and the SEO smoke test passes for all crawler agents.

- [ ] **Step 7: Commit semantic rendering**

```powershell
git add wordpress-plugin/kpopblog/includes/discoverability.php wordpress-plugin/kpopblog/includes/template.php wordpress-plugin/kpopblog/includes/shortcode.php wordpress-plugin/kpopblog/templates/app-shell.php scripts/wordpress/seo-runtime-smoke.ps1
git commit -m "feat: render crawler-readable WordPress content"
```

---

### Task 3: Auto-Publication-to-Discovery Integration

**Files:**
- Modify: `scripts/wordpress/automation-smoke.ps1:125-198`
- Modify if the test exposes a defect: `wordpress-plugin/kpopblog/includes/discoverability.php`
- Test: `scripts/wordpress/automation-smoke.ps1`

**Interfaces:**
- Consumes: the real `kpopblog_run_automation( 'smoke' )` fixture run and all Task 1/2 public interfaces.
- Produces: deterministic proof that the exact automation-created article and schedule are exposed identically to allowed crawlers while a draft and missing slug remain non-public.

- [ ] **Step 1: Add integration assertions while automation fixtures still exist**

Inside the existing `try` block, after `$created_ids` validation and before the second automation run, identify the two generated post types and create one draft control:

```php
$article_id = 0;
$schedule_id = 0;
foreach ( $created_ids as $post_id ) {
	$post_type = get_post_type( $post_id );
	if ( 'post' === $post_type ) { $article_id = (int) $post_id; }
	if ( 'kb_comeback' === $post_type ) { $schedule_id = (int) $post_id; }
}
if ( ! $article_id || ! $schedule_id ) {
	throw new Exception( 'automation fixture post types are incomplete' );
}
$draft_id = wp_insert_post( array(
	'post_type'    => 'post',
	'post_status'  => 'draft',
	'post_title'   => 'Automation discovery private draft',
	'post_name'    => 'automation-discovery-private-draft',
	'post_content' => 'This draft must never appear in public discovery output.',
) );
if ( ! $draft_id || is_wp_error( $draft_id ) ) {
	throw new Exception( 'automation discovery draft fixture could not be created' );
}
```

Use the Compose service hostname for the request while preserving the configured public host:

```php
$fetch = function ( $path, $agent ) {
	return wp_remote_get( 'http://wordpress' . $path, array(
		'timeout' => 30,
		'headers' => array( 'Host' => 'localhost:8088', 'User-Agent' => $agent ),
	) );
};
$article_slug = get_post_field( 'post_name', $article_id );
$article_path = '/news/' . $article_slug;
$reference_html = '';
foreach ( array( 'OAI-SearchBot', 'GPTBot', 'Claude-SearchBot', 'PerplexityBot', 'Googlebot' ) as $agent ) {
	$response = $fetch( $article_path, $agent );
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		throw new Exception( 'crawler article request failed for ' . $agent );
	}
	$html = wp_remote_retrieve_body( $response );
	foreach ( array( get_the_title( $article_id ), 'data-kpopblog-fallback="article"', 'NewsArticle', 'https://example.com/bts-release' ) as $needle ) {
		if ( false === strpos( $html, $needle ) ) { throw new Exception( 'crawler article response missing ' . $needle ); }
	}
	if ( '' === $reference_html ) { $reference_html = $html; }
	if ( $html !== $reference_html ) { throw new Exception( 'crawler-specific HTML detected' ); }
}
```

Fetch `/sitemap.xml`, `/rss.xml`, `/llms.txt`, and `/comebacks`; assert the article path appears in all three machine documents, `/comebacks#event-{schedule ID}` appears in RSS and llms, the schedule title appears in `/comebacks`, and the draft slug appears nowhere. Fetch `/news/automation-discovery-private-draft` and a random missing slug; assert both return `404`, contain `noindex, nofollow`, and do not contain draft body content.

Initialize `$draft_id = 0` before the `try`, then add this exact cleanup inside `finally`:

```php
if ( $draft_id ) { wp_delete_post( (int) $draft_id, true ); }
```

- [ ] **Step 2: Run the integration test and observe the first uncovered defect**

Run:

```powershell
pwsh -NoProfile -File scripts/wordpress/automation-smoke.ps1
```

Expected before any needed correction: either PASS if Tasks 1/2 fully satisfy the integration, or FAIL with the first exact missing surface/status assertion. A PASS is acceptable because the new test is a higher-level integration gate over already test-driven units.

- [ ] **Step 3: Correct only behavior demonstrated by a failing assertion**

If the test fails, change only the relevant renderer or context guard in `discoverability.php`. Do not alter automation validation, persistence, post types, or metadata. Re-run the single failing smoke test after each correction until it prints `AI automation smoke test passed.`

- [ ] **Step 4: Run both discovery integration suites**

```powershell
pwsh -NoProfile -File scripts/wordpress/automation-smoke.ps1
pwsh -NoProfile -File scripts/wordpress/seo-runtime-smoke.ps1
```

Expected: both scripts pass and remove their deterministic fixtures.

- [ ] **Step 5: Commit the integration gate**

```powershell
git add scripts/wordpress/automation-smoke.ps1 wordpress-plugin/kpopblog/includes/discoverability.php
git commit -m "test: verify automated content discovery"
```

---

### Task 4: Packaging, Documentation, and Browser Regression

**Files:**
- Modify: `scripts/wordpress/verify-foundation.ps1:44-91`
- Modify: `wordpress-plugin/README.md`
- Modify: `wordpress-plugin/kpopblog/readme.txt`
- Verify: `src/entry-wordpress.tsx`
- Verify: `src/routes/news.$slug.tsx`
- Verify: `src/routes/comebacks.tsx`

**Interfaces:**
- Consumes: completed discoverability module and smoke tests.
- Produces: packaged plugin coverage, operator documentation, and browser proof that `createRoot()` replaces rather than duplicates fallback HTML.

- [ ] **Step 1: Add the new module to the package manifest assertion**

Add this entry to `$requiredEntries` in `verify-foundation.ps1`:

```powershell
'kpopblog/includes/discoverability.php',
```

- [ ] **Step 2: Document the public machine interface**

Add the following operational contract to `wordpress-plugin/README.md` and a compact equivalent to `wordpress-plugin/kpopblog/readme.txt`:

```markdown
### AI and search discoverability

Published WordPress articles are present in the initial `/news/{slug}` HTML with canonical metadata and `NewsArticle` JSON-LD before the React application starts. The `/comebacks` response includes a semantic schedule fallback and `Event` structured data. React replaces this fallback in JavaScript-capable browsers; crawlers and no-script clients receive the same published WordPress content.

The plugin serves `/robots.txt`, `/sitemap.xml`, `/rss.xml`, and `/llms.txt` from published WordPress records. Search, user-directed AI retrieval, and model-training crawlers are allowed. Drafts, password-protected content, administrator routes, authentication routes, and private notification APIs are excluded.
```

- [ ] **Step 3: Build and run the focused release gates**

Run:

```powershell
npm.cmd run build
npm.cmd run build:wordpress
pwsh -NoProfile -File scripts/wordpress/automation-smoke.ps1
pwsh -NoProfile -File scripts/wordpress/seo-runtime-smoke.ps1
```

Expected: both builds exit `0`; both smoke tests pass.

- [ ] **Step 4: Run repository lint and distinguish inherited failures**

Run:

```powershell
npm.cmd run lint
```

Expected: exit `0`. If it fails, record exact paths and verify no failure is in a touched file before proceeding; fix any touched-file failure.

- [ ] **Step 5: Verify real browser replacement and interaction**

Open the current article slug from `/wp-json/kpopblog/v1/bundle` at `http://localhost:8088/news/{slug}` and `/comebacks` in a real browser. Verify:

- one visible article/calendar view after React loads;
- no remaining `[data-kpopblog-fallback]` element after `createRoot().render()`;
- article title and comeback list remain visible;
- direct refresh remains on the same route;
- no console exception or failed KpopBlog asset/REST request;
- desktop and mobile widths retain navigation and do not introduce horizontal overflow.

Expected: React replaces the server fallback cleanly on both routes and the existing user experience is unchanged.

- [ ] **Step 6: Run the packaged-plugin verification**

```powershell
pwsh -NoProfile -File scripts/wordpress/verify-foundation.ps1
```

Expected: `WordPress operations foundation verified.` and the generated archive contains `kpopblog/includes/discoverability.php`.

- [ ] **Step 7: Commit packaging and documentation**

```powershell
git add scripts/wordpress/verify-foundation.ps1 wordpress-plugin/README.md wordpress-plugin/kpopblog/readme.txt
git commit -m "docs: document AI content discovery"
```

---

## Final Verification

Run from `C:\Users\TK\orca\k-pop-pulse-hub` with Docker Desktop and the local WordPress stack available:

```powershell
git status --short
npm.cmd run lint
npm.cmd run build
npm.cmd run build:wordpress
pwsh -NoProfile -File scripts/wordpress/automation-smoke.ps1
pwsh -NoProfile -File scripts/wordpress/seo-runtime-smoke.ps1
pwsh -NoProfile -File scripts/wordpress/verify-foundation.ps1
```

Expected final state: all commands pass, the worktree contains only intentional changes or is clean after commits, automatically published fixtures are removed by the smoke tests, and browser verification shows one interactive React view with no console errors.
