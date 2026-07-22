<?php
/**
 * Grounded OpenAI content discovery, validation, persistence, and scheduling.
 *
 * @package KpopBlog
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const KPOPBLOG_AUTOMATION_OPTION = 'kpopblog_automation';
const KPOPBLOG_AUTOMATION_HOOK   = 'kpopblog_run_scheduled_automation';

function kpopblog_automation_defaults() {
	return array(
		'enabled'      => 0,
		'auto_publish' => 1,
		'model'        => 'gpt-5.6-luna',
		'frequency'    => 'twicedaily',
		'max_items'    => 6,
		'artist_focus' => '',
	);
}

function kpopblog_automation_models() {
	return array( 'gpt-5.6-luna', 'gpt-5.6-terra', 'gpt-5.6-sol' );
}

function kpopblog_sanitize_automation_settings( $input ) {
	$defaults = kpopblog_automation_defaults();
	$input    = is_array( $input ) ? $input : array();
	$model    = isset( $input['model'] ) ? sanitize_text_field( (string) $input['model'] ) : $defaults['model'];
	$frequency = isset( $input['frequency'] ) ? sanitize_key( (string) $input['frequency'] ) : $defaults['frequency'];
	$focus    = isset( $input['artist_focus'] ) ? sanitize_text_field( (string) $input['artist_focus'] ) : '';

	return array(
		'enabled'      => ! empty( $input['enabled'] ) ? 1 : 0,
		'auto_publish' => ! empty( $input['auto_publish'] ) ? 1 : 0,
		'model'        => in_array( $model, kpopblog_automation_models(), true ) ? $model : $defaults['model'],
		'frequency'    => in_array( $frequency, array( 'hourly', 'twicedaily', 'daily' ), true ) ? $frequency : $defaults['frequency'],
		'max_items'    => max( 1, min( 10, isset( $input['max_items'] ) ? (int) $input['max_items'] : $defaults['max_items'] ) ),
		'artist_focus' => substr( $focus, 0, 500 ),
	);
}

function kpopblog_get_automation_settings() {
	$saved = get_option( KPOPBLOG_AUTOMATION_OPTION, array() );
	return kpopblog_sanitize_automation_settings( array_merge( kpopblog_automation_defaults(), is_array( $saved ) ? $saved : array() ) );
}

/**
 * API credentials are accepted only from server configuration.
 */
function kpopblog_get_openai_api_key() {
	$key = '';
	if ( defined( 'KPOPBLOG_OPENAI_API_KEY' ) ) {
		$key = (string) KPOPBLOG_OPENAI_API_KEY;
	} else {
		$environment_key = getenv( 'OPENAI_API_KEY' );
		$key = false === $environment_key ? '' : (string) $environment_key;
	}
	return trim( $key );
}

function kpopblog_has_openai_api_key() {
	return strlen( kpopblog_get_openai_api_key() ) >= 20;
}

function kpopblog_register_automation_meta() {
	$string = array(
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => '__return_true',
	);
	$url = $string;
	$url['sanitize_callback'] = 'esc_url_raw';
	$array = array(
		'type'          => 'array',
		'single'        => true,
		'show_in_rest'  => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ) ),
		'auth_callback' => '__return_true',
	);

	foreach ( array( 'post', 'kb_comeback' ) as $post_type ) {
		register_post_meta( $post_type, 'kb_ai_generated', $string );
		register_post_meta( $post_type, 'kb_ai_model', $string );
		register_post_meta( $post_type, 'kb_ai_response_id', $string );
		register_post_meta( $post_type, 'kb_ai_confidence', $string );
		register_post_meta( $post_type, 'kb_verified_at', $string );
		register_post_meta( $post_type, 'kb_automation_key', $string );
		register_post_meta( $post_type, 'kb_source_url', $url );
		register_post_meta( $post_type, 'kb_source_title', $string );
		register_post_meta( $post_type, 'kb_source_urls', $array );
	}
}
add_action( 'init', 'kpopblog_register_automation_meta' );

