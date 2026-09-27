<?php
/**
 * Keyless K-pop news collector.
 *
 * Reads publisher RSS feeds (the source catalogue in news-sources.php plus
 * per-artist tag feeds rotated across runs), keeps K-pop items, tags them with
 * catalogue artists, merges the same story from several outlets, and publishes
 * on-site summaries with a compact source credit. Official label YouTube feeds
 * fill the video section, comeback announcements with explicit dates fill the
 * release calendar, and community-automation.php opens discussion threads and
 * a weekly poll.
 *
 * No API key is required. When an OpenAI key is configured and AI briefs are
 * enabled, each summary is rewritten from the publisher's text.
 *
 * @package KpopBlog
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const KPOPBLOG_COLLECTOR_OPTION = 'kpopblog_collector';
const KPOPBLOG_COLLECTOR_HOOK   = 'kpopblog_run_news_collector';
const KPOPBLOG_COLLECTOR_MODEL  = 'rss-collector';

function kpopblog_collector_defaults() {
	return array(
		'enabled'              => 1,
		'frequency'            => 'hourly',
		'max_posts'            => 8,
		'daily_max_posts'      => 48,
		'artist_feeds_per_run' => 6,
		'max_age_days'         => 4,
		'fetch_images'         => 1,
		'collect_videos'       => 1,
		'extract_comebacks'    => 1,
		'discussion_threads'   => 3,
		'community_hubs'       => 1,
		'recurring_threads'    => 1,
		'weekly_poll'          => 1,
		'ai_rewrite'           => 1,
		'artist_feed_template' => 'https://www.soompi.com/tag/%s/feed',
		'sources'              => kpopblog_collector_default_sources(),
		'general_feeds'        => '',
		'video_channels'       => implode( "\n", array(
			'UC3IZKseVpdzPSBaWxBxundA', // HYBE LABELS
			'UCEf_Bc-KVd7onSeifS3py9g', // SMTOWN
			'UCaO6TYtlC8U5ttz62hTrZgg', // JYP Entertainment
			'UCOmHUn--16B90oW2L6FRR3A', // BLACKPINK
			'UCYDmx2Sfpnaxg488yBpZIGg', // STARSHIP
			'UCweOkPb1wVVH0Q0Tlj4a5Pw', // 1theK
		) ),
	);
}

function kpopblog_collector_frequencies() {
	return array(
		'kpopblog_30min' => 'Every 30 minutes',
		'hourly'         => 'Hourly',
		'twicedaily'     => 'Twice daily',
		'daily'          => 'Daily',
	);
}

function kpopblog_collector_cron_schedules( $schedules ) {
	if ( ! isset( $schedules['kpopblog_30min'] ) ) {
		$schedules['kpopblog_30min'] = array( 'interval' => 30 * MINUTE_IN_SECONDS, 'display' => 'Every 30 minutes' );
	}
	return $schedules;
}
add_filter( 'cron_schedules', 'kpopblog_collector_cron_schedules' );

function kpopblog_sanitize_url_lines( $value, $max = 20 ) {
	$urls = array();
	foreach ( preg_split( '/[\r\n]+/', (string) $value ) as $line ) {
		$url = esc_url_raw( trim( $line ), array( 'https' ) );
		if ( '' !== $url && ! in_array( $url, $urls, true ) ) { $urls[] = $url; }
		if ( count( $urls ) >= $max ) { break; }
	}
	return implode( "\n", $urls );
}

function kpopblog_sanitize_collector_settings( $input ) {
	$defaults = kpopblog_collector_defaults();
	$input    = is_array( $input ) ? $input : array();
	$frequency = isset( $input['frequency'] ) ? sanitize_key( (string) $input['frequency'] ) : $defaults['frequency'];
	$template  = isset( $input['artist_feed_template'] ) ? trim( (string) $input['artist_feed_template'] ) : $defaults['artist_feed_template'];
	if ( '' !== $template && ( 0 !== strpos( $template, 'https://' ) || false === strpos( $template, '%s' ) ) ) {
		$template = $defaults['artist_feed_template'];
	}
	$channels = array();
	foreach ( preg_split( '/[\s,]+/', isset( $input['video_channels'] ) ? (string) $input['video_channels'] : '' ) as $channel ) {
		if ( preg_match( '/^UC[A-Za-z0-9_-]{22}$/', $channel ) && ! in_array( $channel, $channels, true ) ) { $channels[] = $channel; }
	}
	$flag = function ( $key ) use ( $input ) { return ! empty( $input[ $key ] ) ? 1 : 0; };
	$catalog = kpopblog_collector_source_catalog();
	$sources = array();
	foreach ( isset( $input['sources'] ) && is_array( $input['sources'] ) ? $input['sources'] : array() as $key => $value ) {
		// Accept both a list of keys and checkbox maps (key => 1).
		$source_key = is_int( $key ) ? sanitize_key( (string) $value ) : ( $value ? sanitize_key( (string) $key ) : '' );
		if ( isset( $catalog[ $source_key ] ) && ! in_array( $source_key, $sources, true ) ) { $sources[] = $source_key; }
	}
	$builtin_urls = wp_list_pluck( $catalog, 'url' );
	$custom = array_filter( explode( "\n", kpopblog_sanitize_url_lines( isset( $input['general_feeds'] ) ? $input['general_feeds'] : '' ) ), function ( $url ) use ( $builtin_urls ) {
		return '' !== $url && ! in_array( $url, $builtin_urls, true );
	} );

	return array(
		'enabled'              => $flag( 'enabled' ),
		'frequency'            => array_key_exists( $frequency, kpopblog_collector_frequencies() ) ? $frequency : $defaults['frequency'],
		'max_posts'            => max( 1, min( 30, isset( $input['max_posts'] ) ? (int) $input['max_posts'] : $defaults['max_posts'] ) ),
		'daily_max_posts'      => max( 1, min( 300, isset( $input['daily_max_posts'] ) ? (int) $input['daily_max_posts'] : $defaults['daily_max_posts'] ) ),
		'artist_feeds_per_run' => max( 0, min( 40, isset( $input['artist_feeds_per_run'] ) ? (int) $input['artist_feeds_per_run'] : $defaults['artist_feeds_per_run'] ) ),
		'max_age_days'         => max( 1, min( 14, isset( $input['max_age_days'] ) ? (int) $input['max_age_days'] : $defaults['max_age_days'] ) ),
		'fetch_images'         => $flag( 'fetch_images' ),
		'collect_videos'       => $flag( 'collect_videos' ),
		'extract_comebacks'    => $flag( 'extract_comebacks' ),
		'discussion_threads'   => max( 0, min( 10, isset( $input['discussion_threads'] ) ? (int) $input['discussion_threads'] : $defaults['discussion_threads'] ) ),
		'community_hubs'       => $flag( 'community_hubs' ),
		'recurring_threads'    => $flag( 'recurring_threads' ),
		'weekly_poll'          => $flag( 'weekly_poll' ),
		'ai_rewrite'           => $flag( 'ai_rewrite' ),
		'artist_feed_template' => esc_url_raw( $template, array( 'https' ) ) ? $template : '',
		'sources'              => $sources,
		'general_feeds'        => implode( "\n", $custom ),
		'video_channels'       => implode( "\n", array_slice( $channels, 0, 20 ) ),
	);
}

function kpopblog_get_collector_settings() {
	$saved = get_option( KPOPBLOG_COLLECTOR_OPTION, null );
	if ( ! is_array( $saved ) ) { return kpopblog_collector_defaults(); }
	return kpopblog_sanitize_collector_settings( array_merge( kpopblog_collector_defaults(), $saved ) );
}

/* ---------- text helpers ---------- */

