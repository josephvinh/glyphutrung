/* ==========================================================
   NOTIFICATION CENTER
   Quản lý lịch sử thông báo, badge, và nhận push từ SW.
   Mảnh này được gộp PHẲNG vào component tnttApp (xem app.js) nên mọi
   thuộc tính/hàm đều mang tiền tố notif* để không đè mảnh khác.
   ========================================================== */

window.TNTT = window.TNTT || {};
window.TNTT.notifications = {
    // State
    showNotificationCenter: false,
    notifItems: [],
    notifUnread: 0,
    notifLoading: false,
    notifFilter: 'all',
    notifPage: 1,
    notifHasMore: false,
    _notifSeq: 0,

    /** Khởi tạo: đọc unread count từ bootstrap + đăng ký SW message listener */
    async notifInit() {
        // Đọc unread count từ bootstrap để tránh API call thừa
        const boot = window.TNTT_BOOT;
        if (boot && typeof boot.unreadNotifications === 'number') {
            this.notifUnread = boot.unreadNotifications;
            this._updateBadge();
        } else {
            // Fallback: gọi API nếu bootstrap không có (legacy)
            await this.notifLoadUnread();
        }

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', (e) => {
                if (e.data?.action === 'PUSH_RECEIVED') {
                    this.notifHandlePush(e.data.notification);
                }
            });
        }
    },

    /** SW báo có push mới khi app đang mở: toast + optimistic update
     *  Dùng optimistic update thay vì gọi API (tránh HTTP request thừa). */
    notifHandlePush(n) {
        window.TNTT.toast.show(`${n.title}\n${n.body}`, 'info', 5000);
        // Optimistic update: tăng badge count ngay
        this.notifUnread++;
        this._updateBadge();
        if (this.showNotificationCenter) {
            this.notifPage = 1;
            this.notifLoadList();
        }
    },

    async notifLoadUnread() {
        try {
            const r = await window.TNTT.core.api('notifications', 'unread_count');
            if (r.ok) this.notifUnread = r.count;
        } catch (e) {}
    },

    async notifOpen() {
        this.notifPage = 1;
        this.notifItems = [];
        this.notifFilter = 'all';
        this.showNotificationCenter = true;
        await this.notifLoadList();
    },

    notifClose() {
        this.showNotificationCenter = false;
    },

    async notifLoadList() {
        const seq = ++this._notifSeq;
        this.notifLoading = true;
        try {
            const page = this.notifPage;
            const r = await window.TNTT.core.api('notifications', 'list', {
                read: this.notifFilter === 'unread' ? '0' : 'all',
                page,
                limit: 20,
            });
            if (seq !== this._notifSeq) return; // đã có yêu cầu mới hơn
            if (r.ok) {
                this.notifItems = page === 1 ? r.items : this.notifItems.concat(r.items);
                this.notifHasMore = r.items.length === r.limit;
            } else if (page > 1) {
                this.notifPage = page - 1;
            }
        } catch (e) {
            if (seq === this._notifSeq && this.notifPage > 1) this.notifPage--;
        } finally {
            if (seq === this._notifSeq) this.notifLoading = false;
        }
    },

    async notifLoadMore() {
        if (!this.notifHasMore || this.notifLoading) return;
        this.notifPage++;
        await this.notifLoadList();
    },

    async notifMarkRead(id) {
        try {
            const r = await window.TNTT.core.api('notifications', 'mark_read', { ids: [id] });
            if (r.ok) {
                const item = this.notifItems.find(x => x.id == id);
                if (item && !item.is_read) {
                    item.is_read = true;
                    this.notifUnread = Math.max(0, this.notifUnread - r.count);
                }
            }
        } catch (e) {}
    },

    async notifMarkAllRead() {
        try {
            const r = await window.TNTT.core.api('notifications', 'mark_read', { all: true });
            if (r.ok) {
                this.notifItems.forEach(x => x.is_read = true);
                this.notifUnread = 0;
            }
        } catch (e) {}
    },

    async notifSetFilter(f) {
        this.notifFilter = f;
        this.notifPage = 1;
        this.notifItems = [];
        await this.notifLoadList();
    },

    async notifOpenItem(item) {
        await this.notifMarkRead(item.id);
        if (item.url) {
            window.TNTT.router.navigate(item.url);
        }
        this.notifClose();
    },

    notifIconForTag(tag) {
        const icons = {
            announcement: 'megaphone',
            leave: 'file-text',
            attendance: 'calendar-check',
            score: 'award',
            gift: 'gift',
            system: 'settings',
            'tntt-chung': 'bell',
            'tntt-thu': 'bell-ring',
        };
        return icons[tag] || 'bell';
    },

    notifColorForTag(tag) {
        const colors = {
            announcement: 'bg-blue-50 text-blue-600 border-blue-100',
            leave: 'bg-amber-50 text-amber-600 border-amber-100',
            attendance: 'bg-emerald-50 text-emerald-600 border-emerald-100',
            score: 'bg-purple-50 text-purple-600 border-purple-100',
            gift: 'bg-rose-50 text-rose-600 border-rose-100',
            system: 'bg-slate-50 text-slate-600 border-slate-100',
        };
        return colors[tag] || 'bg-slate-50 text-slate-600 border-slate-100';
    },
};
