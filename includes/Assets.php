<?php
namespace Samanzavarian_Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * Registers (never enqueues) every stylesheet. Elementor enqueues a widget's
 * handles from get_style_depends() only on pages where that widget renders,
 * so a page without the Process widget never downloads process.css.
 */
final class Assets {

	/** The shared layer every widget depends on: tokens, fonts, icon tile. */
	public const BASE = 'sz-base';

	/**
	 * handle => path under assets/css. Each widget stylesheet depends on BASE,
	 * so declaring the widget handle alone is enough.
	 */
	private const WIDGET_STYLES = [
		'sz-process' => 'widgets/process.css',
	];

	public static function boot(): void {
		// `init` covers the frontend, the editor and the preview iframe alike;
		// registering is cheap and enqueueing happens later, on demand.
		add_action( 'init', [ self::class, 'register' ] );
	}

	public static function register(): void {
		wp_register_style( self::BASE, self::url( 'sz-base.css' ), [], self::version( 'sz-base.css' ) );

		foreach ( self::WIDGET_STYLES as $handle => $file ) {
			wp_register_style( $handle, self::url( $file ), [ self::BASE ], self::version( $file ) );
		}
	}

	private static function url( string $file ): string {
		return SZW_URL . 'assets/css/' . $file;
	}

	/**
	 * Plugin version plus file mtime. A stylesheet edited without a version
	 * bump still gets a new URL, so page caches and CDNs can't keep serving
	 * stale CSS against new markup — the failure mode that cost a full
	 * debugging round on the previous project.
	 */
	private static function version( string $file ): string {
		$mtime = @filemtime( SZW_PATH . 'assets/css/' . $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		return $mtime ? SZW_VERSION . '.' . $mtime : SZW_VERSION;
	}
}
