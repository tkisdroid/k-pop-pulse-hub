<?php
/**
 * WordPress administrator moderation queue.
 *
 * @package KpopBlog
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function kpopblog_register_moderation_admin_page() {
	add_submenu_page(
		'kpopblog-admin',
		'Community moderation',
		'Moderation',
		'kb_moderate_community',
		'kpopblog-community-moderation',
		'kpopblog_render_moderation_admin_page'
	);
}
add_action( 'admin_menu', 'kpopblog_register_moderation_admin_page', 20 );

function kpopblog_moderation_admin_rows( $limit = 100 ) {
	global $wpdb;
	$table = $wpdb->prefix . 'kb_reports';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		return array();
	}
	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = 'pending' ORDER BY created_at ASC LIMIT %d", max( 1, min( 100, (int) $limit ) ) ) );
}

function kpopblog_render_moderation_admin_page() {
	if ( ! current_user_can( 'kb_moderate_community' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'kpopblog' ) );
	}
	$rows = kpopblog_moderation_admin_rows();
	$pending_community = wp_count_posts( 'kb_community' );
	$pending_community_count = $pending_community && isset( $pending_community->pending ) ? (int) $pending_community->pending : 0;
	$pending_comments = wp_count_comments();
	$pending_comment_count = isset( $pending_comments->moderated ) ? (int) $pending_comments->moderated : 0;
	?>
	<div class="wrap">
		<h1>Community moderation</h1>
		<p>
			<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_status=pending&post_type=kb_community' ) ); ?>">Pending community posts (<?php echo esc_html( number_format_i18n( $pending_community_count ) ); ?>)</a>
			<a class="button" href="<?php echo esc_url( admin_url( 'edit-comments.php?comment_status=moderated' ) ); ?>">Pending comments and replies (<?php echo esc_html( number_format_i18n( $pending_comment_count ) ); ?>)</a>
		</p>
		<h2>Open reports (<?php echo esc_html( number_format_i18n( count( $rows ) ) ); ?>)</h2>
		<?php if ( ! $rows ) : ?>
			<p>No open reports.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr><th>Target</th><th>Reporter</th><th>Reason</th><th>Created</th><th>Action</th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->target_type . ' #' . $row->target_id ); ?></td>
						<td><a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . (int) $row->reporter_id ) ); ?>">User #<?php echo esc_html( (string) (int) $row->reporter_id ); ?></a></td>
						<td><?php echo esc_html( $row->reason ); ?></td>
						<td><?php echo esc_html( get_date_from_gmt( $row->created_at, 'Y-m-d H:i' ) ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="kpopblog_update_report" />
								<input type="hidden" name="report_id" value="<?php echo esc_attr( (string) (int) $row->id ); ?>" />
								<?php wp_nonce_field( 'kpopblog_update_report_' . (int) $row->id ); ?>
								<input type="text" name="note" maxlength="2000" placeholder="Optional note" />
								<button class="button button-primary" name="report_action" value="resolved">Resolve</button>
								<button class="button" name="report_action" value="dismissed">Dismiss</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

function kpopblog_handle_report_admin_action() {
	if ( ! current_user_can( 'kb_moderate_community' ) ) {
		wp_die( esc_html__( 'You do not have permission to perform this action.', 'kpopblog' ), '', array( 'response' => 403 ) );
	}
	$report_id = isset( $_POST['report_id'] ) ? (int) $_POST['report_id'] : 0;
	check_admin_referer( 'kpopblog_update_report_' . $report_id );
	$action = isset( $_POST['report_action'] ) && is_string( $_POST['report_action'] ) ? wp_unslash( $_POST['report_action'] ) : '';
	$note = isset( $_POST['note'] ) && is_string( $_POST['note'] ) ? wp_unslash( $_POST['note'] ) : '';
	$result = kpopblog_update_report_status( $report_id, $action, $note );
	$notice = is_wp_error( $result ) ? 'error' : 'updated';
	wp_safe_redirect( add_query_arg( 'kpopblog_notice', $notice, admin_url( 'admin.php?page=kpopblog-community-moderation' ) ) );
	exit;
}
add_action( 'admin_post_kpopblog_update_report', 'kpopblog_handle_report_admin_action' );
