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
 * mfn-custom-inline-css, WP core's own global-styles-inline-css, ad/consent
 * plugins, …) print CSS straight into wp_head() as inline <style> blocks or
 * foreign stylesheet <link> tags, completely bypassing the wp_enqueue_style
 * queue — so kpopblog_strip_theme_assets() (includes/template.php), which
 * only dequeues registered handles, never sees them. That leftover CSS is
 * exactly what was overriding the app's fonts/hover states with the theme's
 * own (e.g. Betheme's "DM Sans" body font winning over Inter/Space
 * Grotesk). Buffer wp_head()'s output and strip every <style> block and
 * every non-KpopBlog stylesheet <link> so nothing but our own bundle can
 * style this page — meta/title/canonical/OG tags etc. are left alone.
 */
ob_start();
wp_head();
$kpopblog_head = ob_get_clean();
$kpopblog_head = preg_replace( '/<style\b[^>]*>.*?<\/style>/is', '', $kpopblog_head );
$kpopblog_head = preg_replace_callback( '/<link\b[^>]*rel=["\']stylesheet["\'][^>]*>/i', function ( $m ) {
	return strpos( $m[0], 'kpopblog-app-css' ) !== false ? $m[0] : '';
}, $kpopblog_head );
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
<?php wp_footer(); ?>
</body>
</html>
