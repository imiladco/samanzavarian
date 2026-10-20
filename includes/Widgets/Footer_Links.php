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
 * Footer Links — the footer's link columns ("On this page", "Elsewhere")
 * in one widget: one list where a heading starts a new column, so the
 * columns share one grid, one rhythm and one set of styles.
 *
 * - Links to known places (email, Behance, LinkedIn, WhatsApp, Telegram,
 *   Instagram, a phone number) get their icon by themselves; any link can
 *   take its own.
 * - Under the pointer the words step aside, an orange line draws along the
 *   row (and leaves the way it came), and a link that leaves the site shows
 *   its ↗.
 * - The rows rise in, column by column, the first time the footer comes
 *   into view.
 */
class Footer_Links extends Widget_Base {

	/** host or scheme fragment => icon */
	private const KNOWN = [
		'mailto:'      => 'mail',
		'tel:'         => 'phone',
		'behance.'     => 'be',
		'linkedin.'    => 'in',
		'wa.me'        => 'wa',
		'whatsapp.'    => 'wa',
		't.me'         => 'tg',
		'telegram.'    => 'tg',
		'instagram.'   => 'ig',
	];

	public function get_name(): string {
		return 'sz-flinks';
	}

	public function get_title(): string {
		return esc_html__( 'Footer Links', 'samanzavarian-widgets' );
	}

	public function get_icon(): string {
		return 'eicon-bullet-list';
	}

	public function get_categories(): array {
		return [ Plugin::CATEGORY ];
	}

