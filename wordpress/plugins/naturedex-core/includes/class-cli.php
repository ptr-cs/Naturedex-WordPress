<?php
/**
 * WP-CLI commands.
 *
 * @package Naturedex
 */

namespace Naturedex;

defined( 'ABSPATH' ) || exit;

final class CLI {
	/**
	 * Configures the site and imports any missing seed content.
	 *
	 * Existing records are never overwritten, and seed records you deleted are
	 * not restored.
	 *
	 * ## OPTIONS
	 *
	 * [--author=<user-id>]
	 * : The user who owns seeded field notes. Defaults to the first administrator.
	 *
	 * ## EXAMPLES
	 *
	 *     wp naturedex seed
	 */
	public function seed( array $args, array $assoc ): void {
		$summary = Seeder::run( (int) ( $assoc['author'] ?? 0 ) );
		\WP_CLI::success( sprintf( 'Imported %d species, %d field notes, and %d pages.', $summary['species'], $summary['posts'], $summary['pages'] ) );
	}
}
