<?php
/**
 * Process widget — runs inside a real WordPress + Elementor with this plugin
 * active. No stubs: the widget is built exactly the way Elementor builds it,
 * and the CSS Elementor generates from the controls is checked too.
 *
 *   wp eval-file tests/process-test.php
 *
 * Exits non-zero on the first failing group, so it can gate a commit.
 */

use Elementor\Core\Files\CSS\Post as Post_CSS;
use Elementor\Icons_Manager;
use Elementor\Plugin as Elementor;
use Samanzavarian_Widgets\Icon_Library;
use Samanzavarian_Widgets\Widgets\Process;
use Samanzavarian_Widgets\Widgets\Process_Layout;

/*
 * Through $GLOBALS on purpose: `wp eval-file` runs this file inside a
 * function, so a top-level `$failed` would be local and a `global $failed`
 * in the helper would count into a different variable — every failure would
 * still end in "ALL PASSED". (It did, once.)
 */
$GLOBALS['szw_failed'] = 0;

function szw_ok( bool $condition, string $label ): void {
	echo ( $condition ? "  ✓ " : "  ✗ " ) . $label . "\n";
	if ( ! $condition ) {
		$GLOBALS['szw_failed']++;
	}
}

function szw_group( string $title ): void {
	echo "\n" . $title . "\n";
}

/** Render one Process widget with the given settings, through Elementor. */
function szw_render( array $settings = [] ): string {
	$element = Elementor::$instance->elements_manager->create_element_instance(
		[
			'id'         => 'szwtest',
			'elType'     => 'widget',
			'widgetType' => 'sz-process',
			'settings'   => $settings,
		]
	);

	ob_start();
	$element->print_element();

	return (string) ob_get_clean();
}

/** @return string[] the n-th capture of every match */
function szw_all( string $pattern, string $html, int $group = 1 ): array {
	preg_match_all( $pattern, $html, $m );

	return $m[ $group ] ?? [];
}

function szw_step( string $id, string $title, string $desc = '', string $glyph = 'chat', array $extra = [] ): array {
	return array_merge(
		[
			'_id'         => $id,
			'title'       => $title,
			'description' => $desc,
			'icon'        => Icon_Library::value( $glyph ),
		],
		$extra
	);
}

/* ------------------------------------------------------------------ */

szw_group( 'Registration' );

szw_ok( null !== Elementor::$instance->widgets_manager->get_widget_types( 'sz-process' ), 'widget sz-process is registered' );
szw_ok( isset( Elementor::$instance->elements_manager->get_categories()['samanzavarian'] ), 'category "samanzavarian" is registered' );

$tabs = Icons_Manager::get_icon_manager_tabs();
szw_ok( isset( $tabs['sz-icons'] ), 'icon library tab is registered' );
szw_ok( isset( $tabs['sz-icons'] ) && ! isset( $tabs['sz-icons']['url'] ), 'outside the admin the tab has no stylesheet URL (nothing extra on the frontend)' );
szw_ok( wp_style_is( 'sz-process', 'registered' ) && wp_style_is( 'sz-base', 'registered' ), 'stylesheets are registered' );

/* ------------------------------------------------------------------ */

szw_group( 'Icon library' );

szw_ok( 29 === count( Icon_Library::GLYPHS ), '29 glyphs from the design sprite' );
szw_ok( 'chat' === Icon_Library::glyph_name( 'szi szi-chat' ), 'stored value → glyph name' );
szw_ok( null === Icon_Library::glyph_name( 'fas fa-star' ) && null === Icon_Library::glyph_name( 'szi szi-nope' ), 'foreign or unknown values are rejected' );

$via_elementor = (string) Icons_Manager::try_get_icon_html( Icon_Library::value( 'spark' ) );
szw_ok( false !== strpos( $via_elementor, '<svg class="szi szi-spark"' ) && false !== strpos( $via_elementor, 'stroke="currentColor"' ), "Elementor's own renderer outputs our inline SVG (so stock widgets can use these icons)" );

foreach ( Icon_Library::GLYPHS as $name => $inner ) {
	if ( preg_match( '/<(?!\/?(?:path|circle|rect)\b)/', $inner ) || preg_match( '/\son\w+=/i', $inner ) ) {
		szw_ok( false, "glyph $name holds only path/circle/rect geometry" );
	}
}
szw_ok( true, 'every glyph is static geometry (no script, no event handlers)' );

/* ------------------------------------------------------------------ */

