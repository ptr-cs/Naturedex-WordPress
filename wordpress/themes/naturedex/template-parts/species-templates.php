<?php
/**
 * Inert copies of each entry for TypeScript to open as dialogs.
 *
 * @package Naturedex
 * @var array $args { species: array[] }
 */

defined( 'ABSPATH' ) || exit;

foreach ( $args['species'] as $naturedex_template_entry ) :
	?>
	<template data-species-detail="<?php echo esc_attr( $naturedex_template_entry['id'] ); ?>">
		<?php get_template_part( 'template-parts/species-detail', null, array( 'species' => $naturedex_template_entry ) ); ?>
	</template>
	<?php
endforeach;
