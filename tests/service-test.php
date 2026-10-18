<?php
/**
 * Service Tabs + Service Panel.
 *
 *   wp eval-file tests/service-test.php
 */

use Elementor\Plugin as Elementor;
use Samanzavarian_Widgets\Icon_Library;
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
szw_ok( (bool) preg_match( '/<a class="sz-svc__btn" href="#contact" data-sz-prefill="AI visuals">Ask about AI visuals<\/a>/', $html ), 'button links to #contact and names its service for the contact form' );
szw_ok( false !== strpos( $html, 'aria-hidden="true"' ) && (bool) preg_match( '/sz-svc__art szs szs--ai" data-szs="ai" aria-hidden="true"/', $html ), 'the scene is decoration, hidden from screen readers' );

foreach ( [ 'direction' => [ 'peach', 'szs-swatch' ], 'post' => [ 'cool', 'szs-grade' ], 'content' => [ 'grid', 'szs-week' ] ] as $name => $expect ) {
	$h = szw_render( Service_Panel::preset( $name ), 'sz-service-panel' );
	szw_ok( false !== strpos( $h, 'sz-svc--' . $expect[0] ) && false !== strpos( $h, $expect[1] ), "preset {$name}: {$expect[0]} tone and its scene" );
}

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

szw_group( 'Panel — AI scene' );

$rounds = Service_Panel::ai_rounds( [ 'ai_frames' => [ [ 'mesh' => '1' ], [ 'mesh' => '2' ], [ 'mesh' => '3' ], [ 'mesh' => '4' ], [ 'mesh' => '5' ] ] ] );
szw_ok( 2 === count( $rounds ) && [ '4', '5', '1' ] === array_column( $rounds[1], 'mesh' ), '5 frames → 2 rounds; the short last round is completed from the start' );
szw_ok( 1 === count( Service_Panel::ai_rounds( [] ) ) && 3 === count( Service_Panel::ai_rounds( [] )[0] ), 'no frames → the design\'s three placeholders' );
szw_ok( [ 'One', 'Two' ] === Service_Panel::ai_prompts( [ 'ai_prompts' => "One\r\n\n  Two  \n" ] ), 'prompts: one per line, blank lines dropped, trimmed' );

$html = szw_render( [], 'sz-service-panel' );
preg_match( '/data-szs-ai="([^"]+)"/', $html, $m );
$cfg = json_decode( html_entity_decode( $m[1] ?? '' ), true );
szw_ok( is_array( $cfg ) && [ 'Perfume bottle on wet stone, soft rim light, 85mm' ] === $cfg['prompts'] && 2 === $cfg['pick'] && 38.0 === (float) $cfg['speed'] && 4000.0 === (float) $cfg['hold'] && true === $cfg['cycle'], 'the script gets its timing and texts from the settings (design defaults)' );
szw_ok( (bool) preg_match( '/<span class="szs-gen__text">Perfume bottle on wet stone, soft rim light, 85mm<\/span><b class="szs-gen__go">Generate<\/b>/', $html ), 'printed finished: the prompt is already typed' );
szw_ok( [ '2', '1', '3' ] === szw_all( '/szs-gen__frame szs-mesh szs-mesh--(\d)/', $html ) && 1 === substr_count( $html, 'is-picked' ) && false !== strpos( $html, 'szs-mesh--1 is-picked' ), 'the design\'s frames, the middle one approved' );
szw_ok( false !== strpos( $html, '<span class="szs-picked__text">Variation 2 approved</span>' ), 'approval label with its number' );

$html = szw_render( [ 'ai_frames' => [ [ 'mesh' => '1' ], [ 'mesh' => '2' ], [ 'mesh' => '3' ], [ 'mesh' => '4', 'media' => [ 'url' => 'https://x.test/v.webm', 'id' => '' ] ] ], 'ai_pick' => '9', 'ai_approved' => '<b>{n}</b>', 'ai_prompts' => '<script>x</script>' ], 'sz-service-panel' );
szw_ok( 1 === substr_count( $html, 'szs-gen__grid is-current' ) && 1 === substr_count( $html, 'szs-gen__grid szs-off' ), 'later rounds are printed hidden' );
szw_ok( false !== strpos( $html, 'szs-gen__frame szs-mesh szs-mesh--4"><video class="szs-gen__media"' ), 'a frame can be a video' );
szw_ok( false !== strpos( $html, '&lt;b&gt;3&lt;/b&gt;' ) && false === strpos( $html, '<script>x' ), 'an out-of-range pick is clamped (9 → 3); labels and prompts are escaped' );

