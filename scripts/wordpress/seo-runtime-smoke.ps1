[CmdletBinding()]
param()

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
        return $json | ConvertFrom-Json -ErrorAction Stop
    } catch {
        throw "WP-CLI did not return valid JSON: $json"
    }
}

function Get-NotificationState {
    $stateScript = @'
global $wpdb;
$jobs_table = $wpdb->prefix . 'kb_notification_jobs';
$fixture_title = 'Comeback: KpopBlog SEO runtime temporary schedule';
echo wp_json_encode( array(
    'job_count'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_table}" ),
    'temp_job_count' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$jobs_table} WHERE title = %s", $fixture_title ) ),
    'next_scheduled' => (int) ( wp_next_scheduled( 'kpopblog_process_notification_jobs' ) ?: 0 ),
) );
'@
    return Convert-WpCliJson (Invoke-WpCli 'eval' $stateScript)
}

function Assert-NotificationStateUnchanged {
    param(
        [Parameter(Mandatory = $true)][object]$Before,
        [Parameter(Mandatory = $true)][object]$After
    )
    foreach ($property in @('job_count', 'temp_job_count', 'next_scheduled')) {
        if ([string]$Before.$property -ne [string]$After.$property) {
            throw "SEO smoke fixture changed notification state $property from $($Before.$property) to $($After.$property)."
        }
    }
}

$notificationStateBefore = Get-NotificationState
if ([int]$notificationStateBefore.temp_job_count -ne 0) {
    throw 'SEO smoke test found a pre-existing temporary schedule notification job.'
}

try {
    $bundle = Invoke-RestMethod -Uri "$baseUrl/wp-json/kpopblog/v1/bundle" -TimeoutSec 30
    $article = @($bundle.articles) | Select-Object -First 1
    if (-not $article) { throw 'SEO smoke test requires one published WordPress article.' }

    $fixture = @'
global $wpdb;
$jobs_table = $wpdb->prefix . 'kb_notification_jobs';
$fixture_title = 'Comeback: KpopBlog SEO runtime temporary schedule';
$job_count_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_table}" );
$temp_job_count_before = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$jobs_table} WHERE title = %s", $fixture_title ) );
$next_scheduled_before = (int) ( wp_next_scheduled( 'kpopblog_process_notification_jobs' ) ?: 0 );
$outbound_requests = 0;
$post_id = 0;
$http_guard = function () use ( &$outbound_requests ) {
    $outbound_requests++;
    return new WP_Error( 'seo_smoke_blocked_http', 'SEO smoke fixture attempted an outbound HTTP request.' );
};
add_filter( 'pre_http_request', $http_guard, PHP_INT_MAX );
try {
    $post_id = wp_insert_post( array(
        'post_type'         => 'kb_comeback',
        'post_title'        => 'KpopBlog SEO runtime temporary schedule',
        'post_content'      => 'Temporary schedule created and removed by seo-runtime-smoke.ps1.',
        'post_status'       => 'draft',
        'post_date'         => '2030-01-01 03:00:00',
        'post_date_gmt'     => '2030-01-01 03:00:00',
        'post_modified'     => '2030-01-01 03:00:00',
        'post_modified_gmt' => '2030-01-01 03:00:00',
    ), true );
    if ( is_wp_error( $post_id ) ) {
        throw new Exception( 'failed to create temporary SEO schedule: ' . $post_id->get_error_message() );
    }
    update_post_meta( $post_id, 'kb_release_at', '2030-01-15T12:00:00+09:00' );
    update_post_meta( $post_id, 'kb_type', 'album' );
    update_post_meta( $post_id, 'kb_source', 'seo-runtime-smoke' );
    $updated = $wpdb->update(
        $wpdb->posts,
        array( 'post_status' => 'publish' ),
        array( 'ID' => $post_id ),
        array( '%s' ),
        array( '%d' )
    );
    if ( 1 !== $updated ) {
        throw new Exception( 'failed to publish temporary SEO schedule without hooks' );
    }
    clean_post_cache( $post_id );
    $published = get_post( $post_id );
    if ( ! $published || 'publish' !== $published->post_status ) {
        throw new Exception( 'temporary SEO schedule is not query-visible as published' );
    }

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
        'id'                => (int) $post_id,
        'job_count'         => $job_count_after,
        'temp_job_count'    => $temp_job_count_after,
        'next_scheduled'    => $next_scheduled_after,
        'outbound_requests' => $outbound_requests,
    ) );
} catch ( Throwable $error ) {
    if ( $post_id ) {
        wp_delete_post( $post_id, true );
    }
    throw $error;
} finally {
    remove_filter( 'pre_http_request', $http_guard, PHP_INT_MAX );
}
'@
    $fixtureResult = Convert-WpCliJson (Invoke-WpCli 'eval' $fixture)
    $temporaryScheduleId = [int]$fixtureResult.id
    if ($temporaryScheduleId -le 0) {
        throw 'SEO smoke test could not determine the temporary schedule ID.'
    }
    if ([int]$fixtureResult.outbound_requests -ne 0) {
        throw 'SEO smoke test fixture attempted an outbound webhook.'
    }

    $bundle = Invoke-RestMethod -Uri "$baseUrl/wp-json/kpopblog/v1/bundle" -TimeoutSec 30
    $schedule = @($bundle.comebacks) | Where-Object { [string]$_.id -eq [string]$temporaryScheduleId } | Select-Object -First 1
    if (-not $schedule) { throw 'SEO smoke test temporary schedule is unavailable from WordPress.' }

    $articlePath = '/news/' + [string]$article.slug
    $articlePattern = [regex]::Escape($articlePath)
    $scheduleAnchorPattern = [regex]::Escape('/comebacks#event-' + [string]$temporaryScheduleId)

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
    if ($llms.Content -notmatch $articlePattern -or $llms.Content -notmatch $scheduleAnchorPattern) {
        throw 'WordPress llms endpoint is missing published article or schedule links.'
    }
} finally {
    if ($temporaryScheduleId -gt 0) {
        Invoke-WpCli 'post' 'delete' ([string]$temporaryScheduleId) '--force' | Out-Null
    }
    $notificationStateAfter = Get-NotificationState
    Assert-NotificationStateUnchanged -Before $notificationStateBefore -After $notificationStateAfter
}

Write-Host 'WordPress SEO runtime smoke test passed.'
