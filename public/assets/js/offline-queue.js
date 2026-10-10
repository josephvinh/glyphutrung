/**
 * OFFLINE QUEUE
 *
 * Queues write operations when offline, syncs when connection restored.
 * Uses IndexedDB for persistent storage.
 *
 * @global IDBKeyRange - Provided by IndexedDB API
 *
 * Usage:
 *   await window.TNTT['offline-queue'].init();
 *   await window.TNTT['offline-queue'].enqueue('attendance', '/api/attendance.php', { data });
 *
 *   // Listen for events
 *   window.TNTTOfflineQueue.addListener((event, data) => {
 *     if (event === 'sync') console.log('Synced:', data);
 *   });
 */

const DB_NAME = 'TNTT';
const DB_VERSION = 1;
const STORE_NAME = 'offline_queue';
const MAX_RETRIES = 3;

class OfflineQueue {
  constructor() {
    /** @type {IDBDatabase|null} IndexedDB connection */
    this.db = null;
    /** @type {boolean} Online status */
    this.isOnline = typeof navigator !== 'undefined' ? navigator.onLine : true;
    /** @type {Set<Function>} Event listeners */
    this.listeners = new Set();
    /** @type {boolean} Initialized flag */
    this.initialized = false;
    /** @type {boolean} Prevent concurrent syncs */
    this._isSyncing = false;

    // Setup online/offline listeners (client-side only)
    if (typeof window !== 'undefined') {
      window.addEventListener('online', () => this.handleOnline());
      window.addEventListener('offline', () => this.handleOffline());
    }
  }

  /**
   * Initialize IndexedDB connection
   * @returns {Promise<void>}
   */
  async init() {
    if (this.initialized && this.db) return;

    return new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);

