<?php
/**
 * Custom Post Types — every content surface visible on thekpopblog.com is editable
 * from the WordPress admin. Articles re-use the built-in "post" type so editors
 * keep the standard authoring UX (Gutenberg, categories, tags, featured image).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function kpopblog_register_cpts() {
	$types = array(
		'kb_artist' => array(
			'singular' => 'Artist', 'plural' => 'Artists',
			'icon' => 'dashicons-star-filled', 'slug' => 'artists',
			'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		),
		'kb_member' => array(
			'singular' => 'Member', 'plural' => 'Members',
			'icon' => 'dashicons-groups', 'slug' => 'members',
			'supports' => array( 'title', 'editor', 'thumbnail' ),
		),
		'kb_comeback' => array(
			'singular' => 'Comeback', 'plural' => 'Comebacks',
			'icon' => 'dashicons-calendar-alt', 'slug' => 'comebacks',
			'supports' => array( 'title', 'editor', 'thumbnail' ),
		),
		'kb_chart' => array(
			'singular' => 'Chart', 'plural' => 'Charts',
			'icon' => 'dashicons-chart-bar', 'slug' => 'charts',
			'supports' => array( 'title', 'editor' ),
		),
		'kb_video' => array(
			'singular' => 'Video', 'plural' => 'Videos',
			'icon' => 'dashicons-video-alt3', 'slug' => 'videos',
			'supports' => array( 'title', 'editor', 'thumbnail', 'author', 'comments' ),
		),
		'kb_thread' => array(
			'singular' => 'Forum thread', 'plural' => 'Forum threads',
			'icon' => 'dashicons-format-chat', 'slug' => 'threads',
			'supports' => array( 'title', 'editor', 'author', 'comments', 'custom-fields' ),
		),
		'kb_community' => array(
			'singular' => 'Community post', 'plural' => 'Community posts',
			'icon' => 'dashicons-format-status', 'slug' => 'community',
			'supports' => array( 'editor', 'author', 'comments', 'custom-fields' ),
			'public' => false, 'has_archive' => false, 'rewrite' => false,
		),
		'kb_submission' => array(
			'singular' => 'Submission', 'plural' => 'Submissions',
			'icon' => 'dashicons-email-alt', 'slug' => 'submissions',
			'supports' => array( 'title', 'editor', 'author' ),
			'public' => false, 'has_archive' => false, 'rewrite' => false,
		),
		'kb_poll' => array(
			'singular' => 'Poll', 'plural' => 'Polls',
			'icon' => 'dashicons-chart-pie', 'slug' => 'polls',
			'supports' => array( 'title', 'editor' ),
		),
	);

	foreach ( $types as $key => $c ) {
		register_post_type( $key, array(
			'label'        => $c['plural'],
			'labels'       => array(
				'name'          => $c['plural'],
				'singular_name' => $c['singular'],
				'add_new_item'  => 'Add new ' . strtolower( $c['singular'] ),
				'edit_item'     => 'Edit ' . strtolower( $c['singular'] ),
				'menu_name'     => 'KpopBlog ' . $c['plural'],
			),
			'public'       => isset( $c['public'] ) ? (bool) $c['public'] : true,
			'show_ui'      => true,
			'show_in_menu' => 'kpopblog-admin',
			'has_archive'  => isset( $c['has_archive'] ) ? (bool) $c['has_archive'] : true,
			'show_in_rest' => true, // Gutenberg + /wp-json/wp/v2/{type}
			'rest_base'    => $key,
			'menu_icon'    => $c['icon'],
			'supports'     => $c['supports'],
			'rewrite'      => array_key_exists( 'rewrite', $c ) ? $c['rewrite'] : array( 'slug' => $c['slug'] ),
		) );
	}

	// Taxonomies shared with native posts (articles).
	register_taxonomy( 'kb_artist_tag', array( 'post', 'kb_thread', 'kb_comeback', 'kb_video', 'kb_poll' ), array(
		'label'        => 'Related artists',
		'public'       => true,
		'show_in_rest' => true,
		'hierarchical' => false,
	) );

	register_taxonomy( 'kb_forum_category', array( 'kb_thread' ), array(
		'label'        => 'Forum categories',
		'labels'       => array(
			'name'          => 'Forum categories',
			'singular_name' => 'Forum category',
			'menu_name'     => 'Forum categories',
		),
		'public'       => false,
		'show_ui'      => true,
		'show_in_rest' => true,
		'hierarchical' => true,
		'rewrite'      => false,
	) );
}
add_action( 'init', 'kpopblog_register_cpts' );

function kpopblog_seed_forum_categories() {
	$legacy_news = get_term_by( 'slug', 'news', 'kb_forum_category' );
	$current_news = get_term_by( 'slug', 'news-reactions', 'kb_forum_category' );
	if ( $legacy_news && ! $current_news ) {
		wp_update_term( $legacy_news->term_id, 'kb_forum_category', array(
			'name'        => 'News Reactions',
			'slug'        => 'news-reactions',
			'description' => 'Discuss verified headlines and industry moves.',
		) );
	}

	$categories = array(
		'general'        => array( 'General K-pop Discussion', 'Everything K-pop: open chat for all groups.' ),
		'comebacks'      => array( 'Comebacks and Debuts', 'Track upcoming releases and rookie debuts.' ),
		'news-reactions' => array( 'News Reactions', 'Discuss verified headlines and industry moves.' ),
		'fandoms'        => array( 'Artist Fandoms', 'Dedicated spaces for fan communities.' ),
		'concerts'       => array( 'Concerts and Tours', 'Tours, festivals, tickets, and live events.' ),
		'albums-merch'   => array( 'Albums and Merch', 'Unboxings, photocard trades, and collection talk.' ),
		'fashion'        => array( 'Styling and Fashion', 'Stage looks, airport fashion, and brand ambassadors.' ),
		'fan-art'        => array( 'Fan Art and Memes', 'Creative fan content from around the world.' ),
	);
	foreach ( $categories as $slug => $category ) {
		if ( ! term_exists( $slug, 'kb_forum_category' ) ) {
			wp_insert_term( $category[0], 'kb_forum_category', array( 'slug' => $slug, 'description' => $category[1] ) );
		}
	}
}
add_action( 'init', 'kpopblog_seed_forum_categories', 20 );

/**
 * Seed a fresh forum with editorial opening discussions. Stable seed keys make
 * the operation idempotent and keep real community posts untouched.
 */
