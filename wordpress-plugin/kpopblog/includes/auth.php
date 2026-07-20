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

function kpopblog_user_capabilities() {
	return array(
		'moderateCommunity'  => current_user_can( 'kb_moderate_community' ),
		'manageAutomation'   => current_user_can( 'kb_manage_automation' ),
		'manageNotifications' => current_user_can( 'kb_manage_notifications' ),
		'manageAds'          => current_user_can( 'kb_manage_ads' ),
		'manageOptions'      => current_user_can( 'manage_options' ),
	);
}

function kpopblog_user_meta_list( $user_id, $key ) {
	$value = get_user_meta( $user_id, $key, true );
	return is_array( $value ) ? array_values( array_filter( $value, function ( $item ) { return '' !== (string) $item; } ) ) : array();
}

function kpopblog_map_public_profile( WP_User $u ) {
	return array(
		'id'              => (string) $u->ID,
		'username'        => $u->user_login,
		'displayName'     => $u->display_name,
		'avatar'          => get_avatar_url( $u->ID ),
		'bio'             => (string) get_user_meta( $u->ID, 'kb_bio', true ),
		'country'         => (string) get_user_meta( $u->ID, 'kb_country', true ),
		'language'        => (string) get_user_meta( $u->ID, 'kb_language', true ),
		'role'            => (string) ( get_user_meta( $u->ID, 'kb_role', true ) ?: 'member' ),
		'trustLevel'      => (int) ( get_user_meta( $u->ID, 'kb_trust_level', true ) ?: 1 ),
		'points'          => (int) get_user_meta( $u->ID, 'kb_points', true ),
		'badges'          => kpopblog_user_meta_list( $u->ID, 'kb_badges' ),
		'followedArtists' => array_map( 'strval', kpopblog_user_meta_list( $u->ID, 'kb_followed_artists' ) ),
		'createdAt'       => mysql_to_rfc3339( $u->user_registered ),
	);
}

function kpopblog_map_user( WP_User $u ) {
	return array_merge(
		kpopblog_map_public_profile( $u ),
		array(
			'email'        => $u->user_email,
			'capabilities' => kpopblog_user_capabilities(),
		)
	);
}

function kpopblog_rate_limit_key( $scope, $identity ) {
	$scope_hash = sanitize_key( (string) $scope );
	$identity_hash = hash_hmac( 'sha256', (string) $identity, wp_salt( 'nonce' ) );
	return 'kb_rate_' . substr( $scope_hash, 0, 24 ) . '_' . substr( $identity_hash, 0, 40 );
}

function kpopblog_check_rate_limit( $scope, $identity, $limit, $window_seconds ) {
	$limit          = max( 1, (int) $limit );
	$window_seconds = max( 1, (int) $window_seconds );
	$key            = kpopblog_rate_limit_key( $scope, $identity );
	$now            = time();
	$record         = get_transient( $key );

	if ( ! is_array( $record ) || ! isset( $record['count'], $record['reset'] ) || (int) $record['reset'] <= $now ) {
		$record = array( 'count' => 0, 'reset' => $now + $window_seconds );
	}

	if ( (int) $record['count'] >= $limit ) {
		return new WP_Error(
			'rate_limited',
			'Too many requests. Please try again later.',
			array(
				'status'     => 429,
				'retryAfter' => max( 1, (int) $record['reset'] - $now ),
			)
		);
	}

	$record['count'] = (int) $record['count'] + 1;
	set_transient( $key, $record, max( 1, (int) $record['reset'] - $now ) );
	return true;
}

function kpopblog_request_ip() {
	if ( ! isset( $_SERVER['REMOTE_ADDR'] ) || ! is_string( $_SERVER['REMOTE_ADDR'] ) ) {
		return 'unknown';
	}
	$ip = trim( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : 'unknown';
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
			if ( ! get_option( 'users_can_register' ) ) {
				return new WP_Error( 'registration_disabled', 'Account registration is currently disabled.', array( 'status' => 403 ) );
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
			$ip_limit = kpopblog_check_rate_limit( 'register_ip', kpopblog_request_ip(), 5, HOUR_IN_SECONDS );
			if ( is_wp_error( $ip_limit ) ) {
				return $ip_limit;
			}
			$identity_limit = kpopblog_check_rate_limit( 'register_identity', strtolower( $email . '|' . $username ), 3, HOUR_IN_SECONDS );
			if ( is_wp_error( $identity_limit ) ) {
				return $identity_limit;
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

	register_rest_route( KPOPBLOG_REST_NS, '/profiles/(?P<username>[^/]+)', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $r ) {
			$username = sanitize_user( rawurldecode( (string) $r->get_param( 'username' ) ), true );
			$user = $username !== '' ? get_user_by( 'login', $username ) : false;
			if ( ! $user ) {
				return new WP_Error( 'profile_not_found', 'Profile not found.', array( 'status' => 404 ) );
			}
			return rest_ensure_response( kpopblog_map_public_profile( $user ) );
		},
	) );

	register_rest_route( KPOPBLOG_REST_NS, '/profile/me', array(
		'methods'             => 'POST',
		'permission_callback' => 'kpopblog_require_login',
		'callback'            => function ( WP_REST_Request $r ) {
			$user_id      = get_current_user_id();
			$display_name = sanitize_text_field( (string) $r->get_param( 'displayName' ) );
			$bio          = sanitize_textarea_field( (string) $r->get_param( 'bio' ) );
			$country      = sanitize_text_field( (string) $r->get_param( 'country' ) );
			$language     = sanitize_key( (string) $r->get_param( 'language' ) );

			if ( strlen( $display_name ) < 2 || strlen( $display_name ) > 80 ) {
				return new WP_Error( 'invalid_display_name', 'Display name must be 2-80 characters.', array( 'status' => 400 ) );
			}
			if ( strlen( $bio ) > 500 || strlen( $country ) > 64 || strlen( $language ) > 12 ) {
				return new WP_Error( 'invalid_profile', 'One or more profile fields are too long.', array( 'status' => 400 ) );
			}

			$result = wp_update_user( array( 'ID' => $user_id, 'display_name' => $display_name ) );
			if ( is_wp_error( $result ) ) {
				return new WP_Error( 'profile_update_failed', 'Profile could not be updated.', array( 'status' => 500 ) );
			}
			update_user_meta( $user_id, 'kb_bio', $bio );
			update_user_meta( $user_id, 'kb_country', $country );
			update_user_meta( $user_id, 'kb_language', $language );
			kpopblog_audit( 'profile_updated', 'user', $user_id );

			return rest_ensure_response( kpopblog_auth_response( array( 'user' => kpopblog_map_user( get_userdata( $user_id ) ) ) ) );
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
