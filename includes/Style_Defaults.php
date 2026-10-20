<?php
namespace Samanzavarian_Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * Fills the Style tab with the design's own values, so a font size or a
 * colour opens showing what is on screen instead of an empty field.
 *
 * The values come from includes/style-defaults.php, generated from the
 * plugin's stylesheets (tools/style-defaults). Sizes are plain pixels, one
 * per device (the design's value at desktop, tablet and phone width), and a property the design changes with a state or at
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
		add_action( 'elementor/element/before_section_end', [ self::class, 'pixels_first' ], 5, 2 );

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
							// tablet and phone values: on the desktop control for the editor's device switcher, on the copies for the page's CSS
							$args = [ 'default' => $c['d'] ];
							foreach ( [ 't' => 'tablet', 'm' => 'mobile' ] as $k => $device ) {
								if ( isset( $c[ $k ] ) ) {
									$args[ $device . '_default' ] = $c[ $k ];
									$element->update_control( $name . '_' . $device, [ 'default' => $c[ $k ] ] );
								}
							}
							$element->update_control( $name, $args );
						}
					}
				);
			}
		}
	}

	/**
	 * Every size control of these widgets speaks pixels: px first in the
	 * unit menu, and a control with no value yet starts on px rather than
	 * em, % or vw. A control that already holds a value (a default, or what
	 * was saved) keeps its own unit.
	 *
	 * @param \Elementor\Controls_Stack $element
	 */
	public static function pixels_first( $element, string $section_id ): void {
		if ( 0 !== strpos( (string) $element->get_name(), 'sz-' ) ) {
			return;
		}
		foreach ( $element->get_controls() as $name => $c ) {
			if ( ( $c['section'] ?? '' ) !== $section_id || empty( $c['size_units'] ) || ! in_array( $c['type'] ?? '', [ 'slider', 'dimensions', 'gaps' ], true ) ) {
				continue;
			}
			// only where px is a size at all (not a gradient's stops or angle)
			if ( ! in_array( 'px', (array) $c['size_units'], true ) || preg_match( '/_(stop|angle)(_tablet|_mobile)?$/', $name ) ) {
				continue;
			}
			$units = array_values( array_unique( array_merge( [ 'px' ], (array) $c['size_units'] ) ) );
			$args  = [ 'size_units' => $units ];
			$def   = (array) ( $c['default'] ?? [] );
			$empty = 'slider' === $c['type'] ? '' === (string) ( $def['size'] ?? '' ) : '' === implode( '', array_map( 'strval', array_intersect_key( $def, array_flip( [ 'top', 'right', 'bottom', 'left', 'row', 'column' ] ) ) ) );
			if ( $empty ) {
				$args['default'] = [ 'unit' => 'px' ] + $def;
				$args['default']['unit'] = 'px';
				$ph = (array) ( $c['placeholder'] ?? [] );
				if ( isset( $ph['unit'] ) && 'px' !== $ph['unit'] ) {
					$args['placeholder'] = ''; // a placeholder in another unit would read wrong beside px
				}
			}
			// the tablet and phone copies are built from these in the editor
			foreach ( [ 'tablet_default', 'mobile_default' ] as $k ) {
				$dd = (array) ( $c[ $k ] ?? [] );
				if ( isset( $c[ $k ] ) && '' === (string) ( $dd['size'] ?? '' ) && 'px' !== ( $dd['unit'] ?? 'px' ) ) {
					$dd['unit']  = 'px';
					$args[ $k ] = $dd;
				}
			}
			$element->update_control( $name, $args );
		}
	}
}
