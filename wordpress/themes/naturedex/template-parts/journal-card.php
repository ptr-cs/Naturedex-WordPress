<?php
/**
 * A field note card, for use inside the loop.
 *
 * @package Naturedex
 * @var array $args { compact?: bool }
 */

defined( 'ABSPATH' ) || exit;

$naturedex_art        = naturedex_post_art( get_the_ID() );
$naturedex_categories = get_the_category();
$naturedex_compact    = ! empty( $args['compact'] );
?>
<article class="journal-card<?php echo $naturedex_compact ? ' journal-compact' : ''; ?>">
	<a class="journal-cover" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<span class="journal-cover-grid"></span>
		<?php
		if ( $naturedex_art ) {
			naturedex_sprite( $naturedex_art, 120 );
		}
		?>
		<span class="cover-stamp">FIELD<br>NOTES</span>
	</a>
	<div class="journal-card-copy">
		<span class="eyebrow"><?php echo esc_html( $naturedex_categories ? $naturedex_categories[0]->name : 'FIELD NOTES' ); ?></span>
		<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		<p><?php echo esc_html( get_the_excerpt() ); ?></p>
		<a class="text-link" href="<?php the_permalink(); ?>">Read field note <?php naturedex_icon( 'arrow', 15 ); ?></a>
	</div>
</article>
