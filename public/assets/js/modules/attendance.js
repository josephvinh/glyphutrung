/* ==========================================================
   ATTENDANCE — Điểm danh
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.attendance = {
    // ==========================================
    // 4. DATA: ĐIỂM DANH
    //
    // Chỉ lưu các em CÓ tới (có mặt / đi trễ). Không lưu dòng "vắng".
    // Trạng thái vắng được suy ra lúc hiển thị:
    //   không có bản ghi + có đơn đã duyệt      -> vắng có phép
    //   không có bản ghi + đã quá giờ chốt      -> vắng không phép
    //   không có bản ghi + chưa tới giờ chốt    -> chưa điểm danh
    // Nhờ vậy không cần tiến trình chạy nền lúc 07:30 để "đánh vắng".
    // ==========================================
    attendances: [],   // máy chủ nạp qua loadData()

    attendanceDate: '',
    activeSession: null,      // { programId, date }
    attendanceSearch: '',
    attendanceClass: '',
    nowTs: Date.now(),        // nhịp đồng hồ, cập nhật 30 giây/lần để badge giờ chốt tự đổi

    openAttendance() {
        if (!this.attendanceDate) this.attendanceDate = this.toDateInput(new Date());
        this.activeSession = null;
        this.attendanceSearch = '';
        this.changeModule('attendance');
    },

    // Các chương trình đang kích hoạt có diễn ra vào một ngày bất kỳ
    programsOn(dateStr) {
        if (!dateStr) return [];
        const dow = new Date(dateStr + 'T00:00:00').getDay();
        return this.programs.filter(p => {
            if (p.status !== 'kích hoạt') return false;
            if (p.type === 'chiến dịch') return p.eventDate === dateStr;
            return p.dayOfWeek === dow;
        }).sort((a, b) => a.startTime.localeCompare(b.startTime));
    },

    get programsOnDate() {
        return this.programsOn(this.attendanceDate);
    },

    // Nhảy tới Chúa Nhật gần nhất (đa số chương trình rơi vào Chúa Nhật)
    goToNearestSunday() {
        const d = new Date(this.attendanceDate + 'T00:00:00');
        d.setDate(d.getDate() - d.getDay());
        this.attendanceDate = this.toDateInput(d);
    },

    shiftAttendanceDate(days) {
        const d = new Date(this.attendanceDate + 'T00:00:00');
        d.setDate(d.getDate() + days);
        this.attendanceDate = this.toDateInput(d);
    },

    startSession(prog) {
        this.activeSession = { programId: prog.id, date: this.attendanceDate };
        this.attendanceSearch = '';
        // Mặc định lớp chính; người kiêm nhiệm vẫn chuyển được sang lớp khác
        // qua bộ chọn (availableClasses đã gồm mọi lớp mình phụ trách).
        this.attendanceClass = this.availableClasses.includes(this.user.assignedClass)
            ? this.user.assignedClass
            : '';
        this.changeModule('attendance');
    },

    exitSession() {
        this.activeSession = null;
        this.attendanceSearch = '';
    },

    get sessionProgram() {
        if (!this.activeSession) return null;
        return this.programs.find(p => p.id === this.activeSession.programId) || null;
    },

    cutoffOf(prog) {
        return prog ? this.addMinutes(prog.startTime, this.CUTOFF_MINUTES) : '';
    },

    get sessionCutoff() {
        return this.cutoffOf(this.sessionProgram);
    },

    // Đã quá giờ chốt chưa, tính cho một buổi bất kỳ.
    // Ngày quá khứ thì đương nhiên rồi, ngày tương lai thì chưa.
    isPastCutoffFor(session) {
        if (!session) return false;
        const prog = this.programs.find(p => p.id === session.programId);
        if (!prog) return false;
        return this.nowTs >= new Date(session.date + 'T' + this.cutoffOf(prog) + ':00').getTime();
    },

    get isPastCutoff() {
        return this.isPastCutoffFor(this.activeSession);
    },

    // Chỉ điểm danh các em đang sinh hoạt, trong phạm vi quyền của người đăng nhập
    get sessionStudents() {
        const q = this.normalizeText(this.attendanceSearch);
        return this.accessibleStudents
            .filter(s => s.status === 'đang sinh hoạt')
            .filter(s => this.attendanceClass === '' || s.className === this.attendanceClass)
            .filter(s => q === ''
                || this.normalizeText(s.name).includes(q)
                || this.normalizeText(s.holyName).includes(q)
                || this.normalizeText(s.code).includes(q));
    },

    attendanceRecord(studentId, session) {
        const ss = session || this.activeSession;
        if (!ss) return null;
        return this.attendances.find(a => a.programId === ss.programId && a.date === ss.date && a.studentId === studentId) || null;
    },

    // Trạng thái cuối cùng của 1 em trong 1 buổi bất kỳ (suy ra, không lưu).
    // Thứ tự ưu tiên: CÓ MẶT luôn thắng đơn phép - em đã xin phép nhưng
    // hôm đó vẫn tới thì ghi nhận có mặt, đơn phép coi như bỏ.
    statusInSession(studentId, session) {
        if (!session) return 'chưa điểm danh';
        const rec = this.attendanceRecord(studentId, session);
        if (rec) return rec.status;
        if (this.hasApprovedLeave(studentId, session)) return 'vắng có phép';
        return this.isPastCutoffFor(session) ? 'vắng không phép' : 'chưa điểm danh';
    },

    studentSessionStatus(studentId) {
        return this.statusInSession(studentId, this.activeSession);
    },

    // Chạm 1 phát là đổi trạng thái. Chạm lại lần nữa để gỡ ra nếu bấm nhầm.
    _chamGanNhat: {},   // id em -> thời điểm chạm gần nhất

    toggleAttendance(student) {
        if (!this.activeSession) return;

        // Chạm nhanh hai lần vào CÙNG một em thì bỏ qua lần thứ hai.
        //
        // Hàm này là bật/tắt: hai lần chạm liền nhau sẽ ghi rồi gỡ ra
        // ngay, và GLV tưởng đã điểm danh xong trong khi em đó vẫn trống.
        // Trước đây trình duyệt còn nuốt bớt một lần chạm để xử lý
        // "chạm đúp phóng to" nên ít lộ; nay đã tắt hành vi đó
        // (touch-action: manipulation) thì cả hai lần đều vào.
        //
        // Muốn gỡ thật thì chạm lại sau khoảng nghỉ — vẫn làm được.
        const gio = Date.now();
        if (this._chamGanNhat[student.id] && gio - this._chamGanNhat[student.id] < 450) return;
        this._chamGanNhat[student.id] = gio;

        const idx = this.attendances.findIndex(a =>
            a.programId === this.activeSession.programId &&
            a.date === this.activeSession.date &&
            a.studentId === student.id);

        if (idx !== -1) {
            const cu = this.attendances[idx];
            this.attendances.splice(idx, 1);
            this.save('attendance', 'toggle', {
                programId: this.activeSession.programId,
                date: this.activeSession.date,
                studentId: student.id
            });
            if (this.isPastCutoff) {
                this.logAction('diemdanh', 'attendance', 'Gỡ điểm danh của ' + student.name,
                               this.sessionProgram.name + ' · ' + this.formatDate(this.activeSession.date) + ' · đang là ' + cu.status);
            }
            return;
        }

        this.attendances.push({
            programId: this.activeSession.programId,
            date: this.activeSession.date,
            studentId: student.id,
            // Quá giờ chốt mới chạm vào tên -> ghi nhận đi trễ, đúng với luồng quét QR
            status: this.isPastCutoff ? 'đi trễ' : 'có mặt',
            method: 'tay',
            markedBy: this.user.fullName,
            markedAt: this.currentTime()
        });

        // Chỉ ghi nhật ký khi sửa sau giờ chốt — lúc đó con số mới
        // ảnh hưởng tới điểm chuyên cần, và đó là thứ cần tra khi có tranh cãi.
        if (this.isPastCutoff) {
            this.logAction('diemdanh', 'attendance', 'Ghi điểm danh cho ' + student.name,
                           this.sessionProgram.name + ' · ' + this.formatDate(this.activeSession.date) + ' · đi trễ');
        }

        this.save('attendance', 'toggle', {
            programId: this.activeSession.programId,
            date: this.activeSession.date,
            studentId: student.id
        });
    },

    get sessionStats() {
        const stats = { present: 0, late: 0, absent: 0, total: this.sessionStudents.length };
        this.sessionStudents.forEach(s => {
            const st = this.studentSessionStatus(s.id);
            if (st === 'có mặt') stats.present++;
            else if (st === 'đi trễ') stats.late++;
            else stats.absent++;
        });
        return stats;
    },

    // Tiến độ hiển thị ngay trên thẻ chọn chương trình
    sessionProgress(prog) {
        const scope = this.accessibleStudents.filter(s => s.status === 'đang sinh hoạt');
        const done = scope.filter(s => this.attendanceRecord(s.id, { programId: prog.id, date: this.attendanceDate })).length;
        return { done: done, total: scope.length };
    },

    // Rút gọn nhãn trên chip: hàng phải thật gọn để GLV quét mắt nhanh tại hiện trường
    attendanceChipLabel(status) {
        if (status === 'vắng không phép') return 'vắng';
        if (status === 'vắng có phép')    return 'có phép';
        if (status === 'chưa điểm danh')  return 'chưa';
        return status;
    },

    attendanceChipClass(status) {
        if (status === 'có mặt')          return 'bg-emerald-50 text-emerald-600 border-emerald-100';
        if (status === 'đi trễ')          return 'bg-amber-50 text-amber-600 border-amber-100';
        if (status === 'vắng có phép')    return 'bg-blue-50 text-blue-600 border-blue-100';
        if (status === 'vắng không phép') return 'bg-rose-50 text-rose-600 border-rose-100';
        return 'bg-slate-50 text-slate-400 border-slate-200';
    },
};
