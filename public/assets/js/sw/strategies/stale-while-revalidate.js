/**
 * Stale-While-Revalidate Strategy
 *
 * Strategy: Return cached response immediately, update cache in background.
 * - Return cached response right away for fast UX
 * - Refresh cache in background for next request
 * - Good for: API data that changes occasionally but not critical
 */

import { cacheManager } from '../core/cache-manager.js';
import { CACHE_CONFIG, log } from '../utils/index.js';

/**
 * Execute stale-while-revalidate strategy
 * @param {Request} request - The fetch request
 * @param {Object} config - Cache configuration (defaults to API)
 * @returns {Promise<Response>}
 */
export async function staleWhileRevalidate(request, config = CACHE_CONFIG.API) {
  const cached = await cacheManager.match(request, config);

  if (cached) {
    log(`SWR HIT: ${request.url}`);
    // Return cached immediately
    // Refresh in background
    refreshCache(request, config);
    return cached;
  }

  // No cache, fetch from network
  log(`SWR MISS: ${request.url}`);
  const response = await fetch(request);
  if (response.ok) {
    await cacheManager.put(request, response, config);
  }
  return response;
}

/**
 * Refresh cache in background (fire-and-forget)
 * @param {Request} request - The request to fetch
 * @param {Object} config - Cache configuration
 */
async function refreshCache(request, config) {
  try {
    const response = await fetch(request);
    if (response.ok) {
      await cacheManager.put(request, response, config);
      log(`SWR background refresh: ${request.url}`);
    }
  } catch (err) {
    // Silently fail
  }
}
