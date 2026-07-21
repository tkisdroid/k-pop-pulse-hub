<?php
/**
 * Custom Post Types — every content surface visible on thekpopblog.com is editable
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
		'kb_video' => array(
			'singular' => 'Video', 'plural' => 'Videos',
			'icon' => 'dashicons-video-alt3', 'slug' => 'videos',
			'supports' => array( 'title', 'editor', 'thumbnail', 'author', 'comments' ),
		),
		'kb_thread' => array(
			'singular' => 'Forum thread', 'plural' => 'Forum threads',
			'icon' => 'dashicons-format-chat', 'slug' => 'threads',
			'supports' => array( 'title', 'editor', 'author', 'comments', 'custom-fields' ),
		),
		'kb_community' => array(
			'singular' => 'Community post', 'plural' => 'Community posts',
			'icon' => 'dashicons-format-status', 'slug' => 'community',
			'supports' => array( 'editor', 'author', 'comments', 'custom-fields' ),
			'public' => false, 'has_archive' => false, 'rewrite' => false,
		),
		'kb_submission' => array(
			'singular' => 'Submission', 'plural' => 'Submissions',
			'icon' => 'dashicons-email-alt', 'slug' => 'submissions',
			'supports' => array( 'title', 'editor', 'author' ),
			'public' => false, 'has_archive' => false, 'rewrite' => false,
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
			'public'       => isset( $c['public'] ) ? (bool) $c['public'] : true,
			'show_ui'      => true,
			'show_in_menu' => 'kpopblog-admin',
			'has_archive'  => isset( $c['has_archive'] ) ? (bool) $c['has_archive'] : true,
			'show_in_rest' => true, // Gutenberg + /wp-json/wp/v2/{type}
			'rest_base'    => $key,
			'menu_icon'    => $c['icon'],
			'supports'     => $c['supports'],
			'rewrite'      => array_key_exists( 'rewrite', $c ) ? $c['rewrite'] : array( 'slug' => $c['slug'] ),
		) );
	}

	// Taxonomies shared with native posts (articles).
	register_taxonomy( 'kb_artist_tag', array( 'post', 'kb_thread', 'kb_comeback', 'kb_video', 'kb_poll' ), array(
		'label'        => 'Related artists',
		'public'       => true,
		'show_in_rest' => true,
		'hierarchical' => false,
	) );

	register_taxonomy( 'kb_forum_category', array( 'kb_thread' ), array(
		'label'        => 'Forum categories',
		'labels'       => array(
			'name'          => 'Forum categories',
			'singular_name' => 'Forum category',
			'menu_name'     => 'Forum categories',
		),
		'public'       => false,
		'show_ui'      => true,
		'show_in_rest' => true,
		'hierarchical' => true,
		'rewrite'      => false,
	) );
}
add_action( 'init', 'kpopblog_register_cpts' );

function kpopblog_seed_forum_categories() {
	$categories = array(
		'general'   => array( 'General', 'General K-pop discussion.' ),
		'news'      => array( 'News', 'Discuss verified K-pop news and announcements.' ),
		'comebacks' => array( 'Comebacks', 'Albums, singles, teasers, and performances.' ),
		'concerts'  => array( 'Concerts', 'Tours, festivals, tickets, and live events.' ),
	);
	foreach ( $categories as $slug => $category ) {
		if ( ! term_exists( $slug, 'kb_forum_category' ) ) {
			wp_insert_term( $category[0], 'kb_forum_category', array( 'slug' => $slug, 'description' => $category[1] ) );
		}
	}
}
add_action( 'init', 'kpopblog_seed_forum_categories', 20 );

function kpopblog_ensure_published_thread_slug( $new_status, $old_status, WP_Post $post ) {
	if ( 'publish' !== $new_status || 'kb_thread' !== $post->post_type || $post->post_name !== '' ) {
		return;
	}
	global $wpdb;
	$slug = wp_unique_post_slug( sanitize_title( $post->post_title ), $post->ID, 'publish', 'kb_thread', $post->post_parent );
	if ( $slug !== '' ) {
		$wpdb->update( $wpdb->posts, array( 'post_name' => $slug ), array( 'ID' => $post->ID ), array( '%s' ), array( '%d' ) );
		clean_post_cache( $post->ID );
	}
}
add_action( 'transition_post_status', 'kpopblog_ensure_published_thread_slug', 10, 3 );
