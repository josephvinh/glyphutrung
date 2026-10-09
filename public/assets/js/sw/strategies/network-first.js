/**
 * Network-First Strategy
 *
 * Strategy: Try network first, fall back to cache.
 * - Fetch from network and cache successful response
 * - Fall back to cache if network fails
 * - Good for: Dynamic data (API responses, user-specific content)
 */

import { cacheManager } from '../core/cache-manager.js';
import { CACHE_CONFIG, log } from '../utils/index.js';

/**
 * Execute network-first strategy
 * @param {Request} request - The fetch request
 * @param {Object} config - Cache configuration (defaults to DATA)
 * @returns {Promise<Response>}
 */
export async function networkFirst(request, config = CACHE_CONFIG.DATA) {
  try {
    const response = await fetch(request, { credentials: 'include' });
    if (response.ok) {
      await cacheManager.put(request, response, config);
      log(`Network-first SUCCESS: ${request.url}`);
      return response;
    }
    // Network error (4xx/5xx)
    throw new Error(`HTTP ${response.status}`);
  } catch (err) {
    log(`Network-first FALLBACK: ${request.url}`);
    const cached = await cacheManager.match(request, config);
    if (cached) {
      return cached;
    }
    // No cache available - return offline error response
    return new Response(JSON.stringify({
      ok: false,
      error: { code: 'NETWORK_ERROR', message: 'Khong co ket noi va khong co du lieu cache.' }
    }), {
      status: 503,
      headers: { 'Content-Type': 'application/json' }
    });
  }
}
