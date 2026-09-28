<?php
/**
 * Community automation. Every post here is written by the "KpopBlog Newsroom"
 * account and flagged official, so readers can always tell editorial prompts
 * apart from member posts. Nothing impersonates members, and no counters are
 * inflated.
 *
 * - News threads: fresh headlines open a thread in the matching board
 *   (comebacks, concerts, fashion, albums and merch, or news reactions).
 * - Artist fan hubs: one hub per artist in Artist Fandoms, refreshed with the
 *   latest headlines and next release whenever that artist is in the news.
 * - Recurring threads: a daily news roundup, a weekly comeback watch, a weekly
 *   concert check-in, and a weekly fan-art prompt.
 * - Polls: a weekly "most-followed news" poll and a comeback-anticipation poll.
 *
 * @package KpopBlog
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Board (forum category) for a news story. */
function kpopblog_forum_category_for_item( array $item ) {
	$title = $item['title'];
	if ( preg_match( '/fashion|style|outfit|ambassador|runway|vogue|dior|chanel|gucci|louis vuitton|prada|celine|givenchy|airport|red carpet|photoshoot|pictorial|editorial|beauty|화보|패션|앰버서더|공항/iu', $title ) ) { return 'fashion'; }
	if ( preg_match( '/album sales|first-week|photocard|merch|pre-order|unboxing|lightstick|light stick|goods|vinyl|\bLP\b|굿즈|포토카드|초동/iu', $title ) ) { return 'albums-merch'; }
	$category = isset( $item['category'] ) ? $item['category'] : 'News';
	if ( 'Comeback' === $category ) { return 'comebacks'; }
	if ( 'Tour' === $category ) { return 'concerts'; }
	return 'news-reactions';
}

function kpopblog_forum_category_flair( $board ) {
	$flairs = array( 'comebacks' => 'Comeback', 'concerts' => 'Concert', 'fashion' => 'Style', 'albums-merch' => 'Merch', 'news-reactions' => 'News' );
	return isset( $flairs[ $board ] ) ? $flairs[ $board ] : 'News';
}

/**
 * Create a newsroom thread.
 *
 * @return int Thread ID or 0.
 */
function kpopblog_newsroom_thread( $board, $title, $body, $flair, array $artists = array(), array $meta = array() ) {
	$term = get_term_by( 'slug', $board, 'kb_forum_category' );
	if ( ! $term ) { return 0; }
	$thread_id = wp_insert_post( wp_slash( array(
		'post_type'      => 'kb_thread',
		'post_status'    => 'publish',
		'post_title'     => $title,
		'post_content'   => $body,
		'post_author'    => kpopblog_newsroom_user_id(),
		'comment_status' => 'open',
	) ), true );
	if ( is_wp_error( $thread_id ) ) { return 0; }
	wp_set_object_terms( $thread_id, (int) $term->term_id, 'kb_forum_category' );
	update_post_meta( $thread_id, 'kb_category_slug', $board );
	update_post_meta( $thread_id, 'kb_flair', $flair );
	update_post_meta( $thread_id, 'kb_language', 'en' );
	update_post_meta( $thread_id, 'kb_official', true );
	if ( $artists ) { update_post_meta( $thread_id, 'kb_related_artist_slugs', array_values( $artists ) ); }
	foreach ( $meta as $key => $value ) { update_post_meta( $thread_id, $key, $value ); }
	return (int) $thread_id;
}

function kpopblog_article_url( $post_id ) {
	return home_url( '/news/' . get_post_field( 'post_name', $post_id ) );
}

/**
 * Open threads for the most discussable new stories, spreading them across
 * boards so every topic stays active.
 *
 * @param array $published Items published by the collector in this run (with post_id).
 * @return int Threads created.
 */
