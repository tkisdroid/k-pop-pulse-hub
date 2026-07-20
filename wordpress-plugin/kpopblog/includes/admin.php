<?php
/**
 * WordPress operations dashboard and administrator-only health API.
 *
 * @package KpopBlog
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Build a normalized health-check result.
 */
function kpopblog_health_check( $id, $status, $label, $message, $action_url = '' ) {
	return array(
		'id'        => sanitize_key( $id ),
		'status'    => in_array( $status, array( 'good', 'warning', 'critical' ), true ) ? $status : 'warning',
		'label'     => (string) $label,
		'message'   => (string) $message,
		'actionUrl' => esc_url_raw( $action_url ),
	);
}

/**
 * Return operational checks without exposing filesystem paths or credentials.
 */
function kpopblog_get_health_checks() {
	$checks        = array();
	$manifest_path = KPOPBLOG_PATH . 'assets/manifest.json';
	$assets_ready  = false;

	if ( is_readable( $manifest_path ) ) {
		$manifest = json_decode( file_get_contents( $manifest_path ), true );
		if ( is_array( $manifest ) && ! empty( $manifest['js'] ) && ! empty( $manifest['css'] ) ) {
			$js_path  = KPOPBLOG_PATH . ltrim( (string) $manifest['js'], '/' );
			$css_path = KPOPBLOG_PATH . ltrim( (string) $manifest['css'], '/' );
			$assets_ready = is_readable( $js_path ) && is_readable( $css_path );
		}
	}

	$checks[] = kpopblog_health_check(
		'assets',
		$assets_ready ? 'good' : 'critical',
		'Application assets',
		$assets_ready ? 'The packaged JavaScript and CSS assets are available.' : 'Build and package the WordPress application assets before deployment.',
		admin_url( 'plugins.php' )
	);

	$front_page_id = (int) get_option( 'page_on_front' );
	$homepage_ready = $front_page_id > 0 && function_exists( 'kpopblog_uses_app_shell' ) && kpopblog_uses_app_shell( $front_page_id );
	$checks[] = kpopblog_health_check(
		'homepage',
		$homepage_ready ? 'good' : 'critical',
		'Application homepage',
		$homepage_ready ? 'The static homepage uses the KpopBlog full-page template.' : 'Select a static homepage that uses the KpopBlog full-page template.',
		admin_url( 'options-reading.php' )
	);

	$permalinks_ready = '' !== (string) get_option( 'permalink_structure' );
	$checks[] = kpopblog_health_check(
		'permalinks',
		$permalinks_ready ? 'good' : 'critical',
		'Permalinks',
		$permalinks_ready ? 'Pretty permalinks are enabled.' : 'Choose a non-plain permalink structure for application routes.',
		admin_url( 'options-permalink.php' )
	);

	$registration_enabled = (bool) get_option( 'users_can_register' );
	$checks[] = kpopblog_health_check(
		'registration',
		$registration_enabled ? 'good' : 'warning',
		'User registration',
		$registration_enabled ? 'Public WordPress registration is enabled.' : 'Public registration is disabled; new community members cannot sign up.',
		admin_url( 'options-general.php' )
	);

	$cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
	$checks[] = kpopblog_health_check(
		'cron',
		$cron_disabled ? 'warning' : 'good',
		'WordPress cron',
		$cron_disabled ? 'WP-Cron is disabled; configure a system cron before enabling automation.' : 'WP-Cron is available for scheduled jobs.',
		admin_url( 'site-health.php' )
	);

	$runtime_ready = (bool) get_option( 'kpopblog_runtime_data_ready', false );
	$checks[] = kpopblog_health_check(
		'runtime_data',
		$runtime_ready ? 'good' : 'warning',
		'WordPress data mode',
		$runtime_ready ? 'All public production workflows use WordPress data.' : 'Some public routes can still show demo-backed data until the content migration is complete.',
		admin_url( 'edit.php' )
	);

	$service_worker_ready = is_readable( KPOPBLOG_PATH . 'assets/sw.js' );
	$checks[] = kpopblog_health_check(
		'service_worker',
		$service_worker_ready ? 'good' : 'warning',
		'Service worker',
		$service_worker_ready ? 'A packaged service worker is available.' : 'The WordPress build has no valid service worker; offline registration must remain disabled.',
		admin_url( 'plugins.php' )
	);

	return $checks;
}

/**
 * Count content and moderation records for the dashboard and REST response.
 */
function kpopblog_get_admin_counts() {
	$content = array();
	foreach ( array( 'post', 'kb_artist', 'kb_member', 'kb_comeback', 'kb_chart', 'kb_thread', 'kb_community', 'kb_poll' ) as $post_type ) {
		$counts = wp_count_posts( $post_type );
		$content[ $post_type ] = $counts && isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	$user_counts    = count_users();
	$comment_counts = wp_count_comments();
	$subscribers    = new WP_Query( array(
		'post_type'      => 'kb_subscriber',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array(
				'key'   => 'kb_sub_confirmed',
				'value' => '1',
			),
		),
	) );
	global $wpdb;
	$reports_table = $wpdb->prefix . 'kb_reports';
	$open_reports = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $reports_table ) ) === $reports_table
		? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$reports_table} WHERE status = 'pending'" )
		: 0;

	return array(
		'content'              => $content,
		'users'                => isset( $user_counts['total_users'] ) ? (int) $user_counts['total_users'] : 0,
		'pendingComments'      => isset( $comment_counts->moderated ) ? (int) $comment_counts->moderated : 0,
		'confirmedSubscribers' => (int) $subscribers->found_posts,
		'openReports'          => $open_reports,
	);
}

