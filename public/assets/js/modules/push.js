/* ==========================================================
   THÔNG BÁO ĐẨY — phía trình duyệt

   Mục tiêu: khi có việc (thông báo mới, đơn chờ duyệt...) thì hiện
   ra ngay màn hình khoá điện thoại, kể cả lúc app đang đóng.

   BA ĐIỀU KIỆN, thiếu một là không chạy:
     1. Trang phải chạy qua https (localhost thì được miễn)
     2. Người dùng phải bấm Cho phép — trình duyệt chỉ hỏi khi có
        thao tác chạm, nên phải gắn vào một cái nút thật
     3. TRÊN iPHONE: phải "Thêm vào Màn hình chính" trước. Mở bằng
        Safari thường thì iOS không cho đăng ký, và không có cách
        nào lách. Đây là giới hạn của Apple, không phải lỗi app.

   Bật theo từng máy, không theo tài khoản: một người dùng cả điện
   thoại lẫn máy tính thì bật ở máy nào máy đó nhận.
   ========================================================== */

window.TNTT = window.TNTT || {};
window.TNTT.push = {

    tbHoTro: false,      // trình duyệt này làm được không
    tbDaBat: false,      // máy này đã bật chưa
    tbBiChan: false,     // người dùng đã bấm Chặn — phải vào cài đặt trình duyệt mở lại
    tbCanCaiApp: false,  // iPhone chưa thêm vào màn hình chính
    tbDangChay: false,
    tbSoMay: 0,
    _swReg: null,

    /** Gọi trong init(). Không hỏi quyền, chỉ dò xem đang ở tình trạng nào. */
    async pushKhoiDong() {
        this.tbHoTro = 'serviceWorker' in navigator
                    && 'PushManager' in window
                    && 'Notification' in window;
        this.tbBiChan = ('Notification' in window) && Notification.permission === 'denied';

        if (!this.tbHoTro) {
            // iPhone chạy Safari thường: thiếu PushManager. Nếu đúng là
            // iOS thì gợi ý thêm vào màn hình chính, vì làm vậy là có.
            const iOS = /iPad|iPhone|iPod/.test(navigator.userAgent)
                     || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            this.tbCanCaiApp = iOS && !window.matchMedia('(display-mode: standalone)').matches;
            return;
        }

        try {
            this._swReg = await navigator.serviceWorker.register('sw.js');
            const dk = await this._swReg.pushManager.getSubscription();
            this.tbDaBat = !!dk && Notification.permission === 'granted';
            await this._pushDongBo(dk ? dk.endpoint : '');
        } catch (e) {
            console.warn('[TNTT] không đăng ký được service worker:', e);
            this.tbHoTro = false;
        }
    },

    async _pushDongBo(endpoint) {
        try {
            const res = await window.TNTT.csrfFetch('api/push.php?action=status&endpoint=' + encodeURIComponent(endpoint));
            const d = await res.json();
            if (!d.ok) return;
            this.tbSoMay = d.devices || 0;
            if (!d.available) { this.tbHoTro = false; return; }   // máy chủ chưa cấu hình khoá
            // Máy chủ mới là nguồn sự thật: đăng ký còn trong trình duyệt
            // nhưng máy chủ đã xoá (gỡ app, đổi khoá) thì coi như chưa bật.
            if (endpoint) this.tbDaBat = d.onThisDevice;
        } catch (e) { /* mất mạng thì cứ để nguyên trạng thái đang hiện */ }
    },

    /** Nút gạt trong Cài đặt → Cá nhân */
    async pushGat() {
        if (this.tbDangChay) return;
        this.tbDangChay = true;
        try {
            if (this.tbDaBat) await this._pushTat();
            else              await this._pushBat();
        } finally {
            this.tbDangChay = false;
        }
    },

    async _pushBat() {
        // Phải hỏi quyền TRONG luồng chạm này, không được await gì
        // nặng trước đó — vài trình duyệt coi là mất "cử chỉ người dùng"
        // và lặng lẽ từ chối.
        const quyen = await Notification.requestPermission();
        if (quyen !== 'granted') {
            this.tbBiChan = quyen === 'denied';
            alert(this.tbBiChan
                ? 'Trình duyệt đang chặn thông báo của trang này.\n\n'
                + 'Mở lại ở: Cài đặt trình duyệt → Thông báo → tìm địa chỉ trang này → Cho phép.'
                : 'Chưa được cho phép nên chưa bật được thông báo.');
            return;
        }

        const r = await window.TNTT.csrfFetch('api/push.php?action=key');
        const k = await r.json();
        if (!k.ok || !k.key) {
            alert('Máy chủ chưa cấu hình khoá thông báo.\n\n'
                + 'Người quản trị cần chạy: php config/tao_khoa_push.php');
            return;
        }

        const dk = await this._swReg.pushManager.subscribe({
            userVisibleOnly: true,                    // bắt buộc, và app này đúng là luôn hiện ra
            applicationServerKey: this._sangMang(k.key)
        });

        const luu = await this.api('push', 'subscribe', { endpoint: dk.endpoint });
        if (!luu.ok) {
            await dk.unsubscribe();                   // máy chủ không nhận thì đừng để lại rác
            alert(luu.error || 'Không lưu được đăng ký.');
            return;
        }
        this.tbDaBat = true;
        await this._pushDongBo(dk.endpoint);
    },

    async _pushTat() {
        const dk = await this._swReg.pushManager.getSubscription();
        if (dk) {
            await this.api('push', 'unsubscribe', { endpoint: dk.endpoint });
            await dk.unsubscribe();
        }
        this.tbDaBat = false;
        await this._pushDongBo('');
    },

    /** Gửi thử cho chính mình, để biết chắc là chạy được */
    async pushThu() {
        if (!this.tbDaBat) { alert('Bật thông báo trên máy này trước đã.'); return; }
        const r = await this.api('push', 'test', {});
        if (!r.ok) { alert(r.error || 'Không gửi được.'); return; }
        alert('Đã gửi tới ' + r.devices + ' máy.\n\n'
            + 'Thông báo có thể chậm vài giây. Thử khoá màn hình rồi chờ xem.');
    },

    /** Khoá VAPID là base64 kiểu URL; PushManager đòi Uint8Array */
    _sangMang(b64) {
        const day = atob((b64 + '='.repeat((4 - b64.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/'));
        const m = new Uint8Array(day.length);
        for (let i = 0; i < day.length; i++) m[i] = day.charCodeAt(i);
        return m;
    },
};
