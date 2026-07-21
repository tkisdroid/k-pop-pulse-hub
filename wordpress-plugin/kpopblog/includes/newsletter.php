<?php
/**
 * Newsletter subscriptions.
 *
 * - Custom post type `kb_subscriber` stores each opt-in (email, frequency,
 *   topics, source, double-opt-in token, confirmation status).
 * - Settings page lets admins configure default frequency, available topics,
 *   CTA copy, success/confirmation message, and an optional ESP webhook
 *   (Mailchimp/Brevo/ConvertKit/MailerLite/etc.) so subscribers are mirrored
 *   to whatever sending service the editorial team uses.
 * - Public REST endpoints (no nonce required, IP rate-limited) handle
 *   subscribe / confirm / unsubscribe so the SPA can present a CTA anywhere.
 *
 * @package KpopBlog
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---------- CPT ---------- */
add_action( 'init', function () {
	register_post_type( 'kb_subscriber', array(
		'label'           => 'Newsletter subscribers',
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => 'kpopblog-admin',
		'menu_icon'       => 'dashicons-email-alt',
		'capability_type' => 'post',
		'map_meta_cap'    => true,
		'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
		'supports'        => array( 'title' ),
	) );

	foreach ( array(
		'kb_sub_email'        => 'string',
		'kb_sub_frequency'    => 'string',
		'kb_sub_topics'       => 'array',
		'kb_sub_source'       => 'string',
		'kb_sub_confirm_hash' => 'string',
		'kb_sub_unsub_hash'   => 'string',
		'kb_sub_token_expires'=> 'string',
		'kb_sub_confirmed'    => 'boolean',
		'kb_sub_confirmed_at' => 'string',
		'kb_sub_unsub_at'     => 'string',
		'kb_sub_consent_at'   => 'string',
		'kb_sub_suppressed'   => 'boolean',
		'kb_sub_locale'       => 'string',
	) as $key => $type ) {
		register_post_meta( 'kb_subscriber', $key, array(
			'show_in_rest' => false,
			'single'       => true,
			'type'         => $type === 'array' ? 'string' : $type,
		) );
	}
} );

/* ---------- Settings ---------- */
function kpopblog_newsletter_defaults() {
	return array(
		'cta_eyebrow'         => 'Stay in the loop',
		'cta_heading'         => 'Get the weekly K-pop briefing',
		'cta_subheading'      => 'Comebacks, chart movements, tour dates and member news — straight to your inbox. No spam, unsubscribe anytime.',
		'cta_button'          => 'Subscribe',
		'success_message'     => "You're in! Check your inbox to confirm your subscription.",
		'confirm_message'     => 'Thanks — your subscription is confirmed.',
		'default_frequency'   => 'weekly',
		'available_topics'    => array( 'comebacks', 'charts', 'tours', 'member-updates', 'editorial' ),
		'double_opt_in'       => true,
		'signup_opt_in_label' => 'Email me the weekly KpopBlog newsletter (you can unsubscribe anytime).',
		'esp_provider'        => '',
		'esp_webhook_url'     => '',
		'esp_list_id'         => '',
		'esp_api_key'         => '',
	);
}

function kpopblog_newsletter_settings() {
	$saved = get_option( 'kpopblog_newsletter', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), kpopblog_newsletter_defaults() );
}

add_action( 'admin_init', function () {
	register_setting( 'kpopblog_newsletter', 'kpopblog_newsletter', array(
		'type'              => 'array',
		'sanitize_callback' => function ( $input ) {
			$out = kpopblog_newsletter_defaults();
			if ( ! is_array( $input ) ) return $out;
			foreach ( array( 'cta_eyebrow', 'cta_heading', 'cta_subheading', 'cta_button', 'success_message', 'confirm_message', 'signup_opt_in_label', 'esp_provider', 'esp_list_id' ) as $k ) {
				if ( isset( $input[ $k ] ) ) $out[ $k ] = sanitize_text_field( (string) $input[ $k ] );
			}
			if ( isset( $input['esp_webhook_url'] ) ) {
				$out['esp_webhook_url'] = esc_url_raw( (string) $input['esp_webhook_url'] );
			}
			if ( isset( $input['esp_api_key'] ) ) {
				$out['esp_api_key'] = trim( (string) $input['esp_api_key'] );
			}
			$out['default_frequency'] = in_array( $input['default_frequency'] ?? '', array( 'daily', 'weekly', 'monthly' ), true ) ? $input['default_frequency'] : 'weekly';
			$out['double_opt_in']     = ! empty( $input['double_opt_in'] );
			if ( isset( $input['available_topics'] ) ) {
				$topics = is_array( $input['available_topics'] ) ? $input['available_topics'] : explode( ',', (string) $input['available_topics'] );
				$out['available_topics'] = array_values( array_filter( array_map( function ( $t ) {
					return sanitize_key( trim( (string) $t ) );
				}, $topics ) ) );
			}
			return $out;
		},
	) );
} );

