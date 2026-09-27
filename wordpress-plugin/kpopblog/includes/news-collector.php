<?php
/**
 * Keyless K-pop news collector.
 *
 * Reads publisher RSS feeds (a general K-pop feed plus one tag feed per
 * artist, rotated across runs), keeps K-pop items, tags them with catalogue
 * artists, and publishes short attributed briefs that link to the original
 * story. Official label YouTube feeds fill the video section, comeback
 * announcements with explicit dates fill the release calendar, and
 * community-automation.php opens discussion threads and a weekly poll.
 *
 * No API key is required. When an OpenAI key is configured and AI briefs are
 * enabled, each brief is rewritten from the feed summary only.
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
		'artist_feeds_per_run' => 6,
		'max_age_days'         => 4,
		'fetch_images'         => 1,
		'collect_videos'       => 1,
		'extract_comebacks'    => 1,
		'discussion_threads'   => 2,
		'weekly_poll'          => 1,
		'ai_rewrite'           => 0,
		'artist_feed_template' => 'https://www.soompi.com/tag/%s/feed',
		'general_feeds'        => implode( "\n", array(
			'https://www.soompi.com/feed',
			'https://www.koreatimes.co.kr/www/rss/entertainment.xml',
			'https://en.yna.co.kr/RSS/culture.xml',
		) ),
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

	return array(
		'enabled'              => $flag( 'enabled' ),
		'frequency'            => array_key_exists( $frequency, kpopblog_collector_frequencies() ) ? $frequency : $defaults['frequency'],
		'max_posts'            => max( 1, min( 30, isset( $input['max_posts'] ) ? (int) $input['max_posts'] : $defaults['max_posts'] ) ),
		'artist_feeds_per_run' => max( 0, min( 40, isset( $input['artist_feeds_per_run'] ) ? (int) $input['artist_feeds_per_run'] : $defaults['artist_feeds_per_run'] ) ),
		'max_age_days'         => max( 1, min( 14, isset( $input['max_age_days'] ) ? (int) $input['max_age_days'] : $defaults['max_age_days'] ) ),
		'fetch_images'         => $flag( 'fetch_images' ),
		'collect_videos'       => $flag( 'collect_videos' ),
		'extract_comebacks'    => $flag( 'extract_comebacks' ),
		'discussion_threads'   => max( 0, min( 10, isset( $input['discussion_threads'] ) ? (int) $input['discussion_threads'] : $defaults['discussion_threads'] ) ),
		'weekly_poll'          => $flag( 'weekly_poll' ),
		'ai_rewrite'           => $flag( 'ai_rewrite' ),
		'artist_feed_template' => esc_url_raw( $template, array( 'https' ) ) ? $template : '',
		'general_feeds'        => kpopblog_sanitize_url_lines( isset( $input['general_feeds'] ) ? $input['general_feeds'] : '' ),
		'video_channels'       => implode( "\n", array_slice( $channels, 0, 20 ) ),
	);
}

function kpopblog_get_collector_settings() {
	$saved = get_option( KPOPBLOG_COLLECTOR_OPTION, null );
	if ( ! is_array( $saved ) ) { return kpopblog_collector_defaults(); }
	return kpopblog_sanitize_collector_settings( array_merge( kpopblog_collector_defaults(), $saved ) );
}

/* ---------- text helpers ---------- */

function kpopblog_collector_clean_summary( $html, $max_chars = 420 ) {
	$html = preg_replace( '#<a[^>]*class="more-link"[^>]*>.*?</a>#is', '', (string) $html );
	$html = preg_replace( '#<p>\s*The post .*? appeared first on .*?</p>#is', '', $html );
	$text = trim( preg_replace( '/\s+/u', ' ', kpopblog_decode_text_entities( $html ) ) );
	$text = preg_replace( '/\s*(\[(…|\.\.\.)\]|…|\.\.\.)\s*(Continue reading.*)?$/u', '…', $text );
	$text = preg_replace( '/\s*Continue reading.*$/u', '', $text );
	// Drop pointers to media that only exists on the source page ("Check out the teaser below!").
	$text = trim( preg_replace( '/(^|(?<=[.!?…]))\s*[^.!?…]*\b(below|above)\b[^.!?…]*[.!?]/iu', '', $text ) );
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $text, 'UTF-8' ) > $max_chars ) {
		$cut = mb_substr( $text, 0, $max_chars, 'UTF-8' );
		$stop = max( mb_strrpos( $cut, '. ', 0, 'UTF-8' ), mb_strrpos( $cut, '! ', 0, 'UTF-8' ), mb_strrpos( $cut, '? ', 0, 'UTF-8' ) );
		$text = $stop > 120 ? mb_substr( $cut, 0, $stop + 1, 'UTF-8' ) : rtrim( $cut ) . '…';
	}
	return $text;
}

