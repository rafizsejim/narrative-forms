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

/** Is PRO active */
function nrfm_is_pro_active() {
	return defined( 'NRFM_PRO_VERSION' );
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
 * Return strict whitelist for core plugin tables.
 */
function nrfm_get_allowed_tables() {
	global $wpdb;
	return array(
		'submissions' => $wpdb->prefix . 'nrfm_submissions',
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
