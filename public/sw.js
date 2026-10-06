/* ==========================================================
   SERVICE WORKER — nhận thông báo khi app đang đóng

   Đây là mảnh chạy NGOÀI trang web, do trình duyệt giữ sống. Không có
   nó thì không nhận được thông báo lúc app không mở.

   Máy chủ gửi một cú chuông RỖNG (không kèm nội dung, xem config/push.php).
   Nhận được chuông, tệp này tự gọi API lấy việc mới nhất rồi hiện lên.

   Vì sao làm vậy: nội dung không phải đi qua máy chủ đẩy của Google hay
   Apple — họ chỉ thấy một tín hiệu rỗng. Và phía PHP đỡ phải mã hoá
   payload, vốn là phần dài và dễ sai nhất của Web Push.
   
   TỐI ƯU:
     - Cache-first với stale-while-revalidate cho static assets
     - Preload critical resources
     - Background sync cho offline actions
   ========================================================== */

// Phiên bản LẤY TỪ chính URL đăng ký (sw.js?v=<hash nội dung bundle>), do
// push.js truyền vào. Đổi giao diện -> hash đổi -> URL SW đổi -> trình duyệt
// cài SW mới -> activate xoá kho cũ (bên dưới). KHÔNG còn bump tay.
const PHIEN_BAN = new URL(self.location.href).searchParams.get('v') || 'tntt-sw-dev';
const KHO      = 'tntt-tinh-' + PHIEN_BAN;

// Critical resources cần preload khi có network
const CRITICAL_ASSETS = [
    '/assets/css/bundle.php',
    '/assets/js/bundle.php',
];

self.addEventListener('install', (e) => {
    // Precache critical assets khi install
    e.waitUntil((async () => {
        const kho = await caches.open(KHO);
        // Chỉ cache những gì thực sự cần cho offline
        // Các file đã có ?v=<timestamp> nên không cần lo cache busting
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
    // Dọn kho của phiên bản cũ, kẻo mỗi lần cập nhật app lại tồn thêm
    // một bộ tệp cũ trong máy người dùng.
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
       
   TỐI ƯU THÊM:
     - Stale-while-revalidate: trả cache ngay, update cache ở nền
     - Compression cache: lưu cả bản nén để tiết kiệm bandwidth
   ========================================================== */
const CHO_GIU = /.(js|css|png|svg|jpg|jpeg|webp|woff2?)$/i;

self.addEventListener('fetch', (e) => {
    const req = e.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);

    // MÁY PHÁT TRIỂN (localhost / 127.0.0.1): KHÔNG dùng kho, luôn đi mạng.
    // Kiểu cache-first bên dưới khiến sửa code phải reload hai lần mới thấy
    // (lần đầu trả bản cũ trong kho). Trên máy dev điều đó rất khó chịu và
    // dễ tưởng "code không ăn". Bản thật (Apache production) vẫn giữ kho để
    // mở nhanh + chạy offline.
    if (url.hostname === 'localhost' || url.hostname === '127.0.0.1') return;

    // Chỉ giữ tệp của chính mình. Phông chữ Google có bộ đệm riêng của
    // trình duyệt rồi, xen vào chỉ tổ rắc rối.
    if (url.origin !== self.location.origin) return;

    // Những thứ TUYỆT ĐỐI không được lấy từ kho
    if (url.pathname.startsWith('/api/')) return;
    if (url.pathname.endsWith('/sw.js')) return;

    // Tệp tĩnh: đuôi .js/.css/ảnh, HOẶC bundle.php (JS/CSS gộp cho bản thật —
    // đuôi .php nhưng bản chất là tệp tĩnh, cần giữ để chạy offline).
    const laTinh = CHO_GIU.test(url.pathname) || url.pathname.endsWith('/bundle.php');
    if (!laTinh) return;

    e.respondWith((async () => {
        const kho = await caches.open(KHO);
        const cu  = await kho.match(req);

        // Đường dẫn kèm ?v=<mtime> là bất biến (đổi tệp là đổi URL), nên có
        // trong kho thì trả luôn, KHÔNG tải lại ở nền. Trước đây mỗi lần mở app
        // lại kéo cả bundle JS/CSS về song song với dữ liệu, tranh băng thông
        // lúc sóng yếu — chính là chỗ làm bản màn hình chính đồng bộ chậm.
        if (cu && url.searchParams.has('v')) return cu;

        const dangTai = fetch(req).then((res) => {
            if (res && res.ok) kho.put(req, res.clone());
            return res;
        }).catch(() => null);

        // Tệp không có ?v=: trả bản kho ngay, tải bản mới ở nền cho lần sau
        if (cu) {
            dangTai;
            return cu;
        }

        const moi = await dangTai;
        if (moi) return moi;

        // Không có mạng mà cũng không có trong kho: trả lỗi rõ ràng,
        // đừng để trình duyệt treo.
        return new Response('', { status: 504, statusText: 'Không có mạng' });
    })());
});

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
            // Gửi kèm TOKEN máy (do máy chủ cấp lúc đăng ký, lưu trong Cache Storage)
            // để máy chủ nhận ra máy này kể cả khi đã đăng xuất. Chưa có token
            // (đăng ký từ bản cũ, chưa mở app lại) thì gửi endpoint như trước —
            // máy chủ chỉ chấp nhận trong thời hạn chuyển tiếp.
            const khoa = new URL('__tntt_push_token', self.registration.scope).href;
            let token = '';
            try {
                const luu = await (await caches.open('tntt-push')).match(khoa);
                if (luu) token = (await luu.text()).trim();
            } catch (err) { /* không đọc được kho thì dùng đường endpoint/phiên */ }
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
                if (d && d.ok && d.item) tin = Object.assign(tin, d.item);
            }
        } catch (err) {
            // Mất mạng hoặc phiên hết hạn: vẫn hiện thông báo chung,
            // còn hơn im lặng để người ta lỡ việc.
        }

        // Báo cho các tab đang mở tự tải lại dữ liệu
        let isFocused = false;
        const ds = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const c of ds) {
            if (c.visibilityState === 'visible') {
                c.postMessage({ action: 'RELOAD_DATA', url: tin.url });
            }
            if (c.focused) isFocused = true;
        }

        // Nếu Admin đang trực tiếp dùng app thì không dội chuông báo hệ thống làm phiền
        if (isFocused) return;

        await self.registration.showNotification(tin.title, {
            body: tin.body,
            icon: 'assets/img/icon.svg',
            badge: 'assets/img/icon-32.png',
            tag: tin.tag,               // cùng tag thì gộp, không dội chuông liên tục
            renotify: false,
            data: { url: tin.url },
            vibrate: [80, 40, 80]
        });
    })());
});

/* Chạm vào thông báo: mở đúng màn hình liên quan.
   Nếu app đang mở sẵn ở đâu đó thì đưa cửa sổ đó lên, không mở thêm. */
self.addEventListener('notificationclick', (e) => {
    e.notification.close();
    const dich = (e.notification.data && e.notification.data.url) || '/';

    e.waitUntil((async () => {
        const ds = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
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
