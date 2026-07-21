[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$compose = Join-Path $repoRoot 'docker-compose.wordpress.yml'
$envFile = Join-Path $repoRoot '.env.wordpress'

if (-not (Test-Path -LiteralPath $envFile)) {
    $envFile = Join-Path $repoRoot '.env.wordpress.example'
}

$assertions = @'
wp_set_current_user( 1 );
do_action( 'admin_menu' );
global $menu;
$menu_found = false;
foreach ( (array) $menu as $item ) {
    if ( isset( $item[2] ) && $item[2] === 'kpopblog-admin' ) {
        $menu_found = true;
        break;
    }
}
if ( ! $menu_found ) {
    throw new Exception( 'K-pop Pulse Hub administrator menu missing' );
}
global $submenu;
$moderation_found = false;
$notifications_found = false;
$newsletter_settings_found = false;
$ads_found = false;
foreach ( isset( $submenu['kpopblog-admin'] ) ? (array) $submenu['kpopblog-admin'] : array() as $item ) {
    if ( isset( $item[2] ) && 'kpopblog-community-moderation' === $item[2] ) {
        $moderation_found = true;
    }
    if ( isset( $item[2] ) && 'kpopblog-notifications' === $item[2] ) {
        $notifications_found = true;
    }
    if ( isset( $item[2] ) && 'kpopblog-newsletter' === $item[2] ) {
        $newsletter_settings_found = true;
    }
    if ( isset( $item[2] ) && 'kpopblog-ads' === $item[2] ) {
        $ads_found = true;
    }
}
if ( ! $moderation_found ) {
    throw new Exception( 'community moderation submenu missing' );
}
if ( ! $notifications_found ) {
    throw new Exception( 'notifications submenu missing' );
}
if ( ! $newsletter_settings_found ) {
    throw new Exception( 'newsletter settings submenu missing' );
}
if ( ! $ads_found ) {
    throw new Exception( 'AdSense settings submenu missing' );
}

$request = new WP_REST_Request( 'GET', '/kpopblog/v1/admin/health' );
$response = rest_do_request( $request );
if ( 200 !== $response->get_status() ) {
    throw new Exception( 'administrator health request failed: ' . $response->get_status() );
}
$data = $response->get_data();
foreach ( array( 'pluginVersion', 'schemaVersion', 'wordpressVersion', 'phpVersion', 'checks', 'counts' ) as $key ) {
    if ( ! array_key_exists( $key, $data ) ) {
        throw new Exception( 'health response key missing: ' . $key );
    }
}
if ( ! isset( $data['counts']['content']['kb_community'] ) || ! array_key_exists( 'openReports', $data['counts'] ) || ! array_key_exists( 'pendingNotificationJobs', $data['counts'] ) ) {
    throw new Exception( 'community administrator counts missing' );
}
$check_ids = wp_list_pluck( $data['checks'], 'id' );
foreach ( array( 'assets', 'homepage', 'permalinks', 'registration', 'cron', 'runtime_data', 'service_worker', 'ads' ) as $check_id ) {
    if ( ! in_array( $check_id, $check_ids, true ) ) {
        throw new Exception( 'health check missing: ' . $check_id );
    }
}

wp_set_current_user( 0 );
$anonymous_response = rest_do_request( new WP_REST_Request( 'GET', '/kpopblog/v1/admin/health' ) );
if ( ! in_array( $anonymous_response->get_status(), array( 401, 403 ), true ) ) {
    throw new Exception( 'anonymous health request was not rejected' );
}
'@

docker compose --env-file $envFile -f $compose run --rm --no-deps cli eval $assertions
if ($LASTEXITCODE -ne 0) {
    throw 'Administrator assertions failed.'
}

Write-Host 'Administrator smoke test passed.'
