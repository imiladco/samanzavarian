<?php
/**
 * Requests, Telegram, the Project Form and Reach.
 *
 *   wp eval-file tests/leads-test.php
 */

use Samanzavarian_Widgets\Leads;
use Samanzavarian_Widgets\Telegram;
use Samanzavarian_Widgets\Widgets\Reach;

require_once __DIR__ . '/lib.php';

szw_group( 'Requests: checking what is sent' );

[ $d, $err ] = Leads::clean( [ 'name' => ' Sara <b>A</b> ', 'email' => 'sara@brand.com', 'phone' => '0912 345 6789', 'services' => [ 'AI visuals', '<script>x</script>' ], 'message' => 'A launch in spring, with edits.', 'link' => 'javascript:alert(1)' ] );
szw_ok( 'Sara A' === $d['name'] && [ 'AI visuals' ] === $d['services'] && isset( $err['link'] ) && 1 === count( $err ), 'tags stripped, a script link refused, the rest accepted' );
[ , $err ] = Leads::clean( [ 'name' => 'S', 'email' => 'nope', 'phone' => '12', 'message' => 'short' ] );
szw_ok( [ 'name', 'email', 'phone', 'message' ] === array_keys( $err ), 'a name, an email, a number of 7–15 digits and a message of a sentence are needed' );
szw_ok( Leads::looks_spammy( 'see http://a.co http://b.co www.c.co https://d.co' ) && ! Leads::looks_spammy( 'our site https://brand.com' ), 'a message of links is kept aside as spam' );
szw_ok( '989123456789' === Leads::wa_digits( '0912 345 6789' ) && '4915112345678' === Leads::wa_digits( '+49 151 1234 5678' ) && '4915112345678' === Leads::wa_digits( '0049 151 1234 5678' ), 'WhatsApp numbers: a local 09… reads as Iranian; + and 00 kept as given' );

szw_group( 'Requests: receiving' );

// Telegram answered here, and every call recorded
$GLOBALS['szw_tg_calls'] = [];
$GLOBALS['szw_tg_fail']  = '';
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		if ( false === strpos( $url, 'api.telegram.org' ) ) {
			return $pre;
		}
		$GLOBALS['szw_tg_calls'][] = $args['body'];
		$fail                      = $GLOBALS['szw_tg_fail'];
		return [ 'headers' => [], 'body' => wp_json_encode( $fail ? [ 'ok' => false, 'description' => $fail ] : [ 'ok' => true, 'result' => [] ] ), 'response' => [ 'code' => $fail ? 400 : 200, 'message' => '' ], 'cookies' => [], 'filename' => null ];
	},
	10,
	3
);
$saved_opt = get_option( Telegram::OPTION );
update_option( Telegram::OPTION, [ 'token' => '123456789:AAAAAAAAAAAAAAAAAAAAAAAAAAAA', 'chat' => '555', 'retention' => 0 ] );
$_SERVER['REMOTE_ADDR'] = '203.0.113.' . wp_rand( 1, 250 );
delete_transient( Leads::ip_key() );

$post = static function ( array $body ) {
	$r = new WP_REST_Request( 'POST', '/' . Leads::ROUTE . '/lead' );
	$r->set_header( 'content-type', 'application/json' );
	$r->set_body( wp_json_encode( $body ) );
	return rest_get_server()->dispatch( $r );
};
$count = static fn() => count( get_posts( [ 'post_type' => Leads::TYPE, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ] ) );
$base  = $count();
$good  = [ 'name' => 'Sara Test', 'email' => 'sara+' . wp_rand() . '@brand.com', 'phone' => '0912 345 6789', 'services' => [ 'AI visuals' ], 'timeline' => 'Within a month', 'budget' => '$3k–10k', 'message' => 'A launch in spring, AI visuals & edits.', 't' => 9000 ];

$r = $post( $good + [ 'website' => 'http://bot.example' ] );
szw_ok( 200 === $r->get_status() && $base === $count(), 'the field only bots see: told "sent", nothing kept' );
$r = $post( array_merge( $good, [ 't' => 300 ] ) );
szw_ok( 200 === $r->get_status() && $base === $count(), 'filled in a third of a second: told "sent", nothing kept' );
$r = $post( array_merge( $good, [ 'email' => 'x' ] ) );
szw_ok( 400 === $r->get_status() && [ 'email' ] === $r->get_data()['errors'], 'a wrong field comes back by name' );

$r = $post( $good );
Telegram::flush_queue();
$ids = get_posts( [ 'post_type' => Leads::TYPE, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'DESC' ] );
$id  = (int) ( $ids[0] ?? 0 );
szw_ok( 200 === $r->get_status() && $base + 1 === $count() && 'new' === Leads::status( $id ) && 'private' === get_post_status( $id ), 'a real request is kept, private, as new' );
$sent = end( $GLOBALS['szw_tg_calls'] );
szw_ok( $sent && false !== strpos( $sent['text'], '<b>Sara Test</b>' ) && false !== strpos( $sent['text'], 'AI visuals &amp; edits' ) && false !== strpos( $sent['reply_markup'], '989123456789' ), 'announced on Telegram: escaped, with a WhatsApp button' );
szw_ok( 'sent' === ( get_post_meta( $id, Telegram::META, true )['state'] ?? '' ), 'the request remembers it was announced' );

$r = $post( $good );
szw_ok( 200 === $r->get_status() && $base + 1 === $count(), 'the same request again within ten minutes: counted once' );

