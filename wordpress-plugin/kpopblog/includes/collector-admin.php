<?php
/**
 * WordPress administration for the keyless news collector.
 *
 * @package KpopBlog
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function kpopblog_register_collector_admin_page() {
	add_submenu_page(
		'kpopblog-admin',
		'News Collector',
		'News Collector',
		'kb_manage_automation',
		'kpopblog-collector',
		'kpopblog_render_collector_admin_page'
	);
}
add_action( 'admin_menu', 'kpopblog_register_collector_admin_page', 11 );

function kpopblog_register_collector_settings() {
	register_setting( 'kpopblog_collector_group', KPOPBLOG_COLLECTOR_OPTION, array(
		'type'              => 'array',
		'sanitize_callback' => 'kpopblog_sanitize_collector_settings',
		'default'           => kpopblog_collector_defaults(),
	) );
}
add_action( 'admin_init', 'kpopblog_register_collector_settings' );

function kpopblog_render_collector_admin_page() {
	if ( ! current_user_can( 'kb_manage_automation' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage the news collector.', 'kpopblog' ) );
	}
	global $wpdb;
	$settings = kpopblog_get_collector_settings();
	$option   = KPOPBLOG_COLLECTOR_OPTION;
	$runs     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}kb_automation_runs WHERE model = %s ORDER BY id DESC LIMIT 15", KPOPBLOG_COLLECTOR_MODEL ) );
	$notice   = isset( $_GET['kb_collector_notice'] ) ? sanitize_key( wp_unslash( $_GET['kb_collector_notice'] ) ) : '';
	$result   = get_transient( 'kpopblog_collector_notice_' . get_current_user_id() );
	$next     = wp_next_scheduled( KPOPBLOG_COLLECTOR_HOOK );
	$artists  = kpopblog_get_artist_matchers();
	$collected = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}kb_automation_items WHERE kind = 'rss'" );
	$checkbox = function ( $key, $label ) use ( $settings, $option ) {
		printf(
			'<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> %4$s</label>',
			esc_attr( $option ),
			esc_attr( $key ),
			checked( 1, $settings[ $key ], false ),
			esc_html( $label )
		);
	};
	?>
	<div class="wrap">
		<h1>News Collector</h1>
		<p>Collects K-pop news for every artist in <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=kb_artist' ) ); ?>">KpopBlog Artists</a> from the publishers selected below. Each story is published as an on-site summary with a small source credit; when several outlets report the same story they are credited on one article. Official YouTube videos, announced release dates, and newsroom discussion threads are added automatically. No API key is required.</p>
		<?php if ( 'completed' === $notice && is_array( $result ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( sprintf( 'Run finished: %d new articles, %d extra sources merged into existing stories, %d videos, %d release dates, %d discussion threads, %d polls (%d feeds read).', $result['created'], $result['updated'], $result['videos'], $result['comebacks'], $result['threads'], $result['polls'], $result['feeds'] ) ); ?></p></div>
		<?php elseif ( 'failed' === $notice ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php echo esc_html( is_string( $result ) ? $result : 'The collector could not complete. Review the run log below.' ); ?></p></div>
		<?php elseif ( 'seeded' === $notice && is_array( $result ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( sprintf( 'Artist catalogue checked: %d artists and %d members added.', $result['artists'], $result['members'] ) ); ?></p></div>
		<?php endif; ?>

		<table class="widefat striped" style="max-width:900px;margin:16px 0 24px">
			<tbody>
				<tr><th style="width:220px">Status</th><td><?php echo $settings['enabled'] ? '<span style="color:#008a20">Enabled</span>' : '<span style="color:#b32d2e">Disabled</span>'; ?></td></tr>
				<tr><th>Next scheduled run</th><td><?php echo $next ? esc_html( wp_date( 'Y-m-d H:i:s T', $next ) ) : 'Not scheduled'; ?></td></tr>
				<tr><th>Last successful run</th><td><?php echo esc_html( (string) get_option( 'kpopblog_collector_last_success', 'Never' ) ); ?></td></tr>
				<tr><th>Articles collected</th><td><?php echo esc_html( number_format_i18n( $collected ) ); ?></td></tr>
				<tr><th>Artists followed</th><td><?php echo esc_html( count( $artists ) ); ?> — <?php echo esc_html( implode( ', ', wp_list_pluck( array_slice( $artists, 0, 40 ), 'name' ) ) ); ?></td></tr>
			</tbody>
		</table>

		<div style="display:flex;gap:10px;flex-wrap:wrap;margin:0 0 24px">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="kpopblog_collector_run" />
				<input type="hidden" name="mode" value="manual" />
				<?php wp_nonce_field( 'kpopblog_collector_run' ); ?>
				<?php submit_button( 'Collect now', 'primary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="kpopblog_collector_run" />
				<input type="hidden" name="mode" value="backfill" />
				<?php wp_nonce_field( 'kpopblog_collector_run' ); ?>
				<?php submit_button( 'Backfill all artists (slower)', 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="kpopblog_seed_artists" />
				<?php wp_nonce_field( 'kpopblog_seed_artists' ); ?>
				<?php submit_button( 'Restore starter artists', 'secondary', 'submit', false ); ?>
			</form>
		</div>

		<form method="post" action="options.php">
			<?php settings_fields( 'kpopblog_collector_group' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Automatic collection</th><td><?php $checkbox( 'enabled', 'Collect and publish automatically with WP-Cron' ); ?></td></tr>
				<tr><th scope="row"><label for="kb_collector_frequency">Frequency</label></th><td><select id="kb_collector_frequency" name="<?php echo esc_attr( $option ); ?>[frequency]"><?php foreach ( kpopblog_collector_frequencies() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['frequency'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
				<tr><th scope="row"><label for="kb_collector_max">New articles per run</label></th><td><input id="kb_collector_max" class="small-text" type="number" min="1" max="30" name="<?php echo esc_attr( $option ); ?>[max_posts]" value="<?php echo esc_attr( $settings['max_posts'] ); ?>" /></td></tr>
				<tr><th scope="row"><label for="kb_collector_daily">Articles per day</label></th><td><input id="kb_collector_daily" class="small-text" type="number" min="1" max="300" name="<?php echo esc_attr( $option ); ?>[daily_max_posts]" value="<?php echo esc_attr( $settings['daily_max_posts'] ); ?>" /><p class="description">Publishing is spread evenly across the day up to this number. <?php $day = get_option( 'kpopblog_collector_day', array() ); echo esc_html( sprintf( 'Published today: %d.', is_array( $day ) && isset( $day['date'] ) && $day['date'] === wp_date( 'Y-m-d' ) ? (int) $day['count'] : 0 ) ); ?></p></td></tr>
				<tr><th scope="row"><label for="kb_collector_artist_feeds">Artist feeds per run</label></th><td><input id="kb_collector_artist_feeds" class="small-text" type="number" min="0" max="40" name="<?php echo esc_attr( $option ); ?>[artist_feeds_per_run]" value="<?php echo esc_attr( $settings['artist_feeds_per_run'] ); ?>" /><p class="description">Artist feeds rotate so every artist is checked regularly without slowing each run.</p></td></tr>
				<tr><th scope="row"><label for="kb_collector_age">Maximum story age (days)</label></th><td><input id="kb_collector_age" class="small-text" type="number" min="1" max="14" name="<?php echo esc_attr( $option ); ?>[max_age_days]" value="<?php echo esc_attr( $settings['max_age_days'] ); ?>" /></td></tr>
				<tr><th scope="row">Content</th><td>
					<?php $checkbox( 'fetch_images', 'Use the source article\'s preview image (og:image) as the thumbnail' ); ?><br />
					<?php $checkbox( 'collect_videos', 'Add official music videos, teasers, and performances from label YouTube channels' ); ?><br />
					<?php $checkbox( 'extract_comebacks', 'Add release dates announced in comeback headlines to the comeback calendar' ); ?><br />
					<?php $checkbox( 'weekly_poll', 'Open weekly fan polls (most-followed news and most-anticipated release)' ); ?><br />
					<?php $checkbox( 'community_hubs', 'Keep a newsroom fan hub thread per artist in Artist Fandoms, refreshed with their latest news' ); ?><br />
					<?php $checkbox( 'recurring_threads', 'Post recurring newsroom threads: daily news roundup, weekly comeback watch, concert check-in, and fan-art prompt' ); ?><br />
					<?php $checkbox( 'ai_rewrite', 'Rewrite each story as an original article (new headline, summary, and body) with the OpenAI model set on the AI Automation page' ); ?>
				</td></tr>
				<tr><th scope="row"><label for="kb_collector_threads">Discussion threads per run</label></th><td><input id="kb_collector_threads" class="small-text" type="number" min="0" max="10" name="<?php echo esc_attr( $option ); ?>[discussion_threads]" value="<?php echo esc_attr( $settings['discussion_threads'] ); ?>" /><p class="description">Newsroom threads for fresh artist headlines, routed to the matching board (Comebacks, Concerts, Styling and Fashion, Albums and Merch, or News Reactions). Set 0 to turn off.</p></td></tr>
				<tr><th scope="row"><label for="kb_collector_template">Artist feed URL</label></th><td><input id="kb_collector_template" class="large-text code" type="url" name="<?php echo esc_attr( $option ); ?>[artist_feed_template]" value="<?php echo esc_attr( $settings['artist_feed_template'] ); ?>" /><p class="description"><code>%s</code> is replaced with each artist's "News tag" field. Leave empty to use general feeds only.</p></td></tr>
				<tr><th scope="row">Sources</th><td>
					<?php foreach ( array( 'en' => 'English', 'ko' => 'Korean (shown as-is on the English site)' ) as $lang => $lang_label ) : ?>
						<p><strong><?php echo esc_html( $lang_label ); ?></strong></p>
						<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:4px 16px;margin-bottom:10px">
						<?php foreach ( kpopblog_collector_source_catalog() as $key => $source ) : if ( $source['lang'] !== $lang ) { continue; } ?>
							<label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[sources][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, (array) $settings['sources'], true ) ); ?> /> <?php echo esc_html( $source['name'] ); ?></label>
						<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
					<p class="description">General music and fashion outlets only contribute stories that name a followed artist in the headline. Rumor-style headlines are skipped for gossip-leaning outlets.</p>
				</td></tr>
				<tr><th scope="row"><label for="kb_collector_feeds">Additional feeds</label></th><td><textarea id="kb_collector_feeds" class="large-text code" rows="3" name="<?php echo esc_attr( $option ); ?>[general_feeds]"><?php echo esc_textarea( $settings['general_feeds'] ); ?></textarea><p class="description">Optional. One HTTPS RSS or Atom URL per line; items are kept only when they are about K-pop.</p></td></tr>
				<tr><th scope="row"><label for="kb_collector_channels">YouTube channel IDs</label></th><td><textarea id="kb_collector_channels" class="large-text code" rows="4" name="<?php echo esc_attr( $option ); ?>[video_channels]"><?php echo esc_textarea( $settings['video_channels'] ); ?></textarea><p class="description">Official label or artist channels (IDs start with UC). Videos are added only when the title names a followed artist.</p></td></tr>
			</table>
			<?php submit_button( 'Save collector settings' ); ?>
		</form>

		<h2>Recent runs</h2>
		<table class="widefat striped">
			<thead><tr><th>Started (UTC)</th><th>Trigger</th><th>Status</th><th>Items read</th><th>Published</th><th>Merged</th><th>Skipped</th><th>Details</th></tr></thead>
			<tbody><?php if ( ! $runs ) : ?><tr><td colspan="8">No collector runs yet.</td></tr><?php else : foreach ( $runs as $run ) :
				$details = json_decode( (string) $run->error_text, true );
				$summary = is_array( $details ) && isset( $details['extra'] ) ? sprintf( '%d feeds · %d videos · %d dates · %d threads · %d polls · %d AI rewrites', $details['extra']['feeds'], $details['extra']['videos'], $details['extra']['comebacks'], $details['extra']['threads'], $details['extra']['polls'], isset( $details['extra']['ai'] ) ? $details['extra']['ai'] : 0 ) : (string) $run->error_text;
				if ( is_array( $details ) && ! empty( $details['sources'] ) ) {
					$parts = array();
					foreach ( $details['sources'] as $name => $count ) { $parts[] = $name . ' ' . (int) $count; }
					$summary .= ' — ' . implode( ', ', $parts );
				}
				if ( is_array( $details ) && ! empty( $details['log'] ) ) { $summary .= ' — ' . implode( '; ', array_slice( $details['log'], 0, 3 ) ); }
				?><tr>
				<td><?php echo esc_html( $run->started_at ); ?></td><td><?php echo esc_html( $run->trigger_type ); ?></td><td><?php echo esc_html( $run->status ); ?></td>
				<td><?php echo esc_html( (string) $run->discovered ); ?></td><td><?php echo esc_html( (string) $run->created ); ?></td><td><?php echo esc_html( (string) $run->updated ); ?></td><td><?php echo esc_html( (string) $run->skipped ); ?></td><td><?php echo esc_html( $summary ); ?></td>
			</tr><?php endforeach; endif; ?></tbody>
		</table>
	</div>
	<?php
}

function kpopblog_handle_collector_run() {
	if ( ! current_user_can( 'kb_manage_automation' ) ) { wp_die( esc_html__( 'You do not have permission to run the collector.', 'kpopblog' ) ); }
	check_admin_referer( 'kpopblog_collector_run' );
	$mode = isset( $_POST['mode'] ) && 'backfill' === $_POST['mode'] ? 'backfill' : 'manual';
	$result = kpopblog_run_news_collector( $mode );
	set_transient( 'kpopblog_collector_notice_' . get_current_user_id(), is_wp_error( $result ) ? $result->get_error_message() : $result, 5 * MINUTE_IN_SECONDS );
	wp_safe_redirect( add_query_arg( 'kb_collector_notice', is_wp_error( $result ) ? 'failed' : 'completed', admin_url( 'admin.php?page=kpopblog-collector' ) ) );
	exit;
}
add_action( 'admin_post_kpopblog_collector_run', 'kpopblog_handle_collector_run' );

function kpopblog_handle_seed_artists() {
	if ( ! current_user_can( 'kb_manage_automation' ) ) { wp_die( esc_html__( 'You do not have permission to manage artists.', 'kpopblog' ) ); }
	check_admin_referer( 'kpopblog_seed_artists' );
	set_transient( 'kpopblog_collector_notice_' . get_current_user_id(), kpopblog_seed_artist_catalog(), 5 * MINUTE_IN_SECONDS );
	wp_safe_redirect( add_query_arg( 'kb_collector_notice', 'seeded', admin_url( 'admin.php?page=kpopblog-collector' ) ) );
	exit;
}
add_action( 'admin_post_kpopblog_seed_artists', 'kpopblog_handle_seed_artists' );

/** Administrator REST trigger used by operations scripts and smoke tests. */
function kpopblog_register_collector_rest_routes() {
	register_rest_route( KPOPBLOG_REST_NS, '/admin/collector/run', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return current_user_can( 'kb_manage_automation' ); },
		'args'                => array( 'mode' => array( 'type' => 'string', 'enum' => array( 'manual', 'backfill' ), 'default' => 'manual' ) ),
		'callback'            => function ( WP_REST_Request $request ) {
			$result = kpopblog_run_news_collector( $request->get_param( 'mode' ) );
			if ( is_wp_error( $result ) ) { $result->add_data( array( 'status' => 409 ) ); return $result; }
			return rest_ensure_response( $result );
		},
	) );
	register_rest_route( KPOPBLOG_REST_NS, '/admin/collector/rewrite', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return current_user_can( 'kb_manage_automation' ); },
		'args'                => array(
			'limit'       => array( 'type' => 'integer', 'default' => 5, 'minimum' => 1, 'maximum' => 20 ),
			'retryFailed' => array( 'type' => 'boolean', 'default' => false ),
		),
		'callback'            => function ( WP_REST_Request $request ) {
			if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 300 ); } // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( $request->get_param( 'retryFailed' ) ) {
				delete_post_meta_by_key( 'kb_ai_rewrite_failed' );
				delete_transient( 'kpopblog_ai_blocked_models' );
			}
			return rest_ensure_response( kpopblog_collector_rewrite_existing( (int) $request->get_param( 'limit' ), time() + 240 ) );
		},
	) );
	register_rest_route( KPOPBLOG_REST_NS, '/admin/collector/status', array(
		'methods'             => 'GET',
		'permission_callback' => function () { return current_user_can( 'kb_manage_automation' ); },
		'callback'            => function () {
			global $wpdb;
			$runs = $wpdb->get_results( $wpdb->prepare( "SELECT id, trigger_type, status, discovered, created, updated, skipped, error_text, started_at, finished_at FROM {$wpdb->prefix}kb_automation_runs WHERE model = %s ORDER BY id DESC LIMIT 10", KPOPBLOG_COLLECTOR_MODEL ), ARRAY_A );
			return rest_ensure_response( array(
				'settings'    => kpopblog_get_collector_settings(),
				'nextRun'     => wp_next_scheduled( KPOPBLOG_COLLECTOR_HOOK ),
				'lastSuccess' => get_option( 'kpopblog_collector_last_success', '' ),
				'lastError'   => get_option( 'kpopblog_collector_last_error', null ),
				'artists'     => count( kpopblog_get_artist_matchers() ),
				'runs'        => $runs,
			) );
		},
	) );
}
add_action( 'rest_api_init', 'kpopblog_register_collector_rest_routes' );
