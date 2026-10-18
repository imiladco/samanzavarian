<?php
/**
 * Service Tabs + Service Panel.
 *
 *   wp eval-file tests/service-test.php
 */

use Elementor\Plugin as Elementor;
use Samanzavarian_Widgets\Icon_Library;
use Samanzavarian_Widgets\Widgets\Scenes\AI_Showreel;
use Samanzavarian_Widgets\Widgets\Scenes\Content_Planner;
use Samanzavarian_Widgets\Widgets\Scenes\Direction_Routes;
use Samanzavarian_Widgets\Widgets\Scenes\Post_Edit;
use Samanzavarian_Widgets\Widgets\Service_Panel;
use Samanzavarian_Widgets\Widgets\Service_Tabs;
use Samanzavarian_Widgets\Widgets\Service_Tabs_Layout;

require_once __DIR__ . '/lib.php';

/** Children as Elementor stores them: a container per tab, a panel in each. */
function szw_tab_children( array $scenes = [ 'ai', 'direction', 'post', 'content' ] ): array {
	$out = [];
	foreach ( $scenes as $i => $scene ) {
		$c = Service_Tabs::child_container( 'Tab ' . $i, null === $scene ? [] : [ [ 'id' => 'pn' . $i, 'elType' => 'widget', 'widgetType' => 'sz-service-panel', 'settings' => Service_Panel::preset( $scene ), 'elements' => [] ] ] );
		$c['id'] = 'ct' . $i;
		$out[] = $c;
	}
	return $out;
}

/* ------------------------------------------------------------------ */

szw_group( 'Registration' );

$wm = Elementor::$instance->widgets_manager;
szw_ok( Service_Tabs::is_supported(), 'nested elements are available on this Elementor' );
szw_ok( null !== $wm->get_widget_types( 'sz-service-tabs' ) && null !== $wm->get_widget_types( 'sz-service-panel' ), 'both widgets are registered' );
szw_ok( wp_script_is( 'sz-service-tabs', 'registered' ) && wp_script_is( 'sz-service-panel', 'registered' ), 'frontend scripts are registered' );
szw_ok( wp_style_is( 'sz-service-tabs', 'registered' ) && wp_style_is( 'sz-service-panel', 'registered' ), 'stylesheets are registered' );

$config = $wm->get_widget_types( 'sz-service-tabs' )->get_config();
szw_ok( ! empty( $config['support_nesting'] ), 'Service Tabs declares nesting support' );

$defaults = $config['defaults']['elements'] ?? [];
szw_ok( 4 === count( $defaults ), 'four default containers' );
szw_ok( [ 'ai', 'direction', 'post', 'content' ] === array_map( static fn( $c ) => $c['elements'][0]['settings']['scene'] ?? '', $defaults ), 'each pre-filled with the design scene, in order' );
szw_ok( [ 'AI visual production', 'Creative direction', 'Post-production', 'Content systems' ] === array_column( array_column( $defaults, 'settings' ), '_title' ), 'containers are named after their tabs (navigator)' );
szw_ok( '0' === ( $defaults[0]['settings']['padding']['top'] ?? null ) && 'column' === ( $defaults[0]['settings']['flex_direction'] ?? '' ), 'containers have no padding of their own and stack their content' );

/* ------------------------------------------------------------------ */

szw_group( 'Tabs — render' );

$html = szw_render( [], 'sz-service-tabs', szw_tab_children() );

szw_ok( 4 === substr_count( $html, 'role="tab"' ), 'four tabs' );
szw_ok( [ 'true', 'false', 'false', 'false' ] === szw_all( '/role="tab"[^>]*aria-selected="(true|false)"/', $html ), 'the first is selected' );
szw_ok( [ '0', '-1', '-1', '-1' ] === szw_all( '/aria-selected="(?:true|false)" tabindex="(-?\d)"/', $html ), 'one tab stop (roving tabindex)' );
szw_ok( [ 'AI visual production', 'Creative direction', 'Post-production', 'Content systems' ] === szw_all( '/class="sz-tabs__title">([^<]+)</', $html ), 'design titles' );
szw_ok( 4 === substr_count( $html, 'class="sz-tabs__sub"' ), 'with subtitles' );
szw_ok( [ 'spark', 'palette', 'sliders', 'cal' ] === szw_all( '/sz-tabs__icon[^"]*"[^>]*><svg class="szi szi-([a-z]+)"/', $html ), 'design icons' );
szw_ok( 1 === substr_count( $html, 'sz-tabs__icon sz-app sz-app--orange' ), 'only the first tile is orange, as designed' );
szw_ok( 4 === substr_count( $html, 'class="sz-tabs__chev"' ) && false !== strpos( $html, 'class="sz-tabs__note"' ), 'chevrons and the note' );
szw_ok( 4 === substr_count( $html, 'role="tabpanel"' ), 'four tabpanels' );

