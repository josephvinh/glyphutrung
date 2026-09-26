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

    // Đã bỏ nút chỉnh sáng/tối trên header — app luôn ở chế độ SÁNG.
    // Giữ 'dark' = false để applyDarkMode() gỡ class .dark nếu máy nào còn sót.
    dark: false,

    init() {
        // Apply saved dark mode state
        this.applyDarkMode();
        if (typeof this.initOfflineAttendance === 'function') {
            this.initOfflineAttendance();
        }
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
    heavyLoaded: false,      // đã tải xong BƯỚC 2 (điểm danh + điểm) chưa

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
            if (res.status === 401) {
                if (window.TNTT?.toast) window.TNTT.toast.error('Phiên đăng nhập hết hạn. Đang chuyển ra màn hình đăng nhập...');
                setTimeout(() => location.reload(), 1500);
                return { ok: false, error: 'Phiên đăng nhập đã hết hạn.' };
            }
            if (res.status === 403) return { ok: false, error: 'Yêu cầu không hợp lệ (CSRF). Vui lòng tải lại trang.' };
            return await res.json();
        } catch (e) {
            return { ok: false, networkError: true, error: 'Mất kết nối máy chủ. Kiểm tra lại mạng.' };
        }
    },

    async save(file, action, body) {
        const r = await this.api(file, action, body);
        if (!r.ok) {
            // Điểm danh ngoại tuyến đã có hàng đợi offline tự động, không báo lỗi đỏ làm hoang mang GLV
            if (!(r.networkError && file === 'attendance')) {
                window.TNTT.toast.error(r.error || 'Có lỗi xảy ra khi lưu dữ liệu.');
            }
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
    // TẢI 2 BƯỚC (cho nhẹ máy yếu lúc mở app):
    //   Bước 1 (core): mọi thứ TRỪ điểm danh/điểm -> app dùng được NGAY
    //                  (trang chủ, danh sách, thông báo, lịch...).
    //   Bước 2 (heavy): điểm danh + điểm, tải NỀN ngay sau, không chặn màn.
    // Nhờ vậy 5MB JSON điểm danh không còn parse chặn màn đầu trên điện thoại.
    async loadData() {
        this.syncing = true;
        try {
            const res = await fetch('api/data.php?part=core');
            const d = await res.json();
            if (!d.ok) { window.TNTT.toast.error(d.error || 'Không nạp được dữ liệu.'); return false; }

            this.students          = d.students;
            this.rebuildStudentIndex();  // chỉ số em O(1) — studentById nhanh ở màn Xin phép
            // Sổ Mộc: ví + chuỗi + lịch sử gần nhất, theo studentId — đã lọc
            // theo đúng phạm vi lớp của this.students ở máy chủ (data.php).
            this.stampSummaries    = d.stampSummaries || {};
            // Sĩ số mọi lớp, đếm ở máy chủ. Cần vì this.students nay chỉ
            // gồm phạm vi mình được xem, không đếm được lớp ngoài phạm vi.
            this.classCounts       = d.classCounts || {};
            this.programs          = d.programs;
            // Chương trình gắn lớp: map programId -> [classId,...] (rỗng = toàn đoàn)
            this.programClasses    = d.programClasses || {};
            this.leaveRequests     = d.leaveRequests;
            this.reports           = d.reports;
            this.rebuildReportIndex();  // chỉ số phiếu O(1) — tránh chậm thao tác (myTasks + danh sách Phiếu LC)
            this.announcements     = d.announcements;
            this.readAnnouncements = d.readAnnouncements;
            this.members           = d.members;
            this.logs              = d.logs;
            this.notes             = d.notes || [];
            // Chỉ là CON SỐ chờ duyệt của Thư viện, để vẽ chấm đỏ trên icon
            // mà không phải mở module (moduleBadge chạy ngay ở Trang chủ).
            this.libraryPending    = d.libraryPending || 0;

            // Điểm danh/điểm sẽ đổ vào ở bước 2. Đặt rỗng + index rỗng để
            // các getter chạy an toàn (trả 0) trong lúc chờ.
            this.attendances = []; this.rebuildAttendanceIndex();
            this.scores      = []; this.rebuildScoreIndex();
            this.heavyLoaded = false;

            this._lastLoadAt = Date.now();  // mốc để auto-đồng-bộ khi mở lại app khỏi nạp dồn
            this.loadHeavy();               // BƯỚC 2 — tải nền, KHÔNG await

            // Phase 3: Load favorites and init keyboard shortcuts
            if (this.students?.loadFavorites) this.students.loadFavorites();
            if (this.students?.initKeyboardShortcuts) this.students.initKeyboardShortcuts();

            return true;
        } catch (e) {
            window.TNTT.toast.error('Không nạp được dữ liệu từ máy chủ.');
            return false;
        } finally {
            this.syncing = false;
            this.$nextTick(() => lucide.createIcons());
        }
    },

    // BƯỚC 2: điểm danh + điểm. Chạy nền, không chặn giao diện. Xong thì
    // dựng lại chỉ số và bật cờ heavyLoaded để các màn cần số liệu sáng lên.
    async loadHeavy() {
        try {
            const res = await fetch('api/data.php?part=heavy');
            const d = await res.json();
            if (!d.ok) return;
            this.attendances = d.attendances || [];
            this.rebuildAttendanceIndex(); // index O(1) — tránh treo khi đoàn lớn
            this.scores      = d.scores || [];
            this.rebuildScoreIndex();      // chỉ số điểm O(1) — tránh treo Lên lớp/ĐTB
            this.heavyLoaded = true;
            this.$nextTick(() => lucide.createIcons());
        } catch (e) {
            // Im lặng: lần đồng bộ sau (bấm Làm mới / mở lại app) sẽ tải lại.
        }
    },

    // Làm mới thủ công — thay cho thao tác "kéo xuống" mà PWA cài ra màn hình
    // chính không có. Chỉ nạp lại dữ liệu (nhẹ, không chớp trắng như tải lại
    // cả trang); báo cho người dùng biết đã đồng bộ.
    async refreshApp() {
        if (this.syncing) return;
        if (typeof this.syncOfflineAttendance === 'function') {
            await this.syncOfflineAttendance();
        }
        if (await this.loadData()) window.TNTT.toast.success('Đã đồng bộ dữ liệu mới nhất.');
    },

    // Đăng xuất thật, gọi lên máy chủ để hủy phiên
    async logout() {
        if (!await window.TNTT.toast.confirm('Đăng xuất khỏi hệ thống?', { confirmText: 'Đăng xuất' })) return;
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

        const isMgr = ['admin', 'bdh', 'truong_khoi'].includes(this.user.role);
        const isBdh = ['admin', 'bdh'].includes(this.user.role);

        // Buổi hôm nay còn dang dở
        if (this.canAccess('attendance') && !this.isUnderMaintenance('attendance')) {
            this.programsOn(today).forEach(p => {
                const session = { programId: p.id, date: today };
                const done = scope.filter(s => this.attendanceRecord(s.id, session)).length;
                if (scope.length > 0 && done < scope.length) {
                    tasks.push({
                        key: 'att-' + p.id, icon: 'clipboard-check',
                        cls: 'bg-blue-50 text-blue-600 border-blue-100',
                        text: isMgr ? 'Đôn đốc điểm danh ' + p.name : 'Điểm danh ' + p.name,
                        detail: (isMgr ? 'Đã điểm danh ' : 'Mới ghi ') + done + '/' + scope.length + ' em · chốt lúc ' + this.cutoffOf(p),
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
                    text: isMgr ? 'Tiến độ Phiếu liên lạc' : 'Lập ' + chua + ' phiếu liên lạc',
                    detail: isMgr ? ('Còn ' + chua + ' phiếu chưa hoàn tất') : (term.name + ' · còn thiếu'),
                    go: 'reports'
                });
            }
        }

        // CẢNH BÁO / ĐÔN ĐỐC RIÊNG CHO BĐH
        if (isBdh) {
            // Duyệt GLV mới
            if (this.pendingMembers && this.pendingMembers.length > 0) {
                tasks.push({
                    key: 'new-staff', icon: 'user-plus',
                    cls: 'bg-indigo-50 text-indigo-600 border-indigo-100',
                    text: 'Duyệt ' + this.pendingMembers.length + ' hồ sơ nhân sự',
                    detail: 'có GLV mới đăng ký chờ duyệt',
                    go: 'staff'
                });
            }

            // Thiếu nhi mồ côi (không có lớp)
            if (this.students) {
                const orphans = this.students.filter(s => s.status === 'đang sinh hoạt' && !s.className).length;
                if (orphans > 0) {
                    tasks.push({
                        key: 'orphan-students', icon: 'alert-circle',
                        cls: 'bg-rose-50 text-rose-600 border-rose-100',
                        text: 'Xếp lớp cho ' + orphans + ' thiếu nhi',
                        detail: 'đang sinh hoạt nhưng chưa có lớp',
                        go: 'students' // Mở danh sách thiếu nhi để lọc
                    });
                }
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

        // Việc từ Lịch cá nhân (ghi chú + buổi họp sắp tới)
        if (this.noteTasks) tasks.push(...this.noteTasks);

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

    async clearLogs() {
        if (await window.TNTT.toast.confirm('Xóa toàn bộ nhật ký thao tác?\nViệc này không hoàn tác được.', { danger: true, confirmText: 'Xoá hết' })) {
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
        // Khu nghiệp vụ chung
        { key: 'students',      label: 'Thiếu Nhi',    icon: 'users',           color: 'text-blue-600',   area: 'glv', group: 'Hằng ngày' },
        { key: 'attendance',    label: 'Điểm danh',    icon: 'clipboard-check', color: 'text-blue-600',   area: 'glv', group: 'Hằng ngày' },
        { key: 'leave',         label: 'Xin phép',     icon: 'file-text',       color: 'text-blue-600',   area: 'glv', group: 'Hằng ngày', badge: 'leave' },
        { key: 'notes',         label: 'Lịch của tôi', icon: 'calendar-check',  color: 'text-teal-600',   area: 'glv', group: 'Hằng ngày', badge: 'notes' },
        { key: 'birthdays',     label: 'Sinh nhật',    icon: 'cake',            color: 'text-rose-500',   area: 'glv', group: 'Hằng ngày', badge: 'birthday', hidden: true },
        { key: 'reporthub',     label: 'Báo cáo',      icon: 'bar-chart-3',     color: 'text-emerald-600', area: 'glv', group: 'Theo dõi' },
        { key: 'stats',         label: 'Thống kê',     icon: 'bar-chart-3',     color: 'text-emerald-600', area: 'glv', group: 'Theo dõi', hidden: true },
        { key: 'analytics',     label: 'Phân tích',    icon: 'bar-chart-2',     color: 'text-purple-600', area: 'glv', group: 'Theo dõi', hidden: true },
        { key: 'org',           label: 'Khối lớp',     icon: 'layers',          color: 'text-indigo-600', area: 'glv', group: 'Quản lý' },
        { key: 'guide',         label: 'Hướng dẫn',    icon: 'info',            color: 'text-sky-600',    area: 'glv', group: 'Theo dõi' },
        { key: 'thu_vien',      label: 'Thư viện',     icon: 'scroll-text',     color: 'text-amber-600',  area: 'glv', group: 'Theo dõi' },
        // reports + scores gộp vào tile "Thiếu Nhi" (mở qua thẻ), ẩn khỏi lưới
        { key: 'reports',       label: 'Sổ liên lạc',  icon: 'clipboard-list',  color: 'text-amber-600',  area: 'glv', group: 'Hằng ngày', hidden: true },
        { key: 'scores',        label: 'Điểm số',      icon: 'graduation-cap',  color: 'text-violet-600', area: 'glv', group: 'Hằng ngày', hidden: true },
        // Khu điều hành (icon màu để dùng chung lưới phẳng + thanh bên)
        { key: 'promotion',     label: 'Lên lớp',      icon: 'trending-up',     color: 'text-violet-600', area: 'bdh', group: 'Chương trình' },
        { key: 'programs',      label: 'Chương trình', icon: 'calendar-plus',   color: 'text-amber-600',  area: 'bdh', group: 'Chương trình' },
        { key: 'calendar',      label: 'Lịch trình',   icon: 'calendar-days',   color: 'text-teal-600',   area: 'bdh', group: 'Chương trình', hidden: true },
        { key: 'announcements', label: 'Thông báo',    icon: 'megaphone',       color: 'text-rose-500',   area: 'bdh', group: 'Điều hành' },
        { key: 'staff',         label: 'Nhân sự',      icon: 'user-cog',        color: 'text-cyan-600',   area: 'bdh', group: 'Điều hành', badge: 'staff' },
        { key: 'years',         label: 'Niên khoá',    icon: 'calendar-range',  color: 'text-indigo-600', area: 'bdh', group: 'Điều hành' },
        { key: 'gifts',         label: 'Danh mục quà', icon: 'gift',            color: 'text-pink-600',   area: 'bdh', group: 'Chương trình' }
    ],

    // Công tắc bảo trì. Tắt thì mọi người thấy nút mờ kèm nhãn "Bảo trì",
    // riêng Quản trị vẫn vào được để kiểm tra trước khi mở lại.
    moduleEnabled: {
        students: true, attendance: true, leave: true, birthdays: true,
        stats: true, analytics: true, org: true, reports: true, reporthub: true, programs: true, announcements: true,
        scores: true, promotion: true, calendar: true, notes: true, guide: true, thu_vien: true, gifts: true
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
        calendar:       { admin: 'view', bdh: 'view', truong_khoi: 'view', glv_chu_nhiem: 'view', glv: 'view' },
        notes:          { admin: 'edit', bdh: 'edit', truong_khoi: 'edit', glv_chu_nhiem: 'edit', glv: 'edit' },
        guide:          { admin: 'view', bdh: 'view', truong_khoi: 'view', glv_chu_nhiem: 'view', glv: 'view' },
        // Thư viện: view = xem + đăng (chờ duyệt); edit = duyệt/gỡ/quản chủ đề.
        thu_vien:       { admin: 'edit', bdh: 'edit', truong_khoi: 'view', glv_chu_nhiem: 'view', glv: 'view', du_bi: 'view' },
        // Danh mục quà: Quản trị/BĐH/Thủ Từ toàn quyền, các vai khác không thấy.
        gifts:          { admin: 'edit', bdh: 'edit', truong_khoi: 'none', glv_chu_nhiem: 'none', glv: 'none', thu_thu: 'edit' }
    },

    permOf(key) {
        const row = this.permissions[key];
        return row ? (row[this.user.role] || 'none') : 'none';
    },

    canAccess(key) {
        if (key === 'reporthub') return this.permOf('stats') !== 'none' || this.permOf('analytics') !== 'none';
        return this.permOf(key) !== 'none';
    },

    // Helper for x-show in templates
    permOfKey(k) {
        return this.permOf(k) !== 'none';
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

    // Lưới phẳng: TẤT CẢ module truy cập được, không chia khu/nhóm,
    // giữ đúng thứ tự ưu tiên trong moduleDefs.
    visibleFlat() {
        return this.moduleDefs.filter(m => !m.hidden && this.canAccess(m.key));
    },

    // Gom nút theo nhóm, giữ thứ tự nhóm xuất hiện lần đầu trong moduleDefs
    moduleGroups(area) {
        const out = [];
        this.visibleModules(area).forEach(m => {
            const name = m.group || '';
            let g = out.find(x => x.name === name);
            if (!g) { g = { name, items: [] }; out.push(g); }
            g.items.push(m);
        });
        return out;
    },

    moduleBadge(key) {
        if (key === 'leave')     return this.canApproveLeave ? this.pendingLeaveCount : 0;
        if (key === 'birthdays') return this.birthdaysToday.length;
        // Người tự đăng ký đang chờ Ban Điều Hành duyệt. Chỉ nhắc người
        // thực sự duyệt được, để GLV thường khỏi thấy chấm đỏ vô nghĩa.
        if (key === 'staff')     return this.canEditModule('staff') ? this.pendingMembers.length : 0;
        if (key === 'notes')     return this.notesBadgeCount;
        // Thư viện: chỉ người duyệt được mới thấy số mục đang chờ (GLV thường
        // thấy chấm đỏ vô nghĩa vì họ không duyệt được).
        if (key === 'thu_vien')  return this.libCanEdit ? this.libraryPending : 0;
        // Việc cần làm -> chấm số trên icon (thay cho khối ở Trang chủ)
        if (key === 'attendance')    return this.attendanceTodoCount;
        if (key === 'announcements') return this.unreadAnnouncementCount;
        if (key === 'students')      return this.reportTodoCount;   // Thiếu Nhi = lối vào Phiếu liên lạc
        return 0;
    },

    // Nhãn chấm gọn: số lớn thì rút thành "99+" cho khỏi phình icon
    moduleBadgeLabel(key) {
        const n = this.moduleBadge(key);
        return n > 99 ? '99+' : n;
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
        if (key === 'reporthub')     return this.openReportHub();
        if (key === 'stats')         return this.openStats();
        if (key === 'analytics')     return this.openAnalytics();
        if (key === 'org')           return this.openOrg();
        if (key === 'reports')       return this.openReports();
        if (key === 'announcements') return this.openAnnouncements();
        if (key === 'scores')        return this.openScores();
        if (key === 'promotion')     return this.openPromotion();
        if (key === 'calendar')      return this.changeModule('calendar');
        if (key === 'notes')         return this.openNotes();
        this.changeModule(key);
    },

    moduleLabel(key) {
        const m = this.moduleDefs.find(x => x.key === key);
        return m ? m.label : key;
    },

    // ---- Màn Cài đặt (chỉ Quản trị) ----
    settingsTab: 'profile',   // 'profile' | 'logs' | 'perms' | 'maintenance' | 'years'
    permRoleTab: 'glv',

    // ---- TAB BÁO CÁO ----
    reportsTab: 'stats',   // 'stats' | 'analytics'

    // ---- NIÊN KHOÁ ----
    years: [],
    yearBusy: false,
    showYearModal: false,
    // id = null nghĩa là đang TẠO MỚI, có id nghĩa là đang SỬA
    yearForm: { id: null, name: '', startDate: '', endDate: '' },

    async apiYear(action, body) {
        try {
            return await (await fetch('api/years.php?action=' + action, {
                method: body ? 'POST' : 'GET',
                headers: { 'Content-Type': 'application/json' },
                body: body ? JSON.stringify(body) : undefined
            })).json();
        } catch (e) {
            return { ok: false, error: 'Không thể kết nối đến máy chủ. Vui lòng kiểm tra mạng.' };
        }
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
        if (this.yearBusy) return;   // chặn bấm Lưu nhiều lần -> tránh tạo trùng
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

        this.yearBusy = true;
        try {
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
        } catch (e) {
            window.TNTT.toast.error('Lỗi khi lưu niên khoá.');
        } finally {
            this.yearBusy = false;
        }
    },

    // Đổi niên khoá đang dùng thì phải tải lại trang: mọi số liệu
    // trên màn hình đều thuộc về năm cũ.
    async activateYear(y) {
        if (this.yearBusy) return;
        if (!await window.TNTT.toast.confirm('Chuyển sang niên khoá ' + y.name + '?\n\nToàn bộ dữ liệu hiển thị sẽ đổi theo năm này.', { confirmText: 'Chuyển' })) return;
        this.yearBusy = true;
        try {
            const r = await this.apiYear('activate', { id: y.id });
            if (!r.ok) { window.TNTT.toast.error(r.error); return; }
            location.reload();
        } catch (e) {
            window.TNTT.toast.error('Lỗi khi đổi niên khoá.');
        } finally {
            this.yearBusy = false;
        }
    },

    async toggleYearLock(y) {
        if (this.yearBusy) return;
        const locking = y.status === 'đang mở';
        const msg = locking
            ? 'Khoá sổ niên khoá ' + y.name + '?\n\nDữ liệu năm này chuyển sang chỉ đọc.'
            : 'Mở lại niên khoá ' + y.name + '?';
        if (!await window.TNTT.toast.confirm(msg, { danger: locking, confirmText: locking ? 'Khoá sổ' : 'Mở lại' })) return;
        this.yearBusy = true;
        try {
            const r = await this.apiYear(locking ? 'lock' : 'unlock', { id: y.id });
            if (!r.ok) { window.TNTT.toast.error(r.error); return; }
            await this.loadYears();
        } catch (e) {
            window.TNTT.toast.error('Lỗi khi khoá/mở niên khoá.');
        } finally {
            this.yearBusy = false;
        }
    },

    yearUsageLabel(u) {
        if (!u) return '';
        return u.enrollments + ' ghi danh · ' + u.programs + ' chương trình · '
             + u.attendances + ' lượt điểm danh · ' + u.leaves + ' đơn phép';
    },

    get isAdmin() {
        return this.user.role === 'admin';
    },

    // Danh xưng thực tế của đoàn: chỉ Giáo Lý Viên / Dự Bị. Các vai điều hành
    // (BĐH, Trưởng khối, Chủ nhiệm) VẪN là Giáo Lý Viên — đó là NHIỆM VỤ, không
    // phải danh xưng. Dùng ở chỗ giới thiệu bản thân cho đúng đời thực.
    danhXung(role) {
        return role === 'du_bi' ? 'Dự Bị' : 'Giáo Lý Viên';
    },
    get myDanhXung() {
        return this.danhXung(this.user.role);
    },

    // Mặc định vào thẻ Cá nhân: đó là thứ MỌI thành viên đều có.
    // Bốn thẻ quản trị vẫn chỉ Quản Trị Hệ Thống mới thấy.
    // Niên khoá tách khỏi Cài Đặt thành module riêng.
    openYears() {
        this.loadYears();
        this.changeModule('years');
    },

    openReportHub() {
        // Chỉ còn Thống kê (đã bỏ Phân tích).
        this.reportsTab = 'stats';
        this.changeModule('reporthub');
        if (this.openStats) this.openStats(true);
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
        this.save('settings', 'permission', { moduleKey: key, roleCode: role, level: level })
            .then(r => { if (!r || !r.ok) this.loadData(); });
        this.logAction('phanquyen', 'settings',
                       'Đổi quyền ' + this.moduleLabel(key) + ' của ' + this.roleLabel(role),
                       this.permLabel(old) + ' → ' + this.permLabel(level));
    },

    toggleModuleEnabled(key) {
        this.moduleEnabled[key] = !this.moduleEnabled[key];
        this.save('settings', 'module', { moduleKey: key })
            .then(r => { if (!r || !r.ok) this.loadData(); });
        this.logAction('baotri', 'settings',
                       (this.moduleEnabled[key] ? 'Mở lại' : 'Tạm khóa') + ' chức năng ' + this.moduleLabel(key),
                       this.moduleEnabled[key] ? 'hoạt động bình thường' : 'đang bảo trì');
    },

    get maintenanceCount() {
        return this.moduleDefs.filter(m => !this.moduleEnabled[m.key]).length;
    },

    async resetPermissions() {
        if (!await window.TNTT.toast.confirm('Đưa toàn bộ phân quyền về mặc định ban đầu?', { danger: true, confirmText: 'Đặt lại' })) return;
        this.permissions = JSON.parse(JSON.stringify(this.defaultPermissions));
        this.save('settings', 'resetPerms', {})
            .then(r => { if (!r || !r.ok) this.loadData(); });
        this.logAction('phanquyen', 'settings', 'Đặt lại toàn bộ phân quyền về mặc định', '');
    },

    // ==========================================
    // 0. DANH MỤC KHỐI & LỚP
    // Tách riêng khỏi danh sách thiếu nhi để lớp mới mở, chưa có em nào
    // vẫn hiện ra trong bộ lọc. Sau này lấy từ api/classes.php.
    // ==========================================
    blocks: ['Khai Tâm', 'Rước Lễ', 'Thêm Sức', 'Bao Đồng'],

    classes: [],   // máy chủ nạp qua loadData()

    programClasses: {},   // programId -> [classId,...] (rỗng = toàn đoàn)

    stampSummaries: {},   // studentId -> {current_balance, held_balance, total_earned, current_streak, longest_streak, recent_transactions[]}

    statusOptions: ['đang sinh hoạt', 'dừng sinh hoạt', 'chuyển xứ', 'đã ra trường'],
};
