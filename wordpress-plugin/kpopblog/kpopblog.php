<?php
/**
 * Plugin Name:       KpopBlog
 * Plugin URI:        https://kpopblog.com
 * Description:       Premium K-pop community blog. Manage articles, artists, members, comebacks, charts, forum threads and polls from the WordPress admin, then render the full React front-end with the [kpopblog] shortcode or the "KpopBlog App" Gutenberg block.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            kpopblog.com
 * License:           GPL-2.0-or-later
 * Text Domain:       kpopblog
 *
 * @package KpopBlog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KPOPBLOG_VERSION', '1.0.0' );
define( 'KPOPBLOG_PATH', plugin_dir_path( __FILE__ ) );
define( 'KPOPBLOG_URL', plugin_dir_url( __FILE__ ) );
define( 'KPOPBLOG_REST_NS', 'kpopblog/v1' );

require_once KPOPBLOG_PATH . 'includes/cpt.php';
require_once KPOPBLOG_PATH . 'includes/meta.php';
require_once KPOPBLOG_PATH . 'includes/rest.php';
require_once KPOPBLOG_PATH . 'includes/rest-write.php';
require_once KPOPBLOG_PATH . 'includes/settings.php';
require_once KPOPBLOG_PATH . 'includes/notifications.php';
require_once KPOPBLOG_PATH . 'includes/newsletter.php';
require_once KPOPBLOG_PATH . 'includes/shortcode.php';
require_once KPOPBLOG_PATH . 'includes/block.php';

register_activation_hook( __FILE__, function () {
	kpopblog_register_cpts();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );
