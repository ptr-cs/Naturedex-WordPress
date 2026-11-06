<?php
/**
 * Page footer and closing markup.
 *
 * @package Naturedex
 */

defined( 'ABSPATH' ) || exit;
?>
			<footer class="page-footer">
				<span>NATUREDEX <span class="footer-star">✦</span> VIRGINIA EDITION</span>
				<span>Take only memories. Leave only footprints.</span>
			</footer>
		</main>
	</div>
</div>
<template data-dialog-close-icon><?php naturedex_icon( 'close' ); ?></template>
<template data-seen-icon-eye><?php naturedex_icon( 'eye', 17 ); ?></template>
<template data-seen-icon-check><?php naturedex_icon( 'check', 17 ); ?></template>
<template data-seen-card-check><?php naturedex_icon( 'check', 13 ); ?></template>
<?php naturedex_print_data(); ?>
<?php wp_footer(); ?>
</body>
</html>
