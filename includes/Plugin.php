<?php
namespace Samanzavarian_Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * Wiring only: requirement checks, then hooks. No widget knows about this
 * class, and adding a widget means one line in WIDGETS.
 */
final class Plugin {

	/** Every widget class this plugin registers, in panel order. */
	private const WIDGETS = [
		Widgets\Process::class,
		Widgets\Service_Tabs::class,
		Widgets\Service_Panel::class,
	];

	public const CATEGORY = 'samanzavarian';

	private static ?Plugin $instance = null;

	/** @var string[] Human-readable reasons the plugin refused to load. */
	private array $problems = [];

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		if ( ! $this->meets_requirements() ) {
			add_action( 'admin_notices', [ $this, 'print_requirement_notice' ] );

			return;
		}

		Assets::boot();
		Icon_Library::boot();

		add_action( 'elementor/elements/categories_registered', [ $this, 'register_category' ] );
		add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
	}

	private function meets_requirements(): bool {
		if ( version_compare( PHP_VERSION, SZW_MIN_PHP, '<' ) ) {
			/* translators: %s: required PHP version */
			$this->problems[] = sprintf( __( 'PHP %s or newer is required.', 'samanzavarian-widgets' ), SZW_MIN_PHP );
		}

		if ( ! did_action( 'elementor/loaded' ) ) {
			$this->problems[] = __( 'Elementor must be installed and active.', 'samanzavarian-widgets' );
		} elseif ( ! defined( 'ELEMENTOR_VERSION' ) || version_compare( ELEMENTOR_VERSION, SZW_MIN_ELEMENTOR, '<' ) ) {
			/* translators: %s: required Elementor version */
			$this->problems[] = sprintf( __( 'Elementor %s or newer is required.', 'samanzavarian-widgets' ), SZW_MIN_ELEMENTOR );
		}

		return ! $this->problems;
	}

	public function print_requirement_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html__( 'Saman Zavarian — Elementor Widgets is inactive.', 'samanzavarian-widgets' ),
			esc_html( implode( ' ', $this->problems ) )
		);
	}

	/** @param \Elementor\Elements_Manager $elements_manager */
	public function register_category( $elements_manager ): void {
		$elements_manager->add_category(
			self::CATEGORY,
			[
				'title' => esc_html__( 'Saman Zavarian', 'samanzavarian-widgets' ),
				'icon'  => 'eicon-favorite',
			]
		);
	}

	/** @param \Elementor\Widgets_Manager $widgets_manager */
	public function register_widgets( $widgets_manager ): void {
		foreach ( self::WIDGETS as $class ) {
			// A nested widget needs Elementor's container support; skip it, not the plugin.
			if ( method_exists( $class, 'is_supported' ) && ! $class::is_supported() ) {
				continue;
			}

			$widgets_manager->register( new $class() );
		}
	}
}
