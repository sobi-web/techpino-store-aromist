<?php
/**
 * Installation: database table and default options.
 *
 * @package AromistVolumeStock
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates / upgrades the ledger table.
 */
final class AVS_Install {

	/**
	 * Ledger table name (with prefix).
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'avs_ledger';
	}

	/**
	 * Activation hook.
	 */
	public static function activate() {
		self::install();
	}

	/**
	 * Run the installer when the stored DB version is older than the code.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'avs_db_version' ) !== AVS_DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Create the table and default options.
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		/*
		 * One row per stock movement of a volume pool.
		 * change_ml is signed (negative = taken from the pool), balance_ml is the pool right after the change.
		 */
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL,
			variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			order_item_id bigint(20) unsigned NOT NULL DEFAULT 0,
			quantity int(11) NOT NULL DEFAULT 0,
			change_ml int(11) NOT NULL DEFAULT 0,
			balance_ml int(11) NULL,
			reason varchar(32) NOT NULL DEFAULT '',
			note text NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY product_date (product_id,created_at),
			KEY reason_date (reason,created_at),
			KEY order_id (order_id)
		) {$charset_collate};";

		dbDelta( $sql );

		add_option( 'avs_settings', AVS_Settings::defaults(), '', false );
		update_option( 'avs_db_version', AVS_DB_VERSION, false );
	}
}
