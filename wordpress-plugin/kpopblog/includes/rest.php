<?php
/**
 * Unified REST namespace: /wp-json/kpopblog/v1/*
 *
 * Returns each content type already shaped to match the React app's TypeScript
 * types (see src/types/index.ts) and the Zod schemas (src/schemas/ai.ts),
 * so the front-end doesn't need a translation layer.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---------- helpers ---------- */

function kpopblog_thumb_url( $post_id, $size = 'large' ) {
	$id = get_post_thumbnail_id( $post_id );
	if ( ! $id ) { return ''; }
	$src = wp_get_attachment_image_src( $id, $size );
	return $src ? $src[0] : '';
}

/**
 * Article image: featured image, then the source preview image, then the
 * first related artist's image, then the generic news artwork.
 */
function kpopblog_article_image_url( $post_id ) {
	static $artist_images = array();
	$image = kpopblog_thumb_url( $post_id );
	if ( '' !== $image ) { return $image; }
	$external = esc_url_raw( (string) get_post_meta( $post_id, 'kb_external_image', true ), array( 'https' ) );
	if ( '' !== $external ) { return $external; }
	if ( function_exists( 'kpopblog_artist_image_url' ) ) {
		foreach ( (array) get_post_meta( $post_id, 'kb_related_artist_slugs', true ) as $slug ) {
			$slug = sanitize_title( (string) $slug );
			if ( ! array_key_exists( $slug, $artist_images ) ) {
				$artist = kpopblog_get_artist_by_slug( $slug );
				$artist_images[ $slug ] = $artist ? kpopblog_artist_image_url( $artist->ID ) : '';
			}
			if ( '' !== $artist_images[ $slug ] ) { return $artist_images[ $slug ]; }
		}
		return kpopblog_placeholder_image_url();
	}
	return '';
}

function kpopblog_meta( $post_id, $key, $default = '' ) {
	$v = get_post_meta( $post_id, $key, true );
	return ( $v === '' || $v === null ) ? $default : $v;
}

function kpopblog_int( $v, $default = 0 ) {
	return is_numeric( $v ) ? (int) $v : $default;
}

/**
 * REST text fields are rendered by clients as plain text, not HTML.
 * Normalize numeric and named entities from WordPress title filters and feeds.
 */
function kpopblog_decode_text_entities( $value ) {
	$text = wp_strip_all_tags( (string) $value, true );
	for ( $pass = 0; $pass < 2; $pass++ ) {
		$decoded = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( $decoded === $text ) {
			break;
		}
		$text = $decoded;
	}
	return $text;
}

function kpopblog_map_article( WP_Post $p ) {
	$author      = get_userdata( $p->post_author );
	$tag_terms   = wp_get_post_terms( $p->ID, 'post_tag', array( 'fields' => 'names' ) );
	$cat_terms   = wp_get_post_terms( $p->ID, 'category', array( 'fields' => 'names' ) );
	$reading     = kpopblog_int( kpopblog_meta( $p->ID, 'kb_reading_time' ), max( 1, (int) round( str_word_count( wp_strip_all_tags( $p->post_content ) ) / 200 ) ) );

	return array(
		'id'               => (string) $p->ID,
		'wpId'             => $p->ID,
		'slug'             => $p->post_name,
		'title'            => kpopblog_decode_text_entities( get_the_title( $p ) ),
		'subtitle'         => kpopblog_decode_text_entities( kpopblog_meta( $p->ID, 'kb_subtitle' ) ),
		'excerpt'          => kpopblog_decode_text_entities( has_excerpt( $p ) ? get_the_excerpt( $p ) : wp_trim_words( wp_strip_all_tags( $p->post_content ), 40 ) ),
		'content'          => apply_filters( 'the_content', $p->post_content ),
		'featuredImage'    => kpopblog_article_image_url( $p->ID ),
		'author'           => $author ? $author->display_name : 'KpopBlog',
		'authorAvatar'     => $author ? get_avatar_url( $author->ID ) : '',
		'category'         => kpopblog_meta( $p->ID, 'kb_category_slug', $cat_terms[0] ?? 'news' ),
		'tags'             => array_values( is_array( $tag_terms ) ? $tag_terms : array() ),
		'relatedArtistIds' => (array) kpopblog_meta( $p->ID, 'kb_related_artist_slugs', array() ),
		'language'         => kpopblog_meta( $p->ID, 'kb_language', 'en' ),
		'source'           => kpopblog_meta( $p->ID, 'kb_source', 'wordpress' ),
		'status'           => $p->post_status === 'publish' ? 'published' : 'draft',
		'viewCount'        => kpopblog_int( get_post_meta( $p->ID, 'kb_view_count', true ) ),
		'commentCount'     => (int) $p->comment_count,
		'reactionCount'    => kpopblog_int( get_post_meta( $p->ID, 'kb_reaction_count', true ) ),
		'publishedAt'      => mysql_to_rfc3339( $p->post_date_gmt ),
		'modifiedAt'       => mysql_to_rfc3339( $p->post_modified_gmt ),
		'readingTime'      => $reading,
	);
}

