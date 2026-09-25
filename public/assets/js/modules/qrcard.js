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

   CUSTOM QR CARD FEATURES:
   - Templates: basic, classic, badge, compact, minimal
   - Presets: lưu/xóa cấu hình tùy chỉnh
   - Logo position: top, center, bottom
   - QR Error Level: L, M, Q, H
   - Export: print, PNG, PDF
   - Upload logo tùy chỉnh
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

    // --- Tab state ---
    qrTab: 'basic',            // 'basic' | 'custom'

    // --- Custom QR Card options ---
    qrCustom: {
        template: 'compact',     // 'basic' | 'classic' | 'badge' | 'compact' | 'minimal'
        errorLevel: 'M',         // 'L' | 'M' | 'Q' | 'H'
        logoId: null,            // ID của logo đã chọn
        logoPosition: 'top',    // 'top' | 'center' | 'bottom'
    },
    qrPresets: [],             // Danh sách presets từ server
    qrLogos: [],               // Danh sách logos đã upload
    qrLogoUploading: false,    // Đang upload logo

    // --- Preset modal ---
    qrShowPresetModal: false,
    qrPresetName: '',
    qrPresetIsDefault: false,
    qrPresetEditingId: null,

    // --- Active preset tracking ---
    qrCustomActivePresetId: null,

    openQrcard() {
        if (this.qrScopeType === 'class' && !this.qrScopeValue) {
            this.qrScopeValue = this.availableClasses[0] || '';
        }
        if (!this.qrHeaderText) {
            this.qrHeaderText = 'Thiếu Nhi Thánh Thể — Phú Trung'
                              + (this.year ? ' · ' + this.year.name : '');
        }
        this.qrReady = false;
        this.qrTab = 'basic';
        this.changeModule('qrcard');
        this.qrSyncSelected();
        this._qrTaiBoSinh()
            .then(() => { this.qrReady = true; })
            .catch(() => { window.TNTT.toast.error('Không tải được bộ sinh mã QR.'); });

        // Load presets and logos for custom tab
        this.qrLoadPresets();
        this.qrLoadLogos();
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

    // Custom preview HTML - sử dụng template mới
    get qrCustomPreviewHtml() {
        if (!this.qrReady) return '<p style="color:#64748b;font-size:13px;margin:0;text-align:center">Đang tải bộ sinh mã…</p>';
        const cards = this.qrPreviewStudents.map(s => this.qrCustomCardHtml(s)).join('');
        if (!cards) return '<p style="color:#64748b;font-size:13px;margin:0;text-align:center">Không có em nào trong phạm vi đã chọn.</p>';
        return '<style>' + this.qrCustomStyleCss() + '</style><div class="luoi-c">' + cards + '</div>';
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

    // Custom card HTML - hỗ trợ nhiều template hơn
    qrCustomCardHtml(s) {
        const errorLevel = this.qrCustom.errorLevel || 'M';
        const qr = this._qrAnhCustom(s.code, 4, errorLevel);
        const fields = this._qrFieldsHtml(s);
        const template = this.qrCustom.template || 'compact';

        // Get logo if selected
        let logoHtml = '';
        if (this.qrCustom.logoId) {
            const logo = this.qrLogos.find(l => l.id === this.qrCustom.logoId);
            if (logo) {
                logoHtml = '<img src="' + logo.url + '" class="logo-img">';
            }
        }

        // Build card based on template
        switch (template) {
            case 'basic':
                return '<div class="the-c basic">'
                     + '<div class="card-header">' + this._thoat(this.qrHeaderText || 'Thiếu Nhi') + '</div>'
                     + '<div class="card-body">' + qr + '</div>'
                     + '<div class="card-footer">' + fields + '</div></div>';

            case 'classic':
                return '<div class="the-c classic">'
                     + '<div class="card-inner">'
                     + (logoHtml && this.qrCustom.logoPosition === 'top' ? '<div class="logo-pos-top">' + logoHtml + '</div>' : '')
                     + '<div class="qr-wrap">' + qr + '</div>'
                     + '<div class="card-info">' + fields + '</div>'
                     + (logoHtml && this.qrCustom.logoPosition === 'bottom' ? '<div class="logo-pos-bottom">' + logoHtml + '</div>' : '')
                     + '</div></div>';

            case 'badge':
                return '<div class="the-c badge">'
                     + (logoHtml && this.qrCustom.logoPosition === 'top' ? '<div class="logo-pos-top">' + logoHtml + '</div>' : '')
                     + '<div class="qr-wrap">' + qr + '</div>'
                     + '<div class="card-info">' + fields + '</div>'
                     + (logoHtml && this.qrCustom.logoPosition !== 'top' ? '<div class="logo-pos-bottom">' + logoHtml + '</div>' : '')
                     + '</div>';

            case 'compact':
                return '<div class="the-c compact">'
                     + '<div class="qr-wrap">' + qr + '</div>'
                     + '<div class="card-info">' + fields + '</div>'
                     + (logoHtml ? '<div class="logo-inline">' + logoHtml + '</div>' : '')
                     + '</div>';

            case 'minimal':
                return '<div class="the-c minimal">'
                     + '<div class="qr-wrap-mini">' + qr + '</div>'
                     + '<div class="card-info-mini">' + fields + '</div>'
                     + '</div>';

            default:
                return this.qrCardHtml(s);
        }
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

    // CSS dựng theo tuỳ chọn — dùng cho cả xem trước lẫn bản in
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

    // Custom CSS cho các template mới
    qrCustomStyleCss() {
        const template = this.qrCustom.template || 'compact';
        const logoSize = '12mm';
        const qrSize = template === 'minimal' ? '18mm' : '22mm';

        let css = '*{box-sizing:border-box}'
            + 'body{font-family:"Be Vietnam Pro",system-ui,sans-serif;margin:0;color:#0f172a}'
            + '.luoi-c{display:grid;grid-template-columns:repeat(2,1fr);gap:4mm}'
            + '.the-c{border:1px solid #e2e8f0;border-radius:3mm;padding:3mm;break-inside:avoid;page-break-inside:avoid;background:#fff}'
            + '.card-header{font-size:9px;font-weight:800;color:#1e3a8a;text-transform:uppercase;letter-spacing:.3px;text-align:center;padding-bottom:2mm}'
            + '.card-body{display:flex;justify-content:center;padding:2mm 0}'
            + '.card-footer{text-align:center;padding-top:2mm}'
            + '.qr-wrap{display:flex;justify-content:center;align-items:center}'
            + '.qr-wrap img,.qr-wrap svg{width:' + qrSize + ';height:' + qrSize + ';image-rendering:pixelated}'
            + '.qr-wrap-mini img,.qr-wrap-mini svg{width:' + qrSize + ';height:' + qrSize + ';image-rendering:pixelated}'
            + '.card-info{text-align:center}'
            + '.card-info-mini{text-align:center}'
            + '.logo-img{width:' + logoSize + ';height:' + logoSize + ';object-fit:contain}'
            + '.logo-pos-top,.logo-pos-bottom{display:flex;justify-content:center;margin:1mm 0}'
            + '.logo-inline{display:flex;justify-content:center;align-items:center;margin-left:2mm}';

        // Template-specific styles
        switch (template) {
            case 'basic':
                css += '.the-c.basic{max-width:50mm;margin:0 auto}';
                break;
            case 'classic':
                css += '.the-c.classic{background:linear-gradient(to bottom,#f8fafc,#fff)}'
                     + '.card-inner{border:2px solid #e2e8f0;border-radius:2mm;padding:3mm;display:flex;flex-direction:column;align-items:center;gap:2mm}';
                break;
            case 'badge':
                css += '.the-c.badge{display:flex;flex-direction:column;align-items:center;text-align:center;gap:1mm;padding:2mm}';
                break;
            case 'compact':
                css += '.the-c.compact{display:flex;align-items:center;gap:3mm}'
                     + '.the-c.compact .card-info{flex:1;min-width:0}';
                break;
            case 'minimal':
                css += '.the-c.minimal{display:flex;align-items:center;gap:2mm;padding:2mm}'
                     + '.the-c.minimal .card-info-mini{min-width:0}';
                break;
        }

        // Common field styles
        css += '.ma{font-size:10px;font-weight:800;letter-spacing:.3px;color:#2563eb;margin:0 0 1px}'
            + '.ten{font-size:11px;font-weight:700;margin:0 0 1px;line-height:1.25;overflow-wrap:anywhere}'
            + '.lop{font-size:9px;color:#64748b;margin:0}'
            + '.ns{font-size:9px;color:#64748b;margin:0}';

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

    // Export với custom options
    async qrExport(type) {
        if (!this.qrReady) { window.TNTT.toast.warning('Bộ sinh mã chưa sẵn sàng.'); return; }
        const ds = this.qrPrintStudents;
        if (!ds.length) { window.TNTT.toast.warning('Không có em nào trong phạm vi đã chọn.'); return; }

        if (type === 'print') {
            await this.qrExportPrint(ds);
        } else if (type === 'png') {
            await this.qrExportPng(ds);
        } else if (type === 'pdf') {
            await this.qrExportPdf(ds);
        }
    },

    async qrExportPrint(ds) {
        this.qrTheDangLam = true;
        const cards = ds.map(s => this.qrCustomCardHtml(s)).join('');
        const ngay = this.formatDate(this.toDateInput(new Date()));
        const html = '<!doctype html><html lang="vi"><head><meta charset="utf-8"><title>Thẻ QR - Custom</title>'
            + '<style>@page{size:A4;margin:10mm}' + this.qrCustomStyleCss()
            + 'h1{font-size:15px;margin:0 0 2px}.phu{font-size:11px;color:#64748b;margin:0 0 10px}'
            + '.huongdan{margin-top:8mm;font-size:10px;color:#64748b;border-top:1px solid #e2e8f0;padding-top:3mm}'
            + '@media print{.huongdan{page-break-before:avoid}}</style></head><body>'
            + '<h1>Thẻ QR - Custom Template</h1>'
            + '<p class="phu">' + ds.length + ' em · ' + this.qrCustom.template + ' · in ngày ' + ngay + '</p>'
            + '<div class="luoi-c">' + cards + '</div>'
            + '<p class="huongdan">Mã QR chỉ chứa mã số của em, không chứa thông tin cá nhân nào.</p>'
            + '<scr' + 'ipt>window.onload=function(){window.print()};</scr' + 'ipt></body></html>';

        const cs = window.open('', '_blank');
        if (!cs) {
            this.qrTheDangLam = false;
            window.TNTT.toast.error('Trình duyệt đã chặn cửa sổ in.');
            return;
        }
        cs.document.write(html);
        cs.document.close();
        this.qrTheDangLam = false;
        this.logAction('xuat', 'students', 'Xuất thẻ QR (Custom)', ds.length + ' em');
    },

    async qrExportPng(ds) {
        window.TNTT.toast.info('Đang tạo file PNG...');
        // Sử dụng html2canvas hoặc canvas API để tạo ảnh
        const cards = ds.map(s => this.qrCustomCardHtml(s)).join('');
        const html = '<!doctype html><html lang="vi"><head><meta charset="utf-8"><style>' + this.qrCustomStyleCss() + '</style></head><body><div class="luoi-c">' + cards + '</div></body></html>';

        // Tạo iframe ẩn để render
        const iframe = document.createElement('iframe');
        iframe.style.cssText = 'position:absolute;width:1200px;height:800px;top:-9999px;left:-9999px;';
        document.body.appendChild(iframe);

        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        iframeDoc.open();
        iframeDoc.write(html);
        iframeDoc.close();

        // Chờ render xong rồi export
        setTimeout(() => {
            try {
                // Sử dụng html2canvas nếu có
                if (window.html2canvas) {
                    html2canvas(iframeDoc.body).then(canvas => {
                        const link = document.createElement('a');
                        link.download = 'the-qr-' + new Date().toISOString().slice(0,10) + '.png';
                        link.href = canvas.toDataURL('image/png');
                        link.click();
                        window.TNTT.toast.success('Đã tải file PNG');
                    }).catch(() => {
                        window.TNTT.toast.error('Không thể tạo PNG. Thử in trực tiếp.');
                    });
                } else {
                    // Fallback: mở print dialog với tùy chọn "Save as PDF"
                    this.qrExportPrint(ds);
                }
            } finally {
                document.body.removeChild(iframe);
            }
        }, 500);
    },

    async qrExportPdf(ds) {
        window.TNTT.toast.info('Đang tạo file PDF...');
        // Tương tự PNG nhưng sử dụng jsPDF
        const cards = ds.map(s => this.qrCustomCardHtml(s)).join('');
        const html = '<!doctype html><html lang="vi"><head><meta charset="utf-8"><style>' + this.qrCustomStyleCss() + '</style></head><body><div class="luoi-c">' + cards + '</div></body></html>';

        const iframe = document.createElement('iframe');
        iframe.style.cssText = 'position:absolute;width:1200px;height:800px;top:-9999px;left:-9999px;';
        document.body.appendChild(iframe);

        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        iframeDoc.open();
        iframeDoc.write(html);
        iframeDoc.close();

        setTimeout(async () => {
            try {
                if (window.html2canvas && window.jspdf) {
                    const canvas = await html2canvas(iframeDoc.body);
                    const { jsPDF } = window.jspdf;
                    const pdf = new jsPDF('p', 'mm', 'a4');
                    const imgData = canvas.toDataURL('image/png');
                    pdf.addImage(imgData, 'PNG', 10, 10, 190, 0);
                    pdf.save('the-qr-' + new Date().toISOString().slice(0,10) + '.pdf');
                    window.TNTT.toast.success('Đã tải file PDF');
                } else {
                    // Fallback: mở print dialog
                    this.qrExportPrint(ds);
                }
            } catch (e) {
                window.TNTT.toast.error('Không thể tạo PDF: ' + e.message);
            } finally {
                if (document.body.contains(iframe)) {
                    document.body.removeChild(iframe);
                }
            }
        }, 500);
    },

    // --- PRESETS MANAGEMENT ---

    async qrLoadPresets() {
        try {
            const res = await fetch('/api/custom-qrcard.php?action=list-presets');
            const data = await res.json();
            if (data.ok) {
                this.qrPresets = data.presets || [];
            }
        } catch (e) {
            console.error('Failed to load presets:', e);
        }
    },

    async qrLoadLogos() {
        try {
            const res = await fetch('/api/custom-qrcard.php?action=list-logos');
            const data = await res.json();
            if (data.ok) {
                this.qrLogos = data.logos || [];
            }
        } catch (e) {
            console.error('Failed to load logos:', e);
        }
    },

    qrLoadPreset(preset) {
        if (!preset || !preset.config) return;
        const config = preset.config;

        // Apply preset config to current custom options
        if (config.template) this.qrCustom.template = config.template;
        if (config.errorLevel) this.qrCustom.errorLevel = config.errorLevel;
        if (config.logoPosition) this.qrCustom.logoPosition = config.logoPosition;
        if (config.logoId) this.qrCustom.logoId = config.logoId;

        // Also apply basic options
        if (config.qrPerRow) this.qrPerRow = config.qrPerRow;
        if (config.qrCutLines !== undefined) this.qrCutLines = config.qrCutLines;
        if (config.qrHeader !== undefined) this.qrHeader = config.qrHeader;
        if (config.qrHeaderText) this.qrHeaderText = config.qrHeaderText;

        // Apply fields
        if (config.qrFields) {
            this.qrFields = { ...this.qrFields, ...config.qrFields };
        }

        this.qrCustomActivePresetId = preset.id;
        window.TNTT.toast.success('Đã áp dụng preset: ' + preset.name);
    },

    async qrSavePreset() {
        if (!this.qrPresetName.trim()) {
            window.TNTT.toast.warning('Vui lòng nhập tên preset.');
            return;
        }

        const config = {
            template: this.qrCustom.template,
            errorLevel: this.qrCustom.errorLevel,
            logoPosition: this.qrCustom.logoPosition,
            logoId: this.qrCustom.logoId,
            qrPerRow: this.qrPerRow,
            qrCutLines: this.qrCutLines,
            qrHeader: this.qrHeader,
            qrHeaderText: this.qrHeaderText,
            qrFields: { ...this.qrFields },
        };

        try {
            const res = await fetch('/api/custom-qrcard.php?action=save-preset', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: this.qrPresetName.trim(),
                    config: config,
                    isDefault: this.qrPresetIsDefault,
                    id: this.qrPresetEditingId || null,
                }),
            });
            const data = await res.json();
            if (data.ok) {
                window.TNTT.toast.success('Đã lưu preset');
                this.qrShowPresetModal = false;
                this.qrPresetName = '';
                this.qrPresetIsDefault = false;
                this.qrPresetEditingId = null;
                await this.qrLoadPresets();
            } else {
                window.TNTT.toast.error(data.error || 'Không thể lưu preset');
            }
        } catch (e) {
            window.TNTT.toast.error('Lỗi khi lưu preset');
        }
    },

    async qrDeletePreset(id) {
        if (!await window.TNTT.toast.confirm('Xóa preset này?')) return;

        try {
            const res = await fetch('/api/custom-qrcard.php?action=delete-preset', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id }),
            });
            const data = await res.json();
            if (data.ok) {
                window.TNTT.toast.success('Đã xóa preset');
                if (this.qrCustomActivePresetId === id) {
                    this.qrCustomActivePresetId = null;
                }
                await this.qrLoadPresets();
            } else {
                window.TNTT.toast.error(data.error || 'Không thể xóa preset');
            }
        } catch (e) {
            window.TNTT.toast.error('Lỗi khi xóa preset');
        }
    },

    async qrSetDefaultPreset(id) {
        try {
            const preset = this.qrPresets.find(p => p.id === id);
            if (!preset) return;

            // Re-save with isDefault = true
            const res = await fetch('/api/custom-qrcard.php?action=save-preset', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: id,
                    name: preset.name,
                    config: { ...preset.config, isDefault: true },
                    isDefault: true,
                }),
            });
            const data = await res.json();
            if (data.ok) {
                window.TNTT.toast.success('Đã đặt làm preset mặc định');
                await this.qrLoadPresets();
            }
        } catch (e) {
            window.TNTT.toast.error('Lỗi khi đặt preset mặc định');
        }
    },

    // --- LOGO MANAGEMENT ---

    async qrUploadLogo(event) {
        const file = event.target.files && event.target.files[0];
        if (!file) return;

        if (file.size > 2 * 1024 * 1024) {
            window.TNTT.toast.error('File quá lớn. Tối đa 2MB.');
            return;
        }

        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        if (!allowedTypes.includes(file.type)) {
            window.TNTT.toast.error('Chỉ chấp nhận file ảnh: JPG, PNG, GIF, WebP, SVG.');
            return;
        }

        this.qrLogoUploading = true;

        try {
            const formData = new FormData();
            formData.append('logo', file);

            const res = await fetch('/api/custom-qrcard.php?action=upload-logo', {
                method: 'POST',
                body: formData,
            });
            const data = await res.json();
            if (data.ok) {
                window.TNTT.toast.success('Đã upload logo');
                await this.qrLoadLogos();
            } else {
                window.TNTT.toast.error(data.error || 'Không thể upload logo');
            }
        } catch (e) {
            window.TNTT.toast.error('Lỗi khi upload logo');
        } finally {
            this.qrLogoUploading = false;
            if (event.target) event.target.value = '';
        }
    },

    async qrDeleteLogo(id) {
        if (!await window.TNTT.toast.confirm('Xóa logo này?')) return;

        try {
            const res = await fetch('/api/custom-qrcard.php?action=delete-logo', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id }),
            });
            const data = await res.json();
            if (data.ok) {
                window.TNTT.toast.success('Đã xóa logo');
                if (this.qrCustom.logoId === id) {
                    this.qrCustom.logoId = null;
                }
                await this.qrLoadLogos();
            } else {
                window.TNTT.toast.error(data.error || 'Không thể xóa logo');
            }
        } catch (e) {
            window.TNTT.toast.error('Lỗi khi xóa logo');
        }
    },

    qrHandleLogoDrop(event) {
        const file = event.dataTransfer.files && event.dataTransfer.files[0];
        if (file) {
            // Create a synthetic event for upload
            const input = this.$refs?.logoInput;
            if (input) {
                const dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
                this.qrUploadLogo({ target: input });
            }
        }
    },

    // Format date for display
    qrFormatDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
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

    /** Vẽ QR với error level tùy chỉnh */
    _qrAnhCustom(noiDung, coO, errorLevel) {
        const level = errorLevel || 'M';
        const qr = window.qrcode(0, level);
        qr.addData(String(noiDung));
        qr.make();
        return qr.createImgTag(coO, 0);
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
