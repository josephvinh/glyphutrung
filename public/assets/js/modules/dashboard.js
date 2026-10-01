/* ==========================================================
   DASHBOARD — Quick stats for Ban Điều Hành
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.dashboard = {
    // ==========================================
    // QUICK STATS DATA
    // ==========================================

    // Today's attendance stats (computed from existing data)
    get todayAttendance() {
        const today = this.toDateInput(new Date());
        const activeStudents = this.accessibleStudents.filter(s => s.status === 'đang sinh hoạt');
        const programsToday = this.programsOn(today);

        if (programsToday.length === 0) {
            return { present: 0, total: activeStudents.length };
        }

        // Sum up attendance across all programs today
        let totalPresent = 0;
        const countedStudents = new Set();

        programsToday.forEach(p => {
            activeStudents.forEach(s => {
                if (countedStudents.has(s.id)) return;
                const record = this.attendanceRecord(s.id, { programId: p.id, date: today });
                if (record && (record.status === 'có mặt' || record.status === 'đi trễ')) {
                    totalPresent++;
                    countedStudents.add(s.id);
                }
            });
        });

        return {
            present: totalPresent,
            total: activeStudents.length
        };
    },

    // Pending leave requests count (for admin approval)
    get pendingLeaves() {
        return this.scopedLeaveRequests.filter(r => r.status === 'chờ duyệt').length;
    },

    // Unread announcements count
    get unreadAnnouncements() {
        return this.visibleAnnouncements.filter(a => !this.readAnnouncements.includes(a.id)).length;
    },

    // NOTE: attendanceRate was removed - it conflicted with analytics.attendanceRate
    // Dashboard doesn't use attendance rate in its UI

    // Recent logs for admin dashboard
    get recentLogs() {
        return this.logs.slice(0, 5);
    },

    // Active programs today
    get todayPrograms() {
        const today = this.toDateInput(new Date());
        return this.programsOn(today);
    }
};
