/* ==========================================================
   PROGRAMS — Chương trình sinh hoạt và giờ chốt
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.programs = {
    // ==========================================
    // 1. DATA: CHƯƠNG TRÌNH & HÀM XỬ LÝ
    // ==========================================
    // Giờ chốt = giờ bắt đầu + 30 phút, cố định toàn hệ thống
    CUTOFF_MINUTES: 30,

    // dayOfWeek: 0 = Chúa Nhật ... 6 = Thứ Bảy (dùng cho chương trình "bắt buộc" lặp hàng tuần)
    // eventDate : ngày cụ thể (dùng cho chương trình "chiến dịch", chỉ diễn ra một lần)
    programs: [],   // máy chủ nạp qua loadData()

    weekdays: [
        { value: 0, label: 'Chúa Nhật' },
        { value: 1, label: 'Thứ Hai' },
        { value: 2, label: 'Thứ Ba' },
        { value: 3, label: 'Thứ Tư' },
        { value: 4, label: 'Thứ Năm' },
        { value: 5, label: 'Thứ Sáu' },
        { value: 6, label: 'Thứ Bảy' }
    ],

    showProgramModal: false,
    isEditingProgram: false,
    programForm: { id: null, name: '', type: 'bắt buộc', status: 'kích hoạt', countForAttendance: true, startTime: '', dayOfWeek: 0, eventDate: '' },

    openCreateProgram() {
        // id để null, chỉ cấp id thật lúc lưu -> khỏi phải đoán "mới hay cũ" bằng độ lớn của id
        this.programForm = { id: null, name: '', type: 'bắt buộc', status: 'kích hoạt', countForAttendance: true, startTime: '', dayOfWeek: 0, eventDate: '' };
        this.isEditingProgram = false;
        this.showProgramModal = true;
    },

    openEditProgram(prog) {
        this.programForm = JSON.parse(JSON.stringify(prog));
        this.isEditingProgram = true;
        this.showProgramModal = true;
    },

    saveProgram() {
        if (!this.programForm.name.trim() || !this.programForm.startTime) {
            alert('Vui lòng nhập tên chương trình và giờ bắt đầu!');
            return;
        }
        if (this.programForm.type === 'chiến dịch' && !this.programForm.eventDate) {
            alert('Chương trình dạng chiến dịch cần chọn ngày diễn ra!');
            return;
        }
        this.programForm.name = this.programForm.name.trim();

        // Dọn cho sạch: bắt buộc thì lặp theo thứ, chiến dịch thì gắn vào một ngày cụ thể
        if (this.programForm.type === 'bắt buộc') {
            this.programForm.eventDate = '';
            this.programForm.dayOfWeek = Number(this.programForm.dayOfWeek);
        } else {
            this.programForm.dayOfWeek = null;
        }

        if (this.isEditingProgram) {
            const idx = this.programs.findIndex(p => p.id === this.programForm.id);
            if (idx !== -1) this.programs[idx] = this.programForm;
        } else {
            this.programForm.id = Date.now();
            this.programs.push(this.programForm);
        }
        this.logAction(this.isEditingProgram ? 'sua' : 'tao', 'programs',
                       (this.isEditingProgram ? 'Sửa' : 'Tạo') + ' chương trình ' + this.programForm.name,
                       this.programSchedule(this.programForm) + ' · ' + this.programForm.startTime);
        this.showProgramModal = false;
    },

    deleteProgram(id) {
        const prog = this.programs.find(p => p.id === id);
        if (!prog) return;
        if (confirm('Xóa chương trình "' + prog.name + '"?')) {
            this.programs = this.programs.filter(p => p.id !== id);
            this.logAction('xoa', 'programs', 'Xóa chương trình ' + prog.name, '');
        }
    },

    toggleProgramStatus(prog) {
        prog.status = prog.status === 'kích hoạt' ? 'đã đóng' : 'kích hoạt';
    },

    toggleProgramAttendance(prog) {
        prog.countForAttendance = !prog.countForAttendance;
    },
};
