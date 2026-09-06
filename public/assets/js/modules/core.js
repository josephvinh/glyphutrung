/* ==========================================================
   CORE — Người dùng, lớp gọi máy chủ, nhật ký, sổ module, phân quyền, danh mục khối lớp
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};

// Boot data from server (populated by _bootstrap_page.php)
window.TNTT.boot = window.TNTT_BOOT || {};

// CSRF token from server boot data
window.TNTT.csrfToken = window.TNTT.boot.csrfToken || '';

/**
 * Fetch wrapper that automatically includes CSRF token in headers.
 * Use this for all POST/PUT/DELETE requests.
 */
window.TNTT.csrfFetch = async (url, options = {}) => {
    return fetch(url, {
        ...options,
        method: options.method || 'GET',
        headers: {
            ...options.headers,
            'X-CSRF-TOKEN': window.TNTT.csrfToken,
            'Content-Type': 'application/json',
        },
    });
};
window.TNTT.core = {
    currentModule: 'dashboard',

    // memberId nối sang bảng members. Khi làm Đăng nhập thì đây chính
    // là bản ghi được nạp ra sau khi xác thực.
    user: {
        memberId: 1,
        holyName: 'Phêrô',
        fullName: 'Nguyễn Văn A',
        phone: '0901000001',
        role: 'admin',
        roleTitle: 'Quản Trị Hệ Thống',
        managedBlock: 'Khai Tâm',
        assignedClass: 'Khai Tâm 1A'
    },

    // Member assignments from boot data
    assignments: window.TNTT.boot?.assignments || [],
    primaryAssignment: window.TNTT.boot?.primaryAssignment || null,

    // Dark mode support
    dark: localStorage.getItem('darkMode') === 'true'
        || (localStorage.getItem('darkMode') === null
            && window.matchMedia('(prefers-color-scheme: dark)').matches),

    init() {
        // Apply saved dark mode state
        this.applyDarkMode();
    },

    applyDarkMode() {
        document.documentElement.classList.toggle('dark', this.dark);
    },

    toggleDark() {
        this.dark = !this.dark;
    },

    $watch: {
        dark(val) {
            this.applyDarkMode();
            localStorage.setItem('darkMode', val);
        }
    },

    // ==========================================
    // 0. CÁ NHÂN
    // ==========================================
    showProfileForm: false,
    profileForm: {},
    year: null,              // niên khoá đang mở
    syncing: false,          // đang nạp dữ liệu

    // ==========================================
    // LỚP GỌI MÁY CHỦ
    //
    // Cách làm: cập nhật màn hình TRƯỚC rồi mới gọi máy chủ, để GLV
    // không phải chờ vòng mạng khi điểm danh giữa nhà thờ sóng yếu.
    // Nếu máy chủ từ chối thì báo lỗi và tải lại trang — dữ liệu
    // đồng bộ lại từ đầu, không để màn hình nói một đằng CSDL một nẻo.
    // ==========================================
    async api(file, action, body) {
        try {
            const headers = { 'Content-Type': 'application/json' };
            // Gửi CSRF token nếu có trong session
            if (window.TNTT?.csrfToken) {
                headers['X-CSRF-TOKEN'] = window.TNTT.csrfToken;
            }
            const res = await fetch('api/' + file + '.php?action=' + action, {
                method: 'POST',
                headers,
                body: JSON.stringify(body || {})
            });
            if (res.status === 401) return { ok: false, error: 'Phiên đăng nhập đã hết hạn.' };
            if (res.status === 403) return { ok: false, error: 'Yêu cầu không hợp lệ (CSRF). Vui lòng tải lại trang.' };
            return await res.json();
        } catch (e) {
            return { ok: false, error: 'Mất kết nối máy chủ. Kiểm tra lại mạng.' };
        }
    },

    async save(file, action, body) {
        const r = await this.api(file, action, body);
        if (!r.ok) {
            window.TNTT.toast.error(r.error || 'Có lỗi xảy ra. Trang sẽ tải lại để đồng bộ dữ liệu.');
            location.reload();
        }
        return r;
    },

    // Nhập CSV: giao diện chỉ tách file, còn ghi vào đâu là việc của
    // máy chủ — cả file nhập trong MỘT giao dịch, sai một dòng thì
    // hoàn tác sạch, không để danh sách nhập được nửa vời.
    async importToServer(rows, colIndex, fileName) {
        const get = (row, key) => (colIndex[key] !== undefined ? (row[colIndex[key]] || '').trim() : '');
        const payload = rows.map(row => ({
            code:        get(row, 'code'),
            holyName:    get(row, 'holyName'),
            name:        get(row, 'name'),
            gender:      this.parseGender(get(row, 'gender')),
            birthDate:   this.parseDate(get(row, 'birthDate')),
            address:     get(row, 'address'),
            fatherName:  get(row, 'fatherName'),
            fatherPhone: get(row, 'fatherPhone'),
            motherName:  get(row, 'motherName'),
            motherPhone: get(row, 'motherPhone'),
            className:   get(row, 'className'),
            status:      this.statusOptions.includes(get(row, 'status').toLowerCase())
                         ? get(row, 'status').toLowerCase() : 'đang sinh hoạt'
        }));

        const r = await this.save('students', 'import', { rows: payload });
        if (!r.ok) return;

        await this.loadData();
        // Nêu rõ phạm vi ghi: chủ nhiệm cần thấy danh sách vừa vào lớp nào
        let msg = 'Đã nạp file: ' + fileName + ' - Thêm mới: ' + r.added + ' em, Cập nhật: ' + r.updated + ' em, Bỏ qua: ' + r.skipped + ' dòng';
        if (r.scope) msg += ' - Phạm vi: ' + r.scope;
        if (r.errors && r.errors.length) msg += '. ' + r.errors.length + ' lỗi';
        window.TNTT.toast.success(msg);
    },

    // Nạp toàn bộ dữ liệu nghiệp vụ của niên khoá đang mở
    async loadData() {
        this.syncing = true;
        try {
            const res = await fetch('api/data.php');
            const d = await res.json();
            if (!d.ok) { window.TNTT.toast.error(d.error || 'Không nạp được dữ liệu.'); return false; }

            this.students          = d.students;
            // Sĩ số mọi lớp, đếm ở máy chủ. Cần vì this.students nay chỉ
            // gồm phạm vi mình được xem, không đếm được lớp ngoài phạm vi.
            this.classCounts       = d.classCounts || {};
            this.programs          = d.programs;
            this.attendances       = d.attendances;
            this.leaveRequests     = d.leaveRequests;
            this.scores            = d.scores;
            this.reports           = d.reports;
            this.announcements     = d.announcements;
            this.readAnnouncements = d.readAnnouncements;
            this.members           = d.members;
            this.logs              = d.logs;
            return true;
        } catch (e) {
            window.TNTT.toast.error('Không nạp được dữ liệu từ máy chủ.');
            return false;
        } finally {
            this.syncing = false;
            this.$nextTick(() => lucide.createIcons());
        }
    },

    // Làm mới thủ công — thay cho thao tác "kéo xuống" mà PWA cài ra màn hình
    // chính không có. Chỉ nạp lại dữ liệu (nhẹ, không chớp trắng như tải lại
    // cả trang); báo cho người dùng biết đã đồng bộ.
    async refreshApp() {
        if (this.syncing) return;
        if (await this.loadData()) window.TNTT.toast.success('Đã đồng bộ dữ liệu mới nhất.');
    },

    // Đăng xuất thật, gọi lên máy chủ để hủy phiên
    async logout() {
        if (!confirm('Đăng xuất khỏi hệ thống?')) return;
        try {
            await fetch('api/auth.php?action=logout', { method: 'POST' });
        } catch (e) { /* mất mạng thì vẫn tải lại để về màn đăng nhập */ }
        location.reload();
    },

    // Cá nhân không còn là màn riêng — nay là thẻ đầu trong Cài Đặt.
    openProfile() {
        this.openSettings('profile');
    },

    get currentMember() {
        return this.members.find(m => m.id === this.user.memberId) || null;
    },

    // Phạm vi phụ trách viết thành một câu cho gọn
    get myScopeLabel() {
        const a = this.assignments || [];
        if (this.isUnrestrictedScope) return 'Toàn đoàn';
        if (!a.length) {   // fallback theo phân công chính
            const scope = this.roleScope(this.user.role);
            if (scope === 'khối') return 'Khối ' + this.user.managedBlock;
            return 'Lớp ' + this.user.assignedClass;
        }
        const parts = [...new Set(a.map(x =>
            x.className ? x.className : (x.blockName ? 'Khối ' + x.blockName : 'Toàn đoàn')))];
        return parts.join(' · ');
    },

    // Chỉ sửa được thông tin của chính mình; vai trò và phân công
    // là việc của Ban Điều Hành bên màn Khối & Lớp.
    // ---- ĐỔI MẬT KHẨU ----
    showChangePw: false,
    pwForm: { current: '', next: '', confirm: '' },
    pwShow: false,
    pwBusy: false,

    openChangePassword() {
        this.pwForm = { current: '', next: '', confirm: '' };
        this.pwShow = false;
        this.showChangePw = true;
    },

    async submitChangePassword() {
        const f = this.pwForm;
        if (!f.current)                 return window.TNTT.toast.warning('Vui lòng nhập mật khẩu hiện tại.');
        if (f.next.length < 6)          return window.TNTT.toast.warning('Mật khẩu mới phải từ 6 ký tự trở lên.');
        if (f.next === f.current)       return window.TNTT.toast.warning('Mật khẩu mới phải khác mật khẩu cũ.');
        if (f.next !== f.confirm)       return window.TNTT.toast.warning('Hai ô mật khẩu mới chưa khớp nhau.');

        this.pwBusy = true;
        try {
            const r = await this.api('auth', 'password', { current: f.current, new: f.next });
            if (!r.ok) { window.TNTT.toast.error(r.error || 'Không đổi được mật khẩu.'); return; }
            this.showChangePw = false;
            this.pwForm = { current: '', next: '', confirm: '' };
            this.logAction('sua', 'profile', 'Đổi mật khẩu', '');
            window.TNTT.toast.success('Đã đổi mật khẩu. Lần đăng nhập sau hãy dùng mật khẩu mới.');
        } finally {
            this.pwBusy = false;
        }
    },

    openProfileForm() {
        this.profileForm = {
            holyName: this.user.holyName,
            fullName: this.user.fullName,
            phone: this.user.phone || '',
            birthDate: this.user.birthDate || ''
        };
        this.showProfileForm = true;
    },

    saveProfile() {
        const f = this.profileForm;
        if (!f.fullName.trim()) { window.TNTT.toast.warning('Vui lòng nhập họ và tên!'); return; }

        this.user.holyName = f.holyName.trim();
        this.user.fullName = f.fullName.trim();
        this.user.phone = f.phone.trim();
        this.user.birthDate = f.birthDate || '';

        const m = this.currentMember;
        if (m) {
            m.holyName = this.user.holyName; m.fullName = this.user.fullName;
            m.phone = this.user.phone; m.birthDate = this.user.birthDate;
        }

        this.logAction('sua', 'profile', 'Cập nhật thông tin cá nhân', this.user.fullName);
        this.showProfileForm = false;
        this.save('auth', 'profile', {
            holyName: this.user.holyName, fullName: this.user.fullName,
            phone: this.user.phone, birthDate: this.user.birthDate
        });
    },

    // ---- VIỆC CẦN LÀM ----
    get currentTerm() {
        const today = this.toDateInput(new Date());
        return this.terms.find(t => today >= t.from && today <= t.to) || this.terms[0];
    },

    get myTasks() {
        const tasks = [];
        const today = this.toDateInput(new Date());
        const scope = this.accessibleStudents.filter(s => s.status === 'đang sinh hoạt');

        // Buổi hôm nay còn dang dở
        if (this.canAccess('attendance') && !this.isUnderMaintenance('attendance')) {
            this.programsOn(today).forEach(p => {
                const session = { programId: p.id, date: today };
                const done = scope.filter(s => this.attendanceRecord(s.id, session)).length;
                if (scope.length > 0 && done < scope.length) {
                    tasks.push({
                        key: 'att-' + p.id, icon: 'clipboard-check',
                        cls: 'bg-blue-50 text-blue-600 border-blue-100',
                        text: 'Điểm danh ' + p.name,
                        detail: 'mới ghi ' + done + '/' + scope.length + ' em · chốt lúc ' + this.cutoffOf(p),
                        go: 'attendance'
                    });
                }
            });
        }

        // Đơn chờ tôi duyệt
        if (this.canApproveLeave && this.pendingLeaveCount > 0) {
            tasks.push({
                key: 'leave', icon: 'file-text',
                cls: 'bg-amber-50 text-amber-600 border-amber-100',
                text: 'Duyệt ' + this.pendingLeaveCount + ' đơn xin phép',
                detail: 'đang chờ trong phạm vi của bạn',
                go: 'leave'
            });
        }

        // Phiếu liên lạc còn thiếu
        if (this.canWriteReports && !this.isUnderMaintenance('reports')) {
            const term = this.currentTerm;
            const chua = scope.filter(s => !this.reportOf(s.id, term.id)).length;
            if (chua > 0) {
                tasks.push({
                    key: 'report', icon: 'clipboard-list',
                    cls: 'bg-emerald-50 text-emerald-600 border-emerald-100',
                    text: 'Lập ' + chua + ' phiếu liên lạc',
                    detail: term.name + ' · còn thiếu',
                    go: 'reports'
                });
            }
        }

        // Thông báo chưa đọc
        if (this.unreadAnnouncementCount > 0) {
            tasks.push({
                key: 'ann', icon: 'megaphone',
                cls: 'bg-rose-50 text-rose-600 border-rose-100',
                text: 'Đọc ' + this.unreadAnnouncementCount + ' thông báo mới',
                detail: 'từ Ban Điều Hành',
                go: 'announcements'
            });
        }

        return tasks;
    },

    // ---- HOẠT ĐỘNG CỦA TÔI ----
    get myLogs() {
        return this.logs.filter(l => l.actor === this.user.fullName);
    },

    get myActivity() {
        const mine = this.myLogs;
        const week = Date.now() - 7 * 86400000;
        return {
            total: mine.length,
            week: mine.filter(l => l.ts >= week).length,
            lastAt: mine.length ? mine[0].at : ''
        };
    },

    // ==========================================
    // 0. NHẬT KÝ THAO TÁC
    //
    // Chỉ ghi việc ĐÁNG TRA CỨU, không ghi mọi cú chạm — nếu ghi hết
    // thì một buổi điểm danh 50 em đã đẩy mọi thứ khác ra khỏi màn hình.
    // Riêng điểm danh chỉ ghi khi sửa SAU GIỜ CHỐT, vì đó mới là lúc
    // con số ảnh hưởng tới điểm chuyên cần cuối năm.
    // ==========================================
    logs: [],
    LOG_LIMIT: 300,

    logDefs: {
        tao:        { icon: 'plus',        label: 'Tạo',        cls: 'bg-blue-50 text-blue-600 border-blue-100' },
        sua:        { icon: 'pencil',      label: 'Sửa',        cls: 'bg-amber-50 text-amber-600 border-amber-100' },
        xoa:        { icon: 'trash-2',     label: 'Xóa',        cls: 'bg-rose-50 text-rose-600 border-rose-100' },
        duyet:      { icon: 'check',       label: 'Duyệt',      cls: 'bg-emerald-50 text-emerald-600 border-emerald-100' },
        tuchoi:     { icon: 'x',           label: 'Từ chối',    cls: 'bg-rose-50 text-rose-600 border-rose-100' },
        diemdanh:   { icon: 'clock',       label: 'Điểm danh',  cls: 'bg-amber-50 text-amber-600 border-amber-100' },
        phanquyen:  { icon: 'shield-check', label: 'Phân quyền', cls: 'bg-indigo-50 text-indigo-600 border-indigo-100' },
        baotri:     { icon: 'wrench',      label: 'Bảo trì',    cls: 'bg-slate-100 text-slate-600 border-slate-200' }
    },

    logSearch: '',
    logFilter: '',

    // action: khóa trong logDefs · what: một câu đọc là hiểu · detail: bổ nghĩa
    logAction(action, module, what, detail) {
        this.logs.unshift({
            id: Date.now() + Math.random(),
            at: this.timestamp(),
            ts: Date.now(),
            actor: this.user.fullName,
            role: this.user.role,
            action: action,
            module: module,
            what: what,
            detail: detail || ''
        });
        if (this.logs.length > this.LOG_LIMIT) this.logs.length = this.LOG_LIMIT;
    },

    get filteredLogs() {
        const q = this.normalizeText(this.logSearch);
        return this.logs
            .filter(l => this.logFilter === '' || l.action === this.logFilter)
            .filter(l => q === ''
                || this.normalizeText(l.actor).includes(q)
                || this.normalizeText(l.what).includes(q)
                || this.normalizeText(l.detail).includes(q));
    },

    // Gom theo ngày để nhìn phát là biết chuyện xảy ra hôm nào
    get groupedLogs() {
        const groups = [];
        this.filteredLogs.forEach(l => {
            const day = l.at.slice(0, 10);
            let g = groups.find(x => x.day === day);
            if (!g) { g = { day: day, label: this.dayLabel(day), items: [] }; groups.push(g); }
            g.items.push(l);
        });
        return groups;
    },

    dayLabel(dateStr) {
        const today = this.toDateInput(new Date());
        const y = new Date(); y.setDate(y.getDate() - 1);
        if (dateStr === today) return 'Hôm nay';
        if (dateStr === this.toDateInput(y)) return 'Hôm qua';
        return this.formatFullDate(dateStr);
    },

    timeAgo(ts) {
        const s = Math.floor((this.nowTs - ts) / 1000);
        if (s < 60) return 'vừa xong';
        if (s < 3600) return Math.floor(s / 60) + ' phút trước';
        if (s < 86400) return Math.floor(s / 3600) + ' giờ trước';
        return Math.floor(s / 86400) + ' ngày trước';
    },

    clearLogs() {
        if (confirm('Xóa toàn bộ nhật ký thao tác?\nViệc này không hoàn tác được.')) {
            this.logs = [];
            this.save('settings', 'clearLogs', {});
            this.logAction('xoa', 'settings', 'Xóa toàn bộ nhật ký thao tác', '');
        }
    },

    // ==========================================
    // 0a. SỔ ĐĂNG KÝ MODULE
    //
    // Lưới App Center, bảng phân quyền và công tắc bảo trì đều đọc từ
    // danh sách này. Thêm module mới chỉ cần khai một dòng ở đây,
    // không phải sửa tay ba chỗ.
    // area: 'glv' = khu nghiệp vụ chung, 'bdh' = khu điều hành
    // ==========================================
    moduleDefs: [
        { key: 'students',      label: 'Thiếu Nhi',    icon: 'users',           color: 'text-blue-600',   area: 'glv' },
        { key: 'attendance',    label: 'Điểm danh',    icon: 'clipboard-check', color: 'text-blue-600',   area: 'glv' },
        { key: 'leave',         label: 'Xin phép',     icon: 'file-text',       color: 'text-blue-600',   area: 'glv', badge: 'leave' },
        { key: 'birthdays',     label: 'Sinh nhật',    icon: 'cake',            color: 'text-rose-500',   area: 'glv', badge: 'birthday' },
        { key: 'stats',         label: 'Thống kê',     icon: 'bar-chart-3',     color: 'text-emerald-600', area: 'glv' },
        { key: 'analytics',     label: 'Phân tích',    icon: 'bar-chart-2',     color: 'text-purple-600', area: 'glv' },
        { key: 'org',           label: 'Khối lớp',     icon: 'layers',          color: 'text-indigo-600', area: 'glv' },
        // reports + scores gộp vào tile "Thiếu Nhi" (mở qua thẻ), ẩn khỏi lưới
        { key: 'reports',       label: 'Sổ liên lạc',  icon: 'clipboard-list',  color: 'text-amber-600',  area: 'glv', hidden: true },
        { key: 'scores',        label: 'Điểm số',      icon: 'graduation-cap',  color: 'text-violet-600', area: 'glv', hidden: true },
        // Khu điều hành: nền tối, icon dùng text-white
        { key: 'promotion',     label: 'Lên lớp',      icon: 'trending-up',     color: 'text-white',      area: 'bdh' },
        { key: 'programs',      label: 'Chương trình', icon: 'calendar-plus',   color: 'text-white',      area: 'bdh' },
        { key: 'calendar',      label: 'Lịch trình',   icon: 'calendar-days',   color: 'text-white',      area: 'bdh' },
        { key: 'announcements', label: 'Thông báo',    icon: 'megaphone',       color: 'text-white',      area: 'bdh' },
        { key: 'staff',         label: 'Nhân sự',      icon: 'user-cog',        color: 'text-white',      area: 'bdh', badge: 'staff' },
        { key: 'years',         label: 'Niên khoá',    icon: 'calendar-range',  color: 'text-white',      area: 'bdh' }
    ],

    // Công tắc bảo trì. Tắt thì mọi người thấy nút mờ kèm nhãn "Bảo trì",
    // riêng Quản trị vẫn vào được để kiểm tra trước khi mở lại.
    moduleEnabled: {
        students: true, attendance: true, leave: true, birthdays: true,
        stats: true, analytics: true, org: true, reports: true, programs: true, announcements: true,
        scores: true, promotion: true, calendar: true
    },

    // ==========================================
    // 0b. PHÂN QUYỀN
    //
    //   none = không thấy module
    //   view = vào xem, không sửa
    //   edit = toàn quyền trong phạm vi của vai trò
    //
    // Bảng này thay cho các câu lệnh kiểm tra vai trò rải rác trước đây,
    // nên Quản trị đổi quyền là cả app đổi theo ngay.
    // ==========================================
    permissions: {
        students:      { admin: 'edit', bdh: 'edit', truong_khoi: 'view', glv_chu_nhiem: 'view', glv: 'view' },
        attendance:    { admin: 'edit', bdh: 'edit', truong_khoi: 'edit', glv_chu_nhiem: 'edit', glv: 'edit' },
        leave:         { admin: 'edit', bdh: 'edit', truong_khoi: 'edit', glv_chu_nhiem: 'edit', glv: 'view' },
        birthdays:     { admin: 'view', bdh: 'view', truong_khoi: 'view', glv_chu_nhiem: 'view', glv: 'view' },
        stats:         { admin: 'view', bdh: 'view', truong_khoi: 'view', glv_chu_nhiem: 'view', glv: 'view' },
        analytics:     { admin: 'view', bdh: 'view', truong_khoi: 'view', glv_chu_nhiem: 'view', glv: 'view' },
        org:           { admin: 'edit', bdh: 'edit', truong_khoi: 'view', glv_chu_nhiem: 'view', glv: 'view' },
        reports:       { admin: 'edit', bdh: 'edit', truong_khoi: 'edit', glv_chu_nhiem: 'edit', glv: 'view' },
        programs:      { admin: 'edit', bdh: 'edit', truong_khoi: 'none', glv_chu_nhiem: 'none', glv: 'none' },
        announcements: { admin: 'edit', bdh: 'edit', truong_khoi: 'edit', glv_chu_nhiem: 'view', glv: 'view' },
        scores:        { admin: 'edit', bdh: 'edit', truong_khoi: 'edit', glv_chu_nhiem: 'edit', glv: 'edit' },
        promotion:     { admin: 'edit', bdh: 'edit', truong_khoi: 'view', glv_chu_nhiem: 'none', glv: 'none' },
        calendar:       { admin: 'view', bdh: 'view', truong_khoi: 'view', glv_chu_nhiem: 'view', glv: 'view' }
    },

    permOf(key) {
        const row = this.permissions[key];
        return row ? (row[this.user.role] || 'none') : 'none';
    },

    canAccess(key) {
        return this.permOf(key) !== 'none';
    },

    canEditModule(key) {
        return this.permOf(key) === 'edit';
    },

    // Quản trị luôn vào được để kiểm tra sau khi bảo trì xong
    isUnderMaintenance(key) {
        return !this.moduleEnabled[key] && this.user.role !== 'admin';
    },

    // Nút trên lưới App Center: thấy được và bấm được hay không
    visibleModules(area) {
        return this.moduleDefs.filter(m => m.area === area && !m.hidden && this.canAccess(m.key));
    },

    moduleBadge(key) {
        if (key === 'leave')     return this.canApproveLeave ? this.pendingLeaveCount : 0;
        if (key === 'birthdays') return this.birthdaysToday.length;
        // Người tự đăng ký đang chờ Ban Điều Hành duyệt. Chỉ nhắc người
        // thực sự duyệt được, để GLV thường khỏi thấy chấm đỏ vô nghĩa.
        if (key === 'staff')     return this.canEditModule('staff') ? this.pendingMembers.length : 0;
        return 0;
    },

    openModule(key) {
        if (!this.canAccess(key)) return;
        if (this.isUnderMaintenance(key)) {
            window.TNTT.toast.warning('Chức năng "' + this.moduleLabel(key) + '" đang tạm bảo trì. Vui lòng quay lại sau.');
            return;
        }
        if (key === 'attendance')    return this.openAttendance();
        if (key === 'leave')         return this.openLeave();
        if (key === 'birthdays')     return this.openBirthdays();
        if (key === 'staff')         return this.openStaff();
        if (key === 'years')         return this.openYears();
        if (key === 'stats')         return this.openStats();
        if (key === 'analytics')     return this.openAnalytics();
        if (key === 'org')           return this.openOrg();
        if (key === 'reports')       return this.openReports();
        if (key === 'announcements') return this.openAnnouncements();
        if (key === 'scores')        return this.openScores();
        if (key === 'promotion')     return this.openPromotion();
        if (key === 'calendar')      return this.changeModule('calendar');
        this.changeModule(key);
    },

    moduleLabel(key) {
        const m = this.moduleDefs.find(x => x.key === key);
        return m ? m.label : key;
    },

    // ---- Màn Cài đặt (chỉ Quản trị) ----
    settingsTab: 'profile',   // 'profile' | 'logs' | 'perms' | 'maintenance' | 'years'
    permRoleTab: 'glv',

    // ---- NIÊN KHOÁ ----
    years: [],
    yearBusy: false,
    showYearModal: false,
    // id = null nghĩa là đang TẠO MỚI, có id nghĩa là đang SỬA
    yearForm: { id: null, name: '', startDate: '', endDate: '' },

    async apiYear(action, body) {
        const res = await fetch('api/years.php?action=' + action, {
            method: body ? 'POST' : 'GET',
            headers: { 'Content-Type': 'application/json' },
            body: body ? JSON.stringify(body) : undefined
        });
        return res.json();
    },

    async loadYears() {
        this.yearBusy = true;
        try {
            const r = await this.apiYear('list');
            if (r.ok) this.years = r.years;
        } catch (e) { /* mất mạng thì để danh sách cũ */ }
        finally { this.yearBusy = false; this.$nextTick(() => lucide.createIcons()); }
    },

    openCreateYear() {
        this.yearForm = { id: null, name: '', startDate: '', endDate: '' };
        this.showYearModal = true;
    },

    // Sửa được cả niên khoá ĐANG DÙNG — đầu năm rất hay phải dời ngày
    // khai giảng. Chỉ niên khoá đã khoá sổ mới không sửa.
    openEditYear(y) {
        if (y.status === 'đã khóa') {
            window.TNTT.toast.warning('Niên khoá "' + y.name + '" đã khoá sổ. Hãy mở lại trước khi sửa.');
            return;
        }
        this.yearForm = { id: y.id, name: y.name, startDate: y.startDate, endDate: y.endDate };
        this.showYearModal = true;
    },

    get yearFormTitle() {
        return this.yearForm.id ? 'Sửa niên khoá' : 'Mở niên khoá mới';
    },

    async saveYear() {
        const f = this.yearForm;
        if (!f.name.trim() || !f.startDate || !f.endDate) {
            window.TNTT.toast.warning('Vui lòng nhập đủ tên niên khoá và hai mốc ngày.');
            return;
        }
        if (f.endDate <= f.startDate) {
            window.TNTT.toast.warning('Ngày kết thúc phải sau ngày bắt đầu.');
            return;
        }

        const body = { name: f.name.trim(), startDate: f.startDate, endDate: f.endDate };
        if (f.id) body.id = f.id;

        const r = await this.apiYear(f.id ? 'update' : 'create', body);
        if (!r.ok) { window.TNTT.toast.error(r.error); return; }

        this.showYearModal = false;
        await this.loadYears();

        // Học kỳ bị co lại cho vừa khoảng mới thì phải nói rõ, đừng đổi ngầm
        if (r.termsAdjusted && r.termsAdjusted.length) {
            window.TNTT.toast.info('Đã sửa niên khoá. Học kỳ được chỉnh cho vừa khoảng mới: ' + r.termsAdjusted.join(', '));
        }

        // Sửa chính niên khoá đang dùng thì mọi số liệu trên màn hình
        // đang dựa vào khoảng cũ -> nạp lại cho khớp
        if (f.id && this.year && this.year.id === f.id) {
            this.year.name = body.name;
            await this.loadData();
        }
    },

    // Đổi niên khoá đang dùng thì phải tải lại trang: mọi số liệu
    // trên màn hình đều thuộc về năm cũ.
    async activateYear(y) {
        if (!confirm('Chuyển sang niên khoá ' + y.name + '?\n\nToàn bộ dữ liệu hiển thị sẽ đổi theo năm này.')) return;
        const r = await this.apiYear('activate', { id: y.id });
        if (!r.ok) { window.TNTT.toast.error(r.error); return; }
        location.reload();
    },

    async toggleYearLock(y) {
        const locking = y.status === 'đang mở';
        const msg = locking
            ? 'Khoá sổ niên khoá ' + y.name + '?\n\nDữ liệu năm này chuyển sang chỉ đọc.'
            : 'Mở lại niên khoá ' + y.name + '?';
        if (!confirm(msg)) return;
        const r = await this.apiYear(locking ? 'lock' : 'unlock', { id: y.id });
        if (!r.ok) { window.TNTT.toast.error(r.error); return; }
        await this.loadYears();
    },

    yearUsageLabel(u) {
        if (!u) return '';
        return u.enrollments + ' ghi danh · ' + u.programs + ' chương trình · '
             + u.attendances + ' lượt điểm danh · ' + u.leaves + ' đơn phép';
    },

    get isAdmin() {
        return this.user.role === 'admin';
    },

    // Mặc định vào thẻ Cá nhân: đó là thứ MỌI thành viên đều có.
    // Bốn thẻ quản trị vẫn chỉ Quản Trị Hệ Thống mới thấy.
    // Niên khoá tách khỏi Cài Đặt thành module riêng.
    openYears() {
        this.loadYears();
        this.changeModule('years');
    },

    openSettings(the) {
        this.settingsTab = the || 'profile';
        if (this.isAdmin) this.loadYears();
        this.changeModule('settings');
    },

    permLabel(level) {
        if (level === 'edit') return 'Toàn quyền';
        if (level === 'view') return 'Chỉ xem';
        return 'Không thấy';
    },

    permChipClass(level) {
        if (level === 'edit') return 'bg-blue-600 text-white border-blue-600';
        if (level === 'view') return 'bg-slate-100 text-slate-600 border-slate-200';
        return 'bg-white text-slate-300 border-slate-200';
    },

    setPermission(key, role, level) {
        // Chặn tự khoá chính mình ra khỏi màn Cài đặt
        if (role === 'admin' && level !== 'edit') {
            window.TNTT.toast.warning('Không thể hạ quyền của Quản Trị Hệ Thống — bạn sẽ tự khoá mình ra ngoài.');
            return;
        }
        const old = this.permissions[key][role];
        if (old === level) return;
        this.permissions[key][role] = level;
        this.save('settings', 'permission', { moduleKey: key, roleCode: role, level: level });
        this.logAction('phanquyen', 'settings',
                       'Đổi quyền ' + this.moduleLabel(key) + ' của ' + this.roleLabel(role),
                       this.permLabel(old) + ' → ' + this.permLabel(level));
    },

    toggleModuleEnabled(key) {
        this.moduleEnabled[key] = !this.moduleEnabled[key];
        this.save('settings', 'module', { moduleKey: key });
        this.logAction('baotri', 'settings',
                       (this.moduleEnabled[key] ? 'Mở lại' : 'Tạm khóa') + ' chức năng ' + this.moduleLabel(key),
                       this.moduleEnabled[key] ? 'hoạt động bình thường' : 'đang bảo trì');
    },

    get maintenanceCount() {
        return this.moduleDefs.filter(m => !this.moduleEnabled[m.key]).length;
    },

    resetPermissions() {
        if (!confirm('Đưa toàn bộ phân quyền về mặc định ban đầu?')) return;
        this.permissions = JSON.parse(JSON.stringify(this.defaultPermissions));
        this.save('settings', 'resetPerms', {});
        this.logAction('phanquyen', 'settings', 'Đặt lại toàn bộ phân quyền về mặc định', '');
    },

    // ==========================================
    // 0. DANH MỤC KHỐI & LỚP
    // Tách riêng khỏi danh sách thiếu nhi để lớp mới mở, chưa có em nào
    // vẫn hiện ra trong bộ lọc. Sau này lấy từ api/classes.php.
    // ==========================================
    blocks: ['Khai Tâm', 'Rước Lễ', 'Thêm Sức', 'Bao Đồng'],

    classes: [],   // máy chủ nạp qua loadData()

    statusOptions: ['đang sinh hoạt', 'dừng sinh hoạt', 'chuyển xứ', 'đã ra trường'],
};