/** Short plain-text summary (videos, legacy callers). */
function kpopblog_collector_clean_summary( $html, $max_chars = 420 ) {
	return kpopblog_collector_build_summary( array( $html ), '', $max_chars );
}

function kpopblog_collector_normalize_url( $url ) {
	$url = esc_url_raw( trim( (string) $url ), array( 'https', 'http' ) );
	if ( '' === $url ) { return ''; }
	$url = preg_replace( '/^http:/i', 'https:', $url );
	$url = remove_query_arg( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'oc' ), $url );
	return untrailingslashit( $url );
}

/* ---------- fetching ---------- */

/** Some publishers send feeds with non-standard content types (e.g. "xml;charset=UTF-8"). */
function kpopblog_collector_force_feed( $feed ) {
	$feed->force_feed( true );
}

function kpopblog_collector_feed_lifetime() {
	return 10 * MINUTE_IN_SECONDS;
}

/**
 * Fetch a feed and return normalized items.
 *
 * @return array|WP_Error
 */
function kpopblog_collector_fetch_feed( $url, $max_items = 60 ) {
	if ( ! function_exists( 'fetch_feed' ) ) {
		include_once ABSPATH . WPINC . '/feed.php';
	}
	add_filter( 'wp_feed_cache_transient_lifetime', 'kpopblog_collector_feed_lifetime' );
	add_action( 'wp_feed_options', 'kpopblog_collector_force_feed' );
	$feed = fetch_feed( $url );
	remove_action( 'wp_feed_options', 'kpopblog_collector_force_feed' );
	remove_filter( 'wp_feed_cache_transient_lifetime', 'kpopblog_collector_feed_lifetime' );
	if ( is_wp_error( $feed ) ) { return $feed; }

	$items = array();
	foreach ( $feed->get_items( 0, $max_items ) as $item ) {
		$categories = array();
		foreach ( (array) $item->get_categories() as $category ) {
			if ( $category && $category->get_label() ) { $categories[] = kpopblog_decode_text_entities( $category->get_label() ); }
		}
		$image = '';
		$enclosure = $item->get_enclosure();
		if ( $enclosure && $enclosure->get_link() && preg_match( '/^image\//', (string) $enclosure->get_type() ) ) {
			$image = $enclosure->get_link();
		} elseif ( $enclosure && $enclosure->get_thumbnail() ) {
			$image = $enclosure->get_thumbnail();
		}
		$description = (string) $item->get_description( true );
		$content     = (string) $item->get_content( true );
		if ( '' === $image && preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $description . $content, $img ) ) {
			$image = html_entity_decode( $img[1], ENT_QUOTES, 'UTF-8' );
		}
		$media_tags = $item->get_item_tags( 'http://search.yahoo.com/mrss/', 'group' );
		$media_description = '';
		if ( ! empty( $media_tags[0]['child']['http://search.yahoo.com/mrss/']['description'][0]['data'] ) ) {
			$media_description = (string) $media_tags[0]['child']['http://search.yahoo.com/mrss/']['description'][0]['data'];
		}
		$items[] = array(
			'id'          => (string) $item->get_id(),
			'title'       => trim( kpopblog_collector_decode( wp_strip_all_tags( (string) $item->get_title() ) ) ),
			'url'         => (string) $item->get_permalink(),
			'summary'     => '' !== $description ? $description : $content,
			'content'     => $content !== $description ? $content : '',
			'media_text'  => $media_description,
			'timestamp'   => (int) $item->get_date( 'U' ),
			'categories'  => $categories,
			'image'       => (string) $image,
		);
	}
	return $items;
}

/* ---------- persistence helpers ---------- */

function kpopblog_collector_seen( $dedupe_key ) {
	global $wpdb;
	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}kb_automation_items WHERE dedupe_key = %s", $dedupe_key ) );
}

function kpopblog_collector_remember( $dedupe_key, $kind, $post_id, $url, array $sources = array() ) {
	global $wpdb;
	$now = current_time( 'mysql', true );
	return false !== $wpdb->insert( $wpdb->prefix . 'kb_automation_items', array(
		'dedupe_key'         => $dedupe_key,
		'content_hash'       => hash( 'sha256', $kind . '|' . $url ),
		'kind'               => substr( $kind, 0, 16 ),
		'wp_post_id'         => (int) $post_id,
		'primary_source_url' => $url,
		'sources_json'       => wp_json_encode( $sources ),
		'response_id'        => '',
		'first_seen_at'      => $now,
		'last_seen_at'       => $now,
	) );
}

/** Dedicated byline for automated content so readers can tell it apart from staff and members. */
function kpopblog_newsroom_user_id() {
	$user_id = (int) get_option( 'kpopblog_newsroom_user_id', 0 );
	if ( $user_id && get_userdata( $user_id ) ) { return $user_id; }
	$existing = get_user_by( 'login', 'kpopblog-newsroom' );
	if ( $existing ) {
		update_option( 'kpopblog_newsroom_user_id', (int) $existing->ID, false );
		return (int) $existing->ID;
	}
	$user_id = wp_insert_user( array(
		'user_login'   => 'kpopblog-newsroom',
		'user_pass'    => wp_generate_password( 40, true, true ),
		'user_email'   => '',
		'display_name' => 'KpopBlog Newsroom',
		'nickname'     => 'KpopBlog Newsroom',
		'first_name'   => 'KpopBlog',
		'last_name'    => 'Newsroom',
		'role'         => 'author',
		'description'  => 'Automated desk that curates K-pop headlines from credited publishers.',
	) );
	if ( is_wp_error( $user_id ) ) {
		return function_exists( 'kpopblog_automation_author_id' ) ? kpopblog_automation_author_id() : 0;
	}
	update_user_meta( $user_id, 'kb_role', 'editor' );
	update_option( 'kpopblog_newsroom_user_id', (int) $user_id, false );
	return (int) $user_id;
}

