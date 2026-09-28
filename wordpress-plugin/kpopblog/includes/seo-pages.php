<?php
/**
 * Server-rendered SEO and answer-engine (AEO) context for app routes.
 *
 * The React app renders in the browser, but search crawlers, answer engines,
 * and link previews read the first HTML response. For each public route this
 * builds the title, description, canonical URL, preview image, schema.org
 * JSON-LD, and a readable HTML fallback that React replaces on load. It plugs
 * into discoverability.php through the generic "page" context kind.
 *
 * @package KpopBlog
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function kpopblog_seo_site_name() {
	$name = trim( wp_strip_all_tags( get_bloginfo( 'name' ) ) );
	return '' !== $name ? $name : 'KpopBlog';
}

function kpopblog_seo_text( $value, $limit = 300 ) {
	$text = trim( preg_replace( '/\s+/u', ' ', kpopblog_decode_text_entities( (string) $value ) ) );
	return wp_html_excerpt( $text, $limit, '…' );
}

function kpopblog_seo_link_list( array $posts, $prefix, $with_excerpt = false ) {
	if ( ! $posts ) { return ''; }
	$html = '<ul>';
	foreach ( $posts as $post ) {
		$slug = 'kb_video' === $post->post_type ? (string) $post->ID : $post->post_name;
		$html .= '<li><a href="' . esc_url( home_url( $prefix . $slug ) ) . '">' . esc_html( kpopblog_decode_text_entities( get_the_title( $post ) ) ) . '</a>';
		if ( $with_excerpt ) {
			$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_strip_all_tags( $post->post_content );
			$html .= ' — ' . esc_html( kpopblog_seo_text( $excerpt, 180 ) );
		}
		$html .= '</li>';
	}
	return $html . '</ul>';
}

function kpopblog_seo_item_list( array $posts, $prefix ) {
	$items = array();
	foreach ( $posts as $index => $post ) {
		$slug = 'kb_video' === $post->post_type ? (string) $post->ID : $post->post_name;
		$items[] = array( '@type' => 'ListItem', 'position' => $index + 1, 'url' => home_url( $prefix . $slug ), 'name' => kpopblog_decode_text_entities( get_the_title( $post ) ) );
	}
	return array( '@type' => 'ItemList', 'itemListElement' => $items );
}

function kpopblog_seo_publisher() {
	$logo = function_exists( 'kpopblog_design_frontend_config' ) ? (string) ( kpopblog_design_frontend_config()['logoUrl'] ?? '' ) : '';
	$org = array( '@type' => 'Organization', '@id' => home_url( '/#organization' ), 'name' => kpopblog_seo_site_name(), 'url' => home_url( '/' ) );
	$org['logo'] = '' !== $logo ? $logo : KPOPBLOG_URL . 'icons/icon-512.png';
	return $org;
}

/** Latest published articles. */
function kpopblog_seo_latest_articles( $limit ) {
	return get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => $limit, 'has_password' => false ) );
}

/**
 * Build the SEO context for a request path, or null when the route has no
 * dedicated context. Unknown entities return array( 'kind' => 'missing_article' ).
 */
