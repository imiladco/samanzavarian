<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Style-tab sections shared by the hero widgets, so every part the design
 * draws can be restyled from Elementor without code: text, boxes and the
 * app-icon tiles. Each method adds one section; empty controls leave the
 * design's own values in place.
 */
final class Style_Controls {

	/** Typography, colour, shadow and spacing of a text part. */
	public static function text( Widget_Base $w, string $id, string $label, string $selector, array $o = [] ): void {
		$w->start_controls_section( $id, [ 'label' => $label, 'tab' => Controls_Manager::TAB_STYLE ] + ( isset( $o['condition'] ) ? [ 'condition' => $o['condition'] ] : [] ) );
		$w->add_group_control( Group_Control_Typography::get_type(), [ 'name' => $id . '_type', 'selector' => $selector ] );
		$w->add_control( $id . '_color', [ 'label' => esc_html__( 'Colour', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $selector => 'color: {{VALUE}};' ] ] );
		if ( ! empty( $o['hover'] ) ) {
			$w->add_control( $id . '_color_h', [ 'label' => esc_html__( 'Colour on hover', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $o['hover'] => 'color: {{VALUE}};' ] ] );
		}
		$w->add_group_control( Group_Control_Text_Shadow::get_type(), [ 'name' => $id . '_shadow', 'selector' => $selector ] );
		if ( empty( $o['no_margin'] ) ) {
			$w->add_responsive_control( $id . '_margin', [ 'label' => esc_html__( 'Margin', 'samanzavarian-widgets' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', 'em', '%' ], 'selectors' => [ $selector => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
		}
		$w->end_controls_section();
	}

	/** Background, border, radius, shadow and padding of a box (with a hover state if given). */
	public static function box( Widget_Base $w, string $id, string $label, string $selector, array $o = [] ): void {
		$w->start_controls_section( $id, [ 'label' => $label, 'tab' => Controls_Manager::TAB_STYLE ] + ( isset( $o['condition'] ) ? [ 'condition' => $o['condition'] ] : [] ) );
		if ( ! empty( $o['hover'] ) ) {
			$w->start_controls_tabs( $id . '_tabs' );
			$w->start_controls_tab( $id . '_n', [ 'label' => esc_html__( 'Normal', 'samanzavarian-widgets' ) ] );
		}
		$w->add_group_control( Group_Control_Background::get_type(), [ 'name' => $id . '_bg', 'types' => [ 'classic', 'gradient' ], 'selector' => $selector ] );
		$w->add_group_control( Group_Control_Border::get_type(), [ 'name' => $id . '_border', 'selector' => $selector ] );
		$w->add_group_control( Group_Control_Box_Shadow::get_type(), [ 'name' => $id . '_shadow', 'selector' => $selector ] );
		if ( ! empty( $o['hover'] ) ) {
			$w->end_controls_tab();
			$w->start_controls_tab( $id . '_h', [ 'label' => $o['hover_label'] ?? esc_html__( 'Hover', 'samanzavarian-widgets' ) ] );
			$w->add_group_control( Group_Control_Background::get_type(), [ 'name' => $id . '_bg_h', 'types' => [ 'classic', 'gradient' ], 'selector' => $o['hover'] ] );
			$w->add_control( $id . '_bc_h', [ 'label' => esc_html__( 'Border colour', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $o['hover'] => 'border-color: {{VALUE}};' ] ] );
			$w->add_group_control( Group_Control_Box_Shadow::get_type(), [ 'name' => $id . '_shadow_h', 'selector' => $o['hover'] ] );
			$w->end_controls_tab();
			$w->end_controls_tabs();
		}
		$w->add_responsive_control( $id . '_radius', [ 'label' => esc_html__( 'Corner radius', 'samanzavarian-widgets' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', '%', 'em' ], 'separator' => 'before', 'selectors' => [ $selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
		$w->add_responsive_control( $id . '_pad', [ 'label' => esc_html__( 'Padding', 'samanzavarian-widgets' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', 'em', '%' ], 'selectors' => [ $selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
		foreach ( (array) ( $o['extra'] ?? [] ) as $key => $c ) {
			$c['responsive'] ?? false ? $w->add_responsive_control( $id . '_' . $key, $c ) : $w->add_control( $id . '_' . $key, $c );
		}
		$w->end_controls_section();
	}

	/** An app-icon tile: size, radius, icon size, background, icon colour, shadow. */
	public static function tile( Widget_Base $w, string $id, string $label, string $selector, array $o = [] ): void {
		$w->start_controls_section( $id, [ 'label' => $label, 'tab' => Controls_Manager::TAB_STYLE ] + ( isset( $o['condition'] ) ? [ 'condition' => $o['condition'] ] : [] ) );
		$w->add_responsive_control( $id . '_size', [ 'label' => esc_html__( 'Tile size', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'size_units' => [ 'px', 'em' ], 'range' => [ 'px' => [ 'min' => 16, 'max' => 160 ], 'em' => [ 'min' => .2, 'max' => 2, 'step' => .01 ] ], 'selectors' => [ $selector => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ] ] );
		$w->add_responsive_control( $id . '_icon', [ 'label' => esc_html__( 'Icon size', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'size_units' => [ 'px', '%' ], 'range' => [ 'px' => [ 'min' => 8, 'max' => 90 ], '%' => [ 'min' => 20, 'max' => 90 ] ], 'selectors' => [ $selector . ' svg, ' . $selector . ' i' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; font-size: {{SIZE}}{{UNIT}};' ] ] );
		$w->add_responsive_control( $id . '_radius', [ 'label' => esc_html__( 'Corner radius', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'size_units' => [ 'px', '%', 'em' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 80 ], '%' => [ 'min' => 0, 'max' => 50 ] ], 'selectors' => [ $selector => 'border-radius: {{SIZE}}{{UNIT}};' ] ] );
		$w->add_control( $id . '_bg', [ 'label' => esc_html__( 'Background', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $selector => '--sz-app-bg:none; background-color: {{VALUE}};' ] ] );
		$w->add_control( $id . '_fg', [ 'label' => esc_html__( 'Icon colour', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $selector => 'color: {{VALUE}};', $selector . ' svg' => 'stroke: {{VALUE}};' ] ] );
		$w->add_group_control( Group_Control_Box_Shadow::get_type(), [ 'name' => $id . '_shadow', 'selector' => $selector ] );
		$w->end_controls_section();
	}

	/** One switch per motion, all on by default. */
	public static function motion( Widget_Base $w, array $switches ): void {
		$w->start_controls_section( 'section_motion', [ 'label' => esc_html__( 'Motion', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		foreach ( $switches as $key => $c ) {
			$w->add_control( $key, $c + [ 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ] );
		}
		$w->add_control( 'motion_note', [ 'type' => Controls_Manager::RAW_HTML, 'raw' => esc_html__( 'Visitors who ask their device for less motion always get none.', 'samanzavarian-widgets' ), 'content_classes' => 'elementor-descriptor' ] );
		$w->end_controls_section();
	}
}
