<!-- MÀN IN THẺ QR (một thẻ trong module Thiếu Nhi) -->
<!-- CÓ 2 CHẾ ĐỘ: In Nhanh (cũ) và Tùy Chỉnh (mới) -->
<div data-module="qrcard" class="module-panel pt-6 pb-24 relative" x-data="{

    // --- STATE CHUNG ---
    qrMode: 'quick',  // 'quick' | 'custom'

    // --- STATE IN NHANH (giữ nguyên từ code cũ) ---
    qrTheDangLam: false,
    qrReady: false,
    qrScopeType: 'class',
    qrScopeValue: '',
    qrFields: { code: true, holyName: true, name: true, className: true, block: false, birthDate: false },
    qrTemplate: 'compact',
    qrPerRow: 3,
    qrCutLines: true,
    qrHeader: true,
    qrHeaderText: '',
    qrSelectedIds: [],

    // --- STATE TÙY CHỈNH (Custom QR Card) ---
    customSelectedIds: [],
    customScopeType: 'class',
    customScopeValue: '',
    customOptions: {
        template: 'basic',
        qrColor: '#000000',
        bgColor: '#ffffff',
        textColor: '#1e293b',
        qrSize: '25mm',
        fontSize: 'medium',
        fields: ['code', 'name'],
        headerText: '',
        showLogo: false,
        logoUrl: '',
        logoPosition: 'top'
    },
    customPreviewHtml: '',
    customPreviewLoading: false,
    customIsLoading: false,
    customLogos: [],
    customPresets: [],
    customNewPresetName: '',

    // Templates
    customTemplates: [
        { code: 'basic', name: 'Basic', desc: 'QR + thông tin cơ bản' },
        { code: 'classic', name: 'Classic', desc: 'Viền trang trí' },
        { code: 'badge', name: 'Thẻ đeo', desc: 'Hình tròn, có lỗ treo' },
        { code: 'compact', name: 'Gọn nhẹ', desc: 'In cắt dán' },
        { code: 'minimal', name: 'Tối giản', desc: 'Tối giản' }
    ],

    // Available fields
    customFields: [
        { key: 'code', label: 'Mã số' },
        { key: 'holyName', label: 'Tên thánh' },
        { key: 'name', label: 'Họ tên' },
        { key: 'className', label: 'Lớp' },
        { key: 'block', label: 'Khối' },
        { key: 'birthDate', label: 'Ngày sinh' }
    ],

    qrSizes: [
        { value: '20mm', label: '20mm' },
        { value: '25mm', label: '25mm' },
        { value: '30mm', label: '30mm' },
        { value: '35mm', label: '35mm' }
    ],

    fontSizes: [
        { value: 'small', label: 'Nhỏ' },
        { value: 'medium', label: 'Trung bình' },
        { value: 'large', label: 'Lớn' }
    ],

    // Computed
    get customAvailableStudents() {
        let list = (window.TNTT?.accessibleStudents || []).filter(s => s.status === 'đang sinh hoạt');
        if (this.customScopeType === 'class' && this.customScopeValue) {
            list = list.filter(s => s.className === this.customScopeValue);
        } else if (this.customScopeType === 'block' && this.customScopeValue) {
            list = list.filter(s => s.block === this.customScopeValue);
        }
        return list;
    },

    get customPreviewStudents() {
        return this.customSelectedIds
            .map(id => this.customAvailableStudents.find(s => s.id === id))
            .filter(s => s)
            .slice(0, 6);
    },

    get customPrintStudents() {
        return this.customSelectedIds
            .map(id => this.customAvailableStudents.find(s => s.id === id))
            .filter(s => s);
    },

    get canCustomExport() {
        return this.customSelectedIds.length > 0 && !this.customIsLoading;
    },

    // Methods
    async initCustom() {
        // Set defaults
        if (!this.customHeaderText) {
            this.customHeaderText = 'Thiếu Nhi Thánh Thể — Phú Trung' + (window.TNTT?.year ? ' · ' + window.TNTT.year.name : '');
        }
        this.loadCustomLibraries();
        this.loadCustomPresets();
        this.loadCustomLogos();
    },

    customOnScopeTypeChange() {
        if (this.customScopeType === 'class') {
            this.customScopeValue = (window.TNTT?.availableClasses || [])[0] || '';
        } else if (this.customScopeType === 'block') {
            this.customScopeValue = (window.TNTT?.availableBlocks || [])[0] || '';
        } else {
            this.customScopeValue = '';
        }
        this.customSyncSelectedToScope();
    },

    customSyncSelectedToScope() {
        const students = this.customAvailableStudents;
        this.customSelectedIds = students.map(s => s.id);
    },

    customToggleField(key) {
        const idx = this.customOptions.fields.indexOf(key);
        if (idx >= 0) {
            this.customOptions.fields.splice(idx, 1);
        } else {
            this.customOptions.fields.push(key);
        }
        if (!this.customOptions.fields.includes('code')) {
            this.customOptions.fields.push('code');
        }
        this.customDebouncePreview();
    },

    customIsFieldSelected(key) {
        return this.customOptions.fields.includes(key);
    },

    customDebouncePreview() {
        if (this._previewTimeout) clearTimeout(this._previewTimeout);
        this._previewTimeout = setTimeout(() => this.customGeneratePreview(), 300);
    },

    async customGeneratePreview() {
        if (!this.customSelectedIds.length) {
            this.customPreviewHtml = '<p style=\"color:#64748b;font-size:13px;margin:0\">Chọn học sinh để xem trước.</p>';
            return;
        }

        this.customPreviewLoading = true;

        try {
            const response = await fetch('/api/custom-qrcard.php?action=preview', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.TNTT?.csrfToken || ''
                },
                body: JSON.stringify({
                    student_ids: this.customSelectedIds.slice(0, 6),
                    options: this.customOptions
                })
            });

            const data = await response.json();

            if (data.ok) {
                this.customPreviewHtml = data.html;
                this.$nextTick(() => this.customRenderQRCodes());
            } else {
                this.customPreviewHtml = '<p style=\"color:#ef4444;font-size:13px;margin:0\">' + (data.error || 'Lỗi.') + '</p>';
            }
        } catch (err) {
            console.error('Preview error:', err);
            this.customPreviewHtml = '<p style=\"color:#ef4444;font-size:13px;margin:0\">Không thể tạo preview.</p>';
        } finally {
            this.customPreviewLoading = false;
        }
    },

    customRenderQRCodes() {
        const containers = document.querySelectorAll('.qr-wrapper[data-code]');
        containers.forEach(container => {
            const code = container.dataset.code;
            const color = container.dataset.color || '#000000';
            if (window.qrcode) {
                const qr = window.qrcode(0, 'M');
                qr.addData(code);
                qr.make();
                const moduleCount = qr.getModuleCount();
                const size = Math.min(container.offsetWidth || 100, container.offsetHeight || 100);
                const cellSize = Math.floor(size / moduleCount);
                const actualSize = moduleCount * cellSize;

                let svg = '<svg width=\"' + actualSize + '\" height=\"' + actualSize + '\" viewBox=\"0 0 ' + actualSize + ' ' + actualSize + '\">';
                svg += '<rect width=\"' + actualSize + '\" height=\"' + actualSize + '\" fill=\"white\"/>';
                for (let r = 0; r < moduleCount; r++) {
                    for (let c = 0; c < moduleCount; c++) {
                        if (qr.isDark(r, c)) {
                            svg += '<rect x=\"' + (c * cellSize) + '\" y=\"' + (r * cellSize) + '\" width=\"' + cellSize + '\" height=\"' + cellSize + '\" fill=\"' + color + '\"/>';
                        }
                    }
                }
                svg += '</svg>';
                container.innerHTML = svg;
            }
        });
    },

    async customExportPNG() {
        if (!this.canCustomExport) {
            window.TNTT?.toast?.warning('Chọn học sinh trước khi xuất.');
            return;
        }
        this.customIsLoading = true;
        try {
            const response = await fetch('/api/custom-qrcard.php?action=export_png', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.TNTT?.csrfToken || ''
                },
                body: JSON.stringify({
                    student_ids: this.customSelectedIds,
                    options: this.customOptions
                })
            });
            const data = await response.json();
            if (data.ok && window.html2canvas) {
                const container = document.createElement('div');
                container.style.cssText = 'position:fixed;left:-9999px;top:-9999px;width:210mm;background:white;padding:10mm';
                container.innerHTML = data.html;
                document.body.appendChild(container);
                await document.fonts.ready;
                const canvas = await html2canvas(container, { scale: 2, useCORS: true, backgroundColor: '#ffffff' });
                const link = document.createElement('a');
                link.download = 'qrcards_' + Date.now() + '.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
                document.body.removeChild(container);
                window.TNTT?.toast?.success('Đã xuất ' + data.count + ' thẻ.');
            }
        } catch (err) {
            console.error('Export PNG error:', err);
            window.TNTT?.toast?.error('Không thể xuất PNG.');
        } finally {
            this.customIsLoading = false;
        }
    },

    async customExportPDF() {
        if (!this.canCustomExport) {
            window.TNTT?.toast?.warning('Chọn học sinh trước khi xuất.');
            return;
        }
        this.customIsLoading = true;
        try {
            const response = await fetch('/api/custom-qrcard.php?action=export_pdf', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.TNTT?.csrfToken || ''
                },
                body: JSON.stringify({
                    student_ids: this.customSelectedIds,
                    options: this.customOptions
                })
            });
            const data = await response.json();
            if (data.ok && window.html2canvas && window.jspdf) {
                const container = document.createElement('div');
                container.style.cssText = 'position:fixed;left:-9999px;top:-9999px;width:210mm;background:white;padding:10mm';
                container.innerHTML = data.html;
                document.body.appendChild(container);
                await document.fonts.ready;
                const canvas = await html2canvas(container, { scale: 2, useCORS: true, backgroundColor: '#ffffff' });
                const imgData = canvas.toDataURL('image/png');
                const pdf = new jspdf.jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
                const pdfWidth = pdf.internal.pageSize.getWidth();
                const pdfHeight = pdf.internal.pageSize.getHeight();
                pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
                pdf.save('qrcards_' + Date.now() + '.pdf');
                document.body.removeChild(container);
                window.TNTT?.toast?.success('Đã xuất ' + data.count + ' thẻ.');
            }
        } catch (err) {
            console.error('Export PDF error:', err);
            window.TNTT?.toast?.error('Không thể xuất PDF.');
        } finally {
            this.customIsLoading = false;
        }
    },

    customPrint() {
        if (!this.canCustomExport) {
            window.TNTT?.toast?.warning('Chọn học sinh trước khi in.');
            return;
        }
        const students = this.customPrintStudents;
        const ngay = new Date().toLocaleDateString('vi-VN');
        const opts = this.customOptions;
        const cardsHtml = students.map(s => this.customBuildCardHtml(s, opts)).join('');
        const headerText = opts.headerText || 'Thiếu Nhi Thánh Thể — Phú Trung';
        const html = '<!DOCTYPE html><html lang=\"vi\"><head><meta charset=\"UTF-8\"><title>Thẻ QR</title>'
            + '<style>@page{size:A4;margin:10mm}body{font-family:\"Be Vietnam Pro\",system-ui,sans-serif;margin:0}.header{text-align:center;margin-bottom:10px}.header h1{font-size:14px;margin:0 0 4px;color:#1e3a8a}.header p{font-size:10px;color:#64748b;margin:0}.luoi{display:flex;flex-wrap:wrap;gap:5mm}</style></head><body>'
            + '<div class=\"header\"><h1>' + this._escapeHtml(headerText) + '</h1><p>' + students.length + ' em · In ngày ' + ngay + '</p></div>'
            + '<div class=\"luoi\">' + cardsHtml + '</div>'
            + '<script src=\"/assets/js/vendor/qrcode.min.js\"></scr' + 'ipt>'
            + '<script>document.addEventListener(\"DOMContentLoaded\",function(){document.querySelectorAll(\".qr-wrapper[data-code]\").forEach(function(c){var code=c.dataset.code,color=c.dataset.color||\"#000000\";if(window.qrcode){var qr=window.qrcode(0,\"M\");qr.addData(code);qr.make();var mc=qr.getModuleCount(),sz=Math.min(c.offsetWidth||80,c.offsetHeight||80),cs=Math.floor(sz/mc),as=mc*cs;var svg=\"<svg width=\"+as+\" height=\"+as+\" viewBox=0 0 \"+as+\" \"+as+\"><rect width=\"+as+\" height=\"+as+\" fill=white/>\";for(var r=0;r<mc;r++)for(var cc=0;cc<mc;cc++)if(qr.isDark(r,cc))svg+=\"<rect x=\"+(cc*cs)+\" y=\"+(r*cs)+\" width=\"+cs+\" height=\"+cs+\" fill=\"+color+\"/>\";svg+=\"</svg>\";c.innerHTML=svg}})});setTimeout(function(){window.print()},500)});</scr' + 'ipt></body></html>';
        const printWin = window.open('', '_blank');
        if (!printWin) {
            window.TNTT?.toast?.error('Trình duyệt đã chặn cửa sổ in.');
            return;
        }
        printWin.document.write(html);
        printWin.document.close();
    },

    customBuildCardHtml(s, opts) {
        const qrColor = opts.qrColor || '#000000';
        const bgColor = opts.bgColor || '#ffffff';
        const textColor = opts.textColor || '#1e293b';
        const fields = opts.fields || ['code', 'name'];
        let html = '<div style=\"background:' + bgColor + ';border:1px solid #e2e8f0;border-radius:4mm;padding:3mm;display:flex;flex-direction:column;align-items:center;text-align:center;max-width:55mm\">';
        if (opts.headerText) {
            html += '<div style=\"font-size:8px;font-weight:800;color:' + textColor + ';text-transform:uppercase;letter-spacing:.3px;margin-bottom:2mm;width:100%\">' + this._escapeHtml(opts.headerText) + '</div>';
        }
        html += '<div class=\"qr-wrapper\" data-code=\"' + this._escapeHtml(s.code) + '\" data-color=\"' + this._escapeHtml(qrColor) + '\" style=\"display:flex;justify-content:center;align-items:center;margin-bottom:2mm\"></div>';
        html += '<div style=\"color:' + textColor + '\">';
        fields.forEach(f => {
            if (f === 'code') html += '<p style=\"font-size:10px;font-weight:800;letter-spacing:.3px;margin:0 0 1px;color:#2563eb\">' + this._escapeHtml(s.code) + '</p>';
            else if (f === 'name') {
                const hn = s.holyName ? s.holyName + ' ' : '';
                html += '<p style=\"font-size:12px;font-weight:700;margin:0 0 1px;line-height:1.25\">' + this._escapeHtml(hn + s.name) + '</p>';
            }
            else if (f === 'className' && s.className) html += '<p style=\"font-size:9px;color:#64748b;margin:0\">Lớp: ' + this._escapeHtml(s.className) + '</p>';
            else if (f === 'block' && s.block) html += '<p style=\"font-size:9px;color:#64748b;margin:0\">' + this._escapeHtml(s.block) + '</p>';
            else if (f === 'birthDate' && s.birthDate) html += '<p style=\"font-size:9px;color:#64748b;margin:0\">Sinh: ' + this._escapeHtml(s.birthDate) + '</p>';
        });
        html += '</div></div>';
        return html;
    },

    _escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\"/g,'&quot;');
    },

    async loadCustomPresets() {
        try {
            const response = await fetch('/api/custom-qrcard.php?action=list_presets', {
                headers: { 'X-CSRF-Token': window.TNTT?.csrfToken || '' }
            });
            const data = await response.json();
            if (data.ok) this.customPresets = data.presets || [];
        } catch (err) { console.error('Load presets error:', err); }
    },

    async saveCustomPreset() {
        if (!this.customNewPresetName.trim()) {
            window.TNTT?.toast?.warning('Nhập tên preset.');
            return;
        }
        try {
            const response = await fetch('/api/custom-qrcard.php?action=save_preset', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.TNTT?.csrfToken || ''
                },
                body: JSON.stringify({ name: this.customNewPresetName.trim(), options: this.customOptions })
            });
            const data = await response.json();
            if (data.ok) {
                window.TNTT?.toast?.success(data.message);
                this.customNewPresetName = '';
                this.loadCustomPresets();
            } else {
                window.TNTT?.toast?.error(data.error);
            }
        } catch (err) { window.TNTT?.toast?.error('Lỗi khi lưu preset.'); }
    },

    applyCustomPreset(preset) {
        if (!preset || !preset.options) return;
        this.customOptions = { ...this.customOptions, ...preset.options };
        this.customDebouncePreview();
        window.TNTT?.toast?.info('Đã áp dụng preset.');
    },

    async deleteCustomPreset(id) {
        if (!confirm('Xóa preset này?')) return;
        try {
            const response = await fetch('/api/custom-qrcard.php?action=delete_preset', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.TNTT?.csrfToken || ''
                },
                body: JSON.stringify({ id })
            });
            const data = await response.json();
            if (data.ok) {
                window.TNTT?.toast?.success('Đã xóa preset.');
                this.customPresets = this.customPresets.filter(p => p.id !== id);
            }
        } catch (err) { window.TNTT?.toast?.error('Lỗi khi xóa preset.'); }
    },

    async loadCustomLogos() {
        try {
            const response = await fetch('/api/custom-qrcard.php?action=list_logos', {
                headers: { 'X-CSRF-Token': window.TNTT?.csrfToken || '' }
            });
            const data = await response.json();
            if (data.ok) this.customLogos = data.logos || [];
        } catch (err) { console.error('Load logos error:', err); }
    },

    async uploadCustomLogo(file) {
        if (!file) return;
        const allowedTypes = ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            window.TNTT?.toast?.error('Chỉ chấp nhận JPG, PNG, SVG, WebP.');
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            window.TNTT?.toast?.error('File không được quá 2MB.');
            return;
        }
        const formData = new FormData();
        formData.append('logo', file);
        try {
            const response = await fetch('/api/custom-qrcard.php?action=upload_logo', {
                method: 'POST',
                headers: { 'X-CSRF-Token': window.TNTT?.csrfToken || '' },
                body: formData
            });
            const data = await response.json();
            if (data.ok) {
                window.TNTT?.toast?.success('Đã upload logo.');
                this.customLogos.unshift({ id: data.logo_id, url: data.url, original_name: data.original_name });
                this.customOptions.logoUrl = data.url;
                this.customOptions.showLogo = true;
                this.customDebouncePreview();
            } else {
                window.TNTT?.toast?.error(data.error);
            }
        } catch (err) { window.TNTT?.toast?.error('Lỗi khi upload logo.'); }
    },

    selectCustomLogo(url) {
        this.customOptions.logoUrl = url;
        this.customOptions.showLogo = true;
        this.customDebouncePreview();
    },

    removeCustomLogo() {
        this.customOptions.logoUrl = '';
        this.customOptions.showLogo = false;
        this.customDebouncePreview();
    },

    loadCustomLibraries() {
        if (!window.html2canvas) {
            const s = document.createElement('script');
            s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
            document.head.appendChild(s);
        }
        if (!window.jspdf) {
            const s = document.createElement('script');
            s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
            document.head.appendChild(s);
        }
    }

}" x-init="initCustom()">

    <!-- Thanh điều hướng gộp (Thiếu nhi) -->
    <?php include __DIR__ . '/partial_children_tabs.php'; ?>

    <!-- Tab chuyển đổi In Nhanh / Tùy Chỉnh -->
    <div class="flex gap-1 mb-4 bg-slate-100 p-1 rounded-xl w-fit mx-auto">
        <button @click="qrMode = 'quick'; $nextTick(() => { if(!qrHeaderText) qrHeaderText = 'Thiếu Nhi Thánh Thể — Phú Trung' + (TNTT?.year ? ' · ' + TNTT.year.name : ''); })"
                :class="qrMode === 'quick' ? 'bg-white shadow text-blue-600 font-bold' : 'text-slate-500 font-medium'"
                class="px-4 py-2 rounded-lg text-sm transition-all">
            <i data-lucide="zap" class="w-4 h-4 inline mr-1"></i> In Nhanh
        </button>
        <button @click="qrMode = 'custom'; customOnScopeTypeChange()"
                :class="qrMode === 'custom' ? 'bg-white shadow text-blue-600 font-bold' : 'text-slate-500 font-medium'"
                class="px-4 py-2 rounded-lg text-sm transition-all">
            <i data-lucide="sliders" class="w-4 h-4 inline mr-1"></i> Tùy Chỉnh
        </button>
    </div>

    <!-- ==================== CHẾ ĐỘ IN NHANH (cũ) ==================== -->
    <div x-show="qrMode === 'quick'" class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <!-- CỘT TRÁI: TUỲ CHỌN -->
        <div class="space-y-4">

            <!-- Phạm vi in -->
            <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Phạm vi in</h3>
                <div class="flex gap-1.5 mb-3">
                    <template x-for="opt in [{v:'class',t:'Theo lớp'},{v:'block',t:'Theo khối'},{v:'all',t:'Tất cả'}]" :key="opt.v">
                        <button type="button" @click="qrScopeType = opt.v; qrOnScopeType()"
                                class="flex-1 px-3 py-2 rounded-xl font-bold text-xs border transition-colors"
                                :class="qrScopeType === opt.v ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-500 border-slate-200'"
                                x-text="opt.t"></button>
                    </template>
                </div>
                <select x-show="qrScopeType === 'class'" x-model="qrScopeValue" @change="qrSyncSelected()"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800">
                    <template x-for="c in availableClasses" :key="c"><option :value="c" x-text="c"></option></template>
                </select>
                <select x-show="qrScopeType === 'block'" style="display:none" x-model="qrScopeValue" @change="qrSyncSelected()"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800">
                    <template x-for="b in availableBlocks" :key="b"><option :value="b" x-text="b"></option></template>
                </select>

                <div class="mt-3 border border-slate-100 rounded-xl overflow-hidden">
                    <div class="bg-slate-50 px-3 py-2 flex items-center justify-between border-b border-slate-100">
                        <label class="flex items-center gap-2 text-xs font-bold text-slate-600 cursor-pointer">
                            <input type="checkbox"
                                   :checked="qrSelectedIds.length === qrScopeStudents.length && qrScopeStudents.length > 0"
                                   @change="$event.target.checked ? (qrSelectedIds = qrScopeStudents.map(s => s.id)) : (qrSelectedIds = [])"
                                   class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            Chọn tất cả
                        </label>
                        <span class="text-micro font-bold text-blue-600" x-text="qrSelectedIds.length + ' / ' + qrScopeStudents.length"></span>
                    </div>
                    <div class="max-h-48 overflow-y-auto bg-white p-2 space-y-1">
                        <template x-for="s in qrScopeStudents" :key="s.id">
                            <label class="flex items-center gap-2.5 p-2 rounded-lg hover:bg-slate-50 cursor-pointer transition-colors"
                                   :class="qrSelectedIds.includes(s.id) ? 'bg-blue-50/50' : ''">
                                <input type="checkbox" :value="s.id" x-model.number="qrSelectedIds"
                                       class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 shrink-0">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-slate-800 leading-tight">
                                        <span class="font-normal text-slate-500 mr-0.5" x-text="s.holyName"></span>
                                        <span x-text="s.name"></span>
                                    </p>
                                    <p class="text-micro text-slate-500" x-text="s.code + ' • Lớp ' + s.className"></p>
                                </div>
                            </label>
                        </template>
                        <div x-show="qrScopeStudents.length === 0" class="text-center py-4 text-xs text-slate-400 font-medium">Không có em nào trong phạm vi này.</div>
                    </div>
                </div>
            </div>

            <!-- Thông tin trên thẻ -->
            <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Thông tin trên thẻ</h3>
                <div class="grid grid-cols-2 gap-2">
                    <template x-for="f in [{k:'code',t:'Mã số'},{k:'holyName',t:'Tên thánh'},{k:'name',t:'Họ tên'},{k:'className',t:'Lớp'},{k:'block',t:'Khối'},{k:'birthDate',t:'Ngày sinh'}]" :key="f.k">
                        <label class="flex items-center gap-2 text-sm text-slate-700 bg-slate-50 rounded-xl px-3 py-2 cursor-pointer">
                            <input type="checkbox" x-model="qrFields[f.k]" class="w-4 h-4 rounded">
                            <span x-text="f.t"></span>
                        </label>
                    </template>
                </div>
                <p class="text-micro text-amber-700 bg-amber-50 border border-amber-100 rounded-xl px-3 py-2 mt-3 leading-relaxed">
                    Mã QR luôn chỉ chứa <b>mã số</b> — an toàn nếu thẻ rơi. Các mục bật ở trên là chữ IN trên thẻ.
                </p>
            </div>

            <!-- Kiểu thẻ & bố cục -->
            <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Kiểu thẻ &amp; bố cục</h3>
                <div class="flex gap-1.5 mb-3">
                    <button type="button" @click="qrTemplate='compact'"
                            class="flex-1 px-3 py-2 rounded-xl font-bold text-xs border transition-colors"
                            :class="qrTemplate==='compact' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-500 border-slate-200'">Gọn (cắt dán)</button>
                    <button type="button" @click="qrTemplate='badge'"
                            class="flex-1 px-3 py-2 rounded-xl font-bold text-xs border transition-colors"
                            :class="qrTemplate==='badge' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-500 border-slate-200'">Thẻ đeo</button>
                </div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-slate-700">Số thẻ mỗi hàng</span>
                    <div class="flex gap-1.5">
                        <template x-for="n in [2,3,4]" :key="n">
                            <button type="button" @click="qrPerRow=n"
                                    class="w-8 h-8 rounded-lg font-bold text-xs border transition-colors"
                                    :class="qrPerRow===n ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-500 border-slate-200'"
                                    x-text="n"></button>
                        </template>
                    </div>
                </div>
                <label class="flex items-center justify-between text-sm text-slate-700 py-1.5">
                    <span>Viền nét đứt để cắt</span>
                    <input type="checkbox" x-model="qrCutLines" class="w-4 h-4 rounded">
                </label>
                <label class="flex items-center justify-between text-sm text-slate-700 py-1.5">
                    <span>In tiêu đề đoàn</span>
                    <input type="checkbox" x-model="qrHeader" class="w-4 h-4 rounded">
                </label>
                <input x-show="qrHeader" x-model="qrHeaderText" type="text" placeholder="Tiêu đề đoàn / niên khoá"
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 mt-1">
            </div>

            <button @click="inTheQR()" :disabled="qrTheDangLam || qrPrintStudents.length === 0"
                    class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center gap-2 disabled:opacity-50">
                <i data-lucide="printer" class="w-5 h-5"></i>
                <span x-text="qrTheDangLam ? 'Đang tạo…' : 'In thẻ (' + qrPrintStudents.length + ' em)'"></span>
            </button>
        </div>

        <!-- CỘT PHẢI: XEM TRƯỚC -->
        <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Xem trước <span class="text-slate-400 normal-case font-medium">(tối đa 6 thẻ)</span></h3>
            <div class="bg-slate-50 rounded-2xl p-3 overflow-x-auto" x-html="qrPreviewHtml"></div>
        </div>
    </div>

    <!-- ==================== CHẾ ĐỘ TÙY CHỈNH (mới) ==================== -->
    <div x-show="qrMode === 'custom'" class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <!-- CỘT TRÁI: TUỲ CHỌN -->
        <div class="space-y-4">

            <!-- Phạm vi chọn học sinh -->
            <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Chọn học sinh</h3>
                <div class="flex gap-1.5 mb-3">
                    <template x-for="opt in [{v:'class',t:'Theo lớp'},{v:'block',t:'Theo khối'},{v:'all',t:'Tất cả'}]" :key="opt.v">
                        <button type="button" @click="customScopeType = opt.v; customOnScopeTypeChange()"
                                class="flex-1 px-3 py-2 rounded-xl font-bold text-xs border transition-colors"
                                :class="customScopeType === opt.v ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-500 border-slate-200'"
                                x-text="opt.t"></button>
                    </template>
                </div>
                <select x-show="customScopeType === 'class'" x-model="customScopeValue" @change="customSyncSelectedToScope()"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm mb-3">
                    <template x-for="c in availableClasses" :key="c"><option :value="c" x-text="c"></option></template>
                </select>
                <select x-show="customScopeType === 'block'" style="display:none" x-model="customScopeValue" @change="customSyncSelectedToScope()"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm mb-3">
                    <template x-for="b in availableBlocks" :key="b"><option :value="b" x-text="b"></option></template>
                </select>

                <!-- Danh sách học sinh -->
                <div class="border border-slate-100 rounded-xl overflow-hidden">
                    <div class="bg-slate-50 px-3 py-2 flex items-center justify-between border-b border-slate-100">
                        <label class="flex items-center gap-2 text-xs font-bold text-slate-600 cursor-pointer">
                            <input type="checkbox"
                                   :checked="customSelectedIds.length === customAvailableStudents.length && customAvailableStudents.length > 0"
                                   @change="$event.target.checked ? (customSelectedIds = customAvailableStudents.map(s => s.id)) : (customSelectedIds = [])"
                                   class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            Chọn tất cả
                        </label>
                        <span class="text-micro font-bold text-blue-600" x-text="customSelectedIds.length + ' / ' + customAvailableStudents.length"></span>
                    </div>
                    <div class="max-h-48 overflow-y-auto bg-white p-2 space-y-1">
                        <template x-for="s in customAvailableStudents" :key="s.id">
                            <label class="flex items-center gap-2.5 p-2 rounded-lg hover:bg-slate-50 cursor-pointer transition-colors"
                                   :class="customSelectedIds.includes(s.id) ? 'bg-blue-50/50' : ''">
                                <input type="checkbox" :value="s.id" x-model="customSelectedIds"
                                       class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 shrink-0">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-slate-800 leading-tight">
                                        <span class="font-normal text-slate-500 mr-0.5" x-text="s.holyName"></span>
                                        <span x-text="s.name"></span>
                                    </p>
                                    <p class="text-micro text-slate-500" x-text="s.code + ' • Lớp ' + s.className"></p>
                                </div>
                            </label>
                        </template>
                        <div x-show="customAvailableStudents.length === 0" class="text-center py-4 text-xs text-slate-400 font-medium">Không có em nào.</div>
                    </div>
                </div>
            </div>

            <!-- Template -->
            <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Template</h3>
                <div class="grid grid-cols-2 gap-2">
                    <template x-for="t in customTemplates" :key="t.code">
                        <button type="button" @click="customOptions.template = t.code; customDebouncePreview()"
                                class="px-3 py-2 rounded-xl text-xs font-medium border transition-all text-left"
                                :class="customOptions.template === t.code ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-200 hover:border-blue-300'">
                            <span x-text="t.name"></span>
                            <span class="block text-micro opacity-70" x-text="t.desc"></span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Màu sắc -->
            <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Màu sắc</h3>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Màu QR</label>
                        <div class="flex items-center gap-2">
                            <input type="color" x-model="customOptions.qrColor" @change="customDebouncePreview()"
                                   class="w-8 h-8 rounded cursor-pointer border-0">
                            <input type="text" x-model="customOptions.qrColor" @change="customDebouncePreview()"
                                   class="flex-1 bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 text-xs">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Màu nền</label>
                        <div class="flex items-center gap-2">
                            <input type="color" x-model="customOptions.bgColor" @change="customDebouncePreview()"
                                   class="w-8 h-8 rounded cursor-pointer border-0">
                            <input type="text" x-model="customOptions.bgColor" @change="customDebouncePreview()"
                                   class="flex-1 bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 text-xs">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Màu chữ</label>
                        <div class="flex items-center gap-2">
                            <input type="color" x-model="customOptions.textColor" @change="customDebouncePreview()"
                                   class="w-8 h-8 rounded cursor-pointer border-0">
                            <input type="text" x-model="customOptions.textColor" @change="customDebouncePreview()"
                                   class="flex-1 bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 text-xs">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kích thước -->
            <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Kích thước</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Kích thước QR</label>
                        <select x-model="customOptions.qrSize" @change="customDebouncePreview()"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm">
                            <template x-for="s in qrSizes" :key="s.value">
                                <option :value="s.value" x-text="s.label"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Cỡ chữ</label>
                        <select x-model="customOptions.fontSize" @change="customDebouncePreview()"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm">
                            <template x-for="s in fontSizes" :key="s.value">
                                <option :value="s.value" x-text="s.label"></option>
                            </template>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Thông tin hiển thị -->
            <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Thông tin hiển thị</h3>
                <div class="grid grid-cols-2 gap-2">
                    <template x-for="f in customFields" :key="f.key">
                        <label class="flex items-center gap-2 text-sm text-slate-700 bg-slate-50 rounded-xl px-3 py-2 cursor-pointer">
                            <input type="checkbox"
                                   :checked="customIsFieldSelected(f.key)"
                                   @change="customToggleField(f.key)"
                                   class="w-4 h-4 rounded"
                                   :disabled="f.key === 'code'">
                            <span x-text="f.label"></span>
                        </label>
                    </template>
                </div>
            </div>

            <!-- Tiêu đề -->
            <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Tiêu đề đoàn</h3>
                <input type="text" x-model="customOptions.headerText" @input="customDebouncePreview()"
                       placeholder="VD: Thiếu Nhi Thánh Thể — Phú Trung"
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm">
            </div>

            <!-- Logo -->
            <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Logo</h3>
                <label class="flex items-center gap-2 text-sm text-slate-700 mb-3 cursor-pointer">
                    <input type="checkbox" x-model="customOptions.showLogo" @change="customDebouncePreview()" class="w-4 h-4 rounded">
                    <span>Hiển thị logo</span>
                </label>
                <div x-show="customOptions.showLogo">
                    <!-- Logo đã upload -->
                    <div x-show="customLogos.length > 0" class="flex flex-wrap gap-2 mb-3">
                        <template x-for="logo in customLogos" :key="logo.id">
                            <button type="button" @click="selectCustomLogo(logo.url)"
                                    class="relative group"
                                    :class="customOptions.logoUrl === logo.url ? 'ring-2 ring-blue-500' : ''">
                                <img :src="logo.url" alt="Logo" class="w-12 h-12 object-contain rounded-lg border bg-white p-1">
                                <button type="button" @click.stop="deleteCustomLogo(logo.id)"
                                        class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white rounded-full text-xs opacity-0 group-hover:opacity-100 transition-opacity">×</button>
                            </button>
                        </template>
                    </div>
                    <!-- Upload logo mới -->
                    <label class="flex items-center justify-center gap-2 border-2 border-dashed border-slate-300 rounded-xl px-4 py-3 cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition-colors">
                        <i data-lucide="upload" class="w-4 h-4 text-slate-400"></i>
                        <span class="text-sm text-slate-500">Upload logo (JPG, PNG, SVG)</span>
                        <input type="file" accept="image/jpeg,image/png,image/svg+xml" class="hidden"
                               @change="uploadCustomLogo($event.target.files[0])">
                    </label>
                </div>
            </div>

            <!-- Presets -->
            <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Presets đã lưu</h3>
                <div x-show="customPresets.length > 0" class="flex flex-wrap gap-2 mb-3">
                    <template x-for="preset in customPresets" :key="preset.id">
                        <div class="flex items-center gap-1 bg-slate-100 rounded-lg px-2 py-1">
                            <button type="button" @click="applyCustomPreset(preset)"
                                    class="text-xs text-slate-700 hover:text-blue-600 font-medium" x-text="preset.name"></button>
                            <button type="button" @click="deleteCustomPreset(preset.id)"
                                    class="text-slate-400 hover:text-red-500 text-xs">×</button>
                        </div>
                    </template>
                </div>
                <div class="flex gap-2">
                    <input type="text" x-model="customNewPresetName" placeholder="Tên preset mới"
                           class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm"
                           @keydown.enter="saveCustomPreset()">
                    <button type="button" @click="saveCustomPreset()"
                            class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-bold hover:bg-blue-700 transition-colors">
                        Lưu
                    </button>
                </div>
            </div>
        </div>

        <!-- CỘT PHẢI: PREVIEW + XUẤT -->
        <div class="space-y-4">
            <!-- Preview -->
            <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Xem trước <span class="text-slate-400 normal-case font-medium">(tối đa 6 thẻ)</span></h3>
                    <span x-show="customPreviewLoading" class="text-xs text-blue-600">
                        <i data-lucide="loader-2" class="w-3 h-3 inline animate-spin"></i> Đang tạo...
                    </span>
                </div>
                <div class="bg-slate-50 rounded-2xl p-3 overflow-x-auto min-h-[300px]" x-html="customPreviewHtml"></div>
                <p class="text-micro text-slate-400 mt-2" x-show="customSelectedIds.length > 6">
                    Hiển thị 6/${customSelectedIds.length} thẻ
                </p>
            </div>

            <!-- Nút xuất -->
            <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Xuất thẻ</h3>
                <div class="grid grid-cols-3 gap-2">
                    <button @click="customExportPNG()" :disabled="!canCustomExport || customIsLoading"
                            class="flex flex-col items-center gap-1 px-4 py-3 bg-emerald-500 text-white rounded-xl hover:bg-emerald-600 transition-colors disabled:opacity-50">
                        <i data-lucide="image" class="w-5 h-5"></i>
                        <span class="text-xs font-bold">PNG</span>
                    </button>
                    <button @click="customExportPDF()" :disabled="!canCustomExport || customIsLoading"
                            class="flex flex-col items-center gap-1 px-4 py-3 bg-red-500 text-white rounded-xl hover:bg-red-600 transition-colors disabled:opacity-50">
                        <i data-lucide="file-text" class="w-5 h-5"></i>
                        <span class="text-xs font-bold">PDF</span>
                    </button>
                    <button @click="customPrint()" :disabled="!canCustomExport || customIsLoading"
                            class="flex flex-col items-center gap-1 px-4 py-3 bg-blue-500 text-white rounded-xl hover:bg-blue-600 transition-colors disabled:opacity-50">
                        <i data-lucide="printer" class="w-5 h-5"></i>
                        <span class="text-xs font-bold">In</span>
                    </button>
                </div>
                <p class="text-micro text-slate-400 mt-2 text-center" x-text="customSelectedIds.length + ' thẻ được chọn'"></p>
            </div>
        </div>
    </div>
</div>
