<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Plugin as Elementor;
use Elementor\Widget_Base;
use Samanzavarian_Widgets\Icon_Library;
use Samanzavarian_Widgets\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Section tag — the small pill above a section title: an icon tile and a
 * name ("What I do", "Selected work"), optionally a badge ("106+") and a
 * link. The tile pops once as the tag scrolls into view: CSS scroll-driven
 * animation, no script; browsers without it simply show the tag.
 *
 * Also home of the style controls both pills share (Status uses them too).
 */
class Section_Tag extends Widget_Base {

	public function get_name(): string {
		return 'sz-tag';
	}

	public function get_title(): string {
		return esc_html__( 'Section Tag', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-tags';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'tag', 'eyebrow', 'label', 'pill', 'badge', 'section', 'kicker' ];
	}

	public function get_style_depends(): array {
		return [ 'sz-pills' ];
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
		$this->start_controls_section( 'section_tag', [ 'label' => esc_html__( 'Tag', 'samanzavarian-widgets' ) ] );

		$this->add_control(
			'text',
			[
				'label'       => esc_html__( 'Text', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'What I do', 'samanzavarian-widgets' ),
				'label_block' => true,
				'dynamic'     => [ 'active' => true ],
			]
		);

		$this->add_control(
			'icon',
			[
				'label'   => esc_html__( 'Icon', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::ICONS,
				'default' => Icon_Library::value( 'spark' ),
			]
		);

		$this->add_control(
			'tile',
			[
				'label'   => esc_html__( 'Icon tile', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'orange',
				'options' => [
					'orange' => esc_html__( 'Orange', 'samanzavarian-widgets' ),
					''       => esc_html__( 'Navy', 'samanzavarian-widgets' ),
					'white'  => esc_html__( 'White', 'samanzavarian-widgets' ),
				],
			]
		);

		$this->add_control(
			'badge',
			[
				'label'       => esc_html__( 'Badge', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => '106+',
				'description' => esc_html__( 'Optional, after the text: a count ("106+") or a word ("New").', 'samanzavarian-widgets' ),
				'dynamic'     => [ 'active' => true ],
			]
		);

		$this->add_control(
			'link',
			[
				'label'       => esc_html__( 'Link', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::URL,
				'description' => esc_html__( 'Optional. An arrow appears on hover.', 'samanzavarian-widgets' ),
				'dynamic'     => [ 'active' => true ],
			]
		);

		$this->add_control(
			'html_tag',
			[
				'label'       => esc_html__( 'HTML tag', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'span',
				'options'     => [ 'span' => 'span', 'p' => 'p', 'div' => 'div', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6' ],
				'description' => esc_html__( 'span (design): the section title stays the heading. A heading tag only if the tag is the section\'s name to search engines.', 'samanzavarian-widgets' ),
				'condition'   => [ 'link[url]' => '' ],
			]
		);

		$this->add_control(
			'pop',
			[
				'label'        => esc_html__( 'Pop the icon into view', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'description'  => esc_html__( 'The tile springs up once as the tag scrolls in. No script; not with reduced motion; browsers without scroll-driven animation just show the tag.', 'samanzavarian-widgets' ),
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
					'light' => esc_html__( 'White (on light backgrounds)', 'samanzavarian-widgets' ),
					'dark'  => esc_html__( 'Dark (on dark backgrounds)', 'samanzavarian-widgets' ),
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

		self::pill_style_controls( $this, 'sz-tag' );

		$this->start_controls_section( 'style_tile', [ 'label' => esc_html__( 'Icon tile and badge', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$this->add_responsive_control(
			'tile_size',
			[
				'label'       => esc_html__( 'Tile size', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SLIDER,
				'placeholder' => Control_Helpers::ph( 24 ),
				'size_units'  => [ 'px' ],
				'range'       => [ 'px' => [ 'min' => 14, 'max' => 60 ] ],
				'selectors'   => [ '{{WRAPPER}} .sz-pill' => '--szp-tile: {{SIZE}}{{UNIT}};' ],
			]
		);

		foreach ( [
			'tile_bg'    => [ esc_html__( 'Tile', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-tag__tile' => 'background: {{VALUE}};' ],
			'tile_color' => [ esc_html__( 'Icon', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-tag__tile' => 'color: {{VALUE}};' ],
			'badge_bg'   => [ esc_html__( 'Badge', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-pill' => '--szp-badge: {{VALUE}};' ],
			'badge_text' => [ esc_html__( 'Badge text', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-pill' => '--szp-badge-text: {{VALUE}};' ],
		] as $id => $def ) {
			$label = array_shift( $def );
			$this->add_control( $id, [ 'label' => $label, 'type' => Controls_Manager::COLOR, 'selectors' => $def ] );
		}

		$this->end_controls_section();
	}

	/**
	 * Style controls both pills share: size, type, colours, shadow.
	 *
	 * @param Widget_Base $w
	 */
	public static function pill_style_controls( Widget_Base $w, string $class ): void {
		$sel = '{{WRAPPER}} .' . $class;

		$w->start_controls_section( 'style_pill', [ 'label' => esc_html__( 'Pill', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$w->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'pill_typo', 'selector' => $sel ] );

		$w->add_responsive_control(
			'pill_height',
			[
				'label'       => esc_html__( 'Height', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SLIDER,
				'placeholder' => Control_Helpers::ph( 'sz-tag' === $class ? 34 : 36 ),
				'size_units'  => [ 'px' ],
				'range'       => [ 'px' => [ 'min' => 22, 'max' => 72 ] ],
				'selectors'   => [ $sel => '--szp-h: {{SIZE}}{{UNIT}};' ],
			]
		);

		foreach ( [
			'pill_bg'          => [ esc_html__( 'Background', 'samanzavarian-widgets' ), '--szp-bg' ],
			'pill_color'       => [ esc_html__( 'Text', 'samanzavarian-widgets' ), '--szp-text' ],
			'pill_color_hover' => [ esc_html__( 'Text on hover (with a link)', 'samanzavarian-widgets' ), '--szp-text-hover' ],
		] as $id => $def ) {
			$w->add_control( $id, [ 'label' => $def[0], 'type' => Controls_Manager::COLOR, 'selectors' => [ $sel => $def[1] . ': {{VALUE}};' ] ] );
		}

		$w->add_group_control( Group_Control_Box_Shadow::get_type(), [ 'name' => 'pill_shadow', 'selector' => $sel ] );

		$w->end_controls_section();
	}

	/* =================================================================
	 * Render
	 * ================================================================= */

	protected function render(): void {
		$s    = $this->get_settings_for_display();
		$text = trim( (string) ( $s['text'] ?? '' ) );

		if ( '' === $text ) {
			return;
		}

		$link  = is_array( $s['link'] ?? null ) && '' !== trim( (string) ( $s['link']['url'] ?? '' ) ) ? $s['link'] : null;
		$tag   = $link ? 'a' : ( in_array( $s['html_tag'] ?? 'span', [ 'span', 'p', 'div', 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ? $s['html_tag'] : 'span' );
		$icon  = Icon_Library::html( $s['icon'] ?? [] );
		$tile  = in_array( $s['tile'] ?? 'orange', [ 'orange', 'white' ], true ) ? ' sz-app--' . $s['tile'] : '';
		$badge = trim( (string) ( $s['badge'] ?? '' ) );

		$this->add_render_attribute(
			'pill',
			'class',
			array_filter( [
				'sz-pill',
				'sz-tag',
				'dark' === ( $s['skin'] ?? 'light' ) ? 'sz-pill--dark' : '',
				'yes' === ( $s['pop'] ?? 'yes' ) ? 'is-pop' : '',
				$link ? 'is-link' : '',
				'' === $icon ? 'no-icon' : '',
			] )
		);
		if ( $link ) {
			$this->add_link_attributes( 'pill', $link );
		}
		?>
		<div class="sz-pillrow sz-w">
			<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- whitelisted ?> <?php $this->print_render_attribute_string( 'pill' ); ?>>
				<?php if ( '' !== $icon ) : ?><span class="sz-tag__tile sz-app<?php echo esc_attr( $tile ); ?>" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon renderer ?></span><?php endif; ?>
				<span class="sz-tag__text"><?php echo esc_html( $text ); ?></span>
				<?php if ( '' !== $badge ) : ?><span class="sz-tag__badge"><?php echo esc_html( $badge ); ?></span><?php endif; ?>
				<?php if ( $link ) : ?><svg class="sz-pill__arrow" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 12h14M13 6l6 6-6 6"/></svg><?php endif; ?>
			</<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- whitelisted ?>>
		</div>
		<?php
	}
}
