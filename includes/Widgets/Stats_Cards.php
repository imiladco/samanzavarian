<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Plugin as Elementor;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Samanzavarian_Widgets\Icon_Library;
use Samanzavarian_Widgets\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Stats — a row of figure cards ("106+ Projects delivered").
 *
 * The final numbers are in the HTML. When the row comes into view after a
 * scroll, stats.js counts them up from zero, one card after another; a row
 * already in view on load, or reduced motion, keeps its numbers as they are,
 * so nobody watches 106 turn into 0. Each number's final width is held
 * while counting, so nothing moves.
 *
 * A "+" suffix is drawn, not typed: the font's plus is a text glyph, thin
 * and low beside 44px digits. The drawn one has the digits' stem weight and
 * sits on their cap height. "Years since" figures are counted from a year,
 * so they never go stale.
 */
class Stats_Cards extends Widget_Base {

	/** The design's cards: icon, tile, value, suffix, label, highlighted, since-year. */
	private const CARDS = [
		[ 'check', 'orange', 106, '+', 'Projects delivered', true, 0 ],
		[ 'factory', '', 30, '+', 'Commercial pieces', false, 0 ],
		[ 'clock', '', 6, '', 'Years in the field', false, 2020 ],
	];

	public function get_name(): string {
		return 'sz-stats';
	}

