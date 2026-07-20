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
global $wpdb;
$table = $wpdb->prefix . 'kb_audit_log';
if ( ! defined( 'KPOPBLOG_SCHEMA_VERSION' ) ) {
    throw new Exception( 'schema version constant missing' );
}
if ( get_option( 'kpopblog_schema_version' ) !== KPOPBLOG_SCHEMA_VERSION ) {
    throw new Exception( 'schema version mismatch' );
}
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
    throw new Exception( 'audit table missing' );
}
$admin = get_role( 'administrator' );
foreach ( array( 'kb_moderate_community', 'kb_manage_automation', 'kb_manage_notifications', 'kb_manage_ads' ) as $cap ) {
    if ( ! $admin || ! $admin->has_cap( $cap ) ) {
        throw new Exception( 'administrator capability missing: ' . $cap );
    }
}
$editor = get_role( 'editor' );
if ( ! $editor || ! $editor->has_cap( 'kb_moderate_community' ) ) {
    throw new Exception( 'editor moderation capability missing' );
}
if ( $editor->has_cap( 'kb_manage_automation' ) ) {
    throw new Exception( 'editor has administrator-only capability' );
}
if ( ! kpopblog_audit( 'foundation_smoke', 'plugin', 0, array( 'source' => 'wp-cli' ) ) ) {
    throw new Exception( 'audit insert failed' );
}
'@

docker compose --env-file $envFile -f $compose run --rm --no-deps cli eval $assertions
if ($LASTEXITCODE -ne 0) {
    throw 'Plugin foundation assertions failed.'
}

docker compose --env-file $envFile -f $compose exec -T wordpress sh -lc "find /var/www/html/wp-content/plugins/kpopblog -name '*.php' -print0 | xargs -0 -n1 php -l"
if ($LASTEXITCODE -ne 0) {
    throw 'PHP syntax checks failed.'
}

Write-Host 'Plugin foundation smoke test passed.'
