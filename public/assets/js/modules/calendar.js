/* ==========================================================
   CALENDAR — Lịch trình hiển thị chương trình/sự kiện theo tháng
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.calendar = {
    // ==========================================
    // 1. DATA
    // ==========================================
    currentDate: new Date(),
    showEventDetailModal: false,
    selectedEvent: null,

    // Tên các ngày trong tuần (viết tắt theo tiếng Việt)
    weekDays: ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'],

    // ==========================================
    // 2. GETTERS
    // ==========================================

    // Nhãn tháng hiển thị: "Tháng 8, 2026"
    get calendarMonthLabel() {
        const month = this.currentDate.getMonth();
        const year = this.currentDate.getFullYear();
        const monthNames = ['Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6',
                           'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12'];
        return monthNames[month] + ', ' + year;
    },

    // HƯỚNG B: Lấy lịch của lớp đang chọn (hoặc tất cả nếu không chọn)
    get calendarSchedules() {
        return this.classSchedules || [];
    },

    // Tạo lưới ngày cho lịch tháng
    get calendarDays() {
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();
        const today = new Date();
        const todayStr = this.toDateInput(today);

        // Ngày đầu tiên của tháng
        const firstDay = new Date(year, month, 1);
        // Ngày cuối cùng của tháng
        const lastDay = new Date(year, month + 1, 0);

        const days = [];
        const startDow = firstDay.getDay(); // 0 = CN, 1 = T2, ...

        // Thêm các ô trống trước ngày đầu tháng
        for (let i = 0; i < startDow; i++) {
            days.push({
                key: 'empty-' + i,
                day: '',
                isEmpty: true,
                isToday: false,
                events: []
            });
        }

        // Thêm các ngày trong tháng
        for (let d = 1; d <= lastDay.getDate(); d++) {
            const dateObj = new Date(year, month, d);
            const dateStr = this.toDateInput(dateObj);
            const dow = dateObj.getDay();

            // HƯỚNG B: Ưu tiên lịch lớp nếu có, không thì dùng programs
            const events = [];

            // Lịch lớp áp dụng cho ngày này (để tránh vẽ TRÙNG với program toàn
            // đoàn mà nó bắt nguồn): thu các programId đã được lịch lớp phủ.
            const daySchedules = this.calendarSchedules.filter(cs => {
                if (cs.dayOfWeek !== dow) return false;
                if (cs.activeFrom && dateStr < cs.activeFrom) return false;
                if (cs.activeTo && dateStr > cs.activeTo) return false;
                return true;
            });
            const coveredProgramIds = new Set(daySchedules.map(cs => cs.programId).filter(Boolean));

            // 1) Chương trình bắt buộc (cách cũ - toàn đoàn), bỏ cái đã có lịch lớp phủ
            this.programs.filter(p => {
                if (p.status !== 'kích hoạt') return false;
                if (p.type === 'bắt buộc') return p.dayOfWeek === dow && !coveredProgramIds.has(p.id);
                return false;
            }).forEach(p => {
                events.push({
                    id: p.id,
                    title: p.name,
                    time: p.startTime,
                    color: 'amber',
                    type: 'program',
                    scheduleId: null
                });
            });

            // 2) Chiến dịch (ngày cụ thể)
            this.programs.filter(p => {
                if (p.status !== 'kích hoạt') return false;
                if (p.type === 'chiến dịch') return p.eventDate === dateStr;
                return false;
            }).forEach(p => {
                events.push({
                    id: p.id,
                    title: p.name,
                    time: p.startTime,
                    color: 'rose',
                    type: 'program',
                    scheduleId: null
                });
            });

            // 3) HƯỚNG B: Lịch lớp riêng (classSchedules) — đã lọc ở daySchedules
            daySchedules.forEach(cs => {
                // Gộp nếu cùng giờ và program, hoặc hiện cả hai
                events.push({
                    id: cs.id,
                    title: cs.className + (cs.slot ? ` (${cs.slot})` : ''),
                    time: cs.startTime,
                    color: cs.slot === 'sáng' ? 'amber' : cs.slot === 'chiều' ? 'blue' : 'indigo',
                    type: 'schedule',
                    scheduleId: cs.id,
                    programId: cs.programId,
                    programName: cs.programName,
                    classId: cs.classId,
                    slot: cs.slot
                });
            });

            // Sắp xếp theo giờ
            events.sort((a, b) => a.time.localeCompare(b.time));

            days.push({
                key: dateStr,
                day: d,
                dateStr: dateStr,
                isEmpty: false,
                isToday: dateStr === todayStr,
                events: events
            });
        }

        return days;
    },

    // Các chương trình diễn ra trong tháng hiện tại
    get programsInMonth() {
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const firstStr = this.toDateInput(firstDay);
        const lastStr = this.toDateInput(lastDay);

        return this.programs.filter(p => {
            if (p.status !== 'kích hoạt') return false;
            if (p.type === 'bắt buộc') {
                // Chương trình bắt buộc: hiện nếu có ngày trong tháng rơi vào thứ đó
                // Lấy tất cả các ngày trong tháng có cùng dayOfWeek
                for (let d = firstDay; d <= lastDay; d.setDate(d.getDate() + 7)) {
                    if (d.getMonth() === month) return true;
                }
                return false;
            } else {
                // Chiến dịch: kiểm tra ngày có trong tháng không
                return p.eventDate >= firstStr && p.eventDate <= lastStr;
            }
        });
    },

    // HƯỚNG B: Các lịch lớp diễn ra trong tháng hiện tại
    get schedulesInMonth() {
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const firstStr = this.toDateInput(firstDay);
        const lastStr = this.toDateInput(lastDay);

        // Với lịch theo thứ, kiểm tra có ngày nào trong tháng khớp
        return this.calendarSchedules.filter(cs => {
            const dow = cs.dayOfWeek;
            // Kiểm tra từng ngày trong tháng
            for (let d = new Date(firstDay); d <= lastDay; d.setDate(d.getDate() + 1)) {
                if (d.getMonth() !== month) continue;
                const dateStr = this.toDateInput(d);
                if (d.getDay() !== dow) continue;
                // Kiểm tra active_from/active_to
                if (cs.activeFrom && dateStr < cs.activeFrom) continue;
                if (cs.activeTo && dateStr > cs.activeTo) continue;
                return true;
            }
            return false;
        });
    },

    // ==========================================
    // 3. METHODS
    // ==========================================

    // Chuyển sang tháng trước
    prevMonth() {
        const d = new Date(this.currentDate);
        d.setMonth(d.getMonth() - 1);
        this.currentDate = d;
    },

    // Chuyển sang tháng sau
    nextMonth() {
        const d = new Date(this.currentDate);
        d.setMonth(d.getMonth() + 1);
        this.currentDate = d;
    },

    // Mở chi tiết sự kiện
    showEventDetail(event) {
        this.selectedEvent = event;
        this.showEventDetailModal = true;
    }
};
