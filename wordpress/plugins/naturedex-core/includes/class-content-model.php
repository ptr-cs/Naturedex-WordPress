<?php
/**
 * Species post type, taxonomies, and registered metadata.
 *
 * Lives in a plugin rather than the theme so content survives a theme change.
 *
 * @package Naturedex
 */

namespace Naturedex;

defined( 'ABSPATH' ) || exit;

final class Content_Model {
	public const POST_TYPE    = 'species';
	public const TAX_GROUP    = 'naturedex_group';
	public const TAX_REGION   = 'naturedex_region';
	public const META_RELATED = 'naturedex_related_species';
	public const META_SEED    = 'naturedex_seed_id';

	/** Text fields on a species. */
	public const TEXT_META = array(
		'naturedex_scientific_name',
		'naturedex_activity',
		'naturedex_size_note',
		'naturedex_region_note',
		'naturedex_sprite',
	);

	/** Measurement fields on a species. A missing value stays missing; it is never shown as zero. */
	public const NUMBER_META = array(
		'naturedex_length_cm_min',
		'naturedex_length_cm_max',
		'naturedex_weight_kg_min',
		'naturedex_weight_kg_max',
	);

	/** List fields on a species. */
	public const LIST_META = array(
		'naturedex_habitats',
		'naturedex_diet',
		'naturedex_source_urls',
	);

	public static function register_hooks(): void {
		add_action( 'init', array( self::class, 'register' ) );
	}

	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Species', 'naturedex' ),
					'singular_name' => __( 'Species', 'naturedex' ),
					'add_new_item'  => __( 'Add species', 'naturedex' ),
					'edit_item'     => __( 'Edit species', 'naturedex' ),
					'all_items'     => __( 'All species', 'naturedex' ),
					'search_items'  => __( 'Search species', 'naturedex' ),
				),
				'public'       => true,
				'has_archive'  => false,
				'menu_icon'    => 'dashicons-pets',
				'show_in_rest' => true,
				'rewrite'      => array(
					'slug'       => 'species',
					'with_front' => false,
				),
				// Without the "editor" feature wp-admin shows one structured form.
				// The excerpt holds the short field-guide description.
				'supports'     => array( 'title', 'excerpt', 'thumbnail', 'revisions' ),
			)
		);

		$taxonomy = array(
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => false,
		);
		register_taxonomy(
			self::TAX_GROUP,
			self::POST_TYPE,
			$taxonomy + array(
				'hierarchical' => true,
				'labels'       => array(
					'name'          => __( 'Animal groups', 'naturedex' ),
					'singular_name' => __( 'Animal group', 'naturedex' ),
				),
			)
		);
		register_taxonomy(
			self::TAX_REGION,
			self::POST_TYPE,
			$taxonomy + array(
				'hierarchical' => true,
				'labels'       => array(
					'name'          => __( 'Regions', 'naturedex' ),
					'singular_name' => __( 'Region', 'naturedex' ),
				),
			)
		);

		$auth = static fn() => current_user_can( 'edit_posts' );
		register_post_meta(
			self::POST_TYPE,
			'naturedex_dex_number',
			array(
				'type'          => 'integer',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => $auth,
			)
		);
		foreach ( self::TEXT_META as $key ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => $auth,
				)
			);
		}
		foreach ( self::NUMBER_META as $key ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'          => 'number',
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => $auth,
				)
			);
		}
		foreach ( self::LIST_META as $key ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'          => 'array',
					'single'        => true,
					'auth_callback' => $auth,
					'show_in_rest'  => array(
						'schema' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
				)
			);
		}
		register_post_meta(
			'post',
			self::META_RELATED,
			array(
				'type'          => 'integer',
				'single'        => false,
				'show_in_rest'  => true,
				'auth_callback' => $auth,
			)
		);
	}
}
