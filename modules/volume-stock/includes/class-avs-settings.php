<?php
/**
 * Plugin settings.
 *
 * @package AromistVolumeStock
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings stored in the `avs_settings` option.
 */
final class AVS_Settings {

	/**
	 * Request-level cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'unit_label'          => 'میل',
			'default_low_ml'      => 50,
			'show_units_in_stock' => 'yes',
			'order_notes'         => 'yes',
			'delete_on_uninstall' => 'no',
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( 'avs_settings', array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * Single setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Sanitize and save.
	 *
	 * @param array $input Raw input.
	 */
	public static function save( array $input ) {
		$clean = array(
			'unit_label'          => sanitize_text_field( isset( $input['unit_label'] ) ? $input['unit_label'] : 'میل' ),
			'default_low_ml'      => absint( isset( $input['default_low_ml'] ) ? $input['default_low_ml'] : 0 ),
			'show_units_in_stock' => ! empty( $input['show_units_in_stock'] ) ? 'yes' : 'no',
			'order_notes'         => ! empty( $input['order_notes'] ) ? 'yes' : 'no',
			'delete_on_uninstall' => ! empty( $input['delete_on_uninstall'] ) ? 'yes' : 'no',
		);

		if ( '' === $clean['unit_label'] ) {
			$clean['unit_label'] = 'میل';
		}

		update_option( 'avs_settings', $clean, false );
		self::$cache = null;
	}

	/**
	 * Signed amount as HTML safe for RTL: <span dir="ltr">+1,250</span> میل.
	 *
	 * @param int $ml Signed amount.
	 * @return string
	 */
	public static function signed_ml_html( $ml ) {
		$ml   = (int) $ml;
		$sign = $ml > 0 ? '+' : ( $ml < 0 ? '−' : '' );
		return '<span dir="ltr" class="avs-num">' . esc_html( $sign . number_format_i18n( abs( $ml ) ) ) . '</span> ' . esc_html( self::get( 'unit_label' ) );
	}

	/**
	 * Format a millilitre amount for display, e.g. "۱٬۲۵۰ میل".
	 *
	 * @param int|float $ml Amount.
	 * @return string
	 */
	public static function format_ml( $ml ) {
		return number_format_i18n( (float) $ml ) . ' ' . self::get( 'unit_label' );
	}
}
