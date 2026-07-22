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

$old_settings = get_option( 'kpopblog_automation', null );
update_option( 'kpopblog_automation', array(
    'enabled'       => 1,
    'auto_publish'  => 1,
    'model'         => 'gpt-5.6-luna',
    'frequency'     => 'twicedaily',
    'max_items'     => 4,
    'artist_focus'  => 'BTS, BLACKPINK',
), false );

putenv( 'OPENAI_API_KEY=automation-smoke-key-not-real' );
$captured_request = array();
$mock_http = function ( $preempt, $args, $url ) use ( &$captured_request ) {
    if ( 'https://api.openai.com/v1/responses' !== $url ) {
        return $preempt;
    }
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
                                    'title'         => 'The &#8220;Jimin Effect&#8221; and the High-Fashion Renaissance',
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
};
add_filter( 'pre_http_request', $mock_http, 10, 3 );

$created_ids = array();
try {
    $result = kpopblog_run_automation( 'smoke' );
    if ( is_wp_error( $result ) ) {
        throw new Exception( 'automation run failed: ' . $result->get_error_message() );
    }
    if ( 2 !== $result['discovered'] || 2 !== $result['created'] ) {
        throw new Exception( 'automation did not persist both verified items: ' . wp_json_encode( $result ) );
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

    $created_ids = $wpdb->get_col( "SELECT wp_post_id FROM {$wpdb->prefix}kb_automation_items WHERE response_id = 'resp_automation_smoke'" );
    if ( 2 !== count( $created_ids ) ) {
        throw new Exception( 'automation provenance rows missing' );
    }
    foreach ( $created_ids as $post_id ) {
        if ( '1' !== get_post_meta( $post_id, 'kb_ai_generated', true ) || '' === get_post_meta( $post_id, 'kb_source_url', true ) ) {
            throw new Exception( 'automation provenance post meta missing' );
        }
        if ( 'publish' !== get_post_status( $post_id ) ) {
            throw new Exception( 'verified automation item was not auto-published' );
        }
    }
    $entity_title_post = null;
    foreach ( $created_ids as $post_id ) {
        $candidate = get_post( (int) $post_id );
        if ( $candidate && false !== strpos( $candidate->post_title, 'Jimin Effect' ) ) {
            $entity_title_post = $candidate;
            break;
        }
    }
    $expected_entity_title = html_entity_decode( 'The &#8220;Jimin Effect&#8221; and the High-Fashion Renaissance', ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    if ( ! $entity_title_post || $expected_entity_title !== $entity_title_post->post_title ) {
        throw new Exception( 'automation did not normalize HTML entities in the title' );
    }
    $entity_title_api = kpopblog_map_article( $entity_title_post );
    if ( $expected_entity_title !== $entity_title_api['title'] ) {
        throw new Exception( 'REST article mapping did not normalize HTML entities' );
    }

    $second = kpopblog_run_automation( 'smoke' );
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
    if ( ! wp_next_scheduled( 'kpopblog_run_scheduled_automation' ) ) {
        throw new Exception( 'automation cron event missing' );
    }
} finally {
    remove_filter( 'pre_http_request', $mock_http, 10 );
    putenv( 'OPENAI_API_KEY' );
    wp_set_current_user( 0 );
    wp_clear_scheduled_hook( 'kpopblog_run_scheduled_automation' );
    foreach ( $created_ids as $post_id ) { wp_delete_post( (int) $post_id, true ); }
    $notification_job_ids = $wpdb->get_col(
        "SELECT id FROM {$wpdb->prefix}kb_notification_jobs WHERE title LIKE 'New article: %Jimin Effect%' OR title = 'Comeback: BLACKPINK Seoul concert'"
    );
    foreach ( $notification_job_ids as $notification_job_id ) {
        $wpdb->delete( $wpdb->prefix . 'kb_notifications', array( 'job_id' => (int) $notification_job_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'kb_notification_jobs', array( 'id' => (int) $notification_job_id ), array( '%d' ) );
    }
    $wpdb->query( "DELETE FROM {$wpdb->prefix}kb_automation_items WHERE response_id = 'resp_automation_smoke'" );
    $wpdb->query( "DELETE FROM {$wpdb->prefix}kb_automation_runs WHERE trigger_type = 'smoke'" );
    if ( null === $old_settings ) {
        delete_option( 'kpopblog_automation' );
    } else {
        update_option( 'kpopblog_automation', $old_settings, false );
    }
}
'@

docker compose --env-file $envFile -f $compose run --rm --no-deps cli eval $assertions
if ($LASTEXITCODE -ne 0) {
    throw 'AI automation assertions failed.'
}

Write-Host 'AI automation smoke test passed.'
