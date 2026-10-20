<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Plugin as Elementor;
use Elementor\Widget_Base;
use Samanzavarian_Widgets\Icon_Library;
use Samanzavarian_Widgets\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Mail Row — the footer's email: a big white pill that opens the mail app,
 * and a Copy button beside it for those who write from somewhere else.
 *
 * - Under the pointer the pill lifts and its envelope is sent off (out at
 *   the top right, back in from the bottom left).
 * - Copy turns into a green "Copied" with a tick and back. The button never
 *   changes width: both words share one place. Screen readers hear it too.
 * - If the browser will not copy, the mail app opens instead.
 */
class Mail_Row extends Widget_Base {

	public function get_name(): string {
		return 'sz-mailrow';
	}

	public function get_title(): string {
		return esc_html__( 'Mail Row', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-envelope';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'email', 'mail', 'copy', 'contact', 'footer' ];
	}

	public function get_style_depends(): array {
		return [ 'sz-footer' ];
	}

	public function get_script_depends(): array {
		return [ 'sz-footer' ];
	}

	protected function is_dynamic_content(): bool {
		return false;
	}

	public function has_widget_inner_wrapper(): bool {
		return ! Elementor::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	protected function register_controls(): void {
		$this->start_controls_section( 'section_mail', [ 'label' => esc_html__( 'Email', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'email', [ 'label' => esc_html__( 'Email address', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'saman.zavarian@gmail.com', 'ai' => [ 'active' => false ] ] );
		$this->add_control( 'shown', [ 'label' => esc_html__( 'Text on the pill', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'placeholder' => esc_html__( 'The address', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'subject', [ 'label' => esc_html__( 'Subject of the new email', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'placeholder' => esc_html__( 'None', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'icon', [ 'label' => esc_html__( 'Icon tile', 'samanzavarian-widgets' ), 'type' => Controls_Manager::ICONS, 'skin' => 'inline', 'label_block' => false, 'default' => Icon_Library::value( 'mail' ) ] );
		$this->add_control( 'copy', [ 'label' => esc_html__( 'Copy button', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'separator' => 'before' ] );
		$this->add_control( 'copy_text', [ 'label' => esc_html__( 'Button text', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Copy', 'condition' => [ 'copy' => 'yes' ] ] );
		$this->add_control( 'done_text', [ 'label' => esc_html__( 'After copying', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Copied', 'condition' => [ 'copy' => 'yes' ] ] );
		$this->add_control( 'copy_phone', [ 'label' => esc_html__( 'Copy button on phones too', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => '', 'return_value' => 'yes', 'description' => esc_html__( 'Off as in the design: on a phone the pill alone opens the mail app.', 'samanzavarian-widgets' ), 'condition' => [ 'copy' => 'yes' ] ] );
		$this->add_control(
			'look',
			[
				'label'        => esc_html__( 'Sits on', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::CHOOSE,
				'default'      => 'dark',
				'toggle'       => false,
				'separator'    => 'before',
				'prefix_class' => 'sz-on-',
				'options'      => [
					'dark'  => [ 'title' => esc_html__( 'A dark background', 'samanzavarian-widgets' ), 'icon' => 'eicon-circle' ],
					'light' => [ 'title' => esc_html__( 'A light background', 'samanzavarian-widgets' ), 'icon' => 'eicon-circle-o' ],
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
				'selectors' => [ '{{WRAPPER}} .sz-mailrow' => 'justify-content: {{VALUE}};' ],
			]
		);
		$this->add_responsive_control( 'gap', [ 'label' => esc_html__( 'Space between', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'range' => [ 'px' => [ 'min' => 0, 'max' => 40 ] ], 'selectors' => [ '{{WRAPPER}} .sz-mailrow' => 'gap: {{SIZE}}px;' ] ] );
		$this->end_controls_section();

		Style_Controls::box( $this, 'st_mail', esc_html__( 'Email pill', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-mailrow .sz-mailrow__mail', [ 'hover' => '{{WRAPPER}} .sz-mailrow .sz-mailrow__mail:hover', 'extra' => [ 'h' => [ 'label' => esc_html__( 'Height', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'responsive' => true, 'range' => [ 'px' => [ 'min' => 32, 'max' => 96 ] ], 'selectors' => [ '{{WRAPPER}} .sz-mailrow' => '--szm-h: {{SIZE}}px;' ] ] ] ] );
		Style_Controls::text( $this, 'st_addr', esc_html__( 'Email pill: text', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-mailrow .sz-mailrow__mail', [ 'no_margin' => true, 'hover' => '{{WRAPPER}} .sz-mailrow .sz-mailrow__mail:hover' ] );
		Style_Controls::tile( $this, 'st_tile', esc_html__( 'Email pill: icon tile', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-mailrow__app' );
		Style_Controls::box( $this, 'st_copy', esc_html__( 'Copy button', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-mailrow .sz-mailrow__copy', [ 'hover' => '{{WRAPPER}} .sz-mailrow .sz-mailrow__copy:hover', 'condition' => [ 'copy' => 'yes' ], 'extra' => [ 'done_bg' => [ 'label' => esc_html__( 'Background once copied', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'separator' => 'before', 'selectors' => [ '{{WRAPPER}} .sz-mailrow .sz-mailrow__copy.is-done' => 'background: {{VALUE}};' ] ], 'done_fg' => [ 'label' => esc_html__( 'Text once copied', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .sz-mailrow .sz-mailrow__copy.is-done' => 'color: {{VALUE}};' ] ] ] ] );
		Style_Controls::text( $this, 'st_copy_t', esc_html__( 'Copy button: text', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-mailrow .sz-mailrow__copy', [ 'no_margin' => true, 'hover' => '{{WRAPPER}} .sz-mailrow .sz-mailrow__copy:hover', 'condition' => [ 'copy' => 'yes' ] ] );
	}

	protected function render(): void {
		$s     = $this->get_settings_for_display();
		$email = sanitize_email( (string) ( $s['email'] ?? '' ) );
		if ( '' === $email ) {
			return;
		}
		$shown = trim( (string) ( $s['shown'] ?? '' ) ) ?: $email;
		$subj  = trim( (string) ( $s['subject'] ?? '' ) );
		$href  = 'mailto:' . $email . ( '' !== $subj ? '?subject=' . rawurlencode( $subj ) : '' );
		$icon  = ! empty( $s['icon']['value'] ) ? Icon_Library::html( $s['icon'] ) : '';
		$copy  = 'yes' === ( $s['copy'] ?? 'yes' );
		$tick  = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 12.5 10 17l9-10"/></svg>';
		echo '<div class="sz-mailrow sz-w' . ( $copy && 'yes' === ( $s['copy_phone'] ?? '' ) ? ' has-copy-phone' : '' ) . '" data-sz-mailrow>';
		echo '<a class="sz-mailrow__mail" href="' . esc_url( $href, [ 'mailto' ] ) . '">';
		if ( '' !== $icon ) {
			echo '<span class="sz-mailrow__app sz-app sz-app--orange" aria-hidden="true"><span class="sz-mailrow__env">' . $icon . '</span></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon markup
		}
		echo '<span class="sz-mailrow__addr">' . esc_html( $shown ) . '</span></a>';
		if ( $copy ) {
			$done = (string) ( $s['done_text'] ?? 'Copied' );
			printf(
				'<button class="sz-mailrow__copy" type="button" data-copy="%1$s" data-done="%2$s" aria-label="%3$s"><span class="sz-mailrow__l is-a">%4$s</span><span class="sz-mailrow__l is-b" aria-hidden="true">%5$s%6$s</span></button><span class="sz-mailrow__sr" role="status"> </span>',
				esc_attr( $email ),
				esc_attr( $done ),
				/* translators: %s: email address */
				esc_attr( sprintf( __( 'Copy %s', 'samanzavarian-widgets' ), $email ) ),
				esc_html( (string) ( $s['copy_text'] ?? 'Copy' ) ),
				$tick, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static
				esc_html( $done )
			);
		}
		echo '</div>';
	}
}
