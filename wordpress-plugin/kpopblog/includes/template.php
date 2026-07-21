<?php
/**
 * Theme isolation for the React SPA — makes the deployed WordPress site look
 * and navigate exactly like the standalone Lovable build, with no active
 * theme's header/footer/sidebar/nav menu/global CSS bleeding through.
 *
 * Editors pick "KpopBlog App (full page)" from Page Attributes → Template on
 * one page and set it as the site's homepage (Settings → Reading). Once
 * that's done, the WHOLE public site renders that page's app shell instead
 * of the active theme's templates — not just unresolved (404) URLs, but
 * also native post singles, CPT archives/singles, category pages, etc.
 * That's deliberate: several of the plugin's own custom post types reuse
 * rewrite slugs the SPA also uses for its own client-side routes (e.g.
 * kb_artist's archive is /artists, matching TanStack Router's own /artists
 * route) — so those requests are real, resolvable WordPress pages, not
 * 404s, and would otherwise render the theme's (empty) archive template
 * instead of falling through to the SPA. wp-admin, wp-login.php, and the
 * REST API never go through this filter, so they're unaffected either way.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const KPOPBLOG_APP_TEMPLATE = 'kpopblog-app';

function kpopblog_xml_escape( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8' );
}

function kpopblog_serve_machine_endpoint() {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path = wp_parse_url( $request_uri, PHP_URL_PATH );
	$path = '/' . ltrim( (string) $path, '/' );
	if ( ! in_array( $path, array( '/rss.xml', '/sitemap.xml', '/robots.txt' ), true ) ) { return; }

	status_header( 200 );
	if ( '/robots.txt' === $path ) {
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Cache-Control: public, max-age=86400' );
		echo "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /moderation\nDisallow: /onboarding\n\nSitemap: " . esc_url_raw( home_url( '/sitemap.xml' ) ) . "\n";
		exit;
	}

	if ( '/rss.xml' === $path ) {
		header( 'Content-Type: application/rss+xml; charset=utf-8' );
		header( 'Cache-Control: public, max-age=1800' );
		$posts = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 50, 'orderby' => 'date', 'order' => 'DESC' ) );
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<rss version="2.0"><channel>' . "\n";
		echo '<title>' . kpopblog_xml_escape( get_bloginfo( 'name' ) ) . '</title>' . "\n";
		echo '<link>' . kpopblog_xml_escape( home_url( '/' ) ) . '</link>' . "\n";
		echo '<description>' . kpopblog_xml_escape( get_bloginfo( 'description' ) ) . '</description>' . "\n";
		echo '<language>' . kpopblog_xml_escape( get_bloginfo( 'language' ) ?: 'en' ) . '</language>' . "\n";
		foreach ( $posts as $post ) {
			$category = (string) kpopblog_meta( $post->ID, 'kb_category_slug', 'news' );
			echo '<item><title>' . kpopblog_xml_escape( get_the_title( $post ) ) . '</title>';
			echo '<link>' . kpopblog_xml_escape( home_url( '/news/' . $post->post_name ) ) . '</link>';
			echo '<guid isPermaLink="false">' . kpopblog_xml_escape( 'post-' . $post->ID ) . '</guid>';
			echo '<pubDate>' . kpopblog_xml_escape( mysql2date( DATE_RSS, $post->post_date_gmt, false ) ) . '</pubDate>';
			echo '<description>' . kpopblog_xml_escape( has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 ) ) . '</description>';
			echo '<category>' . kpopblog_xml_escape( $category ) . '</category></item>' . "\n";
		}
		echo '</channel></rss>';
		exit;
	}

	header( 'Content-Type: application/xml; charset=utf-8' );
	header( 'Cache-Control: public, max-age=3600' );
	$paths = array( '/', '/latest', '/trending', '/artists', '/videos', '/charts', '/comebacks', '/polls', '/community', '/forum', '/newsletter', '/about', '/contact', '/advertise', '/privacy', '/terms', '/copyright', '/corrections', '/community-guidelines' );
	$entries = array_map( function ( $entry_path ) { return array( 'path' => $entry_path, 'modified' => '' ); }, $paths );
	$content_types = array(
		'post'      => '/news/',
		'kb_artist' => '/artist/',
		'kb_member' => '/member/',
		'kb_video'  => '/watch/',
		'kb_poll'   => '/polls/',
		'kb_thread' => '/thread/',
	);
	foreach ( $content_types as $post_type => $prefix ) {
		$posts = get_posts( array( 'post_type' => $post_type, 'post_status' => 'publish', 'numberposts' => 2000, 'orderby' => 'modified', 'order' => 'DESC' ) );
		foreach ( $posts as $post ) {
			$identifier = 'kb_video' === $post_type ? (string) $post->ID : $post->post_name;
			$entries[] = array( 'path' => $prefix . $identifier, 'modified' => $post->post_modified_gmt );
		}
	}
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
	foreach ( $entries as $entry ) {
		echo '<url><loc>' . kpopblog_xml_escape( home_url( $entry['path'] ) ) . '</loc>';
		if ( $entry['modified'] ) { echo '<lastmod>' . kpopblog_xml_escape( mysql2date( 'c', $entry['modified'], false ) ) . '</lastmod>'; }
		echo '</url>' . "\n";
	}
	echo '</urlset>';
	exit;
}
add_action( 'template_redirect', 'kpopblog_serve_machine_endpoint', 0 );

function kpopblog_register_page_template( $templates ) {
	$templates[ KPOPBLOG_APP_TEMPLATE ] = 'KpopBlog App (full page)';
	return $templates;
}
add_filter( 'theme_page_templates', 'kpopblog_register_page_template' );

function kpopblog_uses_app_shell( $post_id ) {
	return get_page_template_slug( $post_id ) === KPOPBLOG_APP_TEMPLATE;
}

function kpopblog_current_request_is_app_shell() {
	if ( is_admin() ) { return false; }
	if ( is_page() && kpopblog_uses_app_shell( get_queried_object_id() ) ) {
		return true;
	}
	$front_id = (int) get_option( 'page_on_front' );
	return $front_id > 0 && kpopblog_uses_app_shell( $front_id );
}

function kpopblog_template_include( $template ) {
	if ( ! kpopblog_current_request_is_app_shell() ) {
		return $template;
	}
	if ( is_404() ) {
		status_header( 200 ); // A client-side route, not a real 404.
	}
	return KPOPBLOG_PATH . 'templates/app-shell.php';
}
add_filter( 'template_include', 'kpopblog_template_include', 99 );

// Registered early so it's in place before _wp_admin_bar_init() reads it
// on template_redirect — an admin toolbar pinned to the top would otherwise
// throw off the SPA's own fixed-position nav.
add_filter( 'show_admin_bar', function ( $show ) {
	return kpopblog_current_request_is_app_shell() ? false : $show;
} );

/**
 * Every other enqueued stylesheet (theme stylesheet, block-library CSS,
 * global styles, other plugins' front-end CSS…) is dropped on the app
 * shell so nothing but the React bundle's own styles apply.
 */
function kpopblog_strip_theme_assets() {
	if ( ! kpopblog_current_request_is_app_shell() ) { return; }
	global $wp_styles;
	if ( ! ( $wp_styles instanceof WP_Styles ) ) { return; }
	foreach ( $wp_styles->queue as $handle ) {
		if ( $handle !== 'kpopblog-app' ) {
			wp_dequeue_style( $handle );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'kpopblog_strip_theme_assets', 100 );
