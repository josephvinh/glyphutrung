/* ==========================================================
   REWARDS — Trạm Đổi Quà (POS) tại quầy (Quản trị / Thủ Thư)
   + XÁC NHẬN ĐƠN ĐẶT TRƯỚC (đơn em tự đặt online ở tracuu.php, SPEC §6.4a)
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.

   Đổi quà là SPEND, gác quyền ĐOÀN-WIDE bằng module 'rewards' (không chia lớp).
   Tái dùng bộ giải mã QR + tiếng bíp của qrscan.js (cùng component nên các
   hàm _qrBip/_qrCoNative/_qrTaiJsQR/qrHoTro đều nằm trên `this`), nhưng giữ
   TRẠNG THÁI camera riêng (rwScan) để không đụng luồng quét điểm danh.

   Màn có HAI CHẾ ĐỘ (rwMode), dùng chung khối "quét thẻ" + camera overlay:
     'pos'     — Đổi tại quầy (POS, đã có từ Phase 1): trừ Mộc trực tiếp.
     'confirm' — Xác nhận đơn đặt trước: tra đơn 'chờ lấy' của em, nhập
                 mật mã đổi quà (hoặc override bằng quyền Thủ thư) để giao.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.rewards = {
    rwMode: 'pos',              // 'pos' | 'confirm' — chế độ đang dùng của màn Trạm Đổi Quà

    // ---- Chế độ POS (đổi tại quầy) ---------------------------------------
    rwStep: 'scan',            // 'scan' | 'shop'
    rwCode: '',                // ô nhập mã thẻ tay
    rwLooking: false,
    rwStudent: null,           // { id, code, fullName, available, currentBalance }
    rwGifts: [],               // danh mục quà đang bán (nạp từ gifts.list)
    rwGiftsLoading: false,
    rwCart: {},                // giftId -> qty
    rwBusy: false,             // đang gửi redeem
    // target: 'pos' | 'confirm' — camera dùng chung cho cả hai chế độ,
    // biết gửi mã vừa quét được về đúng luồng nào (rwLookup / cfLookup).
    rwScan: { open: false, status: '', stream: null, stop: false, target: 'pos' },

    // ---- Chế độ Xác nhận đơn đặt trước -----------------------------------
    cfStep: 'scan',             // 'scan' | 'order'
    cfCode: '',                 // ô nhập mã thẻ tay
    cfLooking: false,
    cfStudent: null,            // { id, code, fullName, available }
    cfPending: null,            // { orderId, total, expiresAt, createdAt, items[] } | null
    cfPassword: '',             // mật mã đổi quà em nhập tại quầy
    cfBusy: false,              // đang gửi confirm/cancel_staff

    get canUseRewards() { return this.canEditModule('rewards'); },
    get rwAvailable() { return this.rwStudent ? Number(this.rwStudent.available) || 0 : 0; },

    get rwCartCount() {
        return Object.values(this.rwCart).reduce((s, q) => s + (Number(q) || 0), 0);
    },
    get rwCartTotal() {
        let t = 0;
        for (const id in this.rwCart) {
            const g = this.rwGifts.find(x => x.id === Number(id));
            if (g) t += (Number(g.stampCost) || 0) * (Number(this.rwCart[id]) || 0);
        }
        return t;
    },

    async loadRewards() {
        this.rwMode = 'pos';
        this.rwStep = 'scan';
        this.rwStudent = null;
        this.rwCart = {};
        this.cfStep = 'scan';
        this.cfCode = '';
        this.cfStudent = null;
        this.cfPending = null;
        this.cfPassword = '';
        await this.loadRewardsGifts();
    },

    // Đổi chế độ POS <-> Xác nhận đơn: dọn sạch màn hình đang dở của CẢ HAI
    // để không lẫn dữ liệu của em này sang em khác khi thủ thư đổi qua lại.
    rwSwitchMode(mode) {
        mode = mode === 'confirm' ? 'confirm' : 'pos';
        if (this.rwMode === mode) return;
        this.rwMode = mode;
        this.rwBackToScan();
        this.cfBackToScan();
    },

    async loadRewardsGifts() {
        this.rwGiftsLoading = true;
        try {
            const r = await this.api('gifts', 'list');
            if (!r || !r.ok) { window.TNTT.toast.error(r && r.error || 'Không nạp được danh mục quà.'); return; }
            // POS chỉ bày quà ĐANG BÁN (máy chủ vẫn kiểm lại lúc đổi).
            this.rwGifts = (r.gifts || []).filter(g => g.status === 'còn bán');
        } finally {
            this.rwGiftsLoading = false;
            this.$nextTick(() => window.lucide && lucide.createIcons());
        }
    },

    // ---- Tra cứu em -----------------------------------------------------
    rwSubmitCode() {
        const code = (this.rwCode || '').trim();
        if (!code) { window.TNTT.toast.warning('Hãy nhập mã thiếu nhi trước.'); return; }
        this.rwLookup(code);
    },

    async rwLookup(code) {
        code = (code || '').trim();
        if (!code) return;
        this.rwLooking = true;
        try {
            const r = await this.api('rewards', 'lookup', { code });
            if (!r || !r.ok) { window.TNTT.toast.error(r && r.error || 'Không tìm thấy em này.'); return; }
            this.rwStudent = r.student;
            this.rwCart = {};
            this.rwCode = '';
            this.rwStep = 'shop';
            this.$nextTick(() => window.lucide && lucide.createIcons());
        } finally {
            this.rwLooking = false;
        }
    },

    rwBackToScan() {
        this.rwStep = 'scan';
        this.rwStudent = null;
        this.rwCart = {};
        this.rwCode = '';
    },

    // ---- Giỏ hàng -------------------------------------------------------
    rwStockLeft(g) {
        return Math.max(0, (Number(g.stock) || 0) - (Number(this.rwCart[g.id]) || 0));
    },
    // Không thể thêm nữa: hết tồn, hoặc thêm 1 món nữa sẽ vượt Mộc khả dụng.
    rwCannotAddMore(g) {
        if (this.rwStockLeft(g) <= 0) return true;
        return (this.rwCartTotal + (Number(g.stampCost) || 0)) > this.rwAvailable;
    },
    // Món hoàn toàn không mua nổi (dù giỏ trống) — để làm mờ thẻ.
    rwGiftUnaffordable(g) {
        return (Number(g.stock) || 0) <= 0 || (Number(g.stampCost) || 0) > this.rwAvailable;
    },

    rwAddToCart(g) {
        if (this.rwCannotAddMore(g)) {
            if (this.rwStockLeft(g) <= 0) window.TNTT.toast.warning('Quà này đã hết hàng.');
            else window.TNTT.toast.warning('Không đủ Mộc để thêm món này.');
            return;
        }
        this.rwCart[g.id] = (Number(this.rwCart[g.id]) || 0) + 1;
    },
    rwRemoveOne(g) {
        const q = (Number(this.rwCart[g.id]) || 0) - 1;
        if (q <= 0) delete this.rwCart[g.id];
        else this.rwCart[g.id] = q;
    },
    rwClearCart() { this.rwCart = {}; },

    // ---- Xác nhận đổi ---------------------------------------------------
    async rwConfirm() {
        if (this.rwBusy) return;
        const items = Object.keys(this.rwCart)
            .map(id => ({ giftId: Number(id), qty: Number(this.rwCart[id]) || 0 }))
            .filter(it => it.qty > 0);
        if (!items.length) { window.TNTT.toast.warning('Giỏ quà đang trống.'); return; }

        const total = this.rwCartTotal;
        if (total > this.rwAvailable) { window.TNTT.toast.error('Số Mộc khả dụng không đủ.'); return; }

        const names = items.map(it => {
            const g = this.rwGifts.find(x => x.id === it.giftId);
            return (g ? g.name : 'Quà') + ' x' + it.qty;
        }).join(', ');
        const ok = await window.TNTT.toast.confirm(
            'Chắc chắn đổi ' + names + ' — trừ ' + total + ' Mộc của em ' + this.rwStudent.fullName + '?',
            { confirmText: 'Đổi quà' });
        if (!ok) return;

        this.rwBusy = true;
        try {
            const r = await this.api('rewards', 'redeem', {
                studentCode: this.rwStudent.code, items
            });
            if (!r || !r.ok) { window.TNTT.toast.error(r && r.error || 'Đổi quà thất bại.'); this._rwBeepErr(); return; }

            // Trừ tồn cục bộ cho khớp máy chủ (không cần nạp lại cả danh mục).
            items.forEach(it => {
                const g = this.rwGifts.find(x => x.id === it.giftId);
                if (g) g.stock = Math.max(0, (Number(g.stock) || 0) - it.qty);
            });

            this._rwBeepOk();
            window.TNTT.toast.success('Đã đổi ' + names + ' (−' + r.total + ' Mộc) cho ' + this.rwStudent.fullName + '.');

            // Quay lại màn quét cho em kế tiếp.
            this.rwBackToScan();
            this.$nextTick(() => window.lucide && lucide.createIcons());
        } catch (e) {
            window.TNTT.toast.error('Mất kết nối khi đổi quà. Hãy thử lại.');
            this._rwBeepErr();
        } finally {
            this.rwBusy = false;
        }
    },

    // ---- XÁC NHẬN ĐƠN ĐẶT TRƯỚC (đơn em tự đặt online ở tracuu.php) ------
    cfSubmitCode() {
        const code = (this.cfCode || '').trim();
        if (!code) { window.TNTT.toast.warning('Hãy nhập mã thiếu nhi trước.'); return; }
        this.cfLookup(code);
    },

    async cfLookup(code) {
        code = (code || '').trim();
        if (!code) return;
        this.cfLooking = true;
        try {
            const r = await this.api('rewards', 'staff_pending', { code });
            if (!r || !r.ok) { window.TNTT.toast.error(r && r.error || 'Không tìm thấy em này.'); return; }
            this.cfStudent = r.student;
            this.cfCode = '';
            if (!r.pending) {
                // Không có đơn chờ lấy: báo rồi ở lại màn quét cho lượt kế tiếp.
                window.TNTT.toast.warning('Em ' + r.student.fullName + ' không có đơn đặt trước.');
                this.cfPending = null;
                return;
            }
            this.cfPending = r.pending;
            this.cfPassword = '';
            this.cfStep = 'order';
            this.$nextTick(() => window.lucide && lucide.createIcons());
        } finally {
            this.cfLooking = false;
        }
    },

    cfBackToScan() {
        this.cfStep = 'scan';
        this.cfStudent = null;
        this.cfPending = null;
        this.cfPassword = '';
        this.cfCode = '';
    },

    // Giao đơn bằng mật mã đổi quà em cung cấp tại quầy.
    async cfConfirm() {
        if (this.cfBusy || !this.cfPending) return;
        const password = (this.cfPassword || '').trim();
        if (!password) { window.TNTT.toast.warning('Hãy nhập mật mã đổi quà của em.'); return; }

        this.cfBusy = true;
        try {
            const r = await this.api('rewards', 'confirm', { orderId: this.cfPending.orderId, password });
            if (!r || !r.ok) { window.TNTT.toast.error(r && r.error || 'Mật mã không đúng, không giao được.'); this._rwBeepErr(); return; }
            this._cfAfterDelivered(r, false);
        } catch (e) {
            window.TNTT.toast.error('Mất kết nối khi giao đơn. Hãy thử lại.');
            this._rwBeepErr();
        } finally {
            this.cfBusy = false;
        }
    },

    // Em quên mật mã: giao bằng QUYỀN Thủ thư (bỏ qua mật mã) — máy chủ ghi log riêng.
    async cfOverride() {
        if (this.cfBusy || !this.cfPending) return;
        const ten = this.cfStudent ? this.cfStudent.fullName : 'em này';
        const ok = await window.TNTT.toast.confirm(
            'Em ' + ten + ' quên mật mã đổi quà.\n'
            + 'Giao đơn #' + this.cfPending.orderId + ' (' + this.cfPending.total + ' Mộc) '
            + 'BẰNG QUYỀN THỦ THƯ, bỏ qua mật mã?\n'
            + 'Thao tác này sẽ được ghi lại trong nhật ký hệ thống.',
            { confirmText: 'Giao bằng quyền Thủ thư' });
        if (!ok) return;

        this.cfBusy = true;
        try {
            const r = await this.api('rewards', 'confirm', { orderId: this.cfPending.orderId, override: true });
            if (!r || !r.ok) { window.TNTT.toast.error(r && r.error || 'Giao đơn thất bại.'); this._rwBeepErr(); return; }
            this._cfAfterDelivered(r, true);
        } catch (e) {
            window.TNTT.toast.error('Mất kết nối khi giao đơn. Hãy thử lại.');
            this._rwBeepErr();
        } finally {
            this.cfBusy = false;
        }
    },

    _cfAfterDelivered(r, viaOverride) {
        const ten = this.cfStudent ? this.cfStudent.fullName : 'em';
        this._rwBeepOk();
        window.TNTT.toast.success(
            (viaOverride ? 'Đã giao (bằng quyền Thủ thư) đơn cho ' : 'Đã giao đơn cho ') + ten
            + ' (−' + r.total + ' Mộc). Còn lại ' + r.available + ' Mộc khả dụng.');
        // Quay lại màn quét cho em kế tiếp.
        this.cfBackToScan();
        this.$nextTick(() => window.lucide && lucide.createIcons());
    },

    // Thủ thư hủy hộ đơn (em đổi ý / không đến lấy) — bỏ qua mật mã, có log.
    async cfCancelOrder() {
        if (this.cfBusy || !this.cfPending) return;
        const ten = this.cfStudent ? this.cfStudent.fullName : 'em này';
        const ok = await window.TNTT.toast.confirm(
            'Hủy hộ đơn #' + this.cfPending.orderId + ' của ' + ten + '?\n'
            + 'Mộc đang giữ và tồn quà sẽ được hoàn lại cho em.',
            { confirmText: 'Hủy đơn' });
        if (!ok) return;

        this.cfBusy = true;
        try {
            const r = await this.api('rewards', 'cancel_staff', { orderId: this.cfPending.orderId });
            if (!r || !r.ok) { window.TNTT.toast.error(r && r.error || 'Hủy đơn thất bại.'); return; }
            window.TNTT.toast.success('Đã hủy đơn #' + r.orderId + ', hoàn lại ' + r.total + ' Mộc.');
            this.cfBackToScan();
        } catch (e) {
            window.TNTT.toast.error('Mất kết nối khi hủy đơn. Hãy thử lại.');
        } finally {
            this.cfBusy = false;
        }
    },

    // Hạn lấy hiển thị kiểu 'HH:mm dd/mm/yyyy' cho dễ đọc tại quầy.
    cfFormatExpiry(iso) {
        if (!iso) return '';
        const d = new Date(String(iso).replace(' ', 'T'));
        if (isNaN(d.getTime())) return String(iso);
        return d.toLocaleString('vi-VN', {
            hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric'
        });
    },

    // ---- Tiếng thành công/ lỗi (tái dùng WebAudio của qrscan.js) --------
    _rwBeepOk() {
        // Hai nốt đi lên cho cảm giác "hoàn tất" (khác bíp quét đơn của qrscan).
        if (typeof this._qrBip === 'function') {
            this._qrBip(880, 90);
            setTimeout(() => this._qrBip(1320, 130), 110);
        }
        if (typeof this._qrRung === 'function') this._qrRung(60);
    },
    _rwBeepErr() {
        if (typeof this._qrBipLoi === 'function') this._qrBipLoi();
        if (typeof this._qrRung === 'function') this._qrRung(150);
    },

    // ---- Quét thẻ bằng camera (một thẻ, dừng sau khi nhận) --------------
    // target: 'pos' (mặc định, đổi tại quầy) | 'confirm' (xác nhận đơn đặt
    // trước) — dùng chung camera, chỉ khác nơi mã quét được sẽ đi tới.
    async rwOpenScan(target) {
        if (!this.qrHoTro) { window.TNTT.toast.error('Trình duyệt này không mở được camera.'); return; }
        if (!window.isSecureContext) { window.TNTT.toast.error('Camera chỉ chạy trên kết nối HTTPS.'); return; }

        this.rwScan.target = target === 'confirm' ? 'confirm' : 'pos';
        this.rwScan.stop = false;
        this.rwScan.status = 'Đang mở camera…';
        this.rwScan.open = true;
        await this.$nextTick();

        try {
            this.rwScan.stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } }, audio: false
            });
        } catch (e) {
            this.rwScan.open = false;
            window.TNTT.toast.error(e && e.name === 'NotAllowedError'
                ? 'Bạn đã từ chối quyền camera. Vào cài đặt trình duyệt để bật lại.'
                : 'Không mở được camera.');
            return;
        }

        const video = this.$refs.rwVideo;

        // iOS Safari: đặt muted/playsinline/autoplay TRƯỚC khi gán srcObject,
        // nếu không Safari chặn tự phát và trả khung hình đen dù luồng vẫn sống.
        video.muted = true;
        video.setAttribute('muted', '');
        video.setAttribute('playsinline', '');
        video.setAttribute('autoplay', '');
        video.srcObject = this.rwScan.stream;

        // Chờ có kích thước khung hình rồi mới play (iOS đôi khi cho khung đen
        // nếu play() gọi trước 'loadedmetadata').
        await new Promise((xong) => {
            if (video.readyState >= 1 && video.videoWidth) return xong();
            const t = setTimeout(xong, 1500);
            video.addEventListener('loadedmetadata',
                () => { clearTimeout(t); xong(); }, { once: true });
        });

        try { await video.play(); }
        catch (e) { try { await video.play(); } catch (e2) { /* khung ngắm vẫn dùng được */ } }
        if (typeof this._qrBip === 'function') this._qrBip(880, 30);   // đánh thức âm thanh iOS
        this.rwScan.status = 'Đưa thẻ vào khung';

        if (typeof this._qrCoNative === 'function' && await this._qrCoNative()) {
            this._rwScanNative(video);
        } else {
            try { await this._qrTaiJsQR(); }
            catch (e) { this.rwCloseScan(); window.TNTT.toast.error('Không tải được bộ giải mã QR.'); return; }
            this._rwScanJs(video);
        }
    },

    _rwScanNative(video) {
        const det = new window.BarcodeDetector({ formats: ['qr_code'] });
        const chay = async () => {
            if (this.rwScan.stop) return;
            try {
                const ma = await det.detect(video);
                if (ma && ma.length) { this._rwOnCode(ma[0].rawValue); return; }
            } catch (e) { /* khung lỗi thì thử khung sau */ }
            requestAnimationFrame(chay);
        };
        requestAnimationFrame(chay);
    },

    _rwScanJs(video) {
        const cv = document.createElement('canvas');
        const ctx = cv.getContext('2d', { willReadFrequently: true });
        let lan = 0;
        const chay = () => {
            if (this.rwScan.stop) return;
            requestAnimationFrame(chay);
            const gio = performance.now();
            if (gio - lan < 50) return;
            lan = gio;
            if (video.readyState !== video.HAVE_ENOUGH_DATA) return;

            const vw = video.videoWidth, vh = video.videoHeight;
            const canh = Math.floor(Math.min(vw, vh) * 0.75);
            const sx = Math.floor((vw - canh) / 2), sy = Math.floor((vh - canh) / 2);
            const dich = 400;
            cv.width = cv.height = dich;
            ctx.drawImage(video, sx, sy, canh, canh, 0, 0, dich, dich);
            const anh = ctx.getImageData(0, 0, dich, dich);
            const kq = window.jsQR(anh.data, anh.width, anh.height, { inversionAttempts: 'dontInvert' });
            if (kq && kq.data) this._rwOnCode(kq.data);
        };
        requestAnimationFrame(chay);
    },

    _rwOnCode(raw) {
        const code = String(raw || '').trim();
        if (!code) return;
        if (typeof this._qrBipOk === 'function') this._qrBipOk();
        if (typeof this._qrRung === 'function') this._qrRung(60);
        const target = this.rwScan.target;
        this.rwCloseScan();
        if (target === 'confirm') this.cfLookup(code);
        else this.rwLookup(code);
    },

    rwCloseScan() {
        this.rwScan.stop = true;
        this.rwScan.open = false;
        if (this.rwScan.stream) {
            try { this.rwScan.stream.getTracks().forEach(t => t.stop()); } catch (e) {}
            this.rwScan.stream = null;
        }
    },
};
