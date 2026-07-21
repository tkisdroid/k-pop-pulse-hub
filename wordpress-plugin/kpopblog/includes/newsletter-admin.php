<?php
/**
 * Newsletter subscriber administration and privacy integration.
 *
 * @package KpopBlog
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function kpopblog_newsletter_status( $post_id ) {
	if ( get_post_meta( $post_id, 'kb_sub_unsub_at', true ) || get_post_meta( $post_id, 'kb_sub_suppressed', true ) ) { return 'unsubscribed'; }
	return get_post_meta( $post_id, 'kb_sub_confirmed', true ) ? 'confirmed' : 'pending';
}

add_filter( 'manage_kb_subscriber_posts_columns', function () {
	return array(
		'cb'        => '<input type="checkbox">',
		'title'     => 'Email',
		'kb_status' => 'Status',
		'kb_topics' => 'Topics',
		'kb_frequency' => 'Frequency',
		'kb_consent' => 'Consent (UTC)',
		'date'      => 'Created',
	);
} );

add_action( 'manage_kb_subscriber_posts_custom_column', function ( $column, $post_id ) {
	if ( 'kb_status' === $column ) {
		echo esc_html( ucfirst( kpopblog_newsletter_status( $post_id ) ) );
	} elseif ( 'kb_topics' === $column ) {
		echo esc_html( implode( ', ', (array) get_post_meta( $post_id, 'kb_sub_topics', true ) ) );
	} elseif ( 'kb_frequency' === $column ) {
		echo esc_html( (string) get_post_meta( $post_id, 'kb_sub_frequency', true ) );
	} elseif ( 'kb_consent' === $column ) {
		echo esc_html( (string) get_post_meta( $post_id, 'kb_sub_consent_at', true ) );
	}
}, 10, 2 );

add_action( 'restrict_manage_posts', function ( $post_type ) {
	if ( 'kb_subscriber' !== $post_type ) { return; }
	$current = isset( $_GET['kb_sub_status'] ) ? sanitize_key( wp_unslash( $_GET['kb_sub_status'] ) ) : '';
	?>
	<label class="screen-reader-text" for="kb_sub_status">Filter by subscription status</label>
	<select id="kb_sub_status" name="kb_sub_status">
		<option value="">All subscription states</option>
		<?php foreach ( array( 'confirmed', 'pending', 'unsubscribed' ) as $status ) : ?>
			<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $current, $status ); ?>><?php echo esc_html( ucfirst( $status ) ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php
} );

add_action( 'pre_get_posts', function ( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || 'kb_subscriber' !== $query->get( 'post_type' ) ) { return; }
	$status = isset( $_GET['kb_sub_status'] ) ? sanitize_key( wp_unslash( $_GET['kb_sub_status'] ) ) : '';
	if ( 'confirmed' === $status ) {
		$query->set( 'meta_query', array(
			'relation' => 'AND',
			array( 'key' => 'kb_sub_confirmed', 'value' => '1' ),
			array( 'key' => 'kb_sub_unsub_at', 'compare' => 'NOT EXISTS' ),
		) );
	} elseif ( 'unsubscribed' === $status ) {
		$query->set( 'meta_query', array( array( 'key' => 'kb_sub_unsub_at', 'compare' => 'EXISTS' ) ) );
	} elseif ( 'pending' === $status ) {
		$query->set( 'meta_query', array(
			'relation' => 'AND',
			array(
				'relation' => 'OR',
				array( 'key' => 'kb_sub_confirmed', 'value' => '1', 'compare' => '!=' ),
				array( 'key' => 'kb_sub_confirmed', 'compare' => 'NOT EXISTS' ),
			),
			array( 'key' => 'kb_sub_unsub_at', 'compare' => 'NOT EXISTS' ),
		) );
	}
} );

add_filter( 'post_row_actions', function ( $actions, $post ) {
	if ( 'kb_subscriber' !== $post->post_type || ! current_user_can( 'kb_manage_notifications' ) ) { return $actions; }
	$status = kpopblog_newsletter_status( $post->ID );
	$base = admin_url( 'admin-post.php' );
	if ( 'pending' === $status ) {
		$actions['kb_resend'] = '<a href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'kpopblog_newsletter_record', 'operation' => 'resend', 'subscriber_id' => $post->ID ), $base ), 'kpopblog_newsletter_record_' . $post->ID ) ) . '">Resend confirmation</a>';
	}
	if ( 'unsubscribed' !== $status ) {
		$actions['kb_unsubscribe'] = '<a href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'kpopblog_newsletter_record', 'operation' => 'unsubscribe', 'subscriber_id' => $post->ID ), $base ), 'kpopblog_newsletter_record_' . $post->ID ) ) . '">Unsubscribe</a>';
	}
	return $actions;
}, 10, 2 );

function kpopblog_handle_newsletter_record_action() {
	if ( ! current_user_can( 'kb_manage_notifications' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage subscribers.', 'kpopblog' ), '', array( 'response' => 403 ) );
	}
	$subscriber_id = isset( $_GET['subscriber_id'] ) ? absint( $_GET['subscriber_id'] ) : 0;
	$operation = isset( $_GET['operation'] ) ? sanitize_key( wp_unslash( $_GET['operation'] ) ) : '';
	check_admin_referer( 'kpopblog_newsletter_record_' . $subscriber_id );
	if ( 'kb_subscriber' !== get_post_type( $subscriber_id ) ) {
		wp_die( esc_html__( 'Subscriber not found.', 'kpopblog' ), '', array( 'response' => 404 ) );
	}

	if ( 'resend' === $operation && 'pending' === kpopblog_newsletter_status( $subscriber_id ) ) {
		$tokens = kpopblog_newsletter_issue_tokens( $subscriber_id );
		kpopblog_newsletter_send_confirmation( $subscriber_id, $tokens );
		kpopblog_audit( 'newsletter_confirmation_resent', 'subscriber', $subscriber_id );
	} elseif ( 'unsubscribe' === $operation ) {
		update_post_meta( $subscriber_id, 'kb_sub_confirmed', false );
		update_post_meta( $subscriber_id, 'kb_sub_suppressed', true );
		if ( ! get_post_meta( $subscriber_id, 'kb_sub_unsub_at', true ) ) {
			update_post_meta( $subscriber_id, 'kb_sub_unsub_at', current_time( 'mysql', true ) );
		}
		kpopblog_audit( 'newsletter_admin_unsubscribed', 'subscriber', $subscriber_id );
	}
	wp_safe_redirect( admin_url( 'edit.php?post_type=kb_subscriber' ) );
	exit;
}
add_action( 'admin_post_kpopblog_newsletter_record', 'kpopblog_handle_newsletter_record_action' );

add_action( 'admin_head-edit.php', function () {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-kb_subscriber' !== $screen->id || ! current_user_can( 'kb_manage_notifications' ) ) { return; }
	$url = wp_nonce_url( admin_url( 'admin-post.php?action=kpopblog_export_subscribers' ), 'kpopblog_export_subscribers' );
	?>
	<script>document.addEventListener('DOMContentLoaded',function(){var a=document.querySelector('.wrap .page-title-action');if(a){var b=a.cloneNode();b.href=<?php echo wp_json_encode( $url ); ?>;b.textContent='Export CSV';a.after(b);}});</script>
	<?php
} );

function kpopblog_export_subscribers() {
	if ( ! current_user_can( 'kb_manage_notifications' ) ) {
		wp_die( esc_html__( 'You are not allowed to export subscribers.', 'kpopblog' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'kpopblog_export_subscribers' );
	$ids = get_posts( array( 'post_type' => 'kb_subscriber', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=kpopblog-subscribers-' . gmdate( 'Y-m-d' ) . '.csv' );
	$output = fopen( 'php://output', 'w' );
	if ( false === $output ) { exit; }
	fputcsv( $output, array( 'email', 'status', 'topics', 'frequency', 'source', 'locale', 'consent_at', 'confirmed_at', 'unsubscribed_at' ) );
	foreach ( $ids as $id ) {
		fputcsv( $output, array(
			get_post_meta( $id, 'kb_sub_email', true ),
			kpopblog_newsletter_status( $id ),
			implode( '|', (array) get_post_meta( $id, 'kb_sub_topics', true ) ),
			get_post_meta( $id, 'kb_sub_frequency', true ),
			get_post_meta( $id, 'kb_sub_source', true ),
			get_post_meta( $id, 'kb_sub_locale', true ),
			get_post_meta( $id, 'kb_sub_consent_at', true ),
			get_post_meta( $id, 'kb_sub_confirmed_at', true ),
			get_post_meta( $id, 'kb_sub_unsub_at', true ),
		) );
	}
	fclose( $output );
	kpopblog_audit( 'newsletter_exported', 'subscriber', 0, array( 'count' => count( $ids ) ) );
	exit;
}
add_action( 'admin_post_kpopblog_export_subscribers', 'kpopblog_export_subscribers' );

function kpopblog_personal_data_export( $email_address, $page = 1 ) {
	global $wpdb;
	$data = array();
	$subscriber_id = kpopblog_newsletter_find_by_email( sanitize_email( $email_address ) );
	if ( $subscriber_id ) {
		$data[] = array(
			'group_id' => 'kpopblog-newsletter',
			'group_label' => 'KpopBlog Newsletter',
			'item_id' => 'subscriber-' . $subscriber_id,
			'data' => array(
				array( 'name' => 'Email', 'value' => get_post_meta( $subscriber_id, 'kb_sub_email', true ) ),
				array( 'name' => 'Status', 'value' => kpopblog_newsletter_status( $subscriber_id ) ),
				array( 'name' => 'Topics', 'value' => implode( ', ', (array) get_post_meta( $subscriber_id, 'kb_sub_topics', true ) ) ),
				array( 'name' => 'Consent time', 'value' => get_post_meta( $subscriber_id, 'kb_sub_consent_at', true ) ),
			),
		);
	}
	$user = get_user_by( 'email', $email_address );
	if ( $user ) {
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}kb_notifications WHERE user_id = %d ORDER BY id ASC", $user->ID ) );
		foreach ( $rows as $row ) {
			$data[] = array(
				'group_id' => 'kpopblog-notifications',
				'group_label' => 'KpopBlog Notifications',
				'item_id' => 'notification-' . $row->id,
				'data' => array(
					array( 'name' => 'Title', 'value' => $row->title ),
					array( 'name' => 'Message', 'value' => $row->body ),
					array( 'name' => 'Created', 'value' => $row->created_at ),
					array( 'name' => 'Read', 'value' => $row->read_at ? 'yes' : 'no' ),
				),
			);
		}
	}
	return array( 'data' => $data, 'done' => true );
}

function kpopblog_personal_data_erase( $email_address, $page = 1 ) {
	global $wpdb;
	$removed = false;
	$subscriber_id = kpopblog_newsletter_find_by_email( sanitize_email( $email_address ) );
	if ( $subscriber_id ) {
		$anonymous_email = 'deleted+' . substr( hash_hmac( 'sha256', strtolower( $email_address ), wp_salt( 'auth' ) ), 0, 20 ) . '@example.invalid';
		update_post_meta( $subscriber_id, 'kb_sub_email', $anonymous_email );
		update_post_meta( $subscriber_id, 'kb_sub_confirmed', false );
		update_post_meta( $subscriber_id, 'kb_sub_suppressed', true );
		update_post_meta( $subscriber_id, 'kb_sub_unsub_at', current_time( 'mysql', true ) );
		delete_post_meta( $subscriber_id, 'kb_sub_source' );
		delete_post_meta( $subscriber_id, 'kb_sub_locale' );
		wp_update_post( array( 'ID' => $subscriber_id, 'post_title' => $anonymous_email ) );
		$removed = true;
	}
	$user = get_user_by( 'email', $email_address );
	if ( $user ) {
		$removed = false !== $wpdb->delete( $wpdb->prefix . 'kb_notifications', array( 'user_id' => $user->ID ), array( '%d' ) ) || $removed;
	}
	return array( 'items_removed' => $removed, 'items_retained' => false, 'messages' => array(), 'done' => true );
}

add_filter( 'wp_privacy_personal_data_exporters', function ( $exporters ) {
	$exporters['kpopblog'] = array( 'exporter_friendly_name' => 'KpopBlog data', 'callback' => 'kpopblog_personal_data_export' );
	return $exporters;
} );

add_filter( 'wp_privacy_personal_data_erasers', function ( $erasers ) {
	$erasers['kpopblog'] = array( 'eraser_friendly_name' => 'KpopBlog data', 'callback' => 'kpopblog_personal_data_erase' );
	return $erasers;
} );

add_action( 'delete_user', function ( $user_id ) {
	global $wpdb;
	$wpdb->delete( $wpdb->prefix . 'kb_notifications', array( 'user_id' => (int) $user_id ), array( '%d' ) );
} );