szw_group( 'Layout pattern (measured from the design)' );

$p = static function ( int $cols ): array {
	$rows = [];
	for ( $k = 1; $k <= 4; $k++ ) {
		$rows[] = Process_Layout::position( $cols, $k );
	}
	return $rows;
};

$four = $p( 4 );
szw_ok( [ -2.0, 1.5, -1.0, 2.0 ] === array_column( $four, 'r' ), '4 columns: tilt -2°, 1.5°, -1°, 2° (the design)' );
szw_ok( [ 0.0, 1.0, 0.15, 1.15 ] === array_column( $four, 'y' ), '4 columns: stagger 0, 40, 6, 46px at 40px (the design)' );
szw_ok( [ 1, 1, 1, 0 ] === array_column( $four, 'a' ), '4 columns: no arrow out of the last column' );
szw_ok( [ 0, 1, 0, 1 ] === array_column( $four, 's' ), '4 columns: arrows alternate above / below' );

$two = $p( 2 );
szw_ok( [ -2.0, 2.0, -2.0, 2.0 ] === array_column( $two, 'r' ) && [ 0.0, 0.65, 0.0, 0.65 ] === array_column( $two, 'y' ), '2 columns: odd -2°, even 2° and 26px down (the design below 900px)' );
szw_ok( [ 1, 0, 1, 0 ] === array_column( $two, 'a' ), '2 columns: arrows never cross a row break' );

$one = $p( 1 );
szw_ok( [ 1, 1, 1, 1 ] === array_column( $one, 'a' ) && [ 1, 1, 1, 1 ] === array_column( $one, 'v' ), '1 column: every card points down to the next' );
szw_ok( [ 0, 1, 0, 1 ] === array_column( $one, 's' ), '1 column: the two drawn arrows alternate' );
szw_ok( [ 0, 0, 0, 0 ] === array_column( $four, 'v' ), 'side by side: no downward arrows' );
szw_ok( false !== strpos( Process_Layout::vars( 1 ), '--szp-pad:0;' ) && false !== strpos( Process_Layout::vars( 4 ), '--szp-pad:1;' ), 'no stagger room under a single column' );
szw_ok( 0 === Process_Layout::CYCLE % 3 && 0 === Process_Layout::CYCLE % 4, 'the 12-position cycle fits 3 and 4 columns exactly' );
szw_ok( false === strpos( Process_Layout::vars( 3 ), 'E' ) && false === strpos( Process_Layout::vars( 3 ), ',' ), 'numbers are written as plain CSS (no exponent, no locale comma)' );

/* ------------------------------------------------------------------ */

szw_group( 'Render — untouched widget is the design' );

$html = szw_render();

szw_ok( 4 === substr_count( $html, 'class="sz-process__item ' ), 'four default steps' );
szw_ok( [ 'light', 'dark', 'light', 'dark' ] === szw_all( '/sz-process__item--(light|dark)/', $html ), 'cards alternate light / dark' );
szw_ok( [ 'white', 'orange', 'white', 'orange' ] === szw_all( '/sz-app--(white|orange)/', $html ), 'icon tiles: white on light cards, orange on dark' );
szw_ok( [ 'chat', 'doc', 'spark', 'check' ] === szw_all( '/class="szi szi-([a-z]+)"/', $html ), 'the design icons: chat, doc, spark, check' );
szw_ok( [ 'Brief', 'Direction', 'Production', 'Delivery' ] === szw_all( '/<h3 class="sz-process__title">([^<]+)</', $html ), 'titles in <h3>' );
szw_ok( [ '1', '2', '3', '4' ] === szw_all( '/sz-process__num" aria-hidden="true">([^<]+)</', $html ), 'numbered automatically 1–4, hidden from screen readers' );
szw_ok( 3 === substr_count( $html, 'class="sz-process__arrow"' ), 'three arrows — none after the last card' );
szw_ok( false !== strpos( $html, '<ol class="sz-process sz-w" role="list">' ), 'an ordered list, with role=list kept for Safari/VoiceOver' );
szw_ok( false === strpos( $html, ' start=' ), 'no start attribute when counting from 1' );

/* ------------------------------------------------------------------ */

szw_group( 'Render — content handling' );

