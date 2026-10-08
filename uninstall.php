<?php
/**
 * Uninstall handler for the Acronyms plugin.
 *
 * Removes all traces: custom table, options, and transients.
 *
 * @package Acronyms
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$table_name = $wpdb->prefix . 'acronyms';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

delete_option( 'acronyms_post_types' );
delete_option( 'acronyms_db_version' );

delete_transient( 'acronyms_list' );
