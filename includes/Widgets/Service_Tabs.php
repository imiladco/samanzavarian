<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Modules\NestedElements\Base\Widget_Nested_Base;
use Elementor\Modules\NestedElements\Controls\Control_Nested_Repeater;
use Elementor\Plugin as Elementor;
use Elementor\Repeater;
use Samanzavarian_Widgets\Icon_Library;
use Samanzavarian_Widgets\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * "What I do" — a list of services as tabs, each opening a panel.
 *
 * A nested widget, like Elementor's own Tabs: every tab owns a real
 * Elementor container, so a panel holds whatever is dropped into it. By
 * default each container holds a Service Panel widget pre-filled with the
 * design's content; delete it and build anything else in its place.
 *
 * Accessibility follows the WAI-ARIA tabs pattern: one tab stop, arrow keys,
 * Home/End, selection follows focus, and aria-orientation tracks the layout
 * actually on screen (the list turns from a column into a row on narrow
 * screens). Every panel is in the HTML, so all content is indexable.
 */
class Service_Tabs extends Widget_Nested_Base {

	/** Containers created with the widget, one per default tab. */
	private const DEFAULT_TABS = [
		[ 'spark', 'orange', 'AI visual production', 'Images and video, directed like a real shoot', 'ai' ],
		[ 'palette', '', 'Creative direction', 'A clear idea before anything is made', 'direction' ],
		[ 'sliders', '', 'Post-production', 'Edit, retouch, grade and motion', 'post' ],
		[ 'cal', '', 'Content systems', 'Plans and formats for brands that publish every week', 'content' ],
	];

	/** Per-render map of the children's attributes, consumed by print_child(). */
	private array $panels = [];

	/**
	 * Nested widgets need Elementor's container experiment (on by default
	 * since Elementor 3.x). Without it the widget is not offered at all.
	 */
	public static function is_supported(): bool {
		return class_exists( Widget_Nested_Base::class )
			&& Elementor::$instance->experiments->is_feature_active( 'container' );
	}

	public function get_name(): string {
		return 'sz-service-tabs';
	}

