<?php
/**
 * Native WordPress authentication for the React SPA — cookie session + REST
 * nonce (see shortcode.php: window.kpopblogConfig.nonce). Endpoints:
 *   POST /auth/register        { email, username, displayName, password }
 *   POST /auth/login           { login, password }   // login = email or username
 *   POST /auth/logout          {}
 *   GET  /auth/me
 *   POST /auth/forgot-password { email }
 *
 * Every response that changes login state returns a fresh nonce because a
 * wp_rest nonce is bound to the acting user ID — the nonce issued while
 * logged out stops validating the moment the session changes.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function kpopblog_map_user( WP_User $u ) {
	return array(
		'id'              => (string) $u->ID,
		'username'        => $u->user_login,
		'displayName'     => $u->display_name,
		'email'           => $u->user_email,
		'avatar'          => get_avatar_url( $u->ID ),
		'bio'             => (string) get_user_meta( $u->ID, 'kb_bio', true ),
		'country'         => (string) get_user_meta( $u->ID, 'kb_country', true ),
		'language'        => (string) get_user_meta( $u->ID, 'kb_language', true ),
		'role'            => (string) ( get_user_meta( $u->ID, 'kb_role', true ) ?: 'member' ),
		'trustLevel'      => (int) ( get_user_meta( $u->ID, 'kb_trust_level', true ) ?: 1 ),
		'points'          => (int) get_user_meta( $u->ID, 'kb_points', true ),
		'badges'          => array_values( (array) get_user_meta( $u->ID, 'kb_badges', true ) ),
		'followedArtists' => array_map( 'strval', (array) get_user_meta( $u->ID, 'kb_followed_artists', true ) ),
		'createdAt'       => mysql_to_rfc3339( $u->user_registered ),
	);
}

function kpopblog_auth_response( $extra = array() ) {
	return array_merge( array( 'nonce' => wp_create_nonce( 'wp_rest' ) ), $extra );
}

function kpopblog_register_auth_routes() {

	register_rest_route( KPOPBLOG_REST_NS, '/auth/register', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'args'                => array(
			'email'       => array( 'type' => 'string', 'required' => true ),
			'username'    => array( 'type' => 'string', 'required' => true ),
			'displayName' => array( 'type' => 'string', 'required' => true ),
			'password'    => array( 'type' => 'string', 'required' => true ),
		),
		'callback'            => function ( WP_REST_Request $r ) {
			if ( is_user_logged_in() ) {
				return new WP_Error( 'already_logged_in', 'You are already logged in', array( 'status' => 400 ) );
			}

			$email        = sanitize_email( (string) $r->get_param( 'email' ) );
			$username     = sanitize_user( (string) $r->get_param( 'username' ), true );
			$display_name = sanitize_text_field( (string) $r->get_param( 'displayName' ) );
			$password     = (string) $r->get_param( 'password' );

			if ( ! is_email( $email ) ) {
				return new WP_Error( 'invalid_email', 'Enter a valid email address', array( 'status' => 400 ) );
			}
			if ( strlen( $username ) < 3 ) {
				return new WP_Error( 'invalid_username', 'Username must be at least 3 characters', array( 'status' => 400 ) );
			}
			if ( strlen( $password ) < 8 ) {
				return new WP_Error( 'weak_password', 'Password must be at least 8 characters', array( 'status' => 400 ) );
			}
			if ( email_exists( $email ) ) {
				return new WP_Error( 'email_taken', 'An account with this email already exists', array( 'status' => 409 ) );
			}
			if ( username_exists( $username ) ) {
				return new WP_Error( 'username_taken', 'This username is already taken', array( 'status' => 409 ) );
			}

			$user_id = wp_create_user( $username, $password, $email );
			if ( is_wp_error( $user_id ) ) {
				return new WP_Error( 'register_failed', $user_id->get_error_message(), array( 'status' => 500 ) );
			}

			wp_update_user( array(
				'ID'           => $user_id,
				'display_name' => $display_name !== '' ? $display_name : $username,
			) );
			update_user_meta( $user_id, 'kb_role', 'member' );
			update_user_meta( $user_id, 'kb_trust_level', 1 );
			update_user_meta( $user_id, 'kb_points', 0 );
			update_user_meta( $user_id, 'kb_badges', array() );
			update_user_meta( $user_id, 'kb_followed_artists', array() );

			wp_set_current_user( $user_id );
			wp_set_auth_cookie( $user_id, true );
			do_action( 'wp_login', $username, get_userdata( $user_id ) );

			return rest_ensure_response( kpopblog_auth_response( array( 'user' => kpopblog_map_user( get_userdata( $user_id ) ) ) ) );
		},
	) );

	register_rest_route( KPOPBLOG_REST_NS, '/auth/login', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'args'                => array(
			'login'    => array( 'type' => 'string', 'required' => true ),
			'password' => array( 'type' => 'string', 'required' => true ),
		),
		'callback'            => function ( WP_REST_Request $r ) {
			$user = wp_signon( array(
				'user_login'    => (string) $r->get_param( 'login' ),
				'user_password' => (string) $r->get_param( 'password' ),
				'remember'      => true,
			), is_ssl() );

			if ( is_wp_error( $user ) ) {
				return new WP_Error( 'login_failed', 'Incorrect email/username or password', array( 'status' => 401 ) );
			}

			wp_set_current_user( $user->ID );
			return rest_ensure_response( kpopblog_auth_response( array( 'user' => kpopblog_map_user( $user ) ) ) );
		},
	) );

	register_rest_route( KPOPBLOG_REST_NS, '/auth/logout', array(
		'methods'             => 'POST',
		'permission_callback' => 'kpopblog_require_login',
		'callback'            => function () {
			wp_logout();
			return rest_ensure_response( kpopblog_auth_response( array( 'ok' => true ) ) );
		},
	) );

	register_rest_route( KPOPBLOG_REST_NS, '/auth/me', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function () {
			if ( ! is_user_logged_in() ) {
				return rest_ensure_response( array( 'user' => null ) );
			}
			return rest_ensure_response( array( 'user' => kpopblog_map_user( wp_get_current_user() ) ) );
		},
	) );

	register_rest_route( KPOPBLOG_REST_NS, '/auth/forgot-password', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'args'                => array(
			'email' => array( 'type' => 'string', 'required' => true ),
		),
		'callback'            => function ( WP_REST_Request $r ) {
			$email = sanitize_email( (string) $r->get_param( 'email' ) );
			if ( is_email( $email ) ) {
				// Pluggable core function — sends WP's native reset-password email
				// (link points at wp-login.php?action=rp). Result is intentionally
				// ignored so the response never reveals whether the account exists.
				retrieve_password( $email );
			}
			return rest_ensure_response( array(
				'ok'      => true,
				'message' => 'If an account exists for that email, a reset link has been sent.',
			) );
		},
	) );
}
add_action( 'rest_api_init', 'kpopblog_register_auth_routes' );
