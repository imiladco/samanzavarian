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

use Samanzavarian_Widgets\Admin\Fa;
use Samanzavarian_Widgets\Admin\Kit;
use Samanzavarian_Widgets\Admin\Settings\Settings;

szw_ok( '۱۲٬۳۴۵' !== Fa::num( 12345 ) && '۱۲,۳۴۵' === Fa::num( 12345 ) && '90' === Fa::only( '۹۰' ), 'Persian digits out, Latin digits in' );
szw_ok( [ 1405, 7, 27 ] === Fa::jalali( strtotime( '2026-10-19 12:00:00 UTC' ) ), 'Jalali date: 19 October 2026 is 27 Mehr 1405' );

$id = Leads::store( [ 'name' => 'Panel Test', 'email' => 'panel@test.co', 'phone' => '', 'services' => [ 'AI visuals' ], 'timeline' => 'Flexible', 'budget' => '$1k–3k', 'message' => 'A test request for the panel.', 'link' => '', 'page' => '' ] );
szw_ok( 'new' === Leads::status( $id ) && 'Received from the form' === ( Leads::events( $id )[0]['message'] ?? '' ), 'a stored request starts new, with its arrival in the events' );

Leads::set_status( $id, 'talking', 'Tester', Kit::statusText( 'talking' ) );
$first = (int) get_post_meta( $id, Leads::FIRST, true );
szw_ok( $first > 0 && 'talking' === Leads::status( $id ) && 'Tester' === end( Leads::events( $id ) )['by'], 'out of "new": the first reply is timed, and who did it is in the events' );
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
$saved = [ get_option( Settings::OPTION, null ), get_option( Settings::SECRETS, null ) ];
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
$old = get_option( Telegram::OPTION, null );
update_option( Telegram::OPTION, [ 'token' => '111:BBBBBBBBBBBBBBBBBBBBBBBBBBBB', 'chat' => '777', 'retention' => 14 ] );
$r = new ReflectionProperty( Settings::class, 'cache' );
$r->setAccessible( true );
$r->setValue( null, null );
szw_ok( '777' === Settings::text( 'tg_chat' ) && 14 === Settings::int( 'retention' ) && '111:BBBBBBBBBBBBBBBBBBBBBBBBBBBB' === Settings::text( 'tg_token' ) && '777' === Telegram::option( 'chat' ), 'Telegram settings from before the panel move in, and Telegram reads them' );
szw_ok( false === strpos( (string) wp_json_encode( get_option( Settings::OPTION ) ), '111:' ), 'the token is kept apart from the other settings' );
null === $old ? delete_option( Telegram::OPTION ) : update_option( Telegram::OPTION, $old );
null === $saved[0] ? delete_option( Settings::OPTION ) : update_option( Settings::OPTION, $saved[0] );
null === $saved[1] ? delete_option( Settings::SECRETS ) : update_option( Settings::SECRETS, $saved[1] );
$r->setValue( null, null );

szw_ok( 'Hi Sara, about ai visuals and post-production' === Kit::fill( 'Hi {first}, about {needs}', [ 'name' => 'Sara Ahmadi', 'services' => [ 'AI visuals', 'Post-production' ] ] ), 'reply templates fill in the first name and the needs' );

szw_finish();
