<?php
/**
 * wp-admin meta boxes for every KpopBlog custom field — lets editors publish
 * and edit articles, artists, members, comebacks, charts, forum threads and
 * polls entirely from wp-admin, without touching the REST API. Field
 * definitions mirror includes/meta.php (kpopblog_register_meta) and the
 * shapes read by includes/rest.php's kpopblog_map_*() functions.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---------- shared helpers ---------- */

function kpopblog_all_artists() {
	return get_posts( array(
		'post_type'   => 'kb_artist',
		'numberposts' => -1,
		'orderby'     => 'title',
		'order'       => 'ASC',
		'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ),
	) );
}

function kpopblog_meta_string( $post_id, $key ) {
	$v = get_post_meta( $post_id, $key, true );
	return is_string( $v ) ? $v : '';
}

/* ---------- field schema ---------- */

function kpopblog_field_schema( $post_type ) {
	switch ( $post_type ) {
		case 'post':
			return array(
				array( 'key' => 'kb_subtitle', 'label' => 'Subtitle', 'type' => 'text' ),
				array( 'key' => 'kb_category_slug', 'label' => 'Category slug', 'type' => 'text', 'placeholder' => 'news, comeback, interview…' ),
				array( 'key' => 'kb_reading_time', 'label' => 'Reading time (min)', 'type' => 'number', 'placeholder' => 'auto if empty' ),
				array( 'key' => 'kb_language', 'label' => 'Language', 'type' => 'text', 'placeholder' => 'en', 'small' => true ),
				array( 'key' => 'kb_source', 'label' => 'Source', 'type' => 'select', 'options' => array( 'editorial' => 'Editorial', 'wordpress' => 'WordPress', 'wire' => 'Wire', 'user' => 'User' ) ),
				array( 'key' => 'kb_related_artist_slugs', 'label' => 'Related artists', 'type' => 'artist_multi' ),
			);
		case 'kb_artist':
			return array(
				array( 'key' => 'kb_korean_name', 'label' => 'Korean name', 'type' => 'text' ),
				array( 'key' => 'kb_type', 'label' => 'Type', 'type' => 'select', 'options' => array( 'boy_group' => 'Boy group', 'girl_group' => 'Girl group', 'solo' => 'Solo', 'other' => 'Other' ) ),
				array( 'key' => 'kb_agency', 'label' => 'Agency', 'type' => 'text' ),
				array( 'key' => 'kb_debut_date', 'label' => 'Debut date', 'type' => 'date' ),
				array( 'key' => 'kb_fandom_name', 'label' => 'Fandom name', 'type' => 'text' ),
				array( 'key' => 'kb_status', 'label' => 'Status', 'type' => 'select', 'options' => array( 'active' => 'Active', 'disbanded' => 'Disbanded', 'hiatus' => 'Hiatus' ) ),
				array( 'key' => 'kb_nationality', 'label' => 'Nationality', 'type' => 'text', 'placeholder' => 'South Korea' ),
				array( 'key' => 'kb_generation', 'label' => 'Generation', 'type' => 'number' ),
				array( 'key' => 'kb_social_links', 'label' => 'Social links', 'type' => 'social' ),
			);
		case 'kb_member':
			return array(
				array( 'key' => 'kb_stage_name', 'label' => 'Stage name', 'type' => 'text' ),
				array( 'key' => 'kb_full_name', 'label' => 'Full name', 'type' => 'text' ),
				array( 'key' => 'kb_korean_name', 'label' => 'Korean name', 'type' => 'text' ),
				array( 'key' => 'kb_birthday', 'label' => 'Birthday', 'type' => 'date' ),
				array( 'key' => 'kb_nationality', 'label' => 'Nationality', 'type' => 'text', 'placeholder' => 'South Korea' ),
				array( 'key' => 'kb_group_slug', 'label' => 'Group', 'type' => 'artist_select' ),
				array( 'key' => 'kb_mbti', 'label' => 'MBTI', 'type' => 'text', 'placeholder' => 'ENFP', 'small' => true ),
				array( 'key' => 'kb_positions', 'label' => 'Positions', 'type' => 'csv', 'placeholder' => 'Leader, Main vocalist, Lead dancer' ),
				array( 'key' => 'kb_facts', 'label' => 'Facts', 'type' => 'lines', 'placeholder' => 'One fact per line' ),
			);
		case 'kb_comeback':
			return array(
				array( 'key' => 'kb_artist_slug', 'label' => 'Artist', 'type' => 'artist_select' ),
				array( 'key' => 'kb_type', 'label' => 'Type', 'type' => 'select', 'options' => array( 'album' => 'Album', 'single' => 'Single', 'mv' => 'Music video' ) ),
				array( 'key' => 'kb_release_at', 'label' => 'Release date', 'type' => 'date' ),
			);
		case 'kb_chart':
			return array(
				array( 'key' => 'kb_chart_id', 'label' => 'Chart ID', 'type' => 'text', 'placeholder' => 'weekly-global' ),
				array( 'key' => 'kb_week_start_date', 'label' => 'Week start date', 'type' => 'date' ),
				array( 'key' => 'kb_entries', 'label' => 'Entries', 'type' => 'chart_entries' ),
			);
		case 'kb_video':
			return array(
				array( 'key' => 'kb_youtube_id', 'label' => 'YouTube video ID', 'type' => 'text', 'placeholder' => 'dQw4w9WgXcQ' ),
				array( 'key' => 'kb_artist_slug', 'label' => 'Artist', 'type' => 'artist_select' ),
				array( 'key' => 'kb_video_category', 'label' => 'Category', 'type' => 'select', 'options' => array( 'MV' => 'Music video', 'Performance' => 'Performance', 'Interview' => 'Interview', 'Other' => 'Other' ) ),
				array( 'key' => 'kb_duration', 'label' => 'Duration', 'type' => 'text', 'placeholder' => '3:45' ),
			);
		case 'kb_thread':
			return array(
				array( 'key' => 'kb_category_slug', 'label' => 'Category slug', 'type' => 'text', 'placeholder' => 'general' ),
				array( 'key' => 'kb_flair', 'label' => 'Flair', 'type' => 'text' ),
				array( 'key' => 'kb_language', 'label' => 'Language', 'type' => 'text', 'placeholder' => 'en', 'small' => true ),
				array( 'key' => 'kb_rumor', 'label' => 'Marked as rumor', 'type' => 'checkbox' ),
				array( 'key' => 'kb_related_artist_slugs', 'label' => 'Related artists', 'type' => 'artist_multi' ),
			);
		case 'kb_poll':
			return array(
				array( 'key' => 'kb_ends_at', 'label' => 'Ends at (UTC)', 'type' => 'datetime' ),
				array( 'key' => 'kb_artist_slug', 'label' => 'Artist', 'type' => 'artist_select', 'allow_empty' => true ),
				array( 'key' => 'kb_options', 'label' => 'Options', 'type' => 'poll_options' ),
			);
		case 'kb_submission':
			return array(
				array( 'key' => 'kb_submission_type', 'label' => 'Submission type', 'type' => 'select', 'options' => array(
					'news-tip' => 'News tip', 'article-draft' => 'Article draft', 'artist-correction' => 'Artist correction',
					'comeback-event' => 'Comeback event', 'translation-request' => 'Translation request',
				) ),
			);
	}
	return array();
}

