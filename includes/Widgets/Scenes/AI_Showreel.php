<?php
namespace Samanzavarian_Widgets\Widgets\Scenes;

use Elementor\Controls_Manager;
use Elementor\Repeater;
use Samanzavarian_Widgets\Icon_Library;
use Samanzavarian_Widgets\Widgets\Service_Panel;

defined( 'ABSPATH' ) || exit;

/**
 * AI visual production — a client's brief, and the piece that was delivered.
 *
 * The sheet shows a brief the way a client writes it to a freelancer, and
 * under it a reel of delivered pieces, each in its real shape (a Reel is
 * 9:16, a website loop 16:9…). The script (assets/js/scenes/ai.js) opens on
 * the whole reel so it is clear there are several, then focuses one: it
 * plays its length, the camera pulls back, glides to another piece (random,
 * never the same twice in a row, all before any repeats), pushes in and the
 * focus pulls sharp. The brief and the result change on the way.
 *
 * Resting picture: the first piece in focus, its neighbours soft at the sides.
 */
final class AI_Showreel extends Scene {

	/** Window inside the sheet, design px; every piece is this tall. */
	public const WIN = [ 412, 204 ];

	/** Gap between pieces on the reel. */
	public const GAP = 14;

	/** Placeholder looks for pieces without media: name => [ label, width:height, seconds ]. */
	public const LOOKS = [
		'perfume' => [ 'Perfume on wet stone', [ 9, 16 ], 5 ],
		'watch'   => [ 'Watch on silk', [ 16, 9 ], 6 ],
		'coffee'  => [ 'Coffee steam', [ 9, 16 ], 4 ],
		'serum'   => [ 'Serum drop', [ 1, 1 ], 4 ],
		'citrus'  => [ 'Citrus splash', [ 4, 5 ], 5 ],
		'night'   => [ 'Night drive', [ 16, 9 ], 6 ],
	];

	public const RATIOS = [ '9:16', '4:5', '1:1', '16:9' ];

	/** The default reel: each brief says where the piece runs, and the piece has that shape. */
	public const PIECES = [
		[ 'look' => 'perfume', 'client' => 'Maison Ellery', 'color' => '#8A5A3C', 'brief' => 'Autumn scent, out on the 14th. Bottle on wet stone, warm rim light, for Reels. No CGI look.', 'format' => 'REEL', 'result' => 'Approved · first round' ],
		[ 'look' => 'watch', 'client' => 'Atelier Noor', 'color' => '#22395A', 'brief' => 'A loop for our website header: silk, low light, the dial catching the light once.', 'format' => 'WEB HERO', 'result' => 'Approved · first round' ],
		[ 'look' => 'coffee', 'client' => 'Kahve Roasters', 'color' => '#6B4127', 'brief' => 'Morning-ritual feel for the new blend: one slow steam shot, warm and calm, for Stories.', 'format' => 'STORY', 'result' => 'Delivered in 2 days' ],
		[ 'look' => 'serum', 'client' => 'Lumen Skin', 'color' => '#6F8AA0', 'brief' => 'The serum\'s texture, no model. Square, for the product page. Clean but soft. By Friday.', 'format' => 'PRODUCT PAGE', 'result' => 'Delivered before Friday' ],
		[ 'look' => 'citrus', 'client' => 'Sol Citrus', 'color' => '#E0531B', 'brief' => 'Feed ad for the sparkling range. Bright and juicy, but our brand orange has to stay exact.', 'format' => 'FEED AD', 'result' => 'Brand colours matched' ],
		[ 'look' => 'night', 'client' => 'Volta Motors', 'color' => '#2B3F66', 'brief' => 'YouTube teaser for the EV launch. Night city, light streaks, six seconds at most.', 'format' => 'TEASER', 'result' => 'Delivered in 3 days' ],
	];

