import { defineConfig, devices } from '@playwright/test';

const port = 4322;
export const baseURL = `http://127.0.0.1:${port}`;

export default defineConfig({
  testDir: './tests/browser',
  globalSetup: './tests/global-setup.ts',
  timeout: 60_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL,
    storageState: 'tests/.auth/admin.json',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off',
  },
  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1440, height: 1000 },
      },
    },
  ],
  webServer: {
    command: 'node tests/start-server.mjs',
    // Setup is finished only when Playground says so; see scripts/playground.mjs.
    wait: { stdout: /Ready! WordPress is running/ },
    stdout: 'pipe',
    env: { PORT: String(port) },
    reuseExistingServer: false,
    timeout: 180_000,
  },
});