$html = szw_render(
	[
		'steps' => [
			szw_step( 'x1', '<script>alert(1)</script>Brief', "Line one\nLine <b>two</b> <a href=\"https://x.test\" onclick=\"evil()\">link</a><script>bad()</script><style>p{}</style>" ),
			szw_step( 'x2', 'Second', '', 'doc', [ 'skin' => 'light' ] ),
			szw_step( 'x3', '', 'Only text', 'nope' ),
		],
		'number_start' => 0,
		'number_pad'   => 'yes',
		'skin_pattern' => 'dark',
		'title_tag'    => 'h4',
	]
);

preg_match( '/class="sz-process__desc">(.*?)<\/p>/s', $html, $desc );
$desc = $desc[1] ?? '';

szw_ok( false !== strpos( $html, '&lt;script&gt;alert(1)&lt;/script&gt;Brief' ), 'a <script> in a title prints as text' );
szw_ok( false !== strpos( $desc, '<b>two</b>' ) && false !== strpos( $desc, 'href="https://x.test"' ), 'description keeps <b> and links' );
szw_ok( false === strpos( $desc, 'onclick' ) && false === stripos( $desc, '<script' ), 'description drops event handlers and <script>' );
szw_ok( false === strpos( $desc, 'bad()' ) && false === strpos( $desc, 'p{}' ), 'script and style blocks are removed whole, not left as stray text' );
szw_ok( false !== strpos( $desc, 'Line one<br>' ), 'line breaks become <br>' );
szw_ok( [ '00', '01', '02' ] === szw_all( '/sz-process__num" aria-hidden="true">([^<]+)</', $html ), 'start at 0 with leading zero: 00 01 02' );
szw_ok( false !== strpos( $html, 'start="0"' ), '<ol start="0"> mirrors the visible numbering' );
szw_ok( [ 'dark', 'light', 'dark' ] === szw_all( '/sz-process__item--(light|dark)/', $html ), 'dark-first pattern, with an explicit "light" overriding it' );
szw_ok( 2 === substr_count( $html, '<h4 class="sz-process__title"' ), 'title tag is configurable (empty titles are skipped)' );
szw_ok( 2 === substr_count( $html, 'class="sz-process__icon' ), 'an unknown icon value renders no empty tile' );
szw_ok( false !== strpos( $html, 'elementor-repeater-item-x1' ), 'each item carries its repeater class for per-item styling' );

$html = szw_render( [ 'title_tag' => 'script' ] );
szw_ok( false === strpos( $html, '<script' ) && false !== strpos( $html, '<div class="sz-process__title"' ), 'a disallowed title tag falls back to <div>' );

$html = szw_render( [ 'show_number' => '' ] );
szw_ok( false === strpos( $html, 'sz-process__num' ), 'numbers can be turned off' );

$html = szw_render(
	[
		'steps' => [
			szw_step( 'l1', 'Linked', '', 'chat', [ 'link' => [ 'url' => 'https://example.test/brief', 'is_external' => 'on', 'nofollow' => '' ] ] ),
			szw_step( 'l2', 'Bad link', '', 'chat', [ 'link' => [ 'url' => 'javascript:alert(1)' ] ] ),
			szw_step( 'l3', 'Plain' ),
		],
	]
);
szw_ok( (bool) preg_match( '/<a class="sz-process__card" href="https:\/\/example\.test\/brief" target="_blank"/', $html ), 'a step link turns the whole card into <a>, new tab honoured' );
szw_ok( false === stripos( $html, 'javascript:' ), 'a javascript: link is not printed' );
szw_ok( 1 <= substr_count( $html, '<div class="sz-process__card"' ), 'steps without a link stay a <div>' );

/* ------------------------------------------------------------------ */

szw_group( 'Render — arrows' );

$html = szw_render();
szw_ok( 3 === substr_count( $html, '<svg class="sz-process__arrow"' ) && false === strpos( $html, 'sz-process__arrow--icon' ), 'default: the hand-drawn arrow from the design' );

$html = szw_render( [ 'arrow_type' => 'icon', 'arrow_icon' => Icon_Library::value( 'send' ) ] );
szw_ok( 3 === substr_count( $html, '<span class="sz-process__arrow sz-process__arrow--icon" aria-hidden="true"><svg class="szi szi-send"' ), 'an icon can replace the arrow, hidden from screen readers' );

$html = szw_render( [ 'arrow_type' => 'icon' ] );
szw_ok( 3 === substr_count( $html, 'sz-process__arrow--icon' ) && false !== strpos( $html, 'arrow-right' ), 'icon style without a pick uses the default arrow icon' );

