<?php
/**
 * Production configuration. Every value comes from the environment, which ECS
 * fills from Secrets Manager; this file holds no secrets.
 *
 * @package Naturedex
 */

function naturedex_env( string $name ): string {
	$value = getenv( $name );
	if ( false === $value || '' === $value ) {
		throw new RuntimeException( "Missing required environment variable {$name}." );
	}
	return $value;
}

define( 'DB_NAME', naturedex_env( 'NATUREDEX_DB_NAME' ) );
define( 'DB_USER', naturedex_env( 'NATUREDEX_DB_USER' ) );
define( 'DB_PASSWORD', naturedex_env( 'NATUREDEX_DB_PASSWORD' ) );
define( 'DB_HOST', naturedex_env( 'NATUREDEX_DB_HOST' ) );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
// The instance requires TLS. The traffic stays inside the VPC, and verifying the
// certificate would additionally need the RDS CA bundle in the image.
define( 'MYSQL_CLIENT_FLAGS', MYSQLI_CLIENT_SSL | MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT );

$table_prefix = 'wp_';

define( 'WP_HOME', naturedex_env( 'NATUREDEX_HOME_URL' ) );
define( 'WP_SITEURL', WP_HOME );

// Only CloudFront can reach this container. It terminates HTTPS and talks to the
// load balancer over HTTP with the load balancer's own hostname, so WordPress
// has to be told the address visitors actually use.
$_SERVER['HTTPS']     = 'on';
$_SERVER['HTTP_HOST'] = parse_url( WP_HOME, PHP_URL_HOST );
define( 'FORCE_SSL_ADMIN', true );

// A fixed hash gives the session cookie a known name, so CloudFront can keep
// signed-in requests out of the shared cache. See infra/lib/naturedex-stack.ts.
define( 'COOKIEHASH', 'naturedex' );

// All keys derive from one secret, so rotating it signs everyone out.
$naturedex_secret = naturedex_env( 'NATUREDEX_AUTH_SECRET' );
foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as $naturedex_key ) {
	define( $naturedex_key, hash_hmac( 'sha256', $naturedex_key, $naturedex_secret ) );
}
unset( $naturedex_secret, $naturedex_key );

define( 'WP_ENVIRONMENT_TYPE', 'production' );
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISALLOW_FILE_EDIT', true );
define( 'DISALLOW_FILE_MODS', true );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
// EventBridge runs cron on a schedule. CDN caching means visits can't be relied on.
define( 'DISABLE_WP_CRON', true );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
