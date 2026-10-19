<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Plugin as Elementor;
use Elementor\Widget_Base;
use Samanzavarian_Widgets\Icon_Library;
use Samanzavarian_Widgets\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Hero Title — the name in the display face: first word navy, an app-icon
 * tile between, second word orange.
 *
 * The words rise into place one after another when the page opens; the
 * tile tips the other way under the pointer. On a phone the tile steps out
 * and the two words stay on one line.
 */
class Hero_Title extends Widget_Base {

	public function get_name(): string {
		return 'sz-htitle';
	}

	public function get_title(): string {
		return esc_html__( 'Hero Title', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-heading';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'hero', 'title', 'name', 'heading' ];
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
		$this->start_controls_section( 'section_title', [ 'label' => esc_html__( 'Title', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'first', [ 'label' => esc_html__( 'First word', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Saman' ] );
		$this->add_control( 'second', [ 'label' => esc_html__( 'Second word (orange)', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Zavarian' ] );
		$this->add_control( 'icon', [ 'label' => esc_html__( 'Tile between', 'samanzavarian-widgets' ), 'type' => Controls_Manager::ICONS, 'default' => Icon_Library::value( 'lens' ), 'skin' => 'inline', 'label_block' => false, 'description' => esc_html__( 'Leave empty for no tile.', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'tag', [ 'label' => esc_html__( 'HTML tag', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SELECT, 'default' => 'h1', 'options' => [ 'h1' => 'H1', 'h2' => 'H2', 'div' => 'div' ], 'description' => esc_html__( 'One H1 per page: the hero\'s title is usually it.', 'samanzavarian-widgets' ) ] );
		$this->add_responsive_control( 'size', [ 'label' => esc_html__( 'Size', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'size_units' => [ 'px', 'vw' ], 'range' => [ 'px' => [ 'min' => 32, 'max' => 220 ] ], 'selectors' => [ '{{WRAPPER}} .sz-htitle' => 'font-size: {{SIZE}}{{UNIT}};' ] ] );
		$this->add_responsive_control( 'align', [ 'label' => esc_html__( 'Alignment', 'samanzavarian-widgets' ), 'type' => Controls_Manager::CHOOSE, 'options' => [ 'flex-start' => [ 'title' => esc_html__( 'Left', 'samanzavarian-widgets' ), 'icon' => 'eicon-text-align-left' ], 'center' => [ 'title' => esc_html__( 'Center', 'samanzavarian-widgets' ), 'icon' => 'eicon-text-align-center' ], 'flex-end' => [ 'title' => esc_html__( 'Right', 'samanzavarian-widgets' ), 'icon' => 'eicon-text-align-right' ] ], 'default' => 'center', 'selectors' => [ '{{WRAPPER}} .sz-htitle' => 'justify-content: {{VALUE}};' ] ] );
		$this->end_controls_section();
	}

	protected function render(): void {
		$s    = $this->get_settings_for_display();
		$tag  = in_array( $s['tag'] ?? 'h1', [ 'h1', 'h2', 'div' ], true ) ? $s['tag'] : 'h1';
		$icon = ! empty( $s['icon']['value'] ) ? Icon_Library::html( $s['icon'] ) : '';
		printf( '<%s class="sz-htitle sz-w" data-sz-rise>', esc_attr( $tag ) );
		if ( '' !== trim( (string) $s['first'] ) ) {
			echo '<span class="sz-htitle__w">' . esc_html( $s['first'] ) . '</span>';
		}
		if ( '' !== $icon ) {
			echo '<span class="sz-htitle__app sz-app" aria-hidden="true">' . $icon . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup
		}
		if ( '' !== trim( (string) $s['second'] ) ) {
			echo '<span class="sz-htitle__w is-o">' . esc_html( $s['second'] ) . '</span>';
		}
		printf( '</%s>', esc_attr( $tag ) );
	}
}
