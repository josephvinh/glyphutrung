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

    // Hàng đợi điểm danh ngoại tuyến (Offline Attendance Resilience)
    offlineAttendanceCount: 0,
    _isSyncingOffline: false,

    // Export Excel modal state
    showExportModal: false,
    exportForm: {
        classId: '',
        fromDate: '',
        toDate: '',
        loading: false,
        error: ''
    },
    nowTs: Date.now(),        // nhịp đồng hồ, cập nhật 30 giây/lần để badge giờ chốt tự đổi

    getOfflineAttendanceQueue() {
        try {
            const raw = localStorage.getItem('tntt_offline_attendance_queue');
            if (!raw) return [];
            const queue = JSON.parse(raw);
            // Migrate legacy items (action:'toggle') → op:'mark'
            // Legacy: { programId, date, studentId, studentName, action:'toggle', createdAt }
            // Mới:    { programId, date, studentId, studentName, op:'mark', offlineMark:true }
            let migrated = false;
            const migratedQueue = queue.map(item => {
                if (item.action === 'toggle') {
                    migrated = true;
                    const { action, createdAt, ...rest } = item;
                    return { ...rest, op: 'mark', offlineMark: true };
                }
                return item;
            });
            if (migrated) {
                this.setOfflineAttendanceQueue(migratedQueue);
                console.log('Đã chuyển đổi queue offline từ legacy format');
            }
            return migratedQueue;
        } catch (e) { return []; }
    },
    setOfflineAttendanceQueue(queue) {
        try {
            localStorage.setItem('tntt_offline_attendance_queue', JSON.stringify(queue));
            this.offlineAttendanceCount = queue.length;
        } catch (e) { console.warn('Không thể lưu offline queue', e); }
    },
    pushOfflineAttendance(item) {
        const queue = this.getOfflineAttendanceQueue();
        const idx = queue.findIndex(q => q.programId === item.programId && q.date === item.date && q.studentId === item.studentId);
        if (idx !== -1) {
            queue[idx] = item;
        } else {
            queue.push(item);
        }
        this.setOfflineAttendanceQueue(queue);
    },
    initOfflineAttendance() {
        this.offlineAttendanceCount = this.getOfflineAttendanceQueue().length;
        window.addEventListener('online', () => {
            if (this.offlineAttendanceCount > 0) {
                this.syncOfflineAttendance();
            }
        });
        setInterval(() => {
            if (navigator.onLine && this.offlineAttendanceCount > 0 && !this._isSyncingOffline) {
                this.syncOfflineAttendance();
            }
        }, 20000);
    },
    async syncOfflineAttendance() {
        if (this._isSyncingOffline) return;
        const queue = this.getOfflineAttendanceQueue();
        if (!queue.length) {
            this.offlineAttendanceCount = 0;
            return;
        }
        if (!navigator.onLine) {
            window.TNTT?.toast?.warning('Thiết bị vẫn đang ngoại tuyến. Vui lòng kiểm tra lại kết nối mạng.');
            return;
        }

        this._isSyncingOffline = true;
        let successCount = 0;
        const failedQueue = [];

        try {
            for (const item of queue) {
                try {
                    const r = await this.api('attendance', 'toggle', {
                        programId: item.programId,
                        date: item.date,
                        studentId: item.studentId
                    });
                    if (r && r.ok) {
                        successCount++;
                    } else if (r && r.networkError) {
                        failedQueue.push(item);
                    } else {
                        // Lỗi nghiệp vụ từ server (đã khoá sổ hoặc không có quyền), bỏ qua
                        console.warn('Bỏ qua bản ghi điểm danh offline:', item, r?.error);
                    }
                } catch (err) {
                    failedQueue.push(item);
                }
            }
        } finally {
            this.setOfflineAttendanceQueue(failedQueue);
            this._isSyncingOffline = false;
        }

        if (successCount > 0) {
            window.TNTT?.toast?.success(`Đã đồng bộ thành công ${successCount} lượt điểm danh lên máy chủ!`);
            if (this.currentModule === 'attendance' && typeof this.loadData === 'function') {
                this.loadData();
            }
        }
    },

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
        // Chương trình CÓ gắn lớp cụ thể: lớp không tra ra id thì coi như
        // KHÔNG thuộc buổi (fail-closed) — khớp với chặn phía máy chủ
        // (attendance.php lọc theo program_classes), tránh hiện buổi cho
        // lớp không được ghi.
        const cls = (this.classes || []).find(c => c.name === className);
        return cls ? ids.includes(cls.id) : false;
    },

    // Các chương trình đang kích hoạt diễn ra vào một ngày; lọc theo lớp nếu có.
    programsOn(dateStr, className = null) {
        if (!dateStr) return [];
        return this.programs
            .filter(p => this.programOccursOn(p, dateStr) && this.programAppliesToClass(p, className))
            .sort((a, b) => a.startTime.localeCompare(b.startTime));
    },

    // Chương trình có nằm trong phạm vi LỚP của người đăng nhập không.
    //
    // Yêu cầu: thành viên gắn lớp (không phải toàn đoàn) chỉ thấy đúng
    // những chương trình đã gắn lớp của mình ở module Chương trình. Buổi
    // gắn cho lớp khác thì KHÔNG hiện trên màn điểm danh — khớp với chặn
    // phía máy chủ (attendance.php lọc scan/lookup/toggle theo program_classes).
    //
    // Chương trình KHÔNG gắn lớp nào = toàn đoàn -> ai cũng thấy.
    programInMyScope(p) {
        if (this.isUnrestrictedScope) return true;      // Quản Trị / BĐH: thấy hết
        const ids = (this.programClasses && this.programClasses[p.id]) || [];
        if (!ids.length) return true;                   // toàn đoàn
        const mine = this.myClasses;
        const myIds = new Set(
            (this.classes || []).filter(c => mine.includes(c.name)).map(c => c.id)
        );
        return ids.some(id => myIds.has(id));
    },

    // Đã tới giờ bắt đầu buổi chưa — để CHỈ hiện buổi khi tới giờ, tránh
    // mở/quét nhầm buổi khác (yêu cầu người dùng).
    //   - Ngày quá khứ: hiện hết (để sửa / đối chiếu).
    //   - Ngày tương lai: chưa tới -> ẩn.
    //   - Hôm nay: so giờ hiện tại với giờ bắt đầu (nowTs cập nhật 30s/lần
    //     nên buổi tự hiện ra khi tới giờ, không cần tải lại trang).
    programStarted(p, dateStr) {
        const today = this.toDateInput(new Date());
        if (dateStr < today) return true;
        if (dateStr > today) return false;
        if (!p.startTime) return true;   // thiếu giờ bắt đầu -> không chặn
        return this.nowTs >= new Date(dateStr + 'T' + p.startTime + ':00').getTime();
    },

    // Buổi trong ngày thuộc phạm vi LỚP của mình (chưa xét giờ bắt đầu).
    // Tính MỘT lần rồi tách "đã tới giờ" / "chưa tới giờ" bên dưới, tránh
    // lặp lại programsOn + programInMyScope cho cả hai danh sách.
    get scopedProgramsOnDate() {
        return this.programsOn(this.attendanceDate, this.attendanceClass || null)
            .filter(p => this.programInMyScope(p));
    },

    // Danh sách buổi HIỆN được (đúng phạm vi lớp + đã tới giờ bắt đầu).
    get programsOnDate() {
        return this.scopedProgramsOnDate.filter(p => this.programStarted(p, this.attendanceDate));
    },

    // Buổi thuộc phạm vi nhưng CHƯA tới giờ (hôm nay chưa tới giờ, hoặc ngày
    // mai trở đi). Hiện mờ để người dùng biết "sắp có", nhưng chưa mở được —
    // vừa nhắc lịch, vừa tránh mở nhầm buổi.
    get pendingProgramsOnDate() {
        return this.scopedProgramsOnDate.filter(p => !this.programStarted(p, this.attendanceDate));
    },

    // Học sinh đang sinh hoạt trong phạm vi mình VÀ thuộc lớp gắn của buổi.
    // Buổi có gắn lớp -> chỉ đếm học sinh của đúng các lớp đó (khớp mẫu số
    // mà GLV thật sự điểm danh được); buổi không gắn lớp = toàn đoàn.
    programScopeStudents(prog) {
        let scope = this.accessibleStudents.filter(s => s.status === 'đang sinh hoạt');
        const ids = (this.programClasses && this.programClasses[prog.id]) || [];
        if (ids.length) {
            const names = new Set((this.classes || []).filter(c => ids.includes(c.id)).map(c => c.name));
            scope = scope.filter(s => names.has(s.className));
        }
        return scope;
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
        if (!this.heavyLoaded || !this.heavyFresh) {
            window.TNTT.toast.info('Đang tải số liệu điểm danh, đợi một chút rồi bắt đầu nhé.');
            return;
        }
        // Chỉ mở buổi khi đã tới giờ bắt đầu (tránh mở/quét nhầm buổi khác).
        // Danh sách đã ẩn buổi chưa tới giờ; chặn thêm ở đây phòng gọi từ nơi khác.
        if (!this.programStarted(prog, this.attendanceDate)) {
            window.TNTT.toast.info('Buổi "' + prog.name + '" chưa tới giờ bắt đầu (' + prog.startTime + ').');
            return;
        }
        this.activeSession = { programId: prog.id, date: this.attendanceDate };
        this.editStatusMode = false;
        this.attendanceSearch = '';
        // Bắt chọn lớp cho điểm danh TAY (giống Danh sách): một lớp thì tự mở,
        // nhiều lớp để trống, chọn lớp nào điểm danh lớp đó. Bộ chọn chỉ hiện
        // lớp mình phụ trách. Riêng quét QR chạy theo khối, không cần chọn lớp.
        this.attendanceClass = this.availableClasses.length === 1 ? this.availableClasses[0] : '';
        this.changeModule('attendance');
    },

    exitSession() {
        this.activeSession = null;
        this.editStatusMode = false;
        this.attendanceSearch = '';
    },

    get sessionProgram() {
        if (!this.activeSession) return null;
        return this.programs.find(p => p.id === this.activeSession.programId) || null;
    },

    cutoffOf(prog) {
        if (!prog) return '';
        // Nguồn duy nhất là giờ tính đi trễ của buổi (bắt buộc nhập). Buổi cũ
        // chưa đặt thì coi giờ bắt đầu là mốc — khớp máy chủ (program_cutoff_ts).
        return prog.cutoffTime || prog.startTime;
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
        const q = (this.attendanceSearch || '').trim();
        // Chưa chọn lớp thì KHÔNG đổ danh sách (giống Danh sách) — trừ khi
        // đang gõ tìm tên. Áp cho mọi vai; bộ chọn chỉ hiện lớp mình phụ trách.
        if (this.attendanceClass === '' && q === '') return [];
        return this.accessibleStudents
            .filter(s => s.status === 'đang sinh hoạt')
            .filter(s => this.attendanceClass === '' || s.className === this.attendanceClass)
            .filter(s => this.matchStudentSearch(s, q));
    },

    // ---- Index điểm danh (O(1)) ----
    // Khoá tra cứu: chương-trình|ngày|em. Nhận CẢ hai kiểu gọi —
    // attKey(programId, date, studentId) (attendance.js dùng) và
    // attKey({programId, date, studentId}) (reports.js/promotion.js dùng).
    // Mô hình nay là program-centric nên scheduleId (nếu có) bị bỏ qua.
    // attendance/stats/reports/promotion GỘP chung một component (app.js),
    // nên CHỈ giữ MỘT định nghĩa ở đây — tránh hai bản đè lẫn nhau làm
    // hỏng toàn bộ index (bản nạp sau thắng, đổi luôn chữ ký hàm).
    attKey(programId, date, studentId) {
        if (programId !== null && typeof programId === 'object') {
            const s = programId;
            return s.programId + '|' + s.date + '|' + s.studentId;
        }
        return programId + '|' + date + '|' + studentId;
    },

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

    // ---- SỬA TRẠNG THÁI (có mặt <-> đi trễ) của bản ghi đã có ----
    // Chế độ bật/tắt: tắt thì chạm tên vẫn là ghi/gỡ như cũ, bật thì chạm tên
    // em ĐÃ GHI sẽ đổi qua lại. Chỉ hiện cho vai có "cửa sửa" (khớp
    // can_override_session_lock ở máy chủ — máy chủ mới là nơi chặn thật,
    // kể cả phạm vi lớp/khối; đây chỉ để ẩn nút với người không dùng được).
    editStatusMode: false,
    _suaBusy: {},       // id em -> đang chờ máy chủ trả lời

    get canEditAttendanceStatus() {
        const duoc = ['admin', 'bdh', 'truong_khoi', 'glv_chu_nhiem'];
        const roles = [this.user && this.user.role, ...((this.assignments || []).map(a => a.role))];
        return roles.some(r => duoc.includes(r));
    },

    async _suaTrangThai(student) {
        const phien = this.activeSession;
        const cu = this.attendanceRecord(student.id, phien);
        if (!cu) {
            window.TNTT.toast.info('Em ' + student.name + ' chưa được ghi điểm danh. Tắt "Sửa trạng thái" rồi chạm tên để ghi.');
            return;
        }
        if (!navigator.onLine) {
            window.TNTT.toast.warning('Cần có mạng để sửa trạng thái điểm danh.');
            return;
        }
        if (this._suaBusy[student.id]) return;
        this._suaBusy[student.id] = true;
        try {
            const moi = cu.status === 'đi trễ' ? 'có mặt' : 'đi trễ';
            const r = await this.save('attendance', 'set_status', {
                programId: phien.programId, date: phien.date, studentId: student.id, status: moi
            });
            if (!r || !r.ok) {
                // save() cố ý IM LẶNG khi mất kết nối với module điểm danh (vì thao
                // tác chạm tên có hàng đợi offline). Thao tác này không có hàng đợi
                // nên phải tự báo, nếu không người dùng tưởng đã đổi xong.
                if (r && r.networkError) {
                    window.TNTT.toast.warning('Mất kết nối máy chủ, chưa đổi được trạng thái. Thử lại khi có mạng.');
                }
                return;   // lỗi khác: save() đã hiện thông báo; giữ nguyên bản ghi
            }
            // Thay bản ghi (xoá + thêm lại) thay vì sửa tại chỗ: đúng cách phần
            // còn lại của file cập nhật mảng lẫn index cho giao diện làm mới.
            this._attXoa(phien.programId, phien.date, student.id);
            this._attThem(Object.assign({}, cu, { status: r.status }));
            window.TNTT.toast.success('Đã đổi ' + student.name + ' thành ' + (r.status === 'có mặt' ? 'Có mặt' : 'Đi trễ') + '.');
        } finally {
            delete this._suaBusy[student.id];
        }
    },

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

        // Chế độ "Sửa trạng thái": chạm tên là đổi có mặt <-> đi trễ, KHÔNG ghi/gỡ.
        if (this.editStatusMode && this.canEditAttendanceStatus) {
            this._suaTrangThai(student);
            return;
        }

        const cu = this.attendanceRecord(student.id, this.activeSession);

        if (cu) {
            this._attXoa(this.activeSession.programId, this.activeSession.date, student.id);
            if (this.isPastCutoff) {
                this.logAction('diemdanh', 'attendance', 'Gỡ điểm danh của ' + student.name,
                               this.sessionProgram.name + ' · ' + this.formatDate(this.activeSession.date) + ' · đang là ' + cu.status);
            }
            if (!navigator.onLine) {
                this.pushOfflineAttendance({
                    programId: this.activeSession.programId,
                    date: this.activeSession.date,
                    studentId: student.id,
                    studentName: student.name,
                    action: 'toggle',
                    createdAt: new Date().toISOString()
                });
                return;
            }
            this.save('attendance', 'toggle', {
                programId: this.activeSession.programId,
                date: this.activeSession.date,
                studentId: student.id
            }).then(r => {
                if (!r || !r.ok) {
                    if (r && r.networkError) {
                        this.pushOfflineAttendance({
                            programId: this.activeSession.programId,
                            date: this.activeSession.date,
                            studentId: student.id,
                            studentName: student.name,
                            action: 'toggle',
                            createdAt: new Date().toISOString()
                        });
                    } else {
                        this._attThem(cu);
                    }
                }
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

        if (!navigator.onLine) {
            this.pushOfflineAttendance({
                programId: this.activeSession.programId,
                date: this.activeSession.date,
                studentId: student.id,
                studentName: student.name,
                action: 'toggle',
                createdAt: new Date().toISOString()
            });
            return;
        }

        this.save('attendance', 'toggle', {
            programId: this.activeSession.programId,
            date: this.activeSession.date,
            studentId: student.id
        }).then(r => {
            if (!r || !r.ok) {
                if (r && r.networkError) {
                    this.pushOfflineAttendance({
                        programId: this.activeSession.programId,
                        date: this.activeSession.date,
                        studentId: student.id,
                        studentName: student.name,
                        action: 'toggle',
                        createdAt: new Date().toISOString()
                    });
                } else {
                    this._attXoa(this.activeSession.programId, this.activeSession.date, student.id);
                }
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
        let n = 0;
        // Chỉ nhắc những buổi thật sự mở được: đúng phạm vi lớp của mình +
        // đã tới giờ bắt đầu. Nếu không, chấm nhắc sẽ đòi làm buổi mà danh
        // sách đã ẩn / chưa cho mở -> không bao giờ tắt được.
        this.programsOn(today)
            .filter(p => this.programInMyScope(p) && this.programStarted(p, today))
            .forEach(p => {
                // Mẫu số theo lớp gắn của buổi, không phải mọi lớp mình phụ trách.
                const scope = this.programScopeStudents(p);
                if (scope.length === 0) return;
                const done = scope.filter(s => this.attendanceRecord(s.id, { programId: p.id, date: today })).length;
                if (done < scope.length) n++;
            });
        return n;
    },

    // Tiến độ hiển thị ngay trên thẻ chọn chương trình
    sessionProgress(prog) {
        const scope = this.programScopeStudents(prog);
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

    // Open export Excel modal with default values
    openExportModal() {
        const today = this.toDateInput(new Date());
        // Default: from first day of month to today
        const firstOfMonth = today.substring(0, 8) + '01';
        this.exportForm = {
            classId: '',
            fromDate: firstOfMonth,
            toDate: today,
            loading: false,
            error: ''
        };
        this.showExportModal = true;
    },

    // Gọi máy chủ lấy dữ liệu bảng rồi dựng file .xlsx ngay trên trình duyệt
    async exportAttendanceDetail(classId, fromDate, toDate) {
        const params = new URLSearchParams({ action: 'attendance-detail' });
        if (classId) params.set('classId', classId);
        if (fromDate) params.set('fromDate', fromDate);
        if (toDate) params.set('toDate', toDate);

        const resp = await fetch('/api/export.php?' + params.toString());
        const data = await resp.json();
        if (data.ok && data.sheet) {
            return this.downloadXlsx([data.sheet], data.filename);
        }
        window.TNTT.toast.error(data.error || 'Không thể xuất báo cáo điểm danh.');
        return false;
    },

    // Export attendance to Excel
    async exportAttendanceExcel() {
        const ec = this.exportForm;

        // Validation
        if (ec.fromDate && ec.toDate && ec.fromDate > ec.toDate) {
            ec.error = 'Ngày bắt đầu phải trước ngày kết thúc.';
            return;
        }

        ec.error = '';
        ec.loading = true;

        try {
            // Convert class name to class ID
            let classId = null;
            if (ec.classId) {
                const cls = (this.classes || []).find(c => c.name === ec.classId);
                classId = cls ? cls.id : null;
            }

            const success = await this.exportAttendanceDetail(
                classId,
                ec.fromDate || '',
                ec.toDate || ''
            );

            if (success) {
                this.showExportModal = false;
                window.TNTT.toast.success('Đã tải báo cáo điểm danh.');
            }
        } catch (e) {
            ec.error = 'Đã xảy ra lỗi khi xuất file.';
        } finally {
            ec.loading = false;
        }
    },
};
