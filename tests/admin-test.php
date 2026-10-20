<?php
/**
 * The Requests panel (Admin\*, PHP 8.1+) and the request pipeline under it.
 *
 *   wp eval-file tests/admin-test.php
 */

use Samanzavarian_Widgets\Leads;
use Samanzavarian_Widgets\Telegram;

require_once __DIR__ . '/lib.php';

szw_group( 'Requests panel' );

szw_ok( Leads::kit() === ( PHP_VERSION_ID >= 80100 ), 'the panel only where PHP can run it (8.1+); below that, the plain list' );

// nothing in the always-loaded files needs more than PHP 7.4
$newer = [];
foreach ( array_merge( glob( SZW_PATH . 'includes/*.php' ), glob( SZW_PATH . 'includes/Widgets/*.php' ) ) as $f ) {
	$src = (string) file_get_contents( $f );
	if ( preg_match( '/\bstr_(starts|ends)_with\(|\bstr_contains\(|\bmatch\s*\(|\benum\s+\w|\breadonly\s|\):\s*never\b|^\s*#\[/m', $src ) ) {
		$newer[] = basename( $f );
	}
}
szw_ok( ! $newer, 'files loaded on every PHP use nothing newer than 7.4' . ( $newer ? ': ' . implode( ', ', $newer ) : '' ) );

if ( ! Leads::kit() ) {
	szw_finish();
	return;
}

use Samanzavarian_Widgets\Admin\Fmt;
use Samanzavarian_Widgets\Admin\Kit;
use Samanzavarian_Widgets\Admin\Settings\Settings;

szw_ok( '12,345' === Fmt::num( 12345 ) && '90' === Fmt::only( '۹۰' ) && '1 request' === Fmt::plural( 1, 'request' ) && '3 requests' === Fmt::plural( 3, 'request' ), 'English numbers; digits typed in any script are read' );
$t = Fmt::local( strtotime( '2026-10-19 12:00:00 UTC' ) )->getTimestamp();
szw_ok( (bool) preg_match( '/^\d{1,2} Oct 2026, \d\d:\d\d$/', Fmt::date( $t, true ) ) && '5 hours' === Fmt::duration( 5 * 3600 ) && '45 min ago' === Fmt::ago( time() - 45 * 60 ), 'English dates, durations and "ago", in the site\'s time zone' );

$id = Leads::store( [ 'name' => 'Panel Test', 'email' => 'panel@test.co', 'phone' => '', 'services' => [ 'AI visuals' ], 'timeline' => 'Flexible', 'budget' => '$1k–3k', 'message' => 'A test request for the panel.', 'link' => '', 'page' => '' ] );
szw_ok( 'new' === Leads::status( $id ) && 'Received from the form' === ( Leads::events( $id )[0]['message'] ?? '' ), 'a stored request starts new, with its arrival in the events' );

Leads::set_status( $id, 'talking', 'Tester', Kit::statusText( 'talking' ) );
$first = (int) get_post_meta( $id, Leads::FIRST, true );
szw_ok( $first > 0 && 'talking' === Leads::status( $id ) && 'Tester' === end( Leads::events( $id ) )['by'] && 'Status: Talking' === end( Leads::events( $id ) )['message'], 'out of "new": the first reply is timed, and who did it is in the events' );
Leads::set_status( $id, 'won', 'Tester' );
Leads::set_status( $id, 'new', 'Tester' );
Leads::set_status( $id, 'talking', 'Tester' );
szw_ok( $first === (int) get_post_meta( $id, Leads::FIRST, true ), 'the first reply stays the first' );
Leads::set_status( $id, 'lost', 'Tester' );
szw_ok( (int) get_post_meta( $id, Leads::CLOSED, true ) > 0, 'won or lost: the closing time, for retention' );

update_post_meta( $id, Leads::STATUS, 'archived' );
Leads::forget();
szw_ok( 'lost' === Leads::status( $id ), 'statuses from before (archived, replied) read as lost and talking' );

