<?php
/**
 * Scene: Post-production — grading sliders a visitor can move, an export
 * label and a timeline.
 *
 * Printed in the design's resting state (Warmth +18, Shadows −12, playhead
 * at 46%, "Final export ready"). assets/js/scenes/post.js makes the sliders
 * drift and the timeline run, and lets visitors grade by hand — the sliders
 * are real range inputs, so keyboard and screen readers work too.
 *
 * @var array<string,mixed> $s
 */

use Samanzavarian_Widgets\Widgets\Service_Panel;

defined( 'ABSPATH' ) || exit;

$szs_adj    = Service_Panel::post_adjustments( $s );
$szs_tracks = Service_Panel::post_tracks( $s );
$szs_shot   = Service_Panel::media_html( $s['post_preview'] ?? [], 'szs-shot__media' );
$szs_dur    = isset( $s['post_duration']['size'] ) && '' !== $s['post_duration']['size'] ? (float) $s['post_duration']['size'] : 7;

$szs_config = [
	'drift'   => 'yes' === ( $s['post_drift'] ?? 'yes' ),
	'done'    => (string) ( $s['post_done'] ?? 'Final export ready' ),
	'working' => (string) ( $s['post_working'] ?? 'Exporting {p}%' ),
	'run'     => max( 2, $szs_dur ) * 1000,
];
?>
<div class="szs-grade szs-a" data-szs-post="<?php echo esc_attr( (string) wp_json_encode( $szs_config ) ); ?>">
	<?php foreach ( $szs_adj as $i => $a ) : ?>
		<?php
		$szs_id  = 'szs-' . $this->get_id() . '-adj-' . $i;
		$szs_pct = ( $a['value'] - $a['min'] ) / ( $a['max'] - $a['min'] );
		?>
		<div class="szs-grade__row"><label for="<?php echo esc_attr( $szs_id ); ?>"><?php echo esc_html( $a['label'] ); ?></label><output class="szs-grade__val" for="<?php echo esc_attr( $szs_id ); ?>"><?php echo esc_html( Service_Panel::signed( $a['value'] ) ); ?></output></div>
		<div class="szs-grade__track<?php echo $a['_id'] ? ' elementor-repeater-item-' . esc_attr( $a['_id'] ) : ''; ?>" style="--p:<?php echo esc_attr( (string) round( $szs_pct, 4 ) ); ?>;<?php echo $a['track'] ? 'background:' . esc_attr( $a['track'] ) : ''; ?>">
			<input class="szs-grade__input" id="<?php echo esc_attr( $szs_id ); ?>" type="range" min="<?php echo esc_attr( (string) $a['min'] ); ?>" max="<?php echo esc_attr( (string) $a['max'] ); ?>" step="1" value="<?php echo esc_attr( (string) $a['value'] ); ?>" data-effect="<?php echo esc_attr( $a['effect'] ); ?>" aria-valuetext="<?php echo esc_attr( Service_Panel::signed( $a['value'] ) ); ?>">
			<i class="szs-grade__knob" aria-hidden="true"></i>
		</div>
	<?php endforeach; ?>
</div>
<?php if ( '' !== $szs_shot ) : ?>
	<figure class="szs-shot szs-a" aria-hidden="true"><?php echo $szs_shot; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in media_html() ?></figure>
<?php endif; ?>
<span class="szs-pill szs-a" aria-hidden="true"><i></i><span class="szs-pill__text"><?php echo esc_html( $szs_config['done'] ); ?></span></span>
<div class="szs-tl szs-a" aria-hidden="true">
	<div class="szs-tl__top"><span><?php echo esc_html( (string) ( $s['post_from'] ?? '00:00:12' ) ); ?></span><span><?php echo esc_html( (string) ( $s['post_to'] ?? '00:00:24' ) ); ?></span></div>
	<?php foreach ( $szs_tracks as $szs_track ) : ?>
		<div class="szs-tl__row">
			<?php foreach ( $szs_track as $c ) : ?>
				<i style="flex:<?php echo (int) $c['length']; ?>;<?php echo $c['color'] ? 'background:' . esc_attr( $c['color'] ) : ''; ?>"></i>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>
	<span class="szs-tl__head"></span>
</div>
