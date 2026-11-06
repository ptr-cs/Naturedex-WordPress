import { startSite } from '../scripts/playground.mjs';

// The site lives in memory, so tests can never touch a working site.
const users = `
foreach ( array( 'contributor', 'subscriber' ) as $role ) {
	$id = wp_create_user( "test-{$role}", 'test-password', "{$role}@example.test" );
	( new WP_User( $id ) )->set_role( $role );
}
`;
// Stands in for GitHub, including hostile values the site must discard. The
// query parameter simulates an outage, which only this test server supports.
const statusFixture = `<?php
add_action( 'init', static function () {
	if ( isset( $_GET['naturedex_test_status'] ) ) {
		update_option( 'naturedex_test_status', sanitize_key( $_GET['naturedex_test_status'] ) );
		delete_transient( 'naturedex_status_report' );
	}
} );
add_filter( 'pre_http_request', static function ( $response, $args, $url ) {
	$reply = static fn( $body ) => array(
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'body'     => wp_json_encode( $body ),
		'headers'  => array(),
		'cookies'  => array(),
	);
	$is_tests   = str_contains( $url, 'status.json' );
	$is_deploys = str_contains( $url, 'deploy.yml' );
	if ( ! $is_tests && ! $is_deploys ) {
		return $response;
	}
	if ( 'down' === get_option( 'naturedex_test_status' ) ) {
		return new WP_Error( 'http_request_failed', 'Simulated outage' );
	}
	if ( $is_tests ) {
		$failed = 'failing' === get_option( 'naturedex_test_status' ) ? 2 : 0;
		return $reply(
			array(
				'at'      => '2026-11-06T18:00:00Z',
				'commit'  => 'abcdef1',
				'image'   => 'passed',
				'suites'  => array(
					'unit'           => array( 'passed' => 18, 'failed' => $failed, 'name' => '<script>window.pwned=1</script>' ),
					'browser'        => array( 'passed' => 35, 'failed' => 0 ),
					'accessibility'  => array( 'passed' => 7, 'failed' => 0 ),
					'infrastructure' => array( 'passed' => 23, 'failed' => 0 ),
					'secret-suite'   => array( 'passed' => 1, 'failed' => 0 ),
				),
				'history' => array( array( 'at' => '2026-11-06T18:00:00Z', 'ok' => true ) ),
				'error'   => 'arn:aws:iam::123456789012:role/leaked in us-east-1',
			)
		);
	}
	return $reply(
		array(
			'workflow_runs' => array(
				array(
					'conclusion'     => 'success',
					'run_started_at' => '2026-11-06T17:00:00Z',
					'updated_at'     => '2026-11-06T17:05:00Z',
					'head_sha'       => 'abcdef1234567890',
					'actor'          => array( 'login' => 'leaked-login' ),
					'html_url'       => 'https://example.test/leaked-url',
				),
				array(
					'conclusion'     => 'failure',
					'run_started_at' => '2026-11-06T16:00:00Z',
					'updated_at'     => '2026-11-06T16:02:00Z',
					'head_sha'       => '<img src=x onerror=alert(1)>',
				),
			),
		)
	);
}, 10, 3 );
`;

// Seeding again through WP-CLI exercises the command the production bootstrap
// uses, and the API tests then prove it created no duplicates.
const site = startSite({
  port: Number(process.env.PORT),
  extraPhp: users,
  extraSteps: [
    { step: 'wp-cli', command: 'wp naturedex seed' },
    {
      step: 'writeFile',
      path: '/wordpress/wp-content/mu-plugins/naturedex-test-status.php',
      data: statusFixture,
    },
  ],
});
site.once('exit', (code) => process.exit(code ?? 1));
for (const signal of ['SIGINT', 'SIGTERM'])
  process.on(signal, () => site.kill(signal));