function kpopblog_map_artist( WP_Post $p ) {
	return array(
		'id'           => (string) $p->ID,
		'slug'         => $p->post_name,
		'name'         => kpopblog_decode_text_entities( get_the_title( $p ) ),
		'koreanName'   => kpopblog_meta( $p->ID, 'kb_korean_name' ),
		'type'         => kpopblog_meta( $p->ID, 'kb_type', 'boy_group' ),
		'agency'       => kpopblog_meta( $p->ID, 'kb_agency' ),
		'debutDate'    => kpopblog_meta( $p->ID, 'kb_debut_date' ),
		'fandomName'   => kpopblog_meta( $p->ID, 'kb_fandom_name' ),
		'generation'   => kpopblog_int( kpopblog_meta( $p->ID, 'kb_generation' ), 4 ),
		'status'       => kpopblog_meta( $p->ID, 'kb_status', 'active' ),
		'nationality'  => kpopblog_meta( $p->ID, 'kb_nationality', 'South Korea' ),
		'bio'          => wp_strip_all_tags( $p->post_content ),
		'image'        => function_exists( 'kpopblog_artist_image_url' ) ? kpopblog_artist_image_url( $p->ID ) : kpopblog_thumb_url( $p->ID ),
		'followerCount'=> kpopblog_int( get_post_meta( $p->ID, 'kb_follower_count', true ) ),
		'memberIds'    => array(),
		'socialLinks'  => (object) ( get_post_meta( $p->ID, 'kb_social_links', true ) ?: array() ),
	);
}

function kpopblog_map_member( WP_Post $p ) {
	return array(
		'id'         => (string) $p->ID,
		'slug'       => $p->post_name,
		'stageName'  => kpopblog_decode_text_entities( kpopblog_meta( $p->ID, 'kb_stage_name', get_the_title( $p ) ) ),
		'fullName'   => kpopblog_meta( $p->ID, 'kb_full_name' ),
		'koreanName' => kpopblog_meta( $p->ID, 'kb_korean_name' ),
		'birthday'   => kpopblog_meta( $p->ID, 'kb_birthday' ),
		'nationality'=> kpopblog_meta( $p->ID, 'kb_nationality', 'South Korea' ),
		'groupId'    => kpopblog_meta( $p->ID, 'kb_group_slug' ),
		'position'   => (array) kpopblog_meta( $p->ID, 'kb_positions', array() ),
		'mbti'       => kpopblog_meta( $p->ID, 'kb_mbti' ),
		'image'      => kpopblog_member_image_url( $p->ID ),
		'facts'      => (array) kpopblog_meta( $p->ID, 'kb_facts', array() ),
	);
}

