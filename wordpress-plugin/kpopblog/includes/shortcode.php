<?php
/**
 * [kpopblog] shortcode — mounts the React single-page app on any WP page or post.
 * Use ?route= in the URL to deep-link, e.g. /community?route=/forum.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function kpopblog_enqueue_assets() {
	$manifest_path = KPOPBLOG_PATH . 'assets/manifest.json';
	$js  = 'assets/app.js';
	$css = 'assets/app.css';

	if ( file_exists( $manifest_path ) ) {
		$m = json_decode( file_get_contents( $manifest_path ), true );
		if ( ! empty( $m['js'] ) )  { $js  = $m['js']; }
		if ( ! empty( $m['css'] ) ) { $css = $m['css']; }
	}

	// Hashed Vite filenames provide cache invalidation. Native module chunks
	// import the entry URL without a query string, so adding ?ver= would make
	// the browser execute the same module twice when a circular chunk imports it.
	wp_register_style( 'kpopblog-app', KPOPBLOG_URL . $css, array(), null );
	wp_register_script( 'kpopblog-app', KPOPBLOG_URL . $js, array(), null, true );

	// Vite builds a native ES module (top-level `import`/`export`) — without
	// type="module" the browser parses it as a classic script and throws
	// "Cannot use import statement outside a module", so nothing mounts.
	add_filter( 'script_loader_tag', function ( $tag, $handle ) {
		if ( $handle !== 'kpopblog-app' ) { return $tag; }
		if ( strpos( $tag, 'type=' ) === false ) {
			$tag = str_replace( ' src=', ' type="module" src=', $tag );
		}
		return $tag;
	}, 10, 2 );

	$mod = function_exists( 'kpopblog_get_moderation_settings' ) ? kpopblog_get_moderation_settings() : array( 'enabled' => 1, 'threshold' => 0.7, 'default_reason' => '' );

	wp_localize_script( 'kpopblog-app', 'kpopblogConfig', array(
		'apiUrl'     => esc_url_raw( rest_url( KPOPBLOG_REST_NS ) ),
		'wpApiUrl'   => esc_url_raw( rest_url( 'wp/v2' ) ),
		'nonce'      => wp_create_nonce( 'wp_rest' ),
		'siteUrl'    => esc_url_raw( home_url( '/' ) ),
		'seoPath'    => kpopblog_request_path(),
		'seo'        => kpopblog_seo_payload( kpopblog_get_public_context() ),
		'adminUrl'   => current_user_can( 'manage_options' ) ? esc_url_raw( admin_url( 'admin.php?page=kpopblog-admin' ) ) : '',
		'registrationEnabled' => (bool) get_option( 'users_can_register' ),
		'locale'     => substr( get_locale(), 0, 2 ),
		'moderation' => array(
			'enabled'       => (bool) $mod['enabled'],
			'threshold'     => (float) $mod['threshold'],
			'defaultReason' => (string) $mod['default_reason'],
		),
		'ads'        => function_exists( 'kpopblog_ads_frontend_config' ) ? kpopblog_ads_frontend_config() : array( 'enabled' => false, 'publisherId' => '', 'slots' => (object) array() ),
		'branding'   => function_exists( 'kpopblog_design_frontend_config' ) ? kpopblog_design_frontend_config() : array( 'siteName' => 'Kpop', 'accentWord' => 'Blog', 'tagline' => '', 'accentColor' => '', 'logoUrl' => '' ),
	) );

	// Styles are printed inside wp_head() (priority 8) — by the time the
	// [kpopblog] shortcode itself runs (during the_content(), well after
	// wp_head() has already finished), it's too late to enqueue the
	// stylesheet and have it actually output. Enqueue eagerly here whenever
	// we can already tell this request will render the app, so the CSS
	// (and script, for consistent load order) make it into <head>/<body>.
	if ( kpopblog_current_page_needs_app_assets() ) {
		wp_enqueue_style( 'kpopblog-app' );
		wp_enqueue_script( 'kpopblog-app' );
	}
}
add_action( 'wp_enqueue_scripts', 'kpopblog_enqueue_assets' );

function kpopblog_current_page_needs_app_assets() {
	if ( function_exists( 'kpopblog_current_request_is_app_shell' ) && kpopblog_current_request_is_app_shell() ) {
		return true;
	}
	if ( is_singular() ) {
		$post = get_post();
		if ( $post && ( has_shortcode( $post->post_content, 'kpopblog' ) || has_block( 'kpopblog/app', $post ) ) ) {
			return true;
		}
	}
	return false;
}

function kpopblog_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'route' => '/' ), $atts, 'kpopblog' );
	wp_enqueue_style( 'kpopblog-app' );
	wp_enqueue_script( 'kpopblog-app' );
	return sprintf(
		'<div id="kpopblog-root" data-initial-route="%s">%s</div>',
		esc_attr( $atts['route'] ),
		function_exists( 'kpopblog_render_public_fallback' ) ? kpopblog_render_public_fallback() : ''
	);
}
add_shortcode( 'kpopblog', 'kpopblog_shortcode' );
