<?php
/**
 * Scene: AI production — a prompt sheet whose frames are generated in rounds.
 *
 * The markup is the finished state of the first round (prompt typed, frames
 * shown, one approved): what visitors without JavaScript or with reduced
 * motion see, and exactly the design. assets/js/scenes/ai.js plays it.
 *
 * In scope from Service_Panel::render_visual(): $s (settings), $this.
 *
 * @var array<string,mixed> $s
 */

use Samanzavarian_Widgets\Icon_Library;
use Samanzavarian_Widgets\Widgets\Service_Panel;

defined( 'ABSPATH' ) || exit;

$szs_rounds  = Service_Panel::ai_rounds( $s );
$szs_prompts = Service_Panel::ai_prompts( $s );
$szs_pick    = max( 1, min( 3, (int) ( $s['ai_pick'] ?? 2 ) ) );
$szs_label   = (string) ( $s['ai_approved'] ?? 'Variation {n} approved' );
$szs_speed   = isset( $s['ai_type_speed']['size'] ) && '' !== $s['ai_type_speed']['size'] ? (float) $s['ai_type_speed']['size'] : 38;
$szs_hold    = isset( $s['ai_hold']['size'] ) && '' !== $s['ai_hold']['size'] ? (float) $s['ai_hold']['size'] : 4;
$szs_badge   = Icon_Library::html( $s['ai_badge'] ?? [] );
$szs_twinkle = '<path d="M12 2c.6 4.8 2.2 6.9 10 10-7.8 3.1-9.4 5.2-10 10-.6-4.8-2.2-6.9-10-10 7.8-3.1 9.4-5.2 10-10z"/>';

$szs_config = [
	'prompts' => $szs_prompts,
	'pick'    => $szs_pick,
	'label'   => $szs_label,
	'speed'   => max( 0, $szs_speed ),
	'hold'    => max( 1, $szs_hold ) * 1000,
];
?>
<div class="szs-gen szs-a" data-szs-ai="<?php echo esc_attr( (string) wp_json_encode( $szs_config ) ); ?>">
	<?php if ( '' !== $szs_badge ) : ?>
		<span class="szs-gen__badge sz-app sz-app--orange"><?php echo $szs_badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon renderer ?></span>
	<?php endif; ?>
	<svg class="szs-twk szs-twk--1" viewBox="0 0 24 24"><?php echo $szs_twinkle; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static path ?></svg>
	<svg class="szs-twk szs-twk--2" viewBox="0 0 24 24"><?php echo $szs_twinkle; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static path ?></svg>
	<div class="szs-gen__sheet">
		<div class="szs-gen__prompt"><span class="szs-gen__text"><?php echo esc_html( $szs_prompts[0] ); ?></span><b class="szs-gen__go"><?php echo esc_html( (string) ( $s['ai_button'] ?? 'Generate' ) ); ?></b></div>
		<div class="szs-gen__rounds">
			<?php foreach ( $szs_rounds as $r => $szs_round ) : ?>
				<div class="szs-gen__grid<?php echo 0 === $r ? ' is-current' : ' szs-off'; ?>">
					<?php foreach ( $szs_round as $k => $szs_frame ) : ?>
						<?php $szs_mesh = in_array( (string) ( $szs_frame['mesh'] ?? '' ), [ '1', '2', '3', '4', '5', '6' ], true ) ? $szs_frame['mesh'] : '1'; ?>
						<i class="szs-gen__frame szs-mesh szs-mesh--<?php echo esc_attr( $szs_mesh ); ?><?php echo 0 === $r && $k + 1 === $szs_pick ? ' is-picked' : ''; ?>"><?php echo Service_Panel::media_html( $szs_frame['media'] ?? [], 'szs-gen__media' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in media_html() ?></i>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
<span class="szs-picked szs-a"><i><?php echo Icon_Library::svg( 'tick' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static glyph ?></i><span class="szs-picked__text"><?php echo esc_html( str_replace( '{n}', (string) $szs_pick, $szs_label ) ); ?></span></span>
