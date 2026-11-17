<?php
/**
 * Uninstall.
 *
 * Products are never touched: ready-made sets, the «ست سفارشی» product and the box products stay
 * (move sets to draft or delete them yourself BEFORE removing the plugin). Only the settings and
 * the scheduled sync are removed.
 *
 * @package AromistDiscoverySet
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

wp_clear_scheduled_hook( 'ads_daily_sync' );
delete_option( 'ads_setup_version' );
