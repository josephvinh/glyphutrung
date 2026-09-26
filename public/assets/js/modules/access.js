/* ==========================================================
   ACCESS — Phân quyền và lọc theo phạm vi
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.access = {
    // ==========================================
    // 10. PHÂN QUYỀN & LỌC
    // ==========================================

    // --- Nguồn dẫn xuất phạm vi (kiêm nhiệm): đọc từ this.assignments ---
    // Không giới hạn nếu có BẤT KỲ phân công 'toàn đoàn' nào (Quản Trị / BĐH).
    get isUnrestrictedScope() {
        const a = this.assignments || [];
        if (a.length) return a.some(x => x.scope === 'toàn đoàn');
        return ['admin', 'bdh'].includes(this.user.role);   // fallback dữ liệu cũ
    },

    // Tên các KHỐI mình phụ trách, hợp mọi phân công đang hiệu lực.
    get myBlocks() {
        const a = this.assignments || [];
        if (!a.length) {   // fallback theo phân công chính
            if (this.user.role === 'truong_khoi') return [this.user.managedBlock];
            const cls = this.classes.find(c => c.name === this.user.assignedClass);
            return cls ? [cls.block] : [];
        }
        const set = new Set();
        a.forEach(x => {
            if (x.blockName) set.add(x.blockName);
            else if (x.className) {
                const c = this.classes.find(cc => cc.name === x.className);
                if (c) set.add(c.block);
            }
        });
        return [...set];
    },

    // Tên các KHỐI mình LÀM TRƯỞNG (phân công phạm vi 'khối') — hẹp hơn
    // myBlocks: không tính khối chỉ vì mình dạy một lớp trong đó. Dùng cho
    // quyền quản thông báo khối.
    get myHeadBlocks() {
        const a = this.assignments || [];
        if (!a.length) {   // fallback dữ liệu cũ
            return this.user.role === 'truong_khoi' && this.user.managedBlock
                ? [this.user.managedBlock] : [];
        }
        return [...new Set(a.filter(x => x.scope === 'khối' && x.blockName).map(x => x.blockName))];
    },

    // Tên các LỚP mình phụ trách; phân công khối mở rộng ra mọi lớp trong khối.
    get myClasses() {
        const a = this.assignments || [];
        if (!a.length) {   // fallback theo phân công chính
            if (this.user.role === 'truong_khoi') {
                return this.classes.filter(c => c.block === this.user.managedBlock).map(c => c.name);
            }
            return this.user.assignedClass ? [this.user.assignedClass] : [];
        }
        const set = new Set();
        a.forEach(x => {
            if (x.className) set.add(x.className);
            else if (x.blockName) {
                this.classes.filter(c => c.block === x.blockName).forEach(c => set.add(c.name));
            }
        });
        return [...set];
    },

    get accessibleStudents() {
        if (this.isUnrestrictedScope) return this.students;
        const classes = this.myClasses;
        return this.students.filter(s => classes.includes(s.className));
    },

    // Khối được phép xem, lấy từ danh mục chứ không lấy từ dữ liệu thiếu nhi
    get availableBlocks() {
        if (this.isUnrestrictedScope) return this.blocks;
        const mine = this.myBlocks;
        return this.blocks.filter(b => mine.includes(b));
    },

    // Lớp hiện trong bộ lọc, kể cả lớp mới mở chưa có em nào
    get availableClasses() {
        let list = this.classes.filter(c => this.availableBlocks.includes(c.block));
        if (!this.isUnrestrictedScope) {
            const mine = this.myClasses;
            list = list.filter(c => mine.includes(c.name));
        }
        if (this.filterBlock !== '') list = list.filter(c => c.block === this.filterBlock);
        return list.map(c => c.name);
    },

    /**
     * Các lớp mình được GHI vào — khác availableClasses ở chỗ không
     * bị bộ lọc đang chọn thu hẹp. Dùng cho phần nhập danh sách, vì
     * quyền ghi không đổi theo việc người dùng đang lọc gì.
     * Trả về null nghĩa là không giới hạn (Quản Trị, Ban Điều Hành).
     */
    get writableClasses() {
        if (this.isUnrestrictedScope) return null;   // Quản Trị / BĐH: không giới hạn
        return this.myClasses;
    },

    get filteredStudents() {
        const q = (this.searchQuery || '').trim();
        // Toàn đoàn (Quản Trị / Ban Điều Hành): KHÔNG đổ cả trăm/nghìn em ra
        // ngay. Bắt buộc chọn khối/lớp (hoặc gõ tìm) mới hiện — nhẹ + gọn.
        if (this.isUnrestrictedScope && q === '' && this.filterBlock === '' && this.filterClass === '') {
            return [];
        }
        return this.accessibleStudents.filter(s => {
            const matchSearch = this.matchStudentSearch(s, q);
            const matchStatus = this.filterStatus === '' || s.status === this.filterStatus;
            const matchBlock  = this.filterBlock === ''  || s.block === this.filterBlock;
            const matchClass  = this.filterClass === ''  || s.className === this.filterClass;
            // Enhanced filters (Phase 1)
            const matchGender = this.filterGender === '' || String(s.gender) === this.filterGender;
            const matchAddress = this.filterAddress === '' ||
                (s.address && this.normalizeText(s.address).includes(this.normalizeText(this.filterAddress)));
            // Age filter
            let matchAge = true;
            if (this.filterAgeFrom !== '' || this.filterAgeTo !== '') {
                const age = this._calculateAge(s.birthDate);
                if (this.filterAgeFrom !== '' && age < parseInt(this.filterAgeFrom)) matchAge = false;
                if (this.filterAgeTo !== '' && age > parseInt(this.filterAgeTo)) matchAge = false;
            }
            return matchSearch && matchStatus && matchBlock && matchClass && matchGender && matchAge && matchAddress;
        });
    },

    // Tính tuổi từ ngày sinh
    _calculateAge(birthDate) {
        if (!birthDate) return 0;
        const birth = new Date(birthDate);
        const today = new Date();
        let age = today.getFullYear() - birth.getFullYear();
        const monthDiff = today.getMonth() - birth.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
            age--;
        }
        return age;
    },

    get hasActiveFilter() {
        return this.filterStatus !== '' || this.filterBlock !== '' || this.filterClass !== ''
            || this.filterGender !== '' || this.filterAgeFrom !== '' || this.filterAgeTo !== ''
            || this.filterAddress !== '';
    },

    clearFilters() {
        this.filterBlock = '';
        this.filterClass = '';
        this.filterStatus = '';
        this.filterGender = '';
        this.filterAgeFrom = '';
        this.filterAgeTo = '';
        this.filterAddress = '';
    },
};