function kpopblog_automation_json_schema( $max_items ) {
	$source_schema = array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'properties'           => array(
			'url'          => array( 'type' => 'string' ),
			'title'        => array( 'type' => 'string' ),
			'publisher'    => array( 'type' => 'string' ),
			'published_at' => array( 'type' => 'string' ),
		),
		'required'             => array( 'url', 'title', 'publisher', 'published_at' ),
	);

	return array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'properties'           => array(
			'items' => array(
				'type'     => 'array',
				'maxItems' => max( 1, min( 10, (int) $max_items ) ),
				'items'    => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'kind'         => array( 'type' => 'string', 'enum' => array( 'news', 'comeback', 'concert' ) ),
						'title'        => array( 'type' => 'string' ),
						'excerpt'      => array( 'type' => 'string' ),
						'content'      => array( 'type' => 'string' ),
						'artist_slugs' => array( 'type' => 'array', 'maxItems' => 8, 'items' => array( 'type' => 'string' ) ),
						'event_date'   => array( 'type' => 'string' ),
						'event_type'   => array( 'type' => 'string' ),
						'confidence'   => array( 'type' => 'number', 'minimum' => 0, 'maximum' => 1 ),
						'sources'      => array( 'type' => 'array', 'minItems' => 1, 'maxItems' => 5, 'items' => $source_schema ),
					),
					'required'             => array( 'kind', 'title', 'excerpt', 'content', 'artist_slugs', 'event_date', 'event_type', 'confidence', 'sources' ),
				),
			),
		),
		'required'             => array( 'items' ),
	);
}

function kpopblog_automation_prompt( array $settings ) {
	$focus = $settings['artist_focus'] !== ''
		? 'Prioritize these artists when there is verified news: ' . $settings['artist_focus'] . '.'
		: 'Cover notable active K-pop artists and groups without favoring rumors.';

	return implode( "\n", array(
		'Today is ' . gmdate( 'Y-m-d' ) . ' UTC. Search the live web for verified K-pop developments from the last 72 hours.',
		'Find material news plus newly announced or materially changed comeback and concert schedules.',
		$focus,
		'Return no more than ' . (int) $settings['max_items'] . ' unique items. Return an empty items array when nothing meets the rules.',
		'Every factual item must have at least one direct HTTPS source URL. Prefer official artist, agency, promoter, venue, chart, and established newsroom sources.',
		'Exclude rumors, social reposts without an original source, ticket resale pages, scraped aggregators, and unconfirmed fan claims.',
		'Use ISO 8601 for event_date when kind is comeback or concert; otherwise use an empty string.',
		'Use event_type album, single, mv, teaser, concert, debut, birthday, or event; use an empty string for news.',
		'Write an original English summary. Do not reproduce copyrighted passages and do not invent or reproduce quotations.',
		'Confidence must reflect the evidence. Include only items with confidence at least 0.90.',
	) );
}

