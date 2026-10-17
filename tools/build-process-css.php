<?php
/**
 * Writes the generated part of assets/css/widgets/process.css — the layout
 * pattern for the design's default columns and the 12 per-position rules —
 * from Process_Layout, the same class that feeds the Columns control.
 *
 *   php tools/build-process-css.php           rewrite the block
 *   php tools/build-process-css.php --check   exit 1 if it has drifted
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

require dirname( __DIR__ ) . '/includes/Widgets/Process_Layout.php';

use Samanzavarian_Widgets\Widgets\Process_Layout;

$file = dirname( __DIR__ ) . '/assets/css/widgets/process.css';
$css  = file_get_contents( $file );

$block = "/* BEGIN GENERATED */\n"
	// The design: four columns, then two at ≤900px.
	. '.sz-process{' . Process_Layout::vars( 4 ) . "}\n"
	. '@media (max-width:900px){.sz-process{' . Process_Layout::vars( 2 ) . "}}\n"
	. Process_Layout::item_rules()
	. '/* END GENERATED */';

$next = preg_replace( '#/\* BEGIN GENERATED \*/.*?/\* END GENERATED \*/#s', $block, $css, 1, $count );

if ( 1 !== $count ) {
	fwrite( STDERR, "markers not found in process.css\n" );
	exit( 1 );
}

if ( in_array( '--check', $argv, true ) ) {
	$ok = $next === $css;
	fwrite( STDOUT, $ok ? "process.css in sync\n" : "process.css OUT OF SYNC — run: php tools/build-process-css.php\n" );
	exit( $ok ? 0 : 1 );
}

file_put_contents( $file, $next );
fwrite( STDOUT, "process.css generated block written\n" );