$panel = Elementor::$instance->widgets_manager->get_widget_types( 'sz-service-panel' );
try {
	$deps = $panel->get_script_depends();
	szw_ok( in_array( 'sz-scene-ai', $deps, true ), 'the bare widget type (no settings) asks for every scene script, without failing' );
} catch ( \Throwable $e ) {
	szw_ok( false, 'the bare widget type asks for scripts without failing: ' . $e->getMessage() );
}
$make = static fn( array $set ) => Elementor::$instance->elements_manager->create_element_instance( [ 'id' => 'dep1234', 'elType' => 'widget', 'widgetType' => 'sz-service-panel', 'settings' => $set ] );
szw_ok( [ 'sz-service-panel', 'sz-scene-ai' ] === $make( [] )->get_script_depends(), 'an AI panel loads only the AI scene script' );
szw_ok( [ 'sz-service-panel' ] === $make( [ 'scene' => 'media' ] )->get_script_depends(), 'a media panel loads no scene script' );
szw_ok( wp_script_is( 'sz-scene-ai', 'registered' ) && in_array( 'sz-service-panel', wp_scripts()->registered['sz-scene-ai']->deps, true ), 'scene scripts load after the panel runtime' );

/* ------------------------------------------------------------------ */

szw_group( 'Panel — Post-production scene' );

$html = szw_render( Service_Panel::preset( 'post' ), 'sz-service-panel' );
szw_ok( (bool) preg_match( '/class="sz-svc__art szs szs--post" data-szs="post">/', $html ), 'the scene is not hidden from assistive tech (it has controls)…' );
szw_ok( 2 === substr_count( $html, 'class="szs-tl szs-a" aria-hidden="true"' ) + substr_count( $html, 'class="szs-pill szs-a" aria-hidden="true"' ), '…its decoration is, piece by piece' );
szw_ok( 2 === substr_count( $html, 'type="range"' ), 'two real range inputs' );
preg_match_all( '/<label for="([^"]+)">([^<]+)<\/label>/', $html, $lb );
preg_match_all( '/<input class="szs-grade__input" id="([^"]+)"/', $html, $in );
szw_ok( [ 'Warmth', 'Shadows' ] === $lb[2] && $lb[1] === $in[1], 'each labelled: Warmth, Shadows' );
szw_ok( (bool) preg_match( '/--p:0\.68;background:linear-gradient\(90deg,#8FB3E0,#E9ECEF,#FFB07F\)/', $html ) && (bool) preg_match( '/--p:0\.42;background:linear-gradient\(90deg,#1B2633,#E9ECEF\)/', $html ), 'knobs at 68% / 42% on the design\'s tracks' );
szw_ok( [ '+18', '−12' ] === szw_all( '/<output class="szs-grade__val"[^>]*>([^<]+)</', $html ), 'values signed with a real minus, as designed' );
szw_ok( (bool) preg_match( '/min="-50" max="50" step="1" value="18" data-effect="warmth" aria-valuetext="\+18"/', $html ), 'range, value, effect and spoken value' );
szw_ok( [ 'flex:3;background:#0D1B2A', 'flex:2;background:#2A4560', 'flex:2;background:#0D1B2A' ] === array_slice( szw_all( '/<i style="([^"]+)"><\/i>/', $html ), 0, 3 ), 'timeline clips: the design\'s first track' );
szw_ok( false !== strpos( $html, '<span class="szs-pill__text">Final export ready</span>' ) && false === strpos( $html, 'szs-shot' ), 'resting label; no preview image unless one is set' );

szw_ok( '+18' === Service_Panel::signed( 18.4 ) && '−12' === Service_Panel::signed( -12 ) && '0' === Service_Panel::signed( -0.3 ), 'signed(): +18, −12, 0' );
szw_ok( '#abc' === Service_Panel::css_color( '#abc' ) && 'var(--e-global-color-accent)' === Service_Panel::css_color( 'var(--e-global-color-accent)' ) && '' === Service_Panel::css_color( 'red;background:url(x)' ), 'colours are checked before going into a style attribute' );
$adj = Service_Panel::post_adjustments( [ 'post_adjustments' => [ [ 'label' => 'X', 'min' => 10, 'max' => 5, 'value' => 99, 'effect' => 'evil' ] ] ] );
szw_ok( 10.0 === $adj[0]['min'] && 11.0 === $adj[0]['max'] && 11.0 === $adj[0]['value'] && 'none' === $adj[0]['effect'], 'a broken range is repaired, the value clamped, an unknown effect ignored' );
$tracks = Service_Panel::post_tracks( [ 'post_clips' => [ [ 'track' => '3', 'length' => 99, 'color' => '#fff' ], [ 'track' => '9', 'length' => 0, 'color' => 'bad;x' ] ] ] );
szw_ok( 1 === count( $tracks ) && [ [ 'length' => 12, 'color' => '#fff' ], [ 'length' => 1, 'color' => '' ] ] === $tracks[0], 'clips: track 9 → 3, lengths 1–12, a bad colour dropped, empty tracks left out' );

