<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * "Scroll-driven": a section that holds still under the header while the
 * page's own scroll moves what is inside it (tabs, a rail, a timeline), and
 * runs it back when scrolling up. Scroll-linked, never scroll-jacked: the
 * browser's scroll is untouched, js/pin.js only reads it.
 *
 * The markup is a tall wrapper (.sz-pin, its ::after is the scroll length)
 * around a sticky stage. Whether it pins is decided in CSS (sz-base.css), by
 * screen width, by reduced motion and outside the editor, so the page has
 * its final height from the first paint: no shift, anchors land right.
 */
final class Pin_Controls {

	public const DEVICES = [ 'desktop' => 'd', 'tablet' => 't', 'mobile' => 'm' ];

	/**
	 * @param string[] $on      devices on by default
	 * @param int|null $step    default scroll per step, in % of the screen height; null: no step control
	 */
	public static function controls( Widget_Base $w, array $on, ?int $step, string $note ): void {
		$w->start_controls_section( 'section_pin', [ 'label' => esc_html__( 'Scroll-driven', 'samanzavarian-widgets' ) ] );
		$w->add_control(
			'pin_on',
			[
				'label'       => esc_html__( 'On', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'default'     => $on,
				'options'     => [
					'desktop' => esc_html__( 'Desktop', 'samanzavarian-widgets' ),
					'tablet'  => esc_html__( 'Tablet', 'samanzavarian-widgets' ),
					'mobile'  => esc_html__( 'Mobile', 'samanzavarian-widgets' ),
				],
			]
		);
		if ( null !== $step ) {
			$w->add_control( 'pin_step', [ 'label' => esc_html__( 'Scroll for each step', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'size_units' => [ 'px' ], 'range' => [ 'px' => [ 'min' => 25, 'max' => 150 ] ], 'default' => [ 'unit' => 'px', 'size' => $step ], 'description' => esc_html__( 'In hundredths of the screen\'s height.', 'samanzavarian-widgets' ) ] );
		}
		$w->add_control( 'pin_top', [ 'label' => esc_html__( 'Holds this far from the top', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'size_units' => [ 'px' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 200 ] ], 'default' => [ 'unit' => 'px', 'size' => 80 ], 'description' => esc_html__( 'Room for the header bar.', 'samanzavarian-widgets' ) ] );
		$w->add_control( 'pin_note', [ 'type' => Controls_Manager::RAW_HTML, 'raw' => esc_html( $note ) . ' ' . esc_html__( 'Never in the editor, with reduced motion, or when the section is taller than the screen; the parent containers must not clip (Overflow: Default).', 'samanzavarian-widgets' ), 'content_classes' => 'elementor-descriptor' ] );
		$w->end_controls_section();
	}

	/** Device classes ("on-d on-m"), or '' when it is off everywhere. */
	public static function classes( array $s ): string {
		$on = array_intersect_key( self::DEVICES, array_flip( (array) ( $s['pin_on'] ?? [] ) ) );
		return $on ? 'on-' . implode( ' on-', $on ) : '';
	}

	/**
	 * The wrapper's opening tags, or '' when it is off everywhere.
	 *
	 * @param string $len  CSS length of the scroll path ('' when js/pin.js sets it)
	 * @param string $kind what drives it, for js ("tabs", "rail", "timeline")
	 */
	public static function open( array $s, string $len, string $kind ): string {
		$cls = self::classes( $s );
		if ( '' === $cls ) {
			return '';
		}
		$top   = max( 0, (int) ( $s['pin_top']['size'] ?? 80 ) );
		$style = '--szp-top:' . $top . 'px' . ( '' !== $len ? ';--szp-len:' . $len : '' );
		return '<div class="sz-pin ' . esc_attr( $cls ) . '" data-sz-pin="' . esc_attr( $kind ) . '" style="' . esc_attr( $style ) . '"><div class="sz-pin__stage">';
	}

	public static function close( array $s ): string {
		return '' === self::classes( $s ) ? '' : '</div></div>';
	}

	/** One step's scroll, as a CSS length. */
	public static function step( array $s, int $fallback ): string {
		return max( 25, min( 150, (int) ( $s['pin_step']['size'] ?? $fallback ) ) ) . 'svh';
	}
}
