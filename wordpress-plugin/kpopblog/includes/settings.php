<?php
/**
 * KpopBlog → Comment Moderation settings.
 *
 * Lets a WP admin tune the AI comment-moderation threshold and the default
 * block reason shown to users when a comment is rejected. Values are exposed
 * to the React SPA via window.kpopblogConfig.moderation (see shortcode.php)
 * and via the public REST endpoint GET /wp-json/kpopblog/v1/moderation/settings.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const KPOPBLOG_MOD_OPTION = 'kpopblog_moderation';

function kpopblog_moderation_defaults() {
	return array(
		'threshold'      => 0.7,
		'default_reason' => 'Your comment was blocked by our automated moderation system. Please revise and try again.',
		'enabled'        => 1,
	);
}

function kpopblog_get_moderation_settings() {
	$saved = get_option( KPOPBLOG_MOD_OPTION, array() );
	if ( ! is_array( $saved ) ) { $saved = array(); }
	$out = array_merge( kpopblog_moderation_defaults(), $saved );
	$out['threshold']      = max( 0, min( 1, (float) $out['threshold'] ) );
	$out['default_reason'] = (string) $out['default_reason'];
	$out['enabled']        = ! empty( $out['enabled'] ) ? 1 : 0;
	return $out;
}

/* ---------- admin page ---------- */

function kpopblog_register_settings_page() {
	add_submenu_page(
		'options-general.php',
		'KpopBlog Moderation',
		'KpopBlog',
		'manage_options',
		'kpopblog-moderation',
		'kpopblog_render_settings_page'
	);
}
add_action( 'admin_menu', 'kpopblog_register_settings_page' );

function kpopblog_register_settings() {
	register_setting( 'kpopblog_moderation_group', KPOPBLOG_MOD_OPTION, array(
		'type'              => 'array',
		'sanitize_callback' => 'kpopblog_sanitize_moderation',
		'default'           => kpopblog_moderation_defaults(),
	) );
}
add_action( 'admin_init', 'kpopblog_register_settings' );

function kpopblog_sanitize_moderation( $input ) {
	$d = kpopblog_moderation_defaults();
	$out = array();
	$out['threshold'] = isset( $input['threshold'] )
		? max( 0, min( 1, (float) $input['threshold'] ) )
		: $d['threshold'];
	$out['default_reason'] = isset( $input['default_reason'] )
		? wp_kses_post( trim( (string) $input['default_reason'] ) )
		: $d['default_reason'];
	if ( $out['default_reason'] === '' ) { $out['default_reason'] = $d['default_reason']; }
	$out['enabled'] = ! empty( $input['enabled'] ) ? 1 : 0;
	return $out;
}

function kpopblog_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$s = kpopblog_get_moderation_settings();
	?>
	<div class="wrap">
		<h1>KpopBlog — Comment Moderation</h1>
		<p>Tune the AI comment-moderation gate used by the React front-end. Changes apply on next page load.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'kpopblog_moderation_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="kb_mod_enabled">Enable AI moderation</label></th>
					<td>
						<input type="checkbox" id="kb_mod_enabled" name="<?php echo esc_attr( KPOPBLOG_MOD_OPTION ); ?>[enabled]" value="1" <?php checked( 1, $s['enabled'] ); ?> />
						<p class="description">When off, comments bypass the AI gate (other WP moderation rules still apply).</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="kb_mod_threshold">Block threshold</label></th>
					<td>
						<input type="number" id="kb_mod_threshold" name="<?php echo esc_attr( KPOPBLOG_MOD_OPTION ); ?>[threshold]" value="<?php echo esc_attr( $s['threshold'] ); ?>" min="0" max="1" step="0.05" class="small-text" />
						<p class="description">Score between <code>0</code> (block everything flagged) and <code>1</code> (only block extreme cases). Default <code>0.7</code>.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="kb_mod_reason">Default block reason</label></th>
					<td>
						<textarea id="kb_mod_reason" name="<?php echo esc_attr( KPOPBLOG_MOD_OPTION ); ?>[default_reason]" rows="3" class="large-text"><?php echo esc_textarea( $s['default_reason'] ); ?></textarea>
						<p class="description">Shown to users when their comment is blocked and the AI did not return a specific reason.</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/* ---------- public REST endpoint ---------- */

function kpopblog_register_moderation_route() {
	register_rest_route( KPOPBLOG_REST_NS, '/moderation/settings', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function () {
			$s = kpopblog_get_moderation_settings();
			return rest_ensure_response( array(
				'enabled'       => (bool) $s['enabled'],
				'threshold'     => (float) $s['threshold'],
				'defaultReason' => (string) $s['default_reason'],
			) );
		},
	) );
}
add_action( 'rest_api_init', 'kpopblog_register_moderation_route' );
