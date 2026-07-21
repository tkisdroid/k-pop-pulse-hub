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
global $wpdb, $submenu;

if ( ! function_exists( 'kpopblog_run_automation' ) ) {
    throw new Exception( 'automation runtime missing' );
}

foreach ( array( 'kb_automation_runs', 'kb_automation_items' ) as $suffix ) {
    $table = $wpdb->prefix . $suffix;
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
        throw new Exception( 'automation table missing: ' . $suffix );
    }
}

$admin = get_role( 'administrator' );
if ( ! $admin || ! $admin->has_cap( 'kb_manage_automation' ) ) {
    throw new Exception( 'automation capability missing' );
}
$administrator_ids = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
if ( ! $administrator_ids ) {
    throw new Exception( 'administrator user missing' );
}
wp_set_current_user( (int) $administrator_ids[0] );

$sanitized = kpopblog_sanitize_automation_settings( array(
    'enabled'       => '1',
    'auto_publish'  => '1',
    'model'         => 'not-a-real-model',
    'frequency'     => 'invalid',
    'max_items'     => '99',
    'artist_focus'  => " BTS, NewJeans, <script>alert(1)</script> ",
) );
if ( 'gpt-5.6-luna' !== $sanitized['model'] || 'twicedaily' !== $sanitized['frequency'] ) {
    throw new Exception( 'automation settings are not using safe defaults' );
}
if ( 10 !== $sanitized['max_items'] || false !== strpos( $sanitized['artist_focus'], '<' ) ) {
    throw new Exception( 'automation settings sanitization failed' );
}

$fixture_response_id = 'resp_automation_smoke';
$fixture_webhook_url = 'https://automation-smoke.invalid/webhook';
$fixture_job_titles = array(
    'New article: BTS confirms a new group release schedule',
    'Comeback: BLACKPINK Seoul concert',
);
$get_fixture_job_ids = function () use ( $wpdb, $fixture_job_titles ) {
    $placeholders = implode( ',', array_fill( 0, count( $fixture_job_titles ), '%s' ) );
    return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}kb_notification_jobs WHERE title IN ({$placeholders})",
        $fixture_job_titles
    ) ) );
};
$get_marker_post_ids = function () use ( $wpdb, $fixture_response_id ) {
    return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'kb_ai_response_id' AND meta_value = %s",
        $fixture_response_id
    ) ) );
};
$get_fixture_run_ids = function () use ( $wpdb, $fixture_response_id ) {
    return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}kb_automation_runs WHERE trigger_type = 'smoke' AND response_id = %s",
        $fixture_response_id
    ) ) );
};
$capture_cron_hook = function ( $hook ) {
    $events = array();
    foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
        if ( isset( $hooks[ $hook ] ) ) {
            $events[ (string) $timestamp ] = $hooks[ $hook ];
        }
    }
    return $events;
};
$record_test_cron_events = function ( $hook, array $before, array &$test_events ) use ( $capture_cron_hook ) {
    foreach ( $capture_cron_hook( $hook ) as $timestamp => $events ) {
        foreach ( $events as $event_key => $event ) {
            if ( ! isset( $before[ $timestamp ][ $event_key ] ) ) {
                $test_events[ $timestamp ][ $event_key ] = $event;
            }
        }
    }
};
$restore_cron_hook = function ( $hook, array $before, array $test_events ) {
    $crons = (array) _get_cron_array();
    $original_crons = $crons;
    foreach ( $test_events as $timestamp => $events ) {
        foreach ( $events as $event_key => $event ) {
            if ( isset( $crons[ $timestamp ][ $hook ][ $event_key ] ) && $crons[ $timestamp ][ $hook ][ $event_key ] === $event ) {
                unset( $crons[ $timestamp ][ $hook ][ $event_key ] );
            }
        }
        if ( isset( $crons[ $timestamp ][ $hook ] ) && ! $crons[ $timestamp ][ $hook ] ) { unset( $crons[ $timestamp ][ $hook ] ); }
        if ( isset( $crons[ $timestamp ] ) && ! $crons[ $timestamp ] ) { unset( $crons[ $timestamp ] ); }
    }
    foreach ( $before as $timestamp => $events ) {
        foreach ( $events as $event_key => $event ) {
            $crons[ $timestamp ][ $hook ][ $event_key ] = $event;
        }
    }
    if ( $crons === $original_crons ) { return; }
    $result = _set_cron_array( $crons, true );
    if ( is_wp_error( $result ) ) {
        throw new Exception( 'could not restore pre-existing cron state for ' . $hook . ': ' . $result->get_error_message() );
    }
};

