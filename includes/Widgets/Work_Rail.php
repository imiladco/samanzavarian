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
use Samanzavarian_Widgets\Projects;

defined( 'ABSPATH' ) || exit;

/**
 * "Selected work" — a filterable slider of project cards, ending on a card
 * that sends visitors to the full archive (Behance).
 *
 * Everything is in the HTML: every card, every filter with its count, and
 * the first filter's layout, so the section reads and indexes without the
 * script. The script adds the motion: the sliding filter thumb, the filter
 * transition, mouse drag with a throw, page buttons, the scrubber, hover
 * previews, and the filter in the address (#work-photo).
 *
 * Filters with no projects are not printed; a visitor never lands on an
 * empty slider.
 */
class Work_Rail extends Widget_Base {

	/** The design's projects (manual list defaults): filter, category, title, icon, placeholder, light, featured. */
	public const PROJECTS = [
		[ 'AI', 'AI visuals', 'AI product key visuals', 'spark', 2, false, true ],
		[ 'Film', 'Brand film', 'Scripted brand films', 'video', 1, false, false ],
		[ 'Photo', 'Industrial', 'Industrial photography', 'factory', 6, false, false ],
		[ 'Photo', 'Product', 'E-commerce product shoots', 'box', 3, true, false ],
		[ 'Post', 'Retouching', 'High-end retouching', 'film', 5, true, false ],
		[ 'AI', 'AI video', 'AI motion and video', 'video', 4, false, false ],
		[ 'Photo', 'Packaging', 'Packaging and label shoots', 'box', 7, false, false ],
		[ 'Post', 'Colour grading', 'Colour grading for campaigns', 'film', 8, false, false ],
	];

	private const FILTERS = [ 'AI', 'Film', 'Photo', 'Post' ];

	/** Placeholder artwork for a project without an image (the design's gradients). */
	private const PLACEHOLDERS = 8;

	/** Ids already printed on this page, so two widgets never share one. */
	private static array $used_ids = [];

	public function get_name(): string {
		return 'sz-work';
	}

