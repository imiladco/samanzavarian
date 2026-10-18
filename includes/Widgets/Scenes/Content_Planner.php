<?php
namespace Samanzavarian_Widgets\Widgets\Scenes;

use Elementor\Controls_Manager;
use Elementor\Repeater;
use Samanzavarian_Widgets\Icon_Library;
use Samanzavarian_Widgets\Widgets\Service_Panel;

defined( 'ABSPATH' ) || exit;

/**
 * Content systems — a rolling plan that runs on rules.
 *
 * A week view of posts (format chips at their time). Played, it rolls on a
 * day at a time with "Today" fixed in place, in a smart time-lapse: quiet
 * hours fly by and time slows as a post's moment comes. Every change has a
 * cause: posts go out at their time, only if ready, then get shared to
 * stories; the team makes them in office hours, a few at a time, nearest
 * deadline first, from a few days ahead; next week is planned on a set day;
 * a trend gets a fast-tracked Reel, which can push another post back a day;
 * a Reel that does well gets a carousel version (assets/js/scenes/content.js).
 *
 * Resting picture: this week as planned (the design), today highlighted.
 */
final class Content_Planner extends Scene {

	/** Day window, design px: the card's inside width, the gap between days. */
	public const WIN = 430;
	public const GAP = 8;
	public const DAYS = [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ];

	public const FORMATS = [
		[ 'name' => 'Reel', 'bg' => '#0D1B2A', 'fg' => '#FFFFFF', 'line' => '', 'times' => '12:00, 18:30, 19:30', 'weight' => 2, 'weekdays' => '' ],
		[ 'name' => 'Carousel', 'bg' => '#FF6B2C', 'fg' => '#111318', 'line' => '', 'times' => '09:30, 12:30', 'weight' => 2, 'weekdays' => '' ],
		[ 'name' => 'Story', 'bg' => '#FFFFFF', 'fg' => '#111318', 'line' => '#E8EAED', 'times' => '12:00', 'weight' => 0, 'weekdays' => '' ],
		[ 'name' => 'Post', 'bg' => '#C9D3DF', 'fg' => '#111318', 'line' => '', 'times' => '09:00, 13:00', 'weight' => 1, 'weekdays' => '' ],
		[ 'name' => 'Collab', 'bg' => '#FFC9AF', 'fg' => '#8A3A12', 'line' => '', 'times' => '17:00, 18:00', 'weight' => 1, 'weekdays' => '' ],
		[ 'name' => 'Live Q&A', 'bg' => '#E5484D', 'fg' => '#FFFFFF', 'line' => '', 'times' => '20:00', 'weight' => 1, 'weekdays' => 'yes' ],
	];

	/** This week, as designed. */
	public const PLAN = [ 'mon' => 'Reel 18:30', 'tue' => '', 'wed' => 'Carousel 09:30', 'thu' => 'Story 12:00', 'fri' => '', 'sat' => 'Reel 19:30', 'sun' => '' ];

	public const LABELS = [
		'content_title'       => [ 'Card title', 'Content plan' ],
		'content_week_label'  => [ 'Week', 'Week {n}' ],
		'content_today'       => [ 'Today', 'Today' ],
		'content_unplanned'   => [ 'A day not planned yet', 'Not planned yet' ],
		'content_rest_title'  => [ 'Notification at rest', 'This week is planned' ],
		'content_rest_text'   => [ 'Notification at rest, second line', '{count} posts scheduled' ],
		'content_next'        => [ 'Next post', 'Next: {type} · {day} {time}' ],
		'content_s_ready'     => [ 'Next post: ready', 'ready and scheduled' ],
		'content_s_making'    => [ 'Next post: being made', 'being made now' ],
		'content_s_planned'   => [ 'Next post: planned', 'planned · made nearer the day' ],
		'content_published'   => [ 'Published', '{type} published · {day} {time}' ],
		'content_published2'  => [ 'Published, second line', 'on time · shared to stories' ],
		'content_say_trend'   => [ 'Trend', 'Trend spotted · {type} for {day} {time}' ],
		'content_trend2'      => [ 'Trend, second line', 'fast-tracked into production' ],
		'content_moved'       => [ 'Moved', '{type} moved to {day} {time}' ],
		'content_moved_trend' => [ 'Moved: because of the trend', 'not ready on time · the trend Reel took its production slot' ],
		'content_moved_late'  => [ 'Moved: needed more time', 'not ready on time · it needs another day to finish' ],
		'content_say_repurpose' => [ 'Repurposed', '{day}\'s {type} reached {x}× your average reach' ],
		'content_repurpose2'  => [ 'Repurposed, second line', 'a carousel version is planned for {day}' ],
		'content_planned'     => [ 'Next week planned', 'Week {n} planned · {count} posts' ],
		'content_planned2'    => [ 'Next week planned, second line', 'production starts {lead} days before each one' ],
		'content_today_word'  => [ 'The word "today"', 'today' ],
	];

