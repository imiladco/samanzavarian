<?php
namespace Samanzavarian_Widgets\Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * Small control-building rules every widget in this plugin follows, kept in
 * one place so the next widget gets them right by default.
 */
final class Control_Helpers {

	/**
	 * The design's value, shown greyed-out in an empty control.
	 *
	 * Style controls are deliberately left without defaults — the design lives
	 * in the stylesheet, and an untouched widget writes no per-page CSS. But a
	 * slider sitting at zero reads as "zero"; the placeholder tells the truth
	 * about what is on screen without writing anything.
	 *
	 * @param int|float $size
	 * @return array{size:int|float,unit:string}
	 */
	public static function ph( $size, string $unit = 'px' ): array {
		return [
			'size' => $size,
			'unit' => $unit,
		];
	}

	/** @return array<string,int|string> placeholder for a DIMENSIONS control */
	public static function ph_box( int $top, int $right, int $bottom, int $left, string $unit = 'px' ): array {
		return [
			'top'    => $top,
			'right'  => $right,
			'bottom' => $bottom,
			'left'   => $left,
			'unit'   => $unit,
		];
	}

	/**
	 * `fields_options` for a Group_Control_Background whose target has a
	 * design gradient: picking any background type switches the gradient off.
	 *
	 * The gradient must sit behind a custom property in the stylesheet:
	 *
	 *     background-image: var(--x, radial-gradient(…));
	 *
	 * and this sets `--x: none`. Clearing `background-image` directly does not
	 * work: Elementor merges a selector's declarations by property name, so a
	 * `background-image: none` from the type field collides with the image
	 * field's own `background-image: url(…)` and — verified on Elementor 4.3 —
	 * wins, silently discarding the user's image. A custom property has its own
	 * name and cannot collide with anything.
	 *
	 * @param string $property the custom property guarding the gradient, e.g. '--szp-card-bg'
	 * @return array<string,array<string,mixed>>
	 */
	public static function clears_design_gradient( string $property ): array {
		return [
			'background' => [
				'selectors' => [ '{{SELECTOR}}' => $property . ': none;' ],
			],
		];
	}
}