function kpopblog_artist_names_for_slugs( array $slugs ) {
	$names = array();
	foreach ( kpopblog_get_artist_matchers() as $matcher ) {
		$names[ $matcher['slug'] ] = $matcher['name'];
	}
	$out = array();
	foreach ( $slugs as $slug ) {
		if ( isset( $names[ $slug ] ) ) { $out[] = $names[ $slug ]; }
	}
	return $out;
}

/* ---------- AI rewrite ---------- */

/**
 * Rewrite a story as an original article with the configured OpenAI model
 * (structured output). Facts come only from the publisher's text; wording,
 * headline, and structure are new. Returns null on any failure so the
 * extractive summary is used instead.
 *
 * @return array{title:string,summary:string,body:string}|null
 */
function kpopblog_collector_ai_rewrite( array $item ) {
	if ( ! function_exists( 'kpopblog_get_openai_api_key' ) || ! kpopblog_has_openai_api_key() ) { return null; }
	$settings = function_exists( 'kpopblog_get_automation_settings' ) ? kpopblog_get_automation_settings() : array( 'model' => 'gpt-6-luna' );
	$source = ! empty( $item['source_text'] ) ? $item['source_text'] : $item['summary'];
	$artists = function_exists( 'kpopblog_artist_names_for_slugs' ) ? kpopblog_artist_names_for_slugs( $item['artists'] ) : array();
	$instructions = implode( "\n", array(
		'You are a news editor at KpopBlog, an English-language K-pop news site.',
		'Rewrite the story below as an original article for K-pop fans.',
		'- Use only facts stated in the source text. Never add names, numbers, dates, places, quotes, or claims that are not in it. If something is unclear, leave it out.',
		'- Write every sentence in your own words; do not copy sentences or distinctive phrases. Keep at most one short direct quote, in quotation marks, and only if it is essential.',
		'- Neutral, clear, friendly tone. No hype, no speculation, no questions to the reader, no markdown, no links, no emojis.',
		'- title: a new factual headline, at most 90 characters, not clickbait.',
		'- summary: one or two sentences (at most 240 characters) giving the key fact.',
		'- body: three short paragraphs (120 to 220 words in total) separated by a blank line: what happened, the key details, and brief context from the source.',
		'- questions: two short, open questions fans could discuss about this story (about the music, performance, or news itself; never about private lives or rumors).',
	) );
	$input = 'Original headline: ' . $item['title'] . "\n" . ( $artists ? 'Artists: ' . implode( ', ', $artists ) . "\n" : '' ) . 'Publisher: ' . $item['publisher'] . "\n\nSource text:\n" . $source;

	$response = wp_remote_post( 'https://api.openai.com/v1/responses', array(
		'timeout'     => 60,
		'redirection' => 0,
		'headers'     => array( 'Authorization' => 'Bearer ' . kpopblog_get_openai_api_key(), 'Content-Type' => 'application/json' ),
		'body'        => wp_json_encode( array(
			'model'             => $settings['model'],
			'store'             => false,
			'reasoning'         => array( 'effort' => 'low' ),
			'instructions'      => $instructions,
			'input'             => $input,
			'max_output_tokens' => 2000,
			'text'              => array(
				'format' => array(
					'type'   => 'json_schema',
					'name'   => 'kpopblog_article',
					'strict' => true,
					'schema' => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'title'     => array( 'type' => 'string' ),
							'summary'   => array( 'type' => 'string' ),
							'body'      => array( 'type' => 'string' ),
							'questions' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						),
						'required'             => array( 'title', 'summary', 'body', 'questions' ),
					),
				),
			),
		) ),
		'data_format' => 'body',
	) );
	if ( is_wp_error( $response ) ) {
		$GLOBALS['kpopblog_ai_last_error'] = 'transport: ' . $response->get_error_message();
		return null;
	}
	$status = (int) wp_remote_retrieve_response_code( $response );
	if ( 200 !== $status ) {
		$error = json_decode( wp_remote_retrieve_body( $response ), true );
		$GLOBALS['kpopblog_ai_last_error'] = 'HTTP ' . $status . ( isset( $error['error']['message'] ) ? ': ' . substr( (string) $error['error']['message'], 0, 160 ) : '' );
		return null;
	}
	$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $decoded ) || ! function_exists( 'kpopblog_extract_openai_output_text' ) ) { return null; }
	$text = kpopblog_extract_openai_output_text( $decoded );
	if ( is_wp_error( $text ) ) {
		$GLOBALS['kpopblog_ai_last_error'] = $text->get_error_code();
		return null;
	}
	$article = json_decode( $text, true );
	if ( ! is_array( $article ) ) { return null; }
	$title   = trim( sanitize_text_field( (string) ( $article['title'] ?? '' ) ) );
	$summary = trim( sanitize_text_field( (string) ( $article['summary'] ?? '' ) ) );
	$body    = trim( sanitize_textarea_field( (string) ( $article['body'] ?? '' ) ) );
	if ( kpopblog_collector_strlen( $title ) < 15 || kpopblog_collector_strlen( $title ) > 140 || kpopblog_collector_strlen( $summary ) < 40 || kpopblog_collector_strlen( $body ) < 400 ) {
		$GLOBALS['kpopblog_ai_last_error'] = 'output failed length checks';
		return null;
	}
	$questions = array();
	foreach ( array_slice( isset( $article['questions'] ) && is_array( $article['questions'] ) ? $article['questions'] : array(), 0, 2 ) as $question ) {
		$question = trim( sanitize_text_field( (string) $question ) );
		if ( kpopblog_collector_strlen( $question ) >= 10 && kpopblog_collector_strlen( $question ) <= 200 ) { $questions[] = $question; }
	}
	return array( 'title' => $title, 'summary' => $summary, 'body' => $body, 'questions' => $questions );
}

/* ---------- publishing ---------- */