$old_settings = get_option( 'kpopblog_automation', null );
$old_webhook_settings = get_option( KPOPBLOG_WEBHOOK_OPTION, null );
$automation_last_success_exists_before = 1 === (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s",
    'kpopblog_automation_last_success'
) );
$automation_last_success_before = $automation_last_success_exists_before ? get_option( 'kpopblog_automation_last_success' ) : null;
$notification_job_ids_before = $get_fixture_job_ids();
$fixture_run_ids_before = $get_fixture_run_ids();
$notification_cron_before = $capture_cron_hook( 'kpopblog_process_notification_jobs' );
$automation_cron_before = $capture_cron_hook( 'kpopblog_run_scheduled_automation' );
$notification_cron_test_events = array();
$automation_cron_test_events = array();
$notification_lock_name = 'kpopblog_notification_job_lock';
$notification_lock_active_before = get_transient( $notification_lock_name );
$notification_lock_value_before = get_option( '_transient_' . $notification_lock_name, null );
$notification_lock_timeout_before = get_option( '_transient_timeout_' . $notification_lock_name, null );
$notification_lock_token = 'automation-smoke-' . wp_generate_uuid4();
$notification_lock_owned = false;
$notification_guard_timestamp = 0;
$automation_guard_timestamp = 0;
$captured_request = array();
$internal_http_requests = array();
$blocked_external_requests = array();
$mock_http = function ( $preempt, $args, $url ) use ( &$captured_request, &$internal_http_requests, &$blocked_external_requests ) {
    if ( 'https://api.openai.com/v1/responses' === $url ) {
        $captured_request = $args;
        $payload = array(
        'id'     => 'resp_automation_smoke',
        'status' => 'completed',
        'output' => array(
            array(
                'type'    => 'message',
                'role'    => 'assistant',
                'content' => array(
                    array(
                        'type' => 'output_text',
                        'text' => wp_json_encode( array(
                            'items' => array(
                                array(
                                    'kind'          => 'news',
                                    'title'         => 'BTS confirms a new group release schedule',
                                    'excerpt'       => 'The group announced a verified release schedule through official channels.',
                                    'content'       => "BTS has confirmed a new group release schedule through official channels.\n\nThe announcement provides fans with a clear timeline and release details. This automated article records only details supported by the cited sources and does not invent quotations.\n\nReaders should use the linked sources for the original announcement and any later updates.",
                                    'artist_slugs'  => array( 'bts' ),
                                    'event_date'    => '',
                                    'event_type'    => '',
                                    'confidence'    => 0.96,
                                    'sources'       => array(
                                        array( 'url' => 'https://example.com/bts-release', 'title' => 'Official BTS release notice', 'publisher' => 'Example Music', 'published_at' => '2026-07-21T00:00:00Z' ),
                                        array( 'url' => 'https://news.example.org/bts-release', 'title' => 'BTS release coverage', 'publisher' => 'Example News', 'published_at' => '2026-07-21T01:00:00Z' ),
                                    ),
                                ),
                                array(
                                    'kind'          => 'concert',
                                    'title'         => 'BLACKPINK Seoul concert',
                                    'excerpt'       => 'BLACKPINK will hold a verified concert in Seoul.',
                                    'content'       => 'BLACKPINK will hold a concert in Seoul. Venue and ticket details are available from the cited official announcement. Fans should verify the on-sale time, venue policy, and later schedule changes through the linked original source.',
                                    'artist_slugs'  => array( 'blackpink' ),
                                    'event_date'    => '2026-10-24T19:00:00+09:00',
                                    'event_type'    => 'concert',
                                    'confidence'    => 0.98,
                                    'sources'       => array(
                                        array( 'url' => 'https://example.com/blackpink-concert', 'title' => 'Official BLACKPINK concert notice', 'publisher' => 'Example Music', 'published_at' => '2026-07-21T00:00:00Z' ),
                                    ),
                                ),
                            ),
                        ) ),
                    ),
                ),
            ),
        ),
    );
        return array(
            'headers'  => array( 'content-type' => 'application/json' ),
            'body'     => wp_json_encode( $payload ),
            'response' => array( 'code' => 200, 'message' => 'OK' ),
            'cookies'  => array(),
            'filename' => null,
        );
    }

    $parts = wp_parse_url( $url );
    if ( is_array( $parts ) && 'http' === ( $parts['scheme'] ?? '' ) && 'wordpress' === ( $parts['host'] ?? '' ) && empty( $parts['port'] ) && empty( $parts['user'] ) && empty( $parts['pass'] ) ) {
        $internal_http_requests[] = $url;
        return false;
    }

    $blocked_external_requests[] = $url;
    return new WP_Error( 'automation_smoke_blocked_http', 'Automation smoke blocked an external HTTP request.' );
};

