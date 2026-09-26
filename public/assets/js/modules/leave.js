/* ==========================================================
   LEAVE — Xin phép
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.leave = {
    // ==========================================
    // 5. DATA: XIN PHÉP
    //
    // Đơn phép không ghi đè dữ liệu điểm danh. Nó chỉ là một bản ghi
    // riêng; trạng thái "vắng có phép" được statusInSession() suy ra khi
    // thấy có đơn đã duyệt. Nhờ vậy duyệt/hủy duyệt đều không phải đụng
    // vào bảng điểm danh.
    // ==========================================
    leaveRequests: [],   // máy chủ nạp qua loadData()

    leaveTab: 'create',          // 'create' | 'approve'
    leaveDate: '',
    leaveProgramId: '',
    leaveSearch: '',
    leaveFilter: 'chờ duyệt',
    showLeaveModal: false,
    leaveForm: { studentId: null, reason: '' },
    showRejectModal: false,
    rejectForm: { requestId: null, reason: '' },

    openLeave() {
        if (!this.leaveDate) this.leaveDate = this.toDateInput(new Date());
        this.leaveSearch = '';
        this.syncLeaveProgram();
        this.changeModule('leave');
    },

    // Đổi ngày thì chương trình đang chọn có thể không còn diễn ra nữa
    syncLeaveProgram() {
        const list = this.programsOn(this.leaveDate);
        if (!list.some(p => p.id === Number(this.leaveProgramId))) {
            this.leaveProgramId = list.length ? list[0].id : '';
        }
    },

    get leaveProgramsOnDate() {
        return this.programsOn(this.leaveDate);
    },

    get leaveSession() {
        if (!this.leaveProgramId || !this.leaveDate) return null;
        return { programId: Number(this.leaveProgramId), date: this.leaveDate };
    },

    get leaveProgram() {
        if (!this.leaveSession) return null;
        return this.programs.find(p => p.id === this.leaveSession.programId) || null;
    },

    get leaveCutoff() {
        return this.cutoffOf(this.leaveProgram);
    },

    get isLeavePastCutoff() {
        return this.isPastCutoffFor(this.leaveSession);
    },

    // Hết ngày diễn ra là khóa sổ, không cho xin bù tuần sau nữa,
    // nếu không điểm chuyên cần cả năm sẽ không bao giờ chốt được.
    get isLeaveExpired() {
        if (!this.leaveDate) return false;
        return this.leaveDate < this.toDateInput(new Date());
    },

    // Luồng 1 (sớm): chọn được mọi em trong quyền.
    // Luồng 2 (trễ): chỉ còn các em đang bị đánh vắng không phép.
    get leaveEligibleStudents() {
        if (!this.leaveSession || this.isLeaveExpired) return [];
        const q = (this.leaveSearch || '').trim();
        return this.accessibleStudents
            .filter(s => s.status === 'đang sinh hoạt')
            .filter(s => !this.isLeavePastCutoff || this.statusInSession(s.id, this.leaveSession) === 'vắng không phép')
            .filter(s => this.matchStudentSearch(s, q));
    },

    leaveRequestOf(studentId, session) {
        const ss = session || this.leaveSession;
        if (!ss) return null;
        return this.leaveRequests.find(r => r.studentId === studentId && r.programId === ss.programId && r.date === ss.date && r.status !== 'từ chối') || null;
    },

    hasApprovedLeave(studentId, session) {
        if (!session) return false;
        return this.leaveRequests.some(r => r.studentId === studentId
            && r.programId === session.programId
            && r.date === session.date
            && r.status === 'đã duyệt');
    },

    openLeaveForm(student) {
        if (this.leaveRequestOf(student.id)) {
            window.TNTT.toast.warning('Em này đã có đơn cho buổi đó rồi.');
            return;
        }
        this.leaveForm = { studentId: student.id, reason: '' };
        this.showLeaveModal = true;
    },

    submitLeave() {
        if (!this.leaveForm.reason.trim()) {
            window.TNTT.toast.warning('Vui lòng ghi lý do xin phép!');
            return;
        }
        this.leaveRequests.push({
            id: Date.now(),
            studentId: this.leaveForm.studentId,
            programId: this.leaveSession.programId,
            date: this.leaveSession.date,
            reason: this.leaveForm.reason.trim(),
            status: 'chờ duyệt',
            createdBy: this.user.fullName,
            createdAt: this.timestamp(),
            approvedBy: '', approvedAt: '', rejectReason: ''
        });
        this.showLeaveModal = false;
        this.save('leave', 'create', {
            studentId: this.leaveForm.studentId,
            programId: this.leaveSession.programId,
            date: this.leaveSession.date,
            reason: this.leaveForm.reason.trim()
        });
    },

    // ---- DUYỆT ĐƠN ----

    // GLV Chủ nhiệm duyệt đơn lớp mình, kể cả đơn do chính mình nộp.
    // Quyền 'view' chỉ nộp được đơn, 'edit' mới duyệt được.
    get canApproveLeave() {
        return this.canEditModule('leave');
    },

    // Đơn nằm trong phạm vi quyền của người đăng nhập
    get scopedLeaveRequests() {
        const ids = new Set(this.accessibleStudents.map(s => s.id));
        return this.leaveRequests
            .filter(r => ids.has(r.studentId))
            .sort((a, b) => (b.date + b.createdAt).localeCompare(a.date + a.createdAt));
    },

    get visibleLeaveRequests() {
        if (this.leaveFilter === '') return this.scopedLeaveRequests;
        return this.scopedLeaveRequests.filter(r => r.status === this.leaveFilter);
    },

    // Con số cho chấm đỏ ngoài App Center, đã lọc theo quyền
    get pendingLeaveCount() {
        return this.scopedLeaveRequests.filter(r => r.status === 'chờ duyệt').length;
    },

    approveLeave(req) {
        const st = this.studentById(req.studentId);
        this.logAction('duyet', 'leave', 'Duyệt đơn phép của ' + (st ? st.name : ''),
                       this.formatDate(req.date) + ' · ' + req.reason);
        req.status = 'đã duyệt';
        req.approvedBy = this.user.fullName;
        req.approvedAt = this.timestamp();
        req.rejectReason = '';
        this.save('leave', 'approve', { id: req.id });
    },

    openRejectForm(req) {
        this.rejectForm = { requestId: req.id, reason: '' };
        this.showRejectModal = true;
    },

    confirmReject() {
        if (!this.rejectForm.reason.trim()) {
            window.TNTT.toast.warning('Vui lòng ghi lý do từ chối để GLV biết mà giải thích với phụ huynh.');
            return;
        }
        const req = this.leaveRequests.find(r => r.id === this.rejectForm.requestId);
        if (req) {
            const st = this.studentById(req.studentId);
            this.logAction('tuchoi', 'leave', 'Từ chối đơn phép của ' + (st ? st.name : ''),
                           this.rejectForm.reason.trim());
            req.status = 'từ chối';
            req.rejectReason = this.rejectForm.reason.trim();
            req.approvedBy = this.user.fullName;
            req.approvedAt = this.timestamp();
            this.save('leave', 'reject', { id: req.id, reason: req.rejectReason });
        }
        this.showRejectModal = false;
    },

    // ---- Tiện ích hiển thị ----
    studentById(id) {
        if (this.studentIndex) return this.studentIndex.get(id) || null;
        return this.students.find(s => s.id === id) || null;
    },

    programById(id) {
        return this.programs.find(p => p.id === id) || null;
    },

    leaveChipClass(status) {
        if (status === 'đã duyệt') return 'bg-emerald-50 text-emerald-600 border-emerald-100';
        if (status === 'từ chối')  return 'bg-rose-50 text-rose-600 border-rose-100';
        return 'bg-amber-50 text-amber-600 border-amber-100';
    },
};