/**
 * Stored body: the summary only. Internal links and the source credit are
 * added at render time by kpopblog_collector_article_footer() so they stay
 * current as related stories, threads, and extra sources arrive.
 */
function kpopblog_collector_article_html( $body_text ) {
	$html = '';
	$paragraphs = preg_split( '/\n{2,}|\r\n\r\n/', $body_text );
	if ( count( $paragraphs ) === 1 ) {
		// Break a long single-block summary into two readable paragraphs.
		$sentences = kpopblog_collector_sentences( $body_text );
		if ( count( $sentences ) >= 4 ) {
			$half = (int) ceil( count( $sentences ) / 2 );
			$paragraphs = array( implode( ' ', array_slice( $sentences, 0, $half ) ), implode( ' ', array_slice( $sentences, $half ) ) );
		}
	}
	foreach ( $paragraphs as $paragraph ) {
		$paragraph = trim( $paragraph );
		if ( '' !== $paragraph ) { $html .= '<p>' . esc_html( $paragraph ) . '</p>'; }
	}
	return $html;
}

/**
 * @return int|WP_Error New post ID.
 */
function kpopblog_collector_publish_article( array $item, array $settings ) {
	$ai_written = ! empty( $item['ai_body'] );
	$body = $ai_written ? $item['ai_body'] : $item['summary'];

	$timestamp = $item['timestamp'] > 0 && $item['timestamp'] <= time() ? $item['timestamp'] : time();
	$post_id = wp_insert_post( wp_slash( array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'post_title'     => $item['title'],
		'post_excerpt'   => $item['summary'],
		'post_content'   => kpopblog_collector_article_html( $body ),
		'post_author'    => kpopblog_newsroom_user_id(),
		'post_date_gmt'  => gmdate( 'Y-m-d H:i:s', $timestamp ),
		'post_date'      => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp ) ),
		'comment_status' => 'open',
	) ), true );
	if ( is_wp_error( $post_id ) ) { return $post_id; }

	$meta = array(
		'kb_category_slug'        => $item['category'],
		'kb_language'             => isset( $item['lang'] ) ? $item['lang'] : 'en',
		'kb_source'               => $ai_written ? 'ai-brief' : 'aggregated',
		'kb_sources'              => array( array( 'url' => $item['url'], 'title' => isset( $item['source_title'] ) ? $item['source_title'] : $item['title'], 'publisher' => $item['publisher'] ) ),
		'kb_ai_model'             => $ai_written ? ( isset( $item['ai_model'] ) ? $item['ai_model'] : '' ) : '',
		'kb_source_key'           => isset( $item['source_key'] ) ? $item['source_key'] : '',
		'kb_source_url'           => $item['url'],
		'kb_source_title'         => isset( $item['source_title'] ) ? $item['source_title'] : $item['title'],
		'kb_source_publisher'     => $item['publisher'],
		'kb_source_urls'          => array( $item['url'] ),
		'kb_related_artist_slugs' => $item['artists'],
		'kb_external_image'       => $item['image'],
		'kb_reading_time'         => 1,
		'kb_collected_at'         => gmdate( 'c' ),
	);
	foreach ( $meta as $key => $value ) {
		if ( '' !== $value && array() !== $value ) { update_post_meta( $post_id, $key, $value ); }
	}
	if ( $item['artists'] ) {
		wp_set_object_terms( $post_id, $item['artists'], 'kb_artist_tag', false );
		wp_set_post_tags( $post_id, kpopblog_artist_names_for_slugs( $item['artists'] ), false );
	}
	return (int) $post_id;
}

/* ---------- comebacks ---------- */

/**
 * Create a release-calendar entry when a headline announces a release and the
 * summary states an explicit upcoming date. Conservative on purpose: anything
 * ambiguous is skipped.
 */
function kpopblog_collector_maybe_comeback( array $item, $article_id ) {
	if ( empty( $item['artists'] ) ) { return 0; }
	if ( ! preg_match( '/\b(announc|confirm|reveal|sets?\b|unveil|schedul|gear|prepar)\w*.*\b(comeback|return|album|mini album|EP|single|debut|release)\b|\b(comeback|release|debut) (date|schedule)/i', $item['title'] ) ) { return 0; }

	$months = 'January|February|March|April|May|June|July|August|September|October|November|December';
	if ( ! preg_match_all( '/\b(' . $months . ')\s+(\d{1,2})(?:st|nd|rd|th)?(?:,?\s+(20\d\d))?/i', $item['summary'], $matches, PREG_SET_ORDER ) ) { return 0; }
	$now = time();
	$best = 0;
	foreach ( $matches as $match ) {
		$year = ! empty( $match[3] ) ? (int) $match[3] : (int) gmdate( 'Y', $item['timestamp'] ?: $now );
		$ts = strtotime( $match[1] . ' ' . (int) $match[2] . ' ' . $year . ' 18:00:00 +0900' );
		if ( ! $ts ) { continue; }
		if ( empty( $match[3] ) && $ts < $now - 30 * DAY_IN_SECONDS ) {
			$ts = strtotime( '+1 year', $ts );
		}
		if ( $ts > $now && $ts < $now + 200 * DAY_IN_SECONDS && $ts > $best ) { $best = $ts; }
	}
	if ( ! $best ) { return 0; }

	$artist_slug = $item['artists'][0];
	$type = 'album';
	if ( preg_match( '/\bdebut/i', $item['title'] ) ) { $type = 'debut'; }
	elseif ( preg_match( '/\bsingle\b/i', $item['title'] ) ) { $type = 'single'; }
	elseif ( preg_match( '/\bM\/?V\b|music video/i', $item['title'] ) ) { $type = 'mv'; }

	$release_title = '';
	if ( preg_match( '/[“"‘\']([^”"’\']{2,80})[”"’\']/u', $item['title'], $quoted ) ) {
		$release_title = trim( $quoted[1] );
	}
	$names = kpopblog_artist_names_for_slugs( array( $artist_slug ) );
	$artist_name = $names ? $names[0] : $artist_slug;
	// Solo and unit releases: keep the headline subject ("SEVENTEEN’s Jeonghan X Joshua") instead of the whole group.
	if ( preg_match( '/^(?:Update:\s*)?(.{2,60}?)\s+(?:Gears|Reveals?|Announces?|Confirms?|Sets?|Unveils?|Drops?|Shares?|Releases?|Prepares?|Makes?|Is|Are|To|Will)\b/u', $item['title'], $subject )
		&& false !== stripos( $subject[1], $artist_name ) ) {
		$artist_name = trim( $subject[1] );
	}
	$title = $release_title ? $artist_name . ' – ' . $release_title : $artist_name . ' ' . ( 'debut' === $type ? 'debut' : 'comeback' );

	$dedupe = hash( 'sha256', 'comeback|' . $artist_slug . '|' . gmdate( 'Y-m-d', $best ) );
	if ( kpopblog_collector_seen( $dedupe ) ) { return 0; }

	$post_id = wp_insert_post( wp_slash( array(
		'post_type'    => 'kb_comeback',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_content' => $item['summary'],
		'post_author'  => kpopblog_newsroom_user_id(),
	) ), true );
	if ( is_wp_error( $post_id ) ) { return 0; }
	update_post_meta( $post_id, 'kb_artist_slug', $artist_slug );
	update_post_meta( $post_id, 'kb_type', $type );
	update_post_meta( $post_id, 'kb_release_at', gmdate( 'c', $best ) );
	update_post_meta( $post_id, 'kb_source_url', $item['url'] );
	update_post_meta( $post_id, 'kb_source_title', $item['title'] );
	update_post_meta( $post_id, 'kb_external_image', $item['image'] );
	update_post_meta( $post_id, 'kb_article_id', (int) $article_id );
	kpopblog_collector_remember( $dedupe, 'comeback', $post_id, $item['url'] );
	return (int) $post_id;
}

