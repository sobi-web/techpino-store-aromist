<?php
/**
 * انتقال یک‌باره‌ی اسنیپت‌های قدیمی (legacy/list.php + legacy/snippets/{id}.php) به بخش «اسنیپت‌ها».
 * ترتیب اجرا، روشن/خاموش بودن (از پنل تکپینو)، «فقط پیشخوان» و شورت‌کدهای هر اسنیپت حفظ می‌شود.
 */

defined( 'ABSPATH' ) || exit;

final class TPSN_Migrate {

	public static function run() {
		// add_option fails when the row exists, so only one request ever migrates.
		if ( ! add_option( TPSN_Loader::MIGRATED, 'running', '', false ) ) {
			return;
		}
		$dir    = __DIR__ . '/legacy/';
		$list   = is_file( $dir . 'list.php' ) ? (array) include $dir . 'list.php' : array();
		$hidden = class_exists( 'Techpino_Panel' ) ? (array) Techpino_Panel::opt()['hidden'] : array();
		$order  = 0;

		foreach ( $list as $id => $s ) {
			$file = $dir . 'snippets/' . $id . '.php';
			if ( ! is_file( $file ) ) {
				continue;
			}
			$order += 10;
			wp_insert_post(
				array(
					'post_type'   => TPSN_Loader::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => wp_slash( $s['name'] ),
					'meta_input'  => wp_slash(
						array(
							'_tpsn_type'   => 'php',
							'_tpsn_scope'  => $s['admin'] ? 'admin' : 'all',
							'_tpsn_order'  => $order,
							'_tpsn_active' => isset( $hidden[ 'x:' . $id ] ) ? '0' : '1',
							'_tpsn_code'   => self::clean( (string) file_get_contents( $file ) ),
							'_tpsn_sig'    => $s['sig'],
							'_tpsn_tags'   => $s['tags'],
							'_tpsn_legacy' => (int) $id,
						)
					),
				)
			);
		}

		TPSN_Loader::rebuild();
		update_option( TPSN_Loader::MIGRATED, TPSN_VERSION, false );
	}

	/** Drop the three header lines the export added: <?php, "// اسنیپت #N: name", and the ABSPATH guard. */
	public static function clean( $code ) {
		return (string) preg_replace(
			array( '/^<\?php[ \t]*\r?\n/', '/^\/\/ اسنیپت #\d+:[^\n]*\n/u', "/^defined\\( 'ABSPATH' \\) \\|\\| exit;[ \t]*\r?\n(\r?\n)?/" ),
			'',
			$code,
			1
		);
	}
}
