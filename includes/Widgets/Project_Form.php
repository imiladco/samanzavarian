<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Plugin as Elementor;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Samanzavarian_Widgets\Icon_Library;
use Samanzavarian_Widgets\Leads;
use Samanzavarian_Widgets\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Project Form — "What do you need?", who you are, the project, and (if
 * you like) when and for how much. Requests are kept in WordPress
 * (Requests in the admin) and announced on Telegram.
 *
 * Rethought from the design:
 * - Needs are tiles in a tray you can combine (a project is often AI and
 *   editing): a picked tile lifts out as a white card, its icon warms to
 *   orange and a check pops on its corner; "2 selected" counts beside the
 *   question. One row of tiles on a phone too: they tighten instead.
 * - Timeline, budget and a reference link wait behind "More details", so the
 *   form starts short. Closed, the row says what was picked inside it.
 * - Timeline and budget are optional one-tap chips; tap again to clear.
 * - A field that is right gets a small green tick when you leave it.
 * - The message box shows examples while it is empty, and its counter only
 *   near the limit. What is typed is kept in this browser as a draft until
 *   it is sent, so a reload loses nothing.
 * - Mistakes are said in words beside the field, on leaving it or sending.
 * - Sent: the form gives way to a confirmation with the actual day of the
 *   reply ("I'll reply by Tuesday, 21 October"), working days only.
 * - No captcha: a field only bots fill, a minimum time, a rate limit and a
 *   duplicate check, on the server (Leads).
 * - The Quote & Reply widget's quick replies fill it (message and need).
 */
class Project_Form extends Widget_Base {

	private const SERVICES = [ [ 'AI visuals', 'AI visuals', 'spark' ], [ 'Creative direction', 'Creative direction', 'palette' ], [ 'Post-production', 'Post-production', 'sliders' ], [ 'Content systems', 'Content systems', 'cal' ] ];
	
	/** The tick a right field gets. */
	private const OK = '<svg class="sz-pf__ok-i" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><circle cx="10" cy="10" r="9"/><path d="M6.2 10.3l2.6 2.6 5-5.2"/></svg>';
	
	/** A tile saved before tiles had icons: one by its name. */
	private static function guess_icon( string $label ): string {
		$l = strtolower( $label );
		foreach ( [ 'ai' => 'spark', 'direct' => 'palette', 'post' => 'sliders', 'edit' => 'sliders', 'content' => 'cal', 'photo' => 'cam', 'video' => 'video', 'film' => 'film', 'brand' => 'brief' ] as $k => $icon ) {
			if ( preg_match( '/\\b' . $k . '/', $l ) ) {
				return $icon;
			}
		}
		return 'spark';
	}

	public function get_name(): string {
		return 'sz-form';
	}

