/* ==========================================================
   GIỜ VIỆT NAM — nguồn DUY NHẤT cho "hôm nay / bây giờ" và định dạng ngày giờ.

   VÌ SAO: máy chủ chạy Asia/Ho_Chi_Minh (UTC+7), còn `new Date()` trong trình duyệt
   chạy theo múi giờ CỦA MÁY. Điện thoại đặt múi giờ khác (đi nước ngoài, máy
   cài sai, CI chạy UTC) thì "hôm nay" lệch ngày với máy chủ: chọn nhầm buổi điểm
   danh, tính sai giờ chốt, nhắc việc sai giờ. Mọi chỗ cần "bây giờ / hôm nay" phải
   đi qua đây, KHÔNG dùng new Date().getHours()/getDate()/toISOString() trực tiếp
   (test VnTimeUsageTest chặn).

   Việt Nam không có giờ mùa hè nên dùng độ lệch cố định +7 giờ; tính bằng getUTC*
   trên mốc đã dịch nên KHÔNG phụ thuộc múi giờ của máy.

   Định dạng chung: ngày dd/mm/yyyy, giờ HH:mm (24 giờ). Dữ liệu trong app và trên máy
   chủ vẫn là yyyy-mm-dd / yyyy-mm-dd HH:mm:ss (giờ VN).
   ========================================================== */
window.TNTT = window.TNTT || {};

