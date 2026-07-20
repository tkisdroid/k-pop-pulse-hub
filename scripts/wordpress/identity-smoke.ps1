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
$original_registration = get_option( 'users_can_register' );
$created_user_id = 0;
$test_login = 'identity_smoke_member';
$existing = get_user_by( 'login', $test_login );
if ( $existing ) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user( $existing->ID );
}

try {
    update_option( 'users_can_register', 0 );
    wp_set_current_user( 0 );
    $blocked = new WP_REST_Request( 'POST', '/kpopblog/v1/auth/register' );
    $blocked->set_body_params( array(
        'email'       => 'identity-disabled@example.test',
        'username'    => 'identity_disabled',
        'displayName' => 'Disabled Registration',
        'password'    => 'IdentitySmokePassword123!',
    ) );
    $blocked_response = rest_do_request( $blocked );
    if ( 403 !== $blocked_response->get_status() ) {
        $unexpected = get_user_by( 'login', 'identity_disabled' );
        if ( $unexpected ) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user( $unexpected->ID );
        }
        throw new Exception( 'disabled registration was not rejected' );
    }

    if ( ! function_exists( 'kpopblog_check_rate_limit' ) || ! function_exists( 'kpopblog_rate_limit_key' ) ) {
        throw new Exception( 'identity rate limiter missing' );
    }
    $rate_key = kpopblog_rate_limit_key( 'identity_smoke', 'fixed-identity' );
    delete_transient( $rate_key );
    if ( true !== kpopblog_check_rate_limit( 'identity_smoke', 'fixed-identity', 2, 60 ) ) {
        throw new Exception( 'first rate-limit request failed' );
    }
    if ( true !== kpopblog_check_rate_limit( 'identity_smoke', 'fixed-identity', 2, 60 ) ) {
        throw new Exception( 'second rate-limit request failed' );
    }
    $limited = kpopblog_check_rate_limit( 'identity_smoke', 'fixed-identity', 2, 60 );
    if ( ! is_wp_error( $limited ) || 429 !== (int) $limited->get_error_data()['status'] ) {
        throw new Exception( 'rate limit did not return HTTP 429' );
    }
    delete_transient( $rate_key );

    update_option( 'users_can_register', 1 );
    delete_transient( kpopblog_rate_limit_key( 'register_ip', kpopblog_request_ip() ) );
    delete_transient( kpopblog_rate_limit_key( 'register_identity', 'identity-smoke@example.test|' . $test_login ) );
    $registration = new WP_REST_Request( 'POST', '/kpopblog/v1/auth/register' );
    $registration->set_body_params( array(
        'email'       => 'identity-smoke@example.test',
        'username'    => $test_login,
        'displayName' => 'Identity Smoke',
        'password'    => 'IdentitySmokePassword123!',
        'role'        => 'administrator',
    ) );
    $registration_response = rest_do_request( $registration );
    if ( 200 !== $registration_response->get_status() ) {
        throw new Exception( 'enabled registration failed: ' . $registration_response->get_status() );
    }
    $created = get_user_by( 'login', $test_login );
    if ( ! $created ) {
        throw new Exception( 'registered user was not persisted' );
    }
    $created_user_id = $created->ID;
    if ( ! in_array( 'subscriber', $created->roles, true ) || 'member' !== get_user_meta( $created_user_id, 'kb_role', true ) ) {
        throw new Exception( 'registration did not enforce the member role' );
    }
    $registration_data = $registration_response->get_data();
    if ( ! empty( $registration_data['user']['capabilities']['manageOptions'] ) ) {
        throw new Exception( 'registered member received administrator capabilities' );
    }

    wp_set_current_user( 0 );
    $profile_response = rest_do_request( new WP_REST_Request( 'GET', '/kpopblog/v1/profiles/' . $test_login ) );
    if ( 200 !== $profile_response->get_status() ) {
        throw new Exception( 'public profile request failed' );
    }
    $profile = $profile_response->get_data();
    if ( isset( $profile['email'] ) || isset( $profile['profile']['email'] ) ) {
        throw new Exception( 'public profile exposed email' );
    }

    wp_set_current_user( $created_user_id );
    $update = new WP_REST_Request( 'POST', '/kpopblog/v1/profile/me' );
    $update->set_body_params( array(
        'displayName' => 'Updated Identity',
        'bio'         => 'Updated from the identity runtime smoke test.',
        'country'     => 'CA',
        'language'    => 'ko',
        'role'        => 'admin',
        'points'      => 999999,
    ) );
    $update_response = rest_do_request( $update );
    if ( 200 !== $update_response->get_status() ) {
        throw new Exception( 'self profile update failed' );
    }
    $updated = $update_response->get_data();
    if ( 'Updated Identity' !== $updated['user']['displayName'] || 'ko' !== $updated['user']['language'] ) {
        throw new Exception( 'self profile update did not persist allowed fields' );
    }
    if ( 'admin' === $updated['user']['role'] || 999999 === (int) $updated['user']['points'] ) {
        throw new Exception( 'self profile update changed privileged fields' );
    }

    wp_set_current_user( 0 );
    $anonymous_update = rest_do_request( $update );
    if ( ! in_array( $anonymous_update->get_status(), array( 401, 403 ), true ) ) {
        throw new Exception( 'anonymous profile update was not rejected' );
    }
} finally {
    update_option( 'users_can_register', $original_registration );
    wp_set_current_user( 1 );
    if ( $created_user_id && ! is_wp_error( $created_user_id ) ) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $created_user_id );
    }
    if ( function_exists( 'kpopblog_rate_limit_key' ) ) {
        delete_transient( kpopblog_rate_limit_key( 'register_ip', kpopblog_request_ip() ) );
        delete_transient( kpopblog_rate_limit_key( 'register_identity', 'identity-smoke@example.test|' . $test_login ) );
    }
}
'@

docker compose --env-file $envFile -f $compose run --rm --no-deps cli eval $assertions
if ($LASTEXITCODE -ne 0) {
    throw 'Identity assertions failed.'
}

Write-Host 'Identity smoke test passed.'
