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

function kpopblog_rest_comment_error( WP_Error $error ) {
	$data = $error->get_error_data();
	if ( is_int( $data ) ) {
		$data = array( 'status' => $data );
	} elseif ( ! is_array( $data ) || empty( $data['status'] ) ) {
		$data = array( 'status' => 500 );
	}
	return new WP_Error( $error->get_error_code(), $error->get_error_message(), $data );
}

function kpopblog_register_write_routes() {
	/* ---------- comments ---------- */
	register_rest_route( KPOPBLOG_REST_NS, '/articles/(?P<slug>[a-zA-Z0-9_-]+)/comments', array(
		'methods'             => 'POST',
		'permission_callback' => 'kpopblog_require_login',
		'args'                => array(
			'body'     => array( 'type' => 'string', 'required' => true ),
			'parentId' => array( 'type' => 'integer', 'minimum' => 0 ),
		),
		'callback'            => function ( WP_REST_Request $r ) {
			$post_id = kpopblog_post_id_by_slug( 'post', $r->get_param( 'slug' ) );
			if ( ! $post_id ) return new WP_Error( 'not_found', 'Article not found', array( 'status' => 404 ) );
			$body = trim( (string) $r->get_param( 'body' ) );
			if ( $body === '' || strlen( $body ) > 4000 ) {
				return new WP_Error( 'bad_input', 'Comment must be 1–4000 chars', array( 'status' => 400 ) );
			}
			$user      = wp_get_current_user();
			$parent_id = max( 0, (int) $r->get_param( 'parentId' ) );
			if ( $parent_id ) {
				$parent = get_comment( $parent_id );
				if ( ! $parent || (int) $parent->comment_post_ID !== $post_id || '1' !== (string) $parent->comment_approved ) {
					return new WP_Error( 'bad_parent', 'Reply target was not found.', array( 'status' => 400 ) );
				}
			}
			$comment = wp_new_comment( array(
				'comment_post_ID'      => $post_id,
				'comment_parent'       => $parent_id,
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
				'comment_author_url'   => '',
				'comment_content'      => wp_kses_post( $body ),
				'user_id'              => $user->ID,
			), true );
			if ( is_wp_error( $comment ) ) {
				return kpopblog_rest_comment_error( $comment );
			}
			if ( ! $comment ) {
				return new WP_Error( 'insert_failed', 'Failed to save comment', array( 'status' => 500 ) );
			}
			$comment_object = get_comment( $comment );
			return rest_ensure_response( array(
				'id'      => (string) $comment,
				'item'    => function_exists( 'kpopblog_map_community_comment' ) ? kpopblog_map_community_comment( $comment_object ) : null,
				'pending' => '1' !== (string) $comment_object->comment_approved,
			) );
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
			$state = kpopblog_subscription_state( get_current_user_id() );
			$following = in_array( $post_id, $state['artists'], true );
			$result = kpopblog_update_subscription( get_current_user_id(), 'artist', $r->get_param( 'slug' ), ! $following );
			if ( is_wp_error( $result ) ) { return $result; }
			return rest_ensure_response( array(
				'following'     => ! $following,
				'followerCount' => (int) get_post_meta( $post_id, 'kb_follower_count', true ),
			) );
		},
	) );

	register_rest_route( KPOPBLOG_REST_NS, '/videos/(?P<slug>[a-zA-Z0-9_-]+)/comments', array(
		'methods'             => 'POST',
		'permission_callback' => 'kpopblog_require_login',
		'args'                => array( 'body' => array( 'type' => 'string', 'required' => true ) ),
		'callback'            => function ( WP_REST_Request $request ) {
			$post_id = kpopblog_post_id_by_slug( 'kb_video', $request->get_param( 'slug' ) );
			if ( ! $post_id ) { return new WP_Error( 'video_not_found', 'Video not found.', array( 'status' => 404 ) ); }
			$body = trim( (string) $request->get_param( 'body' ) );
			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $body ) : strlen( $body );
			if ( $length < 1 || $length > 1000 ) {
				return new WP_Error( 'bad_input', 'Comment must be 1-1000 characters.', array( 'status' => 400 ) );
			}
			$user = wp_get_current_user();
			$comment_id = wp_new_comment( array(
				'comment_post_ID'      => $post_id,
				'comment_content'      => wp_kses_post( $body ),
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
				'comment_author_url'   => '',
				'user_id'              => $user->ID,
				'comment_parent'       => 0,
			), true );
			if ( is_wp_error( $comment_id ) ) { return kpopblog_rest_comment_error( $comment_id ); }
			$comment = get_comment( $comment_id );
			return rest_ensure_response( array(
				'item'    => function_exists( 'kpopblog_map_community_comment' ) ? kpopblog_map_community_comment( $comment ) : array( 'id' => (string) $comment_id ),
				'pending' => '1' !== (string) $comment->comment_approved,
			) );
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
