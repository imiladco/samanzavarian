<?php
namespace Samanzavarian_Widgets;

use Elementor\Icons_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * The design's own line icons, registered as an Elementor icon library.
 *
 * Why a library and not inline SVG strings inside each widget: these glyphs
 * appear in every section of the design. As a library they show up in
 * Elementor's icon picker under their own tab, every widget's ICONS control
 * can default to them, and — through `render_callback` — even Elementor's
 * stock widgets (Icon, Icon Box, Button…) render them correctly.
 *
 * Rendering is inline SVG with `stroke: currentColor`, never a font or a
 * CSS mask, so a single "icon color" control recolours them like any text
 * and they stay crisp at every size.
 */
final class Icon_Library {

	public const LIBRARY = 'sz-icons';
	public const PREFIX  = 'szi-';
	public const DISPLAY = 'szi';

	/**
	 * name => inner SVG markup on a 24×24 grid, stroked, never filled.
	 * Extracted verbatim from the design file's sprite. Static geometry only
	 * (path/circle/rect with numeric attributes) — no user input ever reaches
	 * this map, which is why it is printed without escaping.
	 */
	public const GLYPHS = [
		'lens'    => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="3.8"/>',
		'spark'   => '<path d="M12 3l1.9 4.6L18.5 9.5l-4.6 1.9L12 16l-1.9-4.6L5.5 9.5l4.6-1.9z"/><path d="M19 15l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8z"/>',
		'film'    => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M3 15l5-4 4 3 3-2 6 4"/>',
		'cal'     => '<rect x="3" y="4" width="18" height="16" rx="3"/><path d="M3 9h18M8 4v5M16 4v5"/>',
		'chat'    => '<path d="M4 5h16v10H8l-4 4z"/>',
		'doc'     => '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5M10 13h6M10 17h4"/>',
		'check'   => '<path d="M12 3l2.2 2.1 3-.4.6 3 2.7 1.4-1.4 2.7 1.4 2.7-2.7 1.4-.6 3-3-.4L12 21l-2.2-2.1-3 .4-.6-3-2.7-1.4L4.9 12 3.5 9.3l2.7-1.4.6-3 3 .4z"/><path d="m9 12 2 2 4-4"/>',
		'tick'    => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
		'cam'     => '<path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="3.5"/>',
		'video'   => '<rect x="3" y="6" width="13" height="12" rx="3"/><path d="M16 10l5-3v10l-5-3"/>',
		'box'     => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
		'factory' => '<path d="M3 21V10l6 3V10l6 3V6l6-3v18z"/>',
		'pen'     => '<path d="M4 20l4-1 10-10-3-3L5 16z"/><path d="M13 7l3 3"/>',
		'cap'     => '<path d="M2 9l10-5 10 5-10 5z"/><path d="M6 11v5c3 2 9 2 12 0v-5"/>',
		'mail'    => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/>',
		'in'      => '<rect x="3" y="3" width="18" height="18" rx="4"/><path d="M8 10v7M8 7v.01M12 17v-4a2 2 0 0 1 4 0v4M12 10v7"/>',
		'be'      => '<path d="M3 7h5a2.5 2.5 0 0 1 0 5H3zM3 12h5.5a2.75 2.75 0 0 1 0 5.5H3zM15 7h5M14 14h7a3.5 3.5 0 1 0-1 2.5"/>',
		'wa'      => '<path d="M4 20l1.3-4A8 8 0 1 1 8 18.7z"/><path d="M9 9.5c.5 2.5 2.5 4.5 5 5l1-1.5 2 1-.5 1.5c-3.5.5-8-4-7.5-7.5L10.5 7l1 2z"/>',
		'person'  => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		'brief'   => '<rect x="3" y="7" width="18" height="13" rx="3"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 12h18"/>',
		'palette' => '<path d="M12 3a9 9 0 1 0 0 18c1.2 0 1.8-.8 1.8-1.7 0-1.2-1-1.6-1-2.6 0-.9.7-1.6 1.7-1.6H17a4 4 0 0 0 4-4C21 6.6 17 3 12 3z"/><circle cx="7.5" cy="11" r="1"/><circle cx="10" cy="7" r="1"/><circle cx="15" cy="7" r="1"/>',
		'sliders' => '<path d="M4 7h10M18 7h2M4 17h4M12 17h8"/><circle cx="16" cy="7" r="2"/><circle cx="10" cy="17" r="2"/>',
		'globe'   => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z"/>',
		'chev'    => '<path d="m9 6 6 6-6 6"/>',
		'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'lock'    => '<rect x="5" y="11" width="14" height="10" rx="3"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
		'send'    => '<path d="M21 3 10 14M21 3l-7 18-4-7-7-4z"/>',
		'alert'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5v.01"/>',
		'up'      => '<path d="M12 19V5M6 11l6-6 6 6"/>',
	];

