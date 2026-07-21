[CmdletBinding()]
param(
    [switch]$InjectPostCreationParseFailure
)

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$compose = Join-Path $repoRoot 'docker-compose.wordpress.yml'
$envFile = Join-Path $repoRoot '.env.wordpress'
if (-not (Test-Path -LiteralPath $envFile)) {
    $envFile = Join-Path $repoRoot '.env.wordpress.example'
}
$projectName = 'k-pop-pulse-hub'
$baseUrl = 'http://localhost:8088'
$temporaryScheduleId = 0
$temporaryArticleId = 0
$secondaryScheduleId = 0
$malformedScheduleId = 0
$fixtureMarker = 'seo-runtime-smoke:' + [guid]::NewGuid().ToString('N')

function Invoke-WpCli {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Arguments)
    $output = & docker compose --project-name $projectName --env-file $envFile -f $compose run --rm --no-deps cli @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "WP-CLI command failed: wp $($Arguments -join ' ')"
    }
    return @($output)
}

function Convert-WpCliJson {
    param([Parameter(Mandatory = $true)][object[]]$Output)
    $json = [string](@($Output) | Select-Object -Last 1)
    try {
        return $json | ConvertFrom-Json -DateKind String -ErrorAction Stop
    } catch {
        throw "WP-CLI did not return valid JSON: $json"
    }
}

function Get-NotificationState {
    param([Parameter(Mandatory = $true)][string]$Marker)
    $stateScriptTemplate = @'
global $wpdb;
$jobs_table = $wpdb->prefix . 'kb_notification_jobs';
$fixture_title = 'Comeback: KpopBlog SEO runtime temporary schedule';
$fixture_marker = '__FIXTURE_MARKER__';
echo wp_json_encode( array(
    'job_count'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_table}" ),
    'temp_job_count'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$jobs_table} WHERE title = %s", $fixture_title ) ),
    'next_scheduled'   => (int) ( wp_next_scheduled( 'kpopblog_process_notification_jobs' ) ?: 0 ),
    'marker_post_count'=> (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID WHERE pm.meta_key = %s AND pm.meta_value LIKE %s",
        'kb_source',
        $wpdb->esc_like( $fixture_marker ) . '%'
    ) ),
) );
'@
    $stateScript = $stateScriptTemplate.Replace('__FIXTURE_MARKER__', $Marker)
    return Convert-WpCliJson (Invoke-WpCli 'eval' $stateScript)
}

function Remove-TemporaryFixtures {
    param([Parameter(Mandatory = $true)][string]$Marker)
    $cleanupScriptTemplate = @'
global $wpdb;
$fixture_marker = '__FIXTURE_MARKER__';
$ids = $wpdb->get_col( $wpdb->prepare(
    "SELECT DISTINCT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID WHERE pm.meta_key = %s AND pm.meta_value LIKE %s",
    'kb_source',
    $wpdb->esc_like( $fixture_marker ) . '%'
) );
if ( ! $ids ) {
    echo wp_json_encode( array( 'deleted_ids' => array(), 'outbound_requests' => 0 ) );
    return;
}
if ( count( $ids ) > 4 ) {
    throw new Exception( 'SEO smoke marker matched too many posts; refusing cleanup' );
}
$expected = array(
    $fixture_marker                => array( 'kb_comeback', 'KpopBlog SEO runtime temporary schedule' ),
    $fixture_marker . ':article'   => array( 'post', 'KpopBlog SEO malicious </script><script id="review-injected">injected</script> article' ),
    $fixture_marker . ':secondary' => array( 'kb_comeback', 'KpopBlog SEO runtime secondary schedule' ),
    $fixture_marker . ':malformed' => array( 'kb_comeback', 'KpopBlog SEO runtime malformed schedule' ),
);
$outbound_requests = 0;
$deleted_ids = array();
$http_guard = function () use ( &$outbound_requests ) {
    $outbound_requests++;
    return new WP_Error( 'seo_smoke_blocked_http', 'SEO smoke cleanup attempted an outbound HTTP request.' );
};
add_filter( 'pre_http_request', $http_guard, PHP_INT_MAX );
try {
    foreach ( $ids as $id ) {
        $post_id = (int) $id;
        $post = get_post( $post_id );
        $marker = (string) get_post_meta( $post_id, 'kb_source', true );
        if ( ! $post || ! isset( $expected[ $marker ] ) || $expected[ $marker ][0] !== $post->post_type || $expected[ $marker ][1] !== $post->post_title ) {
            throw new Exception( 'SEO smoke marker post identity mismatch; refusing cleanup' );
        }
        if ( ! wp_delete_post( $post_id, true ) || get_post( $post_id ) ) {
            throw new Exception( 'SEO smoke marker post cleanup failed' );
        }
        $deleted_ids[] = $post_id;
    }
} finally {
    remove_filter( 'pre_http_request', $http_guard, PHP_INT_MAX );
}
if ( 0 !== $outbound_requests ) {
    throw new Exception( 'SEO smoke marker cleanup attempted an outbound webhook' );
}
echo wp_json_encode( array( 'deleted_ids' => $deleted_ids, 'outbound_requests' => $outbound_requests ) );
'@
    $cleanupScript = $cleanupScriptTemplate.Replace('__FIXTURE_MARKER__', $Marker)
    $cleanupResult = Convert-WpCliJson (Invoke-WpCli 'eval' $cleanupScript)
    if ([int]$cleanupResult.outbound_requests -ne 0) {
        throw 'SEO smoke cleanup attempted an outbound webhook.'
    }
}

