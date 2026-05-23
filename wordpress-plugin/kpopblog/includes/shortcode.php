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

	wp_register_style( 'kpopblog-app', KPOPBLOG_URL . $css, array(), KPOPBLOG_VERSION );
	wp_register_script( 'kpopblog-app', KPOPBLOG_URL . $js, array(), KPOPBLOG_VERSION, true );

	$mod = function_exists( 'kpopblog_get_moderation_settings' ) ? kpopblog_get_moderation_settings() : array( 'enabled' => 1, 'threshold' => 0.7, 'default_reason' => '' );

	wp_localize_script( 'kpopblog-app', 'kpopblogConfig', array(
		'apiUrl'     => esc_url_raw( rest_url( KPOPBLOG_REST_NS ) ),
		'wpApiUrl'   => esc_url_raw( rest_url( 'wp/v2' ) ),
		'nonce'      => wp_create_nonce( 'wp_rest' ),
		'siteUrl'    => esc_url_raw( home_url( '/' ) ),
		'locale'     => substr( get_locale(), 0, 2 ),
		'moderation' => array(
			'enabled'       => (bool) $mod['enabled'],
			'threshold'     => (float) $mod['threshold'],
			'defaultReason' => (string) $mod['default_reason'],
		),
	) );
}
add_action( 'wp_enqueue_scripts', 'kpopblog_enqueue_assets' );

function kpopblog_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'route' => '/' ), $atts, 'kpopblog' );
	wp_enqueue_style( 'kpopblog-app' );
	wp_enqueue_script( 'kpopblog-app' );
	return sprintf(
		'<div id="kpopblog-root" data-initial-route="%s"></div>',
		esc_attr( $atts['route'] )
	);
}
add_shortcode( 'kpopblog', 'kpopblog_shortcode' );