/* ---------- videos ---------- */

function kpopblog_collector_video_category( $title ) {
	if ( preg_match( '/teaser|trailer|preview|highlight medley|concept film|spoiler/i', $title ) ) { return 'Teaser'; }
	if ( preg_match( '/\bM\/?V\b|music video|official video|visualizer/i', $title ) ) { return 'MV'; }
	if ( preg_match( '/dance practice|performance|stage|live|choreography|relay dance/i', $title ) ) { return 'Performance'; }
	if ( preg_match( '/interview|behind|making|vlog|episode|\bEP\.\s?\d/i', $title ) ) { return 'Behind'; }
	return 'Other';
}

function kpopblog_collector_collect_videos( array $settings, $deadline, array &$log ) {
	$created = 0;
	$max_age = time() - 21 * DAY_IN_SECONDS;
	foreach ( array_filter( preg_split( '/\s+/', (string) $settings['video_channels'] ) ) as $channel ) {
		if ( time() > $deadline || $created >= 6 ) { break; }
		$items = kpopblog_collector_fetch_feed( 'https://www.youtube.com/feeds/videos.xml?channel_id=' . rawurlencode( $channel ), 15 );
		if ( is_wp_error( $items ) ) { $log[] = 'YouTube ' . $channel . ': ' . $items->get_error_code(); continue; }
		foreach ( $items as $item ) {
			if ( $created >= 6 ) { break; }
			if ( $item['timestamp'] && $item['timestamp'] < $max_age ) { continue; }
			// Shorts and re-uploads are hashtag-heavy; skip them and keep the main upload.
			if ( preg_match( '/#shorts?\b/i', $item['title'] ) || preg_match_all( '/#\S+/u', $item['title'] ) >= 3 ) { continue; }
			$item['title'] = trim( preg_replace( '/(\s+#\S+)+\s*$/u', '', $item['title'] ) );
			$video_id = '';
			if ( preg_match( '/yt:video:([A-Za-z0-9_-]{11})/', $item['id'], $match ) || preg_match( '/[?&]v=([A-Za-z0-9_-]{11})/', $item['url'], $match ) ) {
				$video_id = $match[1];
			}
			if ( '' === $video_id ) { continue; }
			$category = kpopblog_collector_video_category( $item['title'] );
			if ( 'Other' === $category ) { continue; }
			$artists = kpopblog_match_artists( $item['title'] );
			if ( ! $artists ) { continue; }
			$dedupe = hash( 'sha256', 'video|' . $video_id );
			if ( kpopblog_collector_seen( $dedupe ) ) { continue; }

			$timestamp = $item['timestamp'] > 0 ? $item['timestamp'] : time();
			$post_id = wp_insert_post( wp_slash( array(
				'post_type'      => 'kb_video',
				'post_status'    => 'publish',
				'post_title'     => $item['title'],
				'post_content'   => kpopblog_collector_clean_summary( $item['media_text'], 600 ),
				'post_author'    => kpopblog_newsroom_user_id(),
				'post_date_gmt'  => gmdate( 'Y-m-d H:i:s', $timestamp ),
				'post_date'      => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp ) ),
				'comment_status' => 'open',
			) ), true );
			if ( is_wp_error( $post_id ) ) { continue; }
			update_post_meta( $post_id, 'kb_youtube_id', $video_id );
			update_post_meta( $post_id, 'kb_artist_slug', $artists[0] );
			update_post_meta( $post_id, 'kb_video_category', $category );
			update_post_meta( $post_id, 'kb_source_url', 'https://www.youtube.com/watch?v=' . $video_id );
			wp_set_object_terms( $post_id, $artists, 'kb_artist_tag', false );
			kpopblog_collector_remember( $dedupe, 'video', $post_id, 'https://www.youtube.com/watch?v=' . $video_id );
			$created++;
		}
	}
	return $created;
}

/* ---------- run ---------- */

/**
 * Feeds for this run: every enabled catalogue source and custom feed, plus the
 * next artist tag feeds in rotation.
 *
 * @return array<int,array{url:string,artist:string,key:string,source:array}>
 */