/** Member image: featured image, then the group's artist image. */
function kpopblog_member_image_url( $post_id ) {
	static $group_images = array();
	$image = kpopblog_thumb_url( $post_id );
	if ( '' !== $image || ! function_exists( 'kpopblog_get_artist_by_slug' ) ) { return $image; }
	$group = sanitize_title( (string) kpopblog_meta( $post_id, 'kb_group_slug' ) );
	if ( ! array_key_exists( $group, $group_images ) ) {
		$artist = kpopblog_get_artist_by_slug( $group );
		$group_images[ $group ] = $artist ? kpopblog_artist_image_url( $artist->ID ) : kpopblog_placeholder_image_url();
	}
	return $group_images[ $group ];
}

function kpopblog_map_comeback( WP_Post $p ) {
	return array(
		'id'          => (string) $p->ID,
		'artistId'    => kpopblog_meta( $p->ID, 'kb_artist_slug' ),
		'title'       => kpopblog_decode_text_entities( get_the_title( $p ) ),
		'type'        => kpopblog_meta( $p->ID, 'kb_type', 'album' ),
		'releaseAt'   => kpopblog_meta( $p->ID, 'kb_release_at' ),
		'description' => kpopblog_decode_text_entities( $p->post_content ),
		'image'       => kpopblog_thumb_url( $p->ID ) ?: ( kpopblog_comeback_artist_image( $p->ID ) ?: kpopblog_article_image_url( $p->ID ) ),
		'sourceUrl'   => (string) kpopblog_meta( $p->ID, 'kb_source_url' ),
	);
}

function kpopblog_comeback_artist_image( $post_id ) {
	$artist = function_exists( 'kpopblog_get_artist_by_slug' ) ? kpopblog_get_artist_by_slug( kpopblog_meta( $post_id, 'kb_artist_slug' ) ) : null;
	return $artist ? kpopblog_artist_image_url( $artist->ID ) : '';
}

function kpopblog_map_chart( WP_Post $p ) {
	return array(
		'id'            => (string) $p->ID,
		'chartId'       => kpopblog_meta( $p->ID, 'kb_chart_id', 'weekly-global' ),
		'title'         => kpopblog_decode_text_entities( get_the_title( $p ) ),
		'weekStartDate' => kpopblog_meta( $p->ID, 'kb_week_start_date' ),
		'entries'       => (array) kpopblog_meta( $p->ID, 'kb_entries', array() ),
	);
}

function kpopblog_map_thread( WP_Post $p ) {
	return array(
		'id'           => (string) $p->ID,
		'slug'         => $p->post_name,
		'categoryId'   => kpopblog_meta( $p->ID, 'kb_category_slug', 'general' ),
		'title'        => kpopblog_decode_text_entities( get_the_title( $p ) ),
		'body'         => kpopblog_decode_text_entities( $p->post_content ),
		'authorId'     => (string) $p->post_author,
		'author'       => function_exists( 'kpopblog_public_author' ) ? kpopblog_public_author( $p->post_author ) : array(),
		'flair'        => kpopblog_meta( $p->ID, 'kb_flair' ),
		'rumor'        => (bool) get_post_meta( $p->ID, 'kb_rumor', true ),
		'pinned'       => (bool) get_post_meta( $p->ID, 'kb_pinned', true ),
		'locked'       => (bool) get_post_meta( $p->ID, 'kb_locked', true ),
		'official'     => (bool) get_post_meta( $p->ID, 'kb_official', true ),
		'relatedArtistIds' => array_values( array_filter( (array) get_post_meta( $p->ID, 'kb_related_artist_slugs', true ), 'is_string' ) ),
		'views'        => kpopblog_int( get_post_meta( $p->ID, 'kb_views', true ) ),
		'replies'      => (int) $p->comment_count,
		'reactions'    => kpopblog_int( get_post_meta( $p->ID, 'kb_reactions', true ) ),
		'lastActivityAt' => mysql_to_rfc3339( $p->post_modified_gmt ),
		'createdAt'    => mysql_to_rfc3339( $p->post_date_gmt ),
	);
}

