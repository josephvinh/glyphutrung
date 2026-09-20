/* ==========================================================
   THƯ VIỆN TÀI LIỆU — một mảnh của component tnttApp (app.js gộp lại).

   view = xem + đăng (chờ duyệt); edit = duyệt/gỡ bất kỳ + quản chủ đề.
   Upload dùng FormData riêng (không qua api() vì api() gửi JSON).

   libViewer dùng Alpine store ($store.libViewer) để bottom sheet bên
   ngoài component có thể truy cập trạng thái mà không phụ thuộc scope.

   Ba tab đều PHÂN TRANG + TÌM KIẾM riêng: đoàn đông thì kho tài liệu
   cũng dày lên, không thể đổ hết một lượt ra màn hình điện thoại.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.library = {
    // Số mục đang chờ duyệt — lấy từ data.php lúc mở app để vẽ chấm đỏ
    // trên icon Thư viện ở Trang chủ (xem core.js:moduleBadge).
    libraryPending: 0,

    lib: {
        categories: [],      // chủ đề đang bật (cho bộ lọc + ô chọn khi soạn)
        items: [],           // đã duyệt (tab Tất cả)
        mineItems: [],       // của tôi
        pending: [],         // chờ duyệt (BĐH)
        filter: 0,           // category_id đang lọc (0 = tất cả)
        q: '',               // ô tìm của tab Tất cả
        mineQ: '',           // ô tìm của tab Của tôi
        pendingQ: '',        // ô tìm của tab Chờ duyệt
        loading: false,
        loadingMore: false,
        tab: 'all',          // 'all' | 'mine' | 'pending'
        // Phân trang: mỗi tab nhớ trang đang xem, còn trang sau hay không,
        // và tổng số mục (để hiện "đang xem x/y").
        page:    { all: 1, mine: 1, pending: 1 },
        hasMore: { all: false, mine: false, pending: false },
        total:   { all: 0, mine: 0, pending: 0 },
    },

    // Modal soạn: 2 chế độ — 'file' (đăng tệp) hoặc 'article' (viết bài sổ tay).
    // Có id nghĩa là đang SỬA một mục đã đăng (bài viết hoặc tệp).
    libCompose: { open: false, mode: 'article', id: 0, title: '', categoryId: '', description: '', body: '', fileName: '', hasFile: false, busy: false },
    _libFile: null,          // File thô, KHÔNG để Alpine theo dõi

    // Quản chủ đề (chỉ người có quyền edit)
    libCat: { open: false, items: [], newName: '', busy: false },

    // Từ chối kèm lý do — thay cho prompt() trước đây (prompt bị trình duyệt
    // chặn trong PWA, và không hợp phong cách modal của app).
    libRejectBox: { open: false, item: null, reason: '', busy: false },

    get libCanEdit() { return this.canEditModule('thu_vien'); },
    get libItemIcon() { return (it) => this._libItemIcon(it); },

    // Alpine store cho libViewer (dùng chung bởi bottom sheet bên ngoài component)
    initLibViewer() {
        Alpine.store('libViewer', { open: false, item: null });
    },

    async loadLibrary() {
        this.initLibViewer();
        this.lib.loading = true;
        try {
            await this.libLoadCategories();
            await this._libLoad('all', 1, false);
            if (this.libCanEdit) await this._libLoad('pending', 1, false);
        } finally {
            this.lib.loading = false;
            this.$nextTick(() => window.lucide && lucide.createIcons());
        }
    },

    // Chủ đề: bộ lọc/ô chọn chỉ cần chủ đề ĐANG BẬT; màn quản lý cần cả
    // chủ đề đã ẩn (để bật lại), nên giữ nguyên danh sách đầy đủ ở libCat.
    async libLoadCategories() {
        const cat = await this.api('library', 'categories');
        if (!cat.ok) return;
        this.libCat.items  = cat.categories;
        this.lib.categories = cat.categories.filter(c => Number(c.is_active) === 1);
    },

    /**
     * Nạp một trang cho một tab.
     *
     * @param {string} tab    'all' | 'mine' | 'pending'
     * @param {number} page   1-based
     * @param {boolean} append true = nối vào cuối (bấm "Xem thêm")
     */
    async _libLoad(tab, page, append) {
        const body = { page: page, perPage: 20 };
        if (tab === 'all')     { body.category = this.lib.filter || 0; body.q = this.lib.q; }
        if (tab === 'mine')    { body.q = this.lib.mineQ; }
        if (tab === 'pending') { body.q = this.lib.pendingQ; }

        const action = tab === 'all' ? 'list' : (tab === 'mine' ? 'mine' : 'pending');
        const r = await this.api('library', action, body);
        if (!r.ok) return;

        const key = tab === 'all' ? 'items' : (tab === 'mine' ? 'mineItems' : 'pending');
        this.lib[key]        = append ? this.lib[key].concat(r.items) : r.items;
        this.lib.page[tab]   = page;
        this.lib.hasMore[tab] = !!r.hasMore;
        this.lib.total[tab]   = r.total || 0;
        if (tab === 'pending') this.libraryPending = this.lib.total.pending;
        this.$nextTick(() => window.lucide && lucide.createIcons());
    },

    // Nạp lại từ trang 1 (đổi bộ lọc / gõ tìm / sau khi ghi)
    libRefresh()     { return this._libLoad('all', 1, false); },
    libLoadMine()    { return this._libLoad('mine', 1, false); },
    libLoadPending() { return this._libLoad('pending', 1, false); },

    // "Xem thêm" cho tab đang mở
    async libMore() {
        const t = this.lib.tab;
        if (this.lib.loadingMore || !this.lib.hasMore[t]) return;
        this.lib.loadingMore = true;
        try {
            await this._libLoad(t, this.lib.page[t] + 1, true);
        } finally {
            this.lib.loadingMore = false;
        }
    },

    libSetTab(t) {
        this.lib.tab = t;
        if (t === 'mine' && this.lib.mineItems.length === 0)       this.libLoadMine();
        if (t === 'pending' && this.lib.pending.length === 0)      this.libLoadPending();
        this.$nextTick(() => window.lucide && lucide.createIcons());
    },

    // ---------- Soạn (đăng tệp / viết bài sổ tay) ----------
    openCompose(mode) {
        this.libCompose = {
            open: true, mode: mode || 'article', id: 0, title: '',
            categoryId: (this.lib.categories[0] && this.lib.categories[0].id) || '',
            description: '', body: '', fileName: '', hasFile: false, busy: false,
        };
        this._libFile = null;
        this.$nextTick(() => window.lucide && lucide.createIcons());
    },

    editArticle(item) {
        Alpine.store('libViewer').open = false;
        this.libCompose = {
            open: true, mode: 'article', id: item.id, title: item.title,
            categoryId: item.categoryId || '', description: item.description || '',
            body: item.body || '', fileName: '', hasFile: false, busy: false,
        };
        this.$nextTick(() => window.lucide && lucide.createIcons());
    },

    // Sửa mục TỆP: đổi tiêu đề/mô tả/chủ đề, và thay tệp nếu muốn.
    // Tệp hiện tại chỉ hiện TÊN (không cho tải lại vào form) — muốn đổi thì
    // chọn tệp mới, không chọn thì giữ nguyên tệp cũ.
    editFileItem(item) {
        Alpine.store('libViewer').open = false;
        this.libCompose = {
            open: true, mode: 'file', id: item.id, title: item.title,
            categoryId: item.categoryId || '', description: item.description || '',
            body: '', fileName: '', hasFile: true, busy: false,
        };
        this._libFile = null;
        this.$nextTick(() => window.lucide && lucide.createIcons());
    },

    libPickFile(e) {
        const f = e.target.files && e.target.files[0];
        if (!f) { this._libFile = null; this.libCompose.fileName = ''; return; }
        if (f.size > 15 * 1024 * 1024) {
            window.TNTT.toast.error('File quá lớn (tối đa 15MB).');
            e.target.value = ''; this._libFile = null; this.libCompose.fileName = '';
            return;
        }
        this._libFile = f;
        this.libCompose.fileName = f.name;
    },

    async submitCompose() {
        const c = this.libCompose;
        if (c.busy) return;
        if (!c.title.trim()) { window.TNTT.toast.warning('Vui lòng nhập tiêu đề.'); return; }
        if (c.mode === 'article' && !c.body.trim()) { window.TNTT.toast.warning('Vui lòng nhập nội dung.'); return; }
        // Đăng mới mà chưa chọn tệp thì chặn; sửa mục có sẵn thì không bắt buộc
        // chọn lại tệp (không chọn = giữ tệp cũ).
        if (c.mode === 'file' && !c.id && !this._libFile) { window.TNTT.toast.warning('Vui lòng chọn file.'); return; }
        c.busy = true;
        try {
            if (c.mode === 'article') {
                const r = await this.save('library', 'saveArticle', {
                    id: c.id || 0, title: c.title.trim(), category_id: c.categoryId || '',
                    description: c.description || '', body: c.body,
                });
                if (!r.ok) return;
                window.TNTT.toast.success(r.autoApproved ? 'Đã lưu bài sổ tay.' : 'Đã gửi, chờ Ban Điều Hành duyệt.');
            } else {
                const fd = new FormData();
                fd.append('title', c.title.trim());
                fd.append('category_id', c.categoryId || '');
                fd.append('description', c.description || '');
                if (this._libFile) fd.append('file', this._libFile);
                // Sửa mục có sẵn -> updateItem; đăng mới -> upload.
                const action = c.id ? 'updateItem' : 'upload';
                if (c.id) fd.append('id', c.id);
                // KHÔNG tự đặt Content-Type: để trình duyệt tự sinh boundary multipart.
                const res = await fetch('api/library.php?action=' + action, {
                    method: 'POST',
                    headers: (window.TNTT && window.TNTT.csrfToken) ? { 'X-CSRF-TOKEN': window.TNTT.csrfToken } : {},
                    body: fd,
                });
                const r = await res.json();
                if (!r.ok) { window.TNTT.toast.error(r.error || 'Lưu thất bại, thử lại.'); return; }
                window.TNTT.toast.success(
                    c.id ? 'Đã lưu thay đổi.'
                         : (r.autoApproved ? 'Đã đăng tài liệu.' : 'Đã gửi tài liệu, chờ Ban Điều Hành duyệt.'));
            }
            c.open = false;
            this._libFile = null;
            Alpine.store('libViewer').open = false;
            await this.libRefresh();
            if (this.lib.tab === 'mine') await this.libLoadMine();
            if (this.libCanEdit) await this.libLoadPending();
        } catch (e) {
            window.TNTT.toast.error('Mất kết nối máy chủ. Kiểm tra lại mạng.');
        } finally {
            c.busy = false;
        }
    },

    // ---------- Xem ----------
    openLibItem(item) {
        Alpine.store('libViewer', { open: true, item });
        this.$nextTick(() => window.lucide && lucide.createIcons());
    },

    // ---------- Quản chủ đề (chỉ người duyệt được) ----------
    async openCatManager() {
        await this.libLoadCategories();
        this.libCat.newName = '';
        this.libCat.open = true;
        this.$nextTick(() => window.lucide && lucide.createIcons());
    },

    async libSaveCategory(id, name) {
        if (this.libCat.busy) return;
        name = (name || '').trim();
        if (!name) { window.TNTT.toast.warning('Vui lòng nhập tên chủ đề.'); return; }
        this.libCat.busy = true;
        try {
            const r = await this.save('library', 'saveCategory', { id: id || 0, name: name });
            if (!r.ok) return;
            window.TNTT.toast.success(id ? 'Đã đổi tên chủ đề.' : 'Đã thêm chủ đề.');
            this.libCat.newName = '';
            await this.libLoadCategories();
            await this.libRefresh();
        } finally {
            this.libCat.busy = false;
        }
    },

    // Ẩn/hiện chủ đề: ẩn thì tài liệu cũ vẫn còn, chỉ không hiện trong bộ lọc.
    async libToggleCategory(c) {
        if (this.libCat.busy) return;
        this.libCat.busy = true;
        try {
            const r = await this.save('library', 'toggleCategory', { id: c.id });
            if (!r.ok) return;
            await this.libLoadCategories();
            // Đang lọc đúng chủ đề vừa ẩn -> bỏ lọc để không ra màn trống.
            if (Number(c.is_active) === 1 && this.lib.filter === c.id) {
                this.lib.filter = 0;
            }
            await this.libRefresh();
        } finally {
            this.libCat.busy = false;
        }
    },

    // ---------- Duyệt / gỡ ----------
    async libApprove(item) {
        const r = await this.save('library', 'approve', { id: item.id });
        if (r.ok) {
            window.TNTT.toast.success('Đã duyệt.');
            Alpine.store('libViewer').open = false;
            await this.libLoadPending();
            await this.libRefresh();
        }
    },

    // Mở hộp thoại nhập lý do (thay cho prompt() — PWA chặn prompt)
    libReject(item) {
        this.libRejectBox = { open: true, item: item, reason: '', busy: false };
        this.$nextTick(() => window.lucide && window.lucide.createIcons());
    },

    async libConfirmReject() {
        const b = this.libRejectBox;
        if (b.busy || !b.item) return;
        b.busy = true;
        try {
            const r = await this.save('library', 'reject', { id: b.item.id, reason: b.reason });
            if (!r.ok) return;
            window.TNTT.toast.info('Đã từ chối.');
            b.open = false;
            Alpine.store('libViewer').open = false;
            await this.libLoadPending();
        } finally {
            b.busy = false;
        }
    },

    async libDelete(item) {
        if (!confirm('Gỡ "' + item.title + '"? Thao tác này không hoàn tác được.')) return;
        const r = await this.save('library', 'delete', { id: item.id });
        if (r.ok) {
            window.TNTT.toast.success('Đã gỡ tài liệu.');
            Alpine.store('libViewer').open = false;
            await this.libRefresh();
            if (this.lib.tab === 'mine') await this.libLoadMine();
            if (this.libCanEdit) await this.libLoadPending();
        }
    },

    // ---------- Tiện ích hiển thị ----------
    // Chỉ dùng icon CÓ trong bản lucide rút gọn của app (93 icon).
    libItemIcon(item) {
        return item.type === 'article' ? 'scroll-text' : this.libIcon(item.ext);
    },
    libIcon(ext) {
        if (['pdf', 'doc', 'docx'].includes(ext)) return 'file-text';
        if (['ppt', 'pptx', 'jpg', 'jpeg', 'png', 'webp'].includes(ext)) return 'file-up';
        return 'file-text';
    },
    libSizeLabel(kb) {
        return kb >= 1024 ? (kb / 1024).toFixed(1) + ' MB' : kb + ' KB';
    },
    libStatusLabel(s) {
        return s === 'da_duyet' ? 'Đã duyệt' : (s === 'cho_duyet' ? 'Chờ duyệt' : 'Bị từ chối');
    },
};
