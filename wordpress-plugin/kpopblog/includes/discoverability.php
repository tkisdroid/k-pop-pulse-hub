<?php
/**
 * Machine-readable discovery endpoints for public KpopBlog content.
 *
 * @package KpopBlog
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function kpopblog_public_posts( $post_type, $limit, $orderby = 'modified', array $extra_args = array() ) {
	$args = array(
		'post_type'      => $post_type,
		'post_status'    => 'publish',
		'has_password'   => false,
		'numberposts'    => max( 1, (int) $limit ),
		'orderby'        => $orderby,
		'order'          => 'DESC',
		'suppress_filters' => false,
	);
	return get_posts( array_merge( $args, $extra_args ) );
}

function kpopblog_discovery_post_types() {
	return array( 'post', 'kb_artist', 'kb_member', 'kb_video', 'kb_poll', 'kb_thread', 'kb_comeback' );
}

function kpopblog_discovery_content_timestamp() {
	static $latest_timestamp = null;
	if ( null !== $latest_timestamp ) { return $latest_timestamp; }
	$latest_timestamp = 0;
	foreach ( kpopblog_discovery_post_types() as $post_type ) {
		$posts = kpopblog_public_posts( $post_type, 1 );
		if ( ! $posts || '' === (string) $posts[0]->post_modified_gmt ) { continue; }
		$timestamp = strtotime( (string) $posts[0]->post_modified_gmt . ' UTC' );
		if ( false !== $timestamp ) { $latest_timestamp = max( $latest_timestamp, $timestamp ); }
	}
	return $latest_timestamp;
}

function kpopblog_touch_discovery_revision( $minimum_timestamp = 0, $event_token = '' ) {
	global $wpdb;
	static $touched_events = array();
	$floor = max( time(), (int) $minimum_timestamp, kpopblog_discovery_content_timestamp() + 1 );
	if ( '' !== $event_token && isset( $touched_events[ $event_token ] ) && $touched_events[ $event_token ] >= $floor ) {
		return $touched_events[ $event_token ];
	}
	$option_name = 'kpopblog_discovery_revision';
	$result = $wpdb->query( $wpdb->prepare(
		"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %d, 'no') ON DUPLICATE KEY UPDATE option_value = GREATEST(CAST(option_value AS UNSIGNED) + 1, %d)",
		$option_name,
		$floor,
		$floor
	) );
	wp_cache_delete( $option_name, 'options' );
	wp_cache_delete( 'notoptions', 'options' );
	wp_cache_delete( 'alloptions', 'options' );
	$persisted = false === $result ? 0 : (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option_name ) );
	if ( '' !== $event_token ) { $touched_events[ $event_token ] = $persisted; }
	return $persisted;
}

function kpopblog_discovery_post_event_token( $post ) {
	if ( ! ( $post instanceof WP_Post ) ) { return ''; }
	return implode( ':', array( 'post', (int) $post->ID, (string) $post->post_status, (string) $post->post_modified_gmt ) );
}

function kpopblog_touch_discovery_status_revision( $new_status, $old_status, $post ) {
	if ( $new_status !== $old_status && $post instanceof WP_Post && in_array( $post->post_type, kpopblog_discovery_post_types(), true ) && ( 'publish' === $new_status || 'publish' === $old_status ) ) {
		$modified_timestamp = strtotime( (string) $post->post_modified_gmt . ' UTC' );
		kpopblog_touch_discovery_revision( (int) $modified_timestamp + 1, kpopblog_discovery_post_event_token( $post ) );
	}
}
add_action( 'transition_post_status', 'kpopblog_touch_discovery_status_revision', 10, 3 );

function kpopblog_touch_discovery_post_revision( $post_id, $post_after, $post_before ) {
	$is_discovered = $post_after instanceof WP_Post && in_array( $post_after->post_type, kpopblog_discovery_post_types(), true );
	$is_public_transition = $is_discovered && ( 'publish' === $post_after->post_status || 'publish' === $post_before->post_status );
	if ( $is_public_transition && $post_after->to_array() !== $post_before->to_array() ) {
		$before_timestamp = strtotime( (string) $post_before->post_modified_gmt . ' UTC' );
		$after_timestamp = strtotime( (string) $post_after->post_modified_gmt . ' UTC' );
		kpopblog_touch_discovery_revision( max( (int) $before_timestamp, (int) $after_timestamp ) + 1, kpopblog_discovery_post_event_token( $post_after ) );
	}
}
add_action( 'post_updated', 'kpopblog_touch_discovery_post_revision', 10, 3 );

function kpopblog_touch_discovery_delete_revision( $post_id, $post ) {
	if ( $post instanceof WP_Post && 'publish' === $post->post_status && in_array( $post->post_type, kpopblog_discovery_post_types(), true ) ) {
		$modified_timestamp = strtotime( (string) $post->post_modified_gmt . ' UTC' );
		kpopblog_touch_discovery_revision( (int) $modified_timestamp + 1, 'delete:' . (int) $post_id );
	}
}
add_action( 'before_delete_post', 'kpopblog_touch_discovery_delete_revision', 10, 2 );

function kpopblog_discovery_meta_keys() {
	return array( 'kb_category_slug', 'kb_source_url', 'kb_source_urls', 'kb_source_title', 'kb_release_at', 'kb_type', 'kb_artist_slug', '_thumbnail_id' );
}

function kpopblog_touch_discovery_meta_revision( $meta_id, $post_id, $meta_key ) {
	if ( ! in_array( (string) $meta_key, kpopblog_discovery_meta_keys(), true ) ) { return; }
	$post = get_post( $post_id );
	if ( $post instanceof WP_Post && 'publish' === $post->post_status && in_array( $post->post_type, kpopblog_discovery_post_types(), true ) ) {
		kpopblog_touch_discovery_revision( 0, 'meta:' . (int) $post_id );
	}
}
add_action( 'added_post_meta', 'kpopblog_touch_discovery_meta_revision', 10, 3 );
add_action( 'updated_post_meta', 'kpopblog_touch_discovery_meta_revision', 10, 3 );
add_action( 'deleted_post_meta', 'kpopblog_touch_discovery_meta_revision', 10, 3 );

function kpopblog_touch_discovery_option_revision( $option ) {
	if ( in_array( $option, array( 'blogname', 'blogdescription', 'home', 'siteurl', 'WPLANG' ), true ) ) {
		kpopblog_touch_discovery_revision( 0, 'option:' . $option );
	}
}
add_action( 'updated_option', 'kpopblog_touch_discovery_option_revision', 10, 1 );

function kpopblog_request_path() {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
	$path = wp_parse_url( $request_uri, PHP_URL_PATH );
	return '/' . ltrim( (string) $path, '/' );
}

function kpopblog_get_public_context() {
	return isset( $GLOBALS['kpopblog_public_context'] ) && is_array( $GLOBALS['kpopblog_public_context'] )
		? $GLOBALS['kpopblog_public_context']
		: array( 'kind' => 'none' );
}

function kpopblog_prepare_public_context() {
	$path = kpopblog_request_path();
	if ( preg_match( '#^/news/([^/]+)/?$#', $path, $matches ) ) {
		$slug = sanitize_title( rawurldecode( $matches[1] ) );
		$post = '' !== $slug ? get_page_by_path( $slug, OBJECT, 'post' ) : null;
		if ( ! ( $post instanceof WP_Post ) || 'publish' !== $post->post_status || '' !== (string) $post->post_password || post_password_required( $post ) ) {
			$GLOBALS['kpopblog_public_context'] = array( 'kind' => 'missing_article' );
			remove_action( 'wp_head', 'rel_canonical' );
			remove_action( 'template_redirect', 'redirect_canonical' );
			status_header( 404 );
			return;
		}
		$GLOBALS['kpopblog_public_context'] = array( 'kind' => 'article', 'post' => $post );
		remove_action( 'wp_head', 'rel_canonical' );
		remove_action( 'template_redirect', 'redirect_canonical' );
		status_header( 200 );
		return;
	}
	if ( '/comebacks' === untrailingslashit( $path ) ) {
		$GLOBALS['kpopblog_public_context'] = array(
			'kind'  => 'comebacks',
			'posts' => kpopblog_public_posts(
				'kb_comeback',
				200,
				'meta_value',
				array(
					'meta_key' => 'kb_release_at',
					'order'    => 'ASC',
				)
			),
		);
		remove_action( 'wp_head', 'rel_canonical' );
		remove_action( 'template_redirect', 'redirect_canonical' );
		return;
	}
	// Artist, thread, forum, video, poll, home, and listing routes (seo-pages.php).
	$page = function_exists( 'kpopblog_seo_page_context' ) ? kpopblog_seo_page_context( $path ) : null;
	if ( is_array( $page ) ) {
		$GLOBALS['kpopblog_public_context'] = $page;
		remove_action( 'wp_head', 'rel_canonical' );
		remove_action( 'template_redirect', 'redirect_canonical' );
		status_header( 'missing_article' === $page['kind'] ? 404 : 200 );
	}
}
add_action( 'template_redirect', 'kpopblog_prepare_public_context', 1 );

function kpopblog_public_context_is_missing() {
	return 'missing_article' === ( kpopblog_get_public_context()['kind'] ?? 'none' );
}

function kpopblog_public_description( WP_Post $post ) {
	$text = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_strip_all_tags( $post->post_content, true );
	return wp_html_excerpt( preg_replace( '/\s+/', ' ', trim( $text ) ), 300, '…' );
}

function kpopblog_public_source_urls( $post_id ) {
	$raw_urls = get_post_meta( $post_id, 'kb_source_urls', true );
	$raw_urls = is_array( $raw_urls ) ? $raw_urls : array();
	$single = (string) get_post_meta( $post_id, 'kb_source_url', true );
	if ( '' !== $single ) {
		array_unshift( $raw_urls, $single );
	}
	$urls = array();
	foreach ( $raw_urls as $raw_url ) {
		$url = esc_url_raw( trim( (string) $raw_url ), array( 'https' ) );
		if ( '' === $url || 0 !== stripos( $url, 'https://' ) || ! wp_http_validate_url( $url ) || in_array( $url, $urls, true ) ) {
			continue;
		}
		$urls[] = $url;
		if ( count( $urls ) >= 5 ) {
			break;
		}
	}
	return $urls;
}

function kpopblog_render_public_sources( $post_id ) {
	$urls = kpopblog_public_source_urls( $post_id );
	if ( empty( $urls ) ) {
		return '';
	}
	$primary_title = sanitize_text_field( (string) get_post_meta( $post_id, 'kb_source_title', true ) );
	$heading_id = 'kpopblog-source-heading-' . (int) $post_id;
	$html = '<section aria-labelledby="' . esc_attr( $heading_id ) . '"><h2 id="' . esc_attr( $heading_id ) . '">Sources</h2><ul>';
	foreach ( $urls as $index => $url ) {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		$label = 0 === $index && '' !== $primary_title ? $primary_title : $host;
		$html .= '<li><a href="' . esc_url( $url ) . '" rel="noopener noreferrer nofollow">' . esc_html( $label ) . '</a></li>';
	}
	return $html . '</ul></section>';
}

function kpopblog_build_public_json_ld( array $context ) {
	if ( 'page' === ( $context['kind'] ?? '' ) ) {
		return isset( $context['json_ld'] ) ? $context['json_ld'] : array();
	}
	if ( 'article' === ( $context['kind'] ?? '' ) ) {
		$post = $context['post'];
		$author = get_userdata( $post->post_author );
		$canonical = home_url( '/news/' . $post->post_name );
		$data = array(
			'@context'         => 'https://schema.org',
			'@type'            => 'NewsArticle',
			'headline'         => get_the_title( $post ),
			'description'      => kpopblog_public_description( $post ),
			'datePublished'    => get_post_time( 'c', true, $post ),
			'dateModified'     => get_post_modified_time( 'c', true, $post ),
			'mainEntityOfPage' => array( '@type' => 'WebPage', '@id' => $canonical ),
			'author'           => array( '@type' => (int) $post->post_author === (int) get_option( 'kpopblog_newsroom_user_id' ) ? 'Organization' : 'Person', 'name' => $author ? $author->display_name : get_bloginfo( 'name' ) ),
			'publisher'        => array( '@type' => 'Organization', 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) ),
			'citation'         => kpopblog_public_source_urls( $post->ID ),
		);
		$image = kpopblog_article_image_url( $post->ID );
		if ( $image ) {
			$data['image'] = array( $image );
		}
		return $data;
	}
	if ( 'comebacks' !== ( $context['kind'] ?? '' ) ) {
		return array();
	}
	$items = array();
	foreach ( $context['posts'] ?? array() as $post ) {
		$release = (string) get_post_meta( $post->ID, 'kb_release_at', true );
		if ( ! kpopblog_is_strict_iso8601_date( $release ) ) {
			continue;
		}
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => count( $items ) + 1,
			'item'     => array(
				'@type'       => 'Event',
				'name'        => get_the_title( $post ),
				'url'         => home_url( '/comebacks#event-' . $post->ID ),
				'startDate'   => $release,
				'eventStatus' => 'https://schema.org/EventScheduled',
				'description' => wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 ),
			),
		);
	}
	return array(
		'@context'   => 'https://schema.org',
		'@type'      => 'CollectionPage',
		'name'       => 'Comeback Schedule',
		'url'        => home_url( '/comebacks' ),
		'mainEntity' => array( '@type' => 'ItemList', 'itemListElement' => $items ),
	);
}

function kpopblog_filter_public_title( $title ) {
	$context = kpopblog_get_public_context();
	if ( 'article' === ( $context['kind'] ?? '' ) ) {
		return get_the_title( $context['post'] ) . ' — ' . get_bloginfo( 'name' );
	}
	if ( 'comebacks' === ( $context['kind'] ?? '' ) ) {
		return 'Comeback Schedule — ' . get_bloginfo( 'name' );
	}
	if ( 'page' === ( $context['kind'] ?? '' ) ) {
		return $context['title'];
	}
	if ( 'missing_article' === ( $context['kind'] ?? '' ) ) {
		return 'Page not found — ' . get_bloginfo( 'name' );
	}
	return $title;
}
add_filter( 'pre_get_document_title', 'kpopblog_filter_public_title' );

function kpopblog_filter_public_robots( $robots ) {
	if ( kpopblog_public_context_is_missing() ) {
		$robots['noindex'] = true;
		$robots['nofollow'] = true;
		unset( $robots['index'], $robots['follow'] );
	}
	return $robots;
}
add_filter( 'wp_robots', 'kpopblog_filter_public_robots' );

function kpopblog_render_public_json_ld( array $json_ld ) {
	$encoded = wp_json_encode( $json_ld, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( false === $encoded ) {
		return '';
	}
	return '<script id="kpopblog-discovery-jsonld" type="application/ld+json">' . $encoded . '</script>' . "\n";
}

function kpopblog_render_public_head() {
	$context = kpopblog_get_public_context();
	$kind = $context['kind'] ?? 'none';
	if ( 'none' === $kind || 'missing_article' === $kind ) {
		return;
	}
	$image = '';
	if ( 'page' === $kind ) {
		$title = $context['title'];
		$description = $context['description'];
		$canonical = $context['canonical'];
		$open_graph_type = $context['og_type'];
		$image = $context['image'];
	} elseif ( 'article' === $kind ) {
		$post = $context['post'];
		$title = get_the_title( $post ) . ' — ' . get_bloginfo( 'name' );
		$description = kpopblog_public_description( $post );
		$canonical = home_url( '/news/' . $post->post_name );
		$open_graph_type = 'article';
		$published = get_post_time( 'c', true, $post );
		$modified = get_post_modified_time( 'c', true, $post );
		$image = kpopblog_article_image_url( $post->ID );
	} else {
		$title = 'Comeback Schedule — ' . get_bloginfo( 'name' );
		$description = sanitize_text_field( get_bloginfo( 'description' ) );
		if ( '' === $description ) {
			$description = 'Published K-pop comeback and release schedule.';
		}
		$canonical = home_url( '/comebacks' );
		$open_graph_type = 'website';
	}
	$json_ld = kpopblog_build_public_json_ld( $context );
	echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
	echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $canonical ) . '" />' . "\n";
	echo '<meta property="og:type" content="' . esc_attr( $open_graph_type ) . '" />' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
	echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
	echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '" />' . "\n";
	if ( 'article' === $kind ) {
		echo '<meta property="article:published_time" content="' . esc_attr( $published ) . '" />' . "\n";
		echo '<meta property="article:modified_time" content="' . esc_attr( $modified ) . '" />' . "\n";
	}
	// Social networks don't render SVG previews; fall back to the default share image.
	if ( ! $image || preg_match( '/\.svg(?:\?|$)/i', $image ) ) {
		$image = KPOPBLOG_URL . 'icons/og-default.jpg';
	}
	echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
	echo '<meta name="twitter:image" content="' . esc_url( $image ) . '" />' . "\n";
	echo kpopblog_render_public_json_ld( $json_ld );
}
add_action( 'wp_head', 'kpopblog_render_public_head' );

function kpopblog_render_public_fallback() {
	$context = kpopblog_get_public_context();
	if ( 'page' === ( $context['kind'] ?? '' ) ) {
		return isset( $context['html'] ) ? $context['html'] : '';
	}
	if ( 'article' === ( $context['kind'] ?? '' ) ) {
		$post = $context['post'];
		$published = get_post_time( 'c', true, $post );
		$modified = get_post_modified_time( 'c', true, $post );
		$html = '<article data-kpopblog-fallback="article">';
		$html .= '<h1>' . esc_html( get_the_title( $post ) ) . '</h1>';
		$html .= '<p><time datetime="' . esc_attr( $published ) . '">' . esc_html( $published ) . '</time>';
		if ( $modified !== $published ) {
			$html .= ' · Updated <time datetime="' . esc_attr( $modified ) . '">' . esc_html( $modified ) . '</time>';
		}
		$body = function_exists( 'kpopblog_render_article_content' ) ? kpopblog_render_article_content( $post ) : apply_filters( 'the_content', $post->post_content );
		$html .= '</p><div>' . wp_kses_post( $body ) . '</div>';
		$html .= kpopblog_render_public_sources( $post->ID );
		return $html . '</article>';
	}
	if ( 'comebacks' === ( $context['kind'] ?? '' ) ) {
		$html = '<section data-kpopblog-fallback="comebacks"><h1>Comeback Schedule</h1>';
		foreach ( $context['posts'] as $post ) {
			$release = (string) get_post_meta( $post->ID, 'kb_release_at', true );
			if ( ! kpopblog_is_strict_iso8601_date( $release ) ) {
				continue;
			}
			$html .= '<article id="event-' . (int) $post->ID . '"><h2>' . esc_html( get_the_title( $post ) ) . '</h2>';
			$artist_slug = sanitize_title( (string) get_post_meta( $post->ID, 'kb_artist_slug', true ) );
			if ( '' !== $artist_slug ) {
				$html .= '<p><a href="' . esc_url( home_url( '/artist/' . $artist_slug ) ) . '">' . esc_html( $artist_slug ) . '</a></p>';
			}
			$html .= '<p>' . esc_html( (string) get_post_meta( $post->ID, 'kb_type', true ) ) . ' · <time datetime="' . esc_attr( $release ) . '">' . esc_html( $release ) . '</time></p>';
			$html .= '<div>' . wp_kses_post( apply_filters( 'the_content', $post->post_content ) ) . '</div>';
			$html .= kpopblog_render_public_sources( $post->ID ) . '</article>';
		}
		return $html . '</section>';
	}
	return '';
}

function kpopblog_xml_escape( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8' );
}

function kpopblog_private_robots_paths() {
	return array(
		'Disallow: /wp-admin/',
		'Disallow: /wp-login.php',
		'Disallow: /admin',
		'Disallow: /moderation',
		'Disallow: /onboarding',
		'Disallow: /login',
		'Disallow: /signup',
		'Disallow: /forgot-password',
		'Disallow: /submit',
		'Disallow: /bookmarks',
		'Disallow: /cookie-settings',
		'Disallow: /wp-json/kpopblog/v1/admin',
		'Disallow: /wp-json/kpopblog/v1/auth',
		'Disallow: /wp-json/kpopblog/v1/profile/me',
		'Disallow: /wp-json/kpopblog/v1/submissions',
		'Disallow: /wp-json/kpopblog/v1/reports',
		'Disallow: /wp-json/kpopblog/v1/newsletter/subscribe',
		'Disallow: /wp-json/kpopblog/v1/newsletter/confirm',
		'Disallow: /wp-json/kpopblog/v1/newsletter/unsubscribe',
		'Disallow: /wp-json/kpopblog/v1/polls/*/vote',
		'Disallow: /wp-json/kpopblog/v1/artists/*/follow',
		'Disallow: /wp-json/kpopblog/v1/articles/*/engage',
		'Disallow: /wp-json/kpopblog/v1/articles/*/comments',
		'Disallow: /wp-json/kpopblog/v1/videos/*/comments',
		'Disallow: /wp-json/kpopblog/v1/threads',
		'Disallow: /wp-json/kpopblog/v1/community',
		'Disallow: /wp-json/kpopblog/v1/subscriptions',
		'Disallow: /wp-json/kpopblog/v1/notifications',
		'Disallow: /wp-json/kpopblog/v1/events',
		'Disallow: /wp-json/kpopblog/v1/moderation',
	);
}

