<?php
/** Public route coverage and metadata shared by first HTML and SPA navigation. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function kpopblog_seo_collection( $path, $title, $description, $html, $schema = array() ) {
	$url = home_url( $path );
	return array( 'kind' => 'page', 'title' => $title . ' — ' . kpopblog_seo_site_name(), 'description' => kpopblog_seo_text( $description, 180 ), 'canonical' => $url, 'og_type' => 'website', 'image' => '', 'json_ld' => $schema ?: array( '@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => $title, 'url' => $url ), 'html' => '<section data-kpopblog-fallback="page"><h1>' . esc_html( $title ) . '</h1>' . $html . '</section>' );
}

function kpopblog_seo_extra_context( $path ) {
	static $pages = null;
	if ( null === $pages ) { $pages = json_decode( (string) file_get_contents( KPOPBLOG_PATH . 'data/site-pages.json' ), true ) ?: array(); }
	if ( isset( $pages[ $path ] ) ) {
		$page = $pages[ $path ];
		$context = kpopblog_seo_collection( $path, html_entity_decode( $page['title'], ENT_QUOTES, 'UTF-8' ), html_entity_decode( $page['description'], ENT_QUOTES, 'UTF-8' ), '' );
		$context['html'] = '<article data-kpopblog-fallback="static">' . wp_kses_post( $page['html'] ) . '</article>';
		$context['json_ld']['@type'] = '/about' === $path ? 'AboutPage' : ( '/contact' === $path ? 'ContactPage' : 'WebPage' );
		return $context;
	}
	if ( '/videos' === $path || '/polls' === $path ) {
		$is_video = '/videos' === $path;
		$posts = kpopblog_public_posts( $is_video ? 'kb_video' : 'kb_poll', 100 );
		$title = $is_video ? 'K-pop Videos' : 'Fan Polls';
		$description = $is_video ? 'Watch K-pop music videos and performances, explore artists, and join the conversation.' : 'Vote in K-pop fan polls and explore community opinions about artists and upcoming releases.';
		$context = kpopblog_seo_collection( $path, $title, $description, '<p>' . esc_html( $description ) . '</p>' . kpopblog_seo_link_list( $posts, $is_video ? '/watch/' : '/polls/', true ) );
		$context['json_ld']['mainEntity'] = kpopblog_seo_item_list( $posts, $is_video ? '/watch/' : '/polls/' );
		return $context;
	}
	if ( '/charts' === $path ) {
		$html = '<p>Explore the published K-pop chart rankings, tracks, and artists.</p>';
		foreach ( kpopblog_public_posts( 'kb_chart', 5 ) as $post ) {
			$chart = kpopblog_map_chart( $post );
			$html .= '<h2>' . esc_html( $chart['title'] ) . '</h2><p>' . esc_html( $chart['weekStartDate'] ) . '</p><ol>';
			foreach ( $chart['entries'] as $entry ) { $html .= '<li>' . esc_html( $entry['trackTitle'] ) . ' — <a href="' . esc_url( home_url( '/artist/' . $entry['artistSlug'] ) ) . '">' . esc_html( $entry['artistSlug'] ) . '</a></li>'; }
			$html .= '</ol>';
		}
		return kpopblog_seo_collection( $path, 'K-pop Charts', 'Published K-pop chart rankings, tracks, and artists.', $html );
	}
	if ( '/community' === $path ) {
		$html = '<p>Share K-pop moments and join the fan community.</p>';
		foreach ( kpopblog_public_posts( 'kb_community', 30 ) as $post ) { $html .= '<article><p>' . esc_html( wp_strip_all_tags( $post->post_content ) ) . '</p></article>'; }
		return kpopblog_seo_collection( $path, 'Community Wall', 'Share K-pop moments, artist updates, and fan conversations in the KpopBlog community.', $html );
	}
	if ( preg_match( '#^/member/([^/]+)$#', $path, $m ) ) {
		$posts = get_posts( array( 'post_type' => 'kb_member', 'name' => sanitize_title( rawurldecode( $m[1] ) ), 'post_status' => 'publish', 'has_password' => false, 'numberposts' => 1 ) );
		if ( ! $posts ) { return array( 'kind' => 'missing_article' ); }
		$member = kpopblog_map_member( $posts[0] );
		$description = $member['stageName'] . ( $member['position'] ? ' — ' . implode( ', ', $member['position'] ) : '' ) . '. ' . wp_strip_all_tags( $posts[0]->post_content );
		$html = '<p>' . esc_html( $description ) . '</p>';
		$person = array( '@type' => 'Person', '@id' => home_url( $path . '#member' ), 'name' => $member['stageName'], 'url' => home_url( $path ) );
		if ( $member['fullName'] ) { $person['alternateName'] = $member['fullName']; $html .= '<p>Full name: ' . esc_html( $member['fullName'] ) . '</p>'; }
		if ( $member['birthday'] ) { $person['birthDate'] = $member['birthday']; $html .= '<p>Birthday: ' . esc_html( $member['birthday'] ) . '</p>'; }
		if ( $member['image'] && ! preg_match( '/\.svg(?:\?|$)/', $member['image'] ) ) { $person['image'] = $member['image']; }
		if ( $member['groupId'] ) { $html .= '<p><a href="' . esc_url( home_url( '/artist/' . $member['groupId'] ) ) . '">Explore ' . esc_html( $member['groupId'] ) . '</a></p>'; }
		$context = kpopblog_seo_collection( $path, $member['stageName'] . ' — Member Profile', $description, $html, array( '@context' => 'https://schema.org', '@type' => 'ProfilePage', 'url' => home_url( $path ), 'mainEntity' => $person ) );
		$context['image'] = $member['image'];
		return $context;
	}
	if ( preg_match( '#^/(tag|category)/([^/]+)$#', $path, $m ) ) {
		$slug = sanitize_title( rawurldecode( $m[2] ) );
		$taxonomy = 'tag' === $m[1] ? 'post_tag' : 'category';
		$term = get_term_by( 'slug', $slug, $taxonomy );
		$tax = $term ? array( array( 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $term->term_id ) ) : array();
		$args = array( 'post_type' => 'post', 'post_status' => 'publish', 'has_password' => false, 'numberposts' => 50 );
		if ( 'category' === $m[1] ) { $args['meta_query'] = array( array( 'key' => 'kb_category_slug', 'value' => $slug ) ); }
		elseif ( $tax ) { $args['tax_query'] = $tax; }
		else { return array( 'kind' => 'missing_article' ); }
		$posts = get_posts( $args );
		if ( ! $term && ! $posts ) { return array( 'kind' => 'missing_article' ); }
		$name = $term ? $term->name : ucfirst( $slug );
		return kpopblog_seo_collection( $path, ( 'tag' === $m[1] ? '#' : '' ) . $name, 'K-pop news and stories about ' . $name . '.', kpopblog_seo_link_list( $posts, '/news/', true ) );
	}

	// Account, search, token-bearing newsletter, and game pages are usable but not indexed.
	$utility = array( '/login' => 'Login', '/signup' => 'Sign up', '/forgot-password' => 'Forgot password', '/admin' => 'Admin', '/moderation' => 'Moderation', '/onboarding' => 'Get started', '/submit' => 'Submit', '/bookmarks' => 'Bookmarks', '/cookie-settings' => 'Cookie Settings', '/search' => 'Search', '/newsletter' => 'Newsletter', '/quiz' => 'K-pop Quiz', '/quiz/play' => 'K-pop Quiz', '/quiz/results' => 'Quiz Results', '/games' => 'K-pop Games' );
	if ( isset( $utility[ $path ] ) || preg_match( '#^/(profile|author)/[^/]+$#', $path ) ) {
		$title = $utility[ $path ] ?? 'Community profile';
		$context = kpopblog_seo_collection( $path, $title, $title . ' on ' . kpopblog_seo_site_name() . '.', '' );
		$context['noindex'] = true;
		$context['json_ld'] = array();
		return $context;
	}
	return array( 'kind' => 'missing_article' );
}

/** Use the same public data for metadata on the server and after client navigation. */
function kpopblog_context_for_path( $path ) {
	$path = '/' === $path ? '/' : untrailingslashit( $path );
	if ( preg_match( '#^/news/([^/]+)$#', $path, $m ) ) {
		$post = get_page_by_path( sanitize_title( rawurldecode( $m[1] ) ), OBJECT, 'post' );
		return $post instanceof WP_Post && 'publish' === $post->post_status && '' === $post->post_password ? array( 'kind' => 'article', 'post' => $post ) : array( 'kind' => 'missing_article' );
	}
	if ( '/comebacks' === $path ) { return array( 'kind' => 'comebacks', 'posts' => kpopblog_public_posts( 'kb_comeback', 200, 'meta_value', array( 'meta_key' => 'kb_release_at', 'order' => 'ASC' ) ) ); }
	return kpopblog_seo_page_context( $path );
}

