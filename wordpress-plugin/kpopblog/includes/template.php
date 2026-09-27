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
	if ( is_404() && ! kpopblog_public_context_is_missing() ) {
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

/**
 * Client-side routes such as /artist/ive or /thread/some-slug are not
 * WordPress URLs. Without this, WordPress "guesses" a matching post for the
 * 404 and 301-redirects to its CPT permalink (/artists/ive/), which the React
 * router cannot render.
 */
function kpopblog_disable_404_permalink_guess( $do_redirect ) {
	$front_id = (int) get_option( 'page_on_front' );
	return ( $front_id > 0 && kpopblog_uses_app_shell( $front_id ) ) ? false : $do_redirect;
}
add_filter( 'do_redirect_guess_404_permalink', 'kpopblog_disable_404_permalink_guess' );

/** Application route for a KpopBlog post, or '' when the post type has no app page. */
function kpopblog_app_route_for_post( WP_Post $post ) {
	switch ( $post->post_type ) {
		case 'post':        return '/news/' . $post->post_name;
		case 'kb_artist':   return '/artist/' . $post->post_name;
		case 'kb_member':   return '/member/' . $post->post_name;
		case 'kb_thread':   return '/thread/' . $post->post_name;
		case 'kb_poll':     return '/polls/' . $post->post_name;
		case 'kb_video':    return '/watch/' . $post->ID;
		case 'kb_comeback': return '/comebacks';
		case 'kb_chart':    return '/charts';
	}
	return '';
}

/**
 * Point WordPress permalinks (admin "View" links, feeds, canonical tags) at the
 * app routes so every generated link opens a page the app can render.
 */
function kpopblog_app_permalink( $permalink, $post ) {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || '' === $post->post_name ) { return $permalink; }
	$front_id = (int) get_option( 'page_on_front' );
	if ( $front_id <= 0 || ! kpopblog_uses_app_shell( $front_id ) ) { return $permalink; }
	$route = kpopblog_app_route_for_post( $post );
	return '' === $route ? $permalink : home_url( $route );
}
add_filter( 'post_link', 'kpopblog_app_permalink', 20, 2 );
add_filter( 'post_type_link', 'kpopblog_app_permalink', 20, 2 );

/**
 * WordPress redirects /login to wp-login.php. The app has its own /login page,
 * so keep that URL in the app when the front page is the app shell (refreshes,
 * bookmarks, and shared links would otherwise land on the bare WordPress form).
 */
function kpopblog_keep_app_login_route() {
	$front_id = (int) get_option( 'page_on_front' );
	if ( $front_id <= 0 || ! kpopblog_uses_app_shell( $front_id ) ) { return; }
	$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
	if ( '/login' === untrailingslashit( $path ) ) {
		remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );
	}
}
add_action( 'template_redirect', 'kpopblog_keep_app_login_route', 0 );

/** /favicon.ico: serve the KpopBlog icon instead of WordPress's default "W" redirect. */
function kpopblog_serve_favicon() {
	$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
	if ( '/favicon.ico' !== $path || ! is_readable( KPOPBLOG_PATH . 'icons/favicon.ico' ) ) { return; }
	status_header( 200 );
	header( 'Content-Type: image/x-icon' );
	header( 'Cache-Control: public, max-age=604800' );
	readfile( KPOPBLOG_PATH . 'icons/favicon.ico' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
	exit;
}
add_action( 'template_redirect', 'kpopblog_serve_favicon', 0 );
add_action( 'do_favicon', 'kpopblog_serve_favicon', 0 );

/** The app's content and interface are English; say so to browsers and search engines. */
function kpopblog_app_shell_language( $output ) {
	return function_exists( 'kpopblog_current_request_is_app_shell' ) && kpopblog_current_request_is_app_shell() ? 'lang="en"' : $output;
}
add_filter( 'language_attributes', 'kpopblog_app_shell_language' );
