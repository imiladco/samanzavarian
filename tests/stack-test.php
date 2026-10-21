<?php
/**
 * Photo Stack widget — rendered through Elementor, as on a page.
 *
 *   wp eval-file tests/stack-test.php
 */

use Elementor\Plugin as Elementor;
use Samanzavarian_Widgets\Widgets\Photo_Stack;

require_once __DIR__ . '/lib.php';

szw_group( 'Photo Stack: registration' );

szw_ok( null !== Elementor::$instance->widgets_manager->get_widget_types( 'sz-stack' ), 'widget sz-stack is registered' );
szw_ok( wp_style_is( 'sz-stack', 'registered' ) && wp_script_is( 'sz-stack', 'registered' ), 'its stylesheet and script are registered' );

/* ------------------------------------------------------------------ */

szw_group( 'Photo Stack: the design out of the box' );

$html = szw_render( [], 'sz-stack' );

szw_ok( [ '0', '1', '2' ] === szw_all( '/data-slot="(\d)"/', $html ), 'three photos, front first' );
szw_ok( [ '', 'l', 'r' ] === szw_all( '/data-side="([lr]?)"/', $html ), 'the others sit left, then right' );
szw_ok( 3 === substr_count( $html, '<button type="button"' ) || 3 === substr_count( $html, 'type="button"' ), 'every photo is a button' );
szw_ok( false !== strpos( $html, 'aria-expanded="false" aria-label="Saman Zavarian, Visual creative. Show more photos"' ), 'the front one says what it opens' );
szw_ok( false !== strpos( $html, 'aria-label="Bring forward: On set" tabindex="-1"' ), 'the others are out of the tab order until fanned' );
szw_ok( 1 === substr_count( $html, 'sz-stack__mono' ) && false !== strpos( $html, 'S<b>Z</b>' ), 'the monogram, with its accent letter' );
szw_ok( 3 === substr_count( $html, 'sz-stack__mesh--' ), 'no photos: the design\'s gradients' );
szw_ok( [ 'Portrait', 'On set', 'In the edit' ] === array_map( 'trim', szw_all( '/class="sz-stack__lab"[^>]*>(?:<span class="sz-app[^"]*">.*?<\/span>)?([^<]*)<\/span>/s', $html ) ), 'labels for the fan' );
szw_ok( [ ' sz-app--orange', ' sz-app--orange', '' ] === szw_all( '/class="sz-stack__lab"[^>]*><span class="sz-app( sz-app--orange)?"/', $html ), 'label tiles: orange, orange, navy (the design)' );
szw_ok( false !== strpos( $html, 'Behind the scenes, product shoot' ), 'each photo has its own caption' );
szw_ok( false !== strpos( $html, 'class="sz-stack__sticker is-emoji" aria-hidden="true">📸' ), 'the sticker' );
szw_ok( false !== strpos( $html, 'Hover to fan out' ) && false !== strpos( $html, 'Tap to fan out' ) && false !== strpos( $html, 'Tap or swipe to bring a photo forward' ), 'hints for mouse and touch, closed and fanned' );
szw_ok( false !== strpos( $html, 'has-hint' ) && false !== strpos( $html, 'aria-live="polite"' ), 'room for the hint; a live region for swaps' );
szw_ok( false !== strpos( $html, 'data-edit-open="0"' ), 'never held open outside the editor' );

/* ------------------------------------------------------------------ */

szw_group( 'Photo Stack: settings' );

$card = static fn( $title, $extra = [] ) => array_merge( [ '_id' => substr( md5( $title ), 0, 7 ), 'title' => $title, 'subtitle' => 'Sub', 'label' => '', 'icon' => [], 'tile' => 'orange' ], $extra );

$html = szw_render( [ 'cards' => [ $card( 'Only' ) ] ], 'sz-stack' );
szw_ok( false !== strpos( $html, 'is-single' ) && false === strpos( $html, '<button' ) && false === strpos( $html, 'sz-stack__hint' ) && false === strpos( $html, 'sz-stack__lab' ), 'one photo: a plain card, nothing to fan' );

$html = szw_render( [ 'cards' => array_map( $card, [ 'A', 'B', 'C', 'D', 'E', 'F', 'G' ] ) ], 'sz-stack' );
szw_ok( Photo_Stack::MAX_CARDS === substr_count( $html, 'data-slot=' ), 'at most five photos' );
szw_ok( [ '', 'l', 'r', 'l', 'r' ] === szw_all( '/data-side="([lr]?)"/', $html ), 'five fan out left and right in turn' );
szw_ok( false !== strpos( $html, '>B</span>' ), 'an empty label falls back to the caption title' );

