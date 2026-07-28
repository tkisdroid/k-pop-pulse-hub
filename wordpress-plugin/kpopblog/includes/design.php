<?php
/**
 * Design & branding settings — lets an administrator control the public
 * site identity (wordmark, tagline, accent color, logo) entirely from
 * WordPress. Values are exposed to the React SPA via
 * window.kpopblogConfig.branding (see shortcode.php) and applied at boot.
 *
 * @package KpopBlog
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const KPOPBLOG_DESIGN_OPTION = 'kpopblog_design';

function kpopblog_design_defaults() {
	return array(
		'site_name'    => 'Kpop',
		'accent_word'  => 'Blog',
		'tagline'      => 'K-pop news, charts, comebacks and community.',
		'accent_color' => '',
		'logo_url'     => '',
	);
}

function kpopblog_sanitize_design_settings( $input ) {
	$input = is_array( $input ) ? $input : array();
	$d = kpopblog_design_defaults();

	$site_name = isset( $input['site_name'] ) ? sanitize_text_field( (string) $input['site_name'] ) : '';
	if ( $site_name === '' ) { $site_name = $d['site_name']; }

	$accent_word = isset( $input['accent_word'] ) ? sanitize_text_field( (string) $input['accent_word'] ) : '';

	$tagline = isset( $input['tagline'] ) ? sanitize_text_field( (string) $input['tagline'] ) : '';

	$accent_color = isset( $input['accent_color'] ) ? trim( (string) $input['accent_color'] ) : '';
	if ( $accent_color !== '' && ! preg_match( '/^#[0-9a-fA-F]{6}$/', $accent_color ) ) {
		$accent_color = '';
	}

	$logo_url = isset( $input['logo_url'] ) ? esc_url_raw( trim( (string) $input['logo_url'] ) ) : '';

	return array(
		'site_name'    => $site_name,
		'accent_word'  => $accent_word,
		'tagline'      => $tagline,
		'accent_color' => $accent_color,
		'logo_url'     => $logo_url,
	);
}

function kpopblog_get_design_settings() {
	$saved = get_option( KPOPBLOG_DESIGN_OPTION, array() );
	return kpopblog_sanitize_design_settings( is_array( $saved ) ? $saved : array() );
}

function kpopblog_design_frontend_config() {
	$s = kpopblog_get_design_settings();
	return array(
		'siteName'    => (string) $s['site_name'],
		'accentWord'  => (string) $s['accent_word'],
		'tagline'     => (string) $s['tagline'],
		'accentColor' => (string) $s['accent_color'],
		'logoUrl'     => (string) $s['logo_url'],
	);
}

function kpopblog_register_design_settings() {
	register_setting( 'kpopblog_design_group', KPOPBLOG_DESIGN_OPTION, array(
		'type'              => 'array',
		'sanitize_callback' => 'kpopblog_sanitize_design_settings',
		'default'           => kpopblog_design_defaults(),
	) );
}
add_action( 'admin_init', 'kpopblog_register_design_settings' );

function kpopblog_register_design_admin_page() {
	add_submenu_page(
		'kpopblog-admin',
		'Design',
		'Design',
		'manage_options',
		'kpopblog-design',
		'kpopblog_render_design_admin_page'
	);
}
add_action( 'admin_menu', 'kpopblog_register_design_admin_page' );

function kpopblog_design_admin_assets( $hook ) {
	if ( strpos( (string) $hook, 'kpopblog-design' ) === false ) { return; }
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	wp_add_inline_script( 'wp-color-picker', "jQuery(function($){ $('.kb-color-field').wpColorPicker(); });" );
}
add_action( 'admin_enqueue_scripts', 'kpopblog_design_admin_assets' );

function kpopblog_render_design_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$s = kpopblog_get_design_settings();
	?>
	<div class="wrap">
		<h1>Design &amp; Branding</h1>
		<p>Control the public site identity. Changes apply on the next page load of the embedded application.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'kpopblog_design_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="kb_design_site_name">Site name</label></th>
					<td><input id="kb_design_site_name" type="text" name="<?php echo esc_attr( KPOPBLOG_DESIGN_OPTION ); ?>[site_name]" value="<?php echo esc_attr( $s['site_name'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="kb_design_accent_word">Accent word</label></th>
					<td>
						<input id="kb_design_accent_word" type="text" name="<?php echo esc_attr( KPOPBLOG_DESIGN_OPTION ); ?>[accent_word]" value="<?php echo esc_attr( $s['accent_word'] ); ?>" class="regular-text">
						<p class="description">Shown in the gradient brand color right after the site name (e.g. site name "Kpop" + accent word "Blog" renders "Kpop<span style="background:linear-gradient(135deg,#ff4fa3,#8a5cff);-webkit-background-clip:text;background-clip:text;color:transparent">Blog</span>"). Leave blank for a single-color wordmark.</p>
					</td>
				</tr>
				<tr>
					<th><label for="kb_design_tagline">Tagline</label></th>
					<td><input id="kb_design_tagline" type="text" name="<?php echo esc_attr( KPOPBLOG_DESIGN_OPTION ); ?>[tagline]" value="<?php echo esc_attr( $s['tagline'] ); ?>" class="large-text"></td>
				</tr>
				<tr>
					<th><label for="kb_design_accent_color">Accent color</label></th>
					<td>
						<input id="kb_design_accent_color" type="text" name="<?php echo esc_attr( KPOPBLOG_DESIGN_OPTION ); ?>[accent_color]" value="<?php echo esc_attr( $s['accent_color'] ); ?>" class="kb-color-field" data-default-color="">
						<p class="description">Brand color used for buttons, links, focus rings and the wordmark gradient. Leave empty to keep the built-in theme color.</p>
					</td>
				</tr>
				<tr>
					<th><label for="kb_design_logo_url">Logo image URL</label></th>
					<td>
						<input id="kb_design_logo_url" type="text" name="<?php echo esc_attr( KPOPBLOG_DESIGN_OPTION ); ?>[logo_url]" value="<?php echo esc_attr( $s['logo_url'] ); ?>" class="large-text" placeholder="https://…/logo.png">
						<p class="description">Optional. Replaces the gradient logo mark in the header. Use Media → Add New to upload an image, then paste its file URL.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Save design settings' ); ?>
		</form>
	</div>
	<?php
}

add_action( 'update_option_' . KPOPBLOG_DESIGN_OPTION, function ( $old_value, $value ) {
	$s = kpopblog_sanitize_design_settings( $value );
	kpopblog_audit( 'design_settings_updated', 'settings', 0, array(
		'site_name'       => (string) $s['site_name'],
		'has_accent_color'=> $s['accent_color'] !== '',
		'has_logo'        => $s['logo_url'] !== '',
	) );
}, 10, 2 );
