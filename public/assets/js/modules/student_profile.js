/* ==========================================================
   STUDENT_PROFILE — Hồ sơ tổng hợp từng em
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.student_profile = {
    // ==========================================
    // STATE - phải khai báo kiểu này để Alpine truy cập được
    // ==========================================
    get profileStudent() { return this._profileStudent; },
    set profileStudent(v) { this._profileStudent = v; },
    _profileStudent: null,

    get profileTab() { return this._profileTab; },
    set profileTab(v) { this._profileTab = v; },
    _profileTab: 'info', // info | scores | report | attendance | qrcard

    // ==========================================
    // METHODS
    // ==========================================

    /**
     * Mở hồ sơ tổng hợp của một em
     * Called from students.js when user clicks on a student
     */
    openStudentProfile(student) {
        this.profileStudent = student;
        this.profileTab = 'info';
        this.changeModule('student_profile');
        window.scrollTo({ top: 0, behavior: 'instant' });
        // Nạp sẵn bộ sinh mã QR để tab "QR Card" hiển thị được ngay cả khi
        // người dùng chưa từng mở module In thẻ QR. qrReady là cờ dùng chung.
        this.ensureQrLib();
    },

    /** Bảo đảm thư viện qrcode đã nạp; trả về Promise, đặt cờ qrReady. */
    ensureQrLib() {
        if (window.qrcode) { this.qrReady = true; return Promise.resolve(); }
        return this._qrTaiBoSinh()
            .then(() => { this.qrReady = true; })
            .catch(() => { this.qrReady = false; window.TNTT.toast.error('Không tải được bộ sinh mã QR.'); });
    },

    /**
     * Tính số buổi vắng không phép của một em
     */
    getUnexcusedAbsences(studentId) {
        if (!this.attendances || !this.leaves || !this.programs) return 0;

        // Lấy danh sách các buổi đã điểm danh của em (index O(1) theo em)
        const attendedDates = new Set(
            this.attOfStudent(studentId).map(a => a.date)
        );

        // Lấy các ngày có phép
        const excusedDates = new Set(
            this.leaves
                .filter(l => l.studentId === studentId && l.status === 'approved')
                .map(l => l.fromDate)
        );

        // Đếm các buổi đã kết thúc mà em không có mặt và không có phép
        let unexcused = 0;
        const now = new Date();

        this.programs.forEach(p => {
            if (p.status !== 'kích hoạt') return;

            // Với chương trình thường, kiểm tra các Chúa Nhật đã qua
            if (p.type !== 'chiến dịch') {
                // Lấy ngày Chúa Nhật gần nhất hoặc trước đó
                let date = new Date(now);
                date.setDate(date.getDate() - date.getDay()); // Chúa Nhật gần nhất

                // Kiểm tra tối đa 26 Chúa Nhật (1 năm)
                for (let i = 0; i < 26; i++) {
                    const dateStr = date.toISOString().split('T')[0];
                    const cutoff = new Date(date);
                    cutoff.setHours(23, 59, 59);

                    if (cutoff < now) {
                        if (!attendedDates.has(dateStr) && !excusedDates.has(dateStr)) {
                            unexcused++;
                        }
                    }
                    date.setDate(date.getDate() - 7);
                }
            }
        });

        return unexcused;
    },

    /**
     * Tính tỷ lệ điểm danh
     */
    getAttendanceRate(studentId) {
        if (!this.attendances || !this.programs) return 0;

        const mine = this.attOfStudent(studentId);
        const total = mine.length;
        const present = mine.filter(a => a.status === 'có mặt').length;
        const late = mine.filter(a => a.status === 'đi trễ').length;

        // Ước tính tổng số buổi dựa trên các buổi đã điểm danh + vắng
        const attended = present + late;
        const unexcused = this.getUnexcusedAbsences(studentId);
        const totalEstimate = attended + unexcused;

        if (totalEstimate === 0) return 0;
        return Math.round((attended / totalEstimate) * 100);
    },

    /**
     * Tổng hợp Sổ Mộc (ví + chuỗi + lịch sử gần nhất) của em đang xem hồ sơ.
     * Trả về hình dạng rỗng an toàn khi chưa nạp xong dữ liệu, để giao diện
     * không phải tự kiểm tra null ở nhiều chỗ.
     */
    get profileStampSummary() {
        const empty = { current_balance: 0, held_balance: 0, total_earned: 0, current_streak: 0, longest_streak: 0, recent_transactions: [] };
        if (!this.profileStudent || !this.stampSummaries) return empty;
        return this.stampSummaries[this.profileStudent.id] || empty;
    },

    /**
     * In phiếu liên lạc
     */
    printReport(report) {
        if (!report || !this.profileStudent) return;
        window.printReportSingle(report, this.profileStudent);
    },

    /**
     * In thẻ QR của một em
     */
    async printSingleQrcard(student) {
        if (!student || !student.code) return;
        // Thư viện phải sẵn sàng trước khi dựng thẻ, nếu không thẻ in ra
        // sẽ trống mã QR (hoặc lỗi khi vẽ).
        if (!window.qrcode) {
            await this.ensureQrLib();
            if (!window.qrcode) return; // ensureQrLib đã báo lỗi
        }
        window.printSingleQrcard(student);
    },
};
