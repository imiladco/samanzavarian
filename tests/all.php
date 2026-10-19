<?php
/**
 * Every test file, one total. Gate a commit on this:
 *
 *   wp eval-file tests/all.php
 */

require_once __DIR__ . '/lib.php';

$GLOBALS['szw_nested'] = true;

foreach ( [ 'plugin-test.php', 'process-test.php', 'service-test.php', 'work-test.php', 'stack-test.php', 'pills-test.php', 'stats-test.php', 'highlights-test.php' ] as $file ) {
	echo "\n━━ " . $file . " ━━\n";
	require __DIR__ . '/' . $file;
}

$GLOBALS['szw_nested'] = false;
szw_finish();
