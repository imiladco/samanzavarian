<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Plugin as Elementor;
use Elementor\Widget_Base;
use Samanzavarian_Widgets\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Status — the "Available for new projects" pill with a live dot.
 *
 * Three states (available, limited, booked), each with its own text and
 * dot. A booked or limited status can carry a date: the text names it
 * ("Booked until Jun 3") and from that day on the pill reads available by
 * itself — on the server, and in the browser too, so a cached page cannot
 * keep showing an expired status. Optionally the local time where the work
 * happens, for clients in other time zones.
 *
 * The script loads only for an instance that needs it (a date or the clock).
 */
class Status_Pill extends Widget_Base {

	public const STATES = [ 'available', 'limited', 'booked' ];

	public function get_name(): string {
		return 'sz-status';
	}

	public function get_title(): string {
		return esc_html__( 'Status', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-dot-circle-o';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'status', 'available', 'availability', 'booked', 'pill', 'badge', 'time' ];
	}

	public function get_style_depends(): array {
		return [ 'sz-pills' ];
	}

	public function get_script_depends(): array {
		if ( ! $this->get_id() || Elementor::$instance->editor->is_edit_mode() || Elementor::$instance->preview->is_preview_mode() ) {
			return [ 'sz-status' ];
		}

		return self::needs_script( $this->get_settings() ) ? [ 'sz-status' ] : [];
	}

	/** A date can expire and a clock ticks; otherwise the pill is plain HTML. @param array<string,mixed> $s */
	public static function needs_script( array $s ): bool {
		return 'yes' === ( $s['clock'] ?? '' ) || null !== self::until( $s );
	}

	/** Output with a date changes the day it passes, and a clock every minute: never cache those. */
	protected function is_dynamic_content(): bool {
		// Elementor also asks the bare widget type (building the editor's
		// config), which has no settings to read: reading them there is fatal.
		return $this->get_id() ? self::needs_script( $this->get_settings() ) : false;
	}

	public function has_widget_inner_wrapper(): bool {
		return ! Elementor::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	/* =================================================================
	 * Controls
	 * ================================================================= */

	protected function register_controls(): void {
		$this->start_controls_section( 'section_status', [ 'label' => esc_html__( 'Status', 'samanzavarian-widgets' ) ] );

		$this->add_control(
			'state',
			[
				'label'   => esc_html__( 'Status', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'available',
				'options' => [
					'available' => esc_html__( 'Available (green, pulsing)', 'samanzavarian-widgets' ),
					'limited'   => esc_html__( 'Limited (amber)', 'samanzavarian-widgets' ),
					'booked'    => esc_html__( 'Booked (grey)', 'samanzavarian-widgets' ),
				],
			]
		);

		foreach ( self::text_defaults() as $state => $def ) {
			$this->add_control(
				'text_' . $state,
				[
					'label'       => $def[1],
					'type'        => Controls_Manager::TEXT,
					'default'     => $def[0],
					'label_block' => true,
					'description' => $def[2] ?? '',
					'condition'   => 'available' === $state ? [] : [ 'state' => $state ],
				]
			);
		}

		$this->add_control(
			'until',
			[
				'label'          => esc_html__( 'Available again on', 'samanzavarian-widgets' ),
				'type'           => Controls_Manager::DATE_TIME,
				'picker_options' => [ 'enableTime' => false ],
				'description'    => esc_html__( 'Optional. Fills {date} in the text above, and from this day on the pill shows the Available text by itself.', 'samanzavarian-widgets' ),
				'condition'      => [ 'state!' => 'available' ],
			]
		);

		$this->add_control(
			'link',
			[
				'label'       => esc_html__( 'Link', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::URL,
				'placeholder' => '#contact',
				'description' => esc_html__( 'Optional, e.g. to the contact form. An arrow appears on hover.', 'samanzavarian-widgets' ),
				'separator'   => 'before',
				'dynamic'     => [ 'active' => true ],
			]
		);

		$this->add_control(
			'clock',
			[
				'label'        => esc_html__( 'Show local time', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '',
				'return_value' => 'yes',
				'separator'    => 'before',
				'description'  => esc_html__( 'After the text: "14:32 Tehran". Tells clients abroad when you are around. Updates every minute.', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control(
			'timezone',
			[
				'label'       => esc_html__( 'Time zone', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SELECT2,
				'default'     => 'Asia/Tehran',
				'options'     => array_combine( timezone_identifiers_list(), timezone_identifiers_list() ),
				'condition'   => [ 'clock' => 'yes' ],
			]
		);

		$this->add_control(
			'place',
			[
				'label'     => esc_html__( 'Place', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => esc_html__( 'Tehran', 'samanzavarian-widgets' ),
				'condition' => [ 'clock' => 'yes' ],
			]
		);

		$this->add_control(
			'hour12',
			[
				'label'        => esc_html__( '12-hour clock', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '',
				'return_value' => 'yes',
				'condition'    => [ 'clock' => 'yes' ],
			]
		);

		$this->add_control(
			'skin',
			[
				'label'     => esc_html__( 'Skin', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'light',
				'separator' => 'before',
				'options'   => [
					'light' => esc_html__( 'Light glass (on light backgrounds)', 'samanzavarian-widgets' ),
					'dark'  => esc_html__( 'Dark (on dark backgrounds, e.g. the footer)', 'samanzavarian-widgets' ),
				],
			]
		);

		$this->add_responsive_control(
			'align',
			[
				'label'     => esc_html__( 'Alignment', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => [
					'flex-start' => [ 'title' => esc_html__( 'Left', 'samanzavarian-widgets' ), 'icon' => 'eicon-text-align-left' ],
					'center'     => [ 'title' => esc_html__( 'Center', 'samanzavarian-widgets' ), 'icon' => 'eicon-text-align-center' ],
					'flex-end'   => [ 'title' => esc_html__( 'Right', 'samanzavarian-widgets' ), 'icon' => 'eicon-text-align-right' ],
				],
				'selectors' => [ '{{WRAPPER}} .sz-pillrow' => 'justify-content: {{VALUE}};' ],
			]
		);

		$this->end_controls_section();

		Section_Tag::pill_style_controls( $this, 'sz-status' );

		$this->start_controls_section( 'style_dot', [ 'label' => esc_html__( 'Dot', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		foreach ( [
			'available' => esc_html__( 'Available', 'samanzavarian-widgets' ),
			'limited'   => esc_html__( 'Limited', 'samanzavarian-widgets' ),
			'booked'    => esc_html__( 'Booked', 'samanzavarian-widgets' ),
		] as $state => $label ) {
			$this->add_control(
				'dot_' . $state,
				[
					'label'     => $label,
					'type'      => Controls_Manager::COLOR,
					'selectors' => [ '{{WRAPPER}} .sz-status' => '--szp-dot-' . $state . ': {{VALUE}};' ],
				]
			);
		}

		$this->add_control(
			'pulse',
			[
				'label'        => esc_html__( 'Pulse while available', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			]
		);

		$this->end_controls_section();
	}

	/** @return array<string,array{0:string,1:string,2?:string}> */
	private static function text_defaults(): array {
		return [
			'available' => [ __( 'Available for new projects', 'samanzavarian-widgets' ), __( 'Text while available', 'samanzavarian-widgets' ) ],
			'limited'   => [ __( 'Limited availability until {date}', 'samanzavarian-widgets' ), __( 'Text while limited', 'samanzavarian-widgets' ), __( '{date} is the date below; without one, leave it out of the text.', 'samanzavarian-widgets' ) ],
			'booked'    => [ __( 'Booked until {date}', 'samanzavarian-widgets' ), __( 'Text while booked', 'samanzavarian-widgets' ), __( '{date} is the date below; without one, write e.g. "Fully booked".', 'samanzavarian-widgets' ) ],
		];
	}

	/* =================================================================
	 * Data
	 * ================================================================= */

	/** The "available again" date as a timestamp at midnight, site time; null when none or not applicable. @param array<string,mixed> $s */
	public static function until( array $s ): ?int {
		$raw = trim( (string) ( $s['until'] ?? '' ) );

		if ( '' === $raw || 'available' === ( $s['state'] ?? 'available' ) ) {
			return null;
		}

		try {
			$day = new \DateTimeImmutable( substr( $raw, 0, 10 ) . ' 00:00:00', wp_timezone() );
		} catch ( \Exception $e ) {
			return null;
		}

		return $day->getTimestamp();
	}

	/**
	 * What the pill says now: the state (after a passed date, available) and
	 * its text with {date} filled.
	 *
	 * @param array<string,mixed> $s
	 * @return array{state:string,text:string,available:string}
	 */
	public static function current( array $s, ?int $now = null ): array {
		$now   = $now ?? time();
		$state = in_array( $s['state'] ?? 'available', self::STATES, true ) ? $s['state'] : 'available';
		$until = self::until( $s );
		$avail = self::text( $s, 'available' );

		if ( null !== $until && $now >= $until ) {
			$state = 'available';
		}

		$text = 'available' === $state ? $avail : self::text( $s, $state );
		$date = null !== $until ? wp_date( 'M j', $until ) : '';
		$text = trim( preg_replace( '/\s+/', ' ', str_replace( '{date}', (string) $date, $text ) ) );

		return [ 'state' => $state, 'text' => $text, 'available' => $avail ];
	}

	/**
	 * The time now in a zone, as the script writes it, so the first paint
	 * already has it and nothing shifts when the script takes over.
	 */
	public static function clock( string $tz, bool $h12 ): string {
		try {
			$now = new \DateTimeImmutable( 'now', new \DateTimeZone( $tz ) );
		} catch ( \Exception $e ) {
			return '';
		}

		return $now->format( $h12 ? 'g:i A' : 'H:i' );
	}

	/** @param array<string,mixed> $s */
	private static function text( array $s, string $state ): string {
		$value = trim( (string) ( $s[ 'text_' . $state ] ?? '' ) );

		return '' !== $value ? $value : self::text_defaults()[ $state ][0];
	}

	/* =================================================================
	 * Render
	 * ================================================================= */

	protected function render(): void {
		$s     = $this->get_settings_for_display();
		$now   = self::current( $s );
		$until = self::until( $s );
		$clock = 'yes' === ( $s['clock'] ?? '' );
		$link  = is_array( $s['link'] ?? null ) && '' !== trim( (string) ( $s['link']['url'] ?? '' ) ) ? $s['link'] : null;
		$tag   = $link ? 'a' : 'span';

		$this->add_render_attribute(
			'pill',
			[
				'class'          => array_filter( [
					'sz-pill',
					'sz-status',
					'is-' . $now['state'],
					'dark' === ( $s['skin'] ?? 'light' ) ? 'sz-pill--dark' : '',
					'yes' === ( $s['pulse'] ?? 'yes' ) ? '' : 'no-pulse',
					$link ? 'is-link' : '',
				] ),
				'data-sz-status' => '',
			]
		);

		if ( null !== $until && 'available' !== $now['state'] ) {
			$this->add_render_attribute( 'pill', [ 'data-until' => (string) ( $until * 1000 ), 'data-available' => $now['available'] ] );
		}
		if ( $clock ) {
			$tz = in_array( $s['timezone'] ?? '', timezone_identifiers_list(), true ) ? $s['timezone'] : 'UTC';
			$this->add_render_attribute( 'pill', [ 'data-tz' => $tz, 'data-h12' => 'yes' === ( $s['hour12'] ?? '' ) ? '1' : '0' ] );
		}
		if ( $link ) {
			$this->add_link_attributes( 'pill', $link );
		}
		?>
		<div class="sz-pillrow sz-w">
			<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a or span ?> <?php $this->print_render_attribute_string( 'pill' ); ?>>
				<i class="sz-status__dot" aria-hidden="true"></i>
				<span class="sz-status__text"><?php echo esc_html( $now['text'] ); ?></span>
				<?php if ( $clock ) : ?>
					<span class="sz-status__clock"><span class="sz-status__sep" aria-hidden="true"></span><time class="sz-status__time"><?php echo esc_html( self::clock( (string) $tz, 'yes' === ( $s['hour12'] ?? '' ) ) ); ?></time> <span class="sz-status__place"><?php echo esc_html( (string) ( $s['place'] ?? '' ) ); ?></span></span>
				<?php endif; ?>
				<?php if ( $link ) : ?>
					<svg class="sz-pill__arrow" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
				<?php endif; ?>
			</<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a or span ?>>
		</div>
		<?php
	}
}
