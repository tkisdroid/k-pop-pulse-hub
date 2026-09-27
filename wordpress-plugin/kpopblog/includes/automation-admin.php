<?php
/**
 * WordPress administration for grounded content automation.
 *
 * @package KpopBlog
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function kpopblog_register_automation_admin_page() {
	add_submenu_page(
		'kpopblog-admin',
		'AI Content Automation',
		'AI Automation',
		'kb_manage_automation',
		'kpopblog-automation',
		'kpopblog_render_automation_admin_page'
	);
}
add_action( 'admin_menu', 'kpopblog_register_automation_admin_page' );

function kpopblog_register_automation_settings() {
	register_setting( 'kpopblog_automation_group', KPOPBLOG_AUTOMATION_OPTION, array(
		'type'              => 'array',
		'sanitize_callback' => 'kpopblog_sanitize_automation_settings',
		'default'           => kpopblog_automation_defaults(),
	) );
	register_setting( 'kpopblog_automation_key_group', KPOPBLOG_OPENAI_KEY_OPTION, array(
		'type'              => 'string',
		'sanitize_callback' => 'kpopblog_sanitize_openai_api_key',
		'default'           => '',
		'show_in_rest'      => false,
	) );
}
add_action( 'admin_init', 'kpopblog_register_automation_settings' );

function kpopblog_render_automation_admin_page() {
	if ( ! current_user_can( 'kb_manage_automation' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage automation.', 'kpopblog' ) );
	}
	global $wpdb;
	$settings = kpopblog_get_automation_settings();
	$runs = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}kb_automation_runs ORDER BY id DESC LIMIT 20" );
	$notice = isset( $_GET['kb_automation_notice'] ) ? sanitize_key( wp_unslash( $_GET['kb_automation_notice'] ) ) : '';
	?>
	<div class="wrap">
		<h1>AI Content Automation</h1>
		<p>Discover current K-pop news, comeback dates, and concerts with OpenAI web search; validate sources; deduplicate results; and save them as native WordPress content.</p>
		<?php if ( 'completed' === $notice ) : ?><div class="notice notice-success is-dismissible"><p>Automation run completed.</p></div><?php endif; ?>
		<?php if ( 'failed' === $notice ) : ?><div class="notice notice-error is-dismissible"><p>Automation could not complete. Review the recent run log below.</p></div><?php endif; ?>
		<table class="widefat striped" style="max-width:900px;margin:16px 0 24px">
			<tbody>
				<tr><th style="width:220px">OpenAI credential</th><td><?php echo kpopblog_has_openai_api_key() ? '<span style="color:#008a20">Configured</span> (' . esc_html( kpopblog_openai_key_source() ) . ')' : '<span style="color:#b32d2e">Missing</span>'; ?> — optional. The keyless <a href="<?php echo esc_url( admin_url( 'admin.php?page=kpopblog-collector' ) ); ?>">News Collector</a> keeps the site updated without it.</td></tr>
				<tr><th>Next scheduled run</th><td><?php $next = wp_next_scheduled( KPOPBLOG_AUTOMATION_HOOK ); echo $next ? esc_html( wp_date( 'Y-m-d H:i:s T', $next ) ) : 'Not scheduled'; ?></td></tr>
				<tr><th>Last successful run</th><td><?php echo esc_html( (string) get_option( 'kpopblog_automation_last_success', 'Never' ) ); ?></td></tr>
			</tbody>
		</table>

		<form method="post" action="options.php" style="max-width:900px;margin-bottom:24px">
			<?php settings_fields( 'kpopblog_automation_key_group' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="kb_openai_key">OpenAI API key</label></th><td><input id="kb_openai_key" class="regular-text code" type="password" autocomplete="new-password" name="<?php echo esc_attr( KPOPBLOG_OPENAI_KEY_OPTION ); ?>" value="" placeholder="<?php echo '' !== (string) get_option( KPOPBLOG_OPENAI_KEY_OPTION, '' ) ? 'Saved — leave blank to keep' : 'sk-…'; ?>" /><p class="description">Used only when <code>KPOPBLOG_OPENAI_API_KEY</code> or <code>OPENAI_API_KEY</code> is not set on the server. The key is never shown again after saving. Enter <code>-</code> to remove it.</p></td></tr>
			</table>
			<?php submit_button( 'Save API key', 'secondary' ); ?>
		</form>

		<form method="post" action="options.php">
			<?php settings_fields( 'kpopblog_automation_group' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Enable scheduled discovery</th><td><label><input type="checkbox" name="<?php echo esc_attr( KPOPBLOG_AUTOMATION_OPTION ); ?>[enabled]" value="1" <?php checked( 1, $settings['enabled'] ); ?> /> Run automatically with WP-Cron</label></td></tr>
				<tr><th scope="row">Publish verified items</th><td><label><input type="checkbox" name="<?php echo esc_attr( KPOPBLOG_AUTOMATION_OPTION ); ?>[auto_publish]" value="1" <?php checked( 1, $settings['auto_publish'] ); ?> /> Publish after schema, confidence, HTTPS source, and duplicate checks</label><p class="description">Turn this off to save future items as drafts.</p></td></tr>
				<tr><th scope="row"><label for="kb_automation_model">Model</label></th><td><select id="kb_automation_model" name="<?php echo esc_attr( KPOPBLOG_AUTOMATION_OPTION ); ?>[model]"><?php foreach ( kpopblog_automation_models() as $model ) : ?><option value="<?php echo esc_attr( $model ); ?>" <?php selected( $settings['model'], $model ); ?>><?php echo esc_html( $model ); ?></option><?php endforeach; ?></select></td></tr>
				<tr><th scope="row"><label for="kb_automation_frequency">Frequency</label></th><td><select id="kb_automation_frequency" name="<?php echo esc_attr( KPOPBLOG_AUTOMATION_OPTION ); ?>[frequency]"><?php foreach ( array( 'hourly' => 'Hourly', 'twicedaily' => 'Twice daily', 'daily' => 'Daily' ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['frequency'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
				<tr><th scope="row"><label for="kb_automation_max">Maximum items per run</label></th><td><input id="kb_automation_max" class="small-text" type="number" min="1" max="10" name="<?php echo esc_attr( KPOPBLOG_AUTOMATION_OPTION ); ?>[max_items]" value="<?php echo esc_attr( $settings['max_items'] ); ?>" /></td></tr>
				<tr><th scope="row"><label for="kb_automation_focus">Artist focus</label></th><td><textarea id="kb_automation_focus" class="large-text" rows="3" maxlength="500" name="<?php echo esc_attr( KPOPBLOG_AUTOMATION_OPTION ); ?>[artist_focus]"><?php echo esc_textarea( $settings['artist_focus'] ); ?></textarea><p class="description">Optional comma-separated artist names. General K-pop coverage remains enabled.</p></td></tr>
			</table>
			<?php submit_button( 'Save automation settings' ); ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:20px 0 30px">
			<input type="hidden" name="action" value="kpopblog_run_automation" />
			<?php wp_nonce_field( 'kpopblog_run_automation' ); ?>
			<?php submit_button( 'Run verified discovery now', 'secondary', 'submit', false, kpopblog_has_openai_api_key() ? array() : array( 'disabled' => 'disabled' ) ); ?>
		</form>

		<h2>Recent runs</h2>
		<table class="widefat striped">
			<thead><tr><th>Started</th><th>Trigger</th><th>Status</th><th>Model</th><th>Found</th><th>Created</th><th>Updated</th><th>Skipped</th><th>Error</th></tr></thead>
			<tbody><?php if ( ! $runs ) : ?><tr><td colspan="9">No automation runs yet.</td></tr><?php else : foreach ( $runs as $run ) : ?><tr>
				<td><?php echo esc_html( $run->started_at ); ?></td><td><?php echo esc_html( $run->trigger_type ); ?></td><td><?php echo esc_html( $run->status ); ?></td><td><?php echo esc_html( $run->model ); ?></td>
				<td><?php echo esc_html( (string) $run->discovered ); ?></td><td><?php echo esc_html( (string) $run->created ); ?></td><td><?php echo esc_html( (string) $run->updated ); ?></td><td><?php echo esc_html( (string) $run->skipped ); ?></td><td><?php echo esc_html( $run->error_code ); ?></td>
			</tr><?php endforeach; endif; ?></tbody>
		</table>
	</div>
	<?php
}

function kpopblog_handle_manual_automation() {
	if ( ! current_user_can( 'kb_manage_automation' ) ) { wp_die( esc_html__( 'You do not have permission to run automation.', 'kpopblog' ) ); }
	check_admin_referer( 'kpopblog_run_automation' );
	$result = kpopblog_run_automation( 'manual' );
	wp_safe_redirect( add_query_arg( 'kb_automation_notice', is_wp_error( $result ) ? 'failed' : 'completed', admin_url( 'admin.php?page=kpopblog-automation' ) ) );
	exit;
}
add_action( 'admin_post_kpopblog_run_automation', 'kpopblog_handle_manual_automation' );
