<?php
/**
 * Markup helpers shared by the templates.
 *
 * @package Naturedex
 */

use Naturedex\Records;

defined( 'ABSPATH' ) || exit;

/**
 * All published species, loaded once per request.
 *
 * @return array<int, array<string, mixed>>
 */
function naturedex_species(): array {
	static $species = null;
	$species      ??= class_exists( Records::class ) ? Records::all_species() : array();
	return $species;
}

/**
 * @return array<int, array<string, mixed>>
 */
function naturedex_regions(): array {
	static $regions = null;
	$regions      ??= class_exists( Records::class ) ? Records::regions() : array();
	return $regions;
}

function naturedex_icon( string $name, int $size = 20 ): void {
	$paths = array(
		'book'   => '<path d="M4 4h6l2 2 2-2h6v15h-6l-2 2-2-2H4z"/><path d="M12 6v15M7 8h2M15 8h2M7 12h2M15 12h2"/>',
		'map'    => '<path d="m3 5 6-2 6 2 6-2v16l-6 2-6-2-6 2zM9 3v16M15 5v16"/>',
		'pen'    => '<path d="m15 4 5 5M4 20l2-7L17 2l5 5L11 18zM4 20l7-2"/>',
		'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
		'leaf'   => '<path d="M20 3C8 2 3 7 5 14s13 9 15-11Z"/><path d="m4 21 12-12M10 15v-5M10 15h5"/>',
		'arrow'  => '<path d="M4 12h16m-6-6 6 6-6 6"/>',
		'check'  => '<path d="m5 12 4 4 10-10"/>',
		'close'  => '<path d="m5 5 14 14M19 5 5 19"/>',
		'globe'  => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c-6 6-6 12 0 18 6-6 6-12 0-18Z"/>',
		'info'   => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7v1"/>',
		'eye'    => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
	);
	printf(
		'<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true">%2$s</svg>',
		(int) $size,
		$paths[ $name ] ?? $paths['leaf'] // phpcs:ignore WordPress.Security.EscapeOutput -- Static markup above.
	);
}

/**
 * Prints a sprite. TypeScript swaps in data-animated-src when motion is on.
 *
 * @param array<string, mixed> $species Species record.
 */
function naturedex_sprite( array $species, int $size, string $alt = '', bool $lazy = false ): void {
	printf(
		'<img src="%s" alt="%s" width="%d" height="%d" data-sprite="%s" data-animated="false"%s%s>',
		esc_url( $species['sprite'] ),
		esc_attr( $alt ),
		(int) $size,
		(int) $size,
		esc_attr( $species['id'] ),
		$species['animatedSprite'] ? ' data-animated-src="' . esc_url( $species['animatedSprite'] ) . '"' : '',
		$lazy ? ' loading="lazy"' : ''
	);
}

/**
 * @param array{0: float, 1: float}|null $range Measurement range.
 */
function naturedex_format_range( ?array $range, string $unit ): string {
	return $range ? "{$range[0]}–{$range[1]} {$unit}" : 'See field notes';
}

function naturedex_dex_label( int $number ): string {
	return 'NO. ' . str_pad( (string) $number, 3, '0', STR_PAD_LEFT );
}

/**
 * Finds a species by slug in the loaded records.
 *
 * @return array<string, mixed>|null
 */
function naturedex_find_species( string $id ): ?array {
	foreach ( naturedex_species() as $species ) {
		if ( $species['id'] === $id ) {
			return $species;
		}
	}
	return null;
}

/**
 * The art for a journal card: its first related animal, or else the first entry.
 *
 * @return array<string, mixed>|null
 */
function naturedex_post_art( int $post_id ): ?array {
	$related = class_exists( Records::class ) ? Records::related_species( $post_id ) : array();
	$found   = $related ? naturedex_find_species( $related[0]->post_name ) : null;
	return $found ?? naturedex_species()[0] ?? null;
}

/**
 * Compact records for filtering and collection validation in TypeScript. Printed
 * as inert JSON so public pages stay identical for every visitor.
 */
function naturedex_print_data(): void {
	$species = array_map(
		static fn( $entry ) => array(
			'id'             => $entry['id'],
			'dexNumber'      => $entry['dexNumber'],
			'name'           => $entry['name'],
			'scientificName' => $entry['scientificName'],
			'group'          => $entry['group'],
			'habitats'       => $entry['habitats'],
			'regionIds'      => $entry['regionIds'],
		),
		naturedex_species()
	);
	$json    = wp_json_encode(
		array(
			'species' => $species,
			'regions' => naturedex_regions(),
		),
		JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
	);
	echo '<script type="application/json" id="naturedex-data">' . $json . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON_HEX_TAG prevents breaking out of the element.
}

/**
 * The Virginia outline as an SVG path, projected on the server so the map needs
 * no extra request and appears without JavaScript.
 */
function naturedex_map_outline(): string {
	$fallback = 'M38,324 L95,278 L150,278 L198,227 L242,230 L292,164 L338,178 L366,119 L409,133 L426,88 L466,111 L476,158 L506,165 L509,203 L549,219 L568,259 L598,278 L593,321 L38,324Z';
	if ( ! defined( 'Naturedex\\DIR' ) ) {
		return $fallback;
	}
	$data     = json_decode( (string) file_get_contents( \Naturedex\DIR . '/assets/map/virginia.geojson' ), true );
	$geometry = $data['geometry'] ?? $data['features'][0]['geometry'] ?? null;
	if ( ! $geometry ) {
		return $fallback;
	}
	$polygons = 'MultiPolygon' === $geometry['type'] ? $geometry['coordinates'] : array( $geometry['coordinates'] );
	$rings    = array();
	foreach ( $polygons as $polygon ) {
		foreach ( $polygon as $ring ) {
			$points = array();
			foreach ( $ring as $index => $point ) {
				$x        = ( ( $point[0] + 83.8 ) / 8.7 ) * 620 + 30;
				$y        = ( ( 39.6 - $point[1] ) / 3.2 ) * 300 + 40;
				$points[] = sprintf( '%s%.1f,%.1f', $index ? 'L' : 'M', $x, $y );
			}
			$rings[] = implode( ' ', $points ) . 'Z';
		}
	}
	return implode( ' ', $rings );
}