$s = Leads::stats( 30 );
szw_ok( isset( $s['period']['count'], $s['days'], $s['statuses']['won'] ) && 30 === count( $s['days'] ) && $s['period']['count'] >= 1, 'the overview\'s numbers: counts, 30 days, statuses' );
$found = Leads::search( 'lost', 'panel@test', 1, 10, [ 'service' => 'AI visuals', 'budget' => '$1k–3k' ] );
szw_ok( 1 === $found['total'] && $id === $found['rows'][0]['id'] && 0 === Leads::search( 'won', 'panel@test', 1, 10 )['total'] && 0 === Leads::search( 'lost', 'panel@test', 1, 10, [ 'budget' => '$10k+' ] )['total'], 'search, status views and filters find it, and only where they should' );

// retention: closed long ago goes, open stays
$saved = [ get_option( Settings::OPTION, null ), get_option( Settings::SECRETS, null ), get_option( Settings::CHATS, null ) ];
Settings::save( [ 'retention' => '5', 'spam_days' => '30' ], 'data' );
update_post_meta( $id, Leads::CLOSED, time() - 6 * DAY_IN_SECONDS );
$open = Leads::store( [ 'name' => 'Still Open', 'email' => 'open@test.co', 'phone' => '', 'services' => [], 'timeline' => '', 'budget' => '', 'message' => 'Still open, should stay.', 'link' => '', 'page' => '' ] );
Leads::forget();
Leads::cleanup();
szw_ok( null === get_post( $id ) && null !== get_post( $open ), 'retention: a request closed 6 days ago goes after 5; an open one stays' );
wp_delete_post( $open, true );

// settings before the panel move in once
delete_option( Settings::OPTION );
delete_option( Settings::SECRETS );
delete_option( Settings::CHATS );
$old = get_option( Telegram::OPTION, null );
update_option( Telegram::OPTION, [ 'token' => '111:BBBBBBBBBBBBBBBBBBBBBBBBBBBB', 'chat' => '777', 'retention' => 14 ] );
$r = new ReflectionProperty( Settings::class, 'cache' );
$r->setAccessible( true );
$r->setValue( null, null );
szw_ok( 14 === Settings::int( 'retention' ) && '111:BBBBBBBBBBBBBBBBBBBBBBBBBBBB' === Settings::text( 'tg_token' ) && [ '777' => '' ] === Telegram::chats(), 'Telegram settings from before the panel move in (the one chat becomes the first of the list), and Telegram reads them' );
szw_ok( false === strpos( (string) wp_json_encode( get_option( Settings::OPTION ) ), '111:' ), 'the token is kept apart from the other settings' );
null === $old ? delete_option( Telegram::OPTION ) : update_option( Telegram::OPTION, $old );
null === $saved[0] ? delete_option( Settings::OPTION ) : update_option( Settings::OPTION, $saved[0] );
null === $saved[1] ? delete_option( Settings::SECRETS ) : update_option( Settings::SECRETS, $saved[1] );
null === $saved[2] ? delete_option( Settings::CHATS ) : update_option( Settings::CHATS, $saved[2] );
$r->setValue( null, null );

// the form's tiles: icons, and one guessed for a tile saved before tiles had icons
$html = szw_render( [ 'services' => [ [ 'label' => 'Video editing', 'value' => 'Editing' ] ] ], 'sz-form' );
szw_ok( false !== strpos( $html, 'sz-pf__tile' ) && false !== strpos( $html, 'szi-sliders' ) && false !== strpos( $html, 'sz-pf__more' ), 'needs as tiles with icons (an old tile gets one by its name); More details holds the rest' );
szw_ok( false === strpos( szw_render( [ 'timeline' => '', 'budget' => '', 'link' => '' ], 'sz-form' ), 'sz-pf__more' ), 'nothing optional to ask: no More details row' );

// Reply Promise: working days in the owner's zone, days off skipped
$RP  = \Samanzavarian_Widgets\Widgets\Reply_Promise::class;
$wed = strtotime( '2026-10-21 09:00 Asia/Tehran' ); // a Wednesday
szw_ok( 'Saturday' === $RP::reply_day( 2, [ 5 ], 'Asia/Tehran', $wed )->format( 'l' ) && 'Monday' === $RP::reply_day( 2, [ 5, 6, 0 ], 'Asia/Tehran', $wed )->format( 'l' ), 'Reply Promise: two working days, skipping the days off' );
$html = szw_render( [], 'sz-promise' );
szw_ok( false !== strpos( $html, 'Replies within two business days' ) && preg_match( '/hear back by <b>\w+day, \d+ \w+<\/b>/', $html ), 'Reply Promise names the day on the server (no jump on load)' );