function kpopblog_create_news_discussion_threads( array $published, $limit ) {
	if ( $limit < 1 || ! $published ) { return 0; }
	$by_board = array();
	foreach ( $published as $item ) {
		if ( empty( $item['post_id'] ) || empty( $item['artists'] ) ) { continue; }
		$by_board[ kpopblog_forum_category_for_item( $item ) ][] = $item;
	}
	// One story per board per round, so a busy topic doesn't crowd out the others.
	$ordered = array();
	for ( $round = 0; count( $ordered ) < $limit; $round++ ) {
		$added = false;
		foreach ( $by_board as $board => $items ) {
			if ( isset( $items[ $round ] ) ) { $ordered[] = array( $board, $items[ $round ] ); $added = true; }
		}
		if ( ! $added ) { break; }
	}

	$created = 0;
	foreach ( $ordered as $pair ) {
		if ( $created >= $limit ) { break; }
		list( $board, $item ) = $pair;
		$dedupe = hash( 'sha256', 'thread|' . $item['post_id'] );
		if ( kpopblog_collector_seen( $dedupe ) ) { continue; }
		$headline_artists = kpopblog_match_artists( $item['title'] );
		$names = kpopblog_artist_names_for_slugs( $headline_artists ? array( $headline_artists[0] ) : array() );
		$parts = array(
			$item['summary'],
			'Full story on KpopBlog: ' . kpopblog_article_url( $item['post_id'] ),
			'Source: ' . $item['publisher'],
		);
		if ( ! empty( $item['ai_questions'] ) ) {
			$parts[] = "Talking points:\n" . implode( "\n", array_map( function ( $question ) { return '• ' . $question; }, $item['ai_questions'] ) );
		}
		$parts[] = 'What do you think? Share your reaction, keep it respectful, and label anything unconfirmed as speculation.';
		$body = implode( "\n\n", $parts );
		$thread_id = kpopblog_newsroom_thread( $board, $item['title'], $body, $names ? $names[0] : kpopblog_forum_category_flair( $board ), $item['artists'], array( 'kb_article_id' => (int) $item['post_id'] ) );
		if ( ! $thread_id ) { continue; }
		update_post_meta( $item['post_id'], 'kb_discussion_thread_id', $thread_id );
		kpopblog_collector_remember( $dedupe, 'thread', $thread_id, $item['url'] );
		$created++;
	}
	return $created;
}

/* ---------- artist fan hubs ---------- */

/**
 * Create or refresh the fan hub thread for each artist in the news: latest
 * headlines, the next release, and links to the artist page.
 *
 * @return int Hubs created.
 */
function kpopblog_refresh_artist_hubs( array $published ) {
	$artists = array();
	foreach ( $published as $item ) {
		foreach ( array_slice( (array) $item['artists'], 0, 2 ) as $slug ) { $artists[ $slug ] = true; }
	}
	$created = 0;
	foreach ( array_keys( $artists ) as $slug ) {
		$names = kpopblog_artist_names_for_slugs( array( $slug ) );
		if ( ! $names ) { continue; }
		$name = $names[0];
		$news = get_posts( array(
			'post_type'   => 'post',
			'post_status' => 'publish',
			'numberposts' => 5,
			'meta_query'  => array( array( 'key' => 'kb_related_artist_slugs', 'value' => '"' . $slug . '"', 'compare' => 'LIKE' ) ),
		) );
		$lines = array( 'The ' . $name . ' fan hub on KpopBlog: news, comebacks, and conversation in one place. Say hi, share favorite stages, and discuss the latest headlines below.', 'Latest ' . $name . ' news:' );
		foreach ( $news as $post ) {
			$lines[] = '• ' . kpopblog_decode_text_entities( get_the_title( $post ) ) . ' — ' . kpopblog_article_url( $post->ID );
		}
		$next = get_posts( array(
			'post_type'   => 'kb_comeback',
			'post_status' => 'publish',
			'numberposts' => 1,
			'meta_key'    => 'kb_release_at',
			'orderby'     => 'meta_value',
			'order'       => 'ASC',
			'meta_query'  => array( 'relation' => 'AND', array( 'key' => 'kb_artist_slug', 'value' => $slug ), array( 'key' => 'kb_release_at', 'value' => gmdate( 'Y-m-d' ), 'compare' => '>=' ) ),
		) );
		if ( $next ) {
			$release = strtotime( (string) get_post_meta( $next[0]->ID, 'kb_release_at', true ) );
			$lines[] = 'Next release: ' . get_the_title( $next[0] ) . ( $release ? ' (' . gmdate( 'M j, Y', $release ) . ')' : '' ) . ' — ' . home_url( '/comebacks' );
		}
		$lines[] = 'Artist profile and members: ' . home_url( '/artist/' . $slug );
		$body = implode( "\n\n", array_slice( $lines, 0, 2 ) ) . "\n" . implode( "\n", array_slice( $lines, 2 ) );

		$hub = get_posts( array( 'post_type' => 'kb_thread', 'post_status' => 'publish', 'numberposts' => 1, 'meta_key' => 'kb_artist_hub', 'meta_value' => $slug ) );
		if ( $hub ) {
			// Refreshing the body also moves the hub's last activity to now.
			wp_update_post( wp_slash( array( 'ID' => $hub[0]->ID, 'post_content' => $body ) ) );
			continue;
		}
		$thread_id = kpopblog_newsroom_thread( 'fandoms', $name . ' fan hub: news, comebacks and discussion', $body, $name, array( $slug ), array( 'kb_artist_hub' => $slug ) );
		if ( $thread_id ) { $created++; }
	}
	return $created;
}

