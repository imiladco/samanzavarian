<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Plugin as Elementor;
use Elementor\Widget_Base;
use Samanzavarian_Widgets\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Back to Top — the footer's scalloped sticker: "BACK TO TOP •" turning
 * round a dark disc with an arrow.
 *
 * - The text always closes its circle exactly, whatever it says.
 * - It is slapped on (a turn and a spring) the first time it comes into view.
 * - Under the pointer it tips, as in the design, and the ring speeds up and
 *   eases back down when the pointer leaves.
 * - On a click the arrow takes off, comes back from below, and the page
 *   glides to the top.
 * - The ring is its own layer, so its turning costs no repaint; it stops
 *   while off screen.
 */
class Back_To_Top extends Widget_Base {

	public function get_name(): string {
		return 'sz-totop';
	}

	public function get_title(): string {
		return esc_html__( 'Back to Top', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-arrow-up';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'top', 'back to top', 'scroll', 'footer', 'sticker', 'badge' ];
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

	/** A scalloped circle in a 120 box: $n bumps between radii $r and $big. */
	public static function scallop( int $n = 16, float $big = 62, float $r = 52 ): string {
		$p = static fn( float $rad, float $a ) => round( 60 + $rad * cos( $a ), 1 ) . ' ' . round( 60 + $rad * sin( $a ), 1 );
		$d = 'M' . $p( $r, -M_PI / 2 );
		for ( $i = 1; $i <= $n; $i++ ) {
			$a  = -M_PI / 2 + 2 * M_PI * $i / $n;
			$d .= 'Q' . $p( $big, $a - M_PI / $n ) . ' ' . $p( $r, $a );
		}
		return $d . 'Z';
	}

	protected function register_controls(): void {
		$this->start_controls_section( 'section_totop', [ 'label' => esc_html__( 'Sticker', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'text', [ 'label' => esc_html__( 'Ring text', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'BACK TO TOP', 'description' => esc_html__( 'Repeated round the circle; it always closes exactly.', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'times', [ 'label' => esc_html__( 'Times round the ring', 'samanzavarian-widgets' ), 'type' => Controls_Manager::NUMBER, 'default' => 2, 'min' => 1, 'max' => 4 ] );
		$this->add_control( 'sep', [ 'label' => esc_html__( 'Between repeats', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => '•', 'ai' => [ 'active' => false ] ] );
		$this->add_control( 'link', [ 'label' => esc_html__( 'Link', 'samanzavarian-widgets' ), 'type' => Controls_Manager::URL, 'default' => [ 'url' => '#top' ], 'dynamic' => [ 'active' => true ], 'description' => esc_html__( '#top glides to the top of the page.', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'label', [ 'label' => esc_html__( 'Name for screen readers', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Back to top', 'ai' => [ 'active' => false ] ] );
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
				'default'   => 'flex-end',
				'selectors' => [ '{{WRAPPER}} .sz-totop-wrap' => 'justify-content: {{VALUE}};' ],
			]
		);
		$this->end_controls_section();

		$this->start_controls_section( 'st_sticker', [ 'label' => esc_html__( 'Sticker', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		$this->add_responsive_control( 'size', [ 'label' => esc_html__( 'Size', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'range' => [ 'px' => [ 'min' => 48, 'max' => 240 ] ], 'selectors' => [ '{{WRAPPER}} .sz-totop' => 'width: {{SIZE}}px; height: {{SIZE}}px;' ] ] );
		$this->add_control( 'c_sticker', [ 'label' => esc_html__( 'Sticker', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .sz-totop' => '--szt-sticker: {{VALUE}};' ] ] );
		$this->add_control( 'c_text', [ 'label' => esc_html__( 'Ring text', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .sz-totop' => '--szt-text: {{VALUE}};' ] ] );
		$this->add_control( 'c_disc', [ 'label' => esc_html__( 'Disc', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .sz-totop' => '--szt-disc: {{VALUE}};' ] ] );
		$this->add_control( 'c_arrow', [ 'label' => esc_html__( 'Arrow', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .sz-totop' => '--szt-arrow: {{VALUE}};' ] ] );
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), [ 'name' => 'shadow', 'selector' => '{{WRAPPER}} .sz-totop' ] );
		$this->add_control( 'speed', [ 'label' => esc_html__( 'Seconds per turn', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'range' => [ 'px' => [ 'min' => 4, 'max' => 60 ] ], 'selectors' => [ '{{WRAPPER}} .sz-totop' => '--szt-turn: {{SIZE}}s;' ] ] );
		$this->end_controls_section();
		Style_Controls::motion(
			$this,
			[
				'spin'  => [ 'label' => esc_html__( 'The ring turns', 'samanzavarian-widgets' ) ],
				'slap'  => [ 'label' => esc_html__( 'Slapped on when it comes into view', 'samanzavarian-widgets' ) ],
			]
		);
	}

	protected function render(): void {
		$s     = $this->get_settings_for_display();
		$words = trim( (string) ( $s['text'] ?? '' ) );
		$sep   = trim( (string) ( $s['sep'] ?? '•' ) );
		$ring  = '';
		for ( $k = 0, $n = max( 1, min( 4, (int) ( $s['times'] ?? 2 ) ) ); $k < $n && '' !== $words; $k++ ) {
			$ring .= $words . ( '' !== $sep ? ' ' . $sep . "\u{00A0}" : "\u{00A0}" ); // SVG drops a plain trailing space
		}
		$id = 'sz-ring-' . $this->get_id();
		$this->add_render_attribute(
			'a',
			[
				'class'         => [ 'sz-totop', 'sz-w', 'yes' === ( $s['spin'] ?? 'yes' ) ? 'has-spin' : '', 'yes' === ( $s['slap'] ?? 'yes' ) ? 'has-slap' : '' ],
				'data-sz-totop' => '',
				'aria-label'    => (string) ( $s['label'] ?? 'Back to top' ),
			]
		);
		if ( ! empty( $s['link']['url'] ) ) {
			$this->add_link_attributes( 'a', $s['link'] );
		}
		// three layers: the sticker and the disc stay; only the ring between them turns
		echo '<div class="sz-totop-wrap"><a ' . $this->get_render_attribute_string( 'a' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor escapes
		echo '<svg class="sz-totop__sticker" viewBox="0 0 120 120" aria-hidden="true" focusable="false"><path d="' . esc_attr( self::scallop() ) . '"/></svg>';
		if ( '' !== $ring ) {
			// 2π × 37: the text is spread over exactly the whole circle
			echo '<svg class="sz-totop__ring" viewBox="0 0 120 120" aria-hidden="true" focusable="false"><path id="' . esc_attr( $id ) . '" d="M60 23a37 37 0 1 1-.1 0" fill="none"/><text><textPath href="#' . esc_attr( $id ) . '" textLength="232.4" lengthAdjust="spacing">' . esc_html( $ring ) . '</textPath></text></svg>';
		}
		echo '<svg class="sz-totop__core" viewBox="0 0 120 120" aria-hidden="true" focusable="false"><circle cx="60" cy="60" r="21"/><g class="sz-totop__arrow"><path d="M60 69V51M53 58l7-7 7 7"/></g></svg>';
		echo '</a></div>';
	}
}
