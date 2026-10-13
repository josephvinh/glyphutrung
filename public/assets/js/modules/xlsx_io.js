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
        // KHÔNG dùng cellDates: SheetJS dựng Date theo múi giờ của máy (có thể lệch ngày/giờ). Đọc số ngày Excel
        // rồi tự đổi (vn.fromExcel), nhận ô ngày bằng định dạng ô (cellNF).
        const wb = X.read(buf, { type: 'array', cellNF: true });
        const pick = (preferSheet && wb.SheetNames.find(n => n.trim().toLowerCase() === preferSheet.toLowerCase()))
                   || wb.SheetNames[0];
        if (!pick) return [];
        const ws = wb.Sheets[pick];
        if (!ws || !ws['!ref']) return [];
        const vn = window.TNTT.vn;
        const p2 = (n) => String(n).padStart(2, '0');
        const date1904 = !!(wb.Workbook && wb.Workbook.WBProps && wb.Workbook.WBProps.date1904);
        const bang = X.SSF.get_table ? X.SSF.get_table() : {};
        // Định dạng ô có phải ngày/giờ không (z có thể là chuỗi định dạng hoặc mã định dạng dựng sẵn "14").
        const laDinhDangNgay = (z) => {
            if (z === undefined || z === null || z === '') return false;
            const f = /^\d+$/.test(String(z)) ? (bang[Number(z)] || '') : String(z);
            return f !== '' && X.SSF.is_date(f);
        };
        const rg = X.utils.decode_range(ws['!ref']);
        const rows = [];
        for (let r = rg.s.r; r <= rg.e.r; r++) {
            const row = [];
            let coGiaTri = false;
            for (let c = rg.s.c; c <= rg.e.c; c++) {
                const cell = ws[X.utils.encode_cell({ r: r, c: c })];
                let v = '';
                if (cell) {
                    if (cell.t === 'n' && laDinhDangNgay(cell.z)) {
                        const x = vn.fromExcel(cell.v, date1904);
                        if (x) v = vn.fmtDate(x.ymd);                                  // dd/mm/yyyy
                        else if (cell.v >= 0 && cell.v < 1) {                          // ô chỉ có giờ -> HH:mm
                            const phut = Math.round(cell.v * 1440);
                            v = p2(Math.floor(phut / 60) % 24) + ':' + p2(phut % 60);
                        }
                        else v = String(cell.v);
                    } else if (cell.t === 'n') {
                        v = String(cell.v);                                            // số thường -> chuỗi gọn
                    } else if (cell.t === 'e') {
                        v = '';
                    } else {
                        v = String(cell.v === null || cell.v === undefined ? '' : cell.v).trim();
                    }
                }
                if (v !== '') coGiaTri = true;
                row.push(v);
            }
            if (coGiaTri) rows.push(row);                                              // bỏ dòng trống hoàn toàn
        }
        return rows;
    },
};
