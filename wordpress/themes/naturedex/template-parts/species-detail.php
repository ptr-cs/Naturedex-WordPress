<?php
/**
 * A full species entry. Rendered on the species page and inside the <template>
 * that TypeScript clones into a dialog, so the two can never drift apart.
 *
 * @package Naturedex
 * @var array $args { species: array, heading?: string }
 */

defined( 'ABSPATH' ) || exit;

$naturedex_entry   = $args['species'];
$naturedex_heading = $args['heading'] ?? 'h2';
$naturedex_names   = array_column( naturedex_regions(), 'shortName', 'id' );
?>
<div class="detail-hero">
	<div class="detail-sprite">
		<?php naturedex_sprite( $naturedex_entry, 200, "Pixel illustration of {$naturedex_entry['name']}" ); ?>
		<span class="detail-number"><?php echo esc_html( naturedex_dex_label( $naturedex_entry['dexNumber'] ) ); ?></span>
	</div>
	<div>
		<span class="group-badge group-<?php echo esc_attr( strtolower( $naturedex_entry['group'] ) ); ?>"><?php echo esc_html( $naturedex_entry['group'] ); ?></span>
		<<?php echo tag_escape( $naturedex_heading ); ?>><?php echo esc_html( $naturedex_entry['name'] ); ?></<?php echo tag_escape( $naturedex_heading ); ?>>
		<p class="latin-name"><?php echo esc_html( $naturedex_entry['scientificName'] ); ?></p>
		<button type="button" class="primary-button js-only" data-seen-toggle data-seen-detail aria-pressed="false" aria-label="Mark as seen: <?php echo esc_attr( $naturedex_entry['name'] ); ?>" data-species-id="<?php echo esc_attr( $naturedex_entry['id'] ); ?>">
			<span data-seen-icon><?php naturedex_icon( 'eye', 17 ); ?></span>
			<span data-seen-label>Mark as seen</span>
		</button>
	</div>
</div>
<p class="detail-description"><?php echo esc_html( $naturedex_entry['description'] ); ?></p>
<dl class="stat-grid">
	<div><dt>ACTIVE HOURS</dt><dd><?php echo esc_html( $naturedex_entry['activity'] ); ?></dd></div>
	<div><dt>DIET</dt><dd><?php echo esc_html( implode( ', ', $naturedex_entry['diet'] ) ); ?></dd></div>
	<div><dt>LENGTH</dt><dd><?php echo esc_html( naturedex_format_range( $naturedex_entry['size']['lengthCm'], 'cm' ) ); ?></dd></div>
	<div><dt>WEIGHT</dt><dd><?php echo esc_html( naturedex_format_range( $naturedex_entry['size']['weightKg'], 'kg' ) ); ?></dd></div>
	<div class="wide-stat"><dt>HABITAT</dt><dd><?php echo esc_html( implode( ' · ', $naturedex_entry['habitats'] ) ); ?></dd></div>
</dl>
<?php if ( $naturedex_entry['size']['note'] ) : ?>
	<p class="fine-print"><?php echo esc_html( $naturedex_entry['size']['note'] ); ?></p>
<?php endif; ?>
<h3 class="detail-subtitle">Where to explore</h3>
<div class="detail-regions">
	<?php foreach ( $naturedex_entry['regionIds'] as $naturedex_region_id ) : ?>
		<span><?php echo esc_html( $naturedex_names[ $naturedex_region_id ] ?? $naturedex_region_id ); ?></span>
	<?php endforeach; ?>
</div>
<p class="fine-print"><?php echo esc_html( $naturedex_entry['regionNote'] ); ?></p>
<div class="source-links">
	<span class="eyebrow">FIELD REFERENCES</span>
	<?php foreach ( $naturedex_entry['sourceURLs'] as $naturedex_index => $naturedex_url ) : ?>
		<?php $naturedex_host = str_replace( 'www.', '', (string) wp_parse_url( $naturedex_url, PHP_URL_HOST ) ); ?>
		<a href="<?php echo esc_url( $naturedex_url ); ?>" target="_blank" rel="noreferrer"><?php echo esc_html( $naturedex_host ); ?> · Source <?php echo (int) $naturedex_index + 1; ?> ↗</a>
	<?php endforeach; ?>
</div>
