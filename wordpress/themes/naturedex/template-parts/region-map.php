<?php
/**
 * The Virginia region map.
 *
 * @package Naturedex
 * @var array $args { large?: bool }
 */

defined( 'ABSPATH' ) || exit;

$naturedex_outline = naturedex_map_outline();
$naturedex_regions = naturedex_regions();
?>
<section class="region-panel<?php echo empty( $args['large'] ) ? '' : ' region-panel-large'; ?>" aria-labelledby="region-map-title">
	<div class="panel-heading">
		<div>
			<span class="eyebrow">YOUR NEXT DISCOVERY</span>
			<h2 id="region-map-title">A little closer to the wild.</h2>
		</div>
		<span class="map-mini-icon" aria-hidden="true">↗</span>
	</div>
	<div class="map-canvas">
		<span class="map-coordinate map-coordinate-top">39° N / 83° W</span>
		<svg viewBox="0 0 680 400" role="img" aria-label="Virginia outline with four selectable regional habitat areas. Use the region buttons to explore associated animals.">
			<defs>
				<pattern id="map-grid" width="20" height="20" patternUnits="userSpaceOnUse"><path d="M20 0H0V20" fill="none" stroke="#849d79" stroke-opacity=".15" stroke-width="1"/></pattern>
				<pattern id="forest" width="36" height="36" patternUnits="userSpaceOnUse"><path d="M8 6h6v5h4v5H4v-5h4z" fill="#547d55" opacity=".2"/><path d="M10 16h2v5h-2z" fill="#547d55" opacity=".3"/></pattern>
				<clipPath id="virginia-clip"><path d="<?php echo esc_attr( $naturedex_outline ); ?>"/></clipPath>
			</defs>
			<rect width="680" height="400" fill="url(#map-grid)"/>
			<text x="590" y="164" text-anchor="middle" class="map-water-label">CHESAPEAKE</text>
			<text x="590" y="180" text-anchor="middle" class="map-water-label">BAY</text>
			<path d="<?php echo esc_attr( $naturedex_outline ); ?>" transform="translate(0 7)" fill="#a0b393" opacity=".45"/>
			<path d="<?php echo esc_attr( $naturedex_outline ); ?>" fill="#b7ce92" stroke="#637e50" stroke-width="2.5" stroke-linejoin="round"/>
			<g clip-path="url(#virginia-clip)">
				<path d="M0 0h300L190 400H0Z" fill="#8eaf79"/>
				<path d="M300 0h80L270 400h-80Z" fill="#c5ce91"/>
				<path d="M470 0h210v400H390Z" fill="#d2d3a4"/>
				<path d="M0 0h300L190 400H0Z" fill="url(#forest)"/>
				<path d="M300 0h80L270 400h-80Z" fill="url(#forest)" opacity=".6"/>
				<path d="M338 186l32 35 42-8 30 31 40 8 36 38 64 11" stroke="#79a9a7" stroke-width="4" fill="none" stroke-linejoin="round"/>
				<path d="M409 132l24 48 45 11 40 35 46 1" stroke="#79a9a7" stroke-width="3" fill="none"/>
				<?php for ( $naturedex_i = 0; $naturedex_i < 7; $naturedex_i++ ) : ?>
					<path d="M<?php echo (int) ( 176 + $naturedex_i * 13 ); ?> <?php echo (int) ( 268 - $naturedex_i * 14 ); ?>l10-16 10 16z" fill="#567f5c" opacity=".5"/>
				<?php endfor; ?>
			</g>
			<path d="<?php echo esc_attr( $naturedex_outline ); ?>" fill="none" stroke="#637e50" stroke-width="2.5" stroke-linejoin="round"/>
			<text x="178" y="348" class="map-state-label">NORTH CAROLINA</text>
			<text x="200" y="105" class="map-state-label">WEST VIRGINIA</text>
			<g transform="translate(631 140)" fill="#50664f"><path d="M0-15l-6 19 6-4 6 4z"/><text x="-4" y="-23" font-size="11">N</text></g>
		</svg>
		<?php foreach ( $naturedex_regions as $naturedex_region ) : ?>
			<button type="button" class="map-marker js-only" data-region="<?php echo esc_attr( $naturedex_region['id'] ); ?>" style="left: <?php echo esc_attr( ( $naturedex_region['x'] / 680 ) * 100 ); ?>%; top: <?php echo esc_attr( ( $naturedex_region['y'] / 400 ) * 100 ); ?>%" aria-label="<?php echo esc_attr( "{$naturedex_region['shortName']}: {$naturedex_region['name']}" ); ?>" aria-pressed="false">
				<span class="marker-square"></span>
				<span class="marker-label"><?php echo esc_html( $naturedex_region['shortName'] ); ?></span>
			</button>
		<?php endforeach; ?>
		<span class="map-coordinate map-coordinate-bottom">VIRGINIA, UNITED STATES</span>
	</div>
	<div class="map-legend js-only">
		<?php foreach ( $naturedex_regions as $naturedex_region ) : ?>
			<?php
			$naturedex_count = count( array_filter( naturedex_species(), static fn( $entry ) => in_array( $naturedex_region['id'], $entry['regionIds'], true ) ) );
			?>
			<button type="button" data-region="<?php echo esc_attr( $naturedex_region['id'] ); ?>" aria-pressed="false">
				<span style="background: <?php echo esc_attr( $naturedex_region['color'] ); ?>"></span>
				<?php echo esc_html( $naturedex_region['shortName'] ); ?>
				<small><?php echo (int) $naturedex_count; ?></small>
			</button>
		<?php endforeach; ?>
	</div>
	<p class="map-caption">Select a region to explore its wildlife. Regional habitat associations are illustrative, not recorded sightings or complete species ranges.</p>
</section>
