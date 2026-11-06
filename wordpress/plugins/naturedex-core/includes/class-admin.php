<?php
/**
 * wp-admin forms for species, regions, and a post's related species.
 *
 * Plain meta boxes keep the plugin free of third-party field frameworks.
 *
 * @package Naturedex
 */

namespace Naturedex;

use WP_Post;
use WP_Term;

defined( 'ABSPATH' ) || exit;

final class Admin {
	private const NONCE = 'naturedex_admin_fields';

	private const TEXT_FIELDS = array(
		'naturedex_scientific_name' => 'Scientific name',
		'naturedex_activity'        => 'Active hours',
	);

	private const NUMBER_FIELDS = array(
		'naturedex_length_cm_min' => 'Minimum length (cm)',
		'naturedex_length_cm_max' => 'Maximum length (cm)',
		'naturedex_weight_kg_min' => 'Minimum weight (kg)',
		'naturedex_weight_kg_max' => 'Maximum weight (kg)',
	);

	private const LIST_FIELDS = array(
		'naturedex_habitats'    => 'Habitats (one per line)',
		'naturedex_diet'        => 'Diet (one per line)',
		'naturedex_source_urls' => 'Source URLs (one per line)',
	);

	private const NOTE_FIELDS = array(
		'naturedex_size_note'   => 'Size note',
		'naturedex_region_note' => 'Region note',
	);

	private const REGION_FIELDS = array(
		'naturedex_short_name' => 'Short name',
		'naturedex_x'          => 'Map X position (0–680)',
		'naturedex_y'          => 'Map Y position (0–400)',
		'naturedex_color'      => 'Legend color (hex)',
	);

	public static function register_hooks(): void {
		add_action( 'add_meta_boxes', array( self::class, 'add_boxes' ) );
		add_action( 'save_post_' . Content_Model::POST_TYPE, array( self::class, 'save_species' ), 10, 2 );
		add_action( 'save_post_post', array( self::class, 'save_related' ), 10, 2 );
		add_action( Content_Model::TAX_REGION . '_add_form_fields', array( self::class, 'region_add_fields' ) );
		add_action( Content_Model::TAX_REGION . '_edit_form_fields', array( self::class, 'region_edit_fields' ) );
		add_action( 'created_' . Content_Model::TAX_REGION, array( self::class, 'save_region' ) );
		add_action( 'edited_' . Content_Model::TAX_REGION, array( self::class, 'save_region' ) );
	}

	public static function add_boxes(): void {
		add_meta_box( 'naturedex_species', __( 'Field guide entry', 'naturedex' ), array( self::class, 'species_box' ), Content_Model::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'naturedex_related', __( 'Related species', 'naturedex' ), array( self::class, 'related_box' ), 'post', 'side' );
	}

