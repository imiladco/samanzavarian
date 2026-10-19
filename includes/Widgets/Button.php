<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Plugin as Elementor;
use Elementor\Widget_Base;
use Samanzavarian_Widgets\Icon_Library;
use Samanzavarian_Widgets\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Button — one button, the design's pill. Dark (navy) or light (white),
 * with an optional app-icon tile at the start. It lifts a pixel under the
 * pointer, its tile tips, and it gives under the finger. Full width on a
 * phone by default, where a thumb wants a big target.
 */
class Button extends Widget_Base {

	public function get_name(): string {
		return 'sz-btn';
	}

	public function get_title(): string {
		return esc_html__( 'Button', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-button';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'button', 'cta', 'link' ];
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
		$this->start_controls_section( 'section_btn', [ 'label' => esc_html__( 'Button', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'text', [ 'label' => esc_html__( 'Text', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => esc_html__( 'Start a project', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'link', [ 'label' => esc_html__( 'Link', 'samanzavarian-widgets' ), 'type' => Controls_Manager::URL, 'default' => [ 'url' => '#contact' ], 'dynamic' => [ 'active' => true ] ] );
		$this->add_control( 'look', [ 'label' => esc_html__( 'Look', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SELECT, 'default' => 'dark', 'options' => [ 'dark' => esc_html__( 'Dark', 'samanzavarian-widgets' ), 'light' => esc_html__( 'Light', 'samanzavarian-widgets' ), 'orange' => esc_html__( 'Orange', 'samanzavarian-widgets' ) ] ] );
		$this->add_control( 'icon', [ 'label' => esc_html__( 'Icon tile', 'samanzavarian-widgets' ), 'type' => Controls_Manager::ICONS, 'skin' => 'inline', 'label_block' => false, 'default' => Icon_Library::value( 'chat' ) ] );
		$this->add_control( 'size', [ 'label' => esc_html__( 'Size', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SELECT, 'default' => 'md', 'options' => [ 'sm' => esc_html__( 'Small', 'samanzavarian-widgets' ), 'md' => esc_html__( 'Medium', 'samanzavarian-widgets' ), 'lg' => esc_html__( 'Large', 'samanzavarian-widgets' ) ] ] );
		$this->add_responsive_control( 'align', [ 'label' => esc_html__( 'Alignment', 'samanzavarian-widgets' ), 'type' => Controls_Manager::CHOOSE, 'options' => [ 'flex-start' => [ 'title' => esc_html__( 'Left', 'samanzavarian-widgets' ), 'icon' => 'eicon-text-align-left' ], 'center' => [ 'title' => esc_html__( 'Center', 'samanzavarian-widgets' ), 'icon' => 'eicon-text-align-center' ], 'flex-end' => [ 'title' => esc_html__( 'Right', 'samanzavarian-widgets' ), 'icon' => 'eicon-text-align-right' ], 'stretch' => [ 'title' => esc_html__( 'Full width', 'samanzavarian-widgets' ), 'icon' => 'eicon-h-align-stretch' ] ], 'default' => 'center', 'mobile_default' => 'stretch', 'selectors' => [ '{{WRAPPER}} .sz-btn-wrap' => 'justify-content: {{VALUE}};' ] ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style', [ 'label' => esc_html__( 'Button', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'type', 'selector' => '{{WRAPPER}} .sz-btn' ] );
		$this->add_control( 'bg', [ 'label' => esc_html__( 'Background', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .sz-btn' => 'background-color: {{VALUE}};' ] ] );
		$this->add_control( 'fg', [ 'label' => esc_html__( 'Text', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .sz-btn' => 'color: {{VALUE}};' ] ] );
		$this->end_controls_section();
	}

	protected function render(): void {
		$s = $this->get_settings_for_display();
		if ( '' === trim( (string) ( $s['text'] ?? '' ) ) ) {
			return;
		}
		$look = in_array( $s['look'] ?? 'dark', [ 'dark', 'light', 'orange' ], true ) ? $s['look'] : 'dark';
		$this->add_render_attribute( 'a', 'class', [ 'sz-btn', 'sz-w', 'is-' . $look, 'is-' . ( $s['size'] ?? 'md' ) ] );
		if ( ! empty( $s['link']['url'] ) ) {
			$this->add_link_attributes( 'a', $s['link'] );
		}
		$icon = ! empty( $s['icon']['value'] ) ? Icon_Library::html( $s['icon'] ) : '';
		$tone = 'dark' === $look ? 'sz-app--orange' : ( 'orange' === $look ? 'sz-app--white' : '' );
		echo '<div class="sz-btn-wrap"><a ' . $this->get_render_attribute_string( 'a' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor escapes
		if ( '' !== $icon ) {
			echo '<span class="sz-btn__app sz-app ' . esc_attr( $tone ) . '" aria-hidden="true">' . $icon . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup
		}
		echo '<span>' . esc_html( $s['text'] ) . '</span></a></div>';
	}
}
