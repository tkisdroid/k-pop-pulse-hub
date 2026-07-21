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

function kpopblog_public_iso8601_date( $value ) {
	$value = (string) $value;
	$date_pattern = '/^\d{4}-\d{2}-\d{2}\z/';
	$datetime_pattern = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d{1,6})?)?(?:Z|[+-]\d{2}:\d{2})\z/';
	if ( ! preg_match( $date_pattern, $value ) && ! preg_match( $datetime_pattern, $value ) ) {
		return false;
	}
	$parsed = date_parse( $value );
	return 0 === $parsed['error_count'] && 0 === $parsed['warning_count'];
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
			'author'           => array( '@type' => 'Person', 'name' => $author ? $author->display_name : get_bloginfo( 'name' ) ),
			'publisher'        => array( '@type' => 'Organization', 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) ),
			'citation'         => kpopblog_public_source_urls( $post->ID ),
		);
		$image = kpopblog_thumb_url( $post->ID );
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
		if ( ! kpopblog_public_iso8601_date( $release ) ) {
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
	if ( 'missing_article' === ( $context['kind'] ?? '' ) ) {
		return 'Article not found — ' . get_bloginfo( 'name' );
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
	if ( 'article' === $kind ) {
		$post = $context['post'];
		$title = get_the_title( $post ) . ' — ' . get_bloginfo( 'name' );
		$description = kpopblog_public_description( $post );
		$canonical = home_url( '/news/' . $post->post_name );
		$open_graph_type = 'article';
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
	echo kpopblog_render_public_json_ld( $json_ld );
}
add_action( 'wp_head', 'kpopblog_render_public_head' );

function kpopblog_render_public_fallback() {
	$context = kpopblog_get_public_context();
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
		$html .= '</p><div>' . wp_kses_post( apply_filters( 'the_content', $post->post_content ) ) . '</div>';
		$html .= kpopblog_render_public_sources( $post->ID );
		return $html . '</article>';
	}
	if ( 'comebacks' === ( $context['kind'] ?? '' ) ) {
		$html = '<section data-kpopblog-fallback="comebacks"><h1>Comeback Schedule</h1>';
		foreach ( $context['posts'] as $post ) {
			$release = (string) get_post_meta( $post->ID, 'kb_release_at', true );
			if ( ! kpopblog_public_iso8601_date( $release ) ) {
				continue;
			}
			$html .= '<article id="event-' . (int) $post->ID . '"><h2>' . esc_html( get_the_title( $post ) ) . '</h2>';
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

function kpopblog_render_robots() {
	$private_paths = array(
		'Disallow: /wp-admin/',
		'Disallow: /wp-login.php',
		'Disallow: /admin',
		'Disallow: /moderation',
		'Disallow: /onboarding',
		'Disallow: /wp-json/kpopblog/v1/admin',
		'Disallow: /wp-json/kpopblog/v1/auth',
		'Disallow: /wp-json/kpopblog/v1/subscriptions',
		'Disallow: /wp-json/kpopblog/v1/notifications',
		'Disallow: /wp-json/kpopblog/v1/events',
		'Disallow: /wp-json/kpopblog/v1/moderation',
	);
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

function kpopblog_render_rss() {
	$articles = kpopblog_public_posts( 'post', 50, 'date' );
	$schedules = kpopblog_public_posts( 'kb_comeback', 50, 'date' );
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
		if ( ! empty( $source_urls ) ) {
			$description .= ' Sources: ' . implode( ', ', $source_urls ) . '.';
		}
		$output .= '<item><title>' . kpopblog_xml_escape( get_the_title( $post ) ) . '</title>';
		$output .= '<link>' . kpopblog_xml_escape( home_url( '/news/' . $post->post_name ) ) . '</link>';
		$output .= '<guid isPermaLink="false">' . kpopblog_xml_escape( 'post-' . $post->ID ) . '</guid>';
		$output .= '<pubDate>' . kpopblog_xml_escape( mysql2date( DATE_RSS, $post->post_date_gmt, false ) ) . '</pubDate>';
		$output .= '<description>' . kpopblog_xml_escape( $description ) . '</description>';
		$output .= '<category>' . kpopblog_xml_escape( $category ) . '</category></item>' . "\n";
	}

	foreach ( $schedules as $post ) {
		$release_at = (string) kpopblog_meta( $post->ID, 'kb_release_at' );
		if ( ! kpopblog_public_iso8601_date( $release_at ) ) {
			continue;
		}
		$type = (string) kpopblog_meta( $post->ID, 'kb_type', 'album' );
		$description = 'Release: ' . $release_at . '; Type: ' . $type;
		$content = wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 );
		if ( '' !== $content ) {
			$description .= '. ' . $content;
		}
		$source_urls = kpopblog_public_source_urls( $post->ID );
		if ( ! empty( $source_urls ) ) {
			$description .= ' Sources: ' . implode( ', ', $source_urls ) . '.';
		}
		$output .= '<item><title>' . kpopblog_xml_escape( get_the_title( $post ) ) . '</title>';
		$output .= '<link>' . kpopblog_xml_escape( home_url( '/comebacks#event-' . $post->ID ) ) . '</link>';
		$output .= '<guid isPermaLink="false">' . kpopblog_xml_escape( 'comeback-' . $post->ID ) . '</guid>';
		$output .= '<pubDate>' . kpopblog_xml_escape( mysql2date( DATE_RSS, $post->post_date_gmt, false ) ) . '</pubDate>';
		$output .= '<description>' . kpopblog_xml_escape( $description ) . '</description>';
		$output .= '<category>' . kpopblog_xml_escape( $type ) . '</category></item>' . "\n";
	}

	return $output . '</channel></rss>';
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
		$lines[] = '- [' . sanitize_text_field( get_the_title( $post ) ) . '](' . esc_url_raw( home_url( '/news/' . $post->post_name ) ) . ')';
		$source_urls = kpopblog_public_source_urls( $post->ID );
		if ( ! empty( $source_urls ) ) {
			$lines[] = '  - Sources: <' . implode( '>, <', $source_urls ) . '>';
		}
	}
	$lines[] = '';
	$lines[] = '## Comeback schedule';
	foreach ( kpopblog_public_posts( 'kb_comeback', 50 ) as $post ) {
		$release_at = (string) kpopblog_meta( $post->ID, 'kb_release_at' );
		if ( ! kpopblog_public_iso8601_date( $release_at ) ) {
			continue;
		}
		$lines[] = '- [' . sanitize_text_field( get_the_title( $post ) ) . '](' . esc_url_raw( home_url( '/comebacks#event-' . $post->ID ) ) . ')';
		$source_urls = kpopblog_public_source_urls( $post->ID );
		if ( ! empty( $source_urls ) ) {
			$lines[] = '  - Sources: <' . implode( '>, <', $source_urls ) . '>';
		}
	}
	return implode( "\n", $lines ) . "\n";
}

function kpopblog_discovery_last_modified() {
	$latest = '';
	$latest_timestamp = false;
	foreach ( array( 'post', 'kb_artist', 'kb_member', 'kb_video', 'kb_poll', 'kb_thread', 'kb_comeback' ) as $post_type ) {
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
	return '' !== $latest ? $latest : gmdate( 'Y-m-d H:i:s' );
}

function kpopblog_serve_machine_endpoint() {
	$renderers = array(
		'/robots.txt'  => array( 'text/plain; charset=utf-8', 'kpopblog_render_robots' ),
		'/sitemap.xml' => array( 'application/xml; charset=utf-8', 'kpopblog_render_sitemap' ),
		'/rss.xml'     => array( 'application/rss+xml; charset=utf-8', 'kpopblog_render_rss' ),
		'/llms.txt'    => array( 'text/plain; charset=utf-8', 'kpopblog_render_llms' ),
	);
	$path = kpopblog_request_path();
	if ( ! isset( $renderers[ $path ] ) ) { return; }
	$last_modified = kpopblog_discovery_last_modified();
	$last_timestamp = strtotime( $last_modified . ' UTC' );
	$etag = '"' . md5( $path . '|' . $last_modified ) . '"';
	header( 'ETag: ' . $etag );
	header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $last_timestamp ) . ' GMT' );
	$if_none_match = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? trim( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) : '';
	$if_none_match = preg_replace( '/-gzip"$/', '"', $if_none_match );
	$if_modified_since = isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ? strtotime( wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) : false;
	if ( $etag === $if_none_match || ( false !== $if_modified_since && $if_modified_since >= $last_timestamp ) ) {
		status_header( 304 );
		exit;
	}
	status_header( 200 );
	header( 'Content-Type: ' . $renderers[ $path ][0] );
	header( 'Cache-Control: public, max-age=300, must-revalidate' );
	echo call_user_func( $renderers[ $path ][1] );
	exit;
}
add_action( 'template_redirect', 'kpopblog_serve_machine_endpoint', 0 );
