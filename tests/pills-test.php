<?php
/**
 * Status and Section Tag widgets.
 *
 *   wp eval-file tests/pills-test.php
 */

use Elementor\Plugin as Elementor;
use Samanzavarian_Widgets\Widgets\Status_Pill;

require_once __DIR__ . '/lib.php';

szw_group( 'Pills: registration' );

szw_ok( null !== Elementor::$instance->widgets_manager->get_widget_types( 'sz-status' ) && null !== Elementor::$instance->widgets_manager->get_widget_types( 'sz-tag' ), 'sz-status and sz-tag are registered' );
szw_ok( wp_style_is( 'sz-pills', 'registered' ) && wp_script_is( 'sz-status', 'registered' ), 'one shared stylesheet; the status script' );

/* ------------------------------------------------------------------ */

szw_group( 'Status' );

$html = szw_render( [], 'sz-status' );
szw_ok( false !== strpos( $html, 'sz-pill sz-status is-available' ) && false !== strpos( $html, '>Available for new projects<' ), 'out of the box: available, the design\'s text' );
szw_ok( false === strpos( $html, 'data-until' ) && false === strpos( $html, 'data-tz' ) && false === strpos( $html, '<a ' ), 'plain HTML: no date, no clock, no link' );
szw_ok( ! Status_Pill::needs_script( [] ), 'so no script is loaded for it' );

$day  = static fn( $d ) => wp_date( 'Y-m-d', time() + $d * DAY_IN_SECONDS );
$html = szw_render( [ 'state' => 'booked', 'until' => $day( 10 ) . ' 00:00' ], 'sz-status' );
szw_ok( false !== strpos( $html, 'is-booked' ) && false !== strpos( $html, '>Booked until ' . wp_date( 'M j', time() + 10 * DAY_IN_SECONDS ) . '<' ), 'booked, with the date in the text' );
szw_ok( false !== strpos( $html, 'data-until="' ) && false !== strpos( $html, 'data-available="Available for new projects"' ), 'the script can flip it when the day comes, even on a cached page' );
szw_ok( Status_Pill::needs_script( [ 'state' => 'booked', 'until' => $day( 10 ) ] ), 'and loads it for that' );

$html = szw_render( [ 'state' => 'booked', 'until' => $day( -1 ) . ' 00:00' ], 'sz-status' );
szw_ok( false !== strpos( $html, 'is-available' ) && false !== strpos( $html, '>Available for new projects<' ) && false === strpos( $html, 'data-until' ), 'the day has passed: available by itself' );

$html = szw_render( [ 'state' => 'limited', 'text_limited' => 'Limited this month' ], 'sz-status' );
szw_ok( false !== strpos( $html, 'is-limited' ) && false !== strpos( $html, '>Limited this month<' ), 'limited, own text' );
$html = szw_render( [ 'state' => 'booked' ], 'sz-status' );
szw_ok( false !== strpos( $html, '>Booked until<' ), 'no date: {date} is dropped cleanly' );

$html = szw_render( [ 'clock' => 'yes', 'timezone' => 'Asia/Tehran', 'place' => 'Tehran' ], 'sz-status' );
$now  = ( new DateTimeImmutable( 'now', new DateTimeZone( 'Asia/Tehran' ) ) )->format( 'H:i' );
szw_ok( false !== strpos( $html, 'data-tz="Asia/Tehran"' ) && false !== strpos( $html, '<time class="sz-status__time">' . $now . '</time> <span class="sz-status__place">Tehran</span>' ), 'local time, already on the first paint' );
$html = szw_render( [ 'clock' => 'yes', 'timezone' => 'Nope/Zone' ], 'sz-status' );
szw_ok( false !== strpos( $html, 'data-tz="UTC"' ), 'an unknown zone falls back to UTC' );

$html = szw_render( [ 'link' => [ 'url' => '#contact' ], 'skin' => 'dark', 'pulse' => '' ], 'sz-status' );
szw_ok( false !== strpos( $html, '<a ' ) && false !== strpos( $html, 'href="#contact"' ) && false !== strpos( $html, 'sz-pill__arrow' ), 'a link, with its arrow' );
szw_ok( false !== strpos( $html, 'sz-pill--dark' ) && false !== strpos( $html, 'no-pulse' ), 'dark skin; pulse off' );

/* ------------------------------------------------------------------ */

szw_group( 'Section Tag' );

$html = szw_render( [], 'sz-tag' );
szw_ok( false !== strpos( $html, '<span class="sz-pill sz-tag is-pop"' ) && false !== strpos( $html, '>What I do<' ), 'out of the box: a span, the design\'s text, the pop on' );
szw_ok( false !== strpos( $html, 'class="sz-tag__tile sz-app sz-app--orange"' ) && false !== strpos( $html, 'szi-spark' ), 'orange tile with the spark' );

$html = szw_render( [ 'text' => 'Selected work', 'tile' => 'white', 'badge' => '106+', 'html_tag' => 'h2', 'pop' => '' ], 'sz-tag' );
szw_ok( false !== strpos( $html, '<h2 class="sz-pill sz-tag"' ) && false !== strpos( $html, 'sz-app--white' ) && false !== strpos( $html, '<span class="sz-tag__badge">106+</span>' ), 'heading tag, white tile, badge, no pop' );

$html = szw_render( [ 'link' => [ 'url' => 'https://example.com', 'is_external' => 'on' ], 'html_tag' => 'h2' ], 'sz-tag' );
szw_ok( false !== strpos( $html, '<a class="sz-pill sz-tag is-pop is-link"' ) && false !== strpos( $html, 'target="_blank"' ), 'a link wins over the heading tag' );

$html = szw_render( [ 'icon' => [ 'value' => '', 'library' => '' ] ], 'sz-tag' );
szw_ok( false !== strpos( $html, 'no-icon' ) && false === strpos( $html, 'sz-tag__tile' ), 'no icon: no tile, even padding' );
szw_ok( '' === szw_render( [ 'text' => '' ], 'sz-tag' ), 'no text: nothing' );

$css = szw_css( [ [ 'id' => 't1', 'elType' => 'widget', 'widgetType' => 'sz-tag', 'settings' => [ 'pill_bg' => '#123456', 'tile_size' => [ 'size' => 30, 'unit' => 'px' ] ] ] ] );
szw_ok( false !== strpos( $css, '--szp-bg:#123456' ) && false !== strpos( $css, '--szp-tile:30px' ), 'style controls write CSS' );

szw_finish();
