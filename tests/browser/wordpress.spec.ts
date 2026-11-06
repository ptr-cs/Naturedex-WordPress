import { expect, test } from '@playwright/test';
import { contentSecurityPolicy } from '../../infra/lib/csp';

const anonymous = { cookies: [], origins: [] };

test.describe('for signed-out visitors', () => {
  test.use({ storageState: anonymous });

  test('the writing desk sends visitors to sign in and returns them after', async ({
    page,
  }) => {
    await page.goto('/editor');
    await expect(page).toHaveURL(/wp-login\.php/);
    await page.getByLabel('Username or Email Address').fill('admin');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log In' }).click();
    await expect(
      page.getByRole('heading', { name: 'Your writing desk.' }),
    ).toBeVisible();
  });

  test('public pages are cacheable and carry no session data', async ({
    request,
  }) => {
    for (const route of ['/', '/map', '/journal', '/about', '/journal/read-the-water']) {
      const response = await request.get(route);
      expect(response.status(), route).toBe(200);
      expect(response.headers()['cache-control'], route).toContain('s-maxage=300');
      expect(response.headers()['set-cookie'], route).toBeUndefined();
      expect(await response.text(), route).not.toContain('wp_rest');
    }
  });

  test('missing addresses return real 404s with a way back', async ({ page }) => {
    const missing = await page.goto('/no-such-trail');
    expect(missing?.status()).toBe(404);
    await expect(
      page.getByRole('link', { name: 'Return to the field guide' }),
    ).toBeVisible();
    const article = await page.goto('/journal/no-such-note');
    expect(article?.status()).toBe(404);
    await expect(page.getByRole('alert')).toContainText('unavailable');
  });

  test('species have shareable pages with sources', async ({ page }) => {
    await page.goto('/species/red-fox');
    await expect(page.getByRole('heading', { level: 1, name: 'Red Fox' })).toBeVisible();
    await expect(page.getByRole('link', { name: /Source 1/ })).toBeVisible();
    await expect(page.locator('.detail-regions span').first()).toHaveText('Mountains');
    await expect(page.getByRole('button', { name: 'Mark as seen: Red Fox' })).toBeVisible();
  });

  test('the about page is WordPress content', async ({ page }) => {
    await page.goto('/about');
    await expect(
      page.getByRole('heading', { name: 'Made for the curious.' }),
    ).toBeVisible();
    await expect(
      page.getByRole('heading', { name: 'Where your notes live' }),
    ).toBeVisible();
  });

  test('comments, pingbacks, and XML-RPC are closed', async ({ request }) => {
    const xmlrpc = await request.post('/xmlrpc.php', {
      headers: { 'Content-Type': 'text/xml' },
      data: '<?xml version="1.0"?><methodCall><methodName>demo.sayHello</methodName></methodCall>',
    });
    expect(await xmlrpc.text()).toContain('faultCode');
    const author = await request.get('/?author=1', { maxRedirects: 0 });
    expect(author.status()).toBe(301);
    expect(author.headers().location).not.toContain('admin');
  });
});

test.describe('under the production Content-Security-Policy', () => {
  test.use({ storageState: anonymous });

  test('public pages work and report no violations', async ({ page }) => {
    const violations: string[] = [];
    page.on('console', (message) => {
      if (/Content Security Policy/i.test(message.text()))
        violations.push(message.text());
    });
    await page.route('**/*', async (route) => {
      const response = await route.fetch();
      await route.fulfill({
        response,
        headers: {
          ...response.headers(),
          'content-security-policy': contentSecurityPolicy,
        },
      });
    });
    for (const path of ['/map', '/journal', '/journal/read-the-water', '/about', '/species/red-fox']) {
      await page.goto(path);
      await expect(page.getByRole('main')).toBeVisible();
    }
    await page.goto('/');
    await page.getByRole('button', { name: 'View Red Fox', exact: true }).click();
    await expect(page.getByRole('dialog', { name: 'Red Fox' })).toBeVisible();
    expect(violations).toEqual([]);
  });
});

