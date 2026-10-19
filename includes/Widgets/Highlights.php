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
 * Highlights — "What I bring to a project": a deck of short cards, one at a
 * time, advancing on its own with story-style progress segments.
 *
 * Every card sits in the same grid cell, so the deck is as tall as its
 * longest card and never jumps between them. The script moves the deck: the
 * leaving card's content slides away in the direction of travel, the next
 * one's icon springs in and its title and text follow; the sheets under the
 * deck dip as if the top card was lifted off. Auto-advance pauses under
 * keyboard focus, in a background tab and off screen; it stops for good
 * once the visitor takes over, and the pause button (WCAG 2.2.2) stops or
 * restarts it. Screen readers hear only the changes a visitor makes.
 */
class Highlights extends Widget_Base {

	/** The design's cards: icon, tile, title, text. */
	private const ITEMS = [
		[ 'film', 'orange', 'Visual storytelling', 'Scripted, shot and edited brand films end to end at Polytech and Rizom Design.' ],
		[ 'palette', '', 'Art direction', 'Concepts and a visual language agreed before production starts, so the work stays on brief.' ],
		[ 'brief', '', 'Commercial thinking', 'Six years of brand and sales-focused work for product, industrial and corporate clients.' ],
		[ 'cal', '', 'Production management', 'Led the content team at Rizom Design and runs projects at Pely Media today.' ],
		[ 'sliders', '', 'Detail-oriented finishing', 'Retouching, grading and motion handled in-house, down to the final export.' ],
		[ 'spark', '', 'Quick to adopt new tools', 'From camera and Premiere to Midjourney, Runway, Veo and Flux, without losing the craft.' ],
	];

	public function get_name(): string {
		return 'sz-highlights';
	}