	public function get_title(): string {
		return esc_html__( 'Selected Work', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-slider-push';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'work', 'portfolio', 'projects', 'slider', 'carousel', 'filter', 'behance' ];
	}

	public function get_style_depends(): array {
		return [ 'sz-work' ];
	}

	public function get_script_depends(): array {
		return [ 'sz-work' ];
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
		$this->content_projects();
		$this->content_filters();
		$this->content_profile();
		$this->content_end();
		$this->content_settings();
		$this->content_labels();

		$this->style_layout();
		$this->style_filters();
		$this->style_cards();
		$this->style_card_bar();
		$this->style_end();
		$this->style_controls();
	}

	private function content_projects(): void {
		$this->start_controls_section( 'section_projects', [ 'label' => esc_html__( 'Projects', 'samanzavarian-widgets' ) ] );

		$this->add_control(
			'source',
			[
				'label'       => esc_html__( 'Projects from', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'auto',
				'options'     => [
					'auto'    => esc_html__( 'Projects library (the list below until it has projects)', 'samanzavarian-widgets' ),
					'library' => esc_html__( 'Projects library', 'samanzavarian-widgets' ),
					'manual'  => esc_html__( 'A list in this widget', 'samanzavarian-widgets' ),
				],
				'description' => esc_html__( 'The library is Projects in the WordPress menu: each project with its picture, types (the filters) and card, reusable on any page.', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control(
			'library_info',
			[
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => self::library_info(),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
				'condition'       => [ 'source!' => 'manual' ],
			]
		);

		$this->add_control(
			'lib_types',
			[
				'label'       => esc_html__( 'Only these types', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => Projects::type_options(),
				'description' => esc_html__( 'Empty: every type. The filters are the types of the projects shown, in the types\' own order (Projects → Types).', 'samanzavarian-widgets' ),
				'condition'   => [ 'source!' => 'manual' ],
			]
		);

		$this->add_control(
			'lib_order',
			[
				'label'     => esc_html__( 'Order', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'menu',
				'options'   => [
					'menu'     => esc_html__( 'Their Order number', 'samanzavarian-widgets' ),
					'featured' => esc_html__( 'Featured first, then their Order number', 'samanzavarian-widgets' ),
					'date'     => esc_html__( 'Newest first', 'samanzavarian-widgets' ),
				],
				'condition' => [ 'source!' => 'manual' ],
			]
		);

		$this->add_control(
			'lib_limit',
			[
				'label'     => esc_html__( 'At most', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::NUMBER,
				'min'       => 1,
				'max'       => 50,
				'default'   => 12,
				'condition' => [ 'source!' => 'manual' ],
			]
		);

		$this->add_control(
			'manual_heading',
			[
				'label'     => esc_html__( 'List in this widget', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => [ 'source' => 'auto' ],
			]
		);

		$r = new Repeater();

		$r->add_control(
			'title',
			[
				'label'       => esc_html__( 'Title', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Project', 'samanzavarian-widgets' ),
				'label_block' => true,
				'dynamic'     => [ 'active' => true ],
			]
		);

		$r->add_control(
			'category',
			[
				'label'       => esc_html__( 'Category', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'description' => esc_html__( 'Shown above the title and in the card\'s bar, e.g. Brand film.', 'samanzavarian-widgets' ),
				'dynamic'     => [ 'active' => true ],
			]
		);

		$r->add_control(
			'filter',
			[
				'label'       => esc_html__( 'Filter', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => 'Photo',
				'description' => esc_html__( 'The name of one of the filters (Filters section), exactly as written there. Empty: shown under "All" only.', 'samanzavarian-widgets' ),
				'ai'          => [ 'active' => false ],
			]
		);

		$r->add_control(
			'image',
			[
				'label'       => esc_html__( 'Image', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::MEDIA,
				'default'     => [ 'url' => '' ],
				'description' => esc_html__( 'Portrait, at least 700×930 px. Fills the card; the wide card shows it cropped to its shape.', 'samanzavarian-widgets' ),
				'dynamic'     => [ 'active' => true ],
			]
		);

		$r->add_control(
			'placeholder',
			[
				'label'     => esc_html__( 'Artwork without an image', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '1',
				'options'   => array_combine(
					array_map( 'strval', range( 1, self::PLACEHOLDERS ) ),
					array_map( static fn( $n ) => sprintf( /* translators: %d: number */ esc_html__( 'Gradient %d', 'samanzavarian-widgets' ), $n ), range( 1, self::PLACEHOLDERS ) )
				),
				'condition' => [ 'image[url]' => '' ],
			]
		);

		$r->add_control(
			'video',
			[
				'label'       => esc_html__( 'Preview video', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::MEDIA,
				'media_types' => [ 'video' ],
				'default'     => [ 'url' => '' ],
				'description' => esc_html__( 'Optional. Plays without sound while the pointer is on the card, and is only downloaded then. A short loop (5–10 s, under 3 MB) works best.', 'samanzavarian-widgets' ),
			]
		);

		$r->add_control(
			'icon',
			[
				'label'   => esc_html__( 'Icon', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::ICONS,
				'default' => Icon_Library::value( 'spark' ),
			]
		);

		$r->add_control(
			'link',
			[
				'label'       => esc_html__( 'Link', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::URL,
				'placeholder' => 'https://www.behance.net/gallery/…',
				'description' => esc_html__( 'Empty: the profile link (Profile link section).', 'samanzavarian-widgets' ),
				'dynamic'     => [ 'active' => true ],
			]
		);

		$r->add_control(
			'skin',
			[
				'label'       => esc_html__( 'Text on the image', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'dark',
				'options'     => [
					'dark'  => esc_html__( 'White (dark image)', 'samanzavarian-widgets' ),
					'light' => esc_html__( 'Dark (light image)', 'samanzavarian-widgets' ),
				],
			]
		);

		$r->add_control(
			'featured',
			[
				'label'        => esc_html__( 'Featured', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '',
				'return_value' => 'yes',
				'description'  => esc_html__( 'Shows the "Featured" label, and makes this the wide card under "All". The first featured project counts.', 'samanzavarian-widgets' ),
			]
		);

		$defaults = [];

		foreach ( self::PROJECTS as $p ) {
			$defaults[] = [
				'filter'      => $p[0],
				'category'    => $p[1],
				'title'       => $p[2],
				'icon'        => Icon_Library::value( $p[3] ),
				'placeholder' => (string) $p[4],
				'skin'        => $p[5] ? 'light' : 'dark',
				'featured'    => $p[6] ? 'yes' : '',
			];
		}

		$this->add_control(
			'projects',
			[
				'label'       => esc_html__( 'Projects', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => $defaults,
				'title_field' => '{{{ title }}}<# if ( filter ) { #> · {{{ filter }}}<# } #>',
				'button_text' => esc_html__( 'Add project', 'samanzavarian-widgets' ),
				'condition'   => [ 'source!' => 'library' ],
			]
		);

		$this->add_control(
			'title_tag',
			[
				'label'     => esc_html__( 'Title tag', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'h3',
				'options'   => [ 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'p' => 'p', 'span' => 'span' ],
				'separator' => 'before',
			]
		);

		$this->end_controls_section();
	}

	private function content_filters(): void {
		$this->start_controls_section( 'section_filters', [ 'label' => esc_html__( 'Filters', 'samanzavarian-widgets' ) ] );

		$this->add_control(
			'show_filters',
			[
				'label'        => esc_html__( 'Show filters', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			]
		);

		$this->add_control(
			'show_all',
			[
				'label'        => esc_html__( '"All" filter', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'description'  => esc_html__( 'Off: the slider opens on the first filter.', 'samanzavarian-widgets' ),
				'condition'    => [ 'show_filters' => 'yes' ],
			]
		);

		$this->add_control(
			'all_label',
			[
				'label'     => esc_html__( '"All" label', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => esc_html__( 'All', 'samanzavarian-widgets' ),
				'condition' => [ 'show_filters' => 'yes', 'show_all' => 'yes' ],
			]
		);

		$r = new Repeater();

		$r->add_control(
			'label',
			[
				'label'   => esc_html__( 'Name', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'Filter', 'samanzavarian-widgets' ),
			]
		);

		$r->add_control(
			'slug',
			[
				'label'       => esc_html__( 'Link ID', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => 'photo',
				'description' => esc_html__( 'For links straight to this filter: #work-photo. Empty: made from the name.', 'samanzavarian-widgets' ),
				'ai'          => [ 'active' => false ],
			]
		);

		$this->add_control(
			'filters',
			[
				'label'       => esc_html__( 'Filters, in order', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => array_map( static fn( $f ) => [ 'label' => $f ], self::FILTERS ),
				'title_field' => '{{{ label }}}',
				'button_text' => esc_html__( 'Add filter', 'samanzavarian-widgets' ),
				'description' => esc_html__( 'For the list in this widget; the library\'s filters are its types. A filter with no projects is not shown.', 'samanzavarian-widgets' ),
				'condition'   => [ 'show_filters' => 'yes', 'source!' => 'library' ],
			]
		);

		$this->add_control(
			'show_counts',
			[
				'label'        => esc_html__( 'Show counts', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'condition'    => [ 'show_filters' => 'yes' ],
			]
		);

		$this->end_controls_section();
	}

	private function content_profile(): void {
		$this->start_controls_section( 'section_profile', [ 'label' => esc_html__( 'Profile link', 'samanzavarian-widgets' ) ] );

		$this->add_control(
			'profile_url',
			[
				'label'       => esc_html__( 'Profile URL', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::URL,
				'default'     => [ 'url' => 'https://www.behance.net/', 'is_external' => 'on', 'nofollow' => '' ],
				'description' => esc_html__( 'Your Behance (or any archive). Used by the link beside the filters, the last card, and every project without a link of its own.', 'samanzavarian-widgets' ),
				'dynamic'     => [ 'active' => true ],
			]
		);

		$this->add_control(
			'show_profile',
			[
				'label'        => esc_html__( 'Link beside the filters', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'description'  => esc_html__( 'On phones it shrinks to its icon.', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control(
			'profile_label',
			[
				'label'     => esc_html__( 'Text', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => esc_html__( 'Open Behance profile', 'samanzavarian-widgets' ),
				'condition' => [ 'show_profile' => 'yes' ],
			]
		);

		$this->add_control(
			'profile_icon',
			[
				'label'     => esc_html__( 'Icon', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::ICONS,
				'default'   => Icon_Library::value( 'be' ),
				'condition' => [ 'show_profile' => 'yes' ],
			]
		);

		$this->end_controls_section();
	}

	private function content_end(): void {
		$this->start_controls_section( 'section_end', [ 'label' => esc_html__( 'Last card', 'samanzavarian-widgets' ) ] );

		$this->add_control(
			'show_end',
			[
				'label'        => esc_html__( 'Show last card', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'description'  => esc_html__( 'Closes the slider under every filter, with a way to the full archive.', 'samanzavarian-widgets' ),
			]
		);

		$cond = [ 'show_end' => 'yes' ];

		$this->add_control(
			'end_total',
			[
				'label'       => esc_html__( 'Projects in the archive', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 0,
				'default'     => 106,
				'description' => esc_html__( 'Fills {total} in the title.', 'samanzavarian-widgets' ),
				'condition'   => $cond,
			]
		);

		$this->add_control(
			'end_title',
			[
				'label'     => esc_html__( 'Title', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => esc_html__( 'See all {total}+ projects', 'samanzavarian-widgets' ),
				'label_block' => true,
				'condition' => $cond,
			]
		);

		$this->add_control(
			'end_text',
			[
				'label'     => esc_html__( 'Text', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::TEXTAREA,
				'rows'      => 2,
				'default'   => esc_html__( 'Photography, film, AI and retouching, organised by client and project.', 'samanzavarian-widgets' ),
				'condition' => $cond,
			]
		);

		$this->add_control(
			'end_button',
			[
				'label'     => esc_html__( 'Button', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => esc_html__( 'Open Behance', 'samanzavarian-widgets' ),
				'condition' => $cond,
			]
		);

		$this->add_control(
			'end_icon',
			[
				'label'     => esc_html__( 'Icon', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::ICONS,
				'default'   => Icon_Library::value( 'be' ),
				'condition' => $cond,
			]
		);

		$this->add_control(
			'end_link',
			[
				'label'       => esc_html__( 'Link', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::URL,
				'description' => esc_html__( 'Empty: the profile URL.', 'samanzavarian-widgets' ),
				'dynamic'     => [ 'active' => true ],
				'condition'   => $cond,
			]
		);

		$this->end_controls_section();
	}

	private function content_settings(): void {
		$this->start_controls_section( 'section_settings', [ 'label' => esc_html__( 'Settings', 'samanzavarian-widgets' ) ] );

		$this->add_control(
			'wide_lead',
			[
				'label'        => esc_html__( 'Wide first card', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'description'  => esc_html__( 'The first card of each filter spans two columns (under "All", the featured project). Never on phones.', 'samanzavarian-widgets' ),
			]
		);

		// 0.11–0.13 had a switch; Style → Layout → "Cards run out to" replaces it.
		$this->add_control( 'bleed', [ 'type' => Controls_Manager::HIDDEN, 'default' => 'yes' ] );

		$this->add_control(
			'tilt',
			[
				'label'        => esc_html__( 'Tilt cards under the pointer', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'description'  => esc_html__( 'Mouse only; never on touch screens or with reduced motion.', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control(
			'update_url',
			[
				'label'        => esc_html__( 'Put the filter in the address', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'separator'    => 'before',
				'description'  => esc_html__( 'Choosing Photo makes the address end in #work-photo, so that view can be shared. Opening such a link (or clicking one anywhere on the page) opens the filter.', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control(
			'hash_prefix',
			[
				'label'       => esc_html__( 'Link prefix', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'work',
				'description' => esc_html__( 'The part before the filter: #work-photo. Change it when the page has two of these widgets.', 'samanzavarian-widgets' ),
				'ai'          => [ 'active' => false ],
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
				'description'  => esc_html__( 'Sends sz_work_filter (a filter was chosen), sz_work_open (a project was opened) and sz_work_profile (the archive was opened) to Google Tag Manager\'s dataLayer, or to GA4 when it is installed without GTM.', 'samanzavarian-widgets' ),
			]
		);

		$this->end_controls_section();
	}

	private function content_labels(): void {
		$this->start_controls_section( 'section_labels', [ 'label' => esc_html__( 'Labels', 'samanzavarian-widgets' ) ] );

		foreach ( self::label_defaults() as $key => $def ) {
			$this->add_control(
				'label_' . $key,
				[
					'label'       => $def[1],
					'type'        => Controls_Manager::TEXT,
					'default'     => $def[0],
					'description' => $def[2] ?? '',
					'ai'          => [ 'active' => false ],
				]
			);
		}

		$this->end_controls_section();
	}

	/** @return array<string,array{0:string,1:string,2?:string}> key => [default, control label, description] */
	private static function label_defaults(): array {
		return [
			'note'     => [ __( 'Case study on Behance', 'samanzavarian-widgets' ), __( 'Card bar, under the category', 'samanzavarian-widgets' ) ],
			'view'     => [ __( 'View', 'samanzavarian-widgets' ), __( 'Card button', 'samanzavarian-widgets' ) ],
			'featured' => [ __( 'Featured', 'samanzavarian-widgets' ), __( 'Featured label', 'samanzavarian-widgets' ) ],
			'preview'  => [ __( 'Preview', 'samanzavarian-widgets' ), __( 'On a card with a video', 'samanzavarian-widgets' ) ],
			'of'       => [ __( 'of', 'samanzavarian-widgets' ), __( 'Position under the slider', 'samanzavarian-widgets' ), __( '1–3 of 8', 'samanzavarian-widgets' ) ],
			'one'      => [ __( 'project', 'samanzavarian-widgets' ), __( 'One project', 'samanzavarian-widgets' ), __( 'Read out to screen readers after filtering: "1 project in Film".', 'samanzavarian-widgets' ) ],
			'many'     => [ __( 'projects', 'samanzavarian-widgets' ), __( 'Several projects', 'samanzavarian-widgets' ) ],
			'in'       => [ __( 'in', 'samanzavarian-widgets' ), __( '"in" (3 projects in Photo)', 'samanzavarian-widgets' ) ],
			'rail'     => [ __( 'Selected work', 'samanzavarian-widgets' ), __( 'Slider name (screen readers)', 'samanzavarian-widgets' ) ],
			'filters'  => [ __( 'Filter projects', 'samanzavarian-widgets' ), __( 'Filters name (screen readers)', 'samanzavarian-widgets' ) ],
			'prev'     => [ __( 'Previous projects', 'samanzavarian-widgets' ), __( 'Previous button (screen readers)', 'samanzavarian-widgets' ) ],
			'next'     => [ __( 'Next projects', 'samanzavarian-widgets' ), __( 'Next button (screen readers)', 'samanzavarian-widgets' ) ],
		];
	}

	/* ---------------- style ---------------- */

	/** A slider control that writes one custom property on the root. */
	private function var_slider( string $id, string $label, string $var, $placeholder, array $range, array $units = [ 'px', 'custom' ], bool $responsive = true, string $description = '' ): void {
		$args = [
			'label'       => $label,
			'type'        => Controls_Manager::SLIDER,
			'placeholder' => $placeholder,
			'size_units'  => $units,
			'range'       => [ $units[0] => $range ],
			'description' => $description,
			'selectors'   => [ '{{WRAPPER}} .sz-work' => $var . ': {{SIZE}}{{UNIT}};' ],
		];

		$responsive ? $this->add_responsive_control( $id, $args ) : $this->add_control( $id, $args );
	}

	/** A colour control that writes one custom property on the root. */
	private function var_color( string $id, string $label, string $var ): void {
		$this->add_control(
			$id,
			[
				'label'     => $label,
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}} .sz-work' => $var . ': {{VALUE}};' ],
			]
		);
	}

	private function style_layout(): void {
		$this->start_controls_section( 'style_layout', [ 'label' => esc_html__( 'Layout', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$this->add_control( 'col_heading', [ 'label' => esc_html__( 'Filters and controls', 'samanzavarian-widgets' ), 'type' => Controls_Manager::HEADING ] );

		$this->var_slider( 'col_width', esc_html__( 'Content column width', 'samanzavarian-widgets' ), '--szw-col', [ 'size' => 100, 'unit' => '%' ], [ 'min' => 320, 'max' => 1920 ], [ 'px', '%', 'vw', 'custom' ], true, esc_html__( 'The row of filters and the controls under the slider. The first card lines up with it too. In a full-width section, e.g. 1180 px (the design).', 'samanzavarian-widgets' ) );

		$this->add_responsive_control(
			'col_align',
			[
				'label'                => esc_html__( 'Column position', 'samanzavarian-widgets' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => [
					'left'   => [ 'title' => esc_html__( 'Left', 'samanzavarian-widgets' ), 'icon' => 'eicon-h-align-left' ],
					'center' => [ 'title' => esc_html__( 'Center', 'samanzavarian-widgets' ), 'icon' => 'eicon-h-align-center' ],
					'right'  => [ 'title' => esc_html__( 'Right', 'samanzavarian-widgets' ), 'icon' => 'eicon-h-align-right' ],
				],
				'selectors_dictionary' => [ 'left' => '0', 'center' => '.5', 'right' => '1' ],
				'selectors'            => [ '{{WRAPPER}} .sz-work' => '--szw-al: {{VALUE}};' ],
				'description'          => esc_html__( 'When the column is narrower than the widget. Design: center.', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control( 'cards_heading', [ 'label' => esc_html__( 'Cards', 'samanzavarian-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );

		$this->add_control(
			'run_out',
			[
				'label'       => esc_html__( 'Cards run out to', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'window',
				'options'     => [
					'window' => esc_html__( 'The window\'s edges (design)', 'samanzavarian-widgets' ),
					'widget' => esc_html__( 'The widget\'s edges', 'samanzavarian-widgets' ),
					'column' => esc_html__( 'The content column', 'samanzavarian-widgets' ),
				],
				'description' => esc_html__( 'How far the slider reaches while sliding. It always comes to rest with the first card on the content column.', 'samanzavarian-widgets' ),
			]
		);

		$this->var_slider( 'card_width', esc_html__( 'Card width', 'samanzavarian-widgets' ), '--szw-cw', Control_Helpers::ph( 340 ), [ 'min' => 180, 'max' => 600 ], [ 'px', 'vw', 'custom' ], true, esc_html__( 'Design: 30% of the window, between 260 and 340 px.', 'samanzavarian-widgets' ) );
		$this->var_slider( 'card_gap', esc_html__( 'Space between cards', 'samanzavarian-widgets' ), '--szw-gap', Control_Helpers::ph( 18 ), [ 'min' => 0, 'max' => 60 ] );

		$this->add_responsive_control(
			'card_ratio',
			[
				'label'     => esc_html__( 'Card shape', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '',
				'options'   => [
					''    => esc_html__( 'Portrait 3:4 (design)', 'samanzavarian-widgets' ),
					'4/5' => esc_html__( 'Portrait 4:5', 'samanzavarian-widgets' ),
					'2/3' => esc_html__( 'Tall 2:3', 'samanzavarian-widgets' ),
					'9/16' => esc_html__( 'Story 9:16', 'samanzavarian-widgets' ),
					'1/1' => esc_html__( 'Square', 'samanzavarian-widgets' ),
				],
				'selectors' => [ '{{WRAPPER}} .sz-work' => '--szw-ratio: {{VALUE}};' ],
			]
		);

		$this->var_slider( 'top_space', esc_html__( 'Space under the filters', 'samanzavarian-widgets' ), '--szw-top', Control_Helpers::ph( 6 ), [ 'min' => 0, 'max' => 60 ] );
		$this->var_slider( 'bar_space', esc_html__( 'Space above the controls', 'samanzavarian-widgets' ), '--szw-under', Control_Helpers::ph( 30 ), [ 'min' => 0, 'max' => 80 ], [ 'px', 'custom' ], true, esc_html__( 'Includes room for the cards\' shadow.', 'samanzavarian-widgets' ) );

		$this->end_controls_section();
	}

	private function style_filters(): void {
		$this->start_controls_section(
			'style_filters',
			[
				'label'     => esc_html__( 'Filters', 'samanzavarian-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_filters' => 'yes' ],
			]
		);

		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'filter_typo', 'selector' => '{{WRAPPER}} .sz-work__filter' ] );

		$this->var_color( 'filter_track', esc_html__( 'Track', 'samanzavarian-widgets' ), '--szw-track' );
		$this->var_color( 'filter_thumb', esc_html__( 'Active background', 'samanzavarian-widgets' ), '--szw-thumb' );
		$this->var_color( 'filter_color', esc_html__( 'Text', 'samanzavarian-widgets' ), '--szw-ftext' );
		$this->var_color( 'filter_color_on', esc_html__( 'Active text', 'samanzavarian-widgets' ), '--szw-ftext-on' );
		$this->var_color( 'count_bg', esc_html__( 'Count', 'samanzavarian-widgets' ), '--szw-count' );
		$this->var_color( 'count_color', esc_html__( 'Count text', 'samanzavarian-widgets' ), '--szw-count-text' );
		$this->var_color( 'count_bg_on', esc_html__( 'Active count', 'samanzavarian-widgets' ), '--szw-count-on' );
		$this->var_color( 'count_color_on', esc_html__( 'Active count text', 'samanzavarian-widgets' ), '--szw-count-text-on' );

		$this->var_slider( 'filter_radius', esc_html__( 'Radius', 'samanzavarian-widgets' ), '--szw-fradius', Control_Helpers::ph( 14 ), [ 'min' => 0, 'max' => 30 ], [ 'px', 'custom' ], false, esc_html__( 'Of the track; the active background follows it.', 'samanzavarian-widgets' ) );
		$this->var_slider( 'filter_height', esc_html__( 'Height', 'samanzavarian-widgets' ), '--szw-fh', Control_Helpers::ph( 36 ), [ 'min' => 24, 'max' => 64 ], [ 'px' ], true );
		$this->var_slider( 'filter_speed', esc_html__( 'Slide speed', 'samanzavarian-widgets' ), '--szw-fdur', Control_Helpers::ph( 450, 'ms' ), [ 'min' => 0, 'max' => 1200, 'step' => 50 ], [ 'ms' ], false );

		$this->add_control(
			'profile_heading',
			[
				'label'     => esc_html__( 'Profile link', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => [ 'show_profile' => 'yes' ],
			]
		);

		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'profile_typo', 'selector' => '{{WRAPPER}} .sz-work__profile', 'condition' => [ 'show_profile' => 'yes' ] ] );

		$this->add_control(
			'profile_color',
			[
				'label'     => esc_html__( 'Color', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => [ 'show_profile' => 'yes' ],
				'selectors' => [ '{{WRAPPER}} .sz-work__profile' => 'color: {{VALUE}};' ],
			]
		);

		$this->end_controls_section();
	}

	private function style_cards(): void {
		$this->start_controls_section( 'style_cards', [ 'label' => esc_html__( 'Cards', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$this->var_slider( 'card_radius', esc_html__( 'Radius', 'samanzavarian-widgets' ), '--szw-radius', Control_Helpers::ph( 30 ), [ 'min' => 0, 'max' => 60 ] );
		$this->var_slider( 'card_pad', esc_html__( 'Text inset', 'samanzavarian-widgets' ), '--szw-pad', Control_Helpers::ph( 22 ), [ 'min' => 8, 'max' => 48 ] );

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'card_shadow',
				'selector' => '{{WRAPPER}} .sz-work__card',
			]
		);

		$this->add_control( 'cat_heading', [ 'label' => esc_html__( 'Category', 'samanzavarian-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'cat_typo', 'selector' => '{{WRAPPER}} .sz-work__cat' ] );

		$this->add_control( 'title_heading', [ 'label' => esc_html__( 'Title', 'samanzavarian-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'title_typo', 'selector' => '{{WRAPPER}} .sz-work__title' ] );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'wide_title_typo', 'label' => esc_html__( 'Wide card title', 'samanzavarian-widgets' ), 'selector' => '{{WRAPPER}} .sz-work__card.is-wide .sz-work__title' ] );

		$this->add_control( 'colors_heading', [ 'label' => esc_html__( 'Text on images', 'samanzavarian-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$this->var_color( 'dark_text', esc_html__( 'On dark images', 'samanzavarian-widgets' ), '--szw-on-dark' );
		$this->var_color( 'light_text', esc_html__( 'On light images', 'samanzavarian-widgets' ), '--szw-on-light' );
		$this->var_color( 'flag_bg', esc_html__( 'Featured label', 'samanzavarian-widgets' ), '--szw-flag' );

		$this->end_controls_section();
	}

	private function style_card_bar(): void {
		$this->start_controls_section( 'style_card_bar', [ 'label' => esc_html__( 'Card bar', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$this->var_color( 'bar_bg', esc_html__( 'Background', 'samanzavarian-widgets' ), '--szw-bar' );
		$this->var_color( 'bar_title', esc_html__( 'Category', 'samanzavarian-widgets' ), '--szw-bar-title' );
		$this->var_color( 'bar_text', esc_html__( 'Text', 'samanzavarian-widgets' ), '--szw-bar-text' );
		$this->var_slider( 'bar_icon', esc_html__( 'Icon tile', 'samanzavarian-widgets' ), '--szw-icon', Control_Helpers::ph( 38 ), [ 'min' => 0, 'max' => 64 ], [ 'px' ], false, esc_html__( '0 hides it.', 'samanzavarian-widgets' ) );

		$this->add_control( 'view_heading', [ 'label' => esc_html__( 'Button', 'samanzavarian-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$this->var_color( 'view_bg', esc_html__( 'Background', 'samanzavarian-widgets' ), '--szw-view' );
		$this->var_color( 'view_color', esc_html__( 'Text', 'samanzavarian-widgets' ), '--szw-view-text' );
		$this->var_color( 'view_bg_hover', esc_html__( 'Hover background', 'samanzavarian-widgets' ), '--szw-view-on' );
		$this->var_color( 'view_color_hover', esc_html__( 'Hover text', 'samanzavarian-widgets' ), '--szw-view-text-on' );

		$this->end_controls_section();
	}

	private function style_end(): void {
		$this->start_controls_section(
			'style_end',
			[
				'label'     => esc_html__( 'Last card', 'samanzavarian-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_end' => 'yes' ],
			]
		);

		$this->var_color( 'end_bg', esc_html__( 'Background', 'samanzavarian-widgets' ), '--szw-end' );
		$this->var_color( 'end_title_color', esc_html__( 'Title', 'samanzavarian-widgets' ), '--szw-end-title' );
		$this->var_color( 'end_text_color', esc_html__( 'Text', 'samanzavarian-widgets' ), '--szw-end-text' );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'end_title_typo', 'label' => esc_html__( 'Title', 'samanzavarian-widgets' ), 'selector' => '{{WRAPPER}} .sz-work__end-title' ] );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'end_text_typo', 'label' => esc_html__( 'Text', 'samanzavarian-widgets' ), 'selector' => '{{WRAPPER}} .sz-work__end-text' ] );
		$this->var_color( 'end_btn_bg', esc_html__( 'Button', 'samanzavarian-widgets' ), '--szw-end-btn' );
		$this->var_color( 'end_btn_color', esc_html__( 'Button text', 'samanzavarian-widgets' ), '--szw-end-btn-text' );

		$this->end_controls_section();
	}

	private function style_controls(): void {
		$this->start_controls_section( 'style_controls', [ 'label' => esc_html__( 'Controls', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$this->var_slider( 'btn_size', esc_html__( 'Button size', 'samanzavarian-widgets' ), '--szw-btn', Control_Helpers::ph( 46 ), [ 'min' => 28, 'max' => 72 ] );
		$this->var_color( 'btn_bg', esc_html__( 'Button', 'samanzavarian-widgets' ), '--szw-btn-bg' );
		$this->var_color( 'btn_color', esc_html__( 'Arrow', 'samanzavarian-widgets' ), '--szw-btn-icon' );
		$this->var_color( 'prog_track', esc_html__( 'Progress track', 'samanzavarian-widgets' ), '--szw-prog' );
		$this->var_color( 'prog_bar', esc_html__( 'Progress', 'samanzavarian-widgets' ), '--szw-prog-on' );

		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'pos_typo', 'label' => esc_html__( 'Position', 'samanzavarian-widgets' ), 'selector' => '{{WRAPPER}} .sz-work__pos' ] );
		$this->var_color( 'pos_color', esc_html__( 'Position', 'samanzavarian-widgets' ), '--szw-pos' );
		$this->var_color( 'pos_color_on', esc_html__( 'Position, numbers', 'samanzavarian-widgets' ), '--szw-pos-on' );

		$this->end_controls_section();
	}

	/* =================================================================
	 * Data
	 * ================================================================= */

	/**
	 * The filters that will be shown: each with its key (the address part),
	 * label and project count. Empty filters are dropped; "All" leads when on.
	 *
	 * @param array<string,mixed>            $s
	 * @param array<int,array<string,mixed>> $projects as from projects()
	 * @return array<int,array{key:string,label:string,count:int}>
	 */
	public static function filters( array $s, array $projects ): array {
		if ( 'yes' !== ( $s['show_filters'] ?? 'yes' ) ) {
			return [];
		}

		$out  = [];
		$seen = [ 'all' => true ];
		$has  = static fn( string $key ) => count( array_filter( $projects, static fn( $p ) => in_array( $key, $p['groups'], true ) ) );

		if ( 'library' === self::source( $s ) ) {
			foreach ( Projects::types() as $t ) {
				$count = $has( $t->slug );
				if ( $count && ! isset( $seen[ $t->slug ] ) ) {
					$seen[ $t->slug ] = true;
					$out[]            = [ 'key' => $t->slug, 'label' => $t->name, 'count' => $count, 'match' => $t->slug ];
				}
			}
		}

		foreach ( 'library' === self::source( $s ) ? [] : (array) ( $s['filters'] ?? [] ) as $f ) {
			$label = trim( (string) ( $f['label'] ?? '' ) );
			$key   = self::key( (string) ( $f['slug'] ?? '' ) );
			$key   = '' !== $key ? $key : self::key( $label );

			if ( '' === $label || '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;

			$count = count( array_filter( $projects, static fn( $p ) => in_array( self::key( $label ), $p['groups'], true ) || in_array( $key, $p['groups'], true ) ) );

			if ( $count ) {
				$out[] = [ 'key' => $key, 'label' => $label, 'count' => $count, 'match' => self::key( $label ) ];
			}
		}

		if ( 'yes' === ( $s['show_all'] ?? 'yes' ) && $projects ) {
			array_unshift( $out, [ 'key' => 'all', 'label' => (string) ( $s['all_label'] ?? 'All' ), 'count' => count( $projects ), 'match' => '' ] );
		}

		return $out;
	}

	/** Where the cards come from for these settings: 'library' or 'manual'. @param array<string,mixed> $s */
	public static function source( array $s ): string {
		$src = (string) ( $s['source'] ?? 'auto' );

		if ( 'auto' === $src ) {
			return Projects::count() ? 'library' : 'manual';
		}

		return 'library' === $src ? 'library' : 'manual';
	}

	/** How far the cards run: 'window', 'widget' or 'column'. @param array<string,mixed> $s */
	public static function run_out( array $s ): string {
		$run = in_array( $s['run_out'] ?? 'window', [ 'window', 'widget', 'column' ], true ) ? $s['run_out'] : 'window';

		// the old switch, turned off, meant: stay within the widget
		return 'window' === $run && 'yes' !== ( $s['bleed'] ?? 'yes' ) ? 'widget' : $run;
	}

	/** The panel's note on the library: how many projects, where to edit them. */
	private static function library_info(): string {
		if ( ! is_admin() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && ! wp_doing_ajax() ) {
			return '';
		}

		$n     = Projects::count();
		$types = count( Projects::types() );
		$list  = admin_url( 'edit.php?post_type=' . Projects::TYPE );
		$tax   = admin_url( 'edit-tags.php?taxonomy=' . Projects::TAX . '&post_type=' . Projects::TYPE );

		return $n
			/* translators: 1: number of projects, 2: number of types */
			? sprintf( esc_html__( '%1$d projects in %2$d types.', 'samanzavarian-widgets' ), $n, $types ) . ' <a href="' . esc_url( $list ) . '" target="_blank">' . esc_html__( 'Edit projects', 'samanzavarian-widgets' ) . '</a> · <a href="' . esc_url( $tax ) . '" target="_blank">' . esc_html__( 'Types and their order', 'samanzavarian-widgets' ) . '</a>'
			: esc_html__( 'The library is empty.', 'samanzavarian-widgets' ) . ' <a href="' . esc_url( $list ) . '" target="_blank">' . esc_html__( 'Open Projects', 'samanzavarian-widgets' ) . '</a> ' . esc_html__( '(one click adds the design\'s eight examples). Then reload this page.', 'samanzavarian-widgets' );
	}

	/** A filter name or ID as it appears in the address: "Colour grading" → colour-grading. */
	public static function key( string $text ): string {
		return sanitize_title( trim( $text ) );
	}

	/**
	 * Projects with something to show (a title), in order.
	 *
	 * @param array<string,mixed> $s
	 * @return array<int,array<string,mixed>>
	 */
	public static function projects( array $s ): array {
		if ( 'library' === self::source( $s ) ) {
			$types = array_values( array_filter( array_map( 'sanitize_title', (array) ( $s['lib_types'] ?? [] ) ) ) );

			return Projects::cards( $types, (string) ( $s['lib_order'] ?? 'menu' ), (int) ( $s['lib_limit'] ?? 12 ) );
		}

		$out = [];

		foreach ( (array) ( $s['projects'] ?? [] ) as $p ) {
			$title = trim( (string) ( $p['title'] ?? '' ) );

			if ( '' === $title ) {
				continue;
			}

			$ph = (int) ( $p['placeholder'] ?? 1 );

			$out[] = [
				'id'          => (string) ( $p['_id'] ?? '' ),
				'title'       => $title,
				'category'    => trim( (string) ( $p['category'] ?? '' ) ),
				'groups'      => array_values( array_filter( [ self::key( (string) ( $p['filter'] ?? '' ) ) ] ) ),
				'filter'      => trim( (string) ( $p['filter'] ?? '' ) ),
				'image'       => is_array( $p['image'] ?? null ) ? $p['image'] : [],
				'video'       => self::video_url( $p['video'] ?? [] ),
				'placeholder' => $ph >= 1 && $ph <= self::PLACEHOLDERS ? $ph : 1,
				'icon'        => $p['icon'] ?? [],
				'link'        => is_array( $p['link'] ?? null ) ? $p['link'] : [],
				'light'       => 'light' === ( $p['skin'] ?? 'dark' ),
				'featured'    => 'yes' === ( $p['featured'] ?? '' ),
			];
		}

		return $out;
	}

	/** @param mixed $media */
	private static function video_url( $media ): string {
		$url = is_array( $media ) ? trim( (string) ( $media['url'] ?? '' ) ) : '';

		return '' !== $url && preg_match( '/\.(mp4|webm|mov|m4v|ogv)(\?|#|$)/i', $url ) ? $url : ( '' !== $url && ! empty( $media['id'] ) && 0 === strpos( (string) get_post_mime_type( (int) $media['id'] ), 'video/' ) ? $url : '' );
	}

	/**
	 * Which project is the wide one for a filter: under "All" the first
	 * featured project (else the first), elsewhere the filter's first.
	 *
	 * @param array<int,array<string,mixed>> $projects
	 * @param array{key:string,match:string}|null $filter null: no filter (everything shows)
	 */
	public static function lead( array $projects, ?array $filter ): ?int {
		$all = ! $filter || 'all' === $filter['key'];

		if ( $all ) {
			foreach ( $projects as $i => $p ) {
				if ( $p['featured'] ) {
					return $i;
				}
			}

			return $projects ? 0 : null;
		}

		foreach ( $projects as $i => $p ) {
			if ( self::in_filter( $p, $filter ) ) {
				return $i;
			}
		}

		return null;
	}

	/**
	 * @param array<string,mixed>                 $p
	 * @param array{key:string,match:string}|null $filter
	 */
	public static function in_filter( array $p, ?array $filter ): bool {
		return ! $filter || 'all' === $filter['key'] || in_array( $filter['key'], $p['groups'], true ) || ( '' !== $filter['match'] && in_array( $filter['match'], $p['groups'], true ) );
	}

	/**
	 * A unique id for an element on the page; repeats get -2, -3…
	 */
	private static function unique_id( string $id, string $owner ): string {
		$base = $id;
		for ( $k = 2; isset( self::$used_ids[ $id ] ) && self::$used_ids[ $id ] !== $owner; $k++ ) {
			$id = $base . '-' . $k;
		}
		self::$used_ids[ $id ] = $owner;

		return $id;
	}

	/** {total} in the last card's title. */
	public static function end_title( array $s ): string {
		return str_replace( '{total}', (string) max( 0, (int) ( $s['end_total'] ?? 0 ) ), (string) ( $s['end_title'] ?? '' ) );
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
		$s        = $this->get_settings_for_display();
		$projects = self::projects( $s );

		if ( ! $projects ) {
			if ( Elementor::$instance->editor->is_edit_mode() ) {
				echo '<p class="sz-work__note">' . ( 'library' === self::source( $s )
					? esc_html__( 'No published projects in the library match. Add some under Projects in the WordPress menu, or check "Only these types".', 'samanzavarian-widgets' )
					: esc_html__( 'Add a project with a title to show the slider.', 'samanzavarian-widgets' ) ) . '</p>';
			}

			return;
		}

		$uid     = $this->get_id();
		$l       = self::labels( $s );
		$filters = self::filters( $s, $projects );
		$current = $filters[0] ?? null;
		$prefix  = self::key( (string) ( $s['hash_prefix'] ?? 'work' ) );
		$prefix  = '' !== $prefix ? $prefix : 'work';
		$wide    = 'yes' === ( $s['wide_lead'] ?? 'yes' ) ? self::lead( $projects, $current ) : null;
		$shown   = array_values( array_filter( $projects, static fn( $p ) => self::in_filter( $p, $current ) ) );
		$profile = is_array( $s['profile_url'] ?? null ) ? $s['profile_url'] : [];
		$tag     = in_array( $s['title_tag'] ?? 'h3', [ 'h2', 'h3', 'h4', 'p', 'span' ], true ) ? $s['title_tag'] : 'h3';

		$this->add_render_attribute(
			'root',
			[
				'class'          => [ 'sz-work', 'sz-w', 'run-' . self::run_out( $s ), 'window' === self::run_out( $s ) ? 'is-bleed' : '' ],
				'data-sz-work'   => '',
				'data-sz-hash'   => 'yes' === ( $s['update_url'] ?? 'yes' ) ? '1' : '0',
				'data-sz-track'  => 'yes' === ( $s['analytics'] ?? 'yes' ) ? '1' : '0',
				'data-sz-wide'   => 'yes' === ( $s['wide_lead'] ?? 'yes' ) ? '1' : '0',
				'data-sz-tilt'   => 'yes' === ( $s['tilt'] ?? 'yes' ) ? '1' : '0',
				'data-sz-labels' => wp_json_encode( [ 'of' => $l['of'], 'one' => $l['one'], 'many' => $l['many'], 'in' => $l['in'] ] ),
			]
		);
		?>
		<div <?php $this->print_render_attribute_string( 'root' ); ?>>
			<?php $this->render_top( $s, $filters, $uid, $prefix, $l, $profile ); ?>
			<div class="sz-work__box">
				<div class="sz-work__rail" tabindex="0" role="region" aria-roledescription="carousel" aria-label="<?php echo esc_attr( $l['rail'] ); ?>">
					<?php
					$n = 0;
					foreach ( $projects as $i => $p ) {
						$in = self::in_filter( $p, $current );
						$this->render_card( $p, $i, $in ? ++$n : 0, count( $shown ), $i === $wide, $in, $tag, $l, $profile );
					}
					$this->render_end( $s, $profile );
					?>
				</div>
			</div>
			<div class="sz-work__bar">
				<span class="sz-work__pos"><b>1</b> <?php echo esc_html( $l['of'] ); ?> <?php echo (int) count( $shown ); ?></span>
				<div class="sz-work__prog" aria-hidden="true"><i></i></div>
				<div class="sz-work__ctrl">
					<button type="button" class="sz-work__btn" data-dir="-1" aria-label="<?php echo esc_attr( $l['prev'] ); ?>" disabled><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m15 6-6 6 6 6"/></svg></button>
					<button type="button" class="sz-work__btn" data-dir="1" aria-label="<?php echo esc_attr( $l['next'] ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m9 6 6 6-6 6"/></svg></button>
				</div>
			</div>
			<p class="sz-work__sr sz-work__live" aria-live="polite"></p>
			<?php $this->render_notes( $projects, $filters, $s ); ?>
			<?php self::render_bleed_probe( $s ); ?>
		</div>
		<?php
	}

	/**
	 * The slide-out reaches the window's edges, a distance only the browser
	 * knows. Measured here, while the page is still being read, so the
	 * first paint already has it: set later by work.js, the rail moved under
	 * the visitor's eyes (layout shift 0.05). work.js keeps it up to date.
	 * Run later by a "delay JS" optimiser, currentScript is null: the try
	 * swallows it and work.js measures instead.
	 *
	 * @param array<string,mixed> $s
	 */
	private static function render_bleed_probe( array $s ): void {
		if ( 'window' !== self::run_out( $s ) ) {
			return;
		}
		?>
		<script>(function(s){try{var r=s.parentNode,b=r.getBoundingClientRect(),w=document.documentElement.clientWidth;r.style.setProperty('--szw-bl',Math.max(0,Math.floor(b.left))+'px');r.style.setProperty('--szw-br',Math.max(0,Math.floor(w-b.right))+'px')}catch(e){}})(document.currentScript);</script>
		<?php
	}

	/**
	 * @param array<string,mixed>                $s
	 * @param array<int,array<string,mixed>>     $filters
	 * @param array<string,string>               $l
	 * @param array<string,mixed>                $profile
	 */
	private function render_top( array $s, array $filters, string $uid, string $prefix, array $l, array $profile ): void {
		$show_profile = 'yes' === ( $s['show_profile'] ?? 'yes' ) && '' !== trim( (string) ( $profile['url'] ?? '' ) );

		if ( count( $filters ) < 2 && ! $show_profile ) {
			return;
		}

		$counts = 'yes' === ( $s['show_counts'] ?? 'yes' );
		?>
		<div class="sz-work__top">
			<?php if ( count( $filters ) > 1 ) : ?>
				<div class="sz-work__fwrap">
					<div class="sz-work__filters" role="group" aria-label="<?php echo esc_attr( $l['filters'] ); ?>">
						<span class="sz-work__thumb" aria-hidden="true"></span>
						<?php foreach ( $filters as $i => $f ) : ?>
							<button type="button" class="sz-work__filter" id="<?php echo esc_attr( self::unique_id( $prefix . '-' . $f['key'], $uid . '-' . $f['key'] ) ); ?>" data-f="<?php echo esc_attr( $f['key'] ); ?>" data-match="<?php echo esc_attr( $f['match'] ); ?>" data-label="<?php echo esc_attr( $f['label'] ); ?>" aria-pressed="<?php echo 0 === $i ? 'true' : 'false'; ?>"><?php echo esc_html( $f['label'] ); ?><?php if ( $counts ) : ?><sup aria-hidden="true"><?php echo (int) $f['count']; ?></sup><span class="sz-work__sr">, <?php echo (int) $f['count']; ?></span><?php endif; ?></button>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
			<?php
			if ( $show_profile ) {
				$this->add_link_attributes( 'profile', $profile );
				$this->add_render_attribute( 'profile', [ 'class' => 'sz-work__profile', 'data-sz-out' => 'link' ] );
				$icon = Icon_Library::html( $s['profile_icon'] ?? [] );
				?>
				<a <?php $this->print_render_attribute_string( 'profile' ); ?>>
					<?php if ( '' !== $icon ) : ?><span class="sz-app sz-app--orange" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's icon renderer or our static glyph map ?></span><?php endif; ?>
					<span class="sz-work__profile-t"><?php echo esc_html( (string) ( $s['profile_label'] ?? '' ) ); ?></span>
				</a>
				<?php
			}
			?>
		</div>
		<?php
	}

	/**
	 * @param array<string,mixed>  $p
	 * @param array<string,string> $l
	 * @param array<string,mixed>  $profile
	 */
	private function render_card( array $p, int $index, int $n, int $total, bool $wide, bool $shown, string $tag, array $l, array $profile ): void {
		$key  = 'card-' . $index;
		$link = ! empty( $p['link']['url'] ) ? $p['link'] : $profile;
		$href = '' !== trim( (string) ( $link['url'] ?? '' ) );

		if ( $href ) {
			$this->add_link_attributes( $key, $link );
		}

		$this->add_render_attribute(
			$key,
			[
				'class'                => [
					'sz-work__card',
					$p['light'] ? 'is-light' : 'is-dark',
					$wide ? 'is-wide' : '',
					$p['id'] ? 'elementor-repeater-item-' . $p['id'] : '',
				],
				'data-g'               => implode( ' ', $p['groups'] ),
				'data-i'               => (string) $index,
				'aria-roledescription' => 'slide',
				'aria-label'           => $n ? sprintf( '%d %s %d: %s', $n, $l['of'], $total, $p['title'] ) : $p['title'],
			]
		);

		if ( ! $shown ) {
			$this->add_render_attribute( $key, 'hidden', 'hidden' );
		}
		if ( ! empty( $p['post'] ) ) {
			$this->add_render_attribute( $key, 'data-project', (string) $p['post'] );
		}

		$el   = $href ? 'a' : 'div';
		$icon = Icon_Library::html( $p['icon'] );
		?>
		<<?php echo $el; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a or div ?> <?php $this->print_render_attribute_string( $key ); ?>>
			<span class="sz-work__media"><?php echo self::media( $p, $index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts ?></span>
			<?php if ( $p['featured'] ) : ?><span class="sz-work__flag"><?php echo esc_html( $l['featured'] ); ?></span><?php endif; ?>
			<?php if ( $p['video'] ) : ?><span class="sz-work__pv" aria-hidden="true"><i></i><?php echo esc_html( $l['preview'] ); ?></span><?php endif; ?>
			<span class="sz-work__head">
				<?php if ( '' !== $p['category'] ) : ?><small class="sz-work__cat"><?php echo esc_html( $p['category'] ); ?></small><?php endif; ?>
				<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- whitelisted ?> class="sz-work__title"><?php echo esc_html( $p['title'] ); ?></<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- whitelisted ?>>
			</span>
			<span class="sz-work__cbar">
				<?php if ( '' !== $icon ) : ?><span class="sz-work__icon sz-app<?php echo $p['light'] ? '' : ' sz-app--orange'; ?>" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's icon renderer or our static glyph map ?></span><?php endif; ?>
				<span class="sz-work__cbar-t"><b><?php echo esc_html( '' !== $p['category'] ? $p['category'] : $p['title'] ); ?></b><?php echo esc_html( $l['note'] ); ?></span>
				<?php if ( $href ) : ?><em class="sz-work__view"><?php echo esc_html( $l['view'] ); ?></em><?php endif; ?>
			</span>
		</<?php echo $el; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a or div ?>>
		<?php
	}

	/**
	 * The card's picture: the image, else the chosen gradient; plus the
	 * preview video, which only gets its src when first hovered.
	 *
	 * @param array<string,mixed> $p
	 */
	public static function media( array $p, int $index ): string {
		$id   = (int) ( $p['image']['id'] ?? 0 );
		$url  = (string) ( $p['image']['url'] ?? '' );
		$load = $index < 3 ? 'eager' : 'lazy';
		$html = '';

		if ( $id && wp_attachment_is_image( $id ) ) {
			$html = (string) wp_get_attachment_image(
				$id,
				'large',
				false,
				[
					'class'    => 'sz-work__img',
					'alt'      => '',
					'loading'  => $load,
					'decoding' => 'async',
					'sizes'    => '(max-width: 640px) 80vw, 700px',
				]
			);
		} elseif ( '' !== $url ) {
			$html = '<img class="sz-work__img" src="' . esc_url( $url ) . '" alt="" loading="' . $load . '" decoding="async">';
		}

		if ( '' === $html ) {
			$html = '<i class="sz-work__mesh sz-work__mesh--' . (int) $p['placeholder'] . '"></i>';
		}

		if ( $p['video'] ) {
			$html .= '<video class="sz-work__video" data-src="' . esc_url( $p['video'] ) . '" muted loop playsinline preload="none" aria-hidden="true" tabindex="-1"></video>';
		}

		return $html;
	}

	/**
	 * @param array<string,mixed> $s
	 * @param array<string,mixed> $profile
	 */
	private function render_end( array $s, array $profile ): void {
		if ( 'yes' !== ( $s['show_end'] ?? 'yes' ) ) {
			return;
		}

		$link = ! empty( $s['end_link']['url'] ) ? $s['end_link'] : $profile;
		$href = '' !== trim( (string) ( $link['url'] ?? '' ) );
		$el   = $href ? 'a' : 'div';

		if ( $href ) {
			$this->add_link_attributes( 'end', $link );
		}
		$this->add_render_attribute( 'end', [ 'class' => 'sz-work__card sz-work__end', 'data-g' => '*', 'data-sz-out' => 'end_card', 'aria-roledescription' => 'slide' ] );

		$icon   = Icon_Library::html( $s['end_icon'] ?? [] );
		$text   = trim( (string) ( $s['end_text'] ?? '' ) );
		$button = trim( (string) ( $s['end_button'] ?? '' ) );
		?>
		<<?php echo $el; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a or div ?> <?php $this->print_render_attribute_string( 'end' ); ?>>
			<?php if ( '' !== $icon ) : ?><span class="sz-work__end-icon sz-app sz-app--orange" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's icon renderer or our static glyph map ?></span><?php endif; ?>
			<span class="sz-work__end-title"><?php echo esc_html( self::end_title( $s ) ); ?></span>
			<?php if ( '' !== $text ) : ?><span class="sz-work__end-text"><?php echo esc_html( $text ); ?></span><?php endif; ?>
			<?php if ( '' !== $button && $href ) : ?><span class="sz-work__end-btn"><?php echo esc_html( $button ); ?></span><?php endif; ?>
		</<?php echo $el; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a or div ?>>
		<?php
	}

	/**
	 * Editor-only hints for things that would otherwise fail silently.
	 *
	 * @param array<int,array<string,mixed>> $projects
	 * @param array<int,array<string,mixed>> $filters
	 * @param array<string,mixed>            $s
	 */
	private function render_notes( array $projects, array $filters, array $s ): void {
		if ( ! Elementor::$instance->editor->is_edit_mode() ) {
			return;
		}

		foreach ( self::notes( $projects, $filters, $s ) as $note ) {
			echo '<p class="sz-work__note">' . esc_html( $note ) . '</p>';
		}
	}

	/**
	 * @param array<int,array<string,mixed>> $projects
	 * @param array<int,array<string,mixed>> $filters
	 * @param array<string,mixed>            $s
	 * @return string[]
	 */
	public static function notes( array $projects, array $filters, array $s ): array {
		$notes = [];

		if ( 'yes' === ( $s['show_filters'] ?? 'yes' ) && 'manual' === self::source( $s ) ) {
			$names = array_column( $filters, 'match' );
			$keys  = array_column( $filters, 'key' );
			$lost  = [];

			foreach ( $projects as $p ) {
				if ( $p['groups'] && ! array_intersect( $p['groups'], $names ) && ! array_intersect( $p['groups'], $keys ) ) {
					$lost[] = $p['title'] . ' (' . $p['filter'] . ')';
				}
			}

			if ( $lost ) {
				/* translators: %s: list of projects */
				$notes[] = sprintf( __( 'Not in any filter, only under "All": %s. Check the spelling against the Filters section.', 'samanzavarian-widgets' ), implode( ', ', $lost ) );
			}
		}

		if ( 'yes' === ( $s['show_end'] ?? 'yes' ) || 'yes' === ( $s['show_profile'] ?? 'yes' ) ) {
			$url = (string) ( $s['profile_url']['url'] ?? '' );

			if ( '' === trim( $url ) || preg_match( '#^https?://(www\.)?behance\.net/?$#i', trim( $url ) ) ) {
				$notes[] = __( 'Set your profile URL (Profile link section): it still points to behance.net itself.', 'samanzavarian-widgets' );
			}
		}

		return $notes;
	}
}