	public function get_title(): string {
		return esc_html__( 'Project Form', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-form-horizontal';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'form', 'contact', 'brief', 'request', 'project', 'lead', 'telegram' ];
	}

	public function get_style_depends(): array {
		return [ 'sz-form' ];
	}

	public function get_script_depends(): array {
		return [ 'sz-form' ];
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
		$this->start_controls_section( 'section_needs', [ 'label' => esc_html__( 'What they need', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'needs_label', [ 'label' => esc_html__( 'Question', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => esc_html__( 'What do you need?', 'samanzavarian-widgets' ), 'label_block' => true ] );
		$this->add_control( 'needs_hint', [ 'label' => esc_html__( 'Hint', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => esc_html__( 'Pick one or more', 'samanzavarian-widgets' ) ] );
		$r = new Repeater();
		$r->add_control( 'label', [ 'label' => esc_html__( 'Tile', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => esc_html__( 'Service', 'samanzavarian-widgets' ) ] );
		$r->add_control( 'icon', [ 'label' => esc_html__( 'Icon', 'samanzavarian-widgets' ), 'type' => Controls_Manager::ICONS, 'default' => Icon_Library::value( 'spark' ), 'skin' => 'inline', 'label_block' => false ] );
		$r->add_control( 'value', [ 'label' => esc_html__( 'Name in the request', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'description' => esc_html__( 'What you see in Requests and on Telegram; the chip\'s text if empty. Quick replies in Quote & Reply tick a chip by this name.', 'samanzavarian-widgets' ) ] );
		$this->add_control(
			'services',
			[
				'label'       => esc_html__( 'Tiles', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => array_map( static fn( $x ) => [ 'label' => $x[0], 'value' => $x[1], 'icon' => Icon_Library::value( $x[2] ) ], self::SERVICES ),
				'title_field' => '{{{ label }}}',
			]
		);
		$this->end_controls_section();

		$this->start_controls_section( 'section_fields', [ 'label' => esc_html__( 'Fields', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'phone', [ 'label' => esc_html__( 'Phone number', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'description' => esc_html__( 'Optional for the visitor. With a number, the request on Telegram gets a WhatsApp button.', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'link', [ 'label' => esc_html__( 'Reference link', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ] );
		$this->add_control( 'examples', [ 'label' => esc_html__( 'Examples in the message box', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 4, 'separator' => 'before', 'default' => "Launch visuals for a skincare brand, in April.\nA 60-second brand film from footage we already have.\nAI product shots for an online store, about 30 images.", 'description' => esc_html__( 'One per line; they take turns while the box is empty.', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'max', [ 'label' => esc_html__( 'Longest message', 'samanzavarian-widgets' ), 'type' => Controls_Manager::NUMBER, 'default' => 1200, 'min' => 200, 'max' => Leads::MAX_MESSAGE ] );
		$this->add_control( 'timeline', [ 'label' => esc_html__( 'Timeline', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 3, 'separator' => 'before', 'default' => "As soon as possible\nWithin a month\nFlexible", 'description' => esc_html__( 'Optional chips, one per line. Empty hides the question.', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'budget', [ 'label' => esc_html__( 'Budget', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 5, 'default' => "Under $1k\n$1k–3k\n$3k–10k\n$10k+\nNot sure yet", 'description' => esc_html__( 'Optional chips, one per line. Empty hides the question.', 'samanzavarian-widgets' ) ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_reply', [ 'label' => esc_html__( 'After sending', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'reply_days', [ 'label' => esc_html__( 'Reply within (working days)', 'samanzavarian-widgets' ), 'type' => Controls_Manager::NUMBER, 'default' => 2, 'min' => 1, 'max' => 14, 'description' => esc_html__( 'The confirmation names the day: "I\'ll reply by Tuesday, 21 October".', 'samanzavarian-widgets' ) ] );
		$this->add_control(
			'days_off',
			[
				'label'    => esc_html__( 'Days off', 'samanzavarian-widgets' ),
				'type'     => Controls_Manager::SELECT2,
				'multiple' => true,
				'default'  => [ '5' ],
				'options'  => [ '6' => esc_html__( 'Saturday', 'samanzavarian-widgets' ), '0' => esc_html__( 'Sunday', 'samanzavarian-widgets' ), '1' => esc_html__( 'Monday', 'samanzavarian-widgets' ), '2' => esc_html__( 'Tuesday', 'samanzavarian-widgets' ), '3' => esc_html__( 'Wednesday', 'samanzavarian-widgets' ), '4' => esc_html__( 'Thursday', 'samanzavarian-widgets' ), '5' => esc_html__( 'Friday', 'samanzavarian-widgets' ) ],
			]
		);
		$this->add_control( 'tz', [ 'label' => esc_html__( 'Your time zone', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Asia/Tehran', 'ai' => [ 'active' => false ] ] );
		$this->add_control( 'fallback_email', [ 'label' => esc_html__( 'Email if sending fails', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'saman.zavarian@gmail.com', 'description' => esc_html__( 'Named in the message shown if the request cannot be sent.', 'samanzavarian-widgets' ), 'ai' => [ 'active' => false ] ] );
		$this->add_control( 'help', [ 'type' => Controls_Manager::RAW_HTML, 'raw' => wp_kses( sprintf( /* translators: %s: settings link */ __( 'Requests are kept under <b>Requests</b> in the admin. Telegram: %s.', 'samanzavarian-widgets' ), '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . Leads::TYPE . '&page=sz-requests-settings' ) ) . '" target="_blank">' . esc_html__( 'Requests → Settings', 'samanzavarian-widgets' ) . '</a>' ), [ 'b' => [], 'a' => [ 'href' => [], 'target' => [] ] ] ), 'content_classes' => 'elementor-descriptor' ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_labels', [ 'label' => esc_html__( 'Labels', 'samanzavarian-widgets' ) ] );
		foreach ( self::label_defaults() as $key => $def ) {
			$this->add_control( 'label_' . $key, [ 'label' => $def[1], 'type' => Controls_Manager::TEXT, 'default' => $def[0], 'label_block' => strlen( $def[0] ) > 24 || '' === $def[0] ] );
		}
		$this->end_controls_section();
	}

	/** @return array<string,array{0:string,1:string}> */
	private static function label_defaults(): array {
		return [
			'name'      => [ __( 'Your name', 'samanzavarian-widgets' ), __( 'Name', 'samanzavarian-widgets' ) ],
			'email'     => [ __( 'Email', 'samanzavarian-widgets' ), __( 'Email', 'samanzavarian-widgets' ) ],
			'phone'     => [ __( 'Phone or WhatsApp', 'samanzavarian-widgets' ), __( 'Phone', 'samanzavarian-widgets' ) ],
			'optional'  => [ __( 'optional', 'samanzavarian-widgets' ), __( '"optional"', 'samanzavarian-widgets' ) ],
			'message'   => [ __( 'About the project', 'samanzavarian-widgets' ), __( 'Message', 'samanzavarian-widgets' ) ],
			'link'      => [ __( 'Reference link', 'samanzavarian-widgets' ), __( 'Reference link', 'samanzavarian-widgets' ) ],
			'timeline'  => [ __( 'Timing', 'samanzavarian-widgets' ), __( 'Timeline question', 'samanzavarian-widgets' ) ],
			'budget'    => [ __( 'Budget', 'samanzavarian-widgets' ), __( 'Budget question', 'samanzavarian-widgets' ) ],
			'selected'  => [ __( '%d selected', 'samanzavarian-widgets' ), __( 'Needs picked (%d = how many)', 'samanzavarian-widgets' ) ],
			'more'      => [ __( 'More details', 'samanzavarian-widgets' ), __( 'More details: title', 'samanzavarian-widgets' ) ],
			'more_sub'  => [ '', __( 'More details: line under (empty = what is inside)', 'samanzavarian-widgets' ) ],
			'link_set'  => [ __( 'link added', 'samanzavarian-widgets' ), __( 'More details: a link was added', 'samanzavarian-widgets' ) ],
			'privacy'   => [ __( 'Only used to reply to you', 'samanzavarian-widgets' ), __( 'Beside the button', 'samanzavarian-widgets' ) ],
			'send'      => [ __( 'Send request', 'samanzavarian-widgets' ), __( 'Button', 'samanzavarian-widgets' ) ],
			'sending'   => [ __( 'Sending…', 'samanzavarian-widgets' ), __( 'Button while sending', 'samanzavarian-widgets' ) ],
			'e_name'    => [ __( 'Add your name.', 'samanzavarian-widgets' ), __( 'Error: name', 'samanzavarian-widgets' ) ],
			'e_email'   => [ __( 'Add an email address like name@company.com.', 'samanzavarian-widgets' ), __( 'Error: email', 'samanzavarian-widgets' ) ],
			'e_phone'   => [ __( 'Check the number, or leave it empty.', 'samanzavarian-widgets' ), __( 'Error: phone', 'samanzavarian-widgets' ) ],
			'e_message' => [ __( 'Add a sentence or two about the project.', 'samanzavarian-widgets' ), __( 'Error: message', 'samanzavarian-widgets' ) ],
			'e_link'    => [ __( 'Add a full link that starts with https://, or leave it empty.', 'samanzavarian-widgets' ), __( 'Error: link', 'samanzavarian-widgets' ) ],
			'e_rate'    => [ __( 'Too many requests from here for now. Please try again in an hour.', 'samanzavarian-widgets' ), __( 'Error: too many', 'samanzavarian-widgets' ) ],
			'e_fail'    => [ __( 'That did not go through. Email %s and it reaches the same inbox.', 'samanzavarian-widgets' ), __( 'Error: not sent (%s = email)', 'samanzavarian-widgets' ) ],
			'draft'     => [ __( 'Your draft is back.', 'samanzavarian-widgets' ), __( 'Draft restored', 'samanzavarian-widgets' ) ],
			'clear'     => [ __( 'Clear', 'samanzavarian-widgets' ), __( 'Clear the draft', 'samanzavarian-widgets' ) ],
			'ok_title'  => [ __( 'Request sent', 'samanzavarian-widgets' ), __( 'Sent: title', 'samanzavarian-widgets' ) ],
			'ok_text'   => [ __( 'Thanks, %1$s. I\'ll reply by %2$s.', 'samanzavarian-widgets' ), __( 'Sent: text (%1$s name, %2$s day)', 'samanzavarian-widgets' ) ],
			'again'     => [ __( 'Send another request', 'samanzavarian-widgets' ), __( 'Sent: start again', 'samanzavarian-widgets' ) ],
		];
	}

	/** @return string[] */
	private static function lines( string $s ): array {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\R/u', $s ) ?: [] ) ) );
	}

	/* =================================================================
	 * Render
	 * ================================================================= */

	private static function chip_group( string $name, string $legend, string $optional, array $options, string $uid ): void {
		if ( ! $options ) {
			return;
		}
		?>
		<fieldset class="sz-pf__group">
			<legend class="sz-pf__lbl"><?php echo esc_html( $legend ); ?></legend>
			<div class="sz-pf__chips is-small">
				<?php foreach ( $options as $o ) : ?>
					<label class="sz-pf__chip"><input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $o ); ?>"><span><?php echo esc_html( $o ); ?></span></label>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<?php
	}

	protected function render(): void {
		$s   = $this->get_settings_for_display();
		$uid = 'szf-' . $this->get_id();
		$l   = [];
		foreach ( self::label_defaults() as $k => $def ) {
			$v       = trim( (string) ( $s[ 'label_' . $k ] ?? '' ) );
			$l[ $k ] = '' !== $v ? $v : $def[0];
		}
		$services = array_values( array_filter( (array) ( $s['services'] ?? [] ), static fn( $x ) => '' !== trim( (string) ( $x['label'] ?? '' ) ) ) );
		$max      = max( 200, min( Leads::MAX_MESSAGE, (int) ( $s['max'] ?? 1200 ) ) );
		$examples = self::lines( (string) ( $s['examples'] ?? '' ) );
		$days_off = array_map( 'intval', (array) ( $s['days_off'] ?? [ '5' ] ) );
		$opt      = '(' . $l['optional'] . ')';

		$this->add_render_attribute(
			'root',
			[
				'class'       => 'sz-pf sz-w',
				'data-sz-pf'  => '',
				'data-api'    => esc_url_raw( rest_url( Leads::ROUTE . '/lead' ) ),
				'data-id'     => $this->get_id(),
				'data-config' => wp_json_encode(
					[
						'examples' => $examples,
						'max'      => $max,
						'days'     => max( 1, (int) ( $s['reply_days'] ?? 2 ) ),
						'off'      => $days_off,
						'tz'       => trim( (string) ( $s['tz'] ?? 'Asia/Tehran' ) ) ?: 'Asia/Tehran',
						'email'    => sanitize_email( (string) ( $s['fallback_email'] ?? '' ) ),
						'l'        => array_intersect_key( $l, array_flip( [ 'send', 'sending', 'e_name', 'e_email', 'e_phone', 'e_message', 'e_link', 'e_rate', 'e_fail', 'ok_text', 'selected', 'link_set' ] ) ),
					]
				),
			]
		);
		?>
		<div <?php $this->print_render_attribute_string( 'root' ); ?>>
			<form class="sz-pf__form" novalidate>
				<?php if ( $services ) : ?>
					<fieldset class="sz-pf__group">
						<?php // the count sits at the end of the question's line: it takes no room of its own, so nothing moves when it shows ?>
						<legend class="sz-pf__lbl is-needs"><?php echo esc_html( (string) ( $s['needs_label'] ?? '' ) ); ?> <?php if ( '' !== trim( (string) ( $s['needs_hint'] ?? '' ) ) ) : ?><span><?php echo esc_html( $s['needs_hint'] ); ?></span><?php endif; ?><b class="sz-pf__picked" aria-live="polite"></b></legend>
						<div class="sz-pf__tray" style="--n:<?php echo (int) ( count( $services ) <= 4 ? count( $services ) : min( 4, (int) ceil( count( $services ) / 2 ) ) ); ?>">
							<?php foreach ( $services as $x ) : ?>
								<?php
								$val  = trim( (string) ( $x['value'] ?? '' ) ) ?: trim( (string) $x['label'] );
								$icon = ! empty( $x['icon']['value'] ) ? Icon_Library::html( $x['icon'] ) : Icon_Library::svg( self::guess_icon( (string) $x['label'] ) );
								?>
								<label class="sz-pf__tile"><input type="checkbox" name="services[]" value="<?php echo esc_attr( $val ); ?>"><span class="sz-pf__card">
									<span class="sz-pf__ico" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- svg from the icon library ?></span>
									<span class="sz-pf__name"><?php echo esc_html( trim( (string) $x['label'] ) ); ?></span>
									<svg class="sz-pf__badge" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><circle cx="10" cy="10" r="10"/><path d="M6 10.4l2.6 2.6L14 7.6"/></svg>
								</span></label>
							<?php endforeach; ?>
						</div>
					</fieldset>
				<?php endif; ?>

				<div class="sz-pf__set">
					<label class="sz-pf__f"><input name="name" placeholder=" " autocomplete="name" required maxlength="80" aria-describedby="<?php echo esc_attr( $uid ); ?>-e1"><span><?php echo esc_html( $l['name'] ); ?></span><?php echo self::OK; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static svg ?></label>
					<label class="sz-pf__f"><input name="email" type="email" placeholder=" " autocomplete="email" inputmode="email" required maxlength="120" aria-describedby="<?php echo esc_attr( $uid ); ?>-e1"><span><?php echo esc_html( $l['email'] ); ?></span><?php echo self::OK; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static svg ?></label>
					<?php if ( 'yes' === ( $s['phone'] ?? 'yes' ) ) : ?>
						<label class="sz-pf__f"><input name="phone" type="tel" placeholder=" " autocomplete="tel" inputmode="tel" maxlength="32" aria-describedby="<?php echo esc_attr( $uid ); ?>-e1"><span><?php echo esc_html( $l['phone'] ); ?> <em><?php echo esc_html( $opt ); ?></em></span><?php echo self::OK; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static svg ?></label>
					<?php endif; ?>
				</div>
				<p class="sz-pf__err" id="<?php echo esc_attr( $uid ); ?>-e1" aria-live="polite"></p>

				<div class="sz-pf__set">
					<label class="sz-pf__f is-area">
						<textarea name="message" placeholder=" " maxlength="<?php echo (int) $max; ?>" required aria-describedby="<?php echo esc_attr( $uid ); ?>-e2"></textarea><span><?php echo esc_html( $l['message'] ); ?></span>
						<?php if ( $examples ) : ?><i class="sz-pf__ex" aria-hidden="true"><?php echo esc_html( $examples[0] ); ?></i><?php endif; ?>
						<small class="sz-pf__count" aria-hidden="true">0 / <?php echo (int) $max; ?></small>
					</label>
				</div>
				<p class="sz-pf__err" id="<?php echo esc_attr( $uid ); ?>-e2" aria-live="polite"></p>

				<?php
				$timeline = self::lines( (string) ( $s['timeline'] ?? '' ) );
				$budget   = self::lines( (string) ( $s['budget'] ?? '' ) );
				$has_link = 'yes' === ( $s['link'] ?? 'yes' );
				if ( $timeline || $budget || $has_link ) :
					$parts = array_values( array_filter( [ $timeline ? __( 'timing', 'samanzavarian-widgets' ) : '', $budget ? __( 'budget', 'samanzavarian-widgets' ) : '', $has_link ? __( 'a reference link', 'samanzavarian-widgets' ) : '' ] ) );
					$last  = array_pop( $parts );
					$sub   = '' !== $l['more_sub'] ? $l['more_sub'] : ucfirst( ( $parts ? implode( ', ', $parts ) . ' ' . __( 'and', 'samanzavarian-widgets' ) . ' ' : '' ) . $last ) . '. ' . ucfirst( $l['optional'] ) . '.';
					?>
					<div class="sz-pf__more">
						<button type="button" class="sz-pf__mbtn" aria-expanded="false" aria-controls="<?php echo esc_attr( $uid ); ?>-more">
							<span class="sz-pf__mico" aria-hidden="true"><?php echo Icon_Library::svg( 'sliders' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static svg ?><i class="sz-pf__mdot"></i></span>
							<span class="sz-pf__mtxt"><b><?php echo esc_html( $l['more'] ); ?></b><small class="sz-pf__msub" data-sub="<?php echo esc_attr( $sub ); ?>"><?php echo esc_html( $sub ); ?></small></span>
							<svg class="sz-pf__mchev" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg>
						</button>
						<div class="sz-pf__mpanel" id="<?php echo esc_attr( $uid ); ?>-more" inert>
							<div class="sz-pf__minner">
								<?php self::chip_group( 'timeline', $l['timeline'], $opt, $timeline, $uid ); ?>
								<?php self::chip_group( 'budget', $l['budget'], $opt, $budget, $uid ); ?>
								<?php if ( $has_link ) : ?>
									<div class="sz-pf__set is-white">
										<label class="sz-pf__f"><input name="link" type="url" placeholder=" " inputmode="url" maxlength="300" aria-describedby="<?php echo esc_attr( $uid ); ?>-e3"><span><?php echo esc_html( $l['link'] ); ?></span><?php echo self::OK; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static svg ?></label>
									</div>
									<p class="sz-pf__err" id="<?php echo esc_attr( $uid ); ?>-e3" aria-live="polite"></p>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<div class="sz-pf__trap" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>

				<div class="sz-pf__toast" role="alert" hidden></div>

				<div class="sz-pf__send">
					<?php // a restored draft is said where the privacy note is, so nothing moves when it shows ?>
					<small class="sz-pf__note"><?php echo Icon_Library::svg( 'lock', 'sz-pf__lock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static svg ?><?php echo esc_html( $l['privacy'] ); ?></small>
					<small class="sz-pf__draft" hidden><span><?php echo esc_html( $l['draft'] ); ?></span> <button type="button" class="sz-pf__clear"><?php echo esc_html( $l['clear'] ); ?></button></small>
					<button type="submit" class="sz-pf__btn"><span class="sz-pf__go sz-app sz-app--orange" aria-hidden="true"><?php echo Icon_Library::svg( 'send', 'sz-pf__send-i' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static svg ?></span><span class="sz-pf__btxt"><?php echo esc_html( $l['send'] ); ?></span></button>
				</div>
			</form>

			<div class="sz-pf__done" hidden tabindex="-1">
				<svg class="sz-pf__tick" viewBox="0 0 52 52" aria-hidden="true" focusable="false"><circle cx="26" cy="26" r="24"/><path d="M15 27l7.5 7.5L37.5 19"/></svg>
				<h3 class="sz-pf__ok"><?php echo esc_html( $l['ok_title'] ); ?></h3>
				<p class="sz-pf__oktext"></p>
				<ul class="sz-pf__sum"></ul>
				<button type="button" class="sz-pf__again"><?php echo esc_html( $l['again'] ); ?></button>
			</div>
		</div>
		<?php
	}
}