function kpopblog_seo_page_context( $path ) {
	$path = '/' === $path ? '/' : untrailingslashit( $path );
	$site = kpopblog_seo_site_name();
	$tagline = trim( wp_strip_all_tags( get_bloginfo( 'description' ) ) );

	if ( '/' === $path ) {
		$latest = kpopblog_seo_latest_articles( 20 );
		$threads = get_posts( array( 'post_type' => 'kb_thread', 'post_status' => 'publish', 'numberposts' => 10, 'orderby' => 'modified' ) );
		$description = 'K-pop news, comeback schedules, artist profiles, and a fan community. Updated daily with the latest from ' . implode( ', ', array_slice( kpopblog_artist_names_for_slugs( wp_list_pluck( array_slice( kpopblog_get_artist_matchers(), 0, 6 ), 'slug' ) ), 0, 6 ) ) . ', and more.';
		$html = '<section data-kpopblog-fallback="home"><h1>' . esc_html( $site . ( '' !== $tagline ? ' — ' . $tagline : '' ) ) . '</h1><p>' . esc_html( $description ) . '</p>'
			. '<h2>Latest K-pop news</h2>' . kpopblog_seo_link_list( $latest, '/news/', true )
			. '<h2>Community discussions</h2>' . kpopblog_seo_link_list( $threads, '/thread/' )
			. '</section>';
		return array(
			'kind'        => 'page',
			'title'       => $site . ' — K-pop News, Comebacks & Fan Community',
			'description' => $description,
			'canonical'   => home_url( '/' ),
			'og_type'     => 'website',
			'image'       => $latest ? kpopblog_article_image_url( $latest[0]->ID ) : '',
			'json_ld'     => array(
				'@context' => 'https://schema.org',
				'@graph'   => array(
					kpopblog_seo_publisher(),
					array(
						'@type'           => 'WebSite',
						'@id'             => home_url( '/#website' ),
						'url'             => home_url( '/' ),
						'name'            => $site,
						'description'     => $description,
						'publisher'       => array( '@id' => home_url( '/#organization' ) ),
						'inLanguage'      => 'en',
						'potentialAction' => array(
							'@type'       => 'SearchAction',
							'target'      => array( '@type' => 'EntryPoint', 'urlTemplate' => home_url( '/search?q={search_term_string}' ) ),
							'query-input' => 'required name=search_term_string',
						),
					),
					array_merge( array( 'name' => 'Latest K-pop news' ), kpopblog_seo_item_list( $latest, '/news/' ) ),
				),
			),
			'html'        => $html,
		);
	}

	if ( '/latest' === $path || '/trending' === $path ) {
		$latest = kpopblog_seo_latest_articles( 40 );
		$label = '/latest' === $path ? 'Latest K-pop News' : 'Trending K-pop News';
		return array(
			'kind'        => 'page',
			'title'       => $label . ' — ' . $site,
			'description' => 'The newest K-pop headlines: comebacks, charts, tours, awards, and artist news, updated throughout the day.',
			'canonical'   => home_url( $path ),
			'og_type'     => 'website',
			'image'       => $latest ? kpopblog_article_image_url( $latest[0]->ID ) : '',
			'json_ld'     => array( '@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => $label, 'url' => home_url( $path ), 'mainEntity' => kpopblog_seo_item_list( $latest, '/news/' ) ),
			'html'        => '<section data-kpopblog-fallback="latest"><h1>' . esc_html( $label ) . '</h1>' . kpopblog_seo_link_list( $latest, '/news/', true ) . '</section>',
		);
	}

	if ( '/artists' === $path ) {
		$artists = get_posts( array( 'post_type' => 'kb_artist', 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC' ) );
		return array(
			'kind'        => 'page',
			'title'       => 'K-pop Artists: Profiles, Members & News — ' . $site,
			'description' => 'Profiles of ' . count( $artists ) . ' K-pop groups and soloists with members, debut dates, agencies, fandom names, upcoming releases, and the latest news.',
			'canonical'   => home_url( '/artists' ),
			'og_type'     => 'website',
			'image'       => '',
			'json_ld'     => array( '@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => 'K-pop Artists', 'url' => home_url( '/artists' ), 'mainEntity' => kpopblog_seo_item_list( $artists, '/artist/' ) ),
			'html'        => '<section data-kpopblog-fallback="artists"><h1>K-pop Artists</h1>' . kpopblog_seo_link_list( $artists, '/artist/', true ) . '</section>',
		);
	}

	if ( preg_match( '#^/artist/([^/]+)$#', $path, $m ) ) {
		return kpopblog_seo_artist_context( sanitize_title( rawurldecode( $m[1] ) ) );
	}
	if ( preg_match( '#^/thread/([^/]+)$#', $path, $m ) ) {
		return kpopblog_seo_thread_context( sanitize_title( rawurldecode( $m[1] ) ) );
	}
	if ( preg_match( '#^/watch/(\d+)$#', $path, $m ) ) {
		return kpopblog_seo_video_context( (int) $m[1] );
	}
	if ( '/forum' === $path || preg_match( '#^/forum/([^/]+)$#', $path, $m ) ) {
		return kpopblog_seo_forum_context( isset( $m[1] ) ? sanitize_title( rawurldecode( $m[1] ) ) : '' );
	}
	if ( preg_match( '#^/polls/([^/]+)$#', $path, $m ) ) {
		$polls = get_posts( array( 'post_type' => 'kb_poll', 'name' => sanitize_title( rawurldecode( $m[1] ) ), 'post_status' => 'publish', 'numberposts' => 1 ) );
		if ( ! $polls ) { return array( 'kind' => 'missing_article' ); }
		$poll = $polls[0];
		$options = array();
		foreach ( (array) get_post_meta( $poll->ID, 'kb_options', true ) as $option ) {
			if ( ! empty( $option['label'] ) ) { $options[] = (string) $option['label']; }
		}
		$title = kpopblog_decode_text_entities( get_the_title( $poll ) );
		return array(
			'kind'        => 'page',
			'title'       => $title . ' — ' . $site,
			'description' => kpopblog_seo_text( $poll->post_content . ' Options: ' . implode( ', ', $options ) . '.', 300 ),
			'canonical'   => home_url( '/polls/' . $poll->post_name ),
			'og_type'     => 'website',
			'image'       => '',
			'json_ld'     => array( '@context' => 'https://schema.org', '@type' => 'Question', 'name' => $title, 'text' => kpopblog_seo_text( $poll->post_content, 500 ), 'dateCreated' => get_post_time( 'c', true, $poll ), 'answerCount' => count( $options ), 'suggestedAnswer' => array_map( function ( $label ) { return array( '@type' => 'Answer', 'text' => $label ); }, $options ) ),
			'html'        => '<article data-kpopblog-fallback="poll"><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( kpopblog_seo_text( $poll->post_content, 500 ) ) . '</p><ul><li>' . implode( '</li><li>', array_map( 'esc_html', $options ) ) . '</li></ul></article>',
		);
	}
	return null;
}

/** Artist page: MusicGroup/Person entity plus an FAQ answer engines can quote. */
function kpopblog_seo_artist_context( $slug ) {
	$artist = kpopblog_get_artist_by_slug( $slug );
	if ( ! $artist ) { return array( 'kind' => 'missing_article' ); }
	$site    = kpopblog_seo_site_name();
	$name    = kpopblog_decode_text_entities( get_the_title( $artist ) );
	$korean  = (string) get_post_meta( $artist->ID, 'kb_korean_name', true );
	$agency  = (string) get_post_meta( $artist->ID, 'kb_agency', true );
	$debut   = (string) get_post_meta( $artist->ID, 'kb_debut_date', true );
	$fandom  = (string) get_post_meta( $artist->ID, 'kb_fandom_name', true );
	$type    = (string) get_post_meta( $artist->ID, 'kb_type', true );
	$bio     = trim( wp_strip_all_tags( $artist->post_content ) );
	$image   = kpopblog_artist_image_url( $artist->ID );
	if ( kpopblog_placeholder_image_url() === $image ) { $image = ''; } // the site's default share image is used instead
	$url     = home_url( '/artist/' . $slug );
	$members = get_posts( array( 'post_type' => 'kb_member', 'post_status' => 'publish', 'numberposts' => 30, 'meta_key' => 'kb_group_slug', 'meta_value' => $slug, 'orderby' => 'ID', 'order' => 'ASC' ) );
	$member_names = array_map( function ( $member ) { return kpopblog_decode_text_entities( get_the_title( $member ) ); }, $members );
	$news = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 10, 'meta_query' => array( array( 'key' => 'kb_related_artist_slugs', 'value' => '"' . $slug . '"', 'compare' => 'LIKE' ) ) ) );
	$next = get_posts( array( 'post_type' => 'kb_comeback', 'post_status' => 'publish', 'numberposts' => 3, 'meta_key' => 'kb_release_at', 'orderby' => 'meta_value', 'order' => 'ASC', 'meta_query' => array( 'relation' => 'AND', array( 'key' => 'kb_artist_slug', 'value' => $slug ), array( 'key' => 'kb_release_at', 'value' => gmdate( 'Y-m-d' ), 'compare' => '>=' ) ) ) );
	$debut_text = '' !== $debut && strtotime( $debut ) ? gmdate( 'F j, Y', strtotime( $debut ) ) : '';

	$faq = array();
	if ( '' !== $debut_text ) { $faq[] = array( 'When did ' . $name . ' debut?', $name . ' debuted on ' . $debut_text . '.' ); }
	if ( '' !== $agency ) { $faq[] = array( 'Which agency manages ' . $name . '?', $name . ' is managed by ' . $agency . '.' ); }
	if ( '' !== $fandom ) { $faq[] = array( 'What is ' . $name . '\'s fandom name?', $name . '\'s fans are called ' . $fandom . '.' ); }
	if ( $member_names ) { $faq[] = array( 'Who are the members of ' . $name . '?', $name . ' has ' . count( $member_names ) . ' members: ' . implode( ', ', $member_names ) . '.' ); }
	if ( $next ) {
		$release = strtotime( (string) get_post_meta( $next[0]->ID, 'kb_release_at', true ) );
		$faq[] = array( 'When is ' . $name . '\'s next comeback?', $name . '\'s next announced release is ' . kpopblog_decode_text_entities( get_the_title( $next[0] ) ) . ( $release ? ' on ' . gmdate( 'F j, Y', $release ) : '' ) . '.' );
	}
	if ( '' !== $korean ) { $faq[] = array( 'What is ' . $name . '\'s Korean name?', $name . ' is written ' . $korean . ' in Korean.' ); }

	$entity = array(
		'@type'       => 'soloist' === $type ? 'Person' : 'MusicGroup',
		'@id'         => $url . '#artist',
		'name'        => $name,
		'url'         => $url,
		'description' => $bio,
	);
	if ( '' !== $image ) { $entity['image'] = $image; }
	if ( '' !== $korean ) { $entity['alternateName'] = $korean; }
	if ( 'soloist' !== $type ) {
		$entity['genre'] = 'K-pop';
		if ( '' !== $debut ) { $entity['foundingDate'] = $debut; }
		if ( $member_names ) { $entity['member'] = array_map( function ( $member_name ) { return array( '@type' => 'Person', 'name' => $member_name ); }, $member_names ); }
		if ( '' !== $agency ) { $entity['recordLabel'] = array( '@type' => 'Organization', 'name' => $agency ); }
	} elseif ( '' !== $agency ) {
		$entity['affiliation'] = array( '@type' => 'Organization', 'name' => $agency );
	}
	$graph = array( $entity );
	if ( $faq ) {
		$graph[] = array(
			'@type'      => 'FAQPage',
			'@id'        => $url . '#faq',
			'mainEntity' => array_map( function ( $qa ) { return array( '@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $qa[1] ) ); }, $faq ),
		);
	}

	$html = '<article data-kpopblog-fallback="artist"><h1>' . esc_html( $name ) . ( '' !== $korean ? ' (' . esc_html( $korean ) . ')' : '' ) . '</h1>';
	if ( '' !== $bio ) { $html .= '<p>' . esc_html( $bio ) . '</p>'; }
	if ( $faq ) {
		$html .= '<h2>' . esc_html( $name ) . ' facts</h2><dl>';
		foreach ( $faq as $qa ) { $html .= '<dt>' . esc_html( $qa[0] ) . '</dt><dd>' . esc_html( $qa[1] ) . '</dd>'; }
		$html .= '</dl>';
	}
	if ( $news ) { $html .= '<h2>Latest ' . esc_html( $name ) . ' news</h2>' . kpopblog_seo_link_list( $news, '/news/', true ); }
	$html .= '</article>';

	$description = '' !== $bio ? $bio : $name . ' profile, members, and news.';
	if ( '' !== $debut_text ) { $description .= ' Debut: ' . $debut_text . '.'; }
	if ( '' !== $fandom ) { $description .= ' Fandom: ' . $fandom . '.'; }
	return array(
		'kind'        => 'page',
		'title'       => $name . ( '' !== $korean ? ' (' . $korean . ')' : '' ) . ' — Profile, Members, News & Comebacks | ' . $site,
		'description' => kpopblog_seo_text( $description, 300 ),
		'canonical'   => $url,
		'og_type'     => 'profile',
		'image'       => $image,
		'json_ld'     => array( '@context' => 'https://schema.org', '@graph' => $graph ),
		'html'        => $html,
	);
}

/** Forum thread: DiscussionForumPosting with its approved replies. */
function kpopblog_seo_thread_context( $slug ) {
	$threads = get_posts( array( 'post_type' => 'kb_thread', 'name' => $slug, 'post_status' => 'publish', 'numberposts' => 1 ) );
	if ( ! $threads ) { return array( 'kind' => 'missing_article' ); }
	$thread = $threads[0];
	$site   = kpopblog_seo_site_name();
	$title  = kpopblog_decode_text_entities( get_the_title( $thread ) );
	$author = get_userdata( $thread->post_author );
	$url    = home_url( '/thread/' . $thread->post_name );
	$body   = wp_strip_all_tags( $thread->post_content );
	$comments = get_comments( array( 'post_id' => $thread->ID, 'status' => 'approve', 'number' => 20, 'order' => 'ASC' ) );
	$posting = array(
		'@context'             => 'https://schema.org',
		'@type'                => 'DiscussionForumPosting',
		'@id'                  => $url,
		'headline'             => $title,
		'text'                 => kpopblog_seo_text( $body, 2000 ),
		'url'                  => $url,
		'datePublished'        => get_post_time( 'c', true, $thread ),
		'dateModified'         => get_post_modified_time( 'c', true, $thread ),
		'author'               => array( '@type' => (int) $thread->post_author === (int) get_option( 'kpopblog_newsroom_user_id' ) ? 'Organization' : 'Person', 'name' => $author ? $author->display_name : $site ),
		'interactionStatistic' => array(
			array( '@type' => 'InteractionCounter', 'interactionType' => 'https://schema.org/CommentAction', 'userInteractionCount' => (int) $thread->comment_count ),
			array( '@type' => 'InteractionCounter', 'interactionType' => 'https://schema.org/ViewAction', 'userInteractionCount' => (int) get_post_meta( $thread->ID, 'kb_views', true ) ),
		),
	);
	$html = '<article data-kpopblog-fallback="thread"><h1>' . esc_html( $title ) . '</h1><p>' . nl2br( esc_html( $body ) ) . '</p>';
	if ( $comments ) {
		$posting['comment'] = array();
		$html .= '<h2>Replies</h2><ol>';
		foreach ( $comments as $comment ) {
			$posting['comment'][] = array( '@type' => 'Comment', 'text' => kpopblog_seo_text( $comment->comment_content, 1000 ), 'datePublished' => mysql_to_rfc3339( $comment->comment_date_gmt ), 'author' => array( '@type' => 'Person', 'name' => $comment->comment_author ) );
			$html .= '<li><strong>' . esc_html( $comment->comment_author ) . '</strong>: ' . esc_html( kpopblog_seo_text( $comment->comment_content, 600 ) ) . '</li>';
		}
		$html .= '</ol>';
	}
	$html .= '</article>';
	return array(
		'kind'        => 'page',
		'title'       => $title . ' — ' . $site . ' Forum',
		'description' => kpopblog_seo_text( $body, 280 ),
		'canonical'   => $url,
		'og_type'     => 'article',
		'image'       => (int) get_post_meta( $thread->ID, 'kb_article_id', true ) ? kpopblog_article_image_url( (int) get_post_meta( $thread->ID, 'kb_article_id', true ) ) : '',
		'json_ld'     => $posting,
		'html'        => $html,
	);
}

/** Forum index or board: list of threads. */
function kpopblog_seo_forum_context( $board ) {
	$site = kpopblog_seo_site_name();
	$args = array( 'post_type' => 'kb_thread', 'post_status' => 'publish', 'numberposts' => 30, 'orderby' => 'modified' );
	$name = 'K-pop Fan Forum';
	$description = 'Join K-pop fans discussing comebacks, concerts, news, fandoms, fashion, merch, and fan art.';
	if ( '' !== $board ) {
		$term = get_term_by( 'slug', $board, 'kb_forum_category' );
		if ( ! $term ) { return array( 'kind' => 'missing_article' ); }
		$args['tax_query'] = array( array( 'taxonomy' => 'kb_forum_category', 'field' => 'term_id', 'terms' => (int) $term->term_id ) );
		$name = $term->name . ' — K-pop Forum';
		$description = '' !== trim( $term->description ) ? trim( $term->description ) . ' K-pop fan discussions on ' . $site . '.' : $description;
	}
	$threads = get_posts( $args );
	$path = '' === $board ? '/forum' : '/forum/' . $board;
	return array(
		'kind'        => 'page',
		'title'       => $name . ' | ' . $site,
		'description' => $description,
		'canonical'   => home_url( $path ),
		'og_type'     => 'website',
		'image'       => '',
		'json_ld'     => array( '@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => $name, 'url' => home_url( $path ), 'mainEntity' => kpopblog_seo_item_list( $threads, '/thread/' ) ),
		'html'        => '<section data-kpopblog-fallback="forum"><h1>' . esc_html( $name ) . '</h1><p>' . esc_html( $description ) . '</p>' . kpopblog_seo_link_list( $threads, '/thread/' ) . '</section>',
	);
}

/** Video page: VideoObject pointing at the official YouTube upload. */
function kpopblog_seo_video_context( $video_id ) {
	$video = get_post( $video_id );
	if ( ! $video || 'kb_video' !== $video->post_type || 'publish' !== $video->post_status ) { return array( 'kind' => 'missing_article' ); }
	$youtube = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) get_post_meta( $video->ID, 'kb_youtube_id', true ) );
	$title = kpopblog_decode_text_entities( get_the_title( $video ) );
	$description = kpopblog_seo_text( '' !== trim( $video->post_content ) ? $video->post_content : $title, 300 );
	$thumb = '' !== $youtube ? 'https://i.ytimg.com/vi/' . $youtube . '/hqdefault.jpg' : '';
	$json = array(
		'@context'     => 'https://schema.org',
		'@type'        => 'VideoObject',
		'name'         => $title,
		'description'  => $description,
		'uploadDate'   => get_post_time( 'c', true, $video ),
		'thumbnailUrl' => $thumb,
		'url'          => home_url( '/watch/' . $video->ID ),
	);
	if ( '' !== $youtube ) {
		$json['embedUrl'] = 'https://www.youtube.com/embed/' . $youtube;
		$json['contentUrl'] = 'https://www.youtube.com/watch?v=' . $youtube;
	}
	return array(
		'kind'        => 'page',
		'title'       => $title . ' — ' . kpopblog_seo_site_name(),
		'description' => $description,
		'canonical'   => home_url( '/watch/' . $video->ID ),
		'og_type'     => 'video.other',
		'image'       => $thumb,
		'json_ld'     => $json,
		'html'        => '<article data-kpopblog-fallback="video"><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $description ) . '</p></article>',
	);
}

/* ---------- Google News sitemap and IndexNow ---------- */

/** Articles published in the last 48 hours, in Google News sitemap format. */
function kpopblog_render_news_sitemap() {
	$posts = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1000, 'date_query' => array( array( 'after' => '48 hours ago' ) ) ) );
	$site = kpopblog_seo_site_name();
	$out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";
	foreach ( $posts as $post ) {
		$lang = (string) get_post_meta( $post->ID, 'kb_language', true );
		$out .= '<url><loc>' . kpopblog_xml_escape( home_url( '/news/' . $post->post_name ) ) . '</loc><news:news><news:publication><news:name>' . kpopblog_xml_escape( $site ) . '</news:name><news:language>' . kpopblog_xml_escape( '' !== $lang ? $lang : 'en' ) . '</news:language></news:publication>'
			. '<news:publication_date>' . kpopblog_xml_escape( get_post_time( 'c', true, $post ) ) . '</news:publication_date><news:title>' . kpopblog_xml_escape( kpopblog_decode_text_entities( get_the_title( $post ) ) ) . '</news:title></news:news></url>' . "\n";
	}
	return $out . '</urlset>';
}

function kpopblog_indexnow_key() {
	$key = (string) get_option( 'kpopblog_indexnow_key', '' );
	if ( ! preg_match( '/^[a-f0-9]{32}$/', $key ) ) {
		$key = md5( wp_generate_password( 32, true, true ) . home_url() );
		update_option( 'kpopblog_indexnow_key', $key, false );
	}
	return $key;
}

/** Serve /news-sitemap.xml and the IndexNow key file. */
function kpopblog_serve_seo_endpoints() {
	$path = kpopblog_request_path();
	if ( '/news-sitemap.xml' === $path ) {
		status_header( 200 );
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'Cache-Control: public, max-age=600' );
		echo kpopblog_render_news_sitemap(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML escaped per value.
		exit;
	}
	$key = kpopblog_indexnow_key();
	if ( '/' . $key . '.txt' === $path ) {
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=UTF-8' );
		echo esc_html( $key );
		exit;
	}
}
add_action( 'template_redirect', 'kpopblog_serve_seo_endpoints', 0 );

/** Queue public URLs for IndexNow (Bing, Naver, Yandex, Seznam) when content is published. */
function kpopblog_indexnow_queue( $new_status, $old_status, $post ) {
	if ( 'publish' !== $new_status || ! $post instanceof WP_Post || '' === $post->post_name ) { return; }
	$route = function_exists( 'kpopblog_app_route_for_post' ) ? kpopblog_app_route_for_post( $post ) : '';
	if ( '' === $route || ! in_array( $post->post_type, array( 'post', 'kb_thread', 'kb_artist', 'kb_poll', 'kb_video' ), true ) ) { return; }
	$GLOBALS['kpopblog_indexnow_urls'][] = home_url( $route );
}
add_action( 'transition_post_status', 'kpopblog_indexnow_queue', 20, 3 );

function kpopblog_indexnow_flush() {
	$urls = isset( $GLOBALS['kpopblog_indexnow_urls'] ) ? array_values( array_unique( $GLOBALS['kpopblog_indexnow_urls'] ) ) : array();
	if ( ! $urls || 'production' !== wp_get_environment_type() ) { return; }
	$key = kpopblog_indexnow_key();
	wp_remote_post( 'https://api.indexnow.org/indexnow', array(
		'timeout'  => 3,
		'blocking' => false,
		'headers'  => array( 'Content-Type' => 'application/json; charset=utf-8' ),
		'body'     => wp_json_encode( array(
			'host'        => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
			'key'         => $key,
			'keyLocation' => home_url( '/' . $key . '.txt' ),
			'urlList'     => array_slice( $urls, 0, 1000 ),
		) ),
	) );
	$GLOBALS['kpopblog_indexnow_urls'] = array();
}
add_action( 'shutdown', 'kpopblog_indexnow_flush' );
