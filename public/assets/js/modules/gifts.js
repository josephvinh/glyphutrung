/* ==========================================================
   GIFTS — Danh mục quà đổi Mộc (Quản trị / BĐH / Thủ Từ)
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.gifts = {
    gifts: [],          // máy chủ nạp qua loadGifts() (module nạp theo yêu cầu, không nằm trong data.php)
    giftsLoading: false,

    showGiftModal: false,
    isEditingGift: false,
    giftForm: { id: null, name: '', stampCost: 10, stock: 0, imageUrl: '', status: 'còn bán', sortOrder: 1 },

    get canEditGifts() { return this.canEditModule('gifts'); },

    async loadGifts() {
        this.giftsLoading = true;
        try {
            const r = await this.api('gifts', 'list');
            if (!r || !r.ok) { window.TNTT.toast.error(r && r.error || 'Không nạp được danh mục quà.'); return; }
            this.gifts = r.gifts || [];
        } finally {
            this.giftsLoading = false;
            this.$nextTick(() => window.lucide && lucide.createIcons());
        }
    },

    openCreateGift() {
        const nextOrder = this.gifts.length
            ? Math.max(...this.gifts.map(g => Number(g.sortOrder) || 1)) + 1 : 1;
        this.giftForm = { id: null, name: '', stampCost: 10, stock: 0, imageUrl: '', status: 'còn bán', sortOrder: nextOrder };
        this.isEditingGift = false;
        this.showGiftModal = true;
    },

    openEditGift(g) {
        this.giftForm = Object.assign({}, g);
        this.isEditingGift = true;
        this.showGiftModal = true;
    },

    // Gói payload đầy đủ để lưu (dùng chung cho lưu form và bật/tắt nhanh)
    giftPayload(f) {
        return {
            id: f.id, name: (f.name || '').trim(),
            stampCost: Number(f.stampCost) || 0,
            stock: Number(f.stock) || 0,
            imageUrl: (f.imageUrl || '').trim(),
            status: f.status === 'ẩn' ? 'ẩn' : 'còn bán',
            sortOrder: Number(f.sortOrder) || 1,
        };
    },

    async saveGift() {
        const f = this.giftForm;
        f.name = (f.name || '').trim();
        if (!f.name) { window.TNTT.toast.warning('Vui lòng nhập tên quà!'); return; }
        if (!f.stampCost || Number(f.stampCost) <= 0) { window.TNTT.toast.warning('Số Mộc đổi phải lớn hơn 0!'); return; }
        if (Number(f.stock) < 0) { window.TNTT.toast.warning('Tồn kho không được âm!'); return; }

        const payload = this.giftPayload(f);
        const r = await this.api('gifts', 'save', payload);
        if (!r || !r.ok) { window.TNTT.toast.error(r && r.error || 'Không lưu được quà.'); return; }
        payload.id = r.id;   // id thật từ máy chủ (mới hoặc giữ nguyên)

        const idx = this.gifts.findIndex(g => g.id === payload.id);
        if (idx !== -1) this.gifts[idx] = Object.assign({}, payload);
        else this.gifts.push(Object.assign({}, payload));

        this.logAction(this.isEditingGift ? 'sua' : 'tao', 'gifts',
                       (this.isEditingGift ? 'Sửa' : 'Tạo') + ' quà ' + payload.name,
                       payload.stampCost + ' mộc · tồn ' + payload.stock);
        this.showGiftModal = false;
    },

    async deleteGift(id) {
        const g = this.gifts.find(x => x.id === id);
        if (!g) return;
        if (!await window.TNTT.toast.confirm('Xóa quà "' + g.name + '"?', { danger: true, confirmText: 'Xoá' })) return;

        const r = await this.api('gifts', 'delete', { id: id });
        if (!r || !r.ok) { window.TNTT.toast.error(r && r.error || 'Không xoá được quà.'); return; }

        if (r.hidden) {
            // Đã có đơn đổi gắn với quà -> máy chủ chỉ ẨN thay vì xoá.
            g.status = 'ẩn';
            window.TNTT.toast.warning('Quà đã có đơn đổi — đã chuyển sang ẨN thay vì xoá.');
        } else {
            this.gifts = this.gifts.filter(x => x.id !== id);
        }
        this.logAction('xoa', 'gifts', 'Xóa quà ' + g.name, '');
    },
};
