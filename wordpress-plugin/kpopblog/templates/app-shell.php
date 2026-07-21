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
	$html = preg_replace_callback( '/<link\b[^>]*rel=["\']stylesheet["\'][^>]*>/i', function ( $m ) {
		return strpos( $m[0], 'kpopblog-app-css' ) !== false ? $m[0] : '';
	}, $html );
	$html = preg_replace_callback( '/<script\b[^>]*>.*?<\/script>/is', function ( $m ) {
		$allowed = strpos( $m[0], 'kpopblog-app' ) !== false || strpos( $m[0], 'kpopblog-discovery-jsonld' ) !== false;
		return $allowed ? $m[0] : '';
	}, $html );
	return $html;
}

ob_start();
wp_head();
$kpopblog_head = kpopblog_strip_foreign_markup( ob_get_clean() );
?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="dark">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
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
