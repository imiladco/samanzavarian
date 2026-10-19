<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Plugin as Elementor;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Samanzavarian_Widgets\Icon_Library;
use Samanzavarian_Widgets\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Dock — the frosted bar of app icons at the foot of the hero ("What I
 * do"), drawn after the Mac dock and behaving like it: the icons swell as
 * the pointer nears, the nearest most, its neighbours a little (worked out
 * from the pointer's distance, every frame, only while the pointer is
 * there). A separator can stand before an item (the design's Behance).
 *
 * By default it sits pinned to the bottom of its container, centred. On a
 * phone it joins the flow as a three-column grid of the items marked for
 * phones.
 */
class Dock extends Widget_Base {

	private const ITEMS = [
		[ 'Creative direction', 'palette', 'navy', '#services', '' ],
		[ 'AI visuals', 'spark', 'orange', '#services', '' ],
		[ 'Post-production', 'sliders', 'navy', '#services', '' ],
		[ 'Behance', 'be', 'white', 'https://www.behance.net/', 'yes' ],
	];

	public function get_name(): string {
		return 'sz-dock';
	}

	public function get_title(): string {
		return esc_html__( 'Dock', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-apps';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'dock', 'hero', 'icons', 'nav', 'services', 'links' ];
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
		$this->start_controls_section( 'section_items', [ 'label' => esc_html__( 'Items', 'samanzavarian-widgets' ) ] );
		$r = new Repeater();
		$r->add_control( 'label', [ 'label' => esc_html__( 'Label', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Item' ] );
		$r->add_control( 'icon', [ 'label' => esc_html__( 'Icon', 'samanzavarian-widgets' ), 'type' => Controls_Manager::ICONS, 'skin' => 'inline', 'label_block' => false, 'default' => Icon_Library::value( 'spark' ) ] );
		$r->add_control( 'tone', [ 'label' => esc_html__( 'Tile', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SELECT, 'default' => 'navy', 'options' => [ 'navy' => esc_html__( 'Navy', 'samanzavarian-widgets' ), 'orange' => esc_html__( 'Orange', 'samanzavarian-widgets' ), 'white' => esc_html__( 'White', 'samanzavarian-widgets' ) ] ] );
		$r->add_control( 'link', [ 'label' => esc_html__( 'Link', 'samanzavarian-widgets' ), 'type' => Controls_Manager::URL, 'dynamic' => [ 'active' => true ] ] );
		$r->add_control( 'sep', [ 'label' => esc_html__( 'Separator before it', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes' ] );
		$r->add_control( 'no_phone', [ 'label' => esc_html__( 'Hide on phones', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes' ] );
		$this->add_control(
			'items',
			[
				'label'       => esc_html__( 'Items', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'title_field' => '{{{ label }}}',
				'default'     => array_map( static fn( $x ) => [ 'label' => $x[0], 'icon' => Icon_Library::value( $x[1] ), 'tone' => $x[2], 'link' => [ 'url' => $x[3], 'is_external' => 'yes' === $x[4] ? 'on' : '' ], 'sep' => $x[4], 'no_phone' => $x[4] ], self::ITEMS ),
			]
		);
		$this->add_control( 'label_aria', [ 'label' => esc_html__( 'Name for screen readers', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'What I do' ] );
		$this->add_control( 'pin', [ 'label' => esc_html__( 'Pinned to the bottom of its container', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'prefix_class' => 'sz-dock-pin-', 'separator' => 'before', 'description' => esc_html__( 'Centred, 30px from the bottom (phones: in the flow).', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'magnify', [ 'label' => esc_html__( 'Swell under the pointer', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ] );
		$this->end_controls_section();
	}

	protected function render(): void {
		$s     = $this->get_settings_for_display();
		$items = array_values( array_filter( (array) ( $s['items'] ?? [] ), static fn( $x ) => '' !== trim( (string) ( $x['label'] ?? '' ) ) ) );
		if ( ! $items ) {
			return;
		}
		printf( '<nav class="sz-dock sz-w" aria-label="%s"%s>', esc_attr( (string) ( $s['label_aria'] ?? '' ) ), 'yes' === ( $s['magnify'] ?? 'yes' ) ? ' data-sz-dock' : '' );
		foreach ( $items as $i => $x ) {
			if ( 'yes' === ( $x['sep'] ?? '' ) && $i > 0 ) {
				echo '<i class="sz-dock__sep' . ( 'yes' === ( $x['no_phone'] ?? '' ) ? ' is-wide' : '' ) . '" aria-hidden="true"></i>';
			}
			$key = 'a' . $i;
			$this->add_render_attribute( $key, 'class', 'sz-dock__a' . ( 'yes' === ( $x['no_phone'] ?? '' ) ? ' is-wide' : '' ) );
			if ( ! empty( $x['link']['url'] ) ) {
				$this->add_link_attributes( $key, $x['link'] );
			}
			$tone = [ 'orange' => ' sz-app--orange', 'white' => ' sz-app--white' ][ $x['tone'] ?? '' ] ?? '';
			$icon = ! empty( $x['icon']['value'] ) ? Icon_Library::html( $x['icon'] ) : '';
			echo '<a ' . $this->get_render_attribute_string( $key ) . '><span class="sz-dock__app sz-app' . esc_attr( $tone ) . '" aria-hidden="true">' . $icon . '</span><span class="sz-dock__l">' . esc_html( $x['label'] ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor escapes attributes; icon markup
		}
		echo '</nav>';
	}
}
