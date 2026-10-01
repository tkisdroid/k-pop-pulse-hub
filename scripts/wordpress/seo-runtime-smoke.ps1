[CmdletBinding()]
param(
    [switch]$InjectPostCreationParseFailure,
    [string]$ComposeProjectName = 'k-pop-pulse-hub'
)

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
. (Join-Path $PSScriptRoot 'compose-context.ps1')
$composeContext = Resolve-KpopBlogComposeContext -RepositoryRoot $repoRoot -ProjectName $ComposeProjectName
$compose = $composeContext.ComposePath
$envFile = $composeContext.EnvironmentPath
$projectName = $composeContext.ProjectName
Assert-KpopBlogComposeMount -Context $composeContext
$baseUrl = 'http://localhost:8088'
$temporaryScheduleId = 0
$temporaryArticleId = 0
$secondaryScheduleId = 0
$malformedScheduleId = 0
$revisionPostId = 0
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
if ( count( $ids ) > 57 ) {
    throw new Exception( 'SEO smoke marker matched too many posts; refusing cleanup' );
}
$expected = array(
    $fixture_marker                => array( 'kb_comeback', 'KpopBlog SEO runtime temporary schedule' ),
    $fixture_marker . ':article'   => array( 'post', 'KpopBlog SEO [review](unsafe) </script><script id="review-injected">injected</script> article' ),
    $fixture_marker . ':secondary' => array( 'kb_comeback', 'KpopBlog SEO runtime secondary schedule' ),
    $fixture_marker . ':malformed' => array( 'kb_comeback', 'KpopBlog SEO runtime malformed schedule' ),
    $fixture_marker . ':revision'  => array( 'kb_artist', 'KpopBlog SEO same-second revision fixture' ),
    $fixture_marker . ':image'     => array( 'attachment', 'KpopBlog SEO featured image' ),
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
        $bulk_identity = $post && 0 === strpos( $marker, $fixture_marker . ':bulk:' )
            && 'kb_comeback' === $post->post_type
            && 1 === preg_match( '/^KpopBlog SEO bulk schedule \d{2}$/', $post->post_title );
        if ( ! $post || ( ! $bulk_identity && ( ! isset( $expected[ $marker ] ) || $expected[ $marker ][0] !== $post->post_type || $expected[ $marker ][1] !== $post->post_title ) ) ) {
            throw new Exception( 'SEO smoke marker post identity mismatch; refusing cleanup' );
        }
        $deleted = 'attachment' === $post->post_type ? wp_delete_attachment( $post_id, true ) : wp_delete_post( $post_id, true );
        if ( ! $deleted || get_post( $post_id ) ) {
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

function Get-DiscoveryRevisionState {
    $script = @'
global $wpdb;
$exists = 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", 'kpopblog_discovery_revision' ) );
echo wp_json_encode( array( 'exists' => $exists, 'value' => $exists ? get_option( 'kpopblog_discovery_revision' ) : null ) );
'@
    return Convert-WpCliJson (Invoke-WpCli 'eval' $script)
}

function Restore-DiscoveryRevisionState {
    param([Parameter(Mandatory = $true)][object]$State)
    $exists = if ([bool]$State.exists) { 'true' } else { 'false' }
    $value = if ([bool]$State.exists) { [string][long]$State.value } else { '0' }
    $script = @'
$existed = __EXISTED__;
$value = __VALUE__;
if ( $existed ) { update_option( 'kpopblog_discovery_revision', $value, false ); } else { delete_option( 'kpopblog_discovery_revision' ); }
'@.Replace('__EXISTED__', $exists).Replace('__VALUE__', $value)
    Invoke-WpCli 'eval' $script | Out-Null
}

$notificationStateBefore = Get-NotificationState -Marker $fixtureMarker
$discoveryRevisionBefore = Get-DiscoveryRevisionState
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
$malicious_title = 'KpopBlog SEO [review](unsafe) </script><script id="review-injected">injected</script> article';
$malicious_description = str_repeat( 'Oversized manual excerpt [review](unsafe) </script><script id="review-injected-description">injected</script> text. ', 30 );
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
            'post_date'         => '2050-01-01 03:00:00',
            'post_date_gmt'     => '2050-01-01 03:00:00',
            'post_modified'     => '2050-01-01 03:00:00',
            'post_modified_gmt' => '2050-01-01 03:00:00',
        ),
        array(
            'kb_release_at'   => '2030-01-15T12:00:00+09:00',
            'kb_type'         => 'album',
            'kb_source'       => $fixture_marker,
            'kb_source_url'   => $primary_source,
            'kb_source_urls'  => array( $primary_source ),
            'kb_source_title' => 'Primary schedule source',
            'kb_artist_slug'  => 'blackpink',
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
            'post_date'         => '2030-02-03 21:34:56',
            'post_date_gmt'     => '2030-02-03 12:34:56',
            'post_modified'     => '2030-03-04 14:06:07',
            'post_modified_gmt' => '2030-03-04 05:06:07',
        ),
        array(
            'kb_source'       => $fixture_marker . ':article',
            'kb_source_url'   => $primary_source,
            'kb_source_urls'  => array( $primary_source, 'http://example.net/not-https', $secondary_source, $primary_source ),
            'kb_source_title' => 'Review primary source',
        )
    );
    $image_id = wp_insert_attachment( array(
        'post_title'     => 'KpopBlog SEO featured image',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image/jpeg',
        'guid'           => home_url( '/wp-content/uploads/2030/02/kpopblog-seo-featured.jpg' ),
    ), '', $article_id, true );
    if ( is_wp_error( $image_id ) ) { throw new Exception( 'failed to create featured image fixture' ); }
    $created_ids[] = (int) $image_id;
    update_post_meta( $image_id, '_wp_attached_file', '2030/02/kpopblog-seo-featured.jpg' );
    update_post_meta( $image_id, 'kb_source', $fixture_marker . ':image' );
    set_post_thumbnail( $article_id, $image_id );
    $featured_image = kpopblog_thumb_url( $article_id );
    if ( '' === $featured_image ) { throw new Exception( 'featured image fixture URL is unavailable' ); }
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
    $bulk_sentinel_id = 0;
    for ( $index = 0; $index < 51; $index++ ) {
        $release_at = 0 === $index ? '2045-12-31T23:59:00Z' : gmdate( 'Y-m-d', strtotime( '2020-01-01 +' . $index . ' days' ) );
        $post_date = 0 === $index ? '2000-01-01 00:00:00' : gmdate( 'Y-m-d H:i:s', strtotime( '2040-01-01 +' . $index . ' days' ) );
        $bulk_id = $create_fixture(
            'kb_comeback',
            sprintf( 'KpopBlog SEO bulk schedule %02d', $index ),
            '',
            array(
                'post_content'      => 'Bulk RSS candidate ordering fixture.',
                'post_date'         => $post_date,
                'post_date_gmt'     => $post_date,
                'post_modified'     => '2020-01-01 00:00:00',
                'post_modified_gmt' => '2020-01-01 00:00:00',
            ),
            array(
                'kb_release_at' => $release_at,
                'kb_type'       => 'event',
                'kb_source'     => $fixture_marker . ':bulk:' . $index,
            )
        );
        if ( 0 === $index ) { $bulk_sentinel_id = $bulk_id; }
    }
    $revision_slug = 'kpopblog-seo-revision-' . substr( md5( $fixture_marker ), 0, 12 );
    $revision_id = wp_insert_post( array(
        'post_type'    => 'kb_artist',
        'post_title'   => 'KpopBlog SEO same-second revision fixture',
        'post_content' => 'Temporary same-second cache revalidation fixture.',
        'post_status'  => 'draft',
        'post_name'    => $revision_slug,
    ), true );
    if ( is_wp_error( $revision_id ) ) { throw new Exception( 'failed to create same-second revision fixture' ); }
    $created_ids[] = (int) $revision_id;
    update_post_meta( $revision_id, 'kb_source', $fixture_marker . ':revision' );

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
        'bulk_sentinel_id'  => $bulk_sentinel_id,
        'revision_id'       => $revision_id,
        'revision_slug'     => $revision_slug,
        'published_utc'     => '2030-02-03T12:34:56+00:00',
        'modified_utc'      => '2030-03-04T05:06:07+00:00',
        'featured_image'    => $featured_image,
        'primary_source'    => $primary_source,
        'secondary_source'  => $secondary_source,
        'job_count'         => $job_count_after,
        'temp_job_count'    => $temp_job_count_after,
        'next_scheduled'    => $next_scheduled_after,
        'outbound_requests' => $outbound_requests,
    ) );
} catch ( Throwable $error ) {
    foreach ( $created_ids as $created_id ) {
        'attachment' === get_post_type( $created_id ) ? wp_delete_attachment( $created_id, true ) : wp_delete_post( $created_id, true );
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
    $bulkSentinelId = [int]$fixtureResult.bulk_sentinel_id
    $revisionPostId = [int]$fixtureResult.revision_id
    if ($temporaryScheduleId -le 0 -or $temporaryArticleId -le 0 -or $secondaryScheduleId -le 0 -or $malformedScheduleId -le 0 -or $bulkSentinelId -le 0 -or $revisionPostId -le 0) {
        throw 'SEO smoke test could not determine all temporary fixture IDs.'
    }
    if ([int]$fixtureResult.outbound_requests -ne 0) {
        throw 'SEO smoke test fixture attempted an outbound webhook.'
    }

    $metaRevisionScript = @'
$article_id = __ARTICLE_ID__;
$schedule_id = __SCHEDULE_ID__;
$revision_before = (int) get_option( 'kpopblog_discovery_revision', 0 );
update_post_meta( $article_id, 'kb_view_count', 17 );
update_post_meta( $article_id, 'kb_reaction_count', 9 );
$revision_after_counters = (int) get_option( 'kpopblog_discovery_revision', 0 );
$source_before = get_post_meta( $article_id, 'kb_source_url', true );
update_post_meta( $article_id, 'kb_source_url', 'https://example.com/kpopblog-revision-check' );
$revision_after_source = (int) get_option( 'kpopblog_discovery_revision', 0 );
$release_before = get_post_meta( $schedule_id, 'kb_release_at', true );
update_post_meta( $schedule_id, 'kb_release_at', '2030-01-16T12:00:00+09:00' );
$revision_after_release = (int) get_option( 'kpopblog_discovery_revision', 0 );
update_post_meta( $article_id, 'kb_source_url', $source_before );
update_post_meta( $schedule_id, 'kb_release_at', $release_before );
echo wp_json_encode( array(
    'before'         => $revision_before,
    'after_counters' => $revision_after_counters,
    'after_source'   => $revision_after_source,
    'after_release'  => $revision_after_release,
) );
'@.Replace('__ARTICLE_ID__', [string]$temporaryArticleId).Replace('__SCHEDULE_ID__', [string]$temporaryScheduleId)
    $metaRevision = Convert-WpCliJson (Invoke-WpCli 'eval' $metaRevisionScript)
    if ([long]$metaRevision.after_counters -ne [long]$metaRevision.before) {
        throw 'View or reaction metadata incorrectly advanced the discovery revision.'
    }
    if ([long]$metaRevision.after_source -le [long]$metaRevision.after_counters -or [long]$metaRevision.after_release -le [long]$metaRevision.after_source) {
        throw 'Rendered source or release metadata did not advance the discovery revision.'
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
        if ($jsonLd.'@graph') { $jsonLd = @($jsonLd.'@graph' | Where-Object { $_.'@type' -eq 'NewsArticle' })[0] }
        if ([string]$jsonLd.'@type' -ne 'NewsArticle' -or [string]$jsonLd.headline -ne [string]$fixtureResult.article_title) {
            throw "Article JSON-LD identity is incorrect for $agent."
        }
        if ([string]$jsonLd.datePublished -ne [string]$fixtureResult.published_utc -or [string]$jsonLd.dateModified -ne [string]$fixtureResult.modified_utc) {
            throw "Article JSON-LD timestamps are not the exact stored UTC instants for $agent."
        }
        if (@($jsonLd.image).Count -ne 1 -or [string]@($jsonLd.image)[0] -ne [string]$fixtureResult.featured_image) {
            throw "Article JSON-LD featured image is incorrect for $agent."
        }
        $expectedMeta = @{
            'article:published_time' = [string]$fixtureResult.published_utc
            'article:modified_time' = [string]$fixtureResult.modified_utc
            'og:image' = [string]$fixtureResult.featured_image
        }
        foreach ($meta in $expectedMeta.GetEnumerator()) {
            $metaPattern = '(?i)<meta\b(?=[^>]*\bproperty\s*=\s*["'']' + [regex]::Escape($meta.Key) + '["''])(?=[^>]*\bcontent\s*=\s*["'']' + [regex]::Escape($meta.Value) + '["''])[^>]*>'
            if ([regex]::Matches($response.Content, $metaPattern).Count -ne 1) { throw "Article HTML does not have exactly one correct $($meta.Key) tag for $agent." }
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
        throw 'Comeback calendar is missing semantic fallback content or release JSON-LD.'
    }
    $comebackJsonMatches = [regex]::Matches($comebacks.Content, $jsonLdPattern)
    if ($comebackJsonMatches.Count -ne 1) { throw 'Comeback calendar must have exactly one JSON-LD script.' }
    try { $comebackJson = $comebackJsonMatches[0].Groups[3].Value | ConvertFrom-Json -DateKind String -ErrorAction Stop } catch { throw 'Comeback JSON-LD is not valid JSON.' }
    if ($comebackJson.'@graph') { $comebackJson = @($comebackJson.'@graph' | Where-Object { $_.'@type' -eq 'CollectionPage' })[0] }
    $eventItems = @($comebackJson.mainEntity.itemListElement)
    $eventUrls = @($eventItems | ForEach-Object { [string]$_.item.url })
    foreach ($scheduleId in @($temporaryScheduleId, $secondaryScheduleId)) {
        if ($eventUrls -notcontains "$baseUrl/comebacks#event-$scheduleId") { throw "Comeback JSON-LD is missing valid schedule $scheduleId." }
    }
    if ($comebacks.Content -notmatch [regex]::Escape('href="http://localhost:8088/artist/blackpink"')) {
        throw 'Comeback fallback is missing the safe artist link.'
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
    if ($rssItems.Count -gt 50) { throw 'WordPress RSS exceeded its global item bound.' }
    if ([string]$rssItems[0].link -ne "$baseUrl/comebacks#event-$bulkSentinelId") {
        throw 'WordPress RSS pre-truncated schedules by post date instead of selecting the newest release timestamp.'
    }
    foreach ($rssItem in $rssItems) {
        if (([string]$rssItem.description).Length -gt 700) { throw 'WordPress RSS emitted an overlong final description.' }
    }
    $secondaryIndex = [array]::IndexOf(@($rssItems | ForEach-Object { [string]$_.link }), "$baseUrl/comebacks#event-$secondaryScheduleId")
    $articleIndex = [array]::IndexOf(@($rssItems | ForEach-Object { [string]$_.link }), "$baseUrl$fixtureArticlePath")
    $primaryIndex = [array]::IndexOf(@($rssItems | ForEach-Object { [string]$_.link }), "$baseUrl/comebacks#event-$temporaryScheduleId")
    if ($secondaryIndex -lt 0 -or $articleIndex -lt 0 -or $primaryIndex -lt 0 -or -not ($secondaryIndex -lt $articleIndex -and $articleIndex -lt $primaryIndex)) {
        throw 'WordPress RSS did not globally order interleaved article and schedule timestamps.'
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
    if ($notModified.StatusCode -ne 304 -or $notModified.Headers['Cache-Control'] -notmatch 'public') { throw 'WordPress sitemap did not honor its ETag with public cache headers.' }
    $weakList = Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/sitemap.xml" -Headers @{ 'If-None-Match' = '"unrelated", W/' + [string]$sitemap.Headers['ETag'] } -SkipHttpErrorCheck -TimeoutSec 30
    if ($weakList.StatusCode -ne 304) { throw 'WordPress sitemap did not honor weak or list-form If-None-Match validators.' }
    $imsOnly = Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/sitemap.xml" -Headers @{ 'If-Modified-Since' = [string]$sitemap.Headers['Last-Modified'] } -SkipHttpErrorCheck -TimeoutSec 30
    if ($imsOnly.StatusCode -ne 304 -or $imsOnly.Headers['Cache-Control'] -notmatch 'public') { throw 'WordPress sitemap did not honor a current IMS-only validator.' }
    $mismatched = Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/sitemap.xml" -Headers @{ 'If-None-Match' = '"not-current"'; 'If-Modified-Since' = [string]$sitemap.Headers['Last-Modified'] } -SkipHttpErrorCheck -TimeoutSec 30
    if ($mismatched.StatusCode -ne 200 -or $mismatched.Headers['Content-Type'] -notmatch 'application/xml' -or $mismatched.Headers['Cache-Control'] -notmatch 'public') { throw 'If-None-Match did not take precedence with complete public response headers.' }

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
        '/login',
        '/signup',
        '/forgot-password',
        '/submit',
        '/bookmarks',
        '/cookie-settings',
        '/wp-json/kpopblog/v1/admin',
        '/wp-json/kpopblog/v1/auth',
        '/wp-json/kpopblog/v1/profile/me',
        '/wp-json/kpopblog/v1/submissions',
        '/wp-json/kpopblog/v1/reports',
        '/wp-json/kpopblog/v1/newsletter/subscribe',
        '/wp-json/kpopblog/v1/newsletter/confirm',
        '/wp-json/kpopblog/v1/newsletter/unsubscribe',
        '/wp-json/kpopblog/v1/polls/*/vote',
        '/wp-json/kpopblog/v1/artists/*/follow',
        '/wp-json/kpopblog/v1/articles/*/engage',
        '/wp-json/kpopblog/v1/articles/*/comments',
        '/wp-json/kpopblog/v1/videos/*/comments',
        '/wp-json/kpopblog/v1/threads',
        '/wp-json/kpopblog/v1/community',
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
    if ($llms.Content -notmatch [regex]::Escape('\\[review\\]\\(unsafe\\)') -or $llms.Content -match '- \[KpopBlog SEO \[review\]') {
        throw 'WordPress llms output did not neutralize Markdown syntax in stored titles.'
    }
    $privateTransition = @'
$post_id = __POST_ID__;
global $wpdb;
$updated = wp_update_post( array( 'ID' => $post_id, 'post_status' => 'private' ), true );
if ( is_wp_error( $updated ) || 'private' !== get_post_status( $post_id ) ) { throw new Exception( 'could not make cache fixture private' ); }
'@.Replace('__POST_ID__', [string]$temporaryArticleId)
    Invoke-WpCli 'eval' $privateTransition | Out-Null
    $afterPrivate = Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/sitemap.xml" -Headers @{ 'If-None-Match' = [string]$sitemap.Headers['ETag']; 'If-Modified-Since' = [string]$sitemap.Headers['Last-Modified'] } -SkipHttpErrorCheck -TimeoutSec 30
    $beforeLastModified = [DateTimeOffset]::Parse([string]$sitemap.Headers['Last-Modified']).ToUniversalTime()
    $afterLastModified = [DateTimeOffset]::Parse([string]$afterPrivate.Headers['Last-Modified']).ToUniversalTime()
    if ($afterPrivate.StatusCode -ne 200 -or $afterPrivate.Headers['Content-Type'] -notmatch 'application/xml' -or $afterPrivate.Headers['Cache-Control'] -notmatch 'public' -or $afterPrivate.Content -match $fixtureArticlePattern -or [string]$afterPrivate.Headers['ETag'] -eq [string]$sitemap.Headers['ETag'] -or $afterLastModified -le $beforeLastModified) {
        throw 'Discovery cache revalidation exposed a formerly public article after it became private.'
    }

    $sameSecondScript = @'
global $wpdb;
$post_id = __POST_ID__;
$slug = '__POST_SLUG__';
delete_option( 'kpopblog_discovery_revision' );
wp_cache_delete( 'kpopblog_discovery_revision', 'options' );
$option_absent_before = 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", 'kpopblog_discovery_revision' ) );
$fixed_gmt = '2051-04-05 06:07:08';
$fixed_local = get_date_from_gmt( $fixed_gmt );
$force_modified = function ( $data, $postarr ) use ( $post_id, $fixed_gmt, $fixed_local ) {
    if ( isset( $postarr['ID'] ) && (int) $postarr['ID'] === $post_id ) {
        $data['post_modified'] = $fixed_local;
        $data['post_modified_gmt'] = $fixed_gmt;
    }
    return $data;
};
add_filter( 'wp_insert_post_data', $force_modified, PHP_INT_MAX, 2 );
try {
    $published = wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ), true );
    if ( is_wp_error( $published ) || 'publish' !== get_post_status( $post_id ) ) { throw new Exception( 'could not publish same-second revision fixture' ); }
    $published_post = get_post( $post_id );
    $published_revision = (int) get_option( 'kpopblog_discovery_revision', 0 );
    $published_last_modified = kpopblog_discovery_last_modified();
    $published_sitemap = kpopblog_render_sitemap();
    $privatized = wp_update_post( array( 'ID' => $post_id, 'post_status' => 'private' ), true );
    if ( is_wp_error( $privatized ) || 'private' !== get_post_status( $post_id ) ) { throw new Exception( 'could not privatize same-second revision fixture' ); }
    $private_post = get_post( $post_id );
    $private_revision = (int) get_option( 'kpopblog_discovery_revision', 0 );
    $private_last_modified = kpopblog_discovery_last_modified();
    $private_sitemap = kpopblog_render_sitemap();
} finally {
    remove_filter( 'wp_insert_post_data', $force_modified, PHP_INT_MAX );
}
echo wp_json_encode( array(
    'option_absent_before'     => $option_absent_before,
    'published_revision'      => $published_revision,
    'private_revision'        => $private_revision,
    'expected_first_revision' => strtotime( $fixed_gmt . ' UTC' ) + 1,
    'published_last_modified' => $published_last_modified,
    'private_last_modified'   => $private_last_modified,
    'published_modified_gmt'  => $published_post ? $published_post->post_modified_gmt : '',
    'private_modified_gmt'    => $private_post ? $private_post->post_modified_gmt : '',
    'published_contains_path' => false !== strpos( $published_sitemap, '/artist/' . $slug ),
    'private_contains_path'   => false !== strpos( $private_sitemap, '/artist/' . $slug ),
) );
'@.Replace('__POST_ID__', [string]$revisionPostId).Replace('__POST_SLUG__', [string]$fixtureResult.revision_slug)
    $sameSecond = Convert-WpCliJson (Invoke-WpCli 'eval' $sameSecondScript)
    if (-not [bool]$sameSecond.option_absent_before -or [long]$sameSecond.published_revision -ne [long]$sameSecond.expected_first_revision -or [long]$sameSecond.private_revision -ne ([long]$sameSecond.published_revision + 1)) {
        throw 'Option-absent same-second publish-to-private transition did not advance the discovery revision monotonically.'
    }
    if ([string]$sameSecond.published_modified_gmt -ne [string]$sameSecond.private_modified_gmt -or [string]$sameSecond.published_modified_gmt -ne '2051-04-05 06:07:08') {
        throw 'Same-second transition fixture did not retain the deterministic modified timestamp.'
    }
    if (-not [bool]$sameSecond.published_contains_path -or [bool]$sameSecond.private_contains_path) {
        throw 'Same-second transition fixture did not enter and leave the sitemap.'
    }
    $publishedIms = ([DateTimeOffset]::ParseExact([string]$sameSecond.published_last_modified + ' +00:00', 'yyyy-MM-dd HH:mm:ss zzz', [Globalization.CultureInfo]::InvariantCulture)).ToString('R')
    $sameSecondRevalidation = Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/sitemap.xml" -Headers @{ 'If-Modified-Since' = $publishedIms } -SkipHttpErrorCheck -TimeoutSec 30
    if ($sameSecondRevalidation.StatusCode -ne 200 -or $sameSecondRevalidation.Content -match [regex]::Escape('/artist/' + [string]$fixtureResult.revision_slug)) {
        throw 'IMS-only revalidation returned stale output after a same-second publish-to-private transition.'
    }
} finally {
    Remove-TemporaryFixtures -Marker $fixtureMarker
    Restore-DiscoveryRevisionState -State $discoveryRevisionBefore
    $notificationStateAfter = Get-NotificationState -Marker $fixtureMarker
    Assert-NotificationStateUnchanged -Before $notificationStateBefore -After $notificationStateAfter
    $discoveryRevisionAfter = Get-DiscoveryRevisionState
    if ([bool]$discoveryRevisionAfter.exists -ne [bool]$discoveryRevisionBefore.exists -or [string]$discoveryRevisionAfter.value -ne [string]$discoveryRevisionBefore.value) {
        throw 'SEO smoke test did not restore the discovery revision option.'
    }
}

Write-Host 'WordPress SEO runtime smoke test passed.'
