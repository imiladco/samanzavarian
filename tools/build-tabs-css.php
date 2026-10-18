<?php
/**
 * Writes the generated part of assets/css/widgets/service-tabs.css — the
 * default ("side") layout family — from Service_Tabs_Layout, the same class
 * that feeds the Layout control.
 *
 *   php tools/build-tabs-css.php           rewrite the block
 *   php tools/build-tabs-css.php --check   exit 1 if it has drifted
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

require dirname( __DIR__ ) . '/includes/Widgets/Service_Tabs_Layout.php';

use Samanzavarian_Widgets\Widgets\Service_Tabs_Layout;

$file = dirname( __DIR__ ) . '/assets/css/widgets/service-tabs.css';
$css  = file_get_contents( $file );

// Desktop only; other breakpoints come from the Layout control's defaults.
$block = "/* BEGIN GENERATED */\n.sz-tabs{" . Service_Tabs_Layout::vars( 'side' ) . "}\n/* END GENERATED */";

$next = preg_replace( '#/\* BEGIN GENERATED \*/.*?/\* END GENERATED \*/#s', $block, $css, 1, $count );

if ( 1 !== $count ) {
	fwrite( STDERR, "markers not found in service-tabs.css\n" );
	exit( 1 );
}

if ( in_array( '--check', $argv, true ) ) {
	$ok = $next === $css;
	fwrite( STDOUT, $ok ? "service-tabs.css in sync\n" : "service-tabs.css OUT OF SYNC — run: php tools/build-tabs-css.php\n" );
	exit( $ok ? 0 : 1 );
}

file_put_contents( $file, $next );
fwrite( STDOUT, "service-tabs.css generated block written\n" );