$html = szw_render(
	[
		'steps' => [
			szw_step( 'a1', 'One', '', 'chat', [ 'arrow' => 'none' ] ),
			szw_step( 'a2', 'Two', '', 'chat', [ 'arrow' => 'icon', 'arrow_icon' => Icon_Library::value( 'up' ) ] ),
			szw_step( 'a3', 'Three' ),
			szw_step( 'a4', 'Four', '', 'chat', [ 'arrow' => 'icon', 'arrow_icon' => Icon_Library::value( 'send' ) ] ),
		],
	]
);
$arrows = szw_all( '/<li class="sz-process__item[^"]*elementor-repeater-item-(a\d)">.*?<\/(?:a|div)>\s*(<svg class="sz-process__arrow"|<span class="sz-process__arrow sz-process__arrow--icon" aria-hidden="true"><svg class="szi szi-[a-z]+")?/s', $html, 2 );
szw_ok( [ '', '<span class="sz-process__arrow sz-process__arrow--icon" aria-hidden="true"><svg class="szi szi-up"', '<svg class="sz-process__arrow"', '' ] === $arrows, 'per step: none, own icon, default — and never after the last step' );

$html = szw_render( [ 'arrow_type' => 'icon', 'arrow_icon' => [ 'value' => '', 'library' => '' ] ] );
szw_ok( 3 === substr_count( $html, '<svg class="sz-process__arrow"' ), 'an empty icon falls back to the drawn arrow instead of leaving gaps' );

$html = szw_render( [ 'steps' => [] ] );
szw_ok( false === strpos( $html, '<ol' ), 'no steps → no empty list' );

szw_ok( '09' === Process::number_label( 9, true ) && '10' === Process::number_label( 10, true ) && '7' === Process::number_label( 7, false ), 'padding only pads single digits' );

/* ------------------------------------------------------------------ */

szw_group( "Elementor's generated CSS for the controls" );

$post_id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'szw css test' ] );

update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
update_post_meta(
	$post_id,
	'_elementor_data',
	wp_slash(
		wp_json_encode(
			[
				[
					'id'       => 'c0ffee1',
					'elType'   => 'container',
					'settings' => [],
					'elements' => [
						[
							'id'         => 'abc1234',
							'elType'     => 'widget',
							'widgetType' => 'sz-process',
							'settings'   => [
								'columns'                  => '3',
								'columns_tablet'           => '2',
								'arrows_mobile'            => 'hide',
								'dark_card_bg_background'  => 'classic',
								'dark_card_bg_color'       => '#7A1FA2',
								'stagger'                  => [ 'unit' => 'px', 'size' => 24 ],
							],
							'elements'   => [],
						],
					],
				],
			]
		)
	)
);

$css = Post_CSS::create( $post_id );
$css->update();
$out = (string) $css->get_content();

szw_ok( (bool) preg_match( '/\.elementor-element-abc1234 \.sz-process\{[^}]*--szp-cols:3;/', $out ), 'Columns → the 3-column pattern on the widget' );
szw_ok( (bool) preg_match( '/@media\(max-width:\d+px\)\{[^@]*\.elementor-element-abc1234 \.sz-process\{[^}]*--szp-cols:1;/', $out ), 'mobile keeps its own default (1 column) when only desktop and tablet are set' );
szw_ok( (bool) preg_match( '/@media\(max-width:\d+px\)\{[^@]*\.elementor-element-abc1234 \.sz-process\{[^}]*--szp-cols:2;/', $out ), 'tablet value lands in a tablet media query' );
szw_ok( false !== strpos( $out, '--szp-arrows:0' ), 'arrows can be hidden per breakpoint' );
szw_ok( (bool) preg_match( '/sz-process__item--dark \.sz-process__card\{[^}]*background-color:#7A1FA2;[^}]*--szp-card-bg:\s*none;/', $out ), 'a plain colour on the dark card switches the design gradient off' );
szw_ok( false !== strpos( $out, '--szp-stagger:24px' ), 'stagger knob' );

/**
 * Elementor merges a selector's declarations by property name, so the switch
 * that turns the design gradient off must not touch `background-image`
 * itself — otherwise it can erase the user's own image. Each case below is a
 * background type a user can pick.
 */
