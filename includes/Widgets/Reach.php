<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Plugin as Elementor;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Samanzavarian_Widgets\Icon_Library;
use Samanzavarian_Widgets\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Reach — the ways to get in touch, as one grouped list: email, WhatsApp,
 * Telegram, LinkedIn, Instagram, a phone number or any link.
 *
 * Each row is built from what is typed (an address, a number, a username)
 * into the right link and the right icon. WhatsApp opens with a first
 * message already written. WhatsApp and Telegram show "Online" during your
 * working hours, in your time zone (worked out in the browser, so cached
 * pages stay right). Email has Copy: on a computer, where a mail app is
 * often missing, the whole row copies the address; on a phone it opens the
 * mail app.
 *
 * Drawn like the Project Form's needs: a soft tray of rows, each icon in a
 * white tile in its channel's colour. Under the pointer one white card
 * glides from row to row (it follows you down the list rather than each row
 * popping on its own), the other rows step back, the row's tile fills with
 * its channel's colour and the arrow opens into what will happen ("Message",
 * "Call", "Connect"). The rows come in one after another the first time the
 * list scrolls into view.
 */
class Reach extends Widget_Base {

	public const TYPES = [ 'email', 'whatsapp', 'telegram', 'linkedin', 'instagram', 'phone', 'link' ];

	private const ICONS = [ 'email' => 'mail', 'whatsapp' => 'wa', 'telegram' => 'tg', 'linkedin' => 'in', 'instagram' => 'ig', 'phone' => 'phone', 'link' => 'link' ];

	/** What a row does, said on hover. */
	private const VERBS = [ 'whatsapp' => 'Message', 'telegram' => 'Message', 'linkedin' => 'Connect', 'instagram' => 'Follow', 'phone' => 'Call', 'link' => 'Visit' ];

	private const ROWS = [
		[ 'email', 'Email', 'saman.zavarian@gmail.com' ],
		[ 'whatsapp', 'WhatsApp', '+98 903 702 2029' ],
		[ 'linkedin', 'LinkedIn', 'saman-zavarian' ],
	];

	public function get_name(): string {
		return 'sz-reach';
	}

