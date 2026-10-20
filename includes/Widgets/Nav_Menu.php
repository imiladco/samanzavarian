<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Plugin as Elementor;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Samanzavarian_Widgets\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Nav Menu — the header's links.
 *
 * - Under the pointer one white pill glides from link to link; the link of
 *   the section on screen keeps its own (a #section link on this page).
 * - On a phone, where the design simply hid the links, a menu button opens
 *   them in a glass sheet (Escape, a tap outside or a link closes it); a
 *   button at its foot is optional (the bar's own Button is the call).
 * - "Glass bar": the container the menu sits in becomes the design's
 *   frosted pill, floating at the top of the window, always there (it can
 *   be set to step aside while scrolling down).
 *   Done in CSS (:has), so it is right before any script runs.
 */
class Nav_Menu extends Widget_Base {

	private const LINKS = [ [ 'Services', '#services' ], [ 'Work', '#work' ], [ 'About', '#about' ] ];

	public function get_name(): string {
		return 'sz-nav';
	}

	public function get_title(): string {
		return esc_html__( 'Nav Menu', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-nav-menu';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'menu', 'nav', 'header', 'links', 'navigation' ];
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
		$this->start_controls_section( 'section_links', [ 'label' => esc_html__( 'Links', 'samanzavarian-widgets' ) ] );
		$r = new Repeater();
		$r->add_control( 'text', [ 'label' => esc_html__( 'Text', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Link' ] );
		$r->add_control( 'link', [ 'label' => esc_html__( 'Link', 'samanzavarian-widgets' ), 'type' => Controls_Manager::URL, 'dynamic' => [ 'active' => true ] ] );
		$this->add_control(
			'links',
			[
				'label'       => esc_html__( 'Links', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'title_field' => '{{{ text }}}',
				'default'     => array_map( static fn( $x ) => [ 'text' => $x[0], 'link' => [ 'url' => $x[1] ] ], self::LINKS ),
			]
		);
		$this->add_control( 'menu_label', [ 'label' => esc_html__( 'Menu button (phones)', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Menu' ] );
		$this->add_control( 'bar_cta', [ 'label' => esc_html__( '"Let\'s chat" button on the bar', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'separator' => 'before', 'description' => esc_html__( 'After the links, on the right of the bar (on phones too). No separate Button widget needed.', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'cta', [ 'label' => esc_html__( 'Also at the foot of the phone menu', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => '', 'return_value' => 'yes' ] );
		$this->add_control( 'cta_text', [ 'label' => esc_html__( 'Button text', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => "Let's chat" ] );
		$this->add_control( 'cta_link', [ 'label' => esc_html__( 'Button link', 'samanzavarian-widgets' ), 'type' => Controls_Manager::URL, 'default' => [ 'url' => '#contact' ], 'dynamic' => [ 'active' => true ] ] );
		$this->add_control( 'phone', [ 'label' => esc_html__( 'On phones', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SELECT, 'default' => 'menu', 'options' => [ 'menu' => esc_html__( 'A menu button', 'samanzavarian-widgets' ), 'hide' => esc_html__( 'Hidden (as in the design)', 'samanzavarian-widgets' ), 'show' => esc_html__( 'Shown as on a computer', 'samanzavarian-widgets' ) ] ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_bar', [ 'label' => esc_html__( 'Header bar', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'glass', [ 'label' => esc_html__( 'Glass bar', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'prefix_class' => 'sz-nav-glass-', 'description' => esc_html__( 'The container this menu is in becomes the design\'s frosted pill, floating at the top of the window. Put the Brand Mark, this menu and a Button in one container (a row).', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'hide', [ 'label' => esc_html__( 'Step aside while scrolling down', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => '', 'return_value' => 'yes', 'condition' => [ 'glass' => 'yes' ] ] );
		$this->add_responsive_control( 'bar_w', [ 'label' => esc_html__( 'Bar width', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'size_units' => [ 'px' ], 'range' => [ 'px' => [ 'min' => 280, 'max' => 1400 ] ], 'selectors' => [ '{{WRAPPER}}' => '--sz-bar-w: {{SIZE}}{{UNIT}};' ], 'condition' => [ 'glass' => 'yes' ] ] );
		$this->add_responsive_control( 'bar_top', [ 'label' => esc_html__( 'Distance from the top', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'size_units' => [ 'px' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 60 ] ], 'selectors' => [ '{{WRAPPER}}' => '--sz-bar-top: {{SIZE}}{{UNIT}};' ], 'condition' => [ 'glass' => 'yes' ] ] );
		$this->end_controls_section();

		Style_Controls::text( $this, 'st_link', esc_html__( 'Links', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-nav__a', [ 'no_margin' => true, 'hover' => '{{WRAPPER}} .sz-nav__a:hover' ] );
		Style_Controls::box( $this, 'st_pill', esc_html__( 'Pill under the pointer', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-nav__glide' );
		Style_Controls::box( $this, 'st_here', esc_html__( 'Link of the section on screen', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-nav__a.is-here', [ 'extra' => [ 'c' => [ 'label' => esc_html__( 'Text colour', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .sz-nav__a.is-here' => 'color: {{VALUE}};' ] ] ] ] );
		Style_Controls::box( $this, 'st_go', esc_html__( '"Let\'s chat" on the bar', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-nav .sz-nav__go', [ 'hover' => '{{WRAPPER}} .sz-nav .sz-nav__go:hover', 'condition' => [ 'bar_cta' => 'yes' ] ] );
		Style_Controls::text( $this, 'st_go_t', esc_html__( '"Let\'s chat" on the bar: text', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-nav .sz-nav__go', [ 'no_margin' => true, 'hover' => '{{WRAPPER}} .sz-nav .sz-nav__go:hover', 'condition' => [ 'bar_cta' => 'yes' ] ] );
		Style_Controls::box( $this, 'st_cta', esc_html__( 'Phone menu button', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-nav .sz-nav__cta', [ 'condition' => [ 'cta' => 'yes' ], 'extra' => [ 'c' => [ 'label' => esc_html__( 'Text colour', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .sz-nav .sz-nav__cta' => 'color: {{VALUE}};' ] ] ] ] );
		Style_Controls::box( $this, 'st_sheet', esc_html__( 'Phone menu sheet', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-nav__sheet' );
		Style_Controls::box( $this, 'st_bar', esc_html__( 'Glass bar', 'samanzavarian-widgets' ), '.sz-navbar:has({{WRAPPER}})', [ 'condition' => [ 'glass' => 'yes' ] ] );
	}

	protected function render(): void {
		$s     = $this->get_settings_for_display();
		$links = array_values( array_filter( (array) ( $s['links'] ?? [] ), static fn( $x ) => '' !== trim( (string) ( $x['text'] ?? '' ) ) ) );
		if ( ! $links ) {
			return;
		}
		$phone = in_array( $s['phone'] ?? 'menu', [ 'menu', 'hide', 'show' ], true ) ? $s['phone'] : 'menu';
		$id    = 'sz-nav-' . $this->get_id();
		printf( '<nav class="sz-nav sz-w is-phone-%s" aria-label="Primary" data-sz-nav%s>', esc_attr( $phone ), 'yes' === ( $s['glass'] ?? 'yes' ) && 'yes' === ( $s['hide'] ?? '' ) ? ' data-hide' : '' );
		if ( 'menu' === $phone ) {
			printf( '<button type="button" class="sz-nav__menu" aria-expanded="false" aria-controls="%s"><span class="sz-nav__burger" aria-hidden="true"><i></i><i></i></span><span>%s</span></button>', esc_attr( $id ), esc_html( (string) ( $s['menu_label'] ?? 'Menu' ) ) );
		}
		printf( '<div class="sz-nav__sheet" id="%s"><span class="sz-nav__glide" aria-hidden="true"></span>', esc_attr( $id ) );
		foreach ( $links as $i => $x ) {
			$key = 'l' . $i;
			$this->add_render_attribute( $key, 'class', 'sz-nav__a' );
			if ( ! empty( $x['link']['url'] ) ) {
				$this->add_link_attributes( $key, $x['link'] );
			}
			echo '<a ' . $this->get_render_attribute_string( $key ) . '>' . esc_html( $x['text'] ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor escapes
		}
		if ( 'menu' === $phone && 'yes' === ( $s['cta'] ?? '' ) && '' !== trim( (string) ( $s['cta_text'] ?? '' ) ) ) {
			$this->add_render_attribute( 'cta', 'class', 'sz-nav__cta' );
			if ( ! empty( $s['cta_link']['url'] ) ) {
				$this->add_link_attributes( 'cta', $s['cta_link'] );
			}
			echo '<a ' . $this->get_render_attribute_string( 'cta' ) . '><span class="sz-nav__cta-app sz-app sz-app--orange" aria-hidden="true">' . \Samanzavarian_Widgets\Icon_Library::svg( 'chat' ) . '</span><span>' . esc_html( $s['cta_text'] ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor escapes; static svg
		}
		echo '</div>';
		if ( 'yes' === ( $s['bar_cta'] ?? 'yes' ) && '' !== trim( (string) ( $s['cta_text'] ?? '' ) ) ) {
			$this->add_render_attribute( 'go', 'class', 'sz-nav__go' );
			if ( ! empty( $s['cta_link']['url'] ) ) {
				$this->add_link_attributes( 'go', $s['cta_link'] );
			}
			echo '<a ' . $this->get_render_attribute_string( 'go' ) . '>' . esc_html( $s['cta_text'] ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor escapes
		}
		echo '</nav>';
	}
}
