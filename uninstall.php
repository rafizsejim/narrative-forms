<?php
/**
 * Narrative Forms Uninstall
 *
 * Runs when the plugin is deleted through WordPress admin
 */

// Exit if accessed directly or not called from WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Respect setting/constant: keep data by default
$nrfm_purge = false;
// Allow site owners to force purge via constant
if ( defined( 'NRFM_PURGE_ON_UNINSTALL' ) && NRFM_PURGE_ON_UNINSTALL ) {
	$nrfm_purge = true;
} else {
	$nrfm_opt = get_option( 'nrfm_settings', array() );
	if ( ! empty( $nrfm_opt['purge_on_uninstall'] ) ) {
		$nrfm_purge = true;
	}
}

if ( $nrfm_purge ) {
	// Delete options
	delete_option( 'nrfm_settings' );
	delete_option( 'nrfm_starter_form_created' );
	delete_option( 'nrfm_schema_version' );

	// Delete all forms (custom post type)
	$nrfm_forms = get_posts(
		array(
			'post_type'      => 'nrfm_form',
			'posts_per_page' => -1,
			'post_status'    => 'any',
		)
	);
	foreach ( $nrfm_forms as $nrfm_form ) {
		wp_delete_post( $nrfm_form->ID, true );
	}

	// Drop submissions table
	global $wpdb;
	$nrfm_table_name = function_exists( 'nrfm_table' ) ? nrfm_table( 'submissions' ) : ( $wpdb->prefix . 'nrfm_submissions' );
	// Table name is derived from prefix and a hardcoded key; schema changes are allowed during uninstall
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			'DROP TABLE IF EXISTS %i',
			$nrfm_table_name
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	// Clear any transients
	$nrfm_sql = $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_nrfm_%' );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( $nrfm_sql );
	$nrfm_sql2 = $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_nrfm_%' );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( $nrfm_sql2 );
}

// Clear scheduled hooks if any (none currently scheduled)
