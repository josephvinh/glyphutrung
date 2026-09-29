/* ==========================================================
   XLSX — đọc/ghi file Excel (.xlsx) dùng chung cho toàn app
   Một mảnh của component tnttApp. Thay hoàn toàn CSV.

   Thư viện SheetJS (vendor/xlsx.core.min.js, ~500KB) chỉ được tải
   khi người dùng bấm xuất/nhập lần đầu, không làm chậm lúc mở app.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.xlsxIo = {

    _xlsxPromise: null,

    loadXlsxLib() {
        if (window.XLSX) return Promise.resolve(window.XLSX);
        if (!window.TNTT._xlsxPromise) {
            window.TNTT._xlsxPromise = new Promise((resolve, reject) => {
                const s = document.createElement('script');
                s.src = '/assets/js/vendor/xlsx.core.min.js?v=0.20.3';
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
     */
    async downloadXlsx(sheets, fileName) {
        try {
            const X = await this.loadXlsxLib();
            const wb = X.utils.book_new();
            sheets.forEach(sh => {
                const ws = X.utils.aoa_to_sheet(sh.rows);
                const widths = sh.widths || (sh.rows[0] || []).map((_, ci) =>
                    Math.min(40, Math.max(8, ...sh.rows.slice(0, 200).map(r => String(r[ci] ?? '').length)) + 2));
                ws['!cols'] = widths.map(w => ({ wch: w }));
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
