<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Plugin as Elementor;
use Elementor\Widget_Base;
use Samanzavarian_Widgets\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Wordmark — the footer's name, as wide as its box, cut by the bottom edge.
 *
 * - Fitting: the name's width is measured once (per font) and kept as a
 *   ratio; the size is then a container-query length, so the browser keeps
 *   it fitted at every width with no script on resize and no jump. Before
 *   the measure, a ratio estimated on the server already comes close.
 * - The letters rise from below the cut, one after another, the first time
 *   the name comes into view.
 * - Under the pointer the letters near it lift a little, the nearest most,
 *   like a dock (mouse only, a frame at a time, nothing at rest).
 */
class Wordmark extends Widget_Base {

	public function get_name(): string {
		return 'sz-word';
	}

	public function get_title(): string {
		return esc_html__( 'Wordmark', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-t-letter';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'name', 'wordmark', 'logo', 'footer', 'big text', 'fit' ];
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
		$this->start_controls_section( 'section_word', [ 'label' => esc_html__( 'Wordmark', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'first', [ 'label' => esc_html__( 'First word', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'SAMAN' ] );
		$this->add_control( 'second', [ 'label' => esc_html__( 'Second word (accent colour)', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'ZAVARIAN' ] );
		$this->add_control( 'fit', [ 'label' => esc_html__( 'Fill the width', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'description' => esc_html__( 'The size follows the box at every screen width. Off: set the size under Style → Text.', 'samanzavarian-widgets' ) ] );
		$this->add_control( 'cut', [ 'label' => esc_html__( 'Cut by the bottom edge', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'size_units' => [ 'px' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 40 ] ], 'default' => [ 'unit' => 'px', 'size' => 10 ], 'description' => esc_html__( 'How much of the letters\' foot is hidden, in hundredths of the letter height.', 'samanzavarian-widgets' ), 'selectors' => [ '{{WRAPPER}} .sz-word' => '--szw-cut: calc({{SIZE}} * .01em);' ] ] );
		$this->add_control( 'fade', [ 'label' => esc_html__( 'Fade toward the bottom', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'prefix_class' => 'sz-word-fade-' ] );
		$this->add_control( 'label', [ 'label' => esc_html__( 'Read by screen readers', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => '', 'return_value' => 'yes', 'description' => esc_html__( 'Off as in the design: the name is said elsewhere on the page.', 'samanzavarian-widgets' ) ] );
		$this->end_controls_section();

		$this->start_controls_section( 'st_text', [ 'label' => esc_html__( 'Text', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'type', 'selector' => '{{WRAPPER}} .sz-word__line', 'exclude' => [ 'text_decoration' ] ] );
		$this->add_control( 'c1', [ 'label' => esc_html__( 'First word', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .sz-word' => 'color: {{VALUE}};' ] ] );
		$this->add_control( 'c2', [ 'label' => esc_html__( 'Second word', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .sz-word .sz-word__b' => 'color: {{VALUE}};' ] ] );
		$this->add_responsive_control( 'top', [ 'label' => esc_html__( 'Space above', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'range' => [ 'px' => [ 'min' => 0, 'max' => 120 ] ], 'selectors' => [ '{{WRAPPER}} .sz-word' => 'margin-top: {{SIZE}}px;' ] ] );
		$this->end_controls_section();
		Style_Controls::motion(
			$this,
			[
				'rise' => [ 'label' => esc_html__( 'Letters rise in when it comes into view', 'samanzavarian-widgets' ) ],
				'lift' => [ 'label' => esc_html__( 'Letters near the pointer lift', 'samanzavarian-widgets' ) ],
			]
		);
	}

	/** Letters as spans (each can rise and lift); spaces stay plain text so the name still reads. */
	private static function letters( string $s, int &$i ): string {
		$out = '';
		foreach ( preg_split( '//u', $s, -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
			$out .= ' ' === $ch ? ' ' : '<span class="sz-word__ch" style="--i:' . $i++ . '">' . esc_html( $ch ) . '</span>';
		}
		return $out;
	}

	protected function render(): void {
		$s      = $this->get_settings_for_display();
		$first  = trim( (string) ( $s['first'] ?? '' ) );
		$second = trim( (string) ( $s['second'] ?? '' ) );
		if ( '' === $first . $second ) {
			return;
		}
		$i    = 0;
		$fit  = 'yes' === ( $s['fit'] ?? 'yes' );
		$text = trim( $first . ' ' . $second );
		// Bebas Neue: about .4em a letter, .2em a space; the script replaces this with the measure
		$est = 100 / max( 1, mb_strlen( str_replace( ' ', '', $text ) ) * 40 + substr_count( $text, ' ' ) * 20 );
		$cls = [ 'sz-word', 'sz-w', $fit ? 'is-fit' : '', 'yes' === ( $s['rise'] ?? 'yes' ) ? 'has-rise' : '', 'yes' === ( $s['lift'] ?? 'yes' ) ? 'has-lift' : '' ];
		echo '<div class="' . esc_attr( implode( ' ', array_filter( $cls ) ) ) . '" data-sz-word style="--szw-k:' . esc_attr( (string) round( $est, 5 ) ) . '"' . ( 'yes' === ( $s['label'] ?? '' ) ? ' role="img" aria-label="' . esc_attr( $text ) . '"' : ' aria-hidden="true"' ) . '>';
		echo '<span class="sz-word__line">' . self::letters( $first, $i ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped per letter
		if ( '' !== $second ) {
			echo ( '' !== $first ? ' ' : '' ) . '<span class="sz-word__b">' . self::letters( $second, $i ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped per letter
		}
		echo '</span></div>';
	}
}