function kpopblog_seed_forum_threads() {
	if ( get_option( 'kpopblog_forum_starter_threads_v1' ) ) {
		return;
	}

	$threads = array(
		'general' => array(
			array( 'Introduce yourself: your first K-pop song and current playlist', 'Tell the community what brought you to K-pop and which artists or songs are on repeat this week.', 'Introductions' ),
			array( 'Weekly listening check-in: what are you replaying right now?', 'Share a favorite track, a new discovery, or the performance that has stayed with you.', 'Weekly' ),
			array( 'What makes a K-pop stage memorable for you?', 'Is it live vocals, choreography, styling, camera work, or the crowd? Join the conversation.', 'Discussion' ),
		),
		'comebacks' => array(
			array( 'Comeback calendar: releases you are most excited for', 'Add confirmed release dates and explain which concept, teaser, or artist has your attention.', 'Calendar' ),
			array( 'First-listen reactions: what do you notice before a second play?', 'Use this thread for respectful early impressions of new albums and singles.', 'First listen' ),
			array( 'Rookie watch: debuts and first comebacks to keep on the radar', 'Introduce a newer artist and recommend a starting song or performance.', 'Rookie watch' ),
		),
		'news-reactions' => array(
			array( 'News discussion guide: share sources and separate facts from opinion', 'Link the original announcement or a reliable report when opening a news discussion.', 'Pinned guide' ),
			array( 'This week in K-pop: headlines worth talking about', 'Bring verified stories from the week and add context for readers catching up.', 'Weekly news' ),
			array( 'Industry changes you are watching this year', 'Discuss distribution, touring, formats, platforms, and other changes shaping K-pop.', 'Analysis' ),
		),
		'fandoms' => array(
			array( 'Fandom roll call: which artist community are you part of?', 'Find fellow fans, share your bias or favorite era, and welcome new members.', 'Roll call' ),
			array( 'Best entry points for a new fan', 'Recommend a song, variety appearance, live stage, or interview that shows an artist at their best.', 'Recommendations' ),
			array( 'Fan project ideas that keep the community welcoming', 'Share respectful celebration and support ideas for anniversaries, releases, and tours.', 'Community' ),
		),
		'concerts' => array(
			array( 'Concert planning hub: venue, travel, and accessibility tips', 'Share practical tips that help other attendees prepare for a great show.', 'Planning' ),
			array( 'Setlist wishes: songs you hope to hear live', 'Post your dream encore and explain which song would make the biggest impact in a venue.', 'Setlist' ),
			array( 'Post-concert recap: favorite moment of the night', 'Keep spoilers marked and share the performance, ment, or crowd moment you will remember.', 'Concert recap' ),
		),
		'albums-merch' => array(
			array( 'Collection corner: your latest album or merch arrival', 'Show what arrived and share the detail that made the release special to you.', 'Collection' ),
			array( 'Photocard collecting tips for new fans', 'Discuss storage, budgeting, trading etiquette, and ways to keep collecting fun.', 'Guide' ),
			array( 'Packaging and album design appreciation thread', 'Which physical releases have impressed you with their concept, inclusions, or visual direction?', 'Design' ),
		),
		'fashion' => array(
			array( 'Stage styling roundtable: looks that defined an era', 'Share a favorite stage outfit and the concept details that made it work.', 'Stage style' ),
			array( 'Airport fashion and casual styling inspiration', 'Discuss practical looks, signature pieces, and styling ideas without speculation about private details.', 'Fashion' ),
			array( 'Beauty and accessories spotlight', 'Celebrate makeup, hair, nail, and accessory details from public performances and editorials.', 'Beauty' ),
		),
		'fan-art' => array(
			array( 'Share your fan art and creative projects', 'Post original work, credit collaborators, and give constructive feedback with permission.', 'Original work' ),
			array( 'Meme exchange: the moments that still make you laugh', 'Keep it kind, label spoilers, and credit the original creator whenever possible.', 'Memes' ),
			array( 'Creative prompt of the week', 'Choose an artist, era, color palette, or lyric theme and make something inspired by it.', 'Weekly prompt' ),
		),
	);

	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	$author_id = ! empty( $admins ) ? (int) $admins[0] : 0;
	$created_all = true;
	foreach ( $threads as $category_slug => $items ) {
		$term = get_term_by( 'slug', $category_slug, 'kb_forum_category' );
		if ( ! $term ) {
			$created_all = false;
			continue;
		}
		foreach ( $items as $index => $item ) {
			$seed_key = 'forum-starter-v1-' . $category_slug . '-' . ( $index + 1 );
			$existing = get_posts( array(
				'post_type'      => 'kb_thread',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => 'kb_seed_key',
				'meta_value'     => $seed_key,
			) );
			if ( ! empty( $existing ) ) {
				continue;
			}
			$post_id = wp_insert_post( array(
				'post_type'      => 'kb_thread',
				'post_status'    => 'publish',
				'post_title'     => $item[0],
				'post_content'   => $item[1],
				'post_author'    => $author_id,
				'comment_status' => 'open',
			), true );
			if ( is_wp_error( $post_id ) ) {
				$created_all = false;
				continue;
			}
			wp_set_object_terms( $post_id, (int) $term->term_id, 'kb_forum_category' );
			update_post_meta( $post_id, 'kb_category_slug', $category_slug );
			update_post_meta( $post_id, 'kb_flair', $item[2] );
			update_post_meta( $post_id, 'kb_seed_key', $seed_key );
		}
	}

	if ( $created_all ) {
		update_option( 'kpopblog_forum_starter_threads_v1', 1, false );
	}
}
add_action( 'init', 'kpopblog_seed_forum_threads', 30 );

