<?php
/**
 * The field guide layout, shared by the home page and the region map.
 *
 * @package Naturedex
 */

defined( 'ABSPATH' ) || exit;

$naturedex_is_map  = 'map' === naturedex_view();
$naturedex_species = naturedex_species();
$naturedex_regions = naturedex_regions();
$naturedex_groups  = class_exists( \Naturedex\Records::class ) ? \Naturedex\Records::groups() : array();
$naturedex_cardinal = naturedex_find_species( 'northern-cardinal' );
$naturedex_count    = count( $naturedex_species );
$naturedex_recent   = new WP_Query(
	array(
		'posts_per_page'      => 2,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
?>
<section class="discovery-strip" aria-label="Field guide overview">
	<div><span class="strip-icon"><?php naturedex_icon( 'book' ); ?></span><p><strong><?php echo esc_html( str_pad( (string) $naturedex_count, 2, '0', STR_PAD_LEFT ) ); ?></strong><span>species to discover</span></p></div>
	<div><span class="strip-icon"><?php naturedex_icon( 'leaf' ); ?></span><p><strong><?php echo esc_html( str_pad( (string) count( $naturedex_groups ), 2, '0', STR_PAD_LEFT ) ); ?></strong><span>animal groups</span></p></div>
	<div><span class="strip-icon"><?php naturedex_icon( 'map' ); ?></span><p><strong><?php echo esc_html( str_pad( (string) count( $naturedex_regions ), 2, '0', STR_PAD_LEFT ) ); ?></strong><span>regions to explore</span></p></div>
	<span class="strip-message">
		THE BEST ADVENTURES<br><strong>START WITH A CLOSER LOOK.</strong>
		<?php
		if ( $naturedex_cardinal ) {
			naturedex_sprite( $naturedex_cardinal, 60 );
		}
		?>
	</span>
</section>
<div class="guide-layout<?php echo $naturedex_is_map ? ' map-first' : ''; ?>" data-guide>
	<section class="species-section" aria-label="Virginia animal entries">
		<div class="search-box js-only">
			<?php naturedex_icon( 'search', 18 ); ?>
			<input type="search" aria-label="Search the field guide" placeholder="Search a name, species, or habitat…" data-guide-search>
			<span class="search-hint" aria-hidden="true">⌕</span>
		</div>
		<div class="filter-row js-only">
			<label class="group-select-label">
				<span class="sr-only">Animal group</span>
				<select data-guide-group>
					<option value="All">All animals</option>
					<?php foreach ( $naturedex_groups as $naturedex_group ) : ?>
						<option><?php echo esc_html( $naturedex_group ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<button type="button" class="filter-seen" data-guide-seen aria-pressed="false"><?php naturedex_icon( 'eye', 15 ); ?> Discovered</button>
			<label class="sort-label">
				<span class="sr-only">Sort entries</span>
				<select data-guide-sort>
					<option value="number">Dex number</option>
					<option value="name">A–Z</option>
				</select>
			</label>
		</div>
		<div class="region-filter" data-guide-region-chip hidden>
			<span><?php naturedex_icon( 'map', 14 ); ?> <span data-guide-region-name></span></span>
			<button type="button" data-guide-region-clear aria-label="Clear region filter"><?php naturedex_icon( 'close', 14 ); ?></button>
		</div>
		<div class="results-heading">
			<h2 data-guide-title>THE VIRGINIA DEX</h2>
			<span role="status" aria-live="polite" data-guide-count><?php echo (int) $naturedex_count; ?> entries</span>
		</div>
		<div class="species-grid" data-guide-grid>
			<?php
			foreach ( $naturedex_species as $naturedex_entry ) {
				get_template_part( 'template-parts/species-card', null, array( 'species' => $naturedex_entry ) );
			}
			?>
		</div>
		<div class="empty-state" data-guide-empty hidden>
			<?php naturedex_icon( 'search', 32 ); ?>
			<h3>No creatures in this patch.</h3>
			<p>Try a different name, group, or region.</p>
			<button type="button" class="secondary-button" data-guide-reset>Reset filters</button>
		</div>
		<?php get_template_part( 'template-parts/species-templates', null, array( 'species' => $naturedex_species ) ); ?>
	</section>
	<div class="guide-aside">
		<?php get_template_part( 'template-parts/region-map', null, array( 'large' => $naturedex_is_map ) ); ?>
		<div class="region-note panel" data-guide-region-note hidden>
			<span class="eyebrow">A HABITAT WORTH EXPLORING</span>
			<h3 data-guide-region-title></h3>
			<p data-guide-region-description></p>
		</div>
		<section class="field-tip">
			<div class="field-tip-icon"><?php naturedex_icon( 'leaf', 23 ); ?></div>
			<div>
				<span class="eyebrow">A NOTE FROM THE TRAIL</span>
				<h3>Look a little longer.</h3>
				<p>Slow down, listen closely, and leave every habitat just as you found it.</p>
			</div>
			<span class="tip-sparkle" aria-hidden="true">✦</span>
		</section>
		<section class="aside-journal">
			<div class="results-heading">
				<h2>FROM THE FIELD JOURNAL</h2>
				<a href="<?php echo esc_url( home_url( '/journal' ) ); ?>" aria-label="View all field notes">View all ↗</a>
			</div>
			<?php
			while ( $naturedex_recent->have_posts() ) {
				$naturedex_recent->the_post();
				get_template_part( 'template-parts/journal-card', null, array( 'compact' => true ) );
			}
			wp_reset_postdata();
			?>
		</section>
		<div class="original-note">
			<span aria-hidden="true">✧</span>
			<p>Real wildlife. Original pixel art.<br>A field guide with a little nostalgia.</p>
		</div>
	</div>
</div>
