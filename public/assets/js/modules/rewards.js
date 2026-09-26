/* ==========================================================
   REWARDS — Trạm Đổi Quà (POS) tại quầy (Quản trị / Thủ Thư)
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.

   Đổi quà là SPEND, gác quyền ĐOÀN-WIDE bằng module 'rewards' (không chia lớp).
   Tái dùng bộ giải mã QR + tiếng bíp của qrscan.js (cùng component nên các
   hàm _qrBip/_qrCoNative/_qrTaiJsQR/qrHoTro đều nằm trên `this`), nhưng giữ
   TRẠNG THÁI camera riêng (rwScan) để không đụng luồng quét điểm danh.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.rewards = {
    rwStep: 'scan',            // 'scan' | 'shop'
    rwCode: '',                // ô nhập mã thẻ tay
    rwLooking: false,
    rwStudent: null,           // { id, code, fullName, available, currentBalance }
    rwGifts: [],               // danh mục quà đang bán (nạp từ gifts.list)
    rwGiftsLoading: false,
    rwCart: {},                // giftId -> qty
    rwBusy: false,             // đang gửi redeem
    rwScan: { open: false, status: '', stream: null, stop: false },

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
        this.rwStep = 'scan';
        this.rwStudent = null;
        this.rwCart = {};
        await this.loadRewardsGifts();
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
    async rwOpenScan() {
        if (!this.qrHoTro) { window.TNTT.toast.error('Trình duyệt này không mở được camera.'); return; }
        if (!window.isSecureContext) { window.TNTT.toast.error('Camera chỉ chạy trên kết nối HTTPS.'); return; }

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
        video.srcObject = this.rwScan.stream;
        video.setAttribute('playsinline', '');
        try { await video.play(); } catch (e) { /* một số máy cần cử chỉ, bỏ qua */ }
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
        this.rwCloseScan();
        this.rwLookup(code);
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
