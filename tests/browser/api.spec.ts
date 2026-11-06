import {
  expect,
  request as playwrightRequest,
  test,
  type APIRequestContext,
} from '@playwright/test';

const api = '/wp-json/naturedex/v1';

/** A nonce is what a signed-in browser gets from the Writing desk. */
async function nonceFor(context: APIRequestContext) {
  const response = await context.get('/wp-admin/admin-ajax.php?action=rest-nonce');
  expect(response.ok()).toBe(true);
  return (await response.text()).trim();
}

async function session(state?: string) {
  const context = await playwrightRequest.newContext({
    baseURL: 'http://127.0.0.1:4322',
    storageState: state
      ? `tests/.auth/${state}.json`
      : { cookies: [], origins: [] },
  });
  return { context, nonce: state ? await nonceFor(context) : '' };
}

function fieldNote(overrides: Record<string, unknown> = {}) {
  const suffix = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
  return {
    title: `API field note ${suffix}`,
    slug: `api-field-note-${suffix}`,
    excerpt: 'A record created in the isolated test site.',
    body: '## A repeatable field test\n\nThis article exercises actual persistent CMS storage.',
    status: 'draft',
    category: 'Field Notes',
    relatedSpeciesIds: [],
    ...overrides,
  };
}

test.describe('editorial API', () => {
  let headers: Record<string, string>;
  test.beforeEach(async ({ request }) => {
    headers = { 'X-WP-Nonce': await nonceFor(request) };
  });

  test('CMS API persists create, draft, publish, update and delete through WordPress', async ({
    request,
  }) => {
    const note = fieldNote();
    let id: string | undefined;
    try {
      const created = await request.post(`${api}/posts`, { headers, data: note });
      expect(created.status()).toBe(201);
      const record = await created.json();
      id = String(record.id);
      expect(record.slug).toBe(note.slug);
      expect(record.status).toBe('draft');
      expect(record.body).toBe(note.body);

      const anonymous = await session();
      const hasPost = (posts: Array<{ id: unknown }>) =>
        posts.some((post) => String(post.id) === id);
      expect(hasPost(await (await anonymous.context.get(`${api}/posts`)).json())).toBe(false);
      expect((await anonymous.context.get(`${api}/posts/${id}`)).status()).toBe(404);
      expect(
        hasPost(await (await request.get(`${api}/posts?includeDrafts=1`, { headers })).json()),
      ).toBe(true);

      const update = await request.patch(`${api}/posts/${id}`, {
        headers,
        data: { title: `Updated ${note.title}`, status: 'published' },
      });
      expect(update.ok()).toBe(true);
      const updated = await update.json();
      expect(updated.title).toBe(`Updated ${note.title}`);
      expect(updated.status).toBe('published');
      expect(updated.publishedAt).toBeTruthy();
      expect(hasPost(await (await anonymous.context.get(`${api}/posts`)).json())).toBe(true);
      await anonymous.context.dispose();

      const removed = await request.delete(`${api}/posts/${id}`, { headers });
      expect(removed.status()).toBe(204);
      expect(
        hasPost(await (await request.get(`${api}/posts?includeDrafts=1`, { headers })).json()),
      ).toBe(false);
      id = undefined;
    } finally {
      if (id) await request.delete(`${api}/posts/${id}`, { headers });
    }
  });

  test('CMS API rejects incomplete content without creating a post', async ({
    request,
  }) => {
    const before = await (await request.get(`${api}/posts?includeDrafts=1`, { headers })).json();
    const invalid = await request.post(`${api}/posts`, {
      headers,
      data: { title: '', slug: '../escape', body: '' },
    });
    expect(invalid.status()).toBe(422);
    const after = await (await request.get(`${api}/posts?includeDrafts=1`, { headers })).json();
    expect(after.length).toBe(before.length);
  });

  test('CMS API rejects cross-origin writes', async ({ request }) => {
    const response = await request.post(`${api}/posts`, {
      headers: { ...headers, Origin: 'https://unrelated.example' },
      data: fieldNote(),
    });
    expect(response.status()).toBe(403);
  });

  test('CMS API validates nullable fields instead of failing in persistence', async ({
    request,
  }) => {
    const response = await request.post(`${api}/posts`, {
      headers,
      data: fieldNote({ relatedSpeciesIds: null }),
    });
    expect(response.status()).toBe(422);
  });

  test('CMS API rejects unknown related species and duplicate slugs', async ({
    request,
  }) => {
    const unknown = await request.post(`${api}/posts`, {
      headers,
      data: fieldNote({ relatedSpeciesIds: ['not-an-animal'] }),
    });
    expect(unknown.status()).toBe(422);
    const duplicate = await request.post(`${api}/posts`, {
      headers,
      data: fieldNote({ slug: 'the-art-of-noticing' }),
    });
    expect(duplicate.status()).toBe(422);
    expect((await duplicate.json()).message).toContain('already in use');
  });

  test('article text round-trips exactly and raw HTML stays inert', async ({
    request,
    page,
  }) => {
    const body =
      '## Tom & Jerry <b>heading</b>\n\nA <script>window.pwned = true</script> tag, an &amp; entity, and "quotes".\n\n<!-- wp:html --><img src=x onerror=alert(1)><!-- /wp:html -->';
    const note = fieldNote({ body, status: 'published' });
    const created = await request.post(`${api}/posts`, { headers, data: note });
    expect(created.status()).toBe(201);
    const record = await created.json();
    try {
      expect(record.body).toBe(body);
      expect(record.richContent).toBe(false);
      await page.goto(`/journal/${note.slug}`);
      await expect(page.locator('.article-body')).toContainText('<script>');
      await expect(page.locator('.article-body script, .article-body img')).toHaveCount(0);
      expect(await page.evaluate(() => 'pwned' in window)).toBe(false);
    } finally {
      await request.delete(`${api}/posts/${record.id}`, { headers });
    }
  });

  test('renaming a species keeps the field notes that link to it', async ({
    request,
  }) => {
    const related = fieldNote({ relatedSpeciesIds: ['red-fox', 'bald-eagle'] });
    const created = await (await request.post(`${api}/posts`, { headers, data: related })).json();
    const [fox] = await (await request.get('/wp-json/wp/v2/species?slug=red-fox')).json();
    const rename = (slug: string) =>
      request.post(`/wp-json/wp/v2/species/${fox.id}`, { headers, data: { slug } });
    try {
      expect(created.relatedSpeciesIds).toEqual(['red-fox', 'bald-eagle']);
      expect((await rename('renamed-fox')).ok()).toBe(true);
      const record = await (await request.get(`${api}/posts/${created.id}`, { headers })).json();
      expect(record.relatedSpeciesIds).toEqual(['renamed-fox', 'bald-eagle']);
    } finally {
      await rename('red-fox');
      await request.delete(`${api}/posts/${created.id}`, { headers });
    }
  });
});