/** Add a second, broader set of conversation starters without touching v1 data. */
function kpopblog_seed_forum_threads_v2() {
	if ( get_option( 'kpopblog_forum_starter_threads_v2' ) ) {
		return;
	}

	$threads = array(
		'general' => array(
			array( 'K-pop comfort songs for a difficult day', 'Which songs reliably lift your mood or help you reset? Share a track and the moment you connect with it.', 'Recommendations' ),
			array( 'Your favorite music-show performance this month', 'Drop a public performance link and tell us what made the stage stand out.', 'Performance' ),
			array( 'Songs that convinced you to explore a full discography', 'Which first track made you listen beyond the title song?', 'Discussion' ),
			array( 'K-pop goals for the rest of the year', 'Concerts, collections, language learning, playlists, or new artists: what is on your fan list?', 'Community' ),
			array( 'Weekend recommendation exchange', 'Recommend one song, one stage, and one variety clip for someone looking for a new favorite.', 'Weekly' ),
		),
		'comebacks' => array(
			array( 'Teaser photo details you want to discuss', 'Share a confirmed teaser and the visual details or story clues you noticed.', 'Teasers' ),
			array( 'B-side predictions before release day', 'What sound or genre would you like an upcoming album to explore?', 'Predictions' ),
			array( 'Album sequencing discussion: intros, outros, and transitions', 'Which releases feel especially satisfying from the first track to the final song?', 'Album talk' ),
			array( 'Comeback showcase and live-stage wishlist', 'Which song deserves a full live stage, special unit, or performance video?', 'Performance' ),
			array( 'Release-day listening party plans', 'Coordinate respectful listening sessions and share your first impressions after release.', 'Listening party' ),
		),
		'news-reactions' => array(
			array( 'Official statements: what details matter most to you?', 'Discuss how you read agency or artist announcements while keeping links and context visible.', 'Discussion' ),
			array( 'Chart reporting: how to read weekly results responsibly', 'Compare credible chart sources and discuss what a result does and does not show.', 'Charts' ),
			array( 'Music-industry interviews worth reading', 'Recommend published interviews that add useful creative or business context.', 'Reading list' ),
			array( 'What should a good news recap include?', 'Share the details that help you distinguish a useful recap from speculation.', 'Community' ),
			array( 'Verified schedule updates and announcement tracker', 'Post official schedule changes with a source link so readers can find updates quickly.', 'Updates' ),
		),
		'fandoms' => array(
			array( 'Favorite fandom traditions and anniversary projects', 'Celebrate thoughtful traditions that make an artist anniversary feel special.', 'Appreciation' ),
			array( 'Bias line appreciation: a performance you keep returning to', 'Share an official performance and describe the detail that caught your attention.', 'Appreciation' ),
			array( 'Cross-fandom recommendations for curious listeners', 'Introduce another artist with a respectful starter pack of songs and stages.', 'Recommendations' ),
			array( 'What made you stay in a fandom?', 'Tell the story of the music, people, or moments that made a community feel like home.', 'Discussion' ),
			array( 'Fan-made guides worth sharing with newcomers', 'Link guides that are accurate, welcoming, and clearly credit their creators.', 'Guide' ),
		),
		'concerts' => array(
			array( 'Your ideal concert bag: practical essentials only', 'Compare venue-friendly essentials that make show day smoother.', 'Planning' ),
			array( 'Venue sound and sightline tips', 'Share respectful, practical advice for getting the most from a venue.', 'Venue tips' ),
			array( 'Concert outfit ideas that prioritize comfort', 'Discuss styling ideas that work for long queues, travel, weather, and dancing.', 'Fashion' ),
			array( 'How do you preserve a concert memory?', 'Photos, journals, playlists, ticket stubs, and post-show recaps: share your ritual.', 'Discussion' ),
			array( 'Festival lineup discovery thread', 'Which artist on a festival bill would you recommend to someone arriving early?', 'Festival' ),
		),
		'albums-merch' => array(
			array( 'How do you organize albums, inclusions, and photocards?', 'Share storage systems that protect a collection and keep it easy to enjoy.', 'Collection' ),
			array( 'Favorite album versions and why they work', 'Discuss packaging, photos, tracklists, or inclusions that made a version memorable.', 'Album talk' ),
			array( 'Budget-friendly collecting habits', 'Compare approaches that keep collecting sustainable and fun.', 'Guide' ),
			array( 'Merch you use every day', 'Which practical item has become part of your desk, bag, or home routine?', 'Merch' ),
			array( 'Trade etiquette and safety checklist', 'Share best practices for clear photos, proof, shipping, and respectful communication.', 'Trading' ),
		),
		'fashion' => array(
			array( 'Concept colors and styling motifs you love', 'Discuss palettes, textures, and repeated visual motifs from public concepts and stages.', 'Analysis' ),
			array( 'Performance outfits that match the choreography', 'Which costumes helped make a dance or stage idea feel complete?', 'Stage style' ),
			array( 'Favorite red-carpet and editorial looks', 'Share public editorial or event looks that felt distinctive and well considered.', 'Editorial' ),
			array( 'Hair styling eras that changed an artists image', 'Celebrate public style changes without speculation about private choices.', 'Beauty' ),
			array( 'Style inspiration for a themed listening party', 'Build an outfit or mood board around an era, song, or color palette.', 'Creative' ),
		),
		'fan-art' => array(
			array( 'Fan edit showcase: music, timing, and credit', 'Share original edits and credit every clip, artwork, and collaborator used.', 'Original work' ),
			array( 'Wallpaper and lock-screen design thread', 'Post original designs in useful sizes and credit artists for source material.', 'Design' ),
			array( 'Fanfic and writing prompt exchange', 'Share short, respectful prompts and keep all work appropriately tagged.', 'Writing' ),
			array( 'Cosplay and handmade prop progress log', 'Show your process, materials, and lessons learned while respecting event rules.', 'Crafts' ),
			array( 'Caption contest: make this public moment funny', 'Use a public image or clip, keep captions kind, and avoid targeting individuals.', 'Games' ),
		),
	);

	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	$author_id = ! empty( $admins ) ? (int) $admins[0] : 0;
	$created_all = true;
	foreach ( $threads as $category_slug => $items ) {
		$term = get_term_by( 'slug', $category_slug, 'kb_forum_category' );
		if ( ! $term ) {
			$created_all = false;
			continue;
		}
		foreach ( $items as $index => $item ) {
			$seed_key = 'forum-starter-v2-' . $category_slug . '-' . ( $index + 1 );
			$existing = get_posts( array(
				'post_type'      => 'kb_thread',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => 'kb_seed_key',
				'meta_value'     => $seed_key,
			) );
			if ( ! empty( $existing ) ) {
				continue;
			}
			$post_id = wp_insert_post( array(
				'post_type'      => 'kb_thread',
				'post_status'    => 'publish',
				'post_title'     => $item[0],
				'post_content'   => $item[1],
				'post_author'    => $author_id,
				'comment_status' => 'open',
			), true );
			if ( is_wp_error( $post_id ) ) {
				$created_all = false;
				continue;
			}
			wp_set_object_terms( $post_id, (int) $term->term_id, 'kb_forum_category' );
			update_post_meta( $post_id, 'kb_category_slug', $category_slug );
			update_post_meta( $post_id, 'kb_flair', $item[2] );
			update_post_meta( $post_id, 'kb_seed_key', $seed_key );
		}
	}

	if ( $created_all ) {
		update_option( 'kpopblog_forum_starter_threads_v2', 1, false );
	}
}
add_action( 'init', 'kpopblog_seed_forum_threads_v2', 31 );

function kpopblog_ensure_published_thread_slug( $new_status, $old_status, WP_Post $post ) {
	if ( 'publish' !== $new_status || 'kb_thread' !== $post->post_type || $post->post_name !== '' ) {
		return;
	}
	global $wpdb;
	$slug = wp_unique_post_slug( sanitize_title( $post->post_title ), $post->ID, 'publish', 'kb_thread', $post->post_parent );
	if ( $slug !== '' ) {
		$wpdb->update( $wpdb->posts, array( 'post_name' => $slug ), array( 'ID' => $post->ID ), array( '%s' ), array( '%d' ) );
		clean_post_cache( $post->ID );
	}
}
add_action( 'transition_post_status', 'kpopblog_ensure_published_thread_slug', 10, 3 );
