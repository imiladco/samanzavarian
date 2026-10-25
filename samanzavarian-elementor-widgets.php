<?php
/**
 * Plugin Name:       Saman Zavarian — Elementor Widgets
 * Plugin URI:        https://samanzavarian.com/
 * Description:       Custom Elementor widgets for samanzavarian.com, built from the site's design system.
 * Version:           0.50.1
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            imiladco
 * Text Domain:       samanzavarian-widgets
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 *
 * Elementor tested up to:     4.3.5
 * Elementor requires at least: 3.20.0
 *
 * @package Samanzavarian_Widgets
 */

defined( 'ABSPATH' ) || exit;

define( 'SZW_VERSION', '0.50.1' );
define( 'SZW_FILE', __FILE__ );
define( 'SZW_PATH', plugin_dir_path( __FILE__ ) );
define( 'SZW_URL', plugin_dir_url( __FILE__ ) );
define( 'SZW_MIN_PHP', '7.4' );
define( 'SZW_MIN_ELEMENTOR', '3.20.0' );

/*
 * PSR-4 for this plugin's own namespace only. No Composer: the plugin is
 * installed by uploading a folder to shared hosting, so it must not depend on
 * a `composer install` step.
 *
 *   Samanzavarian_Widgets\Widgets\Process  ->  includes/Widgets/Process.php
 */
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'Samanzavarian_Widgets\\';

		if ( 0 !== strncmp( $class, $prefix, strlen( $prefix ) ) ) {
			return;
		}

		$file = SZW_PATH . 'includes/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';

		if ( is_file( $file ) ) {
			require $file;
		}
	}
);

/*
 * Boot after every plugin has loaded, so Elementor's presence and version can
 * be checked reliably regardless of plugin load order.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		load_plugin_textdomain( 'samanzavarian-widgets', false, dirname( plugin_basename( SZW_FILE ) ) . '/languages' );

		\Samanzavarian_Widgets\Plugin::instance();
	}
);