/* ---------- meta box registration ---------- */

function kpopblog_add_meta_boxes() {
	foreach ( array( 'post', 'kb_artist', 'kb_member', 'kb_comeback', 'kb_chart', 'kb_video', 'kb_thread', 'kb_poll', 'kb_submission' ) as $post_type ) {
		add_meta_box( 'kpopblog_details', 'KpopBlog Details', 'kpopblog_render_meta_box', $post_type, 'normal', 'high' );
	}
}
add_action( 'add_meta_boxes', 'kpopblog_add_meta_boxes' );

function kpopblog_render_meta_box( WP_Post $post ) {
	$fields = kpopblog_field_schema( $post->post_type );
	if ( ! $fields ) { return; }

	wp_nonce_field( 'kpopblog_save_meta_box', 'kpopblog_meta_box_nonce' );
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( $fields as $field ) {
		echo '<tr><th><label for="' . esc_attr( $field['key'] ) . '">' . esc_html( $field['label'] ) . '</label></th><td>';
		kpopblog_render_field( $post->ID, $field );
		echo '</td></tr>';
	}
	echo '</tbody></table>';
}

function kpopblog_render_field( $post_id, $field ) {
	$key   = $field['key'];
	$type  = $field['type'];
	$class = ! empty( $field['small'] ) ? 'small-text' : 'regular-text';

	switch ( $type ) {
		case 'text':
			printf(
				'<input type="text" name="%1$s" id="%1$s" value="%2$s" class="%3$s" placeholder="%4$s" />',
				esc_attr( $key ), esc_attr( kpopblog_meta_string( $post_id, $key ) ), esc_attr( $class ), esc_attr( $field['placeholder'] ?? '' )
			);
			break;

		case 'number':
			printf(
				'<input type="number" name="%1$s" id="%1$s" value="%2$s" class="small-text" placeholder="%3$s" />',
				esc_attr( $key ), esc_attr( kpopblog_meta_string( $post_id, $key ) ), esc_attr( $field['placeholder'] ?? '' )
			);
			break;

		case 'date':
			printf( '<input type="date" name="%1$s" id="%1$s" value="%2$s" />', esc_attr( $key ), esc_attr( kpopblog_meta_string( $post_id, $key ) ) );
			break;

		case 'datetime':
			$raw = kpopblog_meta_string( $post_id, $key );
			$val = $raw ? substr( $raw, 0, 16 ) : '';
			printf(
				'<input type="datetime-local" name="%1$s" id="%1$s" value="%2$s" /> <p class="description">Treated as UTC.</p>',
				esc_attr( $key ), esc_attr( $val )
			);
			break;

		case 'select':
			echo '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '">';
			$current = kpopblog_meta_string( $post_id, $key );
			foreach ( $field['options'] as $value => $label ) {
				printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $label ) );
			}
			echo '</select>';
			break;

		case 'checkbox':
			$checked = (bool) get_post_meta( $post_id, $key, true );
			printf(
				'<label><input type="checkbox" name="%1$s" id="%1$s" value="1" %2$s /> %3$s</label>',
				esc_attr( $key ), checked( $checked, true, false ), esc_html( $field['label'] )
			);
			break;

		case 'csv':
			$vals = (array) get_post_meta( $post_id, $key, true );
			printf(
				'<input type="text" name="%1$s" id="%1$s" value="%2$s" class="large-text" placeholder="%3$s" /> <p class="description">Comma-separated.</p>',
				esc_attr( $key ), esc_attr( implode( ', ', $vals ) ), esc_attr( $field['placeholder'] ?? '' )
			);
			break;

		case 'lines':
			$vals = (array) get_post_meta( $post_id, $key, true );
			printf(
				'<textarea name="%1$s" id="%1$s" rows="4" class="large-text" placeholder="%3$s">%2$s</textarea>',
				esc_attr( $key ), esc_textarea( implode( "\n", $vals ) ), esc_attr( $field['placeholder'] ?? '' )
			);
			break;

		case 'social':
			$links = (array) get_post_meta( $post_id, $key, true );
			foreach ( array( 'instagram', 'x', 'youtube', 'tiktok', 'website' ) as $platform ) {
				printf(
					'<p><label style="display:inline-block;width:90px;">%1$s</label><input type="text" name="%2$s[%3$s]" value="%4$s" class="regular-text" /></p>',
					esc_html( ucfirst( $platform ) ), esc_attr( $key ), esc_attr( $platform ), esc_attr( $links[ $platform ] ?? '' )
				);
			}
			break;

		case 'artist_select':
			$artists = kpopblog_all_artists();
			$current = kpopblog_meta_string( $post_id, $key );
			echo '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '">';
			if ( ! empty( $field['allow_empty'] ) || ! $current ) {
				echo '<option value="">— none —</option>';
			}
			foreach ( $artists as $artist ) {
				printf( '<option value="%s" %s>%s</option>', esc_attr( $artist->post_name ), selected( $current, $artist->post_name, false ), esc_html( $artist->post_title ) );
			}
			echo '</select>';
			if ( ! $artists ) {
				echo '<p class="description">No artists yet — create one under KpopBlog Artists first.</p>';
			}
			break;

		case 'artist_multi':
			$artists  = kpopblog_all_artists();
			$selected = (array) get_post_meta( $post_id, $key, true );
			if ( ! $artists ) {
				echo '<p class="description">No artists yet — create one under KpopBlog Artists first.</p>';
				break;
			}
			echo '<div style="max-height:160px;overflow:auto;border:1px solid #dcdcde;padding:8px;">';
			foreach ( $artists as $artist ) {
				printf(
					'<label style="display:block;"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s /> %4$s</label>',
					esc_attr( $key ), esc_attr( $artist->post_name ), checked( in_array( $artist->post_name, $selected, true ), true, false ), esc_html( $artist->post_title )
				);
			}
			echo '</div>';
			break;

		case 'chart_entries':
			$rows  = (array) get_post_meta( $post_id, $key, true );
			$lines = array_map( function ( $r ) {
				return implode( '|', array( $r['rank'] ?? '', $r['artistSlug'] ?? '', $r['trackTitle'] ?? '', $r['previousRank'] ?? '', $r['weeksOnChart'] ?? '' ) );
			}, $rows );
			printf(
				'<textarea name="%1$s" id="%1$s" rows="8" class="large-text code">%2$s</textarea>' .
				'<p class="description">One entry per line: <code>rank|artistSlug|trackTitle|previousRank|weeksOnChart</code>. Leave previousRank empty for a new entry.</p>',
				esc_attr( $key ), esc_textarea( implode( "\n", $lines ) )
			);
			break;

		case 'poll_options':
			$rows  = (array) get_post_meta( $post_id, $key, true );
			$lines = array_map( function ( $r ) {
				return implode( '|', array( $r['id'] ?? '', $r['label'] ?? '', $r['votes'] ?? 0 ) );
			}, $rows );
			printf(
				'<textarea name="%1$s" id="%1$s" rows="6" class="large-text code">%2$s</textarea>' .
				'<p class="description">One option per line: <code>id|label|votes</code>. Leave id empty to auto-generate from the label; leave votes empty to start at 0.</p>',
				esc_attr( $key ), esc_textarea( implode( "\n", $lines ) )
			);
			break;
	}
}

