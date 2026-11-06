import type { DeskConfig } from './types';

export class ApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
  ) {
    super(message);
  }
}

/**
 * REST client for the Writing desk. WordPress nonces expire, so a desk left open
 * overnight fetches a fresh one and retries once rather than losing the author's work.
 */
export function createClient(config: DeskConfig) {
  let nonce = config.nonce;

  function send(path: string, init: RequestInit) {
    return fetch(`${config.root}${path}`, {
      ...init,
      credentials: 'same-origin',
      headers: { ...init.headers, 'X-WP-Nonce': nonce },
    });
  }

  async function refreshNonce() {
    const response = await fetch(config.nonceUrl, {
      credentials: 'same-origin',
    });
    const fresh = response.ok ? (await response.text()).trim() : '';
    if (!/^[a-z0-9]+$/i.test(fresh)) return false;
    nonce = fresh;
    return true;
  }

  return async function request<T>(
    path: string,
    init: RequestInit = {},
  ): Promise<T> {
    let response = await send(path, init);
    if (response.status === 403) {
      const failure = await response.clone().json().catch(() => null);
      if (failure?.code === 'rest_cookie_invalid_nonce' && (await refreshNonce()))
        response = await send(path, init);
    }
    if (response.status === 204) return undefined as T;
    const data = await response.json().catch(() => null);
    if (!response.ok) {
      const message =
        response.status === 401
          ? 'Your session has ended. Sign in again in another tab, then retry.'
          : data?.message || 'The request could not be completed.';
      throw new ApiError(message, response.status);
    }
    return data as T;
  };
}
