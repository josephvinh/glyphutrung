/* ==========================================================
   XLSX — đọc/ghi file Excel (.xlsx) dùng chung cho toàn app
   Một mảnh của component tnttApp. Thay hoàn toàn CSV.

   Thư viện SheetJS (vendor/xlsx.core.min.js, ~500KB) chỉ được tải
   khi người dùng bấm xuất/nhập lần đầu, không làm chậm lúc mở app.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.xlsx_io = {

    _xlsxPromise: null,

    loadXlsxLib() {
        if (window.XLSX) return Promise.resolve(window.XLSX);
        if (!window.TNTT._xlsxPromise) {
            window.TNTT._xlsxPromise = new Promise((resolve, reject) => {
                const s = document.createElement('script');
                s.src = '/assets/js/vendor/xlsx.core.min.js?v=0.20.3-tnr';
                s.onload = () => resolve(window.XLSX);
                s.onerror = () => { window.TNTT._xlsxPromise = null; reject(new Error('Không tải được thư viện Excel')); };
                document.head.appendChild(s);
            });
        }
        return window.TNTT._xlsxPromise;
    },

    /**
     * Tải về file .xlsx.
     * @param {Array<{name:string, rows:any[][], widths?:number[]}>} sheets
     *        rows là mảng các dòng, dòng đầu là tiêu đề. Chuỗi luôn được
     *        ghi là VĂN BẢN (giữ số 0 đầu của SĐT, không bị hiểu thành công thức).
     *        Ô NGÀY: truyền { d: 'yyyy-mm-dd' } hoặc { dt: 'yyyy-mm-dd HH:mm' } (máy chủ cũng trả dạng này)
     *        -> ghi thành ô ngày THẬT hiện dd/mm/yyyy (sắp xếp, lọc, tính toán đúng trong Excel).
     */
    async downloadXlsx(sheets, fileName) {
        try {
            const X = await this.loadXlsxLib();
            const wb = X.utils.book_new();
            sheets.forEach(sh => {
                const ws = this.buildXlsxSheet(X, sh);
                // Tên sheet: tối đa 31 ký tự, không chứa : \ / ? * [ ]
                const ten = String(sh.name || 'Sheet1').replace(/[:\\/?*\[\]]/g, ' ').slice(0, 31);
                X.utils.book_append_sheet(wb, ws, ten);
            });
            X.writeFile(wb, /\.xlsx$/i.test(fileName) ? fileName : fileName.replace(/\.[A-Za-z0-9]+$/, '') + '.xlsx');
            return true;
        } catch (e) {
            window.TNTT.toast.error('Không tạo được file Excel. Vui lòng thử lại.');
            return false;
        }
    },

    /** Dựng một sheet SheetJS từ { rows, widths }; tách riêng để kiểm thử được mà không cần tải file. */
    buildXlsxSheet(X, sh) {
        const vn = window.TNTT.vn;
        const ngay = [];
        const laNgay = v => v !== null && typeof v === 'object' && (v.d !== undefined || v.dt !== undefined);
        const rows = sh.rows.map((row, r) => row.map((v, c) => {
            if (!laNgay(v)) return v;
            const s = v.dt !== undefined ? v.dt : v.d;
            if (s === '' || s === null || s === undefined) return '';
            const x = vn.excel(s);
            if (!x) return vn.fmtDateTime(s);          // không đổi được thành ngày thật -> chữ dd/mm/yyyy
            ngay.push({ r: r, c: c, v: x.v, z: x.z });
            return null;
        }));
        const ws = X.utils.aoa_to_sheet(rows);
        ngay.forEach(n => { ws[X.utils.encode_cell({ r: n.r, c: n.c })] = { t: 'n', v: n.v, z: n.z }; });
        const soCot = Math.max(0, ...sh.rows.map(r => r.length));
        ws['!ref'] = X.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: Math.max(0, rows.length - 1), c: Math.max(0, soCot - 1) } });
        const dai = v => !laNgay(v) ? String(v ?? '').length : (v.dt !== undefined ? 16 : 10);
        const widths = sh.widths || (sh.rows[0] || []).map((_, ci) =>
            Math.min(40, Math.max(8, ...sh.rows.slice(0, 200).map(r => dai(r[ci]))) + 2));
        ws['!cols'] = widths.map(w => ({ wch: w }));
        return ws;
    },

    /**
     * Đọc file .xlsx, trả về mảng dòng (mỗi ô là chuỗi đã trim).
     * Ưu tiên sheet tên "Danh sách"; không có thì lấy sheet đầu tiên.
     * Ô ngày -> dd/mm/yyyy; ô số -> chuỗi số nguyên gọn (không có .0).
     */
    async readXlsxRows(file, preferSheet) {
        const X = await this.loadXlsxLib();
        const buf = await file.arrayBuffer();
        const wb = X.read(buf, { type: 'array', cellDates: true });
        const pick = (preferSheet && wb.SheetNames.find(n => n.trim().toLowerCase() === preferSheet.toLowerCase()))
                   || wb.SheetNames[0];
        if (!pick) return [];
        const raw = X.utils.sheet_to_json(wb.Sheets[pick], { header: 1, raw: true, defval: '', blankrows: false });
        const p2 = (n) => String(n).padStart(2, '0');
        return raw.map(row => row.map(v => {
            if (v instanceof Date) {
                // SheetJS dựng Date theo giờ địa phương; bù 12 giờ để giây lẻ không lùi sang ngày trước
                const d = new Date(v.getTime() + 12 * 3600 * 1000);
                return p2(d.getDate()) + '/' + p2(d.getMonth() + 1) + '/' + d.getFullYear();
            }
            if (typeof v === 'number') return String(v);
            return String(v === null || v === undefined ? '' : v).trim();
        }));
    },
};
