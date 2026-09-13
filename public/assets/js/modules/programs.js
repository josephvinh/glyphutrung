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
    // cutoffTime rỗng = dùng mặc định giờ bắt đầu + CUTOFF_MINUTES
    programForm: { id: null, name: '', type: 'bắt buộc', status: 'kích hoạt', countForAttendance: true, startTime: '', cutoffTime: '', dayOfWeek: 0, eventDate: '' },

    openCreateProgram() {
        // id để null, chỉ cấp id thật lúc lưu -> khỏi phải đoán "mới hay cũ" bằng độ lớn của id
        this.programForm = { id: null, name: '', type: 'bắt buộc', status: 'kích hoạt', countForAttendance: true, startTime: '', cutoffTime: '', dayOfWeek: 0, eventDate: '' };
        this.isEditingProgram = false;
        this.showProgramModal = true;
    },

    openEditProgram(prog) {
        this.programForm = Object.assign({ cutoffTime: '' }, JSON.parse(JSON.stringify(prog)));
        this.isEditingProgram = true;
        this.showProgramModal = true;
    },

    async saveProgram() {
        const f = this.programForm;
        if (!f.name.trim() || !f.startTime) {
            alert('Vui lòng nhập tên chương trình và giờ bắt đầu!');
            return;
        }
        if (f.type === 'chiến dịch' && !f.eventDate) {
            alert('Chương trình dạng chiến dịch cần chọn ngày diễn ra!');
            return;
        }
        // Giờ chốt (nếu nhập) phải sau giờ bắt đầu
        if (f.cutoffTime && f.cutoffTime <= f.startTime) {
            alert('Giờ chốt phải sau giờ bắt đầu!');
            return;
        }
        f.name = f.name.trim();

        // Dọn cho sạch: bắt buộc thì lặp theo thứ, chiến dịch thì gắn vào một ngày cụ thể
        if (f.type === 'bắt buộc') {
            f.eventDate = '';
            f.dayOfWeek = Number(f.dayOfWeek);
        } else {
            f.dayOfWeek = null;
        }

        // LƯU LÊN MÁY CHỦ (trước đây chỉ đổi cục bộ -> mất khi tải lại).
        // Dùng api() (không phải save()) để lỗi nghiệp vụ chỉ hiện toast,
        // không tải lại cả trang.
        const r = await this.api('programs', 'save', {
            id: f.id, name: f.name, type: f.type, status: f.status,
            countForAttendance: !!f.countForAttendance,
            startTime: f.startTime, cutoffTime: f.cutoffTime || '',
            dayOfWeek: f.dayOfWeek, eventDate: f.eventDate || ''
        });
        if (!r || !r.ok) { window.TNTT.toast.error(r && r.error || 'Không lưu được chương trình.'); return; }
        f.id = r.id;               // id thật từ máy chủ (mới hoặc giữ nguyên)

        // Cập nhật danh sách cục bộ để thấy ngay, khỏi chờ nạp lại
        const idx = this.programs.findIndex(p => p.id === f.id);
        if (idx !== -1) this.programs[idx] = Object.assign({}, f);
        else this.programs.push(Object.assign({}, f));

        this.logAction(this.isEditingProgram ? 'sua' : 'tao', 'programs',
                       (this.isEditingProgram ? 'Sửa' : 'Tạo') + ' chương trình ' + f.name,
                       this.programSchedule(f) + ' · ' + f.startTime + (f.cutoffTime ? '→' + f.cutoffTime : ''));
        this.showProgramModal = false;
    },

    async deleteProgram(id) {
        const prog = this.programs.find(p => p.id === id);
        if (!prog) return;
        if (!confirm('Xóa chương trình "' + prog.name + '"?')) return;

        const r = await this.api('programs', 'delete', { id: id });
        if (!r || !r.ok) { window.TNTT.toast.error(r && r.error || 'Không xoá được chương trình.'); return; }

        this.programs = this.programs.filter(p => p.id !== id);
        this.logAction('xoa', 'programs', 'Xóa chương trình ' + prog.name, '');
    },

    // Lưu một chương trình lên máy chủ (dùng cho các nút bật/tắt nhanh).
    async persistProgram(prog) {
        const r = await this.api('programs', 'save', {
            id: prog.id, name: prog.name, type: prog.type, status: prog.status,
            countForAttendance: !!prog.countForAttendance,
            startTime: prog.startTime, cutoffTime: prog.cutoffTime || '',
            dayOfWeek: prog.dayOfWeek, eventDate: prog.eventDate || ''
        });
        if (!r || !r.ok) window.TNTT.toast.error(r && r.error || 'Không lưu được thay đổi.');
        return !!(r && r.ok);
    },

    async toggleProgramStatus(prog) {
        const truoc = prog.status;
        prog.status = prog.status === 'kích hoạt' ? 'đã đóng' : 'kích hoạt';
        if (!await this.persistProgram(prog)) prog.status = truoc;   // lỗi -> hoàn lại
    },

    async toggleProgramAttendance(prog) {
        prog.countForAttendance = !prog.countForAttendance;
        if (!await this.persistProgram(prog)) prog.countForAttendance = !prog.countForAttendance;
    },
};
