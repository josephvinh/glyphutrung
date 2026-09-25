/* ==========================================================
   SERVICE WORKER — nhận thông báo khi app đang đóng

   Đây là mảnh chạy NGOÀI trang web, do trình duyệt giữ sống. Không có
   nó thì không nhận được thông báo lúc app không mở.

   Máy chủ gửi một cú chuông RỖNG (không kèm nội dung, xem config/push.php).
   Nhận được chuông, tệp này tự gọi API lấy việc mới nhất rồi hiện lên.

   Vì sao làm vậy: nội dung không phải đi qua máy chủ đẩy của Google hay
   Apple — họ chỉ thấy một tín hiệu rỗng. Và phía PHP đỡ phải mã hoá
   payload, vốn là phần dài và dễ sai nhất của Web Push.

   FEATURES:
     - Cache-first với stale-while-revalidate cho static assets
     - Offline fallback page cho navigation requests
     - Background sync cho offline attendance actions
     - Message handlers cho cache management
   ========================================================== */

const PHIEN_BAN = 'tntt-sw-9';
const KHO      = 'tntt-tinh-' + PHIEN_BAN;
const OFFLINE_PAGE = '/offline.html';

// Critical resources cần preload khi có network
const CRITICAL_ASSETS = [
    '/assets/css/bundle.php',
    '/assets/js/bundle.php',
];

// Background sync tag cho attendance
const BG_SYNC_TAG_ATTENDANCE = 'tntt-attendance-sync';

self.addEventListener('install', (e) => {
    // Precache critical assets khi install
    e.waitUntil((async () => {
        const kho = await caches.open(KHO);
        try {
            await Promise.allSettled([
                kho.add('/assets/css/bundle.php'),
                kho.add('/assets/js/bundle.php'),
            ]);
        } catch (err) {
            console.warn('[SW] Precache thất bại:', err);
        }
    })());
    self.skipWaiting();
});

self.addEventListener('activate', (e) => e.waitUntil((async () => {
    // Dọn kho của phiên bản cũ
    for (const ten of await caches.keys()) {
        if (ten.startsWith('tntt-tinh-') && ten !== KHO) await caches.delete(ten);
    }
    await self.clients.claim();
})()));

/* ==========================================================
   GIỮ TỆP TĨNH TRONG MÁY

   Vì sao cần: app lưu ra màn hình chính vẫn phải tải lại toàn bộ CSS,
   JS, phông chữ mỗi lần mở. Sóng yếu là mấy giây màn hình trắng.

   Cách chia:
     - Tệp tĩnh (.js .css .png .svg .woff2)  -> LẤY TRONG KHO TRƯỚC.
       Có sẵn thì hiện ngay lập tức, rồi âm thầm tải bản mới về để lần
       sau dùng. Người dùng không phải chờ mạng.
     - Mọi thứ khác (api/, index.php, sw.js) -> LUÔN ĐI MẠNG.
       Điểm danh, điểm số, danh sách phải là số liệu thật của lúc này.
       Đem chúng ra khỏi mạng là sai nghiêm trọng.
       NGOẠI TRỪ: offline fallback page khi không có mạng.

   TỐI ƯU THÊM:
     - Stale-while-revalidate: trả cache ngay, update cache ở nền
     - Offline fallback cho navigation requests
   ========================================================== */
const CHO_GIU = /.(js|css|png|svg|jpg|jpeg|webp|woff2?)$/i;

self.addEventListener('fetch', (e) => {
    const req = e.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);

    // MÁY PHÁT TRIỂN (localhost / 127.0.0.1): KHÔNG dùng kho
    if (url.hostname === 'localhost' || url.hostname === '127.0.0.1') return;

    // Chỉ giữ tệp của chính mình
    if (url.origin !== self.location.origin) return;

    // Những thứ TUYỆT ĐỐI không được lấy từ kho (ngoại trừ offline fallback)
    if (url.pathname.endsWith('/sw.js')) return;
    if (url.pathname.startsWith('/api/')) return;

    // Tệp tĩnh: đuôi .js/.css/ảnh, HOẶC bundle.php
    const laTinh = CHO_GIU.test(url.pathname) || url.pathname.endsWith('/bundle.php');
    if (laTinh) {
        e.respondWith(handleStaticAsset(req));
        return;
    }

    // Navigation requests (trang HTML): dùng stale-while-revalidate
    // Nếu offline, trả offline.html
    if (req.mode === 'navigate') {
        e.respondWith(handleNavigation(req));
        return;
    }
});

