<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Plugin as Elementor;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Samanzavarian_Widgets\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Hero Floats — the work stills floating around the hero, tilted, with a
 * frosted caption.
 *
 * Put it anywhere in the hero's container: by default it lies behind the
 * other widgets, filling the container (a layer), so the title stays on top
 * and nothing is pushed around. The cards fan in one after another, then
 * drift against the pointer at their own depth (parallax). Without an image
 * a card shows one of the design's colour meshes.
 *
 * On a phone the layer becomes a small fan of three above the title (the
 * first three cards; the middle one in front, the only caption shown).
 */
class Hero_Floats extends Widget_Base {

	private const CARDS = [
		[ 'AI key visual', 'm1', 'left', 6, 15, -8, 18, 210 ],
		[ 'Product', 'm3', 'left', 11, 55, 5, 10, 176 ],
		[ 'Brand film', 'm2', 'right', 6, 13, 7, 16, 210 ],
		[ 'Retouch', 'm5', 'right', 8, 57, -5, 12, 176 ],
	];

	public function get_name(): string {
		return 'sz-floats';
	}

	public function get_title(): string {
		return esc_html__( 'Hero Floats', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-gallery-masonry';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'hero', 'floating', 'cards', 'images', 'parallax' ];
	}

	public function get_style_depends(): array {
		return [ 'sz-hero' ];
	}

	public function get_script_depends(): array {
		return [ 'sz-hero' ];
	}

	protected function is_dynamic_content(): bool {
		return false;
	}

	public function has_widget_inner_wrapper(): bool {
		return ! Elementor::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	protected function register_controls(): void {
		$this->start_controls_section( 'section_cards', [ 'label' => esc_html__( 'Cards', 'samanzavarian-widgets' ) ] );
		$r = new Repeater();
		$r->add_control( 'image', [ 'label' => esc_html__( 'Image', 'samanzavarian-widgets' ), 'type' => Controls_Manager::MEDIA, 'default' => [ 'url' => '' ] ] );
		$r->add_control( 'caption', [ 'label' => esc_html__( 'Caption', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Work' ] );
		$r->add_control( 'mesh', [ 'label' => esc_html__( 'Colour without an image', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SELECT, 'default' => 'm1', 'options' => [ 'm1' => esc_html__( 'Peach', 'samanzavarian-widgets' ), 'm2' => esc_html__( 'Navy and orange', 'samanzavarian-widgets' ), 'm3' => esc_html__( 'Pearl', 'samanzavarian-widgets' ), 'm4' => esc_html__( 'Night', 'samanzavarian-widgets' ), 'm5' => esc_html__( 'Apricot', 'samanzavarian-widgets' ), 'm6' => esc_html__( 'Slate', 'samanzavarian-widgets' ) ] ] );
		$r->add_control( 'side', [ 'label' => esc_html__( 'Side', 'samanzavarian-widgets' ), 'type' => Controls_Manager::CHOOSE, 'default' => 'left', 'options' => [ 'left' => [ 'title' => esc_html__( 'Left', 'samanzavarian-widgets' ), 'icon' => 'eicon-h-align-left' ], 'right' => [ 'title' => esc_html__( 'Right', 'samanzavarian-widgets' ), 'icon' => 'eicon-h-align-right' ] ], 'separator' => 'before' ] );
		$r->add_control( 'x', [ 'label' => esc_html__( 'From the side (%)', 'samanzavarian-widgets' ), 'type' => Controls_Manager::NUMBER, 'default' => 6, 'min' => -10, 'max' => 60 ] );
		$r->add_control( 'y', [ 'label' => esc_html__( 'From the top (%)', 'samanzavarian-widgets' ), 'type' => Controls_Manager::NUMBER, 'default' => 15, 'min' => -10, 'max' => 90 ] );
		$r->add_control( 'tilt', [ 'label' => esc_html__( 'Tilt (°)', 'samanzavarian-widgets' ), 'type' => Controls_Manager::NUMBER, 'default' => -8, 'min' => -25, 'max' => 25 ] );
		$r->add_control( 'width', [ 'label' => esc_html__( 'Width (px, at its largest)', 'samanzavarian-widgets' ), 'type' => Controls_Manager::NUMBER, 'default' => 210, 'min' => 90, 'max' => 360 ] );
		$r->add_control( 'depth', [ 'label' => esc_html__( 'Depth (how far it drifts)', 'samanzavarian-widgets' ), 'type' => Controls_Manager::NUMBER, 'default' => 14, 'min' => 0, 'max' => 40 ] );
		$r->add_control( 'wide_only', [ 'label' => esc_html__( 'Only on wide screens', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'description' => esc_html__( 'Hidden under 1100px, where it would crowd the title.', 'samanzavarian-widgets' ) ] );
		$this->add_control(
			'cards',
			[
				'label'       => esc_html__( 'Cards', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'title_field' => '{{{ caption }}}',
				'default'     => array_map( static fn( $c, $i ) => [ 'caption' => $c[0], 'mesh' => $c[1], 'side' => $c[2], 'x' => $c[3], 'y' => $c[4], 'tilt' => $c[5], 'depth' => $c[6], 'width' => $c[7], 'wide_only' => $i % 2 ? 'yes' : '' ], self::CARDS, array_keys( self::CARDS ) ),
			]
		);
		$this->add_control( 'layer', [ 'label' => esc_html__( 'Behind the other widgets', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'prefix_class' => 'sz-floats-layer-', 'separator' => 'before', 'description' => esc_html__( 'Fills its container, behind everything else in it. Off: a box of its own, as tall as below.', 'samanzavarian-widgets' ) ] );
		$this->add_responsive_control( 'height', [ 'label' => esc_html__( 'Height when not behind', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'size_units' => [ 'px', 'vh' ], 'range' => [ 'px' => [ 'min' => 160, 'max' => 900 ] ], 'default' => [ 'unit' => 'px', 'size' => 520 ], 'selectors' => [ '{{WRAPPER}} .sz-floats' => 'height: {{SIZE}}{{UNIT}};' ], 'condition' => [ 'layer!' => 'yes' ] ] );
		$this->end_controls_section();
	}

	protected function render(): void {
		$s     = $this->get_settings_for_display();
		$cards = array_values( array_filter( (array) ( $s['cards'] ?? [] ) ) );
		if ( ! $cards ) {
			return;
		}
		echo '<div class="sz-floats sz-w" data-sz-floats aria-hidden="true">';
		foreach ( $cards as $i => $c ) {
			$side  = 'right' === ( $c['side'] ?? 'left' ) ? 'right' : 'left';
			$style = sprintf( '%s:%s%%;top:%s%%;--r:%sdeg;--w:%dpx', $side, (float) ( $c['x'] ?? 6 ), (float) ( $c['y'] ?? 15 ), (float) ( $c['tilt'] ?? 0 ), max( 90, (int) ( $c['width'] ?? 210 ) ) );
			$mesh  = preg_match( '/^m[1-6]$/', (string) ( $c['mesh'] ?? '' ) ) ? $c['mesh'] : 'm1';
			$cls   = 'sz-float sz-mesh-' . $mesh . ' is-n' . ( $i + 1 ) . ( 'yes' === ( $c['wide_only'] ?? '' ) ? ' is-wide' : '' );
			printf( '<div class="%s" style="%s" data-depth="%d">', esc_attr( $cls ), esc_attr( $style ), (int) ( $c['depth'] ?? 14 ) );
			if ( ! empty( $c['image']['id'] ) ) {
				echo wp_get_attachment_image( (int) $c['image']['id'], 'medium_large', false, [ 'alt' => '', 'loading' => 0 === $i ? 'eager' : 'lazy', 'decoding' => 'async' ] );
			} elseif ( ! empty( $c['image']['url'] ) ) {
				echo '<img src="' . esc_url( $c['image']['url'] ) . '" alt="" decoding="async">';
			}
			if ( '' !== trim( (string) ( $c['caption'] ?? '' ) ) ) {
				echo '<span class="sz-float__cap"><i></i>' . esc_html( $c['caption'] ) . '</span>';
			}
			echo '</div>';
		}
		echo '</div>';
	}
}
