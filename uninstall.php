<?php
// Runs every module's own uninstall.php (the same clean-up each old plugin did when deleted).
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( (array) glob( __DIR__ . '/modules/*/uninstall.php' ) as $tp_file ) {
	include $tp_file;
}
