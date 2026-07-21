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
		'Disallow: /wp-json/kpopblog/v1/notifications',
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
		$output .= '<item><title>' . kpopblog_xml_escape( get_the_title( $post ) ) . '</title>';
		$output .= '<link>' . kpopblog_xml_escape( home_url( '/news/' . $post->post_name ) ) . '</link>';
		$output .= '<guid isPermaLink="false">' . kpopblog_xml_escape( 'post-' . $post->ID ) . '</guid>';
		$output .= '<pubDate>' . kpopblog_xml_escape( mysql2date( DATE_RSS, $post->post_date_gmt, false ) ) . '</pubDate>';
		$output .= '<description>' . kpopblog_xml_escape( has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 ) ) . '</description>';
		$output .= '<category>' . kpopblog_xml_escape( $category ) . '</category></item>' . "\n";
	}

	foreach ( $schedules as $post ) {
		$release_at = (string) kpopblog_meta( $post->ID, 'kb_release_at' );
		if ( '' === $release_at || false === strtotime( $release_at ) ) {
			continue;
		}
		$type = (string) kpopblog_meta( $post->ID, 'kb_type', 'album' );
		$description = 'Release: ' . $release_at . '; Type: ' . $type;
		$content = wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 );
		if ( '' !== $content ) {
			$description .= '. ' . $content;
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
	}
	$lines[] = '';
	$lines[] = '## Comeback schedule';
	foreach ( kpopblog_public_posts( 'kb_comeback', 50 ) as $post ) {
		$lines[] = '- [' . sanitize_text_field( get_the_title( $post ) ) . '](' . esc_url_raw( home_url( '/comebacks#event-' . $post->ID ) ) . ')';
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
