<?php
/**
 * Settings (option `ard_settings`) and the Jalali date helpers the settings page needs.
 *
 * @package AromistDeals
 */

defined( 'ABSPATH' ) || exit;

final class ARD_Settings {

	const OPTION = 'ard_settings';

	public static function defaults(): array {
		return array(
			// Badge + struck-through price next to every sale price on the site (cards, carousels, search…).
			'badge'          => 'yes',
			// Cart page, checkout and mini-cart: badge + old price on discounted lines.
			'cart_badge'     => 'yes',
			// Product cards (the site's size circles): mark discounted sizes, select the discounted size first.
			'card_mark'      => 'yes',
			'card_select'    => 'yes',
			// Product page: countdown under the price, «٪» tag on discounted sizes, open on the discounted size.
			'pdp_timer'      => 'yes',
			'pdp_mark'       => 'yes',
			'pdp_select'     => 'yes',
			// Timer fallback for sales without their own end date (0 = no timer for them).
			'campaign_start' => 0,
			'campaign_end'   => 0,
			// Progress bar: days the bar covers when a sale has an end date but no start date.
			'period_days'    => 7,
			// remaining = the bar empties as the end gets closer; elapsed = it fills up.
			'progress'       => 'remaining',
			// Digits in prices: all = Persian digits in every price on the site (font feature, the text is
			// unchanged), deal = only in this plugin's parts, latin = Latin digits everywhere.
			'digits'         => 'all',
			'badge_bg'       => '#D7B56A',
			'badge_fg'       => '#1D1D1B',
		);
	}

	public static function all(): array {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * @param string $key Setting name.
	 * @return mixed
	 */
	public static function get( string $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	public static function on( string $key ): bool {
		return 'yes' === self::get( $key );
	}

	/** Campaign window when it is still running, else [0, 0]. */
	public static function campaign(): array {
		$end = (int) self::get( 'campaign_end' );
		if ( $end <= time() ) {
			return array( 0, 0 );
		}
		return array( (int) self::get( 'campaign_start' ), $end );
	}

	/* ---------------------------------------------------------------------
	 * Dates: the admin types Jalali («۱۴۰۵/۰۸/۱۰ ۲۳:۵۹»); Gregorian works too.
	 * ------------------------------------------------------------------ */

	public static function en_digits( string $s ): string {
		return strtr(
			$s,
			array(
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			)
		);
	}

	public static function fa_digits( string $s ): string {
		return strtr( $s, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
	}

	/** «1405/08/10 23:59» or «2026-11-01 23:59» (site time zone) → timestamp; 0 when empty or invalid. */
	public static function parse_date( string $input ): int {
		$input = trim( self::en_digits( $input ) );
		if ( ! preg_match( '#^(\d{4})[/\-.](\d{1,2})[/\-.](\d{1,2})(?:\s+(\d{1,2})(?::(\d{1,2}))?)?$#', $input, $m ) ) {
			return 0;
		}
		list( $y, $mo, $d ) = array( (int) $m[1], (int) $m[2], (int) $m[3] );
		$h   = isset( $m[4] ) ? min( 23, (int) $m[4] ) : 23;
		$min = isset( $m[5] ) ? min( 59, (int) $m[5] ) : ( isset( $m[4] ) ? 0 : 59 );
		if ( $y < 1700 ) {
			if ( $mo < 1 || $mo > 12 || $d < 1 || $d > ( $mo <= 6 ? 31 : 30 ) ) {
				return 0;
			}
			list( $y, $mo, $d ) = self::jalali_to_gregorian( $y, $mo, $d );
		}
		if ( ! checkdate( $mo, $d, $y ) ) {
			return 0;
		}
		$date = date_create_immutable( sprintf( '%04d-%02d-%02d %02d:%02d:00', $y, $mo, $d, $h, $min ), wp_timezone() );
		return $date ? $date->getTimestamp() : 0;
	}

	/** Timestamp → «۱۴۰۵/۰۸/۱۰ ۲۳:۵۹» in the site time zone. */
	public static function format_date( int $ts, bool $time = true ): string {
		if ( $ts <= 0 ) {
			return '';
		}
		$dt = ( new DateTimeImmutable( '@' . $ts ) )->setTimezone( wp_timezone() );
		list( $jy, $jm, $jd ) = self::gregorian_to_jalali( (int) $dt->format( 'Y' ), (int) $dt->format( 'n' ), (int) $dt->format( 'j' ) );
		$out = sprintf( '%04d/%02d/%02d', $jy, $jm, $jd ) . ( $time ? ' ' . $dt->format( 'H:i' ) : '' );
		return self::fa_digits( $out );
	}

	/** jdf algorithm. */
	public static function gregorian_to_jalali( int $gy, int $gm, int $gd ): array {
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days  = 355666 + ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) + $gd + $g_d_m[ $gm - 1 ];
		$jy    = -1595 + ( 33 * intdiv( $days, 12053 ) );
		$days %= 12053;
		$jy   += 4 * intdiv( $days, 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$jy  += intdiv( $days - 1, 365 );
			$days = ( $days - 1 ) % 365;
		}
		if ( $days < 186 ) {
			$jm = 1 + intdiv( $days, 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + intdiv( $days - 186, 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}
		return array( $jy, $jm, $jd );
	}

	/** jdf algorithm. */
	public static function jalali_to_gregorian( int $jy, int $jm, int $jd ): array {
		$jy  += 1595;
		$days = -355668 + ( 365 * $jy ) + ( intdiv( $jy, 33 ) * 8 ) + intdiv( ( $jy % 33 ) + 3, 4 ) + $jd + ( ( $jm < 7 ) ? ( $jm - 1 ) * 31 : ( ( $jm - 7 ) * 30 ) + 186 );
		$gy   = 400 * intdiv( $days, 146097 );
		$days %= 146097;
		if ( $days > 36524 ) {
			--$days;
			$gy  += 100 * intdiv( $days, 36524 );
			$days %= 36524;
			if ( $days >= 365 ) {
				++$days;
			}
		}
		$gy   += 4 * intdiv( $days, 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$gy  += intdiv( $days - 1, 365 );
			$days = ( $days - 1 ) % 365;
		}
		$gd    = $days + 1;
		$leap  = ( 0 === $gy % 4 && 0 !== $gy % 100 ) || 0 === $gy % 400;
		$month = array( 0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		for ( $gm = 0; $gm < 13 && $gd > $month[ $gm ]; $gm++ ) {
			$gd -= $month[ $gm ];
		}
		return array( $gy, $gm, $gd );
	}
}
