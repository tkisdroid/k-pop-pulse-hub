<?php
/**
 * wp-admin surface for KpopBlog's user profile fields (kb_role, kb_points,
 * kb_badges, ...) — see kpopblog_map_user() in includes/auth.php and
 * kpopblog_register_user_meta() in includes/meta.php for the field
 * definitions this mirrors. Adds:
 *   - A "KpopBlog Profile" section to the user edit screen (profile.php /
 *     user-edit.php).
 *   - kb_role / kb_points sortable columns on the Users list table.
 *
 * Rank and points are admin-managed only (requires the edit_users
 * capability) so a member can never grant themselves rank or points from
 * their own profile screen; bio/country/language stay self-editable.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const KPOPBLOG_ROLES = array(
	'member'         => 'Member',
	'contributor'    => 'Contributor',
	'trusted_member' => 'Trusted Member',
	'moderator'      => 'Moderator',
	'editor'         => 'Editor',
	'admin'          => 'Admin',
);

function kpopblog_post_string( $key ) {
	return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
}

/* ---------- profile screen fields ---------- */

function kpopblog_render_user_profile_fields( WP_User $user ) {
	$can_manage = current_user_can( 'edit_users' );
	$role       = get_user_meta( $user->ID, 'kb_role', true ) ?: 'member';
	$trust      = get_user_meta( $user->ID, 'kb_trust_level', true ) ?: 1;
	$points     = get_user_meta( $user->ID, 'kb_points', true ) ?: 0;
	$badges     = implode( ', ', (array) get_user_meta( $user->ID, 'kb_badges', true ) );
	$bio        = get_user_meta( $user->ID, 'kb_bio', true );
	$country    = get_user_meta( $user->ID, 'kb_country', true );
	$language   = get_user_meta( $user->ID, 'kb_language', true );
	?>
	<h2>KpopBlog Profile</h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="kb_role">Community role</label></th>
			<td>
				<?php if ( $can_manage ) : ?>
					<select name="kb_role" id="kb_role">
						<?php foreach ( KPOPBLOG_ROLES as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $role, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">Shown as the member's badge/rank across the site — separate from the WordPress role above.</p>
				<?php else : ?>
					<p><?php echo esc_html( KPOPBLOG_ROLES[ $role ] ?? $role ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th><label for="kb_trust_level">Trust level</label></th>
			<td>
				<?php if ( $can_manage ) : ?>
					<input type="number" min="1" max="5" name="kb_trust_level" id="kb_trust_level" value="<?php echo esc_attr( $trust ); ?>" class="small-text" />
				<?php else : ?>
					<p><?php echo esc_html( $trust ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th><label for="kb_points">Points</label></th>
			<td>
				<?php if ( $can_manage ) : ?>
					<input type="number" min="0" name="kb_points" id="kb_points" value="<?php echo esc_attr( $points ); ?>" class="regular-text" />
				<?php else : ?>
					<p><?php echo esc_html( $points ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th><label for="kb_badges">Badges</label></th>
			<td>
				<?php if ( $can_manage ) : ?>
					<input type="text" name="kb_badges" id="kb_badges" value="<?php echo esc_attr( $badges ); ?>" class="regular-text" />
					<p class="description">Comma-separated badge ids, e.g. <code>early-bird, top-fan</code>.</p>
				<?php else : ?>
					<p><?php echo esc_html( $badges ?: '—' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th><label for="kb_bio">Bio</label></th>
			<td><textarea name="kb_bio" id="kb_bio" rows="3" class="large-text"><?php echo esc_textarea( $bio ); ?></textarea></td>
		</tr>
		<tr>
			<th><label for="kb_country">Country</label></th>
			<td><input type="text" name="kb_country" id="kb_country" value="<?php echo esc_attr( $country ); ?>" class="regular-text" /></td>
		</tr>
		<tr>
			<th><label for="kb_language">Preferred language</label></th>
			<td><input type="text" name="kb_language" id="kb_language" value="<?php echo esc_attr( $language ); ?>" class="small-text" placeholder="en" /></td>
		</tr>
	</table>
	<?php
	wp_nonce_field( 'kpopblog_save_user_profile', 'kpopblog_user_profile_nonce' );
}
add_action( 'show_user_profile', 'kpopblog_render_user_profile_fields' );
add_action( 'edit_user_profile', 'kpopblog_render_user_profile_fields' );

function kpopblog_save_user_profile_fields( $user_id ) {
	if ( ! isset( $_POST['kpopblog_user_profile_nonce'] ) || ! wp_verify_nonce( $_POST['kpopblog_user_profile_nonce'], 'kpopblog_save_user_profile' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	update_user_meta( $user_id, 'kb_bio', sanitize_textarea_field( kpopblog_post_string( 'kb_bio' ) ) );
	update_user_meta( $user_id, 'kb_country', sanitize_text_field( kpopblog_post_string( 'kb_country' ) ) );
	update_user_meta( $user_id, 'kb_language', sanitize_text_field( kpopblog_post_string( 'kb_language' ) ) );

	// Gamification/rank fields are admin-managed only.
	if ( current_user_can( 'edit_users' ) ) {
		$role = kpopblog_post_string( 'kb_role' );
		if ( array_key_exists( $role, KPOPBLOG_ROLES ) ) {
			update_user_meta( $user_id, 'kb_role', $role );
		}
		if ( isset( $_POST['kb_trust_level'] ) ) {
			update_user_meta( $user_id, 'kb_trust_level', max( 1, min( 5, (int) $_POST['kb_trust_level'] ) ) );
		}
		if ( isset( $_POST['kb_points'] ) ) {
			update_user_meta( $user_id, 'kb_points', max( 0, (int) $_POST['kb_points'] ) );
		}
		$badges = array_filter( array_map( 'sanitize_title', explode( ',', kpopblog_post_string( 'kb_badges' ) ) ) );
		update_user_meta( $user_id, 'kb_badges', array_values( $badges ) );
	}
}
add_action( 'personal_options_update', 'kpopblog_save_user_profile_fields' );
add_action( 'edit_user_profile_update', 'kpopblog_save_user_profile_fields' );

/* ---------- users list table columns ---------- */

function kpopblog_user_list_columns( $columns ) {
	$columns['kb_role']   = 'Community role';
	$columns['kb_points'] = 'Points';
	return $columns;
}
add_filter( 'manage_users_columns', 'kpopblog_user_list_columns' );

function kpopblog_user_list_column_content( $value, $column_name, $user_id ) {
	if ( $column_name === 'kb_role' ) {
		$role = get_user_meta( $user_id, 'kb_role', true ) ?: 'member';
		return esc_html( KPOPBLOG_ROLES[ $role ] ?? $role );
	}
	if ( $column_name === 'kb_points' ) {
		return esc_html( (string) (int) get_user_meta( $user_id, 'kb_points', true ) );
	}
	return $value;
}
add_filter( 'manage_users_custom_column', 'kpopblog_user_list_column_content', 10, 3 );

function kpopblog_user_sortable_columns( $columns ) {
	$columns['kb_role']   = 'kb_role';
	$columns['kb_points'] = 'kb_points';
	return $columns;
}
add_filter( 'manage_users_sortable_columns', 'kpopblog_user_sortable_columns' );

function kpopblog_user_list_orderby( WP_User_Query $query ) {
	$orderby = $query->get( 'orderby' );
	if ( $orderby === 'kb_role' ) {
		$query->set( 'meta_key', 'kb_role' );
		$query->set( 'orderby', 'meta_value' );
	} elseif ( $orderby === 'kb_points' ) {
		$query->set( 'meta_key', 'kb_points' );
		$query->set( 'orderby', 'meta_value_num' );
	}
}
add_action( 'pre_get_users', 'kpopblog_user_list_orderby' );
