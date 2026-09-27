<?php
/**
 * Plugin Name:       KpopBlog
 * Plugin URI:        https://thekpopblog.com
 * Description:       K-pop publishing and community platform with articles, artists, videos, comebacks, forums, polls, submissions, subscriptions, notifications, advertising, and source-grounded content automation managed from WordPress.
 * Version:           1.5.0
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

define( 'KPOPBLOG_VERSION', '1.5.0' );
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
require_once KPOPBLOG_PATH . 'includes/seo-pages.php';
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
require_once KPOPBLOG_PATH . 'includes/artist-catalog.php';
require_once KPOPBLOG_PATH . 'includes/news-sources.php';
require_once KPOPBLOG_PATH . 'includes/news-collector.php';
require_once KPOPBLOG_PATH . 'includes/community-automation.php';
require_once KPOPBLOG_PATH . 'includes/collector-admin.php';
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
	kpopblog_sync_collector_schedule();
	flush_rewrite_rules();
	kpopblog_audit( 'plugin_activated', 'plugin' );
}
register_activation_hook( __FILE__, 'kpopblog_activate' );

function kpopblog_deactivate() {
	wp_clear_scheduled_hook( 'kpopblog_process_notification_jobs' );
	wp_clear_scheduled_hook( KPOPBLOG_AUTOMATION_HOOK );
	wp_clear_scheduled_hook( KPOPBLOG_COLLECTOR_HOOK );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'kpopblog_deactivate' );

function kpopblog_maybe_upgrade() {
	if ( get_option( 'kpopblog_schema_version' ) !== KPOPBLOG_SCHEMA_VERSION ) {
		kpopblog_install_or_upgrade();
	}
}
add_action( 'plugins_loaded', 'kpopblog_maybe_upgrade' );

/**
 * One-time site setup for automated publishing: follow the starter artists,
 * create the newsroom byline, open member registration, and turn on the news
 * collector. Runs on init so post types and taxonomies are registered.
 */
function kpopblog_maybe_run_site_setup() {
	if ( get_option( 'kpopblog_site_setup_version' ) === '1.4.0' ) {
		return;
	}
	update_option( 'kpopblog_site_setup_version', '1.4.0', false );

	kpopblog_seed_artist_catalog();
	kpopblog_newsroom_user_id();

	if ( ! get_option( 'users_can_register' ) ) {
		update_option( 'users_can_register', 1 );
	}
	if ( in_array( get_option( 'default_role' ), array( 'administrator', 'editor', 'author' ), true ) ) {
		update_option( 'default_role', 'subscriber' );
	}
	if ( ! is_array( get_option( KPOPBLOG_COLLECTOR_OPTION, null ) ) ) {
		add_option( KPOPBLOG_COLLECTOR_OPTION, kpopblog_collector_defaults() );
	}
	update_option( 'kpopblog_runtime_data_ready', 1, false );
	kpopblog_sync_collector_schedule();
	kpopblog_audit( 'site_setup_completed', 'plugin', 0, array( 'version' => '1.4.0' ) );
}
add_action( 'init', 'kpopblog_maybe_run_site_setup', 40 );

/**
 * 1.5.0: collected articles no longer store outbound links in their body.
 * Remove the old source line and "Read the full story" link (the credit is
 * now rendered by kpopblog_collector_article_footer()), record the source list
 * in kb_sources, and drop external URLs from newsroom threads. Direct writes
 * keep publish dates, modified dates, and revisions untouched.
 */
function kpopblog_maybe_migrate_collector_content() {
	if ( get_option( 'kpopblog_collector_content_version' ) === '1.5.0' ) {
		return;
	}
	update_option( 'kpopblog_collector_content_version', '1.5.0', false );
	global $wpdb;

	$articles = get_posts( array(
		'post_type'   => 'post',
		'post_status' => 'any',
		'numberposts' => -1,
		'meta_query'  => array( array( 'key' => 'kb_source', 'value' => array( 'aggregated', 'ai-brief' ), 'compare' => 'IN' ) ),
	) );
	foreach ( $articles as $post ) {
		$content = preg_replace( array( '#<p class="kb-source-credit">.*?</p>#s', '#<p><a class="kb-read-more"[^>]*>.*?</a></p>#s' ), '', $post->post_content );
		if ( $content !== $post->post_content ) {
			$wpdb->update( $wpdb->posts, array( 'post_content' => $content ), array( 'ID' => $post->ID ) );
			clean_post_cache( $post->ID );
		}
		if ( ! get_post_meta( $post->ID, 'kb_sources', true ) && function_exists( 'kpopblog_collector_post_sources' ) ) {
			$sources = kpopblog_collector_post_sources( $post->ID );
			if ( $sources ) { update_post_meta( $post->ID, 'kb_sources', $sources ); }
		}
	}

	$newsroom = (int) get_option( 'kpopblog_newsroom_user_id', 0 );
	if ( $newsroom ) {
		$threads = get_posts( array( 'post_type' => 'kb_thread', 'post_status' => 'any', 'numberposts' => -1, 'author' => $newsroom ) );
		foreach ( $threads as $thread ) {
			$body = preg_replace( '/^Original report: (.+?) — https?:\/\/\S+$/m', 'Source: $1', $thread->post_content );
			$body = str_replace( 'Read the brief: ', 'Full summary on KpopBlog: ', $body );
			if ( $body !== $thread->post_content ) {
				$wpdb->update( $wpdb->posts, array( 'post_content' => $body ), array( 'ID' => $thread->ID ) );
				clean_post_cache( $thread->ID );
			}
		}
	}
}
add_action( 'init', 'kpopblog_maybe_migrate_collector_content', 42 );
