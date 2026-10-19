<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Plugin as Elementor;
use Elementor\Widget_Base;
use Samanzavarian_Widgets\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Hero Lead — the line under the title. Words between *stars* are set bold
 * and get the design's hand-drawn orange underline, drawn in once the title
 * has risen. Balanced lines, a readable measure.
 */
class Hero_Lead extends Widget_Base {

	public function get_name(): string {
		return 'sz-lead';
	}

	public function get_title(): string {
		return esc_html__( 'Hero Lead', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-text';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'hero', 'lead', 'description', 'intro', 'subtitle' ];
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
		$this->start_controls_section( 'section_lead', [ 'label' => esc_html__( 'Lead', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'text', [ 'label' => esc_html__( 'Text', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 3, 'default' => '*More than just content.* Visuals with a point of view, built to make ideas matter.', 'description' => esc_html__( 'Put *stars* around the words that get the bold and the hand-drawn underline.', 'samanzavarian-widgets' ) ] );
		$this->add_responsive_control( 'width', [ 'label' => esc_html__( 'Line length', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'size_units' => [ 'ch' ], 'range' => [ 'ch' => [ 'min' => 16, 'max' => 70 ] ], 'selectors' => [ '{{WRAPPER}} .sz-lead' => 'max-width: {{SIZE}}ch;' ] ] );
		$this->add_responsive_control( 'align', [ 'label' => esc_html__( 'Alignment', 'samanzavarian-widgets' ), 'type' => Controls_Manager::CHOOSE, 'options' => [ 'left' => [ 'title' => esc_html__( 'Left', 'samanzavarian-widgets' ), 'icon' => 'eicon-text-align-left' ], 'center' => [ 'title' => esc_html__( 'Center', 'samanzavarian-widgets' ), 'icon' => 'eicon-text-align-center' ], 'right' => [ 'title' => esc_html__( 'Right', 'samanzavarian-widgets' ), 'icon' => 'eicon-text-align-right' ] ], 'default' => 'center', 'selectors_dictionary' => [ 'left' => 'text-align:left;margin-left:0;margin-right:auto', 'center' => 'text-align:center;margin-left:auto;margin-right:auto', 'right' => 'text-align:right;margin-left:auto;margin-right:0' ], 'selectors' => [ '{{WRAPPER}} .sz-lead' => '{{VALUE}}' ] ] );
		$this->end_controls_section();
	}

	/** "*bold*" → bold with the underline. */
	public static function marks( string $text ): string {
		$scribble = '<svg viewBox="0 0 220 14" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path d="M3 9.5c38-5.5 90-7.5 140-4.6 28 1.6 50 3.4 74 1.2"/></svg>';
		return (string) preg_replace( '/\*([^*]+)\*/', '<b class="sz-lead__sw">$1' . $scribble . '</b>', esc_html( $text ) );
	}

	protected function render(): void {
		$s = $this->get_settings_for_display();
		echo '<p class="sz-lead sz-w" data-sz-rise>' . self::marks( (string) ( $s['text'] ?? '' ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in marks()
	}
}