	public static function boot(): void {
		add_filter( 'elementor/icons_manager/additional_tabs', [ self::class, 'register_tab' ] );
	}

	/**
	 * @param  array<string,array<string,mixed>> $tabs
	 * @return array<string,array<string,mixed>>
	 */
	public static function register_tab( $tabs ): array {
		$tab = [
			'name'            => self::LIBRARY,
			'label'           => esc_html__( 'Saman Zavarian', 'samanzavarian-widgets' ),
			'prefix'          => self::PREFIX,
			'displayPrefix'   => self::DISPLAY,
			'labelIcon'       => self::DISPLAY . ' ' . self::PREFIX . 'lens',
			'ver'             => SZW_VERSION,
			'fetchJson'       => SZW_URL . 'assets/icons/sz-icons.json',
			'native'          => false,
			'render_callback' => [ self::class, 'render_callback' ],
		];

		/*
		 * The picker stylesheet only exists so the icon *picker* can draw
		 * previews. Elementor enqueues a library's `url` on the frontend for
		 * every page that uses one of its icons — but the frontend renders
		 * inline SVG through render_callback and needs no CSS. So the URL is
		 * handed over only inside the admin (the editor lives there), and the
		 * frontend pays nothing for this library.
		 */
		if ( is_admin() ) {
			$tab['url'] = SZW_URL . 'assets/icons/sz-icons-picker.css';
		}

		$tabs = is_array( $tabs ) ? $tabs : [];
		$tabs[ self::LIBRARY ] = $tab;

		return $tabs;
	}

	/** 'szi szi-chat' -> 'chat', or null for anything that is not ours. */
	public static function glyph_name( $value ): ?string {
		if ( ! is_string( $value ) || ! preg_match( '/(?:^|\s)' . preg_quote( self::PREFIX, '/' ) . '([a-z0-9-]+)/', $value, $m ) ) {
			return null;
		}

		return isset( self::GLYPHS[ $m[1] ] ) ? $m[1] : null;
	}

	/** The value Elementor stores for one of our glyphs, for control defaults. */
	public static function value( string $name ): array {
		return [
			'value'   => self::DISPLAY . ' ' . self::PREFIX . $name,
			'library' => self::LIBRARY,
		];
	}

	/**
	 * Elementor calls this for every icon of our library, from any widget.
	 *
	 * @param array<string,mixed> $icon
	 * @param array<string,mixed> $attributes
	 */
	public static function render_callback( $icon, $attributes = [], $tag = 'i' ): string {
		$name = self::glyph_name( $icon['value'] ?? '' );

		if ( null === $name ) {
			return '';
		}

		$class = self::DISPLAY . ' ' . self::PREFIX . $name;

		if ( ! empty( $attributes['class'] ) ) {
			$class .= ' ' . ( is_array( $attributes['class'] ) ? implode( ' ', $attributes['class'] ) : $attributes['class'] );
		}

		return self::svg( $name, $class );
	}

	public static function svg( string $name, string $class = '' ): string {
		if ( ! isset( self::GLYPHS[ $name ] ) ) {
			return '';
		}

		$class = '' !== $class ? $class : self::DISPLAY . ' ' . self::PREFIX . $name;

		return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
			. ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
			. self::GLYPHS[ $name ]
			. '</svg>';
	}

	/**
	 * Any ICONS-control value to markup: ours as inline SVG, everything else
	 * (Font Awesome, uploaded SVG…) through Elementor itself.
	 *
	 * @param array<string,mixed> $icon
	 * @param array<string,mixed> $attributes
	 */
	public static function html( $icon, array $attributes = [] ): string {
		if ( ! is_array( $icon ) || empty( $icon['value'] ) ) {
			return '';
		}

		if ( self::LIBRARY === ( $icon['library'] ?? '' ) ) {
			return self::render_callback( $icon, $attributes );
		}

		if ( method_exists( Icons_Manager::class, 'try_get_icon_html' ) ) {
			return (string) Icons_Manager::try_get_icon_html( $icon, $attributes );
		}

		ob_start();
		Icons_Manager::render_icon( $icon, $attributes );

		return (string) ob_get_clean();
	}
}
