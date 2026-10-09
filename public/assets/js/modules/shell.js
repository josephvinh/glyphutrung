/* ==========================================================
   SHELL — Tiện ích chung, theo dõi icon, khởi động
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.shell = {
    // ==========================================
    // ROUTER — expose cho Alpine template (bottom nav, sidebar)
    // ==========================================
    get router() { return window.TNTT.router; },

    // changeModule - dùng cho nút Back trong các module
    // Gọi router.navigate thay vì update trực tiếp để đồng bộ URL
    changeModule(moduleName) {
        if (window.TNTT?.router) {
            window.TNTT.router.navigate('/' + moduleName);
        }
    },

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

    /**
     * So khớp tìm kiếm thiếu nhi toàn diện:
     * 1. Tên, Tên thánh riêng lẻ
     * 2. Chuỗi kết hợp: "Tên Thánh + Tên" (VD: "teresa mai")
     * 3. Mã định danh GDGLPT
     * 4. Số điện thoại Cha hoặc Mẹ
     */
    matchStudentSearch(student, query) {
        if (!student) return false;
        if (!query) return true;
        const q = this.normalizeText(query);
        if (!q) return true;

        // 1. Tên thánh, họ tên riêng lẻ & kết hợp
        const holy = this.normalizeText(student.holyName || '');
        const name = this.normalizeText(student.name || '');
        const full = (holy + ' ' + name).trim();
        const code = this.normalizeText(student.code || '');

        if (name.includes(q) || holy.includes(q) || full.includes(q) || code.includes(q)) {
            return true;
        }

        // 2. Tìm theo số điện thoại phụ huynh (bỏ ký tự trắng/chấm/gạch nối)
        const cleanPhone = str => String(str || '').replace(/\D/g, '');
        const qDigits = q.replace(/\D/g, '');
        if (qDigits.length >= 3) {
            const fatherPhone = cleanPhone(student.fatherPhone);
            const motherPhone = cleanPhone(student.motherPhone);
            if (fatherPhone.includes(qDigits) || motherPhone.includes(qDigits)) {
                return true;
            }
        }

        return false;
    },

    genderLabel(gender) {
        return Number(gender) === 1 ? 'Nam' : 'Nữ';
    },

    parseGender(value) {
        const v = this.normalizeText(value);
        if (v === 'nu' || v === '0' || v === 'female') return 0;
        return 1;
    },

    // Chuẩn hoá ngày hợp lệ sang yyyy-mm-dd; giữ nguyên chuỗi sai để máy chủ
    // báo lỗi cụ thể "Dòng N: ngày sinh không hợp lệ" (#111)
    parseDate(value) {
        if (!value) return '';
        const dmy = value.match(/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/);
        if (dmy) return dmy[3] + '-' + dmy[2].padStart(2, '0') + '-' + dmy[1].padStart(2, '0');
        const ymd = value.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
        if (ymd) return ymd[1] + '-' + ymd[2].padStart(2, '0') + '-' + ymd[3].padStart(2, '0');
        return value; // Giữ nguyên chuỗi sai để server báo lỗi
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

    // Kiểm tra module có đang active (bao gồm cả student_profile khi key là students)
    isActiveModule(moduleKey) {
        if (this.currentModule === moduleKey) return true;
        if (moduleKey === 'students' && this.currentModule === 'student_profile') return true;
        return false;
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
            // Chỉ gọi lucide khi nó đã được định nghĩa
            if (document.querySelector('i[data-lucide]') && typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
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
            window.TNTT.toast.error('Không nhận được cấu hình từ máy chủ.\n'
                + 'Vui lòng tải lại trang hoặc đăng nhập lại.', 0);
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
        this.PASS_SCORE      = BOOT.config.passScore;
        this.PASS_ATTENDANCE = BOOT.config.passAttendance;

        // Giữ bản gốc để nút "Đặt lại mặc định" có cái mà quay về
        this.defaultPermissions = JSON.parse(JSON.stringify(this.permissions));

        // ---- Khởi tạo Offline Queue cho PWA ----
        this.initOfflineSupport();

        // Nạp dữ liệu nghiệp vụ của niên khoá đang mở.
        this.loadData();

        // Khởi tạo module mặc định (dashboard)
        this.changeModule('dashboard');

        // Khởi tạo URL router - phải sau khi changeModule đầu tiên
        if (window.TNTT?.router?.init) {
            window.TNTT.router.init();
        }

        this.initIconWatcher();

        // Gọi initCore() để khởi tạo các tính năng core và điểm danh ngoại tuyến (#121)
        if (typeof this.initCore === 'function') {
            this.initCore();
        }

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

        // Nhận lệnh tải lại từ Service Worker (khi có tin báo đẩy tới lúc web đang mở)
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', (e) => {
                if (e.data && e.data.action === 'RELOAD_DATA') {
                    this._lastLoadAt = 0; // Bỏ qua chặn 45s để tải ngay lập tức
                    this.loadData();
                }
            });
        }

        // Short Polling 4s/lần: Cập nhật dữ liệu nếu Server có thay đổi (sync.txt).
        // sync.php cực nhẹ (chỉ đọc 1 file, không đụng DB) nên 4s vẫn rẻ mà bàn
        // công tác/điểm số/thông báo của người khác hiện gần như tức thì.
        this._syncVersion = null;
        setInterval(async () => {
            if (this.syncing || document.visibilityState !== 'visible') return;
            try {
                const r = await window.TNTT.csrfFetch('api/sync.php', { cache: 'no-store' });
                const ts = await r.text();
                if (this._syncVersion && ts !== '0' && ts !== this._syncVersion) {
                    this._lastLoadAt = 0;
                    this.loadData();
                }
                this._syncVersion = ts;
            } catch (e) { }
        }, 4000);
    },

    /**
     * Khởi tạo Offline Queue cho PWA
     * - Khởi tạo IndexedDB queue
     * - Lắng nghe sự kiện sync/failed để thông báo người dùng
     */
    initOfflineSupport() {
        // Chờ OfflineQueue khả dụng (nạp từ offline-queue.js)
        const setupQueue = () => {
            if (typeof window.TNTTOfflineQueue !== 'undefined') {
                window.TNTTOfflineQueue.init().catch(err => {
                    console.error('[Shell] Failed to initialize offline queue:', err);
                });

                // Lắng nghe sự kiện sync để thông báo
                window.TNTTOfflineQueue.addListener((event, data) => {
                    if (event === 'sync' && data.synced > 0) {
                        window.TNTT.toast.success(
                            `Đã đồng bộ ${data.synced} mục${data.failed ? `, ${data.failed} thất bại` : ''}`
                        );
                        // Refresh data sau khi sync để cập nhật UI
                        if (typeof this.refreshApp === 'function') {
                            this.refreshApp();
                        }
                    } else if (event === 'failed' && data.failed > 0) {
                        window.TNTT.toast.warning('Một số thao tác offline chưa đồng bộ được');
                    } else if (event === 'online') {
                        console.log('[Shell] Online - triggering sync');
                    } else if (event === 'offline') {
                        window.TNTT.toast.info('Đang offline - thao tác sẽ được lưu vào hàng đợi');
                    }
                });
            } else {
                // Thử lại sau 100ms nếu queue chưa load
                setTimeout(setupQueue, 100);
            }
        };

        setupQueue();
    }
};