function kpopblog_register_admin_menu() {
	add_menu_page(
		'K-pop Pulse Hub',
		'K-pop Pulse Hub',
		'manage_options',
		'kpopblog-admin',
		'kpopblog_render_admin_dashboard',
		'dashicons-chart-area',
		3
	);
	add_submenu_page(
		'kpopblog-admin',
		'Dashboard',
		'Dashboard',
		'manage_options',
		'kpopblog-admin',
		'kpopblog_render_admin_dashboard'
	);
}
add_action( 'admin_menu', 'kpopblog_register_admin_menu' );

function kpopblog_render_admin_dashboard() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'kpopblog' ) );
	}

	$user_id   = get_current_user_id();
	$audit_key = 'kpopblog_admin_viewed_' . $user_id;
	if ( ! get_transient( $audit_key ) ) {
		kpopblog_audit( 'admin_dashboard_viewed', 'dashboard' );
		set_transient( $audit_key, 1, HOUR_IN_SECONDS );
	}

	$counts = kpopblog_get_admin_counts();
	$links  = array(
		'Articles'    => admin_url( 'edit.php' ),
		'Artists'     => admin_url( 'edit.php?post_type=kb_artist' ),
		'Members'     => admin_url( 'edit.php?post_type=kb_member' ),
		'Comebacks'   => admin_url( 'edit.php?post_type=kb_comeback' ),
		'Charts'      => admin_url( 'edit.php?post_type=kb_chart' ),
		'Threads'     => admin_url( 'edit.php?post_type=kb_thread' ),
		'Community'   => admin_url( 'edit.php?post_type=kb_community' ),
		'Polls'       => admin_url( 'edit.php?post_type=kb_poll' ),
		'Users'       => admin_url( 'users.php' ),
		'Comments'    => admin_url( 'edit-comments.php' ),
		'Subscribers' => admin_url( 'edit.php?post_type=kb_subscriber' ),
	);
	?>
	<div class="wrap kpopblog-admin">
		<h1>K-pop Pulse Hub</h1>
		<p>Operate editorial content, members, community activity, subscriptions, and platform health from WordPress.</p>
		<style>
			.kpopblog-admin .kb-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;max-width:1100px;margin:20px 0}.kpopblog-admin .kb-card,.kpopblog-admin .kb-health{background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:16px}.kpopblog-admin .kb-card strong{display:block;font-size:24px;margin-top:8px}.kpopblog-admin .kb-health{max-width:1066px;margin-bottom:8px}.kpopblog-admin .kb-status{display:inline-block;border-radius:999px;padding:2px 9px;margin-right:8px;font-weight:600}.kpopblog-admin .kb-status-good{background:#d7f0df;color:#14532d}.kpopblog-admin .kb-status-warning{background:#fff1c2;color:#713f12}.kpopblog-admin .kb-status-critical{background:#fbd5d5;color:#7f1d1d}
		</style>
		<div class="kb-grid">
			<?php foreach ( $links as $label => $url ) :
				$key = array_search( $label, array( 'Articles', 'Artists', 'Members', 'Comebacks', 'Charts', 'Threads', 'Community', 'Polls' ), true );
				if ( false !== $key ) {
					$post_types = array( 'post', 'kb_artist', 'kb_member', 'kb_comeback', 'kb_chart', 'kb_thread', 'kb_community', 'kb_poll' );
					$value = $counts['content'][ $post_types[ $key ] ];
				} elseif ( $label === 'Users' ) {
					$value = $counts['users'];
				} elseif ( $label === 'Comments' ) {
					$value = $counts['pendingComments'];
				} else {
					$value = $counts['confirmedSubscribers'];
				}
				?>
				<a class="kb-card" href="<?php echo esc_url( $url ); ?>"><span><?php echo esc_html( $label ); ?></span><strong><?php echo esc_html( number_format_i18n( $value ) ); ?></strong></a>
			<?php endforeach; ?>
		</div>
		<h2>Platform health</h2>
		<?php foreach ( kpopblog_get_health_checks() as $check ) : ?>
			<div class="kb-health">
				<span class="kb-status kb-status-<?php echo esc_attr( $check['status'] ); ?>"><?php echo esc_html( ucfirst( $check['status'] ) ); ?></span>
				<strong><?php echo esc_html( $check['label'] ); ?></strong>
				<p><?php echo esc_html( $check['message'] ); ?></p>
				<?php if ( $check['actionUrl'] ) : ?><a href="<?php echo esc_url( $check['actionUrl'] ); ?>">Open setting</a><?php endif; ?>
			</div>
		<?php endforeach; ?>
		<p><a class="button button-secondary" href="<?php echo esc_url( home_url( '/' ) ); ?>">View public site</a></p>
	</div>
	<?php
}

function kpopblog_register_admin_rest_routes() {
	register_rest_route( KPOPBLOG_REST_NS, '/admin/health', array(
		'methods'             => 'GET',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
		'callback'            => function () {
			global $wp_version;
			$response = rest_ensure_response( array(
				'pluginVersion'    => KPOPBLOG_VERSION,
				'schemaVersion'    => KPOPBLOG_SCHEMA_VERSION,
				'wordpressVersion' => $wp_version,
				'phpVersion'       => PHP_VERSION,
				'checks'           => kpopblog_get_health_checks(),
				'counts'           => kpopblog_get_admin_counts(),
			) );
			$response->header( 'Cache-Control', 'no-store' );
			return $response;
		},
	) );
}
add_action( 'rest_api_init', 'kpopblog_register_admin_rest_routes' );
