import { spawn } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export const root = fileURLToPath(new URL('../', import.meta.url));

// Keep in step with the production image in docker/Dockerfile.
const WORDPRESS_VERSION = '7.1.4';
const PHP_VERSION = '8.4';

// Playground sets PHP_SELF to the request path. WordPress reads that as a request
// for itself and clears the 404 on unmatched addresses. Apache reports
// /index.php, so this shim exists locally only and is never deployed.
const apacheParity = `<?php
if ( 'index.php' === basename( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	$_SERVER['PHP_SELF'] = '/index.php';
}
`;

const seed = `<?php
require '/wordpress/wp-load.php';
wp_set_current_user( 1 );
wp_update_user( array( 'ID' => 1, 'display_name' => 'Naturedex Field Notes' ) );
Naturedex\\Seeder::run( 1 );
`;

/**
 * Starts WordPress on PHP-WASM, so the site needs no host PHP, MySQL, or Docker.
 * With dataDir the site persists between runs; without it the site lives in
 * memory and vanishes on exit, which is what isolates the browser tests.
 */
export function startSite({ port, dataDir, extraPhp = '', extraSteps = [] }) {
  const mount = (from, to) => ['--mount', `${resolve(root, from)}:${to}`];
  const content = '/wordpress/wp-content';
  const args = [
    'server',
    `--port=${port}`,
    `--php=${PHP_VERSION}`,
    `--wp=${WORDPRESS_VERSION}`,
    '--define', 'WP_ENVIRONMENT_TYPE', 'local',
    '--define-bool', 'WP_DEBUG', 'true',
    '--define-bool', 'WP_DEBUG_DISPLAY', 'true',
  ];

  const installed = dataDir && existsSync(join(dataDir, 'wp-config.php'));
  if (dataDir) {
    mkdirSync(dataDir, { recursive: true });
    args.push('--mount-before-install', `${resolve(dataDir)}:/wordpress`);
  }
  if (installed) {
    args.push('--wordpress-install-mode=do-not-attempt-installing');
  } else {
    const directory = mkdtempSync(join(tmpdir(), 'naturedex-blueprint-'));
    const blueprint = join(directory, 'blueprint.json');
    writeFileSync(
      blueprint,
      JSON.stringify({
        steps: [
          {
            step: 'writeFile',
            path: '/wordpress/wp-content/mu-plugins/naturedex-apache-parity.php',
            data: apacheParity,
          },
          {
            step: 'activatePlugin',
            pluginPath: 'naturedex-core/naturedex-core.php',
          },
          { step: 'activateTheme', themeFolderName: 'naturedex' },
          { step: 'runPHP', code: seed + extraPhp },
          ...extraSteps,
        ],
      }),
    );
    args.push(`--blueprint=${blueprint}`);
  }
  args.push(
    ...mount('wordpress/plugins/naturedex-core', `${content}/plugins/naturedex-core`),
    ...mount('wordpress/themes/naturedex', `${content}/themes/naturedex`),
  );

  const site = spawn(
    process.execPath,
    [resolve(root, 'node_modules/@wp-playground/cli/wp-playground.js'), ...args],
    { cwd: root, stdio: ['ignore', 'pipe', 'inherit'] },
  );
  // The server answers requests while the blueprint is still seeding, so a
  // successful response proves nothing. Only this line means setup finished.
  site.ready = new Promise((done) => {
    site.stdout.on('data', (chunk) => {
      process.stdout.write(chunk);
      if (chunk.toString().includes('Ready!')) done();
    });
  });
  return site;
}
