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
    // Index tra cứu O(1) (dựng trong loadData) — tránh .find/.filter O(n) trên
    // hàng chục nghìn dòng ở mỗi lần render, vốn làm treo máy với đoàn lớn.
    attIndex: null,      // 'programId|date|studentId' -> bản ghi
    attByStudent: null,  // studentId -> mảng bản ghi của em đó

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

    // Chương trình có diễn ra vào ngày này không.
    // Hỗ trợ LẶP NHIỀU THỨ (daysOfWeek) + KHOẢNG NGÀY áp dụng (effectiveFrom/To).
    programOccursOn(p, dateStr) {
        if (p.status !== 'kích hoạt') return false;
        if (p.type === 'chiến dịch') return p.eventDate === dateStr;
        const dow = new Date(dateStr + 'T00:00:00').getDay();
        const days = (p.daysOfWeek && p.daysOfWeek.length)
            ? p.daysOfWeek
            : (p.dayOfWeek === null || p.dayOfWeek === undefined ? [] : [p.dayOfWeek]);
        if (!days.includes(dow)) return false;
        if (p.effectiveFrom && dateStr < p.effectiveFrom) return false;
        if (p.effectiveTo   && dateStr > p.effectiveTo)   return false;
        return true;
    },

    // Lớp (theo TÊN) có tham gia chương trình không. RỖNG gắn lớp = toàn đoàn.
    programAppliesToClass(p, className) {
        if (!className) return true;
        const ids = (this.programClasses && this.programClasses[p.id]) || [];
        if (!ids.length) return true;
        const cls = (this.classes || []).find(c => c.name === className);
        return cls ? ids.includes(cls.id) : true;
    },

    // Các chương trình đang kích hoạt diễn ra vào một ngày; lọc theo lớp nếu có.
    programsOn(dateStr, className = null) {
        if (!dateStr) return [];
        return this.programs
            .filter(p => this.programOccursOn(p, dateStr) && this.programAppliesToClass(p, className))
            .sort((a, b) => a.startTime.localeCompare(b.startTime));
    },

    get programsOnDate() {
        return this.programsOn(this.attendanceDate, this.attendanceClass || null);
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
        // Chặn khi số liệu điểm danh chưa tải xong (bước 2). Nếu không, sổ
        // sẽ hiện mọi em "chưa điểm danh" và dễ điểm danh đè lên bản ghi cũ.
        if (!this.heavyLoaded) {
            window.TNTT.toast.info('Đang tải số liệu điểm danh, đợi một chút rồi bắt đầu nhé.');
            return;
        }
        this.activeSession = { programId: prog.id, date: this.attendanceDate };
        this.attendanceSearch = '';
        // Bắt chọn lớp cho điểm danh TAY (giống Danh sách): một lớp thì tự mở,
        // nhiều lớp để trống, chọn lớp nào điểm danh lớp đó. Bộ chọn chỉ hiện
        // lớp mình phụ trách. Riêng quét QR chạy theo khối, không cần chọn lớp.
        this.attendanceClass = this.availableClasses.length === 1 ? this.availableClasses[0] : '';
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
        if (!prog) return '';
        // Có nhập giờ chốt riêng thì dùng đúng giờ đó; không thì mặc định
        // giờ bắt đầu + CUTOFF_MINUTES (tương thích chương trình cũ chưa nhập).
        return prog.cutoffTime ? prog.cutoffTime : this.addMinutes(prog.startTime, this.CUTOFF_MINUTES);
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
        // Chưa chọn lớp thì KHÔNG đổ danh sách (giống Danh sách) — trừ khi
        // đang gõ tìm tên. Áp cho mọi vai; bộ chọn chỉ hiện lớp mình phụ trách.
        if (this.attendanceClass === '' && q === '') return [];
        return this.accessibleStudents
            .filter(s => s.status === 'đang sinh hoạt')
            .filter(s => this.attendanceClass === '' || s.className === this.attendanceClass)
            .filter(s => q === ''
                || this.normalizeText(s.name).includes(q)
                || this.normalizeText(s.holyName).includes(q)
                || this.normalizeText(s.code).includes(q));
    },

    // ---- Index điểm danh (O(1)) ----
    attKey(programId, date, studentId) { return programId + '|' + date + '|' + studentId; },

    // Dựng lại toàn bộ index từ this.attendances. Gọi sau loadData.
    rebuildAttendanceIndex() {
        const idx = new Map();
        const byStu = new Map();
        for (const a of this.attendances) {
            idx.set(this.attKey(a.programId, a.date, a.studentId), a);
            let arr = byStu.get(a.studentId);
            if (!arr) { arr = []; byStu.set(a.studentId, arr); }
            arr.push(a);
        }
        this.attIndex = idx;
        this.attByStudent = byStu;
    },

    // Thêm/xoá 1 bản ghi: cập nhật CẢ mảng lẫn index để không lệch.
    _attThem(rec) {
        this.attendances.push(rec);
        if (this.attIndex) this.attIndex.set(this.attKey(rec.programId, rec.date, rec.studentId), rec);
        if (this.attByStudent) {
            let arr = this.attByStudent.get(rec.studentId);
            if (!arr) { arr = []; this.attByStudent.set(rec.studentId, arr); }
            arr.push(rec);
        }
    },
    _attXoa(programId, date, studentId) {
        const key = this.attKey(programId, date, studentId);
        const rec = this.attIndex ? this.attIndex.get(key) : null;
        const i = this.attendances.findIndex(a => a.programId === programId && a.date === date && a.studentId === studentId);
        if (i !== -1) this.attendances.splice(i, 1);
        if (this.attIndex) this.attIndex.delete(key);
        if (this.attByStudent && rec) {
            const arr = this.attByStudent.get(studentId);
            if (arr) { const j = arr.indexOf(rec); if (j !== -1) arr.splice(j, 1); }
        }
        return rec;
    },
    // Mảng điểm danh của 1 em (O(1) lấy mảng nhỏ) — cho hồ sơ/phân tích.
    attOfStudent(studentId) {
        return (this.attByStudent && this.attByStudent.get(studentId)) || [];
    },

    attendanceRecord(studentId, session) {
        const ss = session || this.activeSession;
        if (!ss) return null;
        if (this.attIndex) return this.attIndex.get(this.attKey(ss.programId, ss.date, studentId)) || null;
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

        const cu = this.attendanceRecord(student.id, this.activeSession);

        if (cu) {
            this._attXoa(this.activeSession.programId, this.activeSession.date, student.id);
            if (this.isPastCutoff) {
                this.logAction('diemdanh', 'attendance', 'Gỡ điểm danh của ' + student.name,
                               this.sessionProgram.name + ' · ' + this.formatDate(this.activeSession.date) + ' · đang là ' + cu.status);
            }
            this.save('attendance', 'toggle', {
                programId: this.activeSession.programId,
                date: this.activeSession.date,
                studentId: student.id
            }).then(r => {
                if (!r || !r.ok) this._attThem(cu);
            });
            return;
        }

        const newRec = {
            programId: this.activeSession.programId,
            date: this.activeSession.date,
            studentId: student.id,
            // Quá giờ chốt mới chạm vào tên -> ghi nhận đi trễ, đúng với luồng quét QR
            status: this.isPastCutoff ? 'đi trễ' : 'có mặt',
            method: 'tay',
            markedBy: this.user.fullName,
            markedAt: this.currentTime()
        };
        this._attThem(newRec);

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
        }).then(r => {
            if (!r || !r.ok) {
                this._attXoa(this.activeSession.programId, this.activeSession.date, student.id);
            } else if (r.status) {
                // Cập nhật lại chính xác trạng thái từ server (tránh đồng hồ client lệch)
                this._attXoa(this.activeSession.programId, this.activeSession.date, student.id);
                newRec.status = r.status;
                newRec.markedAt = r.markedAt;
                newRec.markedBy = r.markedBy;
                this._attThem(newRec);
            }
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

    // Số buổi HÔM NAY trong phạm vi còn chưa điểm danh xong — cho chấm nhắc
    // trên icon Điểm danh (thay cho khối "Việc cần làm" ở Trang chủ).
    get attendanceTodoCount() {
        if (!this.canAccess('attendance') || this.isUnderMaintenance('attendance')) return 0;
        const today = this.toDateInput(new Date());
        const scope = this.accessibleStudents.filter(s => s.status === 'đang sinh hoạt');
        if (scope.length === 0) return 0;
        let n = 0;
        this.programsOn(today).forEach(p => {
            const done = scope.filter(s => this.attendanceRecord(s.id, { programId: p.id, date: today })).length;
            if (done < scope.length) n++;
        });
        return n;
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