function kpopblog_collector_feed_plan( array $settings, $all_artists = false ) {
	$plan = array();
	$catalog = kpopblog_collector_source_catalog();
	foreach ( (array) $settings['sources'] as $key ) {
		if ( isset( $catalog[ $key ] ) ) {
			$plan[] = array( 'url' => $catalog[ $key ]['url'], 'artist' => '', 'key' => $key, 'source' => $catalog[ $key ] );
		}
	}
	foreach ( array_filter( preg_split( '/[\r\n]+/', (string) $settings['general_feeds'] ) ) as $url ) {
		$url = trim( $url );
		$plan[] = array( 'url' => $url, 'artist' => '', 'key' => 'custom:' . md5( $url ), 'source' => array( 'name' => kpopblog_collector_publisher_from_url( $url ), 'lang' => 'en', 'gate' => 'kpop' ) );
	}
	if ( '' === $settings['artist_feed_template'] ) { return $plan; }

	$tagged = array_values( array_filter( kpopblog_get_artist_matchers(), function ( $matcher ) {
		return '' !== $matcher['tag'];
	} ) );
	$count = count( $tagged );
	if ( ! $count ) { return $plan; }
	$take = $all_artists ? $count : min( $count, (int) $settings['artist_feeds_per_run'] );
	$cursor = (int) get_option( 'kpopblog_collector_cursor', 0 ) % $count;
	$artist_source = array( 'name' => kpopblog_collector_publisher_from_url( $settings['artist_feed_template'] ), 'lang' => 'en', 'gate' => 'none' );
	for ( $i = 0; $i < $take; $i++ ) {
		$matcher = $tagged[ ( $cursor + $i ) % $count ];
		$plan[] = array( 'url' => sprintf( $settings['artist_feed_template'], rawurlencode( $matcher['tag'] ) ), 'artist' => $matcher['slug'], 'key' => 'artist:' . $matcher['slug'], 'source' => $artist_source );
	}
	update_option( 'kpopblog_collector_cursor', ( $cursor + $take ) % $count, false );
	return $plan;
}

/**
 * Fill in a thin summary and a missing image from the source page, then keep
 * the publisher text for the optional AI rewrite.
 */
function kpopblog_collector_enrich( array $item, array $settings, $deadline ) {
	$thin = kpopblog_collector_strlen( $item['summary'] ) < 260;
	$no_image = ! empty( $settings['fetch_images'] ) && '' === $item['image'];
	$texts = array( $item['raw_summary'], $item['raw_content'] );
	if ( ( $thin || $no_image ) && time() < $deadline - 8 ) {
		$page = kpopblog_collector_fetch_page_meta( $item['url'] );
		if ( $no_image ) { $item['image'] = $page['image']; }
		$texts[] = $page['description'];
		$texts[] = $page['lead'];
		if ( $thin ) {
			$item['summary'] = kpopblog_collector_build_summary( $texts, $item['title'] );
		}
	}
	$item['source_text'] = function_exists( 'mb_substr' )
		? mb_substr( kpopblog_collector_plain_text( implode( "\n\n", array_filter( $texts ) ) ), 0, 4000, 'UTF-8' )
		: substr( kpopblog_collector_plain_text( implode( "\n\n", array_filter( $texts ) ) ), 0, 4000 );
	return $item;
}

/**
 * Run the collector.
 *
 * @param string $trigger scheduled|manual|backfill|fallback
 * @return array|WP_Error Counts.
 */
