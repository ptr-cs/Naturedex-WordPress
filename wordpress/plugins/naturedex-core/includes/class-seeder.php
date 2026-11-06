<?php
/**
 * One-time site setup and idempotent import of the seed content.
 *
 * @package Naturedex
 */

namespace Naturedex;

defined( 'ABSPATH' ) || exit;

final class Seeder {
	private const PAGES = array(
		'field-guide' => 'Field guide',
		'map'         => 'Region map',
		'journal'     => 'Field journal',
		'editor'      => 'Writing desk',
		'about'       => 'About this field guide',
	);

	/**
	 * @return array{species: int, posts: int, pages: int} Number of records created.
	 */
	public static function run( int $author = 0 ): array {
		$author = $author ?: self::first_admin();
		self::configure_site();
		self::import_terms();
		$summary = array(
			'species' => self::import_species(),
			'posts'   => self::import_posts( $author ),
			'pages'   => self::import_pages( $author ),
		);
		self::remove_default_content();
		flush_rewrite_rules( false );
		return $summary;
	}

	private static function first_admin(): int {
		$admins = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'fields'  => 'ID',
			)
		);
		return (int) ( $admins[0] ?? 1 );
	}

	private static function configure_site(): void {
		update_option( 'blogname', 'Naturedex' );
		update_option( 'blogdescription', 'A little curiosity. A whole wild world.' );
		update_option( 'permalink_structure', '/journal/%postname%' );
		update_option( 'timezone_string', 'America/New_York' );
		update_option( 'default_comment_status', 'closed' );
		update_option( 'default_ping_status', 'closed' );
		update_option( 'users_can_register', 0 );
		global $wp_rewrite;
		$wp_rewrite->init();
		Content_Model::register();
	}

	private static function data( string $name ): array {
		return json_decode( (string) file_get_contents( DIR . "/data/{$name}.json" ), true, 32, JSON_THROW_ON_ERROR );
	}

	private static function import_terms(): void {
		foreach ( array( 'Mammals', 'Birds', 'Reptiles', 'Amphibians', 'Fish', 'Invertebrates' ) as $group ) {
			if ( ! term_exists( $group, Content_Model::TAX_GROUP ) ) {
				wp_insert_term( $group, Content_Model::TAX_GROUP );
			}
		}
		foreach ( self::data( 'regions' ) as $region ) {
			if ( term_exists( $region['id'], Content_Model::TAX_REGION ) ) {
				continue;
			}
			$term = wp_insert_term(
				$region['name'],
				Content_Model::TAX_REGION,
				array(
					'slug'        => $region['id'],
					'description' => $region['description'],
				)
			);
			if ( is_wp_error( $term ) ) {
				continue;
			}
			add_term_meta( $term['term_id'], 'naturedex_short_name', $region['shortName'], true );
			add_term_meta( $term['term_id'], 'naturedex_x', $region['x'], true );
			add_term_meta( $term['term_id'], 'naturedex_y', $region['y'], true );
			add_term_meta( $term['term_id'], 'naturedex_color', $region['color'], true );
		}
	}

	/**
	 * The seed ID stays on a record through renames and trashing, which is what
	 * keeps a deliberately deleted seed from coming back.
	 */
	private static function already_imported( string $type, string $seed_id ): bool {
		return (bool) get_posts(
			array(
				'post_type'   => $type,
				'post_status' => array_keys( get_post_stati() ),
				'numberposts' => 1,
				'fields'      => 'ids',
				'meta_key'    => Content_Model::META_SEED,
				'meta_value'  => $seed_id,
			)
		);
	}

	private static function import_species(): int {
		$count = 0;
		foreach ( self::data( 'species' ) as $entry ) {
			if ( self::already_imported( Content_Model::POST_TYPE, $entry['id'] ) ) {
				continue;
			}
			$meta = array(
				'naturedex_dex_number'      => $entry['dexNumber'],
				'naturedex_scientific_name' => $entry['scientificName'],
				'naturedex_activity'        => $entry['activity'],
				'naturedex_size_note'       => $entry['size']['note'],
				'naturedex_region_note'     => $entry['regionNote'],
				'naturedex_sprite'          => $entry['sprite'],
				'naturedex_habitats'        => $entry['habitats'],
				'naturedex_diet'            => $entry['diet'],
				'naturedex_source_urls'     => $entry['sourceURLs'],
				Content_Model::META_SEED    => $entry['id'],
			);
			foreach ( array( 'length_cm' => 'lengthCm', 'weight_kg' => 'weightKg' ) as $key => $field ) {
				if ( null !== $entry['size'][ $field ] ) {
					$meta[ "naturedex_{$key}_min" ] = $entry['size'][ $field ][0];
					$meta[ "naturedex_{$key}_max" ] = $entry['size'][ $field ][1];
				}
			}
			$id = wp_insert_post(
				wp_slash(
					array(
						'post_type'    => Content_Model::POST_TYPE,
						'post_status'  => 'publish',
						'post_title'   => $entry['name'],
						'post_name'    => $entry['id'],
						'post_excerpt' => $entry['description'],
						'meta_input'   => $meta,
					)
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				continue;
			}
			wp_set_object_terms( $id, $entry['group'], Content_Model::TAX_GROUP );
			wp_set_object_terms( $id, $entry['regionIds'], Content_Model::TAX_REGION );
			++$count;
		}
		return $count;
	}

	private static function import_posts( int $author ): int {
		$count = 0;
		foreach ( self::data( 'posts' ) as $entry ) {
			if ( self::already_imported( 'post', $entry['slug'] ) ) {
				continue;
			}
			$gmt = gmdate( 'Y-m-d H:i:s', strtotime( $entry['publishedAt'] ) );
			$id  = wp_insert_post(
				wp_slash(
					array(
						'post_type'     => 'post',
						'post_status'   => 'published' === $entry['status'] ? 'publish' : 'draft',
						'post_author'   => $author,
						'post_title'    => $entry['title'],
						'post_name'     => $entry['slug'],
						'post_excerpt'  => $entry['excerpt'],
						'post_content'  => Content_Format::to_blocks( $entry['body'] ),
						'post_date_gmt' => $gmt,
						'post_date'     => get_date_from_gmt( $gmt ),
						'meta_input'    => array( Content_Model::META_SEED => $entry['slug'] ),
					)
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				continue;
			}
			$term = term_exists( $entry['category'], 'category' ) ?: wp_insert_term( $entry['category'], 'category' );
			if ( ! is_wp_error( $term ) ) {
				wp_set_post_categories( $id, array( (int) $term['term_id'] ) );
			}
			$species = array_filter( array_map( array( Records::class, 'find_species' ), $entry['relatedSpeciesIds'] ) );
			Records::set_related_species( $id, wp_list_pluck( $species, 'ID' ) );
			++$count;
		}
		return $count;
	}

	private static function import_pages( int $author ): int {
		$count = 0;
		foreach ( self::PAGES as $slug => $title ) {
			if ( self::already_imported( 'page', $slug ) ) {
				continue;
			}
			$id = wp_insert_post(
				wp_slash(
					array(
						'post_type'    => 'page',
						'post_status'  => 'publish',
						'post_author'  => $author,
						'post_title'   => $title,
						'post_name'    => $slug,
						'post_content' => 'about' === $slug ? self::about_content() : '',
						'meta_input'   => array( Content_Model::META_SEED => $slug ),
					)
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				continue;
			}
			++$count;
			if ( 'field-guide' === $slug ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $id );
			}
			if ( 'journal' === $slug ) {
				update_option( 'page_for_posts', $id );
			}
		}
		return $count;
	}

	private static function about_content(): string {
		$text = (string) file_get_contents( DIR . '/data/about.txt' );
		$link = '<a href="https://dwr.virginia.gov/wildlife/wildlife-information/">Explore Virginia DWR’s wildlife resources</a>';
		return Content_Format::to_blocks( $text ) . "\n\n<!-- wp:paragraph -->\n<p>{$link}</p>\n<!-- /wp:paragraph -->";
	}

	/**
	 * Removes the sample post and page WordPress installs, if nobody has edited them.
	 */
	private static function remove_default_content(): void {
		foreach ( array( 'hello-world' => 'post', 'sample-page' => 'page' ) as $slug => $type ) {
			$post = get_page_by_path( $slug, OBJECT, $type );
			if ( $post && $post->post_modified === $post->post_date ) {
				wp_delete_post( $post->ID, true );
			}
		}
	}
}