	public function get_title(): string {
		return esc_html__( 'Highlights', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-slides';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'highlights', 'strengths', 'what i bring', 'skills', 'stories', 'carousel', 'deck' ];
	}

	public function get_style_depends(): array {
		return [ 'sz-highlights' ];
	}

	public function get_script_depends(): array {
		return [ 'sz-highlights' ];
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
		$this->start_controls_section( 'section_items', [ 'label' => esc_html__( 'Cards', 'samanzavarian-widgets' ) ] );

		$this->add_control(
			'heading',
			[
				'label'       => esc_html__( 'Heading', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'What I bring to a project', 'samanzavarian-widgets' ),
				'label_block' => true,
				'dynamic'     => [ 'active' => true ],
			]
		);

		$this->add_control(
			'heading_tag',
			[
				'label'   => esc_html__( 'Heading tag', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'h3',
				'options' => [ 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'p' => 'p', 'span' => 'span' ],
			]
		);

		$r = new Repeater();
		$r->add_control( 'icon', [ 'label' => esc_html__( 'Icon', 'samanzavarian-widgets' ), 'type' => Controls_Manager::ICONS, 'default' => Icon_Library::value( 'spark' ) ] );
		$r->add_control(
			'tile',
			[
				'label'   => esc_html__( 'Icon tile', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => [ '' => esc_html__( 'Navy', 'samanzavarian-widgets' ), 'orange' => esc_html__( 'Orange', 'samanzavarian-widgets' ), 'white' => esc_html__( 'White', 'samanzavarian-widgets' ) ],
			]
		);
		$r->add_control( 'title', [ 'label' => esc_html__( 'Title', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => esc_html__( 'Strength', 'samanzavarian-widgets' ), 'label_block' => true, 'dynamic' => [ 'active' => true ] ] );
		$r->add_control( 'text', [ 'label' => esc_html__( 'Text', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 3, 'dynamic' => [ 'active' => true ], 'description' => esc_html__( 'One or two sentences. The deck takes the height of its longest card.', 'samanzavarian-widgets' ) ] );

		$this->add_control(
			'items',
			[
				'label'       => esc_html__( 'Cards', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => array_map( static fn( $i ) => [ 'icon' => Icon_Library::value( $i[0] ), 'tile' => $i[1], 'title' => $i[2], 'text' => $i[3] ], self::ITEMS ),
				'title_field' => '{{{ title }}}',
				'button_text' => esc_html__( 'Add card', 'samanzavarian-widgets' ),
			]
		);

		$this->end_controls_section();

		$this->start_controls_section( 'section_behaviour', [ 'label' => esc_html__( 'Behaviour', 'samanzavarian-widgets' ) ] );

		$this->add_control(
			'autoplay',
			[
				'label'        => esc_html__( 'Advance on its own', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'description'  => esc_html__( 'Pauses while keyboard focus is on it, in a background tab and off screen. Stops for good once a visitor picks a card; the pause button restarts it. Never with reduced motion. In the editor, picking a card in the panel shows it and holds still.', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control(
			'duration',
			[
				'label'      => esc_html__( 'Time per card', 'samanzavarian-widgets' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 's' ],
				'range'      => [ 's' => [ 'min' => 2, 'max' => 15, 'step' => 0.5 ] ],
				'default'    => [ 'size' => 5.2, 'unit' => 's' ],
				'condition'  => [ 'autoplay' => 'yes' ],
			]
		);

		$this->add_control(
			'pause_button',
			[
				'label'        => esc_html__( 'Pause button', 'samanzavarian-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'description'  => esc_html__( 'Content that moves by itself for more than five seconds needs a way to stop it (WCAG 2.2.2).', 'samanzavarian-widgets' ),
				'condition'    => [ 'autoplay' => 'yes' ],
			]
		);

		$this->add_control( 'counter', [ 'label' => esc_html__( 'Counter ("1 of 6")', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'separator' => 'before' ] );
		$this->add_control( 'sheets', [ 'label' => esc_html__( 'Sheets under the deck', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'description' => esc_html__( 'The two edges peeking out below, as in the design; they dip on every change.', 'samanzavarian-widgets' ) ] );

		$this->end_controls_section();

		$this->start_controls_section( 'section_labels', [ 'label' => esc_html__( 'Labels', 'samanzavarian-widgets' ) ] );
		foreach ( self::label_defaults() as $key => $def ) {
			$this->add_control( 'label_' . $key, [ 'label' => $def[1], 'type' => Controls_Manager::TEXT, 'default' => $def[0], 'ai' => [ 'active' => false ] ] );
		}
		$this->end_controls_section();

		$this->style_controls();
	}

	/** @return array<string,array{0:string,1:string}> */
	private static function label_defaults(): array {
		return [
			'of'    => [ __( 'of', 'samanzavarian-widgets' ), __( '"of" in the counter', 'samanzavarian-widgets' ) ],
			'prev'  => [ __( 'Previous', 'samanzavarian-widgets' ), __( 'Previous button (screen readers)', 'samanzavarian-widgets' ) ],
			'next'  => [ __( 'Next', 'samanzavarian-widgets' ), __( 'Next button (screen readers)', 'samanzavarian-widgets' ) ],
			'pause' => [ __( 'Pause', 'samanzavarian-widgets' ), __( 'Pause button (screen readers)', 'samanzavarian-widgets' ) ],
			'play'  => [ __( 'Play', 'samanzavarian-widgets' ), __( 'Play button (screen readers)', 'samanzavarian-widgets' ) ],
			'pick'  => [ __( 'Choose a card', 'samanzavarian-widgets' ), __( 'Progress segments (screen readers)', 'samanzavarian-widgets' ) ],
		];
	}

	private function var_slider( string $id, string $label, string $var, $ph, array $range, array $units = [ 'px', 'custom' ], array $device = [ null, null ] ): void {
		$args = [
			'label'       => $label,
			'type'        => Controls_Manager::SLIDER,
			'placeholder' => $ph,
			'size_units'  => $units,
			'range'       => [ $units[0] => $range ],
			'selectors'   => [ '{{WRAPPER}} .sz-hl' => $var . ': {{SIZE}}{{UNIT}};' ],
		];
		foreach ( [ 'tablet_default', 'mobile_default' ] as $n => $key ) {
			if ( null !== $device[ $n ] ) {
				$args[ $key ] = [ 'size' => $device[ $n ], 'unit' => $units[0] ];
			}
		}
		$this->add_responsive_control( $id, $args );
	}

	private function var_color( string $id, string $label, string $var ): void {
		$this->add_control( $id, [ 'label' => $label, 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .sz-hl' => $var . ': {{VALUE}};' ] ] );
	}

	private function style_controls(): void {
		$this->start_controls_section( 'style_deck', [ 'label' => esc_html__( 'Deck', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		$this->var_slider( 'max_width', esc_html__( 'Max width', 'samanzavarian-widgets' ), '--szh-max', Control_Helpers::ph( 400 ), [ 'min' => 240, 'max' => 900 ], [ 'px', '%', 'custom' ] );
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
				'selectors'            => [ '{{WRAPPER}} .sz-hl' => '{{VALUE}};' ],
			]
		);
		$this->var_color( 'card_bg', esc_html__( 'Background', 'samanzavarian-widgets' ), '--szh-bg' );
		$this->var_slider( 'radius', esc_html__( 'Radius', 'samanzavarian-widgets' ), '--szh-radius', Control_Helpers::ph( 28 ), [ 'min' => 0, 'max' => 48 ], [ 'px', 'custom' ], [ null, 24 ] );
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), [ 'name' => 'card_shadow', 'selector' => '{{WRAPPER}} .sz-hl__card' ] );
		$this->var_slider( 'icon_size', esc_html__( 'Icon tile', 'samanzavarian-widgets' ), '--szh-tile', Control_Helpers::ph( 44 ), [ 'min' => 24, 'max' => 72 ], [ 'px' ], [ null, 40 ] );
		$this->end_controls_section();

		$this->start_controls_section( 'style_text', [ 'label' => esc_html__( 'Text', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'head_typo', 'label' => esc_html__( 'Heading', 'samanzavarian-widgets' ), 'selector' => '{{WRAPPER}} .sz-hl__head' ] );
		$this->var_color( 'head_color', esc_html__( 'Heading and counter', 'samanzavarian-widgets' ), '--szh-head' );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'title_typo', 'label' => esc_html__( 'Title', 'samanzavarian-widgets' ), 'selector' => '{{WRAPPER}} .sz-hl__title' ] );
		$this->var_color( 'title_color', esc_html__( 'Title', 'samanzavarian-widgets' ), '--szh-ink' );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'text_typo', 'label' => esc_html__( 'Text', 'samanzavarian-widgets' ), 'selector' => '{{WRAPPER}} .sz-hl__text' ] );
		$this->var_color( 'text_color', esc_html__( 'Text', 'samanzavarian-widgets' ), '--szh-text' );
		$this->end_controls_section();

		$this->start_controls_section( 'style_controls', [ 'label' => esc_html__( 'Progress and buttons', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		$this->var_color( 'seg_track', esc_html__( 'Segment track', 'samanzavarian-widgets' ), '--szh-seg' );
		$this->var_color( 'seg_fill', esc_html__( 'Segment fill', 'samanzavarian-widgets' ), '--szh-seg-on' );
		$this->var_color( 'btn_bg', esc_html__( 'Buttons', 'samanzavarian-widgets' ), '--szh-btn' );
		$this->var_color( 'btn_hover', esc_html__( 'Buttons on hover', 'samanzavarian-widgets' ), '--szh-btn-hover' );
		$this->var_color( 'btn_icon', esc_html__( 'Button arrows', 'samanzavarian-widgets' ), '--szh-btn-icon' );
		$this->end_controls_section();
	}

	/* =================================================================
	 * Render
	 * ================================================================= */

	/** @param array<string,mixed> $s @return array<int,array<string,mixed>> */
	public static function items( array $s ): array {
		$out = [];
		foreach ( (array) ( $s['items'] ?? [] ) as $it ) {
			$title = trim( (string) ( $it['title'] ?? '' ) );
			if ( '' === $title ) {
				continue;
			}
			$out[] = [
				'id'    => (string) ( $it['_id'] ?? '' ),
				'title' => $title,
				'text'  => trim( (string) ( $it['text'] ?? '' ) ),
				'icon'  => $it['icon'] ?? [],
				'tile'  => in_array( $it['tile'] ?? '', [ 'orange', 'white' ], true ) ? $it['tile'] : '',
			];
		}
		return $out;
	}

	protected function render(): void {
		$s     = $this->get_settings_for_display();
		$items = self::items( $s );

		if ( ! $items ) {
			return;
		}

		$uid  = 'szh-' . $this->get_id();
		$n    = count( $items );
		$many = $n > 1;
		$auto = $many && 'yes' === ( $s['autoplay'] ?? 'yes' );
		$dur  = (float) ( $s['duration']['size'] ?? 5.2 );
		$l    = [];
		foreach ( self::label_defaults() as $k => $def ) {
			$v       = trim( (string) ( $s[ 'label_' . $k ] ?? '' ) );
			$l[ $k ] = '' !== $v ? $v : $def[0];
		}
		$tag     = in_array( $s['heading_tag'] ?? 'h3', [ 'h2', 'h3', 'h4', 'p', 'span' ], true ) ? $s['heading_tag'] : 'h3';
		$heading = trim( (string) ( $s['heading'] ?? '' ) );

		$this->add_render_attribute(
			'root',
			[
				'class'          => array_filter( [ 'sz-hl', 'sz-w', 'yes' === ( $s['sheets'] ?? 'yes' ) && $many ? 'has-sheets' : '' ] ),
				'data-sz-hl'     => '',
				'data-auto'      => $auto ? '1' : '0',
				'data-duration'  => (string) (int) round( max( 2, min( 15, $dur ) ) * 1000 ),
				'data-labels'    => wp_json_encode( [ 'pause' => $l['pause'], 'play' => $l['play'], 'of' => $l['of'] ] ),
			]
		);
		?>
		<div <?php $this->print_render_attribute_string( 'root' ); ?>>
			<?php if ( '' !== $heading || ( $many && 'yes' === ( $s['counter'] ?? 'yes' ) ) || $auto ) : ?>
				<div class="sz-hl__top">
					<?php if ( '' !== $heading ) : ?><<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- whitelisted ?> class="sz-hl__head" id="<?php echo esc_attr( $uid ); ?>-h"><?php echo esc_html( $heading ); ?></<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- whitelisted ?>><?php endif; ?>
					<?php if ( $many && 'yes' === ( $s['counter'] ?? 'yes' ) ) : ?>
						<span class="sz-hl__n" aria-hidden="true"><span class="sz-hl__roll"><span class="sz-hl__i">1</span></span> <?php echo esc_html( $l['of'] ); ?> <?php echo (int) $n; ?></span>
					<?php endif; ?>
					<?php if ( $auto && 'yes' === ( $s['pause_button'] ?? 'yes' ) ) : ?>
						<button type="button" class="sz-hl__pp" aria-pressed="false" aria-label="<?php echo esc_attr( $l['pause'] ); ?>" hidden><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path class="sz-hl__pp-pause" d="M9 7v10M15 7v10"/><path class="sz-hl__pp-play" d="M9 6.5v11l9-5.5z"/></svg></button>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="sz-hl__deck">
				<div class="sz-hl__card" role="region" aria-roledescription="carousel" <?php echo '' !== $heading ? 'aria-labelledby="' . esc_attr( $uid ) . '-h"' : ''; ?>>
					<div class="sz-hl__slides" tabindex="<?php echo $many ? '0' : '-1'; ?>" aria-live="<?php echo $auto ? 'off' : 'polite'; ?>">
						<?php foreach ( $items as $i => $it ) : ?>
							<div class="sz-hl__slide<?php echo 0 === $i ? ' is-on' : ''; ?><?php echo $it['id'] ? ' elementor-repeater-item-' . esc_attr( $it['id'] ) : ''; ?>" id="<?php echo esc_attr( $uid . '-' . $i ); ?>" role="group" aria-roledescription="slide" aria-label="<?php echo esc_attr( ( $i + 1 ) . ' ' . $l['of'] . ' ' . $n ); ?>"<?php echo 0 === $i ? '' : ' aria-hidden="true" inert'; ?>>
								<?php $icon = Icon_Library::html( $it['icon'] ); ?>
								<?php if ( '' !== $icon ) : ?><span class="sz-hl__tile sz-app<?php echo $it['tile'] ? ' sz-app--' . esc_attr( $it['tile'] ) : ''; ?>" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon renderer ?></span><?php endif; ?>
								<div class="sz-hl__body">
									<b class="sz-hl__title"><?php echo esc_html( $it['title'] ); ?></b>
									<?php if ( '' !== $it['text'] ) : ?><p class="sz-hl__text"><?php echo esc_html( $it['text'] ); ?></p><?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
					<?php if ( $many ) : ?>
						<div class="sz-hl__foot">
							<div class="sz-hl__segs" role="tablist" aria-label="<?php echo esc_attr( $l['pick'] ); ?>">
								<?php foreach ( $items as $i => $it ) : ?>
									<button type="button" class="sz-hl__seg" role="tab" data-i="<?php echo (int) $i; ?>" aria-controls="<?php echo esc_attr( $uid . '-' . $i ); ?>" aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( $it['title'] ); ?>" tabindex="<?php echo 0 === $i ? '0' : '-1'; ?>"><i></i><span class="sz-hl__tip" aria-hidden="true"><?php echo esc_html( $it['title'] ); ?></span></button>
								<?php endforeach; ?>
							</div>
							<button type="button" class="sz-hl__btn" data-dir="-1" aria-label="<?php echo esc_attr( $l['prev'] ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m15 6-6 6 6 6"/></svg></button>
							<button type="button" class="sz-hl__btn" data-dir="1" aria-label="<?php echo esc_attr( $l['next'] ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m9 6 6 6-6 6"/></svg></button>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
