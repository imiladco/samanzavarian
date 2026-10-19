<?php
/**
 * Stats widget.
 *
 *   wp eval-file tests/stats-test.php
 */

use Elementor\Plugin as Elementor;
use Samanzavarian_Widgets\Widgets\Stats_Cards;

require_once __DIR__ . '/lib.php';

szw_group( 'Stats' );

szw_ok( null !== Elementor::$instance->widgets_manager->get_widget_types( 'sz-stats' ) && wp_style_is( 'sz-stats', 'registered' ) && wp_script_is( 'sz-stats', 'registered' ), 'registered, with its stylesheet and script' );

$html  = szw_render( [], 'sz-stats' );
$years = (int) wp_date( 'Y' ) - 2020;
szw_ok( [ '106', '30', (string) $years ] === szw_all( '/class="sz-stat__d"[^>]*>([^<]*)</', $html ), 'the design\'s figures, final in the HTML; the years counted from 2020 (' . $years . ')' );
szw_ok( 2 === substr_count( $html, 'class="sz-stat__plus"' ) && false === strpos( $html, '>+<' ), 'the "+" is drawn, not typed' );
szw_ok( 1 === substr_count( $html, 'is-hi' ) && false !== strpos( $html, 'sz-app sz-app--orange' ), 'the first card is the dark one, with the orange tile' );
szw_ok( [ '106+ Projects delivered', '30+ Commercial pieces', $years . ' Years in the field' ] === szw_all( '/class="sz-stat__sr">([^<]*)</', $html ), 'screen readers hear one sentence per card' );
szw_ok( 3 === substr_count( $html, 'class="sz-stat__num" aria-hidden="true"' ), 'and not the pieces, nor the counting' );
szw_ok( false !== strpos( $html, 'data-count="1" data-duration="1400" data-stagger="140"' ) && false !== strpos( $html, '--szs-n:3' ), 'counting on, its timing; one column per card' );

$cards = Stats_Cards::cards( [ 'cards' => [ [ 'kind' => 'years', 'since' => 2020, 'detail' => 'since {since}' ] ] ], 2031 );
szw_ok( 11.0 === (float) $cards[0]['value'] && 'since 2020' === $cards[0]['detail'], 'years since: goes up by itself (2031 → 11), {since} filled' );

$html = szw_render( [ 'cards' => [ [ 'value' => 1250.5, 'decimals' => '1', 'prefix' => '$', 'suffix' => 'k', 'label' => 'Budget', 'link' => [ 'url' => 'https://example.com' ] ] ], 'separator' => 'yes' ], 'sz-stats' );
szw_ok( false !== strpos( $html, 'data-to="1250.5" data-dec="1">1,250.5<' ) && false !== strpos( $html, '<span class="sz-stat__pre">$</span>' ) && false !== strpos( $html, '<em class="sz-stat__suf">k</em>' ), 'prefix, decimals, thousands separator, text suffix' );
szw_ok( false !== strpos( $html, '<a class="sz-stat is-link' ) && false !== strpos( $html, 'href="https://example.com"' ), 'a card with a link is a link' );
$html = szw_render( [ 'cards' => [ [ 'value' => 1250, 'label' => 'x' ] ], 'separator' => '', 'count' => '' ], 'sz-stats' );
szw_ok( false !== strpos( $html, '>1250<' ) && false !== strpos( $html, 'data-count="0"' ), 'separator off; counting off' );
szw_ok( '' === szw_render( [ 'cards' => [] ], 'sz-stats' ), 'no cards: nothing' );

$css = szw_css( [ [ 'id' => 'st', 'elType' => 'widget', 'widgetType' => 'sz-stats', 'settings' => [ 'columns_mobile' => '1', 'max_width' => [ 'size' => 470, 'unit' => 'px' ], 'align' => 'right', 'plus_color' => '#123456' ] ] ] );
szw_ok( false !== strpos( $css, '--szs-max:470px' ) && false !== strpos( $css, 'margin-left:auto;margin-right:0' ) && false !== strpos( szw_at( $css, 767 ), '--szs-cols:1' ) && false !== strpos( $css, '--szs-accent:#123456' ), 'width, position, columns per breakpoint, colours' );
szw_ok( false !== strpos( szw_at( $css, 767 ), '--szs-radius:18px' ) && false !== strpos( szw_at( $css, 767 ), '--szs-gap:8px' ), 'the design\'s phone sizes on the site\'s breakpoint' );

szw_finish();
