/**
 * Sync Manager
 *
 * Manages background synchronization of offline queue items.
 * Handles retry logic and IndexedDB operations.
 */

import { MAX_RETRIES, RETRY_DELAY, DB_NAME, DB_VERSION, STORE_NAME, log, error } from '../utils/index.js';

export class SyncManager {
  constructor() {
    /** @type {IDBDatabase|null} IndexedDB connection */
    this.db = null;
  }

  /**
   * Initialize IndexedDB connection
   * @returns {Promise<IDBDatabase>}
   */
  async initDB() {
    if (this.db) return this.db;

    return new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);

      request.onerror = () => reject(request.error);
      request.onsuccess = () => {
        this.db = request.result;
        resolve(this.db);
      };

      request.onupgradeneeded = (e) => {
        const db = e.target.result;
        if (!db.objectStoreNames.contains(STORE_NAME)) {
          const store = db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
          store.createIndex('action', 'action', { unique: false });
          store.createIndex('status', 'status', { unique: false });
          store.createIndex('timestamp', 'timestamp', { unique: false });
        }
      };
    });
  }

  /**
   * Register for Background Sync API
   * @param {string} tag - Sync tag name
   * @returns {Promise<boolean>}
   */
  async registerBackgroundSync(tag) {
    if ('sync' in self.registration) {
      try {
        await self.registration.sync.register(tag);
        log(`Background sync registered: ${tag}`);
        return true;
      } catch (err) {
        error(`Background sync registration failed:`, err);
        return false;
      }
    }
    return false;
  }

  /**
   * Sync all pending items in a queue
   * @param {string} queueName - Queue name (for future multi-queue support)
   */
  async syncQueue(queueName) {
    const queue = await this.getQueue();
    if (!queue || queue.length === 0) {
      log(`Queue is empty`);
      return;
    }

    for (const item of queue) {
      try {
        await this.processQueueItem(item);
        await this.removeFromQueue(item.id);
        log(`Synced item ${item.id}`);
      } catch (err) {
        error(`Failed to sync item ${item.id}:`, err);
        await this.incrementRetry(item.id);
      }
    }
  }

  /**
   * Process a single queue item
   * @param {Object} item - Queue item with endpoint and payload
   * @returns {Promise<any>}
   */
  async processQueueItem(item) {
    const response = await fetch(item.endpoint, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(item.payload)
    });

    if (!response.ok) {
      throw new Error(`Sync failed: ${response.status}`);
    }

    return response.json();
  }

  /**
   * Get all pending items from queue
   * @returns {Promise<Array>}
   */
  async getQueue() {
    await this.initDB();

    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(STORE_NAME, 'readonly');
      const store = tx.objectStore(STORE_NAME);
      const index = store.index('status');
      const request = index.getAll('pending');

      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  /**
   * Remove item from queue
   * @param {number} id - Item ID to remove
   */
  async removeFromQueue(id) {
    await this.initDB();

    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(STORE_NAME, 'readwrite');
      const store = tx.objectStore(STORE_NAME);
      const request = store.delete(id);

      request.onsuccess = () => resolve();
      request.onerror = () => reject(request.error);
    });
  }

  /**
   * Increment retry count for an item
   * @param {number} id - Item ID
   * @returns {Promise<Object|null>}
   */
  async incrementRetry(id) {
    await this.initDB();

    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(STORE_NAME, 'readwrite');
      const store = tx.objectStore(STORE_NAME);
      const getRequest = store.get(id);

      getRequest.onsuccess = () => {
        const item = getRequest.result;
        if (item) {
          item.retries++;
          if (item.retries >= MAX_RETRIES) {
            item.status = 'failed';
          }
          const putRequest = store.put(item);
          putRequest.onsuccess = () => resolve(item);
          putRequest.onerror = () => reject(putRequest.error);
        } else {
          resolve(null);
        }
      };
      getRequest.onerror = () => reject(getRequest.error);
    });
  }
}

/** @type {SyncManager} Singleton instance */
export const syncManager = new SyncManager();