function kpopblog_run_news_collector( $trigger = 'scheduled' ) {
	global $wpdb;
	$trigger  = in_array( $trigger, array( 'scheduled', 'manual', 'backfill', 'fallback' ), true ) ? $trigger : 'manual';
	$settings = kpopblog_get_collector_settings();
	if ( in_array( $trigger, array( 'scheduled', 'fallback' ), true ) && empty( $settings['enabled'] ) ) {
		return array( 'discovered' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0 );
	}
	if ( ! add_option( 'kpopblog_collector_lock', time(), '', false ) ) {
		$locked_at = (int) get_option( 'kpopblog_collector_lock', 0 );
		if ( $locked_at > time() - 10 * MINUTE_IN_SECONDS ) {
			return new WP_Error( 'collector_locked', 'Another collector run is still active.' );
		}
		update_option( 'kpopblog_collector_lock', time(), false );
	}

	if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 300 ); } // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( function_exists( 'ignore_user_abort' ) ) { ignore_user_abort( true ); }
	$started   = time();
	$backfill  = 'backfill' === $trigger;
	$use_ai    = ! empty( $settings['ai_rewrite'] ) && function_exists( 'kpopblog_has_openai_api_key' ) && kpopblog_has_openai_api_key();
	$deadline  = $started + ( $backfill ? ( $use_ai ? 280 : 150 ) : ( $use_ai ? 200 : 75 ) );
	$max_posts = $backfill ? max( 30, (int) $settings['max_posts'] ) : (int) $settings['max_posts'];

	// Daily quota, paced across the day: by any time of day the site may have published
	// its proportional share of the daily maximum (plus a small buffer). Backfill ignores pacing.
	$day_key   = wp_date( 'Y-m-d' );
	$day_state = get_option( 'kpopblog_collector_day', array() );
	$today     = is_array( $day_state ) && isset( $day_state['date'] ) && $day_state['date'] === $day_key ? (int) $day_state['count'] : 0;
	if ( ! $backfill ) {
		$local    = current_datetime();
		$fraction = max( 1 / 24, ( (int) $local->format( 'G' ) * 3600 + (int) $local->format( 'i' ) * 60 + 3600 ) / 86400 );
		$paced    = min( (int) $settings['daily_max_posts'], (int) ceil( (int) $settings['daily_max_posts'] * $fraction ) + 2 );
		$max_posts = max( 0, min( $max_posts, $paced - $today ) );
	}
	$max_age   = time() - ( $backfill ? max( 7, (int) $settings['max_age_days'] ) : (int) $settings['max_age_days'] ) * DAY_IN_SECONDS;

	$runs_table = $wpdb->prefix . 'kb_automation_runs';
	$wpdb->insert( $runs_table, array(
		'trigger_type' => substr( 'rss-' . $trigger, 0, 16 ),
		'status'       => 'running',
		'model'        => KPOPBLOG_COLLECTOR_MODEL,
		'started_at'   => current_time( 'mysql', true ),
	) );
	$run_id = (int) $wpdb->insert_id;
	update_option( 'kpopblog_collector_last_started', time(), false );

	$counts = array( 'discovered' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0 );
	$extra  = array( 'videos' => 0, 'comebacks' => 0, 'threads' => 0, 'polls' => 0, 'feeds' => 0, 'ai' => 0, 'ai_failed' => 0 );
	$log    = array();
	$candidates = array();
	$seen_urls  = array();
	$seen_titles = array();
	$by_source  = array();

	try {
		foreach ( kpopblog_collector_feed_plan( $settings, $backfill ) as $feed ) {
			if ( time() > $deadline - 25 ) { $log[] = 'Time budget reached before ' . $feed['url']; break; }
			$items = kpopblog_collector_fetch_feed( $feed['url'], 60 );
			if ( is_wp_error( $items ) ) { $log[] = $feed['source']['name'] . ': ' . $items->get_error_message(); continue; }
			$extra['feeds']++;
			foreach ( $items as $raw ) {
				$url = kpopblog_collector_normalize_url( $raw['url'] );
				if ( '' === $url || '' === $raw['title'] || isset( $seen_urls[ $url ] ) ) { continue; }
				if ( $raw['timestamp'] && $raw['timestamp'] < $max_age ) { continue; }
				$seen_urls[ $url ] = true;
				$title_key = strtolower( preg_replace( '/[^\p{L}\p{N}]+/u', '', $raw['title'] ) );
				if ( isset( $seen_titles[ $title_key ] ) ) { continue; }
				$seen_titles[ $title_key ] = true;
				$counts['discovered']++;

				$summary = kpopblog_collector_build_summary( array( $raw['summary'], $raw['content'] ), $raw['title'] );
				$title_artists = kpopblog_match_artists( $raw['title'] );
				$gate_text = $summary . ' ' . ( function_exists( 'mb_substr' ) ? mb_substr( kpopblog_collector_plain_text( $raw['content'] ), 0, 800, 'UTF-8' ) : '' );
				if ( ! kpopblog_collector_passes_gate( $feed['source'], $raw['title'], $gate_text, $raw['categories'], $title_artists, '' !== $feed['artist'] ) ) {
					continue;
				}
				$dedupe = hash( 'sha256', 'rss|' . strtolower( $url ) );
				if ( kpopblog_collector_seen( $dedupe ) ) { continue; }
				$artists = kpopblog_match_artists( $raw['title'], $summary, $raw['categories'] );
				if ( '' !== $feed['artist'] && ! in_array( $feed['artist'], $artists, true ) ) {
					$artists[] = $feed['artist'];
				}

				$candidates[] = array(
					'dedupe'      => $dedupe,
					'title'       => substr( $raw['title'], 0, 200 ),
					'url'         => $url,
					'summary'     => $summary,
					'raw_summary' => $raw['summary'],
					'raw_content' => $raw['content'],
					'timestamp'   => $raw['timestamp'],
					'artists'     => $artists,
					'image'       => esc_url_raw( $raw['image'], array( 'https' ) ),
					'publisher'   => $feed['source']['name'],
					'lang'        => isset( $feed['source']['lang'] ) ? $feed['source']['lang'] : 'en',
					'source_key'  => $feed['key'],
					'category'    => kpopblog_collector_category( $raw['title'] ),
					'feed_artist' => $feed['artist'],
				);
			}
		}

		// Newest first; artist-tagged stories win ties so artist pages fill up.
		usort( $candidates, function ( $a, $b ) {
			if ( $a['timestamp'] === $b['timestamp'] ) { return count( $b['artists'] ) - count( $a['artists'] ); }
			return $b['timestamp'] - $a['timestamp'];
		} );

		// Round-robin: every publisher and every artist feed gets its newest story in
		// before any of them gets a second one.
		$groups = array();
		foreach ( $candidates as $item ) {
			$groups[ '' !== $item['feed_artist'] ? 'artist:' . $item['feed_artist'] : $item['source_key'] ][] = $item;
		}
		$queue = array();
		for ( $round = 0; count( $queue ) < count( $candidates ); $round++ ) {
			$batch = array();
			foreach ( $groups as $items ) {
				if ( isset( $items[ $round ] ) ) { $batch[] = $items[ $round ]; }
			}
			if ( ! $batch ) { break; }
			usort( $batch, function ( $a, $b ) { return $b['timestamp'] - $a['timestamp']; } );
			$queue = array_merge( $queue, $batch );
		}

		$published = array();
		$stories   = kpopblog_collector_recent_stories();
		foreach ( $queue as $item ) {
			if ( count( $published ) >= $max_posts || time() > $deadline ) { break; }
			$source_ref = array( array( 'url' => $item['url'], 'title' => $item['title'], 'publisher' => $item['publisher'] ) );

			// The same story from another outlet: credit it on the existing article.
			$duplicate = kpopblog_collector_find_duplicate( $item, $stories );
			if ( $duplicate ) {
				if ( kpopblog_collector_add_source( $duplicate, $item ) ) {
					$counts['updated']++;
					$by_source[ $item['publisher'] . ' (merged)' ] = ( isset( $by_source[ $item['publisher'] . ' (merged)' ] ) ? $by_source[ $item['publisher'] . ' (merged)' ] : 0 ) + 1;
				}
				kpopblog_collector_remember( $item['dedupe'], 'rss-dup', $duplicate, $item['url'], $source_ref );
				continue;
			}

			$item = kpopblog_collector_enrich( $item, $settings, $deadline );
			if ( kpopblog_collector_strlen( $item['summary'] ) < 80 ) {
				$counts['skipped']++;
				kpopblog_collector_remember( $item['dedupe'], 'rss-thin', 0, $item['url'], $source_ref );
				continue;
			}
			$item['source_title']   = $item['title'];
			$item['source_summary'] = $item['summary'];
			if ( $use_ai && time() < $deadline - 25 ) {
				$ai = kpopblog_collector_ai_rewrite( $item );
				if ( $ai ) {
					$item['title']    = $ai['title'];
					$item['summary']  = $ai['summary'];
					$item['ai_body']  = $ai['body'];
					$item['ai_questions'] = $ai['questions'];
					$item['ai_model'] = kpopblog_get_automation_settings()['model'];
					$extra['ai']++;
				} else {
					$extra['ai_failed']++;
					$log[] = 'AI rewrite skipped: ' . ( isset( $GLOBALS['kpopblog_ai_last_error'] ) ? $GLOBALS['kpopblog_ai_last_error'] : 'unknown error' );
				}
			}
			$post_id = kpopblog_collector_publish_article( $item, $settings );
			if ( is_wp_error( $post_id ) ) { $counts['skipped']++; continue; }
			if ( ! kpopblog_collector_remember( $item['dedupe'], 'rss', $post_id, $item['url'], $source_ref ) ) {
				wp_delete_post( $post_id, true );
				$counts['skipped']++;
				continue;
			}
			$counts['created']++;
			$today++;
			$by_source[ $item['publisher'] ] = ( isset( $by_source[ $item['publisher'] ] ) ? $by_source[ $item['publisher'] ] : 0 ) + 1;
			$item['post_id'] = $post_id;
			$published[] = $item;
			$stories[] = array( 'post_id' => $post_id, 'tokens' => kpopblog_collector_title_tokens( $item['source_title'] ), 'artists' => $item['artists'], 'publisher' => $item['publisher'] );
			// Release dates are read from the publisher's own wording.
			if ( ! empty( $settings['extract_comebacks'] ) && kpopblog_collector_maybe_comeback( array_merge( $item, array( 'title' => $item['source_title'], 'summary' => $item['source_summary'] ) ), $post_id ) ) {
				$extra['comebacks']++;
			}
		}

		update_option( 'kpopblog_collector_day', array( 'date' => $day_key, 'count' => $today ), false );

		if ( ! empty( $settings['collect_videos'] ) && time() < $deadline - 10 ) {
			$extra['videos'] = kpopblog_collector_collect_videos( $settings, $deadline, $log );
		}
		if ( function_exists( 'kpopblog_community_automation_after_collect' ) ) {
			$community = kpopblog_community_automation_after_collect( $published, $settings );
			$extra['threads'] = (int) $community['threads'];
			$extra['polls']   = (int) $community['polls'];
		}

		$wpdb->update( $runs_table, array_merge( $counts, array(
			'status'      => 'completed',
			'error_text'  => substr( wp_json_encode( array( 'extra' => $extra, 'sources' => $by_source, 'log' => array_slice( $log, 0, 12 ) ), JSON_UNESCAPED_UNICODE ), 0, 4000 ),
			'finished_at' => current_time( 'mysql', true ),
		) ), array( 'id' => $run_id ) );
		update_option( 'kpopblog_collector_last_success', gmdate( 'c' ), false );
		delete_option( 'kpopblog_collector_last_error' );
		if ( function_exists( 'kpopblog_audit' ) ) { kpopblog_audit( 'collector_completed', 'automation_run', $run_id, array_merge( $counts, $extra ) ); }
		return array_merge( $counts, $extra );
	} catch ( Throwable $error ) {
		$wpdb->update( $runs_table, array(
			'status'      => 'failed',
			'error_code'  => 'collector_failed',
			'error_text'  => substr( sanitize_text_field( $error->getMessage() ), 0, 500 ),
			'finished_at' => current_time( 'mysql', true ),
		), array( 'id' => $run_id ) );
		update_option( 'kpopblog_collector_last_error', array( 'message' => substr( $error->getMessage(), 0, 300 ), 'at' => gmdate( 'c' ) ), false );
		return new WP_Error( 'collector_failed', $error->getMessage() );
	} finally {
		delete_option( 'kpopblog_collector_lock' );
	}
}