function kpopblog_collector_publisher_from_url( $url ) {
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$host = preg_replace( '/^(www|en|m)\./', '', $host );
	$known = array(
		'soompi.com'      => 'Soompi',
		'koreatimes.co.kr'=> 'The Korea Times',
		'yna.co.kr'       => 'Yonhap News Agency',
		'koreaherald.com' => 'The Korea Herald',
		'koreaboo.com'    => 'Koreaboo',
		'billboard.com'   => 'Billboard',
		'youtube.com'     => 'YouTube',
	);
	return isset( $known[ $host ] ) ? $known[ $host ] : $host;
}

function kpopblog_collector_normalize_url( $url ) {
	$url = esc_url_raw( trim( (string) $url ), array( 'https', 'http' ) );
	if ( '' === $url ) { return ''; }
	$url = preg_replace( '/^http:/i', 'https:', $url );
	$url = remove_query_arg( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'oc' ), $url );
	return untrailingslashit( $url );
}

/** Keyword gate for feeds that mix K-pop with film, drama, and general culture news. */
function kpopblog_collector_is_kpop( $title, $summary, array $categories, array $artists ) {
	if ( preg_match( '/^\s*quiz\b/i', $title ) ) { return false; }
	$cats = strtolower( implode( '|', $categories ) );
	$text = $title . ' ' . $summary;
	$music = preg_match( '/k-?pop|idol|girl group|boy group|comeback|debut|mini album|\balbum\b|\bEP\b|single|music video|\bM\/?V\b|music show|inkigayo|music bank|m countdown|show champion|fan ?meeting|fan-?con|world tour|concert|billboard|melon|circle chart|hanteo|choreograph|trainee|audition/i', $text );
	$screen = preg_match( '/k-?drama|drama|film|movie|box office|actor|actress|series|variety show|webtoon/i', $title ) || false !== strpos( $cats, 'tv/film' ) || false !== strpos( $cats, 'drama' );
	if ( $artists ) {
		// An artist named in the headline is always relevant; body-only mentions need music context.
		return true;
	}
	if ( false !== strpos( $cats, 'music' ) || false !== strpos( $cats, 'kpop' ) || false !== strpos( $cats, 'k-pop' ) ) {
		return ! $screen || (bool) $music;
	}
	return (bool) $music && ! $screen;
}

function kpopblog_collector_category( $title ) {
	if ( preg_match( '/\baward|\bwins?\b|\btrophy|takes? (home )?(1st|first)|daesang|\bMAMA\b|golden disc|music show win/i', $title ) ) { return 'Awards'; }
	if ( preg_match( '/chart|billboard|\bNo\. ?1\b|number one|melon|circle|hanteo|oricon|spotify|streams|sales|brand reputation|record|million/i', $title ) ) { return 'Charts'; }
	if ( preg_match( '/comeback|teaser|pre-release|track ?list|\balbum|\bEP\b|single|\bM\/?V\b|music video|release|debut|drops?\b/i', $title ) ) { return 'Comeback'; }
	if ( preg_match( '/concert|tour|fan ?meeting|fan-?con|festival|stadium|encore|lineup|showcase|headline/i', $title ) ) { return 'Tour'; }
	return 'News';
}

