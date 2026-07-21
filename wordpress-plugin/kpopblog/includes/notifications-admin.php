<?php
/**
 * WordPress administration for durable notifications and broadcasts.
 *
 * @package KpopBlog
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function kpopblog_register_notifications_admin_page() {
	add_submenu_page(
		'kpopblog-admin',
		'Notifications',
		'Notifications',
		'kb_manage_notifications',
		'kpopblog-notifications',
		'kpopblog_render_notifications_admin_page'
	);
}
add_action( 'admin_menu', 'kpopblog_register_notifications_admin_page' );

function kpopblog_render_notifications_admin_page() {
	if ( ! current_user_can( 'kb_manage_notifications' ) ) { return; }
	global $wpdb;

	$jobs = $wpdb->get_results(
		"SELECT id,title,audience_type,audience_key,processed_offset,status,created_at,completed_at
		FROM {$wpdb->prefix}kb_notification_jobs ORDER BY id DESC LIMIT 20"
	);
	$settings = kpopblog_get_webhook_settings();
	$notice = isset( $_GET['kb_notice'] ) ? sanitize_key( wp_unslash( $_GET['kb_notice'] ) ) : '';
	?>
	<div class="wrap">
		<h1>Notifications</h1>
		<?php if ( $notice ) : ?>
			<div class="notice notice-<?php echo 'queued' === $notice ? 'success' : 'error'; ?> is-dismissible"><p>
				<?php echo 'queued' === $notice ? 'Broadcast queued for delivery.' : 'The broadcast could not be queued.'; ?>
			</p></div>
		<?php endif; ?>

		<h2>Broadcast to registered users</h2>
		<p>The message is stored in each user's WordPress inbox. Delivery runs in batches through WordPress cron.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="kpopblog_broadcast_notification">
			<?php wp_nonce_field( 'kpopblog_broadcast_notification' ); ?>
			<table class="form-table" role="presentation">
				<tr><th><label for="kb_notification_title">Title</label></th><td><input id="kb_notification_title" name="title" type="text" maxlength="255" class="regular-text" required></td></tr>
				<tr><th><label for="kb_notification_body">Message</label></th><td><textarea id="kb_notification_body" name="body" rows="4" maxlength="2000" class="large-text"></textarea></td></tr>
				<tr><th><label for="kb_notification_href">Site link</label></th><td><input id="kb_notification_href" name="href" type="text" maxlength="500" class="regular-text" placeholder="/community"><p class="description">Use a site-relative path or a valid URL.</p></td></tr>
			</table>
			<?php submit_button( 'Queue broadcast' ); ?>
		</form>

		<h2>Recent delivery jobs</h2>
		<table class="widefat striped">
			<thead><tr><th>ID</th><th>Title</th><th>Audience</th><th>Status</th><th>Processed</th><th>Created</th><th>Completed</th></tr></thead>
			<tbody>
			<?php if ( ! $jobs ) : ?>
				<tr><td colspan="7">No notification jobs yet.</td></tr>
			<?php else : foreach ( $jobs as $job ) : ?>
				<tr>
					<td><?php echo (int) $job->id; ?></td>
					<td><?php echo esc_html( $job->title ); ?></td>
					<td><?php echo esc_html( $job->audience_type . ( $job->audience_key ? ': ' . $job->audience_key : '' ) ); ?></td>
					<td><?php echo esc_html( $job->status ); ?></td>
					<td><?php echo (int) $job->processed_offset; ?></td>
					<td><?php echo esc_html( $job->created_at ); ?></td>
					<td><?php echo esc_html( $job->completed_at ?: '—' ); ?></td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>

		<h2>Signed webhook</h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'kpopblog_webhook_group' ); ?>
			<table class="form-table" role="presentation">
				<tr><th><label for="kb_wh_enabled">Enable webhook</label></th><td><input type="checkbox" id="kb_wh_enabled" name="<?php echo esc_attr( KPOPBLOG_WEBHOOK_OPTION ); ?>[enabled]" value="1" <?php checked( 1, $settings['enabled'] ); ?>></td></tr>
				<tr><th><label for="kb_wh_url">Webhook URL</label></th><td><input type="url" id="kb_wh_url" name="<?php echo esc_attr( KPOPBLOG_WEBHOOK_OPTION ); ?>[url]" value="<?php echo esc_attr( $settings['url'] ); ?>" class="regular-text"></td></tr>
				<tr><th><label for="kb_wh_secret">Signing secret</label></th><td><input type="password" id="kb_wh_secret" name="<?php echo esc_attr( KPOPBLOG_WEBHOOK_OPTION ); ?>[secret]" value="<?php echo esc_attr( $settings['secret'] ); ?>" class="regular-text" autocomplete="new-password"></td></tr>
			</table>
			<?php submit_button( 'Save webhook settings' ); ?>
		</form>
	</div>
	<?php
}

function kpopblog_handle_notification_broadcast() {
	if ( ! current_user_can( 'kb_manage_notifications' ) ) {
		wp_die( esc_html__( 'You are not allowed to send notifications.', 'kpopblog' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'kpopblog_broadcast_notification' );

	$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
	$body = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';
	$href = isset( $_POST['href'] ) ? esc_url_raw( wp_unslash( $_POST['href'] ) ) : '';
	$job_id = $title ? kpopblog_queue_notification_job( 'system', array( 'title' => $title, 'body' => $body, 'href' => $href ) ) : 0;
	if ( $job_id ) {
		kpopblog_audit( 'notification_broadcast_queued', 'notification_job', $job_id );
	}
	wp_safe_redirect( add_query_arg( 'kb_notice', $job_id ? 'queued' : 'failed', admin_url( 'admin.php?page=kpopblog-notifications' ) ) );
	exit;
}
add_action( 'admin_post_kpopblog_broadcast_notification', 'kpopblog_handle_notification_broadcast' );