$html = szw_render( [ 'cards' => [ $card( 'A', [ 'image' => [ 'url' => 'https://example.com/a.jpg', 'id' => '' ], 'monogram' => 'yes' ] ), $card( 'B' ) ] ], 'sz-stack' );
szw_ok( false !== strpos( $html, '<img class="sz-stack__img" src="https://example.com/a.jpg" alt="A"' ) && false === strpos( $html, 'sz-stack__mono' ), 'a photo replaces gradient and monogram, with its title as alt text' );

$html = szw_render( [ 'cards' => [ $card( 'A', [ 'video' => [ 'url' => 'https://example.com/a.mp4', 'id' => '' ] ] ), $card( 'B', [ 'video' => [ 'url' => 'https://example.com/b.pdf', 'id' => '' ] ] ) ] ], 'sz-stack' );
szw_ok( 1 === substr_count( $html, 'has-video' ) && false !== strpos( $html, '<video class="sz-stack__video" data-src="https://example.com/a.mp4" muted loop playsinline preload="none"' ), 'a video: over the photo, muted, nothing downloaded until the deck nears the screen' );
szw_ok( false === strpos( $html, ' src="https://example.com/a.mp4"' ) && false === strpos( $html, 'b.pdf' ), 'no src on the page; a file that is not a video is ignored' );
szw_ok( false !== strpos( $html, 'sz-stack__mesh--' ) , 'the photo (here the gradient) stays under the video' );

$html = szw_render( [ 'sticker' => 'icon' ], 'sz-stack' );
szw_ok( false !== strpos( $html, 'sz-stack__sticker is-icon' ) && false !== strpos( $html, 'szi-cam' ), 'icon sticker' );
szw_ok( false === strpos( szw_render( [ 'sticker' => '' ], 'sz-stack' ), 'sz-stack__sticker' ), 'no sticker' );

$html = szw_render( [ 'hint' => '', 'bring' => '', 'peek' => '', 'parallax' => '', 'analytics' => '' ], 'sz-stack' );
szw_ok( false === strpos( $html, 'sz-stack__hint' ) && false === strpos( $html, 'has-hint' ), 'hint off' );
szw_ok( false !== strpos( $html, 'data-bring="0" data-peek="0" data-parallax="0" data-scroll="d t m" data-track="0"' ), 'behaviour switches reach the script; scroll-driven on everywhere by default' );
szw_ok( false !== strpos( szw_render( [ 'scroll_on' => [ 'mobile', 'desktop' ] ], 'sz-stack' ), 'data-scroll="d m"' ), 'scroll-driven: the chosen devices, in a fixed order' );
szw_ok( false !== strpos( szw_render( [ 'scroll_on' => [] ], 'sz-stack' ), 'data-scroll=""' ), 'scroll-driven: off' );

$html = szw_render( [ 'label_show' => 'Mehr Fotos', 'label_hover' => 'Zeigen' ], 'sz-stack' );
szw_ok( false !== strpos( $html, 'Mehr Fotos' ) && false !== strpos( $html, '>Zeigen<' ), 'hints and labels are editable' );

/* ------------------------------------------------------------------ */

szw_group( 'Photo Stack: style controls write CSS' );

$css = szw_css( [ [ 'id' => 's1', 'elType' => 'widget', 'widgetType' => 'sz-stack', 'settings' => [ 'width' => [ 'size' => 360, 'unit' => 'px' ], 'mono_accent' => '#123456' ] ] ] );
szw_ok( false !== strpos( $css, '--szk-w:360px' ), 'width' );
szw_ok( false !== strpos( szw_at( $css, 1024 ), '--szk-w:330px' ) && false !== strpos( szw_at( $css, 767 ), '--szk-w:250px' ), 'design sizes for tablet and phone, on the site\'s breakpoints' );
szw_ok( false !== strpos( szw_at( $css, 767 ), '--szk-radius:28px' ) && false !== strpos( szw_at( $css, 767 ), '--szk-st:46px' ), 'phone radius and sticker' );
szw_ok( false !== strpos( $css, '--szk-mono-b:#123456' ), 'monogram accent colour' );

szw_finish();