function Assert-NotificationStateUnchanged {
    param(
        [Parameter(Mandatory = $true)][object]$Before,
        [Parameter(Mandatory = $true)][object]$After
    )
    foreach ($property in @('job_count', 'temp_job_count', 'next_scheduled', 'marker_post_count')) {
        if ([string]$Before.$property -ne [string]$After.$property) {
            throw "SEO smoke fixture changed notification state $property from $($Before.$property) to $($After.$property)."
        }
    }
}

$notificationStateBefore = Get-NotificationState -Marker $fixtureMarker
if ([int]$notificationStateBefore.temp_job_count -ne 0) {
    throw 'SEO smoke test found a pre-existing temporary schedule notification job.'
}
if ([int]$notificationStateBefore.marker_post_count -ne 0) {
    throw 'SEO smoke test generated a duplicate unique fixture marker.'
}

try {
    $bundle = Invoke-RestMethod -Uri "$baseUrl/wp-json/kpopblog/v1/bundle" -TimeoutSec 30
    $article = @($bundle.articles) | Select-Object -First 1
    if (-not $article) { throw 'SEO smoke test requires one published WordPress article.' }

    $fixtureTemplate = @'
global $wpdb;
$jobs_table = $wpdb->prefix . 'kb_notification_jobs';
$fixture_title = 'Comeback: KpopBlog SEO runtime temporary schedule';
$fixture_marker = '__FIXTURE_MARKER__';
$malicious_title = 'KpopBlog SEO malicious </script><script id="review-injected">injected</script> article';
$malicious_description = 'Review description </script><script id="review-injected-description">injected</script> text.';
$primary_source = 'https://example.com/kpopblog-primary?ref=seo&item=1';
$secondary_source = 'https://example.org/kpopblog-secondary';
$job_count_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_table}" );
$temp_job_count_before = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$jobs_table} WHERE title = %s", $fixture_title ) );
$next_scheduled_before = (int) ( wp_next_scheduled( 'kpopblog_process_notification_jobs' ) ?: 0 );
$outbound_requests = 0;
$created_ids = array();
$http_guard = function () use ( &$outbound_requests ) {
    $outbound_requests++;
    return new WP_Error( 'seo_smoke_blocked_http', 'SEO smoke fixture attempted an outbound HTTP request.' );
};
add_filter( 'pre_http_request', $http_guard, PHP_INT_MAX );
try {
    $create_fixture = function ( $post_type, $title, $slug, array $columns, array $meta ) use ( &$created_ids, $wpdb ) {
        $post_id = wp_insert_post( array(
            'post_type'    => $post_type,
            'post_title'   => 'KpopBlog SEO runtime fixture pending publication',
            'post_content' => 'Temporary content created and removed by seo-runtime-smoke.ps1.',
            'post_status'  => 'draft',
            'post_name'    => $slug,
        ), true );
        if ( is_wp_error( $post_id ) ) {
            throw new Exception( 'failed to create temporary SEO fixture: ' . $post_id->get_error_message() );
        }
        $created_ids[] = (int) $post_id;
        foreach ( $meta as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }
        $columns = array_merge( array( 'post_status' => 'publish', 'post_title' => $title ), $columns );
        $updated = $wpdb->update( $wpdb->posts, $columns, array( 'ID' => $post_id ) );
        if ( 1 !== $updated ) {
            throw new Exception( 'failed to publish temporary SEO fixture without hooks' );
        }
        clean_post_cache( $post_id );
        $published = get_post( $post_id );
        if ( ! $published || 'publish' !== $published->post_status || $title !== $published->post_title ) {
            throw new Exception( 'temporary SEO fixture is not query-visible as published' );
        }
        return (int) $post_id;
    };

    $post_id = $create_fixture(
        'kb_comeback',
        'KpopBlog SEO runtime temporary schedule',
        '',
        array(
            'post_content'      => 'Primary temporary schedule created and removed by seo-runtime-smoke.ps1.',
            'post_date'         => '2030-01-01 03:00:00',
            'post_date_gmt'     => '2030-01-01 03:00:00',
            'post_modified'     => '2030-01-01 03:00:00',
            'post_modified_gmt' => '2030-01-01 03:00:00',
        ),
        array(
            'kb_release_at'   => '2030-01-15T12:00:00+09:00',
            'kb_type'         => 'album',
            'kb_source'       => $fixture_marker,
            'kb_source_url'   => $primary_source,
            'kb_source_urls'  => array( $primary_source ),
            'kb_source_title' => 'Primary schedule source',
        )
    );
    $article_slug = 'kpopblog-seo-malicious-' . substr( md5( $fixture_marker ), 0, 12 );
    $article_id = $create_fixture(
        'post',
        $malicious_title,
        $article_slug,
        array(
            'post_content'      => '<p>Malicious stored content fixture.</p>',
            'post_excerpt'      => $malicious_description,
            'post_date'         => '2099-02-03 04:05:06',
            'post_date_gmt'     => '2099-02-03 12:34:56',
            'post_modified'     => '2099-03-04 01:02:03',
            'post_modified_gmt' => '2099-03-04 05:06:07',
        ),
        array(
            'kb_source'       => $fixture_marker . ':article',
            'kb_source_url'   => $primary_source,
            'kb_source_urls'  => array( $primary_source, 'http://example.net/not-https', $secondary_source, $primary_source ),
            'kb_source_title' => 'Review primary source',
        )
    );
    $secondary_id = $create_fixture(
        'kb_comeback',
        'KpopBlog SEO runtime secondary schedule',
        '',
        array(
            'post_content'      => 'Secondary valid date-only schedule.',
            'post_date'         => '2030-02-01 03:00:00',
            'post_date_gmt'     => '2030-02-01 03:00:00',
            'post_modified'     => '2030-02-01 03:00:00',
            'post_modified_gmt' => '2030-02-01 03:00:00',
        ),
        array(
            'kb_release_at'   => '2030-02-20',
            'kb_type'         => 'single',
            'kb_source'       => $fixture_marker . ':secondary',
            'kb_source_url'   => $secondary_source,
            'kb_source_urls'  => array( $secondary_source ),
            'kb_source_title' => 'Secondary schedule source',
        )
    );
    $malformed_id = $create_fixture(
        'kb_comeback',
        'KpopBlog SEO runtime malformed schedule',
        '',
        array(
            'post_content'      => 'Malformed relative date schedule.',
            'post_date'         => '2030-03-01 03:00:00',
            'post_date_gmt'     => '2030-03-01 03:00:00',
            'post_modified'     => '2030-03-01 03:00:00',
            'post_modified_gmt' => '2030-03-01 03:00:00',
        ),
        array(
            'kb_release_at' => 'next Friday at noon',
            'kb_type'       => 'album',
            'kb_source'     => $fixture_marker . ':malformed',
        )
    );

    $job_count_after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_table}" );
    $temp_job_count_after = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$jobs_table} WHERE title = %s", $fixture_title ) );
    $next_scheduled_after = (int) ( wp_next_scheduled( 'kpopblog_process_notification_jobs' ) ?: 0 );
    if ( $job_count_before !== $job_count_after || $temp_job_count_before !== $temp_job_count_after ) {
        throw new Exception( 'temporary SEO schedule queued a notification job' );
    }
    if ( $next_scheduled_before !== $next_scheduled_after ) {
        throw new Exception( 'temporary SEO schedule changed notification cron state' );
    }
    if ( 0 !== $outbound_requests ) {
        throw new Exception( 'temporary SEO schedule attempted an outbound webhook' );
    }
    echo wp_json_encode( array(
        'id'                => $post_id,
        'article_id'        => $article_id,
        'article_slug'      => $article_slug,
        'article_title'     => $malicious_title,
        'secondary_id'      => $secondary_id,
        'malformed_id'      => $malformed_id,
        'published_utc'     => '2099-02-03T12:34:56+00:00',
        'modified_utc'      => '2099-03-04T05:06:07+00:00',
        'primary_source'    => $primary_source,
        'secondary_source'  => $secondary_source,
        'job_count'         => $job_count_after,
        'temp_job_count'    => $temp_job_count_after,
        'next_scheduled'    => $next_scheduled_after,
        'outbound_requests' => $outbound_requests,
    ) );
} catch ( Throwable $error ) {
    foreach ( $created_ids as $created_id ) {
        wp_delete_post( $created_id, true );
    }
    throw $error;
} finally {
    remove_filter( 'pre_http_request', $http_guard, PHP_INT_MAX );
}
'@
    $fixture = $fixtureTemplate.Replace('__FIXTURE_MARKER__', $fixtureMarker)
    $fixtureOutput = Invoke-WpCli 'eval' $fixture
    if ($InjectPostCreationParseFailure) {
        $fixtureOutputText = [string](@($fixtureOutput) | Select-Object -Last 1)
        if ($fixtureOutputText -notmatch '"outbound_requests":0') {
            throw 'Fault injection fixture did not prove zero outbound requests before parsing.'
        }
        Write-Host 'Fault injection confirmed outbound_requests=0 before PowerShell parse failure.'
        $fixtureOutput = @('{"fault_injection":')
    }
    $fixtureResult = Convert-WpCliJson $fixtureOutput
    $temporaryScheduleId = [int]$fixtureResult.id
    $temporaryArticleId = [int]$fixtureResult.article_id
    $secondaryScheduleId = [int]$fixtureResult.secondary_id
    $malformedScheduleId = [int]$fixtureResult.malformed_id
    if ($temporaryScheduleId -le 0 -or $temporaryArticleId -le 0 -or $secondaryScheduleId -le 0 -or $malformedScheduleId -le 0) {
        throw 'SEO smoke test could not determine all temporary fixture IDs.'
    }
    if ([int]$fixtureResult.outbound_requests -ne 0) {
        throw 'SEO smoke test fixture attempted an outbound webhook.'
    }

    $bundle = Invoke-RestMethod -Uri "$baseUrl/wp-json/kpopblog/v1/bundle" -TimeoutSec 30
    $schedule = @($bundle.comebacks) | Where-Object { [string]$_.id -eq [string]$temporaryScheduleId } | Select-Object -First 1
    if (-not $schedule) { throw 'SEO smoke test temporary schedule is unavailable from WordPress.' }

    $articleUrl = "$baseUrl/news/$($fixtureResult.article_slug)"
    $crawlerBodies = @{}
    $jsonLdPattern = '(?is)<script\b(?=[^>]*\bid\s*=\s*(["''])(?:kpopblog-discovery-jsonld)\1)(?=[^>]*\btype\s*=\s*(["''])application/ld\+json\2)[^>]*>(.*?)</script>'
    $browserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0 Safari/537.36'
    foreach ($agent in @('OAI-SearchBot', 'GPTBot', 'Claude-SearchBot', 'PerplexityBot', 'Googlebot', $browserAgent)) {
        $response = Invoke-WebRequest -UseBasicParsing -Uri $articleUrl -Headers @{ 'User-Agent' = $agent } -TimeoutSec 30
        if ($response.StatusCode -ne 200) { throw "Article request failed for $agent." }
        $countPatterns = @{
            title = '(?is)<title\b[^>]*>.*?</title>'
            description = '(?i)<meta\b[^>]*\bname\s*=\s*["'']description["''][^>]*>'
            canonical = '(?i)<link\b[^>]*\brel\s*=\s*["'']canonical["''][^>]*>'
            json_ld = $jsonLdPattern
        }
        foreach ($entry in $countPatterns.GetEnumerator()) {
            $count = [regex]::Matches($response.Content, $entry.Value).Count
            if ($count -ne 1) { throw "Article HTML has $count $($entry.Key) elements for $agent; expected exactly one." }
        }
        if ($response.Content -notmatch '<article[^>]+data-kpopblog-fallback="article"') { throw "Article HTML is missing semantic fallback for $agent." }
        if ($response.Content -match '(?i)<script\b[^>]*\bid\s*=\s*["'']review-injected') { throw "Stored article data injected a script element for $agent." }
        $jsonLdMatch = [regex]::Match($response.Content, $jsonLdPattern)
        try { $jsonLd = $jsonLdMatch.Groups[3].Value | ConvertFrom-Json -DateKind String -ErrorAction Stop } catch { throw "Article JSON-LD is not valid JSON for $agent." }
        if ([string]$jsonLd.'@type' -ne 'NewsArticle' -or [string]$jsonLd.headline -ne [string]$fixtureResult.article_title) {
            throw "Article JSON-LD identity is incorrect for $agent."
        }
        if ([string]$jsonLd.datePublished -ne [string]$fixtureResult.published_utc -or [string]$jsonLd.dateModified -ne [string]$fixtureResult.modified_utc) {
            throw "Article JSON-LD timestamps are not the exact stored UTC instants for $agent."
        }
        $citations = @($jsonLd.citation)
        if ($citations.Count -ne 2 -or $citations[0] -ne [string]$fixtureResult.primary_source -or $citations[1] -ne [string]$fixtureResult.secondary_source) {
            throw "Article JSON-LD citations are not the validated, ordered source list for $agent."
        }
        foreach ($timestamp in @([string]$fixtureResult.published_utc, [string]$fixtureResult.modified_utc)) {
            if ($response.Content -notmatch [regex]::Escape('datetime="' + $timestamp + '"')) { throw "Article fallback is missing UTC time $timestamp for $agent." }
        }
        $crawlerBodies[$agent] = $response.Content
    }
    $referenceBody = $crawlerBodies['OAI-SearchBot']
    foreach ($body in $crawlerBodies.Values) {
        if ($body -ne $referenceBody) { throw 'Crawler user agents received different article HTML.' }
    }

    $filterScript = @'
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['HTTP_HOST'] = 'localhost:8088';
$_SERVER['REQUEST_URI'] = '/';
ob_start();
require KPOPBLOG_PATH . 'templates/app-shell.php';
ob_end_clean();
$input = '<script data-note="kpopblog-discovery-jsonld">foreignAttribute()</script>'
    . '<script>window.note = "kpopblog-discovery-jsonld";</script>'
    . '<script id="kpopblog-discovery-jsonld" type="application/ld+json">{"safe":true}</script>';