function kpopblog_map_poll( WP_Post $p ) {
	$options = (array) kpopblog_meta( $p->ID, 'kb_options', array() );
	$total = 0;
	foreach ( $options as $o ) { $total += isset( $o['votes'] ) ? (int) $o['votes'] : 0; }
	return array(
		'id'          => (string) $p->ID,
		'slug'        => $p->post_name,
		'title'       => kpopblog_decode_text_entities( get_the_title( $p ) ),
		'description' => kpopblog_decode_text_entities( $p->post_content ),
		'options'     => $options,
		'totalVotes'  => $total,
		'endsAt'      => kpopblog_meta( $p->ID, 'kb_ends_at' ),
		'artistId'    => kpopblog_meta( $p->ID, 'kb_artist_slug' ),
	);
}

/* ---------- routes ---------- */

function kpopblog_collection_args() {
	return array(
		'per_page' => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ),
		'page'     => array( 'type' => 'integer', 'default' => 1,  'minimum' => 1 ),
		'search'   => array( 'type' => 'string' ),
		'category' => array( 'type' => 'string' ),
		'artist'   => array( 'type' => 'string' ),
	);
}

function kpopblog_map_video( WP_Post $p ) {
	$youtube_id = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) kpopblog_meta( $p->ID, 'kb_youtube_id' ) );
	return array(
		'id'          => (string) $p->ID,
		'slug'        => $p->post_name,
		'title'       => kpopblog_decode_text_entities( get_the_title( $p ) ),
		'artistId'    => (string) kpopblog_meta( $p->ID, 'kb_artist_slug' ),
		'artistSlug'  => (string) kpopblog_meta( $p->ID, 'kb_artist_slug' ),
		'category'    => (string) kpopblog_meta( $p->ID, 'kb_video_category', 'Other' ),
		'youtubeId'   => $youtube_id,
		'thumbnail'   => $youtube_id ? 'https://i.ytimg.com/vi/' . rawurlencode( $youtube_id ) . '/hqdefault.jpg' : kpopblog_thumb_url( $p->ID ),
		'duration'    => (string) kpopblog_meta( $p->ID, 'kb_duration' ),
		'description' => kpopblog_decode_text_entities( $p->post_content ),
		'commentCount'=> (int) $p->comment_count,
	);
}

function kpopblog_query( $post_type, WP_REST_Request $r ) {
	$args = array(
		'post_type'      => $post_type,
		'post_status'    => 'publish',
		'posts_per_page' => (int) $r->get_param( 'per_page' ),
		'paged'          => (int) $r->get_param( 'page' ),
		's'              => (string) $r->get_param( 'search' ),
	);
	$artist = sanitize_title( (string) $r->get_param( 'artist' ) );
	if ( '' !== $artist && in_array( $post_type, array( 'post', 'kb_thread' ), true ) ) {
		$args['meta_query'] = array( array(
			'key'     => 'kb_related_artist_slugs',
			'value'   => '"' . $artist . '"',
			'compare' => 'LIKE',
		) );
	}
	if ( 'kb_thread' === $post_type && $r->get_param( 'category' ) ) {
		$args['tax_query'] = array( array(
			'taxonomy' => 'kb_forum_category',
			'field'    => 'slug',
			'terms'    => sanitize_title( (string) $r->get_param( 'category' ) ),
		) );
	}
	return new WP_Query( $args );
}

