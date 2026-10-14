/* ==========================================================
   Ô NHẬP NGÀY / GIỜ THEO KHUÔN — dd/mm/yyyy, hh:mm, dd/mm/yyyy hh:mm

   VÌ SAO: <input type="date|time|datetime-local"> hiển thị theo ngôn ngữ/vùng của TRÌNH DUYỆT
   (máy en-US hiện mm/dd/yyyy và giờ AM/PM), không ép được bằng HTML. Ở đây ô là ô CHỮ có khuôn:
   người dùng luôn thấy và gõ dd/mm/yyyy, 24 giờ, bất kể máy đặt ngôn ngữ nào.

   CÁCH DÙNG (qua views/_ngay_input.php, hàm ngay_input()):
       <input x-ngay="form.birthDate" ...>               ngày:     model 'yyyy-mm-dd'
       <input x-ngay.time="form.startTime" ...>          giờ:      model 'HH:mm'
       <input x-ngay.datetime="form.meetingAt" ...>      ngày giờ: model 'yyyy-mm-ddTHH:mm'
   Dữ liệu bên trong và gửi lên máy chủ KHÔNG đổi (vẫn như <input type=date> trả về).

   HÀNH VI:
     - Gõ số là tự chèn dấu / và : ("13102026" -> "13/10/2026"); gõ "5/9/2017" tự thành "05/09/2017".
     - Dán "13/10/2026", "13-10-2026", "2026-10-13" đều được.
     - Chỉ đổi model khi ô ĐỦ và HỢP LỆ (ngày có thật, giờ 00-23:00-59, trong min/max). Xoá hết -> model ''.
       Đang gõ dở thì model giữ nguyên; rời ô (blur) mà dở/sai thì model '' + viền đỏ — giống ô ngày gốc
       (giá trị rỗng khi chưa đủ), nên các kiểm tra "bắt buộc nhập" của form vẫn chặn đúng.
     - Nút lịch bên phải mở bộ chọn gốc của máy; chọn xong ghi lại thành dd/mm/yyyy.
   ========================================================== */
window.TNTT = window.TNTT || {};
// Manifest yêu cầu mỗi module gán window.TNTT.<tên>; phần việc thật là chỉ thị Alpine bên dưới.
window.TNTT.ngay_nhap = {};

