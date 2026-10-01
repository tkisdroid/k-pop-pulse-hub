<?php
/** Consent-based, first-party traffic statistics. No IP addresses or user agents are stored. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function kpopblog_traffic_path( $value ) {
	if ( ! is_string( $value ) || strlen( $value ) > 500 || '/' !== substr( $value, 0, 1 ) || '//' === substr( $value, 0, 2 ) ) { return ''; }
	$path = untrailingslashit( (string) wp_parse_url( $value, PHP_URL_PATH ) );
	if ( '' === $path ) { return '/'; }
	// Only public content routes; never retain search queries or account paths.
	return preg_match( '#^/(?:latest|trending|artists|comebacks|charts|videos|community|forum|polls|quiz|about|news/[a-zA-Z0-9_-]+|artist/[a-zA-Z0-9_-]+|member/[a-zA-Z0-9_-]+|thread/[a-zA-Z0-9_-]+|watch/[a-zA-Z0-9_-]+|polls/[a-zA-Z0-9_-]+|forum/[a-zA-Z0-9_-]+|category/[a-zA-Z0-9_-]+|tag/[a-zA-Z0-9_-]+|author/[a-zA-Z0-9_-]+)$#D', $path ) ? $path : '';
}

function kpopblog_record_traffic( WP_REST_Request $request ) {
	global $wpdb;
	if ( true !== $request->get_param( 'consent' ) ) { return new WP_Error( 'consent_required', 'Analytics consent is required.', array( 'status' => 400 ) ); }
	// Prevent cross-site submissions. Cached public pages need no REST nonce.
	$origin = $request->get_header( 'origin' );
	$site = wp_parse_url( home_url() );
	$site_origin = $site['scheme'] . '://' . $site['host'] . ( isset( $site['port'] ) ? ':' . $site['port'] : '' );
	if ( untrailingslashit( $origin ) !== $site_origin ) {
		return new WP_Error( 'invalid_origin', 'Same-origin requests only.', array( 'status' => 403 ) );
	}
	$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
	if ( current_user_can( 'manage_options' ) || preg_match( '/bot|crawler|spider|headless|preview|slurp/i', $agent ) ) { return new WP_REST_Response( null, 204 ); }
	$path = kpopblog_traffic_path( $request->get_param( 'path' ) );
	$visitor = $request->get_param( 'visitor' );
	$event = $request->get_param( 'event' );
	foreach ( array( $visitor, $event ) as $id ) {
		if ( ! is_string( $id ) || ! preg_match( '/^[a-f0-9-]{36}$/D', $id ) ) { return new WP_Error( 'invalid_id', 'Invalid traffic identifier.', array( 'status' => 400 ) ); }
	}
	if ( '' === $path ) { return new WP_Error( 'invalid_path', 'Public content path required.', array( 'status' => 400 ) ); }
	$visitor_hash = hash_hmac( 'sha256', $visitor, wp_salt( 'auth' ) );
	$rate_key = 'kb_traffic_' . $visitor_hash;
	$rate = get_transient( $rate_key );
	$rate = is_array( $rate ) ? $rate : array( 'start' => time(), 'count' => 0 );
	if ( time() - $rate['start'] >= MINUTE_IN_SECONDS ) { $rate = array( 'start' => time(), 'count' => 0 ); }
	if ( $rate['count'] >= 120 ) { return new WP_Error( 'traffic_rate_limit', 'Too many requests.', array( 'status' => 429 ) ); }
	++$rate['count'];
	set_transient( $rate_key, $rate, MINUTE_IN_SECONDS );
	$result = $wpdb->query( $wpdb->prepare(
		"INSERT IGNORE INTO {$wpdb->prefix}kb_traffic (event_hash, visitor_hash, visit_date, path, created_at) VALUES (%s, %s, %s, %s, %s)",
		hash_hmac( 'sha256', $event, wp_salt( 'auth' ) ), $visitor_hash, wp_date( 'Y-m-d' ), $path, current_time( 'mysql', true )
	) );
	if ( false === $result ) { return new WP_Error( 'traffic_unavailable', 'Statistics storage is unavailable.', array( 'status' => 503 ) ); }
	return new WP_REST_Response( null, 204 );
}

function kpopblog_get_traffic_stats() {
	global $wpdb;
	$today = new DateTimeImmutable( 'today', wp_timezone() );
	$table = $wpdb->prefix . 'kb_traffic';
	$summary = array();
	foreach ( array( 1, 7, 30 ) as $days ) {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors FROM {$table} WHERE visit_date >= %s", $today->modify( '-' . ( $days - 1 ) . ' days' )->format( 'Y-m-d' ) ), ARRAY_A );
		if ( ! is_array( $row ) ) { return new WP_Error( 'traffic_unavailable', 'Statistics storage is unavailable.', array( 'status' => 503 ) ); }
		$summary[ $days ] = array( 'views' => (int) $row['views'], 'visitors' => (int) $row['visitors'] );
	}
	$start = $today->modify( '-29 days' )->format( 'Y-m-d' );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT visit_date AS date, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors FROM {$table} WHERE visit_date >= %s GROUP BY visit_date ORDER BY visit_date", $start ), ARRAY_A );
	$by_date = array_column( $rows, null, 'date' );
	$daily = array();
	for ( $offset = 29; $offset >= 0; --$offset ) {
		$date = $today->modify( '-' . $offset . ' days' )->format( 'Y-m-d' );
		$daily[] = array( 'date' => $date, 'views' => (int) ( $by_date[ $date ]['views'] ?? 0 ), 'visitors' => (int) ( $by_date[ $date ]['visitors'] ?? 0 ) );
	}
	$pages = $wpdb->get_results( $wpdb->prepare( "SELECT path, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors FROM {$table} WHERE visit_date >= %s GROUP BY path ORDER BY views DESC, path ASC LIMIT 20", $start ), ARRAY_A );
	foreach ( $pages as &$page ) { $page['views'] = (int) $page['views']; $page['visitors'] = (int) $page['visitors']; }
	return array( 'summary' => $summary, 'daily' => $daily, 'pages' => $pages, 'timezone' => wp_timezone_string() );
}

add_action( 'rest_api_init', function () {
	register_rest_route( KPOPBLOG_REST_NS, '/analytics/pageview', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'kpopblog_record_traffic' ) );
	register_rest_route( KPOPBLOG_REST_NS, '/admin/analytics', array(
		'methods' => 'GET',
		'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		'callback' => function () {
			$stats = kpopblog_get_traffic_stats();
			if ( is_wp_error( $stats ) ) { return $stats; }
			$response = rest_ensure_response( $stats );
			$response->header( 'Cache-Control', 'private, no-store' );
			return $response;
		},
	) );
} );

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'kpopblog_cleanup_traffic' ) ) { wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'kpopblog_cleanup_traffic' ); }
} );
add_action( 'kpopblog_cleanup_traffic', function () {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}kb_traffic WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ) ) );
} );

add_action( 'admin_menu', function () {
	add_submenu_page( 'kpopblog-admin', '접속자 통계', '접속자 통계', 'manage_options', 'kpopblog-analytics', 'kpopblog_render_traffic_admin' );
} );

function kpopblog_render_traffic_admin() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Administrator access required.' ); }
	$stats = kpopblog_get_traffic_stats();
	if ( is_wp_error( $stats ) ) { wp_die( esc_html( $stats->get_error_message() ) ); }
	?>
	<div class="wrap">
		<h1>접속자 통계</h1>
		<p>통계 수집에 동의한 브라우저만 집계합니다. 방문자는 브라우저 기준 추정치이며, 관리자·알려진 봇은 제외합니다. IP 주소는 저장하지 않습니다. 수집 시작 이전 방문 기록은 표시할 수 없습니다.</p>
		<p>기준 시간대: <?php echo esc_html( $stats['timezone'] ); ?> · 기록 보관: 90일 · <a href="<?php echo esc_url( admin_url( 'admin.php?page=kpopblog-analytics' ) ); ?>">새로고침</a></p>
		<h2>방문 요약</h2>
		<table class="widefat striped"><thead><tr><th>기간</th><th>방문자</th><th>페이지 조회수</th></tr></thead><tbody>
		<?php foreach ( array( 1 => '오늘', 7 => '최근 7일', 30 => '최근 30일' ) as $days => $label ) : ?>
			<tr><td><?php echo esc_html( $label ); ?></td><td><?php echo esc_html( number_format_i18n( $stats['summary'][ $days ]['visitors'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $stats['summary'][ $days ]['views'] ) ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<h2>인기 페이지 (최근 30일)</h2>
		<table class="widefat striped"><thead><tr><th>페이지</th><th>방문자</th><th>조회수</th></tr></thead><tbody>
		<?php foreach ( $stats['pages'] as $page ) : ?>
			<tr><td><a href="<?php echo esc_url( home_url( $page['path'] ) ); ?>"><?php echo esc_html( $page['path'] ); ?></a></td><td><?php echo esc_html( number_format_i18n( $page['visitors'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $page['views'] ) ); ?></td></tr>
		<?php endforeach; ?>
		<?php if ( ! $stats['pages'] ) : ?><tr><td colspan="3">아직 수집된 방문 기록이 없습니다.</td></tr><?php endif; ?>
		</tbody></table>
		<h2>일별 방문 추이</h2>
		<table class="widefat striped"><thead><tr><th>날짜</th><th>방문자</th><th>조회수</th></tr></thead><tbody>
		<?php foreach ( array_reverse( $stats['daily'] ) as $day ) : ?>
			<tr><td><?php echo esc_html( $day['date'] ); ?></td><td><?php echo esc_html( number_format_i18n( $day['visitors'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $day['views'] ) ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
	</div>
	<?php
}