function kpopblog_openai_request( array $settings ) {
	$api_key = kpopblog_get_openai_api_key();
	if ( strlen( $api_key ) < 20 ) {
		return new WP_Error( 'openai_key_missing', 'OpenAI is not configured on the server.' );
	}

	$payload = array(
		'model'             => $settings['model'],
		'store'             => false,
		'reasoning'         => array( 'effort' => 'low' ),
		'tools'             => array(
			array(
				'type'                => 'web_search',
				'search_context_size' => 'medium',
				'filters'             => array(
					'blocked_domains' => array( 'reddit.com', 'quora.com', 'wikipedia.org', 'allkpop.com' ),
				),
			),
		),
		'tool_choice'       => 'auto',
		'include'           => array( 'web_search_call.action.sources' ),
		'input'             => kpopblog_automation_prompt( $settings ),
		'max_output_tokens' => 12000,
		'text'              => array(
			'format' => array(
				'type'   => 'json_schema',
				'name'   => 'kpopblog_grounded_content',
				'strict' => true,
				'schema' => kpopblog_automation_json_schema( $settings['max_items'] ),
			),
		),
	);

	$response = wp_remote_post( 'https://api.openai.com/v1/responses', array(
		'timeout'     => 150,
		'redirection' => 0,
		'headers'     => array(
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
		),
		'body'        => wp_json_encode( $payload ),
		'data_format' => 'body',
	) );
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'openai_transport_error', 'OpenAI could not be reached securely.' );
	}

	$status = (int) wp_remote_retrieve_response_code( $response );
	if ( 401 === $status || 403 === $status ) {
		return new WP_Error( 'openai_auth_failed', 'OpenAI rejected the server credential.' );
	}
	if ( 429 === $status ) {
		return new WP_Error( 'openai_rate_limited', 'OpenAI rate or quota limits prevented this run.' );
	}
	if ( $status < 200 || $status >= 300 ) {
		return new WP_Error( 'openai_request_failed', 'OpenAI returned a temporary service error.' );
	}

	$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $decoded ) ) {
		return new WP_Error( 'openai_invalid_response', 'OpenAI returned an unreadable response.' );
	}
	return $decoded;
}

function kpopblog_extract_openai_output_text( array $response ) {
	if ( isset( $response['status'] ) && 'completed' !== $response['status'] ) {
		return new WP_Error( 'openai_incomplete', 'OpenAI did not complete the content run.' );
	}
	foreach ( isset( $response['output'] ) && is_array( $response['output'] ) ? $response['output'] : array() as $output ) {
		if ( ! is_array( $output ) || 'message' !== ( isset( $output['type'] ) ? $output['type'] : '' ) ) { continue; }
		foreach ( isset( $output['content'] ) && is_array( $output['content'] ) ? $output['content'] : array() as $content ) {
			if ( is_array( $content ) && 'refusal' === ( isset( $content['type'] ) ? $content['type'] : '' ) ) {
				return new WP_Error( 'openai_refusal', 'OpenAI declined this content run.' );
			}
			if ( is_array( $content ) && 'output_text' === ( isset( $content['type'] ) ? $content['type'] : '' ) && isset( $content['text'] ) ) {
				return (string) $content['text'];
			}
		}
	}
	return new WP_Error( 'openai_empty_response', 'OpenAI returned no structured content.' );
}

function kpopblog_validate_automation_url( $url ) {
	$url = esc_url_raw( trim( (string) $url ), array( 'https' ) );
	if ( '' === $url || 0 !== stripos( $url, 'https://' ) || ! wp_http_validate_url( $url ) ) { return ''; }
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	if ( '' === $host || 'localhost' === $host || preg_match( '/(^|\.)local$/', $host ) ) { return ''; }
	return $url;
}

