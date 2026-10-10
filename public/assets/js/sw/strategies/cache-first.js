/**
 * Cache-First Strategy
 *
 * Strategy: Check cache first, fall back to network.
 * - Return cached response if fresh
 * - Fetch from network and cache if not cached or stale
 * - Good for: Static assets (CSS, JS, images)
 */

import { cacheManager } from '../core/cache-manager.js';
import { CACHE_CONFIG, log } from '../utils/index.js';

/**
 * Execute cache-first strategy
 * @param {Request} request - The fetch request
 * @param {Object} config - Cache configuration (defaults to STATIC)
 * @returns {Promise<Response>}
 */
export async function cacheFirst(request, config = CACHE_CONFIG.STATIC) {
  // Check cache first
  const cached = await cacheManager.match(request, config);
  if (cached) {
    log(`Cache-first HIT: ${request.url}`);

    // Check freshness
    const isFresh = await cacheManager.getFreshness(cached, config.maxAge);
    if (isFresh) {
      return cached;
    }

    // Return cached but refresh in background
    fetchAndCache(request, config);
    return cached;
  }

  // Not in cache, fetch from network
  log(`Cache-first MISS: ${request.url}`);
  const response = await fetch(request);
  if (response.ok) {
    await cacheManager.put(request, response, config);
  }
  return response;
}

/**
 * Fetch and cache in background (fire-and-forget)
 * @param {Request} request - The request to fetch
 * @param {Object} config - Cache configuration
 */
async function fetchAndCache(request, config) {
  try {
    const response = await fetch(request);
    if (response.ok) {
      await cacheManager.put(request, response, config);
      log(`Background refresh: ${request.url}`);
    }
  } catch (err) {
    // Silently fail - we already returned cached version
  }
}
