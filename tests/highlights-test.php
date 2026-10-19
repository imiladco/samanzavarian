<?php
/**
 * Highlights widget ("What I bring to a project").
 *
 *   wp eval-file tests/highlights-test.php
 */

use Elementor\Plugin as Elementor;

require_once __DIR__ . '/lib.php';

szw_group( 'Highlights' );

szw_ok( null !== Elementor::$instance->widgets_manager->get_widget_types( 'sz-highlights' ) && wp_style_is( 'sz-highlights', 'registered' ) && wp_script_is( 'sz-highlights', 'registered' ), 'registered, with its stylesheet and script' );

$html = szw_render( [], 'sz-highlights' );
szw_ok( false !== strpos( $html, '<h3 class="sz-hl__head" id="szh-szwtest-h">What I bring to a project</h3>' ), 'the heading, which also names the carousel' );
szw_ok( 6 === substr_count( $html, 'aria-roledescription="slide"' ) && false !== strpos( $html, 'Visual storytelling' ) && false !== strpos( $html, 'without losing the craft' ), 'the design\'s six cards, all in the HTML' );
szw_ok( 1 === substr_count( $html, 'sz-hl__slide is-on' ) && 5 === substr_count( $html, 'aria-hidden="true" inert' ), 'one card live; the others hidden from readers and keys, still sizing the deck' );
szw_ok( 6 === substr_count( $html, 'role="tab"' ) && false !== strpos( $html, 'aria-label="Art direction"' ) && 6 === substr_count( $html, 'class="sz-hl__tip"' ), 'segments are tabs, each named after its card, with a tooltip' );
szw_ok( false !== strpos( $html, 'class="sz-hl__pp" aria-pressed="false" aria-label="Pause" hidden' ), 'a pause button (shown by the script, only when the deck moves)' );
szw_ok( false !== strpos( $html, 'aria-live="off"' ) && false !== strpos( $html, 'data-auto="1" data-duration="5200"' ), 'moving by itself: not read out; 5.2 s a card' );
szw_ok( false !== strpos( $html, '<span class="sz-hl__i">1</span></span> of 6' ) && false !== strpos( $html, 'has-sheets' ), 'counter, sheets' );
szw_ok( 1 === substr_count( $html, 'sz-app sz-app--orange' ), 'the first tile orange, as designed' );

$html = szw_render( [ 'autoplay' => '' ], 'sz-highlights' );
szw_ok( false === strpos( $html, 'sz-hl__pp' ) && false !== strpos( $html, 'aria-live="polite"' ) && false !== strpos( $html, 'data-auto="0"' ), 'still: no pause button, changes read out' );

$html = szw_render( [ 'items' => [ [ 'title' => 'Only', 'text' => 'One card' ] ] ], 'sz-highlights' );
szw_ok( false === strpos( $html, 'sz-hl__foot' ) && false === strpos( $html, 'sz-hl__n' ) && false === strpos( $html, 'has-sheets' ) && false === strpos( $html, 'sz-hl__pp' ), 'one card: no controls, counter, sheets or pause' );

$html = szw_render( [ 'counter' => '', 'sheets' => '', 'heading_tag' => 'p', 'label_of' => 'von' ], 'sz-highlights' );
szw_ok( false === strpos( $html, 'sz-hl__n' ) && false === strpos( $html, 'has-sheets' ) && false !== strpos( $html, '<p class="sz-hl__head"' ) && false !== strpos( $html, 'aria-label="2 von 6"' ), 'counter and sheets off, heading tag, labels' );
szw_ok( '' === szw_render( [ 'items' => [] ], 'sz-highlights' ), 'no cards: nothing' );

$css = szw_css( [ [ 'id' => 'hl', 'elType' => 'widget', 'widgetType' => 'sz-highlights', 'settings' => [ 'max_width' => [ 'size' => 520, 'unit' => 'px' ], 'seg_fill' => '#123456' ] ] ] );
szw_ok( false !== strpos( $css, '--szh-max:520px' ) && false !== strpos( $css, '--szh-seg-on:#123456' ) && false !== strpos( szw_at( $css, 767 ), '--szh-radius:24px' ), 'style controls; the design\'s phone radius on the site\'s breakpoint' );

szw_finish();
