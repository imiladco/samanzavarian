<?php
/**
 * Shared test helpers. Every test file runs inside a real WordPress +
 * Elementor through `wp eval-file`.
 */

use Elementor\Core\Files\CSS\Post as Post_CSS;
use Elementor\Plugin as Elementor;
use Samanzavarian_Widgets\Icon_Library;

if ( function_exists( 'szw_ok' ) ) {
	return;
}

/*
 * Through $GLOBALS on purpose: `wp eval-file` runs this file inside a
 * function, so a top-level `$failed` would be local and a `global $failed`
 * in the helper would count into a different variable — every failure would
 * still end in "ALL PASSED". (It did, once.)
 */
$GLOBALS['szw_failed'] = $GLOBALS['szw_failed'] ?? 0;

function szw_ok( bool $condition, string $label ): void {
	echo ( $condition ? "  ✓ " : "  ✗ " ) . $label . "\n";
	if ( ! $condition ) {
		$GLOBALS['szw_failed']++;
	}
}

function szw_group( string $title ): void {
	echo "\n" . $title . "\n";
}

/**
 * Render one widget through Elementor, exactly as a page would.
 *
 * @param array<string,mixed>            $settings
 * @param array<int,array<string,mixed>> $children nested elements (for nested widgets)
 */
function szw_render( array $settings = [], string $type = 'sz-process', array $children = [] ): string {
	$element = Elementor::$instance->elements_manager->create_element_instance(
		[
			'id'         => 'szwtest',
			'elType'     => 'widget',
			'widgetType' => $type,
			'settings'   => $settings,
			'elements'   => $children,
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

/**
 * Elementor's generated CSS for one element tree on a fresh draft page.
 * A fresh post per call: within one request Elementor keeps a document's
 * earlier rules around, so reusing a post would read the previous case.
 *
 * @param array<int,array<string,mixed>> $elements
 */
function szw_css( array $elements ): string {
	$id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'szw css case' ] );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );

	$file = Post_CSS::create( $id );
	$file->update();
	$css = (string) $file->get_content();

	wp_delete_post( $id, true );

	return $css;
}

/** Rules inside @media(max-width:$max px){…} in a CSS string, joined. */
function szw_at( string $css, int $max ): string {
	preg_match_all( '/@media\(max-width:' . $max . 'px\)\{(.*?\})\}/s', $css, $m );

	return implode( '', $m[1] );
}

function szw_finish(): void {
	if ( ! empty( $GLOBALS['szw_nested'] ) ) {
		return; // all.php prints the total
	}

	echo "\n" . ( $GLOBALS['szw_failed'] ? 'FAILED: ' . $GLOBALS['szw_failed'] : 'ALL PASSED' ) . "\n";

	if ( $GLOBALS['szw_failed'] ) {
		exit( 1 );
	}
}