function kpopblog_render_robots() {
	$private_paths = kpopblog_private_robots_paths();
	$lines = array( 'User-agent: *', 'Allow: /' );
	$lines = array_merge( $lines, $private_paths, array( '' ) );
	$agents = array( 'OAI-SearchBot', 'GPTBot', 'ClaudeBot', 'Claude-SearchBot', 'Claude-User', 'PerplexityBot', 'Perplexity-User', 'Google-Extended' );
	foreach ( $agents as $agent ) {
		$lines[] = 'User-agent: ' . $agent;
		$lines[] = 'Allow: /';
		$lines = array_merge( $lines, $private_paths );
		$lines[] = '';
	}
	$lines[] = 'Sitemap: ' . esc_url_raw( home_url( '/sitemap.xml' ) );
	$lines[] = 'Sitemap: ' . esc_url_raw( home_url( '/news-sitemap.xml' ) );
	$lines[] = '# RSS: ' . esc_url_raw( home_url( '/rss.xml' ) );
	$lines[] = '# LLMs: ' . esc_url_raw( home_url( '/llms.txt' ) );
	return implode( "\n", $lines ) . "\n";
}

function kpopblog_render_sitemap() {
	$paths = array( '/', '/latest', '/trending', '/artists', '/videos', '/charts', '/comebacks', '/polls', '/community', '/forum', '/newsletter', '/about', '/contact', '/advertise', '/privacy', '/terms', '/copyright', '/corrections', '/community-guidelines' );
	$comebacks = kpopblog_public_posts( 'kb_comeback', 1 );
	$comebacks_modified = $comebacks ? (string) $comebacks[0]->post_modified_gmt : '';
	$entries = array_map(
		function ( $entry_path ) use ( $comebacks_modified ) {
			return array(
				'path'     => $entry_path,
				'modified' => '/comebacks' === $entry_path ? $comebacks_modified : '',
			);
		},
		$paths
	);
	$content_types = array(
		'post'      => '/news/',
		'kb_artist' => '/artist/',
		'kb_member' => '/member/',
		'kb_video'  => '/watch/',
		'kb_poll'   => '/polls/',
		'kb_thread' => '/thread/',
	);
	foreach ( $content_types as $post_type => $prefix ) {
		$posts = kpopblog_public_posts( $post_type, 2000 );
		foreach ( $posts as $post ) {
			if ( '' === (string) $post->post_name ) {
				continue;
			}
			$identifier = 'kb_video' === $post_type ? (string) $post->ID : $post->post_name;
			$entries[] = array( 'path' => $prefix . $identifier, 'modified' => (string) $post->post_modified_gmt );
		}
	}

	$output = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	$output .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
	foreach ( $entries as $entry ) {
		$output .= '<url><loc>' . kpopblog_xml_escape( home_url( $entry['path'] ) ) . '</loc>';
		if ( '' !== $entry['modified'] ) {
			$output .= '<lastmod>' . kpopblog_xml_escape( mysql2date( 'c', $entry['modified'], false ) ) . '</lastmod>';
		}
		$output .= '</url>' . "\n";
	}
	return $output . '</urlset>';
}