/* ---------- fetching ---------- */

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
	$feed = fetch_feed( $url );
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
		$media_tags = $item->get_item_tags( 'http://search.yahoo.com/mrss/', 'group' );
		$media_description = '';
		if ( ! empty( $media_tags[0]['child']['http://search.yahoo.com/mrss/']['description'][0]['data'] ) ) {
			$media_description = (string) $media_tags[0]['child']['http://search.yahoo.com/mrss/']['description'][0]['data'];
		}
		$items[] = array(
			'id'          => (string) $item->get_id(),
			'title'       => trim( kpopblog_decode_text_entities( (string) $item->get_title() ) ),
			'url'         => (string) $item->get_permalink(),
			'summary'     => (string) $item->get_description(),
			'media_text'  => $media_description,
			'timestamp'   => (int) $item->get_date( 'U' ),
			'categories'  => $categories,
			'image'       => (string) $image,
		);
	}
	return $items;
}

/** Read the source page's social preview image (og:image). */
function kpopblog_collector_preview_image( $url ) {
	$response = wp_safe_remote_get( $url, array(
		'timeout'             => 8,
		'redirection'         => 3,
		'limit_response_size' => 300 * KB_IN_BYTES,
		'user-agent'          => 'Mozilla/5.0 (compatible; KpopBlogBot/1.0; +' . home_url( '/' ) . ')',
	) );
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) { return ''; }
	$html = (string) wp_remote_retrieve_body( $response );
	foreach ( array( 'og:image', 'twitter:image' ) as $property ) {
		if ( preg_match( '/<meta[^>]+(?:property|name)=["\']' . preg_quote( $property, '/' ) . '["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $match )
			|| preg_match( '/<meta[^>]+content=["\']([^"\']+)["\'][^>]*(?:property|name)=["\']' . preg_quote( $property, '/' ) . '["\']/i', $html, $match ) ) {
			$image = esc_url_raw( html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' ), array( 'https' ) );
			if ( '' !== $image ) { return $image; }
		}
	}
	return '';
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

/* ---------- optional AI brief ---------- */

/**
 * Rewrite a feed summary into an original brief. Returns '' on any failure so
 * the attributed feed summary is used instead.
 */
function kpopblog_collector_ai_brief( array $item ) {
	if ( ! function_exists( 'kpopblog_get_openai_api_key' ) || ! kpopblog_has_openai_api_key() ) { return ''; }
	$settings = function_exists( 'kpopblog_get_automation_settings' ) ? kpopblog_get_automation_settings() : array( 'model' => 'gpt-5.6-luna' );
	$prompt = implode( "\n", array(
		'Write an original English news brief of 90 to 160 words for a K-pop fan site.',
		'Use ONLY the facts in the headline and summary below. Do not add names, numbers, dates, quotes, or claims that are not present.',
		'Do not copy sentences verbatim. Plain paragraphs only, no headings, no markdown, no links.',
		'Headline: ' . $item['title'],
		'Summary: ' . $item['summary'],
		'Publisher: ' . $item['publisher'],
	) );
	$response = wp_remote_post( 'https://api.openai.com/v1/responses', array(
		'timeout' => 45,
		'headers' => array( 'Authorization' => 'Bearer ' . kpopblog_get_openai_api_key(), 'Content-Type' => 'application/json' ),
		'body'    => wp_json_encode( array(
			'model'             => $settings['model'],
			'store'             => false,
			'input'             => $prompt,
			'max_output_tokens' => 700,
		) ),
		'data_format' => 'body',
	) );
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) { return ''; }
	$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $decoded ) || ! function_exists( 'kpopblog_extract_openai_output_text' ) ) { return ''; }
	$text = kpopblog_extract_openai_output_text( $decoded );
	if ( is_wp_error( $text ) ) { return ''; }
	$text = trim( sanitize_textarea_field( $text ) );
	return strlen( $text ) >= 200 ? $text : '';
}

/* ---------- publishing ---------- */

