/* ==========================================================
   EXPORT — Xuất báo cáo PDF/Excel/CSV
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.export = {

    /**
     * Xuất phiếu liên lạc của một em
     * @param {number} termId - ID học kỳ
     * @param {number} studentId - ID học sinh
     * @param {string} format - 'pdf', 'excel', hoặc 'csv'
     */
    async report(termId, studentId, format = 'pdf') {
        const r = await this._api('export', 'report', { termId, studentId, format });
        if (r.ok && r.url) {
            this._download(r.url, r.filename);
        } else {
            window.TNTT.toast.error(r.error || 'Không thể xuất phiếu liên lạc.');
        }
    },

    /**
     * Xuất bảng điểm danh
     * @param {number|null} yearId - ID niên khoá (null = hiện tại)
     * @param {string} format - 'csv' hoặc 'excel'
     */
    async attendance(yearId, format = 'csv') {
        const r = await this._api('export', 'attendance', { yearId, format });
        if (r.ok && r.url) {
            this._download(r.url, r.filename);
        } else {
            window.TNTT.toast.error(r.error || 'Không thể xuất bảng điểm danh.');
        }
    },

    /**
     * Xuất bảng điểm số
     * @param {number} termId - ID học kỳ
     * @param {number|null} classId - ID lớp (null = tất cả)
     * @param {string} format - 'csv' hoặc 'excel'
     */
    async scores(termId, classId, format = 'csv') {
        const r = await this._api('export', 'scores', { termId, classId, format });
        if (r.ok && r.url) {
            this._download(r.url, r.filename);
        } else {
            window.TNTT.toast.error(r.error || 'Không thể xuất bảng điểm.');
        }
    },

    /**
     * Xuất báo cáo điểm danh chi tiết (mỗi dòng là 1 bản ghi)
     * @param {number|null} classId - ID lớp (null = tất cả)
     * @param {string} fromDate - Ngày bắt đầu (YYYY-MM-DD)
     * @param {string} toDate - Ngày kết thúc (YYYY-MM-DD)
     * @param {number|null} programId - ID chương trình (null = tất cả)
     */
    async attendanceDetail(classId = null, fromDate = '', toDate = '', programId = null) {
        const params = new URLSearchParams({
            action: 'attendance-detail'
        });
        if (classId) params.set('classId', classId);
        if (fromDate) params.set('fromDate', fromDate);
        if (toDate) params.set('toDate', toDate);
        if (programId) params.set('programId', programId);

        const resp = await fetch('/api/export.php?' + params.toString());
        const data = await resp.json();

        if (data.ok && data.url) {
            this._download(data.url, data.filename);
            return true;
        } else {
            window.TNTT.toast.error(data.error || 'Không thể xuất báo cáo điểm danh.');
            return false;
        }
    },

    /**
     * Xuất tất cả phiếu liên lạc của một lớp
     * @param {string} className - Tên lớp
     * @param {string} format - 'csv' hoặc 'excel'
     */
    async classReports(className, format = 'csv') {
        const students = window.TNTT.reports.reportStudents || [];
        if (students.length === 0) {
            window.TNTT.toast.warning('Không có phiếu nào để xuất.');
            return;
        }

        const termId = window.TNTT.reportTermId;
        let successCount = 0;
        let errorCount = 0;

        for (const student of students) {
            const r = await this._api('export', 'report', {
                termId,
                studentId: student.id,
                format
            });
            if (r.ok && r.url) {
                this._download(r.url, r.filename);
                successCount++;
                // Small delay to prevent overwhelming the browser
                await new Promise(resolve => setTimeout(resolve, 500));
            } else {
                errorCount++;
            }
        }

        if (errorCount > 0) {
            window.TNTT.toast.warning('Đã xuất ' + successCount + ' phiếu, ' + errorCount + ' phiếu thất bại.');
        }
    },

    /**
     * Gọi API chung
     * @private
     */
    async _api(file, action, body) {
        return window.TNTT.core.api(file, action, body);
    },

    /**
     * Tải file về từ data URL
     * @private
     */
    _download(dataUrl, filename) {
        // Create a temporary link to trigger download
        const link = document.createElement('a');
        link.href = dataUrl;
        link.download = filename || 'download';
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
};