function kpopblog_bounded_discovery_description( $value, $limit = 700 ) {
	$value = preg_replace( '/\s+/', ' ', trim( wp_strip_all_tags( (string) $value, true ) ) );
	return wp_html_excerpt( $value, max( 1, (int) $limit - 1 ), '…' );
}

function kpopblog_discovery_description_with_sources( $description, array $source_urls, $limit = 700 ) {
	$limit = max( 100, (int) $limit );
	$sources = array();
	foreach ( $source_urls as $source_url ) {
		$candidate = $sources;
		$candidate[] = $source_url;
		$suffix = ' Sources: ' . implode( ', ', $candidate ) . '.';
		if ( strlen( $suffix ) > $limit - 100 ) { break; }
		$sources = $candidate;
	}
	$suffix = empty( $sources ) ? '' : ' Sources: ' . implode( ', ', $sources ) . '.';
	$body = kpopblog_bounded_discovery_description( $description, $limit - strlen( $suffix ) );
	return kpopblog_bounded_discovery_description( $body . $suffix, $limit );
}

function kpopblog_public_schedule_candidates( $limit = 50 ) {
	$limit = max( 1, (int) $limit );
	$candidates = array();
	$page = 1;
	do {
		$query = new WP_Query( array(
			'post_type'              => 'kb_comeback',
			'post_status'            => 'publish',
			'has_password'           => false,
			'posts_per_page'         => 500,
			'paged'                  => $page,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => false,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		) );
		foreach ( $query->posts as $post ) {
			$release_at = (string) kpopblog_meta( $post->ID, 'kb_release_at' );
			if ( ! kpopblog_is_strict_iso8601_date( $release_at ) ) { continue; }
			$timestamp = strtotime( $release_at );
			if ( false === $timestamp ) { continue; }
			$candidates[] = array( 'post' => $post, 'timestamp' => $timestamp );
		}
		usort( $candidates, function ( $left, $right ) {
			if ( $right['timestamp'] === $left['timestamp'] ) { return $right['post']->ID <=> $left['post']->ID; }
			return $right['timestamp'] <=> $left['timestamp'];
		} );
		$candidates = array_slice( $candidates, 0, $limit );
		$page++;
	} while ( $page <= (int) $query->max_num_pages );
	return $candidates;
}