// Telegram down: kept, marked, tried again later
$GLOBALS['szw_tg_fail'] = 'Bad Request: chat not found';
$post( array_merge( $good, [ 'message' => 'Another project, a brand film for autumn ' . wp_rand() ] ) );
Telegram::flush_queue();
$ids = get_posts( [ 'post_type' => Leads::TYPE, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'DESC' ] );
$id2 = (int) $ids[0];
$tg  = get_post_meta( $id2, Telegram::META, true );
szw_ok( 'failed' === $tg['state'] && false !== strpos( $tg['error'], 'chat not found' ) && wp_next_scheduled( Telegram::RETRY, [ $id2 ] ), 'Telegram refuses: the request is kept, says why, and is tried again later' );
$GLOBALS['szw_tg_fail'] = '';
wp_clear_scheduled_hook( Telegram::RETRY, [ $id2 ] );
Telegram::send( $id2 );
szw_ok( 'sent' === get_post_meta( $id2, Telegram::META, true )['state'], 'the retry gets through' );

$post( array_merge( $good, [ 'message' => 'buy http://a.co http://b.co http://c.co http://d.co now ' . wp_rand(), 'email' => 'spam@x.co' ] ) );
$calls = count( $GLOBALS['szw_tg_calls'] );
Telegram::flush_queue();
$ids = get_posts( [ 'post_type' => Leads::TYPE, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'DESC' ] );
szw_ok( 'spam' === Leads::status( (int) $ids[0] ) && count( $GLOBALS['szw_tg_calls'] ) === $calls, 'spam is kept aside and never announced' );

for ( $i = 0; $i < Leads::RATE; $i++ ) {
	$last = $post( array_merge( $good, [ 'message' => 'Message number ' . $i . ' about a project.' ] ) );
}
szw_ok( 429 === $last->get_status(), 'more than a few requests an hour from one address: refused' );
Telegram::flush_queue();

$f = Leads::store( [ 'name' => '=cmd|calc', 'email' => 'a@b.co', 'phone' => '', 'services' => [], 'timeline' => '', 'budget' => '', 'message' => 'formula test message', 'link' => '', 'page' => '' ] );
szw_ok( false !== strpos( Leads::csv_text(), "'=cmd|calc" ), 'CSV: a cell that would run as a formula is neutralised' );

// clean up everything this test made
foreach ( get_posts( [ 'post_type' => Leads::TYPE, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => [ [ 'key' => Leads::META, 'value' => 'brand.com', 'compare' => 'LIKE' ] ] ] ) as $x ) {
	wp_delete_post( $x, true );
}
wp_delete_post( $f, true );
foreach ( get_posts( [ 'post_type' => Leads::TYPE, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => [ [ 'key' => Leads::META, 'value' => 'spam@x.co', 'compare' => 'LIKE' ] ] ] ) as $x ) {
	wp_delete_post( $x, true );
}
delete_transient( Leads::ip_key() );
false === $saved_opt ? delete_option( Telegram::OPTION ) : update_option( Telegram::OPTION, $saved_opt );

szw_group( 'Telegram settings' );
$s = Telegram::sanitize( [ 'token' => 'not a token', 'chat' => 'abc', 'retention' => '99999' ] );
szw_ok( '' === $s['chat'] && 3650 === $s['retention'], 'a chat must be an id or @name; retention at most ten years' );
szw_ok( '123456789:BBBBBBBBBBBBBBBBBBBBBBBBBBBB' === Telegram::sanitize( [ 'token' => '123456789:BBBBBBBBBBBBBBBBBBBBBBBBBBBB' ] )['token'], 'a token in the right shape is kept' );

szw_group( 'Project Form and Reach' );
$html = szw_render( [], 'sz-form' );
szw_ok( 4 === substr_count( $html, 'name="services[]"' ) && 3 === substr_count( $html, 'name="timeline"' ) && 5 === substr_count( $html, 'name="budget"' ), 'needs as checkboxes; timeline and budget as optional radios' );
szw_ok( false !== strpos( $html, 'name="phone" type="tel"' ) && false !== strpos( $html, 'class="sz-pf__trap"' ) && false !== strpos( $html, 'szw/v1/lead' ), 'a phone field, the bot trap, the route' );
szw_ok( false === strpos( szw_render( [ 'phone' => '', 'budget' => '' ], 'sz-form' ), 'name="phone"' ) && false === strpos( szw_render( [ 'budget' => '' ], 'sz-form' ), 'name="budget"' ), 'phone and budget can be left out' );

szw_ok( [ 'https://wa.me/989037022029?text=Hi%20there', '+98 903 702 2029' ] === Reach::target( 'whatsapp', '+98 903 702 2029', 'Hi there' ), 'WhatsApp: digits, with the first message written' );
szw_ok( [ 'https://t.me/saman', '@saman' ] === Reach::target( 'telegram', 'https://t.me/saman' ) && [ 'https://www.instagram.com/saman/', '@saman' ] === Reach::target( 'instagram', '@saman' ), 'Telegram and Instagram from a username or a link' );
szw_ok( 'in/saman-zavarian' === Reach::target( 'linkedin', 'saman-zavarian' )[1] && 'tel:+989037022029' === Reach::target( 'phone', '+98 903 702 2029' )[0], 'LinkedIn and phone' );
$rc = szw_render( [], 'sz-reach' );
szw_ok( 3 === substr_count( $rc, 'class="sz-reach__row' ) && 1 === substr_count( $rc, 'class="sz-reach__on"' ) && false !== strpos( $rc, 'sz-reach__copy' ), 'the design\'s rows; "Online" on WhatsApp; Copy on email' );

szw_finish();