(function () {
    const OFFSET_MS = 7 * 3600 * 1000;
    const p2 = n => String(n).padStart(2, '0');
    const RE = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/;

    /** Các thành phần ngày giờ THEO GIỜ VN của một mốc (ms epoch; mặc định = bây giờ). */
    function parts(ms) {
        const d = new Date((ms === undefined ? Date.now() : ms) + OFFSET_MS);
        return {
            y: d.getUTCFullYear(), m: d.getUTCMonth() + 1, d: d.getUTCDate(),
            hh: d.getUTCHours(), mm: d.getUTCMinutes(), ss: d.getUTCSeconds(),
            dow: d.getUTCDay()                       // 0 = Chúa Nhật
        };
    }

    /** Chuỗi 'yyyy-mm-dd hh:mm[:ss]' (hoặc chỉ ngày) -> mốc ms, hiểu là GIỜ VN. NaN nếu sai dạng. */
    function parse(str) {
        const m = RE.exec(String(str || '').trim());
        if (!m) return NaN;
        const t = Date.UTC(+m[1], +m[2] - 1, +m[3], +(m[4] || 0), +(m[5] || 0), +(m[6] || 0));
        const ok = new Date(t);                      // loại ngày không có thật (31/02...)
        if (ok.getUTCFullYear() !== +m[1] || ok.getUTCMonth() !== +m[2] - 1 || ok.getUTCDate() !== +m[3]) return NaN;
        return t - OFFSET_MS;
    }

    const vn = {
        OFFSET_MS: OFFSET_MS,
        parts: parts,
        parse: parse,

        /** Hôm nay theo giờ VN: 'yyyy-mm-dd'. */
        today(ms) { const x = parts(ms); return x.y + '-' + p2(x.m) + '-' + p2(x.d); },

        /** Giờ hiện tại theo giờ VN: 'HH:mm'. */
        time(ms) { const x = parts(ms); return p2(x.hh) + ':' + p2(x.mm); },

        /** Bây giờ theo giờ VN: 'yyyy-mm-dd HH:mm'. */
        now(ms) { return vn.today(ms) + ' ' + vn.time(ms); },

        /** Thứ trong tuần (0 = Chúa Nhật) của một ngày 'yyyy-mm-dd'; không phụ thuộc múi giờ máy. */
        dow(ymd) {
            const m = RE.exec(String(ymd || ''));
            return m ? new Date(Date.UTC(+m[1], +m[2] - 1, +m[3])).getUTCDay() : NaN;
        },

        /** Cộng/trừ ngày cho 'yyyy-mm-dd' (không dính giờ mùa hè hay múi giờ của máy). */
        addDays(ymd, n) {
            const m = RE.exec(String(ymd || ''));
            if (!m) return ymd;
            const d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3] + n));
            return d.getUTCFullYear() + '-' + p2(d.getUTCMonth() + 1) + '-' + p2(d.getUTCDate());
        },

        /**
         * Date của máy đặt đúng NGÀY lịch VN hôm nay (nửa đêm giờ máy). Chỉ dùng cho tính lịch
         * kiểu tháng/tuần bằng new Date(y, m, d) — ngày lịch khớp VN dù máy ở múi giờ nào.
         */
        todayDate() { const x = parts(); return new Date(x.y, x.m - 1, x.d); },

        /** 'yyyy-mm-dd' -> 'dd/mm/yyyy' (chuỗi thuần, không qua Date nên không lệch ngày). */
        fmtDate(ymd) {
            const m = RE.exec(String(ymd || '').trim());
            return m ? m[3] + '/' + m[2] + '/' + m[1] : (ymd || '');
        },

        /** 'yyyy-mm-dd HH:mm[:ss]' -> 'dd/mm/yyyy HH:mm'; chỉ có ngày thì 'dd/mm/yyyy'. */
        fmtDateTime(str) {
            const m = RE.exec(String(str || '').trim());
            if (!m) return str || '';
            return m[3] + '/' + m[2] + '/' + m[1] + (m[4] !== undefined ? ' ' + m[4] + ':' + m[5] : '');
        },

        /**
         * Số ngày Excel -> { ymd: 'yyyy-mm-dd', hh, mm }; null nếu không phải ngày (số < 1 là ô chỉ có giờ).
         * Đổi bằng số học trên mốc UTC (1899-12-30, hoặc 1904-01-01 với sổ làm việc hệ 1904), KHÔNG dựng Date theo
         * múi giờ máy nên đọc file từ máy nào cũng ra đúng ngày.
         */
        fromExcel(serial, date1904) {
            if (typeof serial !== 'number' || !isFinite(serial) || serial < 1) return null;
            let ngay = Math.floor(serial);
            let phut = Math.round((serial - ngay) * 1440);        // làm tròn phút: tránh 23:59:59.99 lùi/nhảy ngày
            if (phut >= 1440) { ngay++; phut = 0; }
            const d = new Date((date1904 ? Date.UTC(1904, 0, 1) : Date.UTC(1899, 11, 30)) + ngay * 86400000);
            return {
                ymd: d.getUTCFullYear() + '-' + p2(d.getUTCMonth() + 1) + '-' + p2(d.getUTCDate()),
                hh: Math.floor(phut / 60), mm: phut % 60
            };
        },

        /** Tuổi tính đến hôm nay (giờ VN) của người sinh 'yyyy-mm-dd'; null nếu không hợp lệ. */
        age(birthYmd, todayYmd) {
            const b = RE.exec(String(birthYmd || ''));
            const t = RE.exec(String(todayYmd || vn.today()));
            if (!b || !t) return null;
            let age = +t[1] - +b[1];
            if (+t[2] < +b[2] || (+t[2] === +b[2] && +t[3] < +b[3])) age--;
            return age;
        }
    };

    window.TNTT.vn = vn;
})();

/* Mảnh gộp vào component tnttApp (app.js): để mẫu HTML và các module khác gọi
   this.vnToday(), this.vnTime()... Tên có tiền tố vn để không đụng mảnh khác. */
window.TNTT.vntime = {
    vnToday()          { return window.TNTT.vn.today(); },
    vnTime()           { return window.TNTT.vn.time(); },
    vnNow()            { return window.TNTT.vn.now(); },
    vnParse(str)       { return window.TNTT.vn.parse(str); },
    vnDow(ymd)         { return window.TNTT.vn.dow(ymd); },
    vnAddDays(ymd, n)  { return window.TNTT.vn.addDays(ymd, n); },
    vnTodayDate()      { return window.TNTT.vn.todayDate(); },
    vnFmtDate(ymd)     { return window.TNTT.vn.fmtDate(ymd); },
    vnFmtDateTime(str) { return window.TNTT.vn.fmtDateTime(str); },
    vnAge(birthYmd)    { return window.TNTT.vn.age(birthYmd); }
};
