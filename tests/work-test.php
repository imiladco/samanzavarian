<?php
/**
 * Selected Work widget — rendered through Elementor, as on a page.
 *
 *   wp eval-file tests/work-test.php
 */

use Elementor\Plugin as Elementor;
use Samanzavarian_Widgets\Widgets\Work_Rail;

require_once __DIR__ . '/lib.php';

szw_group( 'Selected Work: registration' );

szw_ok( null !== Elementor::$instance->widgets_manager->get_widget_types( 'sz-work' ), 'widget sz-work is registered' );
szw_ok( wp_style_is( 'sz-work', 'registered' ) && wp_script_is( 'sz-work', 'registered' ), 'its stylesheet and script are registered' );

/* ------------------------------------------------------------------ */

szw_group( 'Selected Work: the design out of the box' );

$html = szw_render( [ 'source' => 'manual' ], 'sz-work' );

szw_ok( 8 === substr_count( $html, 'aria-roledescription="slide" aria-label=' ) || 9 === substr_count( $html, 'aria-roledescription="slide"' ), 'eight projects and the last card are slides' );
szw_ok( 1 === substr_count( $html, 'sz-work__end"' ) || 1 === substr_count( $html, 'sz-work__end ' ) || false !== strpos( $html, 'sz-work__card sz-work__end' ), 'one last card' );
szw_ok( false !== strpos( $html, 'See all 106+ projects' ), '{total} is filled in the last card' );
szw_ok( [ 'All', 'AI', 'Film', 'Photo', 'Post' ] === szw_all( '/data-label="([^"]*)"/', $html ), 'filters: All, then the design\'s four, in order' );
szw_ok( [ '8', '2', '1', '3', '2' ] === szw_all( '/<sup aria-hidden="true">(\d+)<\/sup>/', $html ), 'each filter shows its count' );
szw_ok( [ 'work-all', 'work-ai', 'work-film', 'work-photo', 'work-post' ] === szw_all( '/class="sz-work__filter" id="([^"]+)"/', $html ), 'filter ids are the address: #work-photo' );
szw_ok( 1 === substr_count( $html, 'aria-pressed="true"' ), 'one filter is pressed' );
szw_ok( 0 === substr_count( $html, 'hidden="hidden"' ), 'under "All" no card is hidden' );
szw_ok( 1 === preg_match_all( '/sz-work__card[^"]*is-wide/', $html ) && preg_match( '/is-wide[^>]*>.*?AI product key visuals/s', $html ), 'the featured project is the wide card' );
szw_ok( false !== strpos( $html, 'aria-label="1 of 8: AI product key visuals"' ), 'cards are named "1 of 8: title"' );
szw_ok( false !== strpos( $html, '<b>1</b> of 8' ), 'position under the slider' );
szw_ok( false !== strpos( $html, 'class="sz-work__profile"' ) && false !== strpos( $html, 'Open Behance profile' ), 'the profile link beside the filters' );
szw_ok( 10 === substr_count( $html, 'href="https://www.behance.net/"' ), 'projects without a link, the last card and the profile link go to the profile' );
szw_ok( 8 === substr_count( $html, 'sz-work__mesh--' ), 'no images: the design\'s gradients' );
szw_ok( false === strpos( $html, '<video' ) && false === strpos( $html, 'sz-work__pv' ), 'no video: no preview label' );
szw_ok( false !== strpos( $html, 'data-sz-hash="1"' ) && false !== strpos( $html, 'data-sz-track="1"' ), 'address and analytics on by default' );
szw_ok( false !== strpos( $html, 'is-bleed' ), 'slides out to the window edges by default' );
szw_ok( false === strpos( $html, 'sz-work__note' ), 'no editor notes on the frontend' );

/* ------------------------------------------------------------------ */

szw_group( 'Selected Work: filters' );

$p = static fn( $title, $filter, $extra = [] ) => array_merge( [ '_id' => substr( md5( $title ), 0, 7 ), 'title' => $title, 'category' => 'Cat', 'filter' => $filter ], $extra );

$html = szw_render( [ 'source' => 'manual', 'projects' => [ $p( 'One', 'Photo' ), $p( 'Two', 'Colour grading' ), $p( 'Three', 'photo' ), $p( 'Four', 'Typo' ) ],
		'filters'  => [ [ 'label' => 'Film' ], [ 'label' => 'Photo' ], [ 'label' => 'Colour grading', 'slug' => 'grade' ] ],
	],
	'sz-work'
);