function kpopblog_render_rss() {
	$articles = kpopblog_public_posts( 'post', 50, 'date' );
	$schedules = kpopblog_public_schedule_candidates( 50 );
	$items = array();
	$output = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	$output .= '<rss version="2.0"><channel>' . "\n";
	$output .= '<title>' . kpopblog_xml_escape( get_bloginfo( 'name' ) ) . '</title>' . "\n";
	$output .= '<link>' . kpopblog_xml_escape( home_url( '/' ) ) . '</link>' . "\n";
	$output .= '<description>' . kpopblog_xml_escape( get_bloginfo( 'description' ) ) . '</description>' . "\n";
	$output .= '<language>' . kpopblog_xml_escape( get_bloginfo( 'language' ) ?: 'en' ) . '</language>' . "\n";

	foreach ( $articles as $post ) {
		if ( '' === (string) $post->post_name ) {
			continue;
		}
		$category = (string) kpopblog_meta( $post->ID, 'kb_category_slug', 'news' );
		$description = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 );
		$source_urls = kpopblog_public_source_urls( $post->ID );
		$description = kpopblog_discovery_description_with_sources( $description, $source_urls );
		$timestamp = strtotime( $post->post_date_gmt . ' UTC' );
		$items[] = array( 'timestamp' => false === $timestamp ? 0 : $timestamp, 'title' => get_the_title( $post ), 'link' => home_url( '/news/' . $post->post_name ), 'guid' => 'post-' . $post->ID, 'description' => $description, 'category' => $category );
	}

	foreach ( $schedules as $schedule ) {
		$post = $schedule['post'];
		$release_at = (string) kpopblog_meta( $post->ID, 'kb_release_at' );
		$type = (string) kpopblog_meta( $post->ID, 'kb_type', 'album' );
		$description = 'Release: ' . $release_at . '; Type: ' . $type;
		$content = wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 );
		if ( '' !== $content ) {
			$description .= '. ' . $content;
		}
		$source_urls = kpopblog_public_source_urls( $post->ID );
		$description = kpopblog_discovery_description_with_sources( $description, $source_urls );
		$items[] = array( 'timestamp' => $schedule['timestamp'], 'title' => get_the_title( $post ), 'link' => home_url( '/comebacks#event-' . $post->ID ), 'guid' => 'comeback-' . $post->ID, 'description' => $description, 'category' => $type );
	}
	usort( $items, function ( $left, $right ) { return $right['timestamp'] <=> $left['timestamp']; } );
	foreach ( array_slice( $items, 0, 50 ) as $item ) {
		$output .= '<item><title>' . kpopblog_xml_escape( $item['title'] ) . '</title>';
		$output .= '<link>' . kpopblog_xml_escape( $item['link'] ) . '</link>';
		$output .= '<guid isPermaLink="false">' . kpopblog_xml_escape( $item['guid'] ) . '</guid>';
		$output .= '<pubDate>' . kpopblog_xml_escape( gmdate( DATE_RSS, $item['timestamp'] ) ) . '</pubDate>';
		$output .= '<description>' . kpopblog_xml_escape( $item['description'] ) . '</description>';
		$output .= '<category>' . kpopblog_xml_escape( $item['category'] ) . '</category></item>' . "\n";
	}

	return $output . '</channel></rss>';
}

