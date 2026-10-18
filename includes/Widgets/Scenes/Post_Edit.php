<?php
namespace Samanzavarian_Widgets\Widgets\Scenes;

use Elementor\Controls_Manager;
use Elementor\Repeater;
use Samanzavarian_Widgets\Widgets\Service_Panel;

defined( 'ABSPATH' ) || exit;

/**
 * Post-production — an editor at work.
 *
 * Grading sliders a visitor can move (real range inputs), a label for the
 * edit's state, and a timeline that never repeats: the playhead is a real
 * transport (play, 2×, rewind, stop) and its timecode always shows exactly
 * where it is. The editor cuts, trims and inserts at the playhead and then
 * reviews each edit; every few edits a new version is exported
 * (assets/js/scenes/post.js). Visitors can scrub the timeline by hand.
 *
 * Resting picture: the playhead stopped at the start timecode, "Final
 * export ready".
 */
final class Post_Edit extends Scene {

	/** Timeline geometry, design px: pixels per second, the playhead's resting place, the card's width. */
	public const PPS  = 36;
	public const HEAD = 276;
	public const VIEW = 640;

	/** The design's two sliders. */
	public const ADJUSTMENTS = [
		[ 'label' => 'Warmth', 'effect' => 'warmth', 'min' => -50, 'max' => 50, 'value' => 18, 'c1' => '#8FB3E0', 'c2' => '#E9ECEF', 'c3' => '#FFB07F' ],
		[ 'label' => 'Shadows', 'effect' => 'shadows', 'min' => -75, 'max' => 75, 'value' => -12, 'c1' => '#1B2633', 'c2' => '', 'c3' => '#E9ECEF' ],
	];

	public const EDITS = [
		'blade'  => 'Cut: split a clip at the playhead',
		'trim'   => 'Trim: shorten a clip, close the gap',
		'insert' => 'Insert: drop a new clip at the playhead',
		'skim'   => 'Skim: run through a finished stretch at 4×',
		'look'   => 'Look ahead: scroll on, then back',
	];

	public const LABELS = [
		'post_done'      => [ 'Finished', 'Final export ready · v{v}' ],
		'post_editing'   => [ 'Editing', 'Editing · cut v{v}' ],
		'post_exporting' => [ 'Exporting', 'Exporting v{v} · {p}%' ],
		'post_hint'      => [ 'Hint on the timeline', 'Drag to scrub' ],
	];

