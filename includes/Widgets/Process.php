<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Plugin as Elementor;
use Elementor\Repeater;
use Elementor\Utils;
use Elementor\Widget_Base;
use Samanzavarian_Widgets\Icon_Library;
use Samanzavarian_Widgets\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * "How we work" — numbered process cards pinned to a board, each pointing to
 * the next with a hand-drawn arrow.
 *
 * Defaults reproduce the design exactly. The desktop design lives in
 * process.css and Style-tab controls start empty, so a control writes CSS
 * only once someone changes it. The one exception is what differs between
 * screen sizes: those are tablet/mobile defaults on responsive controls,
 * because only then do they follow the site's Elementor breakpoints instead
 * of a width fixed in the stylesheet.
 */
class Process extends Widget_Base {

	/** Hand-drawn arrow shapes from the design, one per side of the cards. */
	private const ARROW_OVER  = 'M2 18c6-12 18-12 16-2-2 8-10 4-4-2 6-6 20-2 28 10M44 18l-2 7 8-3';
	private const ARROW_UNDER = 'M2 14c6 8 12 8 14 1 2-6-6-5-3 0 4 6 14 4 32-8M45 3l3 3-3 4';

	/** Inline tags a description may keep; everything else is stripped. */
	/** Icon used when "Icon" is picked as the arrow style. */
	private const ARROW_ICON_DEFAULT = [
		'value'   => 'fas fa-arrow-right',
		'library' => 'fa-solid',
	];

	private const DESC_TAGS = [
		'a'      => [ 'href' => true, 'target' => true, 'rel' => true ],
		'b'      => [],
		'strong' => [],
		'em'     => [],
		'i'      => [],
		'span'   => [ 'class' => true ],
		'br'     => [],
	];

	public function get_name(): string {
		return 'sz-process';
	}