szw_ok( [ 'All', 'Photo', 'Colour grading' ] === szw_all( '/data-label="([^"]*)"/', $html ), 'a filter without projects (Film) is not shown' );
szw_ok( [ '4', '2', '1' ] === szw_all( '/<sup aria-hidden="true">(\d+)<\/sup>/', $html ), 'matching ignores case: "photo" counts under Photo' );
szw_ok( false !== strpos( $html, 'id="work-grade"' ) && false !== strpos( $html, 'data-match="colour-grading"' ), 'a Link ID becomes the address; the name still matches' );

$s = [ 'source' => 'manual', 'projects' => [ $p( 'One', 'Photo' ), $p( 'Four', 'Typo' ) ], 'filters' => [ [ 'label' => 'Photo' ] ] ];
szw_ok( 1 === count( Work_Rail::notes( Work_Rail::projects( $s ), Work_Rail::filters( $s, Work_Rail::projects( $s ) ), [ 'source' => 'manual', 'show_end' => '', 'show_profile' => '' ] ) ), 'editor note: a project whose filter matches none' );

$html = szw_render( [ 'source' => 'manual', 'projects' => [ $p( 'One', 'Photo' ), $p( 'Two', 'Film' ), $p( 'Three', 'Photo' ) ], 'filters' => [ [ 'label' => 'Photo' ], [ 'label' => 'Film' ] ], 'show_all' => '' ], 'sz-work' );
szw_ok( 'work-photo' === ( szw_all( '/id="([^"]+)"[^>]*aria-pressed="true"/', $html )[0] ?? '' ), '"All" off: opens on the first filter' );
szw_ok( 1 === substr_count( $html, 'hidden="hidden"' ) && preg_match( '/hidden="hidden"[^>]*>.*?Two/s', $html ), 'and only its projects show' );
szw_ok( false !== strpos( $html, '<b>1</b> of 2' ) && false !== strpos( $html, 'aria-label="2 of 2: Three"' ), 'position and names count that filter\'s projects' );

$html = szw_render( [ 'source' => 'manual', 'projects' => [ $p( 'One', 'Photo' ) ], 'show_filters' => '', 'show_profile' => '' ], 'sz-work' );
szw_ok( false === strpos( $html, 'sz-work__filters' ) && false === strpos( $html, 'sz-work__top' ), 'filters off and no profile link: no top row' );

$html = szw_render( [ 'source' => 'manual', 'projects' => [ $p( 'One', 'Photo' ) ], 'wide_lead' => '' ], 'sz-work' );
szw_ok( false === strpos( $html, 'is-wide' ) && false !== strpos( $html, 'data-sz-wide="0"' ), 'wide first card can be turned off' );

/* ------------------------------------------------------------------ */

szw_group( 'Selected Work: cards' );

$video = [ 'url' => 'https://example.com/clip.mp4', 'id' => '' ];
$html  = szw_render( [ 'source' => 'manual', 'projects'  => [
			$p( 'Linked', 'Photo', [ 'link' => [ 'url' => 'https://example.com/case', 'is_external' => 'on' ], 'video' => $video, 'skin' => 'light' ] ),
			$p( 'Pic', 'Photo', [ 'image' => [ 'url' => 'https://example.com/a.jpg', 'id' => '' ] ] ),
			$p( '', 'Photo' ),
		],
		'title_tag' => 'h2',
		'end_total' => 40,
		'end_title' => 'All {total}',
	],
	'sz-work'
);

szw_ok( false !== strpos( $html, 'href="https://example.com/case" target="_blank"' ), 'a project\'s own link, opening in a new tab' );
szw_ok( false !== strpos( $html, 'data-src="https://example.com/clip.mp4"' ) && false === strpos( $html, ' src="https://example.com/clip.mp4"' ), 'the preview video waits for a hover to download' );
szw_ok( false !== strpos( $html, 'preload="none"' ) && false !== strpos( $html, 'sz-work__pv' ), 'with a "Preview" label' );
szw_ok( false !== strpos( $html, 'is-light' ), 'light image skin' );
szw_ok( false !== strpos( $html, '<img class="sz-work__img" src="https://example.com/a.jpg"' ), 'an image replaces the gradient' );
szw_ok( 2 === substr_count( $html, '<h2 class="sz-work__title"' ), 'title tag is used; a project without a title is skipped' );
szw_ok( false !== strpos( $html, 'All 40' ), 'last card title and total' );

