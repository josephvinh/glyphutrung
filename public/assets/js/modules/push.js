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
    tbLoiGui: null,      // mã lỗi của lần gửi gần nhất tới máy này (null = không lỗi)
    _epHienTai: '',
    _canToken: false,    // máy chủ báo dòng đăng ký của máy này chưa có token (đăng ký từ bản cũ)
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
            // Đăng ký kèm ?v=<hash nội dung> để SW tự đổi phiên bản khi bundle
            // đổi (đổi giao diện là cache tự làm mới, khỏi bump tay PHIEN_BAN).
            const _av = (typeof window !== 'undefined' && window.__ASSET_V) ? ('?v=' + window.__ASSET_V) : '';
            this._swReg = await navigator.serviceWorker.register('sw.js' + _av);
            const dk = await this._swReg.pushManager.getSubscription();
            this.tbDaBat = !!dk && Notification.permission === 'granted';
            const daDongBo = await this._pushDongBo(dk ? dk.endpoint : '');

            // Máy đã bật từ bản cũ (chưa có token) hoặc token cục bộ bị mất: đăng ký lại
            // lặng lẽ để lấy token, service worker cần nó để nhận nội dung khi đã đăng xuất.
            // Chỉ làm khi status vừa trả OK (đang đăng nhập) VÀ máy chủ xác nhận dòng
            // này là của tài khoản đang đăng nhập (tbDaBat lấy từ onThisDevice). Máy đang
            // giữ đăng ký của tài khoản khác thì KHÔNG tự đụng vào — để người dùng tự bật.
            if (daDongBo && dk && Notification.permission === 'granted' && this.tbDaBat
                && (this._canToken || !(await this._tokenDoc()))) {
                const luu = await window.TNTT.core.api('push', 'subscribe', { endpoint: dk.endpoint });
                if (luu.ok && luu.token) await this._tokenLuu(luu.token);
            }
        } catch (e) {
            console.warn('[TNTT] không đăng ký được service worker:', e);
            this.tbHoTro = false;
        }
    },

    /** @returns {Promise<boolean>} true nếu máy chủ trả trạng thái hợp lệ (đang đăng nhập) */
    async _pushDongBo(endpoint) {
        try {
            const res = await window.TNTT.csrfFetch('api/push.php?action=status&endpoint=' + encodeURIComponent(endpoint));
            const d = await res.json();
            if (!d.ok) return false;
            this.tbSoMay = d.devices || 0;
            this._epHienTai = endpoint;
            this._canToken = !!d.needToken;
            this.tbLoiGui = (typeof d.lastFailCode === 'number') ? d.lastFailCode : null;
            if (!d.available) { this.tbHoTro = false; return false; }   // máy chủ chưa cấu hình khoá
            // Máy chủ mới là nguồn sự thật: đăng ký còn trong trình duyệt
            // nhưng máy chủ đã xoá (gỡ app, đổi khoá) thì coi như chưa bật.
            if (endpoint) this.tbDaBat = d.onThisDevice;
            return true;
        } catch (e) { /* mất mạng thì cứ để nguyên trạng thái đang hiện */ }
        return false;
    },

    /* ---- Token máy: lưu trong Cache Storage vì cả trang lẫn service worker đều đọc được,
       và còn nguyên sau khi đăng xuất. Mọi lỗi đều nuốt im lặng (không có kho thì
       service worker quay về dùng phiên / câu thông báo chung). ---- */
    _tokenKhoa() {
        return new URL('__tntt_push_token', this._swReg ? this._swReg.scope : location.href).href;
    },
    async _tokenLuu(token) {
        try {
            if (!token || !('caches' in window)) return;
            await (await caches.open('tntt-push')).put(this._tokenKhoa(),
                new Response(token, { headers: { 'Content-Type': 'text/plain' } }));
        } catch (e) { /* bỏ qua */ }
    },
    async _tokenDoc() {
        try {
            if (!('caches' in window)) return '';
            const r = await (await caches.open('tntt-push')).match(this._tokenKhoa());
            return r ? (await r.text()).trim() : '';
        } catch (e) { return ''; }
    },
    async _tokenXoa() {
        try {
            if ('caches' in window) await (await caches.open('tntt-push')).delete(this._tokenKhoa());
        } catch (e) { /* bỏ qua */ }
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
            window.TNTT.toast.warning(this.tbBiChan
                ? 'Trình duyệt đang chặn thông báo của trang này.\n\n'
                + 'Mở lại ở: Cài đặt trình duyệt → Thông báo → tìm địa chỉ trang này → Cho phép.'
                : 'Chưa được cho phép nên chưa bật được thông báo.');
            return;
        }

        const r = await window.TNTT.csrfFetch('api/push.php?action=key');
        const k = await r.json();
        if (!k.ok || !k.key) {
            window.TNTT.toast.error('Máy chủ chưa cấu hình khoá thông báo.\n\n'
                + 'Người quản trị cần chạy: php config/tao_khoa_push.php');
            return;
        }

        const dangKy = () => this._swReg.pushManager.subscribe({
            userVisibleOnly: true,                    // bắt buộc, và app này đúng là luôn hiện ra
            applicationServerKey: this._sangMang(k.key)
        });

        let dk = await dangKy();
        let luu = await window.TNTT.core.api('push', 'subscribe', { endpoint: dk.endpoint });
        if (!luu.ok && luu.code === 'endpoint_owned') {
            // Máy dùng chung: đăng ký này đang thuộc tài khoản khác. Huỷ đăng ký trong
            // trình duyệt rồi đăng ký lại để lấy endpoint MỚI (chỉ thử lại một lần).
            try { await dk.unsubscribe(); } catch (e) { /* bỏ qua */ }
            dk = await dangKy();
            luu = await window.TNTT.core.api('push', 'subscribe', { endpoint: dk.endpoint });
        }
        if (!luu.ok) {
            try { await dk.unsubscribe(); } catch (e) { /* bỏ qua */ }   // máy chủ không nhận thì đừng để lại rác
            window.TNTT.toast.error(luu.error || 'Không lưu được đăng ký.');
            return;
        }
        await this._tokenLuu(luu.token);
        this.tbDaBat = true;
        await this._pushDongBo(dk.endpoint);
    },

    async _pushTat() {
        const dk = await this._swReg.pushManager.getSubscription();
        if (dk) {
            await window.TNTT.core.api('push', 'unsubscribe', { endpoint: dk.endpoint });
            await dk.unsubscribe();
        }
        await this._tokenXoa();
        this.tbDaBat = false;
        await this._pushDongBo('');
    },

    /** Gửi thử cho chính mình, để biết chắc là chạy được */
    async pushThu() {
        if (!this.tbDaBat) { window.TNTT.toast.warning('Bật thông báo trên máy này trước đã.'); return; }
        const r = await window.TNTT.core.api('push', 'test', {});
        if (!r.ok) { window.TNTT.toast.error(r.error || 'Không gửi được.'); return; }
        window.TNTT.toast.success('Đã xếp hàng gửi tới ' + r.devices + ' máy.\n\n'
            + 'Nếu sau khoảng 30 giây vẫn không thấy, mở lại mục này để xem trạng thái.');
        // Vài giây sau hỏi lại để hiện lỗi (nếu có) của lần gửi vừa rồi
        setTimeout(() => {
            this._pushDongBo(this._epHienTai || '').then(() => {
                if (this.tbLoiGui !== null) {
                    window.TNTT.toast.warning('Lần gửi gần nhất bị lỗi ('
                        + (this.tbLoiGui === 0 ? 'không kết nối được' : 'mã ' + this.tbLoiGui) + ').\n\n'
                        + 'Tắt rồi bật lại thông báo trên máy này, hoặc thử lại sau.');
                }
            });
        }, 6000);
    },

    /** Khoá VAPID là base64 kiểu URL; PushManager đòi Uint8Array */
    _sangMang(b64) {
        const day = atob((b64 + '='.repeat((4 - b64.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/'));
        const m = new Uint8Array(day.length);
        for (let i = 0; i < day.length; i++) m[i] = day.charCodeAt(i);
        return m;
    },
};
