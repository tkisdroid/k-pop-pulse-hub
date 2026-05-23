<?php
/**
 * KpopBlog → Real-time event bus.
 *
 * Pushes events (comment, follow, subscribe, comeback, article) two ways:
 *   1. Out-bound webhook POST to an admin-configured URL, signed with an
 *      HMAC-SHA256 X-KB-Signature header.
 *   2. In-bound polling: the React app long-polls
 *      GET /wp-json/kpopblog/v1/events?since=<id>&limit=<n>
 *      and merges new items into its notification center.
 *
 * Events are stored in the wp_options table as a capped ring buffer keyed by
 * a monotonically-increasing integer ID so the SPA can cursor through them.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const KPOPBLOG_EVENTS_OPTION   = 'kpopblog_event_log';
const KPOPBLOG_EVENT_SEQ       = 'kpopblog_event_seq';
const KPOPBLOG_WEBHOOK_OPTION  = 'kpopblog_webhook';
const KPOPBLOG_EVENT_LIMIT     = 200;

/* ---------- settings ---------- */

function kpopblog_webhook_defaults() {
	return array( 'url' => '', 'secret' => '', 'enabled' => 0 );
}

function kpopblog_get_webhook_settings() {
	$s = get_option( KPOPBLOG_WEBHOOK_OPTION, array() );
	if ( ! is_array( $s ) ) { $s = array(); }
	return array_merge( kpopblog_webhook_defaults(), $s );
}

function kpopblog_register_webhook_settings() {
	register_setting( 'kpopblog_webhook_group', KPOPBLOG_WEBHOOK_OPTION, array(
		'type'              => 'array',
		'sanitize_callback' => function ( $input ) {
			return array(
				'url'     => isset( $input['url'] ) ? esc_url_raw( trim( (string) $input['url'] ) ) : '',
				'secret'  => isset( $input['secret'] ) ? sanitize_text_field( (string) $input['secret'] ) : '',
				'enabled' => ! empty( $input['enabled'] ) ? 1 : 0,
			);
		},
	) );

	add_submenu_page(
		'options-general.php',
		'KpopBlog Webhook',
		'KpopBlog Webhook',
		'manage_options',
		'kpopblog-webhook',
		'kpopblog_render_webhook_page'
	);
}
add_action( 'admin_init', 'kpopblog_register_webhook_settings' );
add_action( 'admin_menu', function () { /* submenu registered above via admin_init add_submenu_page */ } );

function kpopblog_render_webhook_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$s = kpopblog_get_webhook_settings();
	?>
	<div class="wrap">
		<h1>KpopBlog — Realtime Webhook</h1>
		<p>POSTs JSON to your endpoint on every comment, follow, subscribe, comeback, and article event. Sign verification: <code>X-KB-Signature: sha256=&lt;hmac&gt;</code> over the raw body.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'kpopblog_webhook_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="kb_wh_enabled">Enable outbound webhook</label></th>
					<td><input type="checkbox" id="kb_wh_enabled" name="<?php echo esc_attr( KPOPBLOG_WEBHOOK_OPTION ); ?>[enabled]" value="1" <?php checked( 1, $s['enabled'] ); ?> /></td>
				</tr>
				<tr>
					<th><label for="kb_wh_url">Webhook URL</label></th>
					<td><input type="url" id="kb_wh_url" name="<?php echo esc_attr( KPOPBLOG_WEBHOOK_OPTION ); ?>[url]" value="<?php echo esc_attr( $s['url'] ); ?>" class="regular-text" placeholder="https://example.com/api/public/kpopblog-webhook" /></td>
				</tr>
				<tr>
					<th><label for="kb_wh_secret">Signing secret</label></th>
					<td>
						<input type="text" id="kb_wh_secret" name="<?php echo esc_attr( KPOPBLOG_WEBHOOK_OPTION ); ?>[secret]" value="<?php echo esc_attr( $s['secret'] ); ?>" class="regular-text" />
						<p class="description">Used to compute the HMAC-SHA256 signature header.</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
			<p class="description">In-app polling is always enabled at <code><?php echo esc_html( rest_url( KPOPBLOG_REST_NS . '/events' ) ); ?></code>.</p>
		</form>
	</div>
	<?php
}

/* ---------- event store ---------- */

function kpopblog_next_event_id() {
	$next = (int) get_option( KPOPBLOG_EVENT_SEQ, 0 ) + 1;
	update_option( KPOPBLOG_EVENT_SEQ, $next, false );
	return $next;
}

/**
 * Append an event to the ring buffer and fire the outbound webhook.
 *
 * @param string   $kind     One of: reply, follow, subscribe, article, comeback, system.
 * @param array    $payload  Arbitrary serializable payload (title/body/href/image/etc.).
 * @param int|null $target   Target user ID, or null for broadcast.
 */
function kpopblog_emit_event( $kind, array $payload, $target = null ) {
	$event = array(
		'id'        => kpopblog_next_event_id(),
		'kind'      => sanitize_key( $kind ),
		'targetUserId' => $target ? (int) $target : null,
		'payload'   => $payload,
		'createdAt' => gmdate( 'c' ),
	);
	$log = get_option( KPOPBLOG_EVENTS_OPTION, array() );
	if ( ! is_array( $log ) ) { $log = array(); }
	$log[] = $event;
	if ( count( $log ) > KPOPBLOG_EVENT_LIMIT ) {
		$log = array_slice( $log, -KPOPBLOG_EVENT_LIMIT );
	}
	update_option( KPOPBLOG_EVENTS_OPTION, $log, false );

	kpopblog_dispatch_webhook( $event );
	return $event;
}