function kpopblog_register_routes() {
	$endpoints = array(
		'articles'  => array( 'post',        'kpopblog_map_article'  ),
		'artists'   => array( 'kb_artist',   'kpopblog_map_artist'   ),
		'members'   => array( 'kb_member',   'kpopblog_map_member'   ),
		'comebacks' => array( 'kb_comeback', 'kpopblog_map_comeback' ),
		'charts'    => array( 'kb_chart',    'kpopblog_map_chart'    ),
		'videos'    => array( 'kb_video',    'kpopblog_map_video'    ),
		'threads'   => array( 'kb_thread',   'kpopblog_map_thread'   ),
		'polls'     => array( 'kb_poll',     'kpopblog_map_poll'     ),
	);

	foreach ( $endpoints as $path => $cfg ) {
		list( $post_type, $mapper ) = $cfg;

		register_rest_route( KPOPBLOG_REST_NS, '/' . $path, array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'args'                => kpopblog_collection_args(),
			'callback'            => function ( WP_REST_Request $r ) use ( $post_type, $mapper ) {
				$q = kpopblog_query( $post_type, $r );
				$items = array_map( $mapper, $q->posts );
				$res = rest_ensure_response( $items );
				$res->header( 'X-WP-Total',      (string) $q->found_posts );
				$res->header( 'X-WP-TotalPages', (string) $q->max_num_pages );
				return $res;
			},
		) );

		register_rest_route( KPOPBLOG_REST_NS, '/' . $path . '/(?P<slug>[a-zA-Z0-9_-]+)', array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => function ( WP_REST_Request $r ) use ( $post_type, $mapper ) {
				$slug = $r->get_param( 'slug' );
				$posts = get_posts( array(
					'post_type' => $post_type, 'name' => $slug, 'numberposts' => 1, 'post_status' => 'publish',
				) );
				if ( ! $posts ) { return new WP_Error( 'not_found', 'Not found', array( 'status' => 404 ) ); }
				return rest_ensure_response( $mapper( $posts[0] ) );
			},
		) );
	}

	// Bundle endpoint — one call to hydrate the homepage.
	register_rest_route( KPOPBLOG_REST_NS, '/bundle', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function () {
			$pick = function ( $post_type, $mapper, $n = 10, array $extra = array() ) {
				$posts = get_posts( array_merge( array( 'post_type' => $post_type, 'numberposts' => $n, 'post_status' => 'publish' ), $extra ) );
				return array_map( $mapper, $posts );
			};
			// Upcoming releases first (soonest at the top), then the most recent past ones.
			$today = gmdate( 'Y-m-d' );
			$upcoming = $pick( 'kb_comeback', 'kpopblog_map_comeback', 30, array(
				'meta_key'   => 'kb_release_at',
				'orderby'    => 'meta_value',
				'order'      => 'ASC',
				'meta_query' => array( array( 'key' => 'kb_release_at', 'value' => $today, 'compare' => '>=' ) ),
			) );
			$past = count( $upcoming ) < 30 ? $pick( 'kb_comeback', 'kpopblog_map_comeback', 30 - count( $upcoming ), array(
				'meta_key'   => 'kb_release_at',
				'orderby'    => 'meta_value',
				'order'      => 'DESC',
				'meta_query' => array( array( 'key' => 'kb_release_at', 'value' => $today, 'compare' => '<' ) ),
			) ) : array();
			return rest_ensure_response( array(
				'articles'  => $pick( 'post',        'kpopblog_map_article',  40 ),
				'artists'   => $pick( 'kb_artist',   'kpopblog_map_artist',   100, array( 'orderby' => 'title', 'order' => 'ASC' ) ),
				'members'   => $pick( 'kb_member',   'kpopblog_map_member',   300 ),
				'comebacks' => array_merge( $upcoming, $past ),
				'charts'    => $pick( 'kb_chart',    'kpopblog_map_chart',    5  ),
				'videos'    => $pick( 'kb_video',    'kpopblog_map_video',    40 ),
				'threads'   => $pick( 'kb_thread',   'kpopblog_map_thread',   20 ),
				'polls'     => $pick( 'kb_poll',     'kpopblog_map_poll',     10 ),
				'community' => function_exists( 'kpopblog_map_community_post' ) ? $pick( 'kb_community', 'kpopblog_map_community_post', 20 ) : array(),
				'categories'=> function_exists( 'kpopblog_get_forum_categories_data' ) ? kpopblog_get_forum_categories_data() : array(),
			) );
		},
	) );
}
add_action( 'rest_api_init', 'kpopblog_register_routes' );