function kpopblog_validate_automation_item( $raw ) {
	if ( ! is_array( $raw ) ) { return new WP_Error( 'invalid_item', 'The content item is not an object.' ); }
	$kind = isset( $raw['kind'] ) ? sanitize_key( (string) $raw['kind'] ) : '';
	if ( ! in_array( $kind, array( 'news', 'comeback', 'concert' ), true ) ) {
		return new WP_Error( 'invalid_kind', 'The content kind is unsupported.' );
	}
	$title   = sanitize_text_field( kpopblog_decode_text_entities( isset( $raw['title'] ) ? (string) $raw['title'] : '' ) );
	$excerpt = sanitize_textarea_field( kpopblog_decode_text_entities( isset( $raw['excerpt'] ) ? (string) $raw['excerpt'] : '' ) );
	$content = sanitize_textarea_field( kpopblog_decode_text_entities( isset( $raw['content'] ) ? (string) $raw['content'] : '' ) );
	if ( strlen( $title ) < 8 || strlen( $title ) > 160 || strlen( $excerpt ) < 40 || strlen( $content ) < 120 ) {
		return new WP_Error( 'invalid_copy', 'The generated copy did not meet editorial length rules.' );
	}
	$confidence = isset( $raw['confidence'] ) ? (float) $raw['confidence'] : 0;
	if ( $confidence < 0.9 || $confidence > 1 ) {
		return new WP_Error( 'low_confidence', 'The item did not meet the confidence threshold.' );
	}

	$sources = array();
	$seen_urls = array();
	foreach ( isset( $raw['sources'] ) && is_array( $raw['sources'] ) ? $raw['sources'] : array() as $source ) {
		if ( ! is_array( $source ) ) { continue; }
		$url = kpopblog_validate_automation_url( isset( $source['url'] ) ? $source['url'] : '' );
		if ( '' === $url || isset( $seen_urls[ $url ] ) ) { continue; }
		$seen_urls[ $url ] = true;
		$sources[] = array(
			'url'          => $url,
			'title'        => substr( sanitize_text_field( kpopblog_decode_text_entities( isset( $source['title'] ) ? (string) $source['title'] : '' ) ), 0, 200 ),
			'publisher'    => substr( sanitize_text_field( kpopblog_decode_text_entities( isset( $source['publisher'] ) ? (string) $source['publisher'] : '' ) ), 0, 120 ),
			'published_at' => substr( sanitize_text_field( isset( $source['published_at'] ) ? (string) $source['published_at'] : '' ), 0, 40 ),
		);
		if ( count( $sources ) >= 5 ) { break; }
	}
	if ( empty( $sources ) ) { return new WP_Error( 'source_missing', 'The item has no valid HTTPS source.' ); }

	$artist_slugs = array();
	foreach ( isset( $raw['artist_slugs'] ) && is_array( $raw['artist_slugs'] ) ? $raw['artist_slugs'] : array() as $slug ) {
		$slug = sanitize_title( (string) $slug );
		if ( '' !== $slug && ! in_array( $slug, $artist_slugs, true ) ) { $artist_slugs[] = $slug; }
		if ( count( $artist_slugs ) >= 8 ) { break; }
	}

	$event_date = isset( $raw['event_date'] ) ? sanitize_text_field( (string) $raw['event_date'] ) : '';
	$event_type = isset( $raw['event_type'] ) ? sanitize_key( (string) $raw['event_type'] ) : '';
	if ( 'news' !== $kind ) {
		if ( false === strtotime( $event_date ) || ! in_array( $event_type, array( 'album', 'single', 'mv', 'teaser', 'concert', 'debut', 'birthday', 'event' ), true ) ) {
			return new WP_Error( 'invalid_event', 'The schedule item has an invalid date or type.' );
		}
	} else {
		$event_date = '';
		$event_type = '';
	}

	return array(
		'kind'         => $kind,
		'title'        => $title,
		'excerpt'      => $excerpt,
		'content'      => $content,
		'artist_slugs' => $artist_slugs,
		'event_date'   => $event_date,
		'event_type'   => $event_type,
		'confidence'   => $confidence,
		'sources'      => $sources,
	);
}

function kpopblog_automation_content_html( array $item ) {
	$html = '';
	foreach ( preg_split( '/\r\n|\r|\n/', $item['content'] ) as $paragraph ) {
		$paragraph = trim( $paragraph );
		if ( '' !== $paragraph ) { $html .= '<p>' . esc_html( $paragraph ) . '</p>'; }
	}
	$html .= '<h2>Sources</h2><ul class="kb-ai-sources">';
	foreach ( $item['sources'] as $source ) {
		$label = $source['title'] !== '' ? $source['title'] : $source['publisher'];
		if ( $label === '' ) { $label = (string) wp_parse_url( $source['url'], PHP_URL_HOST ); }
		$html .= '<li><a href="' . esc_url( $source['url'] ) . '" rel="noopener noreferrer nofollow" target="_blank">' . esc_html( $label ) . '</a>';
		if ( $source['publisher'] !== '' && $source['publisher'] !== $label ) { $html .= ' — ' . esc_html( $source['publisher'] ); }
		$html .= '</li>';
	}
	return $html . '</ul>';
}

