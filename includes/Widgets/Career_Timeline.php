<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Plugin as Elementor;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Samanzavarian_Widgets\Icon_Library;
use Samanzavarian_Widgets\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Career Timeline — the career laid out like an edit: one clip per role on
 * an editor's timeline, a "Now" playhead, and an inspector card above that
 * shows the selected role.
 *
 * Dates are typed as "2022" or "2022-03"; an empty end means "now". Clips
 * are placed on the ruler from those dates and packed into as few lanes as
 * overlapping roles need (the longest run of roles on the first lane), so
 * nobody assigns tracks by hand. Roles ending before the "grouped" year sit
 * in a hatched "Before 2022" zone and are labelled that way.
 *
 * Every role's inspector is in the page (indexable, no script needed to read
 * the selected one) and all share one grid cell, so the card is as tall as
 * its longest role and switching never moves the timeline below. The first
 * time the box scrolls into view the playhead runs from the first role to
 * "Now", laying each clip down as it passes.
 */
class Career_Timeline extends Widget_Base {

	/** The design's roles: company, role, start, end ('' = now), icon, text, tags. */
	private const ROLES = [
		[ 'Charkheh Studio', 'Photographer, Videographer and Designer', '2020-03', '2021-05', 'pen', 'Photography and video production, plus logo design and visual identity work for studio clients.', "Photo and video\nLogo design\nVisual identity\nGraphic design" ],
		[ 'Chonan Studio', 'Assistant Photographer and Videographer', '2020-11', '2021-12', 'cam', 'Commercial photo and video production for major corporate clients, working as the primary photographer and videographer on selected projects.', "Commercial photo and video\nLead shooter on selected work\nCorporate clients" ],
		[ 'Beniix', 'Product Photographer', '2022', '2023', 'box', 'Commercial product photography for the website and social media, from the shoot through post-production.', "Product photography\nWeb and social\nPost-production" ],
		[ 'Polytech', 'Visual Content Producer', '2023', '2024', 'factory', 'Brand and sales-focused video production and industrial photography, from script to final edit.', "Brand and sales films\nIndustrial photography\nScripting\nFilming\nEditing" ],
		[ 'Rizom Design', 'Visual Content Producer', '2024', '2026', 'video', 'Content strategy, scripting and directing, video production, photography and retouching, while managing the content team.', "Content strategy\nScripting and directing\nVideo production\nPhotography and retouching\nTeam lead" ],
		[ 'Pely Media', 'AI Creative Producer', '2026', '', 'spark', 'AI-driven image and video production, alongside lead videography and editing, social media management and project management.', "AI image and video\nLead videography\nEditing\nSocial media\nProject management" ],
	];

	public function get_name(): string {
		return 'sz-career';
	}

