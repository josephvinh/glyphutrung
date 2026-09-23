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

            // Lấy các chương trình diễn ra trong ngày này (đa-thứ + khoảng ngày)
            const events = this.programs.filter(p => this.programOccursOn(p, dateStr));

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
            // Hiện nếu chương trình diễn ra vào bất kỳ ngày nào trong tháng
            for (let d = new Date(firstDay); d <= lastDay; d.setDate(d.getDate() + 1)) {
                if (this.programOccursOn(p, this.toDateInput(d))) return true;
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