$bg_case = static function ( array $settings, string $part = 'card' ): string {
	// A fresh post per case: within one request Elementor keeps a document's
	// earlier rules around, so reusing a post would read the previous case.
	$id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'szw bg case' ] );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta(
		$id,
		'_elementor_data',
		wp_slash( wp_json_encode( [ [ 'id' => 'c0ffee1', 'elType' => 'container', 'settings' => [], 'elements' => [ [ 'id' => 'bg12345', 'elType' => 'widget', 'widgetType' => 'sz-process', 'settings' => $settings, 'elements' => [] ] ] ] ] ) )
	);
	$file = Post_CSS::create( $id );
	$file->update();
	preg_match( '/bg12345 \\.sz-process__item--dark \\.sz-process__' . $part . '\\{([^}]*)\\}/', (string) $file->get_content(), $m );
	wp_delete_post( $id, true );

	return $m[1] ?? '';
};

$rule = $bg_case( [ 'dark_card_bg_background' => 'classic', 'dark_card_bg_image' => [ 'url' => 'https://example.test/bg.jpg', 'id' => '' ] ] );
szw_ok( false !== strpos( $rule, 'url("https://example.test/bg.jpg")' ) && false !== strpos( $rule, '--szp-card-bg' ), 'a background IMAGE on the card survives (and the gradient is switched off)' );

$rule = $bg_case( [ 'dark_card_bg_background' => 'gradient', 'dark_card_bg_color' => '#FF0000', 'dark_card_bg_color_b' => '#0000FF' ] );
szw_ok( false !== strpos( $rule, 'linear-gradient(180deg, #FF0000 0%, #0000FF 100%)' ), "the user's own GRADIENT on the card survives" );

$rule = $bg_case( [ 'dark_icon_bg_background' => 'classic', 'dark_icon_bg_color' => '#00AA55' ], 'icon' );
szw_ok( false !== strpos( $rule, 'background-color:#00AA55' ) && false !== strpos( $rule, '--sz-app-bg' ), 'a plain colour on the icon tile switches its gradient off' );

// An untouched widget: desktop is the stylesheet; tablet and mobile come from
// the controls' defaults and so sit in Elementor's media queries, at the
// site's own breakpoint widths — nothing about screen size is hard-coded.
update_post_meta(
	$post_id,
	'_elementor_data',
	wp_slash( wp_json_encode( [ [ 'id' => 'c0ffee1', 'elType' => 'container', 'settings' => [], 'elements' => [ [ 'id' => 'def5678', 'elType' => 'widget', 'widgetType' => 'sz-process', 'settings' => [], 'elements' => [] ] ] ] ] ) )
);

$css = Post_CSS::create( $post_id );
$css->update();
$out = (string) $css->get_content();

$tablet_max = Elementor::$instance->breakpoints->get_breakpoints( 'tablet' )->get_value();
$mobile_max = Elementor::$instance->breakpoints->get_breakpoints( 'mobile' )->get_value();
$at = static function ( int $max ) use ( $out ): string {
	preg_match_all( '/@media\(max-width:' . $max . 'px\)\{(.*?\})\}/s', $out, $m );
	return implode( '', $m[1] );
};

szw_ok( (bool) preg_match( '/def5678 \.sz-process\{[^}]*--szp-cols:2;/', $at( $tablet_max ) ), "tablet: 2 columns at Elementor's tablet breakpoint ({$tablet_max}px)" );
szw_ok( (bool) preg_match( '/def5678 \.sz-process\{[^}]*--szp-cols:1;/', $at( $mobile_max ) ), "mobile: 1 column at Elementor's mobile breakpoint ({$mobile_max}px)" );
szw_ok( false !== strpos( $at( $mobile_max ), '--szp-grid-x:0px' ), 'mobile: the grid does not reach past the edges (no sideways scroll)' );
szw_ok( false === strpos( (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/widgets/process.css' ), '@media (max-width' ), 'the stylesheet itself has no width breakpoints' );

wp_delete_post( $post_id, true );

/* ------------------------------------------------------------------ */

szw_group( 'Generated files are in sync with their source' );

$root = dirname( __DIR__ );

foreach ( [ 'tools/build-icons.php', 'tools/build-process-css.php' ] as $tool ) {
	exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $root . '/' . $tool ) . ' --check 2>&1', $lines, $code );
	szw_ok( 0 === $code, $tool . ' --check' );
	$lines = [];
}

/* ------------------------------------------------------------------ */

echo "\n" . ( $GLOBALS['szw_failed'] ? 'FAILED: ' . $GLOBALS['szw_failed'] : 'ALL PASSED' ) . "\n";

if ( $GLOBALS['szw_failed'] ) {
	exit( 1 );
}
