<?php
/**
 * Career Timeline (sz-career).
 *
 *   wp eval-file tests/career-test.php
 */

use Samanzavarian_Widgets\Widgets\Career_Timeline;

require_once __DIR__ . '/lib.php';

szw_group( 'Career Timeline' );

szw_ok( 2022.0 === Career_Timeline::point( '2022', false ) && abs( Career_Timeline::point( '2022-03', false ) - 2022 - 2 / 12 ) < 1e-9 && abs( Career_Timeline::point( '2022-03', true ) - 2022.25 ) < 1e-9, 'dates: a year, or a year and month (a start at the month\'s beginning, an end at its close)' );
szw_ok( null === Career_Timeline::point( 'soon', false ), 'a date it cannot read is no date' );

$html = szw_render( [], 'sz-career' );
szw_ok( 6 === substr_count( $html, 'role="tab"' ) && 6 === substr_count( $html, 'role="tabpanel"' ), 'the design\'s six roles: a clip and an inspector each, all in the page' );
szw_ok( 1 === substr_count( $html, 'aria-selected="true"' ) && false !== strpos( $html, 'aria-selected="true" tabindex="0"' ) && 5 === substr_count( $html, ' inert' ), 'one selected (the only tab stop); the other inspectors are out of reach' );
szw_ok( (bool) preg_match( '/aria-selected="true"[^>]*>\s*<b>Pely Media/', $html ), 'the role you are in now is selected when the page opens' );
szw_ok( 2 === substr_count( $html, 'class="sz-cr__lane"' ), 'overlapping roles get a second lane, no more' );
szw_ok( false !== strpos( $html, '<small>Before 2022</small>' ) && false !== strpos( $html, '<small>2022 to 2023</small>' ) && false !== strpos( $html, '<small>2026 to now</small>' ), 'dates written from the data: Before 2022, 2022 to 2023, 2026 to now' );
szw_ok( false !== strpos( $html, 'class="sz-cr__zone"' ) && false !== strpos( $html, '<span style="left:0">Before 2022</span>' ), 'earlier years share one hatched stretch of the ruler' );
szw_ok( false !== strpos( $html, 'sz-cr__when is-live' ) && false !== strpos( $html, 'sz-app sz-app--orange' ), 'the current role: a live dot and the orange tile' );

// lanes: the longest unbroken run on top, packed by best fit
$lay = Career_Timeline::layout(
	[ 'roles' => [
		[ 'company' => 'A', 'start' => '2020-01', 'end' => '2021-06' ],
		[ 'company' => 'B', 'start' => '2020-09', 'end' => '2021-12' ],
		[ 'company' => 'C', 'start' => '2022', 'end' => '2023' ],
		[ 'company' => 'D', 'start' => '2023', 'end' => '' ],
	], 'group_before' => 0 ],
	2024.5
);
$lanes = array_column( $lay['roles'], 'lane', 'co' );
szw_ok( 0 === $lanes['B'] && 0 === $lanes['C'] && 0 === $lanes['D'] && 1 === $lanes['A'], 'best fit: the run B → C → D stays on the top lane, A takes the second' );
szw_ok( 2020 === $lay['t0'] && 2025 === $lay['t1'] && 3 === $lay['current'], 'the ruler spans the roles and "now"; the open role is the current one' );
szw_ok( '2023 to now' === $lay['roles'][3]['when'] && '2020 to 2021' === $lay['roles'][0]['when'], 'labels follow the dates when nothing is grouped' );

$one = szw_render( [ 'roles' => [ [ 'company' => 'Solo', 'role' => 'Maker', 'start' => '2024', 'end' => '' ] ] ], 'sz-career' );
szw_ok( false === strpos( $one, 'sz-cr__btn' ) && false === strpos( $one, 'sz-cr__swipe' ) && 1 === substr_count( $one, 'role="tab"' ), 'a single role: no arrows, no swipe hint' );
szw_ok( '' === trim( szw_render( [ 'roles' => [ [ 'company' => 'X', 'start' => 'later' ] ] ], 'sz-career' ) ) || false === strpos( szw_render( [ 'roles' => [ [ 'company' => 'X', 'start' => 'later' ] ] ], 'sz-career' ), 'sz-cr__clip' ), 'a role without a readable start is left out' );

$x = szw_render( [ 'roles' => [ [ 'company' => '<b>x</b>', 'role' => '"><script>', 'start' => '2024', 'tags' => "<i>a</i>" ] ] ], 'sz-career' );
szw_ok( false === strpos( $x, '<script>' ) && false === strpos( $x, '<b>x</b>' ) && false === strpos( $x, '<i>a</i>' ), 'everything typed is escaped' );

szw_finish();
