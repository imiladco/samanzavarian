<?php
/**
 * Scene: AI production — a prompt sheet with generated frames, one approved.
 * Static markup for now; stage 2 makes it editable and animated.
 *
 * @var Samanzavarian_Widgets\Widgets\Service_Panel $this
 */

use Samanzavarian_Widgets\Icon_Library;

defined( 'ABSPATH' ) || exit;

$szs_twinkle = '<path d="M12 2c.6 4.8 2.2 6.9 10 10-7.8 3.1-9.4 5.2-10 10-.6-4.8-2.2-6.9-10-10 7.8-3.1 9.4-5.2 10-10z"/>';
?>
<div class="szs-gen szs-a">
	<span class="szs-gen__badge sz-app sz-app--orange"><?php echo Icon_Library::svg( 'spark' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
	<svg class="szs-twk szs-twk--1" viewBox="0 0 24 24"><?php echo $szs_twinkle; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></svg>
	<svg class="szs-twk szs-twk--2" viewBox="0 0 24 24"><?php echo $szs_twinkle; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></svg>
	<div class="szs-gen__sheet">
		<div class="szs-gen__prompt"><span>Perfume bottle on wet stone, soft rim light, 85mm</span><b>Generate</b></div>
		<div class="szs-gen__grid"><i class="szs-mesh szs-mesh--2"></i><i class="szs-mesh szs-mesh--1 is-picked"></i><i class="szs-mesh szs-mesh--3"></i></div>
	</div>
</div>
<span class="szs-picked szs-a"><i><?php echo Icon_Library::svg( 'tick' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></i>Variation 2 approved</span>
