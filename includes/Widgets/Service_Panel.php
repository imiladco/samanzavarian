<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Plugin as Elementor;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Samanzavarian_Widgets\Icon_Library;
use Samanzavarian_Widgets\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * One service, shown as the design's panel: a toned background, an
 * illustrated scene, and a frosted card with the pitch, what you get and a
 * call to action.
 *
 * Service Tabs puts one in each tab by default, but nothing ties the two
 * together: the panel works on its own anywhere, and a tab works with any
 * content. The only contract is a class — inside an inactive tab
 * (.sz-tabs__panel:not(.is-active)) the scene waits, and it plays when its
 * tab opens.
 */
class Service_Panel extends Widget_Base {

	public const SCENES = [ 'ai', 'direction', 'post', 'content' ];

	public function get_name(): string {
		return 'sz-service-panel';
	}

	public function get_title(): string {
		return esc_html__( 'Service Panel', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-info-box';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'service', 'panel', 'scene', 'card', 'what i do' ];
	}

	public function get_style_depends(): array {
		return [ 'sz-service-panel' ];
	}

	public function get_script_depends(): array {
		return [ 'sz-service-panel' ];
	}

	protected function is_dynamic_content(): bool {
		return false;
	}

	public function has_widget_inner_wrapper(): bool {
		return ! Elementor::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	/**
	 * The design's four panels as settings. Used for the containers Service
	 * Tabs creates, so a fresh widget is the design exactly.
	 *
	 * @return array<string,mixed>
	 */
	public static function preset( string $name ): array {
		$presets = [
			'ai'        => [ 'dark', 'A photographer\'s eye for light, lens and framing, applied to AI images and video and finished to commercial standard.', [ 'Product key visuals', 'Campaign imagery', 'Short AI video' ], 'Ask about AI visuals', 'AI visuals' ],
			'direction' => [ 'peach', 'Concepts, references and a visual language your team can follow, agreed before a single frame is made.', [ 'Concept', 'Moodboard', 'Art direction' ], 'Ask about direction', 'Creative direction' ],
			'post'      => [ 'cool', 'Your footage and images edited, retouched, graded and animated, then delivered ready to publish.', [ 'Video editing', 'Retouching', 'Motion graphics' ], 'Ask about post', 'Post-production' ],
			'content'   => [ 'grid', 'A content plan, repeatable formats and scripts, so your team can publish every week without starting from zero.', [ 'Content strategy', 'Social templates', 'Scripting' ], 'Ask about content', 'Content systems' ],
		];

		$p    = $presets[ $name ] ?? $presets['ai'];
		$gets = [];

		foreach ( $p[2] as $i => $text ) {
			// No _id: Elementor issues unique ones (and the editor copy of a
			// Service Tabs default does too); a fixed one would repeat per panel.
			$gets[] = [
				'text' => $text,
				'icon' => Icon_Library::value( 'tick' ),
			];
		}

		return [
			'scene'          => isset( $presets[ $name ] ) ? $name : 'ai',
			'tone'           => $p[0],
			'text'           => $p[1],
			'gets'           => $gets,
			'button_text'    => $p[3],
			'button_link'    => [ 'url' => '#contact', 'is_external' => '', 'nofollow' => '' ],
			'button_prefill' => $p[4],
		];
	}

	/* =================================================================
	 * Controls
	 * ================================================================= */

	protected function register_controls(): void {
		$ai = self::preset( 'ai' );

		$this->start_controls_section( 'section_visual', [ 'label' => esc_html__( 'Visual', 'samanzavarian-widgets' ) ] );

		$this->add_control(
			'scene',
			[
				'label'   => esc_html__( 'Show', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'ai',
				'options' => [
					'ai'        => esc_html__( 'Scene: AI production', 'samanzavarian-widgets' ),
					'direction' => esc_html__( 'Scene: Creative direction', 'samanzavarian-widgets' ),
					'post'      => esc_html__( 'Scene: Post-production', 'samanzavarian-widgets' ),
					'content'   => esc_html__( 'Scene: Content calendar', 'samanzavarian-widgets' ),
					'media'     => esc_html__( 'Image or video', 'samanzavarian-widgets' ),
					'none'      => esc_html__( 'Nothing', 'samanzavarian-widgets' ),
				],
			]
		);

		$this->add_control(
			'media',
			[
				'label'       => esc_html__( 'Image or video', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::MEDIA,
				'media_types' => [ 'image', 'video' ],
				'description' => esc_html__( 'Videos play muted and looped, and only while visible.', 'samanzavarian-widgets' ),
				'condition'   => [ 'scene' => 'media' ],
			]
		);

		$this->add_control(
			'media_fit',
			[
				'label'     => esc_html__( 'Fit', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'cover',
				'options'   => [
					'cover'   => esc_html__( 'Fill (crop)', 'samanzavarian-widgets' ),
					'contain' => esc_html__( 'Whole (no crop)', 'samanzavarian-widgets' ),
				],
				'condition' => [ 'scene' => 'media' ],
				'selectors' => [ '{{WRAPPER}} .sz-svc__media' => 'object-fit: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'tone',
			[
				'label'     => esc_html__( 'Background', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'dark',
				'options'   => [
					'dark'  => esc_html__( 'Navy glow', 'samanzavarian-widgets' ),
					'peach' => esc_html__( 'Peach', 'samanzavarian-widgets' ),
					'cool'  => esc_html__( 'Cool', 'samanzavarian-widgets' ),
					'grid'  => esc_html__( 'Graph paper', 'samanzavarian-widgets' ),
					'none'  => esc_html__( 'None (set your own in Style)', 'samanzavarian-widgets' ),
				],
				'separator' => 'before',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section( 'section_info', [ 'label' => esc_html__( 'Card', 'samanzavarian-widgets' ) ] );

		$this->add_control(
			'show_info',
			[
				'label'        => esc_html__( 'Show card', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			]
		);

		$on = [ 'show_info' => 'yes' ];

		$this->add_control(
			'text',
			[
				'label'     => esc_html__( 'Text', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::TEXTAREA,
				'rows'      => 4,
				'default'   => $ai['text'],
				'dynamic'   => [ 'active' => true ],
				'condition' => $on,
			]
		);

		$repeater = new Repeater();
		$repeater->add_control(
			'text',
			[
				'label'   => esc_html__( 'Text', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'Deliverable', 'samanzavarian-widgets' ),
				'dynamic' => [ 'active' => true ],
			]
		);
		$repeater->add_control(
			'icon',
			[
				'label'   => esc_html__( 'Icon', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::ICONS,
				'default' => Icon_Library::value( 'tick' ),
			]
		);

		$this->add_control(
			'gets',
			[
				'label'       => esc_html__( 'What you get', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => $ai['gets'],
				'title_field' => '{{{ text }}}',
				'condition'   => $on,
			]
		);

		$this->add_control(
			'button_text',
			[
				'label'     => esc_html__( 'Button', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => $ai['button_text'],
				'dynamic'   => [ 'active' => true ],
				'separator' => 'before',
				'condition' => $on,
			]
		);

		$this->add_control(
			'button_link',
			[
				'label'     => esc_html__( 'Link', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::URL,
				'default'   => $ai['button_link'],
				'dynamic'   => [ 'active' => true ],
				'condition' => $on,
			]
		);

		$this->add_control(
			'button_prefill',
			[
				'label'       => esc_html__( 'Pre-select in contact form', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => $ai['button_prefill'],
				'description' => esc_html__( 'The option the contact form widget ticks when this button is clicked. Leave empty to only follow the link.', 'samanzavarian-widgets' ),
				'condition'   => $on,
				'ai'          => [ 'active' => false ],
			]
		);

		$this->end_controls_section();

		$this->style_panel();
		$this->style_card();
		$this->style_chips();
		$this->style_button();
	}

	private function style_panel(): void {
		$this->start_controls_section( 'style_panel', [ 'label' => esc_html__( 'Panel', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name'           => 'panel_bg',
				'types'          => [ 'classic', 'gradient' ],
				'selector'       => '{{WRAPPER}} .sz-svc',
				'fields_options' => Control_Helpers::clears_design_gradient( '--szs-tone' ),
			]
		);

		$this->add_responsive_control(
			'art_min',
			[
				'label'          => esc_html__( 'Scene height', 'samanzavarian-widgets' ),
				'placeholder'    => Control_Helpers::ph( 320 ),
				'tablet_default' => Control_Helpers::ph( 300 ),
				'mobile_default' => Control_Helpers::ph( 270 ),
				'type'           => Controls_Manager::SLIDER,
				'size_units'     => [ 'px', 'vh', 'custom' ],
				'range'          => [ 'px' => [ 'min' => 0, 'max' => 800 ] ],
				'description'    => esc_html__( 'The least room the scene gets; in a tall panel it grows to fill.', 'samanzavarian-widgets' ),
				'selectors'      => [ '{{WRAPPER}} .sz-svc__art' => 'min-height: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'panel_radius',
			[
				'label'       => esc_html__( 'Radius (on its own)', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 30 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', '%', 'custom' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
				'description' => esc_html__( 'Inside Service Tabs the panel takes the tab\'s frame instead.', 'samanzavarian-widgets' ),
				'selectors'   => [ '{{WRAPPER}} .sz-svc' => 'border-radius: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->end_controls_section();
	}

	private function style_card(): void {
		$this->start_controls_section(
			'style_card',
			[
				'label'     => esc_html__( 'Card', 'samanzavarian-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_info' => 'yes' ],
			]
		);

		$this->add_responsive_control(
			'card_layout',
			[
				'label'                => esc_html__( 'Button position', 'samanzavarian-widgets' ),
				'type'                 => Controls_Manager::SELECT,
				'options'              => [
					'row'   => esc_html__( 'Beside the list', 'samanzavarian-widgets' ),
					'stack' => esc_html__( 'Below the list', 'samanzavarian-widgets' ),
				],
				'default'              => 'row',
				'tablet_default'       => 'stack',
				'mobile_default'       => 'stack',
				'selectors_dictionary' => [
					'row'   => '--szs-card-cols:1fr auto;--szs-btn-self:auto',
					'stack' => '--szs-card-cols:1fr;--szs-btn-self:start',
				],
				'selectors'            => [ '{{WRAPPER}} .sz-svc' => '{{VALUE}}' ],
			]
		);

		$this->add_control(
			'card_bg',
			[
				'label'     => esc_html__( 'Background', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .sz-svc__card' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'card_blur',
			[
				'label'       => esc_html__( 'Background blur', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 22 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-svc__card' => '-webkit-backdrop-filter: blur({{SIZE}}px) saturate(170%); backdrop-filter: blur({{SIZE}}px) saturate(170%);' ],
			]
		);

		$this->add_responsive_control(
			'card_margin',
			[
				'label'       => esc_html__( 'Distance from the edges', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph_box( 0, 14, 14, 14 ),
				'type'        => Controls_Manager::DIMENSIONS,
				'size_units'  => [ 'px', 'custom' ],
				'selectors'   => [ '{{WRAPPER}} .sz-svc__card' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'card_padding',
			[
				'label'       => esc_html__( 'Padding', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph_box( 20, 20, 18, 20 ),
				'type'        => Controls_Manager::DIMENSIONS,
				'size_units'  => [ 'px', 'em', 'custom' ],
				'selectors'   => [ '{{WRAPPER}} .sz-svc__card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'card_radius',
			[
				'label'       => esc_html__( 'Radius', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 22 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', '%', 'custom' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-svc__card' => 'border-radius: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_group_control( Group_Control_Box_Shadow::get_type(), [ 'name' => 'card_shadow', 'selector' => '{{WRAPPER}} .sz-svc__card' ] );

		$this->add_control(
			'text_heading',
			[
				'label'     => esc_html__( 'Text', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'text_typo', 'selector' => '{{WRAPPER}} .sz-svc__text' ] );

		$this->add_control(
			'text_color',
			[
				'label'     => esc_html__( 'Color', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .sz-svc__text' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_responsive_control(
			'text_width',
			[
				'label'       => esc_html__( 'Maximum width', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 52, 'ch' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'ch', 'px', '%' ],
				'range'       => [ 'ch' => [ 'min' => 20, 'max' => 100 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-svc__text' => 'max-width: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->end_controls_section();
	}

	private function style_chips(): void {
		$this->start_controls_section(
			'style_chips',
			[
				'label'     => esc_html__( 'What you get', 'samanzavarian-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_info' => 'yes' ],
			]
		);

		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'chip_typo', 'selector' => '{{WRAPPER}} .sz-svc__get' ] );

		foreach ( [
			'chip_color'      => [ esc_html__( 'Text color', 'samanzavarian-widgets' ), '.sz-svc__get', 'color' ],
			'chip_bg'         => [ esc_html__( 'Background', 'samanzavarian-widgets' ), '.sz-svc__get', 'background-color' ],
			'chip_icon_color' => [ esc_html__( 'Icon color', 'samanzavarian-widgets' ), '.sz-svc__get-icon', 'color' ],
		] as $id => $def ) {
			$this->add_control(
				$id,
				[
					'label'     => $def[0],
					'type'      => Controls_Manager::COLOR,
					'selectors' => [ '{{WRAPPER}} ' . $def[1] => $def[2] . ': {{VALUE}};' ],
				]
			);
		}

		$this->add_responsive_control(
			'chip_height',
			[
				'label'       => esc_html__( 'Height', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 30 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px' ],
				'range'       => [ 'px' => [ 'min' => 18, 'max' => 60 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-svc__get' => 'height: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'chip_gap',
			[
				'label'       => esc_html__( 'Space between', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 6 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-svc__gets' => 'gap: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->end_controls_section();
	}

	private function style_button(): void {
		$this->start_controls_section(
			'style_button',
			[
				'label'     => esc_html__( 'Button', 'samanzavarian-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_info' => 'yes' ],
			]
		);

		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'btn_typo', 'selector' => '{{WRAPPER}} .sz-svc__btn' ] );

		$this->start_controls_tabs( 'btn_states' );

		foreach ( [
			'normal' => [ esc_html__( 'Normal', 'samanzavarian-widgets' ), '.sz-svc__btn' ],
			'hover'  => [ esc_html__( 'Hover', 'samanzavarian-widgets' ), '.sz-svc__btn:hover, {{WRAPPER}} .sz-svc__btn:focus-visible' ],
		] as $state => $def ) {
			$this->start_controls_tab( 'btn_' . $state, [ 'label' => $def[0] ] );

			$this->add_control(
				'btn_color_' . $state,
				[
					'label'     => esc_html__( 'Text color', 'samanzavarian-widgets' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => [ '{{WRAPPER}} ' . $def[1] => 'color: {{VALUE}};' ],
				]
			);

			$this->add_control(
				'btn_bg_' . $state,
				[
					'label'     => esc_html__( 'Background', 'samanzavarian-widgets' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => [ '{{WRAPPER}} ' . $def[1] => 'background-color: {{VALUE}};' ],
				]
			);

			$this->add_group_control( Group_Control_Box_Shadow::get_type(), [ 'name' => 'btn_shadow_' . $state, 'selector' => '{{WRAPPER}} ' . $def[1] ] );

			$this->end_controls_tab();
		}

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'btn_height',
			[
				'label'       => esc_html__( 'Height', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 44 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px' ],
				'range'       => [ 'px' => [ 'min' => 24, 'max' => 80 ] ],
				'separator'   => 'before',
				'selectors'   => [ '{{WRAPPER}} .sz-svc__btn' => 'height: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'btn_padding_x',
			[
				'label'       => esc_html__( 'Side padding', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 18 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-svc__btn' => 'padding-inline: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'btn_radius',
			[
				'label'       => esc_html__( 'Radius', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 99 ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', '%' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 99 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-svc__btn' => 'border-radius: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->end_controls_section();
	}

	/* =================================================================
	 * Render
	 * ================================================================= */

	protected function render(): void {
		$s     = $this->get_settings_for_display();
		$scene = (string) ( $s['scene'] ?? 'ai' );
		$tone  = in_array( $s['tone'] ?? '', [ 'dark', 'peach', 'cool', 'grid' ], true ) ? $s['tone'] : 'none';
		?>
		<div class="sz-svc sz-w sz-svc--<?php echo esc_attr( $tone ); ?>">
			<?php $this->render_visual( $scene, $s ); ?>
			<?php
			if ( 'yes' === ( $s['show_info'] ?? 'yes' ) ) {
				$this->render_card( $s );
			}
			?>
		</div>
		<?php
	}

	/** @param array<string,mixed> $s */
	private function render_visual( string $scene, array $s ): void {
		if ( 'none' === $scene ) {
			echo '<div class="sz-svc__art sz-svc__art--empty" aria-hidden="true"></div>';
			return;
		}

		if ( 'media' === $scene ) {
			echo '<div class="sz-svc__art" aria-hidden="true">' . self::media_html( $s['media'] ?? [], 'sz-svc__media' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in media_html()
			return;
		}

		$file = SZW_PATH . 'includes/Widgets/scenes/' . ( in_array( $scene, self::SCENES, true ) ? $scene : 'ai' ) . '.php';

		echo '<div class="sz-svc__art szs szs--' . esc_attr( $scene ) . '" aria-hidden="true">';
		include $file;
		echo '</div>';
	}

	/** @param array<string,mixed> $s */
	private function render_card( array $s ): void {
		$text = trim( (string) ( $s['text'] ?? '' ) );
		$gets = isset( $s['gets'] ) && is_array( $s['gets'] ) ? $s['gets'] : [];
		$btn  = trim( (string) ( $s['button_text'] ?? '' ) );
		?>
		<div class="sz-svc__card">
			<?php if ( '' !== $text ) : ?>
				<p class="sz-svc__text"><?php echo Process::description_html( $text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses ?></p>
			<?php endif; ?>

			<?php if ( $gets ) : ?>
				<ul class="sz-svc__gets">
					<?php foreach ( $gets as $get ) : ?>
						<li class="sz-svc__get<?php echo empty( $get['_id'] ) ? '' : ' elementor-repeater-item-' . esc_attr( (string) $get['_id'] ); ?>">
							<?php
							$icon = Icon_Library::html( $get['icon'] ?? [] );
							if ( '' !== $icon ) {
								echo '<span class="sz-svc__get-icon" aria-hidden="true">' . $icon . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon renderer
							}
							echo esc_html( (string) ( $get['text'] ?? '' ) );
							?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php
			if ( '' !== $btn ) {
				$this->add_render_attribute( 'btn', 'class', 'sz-svc__btn' );

				if ( ! empty( $s['button_link']['url'] ) ) {
					$this->add_link_attributes( 'btn', $s['button_link'] );
				}

				$prefill = trim( (string) ( $s['button_prefill'] ?? '' ) );
				if ( '' !== $prefill ) {
					$this->add_render_attribute( 'btn', 'data-sz-prefill', $prefill );
				}

				$tag = empty( $s['button_link']['url'] ) ? 'span' : 'a';
				echo '<' . $tag . ' ' . $this->get_render_attribute_string( 'btn' ) . '>' . esc_html( $btn ) . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal tag, escaped attributes
			}
			?>
		</div>
		<?php
	}

	/**
	 * An image or a muted, looping, inline video from a MEDIA control value.
	 * Videos carry data-sz-autoplay instead of autoplay: the panel script
	 * plays them only while they are on screen in an open tab.
	 *
	 * @param array<string,mixed> $media
	 */
	public static function media_html( $media, string $class ): string {
		$url = is_array( $media ) ? (string) ( $media['url'] ?? '' ) : '';

		if ( '' === $url ) {
			return '';
		}

		$id   = (int) ( $media['id'] ?? 0 );
		$mime = $id ? (string) get_post_mime_type( $id ) : '';

		if ( 0 === strpos( $mime, 'video/' ) || preg_match( '/\.(mp4|webm|mov|m4v|ogv)(\?|$)/i', $url ) ) {
			return '<video class="' . esc_attr( $class ) . '" src="' . esc_url( $url ) . '" muted loop playsinline preload="metadata" data-sz-autoplay></video>';
		}

		if ( $id ) {
			return (string) wp_get_attachment_image( $id, 'large', false, [ 'class' => $class, 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ] );
		}

		return '<img class="' . esc_attr( $class ) . '" src="' . esc_url( $url ) . '" alt="" loading="lazy" decoding="async">';
	}
}
