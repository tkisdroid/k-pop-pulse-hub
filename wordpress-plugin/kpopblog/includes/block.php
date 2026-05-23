<?php
/**
 * Gutenberg block wrapper around the [kpopblog] shortcode — gives editors a
 * "KpopBlog App" block to drop on any page.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function kpopblog_register_block() {
	if ( ! function_exists( 'register_block_type' ) ) { return; }
	register_block_type( 'kpopblog/app', array(
		'api_version'     => 2,
		'title'           => 'KpopBlog App',
		'category'        => 'widgets',
		'icon'            => 'star-filled',
		'render_callback' => function ( $attrs ) {
			$route = isset( $attrs['route'] ) ? $attrs['route'] : '/';
			return kpopblog_shortcode( array( 'route' => $route ) );
		},
		'attributes' => array(
			'route' => array( 'type' => 'string', 'default' => '/' ),
		),
	) );
}
add_action( 'init', 'kpopblog_register_block' );