function kpopblog_dispatch_webhook( array $event ) {
	$s = kpopblog_get_webhook_settings();
	if ( empty( $s['enabled'] ) || empty( $s['url'] ) ) { return; }
	$body = wp_json_encode( $event );
	$sig  = $s['secret'] ? 'sha256=' . hash_hmac( 'sha256', $body, $s['secret'] ) : '';
	wp_remote_post( $s['url'], array(
		'headers'  => array_filter( array(
			'Content-Type'    => 'application/json',
			'X-KB-Signature'  => $sig,
			'X-KB-Event-Kind' => $event['kind'],
		) ),
		'body'     => $body,
		'timeout'  => 4,
		'blocking' => false,
	) );
}

/* ---------- domain hooks ---------- */

// New comment → notify the post author (skip self-comments).
add_action( 'comment_post', function ( $comment_id, $approved ) {
	if ( ! $comment_id ) { return; }
	$c = get_comment( $comment_id );
	if ( ! $c ) { return; }
	$post = get_post( $c->comment_post_ID );
	if ( ! $post || (int) $post->post_author === (int) $c->user_id ) { return; }
	kpopblog_emit_event( 'reply', array(
		'title' => sprintf( '%s replied to "%s"', $c->comment_author, get_the_title( $post ) ),
		'body'  => wp_trim_words( wp_strip_all_tags( $c->comment_content ), 24 ),
		'href'  => '/news/' . $post->post_name,
		'pending' => 1 !== (int) $approved,
	), (int) $post->post_author );
}, 10, 2 );

// Follow toggled (fired from rest-write.php).
add_action( 'kb_follow_toggled', function ( $artist_post_id, $user_id, $now_following ) {
	$artist = get_post( $artist_post_id );
	if ( ! $artist ) { return; }
	kpopblog_emit_event( 'follow', array(
		'title' => $now_following
			? sprintf( 'You are now following %s', $artist->post_title )
			: sprintf( 'You unfollowed %s', $artist->post_title ),
		'href'  => '/artist/' . $artist->post_name,
	), (int) $user_id );
}, 10, 3 );

// User subscribed to the newsletter / artist feed (fired from /subscribe).
add_action( 'kb_subscribed', function ( $user_id, $topic ) {
	kpopblog_emit_event( 'system', array(
		'title' => 'Subscription confirmed',
		'body'  => sprintf( "You'll receive updates for %s.", $topic ),
		'href'  => '/profile',
	), (int) $user_id );
}, 10, 2 );

// New published article (broadcast).
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( $new !== 'publish' || $old === 'publish' || $post->post_type !== 'post' ) { return; }
	kpopblog_emit_event( 'article', array(
		'title' => 'New article: ' . get_the_title( $post ),
		'body'  => wp_trim_words( wp_strip_all_tags( $post->post_content ), 24 ),
		'href'  => '/news/' . $post->post_name,
		'image' => kpopblog_thumb_url( $post->ID ),
	) );
}, 10, 3 );

// New comeback scheduled (broadcast).
add_action( 'save_post_kb_comeback', function ( $post_id, $post, $update ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) { return; }
	if ( $post->post_status !== 'publish' ) { return; }
	$release = (string) get_post_meta( $post_id, 'kb_release_at', true );
	kpopblog_emit_event( 'comeback', array(
		'title' => sprintf( 'Comeback: %s', get_the_title( $post ) ),
		'body'  => $release ? ( 'Releases ' . $release ) : '',
		'href'  => '/comebacks',
		'image' => kpopblog_thumb_url( $post_id ),
	) );
}, 10, 3 );

/* ---------- REST endpoints ---------- */

function kpopblog_register_event_routes() {
	register_rest_route( KPOPBLOG_REST_NS, '/events', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'args'                => array(
			'since' => array( 'type' => 'integer', 'default' => 0 ),
			'limit' => array( 'type' => 'integer', 'default' => 50 ),
		),
		'callback'            => function ( WP_REST_Request $r ) {
			$since = max( 0, (int) $r->get_param( 'since' ) );
			$limit = max( 1, min( 100, (int) $r->get_param( 'limit' ) ) );
			$me    = get_current_user_id();
			$log   = get_option( KPOPBLOG_EVENTS_OPTION, array() );
			if ( ! is_array( $log ) ) { $log = array(); }
			$out   = array();
			foreach ( $log as $e ) {
				if ( (int) $e['id'] <= $since ) { continue; }
				$target = isset( $e['targetUserId'] ) ? $e['targetUserId'] : null;
				if ( $target !== null && (int) $target !== (int) $me ) { continue; }
				$out[] = $e;
				if ( count( $out ) >= $limit ) { break; }
			}
			$cursor = $out ? (int) end( $out )['id'] : (int) get_option( KPOPBLOG_EVENT_SEQ, 0 );
			return rest_ensure_response( array( 'events' => array_values( $out ), 'cursor' => $cursor ) );
		},
	) );

	register_rest_route( KPOPBLOG_REST_NS, '/subscribe', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return is_user_logged_in(); },
		'args'                => array(
			'topic' => array( 'type' => 'string', 'required' => true ),
		),
		'callback'            => function ( WP_REST_Request $r ) {
			$topic = sanitize_text_field( (string) $r->get_param( 'topic' ) );
			if ( $topic === '' ) { return new WP_Error( 'bad_input', 'topic required', array( 'status' => 400 ) ); }
			$uid   = get_current_user_id();
			$subs  = (array) get_user_meta( $uid, 'kb_subscriptions', true );
			if ( ! in_array( $topic, $subs, true ) ) { $subs[] = $topic; }
			update_user_meta( $uid, 'kb_subscriptions', $subs );
			do_action( 'kb_subscribed', $uid, $topic );
			return rest_ensure_response( array( 'ok' => true, 'subscriptions' => $subs ) );
		},
	) );
}
add_action( 'rest_api_init', 'kpopblog_register_event_routes' );
