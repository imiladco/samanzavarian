<?php
/**
 * Toolkit (sz-toolkit).
 *
 *   wp eval-file tests/toolkit-test.php
 */

use Samanzavarian_Widgets\Widgets\Toolkit;

require_once __DIR__ . '/lib.php';

szw_group( 'Toolkit' );

$html = szw_render( [], 'sz-toolkit' );
szw_ok( 3 === substr_count( $html, 'class="sz-tk__row' ) && 12 === substr_count( $html, '<li style="--i:' ), 'the design\'s three rows: education, then 4 and 8 tools' );
szw_ok( false !== strpos( $html, '<b class="sz-tk__title">BA in Photography</b>' ) && false !== strpos( $html, '<span class="sz-tk__sub">Soore International University, Tehran</span>' ), 'education in two lines: the degree, then where' );
szw_ok( 1 === substr_count( $html, 'sz-tk__count' ) && false !== strpos( $html, 'AI image and video<span class="sz-tk__count" aria-hidden="true">8</span>' ), 'the AI row counts its tools, right after its label' );
szw_ok( false !== strpos( $html, 'sz-tk__row is-o' ) && false !== strpos( $html, 'sz-app sz-app--orange' ), 'the AI row in orange: tile and dots' );

szw_ok( [ [ 'Premiere Pro', true ], [ 'Lightroom', false ] ] === Toolkit::tools( "Premiere Pro *\n\n  Lightroom  " ), 'a * at the end marks a tool used every day; blank lines are skipped' );
$d = szw_render( [ 'rows' => [ [ 'label' => 'Edit', 'kind' => 'tags', 'tools' => "Photoshop *\nLightroom" ] ] ], 'sz-toolkit' );
szw_ok( false !== strpos( $d, '<li style="--i:0" class="is-daily">Photoshop<span class="sz-tk__daily">daily</span></li>' ) && false === strpos( $d, '*' ), 'a daily tool says so in words' );

$y = szw_render( [ 'rows' => [ [ 'label' => 'Education', 'kind' => 'text', 'title' => 'BA', 'sub' => 'Uni', 'years' => '2014 to 2018' ] ] ], 'sz-toolkit' );
szw_ok( false !== strpos( $y, 'Uni<span class="sz-tk__sep" aria-hidden="true">·</span><span class="sz-tk__years">2014 to 2018</span>' ), 'years join the second line after a dot' );

$x = szw_render( [ 'rows' => [ [ 'label' => '<b>x</b>', 'kind' => 'tags', 'tools' => '<script>y</script>' ] ] ], 'sz-toolkit' );
szw_ok( false === strpos( $x, '<script>' ) && false === strpos( $x, '<b>x</b>' ), 'everything typed is escaped' );
szw_ok( '' === trim( szw_render( [ 'rows' => [] ], 'sz-toolkit' ) ) || false === strpos( szw_render( [ 'rows' => [] ], 'sz-toolkit' ), 'sz-tk__row' ), 'no rows, nothing drawn' );

szw_finish();