(function () {
    const p2 = n => String(n).padStart(2, '0');
    const KIND = {
        date:     { chuSo: 8,  cho: 'dd/mm/yyyy',       dai: 10 },
        time:     { chuSo: 4,  cho: 'hh:mm',            dai: 5 },
        datetime: { chuSo: 12, cho: 'dd/mm/yyyy hh:mm', dai: 16 }
    };
    const RE_MODEL = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::\d{2})?)?$/;

    const soNgayTrongThang = (m, y) => new Date(Date.UTC(y, m, 0)).getUTCDate();

    /** Chuỗi chữ số -> chữ có khuôn, chỉ thêm dấu khi còn chữ số theo sau ('12' -> '12', '123' -> '12/3'). */
    function dungKhuon(d, kind) {
        if (kind === 'time') return d.slice(0, 2) + (d.length > 2 ? ':' + d.slice(2, 4) : '');
        let ra = d.slice(0, 2);
        if (d.length > 2) ra += '/' + d.slice(2, 4);
        if (d.length > 4) ra += '/' + d.slice(4, 8);
        if (kind === 'datetime' && d.length > 8) {
            ra += ' ' + d.slice(8, 10);
            if (d.length > 10) ra += ':' + d.slice(10, 12);
        }
        return ra;
    }

    /** Giá trị model -> chữ hiển thị. */
    function modelSangChu(v, kind) {
        v = String(v === null || v === undefined ? '' : v).trim();
        if (v === '') return '';
        if (kind === 'time') {
            const m = /^(\d{2}):(\d{2})/.exec(v);
            return m ? m[1] + ':' + m[2] : '';
        }
        const m = RE_MODEL.exec(v);
        if (!m) return '';
        const ngay = m[3] + '/' + m[2] + '/' + m[1];
        if (kind === 'date') return ngay;
        return ngay + (m[4] !== undefined ? ' ' + m[4] + ':' + m[5] : '');
    }

    /** Chữ đang gõ -> { ok, iso } | { thieu } | { loi }. */
    function chuSangModel(text, kind) {
        const d = String(text).replace(/\D/g, '');
        if (d.length < KIND[kind].chuSo) return { thieu: true };
        let iso = '';
        if (kind !== 'time') {
            const dd = +d.slice(0, 2), mm = +d.slice(2, 4), yy = +d.slice(4, 8);
            if (mm < 1 || mm > 12 || dd < 1 || yy < 1000 || dd > soNgayTrongThang(mm, yy)) return { loi: 'Ngày không có thật' };
            iso = yy + '-' + p2(mm) + '-' + p2(dd);
        }
        if (kind !== 'date') {
            const o = kind === 'time' ? 0 : 8;
            const hh = +d.slice(o, o + 2), mi = +d.slice(o + 2, o + 4);
            if (hh > 23 || mi > 59) return { loi: 'Giờ không hợp lệ' };
            iso = (kind === 'time' ? '' : iso + 'T') + p2(hh) + ':' + p2(mi);
        }
        return { ok: true, iso: iso };
    }

    /** Kiểm min/max của chính ô (thuộc tính tĩnh hoặc do Alpine gán). Trả thông báo lỗi hoặc ''. */
    function ngoaiGioiHan(iso, el, kind) {
        const min = el.getAttribute('min'), max = el.getAttribute('max');
        const nhan = s => modelSangChu(s, kind);
        if (min && iso < min) return 'Phải từ ' + nhan(min) + (max ? ' đến ' + nhan(max) : ' trở đi');
        if (max && iso > max) return 'Phải' + (min ? ' từ ' + nhan(min) : '') + ' đến ' + nhan(max);
        return '';
    }

    /**
     * Chuẩn hoá chữ vừa gõ/dán thành CHUỖI CHỮ SỐ:
     *  - dán dạng yyyy-mm-dd / d/m/yyyy: đổi về thứ tự ngày-tháng-năm;
     *  - gõ "5/" hoặc "5/9/2017" (một chữ số rồi dấu phân cách): thêm số 0 đứng đầu.
     * Chỉ áp khi người dùng THÊM chữ (không áp khi xoá, kẻo không sửa giữa chừng được).
     */
    function layChuSo(raw, kind, themChu) {
        let t = raw.trim();
        if (kind !== 'time') {
            const iso = /^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})(.*)$/.exec(t);
            if (iso) t = p2(iso[3]) + '/' + p2(iso[2]) + '/' + iso[1] + iso[4];
        }
        if (themChu) {
            const tach = t.split(/[\/\-.:\s]+/);
            const ketThuc = /[\/\-.:\s]$/.test(t);
            const dem = tach.length;
            t = tach.map((x, i) => (x.length === 1 && (i < dem - 1 || (ketThuc && i === dem - 1))) ? '0' + x : x).join('');
        }
        return t.replace(/\D/g, '').slice(0, KIND[kind].chuSo);
    }

    document.addEventListener('alpine:init', () => {
        Alpine.directive('ngay', (el, { modifiers, expression }, { evaluateLater, effect, cleanup }) => {
            const kind = modifiers.includes('time') ? 'time' : (modifiers.includes('datetime') ? 'datetime' : 'date');
            const cfg = KIND[kind];
            const doc = evaluateLater(expression);
            const gan = evaluateLater(expression + ' = __v');
            const nat = el.parentElement ? el.parentElement.querySelector('.ngay-native') : null;
            let daDat = null;                               // giá trị model do CHÍNH ô này vừa ghi (để effect khỏi ghi đè chữ đang gõ)

            el.type = 'text';
            el.setAttribute('inputmode', 'numeric');
            el.setAttribute('autocomplete', 'off');
            el.setAttribute('spellcheck', 'false');
            el.setAttribute('maxlength', String(cfg.dai));
            if (!el.getAttribute('placeholder')) el.setAttribute('placeholder', cfg.cho);

            const datModel = (v) => { daDat = v; gan(() => {}, { scope: { __v: v } }); };
            const baoLoi = (msg) => {
                el.classList.toggle('ngay-loi', !!msg);
                if (msg) el.setAttribute('aria-invalid', 'true'); else el.removeAttribute('aria-invalid');
                el.setCustomValidity(msg || '');
                el.title = msg || '';
            };

            /** Đọc ô -> model. `roiO` = người dùng đã rời ô (dở dang coi như sai). */
            const capNhat = (roiO) => {
                const t = el.value.trim();
                if (t === '') { baoLoi(''); datModel(''); return; }
                const r = chuSangModel(t, kind);
                if (r.ok) {
                    const gh = ngoaiGioiHan(r.iso, el, kind);
                    if (gh) { baoLoi(gh); datModel(''); return; }
                    baoLoi('');
                    datModel(r.iso);
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                } else if (r.thieu) {
                    el.setCustomValidity('Nhập đủ ' + cfg.cho);
                    if (roiO) { baoLoi('Nhập đủ ' + cfg.cho); datModel(''); }
                } else {
                    baoLoi(r.loi); datModel('');
                }
            };

            const onInput = (e) => {
                const truoc = el.value;
                const caret = el.selectionStart === null ? truoc.length : el.selectionStart;
                const soChuTruocCaret = truoc.slice(0, caret).replace(/\D/g, '').length;
                const themChu = !e || !e.inputType || e.inputType.indexOf('delete') !== 0;
                const moi = dungKhuon(layChuSo(truoc, kind, themChu), kind);
                if (moi !== truoc) {
                    el.value = moi;
                    if (caret < truoc.length) {                // đang sửa giữa chuỗi: đặt con trỏ theo số chữ số đứng trước
                        let dem = 0, vt = 0;
                        while (vt < moi.length && dem < soChuTruocCaret) { if (/\d/.test(moi[vt])) dem++; vt++; }
                        try { el.setSelectionRange(vt, vt); } catch (_) { /* ô không nhận con trỏ */ }
                    }
                }
                capNhat(false);
            };
            const onBlur = () => capNhat(true);

            el.addEventListener('input', onInput);
            el.addEventListener('blur', onBlur);

            // ---- Nút lịch: ô gốc trong suốt phủ lên biểu tượng (chạm trúng là mở bộ chọn của máy) ----
            const moLich = () => {
                if (!nat) return;
                const m = chuSangModel(el.value, kind);
                nat.value = m.ok ? m.iso : '';
                const mn = el.getAttribute('min'), mx = el.getAttribute('max');
                if (mn) nat.min = mn; else nat.removeAttribute('min');
                if (mx) nat.max = mx; else nat.removeAttribute('max');
                try { if (nat.showPicker) nat.showPicker(); } catch (_) { /* đã mở hoặc trình duyệt không cho */ }
            };
            const onNatChange = () => {
                if (!nat.value) return;
                el.value = modelSangChu(nat.value, kind);
                capNhat(true);
            };
            if (nat) {
                nat.type = kind === 'time' ? 'time' : (kind === 'datetime' ? 'datetime-local' : 'date');
                nat.addEventListener('click', moLich);
                nat.addEventListener('change', onNatChange);
            }

            // ---- Ô bị vô hiệu (vd. "cả ngày" tắt giờ): nút lịch cũng tắt ----
            const dongBoTat = () => {
                const tat = el.disabled;
                if (nat) nat.disabled = tat;
                if (el.parentElement) el.parentElement.classList.toggle('ngay-off', tat);
            };
            dongBoTat();
            const obs = new MutationObserver(dongBoTat);
            obs.observe(el, { attributes: true, attributeFilter: ['disabled'] });

            // ---- Model đổi từ bên ngoài (mở form sửa, đặt lại form...) -> cập nhật chữ ----
            effect(() => {
                doc((v) => {
                    if (v === daDat) { daDat = null; return; }   // do chính ô này ghi: giữ nguyên chữ đang gõ
                    daDat = null;
                    el.value = modelSangChu(v, kind);
                    baoLoi('');
                });
            });

            cleanup(() => {
                el.removeEventListener('input', onInput);
                el.removeEventListener('blur', onBlur);
                if (nat) { nat.removeEventListener('click', moLich); nat.removeEventListener('change', onNatChange); }
                obs.disconnect();
            });
        });
    });
})();
