// Fails when the WordPress release this site runs needs replacing: WordPress.org
// lists it as insecure, or doesn't list it at all. A scheduled workflow runs
// this daily, so a failure reaches the repository owner by email. It never
// blocks CI or a deploy.
import { readFileSync } from 'node:fs';

const read = (path) => readFileSync(new URL(`../${path}`, import.meta.url), 'utf8');
const pinned = /WORDPRESS_VERSION = '([^']+)'/.exec(read('scripts/playground.mjs'))?.[1];
const image = /^FROM wordpress:(\d+\.\d+(?:\.\d+)?)-/m.exec(read('docker/Dockerfile'))?.[1];
if (!pinned || pinned !== image) {
  console.error(`The image (${image}) and the tests (${pinned}) must use the same WordPress release.`);
  process.exit(1);
}

// Every release WordPress.org knows, marked "latest", "outdated" or "insecure".
const response = await fetch('https://api.wordpress.org/core/stable-check/1.0/', { signal: AbortSignal.timeout(30_000) });
if (!response.ok) throw new Error(`api.wordpress.org answered ${response.status}`);
const releases = await response.json();
const latest = Object.keys(releases).filter((version) => releases[version] === 'latest');
const state = releases[pinned];

console.log(`Running WordPress ${pinned} (${state ?? 'unknown'}). Latest: ${latest.join(', ') || 'unknown'}.`);
const problem =
  state === undefined ? `WordPress ${pinned} is not in WordPress.org's release list.`
  : state === 'insecure' ? `WordPress ${pinned} is marked insecure.`
  : null;
if (problem) {
  console.error(problem);
  console.error('Update docker/Dockerfile and scripts/playground.mjs, run npm run verify, deploy, then run `wp core update-db` as a one-off task.');
  process.exit(1);
}
if (state === 'outdated') console.log('A newer release is out, but this one is not marked insecure.');
console.log('No security release is waiting.');