	public function get_title(): string {
		return esc_html__( 'Process Steps', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-time-line';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'process', 'steps', 'how we work', 'workflow', 'cards', 'timeline' ];
	}

	public function get_style_depends(): array {
		return [ 'sz-process' ];
	}

	/**
	 * Output depends only on the widget's own settings, so Elementor may cache
	 * it. If a dynamic tag is used, Elementor detects that by itself and skips
	 * the cache for that instance.
	 */
	protected function is_dynamic_content(): bool {
		return false;
	}

	/**
	 * Drop the extra .elementor-widget-container only when Elementor's
	 * optimized markup is on — the same rule Elementor's own widgets use —
	 * because only then does it move Advanced-tab styles onto the element.
	 */
	public function has_widget_inner_wrapper(): bool {
		return ! Elementor::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	/* =================================================================
	 * Controls
	 * ================================================================= */

	protected function register_controls(): void {
		$this->content_steps();
		$this->content_settings();
		$this->content_arrows();

		$this->style_layout();
		$this->style_card();
		$this->style_skins();
		$this->style_icon();
		$this->style_title();
		$this->style_description();
		$this->style_number();
		$this->style_arrows();
		$this->style_grid();
	}

	private function content_steps(): void {
		$this->start_controls_section( 'section_steps', [ 'label' => esc_html__( 'Steps', 'samanzavarian-widgets' ) ] );

		$repeater = new Repeater();

		$repeater->add_control(
			'icon',
			[
				'label'   => esc_html__( 'Icon', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::ICONS,
				'default' => Icon_Library::value( 'chat' ),
			]
		);

		$repeater->add_control(
			'title',
			[
				'label'       => esc_html__( 'Title', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Step', 'samanzavarian-widgets' ),
				'label_block' => true,
				'dynamic'     => [ 'active' => true ],
			]
		);

		$repeater->add_control(
			'description',
			[
				'label'   => esc_html__( 'Description', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::TEXTAREA,
				'rows'    => 3,
				'dynamic' => [ 'active' => true ],
			]
		);

		$repeater->add_control(
			'skin',
			[
				'label'   => esc_html__( 'Card style', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					''      => esc_html__( 'Auto (alternate)', 'samanzavarian-widgets' ),
					'light' => esc_html__( 'Light', 'samanzavarian-widgets' ),
					'dark'  => esc_html__( 'Dark', 'samanzavarian-widgets' ),
				],
				'description' => esc_html__( 'Auto follows the pattern set under Settings.', 'samanzavarian-widgets' ),
			]
		);

		$repeater->add_control(
			'link',
			[
				'label'       => esc_html__( 'Link', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::URL,
				'dynamic'     => [ 'active' => true ],
				'description' => esc_html__( 'Optional. Makes the whole card a link.', 'samanzavarian-widgets' ),
			]
		);

		$repeater->add_control(
			'arrow',
			[
				'label'   => esc_html__( 'Arrow to the next step', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					''     => esc_html__( 'Default', 'samanzavarian-widgets' ),
					'icon' => esc_html__( 'Own icon', 'samanzavarian-widgets' ),
					'none' => esc_html__( 'None', 'samanzavarian-widgets' ),
				],
				'separator'   => 'before',
				'description' => esc_html__( 'Default uses the Arrows section. Ignored on the last step.', 'samanzavarian-widgets' ),
			]
		);

		$repeater->add_control(
			'arrow_icon',
			[
				'label'     => esc_html__( 'Icon above the cards', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::ICONS,
				'default'   => self::ARROW_ICON_DEFAULT,
				'condition' => [ 'arrow' => 'icon' ],
			]
		);

		$repeater->add_control(
			'arrow_icon_under',
			[
				'label'       => esc_html__( 'Icon below the cards', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::ICONS,
				'description' => esc_html__( 'Optional. Empty uses the icon above.', 'samanzavarian-widgets' ),
				'condition'   => [ 'arrow' => 'icon' ],
			]
		);

		$this->add_control(
			'steps',
			[
				'label'       => esc_html__( 'Steps', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ title }}}',
				'default'     => [
					[
						'icon'        => Icon_Library::value( 'chat' ),
						'title'       => esc_html__( 'Brief', 'samanzavarian-widgets' ),
						'description' => esc_html__( 'A short call about the goal and the audience.', 'samanzavarian-widgets' ),
					],
					[
						'icon'        => Icon_Library::value( 'doc' ),
						'title'       => esc_html__( 'Direction', 'samanzavarian-widgets' ),
						'description' => esc_html__( 'Concept, references, a fixed quote and dates. You approve it.', 'samanzavarian-widgets' ),
					],
					[
						'icon'        => Icon_Library::value( 'spark' ),
						'title'       => esc_html__( 'Production', 'samanzavarian-widgets' ),
						'description' => esc_html__( 'AI, editing or both, with one review round on the first cut.', 'samanzavarian-widgets' ),
					],
					[
						'icon'        => Icon_Library::value( 'check' ),
						'title'       => esc_html__( 'Delivery', 'samanzavarian-widgets' ),
						'description' => esc_html__( 'Final files in every size each channel needs.', 'samanzavarian-widgets' ),
					],
				],
			]
		);

		$this->end_controls_section();
	}

	private function content_settings(): void {
		$this->start_controls_section( 'section_settings', [ 'label' => esc_html__( 'Settings', 'samanzavarian-widgets' ) ] );

		$columns = [];
		$layouts = [];

		foreach ( Process_Layout::supported_columns() as $cols ) {
			$columns[ (string) $cols ] = (string) $cols;
			$layouts[ (string) $cols ] = Process_Layout::vars( $cols );
		}

		$this->add_responsive_control(
			'columns',
			[
				'label'                => esc_html__( 'Columns', 'samanzavarian-widgets' ),
				'type'                 => Controls_Manager::SELECT,
				'options'              => $columns,
				'default'              => '4',
				'tablet_default'       => '2',
				'mobile_default'       => '1',
				'description'          => esc_html__( 'Follows your Elementor breakpoints. Tilt, stagger and arrows adapt to any column count; in one column the arrows point down.', 'samanzavarian-widgets' ),
				'selectors_dictionary' => $layouts,
				'selectors'            => [ '{{WRAPPER}} .sz-process' => '{{VALUE}}' ],
			]
		);

		$this->add_control(
			'skin_pattern',
			[
				'label'   => esc_html__( 'Auto card style', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'light',
				'options' => [
					'light' => esc_html__( 'Light first, then dark', 'samanzavarian-widgets' ),
					'dark'  => esc_html__( 'Dark first, then light', 'samanzavarian-widgets' ),
				],
				'separator' => 'before',
			]
		);

		$this->add_control(
			'title_tag',
			[
				'label'   => esc_html__( 'Title HTML tag', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'h3',
				'options' => [
					'h2'   => 'H2',
					'h3'   => 'H3',
					'h4'   => 'H4',
					'h5'   => 'H5',
					'h6'   => 'H6',
					'div'  => 'div',
					'p'    => 'p',
					'span' => 'span',
				],
			]
		);

		$this->add_control(
			'show_number',
			[
				'label'        => esc_html__( 'Step number', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'separator'    => 'before',
			]
		);

		$this->add_control(
			'number_start',
			[
				'label'     => esc_html__( 'Start at', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 1,
				'min'       => 0,
				'step'      => 1,
				'condition' => [ 'show_number' => 'yes' ],
			]
		);

		$this->add_control(
			'number_pad',
			[
				'label'        => esc_html__( 'Leading zero (01, 02…)', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '',
				'return_value' => 'yes',
				'condition'    => [ 'show_number' => 'yes' ],
			]
		);

		$this->end_controls_section();
	}

	private function content_arrows(): void {
		$this->start_controls_section( 'section_arrows', [ 'label' => esc_html__( 'Arrows', 'samanzavarian-widgets' ) ] );

		$this->add_responsive_control(
			'arrows',
			[
				'label'   => esc_html__( 'Show arrows', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'options' => [
					'show' => esc_html__( 'Show', 'samanzavarian-widgets' ),
					'hide' => esc_html__( 'Hide', 'samanzavarian-widgets' ),
				],
				'default'              => 'show',
				'description'          => esc_html__( 'Arrows never point past the end of a row.', 'samanzavarian-widgets' ),
				'selectors_dictionary' => [
					'show' => '--szp-arrows:1',
					'hide' => '--szp-arrows:0',
				],
				'selectors' => [ '{{WRAPPER}} .sz-process' => '{{VALUE}}' ],
			]
		);

		$this->add_control(
			'arrow_type',
			[
				'label'   => esc_html__( 'Arrow', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'drawn',
				'options' => [
					'drawn' => esc_html__( 'Hand-drawn (design)', 'samanzavarian-widgets' ),
					'icon'  => esc_html__( 'Icon', 'samanzavarian-widgets' ),
				],
			]
		);

		$this->add_control(
			'arrow_icon',
			[
				'label'       => esc_html__( 'Icon above the cards', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::ICONS,
				'default'     => self::ARROW_ICON_DEFAULT,
				'description' => esc_html__( 'Pick icons that point right; they are turned to point down in one column and mirrored on right-to-left sites. Each step can override them.', 'samanzavarian-widgets' ),
				'condition'   => [ 'arrow_type' => 'icon' ],
			]
		);

		$this->add_control(
			'arrow_icon_under',
			[
				'label'       => esc_html__( 'Icon below the cards', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::ICONS,
				'description' => esc_html__( 'Optional. Like the design, arrows alternate above and below the cards (in one column: odd and even steps). Empty uses the icon above.', 'samanzavarian-widgets' ),
				'condition'   => [ 'arrow_type' => 'icon' ],
			]
		);

		$this->end_controls_section();
	}

	private function style_layout(): void {
		$this->start_controls_section(
			'style_layout',
			[
				'label' => esc_html__( 'Layout', 'samanzavarian-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'gap_x',
			[
				'label'       => esc_html__( 'Column gap', 'samanzavarian-widgets' ),
				'placeholder'    => Control_Helpers::ph( 56 ),
				'tablet_default' => Control_Helpers::ph( 32 ),
				'type'           => Controls_Manager::SLIDER,
				'size_units'     => [ 'px', 'em', 'vw', 'custom' ],
				'range'          => [ 'px' => [ 'min' => 0, 'max' => 120 ] ],
				'description'    => esc_html__( 'Desktop design: grows from 28 to 56px with the screen. Arrows stay centred in the gap.', 'samanzavarian-widgets' ),
				'selectors'   => [ '{{WRAPPER}} .sz-process' => '--szp-gap-x: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'gap_y',
			[
				'label'       => esc_html__( 'Row gap', 'samanzavarian-widgets' ),
				'placeholder'    => Control_Helpers::ph( 56 ),
				'tablet_default' => Control_Helpers::ph( 18 ),
				'mobile_default' => Control_Helpers::ph( 40 ),
				'type'           => Controls_Manager::SLIDER,
				'size_units'     => [ 'px', 'em', 'vw', 'custom' ],
				'range'          => [ 'px' => [ 'min' => 0, 'max' => 120 ] ],
				'description'    => esc_html__( 'Space between rows when cards wrap. In one column the arrows sit in this gap.', 'samanzavarian-widgets' ),
				'selectors'   => [ '{{WRAPPER}} .sz-process' => '--szp-gap-y: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'tilt',
			[
				'label'       => esc_html__( 'Tilt intensity', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 1, '' ),
				'type'        => Controls_Manager::SLIDER,
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 3, 'step' => 0.1 ] ],
				'description' => esc_html__( '1 matches the design, 0 keeps every card straight.', 'samanzavarian-widgets' ),
				'selectors'   => [ '{{WRAPPER}} .sz-process' => '--szp-tilt: {{SIZE}};' ],
			]
		);

		$this->add_responsive_control(
			'stagger',
			[
				'label'       => esc_html__( 'Vertical stagger', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 40 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', 'em', 'custom' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 160 ] ],
				'description' => esc_html__( 'How far alternate cards drop. 0 lines them up.', 'samanzavarian-widgets' ),
				'selectors'   => [ '{{WRAPPER}} .sz-process' => '--szp-stagger: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->end_controls_section();
	}

	private function style_card(): void {
		$this->start_controls_section(
			'style_card',
			[
				'label' => esc_html__( 'Card', 'samanzavarian-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'card_padding',
			[
				'label'       => esc_html__( 'Padding', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph_box( 22, 22, 26, 22 ),
				'type'        => Controls_Manager::DIMENSIONS,
				'size_units'  => [ 'px', 'em', '%', 'custom' ],
				'selectors'   => [ '{{WRAPPER}} .sz-process__card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'card_min_height',
			[
				'label'       => esc_html__( 'Minimum height', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 176 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', 'em', 'vh', 'custom' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 500 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-process__card' => 'min-height: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'card_radius',
			[
				'label'       => esc_html__( 'Border radius', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph_box( 26, 26, 26, 26 ),
				'type'        => Controls_Manager::DIMENSIONS,
				'size_units'  => [ 'px', '%', 'em', 'custom' ],
				'selectors'   => [ '{{WRAPPER}} .sz-process__card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'card_border',
				'selector' => '{{WRAPPER}} .sz-process__card',
			]
		);

		$this->start_controls_tabs( 'card_state' );

		$this->start_controls_tab( 'card_normal', [ 'label' => esc_html__( 'Normal', 'samanzavarian-widgets' ) ] );

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'card_shadow',
				'selector' => '{{WRAPPER}} .sz-process__card',
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'card_hover', [ 'label' => esc_html__( 'Hover', 'samanzavarian-widgets' ) ] );

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'card_shadow_hover',
				'selector' => '{{WRAPPER}} .sz-process__card:hover, {{WRAPPER}} a.sz-process__card:focus-visible',
			]
		);

		$this->add_control(
			'hover_lift',
			[
				'label'       => esc_html__( 'Lift', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 4 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px' ],
				'range'       => [ 'px' => [ 'min' => -20, 'max' => 40 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-process' => '--szp-lift: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_control(
			'hover_keep_tilt',
			[
				'label'        => esc_html__( 'Keep tilt on hover', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'description'  => esc_html__( 'Off (the design) straightens the card when hovered.', 'samanzavarian-widgets' ),
				'selectors'    => [ '{{WRAPPER}} .sz-process' => '--szp-keep-tilt: 1;' ],
			]
		);

		$this->add_control(
			'hover_duration',
			[
				'label'       => esc_html__( 'Animation duration', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 500, 'ms' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'ms' ],
				'range'       => [ 'ms' => [ 'min' => 0, 'max' => 2000, 'step' => 50 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-process' => '--szp-dur: {{SIZE}}ms;' ],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	private function style_skins(): void {
		$this->start_controls_section(
			'style_skins',
			[
				'label' => esc_html__( 'Light & dark cards', 'samanzavarian-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->start_controls_tabs( 'skins' );

		foreach ( [
			'light' => esc_html__( 'Light', 'samanzavarian-widgets' ),
			'dark'  => esc_html__( 'Dark', 'samanzavarian-widgets' ),
		] as $skin => $label ) {
			$item = '{{WRAPPER}} .sz-process__item--' . $skin;

			$this->start_controls_tab( 'skin_' . $skin, [ 'label' => $label ] );

			$this->add_group_control(
				Group_Control_Background::get_type(),
				[
					'name'           => $skin . '_card_bg',
					'types'          => [ 'classic', 'gradient' ],
					'selector'       => $item . ' .sz-process__card',
					'fields_options' => Control_Helpers::clears_design_gradient( '--szp-card-bg' ),
				]
			);

			$this->add_control(
				$skin . '_title_color',
				[
					'label'     => esc_html__( 'Title color', 'samanzavarian-widgets' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => [ $item . ' .sz-process__title' => 'color: {{VALUE}};' ],
				]
			);

			$this->add_control(
				$skin . '_desc_color',
				[
					'label'     => esc_html__( 'Description color', 'samanzavarian-widgets' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => [ $item . ' .sz-process__desc' => 'color: {{VALUE}};' ],
				]
			);

			$this->add_control(
				$skin . '_num_color',
				[
					'label'     => esc_html__( 'Number color', 'samanzavarian-widgets' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => [ $item . ' .sz-process__num' => 'color: {{VALUE}};' ],
				]
			);

			$this->add_control(
				$skin . '_icon_heading',
				[
					'label'     => esc_html__( 'Icon', 'samanzavarian-widgets' ),
					'type'      => Controls_Manager::HEADING,
					'separator' => 'before',
				]
			);

			$this->add_control(
				$skin . '_icon_color',
				[
					'label'     => esc_html__( 'Icon color', 'samanzavarian-widgets' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => [ $item . ' .sz-process__icon' => 'color: {{VALUE}};' ],
				]
			);

			$this->add_group_control(
				Group_Control_Background::get_type(),
				[
					'name'           => $skin . '_icon_bg',
					'types'          => [ 'classic', 'gradient' ],
					'exclude'        => [ 'image' ],
					'selector'       => $item . ' .sz-process__icon',
					'fields_options' => Control_Helpers::clears_design_gradient( '--sz-app-bg' ),
				]
			);

			$this->add_group_control(
				Group_Control_Box_Shadow::get_type(),
				[
					'name'     => $skin . '_icon_shadow',
					'selector' => $item . ' .sz-process__icon',
				]
			);

			$this->end_controls_tab();
		}

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	private function style_icon(): void {
		$this->start_controls_section(
			'style_icon',
			[
				'label' => esc_html__( 'Icon', 'samanzavarian-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'icon_box_size',
			[
				'label'       => esc_html__( 'Tile size', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 40 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', 'em', 'custom' ],
				'range'       => [ 'px' => [ 'min' => 16, 'max' => 120 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-process__icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'icon_size',
			[
				'label'       => esc_html__( 'Icon size', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 19 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', 'em', 'custom' ],
				'range'       => [ 'px' => [ 'min' => 8, 'max' => 80 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-process__icon' => 'font-size: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'icon_radius',
			[
				'label'       => esc_html__( 'Tile radius', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 12 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', '%', 'custom' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-process__icon' => 'border-radius: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_control(
			'icon_stroke',
			[
				'label'       => esc_html__( 'Line weight', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 2, '' ),
				'type'        => Controls_Manager::SLIDER,
				'range'       => [ 'px' => [ 'min' => 0.5, 'max' => 3, 'step' => 0.1 ] ],
				'description' => esc_html__( 'Applies to the design icon set (the "Saman Zavarian" tab in the icon picker).', 'samanzavarian-widgets' ),
				'selectors'   => [ '{{WRAPPER}} .sz-process__icon svg.szi' => 'stroke-width: {{SIZE}};' ],
			]
		);

		$this->end_controls_section();
	}

	private function style_title(): void {
		$this->start_controls_section(
			'style_title',
			[
				'label' => esc_html__( 'Title', 'samanzavarian-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'title_typography',
				'selector' => '{{WRAPPER}} .sz-process__title',
			]
		);

		$this->add_responsive_control(
			'title_spacing',
			[
				'label'       => esc_html__( 'Space above', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 16 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', 'em', 'custom' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 80 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-process__title' => 'margin-top: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->end_controls_section();
	}

	private function style_description(): void {
		$this->start_controls_section(
			'style_desc',
			[
				'label' => esc_html__( 'Description', 'samanzavarian-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'desc_typography',
				'selector' => '{{WRAPPER}} .sz-process__desc',
			]
		);

		$this->add_responsive_control(
			'desc_spacing',
			[
				'label'       => esc_html__( 'Space above', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 6 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', 'em', 'custom' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-process__desc' => 'margin-top: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'desc_max_width',
			[
				'label'       => esc_html__( 'Maximum width', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 24, 'ch' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'ch', 'px', '%', 'custom' ],
				'range'       => [
					'ch' => [ 'min' => 10, 'max' => 80 ],
					'px' => [ 'min' => 80, 'max' => 600 ],
				],
				'description' => esc_html__( 'The design keeps lines short (24 characters) so the step number shows beside the text.', 'samanzavarian-widgets' ),
				'selectors'   => [ '{{WRAPPER}} .sz-process__desc' => 'max-width: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->end_controls_section();
	}

	private function style_number(): void {
		$this->start_controls_section(
			'style_number',
			[
				'label'     => esc_html__( 'Step number', 'samanzavarian-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_number' => 'yes' ],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'num_typography',
				'selector' => '{{WRAPPER}} .sz-process__num',
			]
		);

		$this->add_responsive_control(
			'num_offset_x',
			[
				'label'       => esc_html__( 'Offset from the side', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 10 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', '%', 'custom' ],
				'range'       => [ 'px' => [ 'min' => -100, 'max' => 200 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-process__num' => 'inset-inline-end: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'num_offset_y',
			[
				'label'       => esc_html__( 'Offset from the bottom', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( -26 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', '%', 'custom' ],
				'range'       => [ 'px' => [ 'min' => -200, 'max' => 100 ] ],
				'description' => esc_html__( 'Negative values let the card edge cut the number, as in the design.', 'samanzavarian-widgets' ),
				'selectors'   => [ '{{WRAPPER}} .sz-process__num' => 'bottom: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->end_controls_section();
	}

	private function style_arrows(): void {
		$this->start_controls_section(
			'style_arrows',
			[
				'label' => esc_html__( 'Arrows', 'samanzavarian-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'arrow_color',
			[
				'label'     => esc_html__( 'Color', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .sz-process__arrow' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'arrow_stroke',
			[
				'label'       => esc_html__( 'Line weight', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 1.5, '' ),
				'type'        => Controls_Manager::SLIDER,
				'range'       => [ 'px' => [ 'min' => 0.5, 'max' => 4, 'step' => 0.1 ] ],
				'description' => esc_html__( 'For the hand-drawn arrow and line icons.', 'samanzavarian-widgets' ),
				'selectors'   => [ '{{WRAPPER}} svg.sz-process__arrow, {{WRAPPER}} .sz-process__arrow svg.szi' => 'stroke-width: {{SIZE}};' ],
			]
		);

		$this->add_responsive_control(
			'arrow_width',
			[
				'label'       => esc_html__( 'Size', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 54 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px' ],
				'range'       => [ 'px' => [ 'min' => 12, 'max' => 140 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-process' => '--szp-arrow-w: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'arrow_rotate',
			[
				'label'       => esc_html__( 'Rotate', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 0, 'deg' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'deg' ],
				'range'       => [ 'deg' => [ 'min' => -180, 'max' => 180 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-process' => '--szp-arrow-rot: {{SIZE}}deg;' ],
			]
		);

		$this->add_responsive_control(
			'arrow_offset_x',
			[
				'label'       => esc_html__( 'Offset along', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 0 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px' ],
				'range'       => [ 'px' => [ 'min' => -80, 'max' => 80 ] ],
				'description' => esc_html__( 'Positive moves toward the next card.', 'samanzavarian-widgets' ),
				'selectors'   => [ '{{WRAPPER}} .sz-process' => '--szp-arrow-dx: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'arrow_offset_y',
			[
				'label'       => esc_html__( 'Offset up/down', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 0 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px' ],
				'range'       => [ 'px' => [ 'min' => -80, 'max' => 80 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-process' => '--szp-arrow-dy: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->end_controls_section();
	}

	private function style_grid(): void {
		$this->start_controls_section(
			'style_grid',
			[
				'label' => esc_html__( 'Background grid', 'samanzavarian-widgets' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'show_grid',
			[
				'label'        => esc_html__( 'Show grid', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			]
		);

		$on = [ 'show_grid' => 'yes' ];

		$this->add_control(
			'grid_color',
			[
				'label'     => esc_html__( 'Line color', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => $on,
				'selectors' => [ '{{WRAPPER}} .sz-process' => '--szp-grid-color: {{VALUE}};' ],
			]
		);

		$this->add_responsive_control(
			'grid_size',
			[
				'label'       => esc_html__( 'Cell size', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 96 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px' ],
				'range'       => [ 'px' => [ 'min' => 8, 'max' => 240 ] ],
				'condition'   => $on,
				'selectors'   => [ '{{WRAPPER}} .sz-process' => '--szp-grid-size: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_control(
			'grid_line',
			[
				'label'       => esc_html__( 'Line weight', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 1 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px' ],
				'range'       => [ 'px' => [ 'min' => 1, 'max' => 6 ] ],
				'condition'   => $on,
				'selectors'   => [ '{{WRAPPER}} .sz-process' => '--szp-grid-line: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'grid_extend_x',
			[
				'label'          => esc_html__( 'Extend sideways', 'samanzavarian-widgets' ),
				'placeholder'    => Control_Helpers::ph( 20 ),
				'mobile_default' => Control_Helpers::ph( 0 ),
				'description'    => esc_html__( 'Off on mobile by default: on a narrow screen the lines past the edge would make the page scroll sideways.', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 300 ] ],
				'condition'   => $on,
				'selectors'   => [ '{{WRAPPER}} .sz-process' => '--szp-grid-x: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'grid_extend_y',
			[
				'label'       => esc_html__( 'Extend up and down', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 40 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 300 ] ],
				'condition'   => $on,
				'selectors'   => [ '{{WRAPPER}} .sz-process' => '--szp-grid-y: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_control(
			'grid_fade',
			[
				'label'       => esc_html__( 'Solid area before fading', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 30, '%' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ '%' ],
				'range'       => [ '%' => [ 'min' => 0, 'max' => 100 ] ],
				'description' => esc_html__( '100% shows the grid edge to edge with no fade.', 'samanzavarian-widgets' ),
				'condition'   => $on,
				'selectors'   => [ '{{WRAPPER}} .sz-process' => '--szp-grid-fade: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->end_controls_section();
	}



	/* =================================================================
	 * Render
	 * ================================================================= */

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$steps    = isset( $settings['steps'] ) && is_array( $settings['steps'] ) ? array_values( $settings['steps'] ) : [];

		if ( ! $steps ) {
			return;
		}

		$tag        = Utils::validate_html_tag( $settings['title_tag'] ?? 'h3' );
		$total      = count( $steps );
		$show_num   = 'yes' === ( $settings['show_number'] ?? 'yes' );
		$start      = isset( $settings['number_start'] ) && '' !== $settings['number_start'] ? (int) $settings['number_start'] : 1;
		$pad        = 'yes' === ( $settings['number_pad'] ?? '' );
		$dark_first = 'dark' === ( $settings['skin_pattern'] ?? 'light' );
		$arrow      = 'icon' === ( $settings['arrow_type'] ?? 'drawn' )
			? [ $settings['arrow_icon'] ?? self::ARROW_ICON_DEFAULT, $settings['arrow_icon_under'] ?? null ]
			: null;

		$this->add_render_attribute(
			'board',
			[
				'class' => [ 'sz-process', 'sz-w' ],
				// list-style:none makes Safari/VoiceOver drop list semantics; an explicit role restores them.
				'role' => 'list',
			]
		);

		if ( 'yes' !== ( $settings['show_grid'] ?? 'yes' ) ) {
			$this->add_render_attribute( 'board', 'class', 'sz-process--no-grid' );
		}

		if ( 1 !== $start ) {
			$this->add_render_attribute( 'board', 'start', (string) $start );
		}
		?>
		<ol <?php $this->print_render_attribute_string( 'board' ); ?>>
			<?php
			foreach ( $steps as $index => $step ) {
				$this->render_step( $step, $index, $index < $total - 1, $tag, $show_num ? self::number_label( $start + $index, $pad ) : null, $dark_first, $arrow );
			}
			?>
		</ol>
		<?php
	}

	/**
	 * @param array<string,mixed>      $step
	 * @param array<int,mixed>|null    $arrow the widget's arrow icons [above, below], or null for the drawn arrow
	 */
	private function render_step( array $step, int $index, bool $has_next, string $tag, ?string $number, bool $dark_first, ?array $arrow ): void {
		$skin = self::skin( $step['skin'] ?? '', $index, $dark_first );

		$item_classes = [ 'sz-process__item', 'sz-process__item--' . $skin ];

		if ( ! empty( $step['_id'] ) ) {
			$item_classes[] = 'elementor-repeater-item-' . $step['_id'];
		}

		$icon  = Icon_Library::html( $step['icon'] ?? [] );
		$title = trim( (string) ( $step['title'] ?? '' ) );
		$desc  = trim( (string) ( $step['description'] ?? '' ) );

		$card_tag = 'div';
		$card_key = 'card-' . $index;

		$this->add_render_attribute( $card_key, 'class', 'sz-process__card' );

		if ( ! empty( $step['link']['url'] ) ) {
			$card_tag = 'a';
			$this->add_link_attributes( $card_key, $step['link'] );
		}
		?>
		<li class="<?php echo esc_attr( implode( ' ', $item_classes ) ); ?>">
			<<?php echo $card_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal 'a' or 'div' ?> <?php $this->print_render_attribute_string( $card_key ); ?>>
				<?php if ( '' !== $icon ) : ?>
					<span class="sz-process__icon sz-app sz-app--<?php echo 'dark' === $skin ? 'orange' : 'white'; ?>" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from Elementor's icon renderer or our static glyph map ?></span>
				<?php endif; ?>

				<?php if ( '' !== $title ) : ?>
					<<?php echo esc_html( $tag ); ?> class="sz-process__title"><?php echo esc_html( $title ); ?></<?php echo esc_html( $tag ); ?>>
				<?php endif; ?>

				<?php if ( '' !== $desc ) : ?>
					<p class="sz-process__desc"><?php echo self::description_html( $desc ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- filtered through wp_kses ?></p>
				<?php endif; ?>

				<?php if ( null !== $number ) : ?>
					<span class="sz-process__num" aria-hidden="true"><?php echo esc_html( $number ); ?></span>
				<?php endif; ?>
			</<?php echo $card_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php
			if ( $has_next ) {
				self::render_arrow( $step, $arrow );
			}
			?>
		</li>
		<?php
	}

	/**
	 * The arrow out of one step: the step's own choice first, then the
	 * widget's. An icon that renders nothing falls back to the drawn arrow
	 * rather than leaving a gap in the sequence.
	 *
	 * Which side of the cards an arrow sits on depends on the column count,
	 * which can differ per breakpoint, so with two different icons both are
	 * printed and the stylesheet shows one — exactly like the two paths of
	 * the drawn arrow.
	 *
	 * @param array<string,mixed>   $step
	 * @param array<int,mixed>|null $widget_icons [above, below]
	 */
	private static function render_arrow( array $step, ?array $widget_icons ): void {
		$choice = $step['arrow'] ?? '';

		if ( 'none' === $choice ) {
			return;
		}

		$icons = 'icon' === $choice ? [ $step['arrow_icon'] ?? null, $step['arrow_icon_under'] ?? null ] : $widget_icons;
		$over  = $icons && is_array( $icons[0] ) ? Icon_Library::html( $icons[0] ) : '';

		if ( '' !== $over ) {
			$under = is_array( $icons[1] ?? null ) ? Icon_Library::html( $icons[1] ) : '';
			$inner = '' === $under || $under === $over
				? $over
				: '<span class="sz-process__arrow-over">' . $over . '</span><span class="sz-process__arrow-under">' . $under . '</span>';

			echo '<span class="sz-process__arrow sz-process__arrow--icon" aria-hidden="true">' . $inner . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from Elementor's icon renderer or our static glyph map
			return;
		}
		?>
		<svg class="sz-process__arrow" viewBox="0 0 54 30" aria-hidden="true" focusable="false">
			<path class="sz-process__arrow-over" d="<?php echo esc_attr( self::ARROW_OVER ); ?>"/>
			<path class="sz-process__arrow-under" d="<?php echo esc_attr( self::ARROW_UNDER ); ?>"/>
		</svg>
		<?php
	}

	/** Explicit choice wins; otherwise alternate, starting light (as designed) or dark. */
	public static function skin( $choice, int $index, bool $dark_first = false ): string {
		if ( 'light' === $choice || 'dark' === $choice ) {
			return $choice;
		}

		return ( 1 === $index % 2 ) !== $dark_first ? 'dark' : 'light';
	}

	/**
	 * Line breaks become <br>, a few inline tags survive, everything else goes.
	 *
	 * wp_kses() drops a disallowed tag but keeps its text, so `<script>x()</script>`
	 * would still print "x()". Script and style blocks are removed whole first —
	 * the same pattern WordPress uses in wp_strip_all_tags().
	 */
	public static function description_html( string $text ): string {
		$text = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );

		return wp_kses( nl2br( trim( $text ), false ), self::DESC_TAGS );
	}

	public static function number_label( int $number, bool $pad ): string {
		return $pad && $number >= 0 && $number < 10 ? '0' . $number : (string) $number;
	}
}