function kpopblog_seo_payload( array $context ) {
	$kind = $context['kind'] ?? 'missing_article';
	$payload = array( 'title' => 'Page not found — ' . kpopblog_seo_site_name(), 'description' => 'This page could not be found.', 'canonical' => '', 'og_type' => 'website', 'image' => KPOPBLOG_URL . 'icons/og-default.jpg', 'noindex' => true, 'json_ld' => array() );
	if ( 'page' === $kind ) { $payload = array_merge( $payload, array_intersect_key( $context, $payload ), array( 'noindex' => ! empty( $context['noindex'] ) ) ); }
	elseif ( 'article' === $kind ) {
		$post = $context['post'];
		$payload = array_merge( $payload, array( 'title' => kpopblog_decode_text_entities( get_the_title( $post ) ) . ' — ' . kpopblog_seo_site_name(), 'description' => kpopblog_public_description( $post ), 'canonical' => home_url( '/news/' . $post->post_name ), 'og_type' => 'article', 'image' => kpopblog_article_image_url( $post->ID ), 'noindex' => false, 'published' => get_post_time( 'c', true, $post ), 'modified' => get_post_modified_time( 'c', true, $post ) ) );
	} elseif ( 'comebacks' === $kind ) { $payload = array_merge( $payload, array( 'title' => 'Comeback Schedule — ' . kpopblog_seo_site_name(), 'description' => 'Published K-pop comeback dates, releases, and artist schedules.', 'canonical' => home_url( '/comebacks' ), 'noindex' => false ) ); }
	if ( ! $payload['image'] || preg_match( '/\.svg(?:\?|$)/i', $payload['image'] ) ) { $payload['image'] = KPOPBLOG_URL . 'icons/og-default.jpg'; }
	if ( '0' === (string) get_option( 'blog_public' ) ) { $payload['noindex'] = true; }
	if ( ! $payload['noindex'] ) {
		$ld = kpopblog_build_public_json_ld( $context );
		$graph = isset( $ld['@graph'] ) ? $ld['@graph'] : array( array_diff_key( $ld, array( '@context' => true ) ) );
		if ( home_url( '/' ) !== $payload['canonical'] ) {
			$graph[] = array( '@type' => 'BreadcrumbList', 'itemListElement' => array( array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => home_url( '/' ) ), array( '@type' => 'ListItem', 'position' => 2, 'name' => explode( ' — ', $payload['title'] )[0], 'item' => $payload['canonical'] ) ) );
		}
		$payload['json_ld'] = array( '@context' => 'https://schema.org', '@graph' => $graph );
	}
	return $payload;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'kpopblog/v1', '/seo', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => function ( WP_REST_Request $request ) {
		$path = $request->get_param( 'path' );
		if ( ! is_string( $path ) || strlen( $path ) > 500 || ! preg_match( '#^/(?!/)[^?\x00-\x20]*$#', $path ) ) { return new WP_Error( 'invalid_path', 'A local pathname is required.', array( 'status' => 400 ) ); }
		$response = new WP_REST_Response( kpopblog_seo_payload( kpopblog_context_for_path( $path ) ) );
		$response->header( 'Cache-Control', 'public, max-age=60' );
		return $response;
	} ) );
} );