function kpopblog_automation_author_id() {
	if ( get_current_user_id() > 0 && current_user_can( 'publish_posts' ) ) { return get_current_user_id(); }
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids' ) );
	return $admins ? (int) $admins[0] : 0;
}

function kpopblog_persist_automation_item( array $item, array $settings, $response_id ) {
	global $wpdb;
	$table       = $wpdb->prefix . 'kb_automation_items';
	$primary_url = $item['sources'][0]['url'];
	$dedupe_key  = hash( 'sha256', $item['kind'] . '|' . strtolower( untrailingslashit( $primary_url ) ) );
	$content_hash = hash( 'sha256', wp_json_encode( $item ) );
	$existing    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE dedupe_key = %s", $dedupe_key ) );
	$now         = current_time( 'mysql', true );
	if ( $existing && hash_equals( (string) $existing->content_hash, $content_hash ) ) {
		$wpdb->update( $table, array( 'last_seen_at' => $now, 'response_id' => substr( (string) $response_id, 0, 128 ) ), array( 'id' => (int) $existing->id ), array( '%s', '%s' ), array( '%d' ) );
		return array( 'state' => 'skipped', 'post_id' => (int) $existing->wp_post_id );
	}

	$post_type = 'news' === $item['kind'] ? 'post' : 'kb_comeback';
	$post_data = array(
		'post_type'    => $post_type,
		'post_status'  => ! empty( $settings['auto_publish'] ) ? 'publish' : 'draft',
		'post_title'   => $item['title'],
		'post_excerpt' => $item['excerpt'],
		'post_content' => kpopblog_automation_content_html( $item ),
		'post_author'  => kpopblog_automation_author_id(),
	);
	if ( 'kb_comeback' === $post_type ) {
		$post_data['meta_input'] = array(
			'kb_artist_slug' => $item['artist_slugs'] ? $item['artist_slugs'][0] : '',
			'kb_type'        => $item['event_type'],
			'kb_release_at'  => $item['event_date'],
			'kb_source_url'  => $primary_url,
		);
	}
	if ( $existing && (int) $existing->wp_post_id > 0 && get_post( (int) $existing->wp_post_id ) ) {
		$post_data['ID'] = (int) $existing->wp_post_id;
		$post_id = wp_update_post( wp_slash( $post_data ), true );
		$state = 'updated';
	} else {
		$post_id = wp_insert_post( wp_slash( $post_data ), true );
		$state = 'created';
	}
	if ( is_wp_error( $post_id ) ) { return $post_id; }

	$source_urls = wp_list_pluck( $item['sources'], 'url' );
	$meta = array(
		'kb_ai_generated'    => '1',
		'kb_ai_model'        => $settings['model'],
		'kb_ai_response_id'  => substr( (string) $response_id, 0, 128 ),
		'kb_ai_confidence'   => number_format( $item['confidence'], 2, '.', '' ),
		'kb_verified_at'     => gmdate( 'c' ),
		'kb_automation_key'  => $dedupe_key,
		'kb_source_url'      => $primary_url,
		'kb_source_title'    => $item['sources'][0]['title'],
		'kb_source_urls'     => $source_urls,
	);
	if ( 'post' === $post_type ) {
		$meta['kb_category_slug'] = 'news';
		$meta['kb_language'] = 'en';
		$meta['kb_source'] = 'ai-grounded';
		$meta['kb_related_artist_slugs'] = $item['artist_slugs'];
	} else {
		$meta['kb_artist_slug'] = $item['artist_slugs'] ? $item['artist_slugs'][0] : '';
		$meta['kb_type'] = $item['event_type'];
		$meta['kb_release_at'] = $item['event_date'];
	}
	foreach ( $meta as $key => $value ) { update_post_meta( $post_id, $key, $value ); }
	if ( 'post' === $post_type && $item['artist_slugs'] ) { wp_set_post_tags( $post_id, $item['artist_slugs'], false ); }

	$row = array(
		'dedupe_key'        => $dedupe_key,
		'content_hash'      => $content_hash,
		'kind'              => $item['kind'],
		'wp_post_id'        => (int) $post_id,
		'primary_source_url'=> $primary_url,
		'sources_json'      => wp_json_encode( $item['sources'] ),
		'response_id'       => substr( (string) $response_id, 0, 128 ),
		'first_seen_at'     => $existing ? $existing->first_seen_at : $now,
		'last_seen_at'      => $now,
	);
	if ( $existing ) {
		$wpdb->update( $table, $row, array( 'id' => (int) $existing->id ) );
	} else {
		$inserted = $wpdb->insert( $table, $row );
		if ( false === $inserted ) {
			wp_delete_post( $post_id, true );
			return new WP_Error( 'automation_provenance_failed', 'Could not save automation provenance.' );
		}
	}
	return array( 'state' => $state, 'post_id' => (int) $post_id );
}

