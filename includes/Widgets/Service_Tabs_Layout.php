<?php
namespace Samanzavarian_Widgets\Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * The two layouts of the Service Tabs widget as families of custom
 * properties: the tab list beside the panels (the design's desktop), or as a
 * scrolling row above them (the design's narrow screens).
 *
 * A layout changes a dozen properties on half a dozen elements, and some are
 * not numbers (flex-direction, display, white-space), so it cannot be one
 * knob. Instead every element reads its share through a --szt-* property, and
 * choosing a layout sets the whole family on the widget root. That keeps the
 * choice a single responsive control — so it follows Elementor's breakpoints
 * — while the Style tab can still override any single value at any
 * breakpoint, because those controls are registered later and print later.
 *
 * Single source: the stylesheet's built-in default is written from SIDE by
 * tools/build-tabs-css.php, and the Layout control writes the same strings.
 */
final class Service_Tabs_Layout {

	/** @var array<string,array<string,string>> */
	public const LAYOUTS = [
		'side' => [
			'cols'        => 'minmax(300px,.82fr) 1.5fr',
			'dir'         => 'column',
			'scroll'      => 'visible',
			'list-pad'    => '8px',
			'list-radius' => '30px',
			'tab-flex'    => '1 1 0%',
			'tab-w'       => '100%',
			'tab-pad'     => '16px 14px',
			'tab-gap'     => '14px',
			'tab-radius'  => '22px',
			'icon-box'    => '46px',
			'icon-radius' => '13px',
			'icon-size'   => '22px',
			'title-size'  => '17px',
			'title-wrap'  => 'normal',
			'sub'         => 'block',
			'chev'        => 'block',
			'note'        => 'flex',
			'icon-on'     => 'scale(1.06) rotate(-4deg)',
			'stage-min'   => '560px',
		],
		'top'  => [
			'cols'        => 'minmax(0,1fr)',
			'dir'         => 'row',
			'scroll'      => 'auto',
			'list-pad'    => '6px',
			'list-radius' => '22px',
			'tab-flex'    => '0 0 auto',
			'tab-w'       => 'auto',
			'tab-pad'     => '8px 14px 8px 8px',
			'tab-gap'     => '10px',
			'tab-radius'  => '16px',
			'icon-box'    => '34px',
			'icon-radius' => '10px',
			'icon-size'   => '17px',
			'title-size'  => '15px',
			'title-wrap'  => 'nowrap',
			'sub'         => 'none',
			'chev'        => 'none',
			'note'        => 'none',
			'icon-on'     => 'none',
			'stage-min'   => '0px',
		],
	];

	/** The declaration list one layout writes on the widget root. */
	public static function vars( string $layout ): string {
		$out = '';

		foreach ( self::LAYOUTS[ $layout ] ?? self::LAYOUTS['side'] as $key => $value ) {
			$out .= '--szt-' . $key . ':' . $value . ';';
		}

		return $out;
	}
}
