<?php
/**
 * Bi-directional sync write endpoints — let the React front-end persist
 * comments, poll votes, follows, and engagement counters back to WordPress.
 *
 * All POST endpoints require a valid REST nonce (X-WP-Nonce header) issued
 * by wp_create_nonce('wp_rest') and surfaced to the SPA via the plugin's
 * wp_localize_script call (window.kpopblogConfig.nonce).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function kpopblog_require_login( WP_REST_Request $r ) {
	if ( ! is_user_logged_in() ) {
		return new WP_Error( 'rest_forbidden', 'Login required', array( 'status' => 401 ) );
	}
	return true;
}

function kpopblog_post_id_by_slug( $post_type, $slug ) {
	$posts = get_posts( array( 'post_type' => $post_type, 'name' => $slug, 'numberposts' => 1, 'post_status' => 'publish' ) );
	return $posts ? $posts[0]->ID : 0;
}

function kpopblog_register_write_routes() {
	/* ---------- comments ---------- */
	register_rest_route( KPOPBLOG_REST_NS, '/articles/(?P<slug>[a-zA-Z0-9_-]+)/comments', array(
		'methods'             => 'POST',
		'permission_callback' => 'kpopblog_require_login',
		'args'                => array(
			'body' => array( 'type' => 'string', 'required' => true ),
		),
		'callback'            => function ( WP_REST_Request $r ) {
			$post_id = kpopblog_post_id_by_slug( 'post', $r->get_param( 'slug' ) );
			if ( ! $post_id ) return new WP_Error( 'not_found', 'Article not found', array( 'status' => 404 ) );
			$body = trim( (string) $r->get_param( 'body' ) );
			if ( $body === '' || strlen( $body ) > 4000 ) {
				return new WP_Error( 'bad_input', 'Comment must be 1–4000 chars', array( 'status' => 400 ) );
			}
			$user    = wp_get_current_user();
			$comment = wp_insert_comment( array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
				'comment_content'      => wp_kses_post( $body ),
				'user_id'              => $user->ID,
				'comment_approved'     => get_option( 'comment_moderation' ) ? 0 : 1,
			) );
			if ( is_wp_error( $comment ) || ! $comment ) {
				return new WP_Error( 'insert_failed', 'Failed to save comment', array( 'status' => 500 ) );
			}
			return rest_ensure_response( array( 'id' => (string) $comment, 'pending' => ! get_option( 'comment_moderation' ) ? false : true ) );
		},
	) );

	/* ---------- poll vote ---------- */
	register_rest_route( KPOPBLOG_REST_NS, '/polls/(?P<slug>[a-zA-Z0-9_-]+)/vote', array(
		'methods'             => 'POST',
		'permission_callback' => 'kpopblog_require_login',
		'args'                => array(
			'optionId' => array( 'type' => 'string', 'required' => true ),
		),
		'callback'            => function ( WP_REST_Request $r ) {
			$post_id = kpopblog_post_id_by_slug( 'kb_poll', $r->get_param( 'slug' ) );
			if ( ! $post_id ) return new WP_Error( 'not_found', 'Poll not found', array( 'status' => 404 ) );
			$option_id = sanitize_key( (string) $r->get_param( 'optionId' ) );
			$user_id   = get_current_user_id();
			$voters    = (array) get_post_meta( $post_id, 'kb_voters', true );
			if ( in_array( $user_id, $voters, true ) ) {
				return new WP_Error( 'already_voted', 'You have already voted', array( 'status' => 409 ) );
			}
			$options = (array) get_post_meta( $post_id, 'kb_options', true );
			$found   = false;
			foreach ( $options as &$o ) {
				if ( isset( $o['id'] ) && $o['id'] === $option_id ) {
					$o['votes'] = isset( $o['votes'] ) ? (int) $o['votes'] + 1 : 1;
					$found = true;
				}
			}
			unset( $o );
			if ( ! $found ) return new WP_Error( 'bad_option', 'Unknown option', array( 'status' => 400 ) );
			update_post_meta( $post_id, 'kb_options', $options );
			$voters[] = $user_id;
			update_post_meta( $post_id, 'kb_voters', $voters );
			return rest_ensure_response( array( 'options' => $options ) );
		},
	) );

	/* ---------- follow / unfollow artist ---------- */
	register_rest_route( KPOPBLOG_REST_NS, '/artists/(?P<slug>[a-zA-Z0-9_-]+)/follow', array(
		'methods'             => 'POST',
		'permission_callback' => 'kpopblog_require_login',
		'callback'            => function ( WP_REST_Request $r ) {
			$post_id = kpopblog_post_id_by_slug( 'kb_artist', $r->get_param( 'slug' ) );
			if ( ! $post_id ) return new WP_Error( 'not_found', 'Artist not found', array( 'status' => 404 ) );
			$user_id   = get_current_user_id();
			$followers = (array) get_user_meta( $user_id, 'kb_followed_artists', true );
			$following = in_array( $post_id, $followers, true );
			if ( $following ) {
				$followers = array_values( array_diff( $followers, array( $post_id ) ) );
			} else {
				$followers[] = $post_id;
			}
			update_user_meta( $user_id, 'kb_followed_artists', $followers );
			$count = (int) get_post_meta( $post_id, 'kb_follower_count', true );
			$count = max( 0, $following ? $count - 1 : $count + 1 );
			update_post_meta( $post_id, 'kb_follower_count', $count );
			return rest_ensure_response( array( 'following' => ! $following, 'followerCount' => $count ) );
		},
	) );

	/* ---------- engagement counters (views, reactions) ---------- */
	register_rest_route( KPOPBLOG_REST_NS, '/articles/(?P<slug>[a-zA-Z0-9_-]+)/engage', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true', // anonymous reads are fine
		'args'                => array(
			'kind' => array( 'type' => 'string', 'enum' => array( 'view', 'reaction' ), 'required' => true ),
		),
		'callback'            => function ( WP_REST_Request $r ) {
			$post_id = kpopblog_post_id_by_slug( 'post', $r->get_param( 'slug' ) );
			if ( ! $post_id ) return new WP_Error( 'not_found', 'Article not found', array( 'status' => 404 ) );
			$key   = $r->get_param( 'kind' ) === 'view' ? 'kb_view_count' : 'kb_reaction_count';
			$value = (int) get_post_meta( $post_id, $key, true ) + 1;
			update_post_meta( $post_id, $key, $value );
			return rest_ensure_response( array( 'value' => $value ) );
		},
	) );
}
add_action( 'rest_api_init', 'kpopblog_register_write_routes' );
