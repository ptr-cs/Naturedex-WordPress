<?php
/**
 * A field guide card. The title is a real link until TypeScript upgrades it to a
 * dialog button; without JavaScript it leads to the species page.
 *
 * @package Naturedex
 * @var array $args { species: array }
 */

defined( 'ABSPATH' ) || exit;

$naturedex_card  = $args['species'];
$naturedex_group = strtolower( $naturedex_card['group'] );
$naturedex_size  = $naturedex_card['size']['lengthCm']
	? naturedex_format_range( $naturedex_card['size']['lengthCm'], 'cm' )
	: ( $naturedex_card['habitats'][0] ?? '' );
?>
<article class="species-card" data-species-card="<?php echo esc_attr( $naturedex_card['id'] ); ?>">
	<div class="card-topline">
		<span class="dex-number"><?php echo esc_html( naturedex_dex_label( $naturedex_card['dexNumber'] ) ); ?></span>
		<button type="button" class="seen-toggle js-only" data-seen-toggle aria-pressed="false" aria-label="Mark <?php echo esc_attr( $naturedex_card['name'] ); ?> as seen"><span></span></button>
	</div>
	<div class="species-open">
		<div class="sprite-stage">
			<?php naturedex_sprite( $naturedex_card, 106, '', true ); ?>
			<span class="sprite-shadow"></span>
		</div>
		<h3><a class="species-title" href="<?php echo esc_url( $naturedex_card['url'] ); ?>" aria-label="View <?php echo esc_attr( $naturedex_card['name'] ); ?>"><?php echo esc_html( $naturedex_card['name'] ); ?></a></h3>
		<p class="latin-name"><?php echo esc_html( $naturedex_card['scientificName'] ); ?></p>
		<div class="card-statline">
			<span><?php echo esc_html( $naturedex_card['activity'] ); ?></span>
			<span><?php echo esc_html( $naturedex_size ); ?></span>
		</div>
		<span class="group-badge group-<?php echo esc_attr( $naturedex_group ); ?>"><span class="badge-dot"></span><?php echo esc_html( $naturedex_card['group'] ); ?></span>
		<span class="card-arrow" aria-hidden="true">↗</span>
	</div>
</article>