$html = szw_render( [ 'source' => 'manual', 'projects' => [ $p( 'One', '' ) ], 'profile_url' => [ 'url' => '' ], 'show_end' => '' ], 'sz-work' );
szw_ok( false !== strpos( $html, '<div class="sz-work__card' ) && false === strpos( $html, 'sz-work__view' ), 'no link anywhere: the card is not a link and has no View button' );

$html = szw_render( [ 'source' => 'manual', 'projects' => [ $p( 'One', '' ) ], 'label_of' => 'von', 'label_view' => 'Ansehen' ], 'sz-work' );
szw_ok( false !== strpos( $html, '<b>1</b> von 1' ) && false !== strpos( $html, 'Ansehen' ) && false !== strpos( $html, '&quot;of&quot;:&quot;von&quot;' ), 'labels are editable and reach the script' );

szw_ok( '' === szw_render( [ 'source' => 'manual', 'projects' => [] ], 'sz-work' ), 'no projects: nothing on the frontend' );

/* ------------------------------------------------------------------ */

szw_group( 'Selected Work: style controls write CSS' );

$css = szw_css(
	[
		[
			'id'         => 'w1',
			'elType'     => 'widget',
			'widgetType' => 'sz-work',
			'settings'   => [
				'card_width'        => [ 'size' => 300, 'unit' => 'px' ],
				'card_width_mobile' => [ 'size' => 80, 'unit' => 'vw' ],
				'card_ratio'        => '4/5',
				'filter_thumb'      => '#123456',
				'prog_bar'          => '#ABCDEF',
			],
		],
	]
);

szw_ok( false !== strpos( $css, '--szw-cw:300px' ), 'card width' );
szw_ok( false !== strpos( szw_at( $css, 767 ), '--szw-cw:80vw' ), 'card width per breakpoint' );
szw_ok( false !== strpos( $css, '--szw-ratio:4/5' ), 'card shape' );
szw_ok( false !== strpos( $css, '--szw-thumb:#123456' ) && false !== strpos( $css, '--szw-prog-on:#ABCDEF' ), 'colours' );

/* ------------------------------------------------------------------ */

szw_group( 'Selected Work: the projects library' );

use Samanzavarian_Widgets\Projects;

szw_ok( post_type_exists( Projects::TYPE ) && taxonomy_exists( Projects::TAX ), 'Projects and Project types are registered' );