/**
 * Xử lý tệp tĩnh: stale-while-revalidate pattern
 */
async function handleStaticAsset(req) {
    const kho = await caches.open(KHO);
    const cu  = await kho.match(req);

    // Tải bản mới ở nền (stale-while-revalidate)
    const dangTai = fetch(req).then((res) => {
        if (res && res.ok) {
            kho.put(req, res.clone());
        }
        return res;
    }).catch(() => null);

    if (cu) {
        dangTai; // Fire and forget
        return cu;
    }

    const moi = await dangTai;
    if (moi) return moi;

    return new Response('', { status: 504, statusText: 'Không có mạng' });
}

/**
 * Xử lý navigation requests: stale-while-revalidate với offline fallback
 */
async function handleNavigation(req) {
    const kho = await caches.open(KHO);
    const cu   = await kho.match(req);

    // Luôn thử lấy bản mới ở nền
    const dangTai = fetch(req)
        .then((res) => {
            if (res && res.ok) {
                kho.put(req, res.clone());
            }
            return res;
        })
        .catch(() => null);

    if (cu) {
        // Trả bản cũ ngay, đồng thời tải bản mới
        dangTai;
        return cu;
    }

    const moi = await dangTai;
    if (moi) return moi;

    // Không có mạng và cũng không có trong kho: trả offline page
    const offlineCache = await caches.match(OFFLINE_PAGE);
    if (offlineCache) return offlineCache;

    // Offline page cũng không cache được: fallback cuối cùng
    return new Response(getOfflineHTML(), {
        status: 503,
        statusText: 'Service Unavailable',
        headers: { 'Content-Type': 'text/html; charset=utf-8' }
    });
}

/**
 * Inline offline HTML fallback (khi offline.html chưa được cache)
 */
function getOfflineHTML() {
    return `<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Không có mạng</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, sans-serif;
            background: #c8203a;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            color: #fff;
        }
        .card {
            background: #fff;
            border-radius: 1.5rem;
            padding: 2rem;
            max-width: 360px;
            text-align: center;
            color: #334155;
        }
        h1 { font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem; }
        p { font-size: 0.9rem; color: #64748b; margin-bottom: 1.5rem; }
        button {
            background: #c8203a; color: #fff; font-weight: 600;
            padding: 0.75rem 1.5rem; border-radius: 9999px;
            border: none; cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Không có kết nối mạng</h1>
        <p>Ứng dụng cần internet để hoạt động. Vui lòng kiểm tra WiFi.</p>
        <button onclick="location.reload()">Thử lại</button>
    </div>
</body>
</html>`;
}

/* ==========================================================
   MESSAGE HANDLERS

   Nhận message từ app.js để:
   - Cập nhật cache khi có phiên bản mới
   - Xoá cache cũ
   - Đăng ký background sync
   ========================================================== */
self.addEventListener('message', (e) => {
    const { action, data } = e.data || {};

    switch (action) {
        case 'SKIP_WAITING':
            // Báo service worker mới rằng nó có thể activate ngay
            self.skipWaiting();
            break;

        case 'CACHE_URLS':
            // App gửi danh sách URL cần cache trước
            cacheUrls(e.source, data?.urls || []);
            break;

        case 'CLEAR_CACHE':
            // Xoá toàn bộ cache
            clearAllCache(e.source);
            break;

        case 'GET_VERSION':
            // Trả về phiên bản SW hiện tại
            e.source.postMessage({ action: 'VERSION', version: PHIEN_BAN });
            break;

        case 'PRECACHE_OFFLINE':
            // Precaches offline page
            precacheOfflinePage();
            break;
    }
});

/**
 * Cache danh sách URL được gửi từ app
 */
async function cacheUrls(client, urls) {
    if (!urls || !urls.length) return;

    const kho = await caches.open(KHO);
    let cached = 0;
    let failed = 0;

    await Promise.allSettled(urls.map(async (url) => {
        try {
            const res = await fetch(url);
            if (res.ok) {
                await kho.put(url, res);
                cached++;
            } else {
                failed++;
            }
        } catch {
            failed++;
        }
    }));

    // Báo app kết quả
    if (client && client.postMessage) {
        client.postMessage({
            action: 'CACHE_COMPLETE',
            cached,
            failed,
            total: urls.length
        });
    }
}

/**
 * Xoá toàn bộ cache
 */
