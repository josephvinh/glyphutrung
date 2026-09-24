/* ==========================================================
   CUSTOM QR CARD MODULE

   Module cho phép tạo và xuất thẻ QR tùy chỉnh với nhiều
   template, màu sắc và tùy chọn khác nhau.

   Dependencies:
   - qrcode.min.js (đã có)
   - html2canvas (CDN) cho PNG export
   - jsPDF (CDN) cho PDF export
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.customQrcard = {

    // --- State ---
    isLoading: false,
    previewLoading: false,

    // Student selection
    selectedStudentIds: [],

    // Scope
    scopeType: 'class',       // 'class' | 'block' | 'all'
    scopeValue: '',

    // Options
    options: {
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

    // Templates
    templates: [
        { code: 'basic', name: 'Basic', desc: 'QR + thông tin cơ bản' },
        { code: 'classic', name: 'Classic', desc: 'Viền trang trí, logo đoàn' },
        { code: 'badge', name: 'Thẻ đeo', desc: 'Hình tròn, có lỗ treo' },
        { code: 'compact', name: 'Gọn nhẹ', desc: 'Nhỏ gọn, in cắt dán' },
        { code: 'minimal', name: 'Tối giản', desc: 'Tối giản, chỉ QR + tên' }
    ],

    // Available fields
    availableFields: [
        { key: 'code', label: 'Mã số', required: true },
        { key: 'holyName', label: 'Tên thánh' },
        { key: 'name', label: 'Họ tên', required: true },
        { key: 'className', label: 'Lớp' },
        { key: 'block', label: 'Khối' },
        { key: 'birthDate', label: 'Ngày sinh' }
    ],

    // QR Sizes
    qrSizes: [
        { value: '20mm', label: '20mm' },
        { value: '25mm', label: '25mm (phổ biến)' },
        { value: '30mm', label: '30mm' },
        { value: '35mm', label: '35mm' }
    ],

    // Font sizes
    fontSizes: [
        { value: 'small', label: 'Nhỏ' },
        { value: 'medium', label: 'Trung bình' },
        { value: 'large', label: 'Lớn' }
    ],

    // Logo positions
    logoPositions: [
        { value: 'top', label: 'Trên QR' },
        { value: 'bottom', label: 'Dưới QR' },
        { value: 'center', label: 'Trong QR' }
    ],

    // Presets
    presets: [],
    selectedPresetId: null,
    newPresetName: '',

    // Preview
    previewHtml: '',

    // Uploaded logos
    logos: [],

    /* ================================================================
       COMPUTED
       ================================================================ */

    get availableStudents() {
        let list = (window.TNTT.accessibleStudents || []).filter(s => s.status === 'đang sinh hoạt');
        if (this.scopeType === 'class' && this.scopeValue) {
            list = list.filter(s => s.className === this.scopeValue);
        } else if (this.scopeType === 'block' && this.scopeValue) {
            list = list.filter(s => s.block === this.scopeValue);
        }
        return list;
    },

    get previewStudents() {
        return this.selectedStudentIds
            .map(id => this.availableStudents.find(s => s.id === id))
            .filter(s => s)
            .slice(0, 6);
    },

    get printStudents() {
        return this.selectedStudentIds
            .map(id => this.availableStudents.find(s => s.id === id))
            .filter(s => s);
    },

    get canExport() {
        return this.selectedStudentIds.length > 0 && !this.isLoading;
    },

    get hasLogo() {
        return this.options.showLogo && this.options.logoUrl;
    },

    /* ================================================================
       METHODS
       ================================================================ */

    /**
     * Initialize module
     */
    init() {
        this.resetOptions();
        this.loadPresets();
        this.loadLogos();
        this._loadLibraries();
    },

    /**
     * Reset options to default
     */
    resetOptions() {
        this.options = {
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
        };
        this.previewHtml = '';
    },

    /**
     * Scope change handler
     */
    onScopeTypeChange() {
        if (this.scopeType === 'class') {
            this.scopeValue = (window.TNTT.availableClasses || [])[0] || '';
        } else if (this.scopeType === 'block') {
            this.scopeValue = (window.TNTT.availableBlocks || [])[0] || '';
        } else {
            this.scopeValue = '';
        }
        this.syncSelectedToScope();
    },

    /**
     * Sync selected students when scope changes
     */
    syncSelectedToScope() {
        const students = this.availableStudents;
        this.selectedStudentIds = students.map(s => s.id);
    },

    /**
     * Toggle field selection
     */
    toggleField(key) {
        const idx = this.options.fields.indexOf(key);
        if (idx >= 0) {
            this.options.fields.splice(idx, 1);
        } else {
            this.options.fields.push(key);
        }
        // Always include code if not present
        if (!this.options.fields.includes('code')) {
            this.options.fields.push('code');
        }
    },

    /**
     * Check if field is selected
     */
    isFieldSelected(key) {
        return this.options.fields.includes(key);
    },

    /**
     * Update option
     */
    updateOption(key, value) {
        this.options[key] = value;
        this.debouncePreview();
    },

    /**
     * Debounced preview generation
     */
    debouncePreview() {
        if (this._previewTimeout) {
            clearTimeout(this._previewTimeout);
        }
        this._previewTimeout = setTimeout(() => {
            this.generatePreview();
        }, 300);
    },

    /**
     * Generate preview from backend
     */
    async generatePreview() {
        if (!this.selectedStudentIds.length) {
            this.previewHtml = '<p style="color:#64748b;font-size:13px;margin:0">Chọn học sinh để xem trước.</p>';
            return;
        }

        this.previewLoading = true;

        try {
            const response = await fetch('/api/custom-qrcard.php?action=preview', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.TNTT?.csrfToken || ''
                },
                body: JSON.stringify({
                    student_ids: this.selectedStudentIds.slice(0, 6),
                    options: this.options
                })
            });

            const data = await response.json();

            if (data.ok) {
                this.previewHtml = data.html;
                // Render QR codes after HTML is inserted
                this.$nextTick(() => {
                    this._renderQRCodes();
                });
            } else {
                this.previewHtml = '<p style="color:#ef4444;font-size:13px;margin:0">' + (data.error || 'Lỗi khi tạo preview.') + '</p>';
            }
        } catch (err) {
            console.error('Preview error:', err);
            this.previewHtml = '<p style="color:#ef4444;font-size:13px;margin:0">Không thể tạo preview. Kiểm tra kết nối.</p>';
        } finally {
            this.previewLoading = false;
        }
    },

    /**
     * Render QR codes in preview
     */
    _renderQRCodes() {
        const containers = document.querySelectorAll('.qr-wrapper[data-code]');
        containers.forEach(container => {
            const code = container.dataset.code;
            const color = container.dataset.color || '#000000';
            const qr = window.qrcode(0, 'M');
            qr.addData(code);
            qr.make();
            const moduleCount = qr.getModuleCount();
            const size = Math.min(container.offsetWidth || 100, container.offsetHeight || 100);
            const cellSize = Math.floor(size / moduleCount);
            const actualSize = moduleCount * cellSize;

            container.innerHTML = `<svg width="${actualSize}" height="${actualSize}" viewBox="0 0 ${actualSize} ${actualSize}">
                <rect width="${actualSize}" height="${actualSize}" fill="white"/>
                ${this._generateQRSvgPath(qr, moduleCount, cellSize, color)}
            </svg>`;
        });
    },

    /**
     * Generate SVG path for QR code
     */
    _generateQRSvgPath(qr, moduleCount, cellSize, color) {
        let svg = '';
        for (let r = 0; r < moduleCount; r++) {
            for (let c = 0; c < moduleCount; c++) {
                if (qr.isDark(r, c)) {
                    svg += `<rect x="${c * cellSize}" y="${r * cellSize}" width="${cellSize}" height="${cellSize}" fill="${color}"/>`;
                }
            }
        }
        return svg;
    },

    /* ================================================================
       EXPORT METHODS
       ================================================================ */

    /**
     * Export as PNG
     */
    async exportPNG() {
        if (!this.canExport) {
            window.TNTT?.toast?.warning('Chọn học sinh trước khi xuất.');
            return;
        }

        this.isLoading = true;

        try {
            // Generate HTML
            const response = await fetch('/api/custom-qrcard.php?action=export_png', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.TNTT?.csrfToken || ''
                },
                body: JSON.stringify({
                    student_ids: this.selectedStudentIds,
                    options: this.options
                })
            });

            const data = await response.json();

            if (!data.ok) {
                throw new Error(data.error || 'Lỗi khi xuất PNG');
            }

            // Create a hidden container for rendering
            const container = document.createElement('div');
            container.style.cssText = 'position:fixed;left:-9999px;top:-9999px;width:210mm;background:white;padding:10mm';
            container.innerHTML = data.html;
            document.body.appendChild(container);

            // Wait for fonts to load
            await document.fonts.ready;

            // Use html2canvas if available, otherwise fallback
            if (window.html2canvas) {
                const canvas = await html2canvas(container, {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: '#ffffff'
                });

                // Download
                const link = document.createElement('a');
                link.download = `qrcards_${Date.now()}.png`;
                link.href = canvas.toDataURL('image/png');
                link.click();
            } else {
                // Fallback: use print dialog
                this.print();
            }

            // Cleanup
            document.body.removeChild(container);

            window.TNTT?.toast?.success('Đã xuất ' + data.count + ' thẻ QR.');
            this.logAction('xuat', 'qrcard', 'Xuất PNG QR Card', data.count + ' thẻ');

        } catch (err) {
            console.error('Export PNG error:', err);
            window.TNTT?.toast?.error('Không thể xuất PNG: ' + err.message);
        } finally {
            this.isLoading = false;
        }
    },

    /**
     * Export as PDF
     */
    async exportPDF() {
        if (!this.canExport) {
            window.TNTT?.toast?.warning('Chọn học sinh trước khi xuất.');
            return;
        }

        this.isLoading = true;

        try {
            // Generate HTML
            const response = await fetch('/api/custom-qrcard.php?action=export_pdf', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.TNTT?.csrfToken || ''
                },
                body: JSON.stringify({
                    student_ids: this.selectedStudentIds,
                    options: this.options
                })
            });

            const data = await response.json();

            if (!data.ok) {
                throw new Error(data.error || 'Lỗi khi xuất PDF');
            }

            // Create container for rendering
            const container = document.createElement('div');
            container.style.cssText = 'position:fixed;left:-9999px;top:-9999px;width:210mm;background:white;padding:10mm';
            container.innerHTML = data.html;
            document.body.appendChild(container);

            await document.fonts.ready;

            if (window.html2canvas && window.jspdf) {
                const canvas = await html2canvas(container, {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: '#ffffff'
                });

                const imgData = canvas.toDataURL('image/png');
                const pdf = new jspdf.jsPDF({
                    orientation: 'portrait',
                    unit: 'mm',
                    format: 'a4'
                });

                const pdfWidth = pdf.internal.pageSize.getWidth();
                const pdfHeight = pdf.internal.pageSize.getHeight();

                pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);

                pdf.save(`qrcards_${Date.now()}.pdf`);
            } else {
                this.print();
            }

            document.body.removeChild(container);

            window.TNTT?.toast?.success('Đã xuất ' + data.count + ' thẻ QR.');
            this.logAction('xuat', 'qrcard', 'Xuất PDF QR Card', data.count + ' thẻ');

        } catch (err) {
            console.error('Export PDF error:', err);
            window.TNTT?.toast?.error('Không thể xuất PDF: ' + err.message);
        } finally {
            this.isLoading = false;
        }
    },

    /**
     * Print directly
     */
    print() {
        if (!this.canExport) {
            window.TNTT?.toast?.warning('Chọn học sinh trước khi in.');
            return;
        }

        const students = this.printStudents;

        // Generate print HTML
        const printHtml = this._generatePrintHtml(students);

        const printWin = window.open('', '_blank');
        if (!printWin) {
            window.TNTT?.toast?.error('Trình duyệt đã chặn cửa sổ in. Hãy cho phép popup.');
            return;
        }

        printWin.document.write(printHtml);
        printWin.document.close();

        printWin.onload = () => {
            printWin.print();
        };

        this.logAction('in', 'qrcard', 'In thẻ QR tùy chỉnh', students.length + ' em');
    },

    /**
     * Generate HTML for printing
     */
    _generatePrintHtml(students) {
        const cardsHtml = this._generateCardsHtml(students);
        const ngay = new Date().toLocaleDateString('vi-VN');

        return `<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thẻ QR Tùy Chỉnh</title>
    <style>
        @page { size: A4; margin: 10mm; }
        @media print { .no-print { display: none !important; } }
        body { font-family: "Be Vietnam Pro", system-ui, sans-serif; margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 10px; }
        .header h1 { font-size: 14px; margin: 0 0 4px; color: #1e3a8a; }
        .header p { font-size: 10px; color: #64748b; margin: 0; }
        .luoi { display: flex; flex-wrap: wrap; gap: 5mm; }
        .qrcard { break-inside: avoid; page-break-inside: avoid; }
    </style>
</head>
<body>
    <div class="header">
        <h1>${this._escapeHtml(this.options.headerText || 'Thiếu Nhi Thánh Thể — Phú Trung')}</h1>
        <p>${students.length} em · In ngày ${ngay}</p>
    </div>
    <div class="luoi">${cardsHtml}</div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Render QR codes
            document.querySelectorAll('.qr-wrapper[data-code]').forEach(function(container) {
                const code = container.dataset.code;
                const color = container.dataset.color || '#000000';
                if (window.qrcode) {
                    const qr = window.qrcode(0, 'M');
                    qr.addData(code);
                    qr.make();
                    const moduleCount = qr.getModuleCount();
                    const size = Math.min(container.offsetWidth || 80, container.offsetHeight || 80);
                    const cellSize = Math.floor(size / moduleCount);
                    const actualSize = moduleCount * cellSize;

                    let svg = '<svg width="' + actualSize + '" height="' + actualSize + '" viewBox="0 0 ' + actualSize + ' ' + actualSize + '">';
                    svg += '<rect width="' + actualSize + '" height="' + actualSize + '" fill="white"/>';
                    for (let r = 0; r < moduleCount; r++) {
                        for (let c = 0; c < moduleCount; c++) {
                            if (qr.isDark(r, c)) {
                                svg += '<rect x="' + (c * cellSize) + '" y="' + (r * cellSize) + '" width="' + cellSize + '" height="' + cellSize + '" fill="' + color + '"/>';
                            }
                        }
                    }
                    svg += '</svg>';
                    container.innerHTML = svg;
                }
            });

            // Auto print after QR codes are rendered
            setTimeout(function() { window.print(); }, 500);
        });
    </script>
    <script src="/assets/js/vendor/qrcode.min.js"></script>
</body>
</html>`;
    },

    /**
     * Generate cards HTML for JS
     */
    _generateCardsHtml(students) {
        const template = this.options.template || 'basic';
        const qrColor = this.options.qrColor || '#000000';
        const bgColor = this.options.bgColor || '#ffffff';
        const textColor = this.options.textColor || '#1e293b';
        const qrSize = this.options.qrSize || '25mm';
        const fields = this.options.fields || ['code', 'name'];

        let html = '';

        students.forEach(s => {
            html += '<div class="qrcard" style="background:' + bgColor + ';border:1px solid #e2e8f0;border-radius:4mm;padding:3mm;display:flex;flex-direction:column;align-items:center;text-align:center;max-width:55mm">';

            if (this.options.headerText) {
                html += '<div style="font-size:8px;font-weight:800;color:' + textColor + ';text-transform:uppercase;letter-spacing:.3px;margin-bottom:2mm;width:100%">' + this._escapeHtml(this.options.headerText) + '</div>';
            }

            html += '<div class="qr-wrapper" data-code="' + this._escapeHtml(s.code) + '" data-color="' + this._escapeHtml(qrColor) + '" style="display:flex;justify-content:center;align-items:center;margin-bottom:2mm"></div>';
            html += '<div style="color:' + textColor + '">';

            fields.forEach(f => {
                switch (f) {
                    case 'code':
                        html += '<p style="font-size:10px;font-weight:800;letter-spacing:.3px;margin:0 0 1px;color:#2563eb">' + this._escapeHtml(s.code) + '</p>';
                        break;
                    case 'name':
                        const hn = s.holyName ? s.holyName + ' ' : '';
                        html += '<p style="font-size:12px;font-weight:700;margin:0 0 1px;line-height:1.25">' + this._escapeHtml(hn + s.name) + '</p>';
                        break;
                    case 'className':
                        if (s.className) {
                            html += '<p style="font-size:9px;color:#64748b;margin:0">Lớp: ' + this._escapeHtml(s.className) + '</p>';
                        }
                        break;
                    case 'block':
                        if (s.block) {
                            html += '<p style="font-size:9px;color:#64748b;margin:0">' + this._escapeHtml(s.block) + '</p>';
                        }
                        break;
                    case 'birthDate':
                        if (s.birthDate) {
                            html += '<p style="font-size:9px;color:#64748b;margin:0">Sinh: ' + this._escapeHtml(s.birthDate) + '</p>';
                        }
                        break;
                }
            });

            html += '</div></div>';
        });

        return html;
    },

    /* ================================================================
       PRESETS
       ================================================================ */

    /**
     * Load presets from server
     */
    async loadPresets() {
        try {
            const response = await fetch('/api/custom-qrcard.php?action=list_presets', {
                headers: { 'X-CSRF-Token': window.TNTT?.csrfToken || '' }
            });
            const data = await response.json();
            if (data.ok) {
                this.presets = data.presets || [];
            }
        } catch (err) {
            console.error('Load presets error:', err);
        }
    },

    /**
     * Save current options as preset
     */
    async savePreset() {
        if (!this.newPresetName.trim()) {
            window.TNTT?.toast?.warning('Vui lòng nhập tên preset.');
            return;
        }

        try {
            const response = await fetch('/api/custom-qrcard.php?action=save_preset', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.TNTT?.csrfToken || ''
                },
                body: JSON.stringify({
                    name: this.newPresetName.trim(),
                    options: this.options,
                    is_default: false
                })
            });

            const data = await response.json();

            if (data.ok) {
                window.TNTT?.toast?.success(data.message);
                this.newPresetName = '';
                this.loadPresets();
            } else {
                window.TNTT?.toast?.error(data.error || 'Không thể lưu preset.');
            }
        } catch (err) {
            console.error('Save preset error:', err);
            window.TNTT?.toast?.error('Lỗi khi lưu preset.');
        }
    },

    /**
     * Apply preset
     */
    applyPreset(preset) {
        if (!preset || !preset.options) return;

        this.selectedPresetId = preset.id;
        this.options = { ...this.options, ...preset.options };

        // Trigger preview update
        this.debouncePreview();

        window.TNTT?.toast?.info('Đã áp dụng preset: ' + preset.name);
    },

    /**
     * Delete preset
     */
    async deletePreset(presetId) {
        if (!confirm('Xóa preset này?')) return;

        try {
            const response = await fetch('/api/custom-qrcard.php?action=delete_preset', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.TNTT?.csrfToken || ''
                },
                body: JSON.stringify({ id: presetId })
            });

            const data = await response.json();

            if (data.ok) {
                window.TNTT?.toast?.success('Đã xóa preset.');
                this.presets = this.presets.filter(p => p.id !== presetId);
                if (this.selectedPresetId === presetId) {
                    this.selectedPresetId = null;
                }
            } else {
                window.TNTT?.toast?.error(data.error || 'Không thể xóa preset.');
            }
        } catch (err) {
            console.error('Delete preset error:', err);
            window.TNTT?.toast?.error('Lỗi khi xóa preset.');
        }
    },

    /* ================================================================
       LOGOS
       ================================================================ */

    /**
     * Load logos from server
     */
    async loadLogos() {
        try {
            const response = await fetch('/api/custom-qrcard.php?action=list_logos', {
                headers: { 'X-CSRF-Token': window.TNTT?.csrfToken || '' }
            });
            const data = await response.json();
            if (data.ok) {
                this.logos = data.logos || [];
            }
        } catch (err) {
            console.error('Load logos error:', err);
        }
    },

    /**
     * Upload logo
     */
    async uploadLogo(file) {
        if (!file) return;

        // Validate
        const allowedTypes = ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            window.TNTT?.toast?.error('Chỉ chấp nhận file JPG, PNG, SVG, WebP.');
            return;
        }

        if (file.size > 2 * 1024 * 1024) {
            window.TNTT?.toast?.error('File logo không được quá 2MB.');
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
                this.logos.unshift({
                    id: data.logo_id,
                    url: data.url,
                    original_name: data.original_name
                });
                this.options.logoUrl = data.url;
                this.options.showLogo = true;
                this.debouncePreview();
            } else {
                window.TNTT?.toast?.error(data.error || 'Không thể upload logo.');
            }
        } catch (err) {
            console.error('Upload logo error:', err);
            window.TNTT?.toast?.error('Lỗi khi upload logo.');
        }
    },

    /**
     * Select logo
     */
    selectLogo(url) {
        this.options.logoUrl = url;
        this.options.showLogo = true;
        this.debouncePreview();
    },

    /**
     * Remove logo
     */
    removeLogo() {
        this.options.logoUrl = '';
        this.options.showLogo = false;
        this.debouncePreview();
    },

    /**
     * Delete logo from server
     */
    async deleteLogo(logoId) {
        if (!confirm('Xóa logo này?')) return;

        try {
            const response = await fetch('/api/custom-qrcard.php?action=delete_logo', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.TNTT?.csrfToken || ''
                },
                body: JSON.stringify({ id: logoId })
            });

            const data = await response.json();

            if (data.ok) {
                window.TNTT?.toast?.success('Đã xóa logo.');
                this.logos = this.logos.filter(l => l.id !== logoId);
                if (this.options.logoUrl && this.options.logoUrl.includes(logoId)) {
                    this.removeLogo();
                }
            } else {
                window.TNTT?.toast?.error(data.error || 'Không thể xóa logo.');
            }
        } catch (err) {
            console.error('Delete logo error:', err);
            window.TNTT?.toast?.error('Lỗi khi xóa logo.');
        }
    },

    /* ================================================================
       LIBRARIES
       ================================================================ */

    /**
     * Load external libraries (html2canvas, jsPDF)
     */
    _loadLibraries() {
        // Load html2canvas if not available
        if (!window.html2canvas) {
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
            script.integrity = 'sha512-j/MIL27KM5YLNMog7nXKvLQBcQk5nCAyZcTB5N0+JTLFNHnP8EPJk03L';
            script.crossOrigin = 'anonymous';
            document.head.appendChild(script);
        }

        // Load jsPDF if not available
        if (!window.jspdf) {
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
            script.integrity = 'sha512-qJ8tFLYHPhH7E+JBJFLevzY7k3qVчNBNP8UW8VJ3V5L';
            script.crossOrigin = 'anonymous';
            document.head.appendChild(script);
        }
    },

    /* ================================================================
       UTILITIES
       ================================================================ */

    /**
     * Escape HTML
     */
    _escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    },

    /**
     * Log action
     */
    logAction(action, module, what, detail) {
        if (window.TNTT?.logAction) {
            window.TNTT.logAction(action, module, what, detail);
        }
    },

    /**
     * Open custom QR card tab
     */
    openCustomQrcard() {
        if (window.TNTT) {
            window.TNTT.changeModule('qrcard');
        }
        this.init();
    }
};