// These checks fill the library and empty it again: never on a site that has projects.
if ( Projects::count() || get_terms( [ 'taxonomy' => Projects::TAX, 'hide_empty' => false, 'fields' => 'ids' ] ) ) {
	echo "  – skipped: the library already has projects or types, and these checks would delete them\n";
} else {
szw_ok( 'manual' === Work_Rail::source( [] ), 'an empty library: the widget keeps its own list (nothing changes for existing pages)' );

szw_ok( 8 === Projects::add_examples(), 'one click adds the design\'s eight examples' );
szw_ok( 0 === Projects::add_examples(), 'never twice: only into an empty library' );
szw_ok( 'library' === Work_Rail::source( [] ) && 'manual' === Work_Rail::source( [ 'source' => 'manual' ] ), 'with projects, "auto" takes the library; "manual" keeps the list' );
szw_ok( [ 'ai', 'film', 'photo', 'post' ] === array_keys( Projects::type_options() ), 'types in their filter order' );

$html = szw_render( [], 'sz-work' );
szw_ok( [ 'All', 'AI', 'Film', 'Photo', 'Post' ] === szw_all( '/data-label="([^"]*)"/', $html ) && [ '8', '2', '1', '3', '2' ] === szw_all( '/<sup aria-hidden="true">(\d+)<\/sup>/', $html ), 'filters and counts come from the types; nothing typed in the widget' );
szw_ok( 8 === substr_count( $html, 'data-project=' ) && false !== strpos( $html, 'AI product key visuals' ), 'the cards are the library\'s projects' );
szw_ok( 1 === preg_match_all( '/sz-work__card[^"]*is-wide/', $html ) && preg_match( '/is-wide[^>]*>.*?AI product key visuals/s', $html ), 'the featured project leads "All"' );

// a project in two types shows under both, and the counts follow
$film = get_posts( [ 'post_type' => Projects::TYPE, 'title' => 'Scripted brand films', 'fields' => 'ids' ] )[0];
wp_set_object_terms( $film, [ 'Film', 'AI' ], Projects::TAX );
Projects::store( $film, [ 'category' => 'Brand film', 'icon' => 'video', 'placeholder' => 1, 'link' => 'https://example.com/film', 'new_tab' => 0, 'video' => 'https://example.com/f.mp4' ] );
$html = szw_render( [], 'sz-work' );
szw_ok( [ '8', '3', '1', '3', '2' ] === szw_all( '/<sup aria-hidden="true">(\d+)<\/sup>/', $html ), 'a project in two types counts in both' );
szw_ok( false !== strpos( $html, 'data-g="ai film"' ) || false !== strpos( $html, 'data-g="film ai"' ), 'and carries both, so both filters show it' );
szw_ok( false !== strpos( $html, 'href="https://example.com/film"' ) && false === strpos( $html, 'href="https://example.com/film" target' ) && false !== strpos( $html, 'data-src="https://example.com/f.mp4"' ), 'its card: own link (same tab), preview video' );

$html = szw_render( [ 'lib_types' => [ 'photo' ], 'lib_limit' => 2 ], 'sz-work' );
szw_ok( 2 === substr_count( $html, 'data-project=' ) && [ 'All', 'Photo' ] === szw_all( '/data-label="([^"]*)"/', $html ), 'only chosen types, at most N' );
$retouch = get_posts( [ 'post_type' => Projects::TYPE, 'title' => 'High-end retouching', 'fields' => 'ids' ] )[0];
update_post_meta( $retouch, Projects::FEATURED, '1' );
$titles = szw_all( '/class="sz-work__title">([^<]*)</', szw_render( [ 'lib_order' => 'featured' ], 'sz-work' ) );
szw_ok( [ 'AI product key visuals', 'High-end retouching', 'Scripted brand films' ] === array_slice( $titles, 0, 3 ), 'featured first, each group in its Order' );

$cached = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'szw cache' ] );
update_post_meta( $cached, '_elementor_element_cache', [ 'value' => 'old' ] );
wp_update_post( [ 'ID' => $film, 'post_title' => 'Scripted brand films' ] );
szw_ok( '' === get_post_meta( $cached, '_elementor_element_cache', true ), 'saving a project drops Elementor\'s cached widget HTML, so it shows at once' );
wp_delete_post( $cached, true );

// leave the library empty again for other tests
foreach ( get_posts( [ 'post_type' => Projects::TYPE, 'numberposts' => -1, 'post_status' => 'any', 'fields' => 'ids' ] ) as $id ) {
	wp_delete_post( $id, true );
}
foreach ( get_terms( [ 'taxonomy' => Projects::TAX, 'hide_empty' => false, 'fields' => 'ids' ] ) as $t ) {
	wp_delete_term( $t, Projects::TAX );
}
wp_cache_flush(); // post counts are cached for the request
szw_ok( 0 === Projects::count(), 'cleaned up' );
}

/* ------------------------------------------------------------------ */

szw_group( 'Selected Work: content column and how far the cards run' );

szw_ok( false !== strpos( szw_render( [], 'sz-work' ), 'run-window is-bleed' ), 'design: out to the window' );
szw_ok( false !== strpos( szw_render( [ 'bleed' => '' ], 'sz-work' ), 'run-widget' ), 'the old switch, off, still means the widget\'s edges' );
$html = szw_render( [ 'run_out' => 'column' ], 'sz-work' );
szw_ok( false !== strpos( $html, 'run-column' ) && false === strpos( $html, '<script>' ), 'within the column: no window probe' );
$css = szw_css( [ [ 'id' => 'w9', 'elType' => 'widget', 'widgetType' => 'sz-work', 'settings' => [ 'col_width' => [ 'size' => 1180, 'unit' => 'px' ], 'col_align' => 'left', 'col_width_mobile' => [ 'size' => 100, 'unit' => '%' ] ] ] ] );
szw_ok( false !== strpos( $css, '--szw-col:1180px' ) && false !== strpos( $css, '--szw-al:0' ) && false !== strpos( szw_at( $css, 767 ), '--szw-col:100%' ), 'column width and position, per breakpoint' );

szw_finish();
