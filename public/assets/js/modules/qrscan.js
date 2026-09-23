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
     3. Chỉ giải mã VÙNG GIỮA khung ngắm, không giải mã cả ảnh. Nhanh
        hơn nhiều trên máy yếu.
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

    _qrStream: null,
    _qrDung: false,
    _qrLan: {},             // mã -> thời điểm xử lý gần nhất
    _qrHang: [],            // mã chờ gửi lên máy chủ
    _qrHenGui: null,
    _qrTiengAm: null,
    _qrTraMa: null,         // Map: mã số -> em, tra O(1) thay vì quét mảng

    CHAN_TRUNG_MS: 700,
    LO_TOI_DA: 25,
    CHU_KY_GUI_MS: 1200,

    get qrHoTro() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    },

    /**
     * PHẠM VI QUÉT — theo KHỐI, không theo lớp.
     *
     * Lúc điểm danh các em xếp hàng theo khối. Ai được phân vào khối
     * nào thì quét được mọi em trong khối đó; Quản Trị và Ban Điều
     * Hành quét toàn đoàn.
     *
     * Khác với writableClasses (quyền SỬA hồ sơ thiếu nhi) vốn giữ
     * theo lớp — hai việc khác nhau, đừng gộp.
     *
     * null = không giới hạn.
     */
    get qrLopQuetDuoc() {
        if (this.isUnrestrictedScope) return null;       // toàn đoàn
        // Kiêm nhiệm: hợp mọi KHỐI mình có mặt (khớp scan_class_ids ở backend).
        const khoi = this.myBlocks;
        if (!khoi.length) return [];                     // chưa phân khối
        return this.classes.filter(c => khoi.includes(c.block)).map(c => c.name);
    },

    /**
     * Chữ dưới tiêu đề màn quét — nói đúng phạm vi MÁY QUÉT nhận,
     * không phải lớp đang chọn ở danh sách tay. Hai thứ khác nhau, ghi
     * nhầm là GLV tưởng chỉ quét được lớp mình.
     */
    get qrPhamVi() {
        if (this.isUnrestrictedScope) return 'Quét được toàn đoàn';
        const khoi = this.myBlocks;
        if (!khoi.length) return 'Chưa được phân khối';
        return khoi.length === 1
            ? 'Quét được cả khối ' + khoi[0]
            : 'Quét được các khối ' + khoi.join(', ');
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
     */
    async _qrTaiBangTra() {
        const r = await this.api('attendance', 'lookup', {
            programId: this.activeSession.programId,
            date: this.activeSession.date
        });
        if (!r || !r.ok) throw new Error(r && r.error ? r.error : 'Không tải được danh sách để quét.');

        this._qrTraMa = new Map();
        (r.items || []).forEach(([ma, id, ten, lop]) => {
            const key = String(ma).trim();
            // PHẢI giữ cả 'code' trong giá trị, không chỉ làm khoá Map:
            // _qrNhan() đẩy em.code vào hàng đợi gửi lên máy chủ. Thiếu
            // trường này thì gửi lên toàn null và máy chủ bỏ qua sạch.
            this._qrTraMa.set(key, { code: key, id: id, name: ten, className: lop });
        });

        if (this._qrTraMa.size === 0) {
            throw new Error('Không có em nào trong phạm vi bạn quét được.\n'
                          + 'Hãy nhờ Ban Điều Hành kiểm lại phân công khối/lớp.');
        }
    },

    _qrTaiJsQR() {
        if (window.jsQR) return Promise.resolve();
        return new Promise((ok, hong) => {
            const s = document.createElement('script');
            s.src = 'assets/js/vendor/jsQR.min.js';
            s.onload = ok;
            s.onerror = () => hong(new Error('Không tải được bộ giải mã QR.'));
            document.head.appendChild(s);
        });
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

        this.qrVuaGhi = [];
        this.qrDaQuet = 0;
        this.qrDangGui = 0;
        this._qrLan = {};
        this._qrHang = [];
        this.qrTrangThai = 'Đang mở camera…';
        this.qrMo = true;

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
        video.srcObject = this._qrStream;
        video.setAttribute('playsinline', '');
        await video.play();

        // Chạm mở camera cũng là một cử chỉ người dùng — mượn luôn để
        // "đánh thức" âm thanh, nếu không iOS chặn tiếng bíp đầu tiên.
        this._qrBip(880, 30);

        this._qrDung = false;
        this.qrTrangThai = 'Đưa thẻ vào khung';

        if (await this._qrCoNative()) {
            this._qrVongNative(video);
        } else {
            this.qrDangTai = true;
            this.qrTrangThai = 'Đang tải bộ giải mã…';
            try { await this._qrTaiJsQR(); }
            catch (e) { this.qrDangTai = false; this.dongQuetQR(); window.TNTT.toast.error(e.message); return; }
            this.qrDangTai = false;
            this.qrTrangThai = 'Đưa thẻ vào khung';
            this._qrVongJs(video);
        }
    },

    async _qrVongNative(video) {
        const det = new window.BarcodeDetector({ formats: ['qr_code'] });
        const chay = async () => {
            if (this._qrDung) return;
            try {
                const ma = await det.detect(video);
                for (const m of ma) this._qrNhan(m.rawValue);
            } catch (e) { /* khung lỗi thì bỏ, thử khung sau */ }
            requestAnimationFrame(chay);
        };
        requestAnimationFrame(chay);
    },

    _qrVongJs(video) {
        const cv  = document.createElement('canvas');
        const ctx = cv.getContext('2d', { willReadFrequently: true });
        let lan = 0;

        const chay = () => {
            if (this._qrDung) return;
            requestAnimationFrame(chay);

            // ~20 khung/giây là đủ; chạy hết sức chỉ làm máy nóng và chậm đi
            const gio = performance.now();
            if (gio - lan < 50) return;
            lan = gio;

            if (video.readyState !== video.HAVE_ENOUGH_DATA) return;

            // CHỈ cắt vùng giữa — đúng phần nằm trong khung ngắm (3/4 cạnh ngắn). Giải mã
            // 1/4 diện tích thay vì cả ảnh, nhanh hơn hẳn trên máy yếu.
            const vw = video.videoWidth, vh = video.videoHeight;
            const canh = Math.floor(Math.min(vw, vh) * 0.75);
            const sx = Math.floor((vw - canh) / 2), sy = Math.floor((vh - canh) / 2);

            const dich = 400;
            cv.width = cv.height = dich;
            ctx.drawImage(video, sx, sy, canh, canh, 0, 0, dich, dich);

            const anh = ctx.getImageData(0, 0, dich, dich);
            const kq = window.jsQR(anh.data, anh.width, anh.height,
                                   { inversionAttempts: 'dontInvert' });
            if (kq && kq.data) this._qrNhan(kq.data);
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
            this.qrTrangThai = 'Không có em nào mang mã "' + ma + '"';
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
            this.qrTrangThai = em.name + ' — ngoài khối bạn phụ trách';
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
        this._qrBipOk(); this._qrRung(30);

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

        this._qrTatCamera();
        this.qrMo = false;

        if (sot) {
            window.TNTT.toast.warning('Đã ghi ' + (daGhi - sot) + ' em.\n'
                + 'Còn ' + sot + ' em chưa gửi được lên máy chủ do mất mạng.\n'
                + 'Hãy kiểm lại danh sách và điểm danh tay cho các em đó.', 8000);
        } else if (daGhi) {
            await this.loadData();      // lấy lại số liệu chuẩn từ máy chủ
            window.TNTT.toast.success('Xong. Đã điểm danh ' + daGhi + ' em bằng thẻ QR.');
        }
    },

    /** Đóng ngay, không đợi — dùng khi bấm dấu X */
    dongQuetQR() {
        this._qrDung = true;
        if (this._qrHang.length) this._qrGuiLo();
        this._qrTatCamera();
        this.qrMo = false;
    },

    _qrTatCamera() {
        if (this._qrStream) {
            this._qrStream.getTracks().forEach(t => t.stop());   // tắt đèn camera
            this._qrStream = null;
        }
        const v = this.$refs.qrVideo;
        if (v) v.srcObject = null;
        this.qrTrangThai = '';
        this.qrDangTai = false;
    },
};
