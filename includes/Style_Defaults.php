<?php
namespace Samanzavarian_Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * Fills the Style tab with the design's own values, so a font size or a
 * colour opens showing what is on screen instead of an empty field.
 *
 * The values come from includes/style-defaults.php, generated from the
 * plugin's stylesheets (tools/build-style-defaults). A fluid size keeps its
 * formula (clamp() in Elementor's custom unit), a phone value becomes the
 * mobile default, and a property the design changes with a state or at
 * another breakpoint is not there at all: as a default it would be written
 * into the page's CSS and win over the design's own hover, picked or
 * narrow-screen rule. Such a size still shows its value, as a placeholder,
 * which writes nothing.
 *
 * Applied as each section of each widget is registered (before its end), so
 * nothing in the widgets' own code changes.
 */
final class Style_Defaults {

	public static function boot(): void {
		// off: the tool that measures the design must see it without its own defaults
		if ( ! apply_filters( 'szw_style_defaults', true ) ) {
			return;
		}
		$map = require __DIR__ . '/style-defaults.php';
		foreach ( (array) $map as $widget => $controls ) {
			$by_section = [];
			foreach ( $controls as $name => $c ) {
				$by_section[ $c['s'] ][ $name ] = $c;
			}
			foreach ( $by_section as $section => $list ) {
				add_action(
					"elementor/element/{$widget}/{$section}/before_section_end",
					static function ( $element ) use ( $list ): void {
						foreach ( $list as $name => $c ) {
							if ( ! isset( $c['d'] ) ) {
								// left to the design (it changes with a state, or is inherited): shown, not written
								$element->update_control( $name, [ 'placeholder' => $c['p'] ] );
								continue;
							}
							// the phone value: on the desktop control for the editor's device switcher, on the _mobile copy for the page's CSS
							$element->update_control( $name, isset( $c['m'] ) ? [ 'default' => $c['d'], 'mobile_default' => $c['m'] ] : [ 'default' => $c['d'] ] );
							if ( isset( $c['m'] ) ) {
								$element->update_control( $name . '_mobile', [ 'default' => $c['m'] ] );
							}
						}
					}
				);
			}
		}
	}
}
