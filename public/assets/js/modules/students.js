/* ==========================================================
   STUDENTS — Danh sách thiếu nhi và xuất/nhập CSV
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.

   Phase 3: Favorites, Auto-save Draft, Keyboard Shortcuts
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.students = {
    // ==========================================
    // 2. DATA: DANH SÁCH LỚP & HÀM XỬ LÝ
    // gender: 1 = Nam, 0 = Nữ
    // ==========================================
    // Chỉ gồm các em trong phạm vi mình được XEM (GLV: lớp mình,
    // Trưởng Khối: khối mình, Ban Điều Hành: toàn đoàn).
    students: [],   // máy chủ nạp qua loadData()
    studentIndex: null,   // Map id -> em, dựng ở loadData (studentById O(1))

    // Sĩ số MỌI lớp, đếm sẵn ở máy chủ: { "Khai Tâm 1A": 7, ... }
    // Dùng cho màn Khối & Lớp, vì students ở trên không đủ để đếm.
    classCounts: {},

    // View mode: 'grid' | 'list' — lưu vào localStorage
    viewMode: localStorage.getItem('studentsViewMode') || 'grid',

    searchQuery: '',
    filterStatus: '',
    filterBlock: '',
    filterClass: '',
    // Enhanced filters
    filterGender: '',
    filterAgeFrom: '',
    filterAgeTo: '',
    filterAddress: '',
    showFilter: false,
    showEditModal: false,
    editData: {},

    busy: false,
    displayLimit: 20,

    // ==========================================
    // PHASE 3: FAVORITES
    // ==========================================
    favoriteStudents: [],   // array of student IDs that user has favorited

    // Check if a student is favorited
    isFavorite(studentId) {
        return this.favoriteStudents.includes(studentId);
    },

    // Load favorites from API
    async loadFavorites() {
        try {
            const r = await this.api('students', 'favorites_list');
            if (r && r.ok && Array.isArray(r.favorites)) {
                this.favoriteStudents = r.favorites.map(f => f.student_id);
            }
        } catch (err) {
            console.warn('Could not load favorites:', err);
        }
    },

    // Toggle favorite status for a student
    async toggleFavorite(studentId) {
        try {
            const r = await this.save('students', 'favorites_toggle', { student_id: studentId });
            if (r && r.ok) {
                if (r.favorited) {
                    if (!this.favoriteStudents.includes(studentId)) {
                        this.favoriteStudents.push(studentId);
                    }
                } else {
                    const idx = this.favoriteStudents.indexOf(studentId);
                    if (idx > -1) this.favoriteStudents.splice(idx, 1);
                }
            }
        } catch (err) {
            console.error('Toggle favorite failed:', err);
            window.TNTT.toast.error('Không cập nhật được yêu thích.');
        }
    },

    // ==========================================
    // PHASE 3: AUTO-SAVE DRAFT
    // ==========================================
    draftTimer: null,
    draftDebounceMs: 1500,  // Save draft 1.5s after last change

    // Get draft storage key for a student
    _draftKey(studentId) {
        return 'student_draft_' + (studentId || 'new');
    },

    // Check if draft exists for current form
    get hasDraft() {
        const key = this._draftKey(this.editData?.id);
        return localStorage.getItem(key) !== null;
    },

    // Get draft age (for display)
    getDraftAge() {
        const key = this._draftKey(this.editData?.id);
        const stored = localStorage.getItem(key);
        if (!stored) return null;
        try {
            const data = JSON.parse(stored);
            if (data._savedAt) {
                const saved = new Date(data._savedAt);
                const now = new Date();
                const diffMs = now - saved;
                const diffMins = Math.floor(diffMs / 60000);
                if (diffMins < 1) return 'vừa xong';
                if (diffMins === 1) return '1 phút trước';
                if (diffMins < 60) return diffMins + ' phút trước';
                const diffHours = Math.floor(diffMins / 60);
                if (diffHours === 1) return '1 giờ trước';
                if (diffHours < 24) return diffHours + ' giờ trước';
                return null; // Too old, don't show
            }
        } catch (e) {}
        return null;
    },

    // Save draft to localStorage (debounced)
    scheduleDraftSave() {
        if (this.draftTimer) clearTimeout(this.draftTimer);
        if (!this.showEditModal || !this.editData) return;

        this.draftTimer = setTimeout(() => {
            this.saveDraft();
        }, this.draftDebounceMs);
    },

    // Save current form data to localStorage
    saveDraft() {
        if (!this.showEditModal || !this.editData) return;

        const key = this._draftKey(this.editData.id);
        const draftData = {
            holyName: this.editData.holyName || '',
            name: this.editData.name || '',
            gender: this.editData.gender ?? 1,
            birthDate: this.editData.birthDate || '',
            address: this.editData.address || '',
            fatherName: this.editData.fatherName || '',
            fatherPhone: this.editData.fatherPhone || '',
            motherName: this.editData.motherName || '',
            motherPhone: this.editData.motherPhone || '',
            className: this.editData.className || '',
            status: this.editData.status || 'đang sinh hoạt',
            _savedAt: new Date().toISOString()
        };

        try {
            localStorage.setItem(key, JSON.stringify(draftData));
        } catch (e) {
            console.warn('Could not save draft:', e);
        }
    },

    // Load draft from localStorage (returns true if draft was loaded)
    loadDraft(studentId) {
        const key = this._draftKey(studentId);
        const stored = localStorage.getItem(key);
        if (!stored) return false;

        try {
            const draft = JSON.parse(stored);
            // Check if draft is recent enough (24 hours)
            if (draft._savedAt) {
                const saved = new Date(draft._savedAt);
                const now = new Date();
                const diffHours = (now - saved) / (1000 * 60 * 60);
                if (diffHours > 24) {
                    this.clearDraft(studentId);
                    return false;
                }
            }
            return draft;
        } catch (e) {
            return false;
        }
    },

    // Clear draft from localStorage
    clearDraft(studentId) {
        const key = this._draftKey(studentId || 'new');
        localStorage.removeItem(key);
    },

    // Apply draft to edit form
    applyDraft(draft) {
        if (!draft || !this.editData) return;
        Object.assign(this.editData, {
            holyName: draft.holyName || '',
            name: draft.name || '',
            gender: draft.gender ?? 1,
            birthDate: draft.birthDate || '',
            address: draft.address || '',
            fatherName: draft.fatherName || '',
            fatherPhone: draft.fatherPhone || '',
            motherName: draft.motherName || '',
            motherPhone: draft.motherPhone || '',
            className: draft.className || '',
            status: draft.status || 'đang sinh hoạt'
        });
    },

    // ==========================================
    // PHASE 3: KEYBOARD SHORTCUTS
    // ==========================================
    keyboardHandler: null,

    // Initialize keyboard shortcuts
    initKeyboardShortcuts() {
        if (this.keyboardHandler) return;

        this.keyboardHandler = (e) => {
            // Don't trigger shortcuts when typing in inputs
            const tag = e.target.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
            if (e.target.isContentEditable) return;

            switch(e.key.toLowerCase()) {
                case '/':
                    e.preventDefault();
                    // Focus search input
                    const searchInput = document.querySelector('[x-model="searchQuery"]');
                    if (searchInput) searchInput.focus();
                    break;

                case 'j':
                    e.preventDefault();
                    this.selectNext();
                    break;

                case 'k':
                    e.preventDefault();
                    this.selectPrev();
                    break;

                case 'e':
                    e.preventDefault();
                    // Edit selected student (only if exactly one selected)
                    if (this.selectedStudents.length === 1) {
                        const sid = this.selectedStudents[0];
                        const student = this.studentIndex?.get(sid);
                        if (student) this.openEdit(student);
                    }
                    break;

                case 'Escape':
                    e.preventDefault();
                    this.clearSelection();
                    break;

                case 'n':
                    e.preventDefault();
                    // Open add new student modal (only if user can edit)
                    if (window.TNTT.canEditModule?.('students')) {
                        this.openAddStudent();
                    }
                    break;
            }
        };

        document.addEventListener('keydown', this.keyboardHandler);
    },

    // Cleanup keyboard shortcuts
    destroyKeyboardShortcuts() {
        if (this.keyboardHandler) {
            document.removeEventListener('keydown', this.keyboardHandler);
            this.keyboardHandler = null;
        }
    },

    // Select next student in list
    selectNext() {
        const list = this.filteredStudents;
        if (list.length === 0) return;

        if (this.selectedStudents.length === 0) {
            // Select first item
            this.selectedStudents = [list[0].id];
        } else {
            const lastSelected = this.selectedStudents[this.selectedStudents.length - 1];
            const currentIndex = list.findIndex(s => s.id === lastSelected);
            if (currentIndex < list.length - 1) {
                this.selectedStudents = [list[currentIndex + 1].id];
            }
        }
        this.scrollSelectedIntoView();
    },

    // Select previous student in list
    selectPrev() {
        const list = this.filteredStudents;
        if (list.length === 0) return;

        if (this.selectedStudents.length === 0) {
            // Select last item
            this.selectedStudents = [list[list.length - 1].id];
        } else {
            const firstSelected = this.selectedStudents[0];
            const currentIndex = list.findIndex(s => s.id === firstSelected);
            if (currentIndex > 0) {
                this.selectedStudents = [list[currentIndex - 1].id];
            }
        }
        this.scrollSelectedIntoView();
    },

    // Scroll selected student into view
    scrollSelectedIntoView() {
        this.$nextTick(() => {
            const selectedId = this.selectedStudents[0];
            if (!selectedId) return;

            const card = document.querySelector(`[data-student-id="${selectedId}"]`);
            if (card) {
                card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    },

    // ==========================================
    // 7. BULK SELECTION & ACTIONS
    // ==========================================
    selectedStudents: [],
    showBulkMoveModal: false,
    targetClassForMove: '',

    // Toggle selection for a single student
    toggleStudentSelection(studentId) {
        const idx = this.selectedStudents.indexOf(studentId);
        if (idx > -1) {
            this.selectedStudents.splice(idx, 1);
        } else {
            this.selectedStudents.push(studentId);
        }
    },

    // Check if a student is selected
    isStudentSelected(studentId) {
        return this.selectedStudents.indexOf(studentId) > -1;
    },

    // Toggle select all / deselect all in current view
    toggleSelectAll() {
        if (this.allDisplayedSelected) {
            // All currently displayed are selected → deselect only those
            this.selectedStudents = this.selectedStudents.filter(
                id => !this.filteredStudents.some(s => s.id === id)
            );
        } else {
            // Select all currently displayed (merge with existing selections)
            const displayedIds = this.filteredStudents.map(s => s.id);
            for (const id of displayedIds) {
                if (this.selectedStudents.indexOf(id) === -1) {
                    this.selectedStudents.push(id);
                }
            }
        }
    },

    // Check if all currently displayed students are selected
    get allDisplayedSelected() {
        if (this.filteredStudents.length === 0) return false;
        return this.filteredStudents.every(s => this.selectedStudents.indexOf(s.id) > -1);
    },

    // Open bulk move modal
    openBulkMoveModal() {
        this.targetClassForMove = '';
        this.showBulkMoveModal = true;
    },

    // Execute bulk move
    async executeBulkMove() {
        if (!this.targetClassForMove) {
            window.TNTT.toast.warning('Vui lòng chọn lớp đích.');
            return;
        }
        if (this.selectedStudents.length === 0) {
            window.TNTT.toast.warning('Không có em nào được chọn.');
            return;
        }

        this.busy = true;
        try {
            const r = await this.save('students', 'bulk_move', {
                student_ids: this.selectedStudents,
                target_class: this.targetClassForMove
            });
            if (r && r.ok) {
                window.TNTT.toast.info(`Đã chuyển ${r.moved || this.selectedStudents.length} em sang lớp ${this.targetClassForMove}.`);
                this.selectedStudents = [];
                this.showBulkMoveModal = false;
                await this.loadData();
            }
        } catch (err) {
            console.error(err);
            window.TNTT.toast.error('Chuyển lớp không thành công.');
        } finally {
            this.busy = false;
        }
    },

    // Confirm and execute bulk delete
    async confirmBulkDelete() {
        const count = this.selectedStudents.length;
        if (count === 0) {
            window.TNTT.toast.warning('Không có em nào được chọn.');
            return;
        }

        const confirmed = await window.TNTT.toast.confirm(
            `Xóa ${count} em khỏi danh sách?\n\nHành động này không thể hoàn tác.`,
            { danger: true, confirmText: `Xóa ${count} em`, cancelText: 'Hủy bỏ' }
        );

        if (!confirmed) return;

        this.busy = true;
        try {
            const r = await this.save('students', 'bulk_delete', {
                student_ids: this.selectedStudents
            });
            if (r && r.ok) {
                window.TNTT.toast.info(`Đã xóa ${r.deleted || count} em.`);
                this.selectedStudents = [];
                await this.loadData();
            }
        } catch (err) {
            console.error(err);
            window.TNTT.toast.error('Xóa không thành công.');
        } finally {
            this.busy = false;
        }
    },

    // Clear all selections
    clearSelection() {
        this.selectedStudents = [];
    },

    // Chỉ số em theo id — cùng khuôn attIndex/scoreIndex/reportIndex.
    // studentById() bị gọi cho TỪNG dòng ở danh sách Xin phép nên find()
    // tuyến tính làm chậm màn đó khi nhiều đơn.
    rebuildStudentIndex() {
        const idx = new Map();
        for (const s of this.students) idx.set(s.id, s);
        this.studentIndex = idx;
    },

    get displayedStudents() {
        return this.filteredStudents.slice(0, this.displayLimit);
    },

    loadMore() {
        this.displayLimit += 20;
    },

    openEdit(student) {
        this.editData = JSON.parse(JSON.stringify(student));
        this.editData.isNew = false;
        this.showEditModal = true;
        this._snapEdit();

        // Check for draft and prompt to restore
        const draft = this.loadDraft(student.id);
        if (draft) {
            this.applyDraft(draft);
            this._snapEdit(); // Update snapshot after applying draft
            window.TNTT.toast.info('Đã khôi phục nháp trước đó.');
        }
    },

    // ---- Chống mất dữ liệu khi lỡ đóng cửa sổ đang sửa dở ----
    // Chụp lại nội dung form lúc mở để so sánh khi đóng. Bỏ qua `code`
    // (máy chủ tự cấp, đổi sau khi mở) và `isNew` để không báo nhầm.
    _editSnapshot: '',
    _snapEditKey(o) {
        const c = Object.assign({}, o || {});
        delete c.code; delete c.isNew;
        return JSON.stringify(c);
    },
    _snapEdit() { this._editSnapshot = this._snapEditKey(this.editData); },
    _editDirty() {
        return this.showEditModal && this._snapEditKey(this.editData) !== this._editSnapshot;
    },
    async tryCloseEdit() {
        if (this.draftTimer) clearTimeout(this.draftTimer);
        if (this._editDirty()) {
            const bo = await window.TNTT.toast.confirm(
                'Bỏ các thay đổi chưa lưu?',
                { danger: true, confirmText: 'Bỏ thay đổi', cancelText: 'Tiếp tục sửa' });
            if (!bo) return;
            // User confirmed to discard changes, don't save draft
            this.clearDraft(this.editData?.id);
        }
        this.showEditModal = false;
    },

    /**
     * THÊM MỘT EM MỚI
     *
     * Dùng chung cửa sổ với sửa hồ sơ — cùng bộ trường, cùng chỗ lưu.
     * Điền sẵn lớp mình phụ trách: chủ nhiệm chỉ có một lớp nên khỏi
     * phải chọn, còn Ban Điều Hành thì để trống cho tự chọn.
     */
    openAddStudent() {
        const ghi = this.writableClasses;
        const lop = this.filterClass
                 || (ghi && ghi.length === 1 ? ghi[0] : '');
        const cls = this.classes.find(c => c.name === lop);

        this.editData = {
            id: null, isNew: true,
            code: 'Đang cấp…',      // máy chủ tự cấp; điền ngay bên dưới
            holyName: '', name: '',
            gender: 1, birthDate: '', address: '',
            fatherName: '', fatherPhone: '',
            motherName: '', motherPhone: '',
            className: lop, block: cls ? cls.block : '',
            status: 'đang sinh hoạt'
        };
        this.showEditModal = true;
        this._snapEdit();
        this.fillNextStudentCode();

        // Check for draft and prompt to restore
        const draft = this.loadDraft(null);
        if (draft) {
            this.applyDraft(draft);
            this._snapEdit();
            window.TNTT.toast.info('Đã khôi phục nháp trước đó.');
        }
    },

    // Lấy mã kế tiếp từ máy chủ để hiện sẵn (chỉ đọc). Máy chủ vẫn cấp lại
    // lúc lưu nên đây chỉ là xem trước — client không tự sinh để tránh trùng.
    async fillNextStudentCode() {
        const r = await this.api('students', 'next_code');
        if (r && r.ok && this.editData.isNew) this.editData.code = r.code;
    },

    get editModalTitle() {
        return this.editData.isNew ? 'Thêm thiếu nhi' : 'Cập nhật hồ sơ';
    },

    async saveEdit() {
        const e = this.editData;

        if (!String(e.name || '').trim())  return window.TNTT.toast.warning('Vui lòng nhập họ và tên.');
        if (!e.className)                 return window.TNTT.toast.warning('Vui lòng chọn lớp cho em.');

        e.name = String(e.name).trim();

        // Lớp đổi thì khối phải đổi theo, tránh dữ liệu mâu thuẫn
        const cls = this.classes.find(c => c.name === e.className);
        if (cls) e.block = cls.block;

        this.busy = true;
        try {
            const r = await this.save('students', 'save', e);
            if (r && r.ok) {
                // Clear draft after successful save
                this.clearDraft(e.id);
                // Luôn nạp lại dữ liệu từ server sau khi lưu để lấy bản đã được chuẩn hóa (In hoa, số 0 đầu điện thoại...)
                await this.loadData();
                this.showEditModal = false;
            }
        } catch (err) {
            console.error(err);
            window.TNTT.toast.error('Lưu không thành công. Xin kiểm tra kết nối mạng và thử lại.');
        } finally {
            this.busy = false;
        }
    },

    // ==========================================
    // 3. XUẤT / NHẬP FILE CSV
    // ==========================================
    importColumns: [
        { header: 'Mã số',      key: 'code' },
        { header: 'Tên Thánh',  key: 'holyName' },
        { header: 'Họ và Tên',  key: 'name' },
        { header: 'Giới tính',  key: 'gender' },
        { header: 'Ngày sinh',  key: 'birthDate' },
        { header: 'Khối',       key: 'block' },
        { header: 'Lớp',        key: 'className' },
        { header: 'Tình trạng', key: 'status' },
        { header: 'Tên Cha',    key: 'fatherName' },
        { header: 'SĐT Cha',    key: 'fatherPhone' },
        { header: 'Tên Mẹ',     key: 'motherName' },
        { header: 'SĐT Mẹ',     key: 'motherPhone' },
        { header: 'Địa chỉ',    key: 'address' }
    ],

    // Bọc 1 ô CSV: nhân đôi dấu nháy kép, nếu không tên kiểu Nguyễn Văn "Bo" sẽ phá vỡ cấu trúc file
    csvCell(value) {
        const str = (value === null || value === undefined) ? '' : String(value);
        return '"' + str.replace(/"/g, '""') + '"';
    },

    // ==========================================
    // XUẤT FILE
    //
    // Có dữ liệu  -> xuất danh sách như bình thường.
    // Không có gì -> xuất FILE MẪU đúng định dạng, để GLV điền rồi
    //                nhập ngược lại. Trước đây chỗ này chỉ báo "Không có
    //                dữ liệu để xuất!" rồi thôi, trong khi thông báo lỗi
    //                bên phần Nhập lại bảo người dùng "bấm Xuất để lấy
    //                file mẫu" — hứa mà không có.
    // ==========================================
    exportToCSV() {
        if (this.filteredStudents.length === 0) {
            this.exportTemplateCSV();
            return;
        }

        const lines = [this.importColumns.map(c => this.csvCell(c.header)).join(',')];
        this.filteredStudents.forEach(s => {
            lines.push(this.importColumns.map(c => {
                if (c.key === 'gender')    return this.csvCell(this.genderLabel(s.gender));
                if (c.key === 'birthDate') return this.csvCell(this.formatDate(s.birthDate));
                return this.csvCell(s[c.key]);
            }).join(','));
        });

        this.taiFileCSV(lines, 'Danh_Sach_Thieu_Nhi_' + this.todayStamp() + '.csv');
    },

    // Bỏ dấu và thay khoảng trắng, để tên file không bị vỡ trên Windows
    tenFileAnToan(s) {
        return String(s || '')
            .normalize('NFD').replace(/[̀-ͯ]/g, '')
            .replace(/đ/g, 'd').replace(/Đ/g, 'D')
            .replace(/[^A-Za-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
    },

    taiFileCSV(lines, tenFile) {
        // BOM (U+FEFF) để Excel nhận đúng tiếng Việt UTF-8
        const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = tenFile;
        link.click();
        URL.revokeObjectURL(url);
    },

    /**
     * File mẫu để nhập danh sách.
     *
     * Phần hướng dẫn nằm ở các dòng bắt đầu bằng #, và applyImport() bỏ
     * qua chúng — nên người dùng KHÔNG cần xoá gì trước khi nhập lại.
     * Danh sách lớp hợp lệ lấy từ dữ liệu sống, không chép cứng, nên
     * luôn khớp với những gì đang khai ở màn Khối & Lớp.
     */
    exportTemplateCSV() {
        // Không lọc lớp nào thì lấy lớp mình phụ trách — chủ nhiệm chỉ có
        // đúng một lớp, nên mẫu ra là đã đúng lớp, khỏi phải nhớ tên.
        const chiGhi = this.writableClasses;
        const lop   = this.filterClass
                   || (chiGhi && chiGhi.length === 1 ? chiGhi[0] : '');
        const cls   = this.classes.find(c => c.name === lop);
        const khoi  = cls ? cls.block : (this.filterBlock || '');
        // Chỉ liệt kê lớp mình được GHI vào. Liệt kê cả lớp không được ghi
        // chỉ khiến người dùng điền vào rồi bị máy chủ từ chối.
        const dsLop = chiGhi === null ? this.classes.map(c => c.name) : chiGhi;

        // Ví dụ điền sẵn lớp đang lọc, để GLV chỉ việc thay tên các em
        const viDu = {
            code: 'TN26001', holyName: 'Phêrô', name: 'Nguyễn Văn An',
            gender: 'Nam', birthDate: '05/09/2017',
            block: khoi || 'Khai Tâm', className: lop || (dsLop[0] || 'Khai Tâm 1A'),
            status: 'đang sinh hoạt',
            fatherName: 'Nguyễn Văn Bình', fatherPhone: '0901234567',
            motherName: 'Trần Thị Cúc',    motherPhone: '0907654321',
            address: '12 Nguyễn Trãi'
        };

        const bat = this.importColumns.filter(c => ['name'].includes(c.key))
                                      .map(c => c.header).join(', ');

        const lines = [];
        const ghi = (t) => lines.push('# ' + t);

        lines.push('# ' + '='.repeat(58));
        ghi('MẪU NHẬP DANH SÁCH THIẾU NHI — TNTT Super App');
        if (lop)  ghi('Lớp: ' + lop + (khoi ? '  ·  Khối: ' + khoi : ''));
        if (this.year) ghi('Niên khoá: ' + this.year.name);
        ghi('Xuất lúc: ' + this.formatDate(this.toDateInput(new Date())));
        ghi('');
        ghi('CÁCH DÙNG');
        ghi('  1. Mở file bằng Excel hoặc Google Sheet');
        ghi('  2. Điền mỗi em một dòng, ngay dưới hàng tiêu đề');
        ghi('  3. Lưu lại dạng CSV UTF-8, rồi bấm nút Nhập trên app');
        ghi('');
        ghi('Mọi dòng bắt đầu bằng dấu # đều được bỏ qua khi nhập,');
        ghi('kể cả dòng ví dụ bên dưới — cứ để nguyên, không cần xoá.');
        ghi('');
        ghi('BẮT BUỘC   : ' + bat + '  (thiếu là bỏ qua dòng đó)');
        ghi('Mã số      : để trống nếu nhập mới (hệ thống tự cấp). Ghi mã đã có để CẬP NHẬT.');
        ghi('Giới tính  : Nam | Nữ');
        ghi('Ngày sinh  : dd/mm/yyyy   ví dụ 05/09/2017');
        ghi('Tình trạng : ' + this.statusOptions.join(' | '));
        ghi('Khối       : tự suy ra từ Lớp, ghi sai cũng không ảnh hưởng');
        ghi('Lớp        : phải trùng đúng tên đã khai ở màn Khối & Lớp');
        if (dsLop.length) {
            const dong = [];
            for (let i = 0; i < dsLop.length; i += 3) dong.push(dsLop.slice(i, i + 3).join('  |  '));
            dong.forEach((d, i) => ghi('             ' + d));
        } else {
            ghi('             (chưa khai lớp nào — hãy vào Khối & Lớp tạo trước)');
        }
        ghi('');
        lines.push('# ' + '='.repeat(58));

        // Hàng tiêu đề thật — chính là hàng mà applyImport() dò cột
        lines.push(this.importColumns.map(c => this.csvCell(c.header)).join(','));

        // Dòng ví dụ, gắn # ở ô đầu nên nhập lại cũng không tạo ra em ma
        lines.push(this.importColumns.map((c, i) =>
            this.csvCell((i === 0 ? '#' : '') + (viDu[c.key] || ''))).join(','));

        this.taiFileCSV(lines,
            'Mau_Nhap_Danh_Sach' + (lop ? '_' + this.tenFileAnToan(lop) : '') + '_' + this.todayStamp() + '.csv');

        window.TNTT.toast.info('Lớp này chưa có em nào.\n\n'
            + 'Đã tải về FILE MẪU đúng định dạng'
            + (lop ? ' cho lớp ' + lop : '') + '.\n'
            + 'Điền vào rồi bấm Nhập để đưa danh sách lên.', 7000);
    },

    // Tách CSV thủ công (xử lý ô bọc nháy kép, dấu phẩy và xuống dòng nằm bên trong ô)
    parseCSV(text) {
        const rows = [];
        let row = [], field = '', inQuotes = false;
        if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);

        for (let i = 0; i < text.length; i++) {
            const c = text[i];
            if (inQuotes) {
                if (c === '"') {
                    if (text[i + 1] === '"') { field += '"'; i++; }
                    else { inQuotes = false; }
                } else { field += c; }
            } else if (c === '"') {
                inQuotes = true;
            } else if (c === ',') {
                row.push(field); field = '';
            } else if (c === '\n') {
                row.push(field); rows.push(row); row = []; field = '';
            } else if (c !== '\r') {
                field += c;
            }
        }
        if (field !== '' || row.length > 0) { row.push(field); rows.push(row); }

        return rows.filter(r => r.some(cell => cell.trim() !== ''));
    },

    handleImport(event) {
        const file = event.target.files[0];
        event.target.value = ''; // reset để chọn lại đúng file đó vẫn kích hoạt được
        if (!file) return;

        if (!/\.csv$/i.test(file.name)) {
            window.TNTT.toast.warning('Hiện chỉ nhận file .csv.\nTrong Excel bạn chọn "Lưu dưới dạng" -> CSV UTF-8 rồi tải lên lại nhé.');
            return;
        }

        const reader = new FileReader();
        reader.onerror = () => window.TNTT.toast.error('Không đọc được file. Vui lòng thử lại.');
        reader.onload = (e) => this.applyImport(e.target.result, file.name);
        reader.readAsText(file, 'UTF-8');
    },

    applyImport(text, fileName) {
        // Dòng bắt đầu bằng # là chú thích trong file mẫu (phần hướng dẫn
        // và dòng ví dụ). Bỏ chúng TRƯỚC khi dò tiêu đề, để người dùng cứ
        // để nguyên phần hướng dẫn mà nhập vẫn chạy đúng.
        const rows = this.parseCSV(text)
            .filter(r => !String(r[0] || "").trim().startsWith("#"));
        if (rows.length < 2) {
            window.TNTT.toast.warning('File không có dòng dữ liệu nào.');
            return;
        }

        // Dò cột theo tên tiêu đề, không phụ thuộc thứ tự cột
        const headerRow = rows[0].map(h => this.normalizeText(h));
        const colIndex = {};
        this.importColumns.forEach(c => {
            const i = headerRow.indexOf(this.normalizeText(c.header));
            if (i !== -1) colIndex[c.key] = i;
        });

        if (colIndex.name === undefined) {
            window.TNTT.toast.warning('File thiếu cột bắt buộc "Họ và Tên".\nHãy bấm nút Xuất để lấy file mẫu đúng định dạng.');
            return;
        }

        // Không cập nhật UI ngay lập tức (optimistic UI) vì Import rất dễ lỗi
        // (ví dụ sai quyền, sai lớp, lỗi giao dịch). Thay vào đó, gom dữ liệu
        // gửi lên máy chủ. Nếu thành công, máy chủ sẽ kích hoạt loadData() để
        // nạp lại danh sách chuẩn nhất.

        this.displayLimit = 20;
        // Bật trạng thái đang đồng bộ để người dùng biết app đang làm việc
        this.syncing = true;
        this.importToServer(rows.slice(1), colIndex, fileName).finally(() => {
            this.syncing = false;
        });
    },

    // ==========================================
    // 4. VIEW MODE (Grid/List Toggle)
    // ==========================================
    setViewMode(mode) {
        this.viewMode = mode;
        localStorage.setItem('studentsViewMode', mode);
    },

    // ==========================================
    // 5. ENHANCED FILTERS
    // ==========================================
    // Age options for dropdown (5-25 tuổi)
    get ageOptions() {
        const options = [];
        for (let i = 5; i <= 25; i++) options.push(i);
        return options;
    },

    // Check if any filter is active (including new filters)
    get hasActiveFilter() {
        return !!(
            this.filterBlock ||
            this.filterClass ||
            this.filterStatus ||
            this.filterGender ||
            this.filterAgeFrom ||
            this.filterAgeTo ||
            this.filterAddress
        );
    },

    // Clear all filters including enhanced ones
    clearFilters() {
        this.filterBlock = '';
        this.filterClass = '';
        this.filterStatus = '';
        this.filterGender = '';
        this.filterAgeFrom = '';
        this.filterAgeTo = '';
        this.filterAddress = '';
    },

    // Calculate age from birthDate
    calculateAge(birthDate) {
        if (!birthDate) return '—';
        const birth = new Date(birthDate);
        const today = new Date();
        let age = today.getFullYear() - birth.getFullYear();
        const monthDiff = today.getMonth() - birth.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
            age--;
        }
        return age;
    },

    // ==========================================
    // 6. QUICK ACTIONS
    // ==========================================
    // Copy phone number to clipboard
    async copyPhone(phone) {
        if (!phone) {
            window.TNTT.toast.warning('Không có số điện thoại để sao chép.');
            return;
        }
        try {
            await navigator.clipboard.writeText(phone);
            window.TNTT.toast.info('Đã sao chép số điện thoại!');
        } catch (err) {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = phone;
            textArea.style.position = 'fixed';
            textArea.style.left = '-9999px';
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                window.TNTT.toast.info('Đã sao chép số điện thoại!');
            } catch (e) {
                window.TNTT.toast.error('Không thể sao chép. Vui lòng sao chép thủ công.');
            }
            document.body.removeChild(textArea);
        }
    },

    // ==========================================
    // 7. PDF EXPORT (Phase 4)
    // ==========================================
    showPdfMenu: false,

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    generatePdfListHtml(students) {
        const today = new Date().toLocaleDateString('vi-VN');
        const year = window.TNTT?.currentYear?.name || 'Niên khoá hiện tại';
        const block = this.filterBlock || 'Tất cả các khối';
        const cls = this.filterClass || 'Tất cả các lớp';

        let rows = students.map((s, i) => `
            <tr>
                <td>${i + 1}</td>
                <td>${this.escapeHtml(s.code)}</td>
                <td>${this.escapeHtml(s.holyName)} ${this.escapeHtml(s.name)}</td>
                <td>${s.gender === 1 ? 'Nam' : 'Nữ'}</td>
                <td>${s.birthDate ? new Date(s.birthDate).toLocaleDateString('vi-VN') : '-'}</td>
                <td>${this.escapeHtml(s.className || '-')}</td>
                <td>${this.escapeHtml(s.status)}</td>
            </tr>
        `).join('');

        return `
            <div class="header">
                <h1>DANH SÁCH THIẾU NHI</h1>
                <p>Khối: ${this.escapeHtml(block)} | Lớp: ${this.escapeHtml(cls)}</p>
                <p>${this.escapeHtml(year)} | Ngày in: ${today} | Tổng: ${students.length} em</p>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Mã</th>
                        <th>Họ tên</th>
                        <th>GT</th>
                        <th>Ngày sinh</th>
                        <th>Lớp</th>
                        <th>Tình trạng</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        `;
    },

    generatePdfCardsHtml(students) {
        const today = new Date().toLocaleDateString('vi-VN');
        const year = window.TNTT?.currentYear?.name || 'Niên khoá hiện tại';

        let cards = students.map(s => `
            <div class="card">
                <div class="card-header">
                    <strong>${this.escapeHtml(s.holyName)} ${this.escapeHtml(s.name)}</strong>
                    <span>${this.escapeHtml(s.code)}</span>
                </div>
                <div class="card-body">
                    <p><strong>Lớp:</strong> ${this.escapeHtml(s.className || '-')}</p>
                    <p><strong>Giới tính:</strong> ${s.gender === 1 ? 'Nam' : 'Nữ'}</p>
                    <p><strong>Ngày sinh:</strong> ${s.birthDate ? new Date(s.birthDate).toLocaleDateString('vi-VN') : '-'}</p>
                    <p><strong>Địa chỉ:</strong> ${this.escapeHtml(s.address || '-')}</p>
                    <p><strong>Cha:</strong> ${this.escapeHtml(s.fatherName || '-')} - ${this.escapeHtml(s.fatherPhone || '-')}</p>
                    <p><strong>Mẹ:</strong> ${this.escapeHtml(s.motherName || '-')} - ${this.escapeHtml(s.motherPhone || '-')}</p>
                </div>
            </div>
        `).join('');

        return `
            <div class="header">
                <h1>THẺ THIẾU NHI</h1>
                <p>${this.escapeHtml(year)} | Ngày in: ${today} | Tổng: ${students.length} em</p>
            </div>
            <div class="cards-grid">${cards}</div>
        `;
    },

    exportPdf(type) {
        this.showPdfMenu = false;
        const students = this.filteredStudents;
        if (students.length === 0) {
            window.TNTT.toast.warning('Không có dữ liệu để xuất PDF.');
            return;
        }

        const content = type === 'cards'
            ? this.generatePdfCardsHtml(students)
            : this.generatePdfListHtml(students);

        const printWindow = window.open('', '_blank');
        if (!printWindow) {
            window.TNTT.toast.error('Trình duyệt chặn popup. Vui lòng cho phép popup.');
            return;
        }

        printWindow.document.write(`
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Danh sách thiếu nhi - ${new Date().toLocaleDateString('vi-VN')}</title>
    <style>
        @page { margin: 15mm; size: A4; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Times New Roman', serif; font-size: 11pt; line-height: 1.4; }
        .header { text-align: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #000; }
        .header h1 { font-size: 16pt; margin-bottom: 5px; }
        .header p { font-size: 10pt; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #000; padding: 6px 8px; text-align: left; }
        th { background: #f0f0f0; font-weight: bold; }
        tr:nth-child(even) { background: #fafafa; }
        .cards-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 20px; }
        .card { border: 1px solid #000; padding: 12px; page-break-inside: avoid; }
        .card-header { display: flex; justify-content: space-between; border-bottom: 1px solid #ccc; padding-bottom: 8px; margin-bottom: 8px; }
        .card-header span { color: #666; font-size: 9pt; }
        .card-body p { margin-bottom: 4px; font-size: 10pt; }
        @media print { body { print-color-adjust: exact; -webkit-print-color-adjust: exact; } }
    </style>
</head>
<body>${content}</body>
</html>`);
        printWindow.document.close();
        setTimeout(() => printWindow.print(), 250);
    },
};
