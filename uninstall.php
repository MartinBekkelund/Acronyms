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
$wpdb->query( "DROP TABLE IF EXISTS {$acronyms_table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is built from $wpdb->prefix only. Dropping the plugin table on uninstall.

delete_option( 'acronyms_post_types' );
delete_option( 'acronyms_db_version' );

delete_transient( 'acronyms_list' );

delete_option( 'acronyms_central_remote_enabled' );
delete_option( 'acronyms_central_url' );
delete_option( 'acronyms_central_list' );
delete_option( 'acronyms_central_status' );
delete_option( 'acronyms_central_excluded' );

wp_clear_scheduled_hook( 'acronyms_central_refresh' );
