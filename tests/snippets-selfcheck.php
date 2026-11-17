<?php
// php tests/snippets-selfcheck.php — بدون وردپرس: lint، حذف <?php، و پاک‌سازی ۲۷ اسنیپت قدیمی.
define( 'ABSPATH', __DIR__ );
foreach ( array( 'add_action', 'add_filter' ) as $f ) {
	eval( "function $f() {}" );
}
function is_admin() { return false; }
$m = dirname( __DIR__ ) . '/modules/snippets/';
require $m . 'techpino-snippets.php';
require $m . 'migrate.php';

assert( '' === TPSN_Loader::lint( 'add_action( "init", function () { echo 1; } );' ) );
assert( '' !== TPSN_Loader::lint( 'function x( { }' ) );
assert( false !== strpos( TPSN_Loader::lint( "\$a = 1;\n\$b = ;" ), '(خط 2)' ) );
assert( 'echo 1;' === TPSN_Loader::php_body( "  <?php\necho 1;" ) );
assert( 'echo "<?php";' === TPSN_Loader::php_body( 'echo "<?php";' ) );

$list = include $m . 'legacy/list.php';
assert( 27 === count( $list ) );
foreach ( $list as $id => $s ) {
	$raw  = file_get_contents( $m . "legacy/snippets/$id.php" );
	$code = TPSN_Migrate::clean( $raw );
	assert( 0 !== strpos( ltrim( $code ), '<?php' ), "#$id still starts with <?php" );
	assert( false === strpos( substr( $code, 0, 200 ), "defined( 'ABSPATH' )" ), "#$id header guard left" );
	assert( '' === TPSN_Loader::lint( TPSN_Loader::php_body( $code ) ), "#$id fails lint: " . TPSN_Loader::lint( $code ) );
	assert( strlen( $code ) > strlen( $raw ) - 200, "#$id lost code" );
}
echo "ok\n";