function kpopblog_llms_json_entry( WP_Post $post, $url ) {
	$title = preg_replace( '/([\\`*_{\}\[\]()#+\-.!|>])/', '\\\\$1', sanitize_text_field( get_the_title( $post ) ) );
	$description = preg_replace( '/([\\`*_{\}\[\]()#+\-.!|>])/', '\\\\$1', kpopblog_bounded_discovery_description( kpopblog_public_description( $post ), 300 ) );
	return wp_json_encode( array( 'title' => $title, 'url' => esc_url_raw( $url ), 'description' => $description, 'sources' => kpopblog_public_source_urls( $post->ID ) ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

function kpopblog_render_llms() {
	$site_title = sanitize_text_field( get_bloginfo( 'name' ) );
	$description = sanitize_text_field( get_bloginfo( 'description' ) );
	$lines = array(
		'# ' . $site_title,
		'',
		'> ' . $description,
		'',
		'- Site: ' . esc_url_raw( home_url( '/' ) ),
		'- Sitemap: ' . esc_url_raw( home_url( '/sitemap.xml' ) ),
		'- RSS: ' . esc_url_raw( home_url( '/rss.xml' ) ),
		'- Latest news: ' . esc_url_raw( home_url( '/latest' ) ),
		'- Artists: ' . esc_url_raw( home_url( '/artists' ) ),
		'- Comebacks: ' . esc_url_raw( home_url( '/comebacks' ) ),
		'',
		'## Latest articles',
	);
	foreach ( kpopblog_public_posts( 'post', 50 ) as $post ) {
		if ( '' === (string) $post->post_name ) {
			continue;
		}
		$lines[] = '- ' . kpopblog_llms_json_entry( $post, home_url( '/news/' . $post->post_name ) );
	}
	$lines[] = '';
	$lines[] = '## Artists';
	foreach ( kpopblog_public_posts( 'kb_artist', 100, 'title', array( 'order' => 'ASC' ) ) as $post ) {
		$facts = array_filter( array(
			(string) kpopblog_meta( $post->ID, 'kb_agency' ),
			(string) kpopblog_meta( $post->ID, 'kb_debut_date' ) ? 'debut ' . kpopblog_meta( $post->ID, 'kb_debut_date' ) : '',
			(string) kpopblog_meta( $post->ID, 'kb_fandom_name' ) ? 'fandom ' . kpopblog_meta( $post->ID, 'kb_fandom_name' ) : '',
		) );
		$lines[] = '- [' . get_the_title( $post ) . '](' . esc_url_raw( home_url( '/artist/' . $post->post_name ) ) . ')' . ( $facts ? ': ' . implode( '; ', $facts ) : '' );
	}
	$lines[] = '';
	$lines[] = '## Comeback schedule';
	foreach ( kpopblog_public_posts( 'kb_comeback', 50 ) as $post ) {
		$release_at = (string) kpopblog_meta( $post->ID, 'kb_release_at' );
		if ( ! kpopblog_is_strict_iso8601_date( $release_at ) ) {
			continue;
		}
		$lines[] = '- ' . kpopblog_llms_json_entry( $post, home_url( '/comebacks#event-' . $post->ID ) );
	}
	return implode( "\n", $lines ) . "\n";
}

function kpopblog_discovery_last_modified() {
	$latest = '';
	$latest_timestamp = false;
	foreach ( kpopblog_discovery_post_types() as $post_type ) {
		$posts = kpopblog_public_posts( $post_type, 1 );
		if ( ! $posts || '' === (string) $posts[0]->post_modified_gmt ) {
			continue;
		}
		$modified = (string) $posts[0]->post_modified_gmt;
		$timestamp = strtotime( $modified . ' UTC' );
		if ( false !== $timestamp && ( false === $latest_timestamp || $timestamp > $latest_timestamp ) ) {
			$latest = $modified;
			$latest_timestamp = $timestamp;
		}
	}
	$revision_timestamp = max( (int) get_option( 'kpopblog_discovery_revision', 0 ), (int) filemtime( __FILE__ ) );
	if ( false === $latest_timestamp || $revision_timestamp > $latest_timestamp ) {
		$latest_timestamp = $revision_timestamp;
	}
	return gmdate( 'Y-m-d H:i:s', $latest_timestamp ?: time() );
}

function kpopblog_render_manifest() {
	$design = function_exists( 'kpopblog_get_design_settings' ) ? kpopblog_get_design_settings() : array();
	$site_name   = ! empty( $design['site_name'] ) ? (string) $design['site_name'] : 'Kpop';
	$accent_word = isset( $design['accent_word'] ) ? (string) $design['accent_word'] : 'Blog';
	$brand       = trim( $site_name . $accent_word );
	$tagline     = ! empty( $design['tagline'] ) ? (string) $design['tagline'] : 'K-pop news, artist profiles, comeback calendar, polls and a global fan community.';
	$accent      = ! empty( $design['accent_color'] ) ? (string) $design['accent_color'] : '#0b0b10';
	$icons_base  = KPOPBLOG_URL . 'icons/';

	$manifest = array(
		'name'             => $brand . ' — Global K-pop newsroom',
		'short_name'       => $brand,
		'description'      => $tagline,
		'id'               => home_url( '/' ),
		'start_url'        => home_url( '/' ),
		'scope'            => home_url( '/' ),
		'display'          => 'standalone',
		'background_color' => '#0b0b10',
		'theme_color'      => $accent,
		'orientation'      => 'portrait',
		'icons'            => array(
			array( 'src' => $icons_base . 'icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable' ),
			array( 'src' => $icons_base . 'icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable' ),
		),
	);
	return (string) wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

function kpopblog_serve_machine_endpoint() {
	$renderers = array(
		'/robots.txt'          => array( 'text/plain; charset=utf-8', 'kpopblog_render_robots' ),
		'/sitemap.xml'         => array( 'application/xml; charset=utf-8', 'kpopblog_render_sitemap' ),
		'/rss.xml'             => array( 'application/rss+xml; charset=utf-8', 'kpopblog_render_rss' ),
		'/llms.txt'            => array( 'text/plain; charset=utf-8', 'kpopblog_render_llms' ),
		'/manifest.webmanifest'=> array( 'application/manifest+json; charset=utf-8', 'kpopblog_render_manifest' ),
	);
	$path = kpopblog_request_path();
	if ( ! isset( $renderers[ $path ] ) ) { return; }
	$payload = call_user_func( $renderers[ $path ][1] );
	$last_modified = kpopblog_discovery_last_modified();
	$last_timestamp = strtotime( $last_modified . ' UTC' );
	$etag = '"' . hash( 'sha256', 'kpopblog-discovery-v2|' . $path . '|' . $payload ) . '"';
	header( 'Content-Type: ' . $renderers[ $path ][0] );
	header( 'Cache-Control: public, max-age=0, must-revalidate' );
	header( 'ETag: ' . $etag );
	header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $last_timestamp ) . ' GMT' );
	$if_none_match = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? trim( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) : '';
	$if_modified_since = isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ? strtotime( wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) : false;
	$etag_matches = false;
	foreach ( explode( ',', $if_none_match ) as $candidate ) {
		$candidate = trim( $candidate );
		if ( 0 === strpos( $candidate, 'W/' ) ) { $candidate = substr( $candidate, 2 ); }
		$candidate = preg_replace( '/-gzip"$/', '"', $candidate );
		if ( '*' === $candidate || $etag === $candidate ) { $etag_matches = true; break; }
	}
	if ( '' !== $if_none_match && $etag_matches ) {
		status_header( 304 );
		header( 'Content-Type: ' . $renderers[ $path ][0] );
		header( 'Cache-Control: public, max-age=0, must-revalidate' );
		exit;
	}
	if ( '' === $if_none_match && false !== $if_modified_since && $if_modified_since >= $last_timestamp ) {
		status_header( 304 );
		header( 'Content-Type: ' . $renderers[ $path ][0] );
		header( 'Cache-Control: public, max-age=0, must-revalidate' );
		exit;
	}
	status_header( 200 );
	echo $payload;
	exit;
}
add_action( 'template_redirect', 'kpopblog_serve_machine_endpoint', 0 );
