<?php
/**
 * Manual, responsive Google AdSense placement settings.
 *
 * @package KpopBlog
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const KPOPBLOG_ADS_OPTION = 'kpopblog_ads';

function kpopblog_ads_allowed_slots() {
	return array(
		'global-top-leaderboard' => 'Global top leaderboard',
		'global-pre-footer'      => 'Global pre-footer billboard',
		'home-after-hero'        => 'Home after hero',
		'home-mid-feed'          => 'Home middle feed',
		'home-pre-community'     => 'Home before community',
		'artists-top'            => 'Artists top',
		'charts-top'             => 'Charts top',
		'comebacks-top'          => 'Comebacks top',
		'community-top'          => 'Community top',
		'forum-top'              => 'Forum top',
		'latest-top'             => 'Latest top',
		'polls-top'              => 'Polls top',
		'trending-top'           => 'Trending top',
		'videos-top'             => 'Videos top',
		'article-inline'         => 'Article inline',
		'article-pre-related'    => 'Article before related content',
		'watch-inline'           => 'Video inline',
		'watch-sidebar'          => 'Video sidebar',
	);
}

function kpopblog_ads_defaults() {
	return array( 'enabled' => false, 'publisher_id' => '', 'slots' => array() );
}

function kpopblog_sanitize_ads_settings( $input ) {
	$input = is_array( $input ) ? $input : array();
	$publisher_id = isset( $input['publisher_id'] ) ? trim( (string) $input['publisher_id'] ) : '';
	if ( ! preg_match( '/^ca-pub-\d{16}$/', $publisher_id ) ) {
		return kpopblog_ads_defaults();
	}

	$raw_slots = isset( $input['slots'] ) ? $input['slots'] : array();
	if ( is_string( $raw_slots ) ) {
		$parsed = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $raw_slots ) as $line ) {
			$parts = array_map( 'trim', explode( '=', $line, 2 ) );
			if ( 2 === count( $parts ) ) { $parsed[ $parts[0] ] = $parts[1]; }
		}
		$raw_slots = $parsed;
	}

	$allowed = kpopblog_ads_allowed_slots();
	$slots = array();
	foreach ( is_array( $raw_slots ) ? $raw_slots : array() as $logical_id => $ad_slot ) {
		$logical_id = sanitize_key( (string) $logical_id );
		$ad_slot = trim( (string) $ad_slot );
		if ( isset( $allowed[ $logical_id ] ) && preg_match( '/^\d{4,20}$/', $ad_slot ) ) {
			$slots[ $logical_id ] = $ad_slot;
		}
	}

	return array(
		'enabled'      => ! empty( $input['enabled'] ) && ! empty( $slots ),
		'publisher_id' => $publisher_id,
		'slots'        => $slots,
	);
}

function kpopblog_get_ads_settings() {
	$saved = get_option( KPOPBLOG_ADS_OPTION, array() );
	return kpopblog_sanitize_ads_settings( is_array( $saved ) ? $saved : array() );
}

function kpopblog_ads_frontend_config() {
	$settings = kpopblog_get_ads_settings();
	return array(
		'enabled'     => (bool) $settings['enabled'],
		'publisherId' => (string) $settings['publisher_id'],
		'slots'       => $settings['slots'],
	);
}

function kpopblog_register_ads_settings() {
	register_setting( 'kpopblog_ads_group', KPOPBLOG_ADS_OPTION, array(
		'type'              => 'array',
		'sanitize_callback' => 'kpopblog_sanitize_ads_settings',
		'default'           => kpopblog_ads_defaults(),
	) );
}
add_action( 'admin_init', 'kpopblog_register_ads_settings' );

function kpopblog_register_ads_admin_page() {
	add_submenu_page(
		'kpopblog-admin',
		'AdSense',
		'AdSense',
		'kb_manage_ads',
		'kpopblog-ads',
		'kpopblog_render_ads_admin_page'
	);
}
add_action( 'admin_menu', 'kpopblog_register_ads_admin_page' );

function kpopblog_render_ads_admin_page() {
	if ( ! current_user_can( 'kb_manage_ads' ) ) { return; }
	$settings = kpopblog_get_ads_settings();
	$slot_lines = array();
	foreach ( $settings['slots'] as $logical_id => $ad_slot ) {
		$slot_lines[] = $logical_id . '=' . $ad_slot;
	}
	?>
	<div class="wrap">
		<h1>Google AdSense</h1>
		<p>KpopBlog uses manually placed responsive display units so React owns the page layout. Auto Ads are intentionally not injected into the app shell.</p>
		<p><strong>Before production:</strong> configure a Google-certified consent management platform in AdSense Privacy &amp; messaging for every region where Google requires one. This page only controls KpopBlog's local consent gate and ad-unit mapping.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'kpopblog_ads_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="kb_ads_enabled">Enable AdSense</label></th>
					<td><label><input id="kb_ads_enabled" type="checkbox" name="<?php echo esc_attr( KPOPBLOG_ADS_OPTION ); ?>[enabled]" value="1" <?php checked( $settings['enabled'] ); ?>> Render configured units after advertising consent</label></td>
				</tr>
				<tr>
					<th><label for="kb_ads_publisher">Publisher ID</label></th>
					<td><input id="kb_ads_publisher" type="text" name="<?php echo esc_attr( KPOPBLOG_ADS_OPTION ); ?>[publisher_id]" value="<?php echo esc_attr( $settings['publisher_id'] ); ?>" class="regular-text" placeholder="ca-pub-1234567890123456" pattern="ca-pub-[0-9]{16}"></td>
				</tr>
				<tr>
					<th><label for="kb_ads_slots">Ad units</label></th>
					<td>
						<textarea id="kb_ads_slots" name="<?php echo esc_attr( KPOPBLOG_ADS_OPTION ); ?>[slots]" rows="12" class="large-text code" placeholder="global-top-leaderboard=1234567890"><?php echo esc_textarea( implode( "\n", $slot_lines ) ); ?></textarea>
						<p class="description">One <code>logical-placement=AdSense-slot-ID</code> per line. Unknown placements and custom sticky units are rejected.</p>
						<details><summary>Allowed logical placements</summary><ul>
							<?php foreach ( kpopblog_ads_allowed_slots() as $logical_id => $label ) : ?><li><code><?php echo esc_html( $logical_id ); ?></code> — <?php echo esc_html( $label ); ?></li><?php endforeach; ?>
						</ul></details>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Save AdSense settings' ); ?>
		</form>
	</div>
	<?php
}

add_action( 'update_option_' . KPOPBLOG_ADS_OPTION, function ( $old_value, $value ) {
	$settings = kpopblog_sanitize_ads_settings( $value );
	kpopblog_audit( 'ads_settings_updated', 'settings', 0, array(
		'enabled'             => (bool) $settings['enabled'],
		'publisher_configured' => (bool) $settings['publisher_id'],
		'slot_count'          => count( $settings['slots'] ),
	) );
}, 10, 2 );