	public function get_title(): string {
		return esc_html__( 'Reach', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-envelope';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'contact', 'reach', 'email', 'whatsapp', 'telegram', 'linkedin', 'instagram', 'social' ];
	}

	public function get_style_depends(): array {
		return [ 'sz-reach' ];
	}

	public function get_script_depends(): array {
		return [ 'sz-reach' ];
	}

	protected function is_dynamic_content(): bool {
		return false;
	}

	public function has_widget_inner_wrapper(): bool {
		return ! Elementor::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	protected function register_controls(): void {
		$this->start_controls_section( 'section_rows', [ 'label' => esc_html__( 'Ways to reach you', 'samanzavarian-widgets' ) ] );
		$r = new Repeater();
		$r->add_control(
			'type',
			[
				'label'   => esc_html__( 'Kind', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'email',
				'options' => [ 'email' => 'Email', 'whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'linkedin' => 'LinkedIn', 'instagram' => 'Instagram', 'phone' => esc_html__( 'Phone', 'samanzavarian-widgets' ), 'link' => esc_html__( 'Any link', 'samanzavarian-widgets' ) ],
			]
		);
		$r->add_control( 'label', [ 'label' => esc_html__( 'Name', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Email' ] );
		$r->add_control( 'value', [ 'label' => esc_html__( 'Address, number or username', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'label_block' => true, 'ai' => [ 'active' => false ], 'description' => esc_html__( 'Email: the address. WhatsApp and phone: the number with its country code. Telegram, Instagram, LinkedIn: the username (or the full link). Any link: the address.', 'samanzavarian-widgets' ) ] );
		$r->add_control( 'shown', [ 'label' => esc_html__( 'Shown as', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'placeholder' => esc_html__( 'Automatic', 'samanzavarian-widgets' ), 'description' => esc_html__( 'The grey line under the name; written from the value if empty.', 'samanzavarian-widgets' ) ] );
		$r->add_control( 'online', [ 'label' => esc_html__( '"Online" in working hours', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'condition' => [ 'type' => [ 'whatsapp', 'telegram' ] ] ] );
		$this->add_control(
			'rows',
			[
				'label'       => esc_html__( 'Rows', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => array_map( static fn( $x ) => [ 'type' => $x[0], 'label' => $x[1], 'value' => $x[2], 'online' => 'yes' ], self::ROWS ),
				'title_field' => '{{{ label }}}',
			]
		);
		$this->add_control( 'wa_text', [ 'label' => esc_html__( 'First WhatsApp message', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2, 'separator' => 'before', 'default' => esc_html__( 'Hi Saman, I saw your portfolio and I have a project in mind.', 'samanzavarian-widgets' ), 'description' => esc_html__( 'Already typed when WhatsApp opens; they can change it before sending.', 'samanzavarian-widgets' ) ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_hours', [ 'label' => esc_html__( 'Working hours', 'samanzavarian-widgets' ) ] );
		$this->add_control(
			'days',
			[
				'label'    => esc_html__( 'Days', 'samanzavarian-widgets' ),
				'type'     => Controls_Manager::SELECT2,
				'multiple' => true,
				'default'  => [ '6', '0', '1', '2', '3', '4' ],
				'options'  => [ '6' => esc_html__( 'Saturday', 'samanzavarian-widgets' ), '0' => esc_html__( 'Sunday', 'samanzavarian-widgets' ), '1' => esc_html__( 'Monday', 'samanzavarian-widgets' ), '2' => esc_html__( 'Tuesday', 'samanzavarian-widgets' ), '3' => esc_html__( 'Wednesday', 'samanzavarian-widgets' ), '4' => esc_html__( 'Thursday', 'samanzavarian-widgets' ), '5' => esc_html__( 'Friday', 'samanzavarian-widgets' ) ],
			]
		);
		$this->add_control( 'from', [ 'label' => esc_html__( 'From', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => '10:00', 'placeholder' => '10:00' ] );
		$this->add_control( 'to', [ 'label' => esc_html__( 'To', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => '19:00', 'placeholder' => '19:00' ] );
		$this->add_control( 'tz', [ 'label' => esc_html__( 'Time zone', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Asia/Tehran', 'ai' => [ 'active' => false ] ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_labels', [ 'label' => esc_html__( 'Labels', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'l_copy', [ 'label' => esc_html__( 'Copy', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => esc_html__( 'Copy', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'l_copied', [ 'label' => esc_html__( 'Copied', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => esc_html__( 'Copied', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'l_online', [ 'label' => esc_html__( 'Online', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => esc_html__( 'Online', 'samanzavarian-widgets' ) ] );
		$this->end_controls_section();
		$this->register_style();
	}

	private function register_style(): void {
		$w = '{{WRAPPER}} .sz-reach';
		Style_Controls::box( $this, 'st_tray', esc_html__( 'The list', 'samanzavarian-widgets' ), $w );
		Style_Controls::box( $this, 'st_glide', esc_html__( 'The card under the pointer', 'samanzavarian-widgets' ), $w . ' .sz-reach__glide' );
		Style_Controls::text( $this, 'st_name', esc_html__( 'Names', 'samanzavarian-widgets' ), $w . ' .sz-reach__name', [ 'no_margin' => true ] );
		Style_Controls::text( $this, 'st_sub', esc_html__( 'Addresses', 'samanzavarian-widgets' ), $w . ' .sz-reach__sub', [ 'no_margin' => true ] );
		Style_Controls::tile( $this, 'st_tile', esc_html__( 'Icon tiles', 'samanzavarian-widgets' ), $w . ' .sz-reach__tile' );
		Style_Controls::box( $this, 'st_go', esc_html__( 'Action ("Message →")', 'samanzavarian-widgets' ), $w . ' .sz-reach__row.is-hot .sz-reach__go' );
		Style_Controls::box( $this, 'st_copy', esc_html__( 'Copy button', 'samanzavarian-widgets' ), $w . ' .sz-reach__row .sz-reach__copy', [ 'extra' => [ 'c' => [ 'label' => esc_html__( 'Text colour', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $w . ' .sz-reach__row .sz-reach__copy' => 'color: {{VALUE}};' ] ] ] ] );
		Style_Controls::box( $this, 'st_on', esc_html__( '"Online"', 'samanzavarian-widgets' ), $w . ' .sz-reach__on', [ 'extra' => [ 'c' => [ 'label' => esc_html__( 'Text colour', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ $w . ' .sz-reach__on' => 'color: {{VALUE}};' ] ] ] ] );
	}

	/** Strip a full link down to the username. */
	private static function handle( string $v ): string {
		$v = trim( $v );
		$v = preg_replace( '#^https?://(www\.)?[^/]+/(in/)?#i', '', $v );
		return trim( (string) $v, "@/ \t" );
	}

	/**
	 * A row's link, and the grey line under its name.
	 *
	 * @return array{0:string,1:string}
	 */
	public static function target( string $type, string $value, string $wa_text = '' ): array {
		$value = trim( $value );
		switch ( $type ) {
			case 'email':
				return [ 'mailto:' . sanitize_email( $value ), $value ];
			case 'whatsapp':
				$d = preg_replace( '/\D/', '', $value );
				return [ 'https://wa.me/' . $d . ( '' !== trim( $wa_text ) ? '?text=' . rawurlencode( trim( $wa_text ) ) : '' ), $value ];
			case 'telegram':
				$h = self::handle( $value );
				return [ 'https://t.me/' . rawurlencode( $h ), '@' . $h ];
			case 'instagram':
				$h = self::handle( $value );
				return [ 'https://www.instagram.com/' . rawurlencode( $h ) . '/', '@' . $h ];
			case 'linkedin':
				$h = self::handle( $value );
				return [ preg_match( '#^https?://#i', $value ) ? $value : 'https://www.linkedin.com/in/' . rawurlencode( $h ), 'in/' . $h ];
			case 'phone':
				return [ 'tel:' . preg_replace( '/[^+0-9]/', '', $value ), $value ];
			default:
				$url = preg_match( '#^https?://#i', $value ) ? $value : 'https://' . $value;
				return [ $url, preg_replace( '#^https?://(www\.)?#i', '', rtrim( $value, '/' ) ) ];
		}
	}

	protected function render(): void {
		$s    = $this->get_settings_for_display();
		$rows = array_values( array_filter( (array) ( $s['rows'] ?? [] ), static fn( $r ) => '' !== trim( (string) ( $r['value'] ?? '' ) ) ) );
		if ( ! $rows ) {
			return;
		}
		$this->add_render_attribute(
			'root',
			[
				'class'      => 'sz-reach sz-w',
				'data-sz-rc' => '',
				'data-hours' => wp_json_encode(
					[
						'days' => array_map( 'intval', (array) ( $s['days'] ?? [] ) ),
						'from' => (string) ( $s['from'] ?? '10:00' ),
						'to'   => (string) ( $s['to'] ?? '19:00' ),
						'tz'   => trim( (string) ( $s['tz'] ?? 'Asia/Tehran' ) ) ?: 'Asia/Tehran',
					]
				),
				'data-copied' => (string) ( $s['l_copied'] ?? 'Copied' ),
			]
		);
		?>
		<ul <?php $this->print_render_attribute_string( 'root' ); ?>>
					<li class="sz-reach__glide" aria-hidden="true"></li>
			<?php foreach ( $rows as $r ) : ?>
				<?php
				$type = in_array( $r['type'] ?? '', self::TYPES, true ) ? $r['type'] : 'link';
				[ $href, $line ] = self::target( $type, (string) $r['value'], (string) ( $s['wa_text'] ?? '' ) );
				$shown  = trim( (string) ( $r['shown'] ?? '' ) ) ?: $line;
				$out    = ! in_array( $type, [ 'email', 'phone' ], true );
				$online = in_array( $type, [ 'whatsapp', 'telegram' ], true ) && 'yes' === ( $r['online'] ?? 'yes' );
				$copy   = 'email' === $type;
				?>
				<li class="sz-reach__row<?php echo ! empty( $r['_id'] ) ? ' elementor-repeater-item-' . esc_attr( $r['_id'] ) : ''; ?>" data-type="<?php echo esc_attr( $type ); ?>">
					<a class="sz-reach__a" href="<?php echo esc_url( $href, [ 'mailto', 'tel', 'http', 'https' ] ); ?>"<?php echo $out ? ' target="_blank" rel="noopener"' : ''; ?><?php echo $copy ? ' data-copy="' . esc_attr( trim( (string) $r['value'] ) ) . '"' : ''; ?>>
						<span class="sz-reach__tile" aria-hidden="true"><?php echo Icon_Library::svg( self::ICONS[ $type ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static svg ?></span>
						<span class="sz-reach__t">
							<span class="sz-reach__name"><?php echo esc_html( trim( (string) ( $r['label'] ?? '' ) ) ); ?><?php if ( $online ) : ?><span class="sz-reach__on" hidden><?php echo esc_html( (string) ( $s['l_online'] ?? 'Online' ) ); ?></span><?php endif; ?></span>
							<small class="sz-reach__sub"><?php echo esc_html( $shown ); ?></small>
						</span>
						<?php if ( ! $copy ) : ?><span class="sz-reach__go" aria-hidden="true"><span class="sz-reach__verb"><?php echo esc_html( self::VERBS[ $type ] ?? 'Open' ); ?></span><svg class="sz-reach__chev" viewBox="0 0 24 24" focusable="false"><path d="M5 12h13M13 6l6 6-6 6"/></svg></span><?php endif; ?>
					</a>
					<?php if ( $copy ) : ?>
						<button type="button" class="sz-reach__copy" data-copy="<?php echo esc_attr( trim( (string) $r['value'] ) ); ?>" aria-label="<?php echo esc_attr( ( $s['l_copy'] ?? 'Copy' ) . ' ' . trim( (string) $r['value'] ) ); ?>"><span><?php echo esc_html( (string) ( $s['l_copy'] ?? 'Copy' ) ); ?></span></button>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}
}
