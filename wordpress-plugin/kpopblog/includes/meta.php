<?php
/**
 * Meta fields — registered with show_in_rest so editors can fill them from the
 * post sidebar and the React front-end can read them from /wp-json.
 *
 * Field schemas mirror the Zod schemas in src/schemas/ai.ts so AI-generated
 * content drops in without transformation.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function kpopblog_register_meta() {
	$str = array( 'type' => 'string',  'single' => true, 'show_in_rest' => true, 'auth_callback' => '__return_true' );
	$num = array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'auth_callback' => '__return_true' );
	$arr = array( 'type' => 'array',   'single' => true, 'show_in_rest' => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ) ), 'auth_callback' => '__return_true' );

	// Articles (native posts) — extras beyond title/excerpt/content.
	register_post_meta( 'post', 'kb_subtitle',            $str );
	register_post_meta( 'post', 'kb_category_slug',       $str );
	register_post_meta( 'post', 'kb_reading_time',        $num );
	register_post_meta( 'post', 'kb_related_artist_slugs', $arr );
	register_post_meta( 'post', 'kb_language',            $str );
	register_post_meta( 'post', 'kb_source',              $str ); // editorial|wire|user

	// Artists.
	foreach ( array( 'kb_korean_name','kb_type','kb_agency','kb_debut_date','kb_fandom_name','kb_status','kb_nationality' ) as $k ) {
		register_post_meta( 'kb_artist', $k, $str );
	}
	register_post_meta( 'kb_artist', 'kb_generation', $num );
	register_post_meta( 'kb_artist', 'kb_social_links', array(
		'type' => 'object', 'single' => true, 'auth_callback' => '__return_true',
		'show_in_rest' => array( 'schema' => array(
			'type' => 'object', 'additionalProperties' => array( 'type' => 'string' ),
		) ),
	) );

	// Members.
	foreach ( array( 'kb_stage_name','kb_full_name','kb_korean_name','kb_birthday','kb_nationality','kb_group_slug','kb_mbti' ) as $k ) {
		register_post_meta( 'kb_member', $k, $str );
	}
	register_post_meta( 'kb_member', 'kb_positions', $arr );
	register_post_meta( 'kb_member', 'kb_facts',     $arr );

	// Comebacks.
	foreach ( array( 'kb_artist_slug','kb_type','kb_release_at' ) as $k ) {
		register_post_meta( 'kb_comeback', $k, $str );
	}

	// Charts.
	register_post_meta( 'kb_chart', 'kb_chart_id',        $str );
	register_post_meta( 'kb_chart', 'kb_week_start_date', $str );
	register_post_meta( 'kb_chart', 'kb_entries', array(
		'type' => 'array', 'single' => true, 'auth_callback' => '__return_true',
		'show_in_rest' => array( 'schema' => array(
			'type' => 'array', 'items' => array(
				'type' => 'object',
				'properties' => array(
					'rank'           => array( 'type' => 'integer' ),
					'artistSlug'     => array( 'type' => 'string' ),
					'trackTitle'     => array( 'type' => 'string' ),
					'previousRank'   => array( 'type' => array( 'integer', 'null' ) ),
					'weeksOnChart'   => array( 'type' => 'integer' ),
				),
			),
		) ),
	) );

	// Forum threads.
	foreach ( array( 'kb_category_slug','kb_flair','kb_language' ) as $k ) {
		register_post_meta( 'kb_thread', $k, $str );
	}
	register_post_meta( 'kb_thread', 'kb_rumor', array( 'type' => 'boolean', 'single' => true, 'show_in_rest' => true, 'auth_callback' => '__return_true' ) );
	foreach ( array( 'kb_pinned', 'kb_locked', 'kb_official' ) as $key ) {
		register_post_meta( 'kb_thread', $key, array( 'type' => 'boolean', 'single' => true, 'show_in_rest' => true, 'auth_callback' => '__return_true' ) );
	}
	register_post_meta( 'kb_thread', 'kb_related_artist_slugs', $arr );

	register_post_meta( 'kb_community', 'kb_language', $str );
	register_post_meta( 'kb_community', 'kb_artist_slug', $str );

	// Polls.
	register_post_meta( 'kb_poll', 'kb_ends_at',    $str );
	register_post_meta( 'kb_poll', 'kb_artist_slug', $str );
	register_post_meta( 'kb_poll', 'kb_options', array(
		'type' => 'array', 'single' => true, 'auth_callback' => '__return_true',
		'show_in_rest' => array( 'schema' => array(
			'type' => 'array', 'items' => array(
				'type' => 'object',
				'properties' => array(
					'id'    => array( 'type' => 'string' ),
					'label' => array( 'type' => 'string' ),
					'votes' => array( 'type' => 'integer' ),
				),
			),
		) ),
	) );
}
add_action( 'init', 'kpopblog_register_meta' );

/**
 * User profile fields — mirror the User type in src/types/index.ts so
 * kpopblog_map_user() (includes/auth.php) needs no transformation, and so
 * they're editable from the wp-admin user profile screen / REST.
 */
function kpopblog_register_user_meta() {
	$str = array( 'type' => 'string',  'single' => true, 'show_in_rest' => true, 'auth_callback' => '__return_true' );
	$num = array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'auth_callback' => '__return_true' );
	$arr = array( 'type' => 'array',   'single' => true, 'show_in_rest' => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ) ), 'auth_callback' => '__return_true' );

	register_meta( 'user', 'kb_role',            $str );
	register_meta( 'user', 'kb_bio',             $str );
	register_meta( 'user', 'kb_country',         $str );
	register_meta( 'user', 'kb_language',        $str );
	register_meta( 'user', 'kb_trust_level',     $num );
	register_meta( 'user', 'kb_points',          $num );
	register_meta( 'user', 'kb_badges',          $arr );
	register_meta( 'user', 'kb_followed_artists', $arr );
}
add_action( 'init', 'kpopblog_register_user_meta' );
