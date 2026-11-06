import { spawn } from 'node:child_process';
import { root, startSite } from './playground.mjs';

const port = Number(process.env.PORT || 4321);
const url = `http://127.0.0.1:${port}`;

const build = spawn(process.execPath, ['scripts/build-frontend.mjs'], {
  cwd: root,
  stdio: 'inherit',
});
await new Promise((done) => build.once('exit', done));

const site = startSite({ port, dataDir: `${root}.wp-local` });
site.once('exit', (code) => process.exit(code ?? 0));
for (const signal of ['SIGINT', 'SIGTERM'])
  process.on(signal, () => site.kill(signal));

await site.ready;
console.log(`
Naturedex is ready at ${url}
Writing desk: ${url}/editor (username admin, password password; local only)
After changing frontend/, run: npm run build:frontend
Press Ctrl+C to stop.
`);