test.describe('who may write', () => {
  test('anonymous visitors cannot read drafts or write', async () => {
    const { context } = await session();
    expect((await context.get(`${api}/posts?includeDrafts=1`)).status()).toBe(401);
    expect((await context.post(`${api}/posts`, { data: fieldNote() })).status()).toBe(401);
    expect((await context.delete(`${api}/posts/1`)).status()).toBe(401);
    await context.dispose();
  });

  test('a session cookie without a nonce cannot write', async ({ request }) => {
    const response = await request.post(`${api}/posts`, { data: fieldNote() });
    expect(response.status()).toBe(401);
  });

  test('subscribers cannot write', async () => {
    const { context, nonce } = await session('subscriber');
    const response = await context.post(`${api}/posts`, {
      headers: { 'X-WP-Nonce': nonce },
      data: fieldNote(),
    });
    expect(response.status()).toBe(403);
    await context.dispose();
  });

  test('contributors can draft but cannot publish or touch others’ notes', async ({
    request,
  }) => {
    const { context, nonce } = await session('contributor');
    const headers = { 'X-WP-Nonce': nonce };
    const published = await context.post(`${api}/posts`, {
      headers,
      data: fieldNote({ status: 'published' }),
    });
    expect(published.status()).toBe(403);

    const draft = await context.post(`${api}/posts`, { headers, data: fieldNote() });
    expect(draft.status()).toBe(201);
    const { id } = await draft.json();
    expect(
      (await context.patch(`${api}/posts/${id}`, { headers, data: { status: 'published' } })).status(),
    ).toBe(403);

    const seeded = await (await request.get(`${api}/posts/the-art-of-noticing`)).json();
    expect(
      (await context.patch(`${api}/posts/${seeded.id}`, { headers, data: { title: 'Taken over' } })).status(),
    ).toBe(403);
    expect((await context.delete(`${api}/posts/${seeded.id}`, { headers })).status()).toBe(403);

    expect((await context.delete(`${api}/posts/${id}`, { headers })).status()).toBe(204);
    await context.dispose();
  });

  test('anonymous visitors cannot list user accounts', async () => {
    const { context } = await session();
    expect((await context.get('/wp-json/wp/v2/users')).status()).toBe(404);
    await context.dispose();
  });
});

test.describe('public read API', () => {
  test('serves the seeded catalog and journal', async () => {
    const { context } = await session();
    const species = await (await context.get(`${api}/species`)).json();
    expect(species).toHaveLength(24);
    expect(species[0].dexNumber).toBe(1);
    const regions = await (await context.get(`${api}/regions`)).json();
    expect(regions.map((region: { id: string }) => region.id)).toEqual([
      'mountains',
      'valley',
      'piedmont',
      'coastal',
    ]);
    expect(regions[0].name).toBe('Blue Ridge & Appalachians');
    const posts = await (await context.get(`${api}/posts`)).json();
    expect(posts.map((post: { slug: string }) => post.slug)).toEqual([
      'the-art-of-noticing',
      'read-the-water',
      'beyond-the-bright-wings',
    ]);
    await context.dispose();
  });
});
