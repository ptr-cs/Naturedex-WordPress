<?php
/**
 * Normalized records shared by the REST API and the theme.
 *
 * @package Naturedex
 */

namespace Naturedex;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Records {
	/** Statuses the Writing desk lists. Everything except "publish" reads as a draft. */
	public const EDITORIAL_STATUSES = array( 'publish', 'draft', 'pending', 'future', 'private' );

	/**
	 * Published species in dex order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all_species(): array {
		$posts   = get_posts(
			array(
				'post_type'        => Content_Model::POST_TYPE,
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'suppress_filters' => false,
			)
		);
		$records = array_map( array( self::class, 'species' ), $posts );
		usort( $records, static fn( $a, $b ) => $a['dexNumber'] <=> $b['dexNumber'] );
		return $records;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function species( WP_Post $post ): array {
		$groups  = get_the_terms( $post, Content_Model::TAX_GROUP );
		$regions = get_the_terms( $post, Content_Model::TAX_REGION );
		$art     = self::sprite_urls( $post );
		return array(
			'id'             => $post->post_name,
			'dexNumber'      => (int) get_post_meta( $post->ID, 'naturedex_dex_number', true ),
			'name'           => self::text( $post->post_title ),
			'scientificName' => (string) get_post_meta( $post->ID, 'naturedex_scientific_name', true ),
			'group'          => is_array( $groups ) && $groups ? self::text( $groups[0]->name ) : '',
			'sprite'         => $art['static'],
			'animatedSprite' => $art['animated'],
			'description'    => self::text( $post->post_excerpt ),
			'habitats'       => self::list_meta( $post->ID, 'naturedex_habitats' ),
			'diet'           => self::list_meta( $post->ID, 'naturedex_diet' ),
			'activity'       => (string) get_post_meta( $post->ID, 'naturedex_activity', true ),
			'size'           => array(
				'lengthCm' => self::range( $post->ID, 'naturedex_length_cm' ),
				'weightKg' => self::range( $post->ID, 'naturedex_weight_kg' ),
				'note'     => (string) get_post_meta( $post->ID, 'naturedex_size_note', true ),
			),
			'regionIds'      => self::region_slugs( $regions ),
			'regionNote'     => (string) get_post_meta( $post->ID, 'naturedex_region_note', true ),
			'sourceURLs'     => self::list_meta( $post->ID, 'naturedex_source_urls' ),
			'url'            => get_permalink( $post ),
		);
	}

	/**
	 * Regions from west to east, in the order they were seeded.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function regions(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => Content_Model::TAX_REGION,
				'hide_empty' => false,
				'orderby'    => 'term_id',
			)
		);
		if ( ! is_array( $terms ) ) {
			return array();
		}
		return array_map(
			static fn( $term ) => array(
				'id'          => $term->slug,
				'name'        => self::text( $term->name ),
				'shortName'   => (string) get_term_meta( $term->term_id, 'naturedex_short_name', true ),
				'x'           => (float) get_term_meta( $term->term_id, 'naturedex_x', true ),
				'y'           => (float) get_term_meta( $term->term_id, 'naturedex_y', true ),
				'color'       => (string) get_term_meta( $term->term_id, 'naturedex_color', true ),
				'description' => self::text( $term->description ),
			),
			$terms
		);
	}

	/**
	 * @return string[]
	 */
	public static function groups(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => Content_Model::TAX_GROUP,
				'hide_empty' => false,
				'orderby'    => 'term_id',
			)
		);
		return is_array( $terms ) ? array_map( static fn( $term ) => self::text( $term->name ), $terms ) : array();
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function post( WP_Post $post ): array {
		$content    = Content_Format::from_blocks( $post->post_content );
		$categories = get_the_category( $post->ID );
		$published  = 'publish' === $post->post_status;
		$record     = array(
			'id'                => (string) $post->ID,
			'slug'              => $post->post_name,
			'title'             => self::text( $post->post_title ),
			'excerpt'           => self::text( $post->post_excerpt ),
			'body'              => $content['body'],
			'richContent'       => $content['rich'],
			'status'            => $published ? 'published' : 'draft',
			'category'          => $categories ? self::text( $categories[0]->name ) : 'Field Notes',
			'relatedSpeciesIds' => array_column( self::related_species( $post->ID, false ), 'post_name' ),
			'author'            => get_the_author_meta( 'display_name', (int) $post->post_author ),
			'createdAt'         => get_post_time( 'c', true, $post ),
			'updatedAt'         => get_post_modified_time( 'c', true, $post ),
			'publishedAt'       => $published ? get_post_time( 'c', true, $post ) : null,
		);
		if ( current_user_can( 'edit_post', $post->ID ) ) {
			$record['editUrl'] = get_edit_post_link( $post->ID, 'raw' );
		}
		return $record;
	}

	/**
	 * Species linked to a post. Links are stored as post IDs, so renaming a species
	 * cannot break them, and an unpublished species drops out of public pages.
	 *
	 * @return WP_Post[]
	 */
	public static function related_species( int $post_id, bool $published_only = true ): array {
		$ids = array_map( 'intval', get_post_meta( $post_id, Content_Model::META_RELATED ) );
		if ( ! $ids ) {
			return array();
		}
		return get_posts(
			array(
				'post_type'   => Content_Model::POST_TYPE,
				'post__in'    => $ids,
				'orderby'     => 'post__in',
				'numberposts' => -1,
				'post_status' => $published_only ? 'publish' : array( 'publish', 'draft', 'pending', 'private' ),
			)
		);
	}

	/**
	 * @param int[] $species_ids Species post IDs.
	 */
	public static function set_related_species( int $post_id, array $species_ids ): void {
		delete_post_meta( $post_id, Content_Model::META_RELATED );
		foreach ( array_unique( $species_ids ) as $species_id ) {
			add_post_meta( $post_id, Content_Model::META_RELATED, (int) $species_id );
		}
	}

	public static function find_species( string $slug ): ?WP_Post {
		$post = get_page_by_path( $slug, OBJECT, Content_Model::POST_TYPE );
		return $post instanceof WP_Post ? $post : null;
	}

	/**
	 * Decodes the entities WordPress stores in titles, excerpts, and term names.
	 * Output must still be escaped by whatever displays it.
	 */
	private static function text( string $value ): string {
		return html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * A featured image overrides the bundled art, so a species added in wp-admin
	 * does not need a code change to have a picture.
	 *
	 * @return array{static: string, animated: ?string}
	 */
	private static function sprite_urls( WP_Post $post ): array {
		$thumbnail = get_the_post_thumbnail_url( $post, 'medium' );
		if ( $thumbnail ) {
			return array(
				'static'   => $thumbnail,
				'animated' => null,
			);
		}
		$name = sanitize_file_name( (string) get_post_meta( $post->ID, 'naturedex_sprite', true ) );
		$name = '' !== $name ? $name : $post->post_name;
		if ( ! is_file( DIR . "/assets/sprites/{$name}.svg" ) ) {
			return array(
				'static'   => plugins_url( 'assets/placeholder-sprite.svg', FILE ),
				'animated' => null,
			);
		}
		return array(
			'static'   => plugins_url( "assets/sprites/{$name}.svg", FILE ),
			'animated' => is_file( DIR . "/assets/sprites/animated/{$name}.svg" )
				? plugins_url( "assets/sprites/animated/{$name}.svg", FILE )
				: null,
		);
	}

	/**
	 * WordPress returns terms by name; regions read from west to east.
	 *
	 * @param \WP_Term[]|false|\WP_Error $terms Region terms.
	 * @return string[]
	 */
	private static function region_slugs( $terms ): array {
		if ( ! is_array( $terms ) ) {
			return array();
		}
		usort( $terms, static fn( $a, $b ) => $a->term_id <=> $b->term_id );
		return wp_list_pluck( $terms, 'slug' );
	}

	/**
	 * @return string[]
	 */
	private static function list_meta( int $post_id, string $key ): array {
		$value = get_post_meta( $post_id, $key, true );
		return is_array( $value ) ? array_values( $value ) : array();
	}

	/**
	 * @return array{0: float, 1: float}|null
	 */
	private static function range( int $post_id, string $prefix ): ?array {
		$min = get_post_meta( $post_id, "{$prefix}_min", true );
		$max = get_post_meta( $post_id, "{$prefix}_max", true );
		return is_numeric( $min ) && is_numeric( $max ) ? array( (float) $min, (float) $max ) : null;
	}
}
