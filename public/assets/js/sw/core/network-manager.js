/**
 * Network Manager
 *
 * Handles network requests with fallback to cache.
 * Provides fetch abstraction with error handling.
 */

import { cacheManager } from './cache-manager.js';
import { CACHE_CONFIG, log, error } from '../utils/index.js';

export class NetworkManager {
  /**
   * Fetch a request with error handling
   * @param {Request} request - The request to fetch
   * @param {Object} options - Fetch options
   * @returns {Promise<Response|null>}
   */
  async fetch(request, options = {}) {
    try {
      const response = await fetch(request, {
        credentials: 'include',
        ...options
      });
      return response;
    } catch (err) {
      error('Network fetch failed:', err);
      return null;
    }
  }

  /**
   * Fetch with cache fallback
   * @param {Request} request - The request to fetch
   * @param {Object} config - Cache configuration
   * @returns {Promise<Response|null>}
   */
  async fetchWithFallback(request, config) {
    // Try network first
    const response = await this.fetch(request);
    if (response && response.ok) {
      await cacheManager.put(request, response, config);
      return response;
    }

    // Fallback to cache
    const cached = await cacheManager.match(request, config);
    if (cached) {
      log(`Serving ${request.url} from cache (fallback)`);
      return cached;
    }

    // No network and no cache
    return null;
  }
}

/** @type {NetworkManager} Singleton instance */
export const networkManager = new NetworkManager();