function kpopblog_run_automation( $trigger_type = 'manual' ) {
	global $wpdb;
	$trigger_type = in_array( $trigger_type, array( 'manual', 'scheduled', 'smoke' ), true ) ? $trigger_type : 'manual';
	$settings = kpopblog_get_automation_settings();
	if ( 'scheduled' === $trigger_type && empty( $settings['enabled'] ) ) {
		return array( 'discovered' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0 );
	}
	if ( ! kpopblog_has_openai_api_key() ) {
		return new WP_Error( 'openai_key_missing', 'OpenAI is not configured on the server.' );
	}
	if ( ! add_option( 'kpopblog_automation_lock', time(), '', false ) ) {
		$locked_at = (int) get_option( 'kpopblog_automation_lock', 0 );
		if ( $locked_at > time() - 15 * MINUTE_IN_SECONDS ) {
			return new WP_Error( 'automation_locked', 'Another automation run is still active.' );
		}
		delete_option( 'kpopblog_automation_lock' );
		if ( ! add_option( 'kpopblog_automation_lock', time(), '', false ) ) {
			return new WP_Error( 'automation_locked', 'Another automation run is still active.' );
		}
	}

	$runs_table = $wpdb->prefix . 'kb_automation_runs';
	$started_at = current_time( 'mysql', true );
	$wpdb->insert( $runs_table, array(
		'trigger_type' => $trigger_type,
		'status'       => 'running',
		'model'        => $settings['model'],
		'started_at'   => $started_at,
	) );
	$run_id = (int) $wpdb->insert_id;
	$counts = array( 'discovered' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0 );

	try {
		$response = kpopblog_openai_request( $settings );
		if ( is_wp_error( $response ) ) { throw new Exception( $response->get_error_code() . '|' . $response->get_error_message() ); }
		$response_id = isset( $response['id'] ) ? sanitize_text_field( (string) $response['id'] ) : '';
		$output_text = kpopblog_extract_openai_output_text( $response );
		if ( is_wp_error( $output_text ) ) { throw new Exception( $output_text->get_error_code() . '|' . $output_text->get_error_message() ); }
		$payload = json_decode( $output_text, true );
		if ( ! is_array( $payload ) || ! isset( $payload['items'] ) || ! is_array( $payload['items'] ) ) {
			throw new Exception( 'automation_invalid_json|OpenAI output did not match the content contract.' );
		}

		$counts['discovered'] = min( count( $payload['items'] ), (int) $settings['max_items'] );
		foreach ( array_slice( $payload['items'], 0, (int) $settings['max_items'] ) as $raw_item ) {
			$item = kpopblog_validate_automation_item( $raw_item );
			if ( is_wp_error( $item ) ) { $counts['skipped']++; continue; }
			$result = kpopblog_persist_automation_item( $item, $settings, $response_id );
			if ( is_wp_error( $result ) ) { $counts['skipped']++; continue; }
			$counts[ $result['state'] ]++;
		}

		$wpdb->update( $runs_table, array_merge( $counts, array(
			'status'      => 'completed',
			'response_id' => substr( $response_id, 0, 128 ),
			'finished_at' => current_time( 'mysql', true ),
		) ), array( 'id' => $run_id ) );
		update_option( 'kpopblog_automation_last_success', gmdate( 'c' ), false );
		kpopblog_audit( 'automation_completed', 'automation_run', $run_id, $counts );
		return $counts;
	} catch ( Exception $exception ) {
		$parts = explode( '|', $exception->getMessage(), 2 );
		$error_code = sanitize_key( $parts[0] );
		$error_text = isset( $parts[1] ) ? sanitize_text_field( $parts[1] ) : 'Automation failed.';
		$wpdb->update( $runs_table, array(
			'status'      => 'failed',
			'error_code'  => substr( $error_code, 0, 64 ),
			'error_text'  => substr( $error_text, 0, 500 ),
			'finished_at' => current_time( 'mysql', true ),
		), array( 'id' => $run_id ) );
		kpopblog_audit( 'automation_failed', 'automation_run', $run_id, array( 'errorCode' => $error_code ) );
		return new WP_Error( $error_code ?: 'automation_failed', $error_text );
	} finally {
		delete_option( 'kpopblog_automation_lock' );
	}
}

