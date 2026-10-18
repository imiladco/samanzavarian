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

	/** Scenes that have a script (assets/js/scenes/<name>.js). */
	public const ANIMATED_SCENES = [ 'ai', 'post', 'content', 'direction' ];

	/** Scenes with controls a visitor can use. */
	public const INTERACTIVE_SCENES = [ 'post' ];

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

	/**
	 * Only the script of the scene this instance shows (Elementor asks each
	 * rendered instance). The editor gets every scene: settings change there.
	 */
	public function get_script_depends(): array {
		$all = array_merge( [ 'sz-service-panel' ], array_map( static fn( $s ) => 'sz-scene-' . $s, self::ANIMATED_SCENES ) );

		// The editor (and Elementor itself, on the bare widget type, which has
		// no settings to read) gets every scene.
		if ( ! $this->get_id() || Elementor::$instance->editor->is_edit_mode() || Elementor::$instance->preview->is_preview_mode() ) {
			return $all;
		}

		$scene = (string) $this->get_settings( 'scene' );

		return in_array( $scene, self::ANIMATED_SCENES, true ) ? [ 'sz-service-panel', 'sz-scene-' . $scene ] : [ 'sz-service-panel' ];
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

		$this->controls_ai();
		$this->controls_post();
		$this->controls_content();
		$this->controls_direction();

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
		$this->style_ai();
		$this->style_post();
		$this->style_content();
		$this->style_direction();
		$this->style_card();
		$this->style_chips();
		$this->style_button();
	}

	/* -----------------------------------------------------------------
	 * Scene: AI production
	 * ----------------------------------------------------------------- */

	private function controls_ai(): void {
		$on = [ 'scene' => 'ai' ];

		$this->start_controls_section(
			'section_ai',
			[
				'label'     => esc_html__( 'AI scene', 'samanzavarian-widgets' ),
				'condition' => $on,
			]
		);

		$this->add_control(
			'ai_prompts',
			[
				'label'       => esc_html__( 'Prompts', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 3,
				'default'     => 'Perfume bottle on wet stone, soft rim light, 85mm',
				'description' => esc_html__( 'One per line, one per round of three frames. Fewer lines than rounds repeat from the top.', 'samanzavarian-widgets' ),
			]
		);

		$frames = new Repeater();

		$frames->add_control(
			'media',
			[
				'label'       => esc_html__( 'Image or video', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::MEDIA,
				'media_types' => [ 'image', 'video' ],
				'description' => esc_html__( 'Videos play muted and looped, all frames of a round together.', 'samanzavarian-widgets' ),
			]
		);

		$frames->add_control(
			'mesh',
			[
				'label'       => esc_html__( 'Placeholder', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => '1',
				'options'     => self::mesh_options(),
				'description' => esc_html__( 'The gradient shown while no image or video is set (the design uses these).', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control(
			'ai_frames',
			[
				'label'       => esc_html__( 'Generated frames', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $frames->get_controls(),
				'default'     => [ [ 'mesh' => '2' ], [ 'mesh' => '1' ], [ 'mesh' => '3' ] ],
				'title_field' => '<# print( media && media.url ? media.url.split( "/" ).pop() : "Frame" ); #>',
				'description' => esc_html__( 'Three per round. With more than three, rounds take turns: a new prompt is typed and the next three are generated.', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control(
			'ai_pick',
			[
				'label'   => esc_html__( 'Approved frame', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '2',
				'options' => [
					'1' => esc_html__( 'First', 'samanzavarian-widgets' ),
					'2' => esc_html__( 'Middle', 'samanzavarian-widgets' ),
					'3' => esc_html__( 'Last', 'samanzavarian-widgets' ),
				],
				'separator' => 'before',
			]
		);

		$this->add_control(
			'ai_approved',
			[
				'label'       => esc_html__( 'Approval label', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'Variation {n} approved',
				'description' => esc_html__( '{n} becomes the approved frame\'s number.', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control(
			'ai_button',
			[
				'label'   => esc_html__( 'Button label', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::TEXT,
				'default' => 'Generate',
			]
		);

		$this->add_control(
			'ai_badge',
			[
				'label'   => esc_html__( 'Badge icon', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::ICONS,
				'default' => Icon_Library::value( 'spark' ),
			]
		);

		$this->add_control(
			'ai_ratio',
			[
				'label'     => esc_html__( 'Frame shape', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '3/4',
				'options'   => [
					'3/4'  => esc_html__( 'Portrait 3:4 (design)', 'samanzavarian-widgets' ),
					'4/5'  => esc_html__( 'Portrait 4:5', 'samanzavarian-widgets' ),
					'9/16' => esc_html__( 'Story 9:16', 'samanzavarian-widgets' ),
					'1/1'  => esc_html__( 'Square', 'samanzavarian-widgets' ),
					'16/9' => esc_html__( 'Landscape 16:9', 'samanzavarian-widgets' ),
				],
				'selectors' => [ '{{WRAPPER}} .szs-gen__frame' => 'aspect-ratio: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'ai_motion_heading',
			[
				'label'     => esc_html__( 'Motion', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'ai_type_speed',
			[
				'label'       => esc_html__( 'Typing speed', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 38, 'ms' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'ms' ],
				'range'       => [ 'ms' => [ 'min' => 0, 'max' => 150 ] ],
				'description' => esc_html__( 'Per letter. 0 shows the prompt at once.', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control(
			'ai_hold',
			[
				'label'       => esc_html__( 'Time per round', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 4, 's' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 's' ],
				'range'       => [ 's' => [ 'min' => 1, 'max' => 20, 'step' => 0.5 ] ],
				'description' => esc_html__( 'How long an approved round stays before the next one.', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control(
			'ai_cycle',
			[
				'label'        => esc_html__( 'Keep cycling rounds', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'description'  => esc_html__( 'Off: play through the rounds once and stay on the last. With a single round it plays each time the panel comes into view.', 'samanzavarian-widgets' ),
			]
		);

		$this->end_controls_section();
	}

	private function style_ai(): void {
		$this->start_controls_section(
			'style_ai',
			[
				'label'     => esc_html__( 'AI scene', 'samanzavarian-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'scene' => 'ai' ],
			]
		);

		$this->add_responsive_control(
			'ai_width',
			[
				'label'       => esc_html__( 'Sheet width', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', '%' ],
				'range'       => [
					'px' => [ 'min' => 200, 'max' => 700 ],
					'%'  => [ 'min' => 40, 'max' => 100 ],
				],
				'description' => esc_html__( 'Design: 440px, at most 78% of the panel (86% when narrow). Stays centred.', 'samanzavarian-widgets' ),
				'selectors'   => [ '{{WRAPPER}} .szs-gen' => 'width: {{SIZE}}{{UNIT}}; margin-left: calc({{SIZE}}{{UNIT}} / -2);' ],
			]
		);

		foreach ( [
			'ai_sheet_bg'   => [ esc_html__( 'Sheet', 'samanzavarian-widgets' ), '.szs-gen__sheet', 'background-color' ],
			'ai_prompt_bg'  => [ esc_html__( 'Prompt field', 'samanzavarian-widgets' ), '.szs-gen__prompt', 'background-color' ],
			'ai_prompt_fg'  => [ esc_html__( 'Prompt text', 'samanzavarian-widgets' ), '.szs-gen__prompt', 'color' ],
			'ai_go_bg'      => [ esc_html__( 'Button', 'samanzavarian-widgets' ), '.szs-gen__go', 'background-color' ],
			'ai_go_fg'      => [ esc_html__( 'Button text', 'samanzavarian-widgets' ), '.szs-gen__go', 'color' ],
			'ai_ring'       => [ esc_html__( 'Approval ring', 'samanzavarian-widgets' ), '.szs-gen', '--szs-ring' ],
			'ai_pill_bg'    => [ esc_html__( 'Approval label background', 'samanzavarian-widgets' ), '.szs-picked', 'background-color' ],
			'ai_pill_fg'    => [ esc_html__( 'Approval label text', 'samanzavarian-widgets' ), '.szs-picked', 'color' ],
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

		$this->end_controls_section();
	}

	/**
	 * Frames grouped into rounds of three. A short last round is completed
	 * from the start, so every round fills the sheet.
	 *
	 * @param array<string,mixed> $s
	 * @return array<int,array<int,array<string,mixed>>>
	 */
	public static function ai_rounds( array $s ): array {
		$frames = isset( $s['ai_frames'] ) && is_array( $s['ai_frames'] ) ? array_values( $s['ai_frames'] ) : [];

		if ( ! $frames ) {
			$frames = [ [ 'mesh' => '2' ], [ 'mesh' => '1' ], [ 'mesh' => '3' ] ];
		}

		$count  = count( $frames );
		$rounds = [];

		for ( $start = 0; $start < $count; $start += 3 ) {
			$round = [];
			for ( $k = 0; $k < 3; $k++ ) {
				$round[] = $frames[ ( $start + $k ) % $count ];
			}
			$rounds[] = $round;
		}

		return $rounds;
	}

	/**
	 * @param array<string,mixed> $s
	 * @return string[] one prompt per line, empty lines dropped
	 */
	public static function ai_prompts( array $s ): array {
		$lines = preg_split( '/\R/', (string) ( $s['ai_prompts'] ?? '' ) );
		$lines = array_values( array_filter( array_map( 'trim', $lines ), 'strlen' ) );

		return $lines ? $lines : [ '' ];
	}

	/* -----------------------------------------------------------------
	 * Scene: Post-production
	 * ----------------------------------------------------------------- */

	/** The design's two sliders. Knob positions match it: 18 on ±50 is 68%, −12 on ±75 is 42%. */
	private const POST_ADJUSTMENTS = [
		[ 'label' => 'Warmth', 'effect' => 'warmth', 'min' => -50, 'max' => 50, 'value' => 18, 'c1' => '#8FB3E0', 'c2' => '#E9ECEF', 'c3' => '#FFB07F' ],
		[ 'label' => 'Shadows', 'effect' => 'shadows', 'min' => -75, 'max' => 75, 'value' => -12, 'c1' => '#1B2633', 'c2' => '', 'c3' => '#E9ECEF' ],
	];

	/** The design's timeline: three tracks of clips (track, length, colour). */
	private const POST_CLIPS = [
		[ 1, 3, '#0D1B2A' ], [ 1, 2, '#2A4560' ], [ 1, 2, '#0D1B2A' ],
		[ 2, 2, '#FFD3BF' ], [ 2, 4, '#FF6B2C' ], [ 2, 1, '#FFD3BF' ],
		[ 3, 5, '#E7E9EC' ], [ 3, 2, '#D5D9DF' ],
	];

	private function controls_post(): void {
		$on = [ 'scene' => 'post' ];

		$this->start_controls_section(
			'section_post',
			[
				'label'     => esc_html__( 'Post-production scene', 'samanzavarian-widgets' ),
				'condition' => $on,
			]
		);

		$adj = new Repeater();
		$adj->add_control( 'label', [ 'label' => esc_html__( 'Label', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Exposure' ] );
		$adj->add_control(
			'effect',
			[
				'label'       => esc_html__( 'Changes', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'exposure',
				'options'     => [
					'warmth'     => esc_html__( 'Warmth', 'samanzavarian-widgets' ),
					'shadows'    => esc_html__( 'Shadows', 'samanzavarian-widgets' ),
					'exposure'   => esc_html__( 'Exposure', 'samanzavarian-widgets' ),
					'contrast'   => esc_html__( 'Contrast', 'samanzavarian-widgets' ),
					'saturation' => esc_html__( 'Saturation', 'samanzavarian-widgets' ),
					'none'       => esc_html__( 'Nothing (just a number)', 'samanzavarian-widgets' ),
				],
				'description' => esc_html__( 'What moving the slider does to the timeline clips and the preview image.', 'samanzavarian-widgets' ),
			]
		);
		$adj->add_control( 'min', [ 'label' => esc_html__( 'Minimum', 'samanzavarian-widgets' ), 'type' => Controls_Manager::NUMBER, 'default' => -50 ] );
		$adj->add_control( 'max', [ 'label' => esc_html__( 'Maximum', 'samanzavarian-widgets' ), 'type' => Controls_Manager::NUMBER, 'default' => 50 ] );
		$adj->add_control( 'value', [ 'label' => esc_html__( 'Starting value', 'samanzavarian-widgets' ), 'type' => Controls_Manager::NUMBER, 'default' => 0 ] );
		$adj->add_control( 'c1', [ 'label' => esc_html__( 'Track: low end', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'default' => '#1B2633' ] );
		$adj->add_control( 'c2', [ 'label' => esc_html__( 'Track: middle', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'default' => '' ] );
		$adj->add_control( 'c3', [ 'label' => esc_html__( 'Track: high end', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'default' => '#E9ECEF' ] );

		$this->add_control(
			'post_adjustments',
			[
				'label'       => esc_html__( 'Sliders', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $adj->get_controls(),
				'default'     => self::POST_ADJUSTMENTS,
				'title_field' => '{{{ label }}}',
				'description' => esc_html__( 'Visitors can drag them (or use the arrow keys); the number and the clips follow.', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control(
			'post_drift',
			[
				'label'        => esc_html__( 'Sliders move on their own', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'description'  => esc_html__( 'A slow, small drift around the starting value, as if someone were grading. It stops while a visitor holds a slider.', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control(
			'post_preview',
			[
				'label'       => esc_html__( 'Preview image', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::MEDIA,
				'description' => esc_html__( 'Optional: a photo the sliders grade live. Empty keeps the design as it is.', 'samanzavarian-widgets' ),
				'separator'   => 'before',
			]
		);

		$clip = new Repeater();
		$clip->add_control(
			'track',
			[
				'label'   => esc_html__( 'Track', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '1',
				'options' => [ '1' => '1', '2' => '2', '3' => '3' ],
			]
		);
		$clip->add_control( 'length', [ 'label' => esc_html__( 'Length', 'samanzavarian-widgets' ), 'type' => Controls_Manager::NUMBER, 'default' => 2, 'min' => 1, 'max' => 12 ] );
		$clip->add_control( 'color', [ 'label' => esc_html__( 'Colour', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'default' => '#2A4560' ] );

		$defaults = [];
		foreach ( self::POST_CLIPS as $c ) {
			$defaults[] = [ 'track' => (string) $c[0], 'length' => $c[1], 'color' => $c[2] ];
		}

		$this->add_control(
			'post_clips',
			[
				'label'       => esc_html__( 'Timeline clips', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $clip->get_controls(),
				'default'     => $defaults,
				'title_field' => '<# print( "Track " + track + " · " + length ); #>',
				'separator'   => 'before',
			]
		);

		$this->add_control( 'post_from', [ 'label' => esc_html__( 'Ruler start', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => '00:00:12' ] );
		$this->add_control( 'post_to', [ 'label' => esc_html__( 'Ruler end', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => '00:00:24' ] );

		$this->add_control(
			'post_done',
			[
				'label'     => esc_html__( 'Finished label', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => 'Final export ready',
				'separator' => 'before',
			]
		);

		$this->add_control(
			'post_working',
			[
				'label'       => esc_html__( 'Working label', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'Exporting {p}%',
				'description' => esc_html__( 'Shown while the playhead runs; {p} is its progress.', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control(
			'post_duration',
			[
				'label'       => esc_html__( 'Playhead run', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 7, 's' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 's' ],
				'range'       => [ 's' => [ 'min' => 2, 'max' => 30, 'step' => 0.5 ] ],
			]
		);

		$this->end_controls_section();
	}

	private function style_post(): void {
		$this->start_controls_section(
			'style_post',
			[
				'label'     => esc_html__( 'Post-production scene', 'samanzavarian-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'scene' => 'post' ],
			]
		);

		foreach ( [
			'post_card_bg'  => [ esc_html__( 'Cards', 'samanzavarian-widgets' ), '.szs-grade, {{WRAPPER}} .szs-tl', 'background-color' ],
			'post_label'    => [ esc_html__( 'Slider labels', 'samanzavarian-widgets' ), '.szs-grade__row', 'color' ],
			'post_value'    => [ esc_html__( 'Slider values', 'samanzavarian-widgets' ), '.szs-grade__val', 'color' ],
			'post_knob'     => [ esc_html__( 'Knobs', 'samanzavarian-widgets' ), '.szs-grade__knob', 'background-color' ],
			'post_head'     => [ esc_html__( 'Playhead', 'samanzavarian-widgets' ), '.szs-tl__head, {{WRAPPER}} .szs-tl__head::before', 'background-color' ],
			'post_ruler'    => [ esc_html__( 'Ruler text', 'samanzavarian-widgets' ), '.szs-tl__top', 'color' ],
			'post_pill_bg'  => [ esc_html__( 'Label background', 'samanzavarian-widgets' ), '.szs-pill', 'background-color' ],
			'post_pill_fg'  => [ esc_html__( 'Label text', 'samanzavarian-widgets' ), '.szs-pill', 'color' ],
			'post_dot_done' => [ esc_html__( 'Finished dot', 'samanzavarian-widgets' ), '.szs-pill i', 'background-color' ],
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

		$this->end_controls_section();
	}

	/**
	 * Sliders, cleaned: numbers in range, a usable min < max.
	 *
	 * @param array<string,mixed> $s
	 * @return array<int,array<string,mixed>>
	 */
	public static function post_adjustments( array $s ): array {
		$rows = isset( $s['post_adjustments'] ) && is_array( $s['post_adjustments'] ) ? $s['post_adjustments'] : self::POST_ADJUSTMENTS;
		$out  = [];

		foreach ( $rows as $row ) {
			$min = is_numeric( $row['min'] ?? null ) ? (float) $row['min'] : -50;
			$max = is_numeric( $row['max'] ?? null ) ? (float) $row['max'] : 50;
			if ( $max <= $min ) {
				$max = $min + 1;
			}
			$value = is_numeric( $row['value'] ?? null ) ? (float) $row['value'] : 0;

			$out[] = [
				'label'  => (string) ( $row['label'] ?? '' ),
				'effect' => in_array( $row['effect'] ?? '', [ 'warmth', 'shadows', 'exposure', 'contrast', 'saturation', 'none' ], true ) ? $row['effect'] : 'none',
				'min'    => $min,
				'max'    => $max,
				'value'  => max( $min, min( $max, $value ) ),
				'track'  => self::gradient( [ $row['c1'] ?? '', $row['c2'] ?? '', $row['c3'] ?? '' ] ),
				'_id'    => (string) ( $row['_id'] ?? '' ),
			];
		}

		return $out;
	}

	/**
	 * Clips grouped by track, in order.
	 *
	 * @param array<string,mixed> $s
	 * @return array<int,array<int,array{length:int,color:string}>>
	 */
	public static function post_tracks( array $s ): array {
		$clips  = isset( $s['post_clips'] ) && is_array( $s['post_clips'] ) ? $s['post_clips'] : null;
		$tracks = [ 1 => [], 2 => [], 3 => [] ];

		if ( null === $clips ) {
			$clips = array_map( static fn( $c ) => [ 'track' => $c[0], 'length' => $c[1], 'color' => $c[2] ], self::POST_CLIPS );
		}

		foreach ( $clips as $c ) {
			$t = max( 1, min( 3, (int) ( $c['track'] ?? 1 ) ) );
			$tracks[ $t ][] = [
				'length' => max( 1, min( 12, (int) ( $c['length'] ?? 1 ) ) ),
				'color'  => self::css_color( (string) ( $c['color'] ?? '' ) ),
			];
		}

		return array_values( array_filter( $tracks ) );
	}

	/** Signed, with a real minus sign, like the design: +18, −12, 0. */
	public static function signed( float $n ): string {
		$r = (int) round( $n );

		return $r > 0 ? '+' . $r : ( $r < 0 ? '−' . abs( $r ) : '0' );
	}

	/** A colour value safe inside a style attribute (hex, rgb/hsl(), named, or a CSS variable). */
	public static function css_color( string $c ): string {
		$c = trim( $c );

		return preg_match( '/^(#[0-9a-fA-F]{3,8}|(?:rgb|rgba|hsl|hsla)\([0-9.,%\s\/]+\)|var\(--[a-zA-Z0-9_-]+\)|[a-zA-Z]+)$/', $c ) ? $c : '';
	}

	/** @param string[] $colors */
	private static function gradient( array $colors ): string {
		$stops = array_values( array_filter( array_map( [ self::class, 'css_color' ], $colors ), 'strlen' ) );

		if ( ! $stops ) {
			return '';
		}

		return 1 === count( $stops ) ? $stops[0] : 'linear-gradient(90deg,' . implode( ',', $stops ) . ')';
	}

	/* -----------------------------------------------------------------
	 * Scene: Content calendar
	 * ----------------------------------------------------------------- */

	private const CONTENT_DAYS = [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ];

	/** The design's post types: name, background, text, outline. */
	private const CONTENT_TYPES = [
		[ 'Reel', '#0D1B2A', '#FFFFFF', '' ],
		[ 'Carousel', '#FF6B2C', '#0D1B2A', '' ],
		[ 'Story', '#FFFFFF', '#111318', '#E8EAED' ],
	];

	/** Three weeks of plans; the first is the design's. */
	private const CONTENT_WEEKS = [
		[ 'mon' => 'Reel', 'wed' => 'Carousel', 'thu' => 'Story', 'sat' => 'Reel' ],
		[ 'tue' => 'Carousel', 'thu' => 'Reel', 'fri' => 'Story', 'sun' => 'Carousel' ],
		[ 'mon' => 'Story', 'wed' => 'Reel', 'fri' => 'Carousel + Story', 'sat' => 'Reel' ],
	];

	private function controls_content(): void {
		$this->start_controls_section(
			'section_content',
			[
				'label'     => esc_html__( 'Content calendar scene', 'samanzavarian-widgets' ),
				'condition' => [ 'scene' => 'content' ],
			]
		);

		$this->add_control(
			'content_mode',
			[
				'label'       => esc_html__( 'Which week', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'live',
				'options'     => [
					'live'  => esc_html__( 'The visitor\'s real week', 'samanzavarian-widgets' ),
					'fixed' => esc_html__( 'A fixed week', 'samanzavarian-widgets' ),
				],
				'description' => esc_html__( 'Real week: the week number and today are the visitor\'s own (ISO weeks, Monday first).', 'samanzavarian-widgets' ),
			]
		);

		$fixed = [ 'content_mode' => 'fixed' ];

		$this->add_control( 'content_week', [ 'label' => esc_html__( 'Week number', 'samanzavarian-widgets' ), 'type' => Controls_Manager::NUMBER, 'default' => 42, 'min' => 1, 'max' => 53, 'condition' => $fixed ] );

		$this->add_control(
			'content_today',
			[
				'label'     => esc_html__( 'Highlighted day', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '3',
				'options'   => [ '0' => esc_html__( 'None', 'samanzavarian-widgets' ), '1' => 'Mon', '2' => 'Tue', '3' => 'Wed', '4' => 'Thu', '5' => 'Fri', '6' => 'Sat', '7' => 'Sun' ],
				'condition' => $fixed,
			]
		);

		$types = new Repeater();
		$types->add_control( 'name', [ 'label' => esc_html__( 'Name', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Post' ] );
		$types->add_control( 'bg', [ 'label' => esc_html__( 'Background', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'default' => '#2A4560' ] );
		$types->add_control( 'fg', [ 'label' => esc_html__( 'Text', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'default' => '#FFFFFF' ] );
		$types->add_control( 'line', [ 'label' => esc_html__( 'Outline', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'default' => '' ] );

		$this->add_control(
			'content_types',
			[
				'label'       => esc_html__( 'Post types', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $types->get_controls(),
				'default'     => array_map( static fn( $t ) => [ 'name' => $t[0], 'bg' => $t[1], 'fg' => $t[2], 'line' => $t[3] ], self::CONTENT_TYPES ),
				'title_field' => '{{{ name }}}',
				'separator'   => 'before',
			]
		);

		$weeks = new Repeater();
		foreach ( self::CONTENT_DAYS as $day ) {
			$weeks->add_control(
				$day,
				[
					'label'       => ucfirst( $day ),
					'type'        => Controls_Manager::TEXT,
					'placeholder' => '—',
					'ai'          => [ 'active' => false ],
				]
			);
		}

		$this->add_control(
			'content_weeks',
			[
				'label'       => esc_html__( 'Weekly plans', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $weeks->get_controls(),
				'default'     => self::CONTENT_WEEKS,
				'title_field' => '<# print( [ mon, tue, wed, thu, fri, sat, sun ].filter( Boolean ).join( " · " ) || "Empty week" ); #>',
				'description' => esc_html__( 'Per day, a post type by name ("Reel"), several joined with + ("Reel + Story"). The calendar turns through these weeks in order.', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control( 'content_days', [ 'label' => esc_html__( 'Day names', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Mon, Tue, Wed, Thu, Fri, Sat, Sun', 'description' => esc_html__( 'Seven, comma separated, Monday first.', 'samanzavarian-widgets' ), 'separator' => 'before' ] );
		$this->add_control( 'content_title', [ 'label' => esc_html__( 'Calendar title', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Content plan' ] );
		$this->add_control( 'content_week_label', [ 'label' => esc_html__( 'Week label', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Week {n}', 'description' => esc_html__( '{n} becomes the week number.', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'content_icon', [ 'label' => esc_html__( 'Notification icon', 'samanzavarian-widgets' ), 'type' => Controls_Manager::ICONS, 'default' => Icon_Library::value( 'cal' ), 'separator' => 'before' ] );
		$this->add_control( 'content_done_title', [ 'label' => esc_html__( 'Notification title', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'This week is planned' ] );
		$this->add_control( 'content_done_text', [ 'label' => esc_html__( 'Notification text', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => '{count} posts scheduled', 'description' => esc_html__( '{count} becomes the number of posts that week.', 'samanzavarian-widgets' ) ] );

		$this->add_control(
			'content_drop',
			[
				'label'       => esc_html__( 'Time between posts', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 420, 'ms' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'ms' ],
				'range'       => [ 'ms' => [ 'min' => 100, 'max' => 2000, 'step' => 20 ] ],
				'separator'   => 'before',
			]
		);

		$this->add_control(
			'content_hold',
			[
				'label'       => esc_html__( 'Time before the page turns', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 3.5, 's' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 's' ],
				'range'       => [ 's' => [ 'min' => 1, 'max' => 20, 'step' => 0.5 ] ],
			]
		);

		$this->end_controls_section();
	}

	private function style_content(): void {
		$this->start_controls_section(
			'style_content',
			[
				'label'     => esc_html__( 'Content calendar scene', 'samanzavarian-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'scene' => 'content' ],
			]
		);

		foreach ( [
			'content_card_bg'   => [ esc_html__( 'Calendar', 'samanzavarian-widgets' ), '.szs-week, {{WRAPPER}} .szs-week__leaf', 'background-color' ],
			'content_title_fg'  => [ esc_html__( 'Title', 'samanzavarian-widgets' ), '.szs-week__head', 'color' ],
			'content_day_bg'    => [ esc_html__( 'Days', 'samanzavarian-widgets' ), '.szs-day', 'background-color' ],
			'content_day_fg'    => [ esc_html__( 'Day names', 'samanzavarian-widgets' ), '.szs-day', 'color' ],
			'content_today_bg'  => [ esc_html__( 'Today', 'samanzavarian-widgets' ), '.szs-day.is-today', 'background-color' ],
			'content_today_fg'  => [ esc_html__( 'Today\'s name', 'samanzavarian-widgets' ), '.szs-day.is-today', 'color' ],
			'content_notif_bg'  => [ esc_html__( 'Notification', 'samanzavarian-widgets' ), '.szs-notif', 'background-color' ],
			'content_notif_fg'  => [ esc_html__( 'Notification text', 'samanzavarian-widgets' ), '.szs-notif', 'color' ],
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

		$this->end_controls_section();
	}

	/**
	 * Everything the calendar needs, cleaned: post types (with safe colours),
	 * the plans as type indexes per day, day names, and the week on show.
	 *
	 * @param array<string,mixed> $s
	 * @return array<string,mixed>
	 */
	public static function content_plan( array $s ): array {
		$raw   = isset( $s['content_types'] ) && is_array( $s['content_types'] ) ? $s['content_types'] : array_map( static fn( $t ) => [ 'name' => $t[0], 'bg' => $t[1], 'fg' => $t[2], 'line' => $t[3] ], self::CONTENT_TYPES );
		$types = [];
		$index = [];

		foreach ( $raw as $t ) {
			$name = trim( (string) ( $t['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$index[ strtolower( $name ) ] = count( $types );
			$types[]                      = [
				'name' => $name,
				'bg'   => self::css_color( (string) ( $t['bg'] ?? '' ) ),
				'fg'   => self::css_color( (string) ( $t['fg'] ?? '' ) ),
				'line' => self::css_color( (string) ( $t['line'] ?? '' ) ),
			];
		}

		$weeks = [];
		foreach ( isset( $s['content_weeks'] ) && is_array( $s['content_weeks'] ) ? $s['content_weeks'] : self::CONTENT_WEEKS as $w ) {
			$days = [];
			foreach ( self::CONTENT_DAYS as $day ) {
				$posts = [];
				foreach ( preg_split( '/\s*\+\s*/', trim( (string) ( $w[ $day ] ?? '' ) ) ) as $name ) {
					if ( '' === $name ) {
						continue;
					}
					// a known type by its index; any other name still shows, plainly
					$key     = strtolower( $name );
					$posts[] = isset( $index[ $key ] ) ? $index[ $key ] : [ 'name' => $name ];
				}
				$days[] = $posts;
			}
			$weeks[] = $days;
		}

		if ( ! $weeks ) {
			$weeks[] = array_fill( 0, 7, [] );
		}

		$names = array_map( 'trim', explode( ',', (string) ( $s['content_days'] ?? '' ) ) );
		if ( 7 !== count( $names ) ) {
			$names = [ 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ];
		}

		$live = 'fixed' !== ( $s['content_mode'] ?? 'live' );

		return [
			'types' => $types,
			'weeks' => $weeks,
			'days'  => $names,
			'live'  => $live,
			// The server's best guess for a live calendar (site time zone);
			// the script corrects it to the visitor's own date.
			'week'  => $live ? (int) wp_date( 'W' ) : max( 1, min( 53, (int) ( $s['content_week'] ?? 42 ) ) ),
			'today' => $live ? (int) wp_date( 'N' ) : max( 0, min( 7, (int) ( $s['content_today'] ?? 3 ) ) ),
		];
	}

	/** Inline style for a post of a given type. */
	public static function content_post_style( array $type ): string {
		$css = '';
		$css .= $type['bg'] ? 'background:' . $type['bg'] . ';' : '';
		$css .= $type['fg'] ? 'color:' . $type['fg'] . ';' : '';
		$css .= 'box-shadow:' . ( $type['line'] ? '0 0 0 1px ' . $type['line'] : 'none' ) . ';';

		return $css;
	}

	/* -----------------------------------------------------------------
	 * Scene: Creative direction
	 * ----------------------------------------------------------------- */

	/** Two boards: the design's, and a second direction to curate next. */
	private const DIR_BOARDS = [
		[
			'mesh_a' => '6', 'mesh_b' => '5', 'mesh_c' => '3',
			'c1' => '#0D1B2A', 'c2' => '#FF6B2C', 'c3' => '#FFE6DA', 'c4' => '#E7E9EC',
			'note' => 'Quiet, warm light. Honest materials. One hero object per frame.',
		],
		[
			'mesh_a' => '2', 'mesh_b' => '1', 'mesh_c' => '4',
			'c1' => '#17293D', 'c2' => '#F1A27F', 'c3' => '#FFD3BF', 'c4' => '#F6F6F7',
			'note' => 'Night streets, warm practical light. Grain welcome. Faces lit from the side.',
		],
	];

	/** @return array<string,string> placeholder gradients, as named in the AI scene */
	private static function mesh_options(): array {
		return [
			'1' => esc_html__( 'Peach glow', 'samanzavarian-widgets' ),
			'2' => esc_html__( 'Night orange', 'samanzavarian-widgets' ),
			'3' => esc_html__( 'Pearl', 'samanzavarian-widgets' ),
			'4' => esc_html__( 'Ember', 'samanzavarian-widgets' ),
			'5' => esc_html__( 'Apricot', 'samanzavarian-widgets' ),
			'6' => esc_html__( 'Slate', 'samanzavarian-widgets' ),
		];
	}

	private function controls_direction(): void {
		$this->start_controls_section(
			'section_direction',
			[
				'label'     => esc_html__( 'Creative direction scene', 'samanzavarian-widgets' ),
				'condition' => [ 'scene' => 'direction' ],
			]
		);

		$board = new Repeater();

		foreach ( [ 'a' => esc_html__( 'Chosen image 1 (large, left)', 'samanzavarian-widgets' ), 'b' => esc_html__( 'Chosen image 2 (middle, on top)', 'samanzavarian-widgets' ), 'c' => esc_html__( 'Chosen image 3 (square, right)', 'samanzavarian-widgets' ) ] as $k => $label ) {
			$board->add_control(
				'pick_' . $k,
				[
					'label'       => $label,
					'type'        => Controls_Manager::MEDIA,
					'media_types' => [ 'image', 'video' ],
					'separator'   => 'a' === $k ? 'none' : 'before',
				]
			);
			$board->add_control(
				'mesh_' . $k,
				[
					'label'   => esc_html__( 'Placeholder', 'samanzavarian-widgets' ),
					'type'    => Controls_Manager::SELECT,
					'default' => '1',
					'options' => self::mesh_options(),
				]
			);
		}

		foreach ( [ 1, 2, 3, 4 ] as $i ) {
			$board->add_control(
				'c' . $i,
				[
					/* translators: %d: palette colour number */
					'label'     => sprintf( esc_html__( 'Palette colour %d', 'samanzavarian-widgets' ), $i ),
					'type'      => Controls_Manager::COLOR,
					'separator' => 1 === $i ? 'before' : 'none',
				]
			);
		}

		$board->add_control(
			'note',
			[
				'label'     => esc_html__( 'Direction note', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::TEXTAREA,
				'rows'      => 3,
				'default'   => 'One clear idea, written down.',
				'separator' => 'before',
			]
		);

		$this->add_control(
			'dir_boards',
			[
				'label'       => esc_html__( 'Boards', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $board->get_controls(),
				'default'     => self::DIR_BOARDS,
				'title_field' => '{{{ note }}}',
				'description' => esc_html__( 'Each board: references land, three are chosen and composed, the palette is pulled from them and the note is written. Then the next board.', 'samanzavarian-widgets' ),
			]
		);

		// One shared pool rather than a gallery per board: references are
		// interchangeable, and filling one gallery is quicker than one per board.
		$this->add_control(
			'dir_refs',
			[
				'label'       => esc_html__( 'Other references', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::GALLERY,
				'description' => esc_html__( 'The ones not chosen: they land on each board and are cleared away. Each board takes the next few (up to five). None → gradient placeholders.', 'samanzavarian-widgets' ),
				'separator'   => 'before',
			]
		);

		$this->add_control( 'dir_title', [ 'label' => esc_html__( 'Note title', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Direction note', 'separator' => 'before' ] );

		$this->add_control(
			'dir_hold',
			[
				'label'       => esc_html__( 'Time per board', 'samanzavarian-widgets' ),
				'placeholder' => Control_Helpers::ph( 4, 's' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 's' ],
				'range'       => [ 's' => [ 'min' => 1, 'max' => 20, 'step' => 0.5 ] ],
				'description' => esc_html__( 'How long a finished board stays before the next.', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control(
			'dir_cycle',
			[
				'label'        => esc_html__( 'Keep cycling boards', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'description'  => esc_html__( 'With a single board it plays each time the panel comes into view.', 'samanzavarian-widgets' ),
			]
		);

		$this->end_controls_section();
	}

	private function style_direction(): void {
		$this->start_controls_section(
			'style_direction',
			[
				'label'     => esc_html__( 'Creative direction scene', 'samanzavarian-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'scene' => 'direction' ],
			]
		);

		foreach ( [
			'dir_note_bg'    => [ esc_html__( 'Note', 'samanzavarian-widgets' ), '.szs-note', 'background-color' ],
			'dir_note_fg'    => [ esc_html__( 'Note text', 'samanzavarian-widgets' ), '.szs-note', 'color' ],
			'dir_note_title' => [ esc_html__( 'Note title', 'samanzavarian-widgets' ), '.szs-note b', 'color' ],
			'dir_swatch_bg'  => [ esc_html__( 'Palette', 'samanzavarian-widgets' ), '.szs-swatch', 'background-color' ],
			'dir_ring'       => [ esc_html__( 'Selection ring', 'samanzavarian-widgets' ), '.szs--direction', '--szs-ring' ],
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

		$this->end_controls_section();
	}

	/**
	 * Boards, cleaned and ready to print or hand to the script.
	 *
	 * @param array<string,mixed> $s
	 * @return array<int,array{picks:array<int,array{mesh:string,html:string}>,refs:array<int,array{mesh:string,html:string}>,palette:string[],note:string}>
	 */
	public static function dir_boards( array $s ): array {
		$raw  = isset( $s['dir_boards'] ) && is_array( $s['dir_boards'] ) && $s['dir_boards'] ? $s['dir_boards'] : self::DIR_BOARDS;
		$pool = isset( $s['dir_refs'] ) && is_array( $s['dir_refs'] ) ? array_values( array_filter( $s['dir_refs'], static fn( $i ) => ! empty( $i['url'] ) ) ) : [];
		$out  = [];

		foreach ( array_values( $raw ) as $b => $board ) {
			$mesh  = static fn( $v, $fallback ) => in_array( (string) $v, [ '1', '2', '3', '4', '5', '6' ], true ) ? (string) $v : $fallback;
			$picks = [];
			foreach ( [ 'a' => '6', 'b' => '5', 'c' => '3' ] as $k => $fallback ) {
				$picks[] = [
					'mesh' => $mesh( $board[ 'mesh_' . $k ] ?? '', $fallback ),
					'html' => self::media_html( $board[ 'pick_' . $k ] ?? [], 'szs-mood__media' ),
				];
			}

			// each board takes the next five of the shared pool, wrapping
			$refs = [];
			$take = min( 5, count( $pool ) );
			for ( $i = 0; $i < $take; $i++ ) {
				$refs[] = [ 'mesh' => '3', 'html' => self::media_html( $pool[ ( $b * $take + $i ) % count( $pool ) ], 'szs-mood__media' ) ];
			}
			if ( ! $refs ) {
				// placeholders that differ from the picks
				foreach ( array_slice( array_values( array_diff( [ '1', '2', '4', '5', '6', '3' ], array_column( $picks, 'mesh' ) ) ), 0, 4 ) as $m ) {
					$refs[] = [ 'mesh' => $m, 'html' => '' ];
				}
			}

			$palette = [];
			foreach ( [ 1, 2, 3, 4 ] as $i ) {
				$c = self::css_color( (string) ( $board[ 'c' . $i ] ?? '' ) );
				if ( '' !== $c ) {
					$palette[] = $c;
				}
			}

			$out[] = [
				'picks'   => $picks,
				'refs'    => $refs,
				'palette' => $palette,
				'note'    => trim( (string) ( $board['note'] ?? '' ) ),
			];
		}

		return $out;
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

		$scene = in_array( $scene, self::SCENES, true ) ? $scene : 'ai';
		$data  = in_array( $scene, self::ANIMATED_SCENES, true ) ? ' data-szs="' . esc_attr( $scene ) . '"' : '';

		// Scenes are decoration and hidden from assistive tech — except where
		// the visitor can use something (the grading sliders): that scene
		// hides its decorative parts one by one instead.
		$hidden = in_array( $scene, self::INTERACTIVE_SCENES, true ) ? '' : ' aria-hidden="true"';

		echo '<div class="sz-svc__art szs szs--' . esc_attr( $scene ) . '"' . $data . $hidden . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
		include SZW_PATH . 'includes/Widgets/scenes/' . $scene . '.php';
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
