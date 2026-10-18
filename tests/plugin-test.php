<?php
/**
 * Plugin-wide checks: version bookkeeping and generated files.
 *
 *   wp eval-file tests/plugin-test.php
 */

require_once __DIR__ . '/lib.php';

szw_group( 'Version' );

$root   = dirname( __DIR__ );
$header = get_file_data( $root . '/samanzavarian-elementor-widgets.php', [ 'Version' => 'Version' ] )['Version'];
preg_match( '/^## (\S+)/m', (string) file_get_contents( $root . '/CHANGELOG.md' ), $latest );
szw_ok( SZW_VERSION === $header, "plugin header Version ({$header}) = SZW_VERSION (" . SZW_VERSION . ')' );
szw_ok( ( $latest[1] ?? '' ) === SZW_VERSION, 'CHANGELOG.md has an entry for ' . SZW_VERSION . ' at the top' );

/* ------------------------------------------------------------------ */

szw_group( 'Generated files are in sync with their source' );

$root = dirname( __DIR__ );

foreach ( glob( $root . '/tools/build-*.php' ) as $tool ) {
	$tool = 'tools/' . basename( $tool );
	exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $root . '/' . $tool ) . ' --check 2>&1', $lines, $code );
	szw_ok( 0 === $code, $tool . ' --check' );
	$lines = [];
}

/* ------------------------------------------------------------------ */

szw_group( 'After an update, Elementor\'s output from the old version is dropped' );

$post = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'szw flush' ] );
update_post_meta( $post, '_elementor_element_cache', [ 'timeout' => time() + 3600, 'value' => 'old html' ] );
update_option( \Samanzavarian_Widgets\Plugin::VERSION_OPTION, '0.0.1' );
\Samanzavarian_Widgets\Plugin::instance()->flush_after_update();
szw_ok( '' === get_post_meta( $post, '_elementor_element_cache', true ), 'cached widget HTML is cleared when the version changes' );
szw_ok( SZW_VERSION === get_option( \Samanzavarian_Widgets\Plugin::VERSION_OPTION ), 'and the new version is recorded' );
update_post_meta( $post, '_elementor_element_cache', [ 'timeout' => time() + 3600, 'value' => 'new html' ] );
\Samanzavarian_Widgets\Plugin::instance()->flush_after_update();
szw_ok( '' !== get_post_meta( $post, '_elementor_element_cache', true ), 'same version: nothing is cleared (no flush on every request)' );
wp_delete_post( $post, true );

/* ------------------------------------------------------------------ */

szw_group( 'The editor can load every widget (the path that broke a live site in 0.13.0)' );

$wrong = [];
$catch = static function ( $fn, $message ) use ( &$wrong ) {
	$wrong[] = $fn . ': ' . wp_strip_all_tags( (string) $message );
};
add_action( 'doing_it_wrong_run', $catch, 10, 2 );

foreach ( Elementor\Plugin::$instance->widgets_manager->get_widget_types() as $name => $type ) {
	if ( 0 !== strpos( $name, 'sz-' ) ) {
		continue;
	}
	$class = get_class( $type );
	$fresh = new $class(); // a bare type, as Elementor builds the editor's config from it
	// drop the cached controls, so they are registered again here, in front of the catcher
	Elementor\Plugin::$instance->controls_manager->delete_stack( $fresh );
	try {
		$fresh->get_controls();
		$fresh->get_config();
		szw_ok( true, $name . ': editor config builds' );
	} catch ( \Throwable $e ) {
		szw_ok( false, $name . ': editor config builds — ' . $e->getMessage() );
	}
}

remove_action( 'doing_it_wrong_run', $catch, 10 );
$mine = array_filter( $wrong, static fn( $w ) => false !== strpos( $w, 'redeclare' ) || false !== strpos( $w, 'sz' ) );
szw_ok( ! $mine, 'no control is declared twice' . ( $mine ? ': ' . implode( ' | ', array_unique( $mine ) ) : '' ) );

szw_finish();
