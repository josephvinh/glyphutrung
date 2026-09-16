/* ==========================================================
   THƯ VIỆN TÀI LIỆU — một mảnh của component tnttApp (app.js gộp lại).

   view = xem + đăng (chờ duyệt); edit = duyệt/gỡ bất kỳ (BĐH/Admin).
   Upload dùng FormData riêng (không qua api() vì api() gửi JSON).
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.library = {
    lib: {
        categories: [],
        items: [],          // đã duyệt (tab Tất cả)
        mineItems: [],       // của tôi
        pending: [],         // chờ duyệt (BĐH)
        filter: 0,           // category_id đang lọc (0 = tất cả)
        q: '',
        loading: false,
        tab: 'all',          // 'all' | 'mine' | 'pending'
    },
    // Modal soạn: 2 chế độ — 'file' (đăng tệp) hoặc 'article' (viết bài sổ tay).
    libCompose: { open: false, mode: 'article', id: 0, title: '', categoryId: '', description: '', body: '', fileName: '', busy: false },
    libViewer: { open: false, item: null },
    _libFile: null,          // File thô, KHÔNG để Alpine theo dõi

    get libCanEdit() { return this.canEditModule('thu_vien'); },

    async loadLibrary() {
        this.lib.loading = true;
        try {
            const cat = await this.api('library', 'categories');
            if (cat.ok) this.lib.categories = cat.categories.filter(c => Number(c.is_active) === 1);
            await this.libRefresh();
            if (this.libCanEdit) await this.libLoadPending();
        } finally {
            this.lib.loading = false;
            this.$nextTick(() => window.lucide && lucide.createIcons());
        }
    },

    async libRefresh() {
        const r = await this.api('library', 'list', { category: this.lib.filter || 0, q: this.lib.q });
        if (r.ok) this.lib.items = r.items;
        this.$nextTick(() => window.lucide && lucide.createIcons());
    },

    async libLoadMine() {
        const r = await this.api('library', 'mine');
        if (r.ok) this.lib.mineItems = r.items;
        this.$nextTick(() => window.lucide && lucide.createIcons());
    },

    async libLoadPending() {
        const r = await this.api('library', 'pending');
        if (r.ok) this.lib.pending = r.items;
    },

    libSetTab(t) {
        this.lib.tab = t;
        if (t === 'mine') this.libLoadMine();
        if (t === 'pending') this.libLoadPending();
        this.$nextTick(() => window.lucide && lucide.createIcons());
    },

    // ---------- Soạn (đăng tệp / viết bài sổ tay) ----------
    openCompose(mode) {
        this.libCompose = {
            open: true, mode: mode || 'article', id: 0, title: '',
            categoryId: (this.lib.categories[0] && this.lib.categories[0].id) || '',
            description: '', body: '', fileName: '', busy: false,
        };
        this._libFile = null;
        this.$nextTick(() => window.lucide && lucide.createIcons());
    },

    editArticle(item) {
        this.libViewer.open = false;
        this.libCompose = {
            open: true, mode: 'article', id: item.id, title: item.title,
            categoryId: item.categoryId || '', description: item.description || '',
            body: item.body || '', fileName: '', busy: false,
        };
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
        if (c.mode === 'file' && !this._libFile && !c.id) { window.TNTT.toast.warning('Vui lòng chọn file.'); return; }
        c.busy = true;
        try {
            if (c.mode === 'article') {
                const r = await this.save('library', 'saveArticle', {
                    id: c.id || 0, title: c.title.trim(), category_id: c.categoryId || '',
                    description: c.description || '', body: c.body,
                });
                if (!r.ok) return;
                window.TNTT.toast.success(r.autoApproved ? 'Đã đăng bài sổ tay.' : 'Đã gửi, chờ Ban Điều Hành duyệt.');
            } else {
                const fd = new FormData();
                fd.append('title', c.title.trim());
                fd.append('category_id', c.categoryId || '');
                fd.append('description', c.description || '');
                fd.append('file', this._libFile);
                // KHÔNG tự đặt Content-Type: để trình duyệt tự sinh boundary multipart.
                const res = await fetch('api/library.php?action=upload', {
                    method: 'POST',
                    headers: (window.TNTT && window.TNTT.csrfToken) ? { 'X-CSRF-TOKEN': window.TNTT.csrfToken } : {},
                    body: fd,
                });
                const r = await res.json();
                if (!r.ok) { window.TNTT.toast.error(r.error || 'Đăng thất bại, thử lại.'); return; }
                window.TNTT.toast.success('Đã gửi tài liệu, chờ Ban Điều Hành duyệt.');
            }
            c.open = false;
            this._libFile = null;
            await this.libRefresh();
            if (this.lib.tab === 'mine') this.libLoadMine();
            if (this.libCanEdit) this.libLoadPending();
        } catch (e) {
            window.TNTT.toast.error('Mất kết nối máy chủ. Kiểm tra lại mạng.');
        } finally {
            c.busy = false;
        }
    },

    // ---------- Xem ----------
    openLibItem(item) {
        this.libViewer = { open: true, item };
        this.$nextTick(() => window.lucide && lucide.createIcons());
    },

    // ---------- Duyệt / gỡ ----------
    async libApprove(item) {
        const r = await this.save('library', 'approve', { id: item.id });
        if (r.ok) { window.TNTT.toast.success('Đã duyệt.'); await this.libLoadPending(); await this.libRefresh(); }
    },
    async libReject(item) {
        const reason = prompt('Lý do từ chối (không bắt buộc):', '');
        if (reason === null) return;
        const r = await this.save('library', 'reject', { id: item.id, reason });
        if (r.ok) { window.TNTT.toast.info('Đã từ chối.'); await this.libLoadPending(); }
    },
    async libDelete(item) {
        if (!confirm('Gỡ tài liệu "' + item.title + '"? Thao tác này không hoàn tác được.')) return;
        const r = await this.save('library', 'delete', { id: item.id });
        if (r.ok) {
            window.TNTT.toast.success('Đã gỡ tài liệu.');
            this.libViewer.open = false;
            await this.libRefresh();
            if (this.lib.tab === 'mine') this.libLoadMine();
        }
    },

    // ---------- Tiện ích hiển thị ----------
    libItemIcon(item) {
        return item.type === 'article' ? 'book-open' : this.libIcon(item.ext);
    },
    libIcon(ext) {
        if (ext === 'pdf') return 'file-text';
        if (['jpg', 'jpeg', 'png', 'webp'].includes(ext)) return 'image';
        if (['doc', 'docx'].includes(ext)) return 'file-type';
        if (['ppt', 'pptx'].includes(ext)) return 'monitor-play';
        return 'file';
    },
    libSizeLabel(kb) {
        return kb >= 1024 ? (kb / 1024).toFixed(1) + ' MB' : kb + ' KB';
    },
    libStatusLabel(s) {
        return s === 'da_duyet' ? 'Đã duyệt' : (s === 'cho_duyet' ? 'Chờ duyệt' : 'Bị từ chối');
    },
};