add_action( 'admin_menu', function () {
	add_submenu_page( 'kpopblog-admin', 'Newsletter settings', 'Newsletter settings', 'manage_options', 'kpopblog-newsletter', function () {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$s = kpopblog_newsletter_settings();
		?>
		<div class="wrap">
			<h1>Newsletter settings</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'kpopblog_newsletter' ); ?>
				<table class="form-table" role="presentation">
					<tr><th><label>CTA eyebrow</label></th><td><input type="text" name="kpopblog_newsletter[cta_eyebrow]" value="<?php echo esc_attr( $s['cta_eyebrow'] ); ?>" class="regular-text"></td></tr>
					<tr><th><label>CTA heading</label></th><td><input type="text" name="kpopblog_newsletter[cta_heading]" value="<?php echo esc_attr( $s['cta_heading'] ); ?>" class="regular-text"></td></tr>
					<tr><th><label>CTA subheading</label></th><td><textarea name="kpopblog_newsletter[cta_subheading]" rows="3" class="large-text"><?php echo esc_textarea( $s['cta_subheading'] ); ?></textarea></td></tr>
					<tr><th><label>CTA button</label></th><td><input type="text" name="kpopblog_newsletter[cta_button]" value="<?php echo esc_attr( $s['cta_button'] ); ?>" class="regular-text"></td></tr>
					<tr><th><label>Signup opt-in label</label></th><td><input type="text" name="kpopblog_newsletter[signup_opt_in_label]" value="<?php echo esc_attr( $s['signup_opt_in_label'] ); ?>" class="large-text"></td></tr>
					<tr><th><label>Success message</label></th><td><input type="text" name="kpopblog_newsletter[success_message]" value="<?php echo esc_attr( $s['success_message'] ); ?>" class="large-text"></td></tr>
					<tr><th><label>Confirm message</label></th><td><input type="text" name="kpopblog_newsletter[confirm_message]" value="<?php echo esc_attr( $s['confirm_message'] ); ?>" class="large-text"></td></tr>
					<tr><th><label>Default frequency</label></th><td>
						<select name="kpopblog_newsletter[default_frequency]">
							<?php foreach ( array( 'daily', 'weekly', 'monthly' ) as $f ) : ?>
								<option value="<?php echo esc_attr( $f ); ?>" <?php selected( $s['default_frequency'], $f ); ?>><?php echo esc_html( ucfirst( $f ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td></tr>
					<tr><th><label>Available topics</label></th><td>
						<input type="text" name="kpopblog_newsletter[available_topics]" value="<?php echo esc_attr( implode( ',', (array) $s['available_topics'] ) ); ?>" class="large-text">
						<p class="description">Comma-separated slugs (e.g. comebacks,charts,tours).</p>
					</td></tr>
					<tr><th><label>Double opt-in</label></th><td>
						<label><input type="checkbox" name="kpopblog_newsletter[double_opt_in]" value="1" <?php checked( $s['double_opt_in'] ); ?>> Require email confirmation</label>
					</td></tr>
					<tr><th colspan="2"><h2 style="margin:1em 0 0">External provider (optional)</h2></th></tr>
					<tr><th><label>Provider</label></th><td>
						<select name="kpopblog_newsletter[esp_provider]">
							<?php foreach ( array( '' => 'None (store in WP only)', 'mailchimp' => 'Mailchimp', 'brevo' => 'Brevo (Sendinblue)', 'convertkit' => 'ConvertKit', 'mailerlite' => 'MailerLite', 'webhook' => 'Generic webhook' ) as $k => $label ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['esp_provider'], $k ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td></tr>
					<tr><th><label>List / Audience ID</label></th><td><input type="text" name="kpopblog_newsletter[esp_list_id]" value="<?php echo esc_attr( $s['esp_list_id'] ); ?>" class="regular-text"></td></tr>
					<tr><th><label>API key</label></th><td><input type="password" name="kpopblog_newsletter[esp_api_key]" value="<?php echo esc_attr( $s['esp_api_key'] ); ?>" class="regular-text" autocomplete="off"></td></tr>
					<tr><th><label>Webhook URL</label></th><td><input type="url" name="kpopblog_newsletter[esp_webhook_url]" value="<?php echo esc_attr( $s['esp_webhook_url'] ); ?>" class="large-text" placeholder="https://hooks.example.com/kpop-newsletter"></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	} );
} );

/* ---------- Helpers ---------- */
function kpopblog_newsletter_find_by_email( $email ) {
	$q = new WP_Query( array(
		'post_type'      => 'kb_subscriber',
		'posts_per_page' => 1,
		'post_status'    => 'any',
		'meta_query'     => array( array( 'key' => 'kb_sub_email', 'value' => $email ) ),
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	return $q->posts ? (int) $q->posts[0] : 0;
}

function kpopblog_newsletter_rate_limit( $key, $max = 5, $window = 600 ) {
	$bucket = 'kpopnl_' . hash_hmac( 'sha256', (string) $key, wp_salt( 'nonce' ) );
	$hits   = (int) get_transient( $bucket );
	if ( $hits >= $max ) return false;
	set_transient( $bucket, $hits + 1, $window );
	return true;
}

function kpopblog_newsletter_token_hash( $token ) {
	return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
}

function kpopblog_newsletter_find_by_token( $meta_key, $token ) {
	if ( ! in_array( $meta_key, array( 'kb_sub_confirm_hash', 'kb_sub_unsub_hash' ), true ) ) { return 0; }
	$token = trim( (string) $token );
	if ( strlen( $token ) < 32 || strlen( $token ) > 128 ) { return 0; }
	$query = new WP_Query( array(
		'post_type'      => 'kb_subscriber',
		'posts_per_page' => 1,
		'post_status'    => 'any',
		'meta_query'     => array( array( 'key' => $meta_key, 'value' => kpopblog_newsletter_token_hash( $token ) ) ),
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	return $query->posts ? (int) $query->posts[0] : 0;
}

function kpopblog_newsletter_issue_tokens( $subscriber_id ) {
	$confirm_token = wp_generate_password( 48, false, false );
	$unsubscribe_token = wp_generate_password( 48, false, false );
	update_post_meta( $subscriber_id, 'kb_sub_confirm_hash', kpopblog_newsletter_token_hash( $confirm_token ) );
	update_post_meta( $subscriber_id, 'kb_sub_unsub_hash', kpopblog_newsletter_token_hash( $unsubscribe_token ) );
	update_post_meta( $subscriber_id, 'kb_sub_token_expires', gmdate( 'Y-m-d H:i:s', time() + ( 48 * HOUR_IN_SECONDS ) ) );
	delete_post_meta( $subscriber_id, 'kb_sub_token' );
	return array( 'confirm' => $confirm_token, 'unsubscribe' => $unsubscribe_token );
}

function kpopblog_migrate_legacy_newsletter_tokens() {
	$subscriber_ids = get_posts( array(
		'post_type'      => 'kb_subscriber',
		'post_status'    => 'any',
		'numberposts'    => -1,
		'fields'         => 'ids',
		'meta_key'       => 'kb_sub_token',
		'meta_compare'   => 'EXISTS',
		'suppress_filters' => true,
	) );
	foreach ( $subscriber_ids as $subscriber_id ) {
		$legacy_token = (string) get_post_meta( $subscriber_id, 'kb_sub_token', true );
		if ( $legacy_token ) {
			$hash = kpopblog_newsletter_token_hash( $legacy_token );
			update_post_meta( $subscriber_id, 'kb_sub_confirm_hash', $hash );
			update_post_meta( $subscriber_id, 'kb_sub_unsub_hash', $hash );
			update_post_meta( $subscriber_id, 'kb_sub_token_expires', gmdate( 'Y-m-d H:i:s', time() + ( 48 * HOUR_IN_SECONDS ) ) );
		}
		delete_post_meta( $subscriber_id, 'kb_sub_token' );
	}
}

add_action( 'template_redirect', function () {
	if ( empty( $_GET['kpop_nl_confirm'] ) ) { return; }
	$token = sanitize_text_field( wp_unslash( $_GET['kpop_nl_confirm'] ) );
	if ( strlen( $token ) < 32 || strlen( $token ) > 128 ) { return; }
	wp_safe_redirect( add_query_arg( array( 'token' => $token, 'action' => 'confirm' ), home_url( '/newsletter' ) ) );
	exit;
} );

function kpopblog_newsletter_send_confirmation( $subscriber_id, array $tokens ) {
	$email = (string) get_post_meta( $subscriber_id, 'kb_sub_email', true );
	if ( ! is_email( $email ) || empty( $tokens['confirm'] ) || empty( $tokens['unsubscribe'] ) ) { return false; }
	$confirm_url = add_query_arg( array( 'token' => $tokens['confirm'], 'action' => 'confirm' ), home_url( '/newsletter' ) );
	$unsubscribe_url = add_query_arg( array( 'token' => $tokens['unsubscribe'], 'action' => 'unsubscribe' ), home_url( '/newsletter' ) );
	return wp_mail(
		$email,
		sprintf( '[%s] Confirm your newsletter subscription', get_bloginfo( 'name' ) ),
		"Confirm your subscription:\n\n{$confirm_url}\n\nCancel this request or unsubscribe:\n\n{$unsubscribe_url}\n\nIf you did not request this, no further action is required."
	);
}

function kpopblog_newsletter_send_welcome( $subscriber_id, $unsubscribe_token ) {
	$email = (string) get_post_meta( $subscriber_id, 'kb_sub_email', true );
	if ( ! is_email( $email ) || ! $unsubscribe_token ) { return false; }
	$unsubscribe_url = add_query_arg( array( 'token' => $unsubscribe_token, 'action' => 'unsubscribe' ), home_url( '/newsletter' ) );
	return wp_mail(
		$email,
		sprintf( '[%s] Newsletter subscription confirmed', get_bloginfo( 'name' ) ),
		"Your newsletter subscription is active.\n\nUnsubscribe at any time:\n\n{$unsubscribe_url}"
	);
}

function kpopblog_newsletter_forward_to_esp( $email, $topics, $frequency, $locale ) {
	$s = kpopblog_newsletter_settings();
	if ( empty( $s['esp_provider'] ) ) return;
	$payload = array(
		'email'     => $email,
		'topics'    => $topics,
		'frequency' => $frequency,
		'locale'    => $locale,
		'source'    => 'kpopblog',
	);
	$url     = '';
	$headers = array( 'Content-Type' => 'application/json' );
	switch ( $s['esp_provider'] ) {
		case 'mailchimp':
			if ( ! $s['esp_api_key'] || ! $s['esp_list_id'] || ! strpos( $s['esp_api_key'], '-' ) ) return;
			$dc      = substr( strrchr( $s['esp_api_key'], '-' ), 1 );
			$url     = "https://{$dc}.api.mailchimp.com/3.0/lists/{$s['esp_list_id']}/members";
			$headers['Authorization'] = 'Basic ' . base64_encode( 'anystring:' . $s['esp_api_key'] );
			$payload = array( 'email_address' => $email, 'status' => 'pending', 'tags' => $topics, 'merge_fields' => array( 'FREQUENCY' => $frequency ) );
			break;
		case 'brevo':
			$url     = 'https://api.brevo.com/v3/contacts';
			$headers['api-key'] = $s['esp_api_key'];
			$payload = array( 'email' => $email, 'listIds' => array( (int) $s['esp_list_id'] ), 'attributes' => array( 'FREQUENCY' => $frequency, 'TOPICS' => implode( ',', $topics ) ), 'updateEnabled' => true );
			break;
		case 'convertkit':
			$url     = "https://api.convertkit.com/v3/forms/{$s['esp_list_id']}/subscribe";
			$payload = array( 'api_key' => $s['esp_api_key'], 'email' => $email, 'tags' => $topics );
			break;
		case 'mailerlite':
			$url     = 'https://connect.mailerlite.com/api/subscribers';
			$headers['Authorization'] = 'Bearer ' . $s['esp_api_key'];
			$payload = array( 'email' => $email, 'groups' => array( $s['esp_list_id'] ), 'fields' => array( 'frequency' => $frequency ) );
			break;
		case 'webhook':
		default:
			$url = $s['esp_webhook_url'];
			break;
	}
	if ( ! $url ) return;
	wp_remote_post( $url, array(
		'headers'  => $headers,
		'body'     => wp_json_encode( $payload ),
		'timeout'  => 6,
		'blocking' => false,
	) );
}

/* ---------- REST: subscribe / confirm / unsubscribe / settings ---------- */
add_action( 'rest_api_init', function () {
	register_rest_route( KPOPBLOG_REST_NS, '/newsletter/settings', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function () {
			$s = kpopblog_newsletter_settings();
			return rest_ensure_response( array(
				'cta'              => array(
					'eyebrow'    => $s['cta_eyebrow'],
					'heading'    => $s['cta_heading'],
					'subheading' => $s['cta_subheading'],
					'button'     => $s['cta_button'],
				),
				'signupOptInLabel' => $s['signup_opt_in_label'],
				'successMessage'   => $s['success_message'],
				'confirmMessage'   => $s['confirm_message'],
				'defaultFrequency' => $s['default_frequency'],
				'availableTopics'  => array_values( (array) $s['available_topics'] ),
				'doubleOptIn'      => (bool) $s['double_opt_in'],
			) );
		},
	) );

	register_rest_route( KPOPBLOG_REST_NS, '/newsletter/subscribe', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'args'                => array(
			'email'     => array( 'type' => 'string', 'required' => true ),
			'topics'    => array( 'type' => 'array' ),
			'frequency' => array( 'type' => 'string' ),
			'source'    => array( 'type' => 'string' ),
			'locale'    => array( 'type' => 'string' ),
		),
		'callback'            => function ( WP_REST_Request $r ) {
			$ip = $_SERVER['REMOTE_ADDR'] ?? 'anon';
			if ( ! kpopblog_newsletter_rate_limit( 'sub-ip:' . $ip ) ) {
				return new WP_Error( 'rate_limited', 'Too many attempts. Try again later.', array( 'status' => 429 ) );
			}
			$email = sanitize_email( (string) $r->get_param( 'email' ) );
			if ( ! $email || ! is_email( $email ) ) {
				return new WP_Error( 'bad_email', 'Please enter a valid email address.', array( 'status' => 400 ) );
			}
			if ( ! kpopblog_newsletter_rate_limit( 'sub-email:' . strtolower( $email ), 3, HOUR_IN_SECONDS ) ) {
				return new WP_Error( 'rate_limited', 'Too many attempts. Try again later.', array( 'status' => 429 ) );
			}
			$s         = kpopblog_newsletter_settings();
			$frequency = in_array( $r->get_param( 'frequency' ), array( 'daily', 'weekly', 'monthly' ), true ) ? $r->get_param( 'frequency' ) : $s['default_frequency'];
			$allowed   = (array) $s['available_topics'];
			$topics    = array_values( array_intersect( $allowed, array_map( 'sanitize_key', (array) $r->get_param( 'topics' ) ) ) );
			if ( ! $topics ) $topics = $allowed;
			$source = sanitize_text_field( (string) $r->get_param( 'source' ) ) ?: 'site';
			$locale = sanitize_text_field( (string) $r->get_param( 'locale' ) ) ?: 'en';

			$existing = kpopblog_newsletter_find_by_email( $email );
			if ( $existing && get_post_meta( $existing, 'kb_sub_confirmed', true ) && ! get_post_meta( $existing, 'kb_sub_unsub_at', true ) ) {
				return rest_ensure_response( array(
					'ok'        => true,
					'pending'   => false,
					'message'   => $s['confirm_message'],
					'frequency' => (string) get_post_meta( $existing, 'kb_sub_frequency', true ),
					'topics'    => array_values( (array) get_post_meta( $existing, 'kb_sub_topics', true ) ),
				) );
			}
			$post_id  = $existing ? $existing : wp_insert_post( array(
				'post_type'   => 'kb_subscriber',
				'post_title'  => $email,
				'post_status' => 'publish',
			) );
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				return new WP_Error( 'insert_failed', 'Could not save subscription.', array( 'status' => 500 ) );
			}
			update_post_meta( $post_id, 'kb_sub_email', $email );
			update_post_meta( $post_id, 'kb_sub_frequency', $frequency );
			update_post_meta( $post_id, 'kb_sub_topics', $topics );
			update_post_meta( $post_id, 'kb_sub_source', $source );
			update_post_meta( $post_id, 'kb_sub_locale', $locale );
			$tokens = kpopblog_newsletter_issue_tokens( $post_id );
			update_post_meta( $post_id, 'kb_sub_consent_at', current_time( 'mysql', true ) );
			update_post_meta( $post_id, 'kb_sub_suppressed', false );
			delete_post_meta( $post_id, 'kb_sub_unsub_at' );
			if ( ! $s['double_opt_in'] ) {
				update_post_meta( $post_id, 'kb_sub_confirmed', true );
				update_post_meta( $post_id, 'kb_sub_confirmed_at', current_time( 'mysql', true ) );
				kpopblog_newsletter_forward_to_esp( $email, $topics, $frequency, $locale );
				kpopblog_newsletter_send_welcome( $post_id, $tokens['unsubscribe'] );
			} else {
				update_post_meta( $post_id, 'kb_sub_confirmed', false );
				delete_post_meta( $post_id, 'kb_sub_confirmed_at' );
				kpopblog_newsletter_send_confirmation( $post_id, $tokens );
			}
			do_action( 'kpopblog_newsletter_subscribed', $email, $topics, $frequency, $source );
			return rest_ensure_response( array(
				'ok'        => true,
				'pending'   => (bool) $s['double_opt_in'],
				'message'   => $s['double_opt_in'] ? $s['success_message'] : $s['confirm_message'],
				'frequency' => $frequency,
				'topics'    => $topics,
			) );
		},
	) );

	register_rest_route( KPOPBLOG_REST_NS, '/newsletter/confirm', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'args'                => array( 'token' => array( 'type' => 'string', 'required' => true ) ),
		'callback'            => function ( WP_REST_Request $r ) {
			$token = trim( (string) $r->get_param( 'token' ) );
			$id = kpopblog_newsletter_find_by_token( 'kb_sub_confirm_hash', $token );
			if ( ! $id ) return new WP_Error( 'invalid_token', 'Invalid or expired link.', array( 'status' => 404 ) );
			$already_confirmed = (bool) get_post_meta( $id, 'kb_sub_confirmed', true );
			$expires = (string) get_post_meta( $id, 'kb_sub_token_expires', true );
			if ( ! $already_confirmed && ( ! $expires || strtotime( $expires . ' UTC' ) < time() ) ) {
				return new WP_Error( 'expired_token', 'Invalid or expired link.', array( 'status' => 404 ) );
			}
			update_post_meta( $id, 'kb_sub_confirmed', true );
			update_post_meta( $id, 'kb_sub_suppressed', false );
			delete_post_meta( $id, 'kb_sub_unsub_at' );
			if ( ! $already_confirmed ) {
				update_post_meta( $id, 'kb_sub_confirmed_at', current_time( 'mysql', true ) );
			}
			$email     = (string) get_post_meta( $id, 'kb_sub_email', true );
			$topics    = (array) get_post_meta( $id, 'kb_sub_topics', true );
			$frequency = (string) get_post_meta( $id, 'kb_sub_frequency', true );
			$locale    = (string) get_post_meta( $id, 'kb_sub_locale', true );
			if ( ! $already_confirmed ) {
				kpopblog_newsletter_forward_to_esp( $email, $topics, $frequency, $locale );
				do_action( 'kpopblog_newsletter_confirmed', $email );
			}
			$s = kpopblog_newsletter_settings();
			return rest_ensure_response( array( 'ok' => true, 'message' => $s['confirm_message'] ) );
		},
	) );

	register_rest_route( KPOPBLOG_REST_NS, '/newsletter/unsubscribe', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'args'                => array( 'token' => array( 'type' => 'string', 'required' => true ) ),
		'callback'            => function ( WP_REST_Request $r ) {
			$token = trim( (string) $r->get_param( 'token' ) );
			if ( '' === $token ) {
				return new WP_Error( 'token_required', 'A secure unsubscribe link is required.', array( 'status' => 400 ) );
			}
			$id = kpopblog_newsletter_find_by_token( 'kb_sub_unsub_hash', $token );
			if ( ! $id ) {
				return rest_ensure_response( array( 'ok' => true ) );
			}
			update_post_meta( $id, 'kb_sub_confirmed', false );
			update_post_meta( $id, 'kb_sub_suppressed', true );
			if ( ! get_post_meta( $id, 'kb_sub_unsub_at', true ) ) {
				update_post_meta( $id, 'kb_sub_unsub_at', current_time( 'mysql', true ) );
				do_action( 'kpopblog_newsletter_unsubscribed', (string) get_post_meta( $id, 'kb_sub_email', true ) );
			}
			return rest_ensure_response( array( 'ok' => true ) );
		},
	) );
} );