$created_ids = array();
$test_notification_job_ids = array();
$test_run_ids = array();
$draft_id = 0;
try {
    if ( false !== $notification_lock_active_before ) {
        throw new Exception( 'notification processing was already active before the automation smoke test' );
    }
    set_transient( $notification_lock_name, $notification_lock_token, 15 * MINUTE_IN_SECONDS );
    if ( $notification_lock_token !== get_transient( $notification_lock_name ) ) {
        throw new Exception( 'notification processing lock could not be acquired' );
    }
    $notification_lock_owned = true;

    wp_clear_scheduled_hook( 'kpopblog_process_notification_jobs' );
    $notification_guard_timestamp = time() + DAY_IN_SECONDS;
    if ( ! wp_schedule_single_event( $notification_guard_timestamp, 'kpopblog_process_notification_jobs' ) ) {
        throw new Exception( 'notification processing guard cron could not be scheduled' );
    }
    $record_test_cron_events( 'kpopblog_process_notification_jobs', $notification_cron_before, $notification_cron_test_events );

    wp_clear_scheduled_hook( 'kpopblog_run_scheduled_automation' );
    $automation_guard_timestamp = time() + DAY_IN_SECONDS;
    if ( ! wp_schedule_event( $automation_guard_timestamp, 'twicedaily', 'kpopblog_run_scheduled_automation' ) ) {
        throw new Exception( 'automation execution guard cron could not be scheduled' );
    }
    $record_test_cron_events( 'kpopblog_run_scheduled_automation', $automation_cron_before, $automation_cron_test_events );

    update_option( KPOPBLOG_WEBHOOK_OPTION, array(
        'url'     => $fixture_webhook_url,
        'secret'  => 'automation-smoke-secret-not-real',
        'enabled' => 1,
    ), false );
    update_option( 'kpopblog_automation', array(
        'enabled'       => 1,
        'auto_publish'  => 1,
        'model'         => 'gpt-5.6-luna',
        'frequency'     => 'twicedaily',
        'max_items'     => 4,
        'artist_focus'  => 'BTS, BLACKPINK',
    ), false );
    $record_test_cron_events( 'kpopblog_run_scheduled_automation', $automation_cron_before, $automation_cron_test_events );

    putenv( 'OPENAI_API_KEY=automation-smoke-key-not-real' );
    add_filter( 'pre_http_request', $mock_http, 10, 3 );

    $result = kpopblog_run_automation( 'smoke' );
    if ( is_wp_error( $result ) ) {
        throw new Exception( 'automation run failed: ' . $result->get_error_message() );
    }
    $test_run_ids = array_values( array_diff( $get_fixture_run_ids(), $fixture_run_ids_before ) );
    $provenance_ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
        "SELECT wp_post_id FROM {$wpdb->prefix}kb_automation_items WHERE response_id = %s",
        $fixture_response_id
    ) ) );
    $created_ids = array_values( array_unique( array_merge( $provenance_ids, $get_marker_post_ids() ) ) );
    $test_notification_job_ids = array_values( array_diff( $get_fixture_job_ids(), $notification_job_ids_before ) );

    if ( 2 !== $result['discovered'] || 2 !== $result['created'] ) {
        throw new Exception( 'automation did not persist both verified items: ' . wp_json_encode( $result ) );
    }
    if ( 2 !== count( $provenance_ids ) || 2 !== count( $created_ids ) ) {
        throw new Exception( 'automation provenance rows missing' );
    }

    $body = isset( $captured_request['body'] ) ? json_decode( $captured_request['body'], true ) : null;
    if ( ! is_array( $body ) || 'gpt-5.6-luna' !== $body['model'] ) {
        throw new Exception( 'Responses API request model mismatch' );
    }
    if ( 'web_search' !== $body['tools'][0]['type'] || 'json_schema' !== $body['text']['format']['type'] || empty( $body['text']['format']['strict'] ) ) {
        throw new Exception( 'Responses API grounding or structured output is missing' );
    }
    $authorization = isset( $captured_request['headers']['Authorization'] ) ? $captured_request['headers']['Authorization'] : '';
    if ( 'Bearer automation-smoke-key-not-real' !== $authorization ) {
        throw new Exception( 'server-side API credential was not applied' );
    }
    if ( ! in_array( $fixture_webhook_url, $blocked_external_requests, true ) ) {
        throw new Exception( 'notification webhook was not intercepted by the HTTP isolation guard' );
    }
    if ( 2 !== count( $blocked_external_requests ) ) {
        throw new Exception( 'automation did not emit exactly two isolated notification webhooks' );
    }
    foreach ( $blocked_external_requests as $blocked_url ) {
        if ( $fixture_webhook_url !== $blocked_url ) {
            throw new Exception( 'unexpected external HTTP request was blocked: ' . $blocked_url );
        }
    }
    foreach ( $internal_http_requests as $internal_url ) {
        if ( 0 !== strpos( $internal_url, 'http://wordpress/' ) ) {
            throw new Exception( 'external HTTP request escaped the isolation guard: ' . $internal_url );
        }
    }
    foreach ( $created_ids as $post_id ) {
        if ( '1' !== get_post_meta( $post_id, 'kb_ai_generated', true ) || '' === get_post_meta( $post_id, 'kb_source_url', true ) ) {
            throw new Exception( 'automation provenance post meta missing' );
        }
        if ( 'publish' !== get_post_status( $post_id ) ) {
            throw new Exception( 'verified automation item was not auto-published' );
        }
    }

    $article_id = 0;
    $schedule_id = 0;
    foreach ( $created_ids as $post_id ) {
        $post_type = get_post_type( $post_id );
        if ( 'post' === $post_type ) { $article_id = (int) $post_id; }
        if ( 'kb_comeback' === $post_type ) { $schedule_id = (int) $post_id; }
    }
    if ( ! $article_id || ! $schedule_id ) {
        throw new Exception( 'automation fixture post types are incomplete' );
    }
    $draft_id = wp_insert_post( array(
        'post_type'    => 'post',
        'post_status'  => 'draft',
        'post_title'   => 'Automation discovery private draft',
        'post_name'    => 'automation-discovery-private-draft',
        'post_content' => 'This draft must never appear in public discovery output.',
    ) );
    if ( ! $draft_id || is_wp_error( $draft_id ) ) {
        throw new Exception( 'automation discovery draft fixture could not be created' );
    }

    $fetch = function ( $path, $agent ) {
        return wp_remote_get( 'http://wordpress' . $path, array(
            'timeout' => 30,
            'headers' => array( 'Host' => 'localhost:8088', 'User-Agent' => $agent ),
        ) );
    };
    $article_slug = get_post_field( 'post_name', $article_id );
    $article_path = '/news/' . $article_slug;
    $draft_slug = get_post_field( 'post_name', $draft_id );
    $draft_title = get_the_title( $draft_id );
    $draft_content = get_post_field( 'post_content', $draft_id );
    $draft_leaks = array( $draft_slug, $draft_title, $draft_content );
    $internal_leaks = array(
        'automation-smoke-key-not-real',
        'automation-smoke-secret-not-real',
        $fixture_webhook_url,
        $fixture_response_id,
        'gpt-5.6-luna',
        'kb_ai_generated',
        'kb_ai_model',
        'kb_ai_response_id',
        'kb_ai_confidence',
        'kb_verified_at',
        'kb_automation_key',
        '0.96',
        '0.98',
        'data-confidence="0.96"',
        'data-confidence="0.98"',
        '"confidence":0.96',
        '"confidence":0.98',
        'confidence&quot;:0.96',
        'confidence&quot;:0.98',
    );
    $assert_absent = function ( $surface, $content, array $needles ) {
        foreach ( $needles as $needle ) {
            if ( '' !== $needle && false !== strpos( $content, $needle ) ) {
                throw new Exception( $surface . ' exposed private discovery data: ' . $needle );
            }
        }
    };
    $reference_html = '';
    foreach ( array( 'OAI-SearchBot', 'GPTBot', 'Claude-SearchBot', 'PerplexityBot', 'Googlebot' ) as $agent ) {
        $response = $fetch( $article_path, $agent );
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            throw new Exception( 'crawler article request failed for ' . $agent );
        }
        $html = wp_remote_retrieve_body( $response );
        foreach ( array( get_the_title( $article_id ), 'data-kpopblog-fallback="article"', 'NewsArticle', 'https://example.com/bts-release' ) as $needle ) {
            if ( false === strpos( $html, $needle ) ) { throw new Exception( 'crawler article response missing ' . $needle ); }
        }
        $assert_absent( 'crawler article response for ' . $agent, $html, array_merge( $draft_leaks, $internal_leaks ) );
        if ( '' === $reference_html ) { $reference_html = $html; }
        if ( $html !== $reference_html ) { throw new Exception( 'crawler-specific HTML detected' ); }
    }

    $machine_documents = array();
    foreach ( array( '/sitemap.xml', '/rss.xml', '/llms.txt' ) as $path ) {
        $response = $fetch( $path, 'OAI-SearchBot' );
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            throw new Exception( 'machine discovery request failed for ' . $path );
        }
        $machine_documents[ $path ] = wp_remote_retrieve_body( $response );
        if ( false === strpos( $machine_documents[ $path ], $article_path ) ) {
            throw new Exception( 'machine discovery response missing article path for ' . $path );
        }
        $assert_absent( 'machine discovery response for ' . $path, $machine_documents[ $path ], array_merge( $draft_leaks, $internal_leaks ) );
    }

    $schedule_path = '/comebacks#event-' . $schedule_id;
    foreach ( array( '/rss.xml', '/llms.txt' ) as $path ) {
        if ( false === strpos( $machine_documents[ $path ], $schedule_path ) ) {
            throw new Exception( 'machine discovery response missing schedule path for ' . $path );
        }
    }

    $comebacks_response = $fetch( '/comebacks', 'Googlebot' );
    if ( is_wp_error( $comebacks_response ) || 200 !== wp_remote_retrieve_response_code( $comebacks_response ) ) {
        throw new Exception( 'crawler comeback request failed' );
    }
    $comebacks_html = wp_remote_retrieve_body( $comebacks_response );
    if ( false === strpos( $comebacks_html, get_the_title( $schedule_id ) ) ) {
        throw new Exception( 'crawler comeback response missing automation schedule title' );
    }
    $assert_absent( 'crawler comeback response', $comebacks_html, array_merge( $draft_leaks, $internal_leaks ) );

    $missing_slug = 'automation-discovery-missing-' . wp_generate_uuid4();
    foreach ( array( '/news/' . $draft_slug, '/news/' . $missing_slug ) as $path ) {
        $response = $fetch( $path, 'GPTBot' );
        if ( is_wp_error( $response ) || 404 !== wp_remote_retrieve_response_code( $response ) ) {
            throw new Exception( 'private or missing article did not return 404 for ' . $path );
        }
        $html = wp_remote_retrieve_body( $response );
        if ( false === strpos( $html, 'noindex, nofollow' ) ) {
            throw new Exception( 'private or missing article did not return noindex, nofollow for ' . $path );
        }
        $assert_absent(
            'private or missing article response for ' . $path,
            $html,
            array_merge( $draft_leaks, $internal_leaks, array( 'NewsArticle', 'data-kpopblog-fallback="article"' ) )
        );
    }

    foreach ( $internal_http_requests as $internal_url ) {
        if ( 0 !== strpos( $internal_url, 'http://wordpress/' ) ) {
            throw new Exception( 'external HTTP request escaped the isolation guard: ' . $internal_url );
        }
    }

    $second = kpopblog_run_automation( 'smoke' );
    $test_run_ids = array_values( array_unique( array_merge(
        array_map( 'intval', $test_run_ids ),
        array_values( array_diff( $get_fixture_run_ids(), $fixture_run_ids_before ) )
    ) ) );
    if ( is_wp_error( $second ) || 0 !== $second['created'] || 2 !== $second['skipped'] ) {
        throw new Exception( 'automation deduplication failed' );
    }

    do_action( 'admin_menu' );
    $has_menu = false;
    foreach ( isset( $submenu['kpopblog-admin'] ) ? $submenu['kpopblog-admin'] : array() as $item ) {
        if ( isset( $item[2] ) && 'kpopblog-automation' === $item[2] ) { $has_menu = true; }
    }
    if ( ! $has_menu ) {
        throw new Exception( 'automation administration menu missing' );
    }

    kpopblog_sync_automation_schedule();
    $record_test_cron_events( 'kpopblog_run_scheduled_automation', $automation_cron_before, $automation_cron_test_events );
    $automation_cron_during_test = $capture_cron_hook( 'kpopblog_run_scheduled_automation' );
    $automation_event_count = 0;
    foreach ( $automation_cron_during_test as $events ) { $automation_event_count += count( $events ); }
    $automation_guard_events = $automation_cron_during_test[ (string) $automation_guard_timestamp ] ?? array();
    $automation_guard_is_safe = false;
    foreach ( $automation_guard_events as $event ) {
        if ( 'twicedaily' === ( $event['schedule'] ?? '' ) && empty( $event['args'] ) ) {
            $automation_guard_is_safe = true;
        }
    }
    if ( 1 !== $automation_event_count || ! $automation_guard_is_safe ) {
        throw new Exception( 'automation cron was not suppressed by the cross-process future guard' );
    }
} finally {
    if ( $draft_id && ! is_wp_error( $draft_id ) ) { wp_delete_post( (int) $draft_id, true ); }
    $cleanup_post_ids = array_values( array_unique( array_merge( array_map( 'intval', $created_ids ), $get_marker_post_ids() ) ) );
    foreach ( $cleanup_post_ids as $post_id ) { wp_delete_post( (int) $post_id, true ); }

    $test_notification_job_ids = array_values( array_unique( array_merge(
        array_map( 'intval', $test_notification_job_ids ),
        array_values( array_diff( $get_fixture_job_ids(), $notification_job_ids_before ) )
    ) ) );
    foreach ( $test_notification_job_ids as $notification_job_id ) {
        $wpdb->delete( $wpdb->prefix . 'kb_notifications', array( 'job_id' => (int) $notification_job_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'kb_notification_jobs', array( 'id' => (int) $notification_job_id ), array( '%d' ) );
    }

    $test_run_ids = array_values( array_unique( array_merge(
        array_map( 'intval', $test_run_ids ),
        array_values( array_diff( $get_fixture_run_ids(), $fixture_run_ids_before ) )
    ) ) );
    foreach ( $test_run_ids as $test_run_id ) {
        $wpdb->delete(
            $wpdb->prefix . 'kb_audit_log',
            array( 'action' => 'automation_completed', 'object_type' => 'automation_run', 'object_id' => (int) $test_run_id ),
            array( '%s', '%s', '%d' )
        );
    }
    $wpdb->delete( $wpdb->prefix . 'kb_automation_items', array( 'response_id' => $fixture_response_id ), array( '%s' ) );
    foreach ( $test_run_ids as $test_run_id ) {
        $wpdb->delete( $wpdb->prefix . 'kb_automation_runs', array( 'id' => (int) $test_run_id ), array( '%d' ) );
    }

    if ( $automation_last_success_exists_before ) {
        update_option( 'kpopblog_automation_last_success', $automation_last_success_before, false );
    } else {
        delete_option( 'kpopblog_automation_last_success' );
    }

    remove_action( 'update_option_' . KPOPBLOG_AUTOMATION_OPTION, 'kpopblog_sync_automation_schedule', 10 );
    if ( null === $old_settings ) {
        delete_option( 'kpopblog_automation' );
    } else {
        update_option( 'kpopblog_automation', $old_settings, false );
    }
    add_action( 'update_option_' . KPOPBLOG_AUTOMATION_OPTION, 'kpopblog_sync_automation_schedule', 10, 0 );
    if ( null === $old_webhook_settings ) {
        delete_option( KPOPBLOG_WEBHOOK_OPTION );
    } else {
        update_option( KPOPBLOG_WEBHOOK_OPTION, $old_webhook_settings, false );
    }

    $notification_lock_error = '';
    try {
        $record_test_cron_events( 'kpopblog_process_notification_jobs', $notification_cron_before, $notification_cron_test_events );
        $record_test_cron_events( 'kpopblog_run_scheduled_automation', $automation_cron_before, $automation_cron_test_events );
        if ( $notification_guard_timestamp ) {
            wp_unschedule_event( $notification_guard_timestamp, 'kpopblog_process_notification_jobs' );
        }
        if ( $automation_guard_timestamp ) {
            wp_unschedule_event( $automation_guard_timestamp, 'kpopblog_run_scheduled_automation' );
        }
        $restore_cron_hook( 'kpopblog_process_notification_jobs', $notification_cron_before, $notification_cron_test_events );
        $restore_cron_hook( 'kpopblog_run_scheduled_automation', $automation_cron_before, $automation_cron_test_events );
    } finally {
        if ( $notification_lock_owned ) {
            if ( $notification_lock_token !== get_transient( $notification_lock_name ) ) {
                $notification_lock_error = 'notification processing lock ownership was lost during cleanup';
            } else {
                delete_transient( $notification_lock_name );
                if ( null !== $notification_lock_value_before ) {
                    update_option( '_transient_' . $notification_lock_name, $notification_lock_value_before, false );
                }
                if ( null !== $notification_lock_timeout_before ) {
                    update_option( '_transient_timeout_' . $notification_lock_name, $notification_lock_timeout_before, false );
                }
            }
        }

        remove_filter( 'pre_http_request', $mock_http, 10 );
        putenv( 'OPENAI_API_KEY' );
        wp_set_current_user( 0 );
        if ( '' !== $notification_lock_error ) {
            throw new Exception( $notification_lock_error );
        }
    }
}

if ( $get_marker_post_ids() ) {
    throw new Exception( 'automation marker posts remained after cleanup' );
}
$fixture_job_ids_after = $get_fixture_job_ids();
sort( $fixture_job_ids_after );
sort( $notification_job_ids_before );
if ( $fixture_job_ids_after !== $notification_job_ids_before ) {
    throw new Exception( 'automation notification jobs were not restored to their pre-test state' );
}
$fixture_run_ids_after = $get_fixture_run_ids();
sort( $fixture_run_ids_after );
sort( $fixture_run_ids_before );
if ( $fixture_run_ids_after !== $fixture_run_ids_before ) {
    throw new Exception( 'automation smoke runs were not restored to their pre-test state' );
}
foreach ( $test_run_ids as $test_run_id ) {
    $test_audit_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}kb_audit_log WHERE action = 'automation_completed' AND object_type = 'automation_run' AND object_id = %d",
        $test_run_id
    ) );
    if ( 0 !== $test_audit_count ) {
        throw new Exception( 'automation audit rows remained after cleanup for run ' . $test_run_id );
    }
}
$automation_last_success_exists_after = 1 === (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s",
    'kpopblog_automation_last_success'
) );
if ( $automation_last_success_exists_after !== $automation_last_success_exists_before ) {
    throw new Exception( 'automation last-success option existence was not restored after cleanup' );
}
if ( $automation_last_success_exists_before && get_option( 'kpopblog_automation_last_success' ) !== $automation_last_success_before ) {
    throw new Exception( 'automation last-success option value was not restored after cleanup' );
}
if ( $capture_cron_hook( 'kpopblog_process_notification_jobs' ) !== $notification_cron_before ) {
    throw new Exception( 'notification processing cron was not restored to its pre-test state' );
}
if ( $capture_cron_hook( 'kpopblog_run_scheduled_automation' ) !== $automation_cron_before ) {
    throw new Exception( 'automation cron was not restored to its pre-test state' );
}
if ( get_option( 'kpopblog_automation', null ) !== $old_settings || get_option( KPOPBLOG_WEBHOOK_OPTION, null ) !== $old_webhook_settings ) {
    throw new Exception( 'automation or webhook settings were not restored after cleanup' );
}
if ( get_option( '_transient_' . $notification_lock_name, null ) !== $notification_lock_value_before || get_option( '_transient_timeout_' . $notification_lock_name, null ) !== $notification_lock_timeout_before ) {
    throw new Exception( 'notification processing lock was not restored to its pre-test state' );
}
'@

docker compose --env-file $envFile -f $compose run --rm --no-deps cli eval $assertions
if ($LASTEXITCODE -ne 0) {
    throw 'AI automation assertions failed.'
}

Write-Host 'AI automation smoke test passed.'
