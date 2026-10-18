<?php
namespace Samanzavarian_Widgets\Widgets\Scenes;

use Elementor\Controls_Manager;
use Elementor\Repeater;
use Samanzavarian_Widgets\Widgets\Service_Panel;

defined( 'ABSPATH' ) || exit;

/**
 * Creative direction — three routes for one campaign, and the client's choice.
 *
 * Three pictures of one product in three formats (a 4:5 post, a 9:16 story,
 * a 1:1 ad). Without direction they look like three different shoots. The
 * director then shows the client three routes on the same pictures — each a
 * palette, a note, and the light, set and grade of all three — and the
 * client picks one (assets/js/scenes/direction.js).
 *
 * Resting picture: the first route on all three pictures (the design).
 */
final class Direction_Routes extends Scene {

	public const SLOTS = [ 'a', 'b', 'c' ];

	/** Placeholder looks: how a route lights and styles the pictures while none are set. */
	public const LOOKS = [
		'warm'   => 'Quiet warmth: soft light, peach set',
		'summer' => 'Bold summer: hard sun, flat colour',
		'night'  => 'Night edition: low light, neon edge',
	];

	public const ROUTES = [
		[ 'name' => 'Quiet warmth', 'note' => 'Quiet, warm light. Honest materials. One hero object per frame.', 'look' => 'warm', 'c1' => '#0D1B2A', 'c2' => '#FF6B2C', 'c3' => '#FFE6DA', 'c4' => '#E3E8EE' ],
		[ 'name' => 'Bold summer', 'note' => 'Hard sun, flat colour, hard shadows. Nothing soft.', 'look' => 'summer', 'c1' => '#1B3A8C', 'c2' => '#FF6B2C', 'c3' => '#FFD23F', 'c4' => '#FFF4E0' ],
		[ 'name' => 'Night edition', 'note' => 'Low light, one neon edge. Black does the work.', 'look' => 'night', 'c1' => '#0B1020', 'c2' => '#FF4D8D', 'c3' => '#3D5AFE', 'c4' => '#E8EAF6' ],
	];

	/** The palette shown before any direction: nothing agrees with anything. */
	public const BEFORE_PALETTE = [ '#2E7D32', '#9C27B0', '#FBC02D', '#90A4AE' ];

	public const LABELS = [
		'dir_note_title'  => [ 'Note title', 'Direction note' ],
		'dir_route_title' => [ 'Note title while a route is shown', 'Route {letter} · {name}' ],
		'dir_chosen'      => [ 'Note title once chosen', 'Approved direction' ],
		'dir_before_note' => [ 'Note before any direction', 'Not set yet. Every shoot follows its own taste.' ],
		'dir_say_before'  => [ 'Status: before', 'Before direction · three shoots, three looks' ],
		'dir_say_route'   => [ 'Status: a route is shown', 'Route {letter} of {count} · {name}' ],
		'dir_say_apply'   => [ 'Status: a route is applied', 'Route {letter} on all three pictures' ],
		'dir_say_chosen'  => [ 'Status: the client chose', 'Client chose route {letter} · {name}' ],
	];

