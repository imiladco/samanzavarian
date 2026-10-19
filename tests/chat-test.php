<?php
/**
 * Quote & Reply widget.
 *
 *   wp eval-file tests/chat-test.php
 */

use Elementor\Plugin as Elementor;

require_once __DIR__ . '/lib.php';

szw_group( 'Quote & Reply' );

szw_ok( null !== Elementor::$instance->widgets_manager->get_widget_types( 'sz-chat' ) && wp_style_is( 'sz-chat', 'registered' ) && wp_script_is( 'sz-chat', 'registered' ), 'registered, with its stylesheet and script' );

$html = szw_render( [], 'sz-chat' );
szw_ok( false !== strpos( $html, '<blockquote class="sz-chat__bubble">' ) && false !== strpos( $html, 'The tool matters less than the idea.' ) && false !== strpos( $html, '<figcaption class="sz-chat__cap">Saman, on working with AI</figcaption>' ), 'a quote with its caption, as a figure' );
szw_ok( false !== strpos( $html, 'S<b>Z</b>' ) && false !== strpos( $html, 'sz-chat__tap is-heart' ) && false === strpos( $html, '❤' ), 'monogram; the heart is drawn, not an emoji' );
szw_ok( false !== strpos( $html, 'class="sz-chat__typing" aria-hidden="true"' ), 'typing dots, hidden from readers' );
szw_ok( [ 'AI visuals', 'Edit my footage', 'Direction' ] === array_map( 'trim', szw_all( '/class="sz-chat__chip"[^>]*>(?:<span aria-hidden="true">[^<]*<\/span>)?([^<]*)</', $html ) ), 'the design\'s three quick replies' );
szw_ok( false !== strpos( $html, 'data-text="I need AI visuals for a product launch." data-choice="AI visuals"' ), 'each writes its message and names a form option' );
szw_ok( false !== strpos( $html, 'maxlength="300"' ) && false !== strpos( $html, 'enterkeyhint="send"' ) && false !== strpos( $html, 'aria-label="Send" disabled' ), 'field: limit, send key on phones; send waits for text' );
szw_ok( false !== strpos( $html, '<label class="sz-chat__sr" for="szc-szwtest-t">Message to Saman</label>' ), 'the field has a label' );
szw_ok( false !== strpos( $html, 'data-intro="1" data-action="form"' ), 'arrives as a conversation; hands over to the page\'s form' );

$html = szw_render( [ 'tapback' => 'emoji', 'tapback_emoji' => '🔥', 'show_reply' => '', 'avatar' => [ 'url' => 'https://example.com/me.jpg', 'id' => '' ] ], 'sz-chat' );
szw_ok( false !== strpos( $html, 'sz-chat__tap is-emoji' ) && false !== strpos( $html, '🔥' ) && false === strpos( $html, '<form' ) && false !== strpos( $html, 'src="https://example.com/me.jpg"' ), 'emoji reaction; no reply box; a photo avatar' );

$html = szw_render( [ 'action' => 'whatsapp', 'phone' => '+98 912 123 4567', 'analytics' => '' ], 'sz-chat' );
szw_ok( false !== strpos( $html, 'data-action="whatsapp"' ) && false !== strpos( $html, 'data-phone="989121234567"' ) && false !== strpos( $html, 'data-track="0"' ), 'WhatsApp, the number cleaned to digits; analytics off' );
szw_ok( '' === szw_render( [ 'quote' => '' ], 'sz-chat' ), 'no quote: nothing' );

$css = szw_css( [ [ 'id' => 'ch', 'elType' => 'widget', 'widgetType' => 'sz-chat', 'settings' => [ 'bubble_bg' => '#123456', 'max_width' => [ 'size' => 600, 'unit' => 'px' ] ] ] ] );
szw_ok( false !== strpos( $css, '--szc-bubble:#123456' ) && false !== strpos( $css, '--szc-max:600px' ), 'style controls write CSS' );

szw_finish();
