<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Plugin as Elementor;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Samanzavarian_Widgets\Icon_Library;
use Samanzavarian_Widgets\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * "About" photo stack — a deck of photos that fans out on hover or tap; any
 * photo can then be brought to the front (click, tap, swipe, arrow keys),
 * and its caption tells what it shows.
 *
 * The deck's resting layout is in the stylesheet (data-slot), so the first
 * paint is final and a hover still fans it without the script. The script
 * adds the rest: the fan sized to the room left to the window's edges, the
 * swap, the first-view peek, depth under the mouse, and the hints.
 */
class Photo_Stack extends Widget_Base {

	/** The design's deck: icon, tile, side label, caption title, caption text, placeholder, monogram. */
	private const CARDS = [
		[ 'lens', 'orange', 'Portrait', 'Saman Zavarian', 'Visual creative', 2, true ],
		[ 'cam', 'orange', 'On set', 'On set', 'Behind the scenes, product shoot', 1, false ],
		[ 'sliders', '', 'In the edit', 'In the edit', 'Grading a campaign film', 6, false ],
	];

	public const MAX_CARDS = 5;

	public function get_name(): string {
		return 'sz-stack';
	}

	public function get_title(): string {
		return esc_html__( 'Photo Stack', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-gallery-grid';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'photo', 'stack', 'deck', 'cards', 'about', 'portrait', 'fan' ];
	}

	public function get_style_depends(): array {
		return [ 'sz-stack' ];
	}

	public function get_script_depends(): array {
		return [ 'sz-stack' ];
	}

	protected function is_dynamic_content(): bool {
		return false;
	}

	public function has_widget_inner_wrapper(): bool {
		return ! Elementor::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	/* =================================================================
	 * Controls
	 * ================================================================= */

	protected function register_controls(): void {
		$this->content_cards();
		$this->content_monogram();
		$this->content_sticker();
		$this->content_behaviour();
		$this->content_labels();

		$this->style_deck();
		$this->style_monogram();
		$this->style_caption();
		$this->style_labels();
		$this->style_hint();
	}

	private function content_cards(): void {
		$this->start_controls_section( 'section_cards', [ 'label' => esc_html__( 'Photos', 'samanzavarian-widgets' ) ] );

		$r = new Repeater();

		$r->add_control(
			'image',
			[
				'label'       => esc_html__( 'Photo', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::MEDIA,
				'default'     => [ 'url' => '' ],
				'description' => esc_html__( 'Portrait 4:5, at least 800×1000 px.', 'samanzavarian-widgets' ),
				'dynamic'     => [ 'active' => true ],
			]
		);

		$r->add_control(
			'placeholder',
			[
				'label'     => esc_html__( 'Artwork without a photo', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '2',
				'options'   => array_combine(
					array_map( 'strval', range( 1, 8 ) ),
					array_map( static fn( $n ) => sprintf( /* translators: %d: number */ esc_html__( 'Gradient %d', 'samanzavarian-widgets' ), $n ), range( 1, 8 ) )
				),
				'condition' => [ 'image[url]' => '' ],
			]
		);

		$r->add_control(
			'video',
			[
				'label'       => esc_html__( 'Video', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::MEDIA,
				'media_types' => [ 'video' ],
				'default'     => [ 'url' => '' ],
				'description' => esc_html__( 'Optional. Plays without sound while this photo is in front and the deck is open: on hover with a mouse, after a tap on a phone. The photo shows until the video\'s first frame is ready. A short loop (5–15 s, under 4 MB, MP4) works best. Not played with reduced motion or data saver on.', 'samanzavarian-widgets' ),
			]
		);

		$r->add_control(
			'monogram',
			[
				'label'        => esc_html__( 'Monogram on it', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '',
				'return_value' => 'yes',
				'description'  => esc_html__( 'Your initials in place of a portrait (Monogram section).', 'samanzavarian-widgets' ),
				'condition'    => [ 'image[url]' => '' ],
			]
		);

		$r->add_control(
			'title',
			[
				'label'       => esc_html__( 'Caption title', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'On set', 'samanzavarian-widgets' ),
				'label_block' => true,
				'separator'   => 'before',
				'description' => esc_html__( 'The bar under the photo while it is in front.', 'samanzavarian-widgets' ),
				'dynamic'     => [ 'active' => true ],
			]
		);

		$r->add_control(
			'subtitle',
			[
				'label'       => esc_html__( 'Caption text', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'dynamic'     => [ 'active' => true ],
			]
		);

		$r->add_control(
			'label',
			[
				'label'       => esc_html__( 'Label while fanned', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'description' => esc_html__( 'The small tag on the photo while it is beside the front one. Empty: the caption title.', 'samanzavarian-widgets' ),
			]
		);

		$r->add_control(
			'icon',
			[
				'label'   => esc_html__( 'Icon', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::ICONS,
				'default' => Icon_Library::value( 'cam' ),
			]
		);

		$r->add_control(
			'tile',
			[
				'label'   => esc_html__( 'Icon tile on the label', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'orange',
				'options' => [
					'orange' => esc_html__( 'Orange', 'samanzavarian-widgets' ),
					''       => esc_html__( 'Navy', 'samanzavarian-widgets' ),
				],
			]
		);

		$defaults = [];

		foreach ( self::CARDS as $c ) {
			$defaults[] = [
				'icon'        => Icon_Library::value( $c[0] ),
				'tile'        => $c[1],
				'label'       => $c[2],
				'title'       => $c[3],
				'subtitle'    => $c[4],
				'placeholder' => (string) $c[5],
				'monogram'    => $c[6] ? 'yes' : '',
			];
		}

		$this->add_control(
			'cards',
			[
				'label'       => esc_html__( 'Photos', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => $defaults,
				'title_field' => '{{{ title || label }}}',
				'button_text' => esc_html__( 'Add photo', 'samanzavarian-widgets' ),
				/* translators: %d: number of photos */
				'description' => sprintf( esc_html__( 'The first is in front. Up to %d; the fan spreads them left and right in turn.', 'samanzavarian-widgets' ), self::MAX_CARDS ),
			]
		);

		$this->end_controls_section();
	}

	private function content_monogram(): void {
		$this->start_controls_section( 'section_monogram', [ 'label' => esc_html__( 'Monogram', 'samanzavarian-widgets' ) ] );

		$this->add_control(
			'mono_a',
			[
				'label'   => esc_html__( 'Letters', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::TEXT,
				'default' => 'S',
			]
		);

		$this->add_control(
			'mono_b',
			[
				'label'       => esc_html__( 'Letters in the accent colour', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'Z',
				'description' => esc_html__( 'Follow the first ones: S + Z.', 'samanzavarian-widgets' ),
			]
		);

		$this->end_controls_section();
	}

	private function content_sticker(): void {
		$this->start_controls_section( 'section_sticker', [ 'label' => esc_html__( 'Sticker', 'samanzavarian-widgets' ) ] );

		$this->add_control(
			'sticker',
			[
				'label'       => esc_html__( 'Sticker', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'emoji',
				'options'     => [
					'emoji' => esc_html__( 'Emoji', 'samanzavarian-widgets' ),
					'icon'  => esc_html__( 'Icon', 'samanzavarian-widgets' ),
					''      => esc_html__( 'None', 'samanzavarian-widgets' ),
				],
				'description' => esc_html__( 'An emoji looks different on Windows, Mac and Android; an icon looks the same everywhere.', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control(
			'sticker_emoji',
			[
				'label'     => esc_html__( 'Emoji', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => '📸',
				'condition' => [ 'sticker' => 'emoji' ],
			]
		);

		$this->add_control(
			'sticker_icon',
			[
				'label'     => esc_html__( 'Icon', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::ICONS,
				'default'   => Icon_Library::value( 'cam' ),
				'condition' => [ 'sticker' => 'icon' ],
			]
		);

		$this->end_controls_section();
	}

	private function content_behaviour(): void {
		$this->start_controls_section( 'section_behaviour', [ 'label' => esc_html__( 'Behaviour', 'samanzavarian-widgets' ) ] );

		$switches = [
			'bring'    => [ esc_html__( 'Bring a photo to the front', 'samanzavarian-widgets' ), 'yes', esc_html__( 'While fanned: click or tap a photo, swipe on a phone, or use ← → keys.', 'samanzavarian-widgets' ) ],
			'peek'     => [ esc_html__( 'Peek the first time it is seen', 'samanzavarian-widgets' ), 'yes', esc_html__( 'The photos part for a moment, once, so phone visitors learn they move.', 'samanzavarian-widgets' ) ],
			'parallax' => [ esc_html__( 'Depth under the mouse', 'samanzavarian-widgets' ), 'yes', esc_html__( 'The front photo follows the pointer slightly, the others move against it. Mouse only.', 'samanzavarian-widgets' ) ],
			'hint'     => [ esc_html__( 'Hint under the photos', 'samanzavarian-widgets' ), 'yes', esc_html__( 'Says what to do on that device; fades away once a photo was brought forward.', 'samanzavarian-widgets' ) ],
			'analytics'=> [ esc_html__( 'Report to analytics', 'samanzavarian-widgets' ), 'yes', esc_html__( 'Sends sz_stack_open and sz_stack_photo (with the photo\'s title) to GTM\'s dataLayer, or GA4 without GTM.', 'samanzavarian-widgets' ) ],
			'editor_open' => [ esc_html__( 'Keep fanned in the editor', 'samanzavarian-widgets' ), '', esc_html__( 'Only while editing, to see and edit the photos behind.', 'samanzavarian-widgets' ) ],
		];

		foreach ( $switches as $key => $def ) {
			$this->add_control(
				$key,
				[
					'label'        => $def[0],
					'type'         => Controls_Manager::SWITCHER,
					'default'      => $def[1],
					'return_value' => 'yes',
					'description'  => $def[2],
					'separator'    => 'editor_open' === $key ? 'before' : 'default',
				]
			);
		}

		$this->add_control(
			'scroll_on',
			[
				'label'       => esc_html__( 'Scroll-driven', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'separator'   => 'before',
				'default'     => [ 'desktop', 'tablet', 'mobile' ],
				'options'     => [
					'desktop' => esc_html__( 'Desktop', 'samanzavarian-widgets' ),
					'tablet'  => esc_html__( 'Tablet', 'samanzavarian-widgets' ),
					'mobile'  => esc_html__( 'Mobile', 'samanzavarian-widgets' ),
				],
				'description' => esc_html__( 'With the deck in view below the header, the scroll fans it out, brings the photos to the front one by one (a video plays) and folds it back, in step with the scroll; in reverse when scrolling up. Each photo stays at least a second; flicked past, it stays at rest. If its column has empty room below it, the deck holds still there while the text scrolls past. A hand on the photos always wins. Never in the editor or with reduced motion.', 'samanzavarian-widgets' ),
			]
		);

		$this->add_responsive_control(
			'align',
			[
				'label'     => esc_html__( 'Alignment', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::CHOOSE,
				'separator' => 'before',
				'options'   => [
					'flex-start' => [ 'title' => esc_html__( 'Left', 'samanzavarian-widgets' ), 'icon' => 'eicon-h-align-left' ],
					'center'     => [ 'title' => esc_html__( 'Center', 'samanzavarian-widgets' ), 'icon' => 'eicon-h-align-center' ],
					'flex-end'   => [ 'title' => esc_html__( 'Right', 'samanzavarian-widgets' ), 'icon' => 'eicon-h-align-right' ],
				],
				'selectors' => [ '{{WRAPPER}} .sz-stack' => 'justify-content: {{VALUE}};' ],
			]
		);

		$this->end_controls_section();
	}

	private function content_labels(): void {
		$this->start_controls_section( 'section_labels', [ 'label' => esc_html__( 'Hints and labels', 'samanzavarian-widgets' ) ] );

		foreach ( self::label_defaults() as $key => $def ) {
			$this->add_control(
				'label_' . $key,
				[
					'label'       => $def[1],
					'type'        => Controls_Manager::TEXT,
					'default'     => $def[0],
					'label_block' => true,
					'ai'          => [ 'active' => false ],
				]
			);
		}

		$this->end_controls_section();
	}

	/** @return array<string,array{0:string,1:string}> key => [default, control label] */
	private static function label_defaults(): array {
		return [
			'hover'    => [ __( 'Hover to fan out', 'samanzavarian-widgets' ), __( 'Hint with a mouse', 'samanzavarian-widgets' ) ],
			'tap'      => [ __( 'Tap to fan out', 'samanzavarian-widgets' ), __( 'Hint on touch screens', 'samanzavarian-widgets' ) ],
			'click'    => [ __( 'Click a photo to bring it forward', 'samanzavarian-widgets' ), __( 'Hint once fanned, with a mouse', 'samanzavarian-widgets' ) ],
			'swipe'    => [ __( 'Tap or swipe to bring a photo forward', 'samanzavarian-widgets' ), __( 'Hint once fanned, on touch screens', 'samanzavarian-widgets' ) ],
			'group'    => [ __( 'Photos', 'samanzavarian-widgets' ), __( 'Name of the deck (screen readers)', 'samanzavarian-widgets' ) ],
			'show'     => [ __( 'Show more photos', 'samanzavarian-widgets' ), __( '"Show more photos" (screen readers)', 'samanzavarian-widgets' ) ],
			'hide'     => [ __( 'Hide the other photos', 'samanzavarian-widgets' ), __( '"Hide the other photos" (screen readers)', 'samanzavarian-widgets' ) ],
			'forward'  => [ __( 'Bring forward:', 'samanzavarian-widgets' ), __( '"Bring forward:" (screen readers)', 'samanzavarian-widgets' ) ],
			'front'    => [ __( 'in front', 'samanzavarian-widgets' ), __( '"in front", read out after a swap', 'samanzavarian-widgets' ) ],
		];
	}

	/* ---------------- style ---------------- */

	/** @param array{0:?int,1:?int} $device tablet and phone defaults, in the first unit */
	private function var_slider( string $id, string $label, string $var, $placeholder, array $range, array $units = [ 'px', 'custom' ], bool $responsive = true, string $description = '', array $device = [ null, null ] ): void {
		$args = [
			'label'       => $label,
			'type'        => Controls_Manager::SLIDER,
			'placeholder' => $placeholder,
			'size_units'  => $units,
			'range'       => [ $units[0] => $range ],
			'description' => $description,
			'selectors'   => [ '{{WRAPPER}} .sz-stack' => $var . ': {{SIZE}}{{UNIT}};' ],
		];

		foreach ( [ 'tablet_default', 'mobile_default' ] as $n => $key ) {
			if ( null !== ( $device[ $n ] ?? null ) ) {
				$args[ $key ] = [ 'size' => $device[ $n ], 'unit' => $units[0] ];
			}
		}

		$responsive ? $this->add_responsive_control( $id, $args ) : $this->add_control( $id, $args );
	}

	private function var_color( string $id, string $label, string $var ): void {
		$this->add_control(
			$id,
			[
				'label'     => $label,
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .sz-stack' => $var . ': {{VALUE}};' ],
			]
		);
	}

	private function style_deck(): void {
		$this->start_controls_section( 'style_deck', [ 'label' => esc_html__( 'Photos', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		// Tablet and phone sizes are defaults on purpose: only then do they
		// follow the site's own Elementor breakpoints.
		$this->var_slider( 'width', esc_html__( 'Width', 'samanzavarian-widgets' ), '--szk-w', Control_Helpers::ph( 400 ), [ 'min' => 160, 'max' => 700 ], [ 'px', '%', 'vw', 'custom' ], true, esc_html__( 'Design: 400 px; 330 on tablets, 250 on phones. The fan reaches out about a third of this on each side, less when the window edge is near.', 'samanzavarian-widgets' ), [ 330, 250 ] );
		$this->var_slider( 'radius', esc_html__( 'Radius', 'samanzavarian-widgets' ), '--szk-radius', Control_Helpers::ph( 38 ), [ 'min' => 0, 'max' => 80 ], [ 'px', 'custom' ], true, '', [ null, 28 ] );

		$this->add_group_control( Group_Control_Box_Shadow::get_type(), [ 'name' => 'shadow', 'selector' => '{{WRAPPER}} .sz-stack__card' ] );

		$this->add_control( 'sticker_heading', [ 'label' => esc_html__( 'Sticker', 'samanzavarian-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$this->var_slider( 'sticker_size', esc_html__( 'Size', 'samanzavarian-widgets' ), '--szk-st', Control_Helpers::ph( 58 ), [ 'min' => 24, 'max' => 120 ], [ 'px', 'custom' ], true, '', [ null, 46 ] );
		$this->var_color( 'sticker_bg', esc_html__( 'Background', 'samanzavarian-widgets' ), '--szk-st-bg' );
		$this->var_color( 'sticker_color', esc_html__( 'Icon colour', 'samanzavarian-widgets' ), '--szk-st-icon' );

		$this->var_slider( 'speed', esc_html__( 'Fan speed', 'samanzavarian-widgets' ), '--szk-dur', Control_Helpers::ph( 800, 'ms' ), [ 'min' => 200, 'max' => 1600, 'step' => 50 ], [ 'ms' ], false );

		$this->end_controls_section();
	}

	private function style_monogram(): void {
		$this->start_controls_section( 'style_monogram', [ 'label' => esc_html__( 'Monogram', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'mono_typo', 'selector' => '{{WRAPPER}} .sz-stack__mono', 'exclude' => [ 'line_height' ] ] );
		$this->var_color( 'mono_color', esc_html__( 'Letters', 'samanzavarian-widgets' ), '--szk-mono' );
		$this->var_color( 'mono_accent', esc_html__( 'Accent letters', 'samanzavarian-widgets' ), '--szk-mono-b' );

		$this->end_controls_section();
	}

	private function style_caption(): void {
		$this->start_controls_section( 'style_caption', [ 'label' => esc_html__( 'Caption bar', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$this->var_color( 'cap_bg', esc_html__( 'Background', 'samanzavarian-widgets' ), '--szk-cap' );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'cap_title_typo', 'label' => esc_html__( 'Title', 'samanzavarian-widgets' ), 'selector' => '{{WRAPPER}} .sz-stack__cap b' ] );
		$this->var_color( 'cap_title', esc_html__( 'Title', 'samanzavarian-widgets' ), '--szk-cap-title' );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'cap_text_typo', 'label' => esc_html__( 'Text', 'samanzavarian-widgets' ), 'selector' => '{{WRAPPER}} .sz-stack__cap small' ] );
		$this->var_color( 'cap_text', esc_html__( 'Text', 'samanzavarian-widgets' ), '--szk-cap-text' );

		$this->end_controls_section();
	}

	private function style_labels(): void {
		$this->start_controls_section( 'style_labels', [ 'label' => esc_html__( 'Labels while fanned', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$this->var_color( 'lab_bg', esc_html__( 'Background', 'samanzavarian-widgets' ), '--szk-lab' );
		$this->var_color( 'lab_color', esc_html__( 'Text', 'samanzavarian-widgets' ), '--szk-lab-text' );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'lab_typo', 'selector' => '{{WRAPPER}} .sz-stack__lab' ] );

		$this->end_controls_section();
	}

	private function style_hint(): void {
		$this->start_controls_section(
			'style_hint',
			[
				'label'     => esc_html__( 'Hint', 'samanzavarian-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'hint' => 'yes' ],
			]
		);

		$this->var_color( 'hint_bg', esc_html__( 'Background', 'samanzavarian-widgets' ), '--szk-hint' );
		$this->var_color( 'hint_color', esc_html__( 'Text', 'samanzavarian-widgets' ), '--szk-hint-text' );
		$this->var_color( 'hint_dot', esc_html__( 'Dot', 'samanzavarian-widgets' ), '--szk-hint-dot' );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'hint_typo', 'selector' => '{{WRAPPER}} .sz-stack__hint' ] );

		$this->end_controls_section();
	}

	/* =================================================================
	 * Data
	 * ================================================================= */

	/**
	 * Cards with something to show, at most MAX_CARDS.
	 *
	 * @param array<string,mixed> $s
	 * @return array<int,array<string,mixed>>
	 */
	public static function cards( array $s ): array {
		$out = [];

		foreach ( (array) ( $s['cards'] ?? [] ) as $c ) {
			$title = trim( (string) ( $c['title'] ?? '' ) );
			$label = trim( (string) ( $c['label'] ?? '' ) );
			$image = is_array( $c['image'] ?? null ) ? $c['image'] : [];
			$ph    = (int) ( $c['placeholder'] ?? 2 );

			$out[] = [
				'id'          => (string) ( $c['_id'] ?? '' ),
				'title'       => $title,
				'subtitle'    => trim( (string) ( $c['subtitle'] ?? '' ) ),
				'label'       => '' !== $label ? $label : $title,
				'icon'        => $c['icon'] ?? [],
				'tile'        => 'orange' === ( $c['tile'] ?? 'orange' ) ? 'orange' : '',
				'image'       => $image,
				'placeholder' => $ph >= 1 && $ph <= 8 ? $ph : 2,
				'monogram'    => '' === (string) ( $image['url'] ?? '' ) && 'yes' === ( $c['monogram'] ?? '' ),
				'video'       => self::video_url( $c['video'] ?? [] ),
			];

			if ( count( $out ) === self::MAX_CARDS ) {
				break;
			}
		}

		return $out;
	}

	/** A video file's URL, or '' when the media is not a video. @param mixed $media */
	public static function video_url( $media ): string {
		$url = is_array( $media ) ? trim( (string) ( $media['url'] ?? '' ) ) : '';

		if ( '' === $url ) {
			return '';
		}
		if ( preg_match( '/\.(mp4|webm|mov|m4v|ogv)(\?|#|$)/i', $url ) ) {
			return $url;
		}

		return ! empty( $media['id'] ) && 0 === strpos( (string) get_post_mime_type( (int) $media['id'] ), 'video/' ) ? $url : '';
	}

	/** Which side a slot sits on: odd slots left, even right; 0 is the front. */
	public static function side( int $slot ): string {
		return 0 === $slot ? '' : ( $slot % 2 ? 'l' : 'r' );
	}

	/** @param array<string,mixed> $s @return array<string,string> */
	private static function labels( array $s ): array {
		$out = [];

		foreach ( self::label_defaults() as $key => $def ) {
			$value       = trim( (string) ( $s[ 'label_' . $key ] ?? '' ) );
			$out[ $key ] = '' !== $value ? $value : $def[0];
		}

		return $out;
	}

	/* =================================================================
	 * Render
	 * ================================================================= */

	protected function render(): void {
		$s     = $this->get_settings_for_display();
		$cards = self::cards( $s );

		if ( ! $cards ) {
			return;
		}

		$l    = self::labels( $s );
		$deck = count( $cards ) > 1;
		$yes  = static fn( $k, $d = 'yes' ) => 'yes' === ( $s[ $k ] ?? $d );

		$this->add_render_attribute(
			'root',
			[
				'class'          => [ 'sz-stack', 'sz-w', $deck ? 'is-deck' : 'is-single', 'sz-stack--n' . count( $cards ), $deck && $yes( 'hint' ) ? 'has-hint' : '' ],
				'data-sz-stack'  => '',
				'data-bring'     => $yes( 'bring' ) ? '1' : '0',
				'data-peek'      => $yes( 'peek' ) ? '1' : '0',
				'data-parallax'  => $yes( 'parallax' ) ? '1' : '0',
				'data-scroll'    => $deck ? implode( ' ', array_intersect_key( Pin_Controls::DEVICES, array_flip( (array) ( $s['scroll_on'] ?? [] ) ) ) ) : '',
				'data-track'     => $yes( 'analytics' ) ? '1' : '0',
				'data-edit-open' => $yes( 'editor_open', '' ) && Elementor::$instance->editor->is_edit_mode() ? '1' : '0',
				'data-labels'    => wp_json_encode( [ 'show' => $l['show'], 'hide' => $l['hide'], 'forward' => $l['forward'], 'front' => $l['front'] ] ),
			]
		);
		?>
		<div <?php $this->print_render_attribute_string( 'root' ); ?>>
			<div class="sz-stack__deck" role="group" aria-label="<?php echo esc_attr( $l['group'] ); ?>">
				<?php
				foreach ( $cards as $slot => $card ) {
					$this->render_card( $card, $slot, $deck, $l, $s );
				}
				self::render_sticker( $s );
				if ( $deck && $yes( 'hint' ) ) :
					?>
					<span class="sz-stack__hint" aria-hidden="true"><i data-sz-beat="2400"></i><span class="is-hover"><?php echo esc_html( $l['hover'] ); ?></span><span class="is-tap"><?php echo esc_html( $l['tap'] ); ?></span><span class="is-click"><?php echo esc_html( $l['click'] ); ?></span><span class="is-swipe"><?php echo esc_html( $l['swipe'] ); ?></span></span>
				<?php endif; ?>
			</div>
			<?php if ( $deck ) : ?>
				<p class="sz-stack__sr sz-stack__live" aria-live="polite"></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param array<string,mixed>  $c
	 * @param array<string,string> $l
	 * @param array<string,mixed>  $s
	 */
	private function render_card( array $c, int $slot, bool $deck, array $l, array $s ): void {
		$front = 0 === $slot;
		$el    = $deck ? 'button' : 'div';
		$attrs = [
			'class'     => 'sz-stack__card' . ( $c['video'] ? ' has-video' : '' ) . ( $c['id'] ? ' elementor-repeater-item-' . $c['id'] : '' ),
			'data-i'    => (string) $slot,
			'data-slot' => (string) $slot,
			'data-side' => self::side( $slot ),
		];

		if ( $deck ) {
			$attrs['type'] = 'button';
			if ( $front ) {
				$attrs['aria-expanded'] = 'false';
				$attrs['aria-label']    = trim( $c['title'] . ( '' !== $c['subtitle'] ? ', ' . $c['subtitle'] : '' ) . '. ' . $l['show'], ' .' );
			} else {
				$attrs['aria-label'] = $l['forward'] . ' ' . $c['title'];
				$attrs['tabindex']   = '-1';
			}
		}

		$icon = Icon_Library::html( $c['icon'] );
		?>
		<<?php echo $el; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- button or div ?><?php foreach ( $attrs as $k => $v ) { echo ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"'; } ?>>
			<span class="sz-stack__media"><?php echo self::media( $c, $slot ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts ?></span>
			<?php if ( $c['monogram'] ) : ?>
				<span class="sz-stack__mono" aria-hidden="true"><?php echo esc_html( (string) ( $s['mono_a'] ?? '' ) ); ?><b><?php echo esc_html( (string) ( $s['mono_b'] ?? '' ) ); ?></b></span>
			<?php endif; ?>
			<?php if ( $deck && '' !== $c['label'] ) : ?>
				<span class="sz-stack__lab" aria-hidden="true"><?php if ( '' !== $icon ) : ?><span class="sz-app<?php echo $c['tile'] ? ' sz-app--orange' : ''; ?>"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon renderer ?></span><?php endif; ?><?php echo esc_html( $c['label'] ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $c['title'] || '' !== $c['subtitle'] ) : ?>
				<span class="sz-stack__cap"<?php echo $deck ? ' aria-hidden="true"' : ''; ?>>
					<?php if ( '' !== $icon ) : ?><span class="sz-app sz-app--orange" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon renderer ?></span><?php endif; ?>
					<span class="sz-stack__cap-t"><?php if ( '' !== $c['title'] ) : ?><b><?php echo esc_html( $c['title'] ); ?></b><?php endif; ?><?php if ( '' !== $c['subtitle'] ) : ?><small><?php echo esc_html( $c['subtitle'] ); ?></small><?php endif; ?></span>
				</span>
			<?php endif; ?>
		</<?php echo $el; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- button or div ?>>
		<?php
	}

	/**
	 * The photo (or gradient), and over it the video, which gets its src
	 * only when the deck comes near the screen.
	 *
	 * @param array<string,mixed> $c
	 */
	public static function media( array $c, int $slot ): string {
		$video = ! empty( $c['video'] ) ? '<video class="sz-stack__video" data-src="' . esc_url( $c['video'] ) . '" muted loop playsinline preload="none" aria-hidden="true" tabindex="-1"></video>' : '';

		return self::picture( $c ) . $video;
	}

	/** @param array<string,mixed> $c */
	private static function picture( array $c ): string {
		$id  = (int) ( $c['image']['id'] ?? 0 );
		$url = (string) ( $c['image']['url'] ?? '' );
		$alt = '' !== $c['title'] ? $c['title'] : $c['label'];

		if ( $id && wp_attachment_is_image( $id ) ) {
			return (string) wp_get_attachment_image(
				$id,
				'large',
				false,
				[
					'class'    => 'sz-stack__img',
					'alt'      => $alt,
					'loading'  => 'lazy',
					'decoding' => 'async',
					'sizes'    => '(max-width: 767px) 70vw, 440px',
				]
			);
		}

		if ( '' !== $url ) {
			return '<img class="sz-stack__img" src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async">';
		}

		return '<i class="sz-stack__mesh sz-stack__mesh--' . (int) $c['placeholder'] . '"></i>';
	}

	/** @param array<string,mixed> $s */
	private static function render_sticker( array $s ): void {
		$type = (string) ( $s['sticker'] ?? 'emoji' );

		if ( 'emoji' === $type && '' !== trim( (string) ( $s['sticker_emoji'] ?? '' ) ) ) {
			echo '<span class="sz-stack__sticker is-emoji" aria-hidden="true">' . esc_html( trim( (string) $s['sticker_emoji'] ) ) . '</span>';
		} elseif ( 'icon' === $type ) {
			$icon = Icon_Library::html( $s['sticker_icon'] ?? [] );
			if ( '' !== $icon ) {
				echo '<span class="sz-stack__sticker is-icon" aria-hidden="true">' . $icon . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon renderer
			}
		}
	}
}
