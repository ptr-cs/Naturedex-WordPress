<?php
/**
 * A field note.
 *
 * @package Naturedex
 */

defined( 'ABSPATH' ) || exit;

use Naturedex\Records;

get_header();
while ( have_posts() ) :
	the_post();
	$naturedex_categories = get_the_category();
	$naturedex_related    = array_filter(
		array_map(
			static fn( $post ) => naturedex_find_species( $post->post_name ),
			class_exists( Records::class ) ? Records::related_species( get_the_ID() ) : array()
		)
	);
	?>
	<article class="full-article">
		<a href="<?php echo esc_url( home_url( '/journal' ) ); ?>" class="text-link">← All field notes</a>
		<div class="article-meta">
			<?php if ( $naturedex_categories ) : ?>
				<span class="group-badge"><?php echo esc_html( $naturedex_categories[0]->name ); ?></span>
			<?php endif; ?>
			<span><?php the_author(); ?></span>
			<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
		</div>
		<?php if ( has_excerpt() ) : ?>
			<p class="article-lede"><?php echo esc_html( get_the_excerpt() ); ?></p>
		<?php endif; ?>
		<div class="article-body"><?php the_content(); ?></div>
		<?php if ( $naturedex_related ) : ?>
			<section class="article-related">
				<h2>Meet the neighbors in this story</h2>
				<div class="species-grid">
					<?php
					foreach ( $naturedex_related as $naturedex_species_entry ) {
						get_template_part( 'template-parts/species-card', null, array( 'species' => $naturedex_species_entry ) );
					}
					?>
				</div>
				<?php get_template_part( 'template-parts/species-templates', null, array( 'species' => $naturedex_related ) ); ?>
			</section>
		<?php endif; ?>
	</article>
	<?php
endwhile;
get_footer();
