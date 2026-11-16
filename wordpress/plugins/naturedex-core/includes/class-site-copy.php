<?php
/**
 * Site-wide wording and the heading of each page, edited in WordPress.
 *
 * Site copy (the sidebar tagline, the footer line, and the default search
 * description) is one option, edited in Appearance → Customize → Site copy.
 * Each page's eyebrow, heading, and introduction are post meta, edited in the
 * page's "Page heading" box. The page body is the page's own content.
 *
 * @package Naturedex
 */

namespace Naturedex;

use WP_Customize_Manager;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Site_Copy {
	public const OPTION = 'naturedex_site_copy';

	/**
	 * Each field: label, Customizer control type, description. All are required.
	 */
	public const FIELDS = array(
		'tagline'          => array( 'Sidebar tagline', 'textarea', 'Shown under the navigation. Each line break starts a new line on the site.' ),
		'footer_motto'     => array( 'Footer line', 'text', 'Shown at the bottom of every page.' ),
		'meta_description' => array( 'Default search description', 'textarea', 'Used by search engines and link previews when a page has no description of its own. Keep it under about 160 characters.' ),
	);

	/** The copy a new site starts with, and what shows until the option exists. */
	public const DEFAULTS = array(
		'tagline'          => "A little curiosity.\nA whole wild world.",
		'footer_motto'     => 'Take only memories. Leave only footprints.',
		'meta_description' => 'A little curiosity. A whole wild world. Explore Virginia’s wildlife, keep a field collection, and read field notes in Naturedex.',
	);

	/** Page meta key => label. All optional. */
	public const PAGE_META = array(
		'naturedex_eyebrow' => 'Eyebrow',
		'naturedex_heading' => 'Heading',
		'naturedex_intro'   => 'Introduction',
	);

	private const VERSION_OPTION = 'naturedex_copy_version';
	private const VERSION        = 1;

	public static function register_hooks(): void {
		add_action( 'init', array( self::class, 'register' ) );
		add_action( 'init', array( self::class, 'upgrade' ), 20 );
		add_action( 'customize_register', array( self::class, 'customize' ) );
	}

	public static function register(): void {
		$properties = array_map( static fn() => array( 'type' => 'string' ), self::FIELDS );
		register_setting(
			'naturedex',
			self::OPTION,
			array(
				'type'              => 'object',
				'default'           => self::DEFAULTS,
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'                 => 'object',
						'properties'           => $properties,
						'additionalProperties' => false,
					),
				),
			)
		);
		$auth = static fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', (int) $post_id );
		foreach ( array_keys( self::PAGE_META ) as $key ) {
			register_post_meta(
				'page',
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'auth_callback'     => $auth,
					'sanitize_callback' => 'naturedex_intro' === $key ? 'sanitize_textarea_field' : 'sanitize_text_field',
				)
			);
		}
	}

	/**
	 * One field of the site copy. A blank or missing field falls back to its
	 * default, so the site never shows an empty tagline or footer.
	 */
	public static function get( string $key ): string {
		$copy  = get_option( self::OPTION );
		$value = is_array( $copy ) ? trim( (string) ( $copy[ $key ] ?? '' ) ) : '';
		return '' !== $value ? $value : self::DEFAULTS[ $key ];
	}

	/**
	 * Keeps known fields, cleaned; a blank field keeps its current value.
	 *
	 * @param mixed $value
	 */
	public static function sanitize( $value ): array {
		$value   = is_array( $value ) ? $value : array();
		$current = get_option( self::OPTION );
		$current = is_array( $current ) ? $current : self::DEFAULTS;
		$clean   = array();
		foreach ( self::FIELDS as $key => [ , $type ] ) {
			$text          = (string) ( $value[ $key ] ?? '' );
			$text          = 'textarea' === $type ? sanitize_textarea_field( $text ) : sanitize_text_field( $text );
			$clean[ $key ] = '' !== trim( $text ) ? $text : (string) ( $current[ $key ] ?? self::DEFAULTS[ $key ] );
		}
		return $clean;
	}

	/**
	 * The heading for a page: its eyebrow, its heading (the page title when
	 * blank), and its introduction (none when blank).
	 *
	 * @return array{eyebrow: string, title: string, description: string}
	 */
	public static function page_heading( int $post_id ): array {
		$meta = static fn( string $key ) => trim( (string) get_post_meta( $post_id, $key, true ) );
		return array(
			'eyebrow'     => $meta( 'naturedex_eyebrow' ) ?: 'NATUREDEX',
			'title'       => $meta( 'naturedex_heading' ) ?: wp_strip_all_tags( get_the_title( $post_id ) ),
			'description' => $meta( 'naturedex_intro' ),
		);
	}

	public static function customize( WP_Customize_Manager $customizer ): void {
		$customizer->add_section(
			'naturedex_site_copy',
			array(
				'title'       => __( 'Site copy', 'naturedex' ),
				'description' => __( 'Wording shown on every page. A page’s own heading and introduction are edited on the page.', 'naturedex' ),
				'priority'    => 25,
			)
		);
		foreach ( self::FIELDS as $key => [ $label, $type, $description ] ) {
			$id = self::OPTION . "[{$key}]";
			$customizer->add_setting(
				$id,
				array(
					'type'              => 'option',
					'default'           => self::DEFAULTS[ $key ],
					'sanitize_callback' => 'textarea' === $type ? 'sanitize_textarea_field' : 'sanitize_text_field',
					'validate_callback' => static fn( $validity, $value ) => '' === trim( (string) $value )
						? new WP_Error( 'required', __( 'This can’t be empty.', 'naturedex' ) )
						: $validity,
				)
			);
			$customizer->add_control(
				$id,
				array(
					'label'       => $label,
					'description' => $description,
					'section'     => 'naturedex_site_copy',
					'type'        => $type,
				)
			);
		}
	}

	/**
	 * Gives a site that predates editable copy the copy it was showing: the
	 * site copy option, and the About page's heading. Existing values are kept.
	 */
	public static function upgrade(): void {
		if ( (int) get_option( self::VERSION_OPTION ) >= self::VERSION ) {
			return;
		}
		self::install();
		$about = get_posts(
			array(
				'post_type'   => 'page',
				'post_status' => array_keys( get_post_stati() ),
				'numberposts' => 1,
				'fields'      => 'ids',
				'meta_key'    => Content_Model::META_SEED,
				'meta_value'  => 'about',
			)
		);
		if ( $about ) {
			foreach ( Seeder::ABOUT_HEADING as $key => $value ) {
				if ( ! metadata_exists( 'post', $about[0], $key ) ) {
					update_post_meta( $about[0], $key, $value );
				}
			}
		}
		update_option( self::VERSION_OPTION, self::VERSION );
	}

	/** Stores the default copy if the site has none. */
	public static function install(): void {
		add_option( self::OPTION, self::DEFAULTS );
	}
}
