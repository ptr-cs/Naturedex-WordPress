<?php
/**
 * Theme setup. Styles and scripts are built from /frontend into assets/dist.
 *
 * @package Naturedex
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/inc/views.php';
require_once __DIR__ . '/inc/render.php';

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'html5', array( 'style', 'script', 'gallery', 'caption' ) );
	}
);

// The toolbar would sit over the fixed app shell. wp-admin keeps it.
add_filter( 'show_admin_bar', '__return_false' );

remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'rsd_link' );

add_action(
	'wp_enqueue_scripts',
	static function () {
		$dist = get_theme_file_path( 'assets/dist' );
		$uri  = get_theme_file_uri( 'assets/dist' );
		wp_enqueue_style( 'naturedex', "{$uri}/app.css", array(), (string) filemtime( "{$dist}/app.css" ) );
		wp_enqueue_script(
			'naturedex',
			"{$uri}/app.js",
			array(),
			(string) filemtime( "{$dist}/app.js" ),
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
	}
);

add_action(
	'wp_head',
	static function () {
		$description = 'A little curiosity. A whole wild world. Explore Virginia’s wildlife, keep a field collection, and read field notes in Naturedex.';
		if ( is_singular( 'post' ) ) {
			$description = get_the_excerpt();
		}
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
		echo '<meta name="theme-color" content="#254b3b">' . "\n";
		printf( '<link rel="icon" type="image/svg+xml" href="%s">' . "\n", esc_url( get_theme_file_uri( 'assets/favicon.svg' ) ) );
		printf(
			'<link rel="preload" href="%s" as="font" type="font/ttf" crossorigin>' . "\n",
			esc_url( get_theme_file_uri( 'assets/fonts/Silkscreen-Regular.ttf' ) )
		);
	},
	1
);

add_filter(
	'document_title_separator',
	static fn() => '·'
);

add_filter(
	'pre_get_document_title',
	static function ( string $title ): string {
		$labels = array(
			'guide'   => 'Virginia field guide',
			'map'     => 'Region map',
			'journal' => 'Field journal',
			'editor'  => 'Writing desk',
			'about'   => 'About',
			'404'     => 'Not found',
		);
		$view   = naturedex_view();
		$name   = $labels[ $view ] ?? wp_strip_all_tags( get_the_title() );
		return '' === $name ? $title : "{$name} · Naturedex";
	}
);

// The Writing desk is the only page that needs a session.
add_action(
	'template_redirect',
	static function () {
		if ( 'editor' !== naturedex_view() ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			nocache_headers();
			wp_safe_redirect( wp_login_url( home_url( '/editor' ) ) );
			exit;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die(
				esc_html__( 'Your account does not have access to the writing desk.', 'naturedex' ),
				esc_html__( 'Writing desk', 'naturedex' ),
				array(
					'response'  => 403,
					'link_url'  => esc_url( home_url( '/' ) ),
					'link_text' => esc_html__( 'Return to the field guide', 'naturedex' ),
				)
			);
		}
	}
);

/*
 * Caching contract with CloudFront: public pages hold nothing specific to a
 * visitor, so the CDN may keep them briefly. Signed-in requests, previews, and
 * the Writing desk must never be stored.
 */
add_action(
	'template_redirect',
	static function () {
		if ( is_user_logged_in() || is_preview() || 'editor' === naturedex_view() ) {
			nocache_headers();
			return;
		}
		$shared_seconds = is_404() ? 60 : 300;
		header( "Cache-Control: public, max-age=0, s-maxage={$shared_seconds}" );
	},
	20
);

add_action(
	'login_enqueue_scripts',
	static function () {
		printf(
			'<style>body.login{background:#f7f7f0}#login h1 a{background-image:url(%s);background-size:64px;width:64px;height:64px}</style>',
			esc_url( get_theme_file_uri( 'assets/favicon.svg' ) )
		);
	}
);
add_filter( 'login_headerurl', static fn() => home_url( '/' ) );
add_filter( 'login_headertext', static fn() => 'Naturedex' );