// hero widgets
$h = szw_render( [], 'sz-htitle' );
szw_ok( false !== strpos( $h, '<h1 class="sz-htitle' ) && false !== strpos( $h, 'is-o">Zavarian' ) && false !== strpos( $h, 'szi-lens' ), 'Hero Title: an h1, the orange second word, the tile between' );
szw_ok( false !== strpos( szw_render( [ 'text' => 'Plain *bold <x>* end' ], 'sz-lead' ), '<b class="sz-lead__sw">bold &lt;x&gt;<svg' ), 'Hero Lead: *stars* become the underlined bold, escaped' );
$b = szw_render( [ 'text' => 'Go', 'look' => 'light', 'link' => [ 'url' => 'https://x.co', 'is_external' => 'on' ] ], 'sz-btn' );
szw_ok( false !== strpos( $b, 'is-light' ) && false !== strpos( $b, 'href="https://x.co"' ) && false !== strpos( $b, 'target="_blank"' ), 'Button: look, link, new tab' );
$f = szw_render( [], 'sz-floats' );
szw_ok( 4 === substr_count( $f, 'class="sz-float ' ) && false !== strpos( $f, 'sz-mesh-m2' ) && 2 === substr_count( $f, 'is-wide' ), 'Hero Floats: four cards, meshes without images, two only on wide screens' );
$d = szw_render( [], 'sz-dock' );
szw_ok( 4 === substr_count( $d, '<a class="sz-dock__a' ) && 1 === substr_count( $d, 'sz-dock__sep' ) && false !== strpos( $d, 'data-sz-dock' ), 'Dock: four items, a separator before Behance, the swell on' );

// header
$h = szw_render( [], 'sz-brand' );
szw_ok( false !== strpos( $h, 'class="sz-brand' ) && false !== strpos( $h, '<b>Zavarian</b>' ) && false !== strpos( $h, 'href="#top"' ) && false !== strpos( $h, 'aria-label="Saman Zavarian, back to top"' ), 'Brand Mark: the name, the orange word, back to top, a name for screen readers' );
$n = szw_render( [], 'sz-nav' );
szw_ok( 3 === substr_count( $n, 'class="sz-nav__a"' ) && false !== strpos( $n, 'aria-controls="sz-nav-' ) && false !== strpos( $n, 'data-hide' ) && false !== strpos( $n, 'sz-nav-glass-yes' ), 'Nav Menu: three links, a phone menu button tied to its sheet, the glass bar that steps aside' );
szw_ok( false === strpos( szw_render( [ 'phone' => 'hide' ], 'sz-nav' ), 'sz-nav__menu' ), 'Nav Menu: no menu button when phones hide the links' );

// the Style tab's defaults: the design's values, fluid ones as formulas, the phone one apart
$sd = require SZW_PATH . 'includes/style-defaults.php';
$fs = $sd['sz-htitle']['st_all_type_font_size'] ?? [];
szw_ok( [ 'unit' => 'px', 'size' => 132 ] === ( $fs['d'] ?? null ) && 'px' === ( $fs['t']['unit'] ?? '' ) && 'px' === ( $fs['m']['unit'] ?? '' ) && 'custom' === ( $sd['sz-htitle']['st_all_type_typography']['d'] ?? '' ), 'Style defaults: the title\'s size in plain pixels, for desktop, tablet and phone' );
$units = [];
array_walk_recursive( $sd, static function ( $v, $k ) use ( &$units ) { if ( 'unit' === $k ) { $units[ $v ] = true; } } );
szw_ok( [ 'px' ] === array_keys( $units ), 'Style defaults: every size and line height in px: ' . implode( ', ', array_keys( $units ) ) );
szw_ok( ! isset( $sd['sz-btn']['st_box_bg_color'] ) && ! isset( $sd['sz-dock']['st_tile_fg'] ), 'Style defaults: nothing that changes with hover or with a choice (the button\'s look, the tile\'s tone)' );

szw_ok( 'Hi Sara, about ai visuals and post-production' === Kit::fill( 'Hi {first}, about {needs}', [ 'name' => 'Sara Ahmadi', 'services' => [ 'AI visuals', 'Post-production' ] ] ), 'reply templates fill in the first name and the needs' );

szw_finish();
