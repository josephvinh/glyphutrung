/* ==========================================================
   REPORTS — Sổ liên lạc
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.reports = {
    // ==========================================
    // 8b. DATA: SỔ LIÊN LẠC
    //
    // Phiếu là một BẢN CHỤP tại thời điểm lập, không phải khung nhìn
    // sống. Sửa điểm danh của tháng trước thì phiếu đã gửi phụ huynh
    // vẫn giữ nguyên con số đã in ra — đúng như sổ giấy.
    //
    // Ô điểm học lực hiện GLV nhập tay. Khi làm module Điểm số thì
    // chỉ việc đổ tự động vào đúng ô này, phần còn lại giữ nguyên.
    // ==========================================
    terms: [],   // máy chủ nạp qua loadData()

    conductOptions: ['tốt', 'khá', 'trung bình', 'cần cố gắng'],
    rankOptions: ['Giỏi', 'Khá', 'Trung bình', 'Yếu'],

    reports: [],
    reportIndex: null,   // Map 'studentId|termId' -> phiếu, dựng ở loadData (tra O(1))
    reportTermId: 1,
    reportClass: '',
    showReportForm: false,
    showReportPreview: false,
    reportForm: {},
    previewStudentId: null,
    selectedReports: [], // Chứa mảng ID các em được chọn

    toggleAllReports(e) {
        if (e.target.checked) {
            this.selectedReports = this.reportStudents.map(s => s.id);
        } else {
            this.selectedReports = [];
        }
    },

    openReports() {
        // Bắt chọn lớp trước khi hiện phiếu (giống Danh sách). Chỉ tự mở khi
        // người dùng vỏn vẹn MỘT lớp; nhiều lớp thì để trống, chọn lớp nào
        // xem lớp đó. Bộ chọn chỉ liệt kê lớp mình được phân công.
        this.reportClass = this.availableClasses.length === 1 ? this.availableClasses[0] : '';
        this.changeModule('reports');
    },

    // GLV phụ tá chỉ được xem, không lập phiếu
    get canWriteReports() {
        return this.canEditModule('reports');
    },

    get reportTerm() {
        return this.terms.find(t => t.id === Number(this.reportTermId)) || this.terms[0];
    },

    get reportStudents() {
        return this.accessibleStudents
            .filter(s => s.className === this.reportClass && s.status === 'đang sinh hoạt')
            .sort((a, b) => a.code.localeCompare(b.code));
    },

    // Chuyên cần cả lớp trong học kỳ, tính một lượt cho mọi em.
    // Vẫn áp quy tắc của Thống kê: buổi không ai điểm danh thì bỏ ra.
    get reportAttendance() {
        const term = this.reportTerm;
        const students = this.reportStudents;
        const blank = () => ({ present: 0, late: 0, excused: 0, unexcused: 0, total: 0 });
        const result = { byStudent: {}, counted: 0, untaken: 0 };
        students.forEach(s => { result.byStudent[s.id] = blank(); });
        if (!term || students.length === 0) return result;

        const idx = this.buildAttendanceIndex();
        // HƯỚNG B: Truyền className để tính mẫu số theo lịch riêng của lớp
        const sessions = this.sessionsBetween(term.from, term.to, this.reportClass).filter(s => s.countForAttendance);

        sessions.forEach(ss => {
            // HƯỚNG B: Dùng attKey hỗ trợ scheduleId
            const key = st => this.attKey({ programId: ss.programId, scheduleId: ss.scheduleId, date: ss.date, studentId: st.id });
            const taken = students.some(st => idx.att.has(key(st)) || idx.leave.has(key(st)));
            if (!taken) { result.untaken++; return; }
            result.counted++;

            students.forEach(st => {
                const k = key(st);
                const rec = idx.att.get(k);
                let bucket;
                if (rec) bucket = rec.status === 'đi trễ' ? 'late' : 'present';
                else if (idx.leave.has(k)) bucket = 'excused';
                else bucket = 'unexcused';
                result.byStudent[st.id][bucket]++;
                result.byStudent[st.id].total++;
            });
        });
        return result;
    },

    // Khoá + chỉ số phiếu O(1). Cùng mẫu với attIndex (điểm danh) và
    // scoreIndex (điểm): reportOf bị gọi cho TỪNG em ở nhiều đường render
    // nóng (myTasks trên thanh dưới/sidebar, danh sách Phiếu liên lạc) nên
    // find() tuyến tính làm chậm mọi thao tác. Tra Map thay cho quét mảng.
    reportKey(studentId, termId) { return studentId + '|' + Number(termId); },

    rebuildReportIndex() {
        const idx = new Map();
        for (const r of this.reports) idx.set(this.reportKey(r.studentId, r.termId), r);
        this.reportIndex = idx;
    },

    reportOf(studentId, termId) {
        const t = Number(termId || this.reportTermId);
        if (this.reportIndex) return this.reportIndex.get(this.reportKey(studentId, t)) || null;
        return this.reports.find(r => r.studentId === studentId && r.termId === t) || null;
    },

    reportStatus(studentId) {
        const r = this.reportOf(studentId);
        return r ? r.status : 'chưa lập';
    },

    reportChipClass(status) {
        if (status === 'đã gửi') return 'bg-emerald-50 text-emerald-600 border-emerald-100';
        if (status === 'nháp')   return 'bg-amber-50 text-amber-600 border-amber-100';
        return 'bg-slate-50 text-slate-400 border-slate-200';
    },

    // Gợi ý xếp loại: điểm học lực là chính, chuyên cần kém thì hạ một bậc
    suggestRank(score, rate) {
        let i;
        if (score === null || score === '' || isNaN(Number(score))) {
            if (rate >= 95) i = 0; else if (rate >= 85) i = 1; else if (rate >= 70) i = 2; else i = 3;
        } else {
            const s = Number(score);
            if (s >= 8) i = 0; else if (s >= 6.5) i = 1; else if (s >= 5) i = 2; else i = 3;
            if (rate < 75) i = Math.min(i + 1, 3);
        }
        return this.rankOptions[i];
    },

    refreshSuggestedRank() {
        const a = this.reportForm.attendance;
        this.reportForm.rank = this.suggestRank(this.reportForm.score, a ? a.rate : 0);
    },

    openReportForm(student) {
        const existing = this.reportOf(student.id);
        if (existing) {
            this.reportForm = JSON.parse(JSON.stringify(existing));
        } else {
            const t = this.reportAttendance.byStudent[student.id] || { present: 0, late: 0, excused: 0, unexcused: 0, total: 0 };
            const snapshot = Object.assign({}, t, { rate: this.attendRate(t) });
            // Điểm lấy thẳng từ bảng điểm học kỳ; chủ nhiệm vẫn sửa được
            const avg = this.termAverage(student.id, this.reportTermId);
            this.reportForm = {
                id: null,
                studentId: student.id,
                termId: Number(this.reportTermId),
                attendance: snapshot,
                score: avg === null ? '' : avg,
                conduct: 'tốt',
                rank: this.suggestRank(avg === null ? '' : avg, snapshot.rate),
                remark: '',
                status: 'nháp',
                createdBy: this.user.fullName,
                createdAt: ''
            };
        }
        this.showReportForm = true;
    },

    // Chốt lại bản chụp chuyên cần theo dữ liệu mới nhất
    recalcReportAttendance() {
        const t = this.reportAttendance.byStudent[this.reportForm.studentId];
        if (!t) return;
        this.reportForm.attendance = Object.assign({}, t, { rate: this.attendRate(t) });
        this.refreshSuggestedRank();
    },

    saveReport(send) {
        const f = this.reportForm;
        if (f.score !== '' && (isNaN(Number(f.score)) || Number(f.score) < 0 || Number(f.score) > 10)) {
            window.TNTT.toast.warning('Điểm học lực phải là số từ 0 đến 10, hoặc để trống nếu chưa có.');
            return;
        }
        if (send && !f.remark.trim()) {
            window.TNTT.toast.warning('Vui lòng ghi nhận xét trước khi gửi phiếu cho phụ huynh.');
            return;
        }
        f.remark = f.remark.trim();
        f.status = send ? 'đã gửi' : 'nháp';
        f.createdBy = this.user.fullName;
        f.createdAt = this.timestamp();

        const i = this.reports.findIndex(r => r.id !== null && r.id === f.id);
        if (i !== -1) {
            this.reports[i] = f;
        } else {
            f.id = Date.now();
            this.reports.push(f);
        }
        if (this.reportIndex) this.reportIndex.set(this.reportKey(f.studentId, f.termId), f);
        this.save('reports', 'save', {
            studentId: f.studentId, termId: f.termId, attendance: f.attendance,
            score: String(f.score), conduct: f.conduct, rank: f.rank,
            remark: f.remark, send: !!send
        }).then(r => { if (!r || !r.ok) this.loadData(); });
        
        const em = this.studentById(f.studentId);
        this.logAction(send ? 'duyet' : 'sua', 'reports',
                       (send ? 'Gửi' : 'Lưu nháp') + ' phiếu liên lạc của ' + (em ? em.name : ''),
                       this.reportTerm.name + ' · xếp loại ' + f.rank);
        this.showReportForm = false;
    },

    async deleteReport(studentId) {
        const r = this.reportOf(studentId);
        if (!r) return;
        if (await window.TNTT.toast.confirm('Xóa phiếu liên lạc của em này?', { danger: true, confirmText: 'Xoá' })) {
            this.reports = this.reports.filter(x => x.id !== r.id);
            if (this.reportIndex) this.reportIndex.delete(this.reportKey(r.studentId, r.termId));
            this.showReportForm = false;
            this.save('reports', 'delete', { studentId: r.studentId, termId: r.termId })
                .then(res => { if (!res || !res.ok) this.loadData(); });
        }
    },

    openReportPreview(studentId) {
        this.previewStudentId = studentId;
        this.showReportPreview = true;
    },

    get previewReport() {
        return this.previewStudentId ? this.reportOf(this.previewStudentId) : null;
    },

    get previewStudent() {
        return this.previewStudentId ? this.studentById(this.previewStudentId) : null;
    },

    // Đổi tên thành printClassReport để tránh trùng với student_profile.printReport (#121)
    printClassReport() {
        if (!this.previewStudentId) return;
        window.open('print.php?type=report&termId=' + this.reportTermId + '&studentId=' + this.previewStudentId, '_blank');
    },

    printClassReports() {
        if (this.reportClass === '') {
            window.TNTT.toast.warning('Vui lòng chọn lớp trước.');
            return;
        }
        let url = 'print.php?type=class_reports&termId=' + this.reportTermId + '&className=' + encodeURIComponent(this.reportClass);
        if (this.selectedReports.length > 0) {
            url += '&ids=' + this.selectedReports.join(',');
        }
        window.open(url, '_blank');
    },

    // Số phiếu liên lạc còn thiếu — cho chấm nhắc trên icon Thiếu Nhi.
    //
    // Sổ liên lạc KHÔNG phải làm ngay: chỉ nhắc trong 1 THÁNG CUỐI trước ngày
    // kết thúc học kỳ (tính đến hết ngày cuối kỳ). Ngoài khoảng đó -> 0, khỏi
    // treo chấm đỏ suốt kỳ gây phiền.
    get reportTodoCount() {
        if (!this.canWriteReports || this.isUnderMaintenance('reports')) return 0;
        const today = this.toDateInput(new Date());
        const term = this.terms.find(t => {
            if (!t.to || today > t.to) return false;         // đã qua ngày cuối kỳ
            const d = new Date(t.to + 'T00:00:00');
            d.setMonth(d.getMonth() - 1);                    // lùi đúng 1 tháng
            return today >= this.toDateInput(d);             // đã vào tháng cuối chưa
        });
        if (!term) return 0;
        const scope = this.accessibleStudents.filter(s => s.status === 'đang sinh hoạt');
        return scope.filter(s => !this.reportOf(s.id, term.id)).length;
    },

    get reportProgress() {
        const total = this.reportStudents.length;
        const sent = this.reportStudents.filter(s => this.reportStatus(s.id) === 'đã gửi').length;
        const draft = this.reportStudents.filter(s => this.reportStatus(s.id) === 'nháp').length;
        return { total: total, sent: sent, draft: draft, missing: total - sent - draft };
    }
};