	public function get_title(): string {
		return esc_html__( 'Stats', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-counter';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'stats', 'numbers', 'counter', 'figures', 'facts', 'count up' ];
	}

	public function get_style_depends(): array {
		return [ 'sz-stats' ];
	}

	public function get_script_depends(): array {
		return [ 'sz-stats' ];
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
		$this->content_cards();
		$this->content_settings();
		$this->style_layout();
		$this->style_card();
		$this->style_text();
		$this->style_highlight();
	}

	private function content_cards(): void {
		$this->start_controls_section( 'section_cards', [ 'label' => esc_html__( 'Cards', 'samanzavarian-widgets' ) ] );

		$r = new Repeater();

		$r->add_control(
			'kind',
			[
				'label'   => esc_html__( 'Figure', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'number',
				'options' => [
					'number' => esc_html__( 'A number', 'samanzavarian-widgets' ),
					'years'  => esc_html__( 'Years since a year (counts itself)', 'samanzavarian-widgets' ),
				],
			]
		);

		$r->add_control(
			'value',
			[
				'label'     => esc_html__( 'Number', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 30,
				'step'      => 'any',
				'condition' => [ 'kind' => 'number' ],
			]
		);

		$r->add_control(
			'since',
			[
				'label'       => esc_html__( 'Since the year', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => 2020,
				'min'         => 1900,
				'max'         => 2100,
				'description' => esc_html__( 'The figure is this year minus that one, so it goes up by itself every January.', 'samanzavarian-widgets' ),
				'condition'   => [ 'kind' => 'years' ],
			]
		);

		$r->add_control(
			'decimals',
			[
				'label'     => esc_html__( 'Decimals', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '0',
				'options'   => [ '0' => '106', '1' => '4.9', '2' => '4.85' ],
				'condition' => [ 'kind' => 'number' ],
			]
		);

		$r->add_control(
			'prefix',
			[
				'label'       => esc_html__( 'Before the number', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => '$',
			]
		);

		$r->add_control(
			'suffix',
			[
				'label'       => esc_html__( 'After the number', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => '+',
				'description' => esc_html__( '"+" is drawn to match the digits; anything else (%, k, ×) is set as text.', 'samanzavarian-widgets' ),
			]
		);

		$r->add_control(
			'label',
			[
				'label'       => esc_html__( 'Label', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Commercial pieces', 'samanzavarian-widgets' ),
				'label_block' => true,
				'dynamic'     => [ 'active' => true ],
			]
		);

		$r->add_control(
			'detail',
			[
				'label'       => esc_html__( 'Detail line', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'description' => esc_html__( 'Optional, small, under the label. {since} is the year above: "since {since}".', 'samanzavarian-widgets' ),
				'dynamic'     => [ 'active' => true ],
			]
		);

		$r->add_control(
			'icon',
			[
				'label'   => esc_html__( 'Icon', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::ICONS,
				'default' => Icon_Library::value( 'factory' ),
			]
		);

		$r->add_control(
			'tile',
			[
				'label'   => esc_html__( 'Icon tile', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					''       => esc_html__( 'Navy', 'samanzavarian-widgets' ),
					'orange' => esc_html__( 'Orange', 'samanzavarian-widgets' ),
					'white'  => esc_html__( 'White', 'samanzavarian-widgets' ),
				],
			]
		);

		$r->add_control(
			'highlight',
			[
				'label'        => esc_html__( 'Highlighted (dark)', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '',
				'return_value' => 'yes',
			]
		);

		$r->add_control(
			'link',
			[
				'label'       => esc_html__( 'Link', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::URL,
				'description' => esc_html__( 'Optional: the card opens it, and shows a small ↗ on hover.', 'samanzavarian-widgets' ),
				'dynamic'     => [ 'active' => true ],
			]
		);

		$defaults = [];
		foreach ( self::CARDS as $c ) {
			$defaults[] = [
				'icon'      => Icon_Library::value( $c[0] ),
				'tile'      => $c[1],
				'kind'      => $c[6] ? 'years' : 'number',
				'value'     => $c[2],
				'since'     => $c[6] ? $c[6] : 2020,
				'suffix'    => $c[3],
				'label'     => $c[4],
				'highlight' => $c[5] ? 'yes' : '',
			];
		}

		$this->add_control(
			'cards',
			[
				'label'       => esc_html__( 'Cards', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => $defaults,
				'title_field' => '{{{ prefix }}}{{{ "years" === kind ? "↻" : value }}}{{{ suffix }}} · {{{ label }}}',
				'button_text' => esc_html__( 'Add card', 'samanzavarian-widgets' ),
			]
		);

		$this->end_controls_section();
	}

	private function content_settings(): void {
		$this->start_controls_section( 'section_settings', [ 'label' => esc_html__( 'Counting', 'samanzavarian-widgets' ) ] );

		$this->add_control(
			'count',
			[
				'label'        => esc_html__( 'Count up from zero', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'description'  => esc_html__( 'When the cards scroll into view, once. Cards already on screen when the page opens keep their numbers, and so does everything with reduced motion.', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control(
			'duration',
			[
				'label'     => esc_html__( 'Duration', 'samanzavarian-widgets' ),
				'type'      => Controls_Manager::SLIDER,
				'size_units' => [ 'ms' ],
				'range'     => [ 'ms' => [ 'min' => 400, 'max' => 4000, 'step' => 100 ] ],
				'default'   => [ 'size' => 1400, 'unit' => 'ms' ],
				'condition' => [ 'count' => 'yes' ],
			]
		);

		$this->add_control(
			'stagger',
			[
				'label'       => esc_html__( 'Between cards', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'ms' ],
				'range'       => [ 'ms' => [ 'min' => 0, 'max' => 600, 'step' => 20 ] ],
				'default'     => [ 'size' => 140, 'unit' => 'ms' ],
				'description' => esc_html__( 'Each card starts this much after the one before.', 'samanzavarian-widgets' ),
				'condition'   => [ 'count' => 'yes' ],
			]
		);

		$this->add_control(
			'separator',
			[
				'label'        => esc_html__( 'Thousands separator', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'description'  => esc_html__( '1,200 instead of 1200.', 'samanzavarian-widgets' ),
				'separator'    => 'before',
			]
		);

		$this->end_controls_section();
	}

	/* ---------------- style ---------------- */

	private function var_slider( string $id, string $label, string $var, $ph, array $range, array $units = [ 'px', 'custom' ], array $device = [ null, null ] ): void {
		$args = [
			'label'       => $label,
			'type'        => Controls_Manager::SLIDER,
			'placeholder' => $ph,
			'size_units'  => $units,
			'range'       => [ $units[0] => $range ],
			'selectors'   => [ '{{WRAPPER}} .sz-stats' => $var . ': {{SIZE}}{{UNIT}};' ],
		];
		foreach ( [ 'tablet_default', 'mobile_default' ] as $n => $key ) {
			if ( null !== $device[ $n ] ) {
				$args[ $key ] = [ 'size' => $device[ $n ], 'unit' => $units[0] ];
			}
		}
		$this->add_responsive_control( $id, $args );
	}

	private function var_color( string $id, string $label, string $var ): void {
		$this->add_control( $id, [ 'label' => $label, 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .sz-stats' => $var . ': {{VALUE}};' ] ] );
	}

	private function style_layout(): void {
		$this->start_controls_section( 'style_layout', [ 'label' => esc_html__( 'Layout', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$this->add_responsive_control(
			'columns',
			[
				'label'       => esc_html__( 'Columns', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => '',
				'options'     => [ '' => esc_html__( 'One per card (design)', 'samanzavarian-widgets' ), '1' => '1', '2' => '2', '3' => '3', '4' => '4', '6' => '6' ],
				'selectors'   => [ '{{WRAPPER}} .sz-stats' => '--szs-cols: {{VALUE}};' ],
			]
		);

		$this->var_slider( 'max_width', esc_html__( 'Max width', 'samanzavarian-widgets' ), '--szs-max', [ 'size' => 100, 'unit' => '%' ], [ 'min' => 200, 'max' => 1200 ], [ 'px', '%', 'custom' ] );

		$this->add_responsive_control(
			'align',
			[
				'label'                => esc_html__( 'Position', 'samanzavarian-widgets' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => [
					'left'   => [ 'title' => esc_html__( 'Left', 'samanzavarian-widgets' ), 'icon' => 'eicon-h-align-left' ],
					'center' => [ 'title' => esc_html__( 'Center', 'samanzavarian-widgets' ), 'icon' => 'eicon-h-align-center' ],
					'right'  => [ 'title' => esc_html__( 'Right', 'samanzavarian-widgets' ), 'icon' => 'eicon-h-align-right' ],
				],
				'selectors_dictionary' => [ 'left' => 'margin-left:0;margin-right:auto', 'center' => 'margin-left:auto;margin-right:auto', 'right' => 'margin-left:auto;margin-right:0' ],
				'selectors'            => [ '{{WRAPPER}} .sz-stats__grid' => '{{VALUE}};' ],
				'description'          => esc_html__( 'When narrower than the column. In the design: 470 px, on the right.', 'samanzavarian-widgets' ),
			]
		);

		$this->var_slider( 'gap', esc_html__( 'Space between cards', 'samanzavarian-widgets' ), '--szs-gap', Control_Helpers::ph( 10 ), [ 'min' => 0, 'max' => 40 ], [ 'px', 'custom' ], [ null, 8 ] );

		$this->end_controls_section();
	}

	private function style_card(): void {
		$this->start_controls_section( 'style_card', [ 'label' => esc_html__( 'Card', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$this->var_color( 'card_bg', esc_html__( 'Background', 'samanzavarian-widgets' ), '--szs-bg' );
		$this->var_slider( 'radius', esc_html__( 'Radius', 'samanzavarian-widgets' ), '--szs-radius', Control_Helpers::ph( 22 ), [ 'min' => 0, 'max' => 48 ], [ 'px', 'custom' ], [ null, 18 ] );
		$this->var_slider( 'pad', esc_html__( 'Padding', 'samanzavarian-widgets' ), '--szs-pad', Control_Helpers::ph( 16 ), [ 'min' => 6, 'max' => 40 ], [ 'px', 'custom' ], [ null, 12 ] );
		$this->var_slider( 'tile', esc_html__( 'Icon tile', 'samanzavarian-widgets' ), '--szs-tile', Control_Helpers::ph( 28 ), [ 'min' => 12, 'max' => 64 ], [ 'px' ], [ null, 24 ] );
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), [ 'name' => 'card_shadow', 'selector' => '{{WRAPPER}} .sz-stat:not(.is-hi)' ] );

		$this->end_controls_section();
	}

	private function style_text(): void {
		$this->start_controls_section( 'style_text', [ 'label' => esc_html__( 'Number and label', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$this->var_slider( 'num_size', esc_html__( 'Number size (largest)', 'samanzavarian-widgets' ), '--szs-num', Control_Helpers::ph( 44 ), [ 'min' => 20, 'max' => 96 ], [ 'px' ] );
		$this->add_control(
			'num_note',
			[
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'The number also shrinks with a narrow card, so it never spills out.', 'samanzavarian-widgets' ),
				'content_classes' => 'elementor-descriptor',
			]
		);
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'num_typo', 'label' => esc_html__( 'Number', 'samanzavarian-widgets' ), 'selector' => '{{WRAPPER}} .sz-stat__num', 'exclude' => [ 'font_size', 'line_height' ] ] );
		$this->var_color( 'num_color', esc_html__( 'Number', 'samanzavarian-widgets' ), '--szs-ink' );
		$this->var_color( 'plus_color', esc_html__( 'Plus and suffix', 'samanzavarian-widgets' ), '--szs-accent' );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'label_typo', 'label' => esc_html__( 'Label', 'samanzavarian-widgets' ), 'selector' => '{{WRAPPER}} .sz-stat__label' ] );
		$this->var_color( 'label_color', esc_html__( 'Label', 'samanzavarian-widgets' ), '--szs-label' );
		$this->var_color( 'detail_color', esc_html__( 'Detail line', 'samanzavarian-widgets' ), '--szs-detail' );

		$this->end_controls_section();
	}

	private function style_highlight(): void {
		$this->start_controls_section( 'style_hi', [ 'label' => esc_html__( 'Highlighted card', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$this->var_color( 'hi_bg', esc_html__( 'Background', 'samanzavarian-widgets' ), '--szs-hi-bg' );
		$this->var_color( 'hi_num', esc_html__( 'Number', 'samanzavarian-widgets' ), '--szs-hi-ink' );
		$this->var_color( 'hi_label', esc_html__( 'Label', 'samanzavarian-widgets' ), '--szs-hi-label' );
		$this->var_color( 'hi_glow', esc_html__( 'Light under the pointer', 'samanzavarian-widgets' ), '--szs-hi-glow' );

		$this->end_controls_section();
	}

	/* =================================================================
	 * Data
	 * ================================================================= */

	/**
	 * Cards as they will show: their figure worked out and formatted.
	 *
	 * @param array<string,mixed> $s
	 * @return array<int,array<string,mixed>>
	 */
	public static function cards( array $s, ?int $year = null ): array {
		$year = $year ?? (int) wp_date( 'Y' );
		$sep  = 'yes' === ( $s['separator'] ?? 'yes' ) ? ',' : '';
		$out  = [];

		foreach ( (array) ( $s['cards'] ?? [] ) as $c ) {
			$years = 'years' === ( $c['kind'] ?? 'number' );
			$since = (int) ( $c['since'] ?? 0 );
			$dec   = $years ? 0 : max( 0, min( 2, (int) ( $c['decimals'] ?? 0 ) ) );
			$value = $years ? max( 0, $year - $since ) : (float) ( $c['value'] ?? 0 );
			$text  = number_format( $value, $dec, '.', $sep );
			$label = trim( (string) ( $c['label'] ?? '' ) );

			$out[] = [
				'id'     => (string) ( $c['_id'] ?? '' ),
				'value'  => $value,
				'dec'    => $dec,
				'text'   => $text,
				'prefix' => trim( (string) ( $c['prefix'] ?? '' ) ),
				'suffix' => trim( (string) ( $c['suffix'] ?? '' ) ),
				'label'  => $label,
				'detail' => trim( str_replace( '{since}', (string) $since, (string) ( $c['detail'] ?? '' ) ) ),
				'icon'   => $c['icon'] ?? [],
				'tile'   => in_array( $c['tile'] ?? '', [ 'orange', 'white' ], true ) ? $c['tile'] : '',
				'hi'     => 'yes' === ( $c['highlight'] ?? '' ),
				'link'   => is_array( $c['link'] ?? null ) && '' !== trim( (string) ( $c['link']['url'] ?? '' ) ) ? $c['link'] : null,
			];
		}

		return $out;
	}

	/* =================================================================
	 * Render
	 * ================================================================= */

	protected function render(): void {
		$s     = $this->get_settings_for_display();
		$cards = self::cards( $s );

		if ( ! $cards ) {
			return;
		}

		$ms = static fn( $v, $d ) => (int) ( is_array( $v ) && isset( $v['size'] ) && '' !== $v['size'] ? $v['size'] : $d );

		$this->add_render_attribute(
			'root',
			[
				'class'         => 'sz-stats sz-w',
				'style'         => '--szs-n:' . count( $cards ),
				'data-sz-stats' => '',
				'data-count'    => 'yes' === ( $s['count'] ?? 'yes' ) ? '1' : '0',
				'data-duration' => (string) $ms( $s['duration'] ?? null, 1400 ),
				'data-stagger'  => (string) $ms( $s['stagger'] ?? null, 140 ),
				'data-sep'      => 'yes' === ( $s['separator'] ?? 'yes' ) ? ',' : '',
			]
		);
		?>
		<div <?php $this->print_render_attribute_string( 'root' ); ?>>
			<div class="sz-stats__grid">
				<?php
				foreach ( $cards as $i => $c ) {
					$this->render_card( $c, $i );
				}
				?>
			</div>
		</div>
		<?php
	}

	/** @param array<string,mixed> $c */
	private function render_card( array $c, int $i ): void {
		$key = 'card' . $i;
		$el  = $c['link'] ? 'a' : 'div';

		$this->add_render_attribute( $key, 'class', array_filter( [ 'sz-stat', $c['hi'] ? 'is-hi' : '', $c['link'] ? 'is-link' : '', $c['id'] ? 'elementor-repeater-item-' . $c['id'] : '' ] ) );
		if ( $c['link'] ) {
			$this->add_link_attributes( $key, $c['link'] );
		}

		$icon     = Icon_Library::html( $c['icon'] );
		$plus     = '+' === $c['suffix'];
		$sentence = trim( $c['prefix'] . $c['text'] . $c['suffix'] . ' ' . $c['label'] . ( '' !== $c['detail'] ? ', ' . $c['detail'] : '' ) );
		?>
		<<?php echo $el; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a or div ?> <?php $this->print_render_attribute_string( $key ); ?>>
			<?php if ( '' !== $icon ) : ?><span class="sz-stat__tile sz-app<?php echo $c['tile'] ? ' sz-app--' . esc_attr( $c['tile'] ) : ''; ?>" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon renderer ?></span><?php endif; ?>
			<span class="sz-stat__num" aria-hidden="true"><?php if ( '' !== $c['prefix'] ) : ?><span class="sz-stat__pre"><?php echo esc_html( $c['prefix'] ); ?></span><?php endif; ?><span class="sz-stat__d" data-to="<?php echo esc_attr( (string) $c['value'] ); ?>" data-dec="<?php echo (int) $c['dec']; ?>"><?php echo esc_html( $c['text'] ); ?></span><?php
			if ( $plus ) :
				?><em class="sz-stat__plus"><svg viewBox="0 0 10 10" focusable="false"><path d="M5 1.2v7.6M1.2 5h7.6"/></svg></em><?php
			elseif ( '' !== $c['suffix'] ) :
				?><em class="sz-stat__suf"><?php echo esc_html( $c['suffix'] ); ?></em><?php
			endif;
			?></span>
			<?php if ( '' !== $c['label'] ) : ?><span class="sz-stat__label" aria-hidden="true"><?php echo esc_html( $c['label'] ); ?></span><?php endif; ?>
			<?php if ( '' !== $c['detail'] ) : ?><span class="sz-stat__detail" aria-hidden="true"><?php echo esc_html( $c['detail'] ); ?></span><?php endif; ?>
			<span class="sz-stat__sr"><?php echo esc_html( $sentence ); ?></span>
		</<?php echo $el; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a or div ?>>
		<?php
	}
}
