/* ==========================================================
   IN THẺ QR CHO THIẾU NHI  (một thẻ trong module Thiếu Nhi)

   Không có thẻ thì không có gì để quét. Màn này cho chọn phạm vi in,
   bật/tắt thông tin hiển thị, chọn kiểu thẻ, xem trước rồi in.

   Mã QR LUÔN chỉ chứa MÃ SỐ của em, không gì khác. Cố ý:
     - Quét bằng app khác cũng chỉ ra một chuỗi vô hại như "GDGLPT260001",
       không lộ tên, ngày sinh hay số điện thoại cha mẹ.
     - Thẻ rơi ra ngoài cũng không thành rò rỉ thông tin.
   (Chữ IN trên thẻ thì tuỳ người dùng chọn — đó là thông tin trên giấy.)

   Thư viện qrcode.min.js chỉ nạp khi mở màn, không nằm trong đường tải
   của trang thường.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.qrcard = {

    qrTheDangLam: false,
    qrReady: false,            // đã nạp xong bộ sinh mã chưa

    // --- Tuỳ chọn (không lưu DB, đặt lại mỗi lần mở) ---
    qrScopeType: 'class',      // 'class' | 'block' | 'all'
    qrScopeValue: '',          // tên lớp / tên khối
    qrFields: { code: true, holyName: true, name: true, className: true, block: false, birthDate: false },
    qrTemplate: 'compact',     // 'compact' (gọn) | 'badge' (thẻ đeo)
    qrPerRow: 3,               // số thẻ mỗi hàng
    qrCutLines: true,          // viền nét đứt để cắt
    qrHeader: true,            // in tiêu đề đoàn
    qrHeaderText: '',
    qrSelectedIds: [],         // Danh sách ID các em sẽ in

    openQrcard() {
        if (this.qrScopeType === 'class' && !this.qrScopeValue) {
            this.qrScopeValue = this.availableClasses[0] || '';
        }
        if (!this.qrHeaderText) {
            this.qrHeaderText = 'Thiếu Nhi Thánh Thể — Phú Trung'
                              + (this.year ? ' · ' + this.year.name : '');
        }
        this.qrReady = false;
        this.changeModule('qrcard');
        this.qrSyncSelected();
        this._qrTaiBoSinh()
            .then(() => { this.qrReady = true; })
            .catch(() => { window.TNTT.toast.error('Không tải được bộ sinh mã QR.'); });
    },

    // Khi đổi kiểu chọn phạm vi thì gợi ý giá trị mặc định hợp lý
    qrOnScopeType() {
        if (this.qrScopeType === 'class') this.qrScopeValue = this.availableClasses[0] || '';
        else if (this.qrScopeType === 'block') this.qrScopeValue = this.availableBlocks[0] || '';
        else this.qrScopeValue = '';
        this.qrSyncSelected();
    },

    qrSyncSelected() {
        this.qrSelectedIds = this.qrScopeStudents.map(s => s.id);
    },

    // Toàn bộ học sinh trong phạm vi lớp/khối/đoàn đã chọn
    get qrScopeStudents() {
        let list = this.accessibleStudents.filter(s => s.status === 'đang sinh hoạt');
        if (this.qrScopeType === 'class' && this.qrScopeValue) {
            list = list.filter(s => s.className === this.qrScopeValue);
        } else if (this.qrScopeType === 'block' && this.qrScopeValue) {
            list = list.filter(s => s.block === this.qrScopeValue);
        }
        return list;
    },

    // Các em sẽ in — chỉ in những em được tích chọn
    get qrPrintStudents() {
        return this.qrScopeStudents.filter(s => this.qrSelectedIds.includes(s.id));
    },

    get qrPreviewStudents() {
        return this.qrPrintStudents.slice(0, 6);
    },

    // HTML xem trước (nhúng luôn <style> để khớp y hệt bản in)
    get qrPreviewHtml() {
        if (!this.qrReady) return '<p style="color:#64748b;font-size:13px;margin:0">Đang tải bộ sinh mã…</p>';
        const cards = this.qrPreviewStudents.map(s => this.qrCardHtml(s)).join('');
        if (!cards) return '<p style="color:#64748b;font-size:13px;margin:0">Không có em nào trong phạm vi đã chọn.</p>';
        return '<style>' + this.qrStyleCss() + '</style><div class="luoi">' + cards + '</div>';
    },

    // Một thẻ — dùng chung cho xem trước lẫn bản in
    qrCardHtml(s) {
        const qr = this._qrAnh(s.code, 4);
        const fields = this._qrFieldsHtml(s);
        if (this.qrTemplate === 'badge') {
            return '<div class="the badge">'
                 + (this.qrHeader ? '<div class="badge-head">' + this._thoat(this.qrHeaderText) + '</div>' : '')
                 + '<div class="qr">' + qr + '</div>'
                 + '<div class="tt">' + fields + '</div></div>';
        }
        return '<div class="the compact"><div class="qr">' + qr + '</div>'
             + '<div class="tt">' + fields + '</div></div>';
    },

    _qrFieldsHtml(s) {
        let h = '';
        if (this.qrFields.code) h += '<p class="ma">' + this._thoat(s.code) + '</p>';
        const hn = this.qrFields.holyName && s.holyName ? this._thoat(s.holyName) + ' ' : '';
        if (this.qrFields.name) h += '<p class="ten">' + hn + this._thoat(s.name) + '</p>';
        else if (hn) h += '<p class="ten">' + this._thoat(s.holyName) + '</p>';
        if (this.qrFields.className && s.className) h += '<p class="lop">' + this._thoat(s.className) + '</p>';
        if (this.qrFields.block && s.block) h += '<p class="lop">' + this._thoat(s.block) + '</p>';
        if (this.qrFields.birthDate && s.birthDate) h += '<p class="ns">' + this._thoat(this.formatDate(s.birthDate)) + '</p>';
        return h;
    },

    // CSS dựng theo tuỳ chọn — dùng cho cả xem trước và bản in
    qrStyleCss() {
        const border = this.qrCutLines ? '1px dashed #94a3b8' : '1px solid #e2e8f0';
        const qrSize = this.qrTemplate === 'badge' ? '30mm' : '22mm';
        let css = ''
            + '*{box-sizing:border-box}'
            + 'body{font-family:"Be Vietnam Pro",system-ui,sans-serif;margin:0;color:#0f172a}'
            + '.luoi{display:grid;grid-template-columns:repeat(' + this.qrPerRow + ',1fr);gap:4mm}'
            + '.the{border:' + border + ';border-radius:3mm;padding:3mm;break-inside:avoid;page-break-inside:avoid}'
            + '.qr img{display:block;width:' + qrSize + ';height:' + qrSize + ';image-rendering:pixelated}'
            + '.ma{font-size:10px;font-weight:800;letter-spacing:.3px;color:#2563eb;margin:0 0 1px}'
            + '.ten{font-size:11px;font-weight:700;margin:0 0 1px;line-height:1.25;overflow-wrap:anywhere}'
            + '.lop{font-size:9px;color:#64748b;margin:0}'
            + '.ns{font-size:9px;color:#64748b;margin:0}';
        if (this.qrTemplate === 'badge') {
            css += '.the.badge{display:flex;flex-direction:column;align-items:center;text-align:center;gap:2mm;padding-top:5mm}'
                 + '.badge-head{font-size:8px;font-weight:800;color:#1e3a8a;text-transform:uppercase;letter-spacing:.3px}'
                 + '.the.badge .tt{width:100%}';
        } else {
            css += '.the.compact{display:flex;align-items:center;gap:3mm}'
                 + '.the.compact .tt{min-width:0}';
        }
        return css;
    },

    async inTheQR() {
        if (!this.qrReady) { window.TNTT.toast.warning('Bộ sinh mã chưa sẵn sàng, thử lại sau giây lát.'); return; }
        const ds = this.qrPrintStudents;
        if (!ds.length) { window.TNTT.toast.warning('Không có em nào trong phạm vi đã chọn.'); return; }
        if (ds.length > 200 && !await window.TNTT.toast.confirm('Sẽ in thẻ cho ' + ds.length + ' em. Tiếp tục?', { confirmText: 'In thẻ' })) return;

        this.qrTheDangLam = true;
        const cards = ds.map(s => this.qrCardHtml(s)).join('');
        const ngay = this.formatDate(this.toDateInput(new Date()));
        const html = '<!doctype html><html lang="vi"><head><meta charset="utf-8"><title>Thẻ QR</title>'
            + '<style>@page{size:A4;margin:10mm}' + this.qrStyleCss()
            + 'h1{font-size:15px;margin:0 0 2px}.phu{font-size:11px;color:#64748b;margin:0 0 10px}'
            + '.huongdan{margin-top:8mm;font-size:10px;color:#64748b;border-top:1px solid #e2e8f0;padding-top:3mm}'
            + '@media print{.huongdan{page-break-before:avoid}}</style></head><body>'
            + (this.qrHeader ? '<h1>' + this._thoat(this.qrHeaderText) + '</h1>' : '')
            + '<p class="phu">' + ds.length + ' em · in ngày ' + ngay + '</p>'
            + '<div class="luoi">' + cards + '</div>'
            + '<p class="huongdan">Cắt theo đường nét đứt. Nên ép nhựa hoặc dán lên bìa cứng — '
            + 'thẻ nhàu thì camera khó đọc. Mã QR chỉ chứa mã số của em, không chứa thông tin cá nhân nào.</p>'
            + '<scr' + 'ipt>window.onload=function(){window.print()};</scr' + 'ipt></body></html>';

        const cs = window.open('', '_blank');
        if (!cs) {
            this.qrTheDangLam = false;
            window.TNTT.toast.error('Trình duyệt đã chặn cửa sổ in.\nHãy cho phép mở cửa sổ mới rồi thử lại.');
            return;
        }
        cs.document.write(html);
        cs.document.close();
        this.qrTheDangLam = false;
        this.logAction('tao', 'students', 'In thẻ QR', ds.length + ' em'
                      + (this.qrScopeValue ? ' · ' + this.qrScopeValue : ''));
    },

    // --- Bộ sinh mã ---
    _qrTaiBoSinh() {
        if (window.qrcode) return Promise.resolve();
        return new Promise((ok, hong) => {
            const s = document.createElement('script');
            s.src = 'assets/js/vendor/qrcode.min.js';
            s.onload = ok;
            s.onerror = () => hong(new Error('Không tải được bộ sinh mã QR.'));
            document.head.appendChild(s);
        });
    },

    /** Vẽ một mã QR thành chuỗi <img src="data:..."> */
    _qrAnh(noiDung, coO) {
        // Mức sửa lỗi M: chịu được thẻ hơi bẩn hoặc mờ mà vẫn đọc ra.
        const qr = window.qrcode(0, 'M');
        qr.addData(String(noiDung));
        qr.make();
        return qr.createImgTag(coO, 0);   // 0 = không chừa lề, tự canh bằng CSS
    },

    /** Tên em do người dùng nhập -> phải thoát trước khi nhét vào HTML */
    _thoat(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    },

    /** Trả về chuỗi SVG QR code cho một mã */
    qrSvg(code) {
        if (!code) return '';
        try {
            const qr = window.qrcode(0, 'M');
            qr.addData(String(code));
            qr.make();
            const moduleCount = qr.getModuleCount();
            const cellSize = Math.floor(64 / moduleCount);
            const size = moduleCount * cellSize;
            let svg = `<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 ${size} ${size}">`;
            svg += `<rect width="${size}" height="${size}" fill="white"/>`;
            for (let r = 0; r < moduleCount; r++) {
                for (let c = 0; c < moduleCount; c++) {
                    if (qr.isDark(r, c)) {
                        svg += `<rect x="${c * cellSize}" y="${r * cellSize}" width="${cellSize}" height="${cellSize}" fill="black"/>`;
                    }
                }
            }
            svg += '</svg>';
            return svg;
        } catch (e) {
            return '';
        }
    },
};

