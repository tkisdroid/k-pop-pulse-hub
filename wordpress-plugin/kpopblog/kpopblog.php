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

require_once KPOPBLOG_PATH . 'includes/install.php';
require_once KPOPBLOG_PATH . 'includes/cpt.php';
require_once KPOPBLOG_PATH . 'includes/meta.php';
require_once KPOPBLOG_PATH . 'includes/rest.php';
require_once KPOPBLOG_PATH . 'includes/rest-write.php';
require_once KPOPBLOG_PATH . 'includes/auth.php';
require_once KPOPBLOG_PATH . 'includes/user-admin.php';
require_once KPOPBLOG_PATH . 'includes/admin-fields.php';
require_once KPOPBLOG_PATH . 'includes/settings.php';
require_once KPOPBLOG_PATH . 'includes/notifications.php';
require_once KPOPBLOG_PATH . 'includes/newsletter.php';
require_once KPOPBLOG_PATH . 'includes/shortcode.php';
require_once KPOPBLOG_PATH . 'includes/block.php';
require_once KPOPBLOG_PATH . 'includes/template.php';

function kpopblog_activate() {
	kpopblog_register_cpts();
	kpopblog_install_or_upgrade();
	flush_rewrite_rules();
	kpopblog_audit( 'plugin_activated', 'plugin' );
}
register_activation_hook( __FILE__, 'kpopblog_activate' );

function kpopblog_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'kpopblog_deactivate' );

function kpopblog_maybe_upgrade() {
	if ( get_option( 'kpopblog_schema_version' ) !== KPOPBLOG_SCHEMA_VERSION ) {
		kpopblog_install_or_upgrade();
	}
}
add_action( 'plugins_loaded', 'kpopblog_maybe_upgrade' );