	public static function species_box( WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );
		echo '<table class="form-table" role="presentation">';
		self::input_row( 'naturedex_dex_number', __( 'Dex number', 'naturedex' ), (string) get_post_meta( $post->ID, 'naturedex_dex_number', true ), 'number' );
		foreach ( self::TEXT_FIELDS as $key => $label ) {
			self::input_row( $key, $label, (string) get_post_meta( $post->ID, $key, true ) );
		}
		foreach ( self::NUMBER_FIELDS as $key => $label ) {
			self::input_row( $key, $label, (string) get_post_meta( $post->ID, $key, true ), 'number' );
		}
		foreach ( self::LIST_FIELDS as $key => $label ) {
			$value = get_post_meta( $post->ID, $key, true );
			self::textarea_row( $key, $label, is_array( $value ) ? implode( "\n", $value ) : '' );
		}
		foreach ( self::NOTE_FIELDS as $key => $label ) {
			self::textarea_row( $key, $label, (string) get_post_meta( $post->ID, $key, true ) );
		}
		echo '</table>';
	}

	public static function related_box( WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );
		$selected = array_map( 'intval', get_post_meta( $post->ID, Content_Model::META_RELATED ) );
		$species  = get_posts(
			array(
				'post_type'   => Content_Model::POST_TYPE,
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		echo '<input type="hidden" name="naturedex_related_present" value="1">';
		echo '<div style="max-height:220px;overflow:auto">';
		foreach ( $species as $entry ) {
			printf(
				'<label style="display:block"><input type="checkbox" name="naturedex_related[]" value="%d"%s> %s</label>',
				(int) $entry->ID,
				checked( in_array( $entry->ID, $selected, true ), true, false ),
				esc_html( $entry->post_title )
			);
		}
		echo '</div>';
	}

	public static function save_species( int $post_id, WP_Post $post ): void {
		if ( ! self::can_save( $post_id ) ) {
			return;
		}
		$number = absint( wp_unslash( $_POST['naturedex_dex_number'] ?? 0 ) );
		update_post_meta( $post_id, 'naturedex_dex_number', $number ?: self::next_dex_number() );
		foreach ( array_keys( self::TEXT_FIELDS ) as $key ) {
			update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) ) );
		}
		foreach ( array_keys( self::NOTE_FIELDS ) as $key ) {
			update_post_meta( $post_id, $key, sanitize_textarea_field( wp_unslash( $_POST[ $key ] ?? '' ) ) );
		}
		foreach ( array_keys( self::NUMBER_FIELDS ) as $key ) {
			$value = wp_unslash( $_POST[ $key ] ?? '' );
			if ( is_numeric( $value ) ) {
				update_post_meta( $post_id, $key, (float) $value );
			} else {
				delete_post_meta( $post_id, $key );
			}
		}
		foreach ( array_keys( self::LIST_FIELDS ) as $key ) {
			$lines = preg_split( '/\R/', (string) wp_unslash( $_POST[ $key ] ?? '' ) );
			$lines = array_map( 'naturedex_source_urls' === $key ? 'esc_url_raw' : 'sanitize_text_field', $lines );
			update_post_meta( $post_id, $key, array_values( array_filter( array_map( 'trim', $lines ) ) ) );
		}
	}

	public static function save_related( int $post_id, WP_Post $post ): void {
		// The block editor saves meta boxes in a second request; this field marks it.
		if ( ! isset( $_POST['naturedex_related_present'] ) || ! self::can_save( $post_id ) ) {
			return;
		}
		$ids = array_map( 'absint', (array) wp_unslash( $_POST['naturedex_related'] ?? array() ) );
		Records::set_related_species( $post_id, $ids );
	}

	public static function region_add_fields(): void {
		wp_nonce_field( self::NONCE, self::NONCE );
		foreach ( self::REGION_FIELDS as $key => $label ) {
			printf(
				'<div class="form-field"><label for="%1$s">%2$s</label><input id="%1$s" name="%1$s" type="text"></div>',
				esc_attr( $key ),
				esc_html( $label )
			);
		}
	}

	public static function region_edit_fields( WP_Term $term ): void {
		wp_nonce_field( self::NONCE, self::NONCE );
		foreach ( self::REGION_FIELDS as $key => $label ) {
			printf(
				'<tr class="form-field"><th scope="row"><label for="%1$s">%2$s</label></th><td><input id="%1$s" name="%1$s" type="text" value="%3$s"></td></tr>',
				esc_attr( $key ),
				esc_html( $label ),
				esc_attr( (string) get_term_meta( $term->term_id, $key, true ) )
			);
		}
	}

	public static function save_region( int $term_id ): void {
		if ( ! self::valid_nonce() || ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		foreach ( array_keys( self::REGION_FIELDS ) as $key ) {
			$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) );
			if ( 'naturedex_color' === $key ) {
				$value = (string) sanitize_hex_color( $value );
			}
			update_term_meta( $term_id, $key, $value );
		}
	}

	private static function can_save( int $post_id ): bool {
		return self::valid_nonce()
			&& ! ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			&& ! wp_is_post_revision( $post_id )
			&& current_user_can( 'edit_post', $post_id );
	}

	private static function valid_nonce(): bool {
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ?? '' ) );
		return '' !== $nonce && wp_verify_nonce( $nonce, self::NONCE );
	}

	private static function next_dex_number(): int {
		$numbers = array_column( Records::all_species(), 'dexNumber' );
		return $numbers ? max( $numbers ) + 1 : 1;
	}

	private static function input_row( string $key, string $label, string $value, string $type = 'text' ): void {
		printf(
			'<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input class="regular-text" id="%1$s" name="%1$s" type="%3$s" step="any" value="%4$s"></td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( $type ),
			esc_attr( $value )
		);
	}

	private static function textarea_row( string $key, string $label, string $value ): void {
		printf(
			'<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><textarea class="large-text" id="%1$s" name="%1$s" rows="4">%3$s</textarea></td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_textarea( $value )
		);
	}
}
