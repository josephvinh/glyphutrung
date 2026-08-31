/* ==========================================================
   ACCESS — Phân quyền và lọc theo phạm vi
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.access = {
    // ==========================================
    // 10. PHÂN QUYỀN & LỌC
    // ==========================================
    get accessibleStudents() {
        if (['admin', 'bdh'].includes(this.user.role)) return this.students;
        if (this.user.role === 'truong_khoi') return this.students.filter(s => s.block === this.user.managedBlock);
        return this.students.filter(s => s.className === this.user.assignedClass);
    },

    // Khối được phép xem, lấy từ danh mục chứ không lấy từ dữ liệu thiếu nhi
    get availableBlocks() {
        if (['admin', 'bdh'].includes(this.user.role)) return this.blocks;
        if (this.user.role === 'truong_khoi') return this.blocks.filter(b => b === this.user.managedBlock);
        const cls = this.classes.find(c => c.name === this.user.assignedClass);
        return cls ? [cls.block] : [];
    },

    // Lớp hiện trong bộ lọc, kể cả lớp mới mở chưa có em nào
    get availableClasses() {
        let list = this.classes.filter(c => this.availableBlocks.includes(c.block));
        if (!['admin', 'bdh', 'truong_khoi'].includes(this.user.role)) {
            list = list.filter(c => c.name === this.user.assignedClass);
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
        const vt = this.user.role;
        if (vt === 'admin' || vt === 'bdh') return null;
        if (vt === 'truong_khoi') {
            return this.classes.filter(c => c.block === this.user.managedBlock).map(c => c.name);
        }
        return this.user.assignedClass ? [this.user.assignedClass] : [];
    },

    get filteredStudents() {
        const q = this.normalizeText(this.searchQuery);
        return this.accessibleStudents.filter(s => {
            const matchSearch = q === ''
                || this.normalizeText(s.name).includes(q)
                || this.normalizeText(s.holyName).includes(q)
                || this.normalizeText(s.code).includes(q);
            const matchStatus = this.filterStatus === '' || s.status === this.filterStatus;
            const matchBlock  = this.filterBlock === ''  || s.block === this.filterBlock;
            const matchClass  = this.filterClass === ''  || s.className === this.filterClass;
            return matchSearch && matchStatus && matchBlock && matchClass;
        });
    },

    get hasActiveFilter() {
        return this.filterStatus !== '' || this.filterBlock !== '' || this.filterClass !== '';
    },

    clearFilters() {
        this.filterStatus = '';
        this.filterBlock = '';
        this.filterClass = '';
    },
};