$filtered = kpopblog_strip_foreign_markup( $input );
echo wp_json_encode( array(
    'foreign_attribute_removed' => false === strpos( $filtered, 'foreignAttribute' ),
    'foreign_body_removed'      => false === strpos( $filtered, 'window.note' ),
    'exact_marker_count'        => substr_count( $filtered, 'id="kpopblog-discovery-jsonld"' ),
) );
'@
    $filterResult = Convert-WpCliJson (Invoke-WpCli 'eval' $filterScript)
    if (-not [bool]$filterResult.foreign_attribute_removed -or -not [bool]$filterResult.foreign_body_removed -or [int]$filterResult.exact_marker_count -ne 1) {
        throw 'App shell script filtering did not require the exact JSON-LD id attribute.'
    }

$encodingFailureScript = @'
$recursive = array();
$recursive['self'] =& $recursive;
$output = function_exists( 'kpopblog_render_public_json_ld' ) ? kpopblog_render_public_json_ld( $recursive ) : '__missing__';
echo wp_json_encode( array( 'output' => $output ) );
'@
    $encodingFailureResult = Convert-WpCliJson (Invoke-WpCli 'eval' $encodingFailureScript)
    if ([string]$encodingFailureResult.output -ne '') {
        throw 'Failed JSON encoding emitted a malformed JSON-LD script.'
    }

    $comebacks = Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/comebacks" -Headers @{ 'User-Agent' = 'Claude-SearchBot' } -TimeoutSec 30
    if ($comebacks.Content -notmatch '<section[^>]+data-kpopblog-fallback="comebacks"') {
        throw 'Comeback calendar is missing semantic fallback content or Event JSON-LD.'
    }
    $comebackJsonMatches = [regex]::Matches($comebacks.Content, $jsonLdPattern)
    if ($comebackJsonMatches.Count -ne 1) { throw 'Comeback calendar must have exactly one JSON-LD script.' }
    try { $comebackJson = $comebackJsonMatches[0].Groups[3].Value | ConvertFrom-Json -DateKind String -ErrorAction Stop } catch { throw 'Comeback JSON-LD is not valid JSON.' }
    $eventItems = @($comebackJson.mainEntity.itemListElement)
    $eventUrls = @($eventItems | ForEach-Object { [string]$_.item.url })
    foreach ($scheduleId in @($temporaryScheduleId, $secondaryScheduleId)) {
        if ($eventUrls -notcontains "$baseUrl/comebacks#event-$scheduleId") { throw "Comeback JSON-LD is missing valid schedule $scheduleId." }
    }
    if ($eventUrls -contains "$baseUrl/comebacks#event-$malformedScheduleId" -or $comebacks.Content -match [regex]::Escape('id="event-' + $malformedScheduleId + '"')) {
        throw 'Malformed relative-date schedule was emitted as semantic comeback content.'
    }
    $sourceHeadingMatches = [regex]::Matches($comebacks.Content, 'id="(kpopblog-source-heading-\d+)"')
    $sourceHeadingIds = @($sourceHeadingMatches | ForEach-Object { $_.Groups[1].Value })
    if ($sourceHeadingIds.Count -lt 2 -or @($sourceHeadingIds | Select-Object -Unique).Count -ne $sourceHeadingIds.Count) {
        throw 'Comeback source heading IDs are missing or duplicated.'
    }
    foreach ($scheduleId in @($temporaryScheduleId, $secondaryScheduleId)) {
        $headingId = "kpopblog-source-heading-$scheduleId"
        if ($sourceHeadingIds -notcontains $headingId -or $comebacks.Content -notmatch [regex]::Escape('aria-labelledby="' + $headingId + '"')) {
            throw "Comeback source heading $headingId does not match its aria-labelledby reference."
        }
    }

    $missingSlug = 'automation-discovery-missing-' + [guid]::NewGuid().ToString('N')
    $missing = Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/news/$missingSlug" -SkipHttpErrorCheck -TimeoutSec 30
    if ($missing.StatusCode -ne 404 -or $missing.Content -notmatch 'noindex, nofollow') {
        throw 'Missing article did not return a non-indexable 404.'
    }
    if ([regex]::Matches($missing.Content, '(?i)<link\b[^>]*\brel\s*=\s*["'']canonical["''][^>]*>').Count -ne 0 -or [regex]::Matches($missing.Content, $jsonLdPattern).Count -ne 0) {
        throw 'Missing article emitted a canonical link or JSON-LD.'
    }

    $articlePath = '/news/' + [string]$article.slug
    $articlePattern = [regex]::Escape($articlePath)
    $fixtureArticlePath = '/news/' + [string]$fixtureResult.article_slug
    $fixtureArticlePattern = [regex]::Escape($fixtureArticlePath)
    $scheduleAnchorPattern = [regex]::Escape('/comebacks#event-' + [string]$temporaryScheduleId)
    $secondaryScheduleAnchorPattern = [regex]::Escape('/comebacks#event-' + [string]$secondaryScheduleId)
    $malformedScheduleAnchorPattern = [regex]::Escape('/comebacks#event-' + [string]$malformedScheduleId)

    $rss = Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/rss.xml" -TimeoutSec 30
    if ($rss.StatusCode -ne 200 -or $rss.Headers['Content-Type'] -notmatch 'application/rss\+xml') {
        throw 'WordPress RSS endpoint did not return RSS XML.'
    }
    try { [xml]$rssXml = $rss.Content } catch { throw 'WordPress RSS endpoint returned malformed XML.' }
    if ($rss.Content -notmatch $articlePattern -or $rss.Content -notmatch $fixtureArticlePattern -or $rss.Content -notmatch $scheduleAnchorPattern -or $rss.Content -notmatch $secondaryScheduleAnchorPattern) {
        throw 'WordPress RSS endpoint is missing published article or schedule content.'
    }
    if ($rss.Content -match $malformedScheduleAnchorPattern -or $rss.Content -match [regex]::Escape('KpopBlog SEO runtime malformed schedule')) {
        throw 'WordPress RSS emitted a schedule with a malformed release date.'
    }
    $rssItems = @($rssXml.rss.channel.item)
    $rssFixtureArticle = @($rssItems | Where-Object { [string]$_.link -eq "$baseUrl$fixtureArticlePath" }) | Select-Object -First 1
    $rssPrimarySchedule = @($rssItems | Where-Object { [string]$_.link -eq "$baseUrl/comebacks#event-$temporaryScheduleId" }) | Select-Object -First 1
    $rssSecondarySchedule = @($rssItems | Where-Object { [string]$_.link -eq "$baseUrl/comebacks#event-$secondaryScheduleId" }) | Select-Object -First 1
    if (-not $rssFixtureArticle -or [string]$rssFixtureArticle.description -notlike "*$($fixtureResult.primary_source)*" -or [string]$rssFixtureArticle.description -notlike "*$($fixtureResult.secondary_source)*") {
        throw 'WordPress RSS article description is missing validated citations.'
    }
    if (-not $rssPrimarySchedule -or [string]$rssPrimarySchedule.description -notlike "*$($fixtureResult.primary_source)*" -or -not $rssSecondarySchedule -or [string]$rssSecondarySchedule.description -notlike "*$($fixtureResult.secondary_source)*") {
        throw 'WordPress RSS schedule descriptions are missing validated citations.'
    }
    if ($rss.Content -match [regex]::Escape('http://example.net/not-https')) {
        throw 'WordPress RSS exposed an unvalidated source URL.'
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
    $agents = @('OAI-SearchBot', 'GPTBot', 'ClaudeBot', 'Claude-SearchBot', 'Claude-User', 'PerplexityBot', 'Perplexity-User', 'Google-Extended')
    foreach ($token in $agents) {
        if ($robots.Content -notmatch [regex]::Escape("User-agent: $token")) {
            throw "WordPress robots endpoint does not explicitly allow $token."
        }
    }
    $privatePaths = @(
        '/wp-admin/',
        '/wp-login.php',
        '/admin',
        '/moderation',
        '/onboarding',
        '/wp-json/kpopblog/v1/admin',
        '/wp-json/kpopblog/v1/auth',
        '/wp-json/kpopblog/v1/subscriptions',
        '/wp-json/kpopblog/v1/notifications',
        '/wp-json/kpopblog/v1/events',
        '/wp-json/kpopblog/v1/moderation'
    )
    foreach ($privatePath in $privatePaths) {
        $rulePattern = '(?m)^' + [regex]::Escape("Disallow: $privatePath") + '\r?$'
        $ruleCount = [regex]::Matches($robots.Content, $rulePattern).Count
        if ($ruleCount -ne ($agents.Count + 1)) {
            throw "WordPress robots endpoint does not apply $privatePath to every crawler group."
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
    if ($llms.Content -notmatch $articlePattern -or $llms.Content -notmatch $fixtureArticlePattern -or $llms.Content -notmatch $scheduleAnchorPattern -or $llms.Content -notmatch $secondaryScheduleAnchorPattern) {
        throw 'WordPress llms endpoint is missing published article or schedule links.'
    }
    foreach ($validatedSource in @([string]$fixtureResult.primary_source, [string]$fixtureResult.secondary_source)) {
        if ($llms.Content -notmatch [regex]::Escape($validatedSource)) { throw "WordPress llms output is missing validated citation $validatedSource." }
    }
    if ($llms.Content -match $malformedScheduleAnchorPattern -or $llms.Content -match [regex]::Escape('http://example.net/not-https')) {
        throw 'WordPress llms output exposed a malformed schedule or unvalidated source URL.'
    }
} finally {
    Remove-TemporaryFixtures -Marker $fixtureMarker
    $notificationStateAfter = Get-NotificationState -Marker $fixtureMarker
    Assert-NotificationStateUnchanged -Before $notificationStateBefore -After $notificationStateAfter
}

Write-Host 'WordPress SEO runtime smoke test passed.'
