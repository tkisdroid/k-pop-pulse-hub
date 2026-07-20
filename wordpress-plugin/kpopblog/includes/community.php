<?php
/**
 * WordPress-native community, forum reply, report, and moderation APIs.
 *
 * @package KpopBlog
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function kpopblog_public_author( $user_id ) {
	$user = get_userdata( (int) $user_id );
	if ( ! $user ) {
		return array( 'id' => '0', 'username' => '', 'displayName' => 'Deleted user', 'avatar' => '' );
	}
	return array(
		'id'          => (string) $user->ID,
		'username'    => $user->user_login,
		'displayName' => $user->display_name,
		'avatar'      => get_avatar_url( $user->ID ),
	);
}

function kpopblog_map_community_post( WP_Post $post ) {
	return array(
		'id'        => (string) $post->ID,
		'authorId'  => (string) $post->post_author,
		'author'    => kpopblog_public_author( $post->post_author ),
		'body'      => wp_strip_all_tags( $post->post_content ),
		'language'  => (string) kpopblog_meta( $post->ID, 'kb_language', 'en' ),
		'reactions' => kpopblog_int( get_post_meta( $post->ID, 'kb_reactions', true ) ),
		'createdAt' => mysql_to_rfc3339( $post->post_date_gmt ),
		'artistId'  => (string) kpopblog_meta( $post->ID, 'kb_artist_slug' ),
		'status'    => $post->post_status,
	);
}

function kpopblog_map_community_comment( WP_Comment $comment ) {
	$item = array(
		'id'        => (string) $comment->comment_ID,
		'threadId'  => (string) $comment->comment_post_ID,
		'articleId' => (string) $comment->comment_post_ID,
		'authorId'  => (string) $comment->user_id,
		'author'    => kpopblog_public_author( $comment->user_id ),
		'body'      => wp_strip_all_tags( $comment->comment_content ),
		'reactions' => (int) get_comment_meta( $comment->comment_ID, 'kb_reactions', true ),
		'createdAt' => mysql_to_rfc3339( $comment->comment_date_gmt ),
		'status'    => '1' === (string) $comment->comment_approved ? 'published' : 'pending',
	);
	if ( $comment->comment_parent ) {
		$item['parentId'] = (string) $comment->comment_parent;
	}
	return $item;
}

function kpopblog_collection_response( array $items, $total, $pages ) {
	$response = rest_ensure_response( $items );
	$response->header( 'X-WP-Total', (string) max( 0, (int) $total ) );
	$response->header( 'X-WP-TotalPages', (string) max( 0, (int) $pages ) );
	return $response;
}

function kpopblog_created_response( array $data ) {
	$response = rest_ensure_response( $data );
	$response->set_status( 201 );
	return $response;
}

function kpopblog_member_post_status() {
	return current_user_can( 'publish_posts' ) ? 'publish' : 'pending';
}

function kpopblog_validate_body( $value, $minimum, $maximum, $label ) {
	$body = trim( sanitize_textarea_field( (string) $value ) );
	$length = function_exists( 'mb_strlen' ) ? mb_strlen( $body ) : strlen( $body );
	if ( $length < $minimum || $length > $maximum ) {
		return new WP_Error( 'invalid_body', sprintf( '%s must be %d-%d characters.', $label, $minimum, $maximum ), array( 'status' => 400 ) );
	}
	return $body;
}

function kpopblog_community_rate_limit( $scope, $limit = 10 ) {
	return kpopblog_check_rate_limit( $scope, (string) get_current_user_id(), $limit, MINUTE_IN_SECONDS );
}

function kpopblog_require_moderator() {
	if ( ! is_user_logged_in() ) {
		return new WP_Error( 'rest_forbidden', 'Login required.', array( 'status' => 401 ) );
	}
	if ( ! current_user_can( 'kb_moderate_community' ) ) {
		return new WP_Error( 'rest_forbidden', 'Moderator access required.', array( 'status' => 403 ) );
	}
	return true;
}

function kpopblog_map_report( $row ) {
	return array(
		'id'             => (string) $row->id,
		'targetType'     => $row->target_type,
		'targetId'       => (string) $row->target_id,
		'reporterId'     => (string) $row->reporter_id,
		'reason'         => $row->reason,
		'status'         => $row->status,
		'resolutionNote' => (string) $row->resolution_note,
		'resolvedBy'     => (string) $row->resolved_by,
		'createdAt'      => mysql_to_rfc3339( $row->created_at ),
		'resolvedAt'     => $row->resolved_at ? mysql_to_rfc3339( $row->resolved_at ) : null,
	);
}

function kpopblog_update_report_status( $report_id, $status, $note = '' ) {
	global $wpdb;
	if ( ! current_user_can( 'kb_moderate_community' ) ) {
		return new WP_Error( 'rest_forbidden', 'Moderator access required.', array( 'status' => 403 ) );
	}
	$status = sanitize_key( (string) $status );
	if ( ! in_array( $status, array( 'resolved', 'dismissed' ), true ) ) {
		return new WP_Error( 'invalid_report_action', 'Report action must be resolved or dismissed.', array( 'status' => 400 ) );
	}
	$table = $wpdb->prefix . 'kb_reports';
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $report_id ) );
	if ( ! $row ) {
		return new WP_Error( 'report_not_found', 'Report not found.', array( 'status' => 404 ) );
	}
	if ( 'pending' !== $row->status ) {
		return new WP_Error( 'report_closed', 'Report is already closed.', array( 'status' => 409 ) );
	}
	$note = substr( sanitize_textarea_field( (string) $note ), 0, 2000 );
	$updated = $wpdb->update( $table, array(
		'status'          => $status,
		'open_key'        => null,
		'resolved_by'     => get_current_user_id(),
		'resolution_note' => $note,
		'resolved_at'     => current_time( 'mysql', true ),
	), array( 'id' => (int) $report_id ), array( '%s', '%s', '%d', '%s', '%s' ), array( '%d' ) );
	if ( 1 !== $updated ) {
		return new WP_Error( 'report_update_failed', 'Report could not be updated.', array( 'status' => 500 ) );
	}
	kpopblog_audit( 'report_' . $status, 'report', (int) $report_id, array( 'note' => $note ) );
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $report_id ) );
}

function kpopblog_report_target_exists( $target_type, $target_id ) {
	if ( 'user' === $target_type ) {
		return (bool) get_userdata( $target_id );
	}
	if ( in_array( $target_type, array( 'comment', 'reply' ), true ) ) {
		return (bool) get_comment( $target_id );
	}
	$type_map = array(
		'article'   => 'post',
		'thread'    => 'kb_thread',
		'community' => 'kb_community',
		'post'      => 'kb_community',
	);
	$post = get_post( $target_id );
	return $post && isset( $type_map[ $target_type ] ) && $type_map[ $target_type ] === $post->post_type;
}

function kpopblog_register_community_routes() {
	register_rest_route( KPOPBLOG_REST_NS, '/community', array(
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'args'                => array_merge( kpopblog_collection_args(), array( 'mine' => array( 'type' => 'boolean', 'default' => false ) ) ),
			'callback'            => function ( WP_REST_Request $request ) {
				$mine = $request->get_param( 'mine' ) && is_user_logged_in();
				$query = new WP_Query( array(
					'post_type'      => 'kb_community',
					'post_status'    => $mine ? array( 'publish', 'pending' ) : 'publish',
					'author'         => $mine ? get_current_user_id() : 0,
					'posts_per_page' => (int) $request->get_param( 'per_page' ),
					'paged'          => (int) $request->get_param( 'page' ),
					's'              => (string) $request->get_param( 'search' ),
					'orderby'        => 'date',
					'order'          => 'DESC',
				) );
				return kpopblog_collection_response( array_map( 'kpopblog_map_community_post', $query->posts ), $query->found_posts, $query->max_num_pages );
			},
		),
		array(
			'methods'             => 'POST',
			'permission_callback' => 'kpopblog_require_login',
			'callback'            => function ( WP_REST_Request $request ) {
				$limit = kpopblog_community_rate_limit( 'community_post', 6 );
				if ( is_wp_error( $limit ) ) { return $limit; }
				$body = kpopblog_validate_body( $request->get_param( 'body' ), 1, 2000, 'Community post' );
				if ( is_wp_error( $body ) ) { return $body; }
				$status = kpopblog_member_post_status();
				$post_id = wp_insert_post( array(
					'post_type'    => 'kb_community',
					'post_status'  => $status,
					'post_author'  => get_current_user_id(),
					'post_content' => $body,
					'post_title'   => wp_trim_words( $body, 10, '' ),
					'comment_status' => 'open',
				), true );
				if ( is_wp_error( $post_id ) ) {
					return new WP_Error( 'community_create_failed', 'Community post could not be saved.', array( 'status' => 500 ) );
				}
				update_post_meta( $post_id, 'kb_language', sanitize_key( (string) ( $request->get_param( 'language' ) ?: 'en' ) ) );
				kpopblog_audit( 'community_post_created', 'community', $post_id, array( 'status' => $status ) );
				return kpopblog_created_response( array( 'item' => kpopblog_map_community_post( get_post( $post_id ) ), 'status' => $status ) );
			},
		),
	) );

	register_rest_route( KPOPBLOG_REST_NS, '/forum/categories', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function () {
			$terms = get_terms( array( 'taxonomy' => 'kb_forum_category', 'hide_empty' => false, 'orderby' => 'name' ) );
			if ( is_wp_error( $terms ) ) { return $terms; }
			$items = array();
			foreach ( $terms as $term ) {
				$items[] = array(
					'id'          => (string) $term->term_id,
					'slug'        => $term->slug,
					'name'        => $term->name,
					'description' => $term->description,
					'icon'        => '💬',
					'threadCount' => (int) $term->count,
					'postCount'   => 0,
				);
			}
			return rest_ensure_response( $items );
		},
	) );

	register_rest_route( KPOPBLOG_REST_NS, '/threads', array(
		'methods'             => 'POST',
		'permission_callback' => 'kpopblog_require_login',
		'callback'            => function ( WP_REST_Request $request ) {
			$limit = kpopblog_community_rate_limit( 'forum_thread', 4 );
			if ( is_wp_error( $limit ) ) { return $limit; }
			$title = trim( sanitize_text_field( (string) $request->get_param( 'title' ) ) );
			$title_length = function_exists( 'mb_strlen' ) ? mb_strlen( $title ) : strlen( $title );
			if ( $title_length < 5 || $title_length > 160 ) {
				return new WP_Error( 'invalid_title', 'Thread title must be 5-160 characters.', array( 'status' => 400 ) );
			}
			$body = kpopblog_validate_body( $request->get_param( 'body' ), 1, 10000, 'Thread body' );
			if ( is_wp_error( $body ) ) { return $body; }
			$category_slug = sanitize_title( (string) $request->get_param( 'categorySlug' ) );
			$term = get_term_by( 'slug', $category_slug, 'kb_forum_category' );
			if ( ! $term ) {
				return new WP_Error( 'category_not_found', 'Forum category not found.', array( 'status' => 404 ) );
			}
			$status = kpopblog_member_post_status();
			$slug = wp_unique_post_slug( sanitize_title( $title ), 0, 'publish', 'kb_thread', 0 );
			$post_id = wp_insert_post( array(
				'post_type'    => 'kb_thread',
				'post_status'  => $status,
				'post_author'  => get_current_user_id(),
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $body,
				'comment_status' => 'open',
			), true );
			if ( is_wp_error( $post_id ) ) {
				return new WP_Error( 'thread_create_failed', 'Thread could not be saved.', array( 'status' => 500 ) );
			}
			wp_set_object_terms( $post_id, (int) $term->term_id, 'kb_forum_category' );
			update_post_meta( $post_id, 'kb_category_slug', $term->slug );
			update_post_meta( $post_id, 'kb_language', sanitize_key( (string) ( $request->get_param( 'language' ) ?: 'en' ) ) );
			kpopblog_audit( 'thread_created', 'thread', $post_id, array( 'status' => $status ) );
			return kpopblog_created_response( array( 'item' => kpopblog_map_thread( get_post( $post_id ) ), 'status' => $status ) );
		},
	) );

	register_rest_route( KPOPBLOG_REST_NS, '/threads/(?P<slug>[a-zA-Z0-9_-]+)/replies', array(
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'args'                => kpopblog_collection_args(),
			'callback'            => function ( WP_REST_Request $request ) {
				$thread_id = kpopblog_post_id_by_slug( 'kb_thread', $request->get_param( 'slug' ) );
				if ( ! $thread_id ) { return new WP_Error( 'thread_not_found', 'Thread not found.', array( 'status' => 404 ) ); }
				$per_page = (int) $request->get_param( 'per_page' );
				$page = (int) $request->get_param( 'page' );
				$total = (int) get_comments( array( 'post_id' => $thread_id, 'status' => 'approve', 'type' => 'kb_reply', 'count' => true ) );
				$comments = get_comments( array(
					'post_id' => $thread_id,
					'status'  => 'approve',
					'type'    => 'kb_reply',
					'number'  => $per_page,
					'offset'  => ( $page - 1 ) * $per_page,
					'orderby' => 'comment_date_gmt',
					'order'   => 'ASC',
				) );
				return kpopblog_collection_response( array_map( 'kpopblog_map_community_comment', $comments ), $total, $per_page ? (int) ceil( $total / $per_page ) : 0 );
			},
		),
		array(
			'methods'             => 'POST',
			'permission_callback' => 'kpopblog_require_login',
			'callback'            => function ( WP_REST_Request $request ) {
				$thread_id = kpopblog_post_id_by_slug( 'kb_thread', $request->get_param( 'slug' ) );
				if ( ! $thread_id ) { return new WP_Error( 'thread_not_found', 'Thread not found.', array( 'status' => 404 ) ); }
				if ( get_post_meta( $thread_id, 'kb_locked', true ) ) {
					return new WP_Error( 'thread_locked', 'This thread is locked.', array( 'status' => 409 ) );
				}
				$limit = kpopblog_community_rate_limit( 'forum_reply', 12 );
				if ( is_wp_error( $limit ) ) { return $limit; }
				$body = kpopblog_validate_body( $request->get_param( 'body' ), 1, 10000, 'Reply' );
				if ( is_wp_error( $body ) ) { return $body; }
				$parent_id = max( 0, (int) $request->get_param( 'parentId' ) );
				if ( $parent_id ) {
					$parent = get_comment( $parent_id );
					if ( ! $parent || (int) $parent->comment_post_ID !== $thread_id ) {
						return new WP_Error( 'invalid_parent', 'Parent reply not found.', array( 'status' => 400 ) );
					}
				}
				$user = wp_get_current_user();
				$comment_id = wp_new_comment( array(
					'comment_post_ID'      => $thread_id,
					'comment_parent'       => $parent_id,
					'comment_type'         => 'kb_reply',
					'comment_content'      => $body,
					'comment_author'       => $user->display_name,
					'comment_author_email' => $user->user_email,
					'comment_author_url'   => '',
					'user_id'              => $user->ID,
				), true );
				if ( is_wp_error( $comment_id ) ) {
					return new WP_Error( 'reply_create_failed', 'Reply could not be saved.', array( 'status' => 500 ) );
				}
				wp_update_post( array( 'ID' => $thread_id, 'post_modified_gmt' => current_time( 'mysql', true ) ) );
				kpopblog_audit( 'thread_reply_created', 'comment', $comment_id, array( 'threadId' => $thread_id ) );
				$comment = get_comment( $comment_id );
				return kpopblog_created_response( array( 'item' => kpopblog_map_community_comment( $comment ), 'pending' => '1' !== (string) $comment->comment_approved ) );
			},
		),
	) );

	register_rest_route( KPOPBLOG_REST_NS, '/articles/(?P<slug>[a-zA-Z0-9_-]+)/comments', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'args'                => kpopblog_collection_args(),
		'callback'            => function ( WP_REST_Request $request ) {
			$post_id = kpopblog_post_id_by_slug( 'post', $request->get_param( 'slug' ) );
			if ( ! $post_id ) { return new WP_Error( 'article_not_found', 'Article not found.', array( 'status' => 404 ) ); }
			$per_page = (int) $request->get_param( 'per_page' );
			$page = (int) $request->get_param( 'page' );
			$total = (int) get_comments( array( 'post_id' => $post_id, 'status' => 'approve', 'type' => 'comment', 'count' => true ) );
			$comments = get_comments( array( 'post_id' => $post_id, 'status' => 'approve', 'type' => 'comment', 'number' => $per_page, 'offset' => ( $page - 1 ) * $per_page ) );
			return kpopblog_collection_response( array_map( 'kpopblog_map_community_comment', $comments ), $total, $per_page ? (int) ceil( $total / $per_page ) : 0 );
		},
	) );

	register_rest_route( KPOPBLOG_REST_NS, '/reports', array(
		'methods'             => 'POST',
		'permission_callback' => 'kpopblog_require_login',
		'callback'            => function ( WP_REST_Request $request ) {
			global $wpdb;
			$limit = kpopblog_check_rate_limit( 'community_report', (string) get_current_user_id(), 10, HOUR_IN_SECONDS );
			if ( is_wp_error( $limit ) ) { return $limit; }
			$target_type = sanitize_key( (string) $request->get_param( 'targetType' ) );
			$target_id = max( 0, (int) $request->get_param( 'targetId' ) );
			$reason = kpopblog_validate_body( $request->get_param( 'reason' ), 3, 500, 'Report reason' );
			if ( is_wp_error( $reason ) ) { return $reason; }
			if ( ! kpopblog_report_target_exists( $target_type, $target_id ) ) {
				return new WP_Error( 'report_target_not_found', 'Report target not found.', array( 'status' => 404 ) );
			}
			$open_key = hash( 'sha256', get_current_user_id() . '|' . $target_type . '|' . $target_id );
			$table = $wpdb->prefix . 'kb_reports';
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE open_key = %s", $open_key ) );
			if ( $existing ) {
				return new WP_Error( 'duplicate_report', 'You already have a pending report for this item.', array( 'status' => 409 ) );
			}
			$inserted = $wpdb->insert( $table, array(
				'reporter_id' => get_current_user_id(),
				'target_type' => $target_type,
				'target_id'   => $target_id,
				'reason'      => $reason,
				'status'      => 'pending',
				'open_key'    => $open_key,
				'created_at'  => current_time( 'mysql', true ),
			), array( '%d', '%s', '%d', '%s', '%s', '%s', '%s' ) );
			if ( 1 !== $inserted ) {
				return new WP_Error( 'report_create_failed', 'Report could not be saved.', array( 'status' => 500 ) );
			}
			$report_id = (int) $wpdb->insert_id;
			kpopblog_audit( 'report_created', 'report', $report_id, array( 'targetType' => $target_type, 'targetId' => $target_id ) );
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $report_id ) );
			return kpopblog_created_response( array( 'report' => kpopblog_map_report( $row ) ) );
		},
	) );

	register_rest_route( KPOPBLOG_REST_NS, '/moderation/reports', array(
		'methods'             => 'GET',
		'permission_callback' => 'kpopblog_require_moderator',
		'args'                => array( 'page' => array( 'type' => 'integer', 'default' => 1 ), 'per_page' => array( 'type' => 'integer', 'default' => 20, 'maximum' => 100 ) ),
		'callback'            => function ( WP_REST_Request $request ) {
			global $wpdb;
			$table = $wpdb->prefix . 'kb_reports';
			$page = max( 1, (int) $request->get_param( 'page' ) );
			$per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) );
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" );
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = 'pending' ORDER BY created_at ASC LIMIT %d OFFSET %d", $per_page, ( $page - 1 ) * $per_page ) );
			return kpopblog_collection_response( array_map( 'kpopblog_map_report', $rows ), $total, (int) ceil( $total / $per_page ) );
		},
	) );

	register_rest_route( KPOPBLOG_REST_NS, '/moderation/reports/(?P<id>\d+)', array(
		'methods'             => 'POST',
		'permission_callback' => 'kpopblog_require_moderator',
		'callback'            => function ( WP_REST_Request $request ) {
			$report_id = (int) $request->get_param( 'id' );
			$row = kpopblog_update_report_status( $report_id, $request->get_param( 'action' ), $request->get_param( 'note' ) );
			if ( is_wp_error( $row ) ) { return $row; }
			return rest_ensure_response( array( 'report' => kpopblog_map_report( $row ) ) );
		},
	) );
}
add_action( 'rest_api_init', 'kpopblog_register_community_routes' );
