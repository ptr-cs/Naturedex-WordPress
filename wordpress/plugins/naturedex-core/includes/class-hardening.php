<?php
/**
 * Removes WordPress surfaces this public site does not use.
 *
 * @package Naturedex
 */

namespace Naturedex;

defined( 'ABSPATH' ) || exit;

final class Hardening {
	public static function register_hooks(): void {
		// The site has no comments, so it should accept none.
		add_filter( 'comments_open', '__return_false' );
		add_filter( 'pings_open', '__return_false' );
		add_action(
			'init',
			static function () {
				foreach ( array( 'post', 'page' ) as $type ) {
					remove_post_type_support( $type, 'comments' );
					remove_post_type_support( $type, 'trackbacks' );
				}
			},
			100
		);

		// The Writing desk uses cookies and nonces; no other client needs these.
		// xmlrpc_enabled alone leaves pingbacks and unauthenticated methods running.
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', '__return_empty_array' );
		add_filter( 'wp_is_application_passwords_available', '__return_false' );

		// Keep usernames from being listed by anonymous visitors.
		add_filter( 'rest_endpoints', array( self::class, 'hide_user_routes' ) );
		// Must run before redirect_canonical, which would reveal the username in ?author=1.
		add_action( 'template_redirect', array( self::class, 'close_author_archives' ), 1 );
		add_filter( 'wp_sitemaps_add_provider', array( self::class, 'drop_user_sitemap' ), 10, 2 );

		// A mistyped address should be a 404, not a guess at a similar page.
		add_filter( 'do_redirect_guess_404_permalink', '__return_false' );

		add_filter( 'the_generator', '__return_empty_string' );
		add_filter( 'login_errors', static fn() => __( 'That username or password is incorrect.', 'naturedex' ) );
	}

	public static function hide_user_routes( array $endpoints ): array {
		if ( is_user_logged_in() ) {
			return $endpoints;
		}
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( str_starts_with( $route, '/wp/v2/users' ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}

	public static function close_author_archives(): void {
		if ( is_author() ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	public static function drop_user_sitemap( $provider, string $name ) {
		return 'users' === $name ? false : $provider;
	}
}