/* ---------- save ---------- */

function kpopblog_save_meta_boxes( $post_id, $post ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( wp_is_post_revision( $post_id ) ) { return; }
	if ( ! isset( $_POST['kpopblog_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['kpopblog_meta_box_nonce'], 'kpopblog_save_meta_box' ) ) { return; }
	if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

	$fields = kpopblog_field_schema( $post->post_type );
	if ( ! $fields ) { return; }

	foreach ( $fields as $field ) {
		kpopblog_save_field( $post_id, $field );
	}
}
add_action( 'save_post', 'kpopblog_save_meta_boxes', 10, 2 );

function kpopblog_post_field_string( $key ) {
	return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
}

function kpopblog_save_field( $post_id, $field ) {
	$key  = $field['key'];
	$type = $field['type'];

	switch ( $type ) {
		case 'text':
		case 'date':
		case 'select':
			update_post_meta( $post_id, $key, kpopblog_post_field_string( $key ) );
			break;

		case 'datetime':
			$raw = kpopblog_post_field_string( $key ); // "2026-08-01T14:30"
			update_post_meta( $post_id, $key, $raw ? str_replace( 'T', ' ', $raw ) . ':00Z' : '' );
			break;

		case 'number':
			update_post_meta( $post_id, $key, isset( $_POST[ $key ] ) && $_POST[ $key ] !== '' ? (int) $_POST[ $key ] : '' );
			break;

		case 'checkbox':
			update_post_meta( $post_id, $key, isset( $_POST[ $key ] ) ? 1 : 0 );
			break;

		case 'csv':
			$raw   = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
			$parts = array_filter( array_map( 'sanitize_text_field', array_map( 'trim', explode( ',', $raw ) ) ) );
			update_post_meta( $post_id, $key, array_values( $parts ) );
			break;

		case 'lines':
			$raw   = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
			$parts = array_filter( array_map( 'sanitize_text_field', array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw ) ) ) );
			update_post_meta( $post_id, $key, array_values( $parts ) );
			break;

		case 'social':
			$raw = isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ? $_POST[ $key ] : array();
			$out = array();
			foreach ( $raw as $platform => $url ) {
				$platform = sanitize_key( $platform );
				$url      = is_string( $url ) ? esc_url_raw( wp_unslash( $url ) ) : '';
				if ( $platform && $url ) { $out[ $platform ] = $url; }
			}
			update_post_meta( $post_id, $key, $out );
			break;

		case 'artist_select':
			update_post_meta( $post_id, $key, sanitize_title( kpopblog_post_field_string( $key ) ) );
			break;

		case 'artist_multi':
			$raw = isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ? $_POST[ $key ] : array();
			$out = array_filter( array_map( 'sanitize_title', $raw ) );
			update_post_meta( $post_id, $key, array_values( $out ) );
			break;

		case 'chart_entries':
			$raw   = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
			$lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw ) ) );
			$rows  = array();
			foreach ( $lines as $line ) {
				$parts = array_map( 'trim', explode( '|', $line ) );
				if ( count( $parts ) < 3 || $parts[0] === '' ) { continue; }
				$rows[] = array(
					'rank'         => (int) $parts[0],
					'artistSlug'   => sanitize_title( $parts[1] ?? '' ),
					'trackTitle'   => sanitize_text_field( $parts[2] ?? '' ),
					'previousRank' => isset( $parts[3] ) && $parts[3] !== '' ? (int) $parts[3] : null,
					'weeksOnChart' => isset( $parts[4] ) && $parts[4] !== '' ? (int) $parts[4] : 1,
				);
			}
			update_post_meta( $post_id, $key, $rows );
			break;

		case 'poll_options':
			$raw   = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
			$lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw ) ) );
			$rows  = array();
			foreach ( $lines as $line ) {
				$parts = array_map( 'trim', explode( '|', $line ) );
				if ( count( $parts ) < 2 || $parts[1] === '' ) { continue; }
				$id     = $parts[0] !== '' ? sanitize_key( $parts[0] ) : sanitize_key( $parts[1] );
				$rows[] = array(
					'id'    => $id,
					'label' => sanitize_text_field( $parts[1] ),
					'votes' => isset( $parts[2] ) && $parts[2] !== '' ? max( 0, (int) $parts[2] ) : 0,
				);
			}
			update_post_meta( $post_id, $key, $rows );
			break;
	}
}
