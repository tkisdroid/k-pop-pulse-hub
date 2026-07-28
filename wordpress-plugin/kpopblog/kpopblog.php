<?php
/**
 * Plugin Name:       KpopBlog
 * Plugin URI:        https://thekpopblog.com
 * Description:       K-pop publishing and community platform with articles, artists, videos, comebacks, forums, polls, submissions, subscriptions, notifications, advertising, and source-grounded content automation managed from WordPress.
 * Version:           1.3.1
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            thekpopblog.com
 * License:           GPL-2.0-or-later
 * Text Domain:       kpopblog
 *
 * @package KpopBlog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KPOPBLOG_VERSION', '1.3.1' );
define( 'KPOPBLOG_PATH', plugin_dir_path( __FILE__ ) );
define( 'KPOPBLOG_URL', plugin_dir_url( __FILE__ ) );
define( 'KPOPBLOG_REST_NS', 'kpopblog/v1' );

require_once KPOPBLOG_PATH . 'includes/install.php';
require_once KPOPBLOG_PATH . 'includes/admin.php';
require_once KPOPBLOG_PATH . 'includes/cpt.php';
require_once KPOPBLOG_PATH . 'includes/meta.php';
require_once KPOPBLOG_PATH . 'includes/validation.php';
require_once KPOPBLOG_PATH . 'includes/rest.php';
require_once KPOPBLOG_PATH . 'includes/discoverability.php';
require_once KPOPBLOG_PATH . 'includes/rest-write.php';
require_once KPOPBLOG_PATH . 'includes/auth.php';
require_once KPOPBLOG_PATH . 'includes/community.php';
require_once KPOPBLOG_PATH . 'includes/moderation-admin.php';
require_once KPOPBLOG_PATH . 'includes/user-admin.php';
require_once KPOPBLOG_PATH . 'includes/admin-fields.php';
require_once KPOPBLOG_PATH . 'includes/settings.php';
require_once KPOPBLOG_PATH . 'includes/ads.php';
require_once KPOPBLOG_PATH . 'includes/design.php';
require_once KPOPBLOG_PATH . 'includes/notifications.php';
require_once KPOPBLOG_PATH . 'includes/notifications-admin.php';
require_once KPOPBLOG_PATH . 'includes/automation.php';
require_once KPOPBLOG_PATH . 'includes/automation-admin.php';
require_once KPOPBLOG_PATH . 'includes/newsletter.php';
require_once KPOPBLOG_PATH . 'includes/newsletter-admin.php';
require_once KPOPBLOG_PATH . 'includes/shortcode.php';
require_once KPOPBLOG_PATH . 'includes/block.php';
require_once KPOPBLOG_PATH . 'includes/template.php';
require_once KPOPBLOG_PATH . 'includes/cli-seed.php';

function kpopblog_activate() {
	kpopblog_register_cpts();
	kpopblog_install_or_upgrade();
	kpopblog_sync_automation_schedule();
	flush_rewrite_rules();
	kpopblog_audit( 'plugin_activated', 'plugin' );
}
register_activation_hook( __FILE__, 'kpopblog_activate' );

function kpopblog_deactivate() {
	wp_clear_scheduled_hook( 'kpopblog_process_notification_jobs' );
	wp_clear_scheduled_hook( KPOPBLOG_AUTOMATION_HOOK );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'kpopblog_deactivate' );

function kpopblog_maybe_upgrade() {
	if ( get_option( 'kpopblog_schema_version' ) !== KPOPBLOG_SCHEMA_VERSION ) {
		kpopblog_install_or_upgrade();
	}
}
add_action( 'plugins_loaded', 'kpopblog_maybe_upgrade' );