	public function get_title(): string {
		return esc_html__( 'Career Timeline', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-time-line';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'career', 'timeline', 'experience', 'resume', 'cv', 'jobs', 'roles', 'history' ];
	}

	public function get_style_depends(): array {
		return [ 'sz-career' ];
	}

	public function get_script_depends(): array {
		return [ 'sz-career' ];
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
		$this->start_controls_section( 'section_roles', [ 'label' => esc_html__( 'Roles', 'samanzavarian-widgets' ) ] );

		$r = new Repeater();
		$r->add_control( 'company', [ 'label' => esc_html__( 'Company', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => esc_html__( 'Company', 'samanzavarian-widgets' ), 'label_block' => true, 'dynamic' => [ 'active' => true ] ] );
		$r->add_control( 'role', [ 'label' => esc_html__( 'Role', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => esc_html__( 'Role', 'samanzavarian-widgets' ), 'label_block' => true, 'dynamic' => [ 'active' => true ] ] );
		$r->add_control( 'start', [ 'label' => esc_html__( 'Start', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => '2024', 'placeholder' => '2022-03', 'ai' => [ 'active' => false ] ] );
		$r->add_control( 'end', [ 'label' => esc_html__( 'End', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'placeholder' => esc_html__( 'Empty = now', 'samanzavarian-widgets' ), 'ai' => [ 'active' => false ], 'description' => esc_html__( 'A year (2023) or a year and month (2023-06). Leave End empty for the role you are in now.', 'samanzavarian-widgets' ) ] );
		$r->add_control( 'when', [ 'label' => esc_html__( 'Dates label', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'placeholder' => esc_html__( 'Automatic', 'samanzavarian-widgets' ), 'description' => esc_html__( 'Written from the dates ("2022 to 2023", "2026 to now", "Before 2022"); type here to say it differently.', 'samanzavarian-widgets' ) ] );
		$r->add_control( 'icon', [ 'label' => esc_html__( 'Icon', 'samanzavarian-widgets' ), 'type' => Controls_Manager::ICONS, 'default' => Icon_Library::value( 'brief' ) ] );
		$r->add_control( 'text', [ 'label' => esc_html__( 'What you did', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 3, 'dynamic' => [ 'active' => true ] ] );
		$r->add_control( 'tags', [ 'label' => esc_html__( 'Skills', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 4, 'description' => esc_html__( 'One per line.', 'samanzavarian-widgets' ) ] );

		$this->add_control(
			'roles',
			[
				'label'       => esc_html__( 'Roles', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => array_map(
					static fn( $j ) => [ 'company' => $j[0], 'role' => $j[1], 'start' => $j[2], 'end' => $j[3], 'icon' => Icon_Library::value( $j[4] ), 'text' => $j[5], 'tags' => $j[6] ],
					self::ROLES
				),
				'title_field' => '{{{ company }}} <span style="opacity:.5;margin-left:6px">{{{ start }}}</span>',
				'button_text' => esc_html__( 'Add role', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control(
			'roles_note',
			[
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Order does not matter: clips are placed by their dates, and roles that overlap get a second lane on their own. The role you are in now (no End) is selected when the page opens; the arrows step through roles by date.', 'samanzavarian-widgets' ),
				'content_classes' => 'elementor-descriptor',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section( 'section_timeline', [ 'label' => esc_html__( 'Timeline', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'title', [ 'label' => esc_html__( 'Title', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => esc_html__( 'Career timeline', 'samanzavarian-widgets' ), 'label_block' => true ] );
		$this->add_control( 'hint', [ 'label' => esc_html__( 'Hint', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => esc_html__( 'Select a clip to see the role', 'samanzavarian-widgets' ), 'label_block' => true, 'description' => esc_html__( 'Beside the title on wide screens.', 'samanzavarian-widgets' ) ] );
		$this->add_control(
			'group_before',
			[
				'label'       => esc_html__( 'Group earlier years', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => 2022,
				'min'         => 0,
				'description' => esc_html__( 'Years before this one share one hatched "Before 2022" stretch of the ruler, and roles that ended by then are labelled that way. 0 shows every year.', 'samanzavarian-widgets' ),
			]
		);
		$this->add_control( 'arrival', [ 'label' => esc_html__( 'Play the timeline on arrival', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'description' => esc_html__( 'The first time it scrolls into view, the playhead runs to "Now", laying each clip down as it passes. Never with reduced motion, or when the box is already on screen as the page opens.', 'samanzavarian-widgets' ) ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_labels', [ 'label' => esc_html__( 'Labels', 'samanzavarian-widgets' ) ] );
		foreach ( self::label_defaults() as $key => $def ) {
			$this->add_control( 'label_' . $key, [ 'label' => $def[1], 'type' => Controls_Manager::TEXT, 'default' => $def[0], 'ai' => [ 'active' => false ] ] );
		}
		$this->end_controls_section();

		$this->style_controls();
	}

	/** @return array<string,array{0:string,1:string}> */
	private static function label_defaults(): array {
		return [
			'now'    => [ __( 'Now', 'samanzavarian-widgets' ), __( '"Now" (playhead and dates)', 'samanzavarian-widgets' ) ],
			'to'     => [ __( 'to', 'samanzavarian-widgets' ), __( '"to" in dates', 'samanzavarian-widgets' ) ],
			'before' => [ __( 'Before', 'samanzavarian-widgets' ), __( '"Before" (grouped years)', 'samanzavarian-widgets' ) ],
			'of'     => [ __( 'of', 'samanzavarian-widgets' ), __( '"of" in the counter', 'samanzavarian-widgets' ) ],
			'prev'   => [ __( 'Earlier role', 'samanzavarian-widgets' ), __( 'Earlier button (screen readers)', 'samanzavarian-widgets' ) ],
			'next'   => [ __( 'Later role', 'samanzavarian-widgets' ), __( 'Later button (screen readers)', 'samanzavarian-widgets' ) ],
			'roles'  => [ __( 'Roles', 'samanzavarian-widgets' ), __( 'The clips (screen readers)', 'samanzavarian-widgets' ) ],
			'swipe'  => [ __( 'Swipe the timeline for earlier roles', 'samanzavarian-widgets' ), __( 'Swipe hint (phones)', 'samanzavarian-widgets' ) ],
		];
	}

	private function var_slider( string $id, string $label, string $var, $ph, array $range, array $units = [ 'px', 'custom' ], array $device = [ null, null ] ): void {
		$args = [
			'label'       => $label,
			'type'        => Controls_Manager::SLIDER,
			'placeholder' => $ph,
			'size_units'  => $units,
			'range'       => [ $units[0] => $range ],
			'selectors'   => [ '{{WRAPPER}} .sz-cr' => $var . ': {{SIZE}}{{UNIT}};' ],
		];
		foreach ( [ 'tablet_default', 'mobile_default' ] as $n => $key ) {
			if ( null !== $device[ $n ] ) {
				$args[ $key ] = [ 'size' => $device[ $n ], 'unit' => $units[0] ];
			}
		}
		$this->add_responsive_control( $id, $args );
	}

	private function var_color( string $id, string $label, string $var ): void {
		$this->add_control( $id, [ 'label' => $label, 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .sz-cr' => $var . ': {{VALUE}};' ] ] );
	}

	private function style_controls(): void {
		$this->start_controls_section( 'style_box', [ 'label' => esc_html__( 'Box', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		$this->var_color( 'box_bg', esc_html__( 'Background', 'samanzavarian-widgets' ), '--szc-bg' );
		$this->var_color( 'box_glow', esc_html__( 'Glow (top right)', 'samanzavarian-widgets' ), '--szc-glow' );
		$this->var_slider( 'radius', esc_html__( 'Radius', 'samanzavarian-widgets' ), '--szc-radius', Control_Helpers::ph( 32 ), [ 'min' => 0, 'max' => 56 ], [ 'px', 'custom' ], [ null, 26 ] );
		$this->var_color( 'bar_text', esc_html__( 'Bar and ruler text', 'samanzavarian-widgets' ), '--szc-muted' );
		$this->var_color( 'accent', esc_html__( 'Playhead and selected clip', 'samanzavarian-widgets' ), '--szc-accent' );
		$this->end_controls_section();

		$this->start_controls_section( 'style_insp', [ 'label' => esc_html__( 'Inspector card', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		$this->var_color( 'insp_bg', esc_html__( 'Background', 'samanzavarian-widgets' ), '--szc-card' );
		$this->var_slider( 'insp_radius', esc_html__( 'Radius', 'samanzavarian-widgets' ), '--szc-card-radius', Control_Helpers::ph( 24 ), [ 'min' => 0, 'max' => 48 ] );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'role_typo', 'label' => esc_html__( 'Role', 'samanzavarian-widgets' ), 'selector' => '{{WRAPPER}} .sz-cr__role' ] );
		$this->var_color( 'role_color', esc_html__( 'Role', 'samanzavarian-widgets' ), '--szc-ink' );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'text_typo', 'label' => esc_html__( 'Text', 'samanzavarian-widgets' ), 'selector' => '{{WRAPPER}} .sz-cr__text' ] );
		$this->var_color( 'text_color', esc_html__( 'Company and text', 'samanzavarian-widgets' ), '--szc-text' );
		$this->var_color( 'chip_bg', esc_html__( 'Dates and skills', 'samanzavarian-widgets' ), '--szc-chip' );
		$this->end_controls_section();

		$this->start_controls_section( 'style_clips', [ 'label' => esc_html__( 'Clips', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		$this->var_color( 'clip_bg', esc_html__( 'Clip', 'samanzavarian-widgets' ), '--szc-clip' );
		$this->var_color( 'clip_hover', esc_html__( 'Clip on hover', 'samanzavarian-widgets' ), '--szc-clip-hover' );
		$this->var_color( 'clip_on_text', esc_html__( 'Selected clip text', 'samanzavarian-widgets' ), '--szc-on-ink' );
		$this->var_slider( 'clip_h', esc_html__( 'Clip height', 'samanzavarian-widgets' ), '--szc-clip-h', Control_Helpers::ph( 58 ), [ 'min' => 40, 'max' => 90 ], [ 'px' ], [ null, 62 ] );
		$this->var_slider( 'year_w', esc_html__( 'Width of a year', 'samanzavarian-widgets' ), '--szc-year', Control_Helpers::ph( 126 ), [ 'min' => 60, 'max' => 300 ], [ 'px' ], [ null, 150 ] );
		$this->add_control( 'year_note', [ 'type' => Controls_Manager::RAW_HTML, 'raw' => esc_html__( 'The least room a year gets. Wider boxes stretch the timeline to fit; narrower ones scroll sideways.', 'samanzavarian-widgets' ), 'content_classes' => 'elementor-descriptor' ] );
		$this->end_controls_section();
	}

	/* =================================================================
	 * The timeline, worked out
	 * ================================================================= */

	/** "2022" → 2022.0 (start) / 2022.0 (end); "2022-03" → 2022 + 2/12 (start) / 2022 + 3/12 (end). */
	public static function point( string $date, bool $end ): ?float {
		if ( ! preg_match( '/^\s*(\d{4})(?:\s*[-\/.]\s*(\d{1,2}))?\s*$/', $date, $m ) ) {
			return null;
		}
		$y = (int) $m[1];
		$mo = isset( $m[2] ) ? max( 1, min( 12, (int) $m[2] ) ) : 0;

		if ( ! $mo ) {
			return (float) $y;
		}

		return $y + ( $end ? $mo : $mo - 1 ) / 12;
	}

	/** Today, as a year with a fraction. */
	public static function today(): float {
		$t = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- a local calendar position
		return (int) gmdate( 'Y', $t ) + ( (int) gmdate( 'z', $t ) ) / 366;
	}

	/**
	 * The roles, placed: start and end on the ruler, their lane, their label,
	 * in date order.
	 *
	 * @param array<string,mixed> $s
	 * @return array{roles:array<int,array<string,mixed>>,t0:int,t1:int,now:float,group:int,lanes:int,current:int}
	 */
	public static function layout( array $s, ?float $now = null ): array {
		$now   = $now ?? self::today();
		$l     = self::labels( $s );
		$group = max( 0, (int) ( $s['group_before'] ?? 2022 ) );
		$roles = [];

		foreach ( (array) ( $s['roles'] ?? [] ) as $k => $it ) {
			$co    = trim( (string) ( $it['company'] ?? '' ) );
			$start = self::point( (string) ( $it['start'] ?? '' ), false );
			if ( '' === $co || null === $start ) {
				continue;
			}
			$end_raw = trim( (string) ( $it['end'] ?? '' ) );
			$end     = '' === $end_raw ? null : self::point( $end_raw, true );
			$live    = null === $end;
			$e       = $live ? max( $now, $start + 1 / 12 ) : max( (float) $end, $start + 1 / 12 );
			$sy      = (int) floor( $start );
			$ey      = (int) floor( $e ); // "2023" and "2023-06" both read 2023

			$when = trim( (string) ( $it['when'] ?? '' ) );
			if ( '' === $when ) {
				if ( $live ) {
					$when = $sy . ' ' . $l['to'] . ' ' . mb_strtolower( $l['now'] );
				} elseif ( $group && $e <= $group ) {
					$when = $l['before'] . ' ' . $group;
				} elseif ( $ey <= $sy ) {
					$when = (string) $sy;
				} else {
					$when = $sy . ' ' . $l['to'] . ' ' . $ey;
				}
			}

			$tags = array_values( array_filter( array_map( 'trim', preg_split( '/\R/u', (string) ( $it['tags'] ?? '' ) ) ?: [] ) ) );

			$roles[] = [
				'k'    => (int) $k,
				'id'   => (string) ( $it['_id'] ?? '' ),
				'co'   => $co,
				'role' => trim( (string) ( $it['role'] ?? '' ) ),
				's'    => (float) $start,
				'e'    => (float) $e,
				'live' => $live,
				'when' => $when,
				'icon' => $it['icon'] ?? [],
				'text' => trim( (string) ( $it['text'] ?? '' ) ),
				'tags' => $tags,
			];
		}

		if ( ! $roles ) {
			return [ 'roles' => [], 't0' => 0, 't1' => 0, 'now' => $now, 'group' => 0, 'lanes' => 0, 'current' => -1 ];
		}

		usort( $roles, static fn( $a, $b ) => $a['s'] <=> $b['s'] ?: $a['e'] <=> $b['e'] );

		// lanes: each clip goes to the lane whose last clip ended latest without overlapping
		// (best fit keeps one unbroken run of roles together); a new lane only when all overlap
		$ends = [];
		foreach ( $roles as &$r ) {
			$best = -1;
			foreach ( $ends as $n => $end ) {
				if ( $end <= $r['s'] + .001 && ( -1 === $best || $end > $ends[ $best ] ) ) {
					$best = $n;
				}
			}
			if ( -1 === $best ) {
				$best = count( $ends );
			}
			$ends[ $best ] = $r['e'];
			$r['lane']     = $best;
		}
		unset( $r );

		// the busiest lane on top
		$count = array_count_values( array_column( $roles, 'lane' ) );
		arsort( $count );
		$rank = array_flip( array_keys( $count ) );
		foreach ( $roles as &$r ) {
			$r['lane'] = $rank[ $r['lane'] ];
		}
		unset( $r );

		$t0  = (int) floor( min( array_column( $roles, 's' ) ) );
		$max = max( $now, max( array_column( $roles, 'e' ) ) );
		$t1  = (int) ceil( $max + .15 );
		if ( $group && ( $group <= $t0 || $group >= $t1 ) ) {
			$group = 0;
		}

		$current = count( $roles ) - 1;
		foreach ( $roles as $i => $r ) {
			if ( $r['live'] ) {
				$current = $i;
			}
		}

		return [ 'roles' => $roles, 't0' => $t0, 't1' => $t1, 'now' => $now, 'group' => $group, 'lanes' => count( $count ), 'current' => $current ];
	}

	/** @param array<string,mixed> $s @return array<string,string> */
	private static function labels( array $s ): array {
		$l = [];
		foreach ( self::label_defaults() as $k => $def ) {
			$v       = trim( (string) ( $s[ 'label_' . $k ] ?? '' ) );
			$l[ $k ] = '' !== $v ? $v : $def[0];
		}
		return $l;
	}

	private static function pct( float $y, int $t0, int $t1 ): string {
		return rtrim( rtrim( number_format( ( $y - $t0 ) / ( $t1 - $t0 ) * 100, 3, '.', '' ), '0' ), '.' ) . '%';
	}

	/* =================================================================
	 * Render
	 * ================================================================= */

	protected function render(): void {
		$s   = $this->get_settings_for_display();
		$lay = self::layout( $s );

		if ( ! $lay['roles'] ) {
			return;
		}

		$l     = self::labels( $s );
		$uid   = 'szc-' . $this->get_id();
		$roles = $lay['roles'];
		$n     = count( $roles );
		$cur   = $lay['current'];
		$t0    = $lay['t0'];
		$t1    = $lay['t1'];
		$title = trim( (string) ( $s['title'] ?? '' ) );
		$hint  = trim( (string) ( $s['hint'] ?? '' ) );
		$now   = min( $lay['now'], $t1 );

		$this->add_render_attribute(
			'root',
			[
				'class'       => 'sz-cr sz-w',
				'data-sz-cr'  => '',
				'data-arrive' => 'yes' === ( $s['arrival'] ?? 'yes' ) ? '1' : '0',
				'data-of'     => $l['of'],
				'style'       => '--szc-years:' . ( $t1 - $t0 ) . ';--szc-lanes:' . $lay['lanes'],
			]
		);
		?>
		<div <?php $this->print_render_attribute_string( 'root' ); ?>>
			<div class="sz-cr__bar">
				<span class="sz-cr__dots" aria-hidden="true"><i></i><i></i><i></i></span>
				<?php if ( '' !== $title ) : ?><b class="sz-cr__title"><?php echo esc_html( $title ); ?></b><?php endif; ?>
				<?php if ( '' !== $hint && $n > 1 ) : ?><span class="sz-cr__hint"><?php echo esc_html( $hint ); ?></span><?php endif; ?>
				<?php if ( $n > 1 ) : ?>
					<div class="sz-cr__nav">
						<span class="sz-cr__pos" aria-live="polite"><?php echo esc_html( ( $cur + 1 ) . ' ' . $l['of'] . ' ' . $n ); ?></span>
						<button type="button" class="sz-cr__btn" data-dir="-1" aria-label="<?php echo esc_attr( $l['prev'] ); ?>"<?php disabled( 0 === $cur ); ?>><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m15 6-6 6 6 6"/></svg></button>
						<button type="button" class="sz-cr__btn" data-dir="1" aria-label="<?php echo esc_attr( $l['next'] ); ?>"<?php disabled( $n - 1 === $cur ); ?>><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m9 6 6 6-6 6"/></svg></button>
					</div>
				<?php endif; ?>
			</div>

			<div class="sz-cr__insp">
				<?php foreach ( $roles as $i => $r ) : ?>
					<?php $on = $i === $cur; ?>
					<article class="sz-cr__panel<?php echo $on ? ' is-on' : ''; ?><?php echo $r['id'] ? ' elementor-repeater-item-' . esc_attr( $r['id'] ) : ''; ?>" id="<?php echo esc_attr( $uid . '-p' . $i ); ?>" role="tabpanel" aria-labelledby="<?php echo esc_attr( $uid . '-c' . $i ); ?>"<?php echo $on ? '' : ' aria-hidden="true" inert'; ?>>
						<div class="sz-cr__who">
							<?php $icon = Icon_Library::html( $r['icon'] ); ?>
							<?php if ( '' !== $icon ) : ?><span class="sz-cr__tile sz-app<?php echo $r['live'] ? ' sz-app--orange' : ''; ?>" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon renderer ?></span><?php endif; ?>
							<div class="sz-cr__id">
								<?php if ( '' !== $r['role'] ) : ?><h3 class="sz-cr__role"><?php echo esc_html( $r['role'] ); ?></h3><?php endif; ?>
								<span class="sz-cr__co"><?php echo esc_html( $r['co'] ); ?></span>
								<span class="sz-cr__when<?php echo $r['live'] ? ' is-live' : ''; ?>"><?php echo esc_html( $r['when'] ); ?></span>
							</div>
						</div>
						<?php if ( '' !== $r['text'] || $r['tags'] ) : ?>
							<div class="sz-cr__about">
								<?php if ( '' !== $r['text'] ) : ?><p class="sz-cr__text"><?php echo esc_html( $r['text'] ); ?></p><?php endif; ?>
								<?php if ( $r['tags'] ) : ?>
									<ul class="sz-cr__tags"><?php foreach ( $r['tags'] as $t ) : ?><li><?php echo esc_html( $t ); ?></li><?php endforeach; ?></ul>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>

			<div class="sz-cr__scroll">
				<div class="sz-cr__tracks" role="tablist" aria-label="<?php echo esc_attr( $l['roles'] ); ?>">
					<div class="sz-cr__ruler" aria-hidden="true">
						<?php
						$first = $lay['group'] ? $lay['group'] : $t0;
						if ( $lay['group'] ) {
							echo '<span style="left:0">' . esc_html( $l['before'] . ' ' . $lay['group'] ) . '</span>';
						}
						for ( $y = $first; $y < $t1; $y++ ) {
							echo '<span style="left:' . esc_attr( self::pct( $y, $t0, $t1 ) ) . '">' . (int) $y . '</span>';
						}
						?>
					</div>
					<?php if ( $lay['group'] ) : ?><span class="sz-cr__zone" aria-hidden="true" style="--w:<?php echo esc_attr( self::pct( $lay['group'], $t0, $t1 ) ); ?>"></span><?php endif; ?>
					<?php for ( $lane = 0; $lane < $lay['lanes']; $lane++ ) : ?>
						<div class="sz-cr__lane" role="presentation">
							<?php foreach ( $roles as $i => $r ) : ?>
								<?php
								if ( $r['lane'] !== $lane ) {
									continue;
								}
								$on = $i === $cur;
								?>
								<button type="button" class="sz-cr__clip" role="tab" id="<?php echo esc_attr( $uid . '-c' . $i ); ?>" data-i="<?php echo (int) $i; ?>" data-k="<?php echo (int) $r['k']; ?>" data-s="<?php echo esc_attr( (string) round( $r['s'], 4 ) ); ?>" aria-controls="<?php echo esc_attr( $uid . '-p' . $i ); ?>" aria-selected="<?php echo $on ? 'true' : 'false'; ?>" tabindex="<?php echo $on ? '0' : '-1'; ?>" style="left:<?php echo esc_attr( self::pct( $r['s'], $t0, $t1 ) ); ?>;width:calc(<?php echo esc_attr( self::pct( min( $r['e'], $t1 ), $t0, $t1 ) ); ?> - <?php echo esc_attr( self::pct( $r['s'], $t0, $t1 ) ); ?> - 4px)">
									<b><?php echo esc_html( $r['co'] ); ?></b><small><?php echo esc_html( $r['when'] ); ?></small>
								</button>
							<?php endforeach; ?>
						</div>
					<?php endfor; ?>
					<span class="sz-cr__head" aria-hidden="true" style="left:<?php echo esc_attr( self::pct( $now, $t0, $t1 ) ); ?>"><span class="sz-cr__flag"><i></i><?php echo esc_html( $l['now'] ); ?></span></span>
				</div>
			</div>
			<?php if ( $n > 1 ) : ?>
				<p class="sz-cr__swipe" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="m15 6-6 6 6 6"/></svg><?php echo esc_html( $l['swipe'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
