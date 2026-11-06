<?php
/**
 * Public system health report for the /status page.
 *
 * Everything from outside WordPress is treated as untrusted. Only the fields
 * named here are kept, labels come from this file, and nothing that would help
 * an attacker is ever read: no test names, errors, logs, versions, or
 * infrastructure details. A failing test shows up only as a number.
 *
 * @package Naturedex
 */

namespace Naturedex;

defined( 'ABSPATH' ) || exit;

final class Status {
	private const TESTS_URL   = 'https://raw.githubusercontent.com/ptr-cs/Naturedex-WordPress/status/status.json';
	private const DEPLOYS_URL = 'https://api.github.com/repos/ptr-cs/Naturedex-WordPress/actions/workflows/deploy.yml/runs?branch=main&per_page=10';

	private const CACHE_KEY     = 'naturedex_status_report';
	private const LAST_GOOD_KEY = 'naturedex_status_last_good';
	private const FRESH_SECONDS = 300;
	private const RETRY_SECONDS = 60;

	/** Scheduled work runs every 15 minutes, so an hour late means it's stuck. */
	private const CRON_LATE_SECONDS = 3600;

	private const SUITES = array(
		'unit'           => 'Unit tests',
		'browser'        => 'Browser and API tests',
		'accessibility'  => 'Accessibility scans',
		'infrastructure' => 'Infrastructure tests',
	);

	/**
	 * @return array{overall: string, checks: array, tests: ?array, deployments: array, stale: bool, checkedAt: int}
	 */
	public static function report(): array {
		$external = self::external();
		$checks   = self::site_checks();
		$tests    = $external['tests'];
		$deploys  = $external['deployments'];

		$healthy = ! in_array( false, array_column( $checks, 'ok' ), true )
			&& null !== $tests
			&& $tests['ok']
			&& ( ! $deploys || 'failed' !== $deploys[0]['result'] );

		return array(
			'overall'     => $healthy ? 'operational' : 'attention',
			'checks'      => $checks,
			'tests'       => $tests,
			'deployments' => $deploys,
			'stale'       => $external['stale'],
			'checkedAt'   => time(),
		);
	}

	/**
	 * Fetching is limited by the cache, not by traffic, so visitors can't use this
	 * page to send requests to GitHub. A failure falls back to the last good data.
	 */
	private static function external(): array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$tests   = self::normalize_tests( self::fetch_json( self::TESTS_URL ) );
		$deploys = self::normalize_deployments( self::fetch_json( self::DEPLOYS_URL ) );
		if ( null !== $tests && null !== $deploys ) {
			$fresh = array(
				'tests'       => $tests,
				'deployments' => $deploys,
				'stale'       => false,
			);
			set_transient( self::CACHE_KEY, $fresh, self::FRESH_SECONDS );
			update_option( self::LAST_GOOD_KEY, $fresh, false );
			return $fresh;
		}
		$last = get_option( self::LAST_GOOD_KEY );
		$data = array(
			'tests'       => $tests ?? ( is_array( $last ) ? $last['tests'] : null ),
			'deployments' => $deploys ?? ( is_array( $last ) ? $last['deployments'] : array() ),
			'stale'       => true,
		);
		set_transient( self::CACHE_KEY, $data, self::RETRY_SECONDS );
		return $data;
	}

	private static function fetch_json( string $url ): ?array {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 3,
				'redirection' => 0,
				'user-agent'  => 'Naturedex status page',
				'headers'     => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * @param array<string, mixed>|null $data Published test summary.
	 */
	private static function normalize_tests( ?array $data ): ?array {
		if ( null === $data || ! is_array( $data['suites'] ?? null ) ) {
			return null;
		}
		$suites = array();
		foreach ( self::SUITES as $key => $label ) {
			$suite    = is_array( $data['suites'][ $key ] ?? null ) ? $data['suites'][ $key ] : array();
			$suites[] = array(
				'label'  => $label,
				'passed' => self::count( $suite['passed'] ?? 0 ),
				'failed' => self::count( $suite['failed'] ?? 0 ),
			);
		}
		$image    = 'passed' === ( $data['image'] ?? null );
		$failed   = array_sum( array_column( $suites, 'failed' ) );
		$complete = ! in_array( 0, array_map( static fn( $suite ) => $suite['passed'] + $suite['failed'], $suites ), true );

		return array(
			'suites'   => $suites,
			'image'    => $image,
			'ok'       => 0 === $failed && $complete && $image,
			'commit'   => self::commit( $data['commit'] ?? '' ),
			'at'       => self::time( $data['at'] ?? '' ),
			'recent'   => self::normalize_history( $data['history'] ?? null ),
		);
	}

	/**
	 * @return array<int, array{ok: bool, at: int}>
	 */
	private static function normalize_history( $history ): array {
		if ( ! is_array( $history ) ) {
			return array();
		}
		$runs = array();
		foreach ( array_slice( $history, 0, 20 ) as $run ) {
			if ( is_array( $run ) ) {
				$runs[] = array(
					'ok' => true === ( $run['ok'] ?? null ),
					'at' => self::time( $run['at'] ?? '' ),
				);
			}
		}
		return $runs;
	}

	/**
	 * @param array<string, mixed>|null $data GitHub's list of workflow runs.
	 * @return array<int, array<string, mixed>>|null
	 */
	private static function normalize_deployments( ?array $data ): ?array {
		if ( null === $data || ! is_array( $data['workflow_runs'] ?? null ) ) {
			return null;
		}
		$deployments = array();
		foreach ( array_slice( $data['workflow_runs'], 0, 10 ) as $run ) {
			if ( ! is_array( $run ) ) {
				continue;
			}
			$started  = self::time( $run['run_started_at'] ?? '' );
			$finished = self::time( $run['updated_at'] ?? '' );
			$result   = array(
				'success'   => 'succeeded',
				'failure'   => 'failed',
				'cancelled' => 'cancelled',
				'timed_out' => 'failed',
			)[ $run['conclusion'] ?? '' ] ?? 'running';

			$deployments[] = array(
				'result'  => $result,
				'at'      => $started,
				'minutes' => 'running' === $result ? 0 : max( 1, (int) round( ( $finished - $started ) / 60 ) ),
				'commit'  => self::commit( $run['head_sha'] ?? '' ),
			);
		}
		return $deployments;
	}

	/**
	 * Yes-or-no checks of this running site, with no detail beyond the answer.
	 */
	private static function site_checks(): array {
		global $wpdb;
		$uploads = wp_upload_dir( null, false );
		$jobs    = _get_cron_array();
		$next    = is_array( $jobs ) && $jobs ? (int) array_key_first( $jobs ) : 0;

		return array(
			array(
				'label' => 'Website',
				'ok'    => true,
			),
			array(
				'label' => 'Database',
				'ok'    => 1 === (int) $wpdb->get_var( 'SELECT 1' ),
			),
			array(
				'label' => 'Media storage',
				'ok'    => wp_is_writable( $uploads['basedir'] ),
			),
			array(
				'label' => 'Scheduled jobs',
				'ok'    => 0 === $next || $next > time() - self::CRON_LATE_SECONDS,
			),
		);
	}

	private static function count( $value ): int {
		return is_int( $value ) ? max( 0, min( $value, 100000 ) ) : 0;
	}

	private static function commit( $value ): string {
		return is_string( $value ) && preg_match( '/^[0-9a-f]{7,40}$/D', $value ) ? substr( $value, 0, 7 ) : '';
	}

	private static function time( $value ): int {
		$time = is_string( $value ) ? strtotime( $value ) : false;
		return false === $time ? 0 : $time;
	}
}
