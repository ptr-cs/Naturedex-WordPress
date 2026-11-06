import { request } from '@playwright/test';
import { mkdirSync } from 'node:fs';

const baseURL = 'http://127.0.0.1:4322';

async function signIn(name: string, log: string, pwd: string) {
  const context = await request.newContext({ baseURL });
  await context.get('/wp-login.php');
  const response = await context.post('/wp-login.php', {
    form: {
      log,
      pwd,
      'wp-submit': 'Log In',
      redirect_to: `${baseURL}/wp-admin/`,
      testcookie: '1',
    },
    maxRedirects: 0,
  });
  if (response.status() !== 302)
    throw new Error(`Could not sign in as ${log}: ${response.status()}`);
  await context.storageState({ path: `tests/.auth/${name}.json` });
  await context.dispose();
}

export default async function globalSetup() {
  mkdirSync('tests/.auth', { recursive: true });
  await signIn('admin', 'admin', 'password');
  await signIn('contributor', 'test-contributor', 'test-password');
  await signIn('subscriber', 'test-subscriber', 'test-password');
}
