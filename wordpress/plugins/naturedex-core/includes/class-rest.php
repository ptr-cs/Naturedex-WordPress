<?php
/**
 * The naturedex/v1 REST API.
 *
 * Keeps the normalized contract of the first Naturedex (/posts and /posts/{id}
 * with draft, publish, and validation behavior). Writes use WordPress sessions,
 * capabilities, and REST nonces rather than a shared token.
 *
 * @package Naturedex
 */

namespace Naturedex;

use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Rest {
	public const NAMESPACE = 'naturedex/v1';

	private const MAX_REQUEST_BYTES = 120000;
	private const MAX_LENGTHS       = array(
		'title'    => 160,
		'slug'     => 180,
		'excerpt'  => 500,
		'body'     => 50000,
		'category' => 80,
	);

	public static function register_hooks(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/posts',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'list_posts' ),
					'permission_callback' => array( self::class, 'can_list' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'create_post' ),
					'permission_callback' => array( self::class, 'can_create' ),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<id>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'get_post' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( self::class, 'update_post' ),
					'permission_callback' => array( self::class, 'can_edit' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( self::class, 'delete_post' ),
					'permission_callback' => array( self::class, 'can_delete' ),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/species',
			array(
				'methods'             => 'GET',
				'callback'            => static fn() => rest_ensure_response( Records::all_species() ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/regions',
			array(
				'methods'             => 'GET',
				'callback'            => static fn() => rest_ensure_response( Records::regions() ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function can_list( WP_REST_Request $request ) {
		if ( '1' !== $request->get_param( 'includeDrafts' ) ) {
			return true;
		}
		return self::require_capability( 'edit_posts' );
	}

	public static function can_create( WP_REST_Request $request ) {
		$allowed = self::guard_origin( $request );
		return true === $allowed ? self::require_capability( 'edit_posts' ) : $allowed;
	}

	public static function can_edit( WP_REST_Request $request ) {
		return self::can_change( $request, 'edit_post' );
	}

	public static function can_delete( WP_REST_Request $request ) {
		return self::can_change( $request, 'delete_post' );
	}

	public static function list_posts( WP_REST_Request $request ): WP_REST_Response {
		$drafts = '1' === $request->get_param( 'includeDrafts' );
		$args   = array(
			'post_type'   => 'post',
			'post_status' => $drafts ? Records::EDITORIAL_STATUSES : 'publish',
			'orderby'     => array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			),
			'numberposts' => min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?? 100 ) ) ),
			'paged'       => max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) ),
		);
		if ( $drafts && ! current_user_can( 'edit_others_posts' ) ) {
			$args['author'] = get_current_user_id();
		}
		return self::response( array_map( array( Records::class, 'post' ), get_posts( $args ) ) );
	}

	public static function get_post( WP_REST_Request $request ) {
		$post = self::find_post( (string) $request['id'] );
		$open = $post && ( 'publish' === $post->post_status || current_user_can( 'edit_post', $post->ID ) );
		return $open ? self::response( Records::post( $post ) ) : self::not_found();
	}

	public static function create_post( WP_REST_Request $request ) {
		$data = self::payload( $request );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$invalid = self::validate( $data, null );
		if ( $invalid ) {
			return $invalid;
		}
		$args = self::post_args( $data, null ) + array(
			'post_type'   => 'post',
			'post_author' => get_current_user_id(),
			'post_status' => 'draft',
		);
		$id   = wp_insert_post( wp_slash( $args ), true );
		if ( is_wp_error( $id ) ) {
			return self::failed( $id );
		}
		self::apply_terms( $id, $data );
		return self::response( Records::post( get_post( $id ) ), 201 );
	}

	public static function update_post( WP_REST_Request $request ) {
		$post = self::find_post( (string) $request['id'] );
		if ( ! $post ) {
			return self::not_found();
		}
		$data = self::payload( $request );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$invalid = self::validate( $data, $post );
		if ( $invalid ) {
			return $invalid;
		}
		$result = wp_update_post( wp_slash( array( 'ID' => $post->ID ) + self::post_args( $data, $post ) ), true );
		if ( is_wp_error( $result ) ) {
			return self::failed( $result );
		}
		self::apply_terms( $post->ID, $data );
		return self::response( Records::post( get_post( $post->ID ) ) );
	}

	/**
	 * Moves the post to the trash, where it can be recovered from wp-admin.
	 */
	public static function delete_post( WP_REST_Request $request ) {
		$post = self::find_post( (string) $request['id'] );
		if ( ! $post ) {
			return self::not_found();
		}
		if ( ! wp_trash_post( $post->ID ) ) {
			return new WP_Error( 'naturedex_delete_failed', 'The field note could not be deleted.', array( 'status' => 500 ) );
		}
		return new WP_REST_Response( null, 204 );
	}

	private static function can_change( WP_REST_Request $request, string $capability ) {
		$allowed = self::guard_origin( $request );
		if ( true !== $allowed ) {
			return $allowed;
		}
		$allowed = self::require_capability( 'edit_posts' );
		if ( true !== $allowed ) {
			return $allowed;
		}
		$post = self::find_post( (string) $request['id'] );
		// A missing post is reported by the callback as a 404.
		return ! $post || current_user_can( $capability, $post->ID ) ? true : self::forbidden();
	}

	/**
	 * Nonces already stop cross-site requests. This second check rejects them
	 * with a clear answer and does not depend on how a request authenticated.
	 *
	 * @return true|WP_Error
	 */
	private static function guard_origin( WP_REST_Request $request ) {
		$rejected = new WP_Error( 'naturedex_cross_origin', 'Cross-origin editing is not allowed.', array( 'status' => 403 ) );
		if ( 'cross-site' === $request->get_header( 'sec_fetch_site' ) ) {
			return $rejected;
		}
		$origin = $request->get_header( 'origin' );
		if ( null === $origin ) {
			return true;
		}
		$expected = wp_parse_url( home_url() );
		$actual   = wp_parse_url( $origin );
		foreach ( array( 'scheme', 'host', 'port' ) as $part ) {
			if ( ! is_array( $actual ) || ( $expected[ $part ] ?? null ) !== ( $actual[ $part ] ?? null ) ) {
				return $rejected;
			}
		}
		return true;
	}

	/**
	 * @return true|WP_Error
	 */
	private static function require_capability( string $capability ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'naturedex_sign_in', 'Sign in to use the writing desk.', array( 'status' => 401 ) );
		}
		return current_user_can( $capability ) ? true : self::forbidden();
	}

	private static function forbidden(): WP_Error {
		return new WP_Error( 'naturedex_forbidden', 'Your account is not allowed to do that.', array( 'status' => 403 ) );
	}

	private static function not_found(): WP_Error {
		return new WP_Error( 'naturedex_not_found', 'Post not found.', array( 'status' => 404 ) );
	}

	private static function failed( WP_Error $error ): WP_Error {
		return new WP_Error( 'naturedex_save_failed', $error->get_error_message(), array( 'status' => 500 ) );
	}

	private static function response( $data, int $status = 200 ): WP_REST_Response {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	private static function find_post( string $id ): ?WP_Post {
		if ( ctype_digit( $id ) ) {
			$post = get_post( (int) $id );
			return $post && 'post' === $post->post_type && in_array( $post->post_status, Records::EDITORIAL_STATUSES, true ) ? $post : null;
		}
		$matches = get_posts(
			array(
				'post_type'   => 'post',
				'name'        => $id,
				'post_status' => Records::EDITORIAL_STATUSES,
				'numberposts' => 1,
			)
		);
		return $matches[0] ?? null;
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function payload( WP_REST_Request $request ) {
		if ( strlen( $request->get_body() ) > self::MAX_REQUEST_BYTES ) {
			return new WP_Error( 'naturedex_too_large', 'Post data is too large.', array( 'status' => 413 ) );
		}
		if ( ! $request->is_json_content_type() ) {
			return new WP_Error( 'naturedex_json_required', 'JSON is required.', array( 'status' => 415 ) );
		}
		$data = json_decode( $request->get_body(), true );
		if ( ! is_array( $data ) || array_is_list( $data ) ) {
			return new WP_Error( 'naturedex_invalid_json', 'Send a JSON object.', array( 'status' => 400 ) );
		}
		return $data;
	}

	/**
	 * @param array<string, mixed> $data Submitted fields.
	 * @return WP_Error|null All problems in one message, or null when valid.
	 */
	private static function validate( array $data, ?WP_Post $post ): ?WP_Error {
		$errors = array();
		foreach ( self::MAX_LENGTHS as $key => $max ) {
			if ( array_key_exists( $key, $data ) && ( ! is_string( $data[ $key ] ) || mb_strlen( $data[ $key ] ) > $max ) ) {
				$errors[] = "{$key} must be text with at most {$max} characters.";
			}
		}
		foreach ( array( 'title', 'slug', 'body' ) as $key ) {
			$value = $data[ $key ] ?? null;
			if ( ( ! $post && null === $value ) || ( is_string( $value ) && '' === trim( $value ) ) ) {
				$errors[] = "{$key} is required.";
			}
		}
		if ( array_key_exists( 'status', $data ) && ! in_array( $data['status'], array( 'draft', 'published' ), true ) ) {
			$errors[] = 'Choose draft or published status.';
		}
		if ( isset( $data['slug'] ) && is_string( $data['slug'] ) ) {
			$errors = array_merge( $errors, self::slug_errors( $data['slug'], $post ) );
		}
		if ( array_key_exists( 'relatedSpeciesIds', $data ) && null === self::related_ids( $data['relatedSpeciesIds'] ) ) {
			$errors[] = 'Related species must be a list of known species IDs.';
		}
		if ( $errors ) {
			return new WP_Error( 'naturedex_invalid', implode( ' ', $errors ), array( 'status' => 422 ) );
		}

		if ( 'published' === ( $data['status'] ?? null ) && ! current_user_can( 'publish_posts' ) ) {
			return new WP_Error( 'naturedex_cannot_publish', 'Your account can save drafts but cannot publish.', array( 'status' => 403 ) );
		}
		if ( $post && array_key_exists( 'body', $data ) && Content_Format::from_blocks( $post->post_content )['rich'] ) {
			return new WP_Error( 'naturedex_rich_content', 'This field note uses block editor content. Edit its body in WordPress.', array( 'status' => 409 ) );
		}
		return null;
	}

	/**
	 * WordPress would quietly append "-2" to a taken slug. The desk promises the
	 * address the author typed, so a conflict is an error instead.
	 *
	 * @return string[]
	 */
	private static function slug_errors( string $slug, ?WP_Post $post ): array {
		if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug ) ) {
			return array( 'Slug must contain lowercase letters, numbers, and hyphens.' );
		}
		$unique = wp_unique_post_slug( $slug, $post ? $post->ID : 0, 'publish', 'post', 0 );
		return $unique === $slug ? array() : array( 'That slug is already in use.' );
	}

	/**
	 * @return int[]|null Species post IDs, or null when any entry is invalid.
	 */
	private static function related_ids( $value ): ?array {
		if ( ! is_array( $value ) || count( $value ) > 50 ) {
			return null;
		}
		$ids = array();
		foreach ( $value as $slug ) {
			$species = is_string( $slug ) ? Records::find_species( $slug ) : null;
			if ( ! $species ) {
				return null;
			}
			$ids[] = $species->ID;
		}
		return $ids;
	}

	/**
	 * @param array<string, mixed> $data Validated fields.
	 * @return array<string, mixed> Unslashed wp_insert_post() arguments.
	 */
	private static function post_args( array $data, ?WP_Post $post ): array {
		$args = array();
		if ( isset( $data['title'] ) ) {
			$args['post_title'] = trim( wp_strip_all_tags( $data['title'] ) );
		}
		if ( isset( $data['slug'] ) ) {
			$args['post_name'] = $data['slug'];
		}
		if ( isset( $data['excerpt'] ) ) {
			$args['post_excerpt'] = sanitize_textarea_field( $data['excerpt'] );
		}
		if ( isset( $data['body'] ) ) {
			$args['post_content'] = Content_Format::to_blocks( $data['body'] );
		}
		if ( isset( $data['status'] ) ) {
			$keep_scheduled      = $post && 'published' !== $data['status'] && 'publish' !== $post->post_status;
			$args['post_status'] = 'published' === $data['status'] ? 'publish' : ( $keep_scheduled ? $post->post_status : 'draft' );
		}
		return $args;
	}

	/**
	 * @param array<string, mixed> $data Validated fields.
	 */
	private static function apply_terms( int $post_id, array $data ): void {
		if ( isset( $data['category'] ) ) {
			$name = sanitize_text_field( $data['category'] );
			if ( '' !== $name ) {
				$term = term_exists( $name, 'category' ) ?: wp_insert_term( $name, 'category' );
				if ( ! is_wp_error( $term ) ) {
					wp_set_post_categories( $post_id, array( (int) $term['term_id'] ) );
				}
			}
		}
		if ( array_key_exists( 'relatedSpeciesIds', $data ) ) {
			Records::set_related_species( $post_id, self::related_ids( $data['relatedSpeciesIds'] ) ?? array() );
		}
	}
}
