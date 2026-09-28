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
	return array( 'enabled' => false, 'publisher_id' => '', 'slots' => array(), 'auto_ads' => false, 'consent_mode' => 'site' );
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

	$auto_ads = ! empty( $input['auto_ads'] );
	return array(
		// Auto Ads only needs the publisher ID; manual placements also need slot IDs.
		'enabled'      => ! empty( $input['enabled'] ) && ( ! empty( $slots ) || $auto_ads ),
		'publisher_id' => $publisher_id,
		'slots'        => $slots,
		'auto_ads'     => $auto_ads,
		// site: load ads after the in-app "advertising" consent.
		// optout: load for everyone; visitors who reject advertising get non-personalized ads.
		// google: load for everyone and let Google's certified CMP ask where the law requires.
		'consent_mode' => isset( $input['consent_mode'] ) && in_array( $input['consent_mode'], array( 'optout', 'google' ), true ) ? $input['consent_mode'] : 'site',
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
		'autoAds'     => (bool) $settings['auto_ads'],
		'consentMode' => (string) $settings['consent_mode'],
	);
}

/**
 * ads.txt lines for the configured publisher. Serving the file from WordPress
 * means no upload to the web root is needed on any host; a physical ads.txt in
 * the web root is served by the web server first and takes precedence.
 */
function kpopblog_ads_txt_lines() {
	$settings = kpopblog_get_ads_settings();
	if ( '' === $settings['publisher_id'] ) { return array(); }
	return array( 'google.com, ' . substr( $settings['publisher_id'], 3 ) . ', DIRECT, f08c47fec0942fa0' );
}

function kpopblog_serve_ads_txt() {
	$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
	if ( '/ads.txt' !== $path ) { return; }
	$lines = kpopblog_ads_txt_lines();
	if ( ! $lines ) { return; }
	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Cache-Control: public, max-age=3600' );
	echo implode( "\n", $lines ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}
add_action( 'template_redirect', 'kpopblog_serve_ads_txt', 0 );

/** AdSense site-ownership meta tag; it verifies the site without loading any ad code. */
function kpopblog_adsense_account_meta() {
	$settings = kpopblog_get_ads_settings();
	if ( '' !== $settings['publisher_id'] ) {
		echo '<meta name="google-adsense-account" content="' . esc_attr( $settings['publisher_id'] ) . '" />' . "\n";
	}
}
add_action( 'wp_head', 'kpopblog_adsense_account_meta', 1 );

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
		<p>Choose <strong>Auto ads</strong> to let Google place ads automatically (only the publisher ID is needed; turn on Auto ads for this site in AdSense → Ads → By site). Add manual ad-unit IDs below for fixed placements; both can run together.</p>
		<p><strong>Consent:</strong> with "In-site consent banner", ads load only after a visitor accepts advertising. For higher fill, enable Google's certified consent message in AdSense → Privacy &amp; messaging (European regulations and US state regulations), then switch to "Google certified CMP".</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'kpopblog_ads_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="kb_ads_enabled">Enable AdSense</label></th>
					<td><label><input id="kb_ads_enabled" type="checkbox" name="<?php echo esc_attr( KPOPBLOG_ADS_OPTION ); ?>[enabled]" value="1" <?php checked( $settings['enabled'] ); ?>> Render configured units after advertising consent</label></td>
				</tr>
				<tr>
					<th>Auto ads</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( KPOPBLOG_ADS_OPTION ); ?>[auto_ads]" value="1" <?php checked( $settings['auto_ads'] ); ?>> Load the AdSense Auto ads script on every page</label></td>
				</tr>
				<tr>
					<th>Consent</th>
					<td>
						<label><input type="radio" name="<?php echo esc_attr( KPOPBLOG_ADS_OPTION ); ?>[consent_mode]" value="site" <?php checked( 'site', $settings['consent_mode'] ); ?>> In-site consent banner (ads after "Accept")</label><br>
						<label><input type="radio" name="<?php echo esc_attr( KPOPBLOG_ADS_OPTION ); ?>[consent_mode]" value="optout" <?php checked( 'optout', $settings['consent_mode'] ); ?>> Show ads to everyone; visitors who choose "Reject optional" get non-personalized ads</label><br>
						<label><input type="radio" name="<?php echo esc_attr( KPOPBLOG_ADS_OPTION ); ?>[consent_mode]" value="google" <?php checked( 'google', $settings['consent_mode'] ); ?>> Google certified CMP (requires the AdSense Privacy &amp; messaging consent message to be published)</label>
					</td>
				</tr>
				<tr>
					<th><label for="kb_ads_publisher">Publisher ID</label></th>
					<td><input id="kb_ads_publisher" type="text" name="<?php echo esc_attr( KPOPBLOG_ADS_OPTION ); ?>[publisher_id]" value="<?php echo esc_attr( $settings['publisher_id'] ); ?>" class="regular-text" placeholder="ca-pub-1234567890123456" pattern="ca-pub-[0-9]{16}">
						<p class="description">The site automatically serves <a href="<?php echo esc_url( home_url( '/ads.txt' ) ); ?>" target="_blank" rel="noopener">/ads.txt</a> and the <code>google-adsense-account</code> verification tag for this ID.</p></td>
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
		'auto_ads'            => (bool) $settings['auto_ads'],
		'consent_mode'        => $settings['consent_mode'],
	) );
}, 10, 2 );