preg_match_all( '/<button[^>]*id="([^"]+)" aria-controls="([^"]+)"/', $html, $t );
preg_match_all( '/<div[^>]*id="(sz-panel-[^"]+)"[^>]*role="tabpanel" aria-labelledby="([^"]+)"/', $html, $pn );
szw_ok( $t[2] === $pn[1] && $t[1] === $pn[2], 'every tab controls its panel and every panel is labelled by its tab' );
szw_ok( 1 === preg_match_all( '/class="[^"]*sz-tabs__panel is-active/', $html ), 'exactly one panel is open on load' );
szw_ok( 4 === substr_count( $html, 'class="sz-svc ' ), 'each panel holds its Service Panel' );

$html = szw_render( [ 'active_tab' => 3 ], 'sz-service-tabs', szw_tab_children() );
preg_match_all( '/class="([^"]*sz-tabs__panel[^"]*)"/', $html, $pc );
szw_ok( [ 'false', 'false', 'true', 'false' ] === szw_all( '/role="tab"[^>]*aria-selected="(true|false)"/', $html ) && [ 0, 0, 1, 0 ] === array_map( static fn( $c ) => (int) ( false !== strpos( $c, 'is-active' ) ), $pc[1] ), '"open tab on load" = 3 opens tab and panel 3' );
szw_ok( 0 === Service_Tabs::active_index( 9, 4 ) && 0 === Service_Tabs::active_index( 'x', 4 ) && 3 === Service_Tabs::active_index( 4, 4 ), 'an out-of-range tab number falls back to the first' );

$tabs = [
	[ '_id' => 'a1', 'tab_title' => '<b>Bold</b> & co', 'tab_anchor' => 'my tab<script>', 'tab_icon' => Icon_Library::value( 'spark' ) ],
	[ '_id' => 'a2', 'tab_title' => 'Two', 'tab_icon' => [ 'value' => '', 'library' => '' ] ],
];
$html = szw_render( [ 'tabs' => $tabs, 'show_note' => '', 'show_chevron' => '', 'update_url' => 'yes', 'list_label' => 'My "services"' ], 'sz-service-tabs', szw_tab_children( [ 'ai', null ] ) );
szw_ok( false !== strpos( $html, '&lt;b&gt;Bold&lt;/b&gt; &amp; co' ), 'tab titles are escaped' );
szw_ok( false !== strpos( $html, 'id="mytabscript"' ), 'a Link ID is reduced to a safe id (my tab<script> → mytabscript)' );
szw_ok( 1 === substr_count( $html, 'class="sz-tabs__icon' ), 'a tab without an icon renders no empty tile' );
szw_ok( false === strpos( $html, 'sz-tabs__note' ) && false === strpos( $html, 'sz-tabs__chev' ), 'note and chevrons can be turned off' );
szw_ok( false !== strpos( $html, 'data-sz-hash="1"' ), '"update address" reaches the script' );
szw_ok( false !== strpos( $html, 'aria-label="My &quot;services&quot;"' ), 'the list has its accessible name, escaped' );
szw_ok( false === strpos( $html, 'sz-tabs__sub' ), 'empty subtitles are skipped' );

szw_ok( false === strpos( szw_render( [ 'tabs' => [] ], 'sz-service-tabs', [] ), 'sz-tabs' ), 'no tabs → nothing rendered' );
szw_ok( 'unit-test-service' === Service_Tabs::ids( 'idt1', 0, '', 'Unit test service' )['tab'], 'no Link ID: the id is made from the title' );
szw_ok( 'unit-test-service-2' === Service_Tabs::ids( 'idt2', 0, '', 'Unit test service' )['tab'], 'a second tab with the same title on the page gets -2' );
szw_ok( 'unit-test-service' === Service_Tabs::ids( 'idt1', 0, '', 'Unit test service' )['tab'], 'the same tab rendered again keeps its id' );
szw_ok( 'sz-tab-idt3-1' === Service_Tabs::ids( 'idt3', 0, '', 'خدمات' )['tab'] && 'sz-tab-idt4-1' === Service_Tabs::ids( 'idt4', 0, '', '2024 plan' )['tab'], 'a title that makes no usable id (non-Latin, or starts with a digit) falls back' );
$html = szw_render( [ 'analytics' => '' ], 'sz-service-tabs', szw_tab_children() );
szw_ok( false !== strpos( $html, 'data-sz-track="0"' ) && false !== strpos( szw_render( [], 'sz-service-tabs', szw_tab_children() ), 'data-sz-track="1"' ), 'analytics on by default, can be turned off' );
szw_ok( false === strpos( szw_render( [ 'analytics' => '' ], 'sz-service-panel' ), 'data-sz-track' ), 'the panel button stops reporting when its analytics is off' );
szw_ok( [ 'tab' => 'sz-tab-u1-2', 'panel' => 'sz-panel-u1-2' ] === Service_Tabs::ids( 'u1', 1, '' ), 'default ids are unique per widget and position' );

/* ------------------------------------------------------------------ */

szw_group( 'Tabs — layout follows Elementor breakpoints' );

$css = szw_css( [ [ 'id' => 'c0ffee1', 'elType' => 'container', 'settings' => [], 'elements' => [ [ 'id' => 'tabs123', 'elType' => 'widget', 'widgetType' => 'sz-service-tabs', 'settings' => [], 'elements' => szw_tab_children() ] ] ] ] );
$bp  = Elementor::$instance->breakpoints;
$tab = $bp->get_breakpoints( 'tablet' )->get_value();
$mob = $bp->get_breakpoints( 'mobile' )->get_value();

