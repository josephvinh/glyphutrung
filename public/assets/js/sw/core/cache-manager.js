/**
 * Cache Manager
 *
 * Manages cache lifecycle for service worker resources.
 * Handles opening, reading, writing, and cleaning up caches.
 */

import { CACHE_CONFIG, log, error } from '../utils/index.js';

export class CacheManager {
  constructor() {
    /** @type {Map<string, Cache>} Open cache instances */
    this.caches = {};
  }

  /**
   * Open a cache, reusing existing instance if available
   * @param {Object} config - Cache configuration with name property
   * @returns {Promise<Cache>}
   */
  async open(config) {
    const name = config.name;
    if (!this.caches.has(name)) {
      this.caches.set(name, await caches.open(name));
    }
    return this.caches.get(name);
  }

  /**
   * Match a request in the cache
   * @param {Request} request - The request to match
   * @param {Object} config - Cache configuration
   * @returns {Promise<Response|undefined>}
   */
  async match(request, config) {
    const cache = await this.open(config);
    return cache.match(request);
  }

  /**
   * Store a response in the cache
   * @param {Request} request - The request key
   * @param {Response} response - The response to cache
   * @param {Object} config - Cache configuration
   */
  async put(request, response, config) {
    const cache = await this.open(config);
    // Clone response before storing (can only be consumed once)
    if (response.ok) {
      await cache.put(request, response.clone());
    }
  }

  /**
   * Delete a cache by name
   * @param {Object} config - Cache configuration with name property
   */
  async delete(config) {
    const name = config.name;
    if (this.caches.has(name)) {
      this.caches.delete(name);
    }
    await caches.delete(name);
    log(`Cache ${name} deleted`);
  }

  /**
   * Clean up old TNTT caches (different from current version)
   * @param {string} currentVersion - Current cache version name
   */
  async cleanupOldCaches(currentVersion) {
    const keys = await caches.keys();
    for (const key of keys) {
      if (key.startsWith('tntt-') && key !== currentVersion) {
        log(`Cleaning up old cache: ${key}`);
        await caches.delete(key);
      }
    }
  }

  /**
   * Check if a cached response is still fresh
   * @param {Response} response - The cached response
   * @param {number} maxAge - Maximum age in seconds
   * @returns {boolean}
   */
  async getFreshness(response, maxAge) {
    if (!response) return false;
    const dateHeader = response.headers.get('date');
    if (!dateHeader) return false;

    const age = (Date.now() - new Date(dateHeader).getTime()) / 1000;
    return age < maxAge;
  }
}

/** @type {CacheManager} Singleton instance */
export const cacheManager = new CacheManager();
