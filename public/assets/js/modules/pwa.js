/**
 * PWA Module — Quản lý Service Worker & Offline Support
 *
 * Tính năng:
 * - Đăng ký & cập nhật service worker
 * - Quản lý background sync cho attendance offline
 * - Giao tiếp với SW qua postMessage
 * - Precaching resources
 */
window.TNTT = window.TNTT || {};
window.TNTT.pwa = {
    swReg: null,
    swVersion: null,
    updateAvailable: false,

    /**
     * Khởi tạo PWA
     */
    async init() {
        if (!('serviceWorker' in navigator)) {
            console.log('[PWA] Service Worker not supported');
            return;
        }

        await this.registerSW();
        this.listenForUpdates();
        this.setupOfflineDetection();
        this.setupUpdatePrompt();

        // Precaches offline page sau khi SW ready
        this.precacheOfflinePage();
    },

    /**
     * Đăng ký Service Worker
     */
    async registerSW() {
        try {
            this.swReg = await navigator.serviceWorker.register('/sw.js', {
                scope: '/'
            });

            console.log('[PWA] SW registered:', this.swReg.scope);

            // Kiểm tra phiên bản
            const resp = await fetch('/sw.js');
            const text = await resp.text();
            const match = text.match(/PHIEN_BAN\s*=\s*['"]([^'"]+)['"]/);
            if (match) this.swVersion = match[1];

            // SW mới đang chờ
            this.swReg.addEventListener('updatefound', () => {
                console.log('[PWA] New SW found');
                this.updateAvailable = true;
                // Báo user có bản mới
                if (window.TNTT.toast) {
                    window.TNTT.toast.info('Có bản cập nhật mới! Tải lại trang để áp dụng.');
                }
            });

            // Kiểm tra controller change (user đã accept update)
            navigator.serviceWorker.addEventListener('controllerchange', () => {
                window.location.reload();
            });

        } catch (err) {
            console.error('[PWA] SW registration failed:', err);
        }
    },

    /**
     * Lắng nghe cập nhật từ SW
     */
    listenForUpdates() {
        if (!navigator.serviceWorker.controller) return;

        navigator.serviceWorker.controller.addEventListener('message', (e) => {
            const { action, data } = e.data || {};
            switch (action) {
                case 'RELOAD_DATA':
                    // SW báo có data mới, thông báo app reload
                    if (window.TNTT.toast) {
                        window.TNTT.toast.info('Dữ liệu đã được cập nhật.');
                    }
                    break;

                case 'VERSION':
                    console.log('[PWA] SW version:', data?.version);
                    break;
            }
        });
    },

    /**
     * Thiết lập phát hiện offline
     */
    setupOfflineDetection() {
        window.addEventListener('online', () => {
            document.body.classList.remove('is-offline');
            console.log('[PWA] Back online');
            if (window.TNTT.toast) {
                window.TNTT.toast.success('Đã kết nối lại mạng');
            }
        });

        window.addEventListener('offline', () => {
            document.body.classList.add('is-offline');
            console.log('[PPA] Gone offline');
        });

        // Check initial state
        if (!navigator.onLine) {
            document.body.classList.add('is-offline');
        }
    },

    /**
     * Thiết lập nút cập nhật app
     */
    setupUpdatePrompt() {
        // SW gửi message yêu cầu skip waiting
        if (this.swReg && this.swReg.waiting) {
            this.swReg.waiting.postMessage({ action: 'SKIP_WAITING' });
        }
    },

    /**
     * Báo user có bản cập nhật và reload
     */
    promptUpdate() {
        if (!this.updateAvailable) return;

        if (confirm('Có bản cập nhật mới! Bạn có muốn tải lại ngay không?')) {
            this.applyUpdate();
        }
    },

    /**
     * Áp dụng bản cập nhật
     */
    applyUpdate() {
        if (this.swReg && this.swReg.waiting) {
            this.swReg.waiting.postMessage({ action: 'SKIP_WAITING' });
        }
    },

    /**
     * Precaches offline page
     */
    async precacheOfflinePage() {
        if (!navigator.serviceWorker.controller) return;
        navigator.serviceWorker.controller.postMessage({
            action: 'PRECACHE_OFFLINE'
        });
    },

    /**
     * Cache thêm URLs (ví dụ: module pages)
     */
    async cacheUrls(urls) {
        if (!navigator.serviceWorker.controller) return;
        navigator.serviceWorker.controller.postMessage({
            action: 'CACHE_URLS',
            data: { urls }
        });
    },

    /**
     * Xoá toàn bộ cache
     */
    async clearCache() {
        if (!navigator.serviceWorker.controller) return;
        navigator.serviceWorker.controller.postMessage({
            action: 'CLEAR_CACHE'
        });
        if (window.TNTT.toast) {
            window.TNTT.toast.success('Đã xoá bộ nhớ cache');
        }
    },

    /**
     * Đăng ký background sync cho attendance
     */
    async registerAttendanceSync(attendanceData) {
        // Lưu vào IndexedDB trước
        await this.savePendingAttendance(attendanceData);

        // Đăng ký sync với SW
        if ('serviceWorker' in navigator && 'sync' in window.SyncManager?.prototype || false) {
            try {
                const reg = await navigator.serviceWorker.ready;
                await reg.sync.register('tntt-attendance-sync');
                console.log('[PWA] Attendance sync registered');
            } catch (err) {
                console.warn('[PWA] Background sync not available:', err);
                // Fallback: thử gửi trực tiếp
                return this.submitAttendanceDirect(attendanceData);
            }
        } else {
            // Sync not supported, gửi trực tiếp
            return this.submitAttendanceDirect(attendanceData);
        }
    },

    /**
     * Lưu attendance vào IndexedDB để sync sau
     */
    async savePendingAttendance(data) {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open('tntt-sw-db', 1);
            request.onerror = () => reject(request.error);
            request.onupgradeneeded = (e) => {
                const db = e.target.result;
                if (!db.objectStoreNames.contains('pending_actions')) {
                    db.createObjectStore('pending_actions', { keyPath: 'id', autoIncrement: true });
                }
            };
            request.onsuccess = () => {
                const db = request.result;
                const tx = db.transaction('pending_actions', 'readwrite');
                const store = tx.objectStore('pending_actions');
                store.add({ type: 'attendance', data, timestamp: Date.now() });
                resolve();
            };
        });
    },

    /**
     * Gửi attendance trực tiếp (khi online)
     */
    async submitAttendanceDirect(data) {
        try {
            const resp = await fetch('/api/attendance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
                credentials: 'include'
            });
            return await resp.json();
        } catch (err) {
            console.error('[PWA] Direct attendance submit failed:', err);
            throw err;
        }
    },

    /**
     * Lấy số action đang chờ sync
     */
    async getPendingCount() {
        return new Promise((resolve) => {
            const request = indexedDB.open('tntt-sw-db', 1);
            request.onerror = () => resolve(0);
            request.onsuccess = () => {
                try {
                    const db = request.result;
                    if (!db.objectStoreNames.contains('pending_actions')) {
                        resolve(0);
                        return;
                    }
                    const tx = db.transaction('pending_actions', 'readonly');
                    const store = tx.objectStore('pending_actions');
                    const countReq = store.count();
                    countReq.onsuccess = () => resolve(countReq.result);
                    countReq.onerror = () => resolve(0);
                } catch {
                    resolve(0);
                }
            };
            request.onupgradeneeded = () => resolve(0);
        });
    }
};

// Auto-init khi DOM ready
document.addEventListener('DOMContentLoaded', () => {
    window.TNTT.pwa.init();
});
