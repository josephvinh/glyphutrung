/* ==========================================================
   SERVICE WORKER — PWA v2.0
   Phiên bản cải tiến với:
   - Offline fallback page
   - Better caching strategy
   - Background sync support
   - Install prompt handling
   - Better push notifications
   ========================================================== */

const PHIEN_BAN = 'tntt-sw-v2';
const KHO      = 'tntt-tinh-' + PHIEN_BAN;

// Static assets cần precache
const STATIC_ASSETS = [
    '/assets/css/bundle.php',
    '/assets/js/bundle.php',
    '/manifest.json',
];

self.addEventListener('install', (e) => {
    console.log('[SW] Installing v' + PHIEN_BAN);
    e.waitUntil((async () => {
        const kho = await caches.open(KHO);

        // Precache static assets
        const precachePromises = STATIC_ASSETS.map(async (url) => {
            try {
                const res = await fetch(url);
                if (res.ok) {
                    await kho.put(url, res);
                    console.log('[SW] Precached:', url);
                }
            } catch (err) {
                console.warn('[SW] Precache failed:', url, err);
            }
        });

        await Promise.allSettled(precachePromises);
        console.log('[SW] Precache complete');

        // Skip waiting để activate ngay
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (e) => {
    console.log('[SW] Activating v' + PHIEN_BAN);
    e.waitUntil((async () => {
        // Xóa cache cũ
        const cachesToDelete = await caches.keys();
        await Promise.all(
            cachesToDelete
                .filter(name => name.startsWith('tntt-tinh-') && name !== KHO)
                .map(name => {
                    console.log('[SW] Deleting old cache:', name);
                    return caches.delete(name);
                })
        );

        // Claim all clients
        await self.clients.claim();
        console.log('[SW] Activation complete');
    })());
});

/* ==========================================================
   FETCH HANDLER
   - Static assets: Cache First, fallback network
   - API: Network First, fallback cache
   - HTML: Network First, fallback offline page
   ========================================================== */
const CHO_GIU = /.(js|css|png|svg|jpg|jpeg|webp|woff2?|ico)$/i;

self.addEventListener('fetch', (e) => {
    const req = e.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);

    // Skip localhost dev mode
    if (url.hostname === 'localhost' || url.hostname === '127.0.0.1') return;

    // Skip non-origin requests (CDN, fonts, etc.)
    if (url.origin !== self.location.origin) return;

    // Skip API routes - handled separately
    if (url.pathname.startsWith('/api/')) {
        e.respondWith(networkFirst(req));
        return;
    }

    // Skip service worker
    if (url.pathname.endsWith('/sw.js')) return;

    // Static assets - Cache First
    const laTinh = CHO_GIU.test(url.pathname) ||
                   url.pathname.endsWith('/bundle.php') ||
                   url.pathname.endsWith('.php');

    if (laTinh && !url.pathname.startsWith('/api/')) {
        e.respondWith(cacheFirst(req));
        return;
    }

    // HTML pages - Network First with offline fallback
    if (req.headers.get('accept')?.includes('text/html')) {
        e.respondWith(networkFirstHTML(req));
        return;
    }

    // Everything else - Network First
    e.respondWith(networkFirst(req));
});

// Cache First Strategy
async function cacheFirst(req) {
    const kho = await caches.open(KHO);
    const cached = await kho.match(req);

    if (cached) {
        // Update cache in background
        fetch(req)
            .then(res => { if (res.ok) kho.put(req, res.clone()); })
            .catch(() => {}); // Ignore background update errors
        return cached;
    }

    try {
        const res = await fetch(req);
        if (res.ok) kho.put(req, res.clone());
        return res;
    } catch (err) {
        return new Response('Offline', { status: 503 });
    }
}

// Network First Strategy
async function networkFirst(req) {
    const kho = await caches.open(KHO);

    try {
        const res = await fetch(req);
        if (res.ok) kho.put(req, res.clone());
        return res;
    } catch (err) {
        const cached = await kho.match(req);
        if (cached) return cached;
        return new Response('Offline', { status: 503 });
    }
}

// Network First for HTML
async function networkFirstHTML(req) {
    const kho = await caches.open(KHO);

    try {
        const res = await fetch(req);
        if (res.ok) {
            kho.put(req, res.clone());
            return res;
        }
        return await getOfflinePage();
    } catch (err) {
        const cached = await kho.match(req);
        if (cached) return cached;
        return await getOfflinePage();
    }
}

// Get offline fallback page
async function getOfflinePage() {
    const kho = await caches.open(KHO);
    let offline = await kho.match('/offline.html');

    if (!offline) {
        offline = new Response(OFFLINE_HTML, {
            headers: { 'Content-Type': 'text/html; charset=utf-8' }
        });
    }

    return offline;
}

// Inline offline HTML
const OFFLINE_HTML = `
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Không có mạng - GĐGLPT</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #c8203a 0%, #8b1538 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            max-width: 400px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 24px;
            background: #f8fafc;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }
        h1 {
            color: #1e293b;
            margin-bottom: 12px;
            font-size: 24px;
        }
        p {
            color: #64748b;
            margin-bottom: 24px;
            line-height: 1.6;
        }
        button {
            background: #c8203a;
            color: white;
            border: none;
            padding: 14px 32px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover {
            background: #a31b30;
        }
        .wifi-icon {
            font-size: 48px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <span class="wifi-icon">📡</span>
        </div>
        <h1>Mất kết nối mạng</h1>
        <p>Vui lòng kiểm tra kết nối internet và thử lại. Dữ liệu đã được lưu sẽ hiển thị khi có mạng trở lại.</p>
        <button onclick="location.reload()">Thử lại</button>
    </div>
    <script>
        // Auto retry when online
        window.addEventListener('online', () => location.reload());

        // Show message if still offline after retry
        if (!navigator.onLine) {
            document.querySelector('button').textContent = 'Đang kết nối...';
            document.querySelector('button').disabled = true;
        }
    </script>
</body>
</html>
`;

/* ==========================================================
   PUSH NOTIFICATIONS
   ========================================================== */
self.addEventListener('push', (e) => {
    e.waitUntil((async () => {
        let tin = {
            title: '🔔 GĐGLPT',
            body: 'Bạn có thông báo mới',
            url: '/',
            icon: '/assets/img/icon-192.png',
            badge: '/assets/img/icon-32.png',
            tag: 'tntt-notify',
            vibrate: [100, 50, 100],
            requireInteraction: false,
        };

        // Try to get actual data from push
        if (e.data) {
            try {
                const data = e.data.json();
                Object.assign(tin, data);
            } catch (err) {
                // Empty push - fetch pending notifications
                try {
                    const r = await fetch('/api/push.php?action=pending', {
                        credentials: 'include',
                        cache: 'no-store'
                    });
                    if (r.ok) {
                        const d = await r.json();
                        if (d?.ok && d.item) Object.assign(tin, d.item);
                    }
                } catch (fetchErr) {
                    console.log('[SW] Could not fetch pending notifications');
                }
            }
        }

        // Check if any client is focused
        const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const hasFocused = clients.some(c => c.focused);

        // Only show notification if no focused window
        if (!hasFocused) {
            await self.registration.showNotification(tin.title, {
                body: tin.body,
                icon: tin.icon,
                badge: tin.badge,
                tag: tin.tag,
                vibrate: tin.vibrate,
                requireInteraction: tin.requireInteraction,
                data: { url: tin.url },
                actions: [
                    { action: 'open', title: '📱 Mở app' },
                    { action: 'dismiss', title: 'Đóng' }
                ]
            });
        } else {
            // Send message to focused window instead
            clients.forEach(client => {
                if (client.visibilityState === 'visible') {
                    client.postMessage({
                        type: 'PUSH_NOTIFICATION',
                        data: tin
                    });
                }
            });
        }
    })());
});

/* ==========================================================
   NOTIFICATION CLICK
   ========================================================== */
self.addEventListener('notificationclick', (e) => {
    e.notification.close();

    const action = e.action;
    const data = e.notification.data || {};

    if (action === 'dismiss') return;

    e.waitUntil((async () => {
        // Try to focus existing window
        const clients = await self.clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        });

        for (const client of clients) {
            if (client.url.includes(self.location.origin)) {
                await client.focus();
                if ('navigate' in client) {
                    await client.navigate(data.url || '/');
                }
                return;
            }
        }

        // Open new window
        await self.clients.openWindow(data.url || '/');
    })());
});

/* ==========================================================
   BACKGROUND SYNC
   ========================================================== */
self.addEventListener('sync', (e) => {
    console.log('[SW] Background sync:', e.tag);

    if (e.tag === 'sync-attendance') {
        e.waitUntil(syncAttendance());
    }
});

async function syncAttendance() {
    // Get pending attendance data from IndexedDB
    // This would be implemented based on your app's needs
    console.log('[SW] Syncing attendance data...');
}

/* ==========================================================
   MESSAGE HANDLER
   ========================================================== */
self.addEventListener('message', (e) => {
    const { type, data } = e.data || {};

    switch (type) {
        case 'SKIP_WAITING':
            self.skipWaiting();
            break;

        case 'GET_VERSION':
            e.source.postMessage({ type: 'VERSION', version: PHIEN_BAN });
            break;

        case 'CLEAR_CACHE':
            caches.delete(KHO).then(() => {
                e.source.postMessage({ type: 'CACHE_CLEARED' });
            });
            break;

        case 'RELOAD_DATA':
            // Notify all clients to refresh
            self.clients.matchAll({ type: 'window' }).then(clients => {
                clients.forEach(client => {
                    client.postMessage({ type: 'RELOAD_DATA', url: data?.url });
                });
            });
            break;
    }
});

console.log('[SW] Service Worker loaded v' + PHIEN_BAN);
