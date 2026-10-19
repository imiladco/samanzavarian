<?php
/**
 * Icon words in Elementor's own Text Editor and Heading (Inline_Icons).
 *
 *   wp eval-file tests/tokens-test.php
 */

use Samanzavarian_Widgets\Inline_Icons;

require_once __DIR__ . '/lib.php';

szw_group( 'Icon words in the Text Editor and Heading' );

$text = 'I trained in {cam:photography} and spent six years shooting {box:products} and {video:brand films} for commercial studios. Today most of my work is {spark*:AI image and video}.';
$html = szw_render( [ 'editor' => '<p>' . $text . '</p>', '_css_classes' => 'sz-statement' ], 'text-editor' );

szw_ok( 4 === substr_count( $html, 'style="--i:' ) && false === strpos( $html, '{cam:' ), 'the four codes become pills, on the server' );
szw_ok( false !== strpos( $html, '<span class="sz-tok is-o" style="--i:3">' ), 'the * makes the tile orange; each pill knows its place in the reading order' );
szw_ok( false !== strpos( $html, '<span class="sz-tok__lead"><span class="sz-tok__tile" aria-hidden="true"><svg class="sz-tok__svg"' ) && false !== strpos( $html, '</span>brand</span> films</span>' ), 'the icon is glued to the first word; the rest may wrap; the icon is hidden from readers' );
szw_ok( false !== strpos( $html, 'I trained in ' ) && false !== strpos( $html, 'sz-statement' ), 'the rest of the text untouched; the statement class kept' );

$html = szw_render( [ 'editor' => '<p>See {cam:photography|#work-photo}, keep {nope:this} and {braces as text}.</p>' ], 'text-editor' );
szw_ok( false !== strpos( $html, '<a class="sz-tok is-link" href="#work-photo"' ), 'a | link makes the pill a link' );
szw_ok( false !== strpos( $html, '{nope:this}' ) && false !== strpos( $html, '{braces as text}' ), 'unknown icons and other braces stay as written' );

$html = szw_render( [ 'title' => 'Shooting {cam:products}' ], 'heading' );
szw_ok( 1 === substr_count( $html, 'style="--i:' ), 'the Heading widget too' );

$html = szw_render( [ 'editor' => '<p>{cam:<b>bold</b>} {cam:x|javascript:alert(1)}</p>' ], 'text-editor' );
szw_ok( false === strpos( $html, 'javascript:' ), 'no script links' );
szw_ok( false === strpos( Inline_Icons::convert( '<p>{cam:a&quot;onmouseover=x}</p>' ), 'onmouseover=x"' ), 'words are escaped' );

szw_ok( false !== strpos( (string) file_get_contents( SZW_PATH . 'assets/css/tokens.css' ), 'box-decoration-break:clone' ), 'a wrapped pill keeps its shape on the next line' );

szw_finish();
