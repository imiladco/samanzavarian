<?php
namespace Samanzavarian_Widgets\Widgets\Scenes;

use Samanzavarian_Widgets\Widgets\Service_Panel;

defined( 'ABSPATH' ) || exit;

/**
 * One of the Service Panel's illustrated scenes.
 *
 * A scene owns everything about itself: its Content and Style controls, the
 * markup of its resting picture, the settings its script reads, and the notes
 * it gives the person editing. Service_Panel only picks the scene and frames it.
 *
 * Geometry: a scene is drawn on a fixed design stage (.szs-stage, 695×400 px,
 * or the scene's compact stage in narrow panels) and the panel runtime scales
 * that stage to the panel it sits in. Inside, everything is placed in design
 * pixels, so a scene looks the same in a wide tab, a narrow column or a phone.
 *
 * The markup is always the finished resting state — what a visitor sees
 * without JavaScript, with reduced motion, or with Animation set to "Still".
 */
abstract class Scene {

	/** Content-tab controls (one section, shown only for this scene). */
	abstract public static function controls( Service_Panel $w ): void;

	/** Style-tab controls (one section, shown only for this scene). */
	abstract public static function style( Service_Panel $w ): void;

	/**
	 * The stage's content, escaped.
	 *
	 * @param array<string,mixed> $s  settings for display
	 * @param string              $uid the widget's id, for unique element ids
	 */
	abstract public static function render( array $s, string $uid ): string;

	/**
	 * What the person editing should know about their input (shown only in
	 * the editor), e.g. something the scene had to work around.
	 *
	 * @param array<string,mixed> $s
	 * @return string[]
	 */
	public static function notes( array $s ): array {
		return [];
	}

	/**
	 * Whether the scene shows a video (videos move even when the scene's
	 * animation is set to "Still", so they need the pause button too).
	 *
	 * @param array<string,mixed> $s
	 */
	public static function has_video( array $s ): bool {
		return false;
	}

	/* ---------------- helpers for the scenes ---------------- */

	/**
	 * A number from a NUMBER or SLIDER control, clamped; the default when empty.
	 *
	 * @param array<string,mixed> $s
	 */
	protected static function num( array $s, string $key, float $default, float $min, float $max ): float {
		$v = $s[ $key ] ?? null;
		if ( is_array( $v ) ) {
			$v = $v['size'] ?? null;
		}

		return is_numeric( $v ) && '' !== $v ? max( $min, min( $max, (float) $v ) ) : $default;
	}

	/**
	 * A text setting, trimmed; the default when the key is missing (an empty
	 * value set on purpose stays empty).
	 *
	 * @param array<string,mixed> $s
	 */
	protected static function text( array $s, string $key, string $default ): string {
		return array_key_exists( $key, $s ) ? trim( (string) $s[ $key ] ) : $default;
	}

	/** @param array<string,mixed> $data */
	protected static function data( array $data ): string {
		return esc_attr( (string) wp_json_encode( $data ) );
	}

	/** A safe colour for a style attribute or a script, or ''. */
	protected static function color( $c ): string {
		return Service_Panel::css_color( (string) $c );
	}

	/** Whether MEDIA control values hold a video. @param array<int,mixed> $media */
	protected static function any_video( array $media ): bool {
		foreach ( $media as $m ) {
			if ( false !== strpos( Service_Panel::media_html( $m, '' ), '<video' ) ) {
				return true;
			}
		}

		return false;
	}
}
