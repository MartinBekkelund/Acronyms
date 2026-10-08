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

$acronyms_table_name = $wpdb->prefix . 'acronyms';
$wpdb->query( "DROP TABLE IF EXISTS {$acronyms_table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Dropping the plugin table on uninstall.

delete_option( 'acronyms_post_types' );
delete_option( 'acronyms_db_version' );

delete_transient( 'acronyms_list' );