function kpopblog_collector_article_html( array $item, $body_text ) {
	$html = '';
	foreach ( preg_split( '/\n{2,}|\r\n\r\n/', $body_text ) as $paragraph ) {
		$paragraph = trim( $paragraph );
		if ( '' !== $paragraph ) { $html .= '<p>' . esc_html( $paragraph ) . '</p>'; }
	}
	$link = esc_url( $item['url'] );
	$publisher = esc_html( $item['publisher'] );
	$html .= '<p class="kb-source-credit"><strong>Source:</strong> <a href="' . $link . '" rel="noopener noreferrer nofollow" target="_blank">' . $publisher . '</a>';
	if ( $item['timestamp'] > 0 ) {
		$html .= ' · ' . esc_html( gmdate( 'M j, Y', $item['timestamp'] ) );
	}
	$html .= '</p><p><a class="kb-read-more" href="' . $link . '" rel="noopener noreferrer nofollow" target="_blank">Read the full story on ' . $publisher . ' →</a></p>';
	return $html;
}

/**
 * @return int|WP_Error New post ID.
 */
function kpopblog_collector_publish_article( array $item, array $settings ) {
	$body = '';
	if ( ! empty( $settings['ai_rewrite'] ) ) {
		$body = kpopblog_collector_ai_brief( $item );
	}
	$ai_written = '' !== $body;
	if ( ! $ai_written ) { $body = $item['summary']; }

	$timestamp = $item['timestamp'] > 0 && $item['timestamp'] <= time() ? $item['timestamp'] : time();
	$post_id = wp_insert_post( wp_slash( array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'post_title'     => $item['title'],
		'post_excerpt'   => $item['summary'],
		'post_content'   => kpopblog_collector_article_html( $item, $body ),
		'post_author'    => kpopblog_newsroom_user_id(),
		'post_date_gmt'  => gmdate( 'Y-m-d H:i:s', $timestamp ),
		'post_date'      => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp ) ),
		'comment_status' => 'open',
	) ), true );
	if ( is_wp_error( $post_id ) ) { return $post_id; }

	$meta = array(
		'kb_category_slug'        => $item['category'],
		'kb_language'             => 'en',
		'kb_source'               => $ai_written ? 'ai-brief' : 'aggregated',
		'kb_source_url'           => $item['url'],
		'kb_source_title'         => $item['title'],
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
 * Feeds for this run: every general feed plus the next artist tag feeds in rotation.
 *
 * @return array<int,array{url:string,artist:string}>
 */
function kpopblog_collector_feed_plan( array $settings, $all_artists = false ) {
	$plan = array();
	foreach ( array_filter( preg_split( '/[\r\n]+/', (string) $settings['general_feeds'] ) ) as $url ) {
		$plan[] = array( 'url' => trim( $url ), 'artist' => '' );
	}
	if ( '' === $settings['artist_feed_template'] ) { return $plan; }

	$tagged = array_values( array_filter( kpopblog_get_artist_matchers(), function ( $matcher ) {
		return '' !== $matcher['tag'];
	} ) );
	$count = count( $tagged );
	if ( ! $count ) { return $plan; }
	$take = $all_artists ? $count : min( $count, (int) $settings['artist_feeds_per_run'] );
	$cursor = (int) get_option( 'kpopblog_collector_cursor', 0 ) % $count;
	for ( $i = 0; $i < $take; $i++ ) {
		$matcher = $tagged[ ( $cursor + $i ) % $count ];
		$plan[] = array( 'url' => sprintf( $settings['artist_feed_template'], rawurlencode( $matcher['tag'] ) ), 'artist' => $matcher['slug'] );
	}
	update_option( 'kpopblog_collector_cursor', ( $cursor + $take ) % $count, false );
	return $plan;
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
	$deadline  = $started + ( $backfill ? 150 : 75 );
	$max_posts = $backfill ? max( 30, (int) $settings['max_posts'] ) : (int) $settings['max_posts'];
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
	$extra  = array( 'videos' => 0, 'comebacks' => 0, 'threads' => 0, 'polls' => 0, 'feeds' => 0 );
	$log    = array();
	$candidates = array();
	$seen_urls  = array();
	$seen_titles = array();

	try {
		foreach ( kpopblog_collector_feed_plan( $settings, $backfill ) as $feed ) {
			if ( time() > $deadline - 25 ) { $log[] = 'Time budget reached before ' . $feed['url']; break; }
			$items = kpopblog_collector_fetch_feed( $feed['url'], 60 );
			if ( is_wp_error( $items ) ) { $log[] = $feed['url'] . ': ' . $items->get_error_message(); continue; }
			$extra['feeds']++;
			$publisher = kpopblog_collector_publisher_from_url( $feed['url'] );
			foreach ( $items as $raw ) {
				$url = kpopblog_collector_normalize_url( $raw['url'] );
				if ( '' === $url || '' === $raw['title'] || isset( $seen_urls[ $url ] ) ) { continue; }
				if ( $raw['timestamp'] && $raw['timestamp'] < $max_age ) { continue; }
				$seen_urls[ $url ] = true;
				$title_key = strtolower( preg_replace( '/[^a-z0-9]+/i', '', $raw['title'] ) );
				if ( isset( $seen_titles[ $title_key ] ) ) { continue; }
				$seen_titles[ $title_key ] = true;

				$summary = kpopblog_collector_clean_summary( $raw['summary'] );
				$artists = kpopblog_match_artists( $raw['title'], $summary, $raw['categories'] );
				if ( '' !== $feed['artist'] && ! in_array( $feed['artist'], $artists, true ) ) {
					$artists[] = $feed['artist'];
				}
				$counts['discovered']++;
				if ( ! kpopblog_collector_is_kpop( $raw['title'], $summary, $raw['categories'], kpopblog_match_artists( $raw['title'] ) ?: ( '' !== $feed['artist'] ? array( $feed['artist'] ) : array() ) ) ) {
					continue;
				}
				if ( strlen( $summary ) < 60 ) { $counts['skipped']++; continue; }
				$dedupe = hash( 'sha256', 'rss|' . strtolower( $url ) );
				if ( kpopblog_collector_seen( $dedupe ) ) { continue; }

				$candidates[] = array(
					'dedupe'    => $dedupe,
					'title'     => substr( $raw['title'], 0, 200 ),
					'url'       => $url,
					'summary'   => $summary,
					'timestamp' => $raw['timestamp'],
					'artists'   => $artists,
					'image'     => esc_url_raw( $raw['image'], array( 'https' ) ),
					'publisher' => $publisher,
					'category'  => kpopblog_collector_category( $raw['title'] ),
					'feed_artist' => $feed['artist'],
				);
			}
		}

		// Newest first; artist-tagged stories win ties so artist pages fill up.
		usort( $candidates, function ( $a, $b ) {
			if ( $a['timestamp'] === $b['timestamp'] ) { return count( $b['artists'] ) - count( $a['artists'] ); }
			return $b['timestamp'] - $a['timestamp'];
		} );

		// Fair share first: a few stories per artist feed and from the general feeds,
		// then fill any remaining slots by recency.
		// Round-robin: every source gets its newest story in before any source gets a
		// second one; general feeds count as one source per round.
		$groups = array();
		foreach ( $candidates as $item ) {
			$groups[ '' === $item['feed_artist'] ? '_general' : $item['feed_artist'] ][] = $item;
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
		foreach ( $queue as $item ) {
			if ( count( $published ) >= $max_posts || time() > $deadline ) { break; }
			if ( ! empty( $settings['fetch_images'] ) && '' === $item['image'] && time() < $deadline - 10 ) {
				$item['image'] = kpopblog_collector_preview_image( $item['url'] );
			}
			$post_id = kpopblog_collector_publish_article( $item, $settings );
			if ( is_wp_error( $post_id ) ) { $counts['skipped']++; continue; }
			if ( ! kpopblog_collector_remember( $item['dedupe'], 'rss', $post_id, $item['url'], array( array( 'url' => $item['url'], 'title' => $item['title'], 'publisher' => $item['publisher'] ) ) ) ) {
				wp_delete_post( $post_id, true );
				$counts['skipped']++;
				continue;
			}
			$counts['created']++;
			$item['post_id'] = $post_id;
			$published[] = $item;
			if ( ! empty( $settings['extract_comebacks'] ) && kpopblog_collector_maybe_comeback( $item, $post_id ) ) {
				$extra['comebacks']++;
			}
		}

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
			'error_text'  => substr( wp_json_encode( array( 'extra' => $extra, 'log' => array_slice( $log, 0, 12 ) ) ), 0, 4000 ),
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
