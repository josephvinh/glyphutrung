/* ==========================================================
   ORG — Khối lớp và nhân sự
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.org = {
    // ==========================================
    // 9. DATA: KHỐI LỚP & NHÂN SỰ
    //
    // Phân biệt rõ hai khái niệm:
    //   ROLE  = quyền trong hệ thống, quyết định thấy gì làm gì
    //   TITLE = chức danh hiển thị, thuần tổ chức, không đẻ ra quyền
    // Ví dụ: Đoàn Phó và Thư Ký cùng role 'bdh' nhưng title khác nhau.
    // ==========================================
    roleDefs: [
        { value: 'admin',         label: 'Quản Trị Hệ Thống', level: 5, scope: 'toàn đoàn', desc: 'Toàn quyền, kể cả cấu hình hệ thống' },
        { value: 'bdh',           label: 'Ban Điều Hành',     level: 4, scope: 'toàn đoàn', desc: 'Quản lý toàn đoàn: khối lớp, nhân sự, chương trình' },
        { value: 'truong_khoi',   label: 'Trưởng Khối',       level: 3, scope: 'khối',      desc: 'Quản lý các lớp trong khối mình' },
        { value: 'glv_chu_nhiem', label: 'GLV Chủ Nhiệm',     level: 2, scope: 'lớp',       desc: 'Phụ trách một lớp, được duyệt đơn của lớp' },
        { value: 'glv',           label: 'Giáo Lý Viên',      level: 1, scope: 'lớp',       desc: 'Dạy và điểm danh lớp được phân công' }
    ],

    titleDefs: {
        admin:          ['Quản trị viên'],
        bdh:            ['Đoàn Trưởng', 'Đoàn Phó', 'Thư Ký', 'Thủ Quỹ', 'Ủy Viên'],
        truong_khoi:    ['Trưởng Khối', 'Phó Khối'],
        glv_chu_nhiem:  ['GLV Chủ Nhiệm'],
        glv:            ['GLV Phụ Tá', 'Huynh Trưởng', 'Dự Trưởng'],
        thu_thu:        ['Thủ Thư']
    },

    members: [],   // máy chủ nạp qua loadData()

    expandedBlock: '',
    memberSearch: '',
    memberRoleFilter: '',
    showMemberModal: false,
    isEditingMember: false,
    memberForm: {},
    showBlockModal: false,
    blockForm: { original: '', name: '' },
    busyBlock: false,        // chặn nút khi đang ghi/xóa khối
    showClassModal: false,
    classForm: { original: '', name: '', block: '' },
    busyClass: false,        // chặn nút khi đang ghi/xóa lớp

    // Mọi phân công đang hiệu lực của toàn đoàn — nguồn để dựng roster kiêm
    // nhiệm ở màn Khối & Lớp (thay cho việc đọc lớp chính trong members).
    allAssignments: [],

    openOrg() {
        this.changeModule('org');
        this.loadAllAssignments();
    },

    async loadAllAssignments() {
        const r = await this.api('assignments', 'list_active');
        if (r && r.ok) this.allAssignments = r.assignments;
    },

    // Nhân sự tách khỏi Khối & Lớp thành module riêng.
    openStaff() {
        this.memberSearch = '';
        this.memberRoleFilter = '';
        this.changeModule('staff');
        this.loadAllAssignments();
    },

    // ---- THỦ THƯ: vai kiêm nhiệm thêm (phục vụ đổi quà), không đổi vai gốc ----
    librarianAssignment(memberId) {
        return (this.allAssignments || []).find(a => a.role === 'thu_thu' && a.memberId === memberId) || null;
    },

    async toggleLibrarian(m) {
        if (!m || !m.id || !this.canManageOrg) return;
        const cur = this.librarianAssignment(m.id);
        if (cur) {
            if (!await window.TNTT.toast.confirm('Gỡ vai Thủ Thư của ' + this.memberFullName(m) + '?', { danger: true, confirmText: 'Gỡ' })) return;
            await this.save('assignments', 'end', { assignmentId: cur.id });
        } else {
            await this.save('assignments', 'create', { memberId: Number(m.id), role: 'thu_thu' });
        }
        await this.loadAllAssignments();
    },

    // Chỉ Ban Điều Hành trở lên mới sửa được. Cấp dưới chỉ xem.
    // Dùng chung cho cả Khối & Lớp lẫn Nhân sự: đang ở màn nào thì
    // hỏi quyền của đúng màn đó.
    get canManageOrg() {
        // Dùng canEditModule() như các module khác - nó là getter đã merge từ core
        return this.canEditModule(this.currentModule === 'staff' ? 'staff' : 'org');
    },

    // Role của Ban Điều Hành và Admin không được đụng vào từ màn này
    isProtectedMember(m) {
        return ['admin', 'bdh'].includes(m.role);
    },

    // A′: người ĐÃ có phân công (kiêm nhiệm) thì vai trò/chức vụ + lớp/khối do
    // màn Khối & Lớp quản — khoá các ô đó ở màn Nhân sự để khớp với backend
    // (StaffService trả 409 nếu cố đổi vai tại đây).
    isRoleLockedByAssignment(m) {
        return this.isEditingMember && !this.isProtectedMember(m) && !!m.hasAssignment;
    },

    // Ô "Vai trò" bị khóa khi: đang sửa admin/BĐH (bảo vệ) HOẶC người đã kiêm
    // nhiệm. Gom về một chỗ để 3 nơi trong giao diện không phải lặp biểu thức.
    roleFieldLocked(m) {
        return (this.isEditingMember && this.isProtectedMember(m)) || this.isRoleLockedByAssignment(m);
    },

    canEditMemberRole(m) {
        return this.canManageOrg && !this.isProtectedMember(m);
    },

    // ---- Tra cứu ----
    roleLabel(role) {
        const r = this.roleDefs.find(x => x.value === role);
        return r ? r.label : role;
    },

    /**
     * NHÃN VAI TRÒ HIỆN TRONG DANH SÁCH NGƯỜI KHÁC
     *
     * Quản Trị Hệ Thống là vai trò KỸ THUẬT, không phải chức vụ trong
     * đoàn. Người khác không cần biết ai giữ nó — biết chỉ tạo ra một
     * mục tiêu để nhờ vả hoặc gây sức ép. Nên với người xem không phải
     * Quản Trị, tài khoản admin hiện như một thành viên bình thường.
     *
     * Chính Quản Trị vẫn thấy vai trò của mình, để biết đang ở quyền nào.
     */
    roleLabelFor(m) {
        if (m.role === 'admin' && !this.isAdmin) return '';
        return this.roleLabel(m.role);
    },

    /** Chức danh hiện ra — cũng phải giấu, vì "Quản trị viên" lộ y hệt */
    titleFor(m) {
        if (m.role === 'admin' && !this.isAdmin) return '';
        return m.title || '';
    },

    roleLevel(role) {
        const r = this.roleDefs.find(x => x.value === role);
        return r ? r.level : 0;
    },

    roleScope(role) {
        const r = this.roleDefs.find(x => x.value === role);
        return r ? r.scope : '';
    },

    titleOptionsFor(role) {
        return this.titleDefs[role] || [];
    },

    roleChipClass(role) {
        if (role === 'admin')         return 'bg-slate-800 text-white border-slate-800';
        if (role === 'bdh')           return 'bg-blue-600 text-white border-blue-600';
        if (role === 'truong_khoi')   return 'bg-amber-50 text-amber-600 border-amber-200';
        if (role === 'glv_chu_nhiem') return 'bg-emerald-50 text-emerald-600 border-emerald-200';
        return 'bg-slate-50 text-slate-500 border-slate-200';
    },

    classesInBlock(block) {
        return this.classes.filter(c => c.block === block);
    },

    // Sĩ số lấy từ máy chủ (classCounts), KHÔNG tự đếm từ this.students.
    // Danh sách thiếu nhi nay chỉ gồm phạm vi mình được xem, nên tự đếm
    // sẽ ra 0 cho mọi lớp ngoài phạm vi — trong khi màn Khối & Lớp vẫn
    // hiện sĩ số mọi lớp cho ai cũng xem được.
    classSize(className) {
        return this.classCounts[className] || 0;
    },

    blockSize(block) {
        return this.classesInBlock(block)
            .reduce((tong, c) => tong + (this.classCounts[c.name] || 0), 0);
    },

    // Phân công đang hiệu lực của một lớp (GLV chủ nhiệm, GLV phụ tá, Dự bị).
    assignmentsInClass(className) {
        return this.allAssignments.filter(a =>
            a.className === className && ['glv', 'glv_chu_nhiem', 'du_bi'].includes(a.role));
    },

    // Roster lớp suy từ phân công (kiêm nhiệm) — mỗi phần tử là member thật
    // trộn thêm role của phân công + assignmentId để gỡ.
    membersInClass(className) {
        return this.assignmentsInClass(className)
            .map(a => {
                const mem = this.members.find(x => x.id === a.memberId) || {};
                return { ...mem, id: a.memberId, role: a.role, assignmentId: a.id };
            })
            .sort((a, b) => this.roleLevel(b.role) - this.roleLevel(a.role));
    },

    membersInBlock(block) {
        return this.members.filter(m => m.block === block && !m.className);
    },

    get bdhMembers() {
        return this.members.filter(m => ['admin', 'bdh'].includes(m.role))
            .sort((a, b) => this.roleLevel(b.role) - this.roleLevel(a.role));
    },

    headOfBlock(block) {
        const a = this.allAssignments.find(x => x.role === 'truong_khoi' && x.blockName === block);
        return a ? (this.members.find(m => m.id === a.memberId) || null) : null;
    },

    headOfClass(className) {
        const a = this.allAssignments.find(x => x.role === 'glv_chu_nhiem' && x.className === className);
        return a ? (this.members.find(m => m.id === a.memberId) || null) : null;
    },

    memberFullName(m) {
        return m ? (m.holyName + ' ' + m.fullName) : '';
    },

    // ---- Chọn chủ nhiệm / trưởng khối ----
    // Chỉ thêm/đổi phân công chủ nhiệm (glv_chu_nhiem) / trưởng khối
    // (truong_khoi) — KHÔNG đổi vai trò chính. Backend đảm bảo mỗi lớp/khối
    // chỉ một người giữ chức.
    async setClassHead(className, memberId) {
        await this.save('org', 'setClassHead', { className, memberId: Number(memberId) });
        await this.loadData();
    },

    async setBlockHead(block, memberId) {
        await this.save('org', 'setBlockHead', { block, memberId: Number(memberId) });
        await this.loadData();
    },

    // Thêm một GLV vào lớp (phân công glv, kiêm nhiệm). Vai chính giữ nguyên,
    // nên BĐH/Quản trị vẫn thêm được mà không mất quyền toàn đoàn.
    async addClassMember(className, memberId, role = 'glv') {
        if (!memberId) return;
        const cls = this.classes.find(c => c.name === className);
        if (!cls) return;
        await this.save('assignments', 'create', {
            memberId: Number(memberId), role, classId: cls.id
        });
        await this.loadAllAssignments();
    },

    async removeClassAssignment(assignmentId) {
        if (!assignmentId) return;
        if (!await window.TNTT.toast.confirm('Gỡ phân công này khỏi lớp?', { danger: true, confirmText: 'Gỡ' })) return;
        await this.save('assignments', 'end', { assignmentId });
        await this.loadAllAssignments();
    },

    // Ai đang phục vụ đều có thể được phân công kiêm nhiệm — kể cả BĐH/Quản
    // trị (vai chính toàn đoàn giữ nguyên, lớp/khối chỉ là kiêm nhiệm thêm).
    get assignableMembers() {
        return this.members.filter(m => m.status === 'đang phục vụ');
    },

    candidatesForClass() {
        return this.assignableMembers;
    },

    candidatesForBlock() {
        return this.assignableMembers;
    },

    // Người có thể THÊM vào lớp: loại những ai đã có phân công ở lớp đó.
    addableToClass(className) {
        const already = new Set(this.assignmentsInClass(className).map(a => a.memberId));
        return this.assignableMembers.filter(m => !already.has(m.id));
    },

    // Ghi kèm phân công hiện tại để không nhấc nhầm người đang giữ lớp khác
    memberOptionLabel(m) {
        const at = m.className || m.block;
        return this.memberFullName(m) + (at ? ' — ' + at : '');
    },

    // ---- CRUD KHỐI ----
    openCreateBlock() {
        this.blockForm = { original: '', name: '' };
        this.showBlockModal = true;
    },

    openEditBlock(name) {
        this.blockForm = { original: name, name: name };
        this.showBlockModal = true;
    },

    saveBlock() {
        const name = this.blockForm.name.trim();
        const old = this.blockForm.original;
        if (!name) { window.TNTT.toast.warning('Vui lòng nhập tên khối!'); return; }
        if (this.blocks.some(b => b === name && b !== old)) { window.TNTT.toast.warning('Tên khối này đã tồn tại!'); return; }

        this.busyBlock = true;
        this.save('org', 'saveBlock', { original: old, name }).then(r => {
            this.busyBlock = false;
            if (!r || !r.ok) this.loadData();
        });

        if (!old) {
            this.blocks.push(name);
            this.logAction('tao', 'org', 'Thêm khối ' + name, '');
        } else if (old !== name) {
            this.logAction('sua', 'org', 'Đổi tên khối ' + old + ' thành ' + name, 'cập nhật lan sang lớp, thiếu nhi, GLV');
            this.blocks = this.blocks.map(b => b === old ? name : b);
            this.classes.forEach(c => { if (c.block === old) c.block = name; });
            this.students.forEach(s => { if (s.block === old) s.block = name; });
            this.members.forEach(m => { if (m.block === old) m.block = name; });
            this.announcements.forEach(a => { if (a.audienceType === 'khối' && a.audienceValue === old) a.audienceValue = name; });
            if (this.user.managedBlock === old) this.user.managedBlock = name;
            if (this.filterBlock === old) this.filterBlock = name;
        }
        this.showBlockModal = false;
    },

    async deleteBlock(name) {
        const classCount = this.classesInBlock(name).length;
        if (classCount > 0) {
            window.TNTT.toast.warning('Khối "' + name + '" còn ' + classCount + ' lớp.\nHãy chuyển hoặc xóa hết lớp trước khi xóa khối.');
            return;
        }
        if (!await window.TNTT.toast.confirm('Xóa khối "' + name + '"?', { danger: true, confirmText: 'Xoá' })) return;
        this.busyBlock = true;
        this.save('org', 'deleteBlock', { name }).then(r => {
            this.busyBlock = false;
            if (!r || !r.ok) { this.loadData(); return; }
            this.blocks = this.blocks.filter(b => b !== name);
            this.members.forEach(m => { if (m.block === name) m.block = ''; });
            this.logAction('xoa', 'org', 'Xóa khối ' + name, '');
        });
    },

    // ---- CRUD LỚP ----
    openCreateClass(block) {
        this.classForm = { original: '', name: '', block: block || (this.blocks[0] || '') };
        this.showClassModal = true;
    },

    openEditClass(cls) {
        this.classForm = { original: cls.name, name: cls.name, block: cls.block };
        this.showClassModal = true;
    },

    saveClass() {
        const name = this.classForm.name.trim();
        const old = this.classForm.original;
        if (!name) { window.TNTT.toast.warning('Vui lòng nhập tên lớp!'); return; }
        if (!this.classForm.block) { window.TNTT.toast.warning('Vui lòng chọn khối cho lớp!'); return; }
        if (this.classes.some(c => c.name === name && c.name !== old)) { window.TNTT.toast.warning('Tên lớp này đã tồn tại!'); return; }

        this.busyClass = true;
        this.save('org', 'saveClass', { original: old, name, block: this.classForm.block }).then(r => {
            this.busyClass = false;
            if (!r || !r.ok) { this.loadData(); return; }

            if (!old) {
                this.classes.push({ name, block: this.classForm.block, nextClass: '' });
                this.logAction('tao', 'org', 'Thêm lớp ' + name, 'khối ' + this.classForm.block);
            } else {
                this.logAction('sua', 'org', 'Sửa lớp ' + old, 'thành ' + name + ' · khối ' + this.classForm.block);
                const cls = this.classes.find(c => c.name === old);
                if (cls) { cls.name = name; cls.block = this.classForm.block; }
                this.students.forEach(s => {
                    if (s.className === old) { s.className = name; s.block = this.classForm.block; }
                });
                this.members.forEach(m => {
                    if (m.className === old) { m.className = name; m.block = this.classForm.block; }
                });
                this.announcements.forEach(a => { if (a.audienceType === 'lớp' && a.audienceValue === old) a.audienceValue = name; });
                if (this.user.assignedClass === old) this.user.assignedClass = name;
                if (this.filterClass === old) this.filterClass = name;
                if (this.attendanceClass === old) this.attendanceClass = name;
            }
            // Đổi khối/tên lớp kéo theo phân công của người trong lớp
            this.loadAllAssignments();
        });
        this.showClassModal = false;
    },

    async deleteClass(cls) {
        const n = this.classSize(cls.name);
        if (n > 0) {
            window.TNTT.toast.warning('Lớp "' + cls.name + '" còn ' + n + ' em trong danh sách.\nHãy chuyển các em sang lớp khác trước khi xóa.');
            return;
        }
        const glvCount = this.membersInClass(cls.name).length;
        if (glvCount > 0) {
            window.TNTT.toast.warning('Lớp "' + cls.name + '" còn ' + glvCount + ' GLV đang phụ trách.\nHãy chuyển họ sang lớp khác trước khi xóa.');
            return;
        }
        if (!await window.TNTT.toast.confirm('Xóa lớp "' + cls.name + '"?', { danger: true, confirmText: 'Xoá' })) return;
        this.busyClass = true;
        this.save('org', 'deleteClass', { name: cls.name }).then(r => {
            this.busyClass = false;
            if (!r || !r.ok) { this.loadData(); return; }
            this.classes = this.classes.filter(c => c.name !== cls.name);
            this.logAction('xoa', 'org', 'Xóa lớp ' + cls.name, '');
        });
    },

    // ---- CRUD NHÂN SỰ ----
    // ---- HÀNG CHỜ DUYỆT (tài khoản tự đăng ký) ----
    showApproveModal: false,
    approveForm: { id: null, name: '', phone: '', note: '', role: 'glv' },

    get pendingMembers() {
        return this.members.filter(m => m.status === 'chờ duyệt');
    },

    openApproveForm(m) {
        // Duyệt chỉ bật tài khoản + đặt vai cơ sở. Phân lớp làm sau ở Khối & Lớp.
        this.approveForm = {
            id: m.id, name: this.memberFullName(m), phone: m.phone,
            note: m.registerNote || '', role: 'glv'
        };
        this.showApproveModal = true;
    },

    async confirmApprove() {
        const f = this.approveForm;
        const r = await this.save('org', 'approveMember', { id: f.id, role: f.role });
        if (!r.ok) return;
        this.showApproveModal = false;
        await this.loadData();
    },

    async rejectMember(m) {
        if (!await window.TNTT.toast.confirm('Từ chối đăng ký của "' + this.memberFullName(m) + '"?\n\nTài khoản sẽ bị xóa hẳn.', { danger: true, confirmText: 'Từ chối' })) return;
        const r = await this.save('org', 'rejectMember', { id: m.id });
        if (!r.ok) return;
        await this.loadData();
    },

    // ---- CẤP LẠI MẬT KHẨU ----
    async resetMemberPassword(m) {
        if (!await window.TNTT.toast.confirm('Cấp lại mật khẩu cho "' + this.memberFullName(m) + '"?\n\n'
                   + 'Mật khẩu cũ sẽ hết hiệu lực ngay.', { confirmText: 'Cấp lại' })) return;

        const r = await this.save('org', 'resetPassword', { id: m.id });
        if (!r.ok) return;

        // Hiện một lần duy nhất để BĐH đọc cho GLV — máy chủ chỉ lưu bản băm,
        // đóng hộp này là không xem lại được nữa.
        // duration 0: KHÔNG tự tắt — mật khẩu tạm chỉ hiện một lần, BĐH phải
        // đọc xong rồi tự chạm đóng, không để nó biến mất giữa chừng.
        window.TNTT.toast.info('Đã cấp lại mật khẩu cho ' + r.name + '\n\n'
            + 'Số điện thoại: ' + r.phone + '\n'
            + 'Mật khẩu tạm:  ' + r.password + '\n\n'
            + 'Đọc cho GLV ghi lại. Lần đăng nhập tới hệ thống sẽ buộc họ đổi mật khẩu.\n'
            + 'Chạm để đóng — đóng rồi là không xem lại được nữa.', 0);
    },

    get filteredMembers() {
        const q = this.normalizeText(this.memberSearch);
        return this.members
            .filter(m => this.memberRoleFilter === '' || m.role === this.memberRoleFilter)
            .filter(m => q === ''
                || this.normalizeText(m.fullName).includes(q)
                || this.normalizeText(m.holyName).includes(q)
                || this.normalizeText(m.className).includes(q))
            .sort((a, b) => this.roleLevel(b.role) - this.roleLevel(a.role));
    },

    openEditMember(m) {
        this.memberForm = JSON.parse(JSON.stringify(m));
        this.isEditingMember = true;
        this.showMemberModal = true;
    },

    // Đổi role thì title và phạm vi phân công phải chỉnh theo cho khớp
    onMemberRoleChange() {
        const f = this.memberForm;
        const opts = this.titleOptionsFor(f.role);
        if (!opts.includes(f.title)) f.title = opts[0] || '';
        if (this.roleScope(f.role) === 'toàn đoàn') { f.block = ''; f.className = ''; }
        if (this.roleScope(f.role) === 'khối') f.className = '';
    },

    saveMember() {
        const f = this.memberForm;
        if (!f.fullName.trim()) { window.TNTT.toast.warning('Vui lòng nhập họ và tên!'); return; }
        const scope = this.roleScope(f.role);
        // Người đã kiêm nhiệm: ô vai trò + lớp/khối bị khóa (chỉ sửa danh tính),
        // nên KHÔNG đòi chọn lớp/khối — nếu đòi thì một GLV kiêm nhiệm không có
        // lớp gốc sẽ không sửa nổi cả số điện thoại. Backend cũng bỏ qua tương ứng.
        if (!this.isRoleLockedByAssignment(f)) {
            if (scope === 'khối' && !f.block) { window.TNTT.toast.warning('Vai trò Trưởng Khối cần chọn khối phụ trách!'); return; }
            if (scope === 'lớp' && !f.className) { window.TNTT.toast.warning('Vai trò này cần chọn lớp phụ trách!'); return; }
        }

        f.holyName = f.holyName.trim();
        f.fullName = f.fullName.trim();
        if (scope === 'lớp') {
            const cls = this.classes.find(c => c.name === f.className);
            if (cls) f.block = cls.block;
        }

        // Chủ nhiệm lớp / trưởng khối do màn Khối & Lớp quản qua phân công
        // (setClassHead/setBlockHead — backend tự hạ người cũ). Không đoán ở đây.

        {
            const i = this.members.findIndex(x => x.id === f.id);
            if (i !== -1) {
                this.members[i] = f;
                // Nếu sửa tài khoản của CHÍNH MÌNH, cập nhật luôn phiên hiện tại
                // để thẻ Cá nhân nhận phân công mới ngay lập tức.
                if (f.id === this.user.memberId) {
                    this.user.role = f.role;
                    this.user.roleTitle = f.title;
                    this.user.managedBlock = f.block;
                    this.user.assignedClass = f.className;
                    this.user.holyName = f.holyName;
                    this.user.fullName = f.fullName;
                    this.user.phone = f.phone;
                    this.user.birthDate = f.birthDate;
                    this.assignments = []; // Ép tính lại nhãn theo phân công mới nhất
                }
            }
        }
        this.logAction('sua', 'org', 'Sửa thành viên ' + f.fullName,
                       this.roleLabel(f.role) + ' · ' + (f.className || f.block || 'toàn đoàn'));
        this.showMemberModal = false;
        this.save('org', 'saveMember', f).then(r => { if (!r || !r.ok) this.loadData(); });
    },

    async deleteMember(m) {
        if (this.isProtectedMember(m)) {
            window.TNTT.toast.error('Không thể xóa thành viên Ban Điều Hành hoặc Quản trị từ màn này.');
            return;
        }
        if (await window.TNTT.toast.confirm('Xóa thành viên "' + this.memberFullName(m) + '"?', { danger: true, confirmText: 'Xoá' })) {
            this.members = this.members.filter(x => x.id !== m.id);
            this.logAction('xoa', 'org', 'Xóa thành viên ' + m.fullName, this.roleLabel(m.role));
            this.save('org', 'deleteMember', { id: m.id }).then(r => { if (!r || !r.ok) this.loadData(); });
        }
    },

    // Quản lý phân công kiêm nhiệm đã chuyển sang màn Khối & Lớp:
    // addClassMember / removeClassAssignment / setClassHead / setBlockHead.
};
