<?php
/**
 * Uninstall.
 *
 * Product data is never touched here: products that still have volume stock keep their pool
 * as the parent's stock. Disable volume stock on every product from the panel BEFORE removing
 * the plugin. The ledger and settings are only deleted when "delete data on uninstall" is on.
 *
 * @package AromistVolumeStock
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$avs_settings = get_option( 'avs_settings', array() );

if ( is_array( $avs_settings ) && isset( $avs_settings['delete_on_uninstall'] ) && 'yes' === $avs_settings['delete_on_uninstall'] ) {
	global $wpdb;
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}avs_ledger" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	delete_option( 'avs_settings' );
	delete_option( 'avs_db_version' );
}