function kpopblog_run_scheduled_news_collector() {
	kpopblog_run_news_collector( 'scheduled' );
}
add_action( KPOPBLOG_COLLECTOR_HOOK, 'kpopblog_run_scheduled_news_collector' );

function kpopblog_sync_collector_schedule() {
	$settings = kpopblog_get_collector_settings();
	$event    = wp_get_scheduled_event( KPOPBLOG_COLLECTOR_HOOK );
	if ( empty( $settings['enabled'] ) ) {
		if ( $event ) { wp_clear_scheduled_hook( KPOPBLOG_COLLECTOR_HOOK ); }
		return;
	}
	if ( $event && $event->schedule !== $settings['frequency'] ) {
		wp_clear_scheduled_hook( KPOPBLOG_COLLECTOR_HOOK );
		$event = false;
	}
	if ( ! $event ) {
		wp_schedule_event( time() + 2 * MINUTE_IN_SECONDS, $settings['frequency'], KPOPBLOG_COLLECTOR_HOOK );
	}
}
add_action( 'init', 'kpopblog_sync_collector_schedule', 31 );
add_action( 'update_option_' . KPOPBLOG_COLLECTOR_OPTION, 'kpopblog_sync_collector_schedule', 10, 0 );

/**
 * Safety net for hosts where WP-Cron loopback requests never fire: when the
 * scheduled run is overdue, the next public API request runs the collector
 * after its response has been sent.
 */
function kpopblog_collector_fallback_trigger( $result, $server, $request ) {
	if ( ! $request instanceof WP_REST_Request || 0 !== strpos( $request->get_route(), '/' . KPOPBLOG_REST_NS . '/bundle' ) ) { return $result; }
	$settings = kpopblog_get_collector_settings();
	if ( empty( $settings['enabled'] ) ) { return $result; }
	$next = wp_next_scheduled( KPOPBLOG_COLLECTOR_HOOK );
	$last = (int) get_option( 'kpopblog_collector_last_started', 0 );
	$schedules = wp_get_schedules();
	$interval = isset( $schedules[ $settings['frequency'] ] ) ? (int) $schedules[ $settings['frequency'] ]['interval'] : HOUR_IN_SECONDS;
	$overdue = ( $next && $next < time() - 15 * MINUTE_IN_SECONDS ) || ( $last && $last < time() - $interval - 30 * MINUTE_IN_SECONDS );
	if ( ! $overdue || get_transient( 'kpopblog_collector_fallback_guard' ) ) { return $result; }
	set_transient( 'kpopblog_collector_fallback_guard', 1, 10 * MINUTE_IN_SECONDS );
	if ( ! function_exists( 'fastcgi_finish_request' ) && ! function_exists( 'litespeed_finish_request' ) ) {
		// Without a way to flush the response first, never make a visitor wait for a run.
		spawn_cron();
		return $result;
	}
	add_action( 'shutdown', function () {
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		} else {
			litespeed_finish_request();
		}
		kpopblog_run_news_collector( 'fallback' );
	} );
	return $result;
}
add_filter( 'rest_pre_dispatch', 'kpopblog_collector_fallback_trigger', 10, 3 );

function kpopblog_get_collector_health_check() {
	$settings = kpopblog_get_collector_settings();
	$url = admin_url( 'admin.php?page=kpopblog-collector' );
	if ( empty( $settings['enabled'] ) ) {
		return kpopblog_health_check( 'collector', 'warning', 'News collector', 'Automatic K-pop news collection is turned off.', $url );
	}
	$last = (string) get_option( 'kpopblog_collector_last_success', '' );
	$error = get_option( 'kpopblog_collector_last_error' );
	if ( '' === $last ) {
		return kpopblog_health_check( 'collector', 'warning', 'News collector', 'Collection is scheduled but has not completed a run yet.', $url );
	}
	if ( strtotime( $last ) < time() - 2 * DAY_IN_SECONDS ) {
		return kpopblog_health_check( 'collector', 'critical', 'News collector', 'No successful collection in the last 48 hours' . ( is_array( $error ) ? ': ' . $error['message'] : '.' ), $url );
	}
	return kpopblog_health_check( 'collector', 'good', 'News collector', 'Last successful collection: ' . $last . '.', $url );
}
