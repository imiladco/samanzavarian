<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Plugin as Elementor;
use Elementor\Widget_Base;
use Samanzavarian_Widgets\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Reply Promise — "Replies within two business days", made concrete.
 *
 * A promise in days is abstract; the line under it names the actual day
 * ("Write today, hear back by Tuesday, 21 October"), counted in working days
 * in your time zone, skipping your days off — the same count as the Project
 * Form's confirmation, so the two never disagree. The day is worked out on
 * the server (no jump on load) and again in the browser (a cached page stays
 * right).
 *
 * The clock in the tile sweeps once when the promise comes into view, and
 * again under the pointer; at rest nothing moves.
 */
class Reply_Promise extends Widget_Base {

	public function get_name(): string {
		return 'sz-promise';
	}

	public function get_title(): string {
		return esc_html__( 'Reply Promise', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-clock-o';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'reply', 'response', 'time', 'promise', 'business days', 'contact', 'sla' ];
	}

	public function get_style_depends(): array {
		return [ 'sz-promise' ];
	}

	public function get_script_depends(): array {
		return [ 'sz-promise' ];
	}

	protected function is_dynamic_content(): bool {
		return true; // the day changes; never served from Elementor's element cache
	}

	public function has_widget_inner_wrapper(): bool {
		return ! Elementor::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	protected function register_controls(): void {
		$this->start_controls_section( 'section_promise', [ 'label' => esc_html__( 'Promise', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'title', [ 'label' => esc_html__( 'Promise', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'label_block' => true, 'default' => esc_html__( 'Replies within two business days', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'days', [ 'label' => esc_html__( 'Working days', 'samanzavarian-widgets' ), 'type' => Controls_Manager::NUMBER, 'default' => 2, 'min' => 1, 'max' => 14, 'description' => esc_html__( 'Keep it the same as the Project Form\'s "Reply within".', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'show_day', [ 'label' => esc_html__( 'Name the day', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ] );
		$this->add_control( 'day_text', [ 'label' => esc_html__( 'Day line', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'label_block' => true, 'default' => esc_html__( 'Write today, hear back by %s', 'samanzavarian-widgets' ), 'description' => esc_html__( '%s becomes the day, like "Tuesday, 21 October".', 'samanzavarian-widgets' ), 'condition' => [ 'show_day' => 'yes' ] ] );
		$this->add_control(
			'days_off',
			[
				'label'    => esc_html__( 'Days off', 'samanzavarian-widgets' ),
				'type'     => Controls_Manager::SELECT2,
				'multiple' => true,
				'default'  => [ '5' ],
				'options'  => [ '6' => esc_html__( 'Saturday', 'samanzavarian-widgets' ), '0' => esc_html__( 'Sunday', 'samanzavarian-widgets' ), '1' => esc_html__( 'Monday', 'samanzavarian-widgets' ), '2' => esc_html__( 'Tuesday', 'samanzavarian-widgets' ), '3' => esc_html__( 'Wednesday', 'samanzavarian-widgets' ), '4' => esc_html__( 'Thursday', 'samanzavarian-widgets' ), '5' => esc_html__( 'Friday', 'samanzavarian-widgets' ) ],
				'condition' => [ 'show_day' => 'yes' ],
			]
		);
		$this->add_control( 'tz', [ 'label' => esc_html__( 'Your time zone', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Asia/Tehran', 'ai' => [ 'active' => false ], 'condition' => [ 'show_day' => 'yes' ] ] );
		$this->add_control(
			'look',
			[
				'label'     => esc_html__( 'Look', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::CHOOSE,
				'default'   => 'pill',
				'separator' => 'before',
				'options'   => [
					'pill'  => [ 'title' => esc_html__( 'Pill', 'samanzavarian-widgets' ), 'icon' => 'eicon-button' ],
					'plain' => [ 'title' => esc_html__( 'Plain, no background', 'samanzavarian-widgets' ), 'icon' => 'eicon-text' ],
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
				'selectors' => [ '{{WRAPPER}} .sz-promise-wrap' => 'justify-content: {{VALUE}};' ],
			]
		);
		$this->end_controls_section();
	}

	/**
	 * The day of the reply: $n working days after today in $tz, skipping $off
	 * (0 = Sunday). Same count as js/form.js and js/promise.js.
	 *
	 * @param int[] $off
	 */
	public static function reply_day( int $n, array $off, string $tz, ?int $now = null ): \DateTimeImmutable {
		try {
			$zone = new \DateTimeZone( $tz );
		} catch ( \Exception $e ) {
			$zone = wp_timezone();
		}
		$d = ( new \DateTimeImmutable( '@' . ( $now ?? time() ) ) )->setTimezone( $zone )->setTime( 12, 0 );
		if ( count( array_unique( $off ) ) >= 7 ) {
			$off = [];
		}
		while ( $n > 0 ) {
			$d = $d->modify( '+1 day' );
			if ( ! in_array( (int) $d->format( 'w' ), $off, true ) ) {
				$n--;
			}
		}
		return $d;
	}

	protected function render(): void {
		$s    = $this->get_settings_for_display();
		$days = max( 1, (int) ( $s['days'] ?? 2 ) );
		$off  = array_map( 'intval', (array) ( $s['days_off'] ?? [ '5' ] ) );
		$tz   = trim( (string) ( $s['tz'] ?? 'Asia/Tehran' ) ) ?: 'Asia/Tehran';
		$line = (string) ( $s['day_text'] ?? '%s' );
		$show = 'yes' === ( $s['show_day'] ?? 'yes' );
		$day  = self::reply_day( $days, $off, $tz )->format( 'l, j F' );
		?>
		<div class="sz-promise-wrap">
			<div class="sz-promise sz-w is-<?php echo esc_attr( 'plain' === ( $s['look'] ?? 'pill' ) ? 'plain' : 'pill' ); ?>" data-sz-promise data-config="<?php echo esc_attr( (string) wp_json_encode( [ 'days' => $days, 'off' => $off, 'tz' => $tz ] ) ); ?>">
				<span class="sz-promise__tile" aria-hidden="true">
					<svg viewBox="0 0 24 24" focusable="false"><circle class="sz-promise__face" cx="12" cy="12" r="8.6"/><path class="sz-promise__h" d="M12 12V8.4"/><path class="sz-promise__m" d="M12 12h3.6"/><circle class="sz-promise__pin" cx="12" cy="12" r="1.2"/></svg>
				</span>
				<span class="sz-promise__t">
					<span class="sz-promise__title"><?php echo esc_html( (string) ( $s['title'] ?? '' ) ); ?></span>
					<?php if ( $show ) : ?>
						<small class="sz-promise__day" data-line="<?php echo esc_attr( $line ); ?>"><?php echo wp_kses( str_replace( '%s', '<b>' . esc_html( $day ) . '</b>', esc_html( $line ) ), [ 'b' => [] ] ); ?></small>
					<?php endif; ?>
				</span>
			</div>
		</div>
		<?php
	}
}
