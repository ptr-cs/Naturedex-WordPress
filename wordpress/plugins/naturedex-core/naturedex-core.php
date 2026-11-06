<?php
/**
 * Plugin Name:       Naturedex Core
 * Description:       Content model, REST API, editorial tools, and seed data for the Naturedex Virginia field guide.
 * Version:           0.1.0
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * License:           GPL-2.0-or-later
 * Text Domain:       naturedex
 *
 * @package Naturedex
 */

namespace Naturedex;

defined( 'ABSPATH' ) || exit;

const VERSION = '0.1.0';
const FILE    = __FILE__;
const DIR     = __DIR__;

require_once DIR . '/includes/class-content-model.php';
require_once DIR . '/includes/class-content-format.php';
require_once DIR . '/includes/class-records.php';
require_once DIR . '/includes/class-rest.php';
require_once DIR . '/includes/class-admin.php';
require_once DIR . '/includes/class-hardening.php';
require_once DIR . '/includes/class-seeder.php';
require_once DIR . '/includes/class-status.php';

Content_Model::register_hooks();
Rest::register_hooks();
Admin::register_hooks();
Hardening::register_hooks();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once DIR . '/includes/class-cli.php';
	\WP_CLI::add_command( 'naturedex', CLI::class );
}

register_activation_hook(
	__FILE__,
	static function () {
		Content_Model::register();
		flush_rewrite_rules( false );
	}
);
register_deactivation_hook( __FILE__, static fn() => flush_rewrite_rules( false ) );