$html = szw_render( array_merge( Service_Panel::preset( 'post' ), [ 'post_preview' => [ 'url' => 'https://x.test/p.jpg', 'id' => '' ] ] ), 'sz-service-panel' );
szw_ok( (bool) preg_match( '/<figure class="szs-shot szs-a" aria-hidden="true"><img class="szs-shot__media" src="https:\/\/x\.test\/p\.jpg"/', $html ), 'a preview image joins the scene' );
szw_ok( [ 'sz-service-panel', 'sz-scene-post' ] === $make( [ 'scene' => 'post' ] )->get_script_depends(), 'a Post panel loads only its own scene script' );

/* ------------------------------------------------------------------ */

szw_group( 'Panel — Content calendar scene' );

$fixed = array_merge( Service_Panel::preset( 'content' ), [ 'content_mode' => 'fixed' ] );
$html  = szw_render( $fixed, 'sz-service-panel' );
szw_ok( false !== strpos( $html, '<span class="szs-week__no">Week 42</span>' ) && 1 === preg_match_all( '/class="szs-day is-today"><span class="szs-day__name">Wed</', $html ), 'fixed week: Week 42, Wednesday highlighted (the design)' );
szw_ok( [ 'Reel', 'Carousel', 'Story', 'Reel' ] === szw_all( '/<em class="szs-post"[^>]*>([^<]+)</', $html ), 'the design\'s four posts, in day order' );
szw_ok( (bool) preg_match( '/style="background:#0D1B2A;color:#FFFFFF;box-shadow:none;">Reel/', $html ) && (bool) preg_match( '/style="background:#FFFFFF;color:#111318;box-shadow:0 0 0 1px #E8EAED;">Story/', $html ), 'post types carry the design\'s colours (Story outlined)' );
szw_ok( false !== strpos( $html, '<span class="szs-notif__text">4 posts scheduled</span>' ), 'the notification counts the week\'s posts' );

$plan = Service_Panel::content_plan( [] );
szw_ok( (int) wp_date( 'W' ) === $plan['week'] && (int) wp_date( 'N' ) === $plan['today'] && true === $plan['live'], 'real-week mode (default): the server prints the site\'s current week and day; the script then uses the visitor\'s' );
szw_ok( [ [ 0 ], [], [ 1 ], [ 2 ], [], [ 0 ], [] ] === $plan['weeks'][0] && [ 1, 2 ] === $plan['weeks'][2][4], 'plans: type indexes per day, "Carousel + Story" is two posts' );

$plan = Service_Panel::content_plan( [ 'content_types' => [ [ 'name' => 'Live', 'bg' => 'red;x:y', 'fg' => '#000', 'line' => '' ], [ 'name' => '  ' ] ], 'content_weeks' => [ [ 'mon' => 'live + Podcast', 'tue' => '' ] ], 'content_days' => 'a,b' ] );
szw_ok( 1 === count( $plan['types'] ) && '' === $plan['types'][0]['bg'], 'nameless types are dropped, unsafe colours emptied' );
szw_ok( [ 0, [ 'name' => 'Podcast' ] ] === $plan['weeks'][0][0], 'type names match case-insensitively; an unknown name still shows as itself' );
szw_ok( 'Mon' === $plan['days'][0], 'day names: anything but seven falls back to Mon…Sun' );

$html = szw_render( array_merge( $fixed, [ 'content_week_label' => '<i>W{n}</i>', 'content_done_text' => '{count} <b>x</b>' ] ), 'sz-service-panel' );
szw_ok( false !== strpos( $html, '&lt;i&gt;W42&lt;/i&gt;' ) && false !== strpos( $html, '4 &lt;b&gt;x&lt;/b&gt;' ), 'labels are escaped' );
szw_ok( [ 'sz-service-panel', 'sz-scene-content' ] === $make( [ 'scene' => 'content' ] )->get_script_depends(), 'a Content panel loads only its own scene script' );

szw_finish();
