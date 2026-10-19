<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Plugin as Elementor;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Samanzavarian_Widgets\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Quote & Reply — a message from Saman (avatar, bubble, heart, caption) and
 * a reply box under it (quick replies, a message field, send).
 *
 * The first time it scrolls into view it arrives as a conversation: typing
 * dots, the bubble opening from its corner, the heart, then the quick
 * replies. Space is reserved for all of it (transforms only), so nothing
 * moves; a widget already on screen, or reduced motion, shows the end state.
 *
 * Sending hands the message on: by default into the page's contact form
 * (any form with a message box, an Elementor form included), ticking the
 * option a quick reply names and focusing the first empty field; or to
 * WhatsApp or email. The sent message stays as an outgoing bubble.
 */
class Chat_Reply extends Widget_Base {

	/** The design's quick replies: emoji, label, message, form choice. */
	private const QUICK = [
		[ '🚀', 'AI visuals', 'I need AI visuals for a product launch.', 'AI visuals' ],
		[ '🎬', 'Edit my footage', 'I have footage that needs editing and finishing.', 'Post-production' ],
		[ '🎨', 'Direction', 'I need help shaping the creative direction for a campaign.', 'Creative direction' ],
	];

	public function get_name(): string {
		return 'sz-chat';
	}

	public function get_title(): string {
		return esc_html__( 'Quote & Reply', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-testimonial';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'quote', 'message', 'bubble', 'chat', 'reply', 'imessage', 'contact', 'testimonial' ];
	}

	public function get_style_depends(): array {
		return [ 'sz-chat' ];
	}

	public function get_script_depends(): array {
		return [ 'sz-chat' ];
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
		$this->content_message();
		$this->content_reply();
		$this->content_send();
		$this->content_labels();
		$this->style_controls();
	}

	private function content_message(): void {
		$this->start_controls_section( 'section_message', [ 'label' => esc_html__( 'Message', 'samanzavarian-widgets' ) ] );

		$this->add_control( 'quote', [ 'label' => esc_html__( 'Message', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 3, 'default' => esc_html__( 'The tool matters less than the idea. AI is how I get there faster, not what defines the work.', 'samanzavarian-widgets' ), 'dynamic' => [ 'active' => true ] ] );
		$this->add_control( 'caption', [ 'label' => esc_html__( 'Caption', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => esc_html__( 'Saman, on working with AI', 'samanzavarian-widgets' ), 'label_block' => true, 'dynamic' => [ 'active' => true ] ] );

		$this->add_control( 'avatar_heading', [ 'label' => esc_html__( 'Avatar', 'samanzavarian-widgets' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ] );
		$this->add_control( 'avatar', [ 'label' => esc_html__( 'Photo', 'samanzavarian-widgets' ), 'type' => Controls_Manager::MEDIA, 'default' => [ 'url' => '' ], 'description' => esc_html__( 'Empty: the monogram below.', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'mono_a', [ 'label' => esc_html__( 'Monogram', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'S', 'condition' => [ 'avatar[url]' => '' ] ] );
		$this->add_control( 'mono_b', [ 'label' => esc_html__( 'Monogram, second letters', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Z', 'condition' => [ 'avatar[url]' => '' ] ] );

		$this->add_control(
			'tapback',
			[
				'label'     => esc_html__( 'Reaction on the bubble', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'heart',
				'separator' => 'before',
				'options'   => [
					'heart' => esc_html__( 'Heart, drawn (same everywhere)', 'samanzavarian-widgets' ),
					'emoji' => esc_html__( 'An emoji', 'samanzavarian-widgets' ),
					''      => esc_html__( 'None', 'samanzavarian-widgets' ),
				],
			]
		);
		$this->add_control( 'tapback_emoji', [ 'label' => esc_html__( 'Emoji', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => '❤️', 'condition' => [ 'tapback' => 'emoji' ] ] );

		$this->add_control(
			'intro',
			[
				'label'        => esc_html__( 'Arrive as a conversation', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'separator'    => 'before',
				'description'  => esc_html__( 'The first time it scrolls into view: typing dots, the bubble, the reaction, then the quick replies. Already on screen when the page opens, or with reduced motion: shown as it ends.', 'samanzavarian-widgets' ),
			]
		);

		$this->end_controls_section();
	}

	private function content_reply(): void {
		$this->start_controls_section( 'section_reply', [ 'label' => esc_html__( 'Reply box', 'samanzavarian-widgets' ) ] );

		$this->add_control( 'show_reply', [ 'label' => esc_html__( 'Show the reply box', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ] );

		$r = new Repeater();
		$r->add_control( 'emoji', [ 'label' => esc_html__( 'Emoji', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => '✨' ] );
		$r->add_control( 'label', [ 'label' => esc_html__( 'Label', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => esc_html__( 'Quick reply', 'samanzavarian-widgets' ) ] );
		$r->add_control( 'text', [ 'label' => esc_html__( 'Message it writes', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2 ] );
		$r->add_control( 'choice', [ 'label' => esc_html__( 'Option to tick in the form', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'description' => esc_html__( 'Optional: the value (or label) of a choice in the contact form, e.g. AI visuals. It is ticked when the message is sent.', 'samanzavarian-widgets' ) ] );

		$this->add_control(
			'quick',
			[
				'label'       => esc_html__( 'Quick replies', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => array_map( static fn( $q ) => [ 'emoji' => $q[0], 'label' => $q[1], 'text' => $q[2], 'choice' => $q[3] ], self::QUICK ),
				'title_field' => '{{{ emoji }}} {{{ label }}}',
				'button_text' => esc_html__( 'Add quick reply', 'samanzavarian-widgets' ),
				'condition'   => [ 'show_reply' => 'yes' ],
			]
		);

		$this->add_control( 'placeholder', [ 'label' => esc_html__( 'Field placeholder', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => esc_html__( 'Message Saman…', 'samanzavarian-widgets' ), 'condition' => [ 'show_reply' => 'yes' ] ] );
		$this->add_control( 'max', [ 'label' => esc_html__( 'Longest message', 'samanzavarian-widgets' ), 'type' => Controls_Manager::NUMBER, 'min' => 20, 'max' => 2000, 'default' => 300, 'description' => esc_html__( 'Characters. A "12 left" count appears near the end.', 'samanzavarian-widgets' ), 'condition' => [ 'show_reply' => 'yes' ] ] );

		$this->end_controls_section();
	}

	private function content_send(): void {
		$this->start_controls_section( 'section_send', [ 'label' => esc_html__( 'When sent', 'samanzavarian-widgets' ), 'condition' => [ 'show_reply' => 'yes' ] ] );

		$this->add_control(
			'action',
			[
				'label'   => esc_html__( 'The message goes to', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'form',
				'options' => [
					'form'     => esc_html__( 'The contact form on this page', 'samanzavarian-widgets' ),
					'whatsapp' => esc_html__( 'WhatsApp', 'samanzavarian-widgets' ),
					'email'    => esc_html__( 'Email', 'samanzavarian-widgets' ),
				],
				'description' => esc_html__( 'The form: the message goes into its message box, the quick reply\'s option is ticked, the page scrolls there and the first empty field is focused. Found by itself (Elementor forms too).', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control( 'field', [ 'label' => esc_html__( 'Message box (advanced)', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'placeholder' => '#form-field-message', 'description' => esc_html__( 'Only if the wrong box is found: a CSS selector for the form\'s message box.', 'samanzavarian-widgets' ), 'condition' => [ 'action' => 'form' ], 'ai' => [ 'active' => false ] ] );
		$this->add_control( 'phone', [ 'label' => esc_html__( 'WhatsApp number', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'placeholder' => '989121234567', 'description' => esc_html__( 'International, digits only. With "The contact form", used when the page has no form.', 'samanzavarian-widgets' ), 'ai' => [ 'active' => false ] ] );
		$this->add_control( 'email', [ 'label' => esc_html__( 'Email address', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'placeholder' => 'saman.zavarian@gmail.com', 'condition' => [ 'action' => 'email' ], 'ai' => [ 'active' => false ] ] );
		$this->add_control( 'subject', [ 'label' => esc_html__( 'Email subject', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => esc_html__( 'A project', 'samanzavarian-widgets' ), 'condition' => [ 'action' => 'email' ] ] );
		$this->add_control( 'analytics', [ 'label' => esc_html__( 'Report to analytics', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'separator' => 'before', 'description' => esc_html__( 'sz_reply_quick (a quick reply was picked) and sz_reply_send (where the message went, its length) to GTM or GA4. Never the message itself.', 'samanzavarian-widgets' ) ] );

		$this->end_controls_section();
	}

	private function content_labels(): void {
		$this->start_controls_section( 'section_labels', [ 'label' => esc_html__( 'Labels', 'samanzavarian-widgets' ), 'condition' => [ 'show_reply' => 'yes' ] ] );
		foreach ( self::label_defaults() as $key => $def ) {
			$this->add_control( 'label_' . $key, [ 'label' => $def[1], 'type' => Controls_Manager::TEXT, 'default' => $def[0], 'label_block' => true, 'ai' => [ 'active' => false ] ] );
		}
		$this->end_controls_section();
	}

	/** @return array<string,array{0:string,1:string}> */
	private static function label_defaults(): array {
		return [
			'form'      => [ __( 'Send Saman a message', 'samanzavarian-widgets' ), __( 'Reply box name (screen readers)', 'samanzavarian-widgets' ) ],
			'field'     => [ __( 'Message to Saman', 'samanzavarian-widgets' ), __( 'Field name (screen readers)', 'samanzavarian-widgets' ) ],
			'quick'     => [ __( 'Quick replies', 'samanzavarian-widgets' ), __( 'Quick replies name (screen readers)', 'samanzavarian-widgets' ) ],
			'send'      => [ __( 'Send', 'samanzavarian-widgets' ), __( 'Send button (screen readers)', 'samanzavarian-widgets' ) ],
			'delivered' => [ __( 'Delivered · continue below', 'samanzavarian-widgets' ), __( 'Under the sent message (form)', 'samanzavarian-widgets' ) ],
			'opened'    => [ __( 'Opening WhatsApp…', 'samanzavarian-widgets' ), __( 'Under the sent message (WhatsApp)', 'samanzavarian-widgets' ) ],
			'mailed'    => [ __( 'Opening your email…', 'samanzavarian-widgets' ), __( 'Under the sent message (email)', 'samanzavarian-widgets' ) ],
			'left'      => [ __( '{n} left', 'samanzavarian-widgets' ), __( 'Characters left', 'samanzavarian-widgets' ) ],
			'moved'     => [ __( 'Your message is in the form below.', 'samanzavarian-widgets' ), __( 'Read out after sending (screen readers)', 'samanzavarian-widgets' ) ],
		];
	}

	private function var_color( string $id, string $label, string $var ): void {
		$this->add_control( $id, [ 'label' => $label, 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .sz-chat' => $var . ': {{VALUE}};' ] ] );
	}

	private function style_controls(): void {
		$this->start_controls_section( 'style_bubble', [ 'label' => esc_html__( 'Message', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		$this->add_responsive_control( 'max_width', [ 'label' => esc_html__( 'Max width', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'placeholder' => Control_Helpers::ph( 572 ), 'size_units' => [ 'px', '%', 'custom' ], 'range' => [ 'px' => [ 'min' => 280, 'max' => 900 ] ], 'selectors' => [ '{{WRAPPER}} .sz-chat' => '--szc-max: {{SIZE}}{{UNIT}};' ] ] );
		$this->var_color( 'bubble_bg', esc_html__( 'Bubble', 'samanzavarian-widgets' ), '--szc-bubble' );
		$this->var_color( 'bubble_text', esc_html__( 'Bubble text', 'samanzavarian-widgets' ), '--szc-bubble-text' );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'quote_typo', 'selector' => '{{WRAPPER}} .sz-chat__bubble p' ] );
		$this->var_color( 'caption_color', esc_html__( 'Caption', 'samanzavarian-widgets' ), '--szc-caption' );
		$this->var_color( 'heart_color', esc_html__( 'Heart', 'samanzavarian-widgets' ), '--szc-heart' );
		$this->end_controls_section();

		$this->start_controls_section( 'style_reply', [ 'label' => esc_html__( 'Reply box', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => [ 'show_reply' => 'yes' ] ] );
		$this->var_color( 'chip_bg', esc_html__( 'Quick reply', 'samanzavarian-widgets' ), '--szc-chip' );
		$this->var_color( 'chip_text', esc_html__( 'Quick reply text', 'samanzavarian-widgets' ), '--szc-chip-text' );
		$this->var_color( 'chip_on', esc_html__( 'Quick reply, hover and chosen', 'samanzavarian-widgets' ), '--szc-chip-on' );
		$this->var_color( 'field_bg', esc_html__( 'Field', 'samanzavarian-widgets' ), '--szc-field' );
		$this->var_color( 'send_bg', esc_html__( 'Send, ready', 'samanzavarian-widgets' ), '--szc-send' );
		$this->var_color( 'send_icon', esc_html__( 'Send arrow, ready', 'samanzavarian-widgets' ), '--szc-send-icon' );
		$this->var_color( 'out_bg', esc_html__( 'Sent message', 'samanzavarian-widgets' ), '--szc-out' );
		$this->var_color( 'out_text', esc_html__( 'Sent message text', 'samanzavarian-widgets' ), '--szc-out-text' );
		$this->end_controls_section();
	}

	/* =================================================================
	 * Render
	 * ================================================================= */

	/** @param array<string,mixed> $s @return array<int,array<string,string>> */
	public static function quick( array $s ): array {
		$out = [];
		foreach ( (array) ( $s['quick'] ?? [] ) as $q ) {
			$label = trim( (string) ( $q['label'] ?? '' ) );
			if ( '' === $label ) {
				continue;
			}
			$out[] = [
				'emoji'  => trim( (string) ( $q['emoji'] ?? '' ) ),
				'label'  => $label,
				'text'   => trim( (string) ( $q['text'] ?? '' ) ) ?: $label,
				'choice' => trim( (string) ( $q['choice'] ?? '' ) ),
			];
		}
		return $out;
	}

	protected function render(): void {
		$s     = $this->get_settings_for_display();
		$quote = trim( (string) ( $s['quote'] ?? '' ) );

		if ( '' === $quote ) {
			return;
		}

		$uid   = 'szc-' . $this->get_id();
		$reply = 'yes' === ( $s['show_reply'] ?? 'yes' );
		$l     = [];
		foreach ( self::label_defaults() as $k => $def ) {
			$v       = trim( (string) ( $s[ 'label_' . $k ] ?? '' ) );
			$l[ $k ] = '' !== $v ? $v : $def[0];
		}
		$action = in_array( $s['action'] ?? 'form', [ 'form', 'whatsapp', 'email' ], true ) ? $s['action'] : 'form';
		$max    = max( 20, min( 2000, (int) ( $s['max'] ?? 300 ) ) );

		$this->add_render_attribute(
			'root',
			[
				'class'       => 'sz-chat sz-w',
				'data-sz-chat' => '',
				'data-intro'  => 'yes' === ( $s['intro'] ?? 'yes' ) ? '1' : '0',
				'data-action' => $action,
				'data-field'  => trim( (string) ( $s['field'] ?? '' ) ),
				'data-phone'  => preg_replace( '/\D+/', '', (string) ( $s['phone'] ?? '' ) ),
				'data-email'  => sanitize_email( (string) ( $s['email'] ?? '' ) ),
				'data-subject' => (string) ( $s['subject'] ?? '' ),
				'data-track'  => 'yes' === ( $s['analytics'] ?? 'yes' ) ? '1' : '0',
				'data-labels' => wp_json_encode( [ 'delivered' => $l['delivered'], 'opened' => $l['opened'], 'mailed' => $l['mailed'], 'left' => $l['left'], 'moved' => $l['moved'] ] ),
			]
		);

		$avatar = is_array( $s['avatar'] ?? null ) ? $s['avatar'] : [];
		?>
		<div <?php $this->print_render_attribute_string( 'root' ); ?>>
			<figure class="sz-chat__msg">
				<span class="sz-chat__av" aria-hidden="true">
					<?php if ( ! empty( $avatar['id'] ) && wp_attachment_is_image( (int) $avatar['id'] ) ) : ?>
						<?php echo wp_get_attachment_image( (int) $avatar['id'], 'thumbnail', false, [ 'class' => 'sz-chat__av-img', 'alt' => '', 'loading' => 'lazy' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup ?>
					<?php elseif ( ! empty( $avatar['url'] ) ) : ?>
						<img class="sz-chat__av-img" src="<?php echo esc_url( $avatar['url'] ); ?>" alt="" loading="lazy">
					<?php else : ?>
						<?php echo esc_html( (string) ( $s['mono_a'] ?? '' ) ); ?><b><?php echo esc_html( (string) ( $s['mono_b'] ?? '' ) ); ?></b>
					<?php endif; ?>
				</span>
				<div class="sz-chat__bwrap">
					<span class="sz-chat__typing" aria-hidden="true"><i></i><i></i><i></i></span>
					<blockquote class="sz-chat__bubble">
						<p><?php echo esc_html( $quote ); ?></p>
						<?php self::render_tapback( $s ); ?>
					</blockquote>
				</div>
				<?php if ( '' !== trim( (string) ( $s['caption'] ?? '' ) ) ) : ?>
					<figcaption class="sz-chat__cap"><?php echo esc_html( (string) $s['caption'] ); ?></figcaption>
				<?php endif; ?>
			</figure>
			<?php if ( $reply ) : ?>
				<form class="sz-chat__reply" aria-label="<?php echo esc_attr( $l['form'] ); ?>" novalidate>
					<div class="sz-chat__sent" aria-hidden="true"></div>
					<?php $quick = self::quick( $s ); ?>
					<?php if ( $quick ) : ?>
						<div class="sz-chat__quick" role="group" aria-label="<?php echo esc_attr( $l['quick'] ); ?>">
							<?php foreach ( $quick as $q ) : ?>
								<button type="button" class="sz-chat__chip" aria-pressed="false" data-text="<?php echo esc_attr( $q['text'] ); ?>" data-choice="<?php echo esc_attr( $q['choice'] ); ?>"><?php if ( '' !== $q['emoji'] ) : ?><span aria-hidden="true"><?php echo esc_html( $q['emoji'] ); ?></span><?php endif; ?><?php echo esc_html( $q['label'] ); ?></button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<div class="sz-chat__composer">
						<label class="sz-chat__sr" for="<?php echo esc_attr( $uid ); ?>-t"><?php echo esc_html( $l['field'] ); ?></label>
						<input class="sz-chat__input" id="<?php echo esc_attr( $uid ); ?>-t" type="text" placeholder="<?php echo esc_attr( (string) ( $s['placeholder'] ?? '' ) ); ?>" autocomplete="off" enterkeyhint="send" maxlength="<?php echo (int) $max; ?>">
						<span class="sz-chat__left" aria-live="polite"></span>
						<button class="sz-chat__send" type="submit" aria-label="<?php echo esc_attr( $l['send'] ); ?>" disabled><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 19V5M6 11l6-6 6 6"/></svg></button>
					</div>
					<p class="sz-chat__sr sz-chat__live" aria-live="polite"></p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/** @param array<string,mixed> $s */
	private static function render_tapback( array $s ): void {
		$kind = (string) ( $s['tapback'] ?? 'heart' );

		if ( 'heart' === $kind ) {
			echo '<span class="sz-chat__tap is-heart" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M12 20.3s-7.6-4.6-9.3-9.2C1.5 7.8 3.6 4.4 7 4.4c2.1 0 3.6 1.2 5 3 1.4-1.8 2.9-3 5-3 3.4 0 5.5 3.4 4.3 6.7-1.7 4.6-9.3 9.2-9.3 9.2z"/></svg></span>';
		} elseif ( 'emoji' === $kind && '' !== trim( (string) ( $s['tapback_emoji'] ?? '' ) ) ) {
			echo '<span class="sz-chat__tap is-emoji" aria-hidden="true">' . esc_html( trim( (string) $s['tapback_emoji'] ) ) . '</span>';
		}
	}
}
