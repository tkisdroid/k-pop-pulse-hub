<?php
/**
 * Custom Post Types — every content surface visible on kpopblog.com is editable
 * from the WordPress admin. Articles re-use the built-in "post" type so editors
 * keep the standard authoring UX (Gutenberg, categories, tags, featured image).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function kpopblog_register_cpts() {
	$types = array(
		'kb_artist' => array(
			'singular' => 'Artist', 'plural' => 'Artists',
			'icon' => 'dashicons-star-filled', 'slug' => 'artists',
			'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		),
		'kb_member' => array(
			'singular' => 'Member', 'plural' => 'Members',
			'icon' => 'dashicons-groups', 'slug' => 'members',
			'supports' => array( 'title', 'editor', 'thumbnail' ),
		),
		'kb_comeback' => array(
			'singular' => 'Comeback', 'plural' => 'Comebacks',
			'icon' => 'dashicons-calendar-alt', 'slug' => 'comebacks',
			'supports' => array( 'title', 'editor', 'thumbnail' ),
		),
		'kb_chart' => array(
			'singular' => 'Chart', 'plural' => 'Charts',
			'icon' => 'dashicons-chart-bar', 'slug' => 'charts',
			'supports' => array( 'title', 'editor' ),
		),
		'kb_thread' => array(
			'singular' => 'Forum thread', 'plural' => 'Forum threads',
			'icon' => 'dashicons-format-chat', 'slug' => 'threads',
			'supports' => array( 'title', 'editor', 'author' ),
		),
		'kb_poll' => array(
			'singular' => 'Poll', 'plural' => 'Polls',
			'icon' => 'dashicons-chart-pie', 'slug' => 'polls',
			'supports' => array( 'title', 'editor' ),
		),
	);

	foreach ( $types as $key => $c ) {
		register_post_type( $key, array(
			'label'        => $c['plural'],
			'labels'       => array(
				'name'          => $c['plural'],
				'singular_name' => $c['singular'],
				'add_new_item'  => 'Add new ' . strtolower( $c['singular'] ),
				'edit_item'     => 'Edit ' . strtolower( $c['singular'] ),
				'menu_name'     => 'KpopBlog ' . $c['plural'],
			),
			'public'       => true,
			'has_archive'  => true,
			'show_in_rest' => true, // Gutenberg + /wp-json/wp/v2/{type}
			'rest_base'    => $key,
			'menu_icon'    => $c['icon'],
			'supports'     => $c['supports'],
			'rewrite'      => array( 'slug' => $c['slug'] ),
		) );
	}

	// Taxonomies shared with native posts (articles).
	register_taxonomy( 'kb_artist_tag', array( 'post', 'kb_thread', 'kb_comeback', 'kb_poll' ), array(
		'label'        => 'Related artists',
		'public'       => true,
		'show_in_rest' => true,
		'hierarchical' => false,
	) );
}
add_action( 'init', 'kpopblog_register_cpts' );
