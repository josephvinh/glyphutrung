/* ==========================================================
   SHELL — Tiện ích chung, theo dõi icon, khởi động
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.shell = {
    // ==========================================
    // CÁC HÀM TIỆN ÍCH CHUNG
    // ==========================================

    // Bỏ dấu tiếng Việt để tìm kiếm: gõ "tuong" vẫn ra "Tường", gõ "daminh" vẫn ra "Đaminh"
    normalizeText(str) {
        return (str === null || str === undefined ? '' : String(str))
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .replace(/đ/g, 'd')
            .replace(/Đ/g, 'D')
            .toLowerCase()
            .trim();
    },

    genderLabel(gender) {
        return Number(gender) === 1 ? 'Nam' : 'Nữ';
    },

    parseGender(value) {
        const v = this.normalizeText(value);
        if (v === 'nu' || v === '0' || v === 'female') return 0;
        return 1;
    },

    // Nhận cả 15/05/2017 lẫn 2017-05-15, luôn trả về dạng chuẩn yyyy-mm-dd để lưu
    parseDate(value) {
        if (!value) return '';
        const dmy = value.match(/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/);
        if (dmy) return dmy[3] + '-' + dmy[2].padStart(2, '0') + '-' + dmy[1].padStart(2, '0');
        const ymd = value.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
        if (ymd) return ymd[1] + '-' + ymd[2].padStart(2, '0') + '-' + ymd[3].padStart(2, '0');
        return '';
    },

    formatDate(dateStr) {
        if (!dateStr) return '';
        const parts = dateStr.split('-');
        if (parts.length !== 3) return dateStr;
        return parts[2] + '/' + parts[1] + '/' + parts[0];
    },

    // Cộng phút vào chuỗi 'HH:MM', tự cuộn qua nửa đêm nếu cần
    addMinutes(hhmm, minutes) {
        if (!hhmm) return '';
        const parts = hhmm.split(':');
        const total = (Number(parts[0]) * 60 + Number(parts[1]) + minutes + 1440) % 1440;
        const p = n => String(n).padStart(2, '0');
        return p(Math.floor(total / 60)) + ':' + p(total % 60);
    },

    currentTime() {
        const d = new Date();
        const p = n => String(n).padStart(2, '0');
        return p(d.getHours()) + ':' + p(d.getMinutes());
    },

    // Date -> 'yyyy-mm-dd' theo giờ địa phương (toISOString sẽ lệch ngày vì quy về UTC)
    toDateInput(date) {
        const p = n => String(n).padStart(2, '0');
        return date.getFullYear() + '-' + p(date.getMonth() + 1) + '-' + p(date.getDate());
    },

    weekdayLabel(value) {
        const w = this.weekdays.find(x => x.value === value);
        return w ? w.label : '';
    },

    // 'yyyy-mm-dd' -> 'Chúa Nhật, 23/08/2026'
    formatFullDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr + 'T00:00:00');
        return this.weekdayLabel(d.getDay()) + ', ' + this.formatDate(dateStr);
    },

    // Mô tả lịch của 1 chương trình để in lên thẻ
    programSchedule(prog) {
        if (prog.type === 'chiến dịch') return prog.eventDate ? this.formatDate(prog.eventDate) : 'Chưa chọn ngày';
        return this.weekdayLabel(prog.dayOfWeek) + ' hàng tuần';
    },

    // 'yyyy-mm-dd hh:mm' - dùng cho mốc thời gian nộp / duyệt đơn
    timestamp() {
        const d = new Date();
        return this.toDateInput(d) + ' ' + this.currentTime();
    },

    todayStamp() {
        const d = new Date();
        const p = n => String(n).padStart(2, '0');
        return p(d.getDate()) + '-' + p(d.getMonth() + 1) + '-' + d.getFullYear();
    },

    changeModule(moduleName) {
        this.currentModule = moduleName;
        // Pure JavaScript module switching - no Alpine x-show dependency
        document.querySelectorAll('[data-module]').forEach(el => {
            el.style.display = el.dataset.module === moduleName ? '' : 'none';
        });
        window.scrollTo({ top: 0, behavior: 'instant' });
    },

    // ==========================================
    // TRÌNH THEO DÕI ICON
    //
    // lucide.createIcons() THAY THẾ thẻ <i data-lucide> bằng <svg>.
    // Mỗi khi Alpine dựng lại DOM (x-for thêm dòng mới, x-if bật lại,
    // đổi tab, mở popup...) thì thẻ <i> mới sinh ra lại nằm im vì
    // không ai gọi createIcons() nữa -> icon biến mất.
    //
    // Thay vì rải lời gọi ở từng chỗ (rải bao nhiêu cũng sót), đặt một
    // MutationObserver canh cả trang: hễ thấy còn <i data-lucide> chưa
    // được chuyển thì vẽ lại. Không sợ lặp vô hạn vì sau khi vẽ xong
    // không còn thẻ <i> nào để kích hoạt vòng sau.
    // ==========================================
    initIconWatcher() {
        let scheduled = false;

        const render = () => {
            scheduled = false;
            if (document.querySelector('i[data-lucide]')) lucide.createIcons();
        };

        const schedule = () => {
            if (scheduled) return;
            scheduled = true;
            requestAnimationFrame(render);
        };

        new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true });
        schedule();
    },

    init() {
        const BOOT = window.TNTT_BOOT || null;

        const resetLimit = () => { this.displayLimit = 20; };
        this.$watch('searchQuery', resetLimit);
        this.$watch('filterStatus', resetLimit);
        this.$watch('filterBlock', resetLimit);
        this.$watch('filterClass', resetLimit);

        this.attendanceDate = this.toDateInput(new Date());
        this.leaveDate = this.toDateInput(new Date());
        this.statMonth = this.toDateInput(new Date()).slice(0, 7);
        this.syncLeaveProgram();

        // Đổi ngày xin phép thì chương trình đang chọn có thể không còn diễn ra
        this.$watch('leaveDate', () => this.syncLeaveProgram());
        // Đổi ngày điểm danh thì thoát khỏi phiên đang mở
        this.$watch('attendanceDate', () => { this.activeSession = null; });
        // Đổi đối tượng nhận thì bỏ lựa chọn khối/lớp cũ
        this.$watch('announcementForm.audienceType', () => { this.announcementForm.audienceValue = ''; });

        // ---- Nạp cấu hình thật từ máy chủ ----
        if (!BOOT) {
            // app.js chỉ được nạp sau khi đăng nhập, nên TNTT_BOOT
            // luôn phải có. Thiếu nghĩa là máy chủ hỏng. Báo thẳng —
            // trước đây chỗ này âm thầm rơi về dữ liệu giả, khiến màn
            // hình hiện số liệu bịa mà không ai biết.
            alert('Không nhận được cấu hình từ máy chủ.\n'
                + 'Vui lòng tải lại trang hoặc đăng nhập lại.');
            throw new Error('TNTT_BOOT không tồn tại');
        }
        Object.assign(this.user, BOOT.user);
        this.year        = BOOT.year;
        this.terms       = BOOT.terms;
        this.roleDefs    = BOOT.roles;
        this.blocks      = BOOT.blocks;
        this.classes     = BOOT.classes;
        this.permissions = BOOT.permissions;
        this.moduleEnabled = BOOT.moduleEnabled;
        // Giữ nguyên icon/màu đã khai trong moduleDefs, chỉ lấy thứ tự
        // và nhãn từ máy chủ để Quản trị đổi được mà không phải sửa mã.
        this.moduleDefs = BOOT.modules.map(m => {
            const local = this.moduleDefs.find(x => x.key === m.key) || {};
            return Object.assign({}, local, m);
        });
        this.CUTOFF_MINUTES  = BOOT.config.cutoffMinutes;
        this.PASS_SCORE      = BOOT.config.passScore;
        this.PASS_ATTENDANCE = BOOT.config.passAttendance;

        // Giữ bản gốc để nút "Đặt lại mặc định" có cái mà quay về
        this.defaultPermissions = JSON.parse(JSON.stringify(this.permissions));

        // Nạp dữ liệu nghiệp vụ của niên khoá đang mở.
        this.loadData();

        // Khởi tạo module mặc định (dashboard)
        this.changeModule('dashboard');

        this.initIconWatcher();

        // Dò tình trạng thông báo đẩy của máy này. Không hỏi quyền ở đây —
        // trình duyệt chỉ cho hỏi khi người dùng chạm vào nút, và hỏi ngay
        // lúc mở app thì hầu hết mọi người bấm Chặn cho xong.
        this.pushKhoiDong();

        // Nhịp đồng hồ: để badge "đã quá giờ chốt" tự bật khi tới 07:30
        // mà GLV không phải tải lại trang.
        setInterval(() => { this.nowTs = Date.now(); }, 30000);

        // Tự đồng bộ khi MỞ LẠI app (chuyển tab về / mở PWA từ nền). Nhờ vậy
        // thay đổi của người khác hiện ra mà không cần bấm Làm mới — bớt cảm
        // giác "không realtime". Chỉ nạp lại nếu đã hơn 45s từ lần nạp trước
        // (tránh nạp dồn khi bật/tắt nhanh); nạp NGẦM, không hiện toast.
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible'
                && !this.syncing
                && Date.now() - (this._lastLoadAt || 0) > 45000) {
                this.loadData();
            }
        });
    }
};
