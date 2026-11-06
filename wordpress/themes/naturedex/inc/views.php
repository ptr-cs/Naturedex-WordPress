<?php
/**
 * Maps WordPress's query state to the site's named views.
 *
 * @package Naturedex
 */

defined( 'ABSPATH' ) || exit;

/**
 * @return string guide, map, journal, article, species, editor, about, status, page, or 404.
 */
function naturedex_view(): string {
	if ( is_front_page() ) {
		return 'guide';
	}
	if ( is_home() || is_category() || is_tag() || is_date() ) {
		return 'journal';
	}
	if ( is_singular( 'post' ) ) {
		return 'article';
	}
	if ( is_singular( 'species' ) ) {
		return 'species';
	}
	foreach ( array( 'map', 'editor', 'about', 'status' ) as $slug ) {
		if ( is_page( $slug ) ) {
			return $slug;
		}
	}
	return is_page() ? 'page' : '404';
}

/**
 * A missing /journal/... address gets a journal-specific message.
 */
function naturedex_is_missing_article(): bool {
	$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
	return is_404() && str_starts_with( $path, '/journal/' );
}

/**
 * Text for the breadcrumb and page heading.
 *
 * @return array{crumb: string, eyebrow: string, title: string, description: string, stamp: string}
 */
function naturedex_heading(): array {
	$view     = naturedex_view();
	$journal  = array(
		'crumb'       => 'FIELD JOURNAL',
		'eyebrow'     => 'STORIES FROM THE OUTSIDE',
		'title'       => 'Notes from the field.',
		'description' => 'Small observations. Good questions. Stories worth stepping outside for.',
		'stamp'       => 'pen',
	);
	$headings = array(
		'guide'   => array(
			'crumb'       => 'FIELD GUIDE',
			'eyebrow'     => 'VIRGINIA, ONE DISCOVERY AT A TIME',
			'title'       => 'Meet your wild neighbors.',
			'description' => 'From the Blue Ridge to the bay. Get to know the wildlife just beyond your doorstep.',
			'stamp'       => 'globe',
		),
		'map'     => array(
			'crumb'       => 'REGION MAP',
			'eyebrow'     => 'FOLLOW YOUR CURIOSITY',
			'title'       => 'Adventure has a map.',
			'description' => 'From mountain trails to tidal shores. Pick a region and see who lives there.',
			'stamp'       => 'globe',
		),
		'journal' => $journal,
		'article' => array( 'title' => wp_strip_all_tags( get_the_title() ), 'stamp' => '' ) + $journal,
		'species' => array(
			'crumb'       => 'FIELD GUIDE',
			'eyebrow'     => 'A FIELD GUIDE ENTRY',
			'title'       => wp_strip_all_tags( get_the_title() ),
			'description' => '',
			'stamp'       => '',
		),
		'editor'  => array(
			'crumb'       => 'WRITING DESK',
			'eyebrow'     => 'A PLACE FOR YOUR OBSERVATIONS',
			'title'       => 'Your writing desk.',
			'description' => 'Turn an observation into a story. Save a draft, then publish when you’re ready.',
			'stamp'       => '',
		),
		'about'   => array(
			'crumb'       => 'ABOUT',
			'eyebrow'     => 'A FIELD GUIDE, WITH A LITTLE NOSTALGIA',
			'title'       => 'Made for the curious.',
			'description' => 'An original, pocket-sized guide to the animals that make Virginia home.',
			'stamp'       => 'globe',
		),
		'status'  => array(
			'crumb'       => 'SYSTEM STATUS',
			'eyebrow'     => 'FIELD STATION DIAGNOSTICS',
			'title'       => 'System status.',
			'description' => 'How the site, its tests, and its deployments are doing.',
			'stamp'       => '',
		),
		'page'    => array(
			'crumb'       => 'FIELD GUIDE',
			'eyebrow'     => 'NATUREDEX',
			'title'       => wp_strip_all_tags( get_the_title() ),
			'description' => '',
			'stamp'       => '',
		),
		'404'     => array(
			'crumb'       => 'FIELD GUIDE',
			'eyebrow'     => 'OFF THE MAP',
			'title'       => 'Lost in the tall grass.',
			'description' => 'That page isn’t in this field guide.',
			'stamp'       => '',
		),
	);
	if ( naturedex_is_missing_article() ) {
		return $journal;
	}
	return $headings[ $view ];
}
