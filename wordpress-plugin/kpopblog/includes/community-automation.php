<?php
/**
 * Community automation: newsroom discussion threads for fresh headlines and a
 * weekly fan poll built from the artists in the news. Everything is posted
 * under the "KpopBlog Newsroom" byline and flagged as official so it is never
 * mistaken for member content.
 *
 * @package KpopBlog
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Open a News Reactions thread for the most relevant new articles.
 *
 * @param array $published Items published by the collector in this run (with post_id).
 * @return int Threads created.
 */
function kpopblog_create_news_discussion_threads( array $published, $limit ) {
	if ( $limit < 1 || ! $published ) { return 0; }
	$term = get_term_by( 'slug', 'news-reactions', 'kb_forum_category' );
	if ( ! $term ) { return 0; }

	$tagged = array_values( array_filter( $published, function ( $item ) {
		return ! empty( $item['artists'] ) && ! empty( $item['post_id'] );
	} ) );
	$created = 0;
	foreach ( $tagged as $item ) {
		if ( $created >= $limit ) { break; }
		$dedupe = hash( 'sha256', 'thread|' . $item['post_id'] );
		if ( kpopblog_collector_seen( $dedupe ) ) { continue; }

		$article_url = home_url( '/news/' . get_post_field( 'post_name', $item['post_id'] ) );
		$body = implode( "\n\n", array(
			$item['summary'],
			'Read the brief: ' . $article_url,
			'Original report: ' . $item['publisher'] . ' — ' . $item['url'],
			'What do you think? Share your reaction, keep it respectful, and label anything unconfirmed as speculation.',
		) );
		$thread_id = wp_insert_post( wp_slash( array(
			'post_type'      => 'kb_thread',
			'post_status'    => 'publish',
			'post_title'     => $item['title'],
			'post_content'   => $body,
			'post_author'    => kpopblog_newsroom_user_id(),
			'comment_status' => 'open',
		) ), true );
		if ( is_wp_error( $thread_id ) ) { continue; }
		wp_set_object_terms( $thread_id, (int) $term->term_id, 'kb_forum_category' );
		update_post_meta( $thread_id, 'kb_category_slug', 'news-reactions' );
		$headline_artists = kpopblog_match_artists( $item['title'] );
		$flair_names = $headline_artists ? kpopblog_artist_names_for_slugs( array( $headline_artists[0] ) ) : array();
		update_post_meta( $thread_id, 'kb_flair', $flair_names ? $flair_names[0] : ( 'News' === $item['category'] ? 'News' : $item['category'] ) );
		update_post_meta( $thread_id, 'kb_language', 'en' );
		update_post_meta( $thread_id, 'kb_official', true );
		update_post_meta( $thread_id, 'kb_related_artist_slugs', $item['artists'] );
		update_post_meta( $thread_id, 'kb_article_id', (int) $item['post_id'] );
		update_post_meta( $item['post_id'], 'kb_discussion_thread_id', (int) $thread_id );
		kpopblog_collector_remember( $dedupe, 'thread', $thread_id, $item['url'] );
		$created++;
	}
	return $created;
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

	$week = wp_date( 'M j' );
	$poll_id = wp_insert_post( wp_slash( array(
		'post_type'    => 'kb_poll',
		'post_status'  => 'publish',
		'post_title'   => 'Fan poll: whose news are you following most this week? (' . $week . ')',
		'post_name'    => 'weekly-fan-poll-' . gmdate( 'Y-m-d' ),
		'post_content' => 'These artists made the most headlines on KpopBlog over the past seven days. Vote for the story you are following most closely.',
		'post_author'  => kpopblog_newsroom_user_id(),
	) ), true );
	if ( is_wp_error( $poll_id ) ) { return 0; }
	update_post_meta( $poll_id, 'kb_options', $options );
	update_post_meta( $poll_id, 'kb_ends_at', gmdate( 'c', time() + 7 * DAY_IN_SECONDS ) );
	update_post_meta( $poll_id, 'kb_weekly_poll', '1' );
	return 1;
}

/**
 * Collector hook.
 *
 * @return array{threads:int,polls:int}
 */
function kpopblog_community_automation_after_collect( array $published, array $settings ) {
	$threads = kpopblog_create_news_discussion_threads( $published, (int) $settings['discussion_threads'] );
	$polls   = ! empty( $settings['weekly_poll'] ) ? kpopblog_maybe_create_weekly_poll() : 0;
	return array( 'threads' => $threads, 'polls' => $polls );
}
