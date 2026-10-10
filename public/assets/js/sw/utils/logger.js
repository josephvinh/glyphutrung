/**
 * Service Worker Logger
 *
 * Conditional logging utility for service worker debugging.
 * Set DEBUG = true during development to see logs.
 */

const DEBUG = false; // Set to true for development

/**
 * Log message with timestamp prefix
 * @param {...any} args - Arguments to log
 */
export function log(...args) {
  if (DEBUG && self.registration && self.registration.active) {
    console.log('[SW]', new Date().toISOString(), ...args);
  }
}

/**
 * Log error with timestamp prefix
 * @param {...any} args - Arguments to log
 */
export function error(...args) {
  console.error('[SW]', new Date().toISOString(), ...args);
}

/**
 * Log warning with timestamp prefix
 * @param {...any} args - Arguments to log
 */
export function warn(...args) {
  console.warn('[SW]', new Date().toISOString(), ...args);
}