	public const KEY = [
		'content_key_planned' => 'Planned',
		'content_key_making'  => 'Being made',
		'content_key_ready'   => 'Ready',
		'content_key_live'    => 'Published',
		'content_key_story'   => 'Shared to stories',
	];

	public static function controls( Service_Panel $w ): void {
		$w->start_controls_section(
			'section_content',
			[
				'label'     => esc_html__( 'Content scene: the plan', 'samanzavarian-widgets' ),
				'condition' => [ 'scene' => 'content' ],
			]
		);

		$w->add_control(
			'content_mode',
			[
				'label'       => esc_html__( 'Dates', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'live',
				'options'     => [
					'live'  => esc_html__( 'The visitor\'s real week and day', 'samanzavarian-widgets' ),
					'fixed' => esc_html__( 'A fixed week (as in the design)', 'samanzavarian-widgets' ),
				],
				'description' => esc_html__( 'Real week: the week number and "today" come from the visitor\'s own clock, and time runs on from now.', 'samanzavarian-widgets' ),
			]
		);
		$w->add_control( 'content_week', [ 'label' => esc_html__( 'Week number', 'samanzavarian-widgets' ), 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 53, 'default' => 42, 'condition' => [ 'content_mode' => 'fixed' ] ] );
		$w->add_control(
			'content_day',
			[
				'label'     => esc_html__( 'Today is', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'wed',
				'options'   => array_combine( self::DAYS, [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ] ),
				'condition' => [ 'content_mode' => 'fixed' ],
			]
		);

		$f = new Repeater();
		$f->add_control( 'name', [ 'label' => esc_html__( 'Name', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Reel' ] );
		$f->add_control( 'bg', [ 'label' => esc_html__( 'Colour', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'default' => '#0D1B2A' ] );
		$f->add_control( 'fg', [ 'label' => esc_html__( 'Text', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'default' => '#FFFFFF' ] );
		$f->add_control( 'line', [ 'label' => esc_html__( 'Outline', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'default' => '' ] );
		$f->add_control(
			'times',
			[
				'label'       => esc_html__( 'Usual posting times', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '12:00',
				'description' => esc_html__( 'Comma-separated, 24-hour. Later weeks pick from these.', 'samanzavarian-widgets' ),
			]
		);
		$f->add_control(
			'weight',
			[
				'label'       => esc_html__( 'Share of later weeks', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 5,
				'default'     => 1,
				'description' => esc_html__( '0: only where you plan it by hand.', 'samanzavarian-widgets' ),
			]
		);
		$f->add_control( 'weekdays', [ 'label' => esc_html__( 'Weekdays only', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => '', 'return_value' => 'yes' ] );

		$w->add_control(
			'content_formats',
			[
				'label'       => esc_html__( 'Formats', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $f->get_controls(),
				'default'     => self::FORMATS,
				'title_field' => '{{{ name }}}',
				'separator'   => 'before',
			]
		);

		$w->add_control(
			'content_plan_heading',
			[
				'label'       => esc_html__( 'This week', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::HEADING,
				'separator'   => 'before',
				'description' => esc_html__( 'A format and a time, e.g. "Reel 18:30". Two in a day: "Carousel 09:30 + Story 21:00". Later weeks are planned by the rules below.', 'samanzavarian-widgets' ),
			]
		);
		foreach ( self::DAYS as $i => $d ) {
			$w->add_control( 'content_plan_' . $d, [ 'label' => [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ][ $i ], 'type' => Controls_Manager::TEXT, 'default' => self::PLAN[ $d ] ] );
		}

		$w->add_control( 'content_rules_heading', [ 'label' => esc_html__( 'How the team works', 'samanzavarian-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$w->add_control( 'content_work_from', [ 'label' => esc_html__( 'Office hours from', 'samanzavarian-widgets' ), 'type' => Controls_Manager::NUMBER, 'min' => 0, 'max' => 23, 'placeholder' => '9' ] );
		$w->add_control( 'content_work_to', [ 'label' => esc_html__( 'Office hours to', 'samanzavarian-widgets' ), 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 24, 'placeholder' => '19' ] );
		$w->add_control( 'content_lead', [ 'label' => esc_html__( 'Start making a post (days before)', 'samanzavarian-widgets' ), 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 7, 'placeholder' => '3' ] );
		$w->add_control( 'content_cap', [ 'label' => esc_html__( 'Posts made at the same time', 'samanzavarian-widgets' ), 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 5, 'placeholder' => '2' ] );
		$w->add_control(
			'content_plan_day',
			[
				'label'   => esc_html__( 'Next week is planned on', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'thu',
				'options' => array_combine( self::DAYS, [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ] ),
			]
		);
		$w->add_control( 'content_trend', [ 'label' => esc_html__( 'Trends get a fast Reel', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'description' => esc_html__( 'In about half the weeks.', 'samanzavarian-widgets' ) ] );
		$w->add_control( 'content_repurpose', [ 'label' => esc_html__( 'Strong Reels get a carousel version', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ] );
		$w->add_control(
			'content_quiet',
			[
				'label'       => esc_html__( 'A quiet day takes (seconds)', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 0.5,
				'max'         => 6,
				'step'        => 0.1,
				'placeholder' => '1.4',
				'description' => esc_html__( 'A day with nothing to publish; every day varies a little, and time slows as a post\'s moment comes.', 'samanzavarian-widgets' ),
			]
		);

		$w->add_control( 'content_key', [ 'label' => esc_html__( 'Key under the days', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'separator' => 'before' ] );
		foreach ( self::KEY as $id => $text ) {
			$w->add_control( $id, [ 'label' => $text, 'type' => Controls_Manager::TEXT, 'default' => $text, 'condition' => [ 'content_key' => 'yes' ] ] );
		}

		$w->add_control( 'content_labels_heading', [ 'label' => esc_html__( 'Texts', 'samanzavarian-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before', 'description' => esc_html__( '{type}, {day}, {time}, {n}, {count}, {x} and {lead} are filled in.', 'samanzavarian-widgets' ) ] );
		foreach ( self::LABELS as $id => $l ) {
			$w->add_control( $id, [ 'label' => $l[0], 'type' => Controls_Manager::TEXT, 'default' => $l[1], 'label_block' => true ] );
		}
		$w->add_control( 'content_icon', [ 'label' => esc_html__( 'Notification icon', 'samanzavarian-widgets' ), 'type' => Controls_Manager::ICONS, 'default' => Icon_Library::value( 'cal' ) ] );

		$w->end_controls_section();
	}

	public static function style( Service_Panel $w ): void {
		$w->start_controls_section(
			'style_content',
			[
				'label'     => esc_html__( 'Content scene', 'samanzavarian-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'scene' => 'content' ],
			]
		);

		foreach ( [
			'content_card_bg'  => [ esc_html__( 'Card', 'samanzavarian-widgets' ), '.szs-cp__card', 'background-color' ],
			'content_day_bg'   => [ esc_html__( 'Days', 'samanzavarian-widgets' ), '.szs-cp__day', 'background-color' ],
			'content_today_bg' => [ esc_html__( 'Today', 'samanzavarian-widgets' ), '.szs-cp__day.is-today', 'background-color' ],
			'content_today_fg' => [ esc_html__( 'Today\'s name', 'samanzavarian-widgets' ), '.szs-cp__day.is-today > b', 'color' ],
			'content_live'     => [ esc_html__( 'Published tick', 'samanzavarian-widgets' ), '.szs-cp', '--szs-live' ],
			'content_notif_bg' => [ esc_html__( 'Notification', 'samanzavarian-widgets' ), '.szs-cp__notif', 'background-color' ],
			'content_clock'    => [ esc_html__( 'Clock', 'samanzavarian-widgets' ), '.szs-cp__clock', 'color' ],
			'content_key_fg'   => [ esc_html__( 'Key text', 'samanzavarian-widgets' ), '.szs-cp__key', 'color' ],
		] as $id => $def ) {
			$w->add_control(
				$id,
				[
					'label'     => $def[0],
					'type'      => Controls_Manager::COLOR,
					'selectors' => [ '{{WRAPPER}} ' . $def[1] => $def[2] . ': {{VALUE}};' ],
				]
			);
		}

		$w->end_controls_section();
	}

	/** "18:30" → 18.5; a bad time → null. */
	public static function hour( string $t ): ?float {
		if ( ! preg_match( '/^\s*(\d{1,2})(?:[:.](\d{2}))?\s*$/', $t, $m ) || (int) $m[1] > 23 || (int) ( $m[2] ?? 0 ) > 59 ) {
			return null;
		}

		return (int) $m[1] + (int) ( $m[2] ?? 0 ) / 60;
	}

	/**
	 * Formats, cleaned: names, safe colours, usable times (06:00–23:59).
	 *
	 * @param array<string,mixed> $s
	 * @return array<int,array<string,mixed>>
	 */
	public static function formats( array $s ): array {
		$rows = isset( $s['content_formats'] ) && is_array( $s['content_formats'] ) ? $s['content_formats'] : self::FORMATS;
		$out  = [];

		foreach ( $rows as $row ) {
			$name = trim( (string) ( $row['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$times = array_values( array_filter( array_map( static fn( $t ) => self::hour( $t ), explode( ',', (string) ( $row['times'] ?? '' ) ) ), static fn( $h ) => null !== $h && $h >= 6 ) );
			$out[] = [
				'name'     => $name,
				'bg'       => self::color( $row['bg'] ?? '' ),
				'fg'       => self::color( $row['fg'] ?? '' ),
				'line'     => self::color( $row['line'] ?? '' ),
				'times'    => $times ? $times : [ 12 ],
				'weight'   => max( 0, min( 5, (int) ( $row['weight'] ?? 1 ) ) ),
				'weekdays' => 'yes' === ( $row['weekdays'] ?? '' ),
			];
		}

		return $out ? $out : self::formats( [ 'content_formats' => self::FORMATS ] );
	}

	/**
	 * This week's posts from the day fields: [ { type: format index (or -1), name, d, h } ].
	 * A name that is no format still shows, plain; a missing time takes the format's first.
	 *
	 * @param array<string,mixed>             $s
	 * @param array<int,array<string,mixed>> $formats
	 * @return array<int,array<string,mixed>>
	 */
	public static function plan( array $s, array $formats ): array {
		$names = array_map( static fn( $f ) => mb_strtolower( $f['name'] ), $formats );
		$out   = [];

		foreach ( self::DAYS as $d => $day ) {
			$text = (string) ( array_key_exists( 'content_plan_' . $day, $s ) ? $s[ 'content_plan_' . $day ] : self::PLAN[ $day ] );
			foreach ( preg_split( '/\s*\+\s*/', trim( $text ) ) as $part ) {
				if ( '' === $part ) {
					continue;
				}
				$h    = null;
				$name = $part;
				if ( preg_match( '/^(.*?)\s+(\d{1,2}[:.]\d{2})$/', $part, $m ) ) {
					$name = $m[1];
					$h    = self::hour( $m[2] );
				}
				$type  = array_search( mb_strtolower( trim( $name ) ), $names, true );
				$type  = false === $type ? -1 : (int) $type;
				$out[] = [
					'type' => $type,
					'name' => trim( $name ),
					'd'    => $d,
					'h'    => null !== $h && $h >= 6 ? $h : ( $type >= 0 ? $formats[ $type ]['times'][0] : 12 ),
				];
			}
		}

		return $out;
	}

	/** Chip colours as a style attribute. @param array<string,mixed>|null $f */
	public static function chip_style( ?array $f ): string {
		if ( ! $f ) {
			return '';
		}

		return '--tc:' . ( '' !== $f['bg'] ? $f['bg'] : '#fff' ) . ';--tf:' . ( '' !== $f['fg'] ? $f['fg'] : '#111318' ) . ( '' !== $f['line'] ? ';--tl:' . $f['line'] : '' );
	}

	public static function render( array $s, string $uid ): string {
		$formats = self::formats( $s );
		$plan    = self::plan( $s, $formats );
		$labels  = [];
		foreach ( self::LABELS as $id => $l ) {
			$labels[ $id ] = self::text( $s, $id, $l[1] );
		}
		$live  = 'fixed' !== ( $s['content_mode'] ?? 'live' );
		$week  = $live ? (int) wp_date( 'W' ) : (int) self::num( $s, 'content_week', 42, 1, 53 );
		$today = $live ? (int) wp_date( 'N' ) - 1 : max( 0, (int) array_search( (string) ( $s['content_day'] ?? 'wed' ), self::DAYS, true ) );
		$col   = ( self::WIN - self::GAP * 6 ) / 7;
		$names = [ 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ];

		$config = [
			'formats' => array_map( static fn( $f ) => [ 'name' => $f['name'], 'style' => self::chip_style( $f ), 'times' => $f['times'], 'weight' => $f['weight'], 'weekdays' => $f['weekdays'] ], $formats ),
			'plan'    => array_map( static fn( $p ) => [ 'type' => $p['type'], 'name' => $p['name'], 'd' => $p['d'], 'h' => $p['h'] ], $plan ),
			'live'    => $live,
			'week'    => $week,
			'today'   => $today,
			'rules'   => [
				'work'      => [ (int) self::num( $s, 'content_work_from', 9, 0, 23 ), (int) self::num( $s, 'content_work_to', 19, 1, 24 ) ],
				'lead'      => (int) self::num( $s, 'content_lead', 3, 1, 7 ),
				'cap'       => (int) self::num( $s, 'content_cap', 2, 1, 5 ),
				'planDay'   => max( 0, (int) array_search( (string) ( $s['content_plan_day'] ?? 'thu' ), self::DAYS, true ) ),
				'trend'     => 'yes' === ( $s['content_trend'] ?? 'yes' ),
				'repurpose' => 'yes' === ( $s['content_repurpose'] ?? 'yes' ),
				'quiet'     => self::num( $s, 'content_quiet', 1.4, 0.5, 6 ),
			],
			'labels'  => $labels,
			'days'    => $names,
		];

		/* the week at rest */
		$days = '';
		foreach ( $names as $d => $name ) {
			$days .= '<div class="szs-cp__day' . ( $d === $today ? ' is-today' : '' ) . '" style="left:' . round( $d * ( $col + self::GAP ), 2 ) . 'px;width:' . round( $col, 2 ) . 'px"><b>' . esc_html( $name ) . '</b></div>';
		}
		$chips = '';
		$row   = [];
		foreach ( $plan as $p ) {
			$k      = $row[ $p['d'] ] = ( $row[ $p['d'] ] ?? -1 ) + 1;
			$chips .= '<em class="szs-cp__chip is-ready" style="translate:' . round( $p['d'] * ( $col + self::GAP ) + 4, 2 ) . 'px ' . ( 38 + $k * 36 ) . 'px;width:' . round( $col - 8, 2 ) . 'px;' . esc_attr( self::chip_style( $formats[ $p['type'] ] ?? null ) ) . '"><span>' . esc_html( $p['type'] >= 0 ? $formats[ $p['type'] ]['name'] : $p['name'] ) . '</span><small>' . esc_html( sprintf( '%02d:%02d', (int) $p['h'], (int) round( fmod( $p['h'], 1 ) * 60 ) ) ) . '</small></em>';
		}

		$key = '';
		if ( 'yes' === ( $s['content_key'] ?? 'yes' ) ) {
			$key = '<div class="szs-cp__key">';
			foreach ( [ 'planned' => 'k-plan', 'making' => 'k-make', 'ready' => 'k-ready', 'live' => 'k-live', 'story' => 'k-story' ] as $k => $cls ) {
				$text = self::text( $s, 'content_key_' . $k, self::KEY[ 'content_key_' . $k ] );
				$key .= '' !== $text ? '<span><i class="' . $cls . '"></i>' . esc_html( $text ) . '</span>' : '';
			}
			$key .= '</div>';
		}

		$icon  = Icon_Library::html( $s['content_icon'] ?? Icon_Library::value( 'cal' ) );
		$count = count( $plan );

		$html  = '<div class="szs-cp__notif">' . ( '' !== $icon ? '<span class="szs-cp__ic sz-app sz-app--orange">' . $icon . '</span>' : '' );
		$html .= '<span class="szs-cp__tx"><span><b>' . esc_html( $labels['content_rest_title'] ) . '</b>' . esc_html( str_replace( '{count}', (string) $count, $labels['content_rest_text'] ) ) . '</span></span></div>';
		$html .= '<div class="szs-cp__card' . ( '' === $key ? ' no-key' : '' ) . '" data-szs-content="' . self::data( $config ) . '">';
		$html .= '<div class="szs-cp__hd"><b>' . esc_html( $labels['content_title'] ) . '</b><span class="szs-cp__wk"><span class="szs-cp__clock"></span><span class="szs-cp__wno"><span>' . esc_html( str_replace( '{n}', (string) $week, $labels['content_week_label'] ) ) . '</span></span></span></div>';
		$html .= '<div class="szs-cp__win"><div class="szs-cp__strip">' . $days . $chips . '</div></div>';
		$html .= $key . '</div>';

		return $html;
	}

	public static function notes( array $s ): array {
		$notes   = [];
		$formats = self::formats( $s );
		$unknown = [];
		foreach ( self::plan( $s, $formats ) as $p ) {
			if ( $p['type'] < 0 ) {
				$unknown[ $p['name'] ] = true;
			}
		}
		if ( $unknown ) {
			/* translators: %s: format names */
			$notes[] = sprintf( __( 'Not a format: %s. These show in plain style — add them under Formats, or check the spelling.', 'samanzavarian-widgets' ), implode( ', ', array_keys( $unknown ) ) );
		}
		if ( ! array_filter( array_column( $formats, 'weight' ) ) ) {
			$notes[] = __( 'Every format has a share of 0, so later weeks stay empty.', 'samanzavarian-widgets' );
		}

		return $notes;
	}
}
