<?php
namespace Samanzavarian_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Plugin as Elementor;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Samanzavarian_Widgets\Icon_Library;
use Samanzavarian_Widgets\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Toolkit — the card under the career: education, then the tools by kind
 * ("Editing and finishing", "AI image and video"). One row each: an icon
 * tile and a label, then either two lines of text (a degree, where) or the
 * tools as pills. A row of pills can show how many there are; a tool can
 * be marked "daily".
 *
 * Nothing here is interactive, so nothing pretends to be: no hover on the
 * pills. The first time the card scrolls into view the rows rise one after
 * another, each tile springs in and its pills follow in a quick cascade;
 * on screen at load or with reduced motion it is simply there.
 */
class Toolkit extends Widget_Base {

	/** The design's rows: icon, tile, label, kind, title/tools, subtitle. */
	private const ROWS = [
		[ 'cap', '', 'Education', 'text', 'BA in Photography', 'Soore International University, Tehran' ],
		[ 'sliders', '', 'Editing and finishing', 'tags', "Photoshop\nPremiere Pro\nAfter Effects\nLightroom", '' ],
		[ 'spark', 'orange', 'AI image and video', 'tags', "Midjourney\nRunway\nKrea\nFlux\nVeo\nGoogle Flow\nSeedance\nSyntx", '' ],
	];

	public function get_name(): string {
		return 'sz-toolkit';
	}

