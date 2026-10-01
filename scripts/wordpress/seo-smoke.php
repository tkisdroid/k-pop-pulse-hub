<?php
/** Run with wp eval-file on a disposable localhost WordPress database only. */
if ( ! defined( 'ABSPATH' ) || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Local disposable WordPress is required.' ); }
$ids = array();
$checks = 0;
$assert = function ( $condition, $message ) use ( &$checks ) { if ( ! $condition ) { throw new RuntimeException( $message ); } ++$checks; };
add_filter( 'pre_http_request', function ( $pre, $args, $url ) { return in_array( wp_parse_url( $url, PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ? $pre : new WP_Error( 'test_outbound_blocked', 'No external writes in SEO fixtures.' ); }, 10, 3 );
try {
	foreach ( array( 'post', 'kb_artist', 'kb_member', 'kb_video', 'kb_poll', 'kb_thread' ) as $type ) {
		$id = wp_insert_post( array( 'post_type' => $type, 'post_title' => 'SEO Fixture ' . $type, 'post_name' => 'seo-fixture-' . $type, 'post_status' => 'publish', 'post_content' => '<p>Verified fixture content with an accurate published date.</p>' ), true );
		$assert( ! is_wp_error( $id ), 'Fixture creation failed' );
		$ids[ $type ] = $id;
	}
	update_post_meta( $ids['kb_video'], 'kb_youtube_id', 'dQw4w9WgXcQ' );
	update_post_meta( $ids['kb_member'], 'kb_stage_name', 'Test Member' );
	update_post_meta( $ids['kb_member'], 'kb_group_slug', 'seo-fixture-kb_artist' );
	$paths = array( '/', '/latest', '/trending', '/artists', '/videos', '/charts', '/comebacks', '/polls', '/community', '/forum', '/about', '/contact', '/advertise', '/privacy', '/terms', '/copyright', '/corrections', '/community-guidelines', '/news/seo-fixture-post', '/artist/seo-fixture-kb_artist', '/member/seo-fixture-kb_member', '/watch/' . $ids['kb_video'], '/polls/seo-fixture-kb_poll', '/thread/seo-fixture-kb_thread' );
	foreach ( $paths as $path ) {
		$context = kpopblog_context_for_path( $path );
		$seo = kpopblog_seo_payload( $context );
		$assert( ! $seo['noindex'] && home_url( $path ) === $seo['canonical'] && '' !== $seo['description'], 'Missing indexable metadata: ' . $path );
		$GLOBALS['kpopblog_public_context'] = $context;
		$assert( false !== strpos( kpopblog_render_public_fallback(), '<h1' ), 'Missing no-JS content: ' . $path );
		$response = wp_remote_get( home_url( $path ), array( 'timeout' => 15 ) );
		$body = wp_remote_retrieve_body( $response );
		$assert( 200 === wp_remote_retrieve_response_code( $response ) && 1 === substr_count( $body, 'rel="canonical"' ) && 1 === substr_count( $body, 'name="description"' ), 'HTTP metadata regression: ' . $path );
		$assert( ! preg_match( '/(?:Warning|Fatal error|Deprecated):/', $body ), 'PHP runtime diagnostic: ' . $path );
	}
	foreach ( array( '/no-such-seo-page', '/news/no-such-post', '/artist/no-such-artist', '/member/no-such-member', '/watch/999999999', '/thread/no-such-thread', '/polls/no-such-poll' ) as $path ) {
		$response = wp_remote_get( home_url( $path ) );
		$assert( 404 === wp_remote_retrieve_response_code( $response ) && false !== strpos( wp_remote_retrieve_body( $response ), 'noindex' ), 'Soft 404: ' . $path );
	}
	foreach ( array( '/search', '/login', '/signup', '/profile/me', '/admin', '/newsletter' ) as $path ) { $assert( kpopblog_seo_payload( kpopblog_context_for_path( $path ) )['noindex'], 'Private/utility page indexed: ' . $path ); }
	wp_update_post( array( 'ID' => $ids['post'], 'post_password' => 'local-fixture-password' ) );
	$assert( kpopblog_seo_payload( kpopblog_context_for_path( '/news/seo-fixture-post' ) )['noindex'], 'Protected content leaked into SEO' );
	$assert( false === strpos( kpopblog_render_sitemap(), '/news/seo-fixture-post' ) && false === strpos( kpopblog_render_news_sitemap(), '/news/seo-fixture-post' ), 'Protected content in sitemap' );
	$assert( false === strpos( kpopblog_render_sitemap(), '/newsletter' ), 'Token page in sitemap' );
	$request = new WP_REST_Request( 'GET', '/kpopblog/v1/seo' ); $request->set_param( 'path', '/member/seo-fixture-kb_member' );
	$response = rest_do_request( $request );
	$assert( 200 === $response->get_status() && home_url( '/member/seo-fixture-kb_member' ) === $response->get_data()['canonical'], 'Public SEO API failed' );
	$request->set_param( 'path', 'https://example.com/' ); $assert( 400 === rest_do_request( $request )->get_status(), 'SEO API accepts external URLs' );
	$request->set_param( 'path', '/search?token=secret' ); $assert( 400 === rest_do_request( $request )->get_status(), 'SEO API accepts queries' );
	$video_ld = kpopblog_seo_payload( kpopblog_context_for_path( '/watch/' . $ids['kb_video'] ) )['json_ld']['@graph'][0];
	$assert( ! isset( $video_ld['contentUrl'] ) && isset( $video_ld['embedUrl'], $video_ld['uploadDate'] ), 'YouTube page misrepresented as media file' );
	echo 'SEO integration passed: ' . $checks . ' checks' . PHP_EOL;
} finally { foreach ( $ids as $id ) { wp_delete_post( $id, true ); } }
