<?php
/**
 * The About page and any other page written in WordPress.
 *
 * @package Naturedex
 */

defined( 'ABSPATH' ) || exit;

get_header();
$naturedex_deer = 'about' === naturedex_view() ? naturedex_find_species( 'white-tailed-deer' ) : null;
while ( have_posts() ) :
	the_post();
	?>
	<div class="about-content panel">
		<?php
		if ( $naturedex_deer ) {
			naturedex_sprite( $naturedex_deer, 150, 'Original pixel illustration of a white-tailed deer' );
		}
		the_content();
		?>
	</div>
	<?php
endwhile;
get_footer();