/* ---------- recurring threads ---------- */

/** Local date for editorial scheduling (site timezone). */
function kpopblog_editorial_date( $format = 'Y-m-d', $offset_days = 0 ) {
	return kpopblog_en_date( $format, time() + $offset_days * DAY_IN_SECONDS );
}

/**
 * Date in the site timezone with English month and day names. wp_date() follows
 * the admin locale (e.g. "9월 28" on a Korean install), but the site publishes in English.
 */
function kpopblog_en_date( $format, $timestamp = null ) {
	$date = new DateTimeImmutable( '@' . ( null === $timestamp ? time() : (int) $timestamp ) );
	return $date->setTimezone( wp_timezone() )->format( $format );
}

/** Run $callback once per $period key; returns whatever it returns (or 0 when already done). */
function kpopblog_once( $option, $period, $callback ) {
	if ( get_option( $option ) === $period ) { return 0; }
	$created = (int) call_user_func( $callback ) > 0 ? 1 : 0;
	if ( $created ) { update_option( $option, $period, false ); }
	return $created;
}

/** Daily roundup of yesterday's collected stories in General. */
function kpopblog_daily_roundup_thread() {
	$day = kpopblog_editorial_date( 'Y-m-d', -1 );
	return kpopblog_once( 'kpopblog_roundup_day', $day, function () use ( $day ) {
		$start = new DateTimeImmutable( $day . ' 00:00:00', wp_timezone() );
		$posts = get_posts( array(
			'post_type'   => 'post',
			'post_status' => 'publish',
			'numberposts' => 12,
			'date_query'  => array( array(
				'after'     => $start->format( 'Y-m-d H:i:s' ),
				'before'    => $start->modify( '+1 day' )->format( 'Y-m-d H:i:s' ),
				'inclusive' => true,
			) ),
		) );
		if ( count( $posts ) < 3 ) { return 0; }
		$label = kpopblog_en_date( 'F j', $start->getTimestamp() );
		$lines = array( 'Here is what happened in K-pop on ' . $label . '. Which story matters most to you?', '' );
		foreach ( $posts as $post ) {
			$lines[] = '• ' . kpopblog_decode_text_entities( get_the_title( $post ) ) . ' — ' . kpopblog_article_url( $post->ID );
		}
		$lines[] = '';
		$lines[] = 'Missed something? Add links to official announcements in the replies.';
		return kpopblog_newsroom_thread( 'general', 'K-pop news roundup: ' . $label, implode( "\n", $lines ), 'Daily roundup', array(), array( 'kb_recurring' => 'daily-roundup' ) );
	} );
}

/** Weekly comeback watch in Comebacks and Debuts (Mondays). */
function kpopblog_weekly_comeback_thread() {
	if ( '1' !== kpopblog_editorial_date( 'N' ) ) { return 0; }
	return kpopblog_once( 'kpopblog_comeback_watch_week', kpopblog_editorial_date( 'o-W' ), function () {
		$upcoming = get_posts( array(
			'post_type'   => 'kb_comeback',
			'post_status' => 'publish',
			'numberposts' => 12,
			'meta_key'    => 'kb_release_at',
			'orderby'     => 'meta_value',
			'order'       => 'ASC',
			'meta_query'  => array( array( 'key' => 'kb_release_at', 'value' => gmdate( 'Y-m-d' ), 'compare' => '>=' ) ),
		) );
		if ( ! $upcoming ) { return 0; }
		$lines = array( 'Releases on the KpopBlog calendar for the coming weeks. Which one are you counting down to?', '' );
		foreach ( $upcoming as $comeback ) {
			$release = strtotime( (string) get_post_meta( $comeback->ID, 'kb_release_at', true ) );
			$lines[] = '• ' . ( $release ? kpopblog_en_date( 'M j', $release ) . ' — ' : '' ) . get_the_title( $comeback );
		}
		$lines[] = '';
		$lines[] = 'Full calendar: ' . home_url( '/comebacks' );
		return kpopblog_newsroom_thread( 'comebacks', 'Comeback watch: week of ' . kpopblog_editorial_date( 'F j' ), implode( "\n", $lines ), 'Weekly', array(), array( 'kb_recurring' => 'comeback-watch' ) );
	} );
}