	public static function controls( Service_Panel $w ): void {
		$w->start_controls_section(
			'section_direction',
			[
				'label'     => esc_html__( 'Direction scene: routes', 'samanzavarian-widgets' ),
				'condition' => [ 'scene' => 'direction' ],
			]
		);

		$w->add_control(
			'dir_formats_heading',
			[
				'label'       => esc_html__( 'The three pictures', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::HEADING,
				'description' => esc_html__( 'One product in three formats. Only pictures go in the frames: no text.', 'samanzavarian-widgets' ),
			]
		);
		foreach ( [ 'a' => 'Post · 4:5', 'b' => 'Story · 9:16', 'c' => 'Ad · 1:1' ] as $slot => $tag ) {
			$w->add_control(
				'dir_tag_' . $slot,
				[
					/* translators: %s: frame letter */
					'label'   => sprintf( esc_html__( 'Label under frame %s', 'samanzavarian-widgets' ), strtoupper( $slot ) ),
					'type'    => Controls_Manager::TEXT,
					'default' => $tag,
				]
			);
		}
		foreach ( self::SLOTS as $slot ) {
			$w->add_control(
				'dir_before_' . $slot,
				[
					/* translators: %s: frame letter */
					'label'       => sprintf( esc_html__( 'Before direction, frame %s', 'samanzavarian-widgets' ), strtoupper( $slot ) ),
					'type'        => Controls_Manager::MEDIA,
					'media_types' => [ 'image', 'video' ],
					'description' => 'a' === $slot ? esc_html__( 'Three pictures that don\'t belong together (different light, colour, set). Empty: placeholders that show exactly that.', 'samanzavarian-widgets' ) : '',
					'separator'   => 'a' === $slot ? 'before' : 'none',
				]
			);
		}

		$r = new Repeater();
		$r->add_control( 'name', [ 'label' => esc_html__( 'Route name', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Route' ] );
		$r->add_control(
			'note',
			[
				'label'       => esc_html__( 'Direction note', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 3,
				'default'     => '',
				'description' => esc_html__( 'Rules, not moods: light, materials, framing. About 70 characters fit.', 'samanzavarian-widgets' ),
			]
		);
		foreach ( [ 'c1' => '#0D1B2A', 'c2' => '#FF6B2C', 'c3' => '#FFE6DA', 'c4' => '#E3E8EE' ] as $k => $c ) {
			$r->add_control(
				$k,
				[
					/* translators: %s: 1 to 4 */
					'label'   => sprintf( esc_html__( 'Palette colour %s', 'samanzavarian-widgets' ), substr( $k, 1 ) ),
					'type'    => Controls_Manager::COLOR,
					'default' => $c,
				]
			);
		}
		foreach ( self::SLOTS as $slot ) {
			$r->add_control(
				'pic_' . $slot,
				[
					/* translators: %s: frame letter */
					'label'       => sprintf( esc_html__( 'Frame %s in this route', 'samanzavarian-widgets' ), strtoupper( $slot ) ),
					'type'        => Controls_Manager::MEDIA,
					'media_types' => [ 'image', 'video' ],
					'separator'   => 'a' === $slot ? 'before' : 'none',
				]
			);
		}
		$r->add_control(
			'look',
			[
				'label'       => esc_html__( 'Placeholder look', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'warm',
				'options'     => self::LOOKS,
				'description' => esc_html__( 'For frames without a picture yet.', 'samanzavarian-widgets' ),
			]
		);

		$w->add_control(
			'dir_routes',
			[
				'label'       => esc_html__( 'Routes', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => self::ROUTES,
				'title_field' => '{{{ name }}}',
				'description' => esc_html__( 'The first route is the resting picture. Three is the classic number to show a client.', 'samanzavarian-widgets' ),
				'separator'   => 'before',
			]
		);

		$w->add_control(
			'dir_choice',
			[
				'label'   => esc_html__( 'The client chooses', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					''  => esc_html__( 'A different route each time', 'samanzavarian-widgets' ),
					'1' => esc_html__( 'Route A', 'samanzavarian-widgets' ),
					'2' => esc_html__( 'Route B', 'samanzavarian-widgets' ),
					'3' => esc_html__( 'Route C', 'samanzavarian-widgets' ),
				],
			]
		);

		$w->add_control(
			'dir_hold',
			[
				'label'       => esc_html__( 'Time on each route', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 1,
				'max'         => 10,
				'step'        => 0.5,
				'placeholder' => '2.6',
				'description' => esc_html__( 'Seconds.', 'samanzavarian-widgets' ),
			]
		);

		$w->add_control( 'dir_labels_heading', [ 'label' => esc_html__( 'Labels', 'samanzavarian-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before', 'description' => esc_html__( '{letter}, {count} and {name} are filled in.', 'samanzavarian-widgets' ) ] );
		foreach ( self::LABELS as $id => $l ) {
			$w->add_control( $id, [ 'label' => $l[0], 'type' => Controls_Manager::TEXT, 'default' => $l[1], 'label_block' => true ] );
		}

		$w->end_controls_section();
	}

	public static function style( Service_Panel $w ): void {
		$w->start_controls_section(
			'style_direction',
			[
				'label'     => esc_html__( 'Direction scene', 'samanzavarian-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'scene' => 'direction' ],
			]
		);

		foreach ( [
			'dir_note_bg'    => [ esc_html__( 'Note background', 'samanzavarian-widgets' ), '.szs-dir__note', 'background-color' ],
			'dir_note_fg'    => [ esc_html__( 'Note text', 'samanzavarian-widgets' ), '.szs-dir__note', 'color' ],
			'dir_note_head'  => [ esc_html__( 'Note title', 'samanzavarian-widgets' ), '.szs-dir__nh', 'color' ],
			'dir_status_bg'  => [ esc_html__( 'Status background', 'samanzavarian-widgets' ), '.szs-dir__status > span', 'background-color' ],
			'dir_status_fg'  => [ esc_html__( 'Status text', 'samanzavarian-widgets' ), '.szs-dir__status > span', 'color' ],
			'dir_swatch_bg'  => [ esc_html__( 'Palette background', 'samanzavarian-widgets' ), '.szs-dir__swatch', 'background-color' ],
			'dir_tag_fg'     => [ esc_html__( 'Frame labels', 'samanzavarian-widgets' ), '.szs-dir__tag', 'color' ],
			'dir_grid'       => [ esc_html__( 'Layout grid', 'samanzavarian-widgets' ), '.szs-dir__grid', '--szs-grid' ],
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
	 * Routes, cleaned: name, note, palette (safe colours only) and a picture
	 * per frame (media, or the route's placeholder look).
	 *
	 * @param array<string,mixed> $s
	 * @return array<int,array<string,mixed>>
	 */
	public static function routes( array $s ): array {
		$rows = isset( $s['dir_routes'] ) && is_array( $s['dir_routes'] ) && $s['dir_routes'] ? array_values( $s['dir_routes'] ) : self::ROUTES;
		$out  = [];

		foreach ( $rows as $i => $row ) {
			$look = isset( self::LOOKS[ $row['look'] ?? '' ] ) ? (string) $row['look'] : array_keys( self::LOOKS )[ $i % 3 ];
			$pics = [];
			foreach ( self::SLOTS as $slot ) {
				$pics[ $slot ] = self::picture( $row[ 'pic_' . $slot ] ?? [], $slot, 'look-' . $look );
			}
			$out[] = [
				'name' => trim( (string) ( $row['name'] ?? '' ) ),
				'note' => trim( (string) ( $row['note'] ?? '' ) ),
				'pal'  => array_values( array_filter( array_map( [ self::class, 'color' ], [ $row['c1'] ?? '', $row['c2'] ?? '', $row['c3'] ?? '', $row['c4'] ?? '' ] ), 'strlen' ) ),
				'pics' => $pics,
			];
		}

		return $out;
	}

	/** One frame's picture: the media, or a placeholder in the given look. */
	public static function picture( $media, string $slot, string $look ): string {
		$html = Service_Panel::media_html( is_array( $media ) ? $media : [], 'szs-dir__media' );
		if ( '' !== $html ) {
			return '<div class="szs-dir__look">' . $html . '</div>';
		}

		return '<div class="szs-dir__look szs-dir__ph lk-' . $slot . ' ' . $look . '"><i class="lk-floor"></i><i class="lk-light"></i><i class="lk-prop"></i><i class="lk-shadow"></i><i class="lk-pb"></i></div>';
	}

	public static function render( array $s, string $uid ): string {
		$routes = self::routes( $s );
		$first  = $routes[0];
		$labels = [];
		foreach ( self::LABELS as $id => $l ) {
			$labels[ $id ] = self::text( $s, $id, $l[1] );
		}
		$before = [];
		foreach ( self::SLOTS as $slot ) {
			$before[ $slot ] = self::picture( $s[ 'dir_before_' . $slot ] ?? [], $slot, 'look-before-' . $slot );
		}

		$config = [
			'routes' => array_map( static fn( $r ) => [ 'name' => $r['name'], 'note' => esc_html( $r['note'] ), 'pal' => $r['pal'], 'pics' => $r['pics'] ], $routes ),
			'before' => [ 'pal' => self::BEFORE_PALETTE, 'pics' => $before, 'note' => esc_html( $labels['dir_before_note'] ) ],
			'labels' => array_map( 'esc_html', $labels ),
			'choice' => max( 0, min( count( $routes ), (int) ( $s['dir_choice'] ?? 0 ) ) ),
			'hold'   => self::num( $s, 'dir_hold', 2.6, 1, 10 ) * 1000,
		];

		$dots = '';
		foreach ( array_pad( $first['pal'], 4, '' ) as $c ) {
			$dots .= '<b' . ( '' !== $c ? ' style="background-color:' . esc_attr( $c ) . '"' : ' class="is-empty"' ) . '></b>';
		}

		$tiles = '';
		foreach ( self::SLOTS as $slot ) {
			$tag    = self::text( $s, 'dir_tag_' . $slot, [ 'a' => 'Post · 4:5', 'b' => 'Story · 9:16', 'c' => 'Ad · 1:1' ][ $slot ] );
			$tiles .= '<div class="szs-dir__tile szs-dir__tile--' . $slot . '"><div class="szs-dir__clip">' . $first['pics'][ $slot ] . '</div>' . ( '' !== $tag ? '<span class="szs-dir__tag">' . esc_html( $tag ) . '</span>' : '' ) . '</div>';
		}

		$html  = '<div class="szs-dir__swatch" data-szs-direction="' . self::data( $config ) . '">' . $dots . '</div>';
		$html .= '<span class="szs-dir__status"></span>';
		$html .= $tiles;
		$html .= '<p class="szs-dir__note"><b class="szs-dir__nh">' . esc_html( $labels['dir_note_title'] ) . '</b><span class="szs-dir__nt"><span>' . esc_html( $first['note'] ) . '</span></span></p>';
		$html .= '<div class="szs-dir__grid"></div>';

		return $html;
	}

	public static function notes( array $s ): array {
		$notes  = [];
		$routes = self::routes( $s );
		if ( count( $routes ) < 2 ) {
			$notes[] = __( 'Only one route: there is nothing for the client to choose between. Two or three routes tell the story.', 'samanzavarian-widgets' );
		}
		foreach ( $routes as $i => $r ) {
			if ( count( $r['pal'] ) < 4 ) {
				/* translators: %d: route number */
				$notes[] = sprintf( __( 'Route %d has fewer than four palette colours; the empty dots stay grey.', 'samanzavarian-widgets' ), $i + 1 );
			}
		}

		return $notes;
	}

	public static function has_video( array $s ): bool {
		$media = [ $s['dir_before_a'] ?? [], $s['dir_before_b'] ?? [], $s['dir_before_c'] ?? [] ];
		foreach ( isset( $s['dir_routes'] ) && is_array( $s['dir_routes'] ) ? $s['dir_routes'] : [] as $r ) {
			foreach ( self::SLOTS as $slot ) {
				$media[] = $r[ 'pic_' . $slot ] ?? [];
			}
		}

		return self::any_video( $media );
	}
}
