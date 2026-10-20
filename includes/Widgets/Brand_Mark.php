<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Plugin as Elementor;
use Elementor\Widget_Base;
use Samanzavarian_Widgets\Icon_Library;
use Samanzavarian_Widgets\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Brand Mark — the header's name: an app-icon tile, then the name in the
 * display face, the second word orange. It links home (or to the top of
 * the page, smoothly); the tile turns a little under the pointer.
 */
class Brand_Mark extends Widget_Base {

	public function get_name(): string {
		return 'sz-brand';
	}

	public function get_title(): string {
		return esc_html__( 'Brand Mark', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-site-logo';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'logo', 'brand', 'header', 'name', 'mark' ];
	}

	public function get_style_depends(): array {
		return [ 'sz-header' ];
	}

	public function get_script_depends(): array {
		return [ 'sz-header' ];
	}

	protected function is_dynamic_content(): bool {
		return false;
	}

	public function has_widget_inner_wrapper(): bool {
		return ! Elementor::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	protected function register_controls(): void {
		$this->start_controls_section( 'section_mark', [ 'label' => esc_html__( 'Mark', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'first', [ 'label' => esc_html__( 'First word', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Saman' ] );
		$this->add_control( 'second', [ 'label' => esc_html__( 'Second word (orange)', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Zavarian' ] );
		$this->add_control( 'icon', [ 'label' => esc_html__( 'Tile', 'samanzavarian-widgets' ), 'type' => Controls_Manager::ICONS, 'default' => Icon_Library::value( 'lens' ), 'skin' => 'inline', 'label_block' => false ] );
		$this->add_control( 'link', [ 'label' => esc_html__( 'Link', 'samanzavarian-widgets' ), 'type' => Controls_Manager::URL, 'default' => [ 'url' => '#top' ], 'dynamic' => [ 'active' => true ], 'description' => esc_html__( '#top scrolls to the top of this page; or your home page address.', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'label', [ 'label' => esc_html__( 'Name for screen readers', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Saman Zavarian, back to top' ] );
		$this->end_controls_section();

		Style_Controls::text( $this, 'st_name', esc_html__( 'Name', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-brand', [ 'no_margin' => true ] );
		Style_Controls::text( $this, 'st_second', esc_html__( 'Second word', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-brand b', [ 'no_margin' => true ] );
		Style_Controls::tile( $this, 'st_tile', esc_html__( 'Tile', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-brand__app' );
		$this->start_controls_section( 'st_layout', [ 'label' => esc_html__( 'Layout', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		$this->add_responsive_control( 'gap', [ 'label' => esc_html__( 'Space after the tile', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'size_units' => [ 'px' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 30 ] ], 'selectors' => [ '{{WRAPPER}} .sz-brand' => 'gap: {{SIZE}}{{UNIT}};' ] ] );
		$this->add_responsive_control( 'pad', [ 'label' => esc_html__( 'Padding', 'samanzavarian-widgets' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px' ], 'selectors' => [ '{{WRAPPER}} .sz-brand' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
		$this->end_controls_section();
	}

	protected function render(): void {
		$s = $this->get_settings_for_display();
		$this->add_render_attribute( 'a', [ 'class' => 'sz-brand sz-w', 'aria-label' => (string) ( $s['label'] ?? '' ) ] );
		if ( ! empty( $s['link']['url'] ) ) {
			$this->add_link_attributes( 'a', $s['link'] );
		}
		$icon = ! empty( $s['icon']['value'] ) ? Icon_Library::html( $s['icon'] ) : '';
		echo '<a ' . $this->get_render_attribute_string( 'a' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor escapes
		if ( '' !== $icon ) {
			echo '<span class="sz-brand__app sz-app" aria-hidden="true">' . $icon . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup
		}
		echo '<span class="sz-brand__t">' . esc_html( (string) ( $s['first'] ?? '' ) ) . ( '' !== trim( (string) ( $s['second'] ?? '' ) ) ? ' <b>' . esc_html( $s['second'] ) . '</b>' : '' ) . '</span></a>';
	}
}
