/* ==========================================================
   SCHEDULES — Thời khóa biểu riêng cho từng lớp (Hướng B)
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.schedules = {
    // ==========================================
    // 1. DATA & STATE
    // ==========================================
    classSchedules: [],   // nạp từ data.php
    scheduleExceptions: {},  // scheduleId -> exceptions[], nạp khi cần

    showScheduleModal: false,
    isEditingSchedule: false,
    showExceptionModal: false,
    editingException: null,
    exceptionScheduleId: null,

    // Form state
    scheduleForm: {
        id: null,
        classId: 0,
        dayOfWeek: 0,
        startTime: '',
        cutoffTime: '',
        slot: '',
        programId: '',
        activeFrom: '',
        activeTo: '',
        status: 'kích hoạt'
    },

    // Form ngoại lệ
    exceptionForm: {
        id: null,
        scheduleId: null,
        onDate: '',
        kind: 'nghỉ',
        newStart: '',
        newCutoff: '',
        note: ''
    },

    // ==========================================
    // 2. HELPERS: Ngoại lệ lịch
    // ==========================================

    // Lấy ngoại lệ của một schedule (từ cache hoặc API)
    async getExceptions(scheduleId) {
        if (this.scheduleExceptions[scheduleId]) {
            return this.scheduleExceptions[scheduleId];
        }
        const r = await this.api('schedules', 'exceptions', { scheduleId: scheduleId });
        if (r && r.ok && r.exceptions) {
            this.scheduleExceptions[scheduleId] = r.exceptions;
            return r.exceptions;
        }
        return [];
    },

    // Kiểm tra ngoại lệ cho một schedule vào một ngày cụ thể
    async checkException(scheduleId, dateStr) {
        const exceptions = await this.getExceptions(scheduleId);
        return exceptions.find(e => e.onDate === dateStr) || null;
    },

    // Kiểm tra schedule có ngoại lệ "nghỉ" vào ngày này không
    async isScheduleCancelled(scheduleId, dateStr) {
        const exc = await this.checkException(scheduleId, dateStr);
        return exc && exc.kind === 'nghỉ';
    },

    // Lấy giờ điều chỉnh nếu có ngoại lệ "dời_giờ" hoặc "học_bù"
    async getExceptionTime(scheduleId, dateStr, defaultStart, defaultCutoff) {
        const exc = await this.checkException(scheduleId, dateStr);
        if (exc && (exc.kind === 'dời_giờ' || exc.kind === 'học_bù')) {
            return {
                start: exc.newStart || defaultStart,
                cutoff: exc.newCutoff || this.addMinutes(exc.newStart || defaultStart, this.CUTOFF_MINUTES || 30)
            };
        }
        return null;
    },

    // ==========================================
    // 2. COMPUTED / DERIVED
    // ==========================================
    get groupedByClass() {
        const map = {};
        for (const s of this.classSchedules) {
            if (!map[s.className]) map[s.className] = [];
            map[s.className].push(s);
        }
        return map;
    },

    get classOptions() {
        // Lấy danh sách lớp từ enrollments của năm nay
        const seen = new Set();
        const classes = [];
        for (const s of window.TNTT.core.students || []) {
            if (s.className && !seen.has(s.className)) {
                seen.add(s.className);
                // Tìm classId từ danh sách học sinh hoặc tạo tạm
                classes.push({ id: s.classId || 0, name: s.className });
            }
        }
        // Loại bỏ trùng và sắp xếp
        const unique = [];
        const ids = new Set();
        for (const c of classes) {
            if (!ids.has(c.id)) { ids.add(c.id); unique.push(c); }
        }
        return unique.sort((a, b) => a.name.localeCompare(b.name, 'vi'));
    },

    // ==========================================
    // 3. HELPERS
    // ==========================================
    getDayLabel(dow) {
        const days = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
        return days[dow] || '';
    },

    getDayColorClass(dow) {
        const colors = [
            'text-rose-600 bg-rose-50',    // 0: CN - Chúa Nhật
            'text-slate-600 bg-slate-50',   // 1: Thứ Hai
            'text-slate-600 bg-slate-50',   // 2: Thứ Ba
            'text-slate-600 bg-slate-50',   // 3: Thứ Tư
            'text-amber-600 bg-amber-50',   // 4: Thứ Năm
            'text-slate-600 bg-slate-50',   // 5: Thứ Sáu
            'text-indigo-600 bg-indigo-50'  // 6: Thứ Bảy
        ];
        return colors[dow] || 'text-slate-600 bg-slate-50';
    },

    getSlotBadgeClass(slot) {
        switch (slot) {
            case 'sáng':  return 'bg-amber-50 text-amber-700';
            case 'chiều': return 'bg-blue-50 text-blue-700';
            case 'tối':   return 'bg-indigo-50 text-indigo-700';
            default:      return 'bg-slate-50 text-slate-600';
        }
    },

    // ==========================================
    // 4. ACTIONS
    // ==========================================

    // Mở popup tạo mới
    openCreateSchedule() {
        this.scheduleForm = {
            id: null,
            classId: 0,
            dayOfWeek: 0,
            startTime: '',
            cutoffTime: '',
            slot: '',
            programId: '',
            activeFrom: '',
            activeTo: '',
            status: 'kích hoạt'
        };
        this.isEditingSchedule = false;
        this.showScheduleModal = true;
    },

    // Mở popup sửa
    openEditSchedule(sch) {
        this.scheduleForm = {
            id: sch.id,
            classId: sch.classId,
            dayOfWeek: sch.dayOfWeek,
            startTime: sch.startTime,
            cutoffTime: sch.cutoffTime || '',
            slot: sch.slot || '',
            programId: sch.programId || '',
            activeFrom: sch.activeFrom || '',
            activeTo: sch.activeTo || '',
            status: sch.status || 'kích hoạt'
        };
        this.isEditingSchedule = true;
        this.showScheduleModal = true;
    },

    // Lưu thời khóa biểu
    async saveSchedule() {
        const f = this.scheduleForm;

        if (!f.classId) {
            window.TNTT.toast.error('Vui lòng chọn lớp!');
            return;
        }
        if (!f.startTime) {
            window.TNTT.toast.error('Vui lòng nhập giờ bắt đầu!');
            return;
        }
        if (f.cutoffTime && f.cutoffTime <= f.startTime) {
            window.TNTT.toast.error('Giờ chốt phải sau giờ bắt đầu!');
            return;
        }
        if (f.activeFrom && f.activeTo && f.activeFrom > f.activeTo) {
            window.TNTT.toast.error('Ngày bắt đầu phải trước ngày kết thúc!');
            return;
        }

        const r = await this.api('schedules', 'save', {
            id: f.id,
            classId: f.classId,
            dayOfWeek: f.dayOfWeek,
            startTime: f.startTime,
            cutoffTime: f.cutoffTime || '',
            slot: f.slot || '',
            programId: f.programId || null,
            activeFrom: f.activeFrom || '',
            activeTo: f.activeTo || '',
            status: f.status
        });

        if (!r || !r.ok) {
            window.TNTT.toast.error(r && r.error || 'Không lưu được thời khóa biểu.');
            return;
        }

        // Cập nhật danh sách cục bộ
        const scheduleData = {
            id: r.id || f.id,
            classId: f.classId,
            className: this.classOptions.find(c => c.id === f.classId)?.name || '',
            dayOfWeek: f.dayOfWeek,
            startTime: f.startTime,
            cutoffTime: f.cutoffTime || null,
            slot: f.slot || null,
            programId: f.programId || null,
            programName: window.TNTT.programs?.programs.find(p => p.id == f.programId)?.name || '',
            activeFrom: f.activeFrom || '',
            activeTo: f.activeTo || '',
            status: f.status
        };

        const idx = this.classSchedules.findIndex(s => s.id === scheduleData.id);
        if (idx !== -1) {
            this.classSchedules[idx] = scheduleData;
        } else {
            this.classSchedules.push(scheduleData);
        }

        this.logAction(
            this.isEditingSchedule ? 'sua' : 'tao',
            'class_schedules',
            (this.isEditingSchedule ? 'Sửa' : 'Tạo') + ' thời khóa biểu lớp',
            `lớp=${scheduleData.className}, thứ=${f.dayOfWeek}, giờ=${f.startTime}`
        );

        this.showScheduleModal = false;
        window.TNTT.toast.success('Đã lưu thời khóa biểu!');
    },

    // Xóa thời khóa biểu
    async deleteSchedule(id) {
        if (!confirm('Xóa thời khóa biểu này?')) return;

        const r = await this.api('schedules', 'delete', { id: id });
        if (!r || !r.ok) {
            window.TNTT.toast.error(r && r.error || 'Không xóa được thời khóa biểu.');
            return;
        }

        this.classSchedules = this.classSchedules.filter(s => s.id !== id);
        this.logAction('xoa', 'class_schedules', 'Xóa thời khóa biểu', `id=${id}`);
        window.TNTT.toast.success('Đã xóa thời khóa biểu!');
    },

    // Seed dữ liệu từ chương trình hiện tại
    async seedSchedules() {
        if (!confirm('Tạo thời khóa biểu từ chương trình hiện tại cho tất cả các lớp?')) return;

        const r = await this.api('schedules', 'seed', {});
        if (!r || !r.ok) {
            window.TNTT.toast.error(r && r.error || 'Không tạo được dữ liệu seed.');
            return;
        }

        // Tải lại danh sách
        await window.TNTT.core.loadData();

        window.TNTT.toast.success(r.message || `Đã tạo ${r.created || 0} lịch!`);
    },

    // ==========================================
    // 3. NGOẠI LỆ LỊCH
    // ==========================================

    // Mở popup ngoại lệ cho một schedule
    async openScheduleExceptions(sch) {
        this.exceptionScheduleId = sch.id;
        // Tải ngoại lệ từ API
        await this.getExceptions(sch.id);
        // Reset form
        this.exceptionForm = {
            id: null,
            scheduleId: sch.id,
            onDate: '',
            kind: 'nghỉ',
            newStart: '',
            newCutoff: '',
            note: ''
        };
        this.editingException = null;
        this.showExceptionModal = true;
    },

    // Mở form sửa ngoại lệ
    openEditException(exc) {
        this.editingException = exc;
        this.exceptionForm = {
            id: exc.id,
            scheduleId: exc.scheduleId,
            onDate: exc.onDate,
            kind: exc.kind,
            newStart: exc.newStart || '',
            newCutoff: exc.newCutoff || '',
            note: exc.note || ''
        };
    },

    // Lưu ngoại lệ
    async saveException() {
        const f = this.exceptionForm;
        if (!f.onDate) {
            window.TNTT.toast.error('Vui lòng chọn ngày ngoại lệ!');
            return;
        }
        if (f.kind !== 'nghỉ' && !f.newStart) {
            window.TNTT.toast.error('Vui lòng nhập giờ mới!');
            return;
        }

        const r = await this.api('schedules', 'saveException', {
            id: f.id,
            scheduleId: f.scheduleId,
            onDate: f.onDate,
            kind: f.kind,
            newStart: f.newStart || '',
            newCutoff: f.newCutoff || '',
            note: f.note || ''
        });

        if (!r || !r.ok) {
            window.TNTT.toast.error(r && r.error || 'Không lưu được ngoại lệ.');
            return;
        }

        // Cập nhật cache cục bộ
        if (!this.scheduleExceptions[f.scheduleId]) {
            this.scheduleExceptions[f.scheduleId] = [];
        }

        const exc = {
            id: r.id || f.id,
            scheduleId: f.scheduleId,
            onDate: f.onDate,
            kind: f.kind,
            newStart: f.newStart || null,
            newCutoff: f.newCutoff || null,
            note: f.note || ''
        };

        const idx = this.scheduleExceptions[f.scheduleId].findIndex(e => e.id === exc.id);
        if (idx !== -1) {
            this.scheduleExceptions[f.scheduleId][idx] = exc;
        } else {
            this.scheduleExceptions[f.scheduleId].push(exc);
        }

        // Reset form
        this.exceptionForm = {
            id: null,
            scheduleId: f.scheduleId,
            onDate: '',
            kind: 'nghỉ',
            newStart: '',
            newCutoff: '',
            note: ''
        };
        this.editingException = null;

        this.logAction(
            f.id ? 'sua' : 'tao',
            'schedule_exceptions',
            (f.id ? 'Sửa' : 'Tạo') + ' ngoại lệ lịch',
            `ngày=${f.onDate}, loại=${f.kind}`
        );

        window.TNTT.toast.success('Đã lưu ngoại lệ!');
    },

    // Xóa ngoại lệ
    async deleteException(id) {
        if (!confirm('Xóa ngoại lệ này?')) return;

        const r = await this.api('schedules', 'deleteException', { id: id });
        if (!r || !r.ok) {
            window.TNTT.toast.error(r && r.error || 'Không xóa được ngoại lệ.');
            return;
        }

        // Cập nhật cache cục bộ
        for (const sid in this.scheduleExceptions) {
            this.scheduleExceptions[sid] = this.scheduleExceptions[sid].filter(e => e.id !== id);
        }

        this.logAction('xoa', 'schedule_exceptions', 'Xóa ngoại lệ lịch', `id=${id}`);
        window.TNTT.toast.success('Đã xóa ngoại lệ!');
    },
};