test.describe('without JavaScript', () => {
  test.use({ javaScriptEnabled: false, storageState: anonymous });

  test('the guide, entries, and journal are all readable', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.species-card')).toHaveCount(24);
    await expect(page.getByLabel('Search the field guide')).toBeHidden();
    await page.getByRole('link', { name: 'View Red Fox' }).click();
    await expect(page).toHaveURL(/\/species\/red-fox$/);
    await expect(page.getByRole('heading', { level: 1, name: 'Red Fox' })).toBeVisible();
    await page.goto('/journal/the-art-of-noticing');
    await expect(
      page.getByRole('heading', { name: 'Choose one small place' }),
    ).toBeVisible();
  });
});

test('the writing desk keeps block-editor content safe', async ({
  page,
  request,
}) => {
  const nonce = await (await request.get('/wp-admin/admin-ajax.php?action=rest-nonce')).text();
  const created = await request.post('/wp-json/wp/v2/posts', {
    headers: { 'X-WP-Nonce': nonce.trim() },
    data: {
      title: 'A note with a list',
      status: 'draft',
      excerpt: 'Written in the block editor.',
      content:
        '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>First</li><!-- /wp:list-item --></ul><!-- /wp:list -->',
    },
  });
  const { id } = await created.json();
  try {
    const record = await (
      await request.get(`/wp-json/naturedex/v1/posts/${id}`, {
        headers: { 'X-WP-Nonce': nonce.trim() },
      })
    ).json();
    expect(record.richContent).toBe(true);
    const overwrite = await request.patch(`/wp-json/naturedex/v1/posts/${id}`, {
      headers: { 'X-WP-Nonce': nonce.trim() },
      data: { body: 'Flattened.' },
    });
    expect(overwrite.status()).toBe(409);

    await page.goto('/editor');
    await page.getByRole('button', { name: 'Edit A note with a list', exact: true }).click();
    await expect(page.getByRole('link', { name: 'Edit the body in WordPress' })).toBeVisible();
    await expect(page.getByLabel('Body', { exact: true })).toBeHidden();
  } finally {
    await request.delete(`/wp-json/wp/v2/posts/${id}?force=true`, {
      headers: { 'X-WP-Nonce': nonce.trim() },
    });
  }
});

test('correcting a species in wp-admin updates the site everywhere', async ({
  page,
  request,
}) => {
  const [fox] = await (await request.get('/wp-json/wp/v2/species?slug=red-fox')).json();
  const edit = async (activity: string, weightMax: string) => {
    await page.goto(`/wp-admin/post.php?post=${fox.id}&action=edit`);
    await page.getByLabel('Active hours').fill(activity);
    await page.getByLabel('Maximum weight (kg)').fill(weightMax);
    await page.locator('#publish').click();
    await expect(page.locator('#message')).toContainText('updated');
  };
  await edit('Nocturnal and crepuscular', '');
  try {
    await page.goto('/species/red-fox');
    await expect(page.getByText('Nocturnal and crepuscular')).toBeVisible();
    // A removed measurement must read as unknown, never as zero.
    await expect(page.locator('.stat-grid')).toContainText('See field notes');
    await page.goto('/');
    await expect(
      page.locator('[data-species-card="red-fox"] .card-statline'),
    ).toContainText('Nocturnal and crepuscular');
  } finally {
    await edit('Crepuscular', '14.1');
  }
  const [restored] = (await (await request.get('/wp-json/naturedex/v1/species')).json()).filter(
    (entry: { id: string }) => entry.id === 'red-fox',
  );
  expect(restored.activity).toBe('Crepuscular');
  expect(restored.size.weightKg).toEqual([3.2, 14.1]);
});

test('regions keep their map fields in wp-admin', async ({ page }) => {
  await page.goto('/wp-admin/edit-tags.php?taxonomy=naturedex_region&post_type=species');
  await page.getByRole('link', { name: 'Shenandoah Valley', exact: true }).click();
  await expect(page.getByLabel('Short name')).toHaveValue('Valley');
  await expect(page.getByLabel('Legend color (hex)')).toHaveValue('#c2c778');
});