	public function get_keywords(): array {
		return [ 'footer', 'links', 'menu', 'social', 'elsewhere', 'columns' ];
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

	/** The icon a URL earns by where it goes, or ''. */
	public static function known_icon( string $url ): string {
		$url = strtolower( $url );
		foreach ( self::KNOWN as $needle => $icon ) {
			if ( false !== strpos( $url, $needle ) ) {
				return $icon;
			}
		}
		return '';
	}

	protected function register_controls(): void {
		$this->start_controls_section( 'section_links', [ 'label' => esc_html__( 'Links', 'samanzavarian-widgets' ) ] );
		$r = new Repeater();
		$r->add_control(
			'kind',
			[
				'label'   => esc_html__( 'This row is', 'samanzavarian-widgets' ),
				'type'    => Controls_Manager::CHOOSE,
				'default' => 'link',
				'toggle'  => false,
				'options' => [
					'head' => [ 'title' => esc_html__( 'A column heading (starts a new column)', 'samanzavarian-widgets' ), 'icon' => 'eicon-heading' ],
					'link' => [ 'title' => esc_html__( 'A link', 'samanzavarian-widgets' ), 'icon' => 'eicon-link' ],
				],
			]
		);
		$r->add_control( 'text', [ 'label' => esc_html__( 'Text', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => esc_html__( 'Link', 'samanzavarian-widgets' ) ] );
		$r->add_control( 'link', [ 'label' => esc_html__( 'Link', 'samanzavarian-widgets' ), 'type' => Controls_Manager::URL, 'dynamic' => [ 'active' => true ], 'default' => [ 'url' => '#' ], 'condition' => [ 'kind' => 'link' ] ] );
		$r->add_control( 'icon', [ 'label' => esc_html__( 'Icon', 'samanzavarian-widgets' ), 'type' => Controls_Manager::ICONS, 'skin' => 'inline', 'label_block' => false, 'description' => esc_html__( 'Empty: email, phone, Behance, LinkedIn, WhatsApp, Telegram and Instagram links get theirs by themselves.', 'samanzavarian-widgets' ), 'condition' => [ 'kind' => 'link' ] ] );
		$link = static fn( string $t, string $u, bool $out = false ) => [ 'kind' => 'link', 'text' => $t, 'link' => [ 'url' => $u, 'is_external' => $out ? 'on' : '', 'nofollow' => '' ] ];
		$this->add_control(
			'rows',
			[
				'label'       => esc_html__( 'Rows', 'samanzavarian-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'title_field' => '<# if ( kind === "head" ) { #>▸ <b>{{{ text }}}</b><# } else { #>{{{ text }}}<# } #>',
				'default'     => [
					[ 'kind' => 'head', 'text' => 'On this page' ],
					$link( 'Services', '#services' ),
					$link( 'Work', '#work' ),
					$link( 'About', '#about' ),
					$link( 'Experience', '#experience' ),
					$link( 'Contact', '#contact' ),
					[ 'kind' => 'head', 'text' => 'Elsewhere' ],
					$link( 'Behance', 'https://www.behance.net/', true ),
					$link( 'LinkedIn', 'https://www.linkedin.com/in/saman-zavarian', true ),
					$link( 'WhatsApp', 'https://wa.me/989037022029', true ),
					$link( 'Email', 'mailto:saman.zavarian@gmail.com' ),
				],
			]
		);
		$this->add_control( 'head_tag', [ 'label' => esc_html__( 'Heading tag', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SELECT, 'default' => 'h4', 'options' => [ 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'p' => 'p', 'div' => 'div' ] ] );
		$this->add_control( 'label', [ 'label' => esc_html__( 'Name for screen readers', 'samanzavarian-widgets' ), 'type' => Controls_Manager::TEXT, 'default' => 'Footer', 'ai' => [ 'active' => false ] ] );
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
		$this->end_controls_section();

		$this->start_controls_section( 'st_layout', [ 'label' => esc_html__( 'Columns', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		$this->add_responsive_control( 'col_w', [ 'label' => esc_html__( 'Column width', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'size_units' => [ 'px', '%' ], 'range' => [ 'px' => [ 'min' => 80, 'max' => 400 ] ], 'selectors' => [ '{{WRAPPER}} .sz-flinks' => '--szf-col: {{SIZE}}{{UNIT}};' ] ] );
		$this->add_responsive_control( 'col_gap', [ 'label' => esc_html__( 'Space between columns', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'range' => [ 'px' => [ 'min' => 0, 'max' => 120 ] ], 'selectors' => [ '{{WRAPPER}} .sz-flinks' => 'column-gap: {{SIZE}}px;' ] ] );
		$this->add_responsive_control( 'row_gap', [ 'label' => esc_html__( 'Space between rows of columns', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'range' => [ 'px' => [ 'min' => 0, 'max' => 80 ] ], 'selectors' => [ '{{WRAPPER}} .sz-flinks' => 'row-gap: {{SIZE}}px;' ] ] );
		$this->add_responsive_control( 'row_h', [ 'label' => esc_html__( 'Link height', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'range' => [ 'px' => [ 'min' => 24, 'max' => 72 ] ], 'selectors' => [ '{{WRAPPER}} .sz-flinks__a' => 'height: {{SIZE}}px;' ] ] );
		$this->add_control( 'line', [ 'label' => esc_html__( 'Line between links', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .sz-flinks' => '--szf-line: {{VALUE}};' ] ] );
		$this->add_control( 'accent', [ 'label' => esc_html__( 'Line under the pointer', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .sz-flinks' => '--szf-accent: {{VALUE}};' ] ] );
		$this->end_controls_section();
		Style_Controls::text( $this, 'st_head', esc_html__( 'Column headings', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-flinks .sz-flinks__h' );
		Style_Controls::text( $this, 'st_link', esc_html__( 'Links', 'samanzavarian-widgets' ), '{{WRAPPER}} .sz-flinks .sz-flinks__a', [ 'no_margin' => true, 'hover' => '{{WRAPPER}} .sz-flinks .sz-flinks__a:hover' ] );
		$this->start_controls_section( 'st_icon', [ 'label' => esc_html__( 'Icons', 'samanzavarian-widgets' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		$this->add_responsive_control( 'icon_size', [ 'label' => esc_html__( 'Size', 'samanzavarian-widgets' ), 'type' => Controls_Manager::SLIDER, 'range' => [ 'px' => [ 'min' => 8, 'max' => 40 ] ], 'selectors' => [ '{{WRAPPER}} .sz-flinks__ic' => 'width: {{SIZE}}px; height: {{SIZE}}px; font-size: {{SIZE}}px;' ] ] );
		$this->add_control( 'icon_color', [ 'label' => esc_html__( 'Colour', 'samanzavarian-widgets' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .sz-flinks__ic' => 'color: {{VALUE}}; stroke: {{VALUE}};' ] ] );
		$this->end_controls_section();
		Style_Controls::motion(
			$this,
			[
				'rise' => [ 'label' => esc_html__( 'Rows rise in when the footer comes into view', 'samanzavarian-widgets' ) ],
			]
		);
	}

	protected function render(): void {
		$s    = $this->get_settings_for_display();
		$tag  = in_array( $s['head_tag'] ?? 'h4', [ 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div' ], true ) ? $s['head_tag'] : 'h4';
		$home = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$cols = [];
		foreach ( (array) ( $s['rows'] ?? [] ) as $row ) {
			if ( 'head' === ( $row['kind'] ?? 'link' ) ) {
				$cols[] = [ 'head' => (string) ( $row['text'] ?? '' ), 'links' => [] ];
			} else {
				if ( ! $cols ) {
					$cols[] = [ 'head' => '', 'links' => [] ];
				}
				$cols[ count( $cols ) - 1 ]['links'][] = $row;
			}
		}
		if ( ! $cols ) {
			return;
		}
		$out = static fn( string $url, array $link ) => ! empty( $link['is_external'] ) || ( preg_match( '#^https?://#i', $url ) && $home !== (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$arrow = '<svg class="sz-flinks__out" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 17 17 7M9 7h8v8"/></svg>';
		$i     = 0;
		echo '<nav class="sz-flinks sz-w' . ( 'yes' === ( $s['rise'] ?? 'yes' ) ? ' has-rise' : '' ) . '" data-sz-flinks aria-label="' . esc_attr( (string) ( $s['label'] ?? 'Footer' ) ) . '">';
		foreach ( $cols as $c => $col ) {
			echo '<div class="sz-flinks__col" style="--c:' . (int) $c . '">';
			if ( '' !== trim( $col['head'] ) ) {
				printf( '<%1$s class="sz-flinks__h">%2$s</%1$s>', tag_escape( $tag ), esc_html( $col['head'] ) );
			}
			echo '<ul class="sz-flinks__list">';
			foreach ( $col['links'] as $row ) {
				$key  = 'a' . $i;
				$url  = (string) ( $row['link']['url'] ?? '' );
				$away = $out( $url, (array) ( $row['link'] ?? [] ) );
				$this->add_render_attribute( $key, [ 'class' => [ 'sz-flinks__a', $away ? 'is-out' : '' ], 'style' => '--i:' . $i ] );
				if ( '' !== $url ) {
					$this->add_link_attributes( $key, $row['link'] );
				}
				$icon = ! empty( $row['icon']['value'] ) ? Icon_Library::html( $row['icon'], [ 'class' => 'sz-flinks__ic' ] ) : '';
				if ( '' === $icon && '' !== ( $known = self::known_icon( $url ) ) ) {
					$icon = Icon_Library::svg( $known, 'szi sz-flinks__ic' );
				}
				echo '<li><a ' . $this->get_render_attribute_string( $key ) . '>' . $icon . '<span class="sz-flinks__t">' . esc_html( (string) ( $row['text'] ?? '' ) ) . '</span>' . ( $away ? $arrow : '' ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts
				++$i;
			}
			echo '</ul></div>';
		}
		echo '</nav>';
	}
}
