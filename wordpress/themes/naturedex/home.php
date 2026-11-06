<?php
/**
 * The field journal, including category, tag, and date listings.
 *
 * @package Naturedex
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="journal-intro">
	<p>A journal for the things you almost walked past.</p>
	<a href="<?php echo esc_url( home_url( '/editor' ) ); ?>" class="secondary-button"><?php naturedex_icon( 'pen', 16 ); ?> Write a field note</a>
</div>
<?php if ( have_posts() ) : ?>
	<div class="journal-grid">
		<?php
		while ( have_posts() ) {
			the_post();
			get_template_part( 'template-parts/journal-card' );
		}
		?>
	</div>
	<?php
	the_posts_pagination(
		array(
			'prev_text'          => 'Newer notes',
			'next_text'          => 'Older notes',
			'screen_reader_text' => 'Field journal pages',
			'aria_label'         => 'Field journal pages',
			'class'              => 'journal-pagination',
		)
	);
	?>
<?php else : ?>
	<div class="empty-state">
		<h2>The first page is yours.</h2>
		<p>Publish a field note from the writing desk to start your journal.</p>
		<a href="<?php echo esc_url( home_url( '/editor' ) ); ?>" class="primary-button">Open writing desk</a>
	</div>
<?php endif; ?>
<?php
get_footer();
