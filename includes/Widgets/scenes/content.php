<?php
/**
 * Scene: Content systems — a week's content plan and a notification.
 * Static markup for now; a later stage makes the calendar fill and turn.
 */

use Samanzavarian_Widgets\Icon_Library;

defined( 'ABSPATH' ) || exit;
?>
<div class="szs-notif szs-a"><span class="sz-app sz-app--orange"><?php echo Icon_Library::svg( 'cal' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><span><b>This week is planned</b>4 posts scheduled</span></div>
<div class="szs-week szs-a">
	<div class="szs-week__head">Content plan<span>Week 42</span></div>
	<div class="szs-week__days">
		<div class="szs-day">Mon<em class="szs-post szs-post--reel">Reel</em></div>
		<div class="szs-day">Tue</div>
		<div class="szs-day is-today">Wed<em class="szs-post szs-post--carousel">Carousel</em></div>
		<div class="szs-day">Thu<em class="szs-post szs-post--story">Story</em></div>
		<div class="szs-day">Fri</div>
		<div class="szs-day">Sat<em class="szs-post szs-post--reel">Reel</em></div>
		<div class="szs-day">Sun</div>
	</div>
</div>
