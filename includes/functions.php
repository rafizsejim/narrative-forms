<?php
/** Helper functions */

defined( 'ABSPATH' ) || exit;

/** Get submission count for a form */
function nrfm_get_submissions_count( $form_id ) {
	global $wpdb;

	$table = nrfm_get_valid_table( 'submissions' );
	if ( $table === '' ) {
		return 0;
	}
	// Try object cache first
	$ckey   = 'nrfm_subs_count_' . intval( $form_id );
	$cached = wp_cache_get( $ckey, 'nrfm' );
	if ( $cached !== false ) {
		return intval( $cached ); }

	// Use %i for table identifier (WP 6.2+)
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$count = $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM %i WHERE form_id = %d',
			$table,
			$form_id
		)
	);
	wp_cache_set( $ckey, intval( $count ), 'nrfm', 60 );

	return intval( $count );
}

/**
 * Helpers (DRY consolidation)
 */
function nrfm_can_manage() {
	/**
	 * Filter capability used to manage Narrative Forms admin actions.
	 */
	$cap = apply_filters( 'nrfm_manage_capability', 'manage_options' );
	return current_user_can( $cap );
}

function nrfm_table( $key ) {
	global $wpdb;
	$key = preg_replace( '/[^a-z0-9_]/i', '', $key );
	return $wpdb->prefix . 'nrfm_' . $key;
}

/**
 * Read a sanitized admin request value ($_GET by default, or $_REQUEST).
 *
 * A small helper for admin screen logic. Nonce-verified state changes must still
 * verify their own nonce; this is for reading navigation context (page/tab/action).
 *
 * @param string     $key     Request key.
 * @param string|int $default Default value; its type also coerces the return type.
 * @param string     $source  'get' (default) or 'request'.
 * @return string|int|array
 */
function nrfm_admin_request( $key, $default = '', $source = 'get' ) {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- reads navigation context only.
	$bag = ( $source === 'request' ) ? $_REQUEST : $_GET;
	if ( ! isset( $bag[ $key ] ) ) {
		return $default;
	}
	$value = wp_unslash( $bag[ $key ] );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	if ( is_array( $default ) ) {
		return is_array( $value ) ? $value : $default;
	}
	if ( is_int( $default ) ) {
		return (int) $value;
	}
	return sanitize_text_field( $value );
}

/**
 * Is the current admin screen a Narrative Forms form-edit page?
 *
 * Used by admin modules to scope their asset enqueues to the form editor only
 * (Rule 3: load assets only where they are needed).
 *
 * @param string $hook The admin page hook passed to admin_enqueue_scripts.
 * @return bool
 */
function nrfm_is_form_edit_screen( $hook ) {
	if ( strpos( (string) $hook, 'nrfm-forms' ) === false ) {
		return false;
	}
	// Screen navigation only; not a state change, so no nonce is required here.
	$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return $action === 'edit';
}

/**
 * Base slug for hosted/share form links, e.g. the "forms" in /forms/your-form.
 * Stored as `pro_share_base` inside nrfm_settings (key kept for backward compat).
 *
 * @return string A sanitized, non-empty slug (default "forms").
 */
function nrfm_get_share_base() {
	$settings = function_exists( 'nrfm_get_settings' ) ? nrfm_get_settings() : get_option( 'nrfm_settings', array() );
	$base = isset( $settings['pro_share_base'] ) ? $settings['pro_share_base'] : 'forms';
	$base = apply_filters( 'nrfm_share_base', $base );
	$base = is_string( $base ) ? trim( $base ) : 'forms';
	$base = sanitize_title( $base );
	$base = trim( $base, "/ \t\n\r\0\x0B" );
	return $base !== '' ? $base : 'forms';
}

/**
 * Render a consistent frontend message block (used when a form is blocked by
 * require-login or schedule windows, or for share errors).
 *
 * @param string $message   Message text (escaped).
 * @param string $link_url  Optional link URL.
 * @param string $link_text Optional link text (escaped).
 * @return string
 */
function nrfm_error_message_html( $message, $link_url = '', $link_text = '' ) {
	$html = '<div class="nrfm-message nrfm-error">' . esc_html( (string) $message );
	if ( $link_url !== '' && $link_text !== '' ) {
		$html .= ' <a href="' . esc_url( $link_url ) . '">' . esc_html( $link_text ) . '</a>';
	}
	$html .= '</div>';
	return $html;
}

/**
 * Return strict whitelist for core plugin tables.
 */
function nrfm_get_allowed_tables() {
	global $wpdb;
	return array(
		'submissions'   => $wpdb->prefix . 'nrfm_submissions',
		'resume_drafts' => $wpdb->prefix . 'nrfm_resume_drafts',
		'webhook_logs'  => $wpdb->prefix . 'nrfm_webhook_logs',
	);
}

/**
 * Return a validated table name for known keys, or empty string.
 */
function nrfm_get_valid_table( $key ) {
	$key = preg_replace( '/[^a-z0-9_]/i', '', (string) $key );
	$allowed = nrfm_get_allowed_tables();
	$table = nrfm_table( $key );
	if ( ! isset( $allowed[ $key ] ) || $table !== $allowed[ $key ] ) {
		return '';
	}
	return $table;
}

/**
 * Clear cached submission counters for one form.
 */
function nrfm_clear_submission_cache( $form_id ) {
	$form_id = (int) $form_id;
	if ( $form_id <= 0 ) {
		return;
	}
	wp_cache_delete( 'nrfm_subs_count_' . $form_id, 'nrfm' );
	wp_cache_delete( 'nrfm_subs_total_' . $form_id, 'nrfm' );
}

/**
 * Enqueue async action processing.
 * Uses Action Scheduler when available, falls back to WP-Cron.
 */
function nrfm_enqueue_actions_job( $job ) {
	// Prefer Action Scheduler if available
	if ( function_exists( 'as_schedule_single_action' ) ) {
		$timestamp = time() + 5;
		// Group under 'nrfm' for easy tracking
		as_schedule_single_action( $timestamp, 'nrfm_process_actions_async', array( $job ), 'nrfm' );
		return;
	}
	// Fallback to WP-Cron
	wp_schedule_single_event( time() + 5, 'nrfm_process_actions_async', array( $job ) );
}

/** Get plugin settings */
function nrfm_get_settings( $key = null, $default = null ) {
	$settings = get_option( 'nrfm_settings', array() ); // used for read-only; leaving as-is for backward compat

	$defaults = array(
		'load_stylesheet' => 1,
	);

	$settings = wp_parse_args( $settings, $defaults );

	if ( $key !== null ) {
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	return $settings;
}