async function clearAllCache(client) {
    const deleted = [];
    for (const ten of await caches.keys()) {
        if (await caches.delete(ten)) deleted.push(ten);
    }

    if (client && client.postMessage) {
        client.postMessage({ action: 'CACHE_CLEARED', caches: deleted });
    }
}

/**
 * Precaches offline page
 */
async function precacheOfflinePage() {
    try {
        const res = await fetch(OFFLINE_PAGE);
        if (res.ok) {
            const kho = await caches.open(KHO);
            await kho.put(OFFLINE_PAGE, res);
            console.log('[SW] Offline page cached');
        }
    } catch (err) {
        console.warn('[SW] Cannot cache offline page:', err);
    }
}

/* ==========================================================
   BACKGROUND SYNC

   Cho phép gửi attendance khi offline, tự động sync khi có mạng
   ========================================================== */

// Đăng ký background sync khi có action cần sync
self.addEventListener('sync', (e) => {
    if (e.tag === BG_SYNC_TAG_ATTENDANCE) {
        e.waitUntil(syncPendingAttendance());
    }
});

/**
 * Sync các attendance action đang chờ
 */
async function syncPendingAttendance() {
    // Lấy danh sách action từ IndexedDB
    try {
        const db = await openDB();
        const tx = db.transaction('pending_actions', 'readonly');
        const store = tx.objectStore('pending_actions');
        const actions = await getAllFromStore(store);

        for (const action of actions) {
            if (action.type === 'attendance') {
                try {
                    const res = await fetch('/api/attendance.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(action.data),
                        credentials: 'include'
                    });

                    if (res.ok) {
                        // Xoá action đã sync thành công
                        const delTx = db.transaction('pending_actions', 'readwrite');
                        delTx.objectStore('pending_actions').delete(action.id);
                    }
                } catch (err) {
                    console.warn('[SW] Sync attendance failed:', err);
                }
            }
        }
    } catch (err) {
        console.warn('[SW] Cannot open pending_actions DB:', err);
    }
}

/**
 * Mở IndexedDB để lưu pending actions
 */
function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('tntt-sw-db', 1);
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        request.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains('pending_actions')) {
                db.createObjectStore('pending_actions', { keyPath: 'id', autoIncrement: true });
            }
        };
    });
}

function getAllFromStore(store) {
    return new Promise((resolve, reject) => {
        const request = store.getAll();
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result || []);
    });
}

/* ==========================================================
   PUSH NOTIFICATIONS
   ========================================================== */
self.addEventListener('push', (e) => {
    e.waitUntil((async () => {
        let tin = {
            title: 'Có việc mới',
            body:  'Mở app để xem chi tiết.',
            url:   '/',
            tag:   'tntt-chung'
        };

        // Cú chuông rỗng -> tự đi hỏi có việc gì
        try {
            const r = await fetch('/api/push.php?action=pending', {
                credentials: 'include',
                cache: 'no-store'
            });
            if (r.ok) {
                const d = await r.json();
                if (d && d.ok && d.item) tin = Object.assign(tin, d.item);
            }
        } catch (err) {
            // Mất mạng: vẫn hiện thông báo chung
        }

        // Báo các tab đang mở tự tải lại dữ liệu
        let isFocused = false;
        const ds = await self.clients.matchAll({ type: 'Window', includeUncontrolled: true });
        for (const c of ds) {
            if (c.visibilityState === 'visible') {
                c.postMessage({ action: 'RELOAD_DATA', url: tin.url });
            }
            if (c.focused) isFocused = true;
        }

        if (isFocused) return;

        await self.registration.showNotification(tin.title, {
            body: tin.body,
            icon: 'assets/img/icon.svg',
            badge: 'assets/img/icon-32.png',
            tag: tin.tag,
            renotify: false,
            data: { url: tin.url },
            vibrate: [80, 40, 80]
        });
    })());
});

/* Chạm vào thông báo: mở đúng màn hình liên quan */
self.addEventListener('notificationclick', (e) => {
    e.notification.close();
    const dich = (e.notification.data && e.notification.data.url) || '/';

    e.waitUntil((async () => {
        const ds = await self.clients.matchAll({ type: 'Window', includeUncontrolled: true });
        for (const c of ds) {
            if (c.url.includes(self.location.origin)) {
                await c.focus();
                if ('navigate' in c) { try { await c.navigate(dich); } catch (err) {} }
                return;
            }
        }
        await self.clients.openWindow(dich);
    })());
});
