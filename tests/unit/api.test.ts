import { afterEach, describe, expect, it, vi } from 'vitest';
import { ApiError, createClient } from '../../frontend/lib/api';

const config = {
  root: 'https://example.test/wp-json/naturedex/v1/',
  nonce: 'stale',
  nonceUrl: 'https://example.test/wp-admin/admin-ajax.php?action=rest-nonce',
};
const json = (status: number, body: unknown) =>
  new Response(JSON.stringify(body), { status });

afterEach(() => vi.unstubAllGlobals());

describe('the Writing desk REST client', () => {
  it('sends the nonce and same-origin credentials', async () => {
    const fetchMock = vi.fn(async () => json(200, []));
    vi.stubGlobal('fetch', fetchMock);
    await createClient(config)('posts');
    expect(fetchMock).toHaveBeenCalledWith(
      `${config.root}posts`,
      expect.objectContaining({
        credentials: 'same-origin',
        headers: { 'X-WP-Nonce': 'stale' },
      }),
    );
  });

  it('renews an expired nonce and retries once, so open work is not lost', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(json(403, { code: 'rest_cookie_invalid_nonce' }))
      .mockResolvedValueOnce(new Response('abc123'))
      .mockResolvedValueOnce(json(200, { id: '7' }));
    vi.stubGlobal('fetch', fetchMock);
    await expect(createClient(config)('posts/7')).resolves.toEqual({ id: '7' });
    expect(fetchMock.mock.calls[2][1].headers).toEqual({
      'X-WP-Nonce': 'abc123',
    });
  });

  it('reports the server message for a validation failure', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => json(422, { message: 'That slug is already in use.' })),
    );
    await expect(createClient(config)('posts')).rejects.toEqual(
      new ApiError('That slug is already in use.', 422),
    );
  });

  it('tells the author when their session has ended', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => json(401, { message: 'Sign in.' })),
    );
    await expect(createClient(config)('posts')).rejects.toThrow(/session/);
  });

  it('treats a 204 delete as success without parsing a body', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => new Response(null, { status: 204 })),
    );
    await expect(createClient(config)('posts/7')).resolves.toBeUndefined();
  });
});
