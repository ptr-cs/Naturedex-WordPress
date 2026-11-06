<?php
/**
 * Not found.
 *
 * @package Naturedex
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<?php if ( naturedex_is_missing_article() ) : ?>
	<p class="error-message" role="alert">This field note is unavailable or has not been published.</p>
	<a href="<?php echo esc_url( home_url( '/journal' ) ); ?>" class="text-link">← All field notes</a>
<?php else : ?>
	<div class="empty-state">
		<h2>This trail doesn’t lead anywhere.</h2>
		<a class="primary-button" href="<?php echo esc_url( home_url( '/' ) ); ?>">Return to the field guide</a>
	</div>
<?php endif; ?>
<?php
get_footer();
