<?php
namespace Samanzavarian_Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * Icon words inside Elementor's own Text Editor and Heading widgets (and
 * Elementor 4's Paragraph and Heading) — the
 * About statement's "photography", "products"… pills — with no widget of
 * our own: the text stays an ordinary paragraph, written like this:
 *
 *   I trained in {cam:photography} and today most of my work is {spark*:AI image and video}.
 *
 *   {icon:words}          a pill with the icon's navy tile
 *   {icon*:words}         …with an orange tile
 *   {icon:words|#link}    …that links (e.g. #work-photo opens that filter)
 *
 * Only known icon names are turned (anything else in braces stays text).
 * The pills are made on the server, so they are plain HTML: indexable,
 * nothing shifts, no script needed to show them. The CSS (and the small
 * script that lights them up in reading order as the paragraph comes into
 * view) rides along with Elementor's own frontend files on Elementor pages.
 * The editor preview turns them live as you type, and the Text Editor and
 * Heading panels list every icon by name.
 *
 * "sz-statement" in Advanced → CSS Classes gives the design's statement
 * type (22px, line height 1.8, smaller on phones).
 */
final class Inline_Icons {

	/** {icon(*):words(|link)} — the words never contain braces, pipes or tags. */
	public const PATTERN = '/\{([a-z]+)(\*?):([^{}|<>]+?)(?:\|([^{}<>\s]+))?\}([.,;:!?…]*)/u';

	/** Elementor's Text Editor and Heading, and v4's Paragraph and Heading. */
	private const WIDGETS = [ 'text-editor', 'heading', 'e-paragraph', 'e-heading' ];

	public static function boot(): void {
		add_filter( 'elementor/widget/render_content', [ self::class, 'render' ], 10, 2 );
		add_action( 'elementor/frontend/after_enqueue_styles', [ self::class, 'styles' ] );
		add_action( 'elementor/frontend/after_enqueue_scripts', [ self::class, 'scripts' ] );
		add_action( 'elementor/preview/enqueue_scripts', [ self::class, 'preview' ] );
		add_action( 'elementor/element/text-editor/section_editor/before_section_end', [ self::class, 'help' ] );
		add_action( 'elementor/element/heading/section_title/before_section_end', [ self::class, 'help' ] );
	}

	/* ---------------- turning the codes into pills ---------------- */

	/**
	 * @param string                 $content
	 * @param \Elementor\Widget_Base $widget
	 */
	public static function render( $content, $widget ): string {
		$content = (string) $content;

		if ( false === strpos( $content, '{' ) || ! in_array( $widget->get_name(), self::WIDGETS, true ) ) {
			return $content;
		}

		return self::convert( $content );
	}

	/** Every {icon:words} with a known icon, as a pill; the rest untouched. */
	public static function convert( string $html ): string {
		$i = 0;

		return (string) preg_replace_callback(
			self::PATTERN,
			static function ( $m ) use ( &$i ) {
				if ( ! isset( Icon_Library::GLYPHS[ $m[1] ] ) ) {
					return $m[0];
				}

				$pill = self::pill( $m[1], '*' === $m[2], html_entity_decode( trim( $m[3] ), ENT_QUOTES ), $m[4] ?? '', $i++ );

				// punctuation right after a pill is drawn into its padding, so "video." does not read "video ."
				return '' !== ( $m[5] ?? '' ) ? $pill . '<span class="sz-tok__p">' . $m[5] . '</span>' : $pill;
			},
			$html
		);
	}

	/**
	 * The icon is glued to the first word (it never ends a line alone);
	 * the rest may wrap, and the pill's shape continues on the next line.
	 */
	public static function pill( string $icon, bool $orange, string $words, string $link, int $i ): string {
		$parts = preg_split( '/\s+/u', $words, 2 );
		$first = (string) ( $parts[0] ?? '' );
		$rest  = isset( $parts[1] ) ? ' ' . $parts[1] : '';
		$tile  = '<span class="sz-tok__tile" aria-hidden="true">' . Icon_Library::svg( $icon, 'sz-tok__svg' ) . '</span>';
		$body  = '<span class="sz-tok__lead">' . $tile . esc_html( $first ) . '</span>' . esc_html( $rest );
		$class = 'sz-tok' . ( $orange ? ' is-o' : '' );
		$style = '--i:' . $i;

		if ( '' !== $link ) {
			$url = esc_url( $link );
			if ( '' !== $url ) {
				return '<a class="' . $class . ' is-link" href="' . $url . '" style="' . $style . '">' . $body . '</a>';
			}
		}

		return '<span class="' . $class . '" style="' . $style . '">' . $body . '</span>';
	}

	/* ---------------- the pills' look and arrival, on Elementor pages ---------------- */

	public static function styles(): void {
		$css = @file_get_contents( SZW_PATH . 'assets/css/tokens.css' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file

		if ( $css ) {
			wp_add_inline_style( 'elementor-frontend', $css );
		}
	}

	public static function scripts(): void {
		$js = @file_get_contents( SZW_PATH . 'assets/js/tokens.js' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file

		if ( $js ) {
			wp_add_inline_script( 'elementor-frontend', $js );
		}
	}

	/** In the editor's preview, codes become pills while you type. */
	public static function preview(): void {
		wp_enqueue_script( 'sz-tokens-preview', SZW_URL . 'assets/js/editor/tokens-preview.js', [], SZW_VERSION, true );
		wp_add_inline_script( 'sz-tokens-preview', 'window.szwGlyphs=' . wp_json_encode( Icon_Library::GLYPHS ) . ';', 'before' );
	}

	/* ---------------- the guide in the panel ---------------- */

	/** @param \Elementor\Controls_Stack $element */
	public static function help( $element ): void {
		$tiles = '';

		foreach ( array_keys( Icon_Library::GLYPHS ) as $name ) {
			$tiles .= '<span title="{' . esc_attr( $name ) . ':…}" style="display:inline-flex;align-items:center;gap:5px;margin:0 8px 6px 0;font-family:monospace;font-size:11px">'
				. '<span style="display:inline-grid;place-items:center;width:20px;height:20px;border-radius:6px;background:#0D1B2A;color:#FF6B2C">' . Icon_Library::svg( $name, '' ) . '</span>' . esc_html( $name ) . '</span>';
		}

		$element->add_control(
			'sz_tokens_help',
			[
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'separator'       => 'before',
				'content_classes' => 'elementor-descriptor',
				'raw'             => '<details><summary style="cursor:pointer;font-weight:600">' . esc_html__( 'Words with icons', 'samanzavarian-widgets' ) . '</summary>'
					. '<p style="margin:8px 0">' . wp_kses( __( 'Write <code>{cam:photography}</code> for a pill with an icon, <code>{spark*:AI video}</code> for an orange tile, <code>{cam:photography|#work-photo}</code> to make it a link. For the design\'s statement type, add <code>sz-statement</code> under Advanced → CSS Classes.', 'samanzavarian-widgets' ), [ 'code' => [] ] ) . '</p>'
					. '<style>.sz-tokhelp svg{width:12px;height:12px}</style><div class="sz-tokhelp" style="line-height:1">' . $tiles . '</div></details>',
			]
		);
	}
}
