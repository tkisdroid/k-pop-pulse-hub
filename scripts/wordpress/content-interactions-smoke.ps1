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
$user_id = 0;
$article_id = 0;
$video_id = 0;
$artist_id = 0;
$submission_id = 0;
$comment_ids = array();
$previous_comment_moderation = get_option( 'comment_moderation' );
$baseline_job_id = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id), 0) FROM {$wpdb->prefix}kb_notification_jobs" );
$run_token = strtolower( wp_generate_password( 8, false, false ) );
$test_login = 'content_smoke_' . $run_token;

try {
    foreach ( array( 'kb_video', 'kb_submission', 'kb_artist' ) as $post_type ) {
        if ( ! post_type_exists( $post_type ) ) {
            throw new Exception( 'post type missing: ' . $post_type );
        }
    }

    $user_id = wp_insert_user( array(
        'user_login'   => $test_login,
        'user_email'   => $test_login . '@example.test',
        'user_pass'    => wp_generate_password( 24, true, true ),
        'display_name' => 'Content Smoke',
        'role'         => 'subscriber',
    ) );
    if ( is_wp_error( $user_id ) ) {
        throw new Exception( 'failed to create content smoke user' );
    }

    wp_set_current_user( $user_id );
    update_option( 'comment_moderation', 0 );
    add_filter( 'comment_flood_filter', '__return_false' );

    $article_id = wp_insert_post( array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'post_title'     => 'Content smoke article ' . $run_token,
        'post_name'      => 'content-smoke-article-' . $run_token,
        'post_content'   => 'A temporary article used to verify WordPress content interactions.',
        'post_author'    => $user_id,
        'comment_status' => 'open',
    ), true );
    $video_id = wp_insert_post( array(
        'post_type'      => 'kb_video',
        'post_status'    => 'publish',
        'post_title'     => 'Content smoke video ' . $run_token,
        'post_name'      => 'content-smoke-video-' . $run_token,
        'post_content'   => 'A temporary video used to verify comments.',
        'post_author'    => $user_id,
        'comment_status' => 'open',
    ), true );
    $artist_id = wp_insert_post( array(
        'post_type'    => 'kb_artist',
        'post_status'  => 'publish',
        'post_title'   => 'Content Smoke Artist ' . $run_token,
        'post_name'    => 'content-smoke-artist-' . $run_token,
        'post_content' => 'A temporary artist used to verify follows.',
    ), true );
    foreach ( array( $article_id, $video_id, $artist_id ) as $post_id ) {
        if ( is_wp_error( $post_id ) || ! $post_id ) {
            throw new Exception( 'failed to create content smoke fixture' );
        }
    }

    $submission_request = new WP_REST_Request( 'POST', '/kpopblog/v1/submissions' );
    $submission_request->set_body_params( array(
        'type'    => 'news-tip',
        'subject' => 'Content smoke submission ' . $run_token,
        'details' => 'Temporary submission details for the content interaction smoke test.',
    ) );
    $submission_response = rest_do_request( $submission_request );
    $submission_data = $submission_response->get_data();
    if ( 201 !== $submission_response->get_status() || 'pending' !== $submission_data['status'] ) {
        throw new Exception( 'submission endpoint did not create a pending item' );
    }
    $submission_id = (int) $submission_data['id'];
    if ( 'kb_submission' !== get_post_type( $submission_id ) || 'pending' !== get_post_status( $submission_id ) ) {
        throw new Exception( 'submission was not persisted in the editorial queue' );
    }

    $article_slug = get_post_field( 'post_name', $article_id );
    $root_request = new WP_REST_Request( 'POST', '/kpopblog/v1/articles/' . $article_slug . '/comments' );
    $root_request->set_body_params( array( 'body' => 'Root article comment from the smoke test.' ) );
    $root_response = rest_do_request( $root_request );
    $root_data = $root_response->get_data();
    if ( 200 !== $root_response->get_status() || empty( $root_data['item']['id'] ) ) {
        throw new Exception( 'article comment endpoint failed' );
    }
    $root_comment_id = (int) $root_data['item']['id'];
    $comment_ids[] = $root_comment_id;

    $reply_request = new WP_REST_Request( 'POST', '/kpopblog/v1/articles/' . $article_slug . '/comments' );
    $reply_request->set_body_params( array( 'body' => 'Nested reply from the smoke test.', 'parentId' => $root_comment_id ) );
    $reply_response = rest_do_request( $reply_request );
    $reply_data = $reply_response->get_data();
    if ( 200 !== $reply_response->get_status() || (string) $root_comment_id !== (string) $reply_data['item']['parentId'] ) {
        throw new Exception( 'article reply relationship was not persisted' );
    }
    $reply_comment_id = (int) $reply_data['item']['id'];
    $comment_ids[] = $reply_comment_id;
    if ( (int) get_comment( $reply_comment_id )->comment_parent !== $root_comment_id ) {
        throw new Exception( 'WordPress comment_parent does not match the requested article reply' );
    }

    $article_list_response = rest_do_request( new WP_REST_Request( 'GET', '/kpopblog/v1/articles/' . $article_slug . '/comments' ) );
    $article_items = $article_list_response->get_data();
    $listed_reply = array_values( array_filter( $article_items, function ( $item ) use ( $reply_comment_id ) { return (int) $item['id'] === $reply_comment_id; } ) );
    if ( 200 !== $article_list_response->get_status() || ! $listed_reply || (string) $root_comment_id !== (string) $listed_reply[0]['parentId'] ) {
        throw new Exception( 'article comments endpoint did not return the reply relationship' );
    }

    $video_slug = get_post_field( 'post_name', $video_id );
    $video_comment_request = new WP_REST_Request( 'POST', '/kpopblog/v1/videos/' . $video_slug . '/comments' );
    $video_comment_request->set_body_params( array( 'body' => 'Video comment from the smoke test.' ) );
    $video_comment_response = rest_do_request( $video_comment_request );
    $video_comment_data = $video_comment_response->get_data();
    if ( 200 !== $video_comment_response->get_status() || empty( $video_comment_data['item']['id'] ) ) {
        throw new Exception( 'video comment endpoint failed: status=' . $video_comment_response->get_status() . ' data=' . wp_json_encode( $video_comment_data ) );
    }
    $video_comment_id = (int) $video_comment_data['item']['id'];
    $comment_ids[] = $video_comment_id;
    $video_list_response = rest_do_request( new WP_REST_Request( 'GET', '/kpopblog/v1/videos/' . $video_slug . '/comments' ) );
    $video_items = $video_list_response->get_data();
    if ( 200 !== $video_list_response->get_status() || ! array_filter( $video_items, function ( $item ) use ( $video_comment_id ) { return (int) $item['id'] === $video_comment_id; } ) ) {
        throw new Exception( 'video comments endpoint did not return the saved comment' );
    }

    $artist_slug = get_post_field( 'post_name', $artist_id );
    $follow_response = rest_do_request( new WP_REST_Request( 'POST', '/kpopblog/v1/artists/' . $artist_slug . '/follow' ) );
    $follow_data = $follow_response->get_data();
    if ( 200 !== $follow_response->get_status() || empty( $follow_data['following'] ) || ! in_array( $artist_id, kpopblog_subscription_state( $user_id )['artists'], true ) ) {
        throw new Exception( 'artist follow was not persisted' );
    }
    $unfollow_response = rest_do_request( new WP_REST_Request( 'POST', '/kpopblog/v1/artists/' . $artist_slug . '/follow' ) );
    $unfollow_data = $unfollow_response->get_data();
    if ( 200 !== $unfollow_response->get_status() || ! empty( $unfollow_data['following'] ) || in_array( $artist_id, kpopblog_subscription_state( $user_id )['artists'], true ) ) {
        throw new Exception( 'artist unfollow was not persisted' );
    }
} finally {
    remove_filter( 'comment_flood_filter', '__return_false' );
    update_option( 'comment_moderation', $previous_comment_moderation );
    wp_set_current_user( 1 );
    foreach ( array_reverse( $comment_ids ) as $comment_id ) {
        wp_delete_comment( $comment_id, true );
    }
    foreach ( array( $submission_id, $video_id, $artist_id, $article_id ) as $post_id ) {
        if ( $post_id && ! is_wp_error( $post_id ) ) {
            wp_delete_post( $post_id, true );
        }
    }
    if ( $user_id && ! is_wp_error( $user_id ) ) {
        $job_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}kb_notification_jobs WHERE id > %d AND created_by = %d",
            $baseline_job_id,
            $user_id
        ) );
        foreach ( $job_ids as $job_id ) {
            $wpdb->delete( $wpdb->prefix . 'kb_notifications', array( 'job_id' => (int) $job_id ), array( '%d' ) );
            $wpdb->delete( $wpdb->prefix . 'kb_notification_jobs', array( 'id' => (int) $job_id ), array( '%d' ) );
        }
        $wpdb->delete( $wpdb->prefix . 'kb_notifications', array( 'user_id' => $user_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'kb_audit_log', array( 'actor_id' => $user_id ), array( '%d' ) );
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $user_id );
    }
}
'@

docker compose --env-file $envFile -f $compose run --rm --no-deps cli eval $assertions
if ($LASTEXITCODE -ne 0) {
    throw 'Content interaction assertions failed.'
}

Write-Host 'Content interaction smoke test passed.'
