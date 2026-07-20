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
$check_ids = wp_list_pluck( $data['checks'], 'id' );
foreach ( array( 'assets', 'homepage', 'permalinks', 'registration', 'cron', 'runtime_data', 'service_worker' ) as $check_id ) {
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