szw_ok( false !== strpos( szw_at( $css, $tab ), Service_Tabs_Layout::vars( 'top' ) ), "tablet ({$tab}px): tabs become a row above the panel" );
szw_ok( false !== strpos( szw_at( $css, $mob ), Service_Tabs_Layout::vars( 'top' ) ), "mobile ({$mob}px): same" );
szw_ok( false === strpos( (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/widgets/service-tabs.css' ), '@media (max-width' ), 'no width breakpoints in the stylesheet' );

$side = Service_Tabs_Layout::LAYOUTS['side'];
$top  = Service_Tabs_Layout::LAYOUTS['top'];
szw_ok( array_keys( $side ) === array_keys( $top ), 'both layouts define the same properties (no value left over from the other)' );

$css = szw_css( [ [ 'id' => 'c0ffee1', 'elType' => 'container', 'settings' => [], 'elements' => [ [ 'id' => 'tabs456', 'elType' => 'widget', 'widgetType' => 'sz-service-tabs', 'settings' => [ 'layout_mobile' => 'side', 'icon_box_tablet' => [ 'unit' => 'px', 'size' => 60 ] ], 'elements' => szw_tab_children() ] ] ] ] );
$t   = szw_at( $css, $tab );
szw_ok( strpos( $t, '--szt-icon-box:60px' ) > strpos( $t, '--szt-icon-box:34px' ), 'a Style value at a breakpoint is printed after the layout and wins there' );
szw_ok( false !== strpos( szw_at( $css, $mob ), Service_Tabs_Layout::vars( 'side' ) ), 'any breakpoint can switch back to the side layout' );

/* ------------------------------------------------------------------ */

szw_group( 'Panel — render' );

$html = szw_render( [], 'sz-service-panel' );
szw_ok( false !== strpos( $html, 'sz-svc sz-w sz-svc--dark' ) && false !== strpos( $html, 'szs--ai' ), 'default: the AI panel on navy' );
szw_ok( [ 'Product key visuals', 'Campaign imagery', 'Short AI video' ] === array_map( 'trim', szw_all( '/<\/span>([^<]+)<\/li>/', $html ) ), 'design deliverables' );
szw_ok( (bool) preg_match( '/<a class="sz-svc__btn" href="#contact" data-sz-prefill="AI visuals" data-sz-track="AI visuals">Ask about AI visuals<\/a>/', $html ), 'button links to #contact, names its service for the contact form and for analytics' );
szw_ok( false !== strpos( $html, 'aria-hidden="true"' ) && (bool) preg_match( '/sz-svc__art szs szs--ai" data-szs="ai" data-szs-motion="once" aria-hidden="true"/', $html ), 'the scene is decoration, hidden from screen readers' );

foreach ( [ 'direction' => [ 'peach', 'szs-dir__swatch' ], 'post' => [ 'cool', 'szs-pe__grade' ], 'content' => [ 'grid', 'szs-cp__card' ] ] as $name => $expect ) {
	$h = szw_render( Service_Panel::preset( $name ), 'sz-service-panel' );
	szw_ok( false !== strpos( $h, 'sz-svc--' . $expect[0] ) && false !== strpos( $h, $expect[1] ), "preset {$name}: {$expect[0]} tone and its scene" );
}
szw_ok( 1 === substr_count( szw_render( [], 'sz-service-panel' ), '<div class="szs-stage">' ), 'every scene draws on one design stage (the runtime scales it to the panel)' );

$html = szw_render( [ 'scene' => 'media', 'media' => [ 'url' => 'https://x.test/a.mp4', 'id' => '' ] ], 'sz-service-panel' );
szw_ok( (bool) preg_match( '/<video class="sz-svc__media" src="https:\/\/x\.test\/a\.mp4" muted loop playsinline preload="metadata" data-sz-autoplay><\/video>/', $html ), 'a video is muted, inline, looped, and left for the script to play' );
szw_ok( false === strpos( $html, ' autoplay' ), 'never the autoplay attribute (it would play in closed tabs)' );

$html = szw_render( [ 'scene' => 'media', 'media' => [ 'url' => 'https://x.test/a.jpg', 'id' => '' ] ], 'sz-service-panel' );
szw_ok( (bool) preg_match( '/<img class="sz-svc__media" src="https:\/\/x\.test\/a\.jpg" alt="" loading="lazy"/', $html ), 'an image is lazy and decorative' );

$html = szw_render( [ 'text' => "Hi <script>bad()</script><b>you</b>", 'button_link' => [ 'url' => 'javascript:alert(1)' ], 'button_prefill' => '"x"', 'gets' => [ [ '_id' => 'g1', 'text' => '<i>x</i>' ] ] ], 'sz-service-panel' );
szw_ok( false === strpos( $html, 'bad()' ) && false !== strpos( $html, '<b>you</b>' ), 'card text: same filtering as Process descriptions' );
szw_ok( false === stripos( $html, 'javascript:' ), 'a javascript: link is dropped' );
szw_ok( false !== strpos( $html, 'data-sz-prefill="&quot;x&quot;"' ) && false !== strpos( $html, '&lt;i&gt;x&lt;/i&gt;' ), 'prefill and deliverables are escaped' );

$html = szw_render( [ 'button_link' => [ 'url' => '' ] ], 'sz-service-panel' );
szw_ok( false !== strpos( $html, '<span class="sz-svc__btn"' ), 'a button without a link is not a dead <a>' );

$html = szw_render( [ 'show_info' => '', 'scene' => 'none', 'tone' => 'none' ], 'sz-service-panel' );
szw_ok( false === strpos( $html, 'sz-svc__card' ) && false !== strpos( $html, 'sz-svc--none' ), 'card and scene can both be turned off' );

$css = szw_css( [ [ 'id' => 'c0ffee1', 'elType' => 'container', 'settings' => [], 'elements' => [ [ 'id' => 'pan1234', 'elType' => 'widget', 'widgetType' => 'sz-service-panel', 'settings' => [ 'panel_bg_background' => 'classic', 'panel_bg_image' => [ 'url' => 'https://x.test/bg.jpg', 'id' => '' ] ], 'elements' => [] ] ] ] ] );
szw_ok( (bool) preg_match( '/pan1234 \.sz-svc\{[^}]*url\("https:\/\/x\.test\/bg\.jpg"\)[^}]*--szs-tone:\s*none/', $css ) || (bool) preg_match( '/pan1234 \.sz-svc\{[^}]*--szs-tone:\s*none[^}]*url\("https:\/\/x\.test\/bg\.jpg"\)/', $css ), 'a background image replaces the tone gradient without being erased by it' );
szw_ok( false !== strpos( szw_at( $css, $mob ), 'min-height:270px' ) && false !== strpos( szw_at( $css, $tab ), 'min-height:300px' ), 'scene height: 320 / 300 / 270 at Elementor breakpoints (the design)' );

/* ------------------------------------------------------------------ */

szw_group( 'Panel — AI scene: brief and reel' );


$html = szw_render( [], 'sz-service-panel' );
szw_ok( 6 === substr_count( $html, 'class="szs-ai__item' ), 'six delivered pieces by default' );
szw_ok( 1 === substr_count( $html, ' is-focus' ) && 5 === substr_count( $html, ' szs-off' ), 'resting: the first piece in focus; the others wait (their videos stay paused)' );
szw_ok( [ '9:16', '16:9', '9:16', '1:1', '4:5', '16:9' ] === szw_all( '/data-ar="([^"]+)"/', $html ), 'each piece keeps its own shape (a Reel is 9:16, a website loop 16:9…)' );
szw_ok( [ 'left:0px;width:115px', 'left:129px;width:363px', 'left:506px;width:115px' ] === array_slice( szw_all( '/szs-ai__item[^"]*" style="([^"]+)"/', $html ), 0, 3 ), 'same height, width from the shape, 14px apart' );
szw_ok( (bool) preg_match( '/class="szs-ai__strip" style="transform:translateX\(148\.5px\)"/', $html ), 'the first piece is centred in the window without the script' );
szw_ok( (bool) preg_match( '/<span class="szs-ai__av" style="background-color:#8A5A3C">ME<\/span><span class="szs-ai__bt"><span class="szs-ai__who"><b>Maison Ellery<\/b> · client brief<\/span><span class="szs-ai__q">“Autumn scent/', $html ), 'the brief: initials made from the client\'s name, who, and their words' );
szw_ok( false !== strpos( $html, '<span class="szs-ai__n">01 / 06</span><span class="szs-ai__f">REEL · 0:00 / 0:05</span>' ), 'which piece, its format and its own clock' );
szw_ok( false !== strpos( $html, '<span class="szs-ai__cap">Showreel · 6 pieces</span>' ) && false !== strpos( $html, '<em>Approved · first round</em>' ), 'the reel label counts the pieces; the first result shows' );
szw_ok( 6 === substr_count( $html, 'szs-ph szs-ph--' ), 'no media yet: placeholder footage in every piece' );
preg_match( '/data-szs-ai="([^"]+)"/', $html, $m );
$cfg = json_decode( html_entity_decode( $m[1] ?? '' ), true );
szw_ok( is_array( $cfg ) && 6 === count( $cfg['pieces'] ) && true === $cfg['overview'] && true === $cfg['af'] && 1.0 === (float) $cfg['glide'], 'the script gets the pieces and the options' );
szw_ok( false === $cfg['pieces'][0]['fixed'] && 5.0 === (float) $cfg['pieces'][0]['secs'] && 6.0 === (float) $cfg['pieces'][1]['secs'], 'no seconds set: a video plays its own length; a placeholder its look\'s (5 s, 6 s…)' );

$pieces = AI_Showreel::pieces(
	[
		'ai_pieces' => [
			[ 'client' => 'a b c', 'brief' => '<b>x</b>', 'color' => 'red;x', 'ratio' => '1:1', 'seconds' => '45', 'media' => [ 'url' => 'https://x.test/v.mp4', 'id' => '' ] ],
			[ 'client' => '', 'initials' => 'QQQQ', 'look' => 'nope', 'ratio' => '7:3' ],
		],
	]
);
szw_ok( 204 === $pieces[0]['w'] && '1:1' === $pieces[0]['ar'] && 30.0 === $pieces[0]['secs'] && true === $pieces[0]['fixed'], 'a chosen shape and seconds (capped at 30)' );
szw_ok( 'AB' === $pieces[0]['ini'] && '' === $pieces[0]['color'] && false !== strpos( $pieces[0]['html'], '<video class="szs-ai__media"' ), 'initials from the name, an unsafe colour dropped, a video piece' );
szw_ok( 'QQQ' === $pieces[1]['ini'] && 'watch' === $pieces[1]['look'] && '16:9' === $pieces[1]['ar'], 'initials at most three; an unknown look and shape fall back' );
szw_ok( false !== strpos( AI_Showreel::brief_html( $pieces[0], '' ), '“&lt;b&gt;x&lt;/b&gt;”' ), 'briefs are escaped' );
szw_ok( '1:05' === AI_Showreel::clock( 65.9 ) && '0:05' === AI_Showreel::clock( 5 ), 'clock: m:ss, like a video player' );

$panel = Elementor::$instance->widgets_manager->get_widget_types( 'sz-service-panel' );
try {
	$deps = $panel->get_script_depends();
	szw_ok( in_array( 'sz-scene-ai', $deps, true ) && in_array( 'sz-scene-ai', $panel->get_style_depends(), true ), 'the bare widget type (no settings) asks for every scene script and stylesheet, without failing' );
} catch ( \Throwable $e ) {
	szw_ok( false, 'the bare widget type asks for assets without failing: ' . $e->getMessage() );
}
$make = static fn( array $set ) => Elementor::$instance->elements_manager->create_element_instance( [ 'id' => 'dep1234', 'elType' => 'widget', 'widgetType' => 'sz-service-panel', 'settings' => $set ] );
szw_ok( [ 'sz-service-panel', 'sz-scene-ai' ] === $make( [] )->get_script_depends() && [ 'sz-service-panel', 'sz-scene-ai' ] === $make( [] )->get_style_depends(), 'an AI panel loads only the AI scene\'s script and stylesheet' );
szw_ok( [ 'sz-service-panel' ] === $make( [ 'scene' => 'media' ] )->get_script_depends() && [ 'sz-service-panel' ] === $make( [ 'scene' => 'media' ] )->get_style_depends(), 'a media panel loads no scene assets' );
szw_ok( wp_script_is( 'sz-scene-ai', 'registered' ) && in_array( 'sz-service-panel', wp_scripts()->registered['sz-scene-ai']->deps, true ) && wp_style_is( 'sz-scene-ai', 'registered' ), 'scene scripts and stylesheets load after the panel\'s own' );

/* ------------------------------------------------------------------ */

szw_group( 'Panel — Creative direction scene: routes' );

$html = szw_render( Service_Panel::preset( 'direction' ), 'sz-service-panel' );
szw_ok( [ '#0D1B2A', '#FF6B2C', '#FFE6DA', '#E3E8EE' ] === szw_all( '/<b style="background-color:([^"]+)"><\/b>/', $html ), 'resting: route A\'s palette' );
szw_ok( 3 === preg_match_all( '/szs-dir__clip"><div class="szs-dir__look szs-dir__ph lk-[abc] look-warm"/', $html ), 'three placeholder pictures in route A\'s look' );
szw_ok( [ 'Post · 4:5', 'Story · 9:16', 'Ad · 1:1' ] === szw_all( '/<span class="szs-dir__tag">([^<]+)</', $html ), 'the three formats are labelled' );
szw_ok( false !== strpos( $html, '<b class="szs-dir__nh">Direction note</b><span class="szs-dir__nt"><span>Quiet, warm light. Honest materials. One hero object per frame.</span></span>' ), 'the design\'s note' );
szw_ok( 0 === substr_count( $html, 'szs-dir__look"><' ) || false === strpos( $html, '<img' ), 'no text inside the frames: pictures only' );
preg_match( '/data-szs-direction="([^"]+)"/', $html, $m );
$cfg = json_decode( html_entity_decode( $m[1] ?? '' ), true );
szw_ok( is_array( $cfg ) && 3 === count( $cfg['routes'] ) && 0 === $cfg['choice'] && 2600.0 === (float) $cfg['hold'], 'three routes for the script; the client\'s choice varies; 2.6 s on each' );
szw_ok( false !== strpos( $cfg['before']['pics']['a'], 'look-before-a' ) && 4 === count( $cfg['before']['pal'] ), 'before direction: three different shoots, a palette that agrees with nothing' );
szw_ok( 'Route {letter} of {count} · {name}' === $cfg['labels']['dir_say_route'], 'the narration is editable text' );

$routes = Direction_Routes::routes( [ 'dir_routes' => [ [ 'name' => 'X', 'c1' => '#123', 'c2' => 'red;x', 'pic_a' => [ 'url' => 'https://x.test/a.mp4', 'id' => '' ], 'look' => 'bad' ] ] ] );
szw_ok( [ '#123' ] === $routes[0]['pal'] && false !== strpos( $routes[0]['pics']['a'], '<video class="szs-dir__media"' ) && false !== strpos( $routes[0]['pics']['b'], 'look-warm' ), 'unsafe colours dropped; a frame can be a video; others fall back to the look' );
szw_ok( Direction_Routes::has_video( [ 'dir_routes' => [ [ 'pic_c' => [ 'url' => 'https://x.test/c.webm', 'id' => '' ] ] ] ] ) && ! Direction_Routes::has_video( [] ), 'a video in any route counts (pause button)' );
preg_match( '/data-szs-direction="([^"]+)"/', szw_render( array_merge( Service_Panel::preset( 'direction' ), [ 'dir_choice' => '3' ] ), 'sz-service-panel' ), $m );
szw_ok( 3 === json_decode( html_entity_decode( $m[1] ), true )['choice'], 'the client can be made to choose a set route' );
$n = Direction_Routes::notes( [ 'dir_routes' => [ [ 'name' => 'Solo', 'c1' => '#000' ] ] ] );
szw_ok( 2 === count( $n ) && false !== strpos( $n[0], 'Only one route' ), 'one route, or a short palette, is pointed out' );
szw_ok( [ 'sz-service-panel', 'sz-scene-direction' ] === $make( [ 'scene' => 'direction' ] )->get_script_depends(), 'a Direction panel loads only its own scene script' );

/* ------------------------------------------------------------------ */

szw_group( 'Panel — Post-production scene: the edit' );

$html = szw_render( Service_Panel::preset( 'post' ), 'sz-service-panel' );
szw_ok( (bool) preg_match( '/class="sz-svc__art szs szs--post" data-szs="post" data-szs-motion="once">/', $html ), 'the scene is not hidden from assistive tech (it has controls)…' );
szw_ok( 1 === substr_count( $html, 'class="szs-pe__tl" aria-hidden="true"' ) && 1 === substr_count( $html, 'class="szs-pe__pill" aria-hidden="true"' ), '…its decoration is, piece by piece' );
preg_match_all( '/<label for="([^"]+)">([^<]+)<\/label>/', $html, $lb );
preg_match_all( '/<input class="szs-pe__input" id="([^"]+)"/', $html, $in );
szw_ok( [ 'Warmth', 'Shadows' ] === $lb[2] && $lb[1] === $in[1], 'two real range inputs, labelled: Warmth, Shadows' );
szw_ok( (bool) preg_match( '/--p:0\.68;background:linear-gradient\(90deg,#8FB3E0,#E9ECEF,#FFB07F\)/', $html ) && (bool) preg_match( '/--p:0\.42;background:linear-gradient\(90deg,#1B2633,#E9ECEF\)/', $html ), 'knobs at 68% / 42% on the design\'s tracks' );
szw_ok( [ '+18', '−12' ] === szw_all( '/<output class="szs-pe__val"[^>]*>([^<]+)</', $html ), 'values signed with a real minus, as designed' );
szw_ok( false !== strpos( $html, '<span class="szs-pe__mode" data-m="stop"><i></i><em></em></span><span class="szs-pe__time">00:00:12:00</span>' ), 'resting: the transport stopped at 00:00:12:00' );
szw_ok( false !== strpos( $html, '<span>Final export ready · v1</span>' ) && false !== strpos( $html, 'Drag to scrub' ), 'resting label; a hint that the timeline can be scrubbed' );
szw_ok( 2 === substr_count( $html, 'class="szs-pe__lane"' ) && 1 === substr_count( $html, 'class="szs-pe__lane is-audio"' ) && substr_count( $html, 'class="szs-pe__clip' ) > 12, 'two video tracks and an audio track, filled with clips around the playhead' );
szw_ok( (bool) preg_match( '/class="szs-pe__lanes" style="transform:translateX\(-156px\)"/', $html ) && false !== strpos( $html, 'class="szs-pe__head" style="transform:translateX(276px)"' ), 'the playhead rests at 12 s, 276px into the card (36px a second)' );
szw_ok( false !== strpos( $html, '<b>00:12</b>' ) && false !== strpos( $html, '<b>00:16</b>' ), 'the ruler is labelled every 2 s' );
szw_ok( 0 === substr_count( szw_render( array_merge( Service_Panel::preset( 'post' ), [ 'post_audio' => '' ] ), 'sz-service-panel' ), 'szs-pe__lane is-audio' ), 'the audio track can be left out' );
preg_match( '/data-szs-post="([^"]+)"/', $html, $m );
$cfg = json_decode( html_entity_decode( $m[1] ?? '' ), true );
szw_ok( is_array( $cfg ) && 12.0 === (float) $cfg['t0'] && 25 === $cfg['fps'] && 3 === $cfg['every'] && 'fast' === $cfg['pace'] && [ 'blade', 'trim', 'insert', 'skim', 'look' ] === $cfg['edits'], 'the script gets the clock, the pace and the editor\'s moves' );
szw_ok( '01:02:05:15' === Post_Edit::timecode( 3725.5, 30 ) && '00:00:12:00' === Post_Edit::timecode( 12, 25 ), 'timecode: hh:mm:ss:ff at the frame rate' );
szw_ok( [ '#abc', '#123456' ] === Post_Edit::colors( '#abc, red;x, #123456', '#000' ) && [ '#000' ] === Post_Edit::colors( 'nope;', '#000' ), 'track colours: unsafe ones dropped, a fallback if none is left' );
$adj = Post_Edit::adjustments( [ 'post_adjustments' => [ [ 'label' => 'X', 'min' => 10, 'max' => 5, 'value' => 99, 'effect' => 'evil' ] ] ] );
szw_ok( 10.0 === $adj[0]['min'] && 11.0 === $adj[0]['max'] && 11.0 === $adj[0]['value'] && 'none' === $adj[0]['effect'], 'a broken slider range is repaired, the value clamped, an unknown effect ignored' );
szw_ok( '+18' === Service_Panel::signed( 18.4 ) && '−12' === Service_Panel::signed( -12 ) && '0' === Service_Panel::signed( -0.3 ), 'signed(): +18, −12, 0' );
szw_ok( '#abc' === Service_Panel::css_color( '#abc' ) && 'var(--e-global-color-accent)' === Service_Panel::css_color( 'var(--e-global-color-accent)' ) && '' === Service_Panel::css_color( 'red;background:url(x)' ), 'colours are checked before going into a style attribute' );
szw_ok( [ 'sz-service-panel', 'sz-scene-post' ] === $make( [ 'scene' => 'post' ] )->get_script_depends(), 'a Post panel loads only its own scene script' );

/* ------------------------------------------------------------------ */

szw_group( 'Panel — Content scene: the rolling plan' );

$fixed = array_merge( Service_Panel::preset( 'content' ), [ 'content_mode' => 'fixed' ] );
$html  = szw_render( $fixed, 'sz-service-panel' );
szw_ok( false !== strpos( $html, '<span class="szs-cp__wno"><span>Week 42</span></span>' ) && (bool) preg_match( '/class="szs-cp__day is-today"[^>]*><b>Wed</', $html ), 'fixed week: Week 42, Wednesday highlighted' );
szw_ok( [ 'Reel', 'Carousel', 'Story', 'Reel' ] === szw_all( '/<em class="szs-cp__chip[^>]*><span>([^<]+)</', $html ) && [ '18:30', '09:30', '12:00', '19:30' ] === szw_all( '/<small>([^<]+)<\/small>/', $html ), 'the design\'s four posts, each at its time' );
szw_ok( (bool) preg_match( '/--tc:#0D1B2A;--tf:#FFFFFF"><span>Reel/', $html ) && (bool) preg_match( '/--tc:#FFFFFF;--tf:#111318;--tl:#E8EAED"><span>Story/', $html ), 'formats carry their colours (Story outlined)' );
szw_ok( false !== strpos( $html, '<b>This week is planned</b>4 posts scheduled' ), 'resting notification counts the week\'s posts' );
szw_ok( 5 === substr_count( $html, '<span><i class="k-' ) && false !== strpos( $html, 'Shared to stories' ), 'a key under the days explains every mark' );
szw_ok( false === strpos( szw_render( array_merge( $fixed, [ 'content_key' => '' ] ), 'sz-service-panel' ), 'szs-cp__key' ), 'the key can be turned off' );

$live = szw_render( Service_Panel::preset( 'content' ), 'sz-service-panel' );
szw_ok( false !== strpos( $live, '<span>Week ' . (int) wp_date( 'W' ) . '</span>' ), 'real-week mode (default): the server prints the site\'s current week; the script then uses the visitor\'s' );

$formats = Content_Planner::formats( [] );
$plan    = Content_Planner::plan( [ 'content_plan_mon' => 'Carousel 09:30 + story 21:00', 'content_plan_tue' => 'Podcast', 'content_plan_wed' => 'Reel', 'content_plan_thu' => 'Reel 03:00' ], $formats );
szw_ok( 1 === $plan[0]['type'] && 9.5 === $plan[0]['h'] && 2 === $plan[1]['type'] && 21.0 === $plan[1]['h'], 'two posts in a day; format names match case-insensitively' );
szw_ok( -1 === $plan[2]['type'] && 'Podcast' === $plan[2]['name'] && 12.0 === (float) $plan[2]['h'], 'an unknown format still shows, at noon' );
szw_ok( 12.0 === (float) $plan[3]['h'] && 12.0 === (float) $plan[4]['h'], 'no time, or a time in the night: the format\'s first usual time' );
szw_ok( 18.5 === Content_Planner::hour( '18:30' ) && 9.0 === Content_Planner::hour( '9' ) && null === Content_Planner::hour( '25:00' ) && null === Content_Planner::hour( 'x' ), 'times: 18:30, 9, and nonsense' );
$f = Content_Planner::formats( [ 'content_formats' => [ [ 'name' => ' ', 'bg' => '#000' ], [ 'name' => 'Live', 'bg' => 'red;x', 'times' => '05:00, 20:00, nope', 'weight' => 9 ] ] ] );
szw_ok( 1 === count( $f ) && '' === $f[0]['bg'] && [ 20.0 ] === $f[0]['times'] && 5 === $f[0]['weight'], 'formats: nameless dropped, unsafe colours emptied, night times dropped, share capped' );
preg_match( '/data-szs-content="([^"]+)"/', $html, $m );
$cfg = json_decode( html_entity_decode( $m[1] ?? '' ), true );
szw_ok( is_array( $cfg ) && [ 9, 19 ] === $cfg['rules']['work'] && 3 === $cfg['rules']['lead'] && 2 === $cfg['rules']['cap'] && 3 === $cfg['rules']['planDay'] && 1.4 === (float) $cfg['rules']['quiet'], 'the team\'s rules reach the script: office hours, lead time, parallel work, planning day, pace' );
$html = szw_render( array_merge( $fixed, [ 'content_week_label' => '<i>W{n}</i>', 'content_rest_text' => '{count} <b>x</b>' ] ), 'sz-service-panel' );
szw_ok( false !== strpos( $html, '&lt;i&gt;W42&lt;/i&gt;' ) && false !== strpos( $html, '4 &lt;b&gt;x&lt;/b&gt;' ), 'labels are escaped' );
szw_ok( [ 'sz-service-panel', 'sz-scene-content' ] === $make( [ 'scene' => 'content' ] )->get_script_depends(), 'a Content panel loads only its own scene script' );

/* ------------------------------------------------------------------ */

szw_group( 'Panel — motion policy' );

$html = szw_render( [], 'sz-service-panel' );
szw_ok( false !== strpos( $html, 'data-szs="ai" data-szs-motion="once"' ), 'default: play once, then settle' );
szw_ok( (bool) preg_match( '/<button type="button" class="sz-svc__motion" aria-pressed="false" hidden data-label-pause="Pause animation" data-label-play="Play animation" aria-label="Pause animation">/', $html ), 'a pause button, hidden until something moves (the script shows it)' );
szw_ok( false === strpos( szw_render( [ 'motion' => 'static' ], 'sz-service-panel' ), 'sz-svc__motion' ), '"Still": no button' );
szw_ok( false !== strpos( szw_render( [ 'motion' => 'loop' ], 'sz-service-panel' ), 'data-szs-motion="loop"' ), '"Keep playing" reaches the script' );
szw_ok( false === strpos( szw_render( [ 'motion_button' => '' ], 'sz-service-panel' ), 'sz-svc__motion' ), 'the button can be turned off' );
szw_ok( false !== strpos( szw_render( [ 'scene' => 'media', 'media' => [ 'url' => 'https://x.test/v.mp4', 'id' => '' ] ], 'sz-service-panel' ), 'sz-svc__motion' ), 'a panel video gets the pause button too' );
szw_ok( false === strpos( szw_render( [ 'scene' => 'media', 'media' => [ 'url' => 'https://x.test/p.jpg', 'id' => '' ] ], 'sz-service-panel' ), 'sz-svc__motion' ), 'a still image does not' );
szw_ok( false !== strpos( szw_render( [ 'motion' => 'static', 'ai_pieces' => [ [ 'client' => 'A', 'media' => [ 'url' => 'https://x.test/v.webm', 'id' => '' ] ], [ 'client' => 'B' ] ] ], 'sz-service-panel' ), 'sz-svc__motion' ), '"Still" scene with a video piece: the video still gets a pause button' );

/* ------------------------------------------------------------------ */

szw_group( 'Panel — notes for the editor' );

szw_ok( [] === Service_Panel::editor_notes( 'content', Service_Panel::preset( 'content' ) ) && [] === Service_Panel::editor_notes( 'ai', [] ) && [] === Service_Panel::editor_notes( 'direction', [] ) && [] === Service_Panel::editor_notes( 'post', [] ), 'the defaults raise nothing' );
$n = Service_Panel::editor_notes( 'content', [ 'content_plan_mon' => 'Podcast + reel', 'content_plan_tue' => 'Podcast' ] );
szw_ok( 1 === count( $n ) && false !== strpos( $n[0], 'Not a format: Podcast.' ), 'a mistyped format is named once (and "reel" still matches Reel)' );
$n = Service_Panel::editor_notes( 'post', [ 'post_adjustments' => [ [ 'label' => 'Glow', 'min' => 5, 'max' => 5, 'value' => 9 ] ] ] );
szw_ok( 1 === count( $n ) && false !== strpos( $n[0], 'Slider "Glow"' ), 'an impossible slider range is reported' );
szw_ok( 1 === count( Service_Panel::editor_notes( 'post', [ 'post_edits' => [ 'x' ] ] ) ), 'an editor with nothing to do is pointed out' );
szw_ok( 1 === count( Service_Panel::editor_notes( 'ai', [ 'ai_pieces' => [ [ 'client' => 'A', 'brief' => str_repeat( 'x', 120 ) ], [ 'client' => 'B' ] ] ] ) ) && 1 === count( Service_Panel::editor_notes( 'ai', [ 'ai_pieces' => [ [ 'client' => 'A' ] ] ] ) ), 'a brief too long for two lines, or a reel of one, is pointed out' );
szw_ok( false === strpos( szw_render( [ 'scene' => 'content', 'content_plan_mon' => 'Podcast' ], 'sz-service-panel' ), 'sz-svc__editor-note' ), 'visitors never see these notes' );

szw_finish();
