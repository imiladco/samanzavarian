<?php
/**
 * Scene: Creative direction — "from chaos to direction".
 *
 * Printed as the first board finished: palette, three chosen references in
 * the design's composition, and the direction note (the design exactly).
 * assets/js/scenes/direction.js plays each board: references land on the
 * board, three are chosen, the rest are cleared, the chosen ones are
 * composed, the palette is pulled from them and the note is written.
 *
 * @var array<string,mixed> $s
 */

use Samanzavarian_Widgets\Widgets\Service_Panel;

defined( 'ABSPATH' ) || exit;

$szs_boards = Service_Panel::dir_boards( $s );
$szs_board  = $szs_boards[0];
$szs_hold   = isset( $s['dir_hold']['size'] ) && '' !== $s['dir_hold']['size'] ? (float) $s['dir_hold']['size'] : 4;

$szs_config = [
	'boards' => $szs_boards,
	'hold'   => max( 1, $szs_hold ) * 1000,
];
?>
<div class="szs-swatch szs-a" data-szs-direction="<?php echo esc_attr( (string) wp_json_encode( $szs_config ) ); ?>"><?php foreach ( $szs_board['palette'] as $szs_c ) : ?><b style="background:<?php echo esc_attr( $szs_c ); ?>"></b><?php endforeach; ?></div>
<?php foreach ( [ 'a', 'b', 'c' ] as $k => $szs_slot ) : ?><i class="szs-mesh szs-mesh--<?php echo esc_attr( $szs_board['picks'][ $k ]['mesh'] ); ?> szs-mood szs-mood--<?php echo esc_attr( $szs_slot ); ?> szs-a"><?php echo $szs_board['picks'][ $k ]['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in media_html() ?></i><?php endforeach; ?>
<p class="szs-note szs-a"><b><?php echo esc_html( (string) ( $s['dir_title'] ?? 'Direction note' ) ); ?></b><span class="szs-note__text"><?php echo esc_html( $szs_board['note'] ); ?></span></p>