/** In thẻ QR của một em (gọi từ ngoài module) */
window.printSingleQrcard = function(student) {
    if (!student || !student.code) return;
    const qrcard = window.TNTT.qrcard;
    const cardHtml = qrcard.qrCardHtml(student);
    const html = '<!doctype html><html lang="vi"><head><meta charset="utf-8"><title>Thẻ QR - ' + qrcard._thoat(student.code) + '</title>'
        + '<style>@page{size:A5;margin:5mm}' + qrcard.qrStyleCss()
        + '*{box-sizing:border-box}body{font-family:"Be Vietnam Pro",system-ui,sans-serif;margin:0;display:flex;justify-content:center;align-items:center;min-height:100vh}'
        + '.wrapper{display:flex;justify-content:center;align-items:center}.the{page-break-after:always}</style></head><body>'
        + '<div class="wrapper"><div class="the">' + cardHtml + '</div></div>'
        + '<scr' + 'ipt>window.onload=function(){window.print()};</scr' + 'ipt></body></html>';
    const win = window.open('', '_blank');
    if (!win) { window.TNTT.toast.error('Trình duyệt đã chặn cửa sổ in.'); return; }
    win.document.write(html);
    win.document.close();
};

/** In phiếu liên lạc của một em */
window.printReportSingle = function(report, student) {
    if (!report || !student) return;
    window.open('print.php?type=report&termId=' + report.termId + '&studentId=' + student.id, '_blank');
};
