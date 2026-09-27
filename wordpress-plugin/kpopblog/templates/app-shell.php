<?php
/**
 * Full-page app shell — no theme header/footer/sidebar/widgets, just the
 * React SPA. This is what makes the deployed site look and behave exactly
 * like the standalone Lovable build instead of the React app sitting inside
 * the active WordPress theme's chrome. See includes/template.php for how
 * this file gets selected (Page Attributes template picker, plus a 404
 * fallback so client-side routes like /artists or /news/some-slug resolve
 * here instead of the theme's 404 page).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * A lot of themes/plugins (Betheme's mfn-dynamic-inline-css and
 * mfn-custom-inline-css, WP core's own global-styles-inline-css, the
 * AdSense plugin's Auto Ads snippet, …) print CSS/JS straight into
 * wp_head()/wp_footer() as inline tags, completely bypassing the
 * wp_enqueue_style/script queue — so kpopblog_strip_theme_assets()
 * (includes/template.php), which only dequeues registered handles, never
 * sees them. Leftover CSS is what overrode the app's fonts/hover states
 * with the theme's own styling (e.g. Betheme's "DM Sans" body font beating
 * Inter/Space Grotesk). Leftover JS is worse: AdSense's Auto Ads script
 * repeatedly scans and mutates the DOM looking for ad placements, and that
 * collides with React's own re-renders often enough to freeze the tab
 * ("page unresponsive"). Buffer wp_head()/wp_footer() and strip every
 * <style> block, every non-KpopBlog stylesheet <link>, and every <script>
 * that isn't our own bundle — meta/title/canonical/OG tags are left alone.
 */
function kpopblog_strip_foreign_markup( $html ) {
	$html = preg_replace( '/<style\b[^>]*>.*?<\/style>/is', '', $html );
	// Theme and WordPress site icons: the shell prints the KpopBlog icon set itself.
	$html = preg_replace( '/<link\b[^>]*rel=["\'](?:shortcut icon|icon|apple-touch-icon)["\'][^>]*>\s*/i', '', $html );
	$html = preg_replace( '/<meta\b[^>]*name=["\']msapplication-TileImage["\'][^>]*>\s*/i', '', $html );
	$html = preg_replace_callback( '/<link\b[^>]*rel=["\']stylesheet["\'][^>]*>/i', function ( $m ) {
		return strpos( $m[0], 'kpopblog-app-css' ) !== false ? $m[0] : '';
	}, $html );
	$html = preg_replace_callback( '/<script\b[^>]*>.*?<\/script>/is', function ( $m ) {
		$has_json_ld_id = 1 === preg_match( '/^<script\b[^>]*\bid\s*=\s*(["\'])kpopblog-discovery-jsonld\1(?:\s|>)/i', $m[0] );
		$allowed = strpos( $m[0], 'kpopblog-app' ) !== false || $has_json_ld_id;
		return $allowed ? $m[0] : '';
	}, $html );
	return $html;
}

ob_start();
wp_head();
$kpopblog_head = kpopblog_strip_foreign_markup( ob_get_clean() );

$kpopblog_design  = function_exists( 'kpopblog_get_design_settings' ) ? kpopblog_get_design_settings() : array();
$kpopblog_theme_c = ! empty( $kpopblog_design['accent_color'] ) ? $kpopblog_design['accent_color'] : '#0b0b10';
$kpopblog_brand   = trim( ( ! empty( $kpopblog_design['site_name'] ) ? $kpopblog_design['site_name'] : 'Kpop' ) . ( isset( $kpopblog_design['accent_word'] ) ? $kpopblog_design['accent_word'] : 'Blog' ) );
$kpopblog_icons   = KPOPBLOG_URL . 'icons/';
?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="dark">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
<meta name="theme-color" content="<?php echo esc_attr( $kpopblog_theme_c ); ?>" />
<meta name="mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
<meta name="apple-mobile-web-app-title" content="<?php echo esc_attr( $kpopblog_brand ); ?>" />
<link rel="manifest" href="<?php echo esc_url( home_url( '/manifest.webmanifest' ) ); ?>" />
<link rel="icon" href="<?php echo esc_url( home_url( '/favicon.ico' ) ); ?>" sizes="48x48" />
<link rel="icon" type="image/svg+xml" href="<?php echo esc_url( $kpopblog_icons . 'favicon.svg' ); ?>" />
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo esc_url( $kpopblog_icons . 'favicon-32.png' ); ?>" />
<link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url( $kpopblog_icons . 'apple-touch-icon.png' ); ?>" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" />
<?php echo $kpopblog_head; ?>
</head>
<body <?php body_class( 'kpopblog-app-shell' ); ?>>
<?php echo do_shortcode( '[kpopblog]' ); ?>
<?php
ob_start();
wp_footer();
echo kpopblog_strip_foreign_markup( ob_get_clean() );
?>
</body>
</html>
