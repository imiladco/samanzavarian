<?php
namespace Samanzavarian_Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * Registers (never enqueues) every stylesheet and script. Elementor enqueues
 * a widget's handles from get_style_depends()/get_script_depends() only on
 * pages where that widget renders, so a page without the Process widget never
 * downloads process.css. The one exception is the editor script, which only
 * the editor loads.
 */
final class Assets {

	/** The shared layer every widget depends on: tokens, fonts, icon tile. */
	public const BASE = 'sz-base';

	/**
	 * handle => path under assets/css. Each widget stylesheet depends on BASE,
	 * so declaring the widget handle alone is enough.
	 */
	private const WIDGET_STYLES = [
		'sz-process'       => 'widgets/process.css',
		'sz-service-tabs'  => 'widgets/service-tabs.css',
		'sz-service-panel' => 'widgets/service-panel.css',
	];

	/** handle => path under assets/js. Frontend scripts, loaded in the footer, no jQuery. */
	private const WIDGET_SCRIPTS = [
		'sz-service-tabs'  => 'service-tabs.js',
		'sz-service-panel' => 'service-panel.js',
	];

	public static function boot(): void {
		// `init` covers the frontend, the editor and the preview iframe alike;
		// registering is cheap and enqueueing happens later, on demand.
		add_action( 'init', [ self::class, 'register' ] );
		add_action( 'elementor/editor/before_enqueue_scripts', [ self::class, 'enqueue_editor' ] );
	}

	/**
	 * Nested widgets register their element type in the editor window, after
	 * Elementor's nested-elements module — hence that dependency.
	 */
	public static function enqueue_editor(): void {
		wp_enqueue_script( 'sz-editor-service-tabs', SZW_URL . 'assets/js/editor/service-tabs.js', [ 'nested-elements' ], self::version( 'js/editor/service-tabs.js' ), true );
		wp_add_inline_script( 'sz-editor-service-tabs', 'window.szwGlyphs=' . wp_json_encode( Icon_Library::GLYPHS ) . ';', 'before' );
	}

	public static function register(): void {
		wp_register_style( self::BASE, self::url( 'sz-base.css' ), [], self::version( 'css/sz-base.css' ) );

		foreach ( self::WIDGET_STYLES as $handle => $file ) {
			wp_register_style( $handle, self::url( $file ), [ self::BASE ], self::version( 'css/' . $file ) );
		}

		foreach ( self::WIDGET_SCRIPTS as $handle => $file ) {
			wp_register_script( $handle, SZW_URL . 'assets/js/' . $file, [], self::version( 'js/' . $file ), [ 'in_footer' => true, 'strategy' => 'defer' ] );
		}

		// One script per animated scene, on top of the panel runtime.
		foreach ( Widgets\Service_Panel::ANIMATED_SCENES as $scene ) {
			$file = 'scenes/' . $scene . '.js';
			wp_register_script( 'sz-scene-' . $scene, SZW_URL . 'assets/js/' . $file, [ 'sz-service-panel' ], self::version( 'js/' . $file ), [ 'in_footer' => true, 'strategy' => 'defer' ] );
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
		$mtime = @filemtime( SZW_PATH . 'assets/' . $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		return $mtime ? SZW_VERSION . '.' . $mtime : SZW_VERSION;
	}
}
