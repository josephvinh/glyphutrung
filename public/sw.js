/* ==========================================================
   SERVICE WORKER - API + PWA Refactor

   Modular service worker with cache strategies:
   - Static assets: Cache-first (7 days)
   - API data: Stale-while-revalidate
   - Push notifications: Full payload support
   - Background sync: IndexedDB queue

   Version from URL: sw.js?v=<hash> for cache busting
   ========================================================== */

import { cacheManager } from './assets/js/sw/core/cache-manager.js';
import { syncManager } from './assets/js/sw/core/sync-manager.js';
import { cacheFirst } from './assets/js/sw/strategies/cache-first.js';
import { networkFirst } from './assets/js/sw/strategies/network-first.js';
import { staleWhileRevalidate } from './assets/js/sw/strategies/stale-while-revalidate.js';
import { CACHE_CONFIG, API_PATTERNS, log, warn } from './assets/js/sw/utils/index.js';

// Version from URL
const PHIEN_BAN = new URL(self.location.href).searchParams.get('v') || 'tntt-sw-dev';
const KHO = 'tntt-tinh-' + PHIEN_BAN;

// ==========================================================
// INSTALL
// ==========================================================
self.addEventListener('install', (e) => {
  e.waitUntil((async () => {
    log('Service Worker installing...');
    // Precache critical assets
    const kho = await caches.open(KHO);
    try {
      await Promise.allSettled([
        kho.add('/assets/css/bundle.php'),
        kho.add('/assets/js/bundle.php'),
      ]);
    } catch (err) {
      warn('Precache failed:', err);
    }
    self.skipWaiting();
  })());
});

// ==========================================================
// ACTIVATE
// ==========================================================
self.addEventListener('activate', (e) => {
  e.waitUntil((async () => {
    log('Service Worker activating...');
    // Cleanup old caches
    await cacheManager.cleanupOldCaches(KHO);
    await self.clients.claim();
    log('Service Worker activated');
  })());
});

// ==========================================================
// FETCH - Request Handling
// ==========================================================
self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);

  // Skip localhost dev
  if (url.hostname === 'localhost' || url.hostname === '127.0.0.1') return;

  // Skip cross-origin
  if (url.origin !== self.location.origin) return;

  // Skip API (use network-first, no caching)
  if (url.pathname.startsWith('/api/')) return;

  // Skip service worker
  if (url.pathname.endsWith('/sw.js')) return;

  // Static assets: Cache-first
  if (API_PATTERNS.STATIC.test(url.pathname) || API_PATTERNS.BUNDLE.test(url.pathname)) {
    e.respondWith(cacheFirst(req, CACHE_CONFIG.STATIC));
    return;
  }

  // PHP pages: NEVER cache - these may contain user-specific data (TNTT_BOOT, CSRF tokens)
  // index.php specifically sends Cache-Control: no-store for this reason
  if (url.pathname.endsWith('.php')) {
    // Just fetch from network, don't cache
    e.respondWith(fetch(req, { credentials: 'include' }));
    return;
  }
});

// ==========================================================
// PUSH NOTIFICATIONS
// ==========================================================
self.addEventListener('push', (e) => {
  e.waitUntil((async () => {
    let notification = {
      title: 'Co viec moi',
      body: 'Mo app de xem chi tiet.',
      icon: 'assets/img/icon.svg',
      badge: 'assets/img/icon-32.png',
      tag: 'tntt-chung',
      data: { url: '/' }
    };

    // Try to parse payload (new format)
    if (e.data) {
      try {
        const data = e.data.json();
        notification = { ...notification, ...data };
      } catch (err) {
        // Fallback to old behavior (fetch from server)
        try {
          notification = await fetchNotificationContent();
        } catch (fetchErr) {
          // Keep default notification
        }
      }
    } else {
      // No payload, fetch from server
      try {
        notification = await fetchNotificationContent();
      } catch (err) {
        // Keep default notification
      }
    }

    // Notify open tabs
    await notifyOpenTabs(notification.data.url);

    // Show notification if no focused tab
    if (!await hasFocusedClient()) {
      await self.registration.showNotification(notification.title, {
        body: notification.body,
        icon: notification.icon,
        badge: notification.badge,
        tag: notification.tag,
        renotify: false,
        data: notification.data,
        vibrate: [80, 40, 80]
      });
    }
  })());
});

async function fetchNotificationContent() {
  const khoa = new URL('__tntt_push_token', self.registration.scope).href;
  let token = '';
  try {
    const luu = await (await caches.open('tntt-push')).match(khoa);
    if (luu) token = (await luu.text()).trim();
  } catch (err) {}

  const dk = token ? null : await self.registration.pushManager.getSubscription();
  const r = await fetch('/api/push.php?action=pending', {
    method: 'POST',
    credentials: 'include',
    cache: 'no-store',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(token ? { token } : { endpoint: dk ? dk.endpoint : '' })
  });

  if (r.ok) {
    const d = await r.json();
    if (d && d.ok && d.item) {
      return {
        title: d.item.title || 'Co viec moi',
        body: d.item.body || 'Mo app de xem chi tiet.',
        icon: d.item.icon || 'assets/img/icon.svg',
        tag: d.item.tag || 'tntt-chung',
        data: { url: d.item.url || '/' }
      };
    }
  }
  throw new Error('Failed to fetch notification');
}

async function hasFocusedClient() {
  const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
  return clients.some(c => c.focused);
}

async function notifyOpenTabs(url) {
  const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
  for (const c of clients) {
    if (c.focused) {
      c.postMessage({ action: 'RELOAD_DATA', url: url || '/' });
    }
  }
}

// ==========================================================
// NOTIFICATION CLICK
// ==========================================================
self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const targetUrl = (e.notification.data && e.notification.data.url) || '/';

  e.waitUntil((async () => {
    // Focus existing window if available (including uncontrolled tabs)
    const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const c of clients) {
      if (c.url.includes(self.location.origin)) {
        await c.focus();
        if ('navigate' in c) {
          try { await c.navigate(targetUrl); } catch (err) {}
        }
        return;
      }
    }
    // Open new window
    await self.clients.openWindow(targetUrl);
  })());
});

// ==========================================================
// OFFLINE SYNC DISABLED
// Background sync removed: only the page syncs, not the SW.
// This prevents race conditions where both page and SW sync the same item.
// ==========================================================
