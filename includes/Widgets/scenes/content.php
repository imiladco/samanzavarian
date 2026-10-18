<?php
/**
 * Scene: Content systems — a week's content plan and a notification.
 *
 * Printed as a finished week (the design: Week 42, Wednesday highlighted,
 * four posts). In "real week" mode the server's best guess of the week and
 * today is printed and the script corrects it to the visitor's own date.
 * assets/js/scenes/content.js fills the week post by post, announces it,
 * and turns the page to the next planned week.
 *
 * @var array<string,mixed> $s
 */

use Samanzavarian_Widgets\Icon_Library;
use Samanzavarian_Widgets\Widgets\Service_Panel;

defined( 'ABSPATH' ) || exit;

$szs_plan  = Service_Panel::content_plan( $s );
$szs_week  = $szs_plan['weeks'][0];
$szs_count = array_sum( array_map( 'count', $szs_week ) );
$szs_label = (string) ( $s['content_week_label'] ?? 'Week {n}' );
$szs_text  = (string) ( $s['content_done_text'] ?? '{count} posts scheduled' );
$szs_icon  = Icon_Library::html( $s['content_icon'] ?? [] );
$szs_drop  = isset( $s['content_drop']['size'] ) && '' !== $s['content_drop']['size'] ? (float) $s['content_drop']['size'] : 420;
$szs_hold  = isset( $s['content_hold']['size'] ) && '' !== $s['content_hold']['size'] ? (float) $s['content_hold']['size'] : 3.5;

$szs_config = [
	'live'  => $szs_plan['live'],
	'week'  => $szs_plan['week'],
	'today' => $szs_plan['today'],
	'types' => $szs_plan['types'],
	'weeks' => $szs_plan['weeks'],
	'label' => $szs_label,
	'text'  => $szs_text,
	'drop'  => max( 100, $szs_drop ),
	'hold'  => max( 1, $szs_hold ) * 1000,
];
?>
<div class="szs-notif szs-a"><?php if ( '' !== $szs_icon ) : ?><span class="sz-app sz-app--orange"><?php echo $szs_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon renderer ?></span><?php endif; ?><span><b><?php echo esc_html( (string) ( $s['content_done_title'] ?? 'This week is planned' ) ); ?></b><span class="szs-notif__text"><?php echo esc_html( str_replace( '{count}', (string) $szs_count, $szs_text ) ); ?></span></span></div>
<div class="szs-week szs-a" data-szs-content="<?php echo esc_attr( (string) wp_json_encode( $szs_config ) ); ?>">
	<div class="szs-week__head"><?php echo esc_html( (string) ( $s['content_title'] ?? 'Content plan' ) ); ?><span class="szs-week__no"><?php echo esc_html( str_replace( '{n}', (string) $szs_plan['week'], $szs_label ) ); ?></span></div>
	<div class="szs-week__days">
		<?php foreach ( $szs_plan['days'] as $d => $szs_name ) : ?>
			<div class="szs-day<?php echo $d + 1 === $szs_plan['today'] ? ' is-today' : ''; ?>"><span class="szs-day__name"><?php echo esc_html( $szs_name ); ?></span><?php foreach ( $szs_week[ $d ] as $szs_post ) : ?><?php $szs_type = is_int( $szs_post ) ? $szs_plan['types'][ $szs_post ] : [ 'name' => $szs_post['name'], 'bg' => '', 'fg' => '', 'line' => '' ]; ?><em class="szs-post" style="<?php echo esc_attr( Service_Panel::content_post_style( $szs_type ) ); ?>"><?php echo esc_html( $szs_type['name'] ); ?></em><?php endforeach; ?></div>
		<?php endforeach; ?>
	</div>
</div>
