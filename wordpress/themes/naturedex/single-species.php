<?php
/**
 * A species' own address, used for sharing and by visitors without JavaScript.
 *
 * @package Naturedex
 */

defined( 'ABSPATH' ) || exit;

get_header();
$naturedex_entry = naturedex_find_species( (string) get_post_field( 'post_name' ) );
?>
<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="text-link">← Back to the field guide</a>
<?php if ( $naturedex_entry ) : ?>
	<div class="species-page panel">
		<?php get_template_part( 'template-parts/species-detail', null, array( 'species' => $naturedex_entry, 'heading' => 'h2' ) ); ?>
	</div>
<?php endif; ?>
<?php
get_footer();