	public function get_title(): string {
		return esc_html__( 'Service Tabs', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-tabs';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'tabs', 'services', 'what i do', 'nested', 'panel' ];
	}

	public function get_style_depends(): array {
		return [ 'sz-service-tabs' ];
	}

	public function get_script_depends(): array {
		return [ 'sz-service-tabs' ];
	}

	public function has_widget_inner_wrapper(): bool {
		return ! Elementor::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	public function show_in_panel(): bool {
		return self::is_supported();
	}

	/* =================================================================
	 * Nesting contract
	 * ================================================================= */

	/** @return array<int,array<string,mixed>> */
	protected function get_default_children_elements() {
		$children = [];

		foreach ( self::DEFAULT_TABS as $index => $tab ) {
			$children[] = self::child_container(
				$tab[2],
				[
					[
						'elType'     => 'widget',
						'widgetType' => 'sz-service-panel',
						'settings'   => Service_Panel::preset( $tab[4] ),
					],
				]
			);
		}

		return $children;
	}

	/**
	 * A tab's container: full width, no padding of its own, children stacked —
	 * so a Service Panel inside fills it edge to edge, as in the design.
	 *
	 * @param array<int,array<string,mixed>> $elements
	 * @return array<string,mixed>
	 */
	public static function child_container( string $title, array $elements = [] ): array {
		return [
			'elType'   => 'container',
			'settings' => [
				'_title'         => $title,
				'content_width'  => 'full',
				'flex_direction' => 'column',
				'flex_gap'       => [ 'size' => 0, 'column' => '0', 'row' => '0', 'unit' => 'px' ],
				'padding'        => [ 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ],
			],
			'elements' => $elements,
		];
	}

	protected function get_default_repeater_title_setting_key() {
		return 'tab_title';
	}

	protected function get_default_children_title() {
		/* translators: %d: tab number */
		return esc_html__( 'Panel #%d', 'samanzavarian-widgets' );
	}

	protected function get_default_children_placeholder_selector() {
		return '.sz-tabs__stage';
	}

	protected function get_html_wrapper_class() {
		return 'elementor-widget-sz-service-tabs';
	}

	/* =================================================================
	 * Controls
	 * ================================================================= */

	protected function register_controls(): void {
		$this->content_tabs();
		$this->content_note();
		$this->content_settings();
		Pin_Controls::controls( $this, [ 'desktop' ], 60, esc_html__( 'The section holds still while the page\'s scroll opens one service after another, and back again when scrolling up. A tab clicked takes the page to its place.', 'samanzavarian-widgets' ) );

		$this->style_layout();
		$this->style_list();
		$this->style_tab();
		$this->style_icon();
		$this->style_text();
		$this->style_chevron();
		$this->style_note();
		$this->style_panels();
	}

	private function content_tabs(): void {
		$this->start_controls_section( 'section_tabs', [ 'label' => esc_html__( 'Tabs', 'samanzavarian-widgets' ) ] );

		$repeater = new Repeater();

		$repeater->add_control(
			'tab_title',
			[
				'label'       => esc_html__( 'Title', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Service', 'samanzavarian-widgets' ),
				'label_block' => true,
				'dynamic'     => [ 'active' => true ],
			]
		);

		$repeater->add_control(
			'tab_subtitle',
			[
				'label'       => esc_html__( 'Subtitle', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'dynamic'     => [ 'active' => true ],
			]
		);

		$repeater->add_control(
			'tab_icon',
			[
				'label'   => esc_html__( 'Icon', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::ICONS,
				'default' => Icon_Library::value( 'spark' ),
			]
		);

		$repeater->add_control(
			'tab_icon_style',
			[
				'label'   => esc_html__( 'Icon tile', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					''       => esc_html__( 'Navy', 'samanzavarian-widgets' ),
					'orange' => esc_html__( 'Orange', 'samanzavarian-widgets' ),
					'white'  => esc_html__( 'White', 'samanzavarian-widgets' ),
				],
			]
		);

		$repeater->add_control(
			'tab_anchor',
			[
				'label'       => esc_html__( 'Link ID', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => 'ai-visuals',
				'description' => esc_html__( 'A link to #this-id anywhere on the site opens this tab and scrolls to it. Empty: made from the title (AI visual production → #ai-visual-production).', 'samanzavarian-widgets' ),
				'ai'          => [ 'active' => false ],
			]
		);

		$defaults = [];

		foreach ( self::DEFAULT_TABS as $tab ) {
			$defaults[] = [
				'tab_icon'       => Icon_Library::value( $tab[0] ),
				'tab_icon_style' => $tab[1],
				'tab_title'      => $tab[2],
				'tab_subtitle'   => $tab[3],
			];
		}

		$this->add_control(
			'tabs',
			[
				'label'       => esc_html__( 'Tabs', 'samanzavarian-widgets' ),
				'type'        => Control_Nested_Repeater::CONTROL_TYPE,
				'fields'      => $repeater->get_controls(),
				'default'     => $defaults,
				'title_field' => '{{{ tab_title }}}',
				'button_text' => esc_html__( 'Add tab', 'samanzavarian-widgets' ),
			]
		);

		$this->end_controls_section();
	}

	private function content_note(): void {
		$this->start_controls_section( 'section_note', [ 'label' => esc_html__( 'Note under the tabs', 'samanzavarian-widgets' ) ] );

		$this->add_control(
			'show_note',
			[
				'label'        => esc_html__( 'Show note', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			]
		);

		$this->add_control(
			'note_icon',
			[
				'label'     => esc_html__( 'Icon', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::ICONS,
				'default'   => Icon_Library::value( 'globe' ),
				'condition' => [ 'show_note' => 'yes' ],
			]
		);

		$this->add_control(
			'note_text',
			[
				'label'     => esc_html__( 'Text', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::TEXTAREA,
				'rows'      => 2,
				'default'   => esc_html__( 'All of it runs remotely, wherever you are.', 'samanzavarian-widgets' ),
				'dynamic'   => [ 'active' => true ],
				'condition' => [ 'show_note' => 'yes' ],
			]
		);

		$this->end_controls_section();
	}

	private function content_settings(): void {
		$this->start_controls_section( 'section_settings', [ 'label' => esc_html__( 'Settings', 'samanzavarian-widgets' ) ] );

		$this->add_responsive_control(
			'layout',
			[
				'label'                => esc_html__( 'Tab list', 'samanzavarian-widgets' ),
				'type'                 => Controls_Manager::SELECT,
				'options'              => [
					'side' => esc_html__( 'Beside the panel', 'samanzavarian-widgets' ),
					'top'  => esc_html__( 'Row above the panel', 'samanzavarian-widgets' ),
				],
				'default'              => 'side',
				'tablet_default'       => 'top',
				'mobile_default'       => 'top',
				'description'          => esc_html__( 'Follows your Elementor breakpoints. The row scrolls sideways when the tabs do not fit, and shows titles only.', 'samanzavarian-widgets' ),
				'selectors_dictionary' => [
					'side' => Service_Tabs_Layout::vars( 'side' ),
					'top'  => Service_Tabs_Layout::vars( 'top' ),
				],
				'selectors'            => [ '{{WRAPPER}} .sz-tabs' => '{{VALUE}}' ],
			]
		);

		$this->add_control(
			'active_tab',
			[
				'label'       => esc_html__( 'Open tab on load', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 1,
				'step'        => 1,
				'default'     => 1,
				'description' => esc_html__( 'A link to a tab\'s ID overrides this.', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control(
			'equal_height',
			[
				'label'        => esc_html__( 'Same height for every panel', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'description'  => esc_html__( 'On (the design): the page never jumps when switching tabs. Off: each panel takes its own height.', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control(
			'update_url',
			[
				'label'        => esc_html__( 'Put the open tab in the address', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '',
				'return_value' => 'yes',
				'description'  => esc_html__( 'Only for tabs with a Link ID. Lets visitors share or bookmark a tab.', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control(
			'analytics',
			[
				'label'        => esc_html__( 'Report to analytics', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'separator'    => 'before',
				'description'  => esc_html__( 'Sends sz_services_view (the list was seen) and sz_service_tab (a service was opened, with its name) to Google Tag Manager\'s dataLayer, or to GA4 directly when it is installed without GTM. Does nothing if neither is on the site.', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control(
			'list_label',
			[
				'label'       => esc_html__( 'Name for screen readers', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Services', 'samanzavarian-widgets' ),
				'separator'   => 'before',
				'ai'          => [ 'active' => false ],
			]
		);

		$this->end_controls_section();
	}

	private function style_layout(): void {
		$this->start_controls_section( 'style_layout', [ 'label' => esc_html__( 'Layout', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$this->add_responsive_control(
			'list_width',
			[
				'label'       => esc_html__( 'Tab list width', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', '%', 'custom' ],
				'range'       => [
					'px' => [ 'min' => 200, 'max' => 700 ],
					'%'  => [ 'min' => 15, 'max' => 70 ],
				],
				'description' => esc_html__( 'When the list is beside the panel. Design: about 35%, never under 300px.', 'samanzavarian-widgets' ),
				'selectors'   => [ '{{WRAPPER}} .sz-tabs' => '--szt-cols: {{SIZE}}{{UNIT}} minmax(0,1fr);' ],
			]
		);

		$this->add_responsive_control(
			'gap',
			[
				'label'       => esc_html__( 'Gap', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 18 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', 'em', 'custom' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 80 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-tabs' => 'gap: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->end_controls_section();
	}

	private function style_list(): void {
		$this->start_controls_section( 'style_list', [ 'label' => esc_html__( 'Tab list', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name'     => 'list_bg',
				'types'    => [ 'classic', 'gradient' ],
				'selector' => '{{WRAPPER}} .sz-tabs__nav',
			]
		);

		$this->add_responsive_control(
			'list_padding',
			[
				'label'       => esc_html__( 'Padding', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph_box( 8, 8, 8, 8 ),
				'type'        => Controls_Manager::DIMENSIONS,
				'size_units'  => [ 'px', 'em', 'custom' ],
				'selectors'   => [ '{{WRAPPER}} .sz-tabs' => '--szt-list-pad: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'list_radius',
			[
				'label'       => esc_html__( 'Radius', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 30 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', '%', 'custom' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-tabs' => '--szt-list-radius: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_group_control( Group_Control_Border::get_type(), [ 'name' => 'list_border', 'selector' => '{{WRAPPER}} .sz-tabs__nav' ] );
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), [ 'name' => 'list_shadow', 'selector' => '{{WRAPPER}} .sz-tabs__nav' ] );

		$this->add_responsive_control(
			'tabs_spacing',
			[
				'label'       => esc_html__( 'Space between tabs', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 4 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', 'custom' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-tabs__list' => 'gap: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->end_controls_section();
	}

	private function style_tab(): void {
		$this->start_controls_section( 'style_tab', [ 'label' => esc_html__( 'Tab', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$this->add_responsive_control(
			'tab_padding',
			[
				'label'       => esc_html__( 'Padding', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph_box( 16, 14, 16, 14 ),
				'type'        => Controls_Manager::DIMENSIONS,
				'size_units'  => [ 'px', 'em', 'custom' ],
				'selectors'   => [ '{{WRAPPER}} .sz-tabs' => '--szt-tab-pad: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'tab_gap',
			[
				'label'       => esc_html__( 'Space inside', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 14 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', 'custom' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-tabs' => '--szt-tab-gap: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'tab_radius',
			[
				'label'       => esc_html__( 'Radius', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 22 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', '%', 'custom' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
				'description' => esc_html__( 'Also shapes the sliding highlight.', 'samanzavarian-widgets' ),
				'selectors'   => [ '{{WRAPPER}} .sz-tabs' => '--szt-tab-radius: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->start_controls_tabs( 'tab_states' );

		foreach ( [
			'normal' => [ esc_html__( 'Normal', 'samanzavarian-widgets' ), '.sz-tabs__tab' ],
			'hover'  => [ esc_html__( 'Hover', 'samanzavarian-widgets' ), '.sz-tabs__tab:not([aria-selected="true"]):hover' ],
			'active' => [ esc_html__( 'Active', 'samanzavarian-widgets' ), '.sz-tabs__tab[aria-selected="true"]' ],
		] as $state => $def ) {
			$sel = '{{WRAPPER}} ' . $def[1];

			$this->start_controls_tab( 'tab_' . $state, [ 'label' => $def[0] ] );

			if ( 'active' === $state ) {
				$this->add_control(
					'highlight_color',
					[
						'label'       => esc_html__( 'Highlight', 'samanzavarian-widgets' ),
						'type'        => Controls_Manager::COLOR,
						'description' => esc_html__( 'The shape that slides to the active tab.', 'samanzavarian-widgets' ),
						'selectors'   => [ '{{WRAPPER}} .sz-tabs__hl' => 'background-color: {{VALUE}};' ],
					]
				);
			} else {
				$this->add_control(
					'tab_bg_' . $state,
					[
						'label'     => esc_html__( 'Background', 'samanzavarian-widgets' ),
						'type'      => Controls_Manager::COLOR,
						'selectors' => [ $sel => 'background-color: {{VALUE}};' ],
					]
				);
			}

			$this->add_control(
				'tab_title_color_' . $state,
				[
					'label'     => esc_html__( 'Title color', 'samanzavarian-widgets' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => [ $sel . ' .sz-tabs__title' => 'color: {{VALUE}};' ],
				]
			);

			$this->add_control(
				'tab_sub_color_' . $state,
				[
					'label'     => esc_html__( 'Subtitle color', 'samanzavarian-widgets' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => [ $sel . ' .sz-tabs__sub' => 'color: {{VALUE}};' ],
				]
			);

			$this->end_controls_tab();
		}

		$this->end_controls_tabs();

		$this->add_control(
			'motion_duration',
			[
				'label'       => esc_html__( 'Highlight speed', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 550, 'ms' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'ms' ],
				'range'       => [ 'ms' => [ 'min' => 0, 'max' => 1500, 'step' => 50 ] ],
				'separator'   => 'before',
				'selectors'   => [ '{{WRAPPER}} .sz-tabs' => '--szt-hl-dur: {{SIZE}}ms;' ],
			]
		);

		$this->end_controls_section();
	}

	private function style_icon(): void {
		$this->start_controls_section( 'style_icon', [ 'label' => esc_html__( 'Icon', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$this->add_responsive_control(
			'icon_box',
			[
				'label'       => esc_html__( 'Tile size', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 46 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', 'custom' ],
				'range'       => [ 'px' => [ 'min' => 16, 'max' => 100 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-tabs' => '--szt-icon-box: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'icon_size',
			[
				'label'       => esc_html__( 'Icon size', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 22 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', 'custom' ],
				'range'       => [ 'px' => [ 'min' => 8, 'max' => 60 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-tabs' => '--szt-icon-size: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'icon_radius',
			[
				'label'       => esc_html__( 'Tile radius', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 13 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', '%', 'custom' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-tabs' => '--szt-icon-radius: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_control(
			'icon_color',
			[
				'label'     => esc_html__( 'Icon color', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .sz-tabs__icon' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name'           => 'icon_bg',
				'types'          => [ 'classic', 'gradient' ],
				'exclude'        => [ 'image' ],
				'selector'       => '{{WRAPPER}} .sz-tabs__icon',
				'fields_options' => Control_Helpers::clears_design_gradient( '--sz-app-bg' ),
			]
		);

		$this->add_control(
			'icon_active_motion',
			[
				'label'        => esc_html__( 'Tilt the active icon', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'prefix_class' => 'sz-tabs-tilt-',
			]
		);

		$this->end_controls_section();
	}

	private function style_text(): void {
		$this->start_controls_section( 'style_text', [ 'label' => esc_html__( 'Title & subtitle', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'title_typo', 'label' => esc_html__( 'Title', 'samanzavarian-widgets' ), 'selector' => '{{WRAPPER}} .sz-tabs__title' ] );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'sub_typo', 'label' => esc_html__( 'Subtitle', 'samanzavarian-widgets' ), 'selector' => '{{WRAPPER}} .sz-tabs__sub' ] );

		$this->add_responsive_control(
			'sub_spacing',
			[
				'label'       => esc_html__( 'Space above subtitle', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 3 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', 'custom' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-tabs__sub' => 'margin-top: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->end_controls_section();
	}

	private function style_chevron(): void {
		$this->start_controls_section( 'style_chevron', [ 'label' => esc_html__( 'Chevron', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$this->add_control(
			'show_chevron',
			[
				'label'        => esc_html__( 'Show chevron', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			]
		);

		$this->add_control(
			'chev_size',
			[
				'label'       => esc_html__( 'Size', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 18 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px' ],
				'range'       => [ 'px' => [ 'min' => 8, 'max' => 40 ] ],
				'condition'   => [ 'show_chevron' => 'yes' ],
				'selectors'   => [ '{{WRAPPER}} .sz-tabs__chev' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_control(
			'chev_color',
			[
				'label'     => esc_html__( 'Color', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => [ 'show_chevron' => 'yes' ],
				'selectors' => [ '{{WRAPPER}} .sz-tabs__chev' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'chev_color_active',
			[
				'label'     => esc_html__( 'Active color', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => [ 'show_chevron' => 'yes' ],
				'selectors' => [ '{{WRAPPER}} .sz-tabs__tab[aria-selected="true"] .sz-tabs__chev' => 'color: {{VALUE}};' ],
			]
		);

		$this->end_controls_section();
	}

	private function style_note(): void {
		$this->start_controls_section(
			'style_note',
			[
				'label'     => esc_html__( 'Note', 'samanzavarian-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_note' => 'yes' ],
			]
		);

		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'note_typo', 'selector' => '{{WRAPPER}} .sz-tabs__note' ] );

		$this->add_control(
			'note_color',
			[
				'label'     => esc_html__( 'Color', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .sz-tabs__note' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'note_line',
			[
				'label'     => esc_html__( 'Divider color', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .sz-tabs__note' => 'border-top-color: {{VALUE}};' ],
			]
		);

		$this->add_responsive_control(
			'note_padding',
			[
				'label'       => esc_html__( 'Padding', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph_box( 14, 14, 8, 14 ),
				'type'        => Controls_Manager::DIMENSIONS,
				'size_units'  => [ 'px', 'em', 'custom' ],
				'selectors'   => [ '{{WRAPPER}} .sz-tabs__note' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);

		$this->end_controls_section();
	}

	private function style_panels(): void {
		$this->start_controls_section( 'style_panels', [ 'label' => esc_html__( 'Panels', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$this->add_responsive_control(
			'stage_min',
			[
				'label'       => esc_html__( 'Minimum height', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 560 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', 'vh', 'custom' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 1000 ] ],
				'description' => esc_html__( 'Design: 560px beside the list, none when the list is a row.', 'samanzavarian-widgets' ),
				'selectors'   => [ '{{WRAPPER}} .sz-tabs' => '--szt-stage-min: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'panel_radius',
			[
				'label'       => esc_html__( 'Radius', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 30 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', '%', 'custom' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-tabs__stage > .sz-tabs__panel' => 'border-radius: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_group_control( Group_Control_Box_Shadow::get_type(), [ 'name' => 'panel_shadow', 'selector' => '{{WRAPPER}} .sz-tabs__stage > .sz-tabs__panel' ] );

		$this->add_control(
			'panel_transition',
			[
				'label'   => esc_html__( 'Switch animation', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'settle',
				'options' => [
					'settle' => esc_html__( 'Fade and settle (design)', 'samanzavarian-widgets' ),
					'fade'   => esc_html__( 'Fade', 'samanzavarian-widgets' ),
					'none'   => esc_html__( 'None', 'samanzavarian-widgets' ),
				],
				'prefix_class' => 'sz-tabs-fx-',
			]
		);

		$this->end_controls_section();
	}

	/* =================================================================
	 * Render
	 * ================================================================= */

	/** Lead-in and lead-out of the scroll-driven path, in hundredths of the screen height. */
	private const PIN_EDGE = 15;

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$tabs     = isset( $settings['tabs'] ) && is_array( $settings['tabs'] ) ? array_values( $settings['tabs'] ) : [];

		if ( ! $tabs ) {
			return;
		}

		$uid    = $this->get_id();
		$active = self::active_index( $settings['active_tab'] ?? 1, count( $tabs ) );

		$this->add_render_attribute(
			'root',
			[
				'class'         => [ 'sz-tabs', 'sz-w', 'yes' === ( $settings['equal_height'] ?? 'yes' ) ? 'sz-tabs--equal' : 'sz-tabs--auto' ],
				'data-sz-tabs'  => '',
				'data-sz-hash'  => 'yes' === ( $settings['update_url'] ?? '' ) ? '1' : '0',
				'data-sz-track' => 'yes' === ( $settings['analytics'] ?? 'yes' ) ? '1' : '0',
			]
		);

		$this->panels = [];
		// the path: a step per tab after the first, and a lead-in and lead-out (half a step's worth each)
		$step = (int) Pin_Controls::step( $settings, 60 );
		echo Pin_Controls::open( $settings, 'calc(' . ( count( $tabs ) - 1 ) . ' * ' . $step . 'svh + ' . self::PIN_EDGE * 2 . 'svh)', 'tabs', [ 'step' => $step, 'edge' => self::PIN_EDGE, 'n' => count( $tabs ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
		?>
		<div <?php $this->print_render_attribute_string( 'root' ); ?>>
			<div class="sz-tabs__nav">
				<div class="sz-tabs__list" role="tablist" aria-orientation="vertical" aria-label="<?php echo esc_attr( (string) ( $settings['list_label'] ?? '' ) ); ?>">
					<span class="sz-tabs__hl" aria-hidden="true"></span>
					<?php
					foreach ( $tabs as $index => $tab ) {
						$ids = self::ids( $uid, $index, $tab['tab_anchor'] ?? '', (string) ( $tab['tab_title'] ?? '' ) );

						$this->panels[ $index ] = [
							'id'     => $ids['panel'],
							'tab'    => $ids['tab'],
							'active' => $index === $active,
						];

						$this->render_tab( $tab, $index, $ids, $index === $active, 'yes' === ( $settings['show_chevron'] ?? 'yes' ) );
					}
					?>
				</div>
				<?php $this->render_note( $settings ); ?>
			</div>
			<div class="sz-tabs__stage">
				<?php
				foreach ( $tabs as $index => $tab ) {
					$this->print_child( $index );
				}
				?>
			</div>
		</div>
		<?php
		echo Pin_Controls::close( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static
	}

	/**
	 * @param array<string,mixed>  $tab
	 * @param array<string,string> $ids
	 */
	private function render_tab( array $tab, int $index, array $ids, bool $active, bool $chevron ): void {
		$style = in_array( $tab['tab_icon_style'] ?? '', [ 'orange', 'white' ], true ) ? ' sz-app--' . $tab['tab_icon_style'] : '';
		$icon  = Icon_Library::html( $tab['tab_icon'] ?? [] );
		$sub   = trim( (string) ( $tab['tab_subtitle'] ?? '' ) );
		?>
		<button type="button" class="sz-tabs__tab<?php echo empty( $tab['_id'] ) ? '' : ' elementor-repeater-item-' . esc_attr( $tab['_id'] ); ?>" role="tab" id="<?php echo esc_attr( $ids['tab'] ); ?>" aria-controls="<?php echo esc_attr( $ids['panel'] ); ?>" aria-selected="<?php echo $active ? 'true' : 'false'; ?>" tabindex="<?php echo $active ? '0' : '-1'; ?>" data-sz-index="<?php echo (int) $index; ?>">
			<?php if ( '' !== $icon ) : ?>
				<span class="sz-tabs__icon sz-app<?php echo esc_attr( $style ); ?>" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's icon renderer or our static glyph map ?></span>
			<?php endif; ?>
			<span class="sz-tabs__text">
				<span class="sz-tabs__title"><?php echo esc_html( (string) ( $tab['tab_title'] ?? '' ) ); ?></span>
				<?php if ( '' !== $sub ) : ?>
					<span class="sz-tabs__sub"><?php echo esc_html( $sub ); ?></span>
				<?php endif; ?>
			</span>
			<?php
			if ( $chevron ) {
				echo Icon_Library::svg( 'chev', 'sz-tabs__chev' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static glyph
			}
			?>
		</button>
		<?php
	}

	/** @param array<string,mixed> $settings */
	private function render_note( array $settings ): void {
		$text = trim( (string) ( $settings['note_text'] ?? '' ) );

		if ( 'yes' !== ( $settings['show_note'] ?? 'yes' ) || '' === $text ) {
			return;
		}

		$icon = Icon_Library::html( $settings['note_icon'] ?? [] );
		?>
		<p class="sz-tabs__note"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's icon renderer or our static glyph map ?><span><?php echo esc_html( $text ); ?></span></p>
		<?php
	}

	/**
	 * Print one tab's container with the tabpanel attributes. Elementor's own
	 * nested tabs use the same filter: the container renders itself, we only
	 * add attributes to it on the way out.
	 *
	 * @param int $index
	 */
	public function print_child( $index ) {
		$children = $this->get_children();

		if ( empty( $children[ $index ] ) ) {
			return;
		}

		$child = $children[ $index ];
		$panel = $this->panels[ $index ] ?? null;

		$add = static function ( $should_render, $container ) use ( $child, $panel ) {
			if ( $panel && $container->get_id() === $child->get_id() ) {
				$container->add_render_attribute(
					'_wrapper',
					[
						'id'              => $panel['id'],
						'role'            => 'tabpanel',
						'aria-labelledby' => $panel['tab'],
						'tabindex'        => '0',
						'class'           => [ 'sz-tabs__panel', $panel['active'] ? 'is-active' : '' ],
					]
				);
			}

			return $should_render;
		};

		add_filter( 'elementor/frontend/container/should_render', $add, 10, 2 );
		$child->print_element();
		remove_filter( 'elementor/frontend/container/should_render', $add, 10 );
	}

	/** 1-based setting → valid 0-based index. */
	public static function active_index( $setting, int $count ): int {
		$n = (int) $setting;

		return $n >= 1 && $n <= $count ? $n - 1 : 0;
	}

	/** Tab ids already printed on this page, so two tabs never share one. */
	private static array $used_ids = [];

	/**
	 * The id pair for a tab. The Link ID — or, when empty, a slug of the
	 * title — becomes the tab button's id, so a plain #link to it both
	 * scrolls there natively and is picked up by the script to open the tab.
	 * Titles that make no usable slug (e.g. non-Latin) fall back to a
	 * generated id; repeats on the page get -2, -3…
	 *
	 * @return array{tab:string,panel:string}
	 */
	public static function ids( string $uid, int $index, $anchor, string $title = '' ): array {
		$n    = $index + 1;
		$slug = sanitize_html_class( (string) $anchor );

		if ( '' === $slug && '' !== $title ) {
			$slug = sanitize_html_class( sanitize_title( $title ) );
		}
		if ( '' === $slug || is_numeric( $slug[0] ) ) {
			$slug = 'sz-tab-' . $uid . '-' . $n;
		}

		$id = $slug;
		for ( $k = 2; isset( self::$used_ids[ $id ] ) && self::$used_ids[ $id ] !== $uid . '-' . $n; $k++ ) {
			$id = $slug . '-' . $k;
		}
		self::$used_ids[ $id ] = $uid . '-' . $n;

		return [
			'tab'   => $id,
			'panel' => 'sz-panel-' . $uid . '-' . $n,
		];
	}

	/* =================================================================
	 * Editor template — mirrors render(); the children are placed into
	 * .sz-tabs__stage by Elementor's nested view.
	 * ================================================================= */

	protected function content_template(): void {
		?>
		<#
		const uid = view.getID(),
			tabs = settings.tabs || [],
			count = tabs.length,
			activeSetting = parseInt( settings.active_tab, 10 ),
			active = activeSetting >= 1 && activeSetting <= count ? activeSetting - 1 : 0;
		#>
		<# if ( count ) { #>
		<div class="sz-tabs sz-w {{ 'yes' === settings.equal_height ? 'sz-tabs--equal' : 'sz-tabs--auto' }}" data-sz-tabs data-sz-hash="0" data-sz-track="0">
			<div class="sz-tabs__nav">
				<div class="sz-tabs__list" role="tablist" aria-orientation="vertical" aria-label="{{ settings.list_label }}">
					<span class="sz-tabs__hl" aria-hidden="true"></span>
					<# _.each( tabs, function( tab, index ) {
						const ids = window.szwTabIds( uid, index, tab.tab_anchor, tab.tab_title ),
							on = index === active,
							style = [ 'orange', 'white' ].includes( tab.tab_icon_style ) ? ' sz-app--' + tab.tab_icon_style : '',
							icon = window.szwIcon( view, tab.tab_icon );
					#>
					<button type="button" class="sz-tabs__tab elementor-repeater-item-{{ tab._id }}" role="tab" id="{{ ids.tab }}" aria-controls="{{ ids.panel }}" aria-selected="{{ on ? 'true' : 'false' }}" tabindex="{{ on ? '0' : '-1' }}" data-sz-index="{{ index }}">
						<# if ( icon ) { #><span class="sz-tabs__icon sz-app{{ style }}" aria-hidden="true">{{{ icon }}}</span><# } #>
						<span class="sz-tabs__text">
							<span class="sz-tabs__title">{{ tab.tab_title }}</span>
							<# if ( tab.tab_subtitle ) { #><span class="sz-tabs__sub">{{ tab.tab_subtitle }}</span><# } #>
						</span>
						<# if ( 'yes' === settings.show_chevron ) { #>{{{ window.szwGlyph( 'chev', 'sz-tabs__chev' ) }}}<# } #>
					</button>
					<# } ); #>
				</div>
				<# if ( 'yes' === settings.show_note && settings.note_text ) { #>
				<p class="sz-tabs__note">{{{ window.szwIcon( view, settings.note_icon ) }}}<span>{{ settings.note_text }}</span></p>
				<# } #>
			</div>
			<div class="sz-tabs__stage"></div>
		</div>
		<# } #>
		<?php
	}
}
