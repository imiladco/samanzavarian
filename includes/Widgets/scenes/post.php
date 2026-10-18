<?php
/**
 * Scene: Post-production — grading sliders, an export pill and a timeline.
 * Static markup for now; a later stage makes the sliders live and the
 * timeline edit itself.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="szs-grade szs-a">
	<div class="szs-grade__row">Warmth<span>+18</span></div><div class="szs-grade__track"><i></i></div>
	<div class="szs-grade__row">Shadows<span>−12</span></div><div class="szs-grade__track szs-grade__track--2"><i></i></div>
</div>
<span class="szs-pill szs-a"><i></i>Final export ready</span>
<div class="szs-tl szs-a">
	<div class="szs-tl__top"><span>00:00:12</span><span>00:00:24</span></div>
	<div class="szs-tl__row"><i style="flex:3;background:#0D1B2A"></i><i style="flex:2;background:#2A4560"></i><i style="flex:2;background:#0D1B2A"></i></div>
	<div class="szs-tl__row"><i style="flex:2;background:#FFD3BF"></i><i style="flex:4;background:#FF6B2C"></i><i style="flex:1;background:#FFD3BF"></i></div>
	<div class="szs-tl__row"><i style="flex:5;background:#E7E9EC"></i><i style="flex:2;background:#D5D9DF"></i></div>
	<span class="szs-tl__head"></span>
</div>