function kpopblog_run_scheduled_automation() {
	$result = kpopblog_run_automation( 'scheduled' );
	if ( is_wp_error( $result ) ) {
		update_option( 'kpopblog_automation_last_error', array( 'code' => $result->get_error_code(), 'at' => gmdate( 'c' ) ), false );
	} else {
		delete_option( 'kpopblog_automation_last_error' );
	}
}
add_action( KPOPBLOG_AUTOMATION_HOOK, 'kpopblog_run_scheduled_automation' );

function kpopblog_sync_automation_schedule() {
	$settings = kpopblog_get_automation_settings();
	$event    = wp_get_scheduled_event( KPOPBLOG_AUTOMATION_HOOK );
	if ( empty( $settings['enabled'] ) ) {
		if ( $event ) { wp_clear_scheduled_hook( KPOPBLOG_AUTOMATION_HOOK ); }
		return;
	}
	if ( $event && $event->schedule !== $settings['frequency'] ) {
		wp_clear_scheduled_hook( KPOPBLOG_AUTOMATION_HOOK );
		$event = false;
	}
	if ( ! $event ) {
		wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, $settings['frequency'], KPOPBLOG_AUTOMATION_HOOK );
	}
}
add_action( 'init', 'kpopblog_sync_automation_schedule', 30 );
add_action( 'update_option_' . KPOPBLOG_AUTOMATION_OPTION, 'kpopblog_sync_automation_schedule', 10, 0 );

function kpopblog_get_automation_health_check() {
	$settings = kpopblog_get_automation_settings();
	if ( empty( $settings['enabled'] ) ) {
		return kpopblog_health_check( 'automation', 'warning', 'AI content automation', 'Automation is disabled; enable it after configuring the server credential.', admin_url( 'admin.php?page=kpopblog-automation' ) );
	}
	if ( ! kpopblog_has_openai_api_key() ) {
		return kpopblog_health_check( 'automation', 'critical', 'AI content automation', 'The server-side OpenAI credential is missing.', admin_url( 'admin.php?page=kpopblog-automation' ) );
	}
	if ( ! wp_next_scheduled( KPOPBLOG_AUTOMATION_HOOK ) ) {
		return kpopblog_health_check( 'automation', 'critical', 'AI content automation', 'Automation is enabled but its WordPress cron event is missing.', admin_url( 'admin.php?page=kpopblog-automation' ) );
	}
	return kpopblog_health_check( 'automation', 'good', 'AI content automation', 'Grounded discovery is enabled and scheduled with server-only credentials.', admin_url( 'admin.php?page=kpopblog-automation' ) );
}
