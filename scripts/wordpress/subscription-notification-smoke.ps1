[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$compose = Join-Path $repoRoot 'docker-compose.wordpress.yml'
$envFile = Join-Path $repoRoot '.env.wordpress'

if (-not (Test-Path -LiteralPath $envFile)) {
    $envFile = Join-Path $repoRoot '.env.wordpress.example'
}

$assertions = @'
global $wpdb;
$subscriber_id = 0;
$legacy_subscriber_id = 0;
$user_one_id = 0;
$user_two_id = 0;
$broadcast_job_id = 0;
$notifications_table = $wpdb->prefix . 'kb_notifications';
$jobs_table = $wpdb->prefix . 'kb_notification_jobs';
$run_token = str_replace( '-', '', wp_generate_uuid4() );
$email = 'subscription-smoke+' . $run_token . '@example.test';
$original_settings = get_option( 'kpopblog_newsletter', null );
$captured_mail = array();

$mail_filter = function ( $return, $atts ) use ( &$captured_mail ) {
    $captured_mail = $atts;
    return true;
};
add_filter( 'pre_wp_mail', $mail_filter, 10, 2 );

try {
    foreach ( array( $notifications_table, $jobs_table ) as $table ) {
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            throw new Exception( 'notification table missing: ' . $table );
        }
    }

    $existing_subscriber = kpopblog_newsletter_find_by_email( $email );
    if ( $existing_subscriber ) {
        wp_delete_post( $existing_subscriber, true );
    }

    $legacy_subscriber_id = wp_insert_post( array( 'post_type' => 'kb_subscriber', 'post_status' => 'publish', 'post_title' => 'legacy-subscription-smoke@example.test' ) );
    update_post_meta( $legacy_subscriber_id, 'kb_sub_email', 'legacy-subscription-smoke@example.test' );
    update_post_meta( $legacy_subscriber_id, 'kb_sub_token', 'LegacySubscriptionSmokeToken123456789012345' );
    kpopblog_migrate_legacy_newsletter_tokens();
    if ( get_post_meta( $legacy_subscriber_id, 'kb_sub_token', true ) || ! get_post_meta( $legacy_subscriber_id, 'kb_sub_confirm_hash', true ) ) {
        throw new Exception( 'legacy newsletter token migration failed' );
    }
    wp_delete_post( $legacy_subscriber_id, true );
    $legacy_subscriber_id = 0;

    $settings = kpopblog_newsletter_defaults();
    $settings['double_opt_in'] = true;
    update_option( 'kpopblog_newsletter', $settings, false );
    $_SERVER['REMOTE_ADDR'] = 'smoke-' . $run_token;

    $subscribe_request = new WP_REST_Request( 'POST', '/kpopblog/v1/newsletter/subscribe' );
    $subscribe_request->set_body_params( array(
        'email' => $email,
        'topics' => array( 'editorial' ),
        'frequency' => 'weekly',
        'source' => 'runtime-smoke',
        'locale' => 'en',
    ) );
    $subscribe_response = rest_do_request( $subscribe_request );
    if ( 200 !== $subscribe_response->get_status() || empty( $subscribe_response->get_data()['pending'] ) ) {
        throw new Exception( 'double opt-in subscription failed' );
    }

    $subscriber_id = kpopblog_newsletter_find_by_email( $email );
    if ( ! $subscriber_id ) {
        throw new Exception( 'subscriber was not persisted' );
    }
    if ( get_post_meta( $subscriber_id, 'kb_sub_token', true ) ) {
        throw new Exception( 'newsletter token was stored in plaintext' );
    }
    if ( ! get_post_meta( $subscriber_id, 'kb_sub_confirm_hash', true ) || ! get_post_meta( $subscriber_id, 'kb_sub_unsub_hash', true ) ) {
        throw new Exception( 'hashed newsletter tokens missing' );
    }

    $mail_body = isset( $captured_mail['message'] ) ? (string) $captured_mail['message'] : '';
    if ( ! preg_match( '/token=([A-Za-z0-9_-]+)&action=confirm/', $mail_body, $confirm_match ) ) {
        throw new Exception( 'confirmation link missing from email' );
    }
    if ( ! preg_match( '/token=([A-Za-z0-9_-]+)&action=unsubscribe/', $mail_body, $unsubscribe_match ) ) {
        throw new Exception( 'unsubscribe link missing from email' );
    }

    $email_unsubscribe = new WP_REST_Request( 'POST', '/kpopblog/v1/newsletter/unsubscribe' );
    $email_unsubscribe->set_body_params( array( 'email' => $email ) );
    if ( 400 !== rest_do_request( $email_unsubscribe )->get_status() ) {
        throw new Exception( 'email-only unsubscribe remained available' );
    }

    $confirm_request = new WP_REST_Request( 'POST', '/kpopblog/v1/newsletter/confirm' );
    $confirm_request->set_body_params( array( 'token' => $confirm_match[1] ) );
    if ( 200 !== rest_do_request( $confirm_request )->get_status() || ! get_post_meta( $subscriber_id, 'kb_sub_confirmed', true ) ) {
        throw new Exception( 'newsletter confirmation failed' );
    }
    if ( 200 !== rest_do_request( $confirm_request )->get_status() ) {
        throw new Exception( 'newsletter confirmation was not idempotent' );
    }

    $unsubscribe_request = new WP_REST_Request( 'POST', '/kpopblog/v1/newsletter/unsubscribe' );
    $unsubscribe_request->set_body_params( array( 'token' => $unsubscribe_match[1] ) );
    if ( 200 !== rest_do_request( $unsubscribe_request )->get_status() || get_post_meta( $subscriber_id, 'kb_sub_confirmed', true ) ) {
        throw new Exception( 'token unsubscribe failed' );
    }
    if ( 200 !== rest_do_request( $unsubscribe_request )->get_status() ) {
        throw new Exception( 'newsletter unsubscribe was not idempotent' );
    }
    if ( 'unsubscribed' !== kpopblog_newsletter_status( $subscriber_id ) ) {
        throw new Exception( 'newsletter administrator status mapping failed' );
    }
    if ( post_type_supports( 'kb_subscriber', 'custom-fields' ) ) {
        throw new Exception( 'subscriber security metadata is exposed in custom fields' );
    }
    $exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
    $erasers = apply_filters( 'wp_privacy_personal_data_erasers', array() );
    if ( empty( $exporters['kpopblog']['callback'] ) || empty( $erasers['kpopblog']['callback'] ) ) {
        throw new Exception( 'WordPress privacy integration missing' );
    }
    $privacy_export = kpopblog_personal_data_export( $email );
    if ( empty( $privacy_export['data'] ) || empty( $privacy_export['done'] ) ) {
        throw new Exception( 'newsletter privacy export missing subscriber data' );
    }

    foreach ( array( 'notification_smoke_one', 'notification_smoke_two' ) as $index => $login ) {
        $existing = get_user_by( 'login', $login );
        if ( $existing ) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user( $existing->ID );
        }
        $created = wp_insert_user( array(
            'user_login' => $login,
            'user_email' => $login . '@example.test',
            'user_pass' => wp_generate_password( 24, true, true ),
            'display_name' => 'Notification Smoke ' . ( $index + 1 ),
            'role' => 'subscriber',
        ) );
        if ( is_wp_error( $created ) ) {
            throw new Exception( 'failed to create notification smoke user' );
        }
        if ( 0 === $index ) {
            $user_one_id = (int) $created;
        } else {
            $user_two_id = (int) $created;
        }
    }

    wp_set_current_user( $user_one_id );
    $topic_request = new WP_REST_Request( 'POST', '/kpopblog/v1/subscriptions' );
    $topic_request->set_body_params( array( 'type' => 'topic', 'key' => 'editorial', 'subscribed' => true ) );
    $topic_response = rest_do_request( $topic_request );
    if ( 200 !== $topic_response->get_status() || ! in_array( 'editorial', $topic_response->get_data()['topics'], true ) ) {
        throw new Exception( 'topic subscription failed' );
    }

    $private_notification_id = kpopblog_create_notification( $user_one_id, 'system', array( 'title' => 'Private smoke notification' ) );
    if ( ! $private_notification_id ) {
        throw new Exception( 'private notification insert failed' );
    }

    wp_set_current_user( $user_two_id );
    $list_request = new WP_REST_Request( 'GET', '/kpopblog/v1/notifications' );
    $user_two_private_list = rest_do_request( $list_request )->get_data();
    if ( ! empty( $user_two_private_list['items'] ) ) {
        throw new Exception( 'private notification leaked to another user' );
    }
    $foreign_read = new WP_REST_Request( 'POST', '/kpopblog/v1/notifications/' . $private_notification_id . '/read' );
    if ( 404 !== rest_do_request( $foreign_read )->get_status() ) {
        throw new Exception( 'another user marked a private notification read' );
    }

    wp_set_current_user( 1 );
    $broadcast_request = new WP_REST_Request( 'POST', '/kpopblog/v1/notifications/broadcast' );
    $broadcast_request->set_body_params( array(
        'title' => 'Runtime smoke broadcast',
        'body' => 'Broadcast delivery body.',
        'href' => '/community',
    ) );
    $broadcast_response = rest_do_request( $broadcast_request );
    if ( 202 !== $broadcast_response->get_status() ) {
        throw new Exception( 'administrator broadcast was not queued' );
    }
    $broadcast_job_id = (int) $broadcast_response->get_data()['jobId'];
    $broadcast_status = 'pending';
    for ( $attempt = 0; $attempt < 10; $attempt++ ) {
        kpopblog_process_notification_jobs();
        $broadcast_status = (string) $wpdb->get_var( $wpdb->prepare(
            "SELECT status FROM {$jobs_table} WHERE id = %d",
            $broadcast_job_id
        ) );
        if ( 'completed' === $broadcast_status ) {
            break;
        }
        usleep( 100000 );
    }
    if ( 'completed' !== $broadcast_status ) {
        throw new Exception( 'administrator broadcast was not processed' );
    }

    wp_set_current_user( $user_one_id );
    $user_one_list = rest_do_request( $list_request )->get_data();
    if ( count( $user_one_list['items'] ) < 2 || $user_one_list['unreadCount'] < 2 ) {
        throw new Exception( 'user one inbox missing durable notifications' );
    }
    $read_request = new WP_REST_Request( 'POST', '/kpopblog/v1/notifications/' . $private_notification_id . '/read' );
    $read_response = rest_do_request( $read_request );
    if ( 200 !== $read_response->get_status() || empty( $read_response->get_data()['item']['read'] ) ) {
        throw new Exception( 'notification read state was not persisted' );
    }

    wp_set_current_user( $user_two_id );
    $user_two_list = rest_do_request( $list_request )->get_data();
    $user_two_broadcasts = array_filter( $user_two_list['items'], function ( $item ) {
        return isset( $item['title'] ) && 'Runtime smoke broadcast' === $item['title'];
    } );
    if ( 1 !== count( $user_two_broadcasts ) ) {
        throw new Exception( 'broadcast was not delivered to user two' );
    }
} finally {
    remove_filter( 'pre_wp_mail', $mail_filter, 10 );
    wp_set_current_user( 1 );
    if ( $subscriber_id ) {
        wp_delete_post( $subscriber_id, true );
    }
    if ( $legacy_subscriber_id ) {
        wp_delete_post( $legacy_subscriber_id, true );
    }
    if ( $user_one_id ) {
        $wpdb->delete( $notifications_table, array( 'user_id' => $user_one_id ), array( '%d' ) );
    }
    if ( $user_two_id ) {
        $wpdb->delete( $notifications_table, array( 'user_id' => $user_two_id ), array( '%d' ) );
    }
    if ( $broadcast_job_id ) {
        $wpdb->delete( $notifications_table, array( 'job_id' => $broadcast_job_id ), array( '%d' ) );
    }
    $wpdb->delete( $notifications_table, array( 'title' => 'Runtime smoke broadcast' ), array( '%s' ) );
    $wpdb->query( "DELETE FROM {$jobs_table} WHERE title = 'Runtime smoke broadcast'" );
    foreach ( array( $user_one_id, $user_two_id ) as $user_id ) {
        if ( $user_id ) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user( $user_id );
        }
    }
    if ( null === $original_settings ) {
        delete_option( 'kpopblog_newsletter' );
    } else {
        update_option( 'kpopblog_newsletter', $original_settings, false );
    }
}
'@

docker compose --env-file $envFile -f $compose run --rm --no-deps cli eval $assertions
if ($LASTEXITCODE -ne 0) {
    throw 'Subscription and notification assertions failed.'
}

Write-Host 'Subscription and notification smoke test passed.'
