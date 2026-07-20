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
$user_id = 0;
$community_id = 0;
$thread_id = 0;
$reply_id = 0;
$report_id = 0;
$reports_table = $wpdb->prefix . 'kb_reports';
$test_login = 'community_smoke_member';

try {
    if ( ! post_type_exists( 'kb_community' ) ) {
        throw new Exception( 'community post type missing' );
    }
    if ( ! taxonomy_exists( 'kb_forum_category' ) ) {
        throw new Exception( 'forum category taxonomy missing' );
    }
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $reports_table ) ) !== $reports_table ) {
        throw new Exception( 'reports table missing' );
    }

    $existing = get_user_by( 'login', $test_login );
    if ( $existing ) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $existing->ID );
    }
    $user_id = wp_insert_user( array(
        'user_login'   => $test_login,
        'user_email'   => 'community-smoke@example.test',
        'user_pass'    => wp_generate_password( 24, true, true ),
        'display_name' => 'Community Smoke',
        'role'         => 'subscriber',
    ) );
    if ( is_wp_error( $user_id ) ) {
        throw new Exception( 'failed to create community smoke user' );
    }

    $term = term_exists( 'general', 'kb_forum_category' );
    if ( ! $term ) {
        $term = wp_insert_term( 'General', 'kb_forum_category', array( 'slug' => 'general', 'description' => 'General K-pop discussion.' ) );
    }
    if ( is_wp_error( $term ) ) {
        throw new Exception( 'failed to create forum category' );
    }

    wp_set_current_user( $user_id );
    $community_request = new WP_REST_Request( 'POST', '/kpopblog/v1/community' );
    $community_request->set_body_params( array( 'body' => 'Community smoke post body.', 'language' => 'en' ) );
    $community_response = rest_do_request( $community_request );
    if ( 201 !== $community_response->get_status() ) {
        throw new Exception( 'community creation failed: ' . $community_response->get_status() );
    }
    $community_data = $community_response->get_data();
    $community_id = (int) $community_data['item']['id'];
    if ( 'pending' !== $community_data['status'] || 'pending' !== get_post_status( $community_id ) ) {
        throw new Exception( 'member community post did not enter review' );
    }

    $mine_request = new WP_REST_Request( 'GET', '/kpopblog/v1/community' );
    $mine_request->set_param( 'mine', 1 );
    $mine_response = rest_do_request( $mine_request );
    if ( 200 !== $mine_response->get_status() || '1' !== $mine_response->get_headers()['X-WP-Total'] ) {
        throw new Exception( 'community pagination headers missing' );
    }

    $thread_request = new WP_REST_Request( 'POST', '/kpopblog/v1/threads' );
    $thread_request->set_body_params( array(
        'categorySlug' => 'general',
        'title'        => 'Community smoke discussion',
        'body'         => 'Thread body created by the runtime smoke test.',
        'language'     => 'en',
    ) );
    $thread_response = rest_do_request( $thread_request );
    if ( 201 !== $thread_response->get_status() ) {
        throw new Exception( 'thread creation failed: ' . $thread_response->get_status() );
    }
    $thread_data = $thread_response->get_data();
    $thread_id = (int) $thread_data['item']['id'];
    if ( 'pending' !== $thread_data['status'] ) {
        throw new Exception( 'member thread did not enter review' );
    }
    wp_set_current_user( 1 );
    wp_publish_post( $thread_id );
    wp_set_current_user( $user_id );

    $reply_request = new WP_REST_Request( 'POST', '/kpopblog/v1/threads/' . get_post_field( 'post_name', $thread_id ) . '/replies' );
    $reply_request->set_body_params( array( 'body' => 'Forum reply from the runtime smoke test.' ) );
    $reply_response = rest_do_request( $reply_request );
    if ( 201 !== $reply_response->get_status() ) {
        throw new Exception( 'thread reply failed: ' . $reply_response->get_status() . ' slug=' . get_post_field( 'post_name', $thread_id ) . ' data=' . wp_json_encode( $reply_response->get_data() ) );
    }
    $reply_data = $reply_response->get_data();
    $reply_id = (int) $reply_data['item']['id'];

    update_post_meta( $thread_id, 'kb_locked', 1 );
    $locked_response = rest_do_request( $reply_request );
    if ( 409 !== $locked_response->get_status() ) {
        throw new Exception( 'locked thread accepted a reply' );
    }

    $report_request = new WP_REST_Request( 'POST', '/kpopblog/v1/reports' );
    $report_request->set_body_params( array(
        'targetType' => 'thread',
        'targetId'   => (string) $thread_id,
        'reason'     => 'Runtime smoke report reason.',
    ) );
    $report_response = rest_do_request( $report_request );
    if ( 201 !== $report_response->get_status() ) {
        throw new Exception( 'report creation failed: ' . $report_response->get_status() );
    }
    $report_data = $report_response->get_data();
    $report_id = (int) $report_data['report']['id'];
    $duplicate_response = rest_do_request( $report_request );
    if ( 409 !== $duplicate_response->get_status() ) {
        throw new Exception( 'duplicate pending report was accepted' );
    }

    $resolve_request = new WP_REST_Request( 'POST', '/kpopblog/v1/moderation/reports/' . $report_id );
    $resolve_request->set_body_params( array( 'action' => 'resolved', 'note' => 'Checked by smoke test.' ) );
    $member_resolve = rest_do_request( $resolve_request );
    if ( 403 !== $member_resolve->get_status() ) {
        throw new Exception( 'member resolved a moderation report' );
    }
    wp_set_current_user( 1 );
    $admin_resolve = rest_do_request( $resolve_request );
    if ( 200 !== $admin_resolve->get_status() || 'resolved' !== $admin_resolve->get_data()['report']['status'] ) {
        throw new Exception( 'administrator could not resolve report' );
    }

    $audit_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}kb_audit_log WHERE action = %s AND object_type = %s AND object_id = %d",
        'report_resolved',
        'report',
        $report_id
    ) );
    if ( $audit_count < 1 ) {
        throw new Exception( 'report resolution was not audited' );
    }
} finally {
    wp_set_current_user( 1 );
    if ( $reply_id ) {
        wp_delete_comment( $reply_id, true );
    }
    if ( $community_id ) {
        wp_delete_post( $community_id, true );
    }
    if ( $thread_id ) {
        wp_delete_post( $thread_id, true );
    }
    if ( $report_id && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $reports_table ) ) === $reports_table ) {
        $wpdb->delete( $reports_table, array( 'id' => $report_id ), array( '%d' ) );
    }
    if ( $user_id && ! is_wp_error( $user_id ) ) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $user_id );
    }
}
'@

docker compose --env-file $envFile -f $compose run --rm --no-deps cli eval $assertions
if ($LASTEXITCODE -ne 0) {
    throw 'Community assertions failed.'
}

Write-Host 'Community smoke test passed.'