/** Weekly concert check-in (Wednesdays). */
function kpopblog_weekly_concert_thread() {
	if ( '3' !== kpopblog_editorial_date( 'N' ) ) { return 0; }
	return kpopblog_once( 'kpopblog_concert_week', kpopblog_editorial_date( 'o-W' ), function () {
		$tours = get_posts( array(
			'post_type'   => 'post',
			'post_status' => 'publish',
			'numberposts' => 8,
			'date_query'  => array( array( 'after' => '10 days ago' ) ),
			'meta_query'  => array( array( 'key' => 'kb_category_slug', 'value' => 'Tour' ) ),
		) );
		$lines = array( 'Going to a show, watching a livestream, or planning a trip? Share dates, venues, setlist wishes, and tips for fellow fans.' );
		if ( $tours ) {
			$lines[] = '';
			$lines[] = 'Recent tour and festival news:';
			foreach ( $tours as $post ) {
				$lines[] = '• ' . kpopblog_decode_text_entities( get_the_title( $post ) ) . ' — ' . kpopblog_article_url( $post->ID );
			}
		}
		return kpopblog_newsroom_thread( 'concerts', 'Concert check-in: week of ' . kpopblog_editorial_date( 'F j' ), implode( "\n", $lines ), 'Weekly', array(), array( 'kb_recurring' => 'concert-checkin' ) );
	} );
}

/** Weekly fan-art prompt (Fridays). */
function kpopblog_weekly_fan_art_thread() {
	if ( '5' !== kpopblog_editorial_date( 'N' ) ) { return 0; }
	$prompts = array(
		'Draw or design your favorite comeback concept of the year.',
		'Pick a lyric that stuck with you and turn it into artwork.',
		'Reimagine a stage outfit in your own style.',
		'Make a lock-screen wallpaper for your bias group.',
		'Create an album cover for a song you wish existed.',
		'Illustrate a fan-meeting or concert memory.',
		'Design a light stick for a group that inspires you.',
		'Turn a variety-show moment into a comic strip.',
		'Make a color palette inspired by a music video.',
		'Draw your group as characters from a different era.',
		'Create a poster for an imaginary world tour.',
		'Sketch a chibi version of a recent performance.',
	);
	$week = (int) kpopblog_editorial_date( 'W' );
	return kpopblog_once( 'kpopblog_fan_art_week', kpopblog_editorial_date( 'o-W' ), function () use ( $prompts, $week ) {
		$prompt = $prompts[ $week % count( $prompts ) ];
		$body = implode( "\n\n", array(
			'This week\'s prompt: ' . $prompt,
			'Post original work only, credit any references, and keep feedback kind and constructive. Tag the artists that inspired you.',
		) );
		return kpopblog_newsroom_thread( 'fan-art', 'Fan art Friday: ' . rtrim( $prompt, '.' ), $body, 'Weekly prompt', array(), array( 'kb_recurring' => 'fan-art' ) );
	} );
}

/* ---------- polls ---------- */

function kpopblog_create_poll( $slug, $title, $description, array $options, array $meta = array() ) {
	$poll_id = wp_insert_post( wp_slash( array(
		'post_type'    => 'kb_poll',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => $description,
		'post_author'  => kpopblog_newsroom_user_id(),
	) ), true );
	if ( is_wp_error( $poll_id ) ) { return 0; }
	update_post_meta( $poll_id, 'kb_options', $options );
	update_post_meta( $poll_id, 'kb_ends_at', gmdate( 'c', time() + 7 * DAY_IN_SECONDS ) );
	foreach ( $meta as $key => $value ) { update_post_meta( $poll_id, $key, $value ); }
	return (int) $poll_id;
}

/**
 * Keep one open weekly poll whose options are the artists with the most news
 * coverage over the last seven days.
 *
 * @return int Polls created (0 or 1).
 */
