/**
 * Service Worker Constants
 *
 * Central configuration for cache names, API patterns, and retry settings.
 */

/** @type {Object} Cache configuration by type */
export const CACHE_CONFIG = {
  STATIC: {
    name: 'tntt-static-v1',
    maxAge: 7 * 24 * 60 * 60, // 7 days
    strategy: 'cache-first'
  },
  API: {
    name: 'tntt-api-v1',
    maxAge: 60 * 60, // 1 hour
    strategy: 'stale-while-revalidate'
  },
  DATA: {
    name: 'tntt-data-v1',
    maxAge: 5 * 60, // 5 minutes
    strategy: 'network-first'
  }
};

/** @type {Object} URL patterns for request routing */
export const API_PATTERNS = {
  STATIC: /\.(js|css|png|svg|jpg|jpeg|webp|woff2?)(\?.*)?$/i,
  BUNDLE: /\/bundle\.php(\?.*)?$/,
  API_DATA: /\/api\/data\.php/,
  API_PUBLIC: /\/api\/(push|sync|bible)\.php/
};

/** @type {number} Maximum retry attempts for failed requests */
export const MAX_RETRIES = 3;

/** @type {number} Base delay for exponential backoff (ms) */
export const RETRY_DELAY = 1000;

/** @type {string} Database name for IndexedDB storage */
export const DB_NAME = 'TNTT';

/** @type {number} IndexedDB schema version */
export const DB_VERSION = 1;

/** @type {string} Object store name for offline queue */
export const STORE_NAME = 'offline_queue';