	public static function controls( Service_Panel $w ): void {
		$w->start_controls_section(
			'section_post',
			[
				'label'     => esc_html__( 'Post-production scene: the edit', 'samanzavarian-widgets' ),
				'condition' => [ 'scene' => 'post' ],
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
				'description' => esc_html__( 'What moving the slider does to the clips on the timeline.', 'samanzavarian-widgets' ),
			]
		);
		$adj->add_control( 'min', [ 'label' => esc_html__( 'Minimum', 'samanzavarian-widgets' ), 'type' => Controls_Manager::NUMBER, 'default' => -50 ] );
		$adj->add_control( 'max', [ 'label' => esc_html__( 'Maximum', 'samanzavarian-widgets' ), 'type' => Controls_Manager::NUMBER, 'default' => 50 ] );
		$adj->add_control( 'value', [ 'label' => esc_html__( 'Starting value', 'samanzavarian-widgets' ), 'type' => Controls_Manager::NUMBER, 'default' => 0 ] );
		$adj->add_control( 'c1', [ 'label' => esc_html__( 'Track: low end', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'default' => '#1B2633' ] );
		$adj->add_control( 'c2', [ 'label' => esc_html__( 'Track: middle', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'default' => '' ] );
		$adj->add_control( 'c3', [ 'label' => esc_html__( 'Track: high end', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'default' => '#E9ECEF' ] );

		$w->add_control(
			'post_adjustments',
			[
				'label'       => esc_html__( 'Sliders', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $adj->get_controls(),
				'default'     => self::ADJUSTMENTS,
				'title_field' => '{{{ label }}}',
				'description' => esc_html__( 'Visitors can drag them (or use the arrow keys); the number and the clips follow.', 'samanzavarian-widgets' ),
			]
		);
		$w->add_control(
			'post_drift',
			[
				'label'        => esc_html__( 'Sliders move on their own', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'description'  => esc_html__( 'As if someone were grading. A slider a visitor has just used is left alone for a while.', 'samanzavarian-widgets' ),
			]
		);

		$w->add_control( 'post_tl_heading', [ 'label' => esc_html__( 'Timeline', 'samanzavarian-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$w->add_control(
			'post_start',
			[
				'label'       => esc_html__( 'Start at (seconds)', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 3600,
				'placeholder' => '12',
				'description' => esc_html__( 'Where the playhead rests: 12 shows 00:00:12:00.', 'samanzavarian-widgets' ),
			]
		);
		$w->add_control(
			'post_fps',
			[
				'label'   => esc_html__( 'Frame rate', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '25',
				'options' => [
					'24' => '24 fps',
					'25' => '25 fps',
					'30' => '30 fps',
				],
			]
		);
		$w->add_control(
			'post_video_colors',
			[
				'label'       => esc_html__( 'Video track colours', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '#0D1B2A, #23384F',
				'description' => esc_html__( 'Comma-separated. Clips pick from these.', 'samanzavarian-widgets' ),
			]
		);
		$w->add_control( 'post_video2_colors', [ 'label' => esc_html__( 'Second video track colours', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => '#FFC9AF, #FF6B2C, #FFB08A' ] );
		$w->add_control(
			'post_audio',
			[
				'label'        => esc_html__( 'Audio track', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			]
		);

		$w->add_control( 'post_edit_heading', [ 'label' => esc_html__( 'The editor', 'samanzavarian-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$w->add_control(
			'post_edits',
			[
				'label'       => esc_html__( 'What the editor does', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'default'     => array_keys( self::EDITS ),
				'options'     => self::EDITS,
				'label_block' => true,
				'description' => esc_html__( 'In random order, never the same twice in a row. Each cut, trim and insert is reviewed: back up, play through it at 1×.', 'samanzavarian-widgets' ),
			]
		);
		$w->add_control(
			'post_pace',
			[
				'label'   => esc_html__( 'Pace', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'fast',
				'options' => [
					'fast' => esc_html__( 'Brisk: mostly 2×', 'samanzavarian-widgets' ),
					'real' => esc_html__( 'Real time: mostly 1×', 'samanzavarian-widgets' ),
				],
			]
		);
		$w->add_control(
			'post_export_every',
			[
				'label'       => esc_html__( 'Export after every', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 1,
				'max'         => 10,
				'placeholder' => '3',
				'description' => esc_html__( 'edits. "Play once" rests after the first export.', 'samanzavarian-widgets' ),
			]
		);

		$w->add_control( 'post_labels_heading', [ 'label' => esc_html__( 'Labels', 'samanzavarian-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before', 'description' => esc_html__( '{v} is the version, {p} the export progress. An empty hint is not shown.', 'samanzavarian-widgets' ) ] );
		foreach ( self::LABELS as $id => $l ) {
			$w->add_control( $id, [ 'label' => $l[0], 'type' => Controls_Manager::TEXT, 'default' => $l[1], 'label_block' => true ] );
		}

		$w->end_controls_section();
	}

	public static function style( Service_Panel $w ): void {
		$w->start_controls_section(
			'style_post',
			[
				'label'     => esc_html__( 'Post-production scene', 'samanzavarian-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'scene' => 'post' ],
			]
		);

		foreach ( [
			'post_card_bg' => [ esc_html__( 'Cards', 'samanzavarian-widgets' ), '.szs-pe__grade, {{WRAPPER}} .szs-pe__tl, {{WRAPPER}} .szs-pe__tc', 'background-color' ],
			'post_label'   => [ esc_html__( 'Slider labels', 'samanzavarian-widgets' ), '.szs-pe__row', 'color' ],
			'post_value'   => [ esc_html__( 'Slider values', 'samanzavarian-widgets' ), '.szs-pe__val', 'color' ],
			'post_knob'    => [ esc_html__( 'Knobs', 'samanzavarian-widgets' ), '.szs-pe__knob', 'background-color' ],
			'post_head'    => [ esc_html__( 'Playhead and transport', 'samanzavarian-widgets' ), '.szs-pe__tl', '--szs-head' ],
			'post_tc'      => [ esc_html__( 'Timecode', 'samanzavarian-widgets' ), '.szs-pe__time', 'color' ],
			'post_ruler'   => [ esc_html__( 'Ruler', 'samanzavarian-widgets' ), '.szs-pe__tick b', 'color' ],
			'post_pill_bg' => [ esc_html__( 'Label background', 'samanzavarian-widgets' ), '.szs-pe__pill', 'background-color' ],
			'post_pill_fg' => [ esc_html__( 'Label text', 'samanzavarian-widgets' ), '.szs-pe__pill', 'color' ],
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

	/**
	 * Sliders, cleaned: numbers in range, a usable min < max.
	 *
	 * @param array<string,mixed> $s
	 * @return array<int,array<string,mixed>>
	 */
	public static function adjustments( array $s ): array {
		$rows = isset( $s['post_adjustments'] ) && is_array( $s['post_adjustments'] ) ? $s['post_adjustments'] : self::ADJUSTMENTS;
		$out  = [];

		foreach ( $rows as $row ) {
			$min = is_numeric( $row['min'] ?? null ) ? (float) $row['min'] : -50;
			$max = is_numeric( $row['max'] ?? null ) ? (float) $row['max'] : 50;
			if ( $max <= $min ) {
				$max = $min + 1;
			}
			$value = is_numeric( $row['value'] ?? null ) ? (float) $row['value'] : 0;
			$stops = array_values( array_filter( array_map( [ self::class, 'color' ], [ $row['c1'] ?? '', $row['c2'] ?? '', $row['c3'] ?? '' ] ), 'strlen' ) );

			$out[] = [
				'label'  => (string) ( $row['label'] ?? '' ),
				'effect' => in_array( $row['effect'] ?? '', [ 'warmth', 'shadows', 'exposure', 'contrast', 'saturation', 'none' ], true ) ? $row['effect'] : 'none',
				'min'    => $min,
				'max'    => $max,
				'value'  => max( $min, min( $max, $value ) ),
				'track'  => count( $stops ) > 1 ? 'linear-gradient(90deg,' . implode( ',', $stops ) . ')' : ( $stops[0] ?? '' ),
				'_id'    => (string) ( $row['_id'] ?? '' ),
			];
		}

		return $out;
	}

	/** @return string[] safe colours from a comma-separated list */
	public static function colors( string $list, string $fallback ): array {
		$c = array_values( array_filter( array_map( [ self::class, 'color' ], explode( ',', $list ) ), 'strlen' ) );

		return $c ? $c : explode( ',', $fallback );
	}

	/** hh:mm:ss:ff */
	public static function timecode( float $sec, int $fps ): string {
		$w = (int) floor( $sec );

		return sprintf( '%02d:%02d:%02d:%02d', intdiv( $w, 3600 ), intdiv( $w, 60 ) % 60, $w % 60, (int) floor( fmod( $sec, 1 ) * $fps + 1e-6 ) );
	}

	public static function render( array $s, string $uid ): string {
		$t0     = self::num( $s, 'post_start', 12, 0, 3600 );
		$fps    = in_array( (string) ( $s['post_fps'] ?? '25' ), [ '24', '25', '30' ], true ) ? (int) $s['post_fps'] : 25;
		$v1     = self::colors( (string) ( $s['post_video_colors'] ?? '' ), '#0D1B2A,#23384F' );
		$v2     = self::colors( (string) ( $s['post_video2_colors'] ?? '' ), '#FFC9AF,#FF6B2C,#FFB08A' );
		$audio  = 'yes' === ( $s['post_audio'] ?? 'yes' );
		$labels = [];
		foreach ( self::LABELS as $id => $l ) {
			$labels[ $id ] = self::text( $s, $id, $l[1] );
		}
		$edits = array_values( array_intersect( isset( $s['post_edits'] ) && is_array( $s['post_edits'] ) ? $s['post_edits'] : array_keys( self::EDITS ), array_keys( self::EDITS ) ) );

		$config = [
			't0'     => $t0,
			'fps'    => $fps,
			'pps'    => self::PPS,
			'head'   => self::HEAD,
			'lanes'  => array_values( array_filter( [ $v1, $v2, $audio ? [ '#DDE1E6' ] : null ] ) ),
			'audio'  => $audio,
			'edits'  => $edits,
			'pace'   => 'real' === ( $s['post_pace'] ?? 'fast' ) ? 'real' : 'fast',
			'every'  => (int) self::num( $s, 'post_export_every', 3, 1, 10 ),
			'drift'  => 'yes' === ( $s['post_drift'] ?? 'yes' ),
			'labels' => $labels,
		];

		/* the sliders */
		$grade = '';
		foreach ( self::adjustments( $s ) as $i => $a ) {
			$id     = 'szs-' . $uid . '-adj-' . $i;
			$pct    = ( $a['value'] - $a['min'] ) / ( $a['max'] - $a['min'] );
			$grade .= '<div class="szs-pe__row"><label for="' . esc_attr( $id ) . '">' . esc_html( $a['label'] ) . '</label><output class="szs-pe__val" for="' . esc_attr( $id ) . '">' . esc_html( Service_Panel::signed( $a['value'] ) ) . '</output></div>';
			$grade .= '<div class="szs-pe__track' . ( '' !== $a['_id'] ? ' elementor-repeater-item-' . esc_attr( $a['_id'] ) : '' ) . '" style="--p:' . esc_attr( (string) round( $pct, 4 ) ) . ';' . ( '' !== $a['track'] ? 'background:' . esc_attr( $a['track'] ) : '' ) . '">';
			$grade .= '<input class="szs-pe__input" id="' . esc_attr( $id ) . '" type="range" min="' . esc_attr( (string) $a['min'] ) . '" max="' . esc_attr( (string) $a['max'] ) . '" step="1" value="' . esc_attr( (string) $a['value'] ) . '" data-effect="' . esc_attr( $a['effect'] ) . '" aria-valuetext="' . esc_attr( Service_Panel::signed( $a['value'] ) ) . '">';
			$grade .= '<i class="szs-pe__knob" aria-hidden="true"></i></div>';
		}

		/* the timeline at rest: ruler and clips around the playhead (the script carries on from these) */
		$view_t = $t0 - self::HEAD / self::PPS;
		$left   = ( $view_t - 3 ) * self::PPS;
		$right  = ( $view_t * self::PPS ) + self::VIEW + 120;
		$ticks  = '';
		for ( $k = (int) floor( ( $view_t - 3 ) * 2 ); $k / 2 * self::PPS <= $right; $k++ ) {
			$t      = $k / 2;
			$whole  = 0 === $k % 2;
			$label  = $whole && 0 === $k % 4 ? '<b>' . sprintf( '%02d:%02d', intdiv( (int) $t, 60 ) % 60, (int) $t % 60 ) . '</b>' : '';
			$ticks .= '<i class="szs-pe__tick' . ( $whole ? ' is-s' : '' ) . '" style="translate:' . round( $t * self::PPS, 1 ) . 'px 0">' . $label . '</i>';
		}
		$pattern = [ [ 118, 64, 150, 96, 180, 72, 140 ], [ 70, 160, 88, 130, 60, 140, 110 ], [ 190, 130, 220, 160, 140 ] ];
		$lanes   = '';
		foreach ( $config['lanes'] as $l => $colors ) {
			$x      = $left + ( 0 === $l ? 0 : ( 1 === $l ? 40 : 12 ) );
			$clips  = '';
			$audiol = $audio && 2 === $l;
			foreach ( $pattern[ $l ] as $k => $len ) {
				if ( $x > $right ) {
					break;
				}
				$clips .= '<i class="szs-pe__clip' . ( $audiol ? ' is-audio' : '' ) . '" style="translate:' . round( $x ) . 'px 0;width:' . $len . 'px' . ( $audiol ? '' : ';background-color:' . esc_attr( $colors[ $k % count( $colors ) ] ) ) . '"></i>';
				$x     += $len + 5;
			}
			$lanes .= '<div class="szs-pe__lane' . ( $audiol ? ' is-audio' : '' ) . '" style="top:' . ( 38 + 26 * $l ) . 'px">' . $clips . '</div>';
		}

		$done = str_replace( [ '{v}', '{p}' ], [ '1', '100' ], $labels['post_done'] );

		$html  = '<div class="szs-pe__grade" data-szs-post="' . self::data( $config ) . '">' . $grade . '</div>';
		$html .= '<span class="szs-pe__pill" aria-hidden="true"><i></i><span>' . esc_html( $done ) . '</span></span>';
		$html .= '<div class="szs-pe__tl" aria-hidden="true">';
		$html .= '<div class="szs-pe__lanes" style="transform:translateX(' . round( -$view_t * self::PPS, 2 ) . 'px)">' . $ticks . $lanes . '</div>';
		$html .= '<div class="szs-pe__tc"><span class="szs-pe__mode" data-m="stop"><i></i><em></em></span><span class="szs-pe__time">' . esc_html( self::timecode( $t0, $fps ) ) . '</span></div>';
		$html .= '' !== $labels['post_hint'] ? '<span class="szs-pe__hint">' . esc_html( $labels['post_hint'] ) . '</span>' : '';
		$html .= '<span class="szs-pe__head" style="transform:translateX(' . self::HEAD . 'px)"></span>';
		$html .= '</div>';

		return $html;
	}

	public static function notes( array $s ): array {
		$notes = [];
		if ( isset( $s['post_adjustments'] ) && is_array( $s['post_adjustments'] ) ) {
			foreach ( $s['post_adjustments'] as $row ) {
				$min = (float) ( $row['min'] ?? 0 );
				$max = (float) ( $row['max'] ?? 0 );
				$val = (float) ( $row['value'] ?? 0 );
				if ( $max <= $min || $val < $min || $val > $max ) {
					/* translators: %s: slider label */
					$notes[] = sprintf( __( 'Slider "%s": the starting value must sit between a minimum and a larger maximum. It has been adjusted to fit.', 'samanzavarian-widgets' ), (string) ( $row['label'] ?? '' ) );
				}
			}
		}
		if ( isset( $s['post_edits'] ) && is_array( $s['post_edits'] ) && ! array_intersect( $s['post_edits'], array_keys( self::EDITS ) ) ) {
			$notes[] = __( 'No editing actions are chosen, so the timeline only plays.', 'samanzavarian-widgets' );
		}

		return $notes;
	}
}