function kpopblog_maybe_create_weekly_poll() {
	$open = get_posts( array(
		'post_type'   => 'kb_poll',
		'post_status' => 'publish',
		'numberposts' => 1,
		'fields'      => 'ids',
		'meta_query'  => array( array( 'key' => 'kb_weekly_poll', 'value' => '1' ) ),
		'date_query'  => array( array( 'after' => '7 days ago' ) ),
	) );
	if ( $open ) { return 0; }

	$recent = get_posts( array(
		'post_type'   => 'post',
		'post_status' => 'publish',
		'numberposts' => 200,
		'fields'      => 'ids',
		'date_query'  => array( array( 'after' => '7 days ago' ) ),
		'meta_key'    => 'kb_related_artist_slugs',
	) );
	$tally = array();
	foreach ( $recent as $post_id ) {
		foreach ( (array) get_post_meta( $post_id, 'kb_related_artist_slugs', true ) as $slug ) {
			$slug = sanitize_title( (string) $slug );
			if ( '' !== $slug ) { $tally[ $slug ] = ( isset( $tally[ $slug ] ) ? $tally[ $slug ] : 0 ) + 1; }
		}
	}
	arsort( $tally );
	$options = array();
	foreach ( array_slice( array_keys( $tally ), 0, 5 ) as $slug ) {
		$names = kpopblog_artist_names_for_slugs( array( $slug ) );
		if ( $names ) { $options[] = array( 'id' => $slug, 'label' => $names[0], 'votes' => 0 ); }
	}
	if ( count( $options ) < 2 ) { return 0; }

	return kpopblog_create_poll(
		'weekly-fan-poll-' . gmdate( 'Y-m-d' ),
		'Fan poll: whose news are you following most this week? (' . kpopblog_en_date( 'M j' ) . ')',
		'These artists made the most headlines on KpopBlog over the past seven days. Vote for the story you are following most closely.',
		$options,
		array( 'kb_weekly_poll' => '1' )
	) ? 1 : 0;
}

/** Weekly "most anticipated release" poll when at least two releases are coming up. */
function kpopblog_maybe_create_comeback_poll() {
	return kpopblog_once( 'kpopblog_comeback_poll_week', kpopblog_editorial_date( 'o-W' ), function () {
		$upcoming = get_posts( array(
			'post_type'   => 'kb_comeback',
			'post_status' => 'publish',
			'numberposts' => 5,
			'meta_key'    => 'kb_release_at',
			'orderby'     => 'meta_value',
			'order'       => 'ASC',
			'meta_query'  => array( array( 'key' => 'kb_release_at', 'value' => gmdate( 'Y-m-d' ), 'compare' => '>=' ) ),
		) );
		if ( count( $upcoming ) < 2 ) { return 0; }
		$options = array();
		foreach ( $upcoming as $comeback ) {
			$options[] = array( 'id' => 'release-' . $comeback->ID, 'label' => kpopblog_decode_text_entities( get_the_title( $comeback ) ), 'votes' => 0 );
		}
		return kpopblog_create_poll(
			'most-anticipated-release-' . gmdate( 'Y-m-d' ),
			'Which upcoming release are you most excited for? (' . kpopblog_en_date( 'M j' ) . ')',
			'Releases announced on the KpopBlog comeback calendar. Cast your vote and tell us why in the Comebacks board.',
			$options,
			array( 'kb_comeback_poll' => '1' )
		);
	} );
}

/**
 * Collector hook.
 *
 * @return array{threads:int,polls:int}
 */
function kpopblog_community_automation_after_collect( array $published, array $settings ) {
	$threads = kpopblog_create_news_discussion_threads( $published, (int) $settings['discussion_threads'] );
	if ( ! empty( $settings['community_hubs'] ) ) {
		$threads += kpopblog_refresh_artist_hubs( $published );
	}
	if ( ! empty( $settings['recurring_threads'] ) ) {
		$threads += kpopblog_daily_roundup_thread();
		$threads += kpopblog_weekly_comeback_thread();
		$threads += kpopblog_weekly_concert_thread();
		$threads += kpopblog_weekly_fan_art_thread();
	}
	$polls = 0;
	if ( ! empty( $settings['weekly_poll'] ) ) {
		$polls += kpopblog_maybe_create_weekly_poll();
		$polls += kpopblog_maybe_create_comeback_poll();
	}
	return array( 'threads' => $threads, 'polls' => $polls );
}
