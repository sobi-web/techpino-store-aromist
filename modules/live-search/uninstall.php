<?php
/**
 * Removes the stored index and its file.
 *
 * @package TechpinoLiveSearch
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'tpls_index' );
delete_option( 'tpls_index_data' );
delete_option( 'tpls_version' );
delete_option( 'tpls_settings' );

$tpls_uploads = wp_upload_dir( null, false );
$tpls_dir     = trailingslashit( $tpls_uploads['basedir'] ) . 'techpino-live-search';
wp_delete_file( $tpls_dir . '/index.json' );
@rmdir( $tpls_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- fine if it is not empty.