      request.onerror = () => {
        console.error('[OfflineQueue] IndexedDB error:', request.error);
        reject(request.error);
      };
      request.onsuccess = () => {
        this.db = request.result;
        this.initialized = true;
        resolve();
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
   * Add item to queue
   * @param {string} action - Action type (e.g., 'attendance', 'score')
   * @param {string} endpoint - API endpoint URL
   * @param {Object} payload - Request payload
   * @returns {Promise<number>} New item ID
   */
  async enqueue(action, endpoint, payload) {
    await this.init();

    const item = {
      action,
      endpoint,
      payload,
      timestamp: Date.now(),
      retries: 0,
      status: 'pending'
    };

    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(STORE_NAME, 'readwrite');
      const store = tx.objectStore(STORE_NAME);
      const request = store.add(item);

      request.onsuccess = () => {
        const id = request.result;
        this.notifyListeners('enqueue', { ...item, id });
        this.trySync();
        resolve(id);
      };
      request.onerror = () => {
        console.error('[OfflineQueue] Failed to enqueue:', request.error);
        reject(request.error);
      };
    });
  }

  /**
   * Get all pending items
   * @returns {Promise<Array>}
   */
  async getPending() {
    await this.init();

    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(STORE_NAME, 'readonly');
      const store = tx.objectStore(STORE_NAME);
      // Get items with status 'pending' or 'syncing' (to handle race conditions)
      const index = store.index('status');
      const request = index.getAll(IDBKeyRange.only('pending'));

      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  /**
   * Get queue status
   * @returns {Promise<Object>}
   */
  async getStatus() {
    const pending = await this.getPending();
    const failed = await this.getFailed();
    return {
      pending: pending.length,
      failed: failed.length,
      isOnline: this.isOnline
    };
  }

  /**
   * Get failed items
   * @returns {Promise<Array>}
   */
  async getFailed() {
    await this.init();

    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(STORE_NAME, 'readonly');
      const store = tx.objectStore(STORE_NAME);
      const index = store.index('status');
      const request = index.getAll('failed');

      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  /**
   * Remove item from queue
   * @param {number} id - Item ID to remove
   */
  async remove(id) {
    await this.init();

    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(STORE_NAME, 'readwrite');
      const store = tx.objectStore(STORE_NAME);
      const request = store.delete(id);

      request.onsuccess = () => {
        this.notifyListeners('remove', { id });
        resolve();
      };
      request.onerror = () => reject(request.error);
    });
  }

  /**
   * Update item status
   * @param {number} id - Item ID
   * @param {string} status - New status ('pending', 'failed', 'syncing')
   */
  async updateStatus(id, status) {
    await this.init();

    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(STORE_NAME, 'readwrite');
      const store = tx.objectStore(STORE_NAME);
      const getRequest = store.get(id);

      getRequest.onsuccess = () => {
        const item = getRequest.result;
        if (item) {
          item.status = status;
          const putRequest = store.put(item);
          putRequest.onsuccess = () => resolve();
          putRequest.onerror = () => reject(putRequest.error);
        } else {
          resolve();
        }
      };
      getRequest.onerror = () => reject(getRequest.error);
    });
  }

  /**
   * Increment retry count
   * @param {number} id - Item ID
   * @returns {Promise<Object|null>}
   */
  async incrementRetry(id) {
    await this.init();

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

  /**
   * Process sync
   * @returns {Promise<Object>} Sync results
   */
  async sync() {
    if (!this.isOnline) {
      console.log('[OfflineQueue] Skipping sync - offline');
      return { synced: 0, failed: 0 };
    }

    await this.init();

    // Prevent concurrent syncs (page and SW can both call sync())
    if (this._isSyncing) {
      console.log('[OfflineQueue] Sync already in progress, skipping');
      return { synced: 0, failed: 0 };
    }
    this._isSyncing = true;

    try {
      const pending = await this.getPending();
      if (pending.length === 0) {
        return { synced: 0, failed: 0 };
      }

      // Mark all items as 'syncing' in a single transaction to prevent duplicates
      await this._markSyncing(pending.map(i => i.id));

      let synced = 0;
      let failed = 0;

      for (const item of pending) {
        try {
          await this.syncItem(item);
          await this.remove(item.id);
          synced++;
        } catch (err) {
          console.error('[OfflineQueue] Sync failed for item', item.id, err);
          const updated = await this.incrementRetry(item.id);
          if (updated && updated.status === 'failed') {
            failed++;
          }
        }
      }

      this.notifyListeners('sync', { synced, failed });
      return { synced, failed };
    } finally {
      this._isSyncing = false;
    }
  }

  /**
   * Mark items as syncing to prevent duplicate POSTs
   */
  async _markSyncing(ids) {
    if (!this.db || !ids.length) return;

    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(STORE_NAME, 'readwrite');
      const store = tx.objectStore(STORE_NAME);
      const index = store.index('status');

      const getAll = index.getAll('pending');
      getAll.onsuccess = () => {
        const allPending = getAll.result;
        for (const item of allPending) {
          if (ids.includes(item.id)) {
            item.status = 'syncing';
            store.put(item);
          }
        }
      };
      getAll.onerror = () => resolve();
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error);
    });
  }

  /**
   * Sync single item
   * @param {Object} item - Queue item
   * @returns {Promise<any>}
   */
  async syncItem(item) {
    // Get current CSRF token at sync time (may have changed since enqueue)
    const csrfToken = this._getCsrfToken();

    const response = await fetch(item.endpoint, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        ...(csrfToken && { 'X-CSRF-TOKEN': csrfToken })
      },
      body: JSON.stringify(item.payload)
    });

    // CSRF failure: refresh token and retry once
    if (response.status === 403) {
      await this._refreshCsrfToken();
      const newToken = this._getCsrfToken();
      const retryResponse = await fetch(item.endpoint, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          ...(newToken && { 'X-CSRF-TOKEN': newToken })
        },
        body: JSON.stringify(item.payload)
      });
      if (!retryResponse.ok) {
        throw new Error(`Sync failed after token refresh: ${retryResponse.status}`);
      }
      return retryResponse.json();
    }

    if (!response.ok) {
      throw new Error(`Sync failed: ${response.status}`);
    }

    return response.json();
  }

  /**
   * Get CSRF token from page
   * @returns {string|null}
   */
  _getCsrfToken() {
    // Try various sources for CSRF token
    const meta = document?.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.content;
    return window?.TNTT?.csrfToken || window?.TNTT_BOOT?.csrfToken || null;
  }

  /**
   * Refresh CSRF token (called on 403)
   */
  async _refreshCsrfToken() {
    try {
      const res = await fetch('/api/auth.php?action=csrf', { credentials: 'include' });
      if (res.ok) {
        const data = await res.json();
        if (data.token) {
          if (window.TNTT) window.TNTT.csrfToken = data.token;
          if (window.TNTT_BOOT) window.TNTT_BOOT.csrfToken = data.token;
        }
      }
    } catch (e) {
      console.warn('[OfflineQueue] Failed to refresh CSRF token:', e);
    }
  }

  /**
   * Try to sync (called after enqueue or when coming online)
   */
  trySync() {
    if (this.isOnline) {
      // Use Background Sync API if available
      if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
        navigator.serviceWorker.ready.then(registration => {
          registration.sync.register('offline-sync').catch(() => {
            // Fallback to immediate sync
            this.sync();
          });
        });
      } else {
        // Fallback to immediate sync
        this.sync();
      }
    }
  }

  /**
   * Handle going online
   */
  handleOnline() {
    console.log('[OfflineQueue] Online');
    this.isOnline = true;
    this.notifyListeners('online');
    this.sync();
  }

  /**
   * Handle going offline
   */
  handleOffline() {
    console.log('[OfflineQueue] Offline');
    this.isOnline = false;
    this.notifyListeners('offline');
  }

  /**
   * Add event listener
   * @param {Function} callback - Event callback
   * @returns {Function} Unsubscribe function
   */
  addListener(callback) {
    this.listeners.add(callback);
    return () => this.listeners.delete(callback);
  }

  /**
   * Notify listeners
   * @param {string} event - Event name
   * @param {Object} data - Event data
   */
  notifyListeners(event, data) {
    this.listeners.forEach(cb => {
      try {
        cb(event, data);
      } catch (err) {
        console.error('[OfflineQueue] Listener error:', err);
      }
    });
  }

  /**
   * Clear all items from queue
   */
  async clear() {
    await this.init();

    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(STORE_NAME, 'readwrite');
      const store = tx.objectStore(STORE_NAME);
      const request = store.clear();

      request.onsuccess = () => {
        this.notifyListeners('clear');
        resolve();
      };
      request.onerror = () => reject(request.error);
    });
  }

  /**
   * Retry failed items
   * @returns {Promise<number>} Number of items to retry
   */
  async retryFailed() {
    await this.init();

    const failed = await this.getFailed();
    for (const item of failed) {
      await this.updateStatus(item.id, 'pending');
    }
    this.trySync();
    return failed.length;
  }
}

// Export singleton (works in both SW and browser contexts)
// Register as window.TNTT['offline-queue'] so app.js gopManh() can find it
if (typeof window !== 'undefined') {
  window.TNTT = window.TNTT || {};
  window.TNTT['offline-queue'] = new OfflineQueue();
}
