/* ==========================================================
   NOTIFICATION CENTER
   Quản lý notification history, badge, và nhận push từ SW
   ========================================================== */

window.TNTT = window.TNTT || {};
window.TNTT.notifications = {
    // State
    items: [],
    unreadCount: 0,
    loading: false,
    filter: 'all',
    page: 1,
    hasMore: true,

    /** Khởi tạo: nạp số chưa đọc + đăng ký SW message listener */
    async init() {
        await this.loadUnreadCount();

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', (e) => {
                if (e.data?.action === 'PUSH_RECEIVED') {
                    this.handlePushReceived(e.data.notification);
                }
            });
        }
    },

    /** Gọi khi SW báo có push notification mới */
    handlePushReceived(notification) {
        window.TNTT.toast.show(
            `${notification.title}\n${notification.body}`,
            'info',
            5000
        );

        this.items.unshift({
            id: 'temp-' + Date.now(),
            tag: notification.tag,
            title: notification.title,
            body: notification.body,
            url: notification.url,
            is_read: false,
            created_at: new Date().toISOString(),
        });

        this.unreadCount++;
        this.updateBadge();
    },

    async loadUnreadCount() {
        try {
            const r = await window.TNTT.core.api('notifications', 'unread_count');
            if (r.ok) {
                this.unreadCount = r.count;
                this.updateBadge();
            }
        } catch (e) {}
    },

    updateBadge() {
        const badge = document.getElementById('notif-badge');
        if (badge) {
            badge.textContent = this.unreadCount > 0 ?
                (this.unreadCount > 99 ? '99+' : this.unreadCount) : '';
            badge.style.display = this.unreadCount > 0 ? 'flex' : 'none';
        }
    },

    async open() {
        this.page = 1;
        this.items = [];
        this.filter = 'all';
        await this.loadList();
        window.TNTT.shell.showNotificationCenter = true;
    },

    close() {
        window.TNTT.shell.showNotificationCenter = false;
    },

    async loadList() {
        if (this.loading) return;
        this.loading = true;
        try {
            const r = await window.TNTT.core.api('notifications', 'list', {
                read: this.filter === 'unread' ? '0' : 'all',
                page: this.page,
                limit: 20,
            });
            if (r.ok) {
                if (this.page === 1) {
                    this.items = r.items;
                } else {
                    this.items.push(...r.items);
                }
                this.hasMore = r.items.length === r.limit;
            }
        } finally {
            this.loading = false;
        }
    },

    async loadMore() {
        if (!this.hasMore || this.loading) return;
        this.page++;
        await this.loadList();
    },

    async markRead(id) {
        try {
            const r = await window.TNTT.core.api('notifications', 'mark_read', { ids: [id] });
            if (r.ok) {
                const item = this.items.find(x => x.id == id);
                if (item && !item.is_read) {
                    item.is_read = true;
                    this.unreadCount = Math.max(0, this.unreadCount - r.count);
                    this.updateBadge();
                }
            }
        } catch (e) {}
    },

    async markAllRead() {
        try {
            const r = await window.TNTT.core.api('notifications', 'mark_read', { all: true });
            if (r.ok) {
                this.items.forEach(x => x.is_read = true);
                this.unreadCount = 0;
                this.updateBadge();
            }
        } catch (e) {}
    },

    async setFilter(f) {
        this.filter = f;
        this.page = 1;
        this.items = [];
        await this.loadList();
    },

    async openNotification(item) {
        await this.markRead(item.id);
        if (item.url) {
            window.TNTT.router.navigate(item.url);
        }
        this.close();
    },

    iconForTag(tag) {
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

    colorForTag(tag) {
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
