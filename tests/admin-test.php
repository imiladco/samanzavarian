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
szw_ok( 3 === substr_count( $n, 'class="sz-nav__a"' ) && false !== strpos( $n, 'aria-controls="sz-nav-' ) && false === strpos( $n, 'data-hide' ) && false !== strpos( $n, 'sz-nav-glass-yes' ) && false === strpos( $n, 'sz-nav__cta' ), 'Nav Menu: three links, a phone menu button tied to its sheet, the glass bar that stays put, no button in the sheet' );
szw_ok( false !== strpos( $n, 'class="sz-nav__go" href="#contact">Let&#039;s chat</a></nav>' ), 'Nav Menu: "Let\'s chat" on the bar by default, last in it' );
szw_ok( false === strpos( szw_render( [ 'phone' => 'hide' ], 'sz-nav' ), 'sz-nav__menu' ), 'Nav Menu: no menu button when phones hide the links' );
$n2 = szw_render( [ 'cta' => 'yes', 'hide' => 'yes' ], 'sz-nav' );
szw_ok( false !== strpos( $n2, 'class="sz-nav__cta" href="#contact"' ) && false !== strpos( $n2, 'data-hide' ), 'Nav Menu: the sheet\'s button and stepping aside, when switched on' );

// footer
$fl = szw_render( [], 'sz-flinks' );
szw_ok( 2 === substr_count( $fl, 'class="sz-flinks__col"' ) && 9 === substr_count( $fl, 'sz-flinks__a' ) - substr_count( $fl, 'sz-flinks__a::' ) && 2 === substr_count( $fl, '<h4 class="sz-flinks__h">' ), 'Footer Links: a heading starts a column; two columns, nine links' );
szw_ok( 3 === substr_count( $fl, 'sz-flinks__out' ) && 4 === substr_count( $fl, 'class="szi sz-flinks__ic"' ), 'Footer Links: known places get their icon by themselves; links that leave the site show their arrow' );
szw_ok( 'wa' === \Samanzavarian_Widgets\Widgets\Footer_Links::known_icon( 'https://wa.me/1' ) && '' === \Samanzavarian_Widgets\Widgets\Footer_Links::known_icon( '#work' ), 'Footer Links: icons by address' );
$tt = szw_render( [], 'sz-totop' );
szw_ok( false !== strpos( $tt, 'href="#top"' ) && false !== strpos( $tt, 'aria-label="Back to top"' ) && 2 === substr_count( $tt, 'BACK TO TOP' ) && false !== strpos( $tt, 'textLength="232.4"' ) && 3 === substr_count( $tt, '<svg' ), 'Back to Top: the ring closes exactly, three layers, a name for screen readers' );
szw_ok( 16 === substr_count( \Samanzavarian_Widgets\Widgets\Back_To_Top::scallop(), 'Q' ), 'Back to Top: sixteen scallops' );
$mr = szw_render( [], 'sz-mailrow' );
szw_ok( false !== strpos( $mr, 'href="mailto:saman.zavarian@gmail.com"' ) && false !== strpos( $mr, 'data-copy="saman.zavarian@gmail.com"' ) && false !== strpos( $mr, 'role="status"' ) && false === strpos( $mr, 'has-copy-phone' ), 'Mail Row: the pill opens mail, Copy copies and says so, hidden on phones by default' );
szw_ok( '' === szw_render( [ 'email' => 'not an address' ], 'sz-mailrow' ) && false === strpos( szw_render( [ 'copy' => '' ], 'sz-mailrow' ), 'sz-mailrow__copy' ), 'Mail Row: nothing without an address; no Copy when switched off' );
$wd = szw_render( [], 'sz-word' );
szw_ok( 13 === substr_count( $wd, 'class="sz-word__ch"' ) && false !== strpos( $wd, '<span class="sz-word__b">' ) && false !== strpos( $wd, 'aria-hidden="true"' ) && false !== strpos( $wd, 'is-fit' ) && preg_match( '/--szw-k:0\.1[0-9]+/', $wd ), 'Wordmark: a letter at a time, the second word apart, fitted from the first paint, silent for screen readers' );
szw_ok( false !== strpos( szw_render( [ 'label' => 'yes' ], 'sz-word' ), 'aria-label="SAMAN ZAVARIAN"' ) && '' === szw_render( [ 'first' => '', 'second' => '' ], 'sz-word' ), 'Wordmark: read aloud when asked; nothing when empty' );
// scroll-driven
$pw = szw_render( [], 'sz-work' );
szw_ok( preg_match( '#<div class="sz-pin on-d" data-sz-pin="rail" style="--szp-top:80px"><div class="sz-pin__stage">\s*<div class="sz-work #', $pw ) && preg_match( '#</script>\s*</div>\s*</div></div>#', $pw ), 'Selected work: scroll-driven on desktop by default, the rail inside the sticky stage' );
szw_ok( false === strpos( szw_render( [ 'pin_on' => [] ], 'sz-work' ), 'sz-pin' ), 'Selected work: no wrapper at all when scroll-driven is off everywhere' );
szw_ok( 'on-d on-m' === \Samanzavarian_Widgets\Widgets\Pin_Controls::classes( [ 'pin_on' => [ 'mobile', 'desktop' ] ] ) && '60svh' === \Samanzavarian_Widgets\Widgets\Pin_Controls::step( [], 60 ), 'Scroll-driven: device classes and step length' );
$pt = szw_render( [ 'tabs' => [ [ 'tab_title' => 'A' ], [ 'tab_title' => 'B' ], [ 'tab_title' => 'C' ], [ 'tab_title' => 'D' ] ] ], 'sz-service-tabs' );
szw_ok( false !== strpos( $pt, 'data-sz-pin="tabs" data-sz-pin-stops="[15,75,135,195]" data-sz-pin-map="[[0,0],[1,0],[2,0],[3,0]]" data-sz-pin-total="210" data-sz-pin-n="4" style="--szp-top:80px;--szp-len:210svh"' ), 'What I do: scroll-driven on desktop, its path set from the first paint (a stop per tab, the lead-in and lead-out)' );
$pp = \Samanzavarian_Widgets\Widgets\Service_Tabs::path( [ [ 1, 1, 1, 1, 1, 1 ], [ 1, 1, 1, 1, 1 ], [ 1 ], [ 1 ] ], 60, 35, 400 );
szw_ok( 13 === count( $pp['stops'] ) && 400.0 === (float) $pp['len'] && [ 1, 4 ] === $pp['map'][10] && $pp['stops'][1] - $pp['stops'][0] >= 20, 'Scroll path: a stop per chapter, kept within the budget (more content, shorter steps, not a longer page)' );
$pq = \Samanzavarian_Widgets\Widgets\Service_Tabs::path( array_fill( 0, 4, array_fill( 0, 30, 1 ) ), 60, 35, 400 );
szw_ok( abs( ( $pq['stops'][1] - $pq['stops'][0] ) - 20 ) < 0.01, 'Scroll path: a chapter never gets less than its minimum scroll' );
$pc = szw_render( [], 'sz-career' );
szw_ok( preg_match( '#<div class="sz-pin on-m" data-sz-pin="timeline" data-sz-pin-step="45" data-sz-pin-edge="10" data-sz-pin-n="(\d+)" style="--szp-top:80px;--szp-len:calc\(\1 \* 45svh \+ 20svh\)"><div class="sz-pin__stage">\s*<div class="sz-cr #', $pc ), 'Experience: scroll-driven on phones only, a step per role, its path set from the first paint' );
$wv = \Samanzavarian_Widgets\Widgets\Service_Panel::chapters( [ 'scene' => 'ai', 'ai_pieces' => [ [ 'media' => [ 'url' => 'https://x.test/a.mp4' ] ], [ 'media' => [ 'url' => 'https://x.test/b.jpg' ] ], [ 'media' => [ 'url' => 'https://x.test/c.mp4' ] ] ] ] );
szw_ok( [ 1.4, 1.0, 1.4 ] === $wv, 'Chapters: a video piece gets more scroll than a still' );
$star = \Samanzavarian_Widgets\Inline_Icons::convert( '<p>since 2020.{star}</p>' );
szw_ok( false !== strpos( $star, '2020.<span class="sz-star" aria-hidden="true"><svg' ) && false === strpos( $star, '{star}' ), 'The {star} code becomes the footer\'s star' );

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
