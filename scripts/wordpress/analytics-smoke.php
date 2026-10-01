<?php
/** Run with `wp eval-file scripts/wordpress/analytics-smoke.php` on a disposable localhost install. */
if ( ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) {
	throw new Exception( 'Use a disposable localhost WordPress database for this test.' );
}

function kb_traffic_assert( $condition, $message ) {
	if ( ! $condition ) { throw new Exception( $message ); }
}
function kb_traffic_request( $changes = array(), $origin = 'http://localhost:8099' ) {
	$request = new WP_REST_Request( 'POST', '/kpopblog/v1/analytics/pageview' );
	$request->set_header( 'origin', $origin );
	$request->set_body_params( array_merge( array(
		'consent' => true, 'path' => '/news/test?private=discard-me',
		'visitor' => '11111111-1111-4111-8111-111111111111',
		'event' => '22222222-2222-4222-8222-222222222222',
	), $changes ) );
	return rest_do_request( $request );
}

global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->prefix}kb_traffic" );
update_option( 'timezone_string', 'Asia/Seoul' );
wp_set_current_user( 0 );
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 integration-test';
delete_transient( 'kb_traffic_' . hash_hmac( 'sha256', '11111111-1111-4111-8111-111111111111', wp_salt( 'auth' ) ) );

kb_traffic_assert( 204 === kb_traffic_request()->get_status(), 'Page view storage failed.' );
kb_traffic_assert( 204 === kb_traffic_request()->get_status(), 'Event retry failed.' );
$stats = kpopblog_get_traffic_stats();
kb_traffic_assert( $stats['summary'][1] === array( 'views' => 1, 'visitors' => 1 ), 'Duplicate event was counted twice.' );
kb_traffic_assert( '/news/test' === $stats['pages'][0]['path'], 'Query parameters were retained.' );
kb_traffic_assert( 30 === count( $stats['daily'] ) && 'Asia/Seoul' === $stats['timezone'], 'Date series or timezone missing.' );
kb_traffic_assert( wp_date( 'Y-m-d' ) === $stats['daily'][29]['date'], 'Today is not in the site timezone.' );

kb_traffic_request( array( 'event' => '33333333-3333-4333-8333-333333333333' ) );
kb_traffic_request( array( 'visitor' => '44444444-4444-4444-8444-444444444444', 'event' => '55555555-5555-4555-8555-555555555555', 'path' => '/artists' ) );
kb_traffic_assert( kpopblog_get_traffic_stats()['summary'][1] === array( 'views' => 3, 'visitors' => 2 ), 'Browser deduplication or navigation counts failed.' );
kb_traffic_assert( 400 === kb_traffic_request( array( 'consent' => false ) )->get_status(), 'Rejected consent was collected.' );
kb_traffic_assert( 403 === kb_traffic_request( array(), 'https://other.example' )->get_status(), 'Cross-site collection allowed.' );
kb_traffic_assert( 400 === kb_traffic_request( array( 'path' => '/profile/private' ) )->get_status(), 'Private route collected.' );
kb_traffic_assert( 400 === kb_traffic_request( array( 'visitor' => array() ) )->get_status(), 'Malformed identifier accepted.' );
$_SERVER['HTTP_USER_AGENT'] = 'Googlebot';
kb_traffic_request( array( 'event' => '66666666-6666-4666-8666-666666666666' ) );
kb_traffic_assert( 3 === kpopblog_get_traffic_stats()['summary'][1]['views'], 'Bot counted.' );
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 integration-test';

$old_date = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-7 days' )->format( 'Y-m-d' );
$wpdb->insert( $wpdb->prefix . 'kb_traffic', array( 'event_hash' => str_repeat( 'a', 64 ), 'visitor_hash' => str_repeat( 'b', 64 ), 'visit_date' => $old_date, 'path' => '/', 'created_at' => current_time( 'mysql', true ) ) );
$stats = kpopblog_get_traffic_stats();
kb_traffic_assert( 3 === $stats['summary'][7]['views'] && 4 === $stats['summary'][30]['views'], 'Reporting window boundaries incorrect.' );
$wpdb->insert( $wpdb->prefix . 'kb_traffic', array( 'event_hash' => str_repeat( 'c', 64 ), 'visitor_hash' => str_repeat( 'd', 64 ), 'visit_date' => '2000-01-01', 'path' => '/', 'created_at' => '2000-01-01 00:00:00' ) );
do_action( 'kpopblog_cleanup_traffic' );
kb_traffic_assert( 4 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}kb_traffic" ), 'Retention cleanup failed.' );

kb_traffic_assert( in_array( rest_do_request( new WP_REST_Request( 'GET', '/kpopblog/v1/admin/analytics' ) )->get_status(), array( 401, 403 ), true ), 'Anonymous statistics exposed.' );
$subscriber = wp_insert_user( array( 'user_login' => 'traffic-test-member', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
if ( is_wp_error( $subscriber ) ) { $subscriber = get_user_by( 'login', 'traffic-test-member' )->ID; }
wp_set_current_user( $subscriber );
kb_traffic_assert( 403 === rest_do_request( new WP_REST_Request( 'GET', '/kpopblog/v1/admin/analytics' ) )->get_status(), 'Member statistics exposed.' );
wp_set_current_user( 1 );
$response = rest_do_request( new WP_REST_Request( 'GET', '/kpopblog/v1/admin/analytics' ) );
kb_traffic_assert( 200 === $response->get_status() && 'private, no-store' === $response->get_headers()['Cache-Control'], 'Admin report access/cache headers incorrect.' );
ob_start();
kpopblog_render_traffic_admin();
$html = ob_get_clean();
kb_traffic_assert( false !== strpos( $html, '최근 30일' ) && false !== strpos( $html, '/news/test' ), 'Admin report not rendered.' );
kb_traffic_assert( false !== strpos( get_site_icon_url( 32 ), 'favicon-32.png?ver=' . KPOPBLOG_VERSION ), 'WordPress fallback icon missing.' );
kb_traffic_assert( 'https://example.test/custom.png' === apply_filters( 'get_site_icon_url', 'https://example.test/custom.png', 32 ), 'Custom icon overwritten.' );
echo "Analytics integration passed: consent, navigation, deduplication, windows, timezone, retention, roles, report, icons.\n";
