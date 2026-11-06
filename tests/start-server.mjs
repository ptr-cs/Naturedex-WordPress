import { startSite } from '../scripts/playground.mjs';

// The site lives in memory, so tests can never touch a working site.
const users = `
foreach ( array( 'contributor', 'subscriber' ) as $role ) {
	$id = wp_create_user( "test-{$role}", 'test-password', "{$role}@example.test" );
	( new WP_User( $id ) )->set_role( $role );
}
`;
// Seeding again through WP-CLI exercises the command the production bootstrap
// uses, and the API tests then prove it created no duplicates.
const site = startSite({
  port: Number(process.env.PORT),
  extraPhp: users,
  extraSteps: [{ step: 'wp-cli', command: 'wp naturedex seed' }],
});
site.once('exit', (code) => process.exit(code ?? 1));
for (const signal of ['SIGINT', 'SIGTERM'])
  process.on(signal, () => site.kill(signal));