	public function get_title(): string {
		return esc_html__( 'Toolkit', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-bullet-list';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'toolkit', 'tools', 'skills', 'software', 'education', 'degree', 'resume', 'cv' ];
	}

	public function get_style_depends(): array {
		return [ 'sz-toolkit' ];
	}

	public function get_script_depends(): array {
		return [ 'sz-toolkit' ];
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
		$this->start_controls_section( 'section_rows', [ 'label' => esc_html__( 'Rows', 'samanzavarian-widgets' ) ] );

		$r = new Repeater();
		$r->add_control( 'label', [ 'label' => esc_html__( 'Label', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => esc_html__( 'Tools', 'samanzavarian-widgets' ), 'label_block' => true, 'dynamic' => [ 'active' => true ] ] );
		$r->add_control( 'icon', [ 'label' => esc_html__( 'Icon', 'samanzavarian-widgets' ), 'type' => Controls_Manager::ICONS, 'default' => Icon_Library::value( 'sliders' ) ] );
		$r->add_control(
			'tile',
			[
				'label'       => esc_html__( 'Colour', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => '',
				'options'     => [ '' => esc_html__( 'Navy', 'samanzavarian-widgets' ), 'orange' => esc_html__( 'Orange', 'samanzavarian-widgets' ) ],
				'description' => esc_html__( 'The icon tile and the pills\' dots.', 'samanzavarian-widgets' ),
			]
		);
		$r->add_control(
			'kind',
			[
				'label'   => esc_html__( 'Shows', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::CHOOSE,
				'default' => 'tags',
				'toggle'  => false,
				'options' => [
					'tags' => [ 'title' => esc_html__( 'Tools as pills', 'samanzavarian-widgets' ), 'icon' => 'eicon-tags' ],
					'text' => [ 'title' => esc_html__( 'Two lines of text', 'samanzavarian-widgets' ), 'icon' => 'eicon-text' ],
				],
			]
		);
		$r->add_control(
			'tools',
			[
				'label'       => esc_html__( 'Tools', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 6,
				'default'     => "Photoshop\nPremiere Pro",
				'description' => esc_html__( 'One per line. End a line with * to mark a tool you use every day ("Premiere Pro *").', 'samanzavarian-widgets' ),
				'condition'   => [ 'kind' => 'tags' ],
			]
		);
		$r->add_control( 'count', [ 'label' => esc_html__( 'Show how many', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'description' => esc_html__( 'A small number beside the label.', 'samanzavarian-widgets' ), 'condition' => [ 'kind' => 'tags' ] ] );
		$r->add_control( 'title', [ 'label' => esc_html__( 'First line', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'label_block' => true, 'dynamic' => [ 'active' => true ], 'condition' => [ 'kind' => 'text' ] ] );
		$r->add_control( 'sub', [ 'label' => esc_html__( 'Second line', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'label_block' => true, 'dynamic' => [ 'active' => true ], 'condition' => [ 'kind' => 'text' ] ] );
		$r->add_control( 'years', [ 'label' => esc_html__( 'Years', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'placeholder' => '2014 to 2018', 'description' => esc_html__( 'Optional; joins the second line after a dot.', 'samanzavarian-widgets' ), 'condition' => [ 'kind' => 'text' ] ] );

		$this->add_control(
			'rows',
			[
				'label'       => esc_html__( 'Rows', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => array_map(
					static fn( $x ) => [ 'icon' => Icon_Library::value( $x[0] ), 'tile' => $x[1], 'label' => $x[2], 'kind' => $x[3], 'tools' => 'tags' === $x[3] ? $x[4] : '', 'title' => 'text' === $x[3] ? $x[4] : '', 'sub' => $x[5], 'count' => 'orange' === $x[1] ? 'yes' : '' ],
					self::ROWS
				),
				'title_field' => '{{{ label }}}',
				'button_text' => esc_html__( 'Add row', 'samanzavarian-widgets' ),
			]
		);

		$this->add_control( 'daily_label', [ 'label' => esc_html__( '"daily" (tools marked with *)', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => esc_html__( 'daily', 'samanzavarian-widgets' ), 'separator' => 'before' ] );
		$this->add_control( 'arrival', [ 'label' => esc_html__( 'Rise in on arrival', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'description' => esc_html__( 'The first time the card scrolls into view. Never with reduced motion, or when it is on screen as the page opens.', 'samanzavarian-widgets' ) ] );

		$this->end_controls_section();

		$this->start_controls_section( 'style_card', [ 'label' => esc_html__( 'Card', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		$this->var_color( 'card_bg', esc_html__( 'Background', 'samanzavarian-widgets' ), '--szt-bg' );
		$this->var_slider( 'radius', esc_html__( 'Radius', 'samanzavarian-widgets' ), '--szt-radius', Control_Helpers::ph( 28 ), [ 'min' => 0, 'max' => 48 ], [ null, 24 ] );
		$this->var_color( 'line', esc_html__( 'Lines between rows', 'samanzavarian-widgets' ), '--szt-line' );
		$this->var_slider( 'label_w', esc_html__( 'Label column', 'samanzavarian-widgets' ), '--szt-label', Control_Helpers::ph( 236 ), [ 'min' => 120, 'max' => 400 ] );
		$this->end_controls_section();

		$this->start_controls_section( 'style_text', [ 'label' => esc_html__( 'Text and pills', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'label_typo', 'label' => esc_html__( 'Label', 'samanzavarian-widgets' ), 'selector' => '{{WRAPPER}} .sz-tk__label' ] );
		$this->var_color( 'ink', esc_html__( 'Label and first line', 'samanzavarian-widgets' ), '--szt-ink' );
		$this->var_color( 'muted', esc_html__( 'Second line', 'samanzavarian-widgets' ), '--szt-muted' );
		$this->var_color( 'pill_bg', esc_html__( 'Pill', 'samanzavarian-widgets' ), '--szt-pill' );
		$this->var_color( 'pill_text', esc_html__( 'Pill text', 'samanzavarian-widgets' ), '--szt-pill-ink' );
		$this->end_controls_section();
	}

	private function var_slider( string $id, string $label, string $var, $ph, array $range, array $device = [ null, null ] ): void {
		$args = [
			'label'       => $label,
			'type'        => Controls_Manager::SLIDER,
			'placeholder' => $ph,
			'size_units'  => [ 'px' ],
			'range'       => [ 'px' => $range ],
			'selectors'   => [ '{{WRAPPER}} .sz-tk' => $var . ': {{SIZE}}{{UNIT}};' ],
		];
		foreach ( [ 'tablet_default', 'mobile_default' ] as $n => $key ) {
			if ( null !== $device[ $n ] ) {
				$args[ $key ] = [ 'size' => $device[ $n ], 'unit' => 'px' ];
			}
		}
		$this->add_responsive_control( $id, $args );
	}

	private function var_color( string $id, string $label, string $var ): void {
		$this->add_control( $id, [ 'label' => $label, 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .sz-tk' => $var . ': {{VALUE}};' ] ] );
	}

	/* =================================================================
	 * Render
	 * ================================================================= */

	/**
	 * "Premiere Pro *" → [ 'Premiere Pro', true ].
	 *
	 * @return array<int,array{0:string,1:bool}>
	 */
	public static function tools( string $list ): array {
		$out = [];
		foreach ( preg_split( '/\R/u', $list ) ?: [] as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$daily = (bool) preg_match( '/\s*\*$/u', $line );
			$name  = trim( preg_replace( '/\s*\*$/u', '', $line ) );
			if ( '' !== $name ) {
				$out[] = [ $name, $daily ];
			}
		}
		return $out;
	}

	protected function render(): void {
		$s    = $this->get_settings_for_display();
		$rows = array_values( array_filter( (array) ( $s['rows'] ?? [] ), static fn( $r ) => '' !== trim( (string) ( $r['label'] ?? '' ) ) ) );

		if ( ! $rows ) {
			return;
		}

		$daily = trim( (string) ( $s['daily_label'] ?? '' ) );
		$this->add_render_attribute( 'root', [ 'class' => 'sz-tk sz-w', 'data-sz-tk' => '', 'data-arrive' => 'yes' === ( $s['arrival'] ?? 'yes' ) ? '1' : '0' ] );
		?>
		<div <?php $this->print_render_attribute_string( 'root' ); ?>>
			<?php foreach ( $rows as $n => $r ) : ?>
				<?php
				$orange = 'orange' === ( $r['tile'] ?? '' );
				$kind   = 'text' === ( $r['kind'] ?? 'tags' ) ? 'text' : 'tags';
				$tools  = 'tags' === $kind ? self::tools( (string) ( $r['tools'] ?? '' ) ) : [];
				$icon   = Icon_Library::html( $r['icon'] ?? [] );
				?>
				<div class="sz-tk__row<?php echo $orange ? ' is-o' : ''; ?><?php echo ! empty( $r['_id'] ) ? ' elementor-repeater-item-' . esc_attr( $r['_id'] ) : ''; ?>" style="--r:<?php echo (int) $n; ?>">
					<div class="sz-tk__head">
						<?php if ( '' !== $icon ) : ?><span class="sz-tk__tile sz-app<?php echo $orange ? ' sz-app--orange' : ''; ?>" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon renderer ?></span><?php endif; ?>
						<span class="sz-tk__label"><?php echo esc_html( trim( (string) $r['label'] ) ); ?><?php if ( $tools && 'yes' === ( $r['count'] ?? '' ) ) : ?><span class="sz-tk__count" aria-hidden="true"><?php echo count( $tools ); ?></span><?php endif; ?></span>
					</div>
					<?php if ( 'text' === $kind ) : ?>
						<?php
						$sub   = trim( (string) ( $r['sub'] ?? '' ) );
						$years = trim( (string) ( $r['years'] ?? '' ) );
						?>
						<div class="sz-tk__text">
							<?php if ( '' !== trim( (string) ( $r['title'] ?? '' ) ) ) : ?><b class="sz-tk__title"><?php echo esc_html( trim( (string) $r['title'] ) ); ?></b><?php endif; ?>
							<?php if ( '' !== $sub || '' !== $years ) : ?><span class="sz-tk__sub"><?php echo esc_html( $sub ); ?><?php if ( '' !== $sub && '' !== $years ) : ?><span class="sz-tk__sep" aria-hidden="true">·</span><?php endif; ?><?php if ( '' !== $years ) : ?><span class="sz-tk__years"><?php echo esc_html( $years ); ?></span><?php endif; ?></span><?php endif; ?>
						</div>
					<?php elseif ( $tools ) : ?>
						<ul class="sz-tk__tools">
							<?php foreach ( $tools as $i => $t ) : ?>
								<li style="--i:<?php echo (int) $i; ?>"<?php echo $t[1] ? ' class="is-daily"' : ''; ?>><?php echo esc_html( $t[0] ); ?><?php if ( $t[1] && '' !== $daily ) : ?><span class="sz-tk__daily"><?php echo esc_html( $daily ); ?></span><?php endif; ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
