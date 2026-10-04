/* ==========================================================
   BIBLE — Thống kê Lời Chúa Mỗi Ngày
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.bible = {
    // ==========================================
    // DATA: LỜI CHÚA MỖI NGÀY
    // ==========================================
    bibleStats: {
        total_requests: 0,
        unique_ips: 0,
        today_unique_ips: 0,
        last_request: null
    },
    bibleList: [],
    bibleLoading: false,

    // ==========================================
    // INIT: Tải stats khi khởi động (chỉ admin)
    // ==========================================
    initBible() {
        // Dùng this.isAdmin từ tnttApp (được gán từ core module)
        // isAdmin là getter trên user object, an toàn khi user chưa load
        const isAdmin = this.user?.role === 'admin';
        if (isAdmin || this.user?.role === 'bdh') {
            this.refreshBibleStats();
        }
    },

    // ==========================================
    // METHODS
    // ==========================================

    /**
     * Làm mới dữ liệu thống kê Lời Chúa Mỗi Ngày
     * Gọi cả stats và list APIs
     */
    async refreshBibleStats() {
        this.bibleLoading = true;
        try {
            const [statsRes, listRes] = await Promise.all([
                fetch('api/bible.php?action=stats'),
                fetch('api/bible.php?action=list')
            ]);

            const statsData = await statsRes.json();
            const listData = await listRes.json();

            if (statsData.success) {
                this.bibleStats = statsData.stats;
            }

            if (listData.success) {
                this.bibleList = listData.rows || [];
            }
        } catch (err) {
            console.error('[Bible] Lỗi khi tải thống kê:', err);
        } finally {
            this.bibleLoading = false;
        }
    },

    /**
     * Format thời gian tương đối (VD: "5 phút trước")
     */
    timeAgo(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        const now = new Date();
        const diffMs = now - date;
        const diffSec = Math.floor(diffMs / 1000);
        const diffMin = Math.floor(diffSec / 60);
        const diffHour = Math.floor(diffMin / 60);
        const diffDay = Math.floor(diffHour / 24);

        if (diffSec < 60) return 'vừa xong';
        if (diffMin < 60) return diffMin + ' phút trước';
        if (diffHour < 24) return diffHour + ' giờ trước';
        if (diffDay < 7) return diffDay + ' ngày trước';

        // Format ngày/tháng
        const d = date.getDate();
        const m = date.getMonth() + 1;
        return d + '/' + m;
    },
};
