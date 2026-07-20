<?php
/**
 * Plugin installation, schema upgrades, capabilities, and audit logging.
 *
 * @package KpopBlog
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const KPOPBLOG_SCHEMA_VERSION = '1.1.0';

/**
 * Create or update plugin-owned tables and capabilities.
 */
function kpopblog_install_or_upgrade() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$audit_table     = $wpdb->prefix . 'kb_audit_log';
	$reports_table   = $wpdb->prefix . 'kb_reports';
	$charset_collate = $wpdb->get_charset_collate();
	$audit_sql       = "CREATE TABLE {$audit_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
		action varchar(64) NOT NULL,
		object_type varchar(32) NOT NULL DEFAULT '',
		object_id bigint(20) unsigned NOT NULL DEFAULT 0,
		details_json longtext NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY action_created (action,created_at),
		KEY object_lookup (object_type,object_id),
		KEY actor_created (actor_id,created_at)
	) {$charset_collate};";
	$reports_sql     = "CREATE TABLE {$reports_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		reporter_id bigint(20) unsigned NOT NULL,
		target_type varchar(32) NOT NULL,
		target_id bigint(20) unsigned NOT NULL,
		reason varchar(500) NOT NULL,
		status varchar(16) NOT NULL DEFAULT 'pending',
		open_key varchar(64) NULL,
		resolved_by bigint(20) unsigned NOT NULL DEFAULT 0,
		resolution_note text NULL,
		created_at datetime NOT NULL,
		resolved_at datetime NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY open_key (open_key),
		KEY status_created (status,created_at),
		KEY target_lookup (target_type,target_id),
		KEY reporter_created (reporter_id,created_at)
	) {$charset_collate};";

	dbDelta( $audit_sql );
	dbDelta( $reports_sql );

	$admin = get_role( 'administrator' );
	if ( $admin ) {
		foreach ( array( 'kb_moderate_community', 'kb_manage_automation', 'kb_manage_notifications', 'kb_manage_ads' ) as $capability ) {
			$admin->add_cap( $capability );
		}
	}

	$editor = get_role( 'editor' );
	if ( $editor ) {
		$editor->add_cap( 'kb_moderate_community' );
	}

	update_option( 'kpopblog_schema_version', KPOPBLOG_SCHEMA_VERSION, false );
}

/**
 * Persist an administrator or system action.
 *
 * @param string $action      Stable action identifier.
 * @param string $object_type Target type.
 * @param int    $object_id   Target WordPress object ID.
 * @param array  $details     Additional JSON-serializable context.
 * @return bool Whether the row was inserted.
 */
function kpopblog_audit( $action, $object_type = '', $object_id = 0, array $details = array() ) {
	global $wpdb;

	$action      = substr( sanitize_key( $action ), 0, 64 );
	$object_type = substr( sanitize_key( $object_type ), 0, 32 );
	if ( $action === '' ) {
		return false;
	}

	$result = $wpdb->insert(
		$wpdb->prefix . 'kb_audit_log',
		array(
			'actor_id'    => get_current_user_id(),
			'action'      => $action,
			'object_type' => $object_type,
			'object_id'   => max( 0, (int) $object_id ),
			'details_json'=> $details ? wp_json_encode( $details ) : null,
			'created_at'  => current_time( 'mysql', true ),
		),
		array( '%d', '%s', '%s', '%d', '%s', '%s' )
	);

	return 1 === $result;
}
