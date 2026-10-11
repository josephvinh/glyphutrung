/* ==========================================================
   QUÉT QR ĐIỂM DANH

   Yêu cầu thực tế: ~500 em trong 10 phút = 1,2 giây mỗi em.
   Mọi quyết định dưới đây đều xoay quanh con số đó.

   BỐN CHỖ ĐÃ TỐI ƯU
     1. Gửi theo LÔ. Trước đây mỗi lần quét là một lượt HTTP + 5 truy
        vấn. 500 em thành 500 vòng mạng — sóng yếu ở sân nhà thờ thì
        không kịp. Nay gom lại, cứ 1,2 giây hoặc đủ 25 mã thì gửi một
        lượt. 500 em còn khoảng 20 lượt.
     2. Chặn trùng còn 700ms thay vì 3 giây. Việc chống ghi trùng đã do
        trạng thái "đã điểm danh" và khoá duy nhất của CSDL lo; mốc thời
        gian chỉ để khỏi xử lý lại cùng một khung hình.
     3. Chỉ nhận mã nằm trong VÙNG GIỮA khung ngắm (75% cạnh ngắn), cả hai
        đường giải mã. jsQR chỉ đọc đúng vùng đó (nhanh hơn nhiều trên máy
        yếu); BarcodeDetector đọc cả khung nhưng bị lọc theo vị trí mã.
     4. Có tiếng bíp. GLV cầm 500 tấm thẻ thì không rảnh nhìn màn hình —
        phải nghe mà biết máy đã ăn.

   HAI ĐƯỜNG GIẢI MÃ
     - BarcodeDetector: có sẵn trong Chrome/Android, hệ điều hành giải
       mã nên rất nhanh, không tốn dữ liệu tải về.
     - jsQR: dự phòng cho Safari iOS. 127 KB và CHỈ tải khi cần.

   Camera chỉ chạy trên HTTPS — luật của trình duyệt.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.qrscan = {

    qrMo: false,
    qrTrangThai: '',
    qrDangTai: false,
    qrVuaGhi: [],           // vài em gần nhất, hiện lại cho GLV yên tâm
    qrDaQuet: 0,            // tổng số em đã ghi trong phiên quét này
    qrDangGui: 0,           // còn bao nhiêu mã chưa gửi xong
    qrDenPin: false,        // trạng thái bật/tắt đèn flash pin
    qrCoDenPin: false,      // camera máy có hỗ trợ đèn flash hay không
    qrChanDoan: '',         // dòng thông số camera (tạm thời), không bị ghi đè
    qrHienThanhCong: false, // trigger animation thành công
    qrTenThanhCong: '',     // tên em vừa quét thành công
    qrHienLoi: false,       // trigger animation lỗi
    qrLoiMessage: '',       // thông báo lỗi
    qrDaXemHuongDan: false, // đã xem hướng dẫn chưa (dùng localStorage)
    qrHienHuongDan: false,  // hiện overlay hướng dẫn
    _qrDuong: '',           // đường giải mã đang dùng: BarcodeDetector | jsQR

    _qrStream: null,
    _qrDung: false,
    _qrLan: {},             // mã -> thời điểm xử lý gần nhất
    _qrHang: [],            // mã chờ gửi lên máy chủ
    _qrHenGui: null,
    _qrTiengAm: null,
    _qrTraMa: null,         // Map: mã số -> em, tra O(1) thay vì quét mảng
    _qrJsQrPromise: null,  // Promise đang tải jsQR, null = chưa/chưa tải
    _qrAnimTimer: null, // Timer cho animation

    CHAN_TRUNG_MS: 700,
    LO_TOI_DA: 25,
    CHU_KY_GUI_MS: 1200,
    QR_DECODE_INTERVAL_MS: 50,

    get qrHoTro() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    },

    /**
     * PHẠM VI QUÉT — theo LỚP, nhất quán với chạm tay.
     *
     * Ai có quyền 'edit' điểm danh trên lớp nào thì quét được em trong
     * lớp đó; Quản Trị và Ban Điều Hành quét toàn đoàn.
     *
     * null = không giới hạn.
     */
    get qrLopQuetDuoc() {
        // Dùng writableClasses thay vì availableClasses để KHÔNG bị bộ lọc
        // filterBlock thu hẹp — quyền quét không đổi theo lớp đang chọn.
        const classes = this.writableClasses;
        if (classes === null) return null;              // toàn đoàn
        return classes.length ? classes : [];
    },

    /**
     * Chữ dưới tiêu đề màn quét — nói đúng phạm vi MÁY QUÉT nhận,
     * không phải lớp đang chọn ở danh sách tay. Hai thứ khác nhau, ghi
     * nhầm là GLV tưởng chỉ quét được lớp mình.
     */
    get qrPhamVi() {
        if (this.isUnrestrictedScope) return 'Quét được toàn đoàn';
        const lop = this.writableClasses;
        if (lop === null || !lop.length) return 'Chưa được phân lớp';
        return lop.length === 1
            ? 'Quét được lớp ' + lop[0]
            : 'Quét được các lớp ' + lop.join(', ');
    },

    async _qrCoNative() {
        if (!('BarcodeDetector' in window)) return false;
        try {
            return (await window.BarcodeDetector.getSupportedFormats()).includes('qr_code');
        } catch (e) { return false; }
    },

    /**
     * Tải bảng tra mã số -> em, phạm vi theo khối.
     *
     * Máy chủ trả mảng gọn [mã, id, tên, lớp] thay vì mảng đối tượng —
     * 500 em thì tiết kiệm đáng kể đường truyền.
     *
     * Nếu offline, dùng bảng đã lưu từ lần mở buổi trước đó (nếu có).
     */
    async _qrTaiBangTra() {
        const key = `qrlookup_${this.activeSession.programId}_${this.activeSession.date}`;
        const cached = this._qrTaiBangTra_TuCache(key);

        // Thử lấy từ server trước
        try {
            const r = await this.api('attendance', 'lookup', {
                programId: this.activeSession.programId,
                date: this.activeSession.date
            });
            if (r && r.ok) {
                this._qrTraMa = new Map();
                (r.items || []).forEach(([ma, id, ten, lop]) => {
                    const k = String(ma).trim();
                    this._qrTraMa.set(k, { code: k, id: id, name: ten, className: lop });
                });
                // Lưu cache để dùng offline
                this._qrLuuBangTra(key, this._qrTraMa);
                if (this._qrTraMa.size === 0) {
                    throw new Error('Không có em nào trong phạm vi bạn quét được.\n'
                                  + 'Hãy nhờ Ban Điều Hành kiểm lại phân công khối/lớp.');
                }
                return;
            }
        } catch (e) {
            // Chỉ rơi về cache khi là lỗi mạng. Lỗi nghiệp vụ (401/403)
            // phải được thông báo, không dùng bảng tra cũ.
            if (!e.networkError) throw e;
        }

        // Offline: dùng cache nếu có
        if (cached) {
            this._qrTraMa = cached;
            return;
        }

        // Không có cache và không có mạng
        throw new Error('Không tải được danh sách để quét.\n'
                      + 'Hãy mở buổi lúc có mạng trước.');
    },

    /** Lưu bảng tra vào localStorage (theo programId+date, không lưu PII nhạy cảm) */
    _qrLuuBangTra(key, bangTra) {
        try {
            const items = [];
            bangTra.forEach((em, ma) => {
                // Chỉ lưu 4 trường cần thiết cho quét, không lưu ngày sinh/địa chỉ/SĐT
                items.push([ma, em.id, em.name, em.className]);
            });
            localStorage.setItem(key, JSON.stringify({
                items,
                savedAt: Date.now()
            }));
        } catch (e) {
            console.warn('Không lưu được bảng tra QR:', e);
        }
    },

    /** Đọc bảng tra từ localStorage (hết hạn sau 24 giờ) */
    _qrTaiBangTra_TuCache(key) {
        try {
            const raw = localStorage.getItem(key);
            if (!raw) return null;
            const data = JSON.parse(raw);
            // Kiểm tra TTL: 24 giờ = 86400000 ms
            const MAX_AGE = 86400000;
            if (Date.now() - data.savedAt > MAX_AGE) {
                localStorage.removeItem(key);
                return null;
            }
            const bangTra = new Map();
            (data.items || []).forEach(([ma, id, ten, lop]) => {
                const k = String(ma).trim();
                bangTra.set(k, { code: k, id, name: ten, className: lop });
            });
            return bangTra.size > 0 ? bangTra : null;
        } catch (e) {
            return null;
        }
    },

    _qrTaiJsQR() {
        if (window.jsQR) return Promise.resolve();
        if (this._qrJsQrPromise) return this._qrJsQrPromise; // đang tải rồi
        this._qrJsQrPromise = new Promise((ok, hong) => {
            const s = document.createElement('script');
            s.src = 'assets/js/vendor/jsQR.min.js';
            s.onload = () => { this._qrJsQrPromise = null; ok(); };
            s.onerror = () => { this._qrJsQrPromise = null; hong(new Error('Không tải được bộ giải mã QR.')); };
            document.head.appendChild(s);
        });
        return this._qrJsQrPromise;
    },

    /* ---------- Tiếng bíp ----------
       Tự tạo bằng WebAudio, không phải tải tệp âm thanh nào. */
    _qrBip(tanSo, dai) {
        try {
            if (!this._qrTiengAm) {
                const AC = window.AudioContext || window.webkitAudioContext;
                if (!AC) return;
                this._qrTiengAm = new AC();
            }
            const ac = this._qrTiengAm;
            if (ac.state === 'suspended') ac.resume();
            const os = ac.createOscillator();
            const g  = ac.createGain();
            os.type = 'sine';
            os.frequency.value = tanSo;
            g.gain.setValueAtTime(0.0001, ac.currentTime);
            g.gain.exponentialRampToValueAtTime(0.25, ac.currentTime + 0.01);
            g.gain.exponentialRampToValueAtTime(0.0001, ac.currentTime + dai / 1000);
            os.connect(g); g.connect(ac.destination);
            os.start(); os.stop(ac.currentTime + dai / 1000 + 0.02);
        } catch (e) { /* máy không cho phát tiếng thì thôi */ }
    },
    _qrBipOk()  { this._qrBip(1180, 90); },
    _qrBipLoi() { this._qrBip(320, 220); },
    _qrRung(ms) { if (navigator.vibrate) { try { navigator.vibrate(ms); } catch (e) {} } },

    /** Clear timer animation để tránh conflict khi quét liên tiếp */
    _clearQRTimers() {
        if (this._qrAnimTimer) {
            clearTimeout(this._qrAnimTimer);
            this._qrAnimTimer = null;
        }
        // Reset cả cờ để tránh animation bị kẹt
        this.qrHienThanhCong = false;
        this.qrHienLoi = false;
    },

    /* ---------- CHẨN ĐOÁN camera (tạm thời) ----------
       Ghi thông số camera vào qrChanDoan (dòng chữ nhỏ dưới danh sách, KHÔNG
       lẫn với dòng trạng thái nên không bị ghi đè). CHỈ hiện khi khung hình
       tối/không có (hoặc mở trang với ?qrdebug), lúc camera chạy tốt thì im.
       Nội dung: kích thước khung hình,
       readyState, đang phát/dừng, độ sáng khung từ <video> và từ <canvas>
       hiển thị, trạng thái track, đường giải mã. Chụp màn hình dòng này là
       biết máy đen hình vì đâu:
         sáng video = 0  -> camera không có khung hình
         sáng video > 0 mà canvas = 0 -> lỗi vẽ canvas
         cả hai > 0 mà màn vẫn đen    -> lỗi CSS/compositing
       Gỡ sau khi các máy iOS đều chạy ổn. */
    _qrTuKiemTra(video, phien) {
        const luonHien = /[?&]qrdebug\b/.test(location.search);
        const nho = document.createElement('canvas');
        const nctx = nho.getContext('2d', { willReadFrequently: true });
        const tb = (d) => { let t = 0; for (let i = 0; i < d.length; i += 4) t += d[i] + d[i + 1] + d[i + 2]; return Math.round(t / (d.length / 4) / 3); };
        let n = 0;
        const tick = () => {
            if (this._qrDung || this._qrPhien !== phien) return;
            n++;
            const vw = video.videoWidth, vh = video.videoHeight;
            let sv = -1, sc = -1;
            if (vw && vh) {
                nho.width = 32; nho.height = 32;
                try { nctx.drawImage(video, 0, 0, 32, 32); sv = tb(nctx.getImageData(0, 0, 32, 32).data); }
                catch (e) { sv = -2; }                       // bị chặn đọc
            }
            const cv = this.$refs.qrCanvas;
            if (cv && cv.width) {
                try { sc = tb(cv.getContext('2d').getImageData(cv.width / 2 - 16, cv.height / 2 - 16, 32, 32).data); }
                catch (e) { sc = -2; }
            }
            const tr = this._qrStream ? this._qrStream.getVideoTracks()[0] : null;
            const toi = !(vw && vh && sv > 12) || (n >= 2 && sc <= 12);
            if (toi || luonHien) {
                this.qrChanDoan = vw + '×' + vh + ' rs' + video.readyState + (video.paused ? ' DỪNG' : '')
                    + ' sángV' + sv + ' sángC' + sc
                    + ' ' + (tr ? tr.readyState + (tr.muted ? '/tắt' : '') : '-')
                    + ' ' + (this._qrDuong || '-') + (window.jsQR ? ' ✓' : '');
            }
            if (n < 8 || (toi && n < 40)) setTimeout(tick, 1000);
        };
        setTimeout(tick, 600);
    },

    async moQuetQR() {
        if (!this.activeSession) { window.TNTT.toast.warning('Hãy chọn buổi điểm danh trước khi quét.'); return; }

        // Buổi có thể tắt quét QR (chỉ điểm danh tay)
        const _prog = this.sessionProgram;
        if (_prog && _prog.allowQr === false) {
            window.TNTT.toast.warning('Buổi này không cho phép quét QR — hãy điểm danh bằng cách chạm tên.');
            return;
        }

        // KHÔNG bắt chọn lớp. Các em xếp hàng theo khối, bắt dừng lại
        // đổi lớp mỗi lần là hỏng cả nhịp. Quét theo khối của mình.
        const lopQuet = this.qrLopQuetDuoc;
        if (lopQuet !== null && lopQuet.length === 0) {
            window.TNTT.toast.warning('Bạn chưa được phân vào khối nào nên chưa quét được.\n'
                + 'Hãy nhờ Ban Điều Hành phân công lớp hoặc khối.');
            return;
        }
        if (!this.qrHoTro) {
            window.TNTT.toast.error('Trình duyệt này không mở được camera.\nHãy dùng Chrome hoặc Safari trên điện thoại.');
            return;
        }
        if (!window.isSecureContext) {
            window.TNTT.toast.error('Camera chỉ chạy trên kết nối HTTPS.\nHãy mở trang bằng địa chỉ https://');
            return;
        }

        // Bảng tra riêng cho máy quét: chỉ mã số / tên / lớp.
        //
        // KHÔNG dùng this.students — danh sách đó chỉ gồm các em trong
        // phạm vi mình được XEM (lớp mình), trong khi máy quét cần cả
        // khối. Và nó chứa ngày sinh, địa chỉ, số điện thoại cha mẹ —
        // những thứ việc quét không cần tới.
        this.qrTrangThai = 'Đang tải danh sách…';
        try {
            await this._qrTaiBangTra();
        } catch (e) {
            this.qrMo = false;
            window.TNTT.toast.error(e.message);
            return;
        }

        // Thông báo nếu dùng bảng tra cũ (offline)
        const key = `qrlookup_${this.activeSession.programId}_${this.activeSession.date}`;
        const cached = this._qrTaiBangTra_TuCache(key);
        if (!navigator.onLine && cached) {
            this.qrTrangThai = 'Đang tải danh sách từ bộ nhớ đệm…';
        }

        this.qrVuaGhi = [];
        this.qrDaQuet = 0;
        this.qrDangGui = 0;
        this._qrLan = {};
        this._qrHang = [];
        this.qrTrangThai = 'Đang mở camera…';
        this.qrMo = true;

        // Hiện hướng dẫn lần đầu (dùng localStorage để nhớ)
        try {
            if (!this.qrDaXemHuongDan) {
                this.qrDaXemHuongDan = localStorage.getItem('tntt_qr_huongdan') === '1';
            }
            if (!this.qrDaXemHuongDan) {
                this.qrHienHuongDan = true;
            }
        } catch (e) {
            // Chế độ ẩn danh hoặc bị chặn lưu trữ → hiện hướng dẫn mặc định
            this.qrDaXemHuongDan = false;
            this.qrHienHuongDan = true;
        }

        await this.$nextTick();

        try {
            this._qrStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: 'environment' },
                    width:  { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: false
            });
        } catch (e) {
            this.qrMo = false;
            window.TNTT.toast.error(e && e.name === 'NotAllowedError'
                ? 'Bạn đã từ chối quyền camera. Vào cài đặt trình duyệt để bật lại.'
                : 'Không mở được camera: ' + (e && e.message ? e.message : e));
            return;
        }

        const video = this.$refs.qrVideo;

        // iOS Safari: các thuộc tính này PHẢI đặt TRƯỚC khi gán srcObject.
        // Thiếu 'playsinline'/'muted' thì Safari đòi phát toàn màn hình hoặc
        // chặn tự phát, và khung hình trả về đen dù luồng camera vẫn sống.
        video.muted = true;
        video.setAttribute('muted', '');
        video.setAttribute('playsinline', '');
        video.setAttribute('autoplay', '');
        video.srcObject = this._qrStream;

        // Chờ có kích thước khung hình rồi mới play. Gọi play() trước
        // 'loadedmetadata' trên iOS đôi khi cho ra khung đen đứng yên.
        await new Promise((xong) => {
            if (video.readyState >= 1 && video.videoWidth) return xong();
            const t = setTimeout(xong, 1500);   // đừng treo mãi nếu sự kiện không bắn
            video.addEventListener('loadedmetadata',
                () => { clearTimeout(t); xong(); }, { once: true });
        });

        try {
            await video.play();
        } catch (e) {
            // Vài máy cần gọi lại sau khi đã có khung hình đầu tiên.
            try { await video.play(); } catch (e2) { /* khung ngắm vẫn dùng được */ }
        }

        // Kiểm tra khả năng bật đèn Flash (Torch) của camera thiết bị
        const track = this._qrStream ? this._qrStream.getVideoTracks()[0] : null;
        const cap = (track && track.getCapabilities) ? track.getCapabilities() : {};
        this.qrCoDenPin = !!cap.torch;
        this.qrDenPin = false;

        // Chạm mở camera cũng là một cử chỉ người dùng — mượn luôn để
        // "đánh thức" âm thanh, nếu không iOS chặn tiếng bíp đầu tiên.
        this._qrBip(880, 30);

        this._qrDung = false;
        // Mã phiên: vòng lặp/hẹn giờ của phiên cũ tự dừng nếu mở lại nhanh.
        const phien = this._qrPhien = (this._qrPhien || 0) + 1;
        this.qrTrangThai = 'Đưa thẻ vào khung';

        // CHẨN ĐOÁN tạm thời: chỉ hiện dòng thông số (qrChanDoan) khi khung
        // camera tối/không có khung hình, hoặc khi mở trang với ?qrdebug.
        // Gỡ sau khi các máy iOS đều chạy ổn.
        this._qrTuKiemTra(video, phien);

        // Hình xem trước chạy NGAY (không đợi jsQR tải xong) để GLV thấy camera.
        const native = await this._qrCoNative();
        this._qrDuong = native ? 'BarcodeDetector' : 'jsQR';
        this._qrVongHinh(video, native, phien);

        if (!native) {
            this.qrDangTai = true;
            this.qrTrangThai = 'Đang tải bộ giải mã…';
            try { await this._qrTaiJsQR(); }
            catch (e) {
                if (this._qrPhien !== phien || this._qrDung) return;    // đã đóng/mở lại thì thôi
                this.qrDangTai = false; this.dongQuetQR(); window.TNTT.toast.error(e.message); return;
            }
            if (this._qrPhien !== phien || this._qrDung) return;
            this.qrDangTai = false;
            // Không đè lên tên em / lỗi vừa hiện trong lúc chờ tải
            if (this.qrTrangThai === 'Đang tải bộ giải mã…') this.qrTrangThai = 'Đưa thẻ vào khung';
        }
    },

    /**
     * VÒNG VẼ + GIẢI MÃ. Hình người dùng thấy là <canvas x-ref="qrCanvas">,
     * không phải <video>: iOS WebKit hay không vẽ nổi video camera trực tiếp
     * (ô đen) dù luồng vẫn sống, còn canvas vẽ ổn định.
     *
     * Mỗi khung: cắt hình VUÔNG giữa video (giống object-cover trong ô vuông)
     * vẽ vào canvas -> giải mã TRƯỚC -> rồi mới phủ tối phần ngoài khung ngắm
     * (nếu phủ trước thì mã nằm sát mép bị tối, khó đọc).
     *   - BarcodeDetector: đọc thẳng từ <video>, hệ điều hành giải mã.
     *   - jsQR: chỉ đọc vùng giữa 75% (đúng phần trong khung ngắm).
     */
    _qrVongHinh(video, native, phien) {
        // Thiếu canvas hiển thị thì vẫn giải mã được (canvas ngoài màn hình),
        // chỉ là không có hình xem trước — không để việc quét chết im lặng.
        const cv = this.$refs.qrCanvas || document.createElement('canvas');
        const N = 560;                                   // canvas vuông N×N
        cv.width = cv.height = N;
        // willReadFrequently chỉ cần cho jsQR (đọc điểm ảnh liên tục); đường
        // native không đọc điểm ảnh nên để trình duyệt dùng canvas GPU.
        const ctx = cv.getContext('2d', native ? {} : { willReadFrequently: true });
        const lo = Math.floor(N * 0.125), canh = N - 2 * lo;   // khung ngắm = 75% giữa
        const det = native ? new window.BarcodeDetector({ formats: ['qr_code'] }) : null;
        let laiVe = 0, laiDoc = 0, dangDoc = false;

        const chay = () => {
            if (this._qrDung || this._qrPhien !== phien) return;
            requestAnimationFrame(chay);

            const gio = performance.now();
            if (gio - laiVe < 33) return;                // ~30 khung/giây là đủ
            if (!video.videoWidth || video.readyState < video.HAVE_CURRENT_DATA) return;
            laiVe = gio;

            const vw = video.videoWidth, vh = video.videoHeight;
            const s = Math.min(vw, vh);
            ctx.drawImage(video, Math.floor((vw - s) / 2), Math.floor((vh - s) / 2), s, s, 0, 0, N, N);

            // Giải mã với interval cố định 50ms (~20 lần/giây)
            if (gio - laiDoc >= this.QR_DECODE_INTERVAL_MS) {
                laiDoc = gio;
                if (det) {
                    if (!dangDoc) {
                        dangDoc = true;
                        det.detect(video)
                           .then(ma => {
                               // Chỉ nhận mã nằm trong khung ngắm (75% giữa ô vuông đang
                               // hiển thị), để hai đường giải mã quét CÙNG một vùng.
                               const x0 = (vw - s) / 2 + s * 0.125, y0 = (vh - s) / 2 + s * 0.125, w = s * 0.75;
                               for (const m of ma) {
                                   const b = m.boundingBox;
                                   if (b) {
                                       const cx = b.x + b.width / 2, cy = b.y + b.height / 2;
                                       if (cx < x0 || cx > x0 + w || cy < y0 || cy > y0 + w) continue;
                                   }
                                   this._qrNhan(m.rawValue);
                               }
                           })
                           .catch(() => { /* khung lỗi thì bỏ, thử khung sau */ })
                           .finally(() => { dangDoc = false; });
                    }
                } else if (window.jsQR && video.readyState === video.HAVE_ENOUGH_DATA) {
                    const anh = ctx.getImageData(lo, lo, canh, canh);
                    const kq = window.jsQR(anh.data, anh.width, anh.height, { inversionAttempts: 'dontInvert' });
                    if (kq && kq.data) this._qrNhan(kq.data);
                }
            }

            // Phủ tối phần ngoài khung ngắm (thay cho box-shadow CSS đè lên video)
            ctx.fillStyle = 'rgba(15,23,42,0.5)';
            ctx.fillRect(0, 0, N, lo);
            ctx.fillRect(0, N - lo, N, lo);
            ctx.fillRect(0, lo, lo, canh);
            ctx.fillRect(N - lo, lo, lo, canh);
        };
        requestAnimationFrame(chay);
    },

    _qrNhan(chuoi) {
        const ma = String(chuoi || '').trim();
        if (!ma) return;

        const gio = Date.now();
        if (this._qrLan[ma] && gio - this._qrLan[ma] < this.CHAN_TRUNG_MS) return;
        this._qrLan[ma] = gio;

        const em = this._qrTraMa.get(ma);
        if (!em) {
            // Animation lỗi: mã không tìm thấy
            this._clearQRTimers();
            this.qrLoiMessage = 'Không có em nào mang mã "' + ma + '"';
            this.qrHienLoi = true;
            this._qrAnimTimer = setTimeout(() => { this.qrHienLoi = false; }, 800);
            this.qrTrangThai = this.qrLoiMessage;
            this._qrBipLoi(); this._qrRung(150);
            return;
        }
        // Đã chọn lớp -> chỉ nhận đúng lớp đó (tránh điểm danh nhầm lớp).
        // Máy quét LUÔN theo phạm vi khối, KHÔNG theo ô chọn lớp.
        //
        // Ô chọn lớp là để lọc danh sách điểm danh tay, và với GLV nó bị
        // khoá cứng vào lớp mình. Nếu máy quét cũng nghe theo ô đó thì
        // GLV không bao giờ quét được em lớp bên cạnh — đúng cái mà thay
        // đổi này muốn bỏ.
        const lopQuet = this.qrLopQuetDuoc;   // null = toàn đoàn
        if (lopQuet !== null && !lopQuet.includes(em.className)) {
            this._clearQRTimers();
            this.qrLoiMessage = em.name + ' — ngoài khối bạn phụ trách';
            this.qrHienLoi = true;
            this._qrAnimTimer = setTimeout(() => { this.qrHienLoi = false; }, 800);
            this.qrTrangThai = this.qrLoiMessage;
            this._qrBipLoi(); this._qrRung(150);
            return;
        }
        const truoc = this.studentSessionStatus(em.id);
        if (truoc === 'có mặt' || truoc === 'đi trễ') {
            this.qrTrangThai = em.name + ' — đã điểm danh';
            return;                      // im lặng, không bíp: chuyện bình thường
        }

        this._qrGhiTamThoi(em);
        this._qrHang.push(em.code);
        this.qrDangGui = this._qrHang.length;

        this.qrDaQuet++;
        this.qrTrangThai = em.name;
        this.qrVuaGhi.unshift({ id: em.id, ten: em.name, lop: em.className, luc: this.currentTime() });
        if (this.qrVuaGhi.length > 4) this.qrVuaGhi.pop();
        this._qrBipOk(); this._qrRung(60);

        // Animation thành công: hiện tên bay lên
        this._clearQRTimers();
        this.qrTenThanhCong = em.name;
        this.qrHienThanhCong = true;
        this._qrAnimTimer = setTimeout(() => { this.qrHienThanhCong = false; }, 600);

        // Đủ lô thì gửi ngay, chưa đủ thì hẹn giờ
        if (this._qrHang.length >= this.LO_TOI_DA) this._qrGuiLo();
        else if (!this._qrHenGui) {
            this._qrHenGui = setTimeout(() => this._qrGuiLo(), this.CHU_KY_GUI_MS);
        }
    },

    /**
     * Ghi vào danh sách trên máy NGAY, không đợi máy chủ trả lời.
     * GLV thấy con số nhảy tức thì; máy chủ đồng bộ ở nền.
     */
    _qrGhiTamThoi(em) {
        const s = this.activeSession;
        if (this.attendanceRecord(em.id, s)) return;   // đã có -> bỏ
        this._attThem({
            programId: s.programId, date: s.date, studentId: em.id,
            status: this.isPastCutoff ? 'đi trễ' : 'có mặt',
            method: 'qr', markedBy: this.user.fullName, markedAt: this.currentTime()
        });
    },

    async _qrGuiLo() {
        if (this._qrHenGui) { clearTimeout(this._qrHenGui); this._qrHenGui = null; }
        if (!this._qrHang.length) return;

        const lo = this._qrHang.splice(0, this.LO_TOI_DA);
        const s  = this.activeSession;

        try {
            const r = await this.api('attendance', 'scan', {
                programId: s.programId, date: s.date, codes: lo
            });
            if (!r.ok) {
                // Trả mã về hàng để lượt sau gửi lại, đừng mất điểm danh của em nào
                this._qrHang = lo.concat(this._qrHang);
                this.qrTrangThai = 'Lỗi gửi: ' + (r.error || 'thử lại…');
                this._qrBipLoi();
            } else if (r.skipped && r.skipped.length) {
                this.qrTrangThai = 'Máy chủ bỏ qua ' + r.skipped.length + ' mã';
            }
        } catch (e) {
            this._qrHang = lo.concat(this._qrHang);
            this.qrTrangThai = 'Mất mạng — sẽ gửi lại';
        }

        this.qrDangGui = this._qrHang.length;
        if (this._qrHang.length && !this._qrHenGui) {
            this._qrHenGui = setTimeout(() => this._qrGuiLo(), this.CHU_KY_GUI_MS);
        }
    },

    /** Kết thúc: gửi nốt hàng đợi rồi mới tắt camera */
    async ketThucQuet() {
        this._qrDung = true;

        let vong = 0;
        while (this._qrHang.length && vong < 10) {
            this.qrTrangThai = 'Đang gửi nốt ' + this._qrHang.length + ' mã…';
            await this._qrGuiLo();
            vong++;
        }

        const sot = this._qrHang.length;
        const daGhi = this.qrDaQuet;

        if (sot && this.activeSession) {
            // Tự động lưu toàn bộ mã chưa gửi vào hàng đợi ngoại tuyến để đồng bộ sau
            this._qrHang.forEach(ma => {
                const em = this._qrTraMa ? this._qrTraMa.get(ma) : null;
                if (em) {
                    this.pushOfflineAttendance({
                        programId: this.activeSession.programId,
                        date: this.activeSession.date,
                        studentId: em.id,
                        studentName: em.name,
                        op: 'mark'
                    });
                }
            });
            this._qrHang = [];
            this.qrDangGui = 0;
        }

        this._qrTatCamera();
        this.qrMo = false;

        if (sot) {
            window.TNTT.toast.warning('Đã điểm danh ' + daGhi + ' em.\n'
                + 'Trong đó ' + sot + ' em đã được lưu tạm trên máy do mất mạng.\n'
                + 'Hệ thống sẽ tự động gửi lên máy chủ ngay khi có mạng trở lại!', 7000);
        } else if (daGhi) {
            await this.loadData();      // lấy lại số liệu chuẩn từ máy chủ
            window.TNTT.toast.success('Xong. Đã điểm danh ' + daGhi + ' em bằng thẻ QR.');
        }
    },

    /** Đóng ngay, không đợi — dùng khi bấm dấu X */
    dongQuetQR() {
        this._qrDung = true;
        if (this._qrHang.length && this.activeSession) {
            this._qrHang.forEach(ma => {
                const em = this._qrTraMa ? this._qrTraMa.get(ma) : null;
                if (em) {
                    this.pushOfflineAttendance({
                        programId: this.activeSession.programId,
                        date: this.activeSession.date,
                        studentId: em.id,
                        studentName: em.name,
                        op: 'mark'
                    });
                }
            });
            this._qrHang = [];
            this.qrDangGui = 0;
        }
        this._qrTatCamera();
        this.qrMo = false;
    },

    /** Đóng hướng dẫn và lưu lại đã xem */
    dongHuongDanQR() {
        this.qrHienHuongDan = false;
        this.qrDaXemHuongDan = true;
        try { localStorage.setItem('tntt_qr_huongdan', '1'); } catch (e) { /* ignore */ }
    },

    /** Bật / Tắt Đèn pin (Torch/Flashlight) camera */
    async toggleTorch() {
        if (!this._qrStream) return;
        const track = this._qrStream.getVideoTracks()[0];
        if (!track) return;
        try {
            this.qrDenPin = !this.qrDenPin;
            await track.applyConstraints({
                advanced: [{ torch: this.qrDenPin }]
            });
        } catch (e) {
            console.warn('Không thể điều khiển đèn pin:', e);
            this.qrDenPin = false;
        }
    },

    _qrTatCamera() {
        this.qrDenPin = false;
        this.qrCoDenPin = false;
        this.qrHienHuongDan = false;
        this._clearQRTimers(); // reset qrHienThanhCong, qrHienLoi
        if (this._qrStream) {
            this._qrStream.getTracks().forEach(t => t.stop());   // tắt đèn camera
            this._qrStream = null;
        }
        const v = this.$refs.qrVideo;
        if (v) v.srcObject = null;
        this.qrTrangThai = '';
        this.qrChanDoan = '';
        this.qrDangTai = false;
    },
};
