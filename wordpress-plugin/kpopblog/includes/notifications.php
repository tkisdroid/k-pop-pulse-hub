<?php
/**
 * Durable user subscriptions, notification inboxes, broadcast jobs, and webhooks.
 *
 * @package KpopBlog
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const KPOPBLOG_WEBHOOK_OPTION = 'kpopblog_webhook';
const KPOPBLOG_NOTIFICATION_BATCH_SIZE = 100;

function kpopblog_webhook_defaults() {
	return array( 'url' => '', 'secret' => '', 'enabled' => 0 );
}

function kpopblog_get_webhook_settings() {
	$settings = get_option( KPOPBLOG_WEBHOOK_OPTION, array() );
	return array_merge( kpopblog_webhook_defaults(), is_array( $settings ) ? $settings : array() );
}

function kpopblog_register_webhook_settings() {
	register_setting( 'kpopblog_webhook_group', KPOPBLOG_WEBHOOK_OPTION, array(
		'type'              => 'array',
		'sanitize_callback' => function ( $input ) {
			$input = is_array( $input ) ? $input : array();
			return array(
				'url'     => isset( $input['url'] ) ? esc_url_raw( trim( (string) $input['url'] ) ) : '',
				'secret'  => isset( $input['secret'] ) ? sanitize_text_field( (string) $input['secret'] ) : '',
				'enabled' => ! empty( $input['enabled'] ) ? 1 : 0,
			);
		},
	) );
}
add_action( 'admin_init', 'kpopblog_register_webhook_settings' );

function kpopblog_dispatch_webhook( array $event ) {
	$settings = kpopblog_get_webhook_settings();
	if ( empty( $settings['enabled'] ) || empty( $settings['url'] ) ) { return; }

	$body = wp_json_encode( $event );
	$signature = $settings['secret'] ? 'sha256=' . hash_hmac( 'sha256', $body, $settings['secret'] ) : '';
	wp_remote_post( $settings['url'], array(
		'headers'  => array_filter( array(
			'Content-Type'    => 'application/json',
			'X-KB-Signature'  => $signature,
			'X-KB-Event-Kind' => $event['kind'],
		) ),
		'body'     => $body,
		'timeout'  => 4,
		'blocking' => false,
	) );
}

function kpopblog_notification_payload( array $payload ) {
	return array(
		'title' => isset( $payload['title'] ) ? substr( sanitize_text_field( (string) $payload['title'] ), 0, 255 ) : '',
		'body'  => isset( $payload['body'] ) ? wp_strip_all_tags( (string) $payload['body'] ) : '',
		'href'  => isset( $payload['href'] ) ? substr( esc_url_raw( (string) $payload['href'] ), 0, 500 ) : '',
		'image' => isset( $payload['image'] ) ? substr( esc_url_raw( (string) $payload['image'] ), 0, 500 ) : '',
	);
}

function kpopblog_map_notification_row( $row ) {
	return array(
		'id'        => (string) $row->id,
		'kind'      => sanitize_key( $row->kind ),
		'title'     => (string) $row->title,
		'body'      => (string) $row->body,
		'href'      => (string) $row->href,
		'image'     => (string) $row->image,
		'read'      => ! empty( $row->read_at ),
		'createdAt' => gmdate( 'c', strtotime( $row->created_at . ' UTC' ) ),
	);
}

function kpopblog_create_notification( $user_id, $kind, array $payload, $job_id = 0 ) {
	global $wpdb;

	$user_id = (int) $user_id;
	if ( $user_id < 1 || ! get_userdata( $user_id ) ) { return 0; }

	$payload = kpopblog_notification_payload( $payload );
	if ( '' === $payload['title'] ) { return 0; }

	$job_id = max( 0, (int) $job_id );
	$delivery_key = $job_id ? hash( 'sha256', $job_id . ':' . $user_id ) : null;
	$result = $wpdb->insert(
		$wpdb->prefix . 'kb_notifications',
		array(
			'user_id'      => $user_id,
			'job_id'       => $job_id,
			'delivery_key' => $delivery_key,
			'kind'         => substr( sanitize_key( $kind ), 0, 32 ) ?: 'system',
			'title'        => $payload['title'],
			'body'         => $payload['body'],
			'href'         => $payload['href'],
			'image'        => $payload['image'],
			'created_at'   => current_time( 'mysql', true ),
		),
		array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);

	if ( 1 === $result ) {
		return (int) $wpdb->insert_id;
	}
	if ( $delivery_key ) {
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}kb_notifications WHERE delivery_key = %s",
			$delivery_key
		) );
	}
	return 0;
}

function kpopblog_queue_notification_job( $kind, array $payload, $audience_type = 'all', $audience_key = '' ) {
	global $wpdb;

	$payload = kpopblog_notification_payload( $payload );
	$allowed_audiences = array( 'all', 'topic', 'artist' );
	$audience_type = in_array( $audience_type, $allowed_audiences, true ) ? $audience_type : 'all';
	$audience_key = substr( sanitize_key( (string) $audience_key ), 0, 191 );
	if ( '' === $payload['title'] || ( 'all' !== $audience_type && '' === $audience_key ) ) { return 0; }

	$result = $wpdb->insert(
		$wpdb->prefix . 'kb_notification_jobs',
		array(
			'kind'          => substr( sanitize_key( $kind ), 0, 32 ) ?: 'system',
			'title'         => $payload['title'],
			'body'          => $payload['body'],
			'href'          => $payload['href'],
			'image'         => $payload['image'],
			'audience_type' => $audience_type,
			'audience_key'  => $audience_key,
			'status'        => 'pending',
			'created_by'    => get_current_user_id(),
			'created_at'    => current_time( 'mysql', true ),
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
	);
	if ( 1 !== $result ) { return 0; }

	$job_id = (int) $wpdb->insert_id;
	if ( ! wp_next_scheduled( 'kpopblog_process_notification_jobs' ) ) {
		wp_schedule_single_event( time() + 5, 'kpopblog_process_notification_jobs' );
	}
	kpopblog_dispatch_webhook( array(
		'id'           => 'job-' . $job_id,
		'kind'         => sanitize_key( $kind ),
		'payload'      => $payload,
		'audienceType' => $audience_type,
		'audienceKey'  => $audience_key,
		'createdAt'    => gmdate( 'c' ),
	) );
	return $job_id;
}

function kpopblog_user_matches_notification_audience( $user_id, $type, $key ) {
	if ( 'all' === $type ) { return true; }
	if ( 'topic' === $type ) {
		$topics = array_map( 'sanitize_key', (array) get_user_meta( $user_id, 'kb_subscriptions', true ) );
		return in_array( sanitize_key( $key ), $topics, true );
	}
	if ( 'artist' === $type ) {
		$artists = array_map( 'intval', (array) get_user_meta( $user_id, 'kb_followed_artists', true ) );
		return in_array( (int) $key, $artists, true );
	}
	return false;
}

function kpopblog_process_notification_jobs() {
	global $wpdb;

	if ( get_transient( 'kpopblog_notification_job_lock' ) ) { return; }
	set_transient( 'kpopblog_notification_job_lock', 1, 2 * MINUTE_IN_SECONDS );

	try {
		$jobs = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}kb_notification_jobs WHERE status IN ('pending','processing') ORDER BY id ASC LIMIT 5"
		);
		foreach ( $jobs as $job ) {
			$wpdb->update(
				$wpdb->prefix . 'kb_notification_jobs',
				array( 'status' => 'processing', 'error_text' => null ),
				array( 'id' => (int) $job->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			$user_ids = get_users( array(
				'fields'  => 'ID',
				'number'  => KPOPBLOG_NOTIFICATION_BATCH_SIZE,
				'offset'  => (int) $job->processed_offset,
				'orderby' => 'ID',
				'order'   => 'ASC',
			) );
			$payload = array( 'title' => $job->title, 'body' => $job->body, 'href' => $job->href, 'image' => $job->image );
			foreach ( $user_ids as $user_id ) {
				if ( kpopblog_user_matches_notification_audience( $user_id, $job->audience_type, $job->audience_key ) ) {
					kpopblog_create_notification( $user_id, $job->kind, $payload, (int) $job->id );
				}
			}

			$next_offset = (int) $job->processed_offset + count( $user_ids );
			$complete = count( $user_ids ) < KPOPBLOG_NOTIFICATION_BATCH_SIZE;
			$wpdb->update(
				$wpdb->prefix . 'kb_notification_jobs',
				array(
					'processed_offset' => $next_offset,
					'status'           => $complete ? 'completed' : 'pending',
					'completed_at'     => $complete ? current_time( 'mysql', true ) : null,
				),
				array( 'id' => (int) $job->id ),
				array( '%d', '%s', '%s' ),
				array( '%d' )
			);
		}
	} finally {
		delete_transient( 'kpopblog_notification_job_lock' );
	}

	$pending = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->prefix}kb_notification_jobs WHERE status IN ('pending','processing')"
	);
	if ( $pending && ! wp_next_scheduled( 'kpopblog_process_notification_jobs' ) ) {
		wp_schedule_single_event( time() + 10, 'kpopblog_process_notification_jobs' );
	}
}
add_action( 'kpopblog_process_notification_jobs', 'kpopblog_process_notification_jobs' );

add_action( 'init', function () {
	global $wpdb;
	if ( wp_next_scheduled( 'kpopblog_process_notification_jobs' ) ) { return; }
	$pending = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->prefix}kb_notification_jobs WHERE status IN ('pending','processing')"
	);
	if ( $pending ) {
		wp_schedule_single_event( time() + 10, 'kpopblog_process_notification_jobs' );
	}
}, 20 );

function kpopblog_notification_topics() {
	if ( function_exists( 'kpopblog_newsletter_settings' ) ) {
		$settings = kpopblog_newsletter_settings();
		return array_values( array_unique( array_map( 'sanitize_key', (array) $settings['available_topics'] ) ) );
	}
	return array( 'comebacks', 'charts', 'tours', 'member-updates', 'editorial' );
}

function kpopblog_subscription_state( $user_id ) {
	$topics = array_values( array_intersect(
		kpopblog_notification_topics(),
		array_map( 'sanitize_key', (array) get_user_meta( $user_id, 'kb_subscriptions', true ) )
	) );
	$artists = array_values( array_filter( array_map( 'intval', (array) get_user_meta( $user_id, 'kb_followed_artists', true ) ) ) );
	return array( 'topics' => $topics, 'artists' => $artists );
}

function kpopblog_update_subscription( $user_id, $type, $key, $subscribed ) {
	if ( 'topic' === $type ) {
		$key = sanitize_key( $key );
		if ( ! in_array( $key, kpopblog_notification_topics(), true ) ) {
			return new WP_Error( 'invalid_topic', 'Unknown subscription topic.', array( 'status' => 400 ) );
		}
		$items = array_map( 'sanitize_key', (array) get_user_meta( $user_id, 'kb_subscriptions', true ) );
		$items = array_values( array_unique( array_filter( $items ) ) );
		$items = $subscribed ? array_values( array_unique( array_merge( $items, array( $key ) ) ) ) : array_values( array_diff( $items, array( $key ) ) );
		update_user_meta( $user_id, 'kb_subscriptions', $items );
	} elseif ( 'artist' === $type ) {
		$artist = get_page_by_path( sanitize_title( $key ), OBJECT, 'kb_artist' );
		if ( ! $artist || 'publish' !== $artist->post_status ) {
			return new WP_Error( 'invalid_artist', 'Artist not found.', array( 'status' => 404 ) );
		}
		$artist_id = (int) $artist->ID;
		$items = array_values( array_unique( array_filter( array_map( 'intval', (array) get_user_meta( $user_id, 'kb_followed_artists', true ) ) ) ) );
		$was_subscribed = in_array( $artist_id, $items, true );
		$items = $subscribed ? array_values( array_unique( array_merge( $items, array( $artist_id ) ) ) ) : array_values( array_diff( $items, array( $artist_id ) ) );
		update_user_meta( $user_id, 'kb_followed_artists', $items );
		if ( $was_subscribed !== (bool) $subscribed ) {
			$count = max( 0, (int) get_post_meta( $artist_id, 'kb_follower_count', true ) + ( $subscribed ? 1 : -1 ) );
			update_post_meta( $artist_id, 'kb_follower_count', $count );
			do_action( 'kb_follow_toggled', $artist_id, $user_id, (bool) $subscribed );
		}
	} else {
		return new WP_Error( 'invalid_type', 'Subscription type must be topic or artist.', array( 'status' => 400 ) );
	}
	return kpopblog_subscription_state( $user_id );
}

function kpopblog_register_notification_routes() {
	register_rest_route( KPOPBLOG_REST_NS, '/subscriptions', array(
		array(
			'methods'             => 'GET',
			'permission_callback' => 'kpopblog_require_login',
			'callback'            => function () { return rest_ensure_response( kpopblog_subscription_state( get_current_user_id() ) ); },
		),
		array(
			'methods'             => 'POST',
			'permission_callback' => 'kpopblog_require_login',
			'args'                => array(
				'type'       => array( 'type' => 'string', 'required' => true ),
				'key'        => array( 'type' => 'string', 'required' => true ),
				'subscribed' => array( 'type' => 'boolean', 'required' => true ),
			),
			'callback'            => function ( WP_REST_Request $request ) {
				$result = kpopblog_update_subscription(
					get_current_user_id(),
					sanitize_key( (string) $request->get_param( 'type' ) ),
					(string) $request->get_param( 'key' ),
					(bool) $request->get_param( 'subscribed' )
				);
				return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
			},
		),
	) );

	register_rest_route( KPOPBLOG_REST_NS, '/notifications', array(
		'methods'             => 'GET',
		'permission_callback' => 'kpopblog_require_login',
		'args'                => array(
			'page'    => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
			'perPage' => array( 'type' => 'integer', 'default' => 30, 'minimum' => 1, 'maximum' => 100 ),
		),
		'callback'            => function ( WP_REST_Request $request ) {
			global $wpdb;
			$user_id = get_current_user_id();
			$page = max( 1, (int) $request->get_param( 'page' ) );
			$per_page = max( 1, min( 100, (int) $request->get_param( 'perPage' ) ) );
			$offset = ( $page - 1 ) * $per_page;
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}kb_notifications WHERE user_id = %d ORDER BY id DESC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			) );
			$total = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}kb_notifications WHERE user_id = %d",
				$user_id
			) );
			$unread = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}kb_notifications WHERE user_id = %d AND read_at IS NULL",
				$user_id
			) );
			return rest_ensure_response( array(
				'items'       => array_map( 'kpopblog_map_notification_row', $rows ),
				'total'       => $total,
				'unreadCount' => $unread,
				'page'        => $page,
				'perPage'     => $per_page,
			) );
		},
	) );

	register_rest_route( KPOPBLOG_REST_NS, '/notifications/(?P<id>\d+)/read', array(
		'methods'             => 'POST',
		'permission_callback' => 'kpopblog_require_login',
		'callback'            => function ( WP_REST_Request $request ) {
			global $wpdb;
			$id = (int) $request->get_param( 'id' );
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}kb_notifications WHERE id = %d AND user_id = %d",
				$id,
				get_current_user_id()
			) );
			if ( ! $row ) { return new WP_Error( 'not_found', 'Notification not found.', array( 'status' => 404 ) ); }
			if ( empty( $row->read_at ) ) {
				$wpdb->update(
					$wpdb->prefix . 'kb_notifications',
					array( 'read_at' => current_time( 'mysql', true ) ),
					array( 'id' => $id, 'user_id' => get_current_user_id() ),
					array( '%s' ),
					array( '%d', '%d' )
				);
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}kb_notifications WHERE id = %d", $id ) );
			}
			return rest_ensure_response( array( 'item' => kpopblog_map_notification_row( $row ) ) );
		},
	) );

	register_rest_route( KPOPBLOG_REST_NS, '/notifications/read-all', array(
		'methods'             => 'POST',
		'permission_callback' => 'kpopblog_require_login',
		'callback'            => function () {
			global $wpdb;
			$updated = $wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->prefix}kb_notifications SET read_at = %s WHERE user_id = %d AND read_at IS NULL",
				current_time( 'mysql', true ),
				get_current_user_id()
			) );
			return rest_ensure_response( array( 'updated' => max( 0, (int) $updated ) ) );
		},
	) );

	register_rest_route( KPOPBLOG_REST_NS, '/notifications/broadcast', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return current_user_can( 'kb_manage_notifications' ); },
		'args'                => array(
			'title' => array( 'type' => 'string', 'required' => true ),
			'body'  => array( 'type' => 'string' ),
			'href'  => array( 'type' => 'string' ),
			'image' => array( 'type' => 'string' ),
		),
		'callback'            => function ( WP_REST_Request $request ) {
			$job_id = kpopblog_queue_notification_job( 'system', array(
				'title' => $request->get_param( 'title' ),
				'body'  => $request->get_param( 'body' ),
				'href'  => $request->get_param( 'href' ),
				'image' => $request->get_param( 'image' ),
			) );
			if ( ! $job_id ) { return new WP_Error( 'queue_failed', 'Could not queue broadcast.', array( 'status' => 500 ) ); }
			kpopblog_audit( 'notification_broadcast_queued', 'notification_job', $job_id );
			$response = rest_ensure_response( array( 'jobId' => (string) $job_id, 'status' => 'pending' ) );
			$response->set_status( 202 );
			return $response;
		},
	) );

	register_rest_route( KPOPBLOG_REST_NS, '/events', array(
		'methods'             => 'GET',
		'permission_callback' => 'kpopblog_require_login',
		'args'                => array( 'since' => array( 'type' => 'integer', 'default' => 0 ), 'limit' => array( 'type' => 'integer', 'default' => 50 ) ),
		'callback'            => function ( WP_REST_Request $request ) {
			global $wpdb;
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}kb_notifications WHERE user_id = %d AND id > %d ORDER BY id ASC LIMIT %d",
				get_current_user_id(),
				max( 0, (int) $request->get_param( 'since' ) ),
				max( 1, min( 100, (int) $request->get_param( 'limit' ) ) )
			) );
			$events = array();
			foreach ( $rows as $row ) {
				$item = kpopblog_map_notification_row( $row );
				$events[] = array( 'id' => (int) $row->id, 'kind' => $item['kind'], 'payload' => array( 'title' => $item['title'], 'body' => $item['body'], 'href' => $item['href'], 'image' => $item['image'] ), 'createdAt' => $item['createdAt'] );
			}
			$cursor = $events ? (int) end( $events )['id'] : max( 0, (int) $request->get_param( 'since' ) );
			return rest_ensure_response( array( 'events' => $events, 'cursor' => $cursor ) );
		},
	) );
}
add_action( 'rest_api_init', 'kpopblog_register_notification_routes' );

add_action( 'comment_post', function ( $comment_id, $approved ) {
	if ( 1 !== (int) $approved ) { return; }
	$comment = get_comment( $comment_id );
	if ( ! $comment ) { return; }
	$post = get_post( $comment->comment_post_ID );
	if ( ! $post || (int) $post->post_author === (int) $comment->user_id ) { return; }
	$href_prefixes = array(
		'post'      => '/news/',
		'kb_thread' => '/thread/',
		'kb_video'  => '/watch/',
	);
	if ( ! isset( $href_prefixes[ $post->post_type ] ) ) { return; }
	$href = $href_prefixes[ $post->post_type ] . $post->post_name;
	$notification_id = kpopblog_create_notification( (int) $post->post_author, 'reply', array(
		'title' => sprintf( '%s replied to "%s"', $comment->comment_author, get_the_title( $post ) ),
		'body'  => wp_trim_words( wp_strip_all_tags( $comment->comment_content ), 24 ),
		'href'  => $href,
	) );
	if ( $notification_id ) {
		kpopblog_dispatch_webhook( array( 'id' => $notification_id, 'kind' => 'reply', 'targetUserId' => (int) $post->post_author, 'createdAt' => gmdate( 'c' ) ) );
	}
}, 10, 2 );

add_action( 'kb_follow_toggled', function ( $artist_post_id, $user_id, $now_following ) {
	$artist = get_post( $artist_post_id );
	if ( ! $artist ) { return; }
	kpopblog_create_notification( (int) $user_id, 'follow', array(
		'title' => $now_following ? sprintf( 'You are now following %s', $artist->post_title ) : sprintf( 'You unfollowed %s', $artist->post_title ),
		'href'  => '/artist/' . $artist->post_name,
	) );
}, 10, 3 );

add_action( 'transition_post_status', function ( $new_status, $old_status, $post ) {
	if ( 'publish' !== $new_status || 'publish' === $old_status || 'post' !== $post->post_type ) { return; }
	kpopblog_queue_notification_job( 'article', array(
		'title' => 'New article: ' . get_the_title( $post ),
		'body'  => wp_trim_words( wp_strip_all_tags( $post->post_content ), 24 ),
		'href'  => '/news/' . $post->post_name,
		'image' => kpopblog_thumb_url( $post->ID ),
	), 'topic', 'editorial' );
}, 10, 3 );

add_action( 'save_post_kb_comeback', function ( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'publish' !== $post->post_status ) { return; }
	$release = (string) get_post_meta( $post_id, 'kb_release_at', true );
	kpopblog_queue_notification_job( 'comeback', array(
		'title' => sprintf( 'Comeback: %s', get_the_title( $post ) ),
		'body'  => $release ? 'Releases ' . $release : '',
		'href'  => '/comebacks',
		'image' => kpopblog_thumb_url( $post_id ),
	), 'topic', 'comebacks' );
}, 10, 2 );
