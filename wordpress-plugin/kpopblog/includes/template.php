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
