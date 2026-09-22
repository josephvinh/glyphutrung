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

    showScheduleModal: false,
    isEditingSchedule: false,

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
};
