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
$original = get_option( 'kpopblog_ads', null );
try {
    $invalid = kpopblog_sanitize_ads_settings( array(
        'enabled' => 1,
        'publisher_id' => 'pub-invalid',
        'slots' => "global-top-leaderboard=slot-invalid\nunknown-slot=1234567890",
    ) );
    if ( $invalid['enabled'] || $invalid['publisher_id'] || $invalid['slots'] ) {
        throw new Exception( 'invalid AdSense settings were accepted' );
    }

    $valid = kpopblog_sanitize_ads_settings( array(
        'enabled' => 1,
        'publisher_id' => 'ca-pub-1234567890123456',
        'slots' => "global-top-leaderboard=1234567890\narticle-inline=2345678901\nglobal-sticky-footer=3456789012\nunknown-slot=4567890123",
    ) );
    if ( ! $valid['enabled'] || 'ca-pub-1234567890123456' !== $valid['publisher_id'] ) {
        throw new Exception( 'valid AdSense publisher configuration was rejected' );
    }
    if ( 2 !== count( $valid['slots'] ) || '1234567890' !== $valid['slots']['global-top-leaderboard'] || isset( $valid['slots']['global-sticky-footer'] ) ) {
        throw new Exception( 'AdSense slot allow-list failed' );
    }

    update_option( 'kpopblog_ads', $valid, false );
    $frontend = kpopblog_ads_frontend_config();
    if ( empty( $frontend['enabled'] ) || $frontend['publisherId'] !== $valid['publisher_id'] || $frontend['slots'] !== $valid['slots'] ) {
        throw new Exception( 'AdSense frontend configuration mismatch' );
    }

    wp_set_current_user( 1 );
    do_action( 'admin_menu' );
    global $submenu;
    $ads_menu = false;
    foreach ( isset( $submenu['kpopblog-admin'] ) ? (array) $submenu['kpopblog-admin'] : array() as $item ) {
        if ( isset( $item[2] ) && 'kpopblog-ads' === $item[2] ) {
            $ads_menu = true;
            break;
        }
    }
    if ( ! $ads_menu ) {
        throw new Exception( 'AdSense administrator submenu missing' );
    }

    kpopblog_enqueue_assets();
    $registered_script = wp_scripts()->registered['kpopblog-app'];
    $registered_style = wp_styles()->registered['kpopblog-app'];
    if ( null !== $registered_script->ver || null !== $registered_style->ver ) {
        throw new Exception( 'hashed application assets received a WordPress version query' );
    }
    $localized = wp_scripts()->get_data( 'kpopblog-app', 'data' );
    if ( false === strpos( (string) $localized, 'ca-pub-1234567890123456' ) || false === strpos( (string) $localized, 'global-top-leaderboard' ) ) {
        throw new Exception( 'AdSense settings were not localized to the app' );
    }
} finally {
    if ( null === $original ) {
        delete_option( 'kpopblog_ads' );
    } else {
        update_option( 'kpopblog_ads', $original, false );
    }
}
'@

docker compose --env-file $envFile -f $compose run --rm --no-deps cli eval $assertions
if ($LASTEXITCODE -ne 0) {
    throw 'AdSense assertions failed.'
}

Write-Host 'AdSense smoke test passed.'