	public static function controls( Service_Panel $w ): void {
		$w->start_controls_section(
			'section_ai',
			[
				'label'     => esc_html__( 'AI scene: brief and reel', 'samanzavarian-widgets' ),
				'condition' => [ 'scene' => 'ai' ],
			]
		);

		$r = new Repeater();
		$r->add_control(
			'media',
			[
				'label'       => esc_html__( 'Delivered piece (video or image)', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::MEDIA,
				'media_types' => [ 'image', 'video' ],
				'description' => esc_html__( 'Videos: MP4 or WebM, a few seconds, 720p at most, under 2 MB. Only the piece in focus (and its neighbours while the reel is in view) plays; the rest wait unloaded.', 'samanzavarian-widgets' ),
			]
		);
		$r->add_control(
			'look',
			[
				'label'       => esc_html__( 'Placeholder', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'perfume',
				'options'     => array_map( static fn( $l ) => $l[0], self::LOOKS ),
				'description' => esc_html__( 'Shown while no video or image is set.', 'samanzavarian-widgets' ),
			]
		);
		$r->add_control(
			'ratio',
			[
				'label'       => esc_html__( 'Shape', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => '',
				'options'     => [
					''     => esc_html__( 'Automatic (from the file)', 'samanzavarian-widgets' ),
					'9:16' => esc_html__( 'Reel / Story 9:16', 'samanzavarian-widgets' ),
					'4:5'  => esc_html__( 'Feed 4:5', 'samanzavarian-widgets' ),
					'1:1'  => esc_html__( 'Square 1:1', 'samanzavarian-widgets' ),
					'16:9' => esc_html__( 'Landscape 16:9', 'samanzavarian-widgets' ),
				],
				'description' => esc_html__( 'Pieces are never cropped: each shows in its own shape.', 'samanzavarian-widgets' ),
			]
		);
		$r->add_control( 'client', [ 'label' => esc_html__( 'Client', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Client name', 'separator' => 'before' ] );
		$r->add_control(
			'initials',
			[
				'label'       => esc_html__( 'Initials', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => esc_html__( 'From the name', 'samanzavarian-widgets' ),
			]
		);
		$r->add_control( 'color', [ 'label' => esc_html__( 'Avatar colour', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'default' => '#22395A' ] );
		$r->add_control(
			'brief',
			[
				'label'       => esc_html__( 'Brief', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 3,
				'default'     => '',
				'description' => esc_html__( 'What the client asked for, in their words. Two lines fit (about 95 characters).', 'samanzavarian-widgets' ),
			]
		);
		$r->add_control( 'format', [ 'label' => esc_html__( 'Format label', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'REEL', 'separator' => 'before' ] );
		$r->add_control(
			'seconds',
			[
				'label'       => esc_html__( 'Seconds on screen', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 2,
				'max'         => 30,
				'default'     => '',
				'placeholder' => esc_html__( 'Video length', 'samanzavarian-widgets' ),
				'description' => esc_html__( 'Empty: a video plays to its end (4–6 s for images and placeholders).', 'samanzavarian-widgets' ),
			]
		);
		$r->add_control( 'result', [ 'label' => esc_html__( 'Result', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Approved · first round' ] );

		$w->add_control(
			'ai_pieces',
			[
				'label'       => esc_html__( 'Pieces', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => self::PIECES,
				'title_field' => '{{{ client }}} · {{{ format }}}',
			]
		);

		$w->add_control(
			'ai_overview',
			[
				'label'        => esc_html__( 'Open on the whole reel', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'description'  => esc_html__( 'The first time the scene plays it shows every piece side by side, so it is clear there are several, then focuses the first.', 'samanzavarian-widgets' ),
				'separator'    => 'before',
			]
		);
		$w->add_control(
			'ai_af',
			[
				'label'        => esc_html__( 'Autofocus brackets', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			]
		);
		$w->add_control( 'ai_brief_label', [ 'label' => esc_html__( 'Label after the client name', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'client brief', 'separator' => 'before' ] );
		$w->add_control(
			'ai_reel_label',
			[
				'label'       => esc_html__( 'Label on the whole reel', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'Showreel · {n} pieces',
				'description' => esc_html__( '{n} becomes the number of pieces.', 'samanzavarian-widgets' ),
			]
		);
		$w->add_control( 'ai_badge', [ 'label' => esc_html__( 'Badge icon', 'samanzavarian-widgets' ), 'type' => Controls_Manager::ICONS, 'default' => Icon_Library::value( 'spark' ) ] );

		$w->end_controls_section();
	}

	public static function style( Service_Panel $w ): void {
		$w->start_controls_section(
			'style_ai',
			[
				'label'     => esc_html__( 'AI scene', 'samanzavarian-widgets' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'scene' => 'ai' ],
			]
		);

		foreach ( [
			'ai_sheet_bg' => [ esc_html__( 'Sheet', 'samanzavarian-widgets' ), '.szs-ai__sheet', 'background-color' ],
			'ai_brief_bg' => [ esc_html__( 'Brief background', 'samanzavarian-widgets' ), '.szs-ai__brief', 'background-color' ],
			'ai_brief_fg' => [ esc_html__( 'Brief text', 'samanzavarian-widgets' ), '.szs-ai__q', 'color' ],
			'ai_name_fg'  => [ esc_html__( 'Client name', 'samanzavarian-widgets' ), '.szs-ai__who b', 'color' ],
			'ai_win_bg'   => [ esc_html__( 'Reel background', 'samanzavarian-widgets' ), '.szs-ai__win', 'background-color' ],
			'ai_af_color' => [ esc_html__( 'Focus lock colour', 'samanzavarian-widgets' ), '.szs-ai__win', '--szs-af' ],
			'ai_prog'     => [ esc_html__( 'Time bar', 'samanzavarian-widgets' ), '.szs-ai__prog i', 'background-color' ],
			'ai_ok_bg'    => [ esc_html__( 'Result label background', 'samanzavarian-widgets' ), '.szs-ai__ok', 'background-color' ],
			'ai_ok_fg'    => [ esc_html__( 'Result label text', 'samanzavarian-widgets' ), '.szs-ai__ok', 'color' ],
			'ai_ok_tick'  => [ esc_html__( 'Result tick', 'samanzavarian-widgets' ), '.szs-ai__ok > i', 'background-color' ],
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

		$w->add_control(
			'ai_glide',
			[
				'label'       => esc_html__( 'Glide speed', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::NUMBER,
				'placeholder' => '1',
				'min'         => 0.5,
				'max'         => 2,
				'step'        => 0.1,
				'description' => esc_html__( 'Speed of the move between pieces. 1 is the design.', 'samanzavarian-widgets' ),
				'separator'   => 'before',
			]
		);

		$w->end_controls_section();
	}

	/**
	 * The reel, cleaned: texts, a safe colour, a shape, seconds and placement.
	 *
	 * @param array<string,mixed> $s
	 * @return array<int,array<string,mixed>>
	 */
	public static function pieces( array $s ): array {
		$rows = isset( $s['ai_pieces'] ) && is_array( $s['ai_pieces'] ) && $s['ai_pieces'] ? array_values( $s['ai_pieces'] ) : self::PIECES;
		$out  = [];
		$x    = 0;

		foreach ( $rows as $i => $row ) {
			$look  = isset( self::LOOKS[ $row['look'] ?? '' ] ) ? (string) $row['look'] : array_keys( self::LOOKS )[ $i % count( self::LOOKS ) ];
			$media = is_array( $row['media'] ?? null ) ? $row['media'] : [];
			$html  = Service_Panel::media_html( $media, 'szs-ai__media' );
			$meta  = ! empty( $media['id'] ) ? wp_get_attachment_metadata( (int) $media['id'] ) : [];
			$meta  = is_array( $meta ) ? $meta : [];

			// Shape: chosen, else the file's own, else the placeholder's.
			$ratio = in_array( $row['ratio'] ?? '', self::RATIOS, true ) ? array_map( 'intval', explode( ':', $row['ratio'] ) ) : null;
			if ( ! $ratio && '' !== $html && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
				$ratio = [ (int) $meta['width'], (int) $meta['height'] ];
			}
			$ratio = $ratio ? $ratio : self::LOOKS[ $look ][1];
			$ar    = max( 0.4, min( 2.4, $ratio[0] / $ratio[1] ) );

			// Seconds: chosen, else the video's length, else 5.
			$fixed = is_numeric( $row['seconds'] ?? null ) && '' !== $row['seconds'];
			$secs  = $fixed ? (float) $row['seconds'] : ( ! empty( $meta['length'] ) ? (float) $meta['length'] : (float) self::LOOKS[ $look ][2] );

			$client = trim( (string) ( $row['client'] ?? '' ) );
			$ini    = trim( (string) ( $row['initials'] ?? '' ) );
			if ( '' === $ini && '' !== $client ) {
				$words = preg_split( '/\s+/u', $client );
				$ini   = mb_strtoupper( mb_substr( $words[0], 0, 1 ) . ( isset( $words[1] ) ? mb_substr( $words[1], 0, 1 ) : '' ) );
			}

			$w     = (int) round( self::WIN[1] * $ar );
			$out[] = [
				'html'   => $html,
				'look'   => $look,
				'video'  => false !== strpos( $html, '<video' ),
				'ar'     => $ratio[0] . ':' . $ratio[1],
				'x'      => $x,
				'w'      => $w,
				'client' => $client,
				'ini'    => mb_substr( $ini, 0, 3 ),
				'color'  => self::color( $row['color'] ?? '' ),
				'brief'  => trim( (string) ( $row['brief'] ?? '' ) ),
				'format' => trim( (string) ( $row['format'] ?? '' ) ),
				'secs'   => max( 2.0, min( 30.0, $secs ) ),
				'fixed'  => $fixed,
				'result' => trim( (string) ( $row['result'] ?? '' ) ),
				'_id'    => (string) ( $row['_id'] ?? '' ),
			];
			$x += $w + self::GAP;
		}

		return $out;
	}

	/** m:ss, like a video player. */
	public static function clock( float $secs ): string {
		$s = (int) floor( $secs );

		return floor( $s / 60 ) . ':' . str_pad( (string) ( $s % 60 ), 2, '0', STR_PAD_LEFT );
	}

	/** @param array<string,mixed> $p a cleaned piece */
	public static function brief_html( array $p, string $label ): string {
		$avatar = '<span class="szs-ai__av"' . ( '' !== $p['color'] ? ' style="background-color:' . esc_attr( $p['color'] ) . '"' : '' ) . '>' . esc_html( $p['ini'] ) . '</span>';
		$who    = '<b>' . esc_html( $p['client'] ) . '</b>' . ( '' !== $label ? ' · ' . esc_html( $label ) : '' );
		$quote  = '' !== $p['brief'] ? '<span class="szs-ai__q">“' . esc_html( $p['brief'] ) . '”</span>' : '';

		return $avatar . '<span class="szs-ai__bt"><span class="szs-ai__who">' . $who . '</span>' . $quote . '</span>';
	}

	public static function render( array $s, string $uid ): string {
		$pieces = self::pieces( $s );
		$first  = $pieces[0];
		$label  = self::text( $s, 'ai_brief_label', 'client brief' );
		$n      = count( $pieces );
		$badge  = Icon_Library::html( $s['ai_badge'] ?? Icon_Library::value( 'spark' ) );
		$num    = static fn( $i ) => str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT );

		$config = [
			'pieces'   => array_map(
				static fn( $p ) => [
					'brief'  => self::brief_html( $p, $label ),
					'format' => $p['format'],
					'secs'   => $p['secs'],
					'fixed'  => $p['fixed'],
					'result' => $p['result'],
				],
				$pieces
			),
			'overview' => 'yes' === ( $s['ai_overview'] ?? 'yes' ),
			'af'       => 'yes' === ( $s['ai_af'] ?? 'yes' ),
			'glide'    => self::num( $s, 'ai_glide', 1, 0.5, 2 ),
		];

		$items = '';
		foreach ( $pieces as $i => $p ) {
			$cls    = 'szs-ai__item' . ( '' === $p['html'] ? ' szs-ph szs-ph--' . $p['look'] : '' ) . ( 0 === $i ? ' is-focus' : ' szs-off' ) . ( '' !== $p['_id'] ? ' elementor-repeater-item-' . $p['_id'] : '' );
			$inner  = '' !== $p['html'] ? $p['html'] : '<span class="szs-ph__fx">' . self::placeholder( $p['look'] ) . '</span>';
			$items .= '<div class="' . esc_attr( $cls ) . '" style="left:' . $p['x'] . 'px;width:' . $p['w'] . 'px" data-n="' . $num( $i ) . '" data-ar="' . esc_attr( $p['ar'] ) . '">' . $inner . '</div>';
		}

		// Resting: the first piece centred in the window.
		$shift = round( self::WIN[0] / 2 - ( $first['x'] + $first['w'] / 2 ), 1 );
		$fmt   = ( '' !== $first['format'] ? $first['format'] . ' · ' : '' ) . '0:00 / ' . self::clock( $first['secs'] );
		$reel  = str_replace( '{n}', (string) $n, self::text( $s, 'ai_reel_label', 'Showreel · {n} pieces' ) );

		$html  = '' !== $badge ? '<span class="szs-ai__badge sz-app sz-app--orange">' . $badge . '</span>' : '';
		$html .= '<div class="szs-ai__sheet" data-szs-ai="' . self::data( $config ) . '">';
		$html .= '<div class="szs-ai__brief"><div>' . self::brief_html( $first, $label ) . '</div></div>';
		$html .= '<div class="szs-ai__win">';
		$html .= '<div class="szs-ai__strip" style="transform:translateX(' . $shift . 'px)">' . $items . '</div>';
		$html .= '<div class="szs-ai__af"><i></i><i></i><i></i><i></i></div>';
		$html .= '<div class="szs-ai__hud"><span class="szs-ai__n">' . $num( 0 ) . ' / ' . str_pad( (string) $n, 2, '0', STR_PAD_LEFT ) . '</span><span class="szs-ai__f">' . esc_html( $fmt ) . '</span></div>';
		$html .= '' !== $reel ? '<span class="szs-ai__cap">' . esc_html( $reel ) . '</span>' : '';
		$html .= '<div class="szs-ai__prog"><i></i></div>';
		$html .= '</div></div>';
		$html .= '<span class="szs-ai__ok"' . ( '' === $first['result'] ? ' hidden' : '' ) . '><i></i><span><em>' . esc_html( $first['result'] ) . '</em></span></span>';

		return $html;
	}

	/** The placeholder "footage": shapes the stylesheet animates (assets/css/widgets/scenes/ai.css). */
	public static function placeholder( string $look ): string {
		$i = static fn( string $c, string $style = '' ) => '<i class="' . $c . '"' . ( '' !== $style ? ' style="' . $style . '"' : '' ) . '></i>';

		switch ( $look ) {
			case 'watch':
				return '<i class="wt-wrap"><b class="wt-silk"></b></i><i class="wt-case"><b class="wt-dial"><b class="wt-hand h2"></b><b class="wt-hand"></b><b class="wt-glint"></b></b></i>';
			case 'coffee':
				return $i( 'cf-steam', '--x:-14px' ) . $i( 'cf-steam', '--x:4px;animation-delay:-1.3s' ) . $i( 'cf-steam', '--x:16px;animation-delay:-2.5s' ) . $i( 'cf-saucer' ) . $i( 'cf-handle' ) . $i( 'cf-cup' );
			case 'serum':
				return $i( 'sr-glow' ) . $i( 'sr-ring', 'animation-delay:.84s' ) . $i( 'sr-ring', 'animation-delay:1.14s' ) . $i( 'sr-ring', 'animation-delay:1.44s' ) . $i( 'sr-drop' );
			case 'citrus':
				$b = '';
				foreach ( [ 12, 30, 46, 64, 82, 92 ] as $k => $x ) {
					$b .= $i( 'ct-b', 'left:' . $x . '%;animation-delay:' . ( -0.55 * $k ) . 's' );
				}
				return $i( 'ct-slice', '--s:70px;left:10%;top:16%' ) . $i( 'ct-slice', '--s:50px;left:52%;top:50%;animation-delay:-2s' ) . $i( 'ct-slice', '--s:34px;left:68%;top:12%;animation-delay:-4s' ) . $b;
			case 'night':
				$st = '';
				foreach ( [ [ 150, '#ff5a3c', 1.1, 0, 120 ], [ 164, '#ffffff', 0.8, -0.4, 90 ], [ 176, '#ff5a3c', 1.4, -0.9, 160 ], [ 186, '#fff3d6', 0.9, -0.2, 110 ], [ 158, '#ffd27f', 1.7, -1.1, 70 ] ] as $l ) {
					$st .= $i( 'nd-st', 'top:' . $l[0] . 'px;--c:' . $l[1] . ';--t:' . $l[2] . 's;--w:' . $l[4] . 'px;animation-delay:' . $l[3] . 's' );
				}
				return $i( 'nd-bk', '--s:26px;--c:#ffb36b;left:12%;top:18%' ) . $i( 'nd-bk', '--s:18px;--c:#7fb2ff;left:34%;top:26%;animation-delay:-1s' ) . $i( 'nd-bk', '--s:30px;--c:#ff7a5c;left:62%;top:14%;animation-delay:-2s' ) . $i( 'nd-bk', '--s:16px;--c:#fff1c9;left:82%;top:30%' ) . $i( 'nd-road' ) . $st;
			default: // perfume
				return $i( 'pf-glow' ) . $i( 'pf-stone' ) . $i( 'pf-cap' ) . '<i class="pf-bottle"><b class="pf-rim"></b></i>' . $i( 'pf-drop', 'left:30%;top:64%' ) . $i( 'pf-drop', 'left:80%;top:70%;animation-delay:-1.2s' ) . $i( 'pf-drop', 'left:44%;top:78%;animation-delay:-.5s' );
		}
	}

	public static function notes( array $s ): array {
		$notes = [];
		foreach ( self::pieces( $s ) as $i => $p ) {
			if ( mb_strlen( $p['brief'] ) > 100 ) {
				/* translators: %d: piece number */
				$notes[] = sprintf( __( 'Piece %d: the brief is longer than two lines fit, so its end is cut off. Keep it under about 95 characters.', 'samanzavarian-widgets' ), $i + 1 );
			}
		}
		if ( 1 === count( self::pieces( $s ) ) ) {
			$notes[] = __( 'One piece: the reel has nothing to move to, so it simply plays that piece. Add more to see the glide.', 'samanzavarian-widgets' );
		}

		return $notes;
	}

	public static function has_video( array $s ): bool {
		return self::any_video( array_column( isset( $s['ai_pieces'] ) && is_array( $s['ai_pieces'] ) ? $s['ai_pieces'] : [], 'media' ) );
	}
}
