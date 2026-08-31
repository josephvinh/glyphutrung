/* ==========================================================
   ANALYTICS — Attendance Analytics Dashboard
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.analytics = {
    // ==========================================
    // DATA: ANALYTICS
    //
    // Computed properties for attendance analytics
    // using existing attendance and leave data
    // ==========================================

    openAnalytics() {
        this.changeModule('analytics');
    },

    // Get total sessions (programs with attendance records)
    get totalSessions() {
        const dates = new Set();
        this.attendances.forEach(a => dates.add(a.date));
        return dates.size || 0;
    },

    // Overall attendance statistics
    get attendanceStats() {
        const students = this.accessibleStudents.filter(s => s.status === 'đang sinh hoạt');
        const totalPossible = students.length * this.totalSessions;
        let present = 0, excused = 0, unexcused = 0, late = 0;

        // Count from attendance records
        this.attendances.forEach(a => {
            if (a.status === 'present' || a.status === 'có mặt') present++;
            else if (a.status === 'late' || a.status === 'đi trễ') late++;
        });

        // Count from approved leave requests
        this.leaveRequests.filter(r => r.status === 'đã duyệt').forEach(r => {
            excused++;
        });

        // Calculate unexcused (missing records that aren't excused)
        const expectedRecords = students.length * this.totalSessions;
        const recordedRecords = this.attendances.length + this.leaveRequests.filter(r => r.status === 'đã duyệt').length;
        unexcused = Math.max(0, expectedRecords - recordedRecords);

        return {
            total: totalPossible,
            present: present + late,
            excused: excused,
            unexcused: unexcused,
            rate: totalPossible > 0 ? Math.round(((present + late) / totalPossible) * 100) : 0,
            excusedRate: totalPossible > 0 ? Math.round((excused / totalPossible) * 100) : 0,
            unexcusedRate: totalPossible > 0 ? Math.round((unexcused / totalPossible) * 100) : 0
        };
    },

    get totalSessionsDisplay() {
        return this.totalSessions;
    },

    get attendanceRate() {
        return this.attendanceStats.rate;
    },

    get excusedRate() {
        return this.attendanceStats.excusedRate;
    },

    get unexcusedRate() {
        return this.attendanceStats.unexcusedRate;
    },

    // Weekly attendance data for chart
    get weeklyData() {
        const weeks = [];
        const now = new Date();
        const startOfWeek = new Date(now);
        startOfWeek.setDate(now.getDate() - now.getDay() - 28); // Start 4 weeks ago

        for (let i = 0; i < 4; i++) {
            const weekStart = new Date(startOfWeek);
            weekStart.setDate(startOfWeek.getDate() + (i * 7));
            const weekEnd = new Date(weekStart);
            weekEnd.setDate(weekStart.getDate() + 6);

            const ws = this.toDateInput(weekStart);
            const we = this.toDateInput(weekEnd);

            let present = 0, excused = 0, unexcused = 0, total = 0;

            // Count attendance for this week
            this.attendances.forEach(a => {
                if (a.date >= ws && a.date <= we) {
                    total++;
                    if (a.status === 'present' || a.status === 'có mặt') present++;
                    else if (a.status === 'late' || a.status === 'đi trễ') present++;
                }
            });

            // Count excused absences
            this.leaveRequests.filter(r => r.status === 'đã duyệt').forEach(r => {
                if (r.date >= ws && r.date <= we) excused++;
            });

            weeks.push({
                week: i + 1,
                label: 'Tuần ' + (i + 1),
                present: present,
                excused: excused,
                unexcused: unexcused,
                total: total,
                rate: total > 0 ? Math.round((present / total) * 100) : 0
            });
        }

        return weeks;
    },

    // Class attendance breakdown
    get classData() {
        const classMap = {};
        const students = this.accessibleStudents.filter(s => s.status === 'đang sinh hoạt');

        // Group students by class
        students.forEach(s => {
            if (!classMap[s.className]) {
                classMap[s.className] = {
                    id: s.classId || s.className,
                    name: s.className || 'Chưa xếp lớp',
                    total: 0,
                    present: 0,
                    excused: 0,
                    unexcused: 0
                };
            }
        });

        // Count attendance by class
        this.attendances.forEach(a => {
            const student = students.find(s => s.id === a.studentId);
            if (student && classMap[student.className]) {
                classMap[student.className].total++;
                if (a.status === 'present' || a.status === 'có mặt' || a.status === 'late' || a.status === 'đi trễ') {
                    classMap[student.className].present++;
                }
            }
        });

        // Calculate rates
        return Object.values(classMap)
            .map(c => ({
                id: c.id,
                name: c.name,
                rate: c.total > 0 ? Math.round((c.present / c.total) * 100) : 0,
                present: c.present,
                total: c.total
            }))
            .sort((a, b) => b.rate - a.rate);
    },

    // Students with low attendance (<70%)
    get lowAttendance() {
        const students = this.accessibleStudents.filter(s => s.status === 'đang sinh hoạt');
        const results = [];

        students.forEach(s => {
            const totalSessions = this.totalSessions;
            const studentAttendance = this.attendances.filter(a => a.studentId === s.id);
            const present = studentAttendance.filter(a =>
                a.status === 'present' || a.status === 'có mặt' ||
                a.status === 'late' || a.status === 'đi trễ'
            ).length;
            const excused = this.leaveRequests.filter(r =>
                r.studentId === s.id && r.status === 'đã duyệt'
            ).length;

            const total = present + excused + (totalSessions - studentAttendance.length - excused);
            const rate = total > 0 ? Math.round((present / total) * 100) : 0;

            if (rate < 70) {
                results.push({
                    id: s.id,
                    name: s.name,
                    holyName: s.holyName || '',
                    class: s.className || 'Chưa xếp lớp',
                    rate: rate
                });
            }
        });

        return results.sort((a, b) => a.rate - b.rate);
    },

    // Helper: get bar height for weekly chart
    getBarHeight(present, total) {
        if (total === 0) return 20; // Minimum height
        const percent = (present / total) * 100;
        return Math.max(20, Math.min(percent, 100)); // Scale between 20-100px
    },

    // Helper: get progress bar class based on rate
    progressClass(rate) {
        if (rate >= 80) return 'analytics-progress-bar-success';
        if (rate >= 60) return 'analytics-progress-bar-warning';
        return 'analytics-progress-bar-danger';
    },

    // Helper: get avatar color class
    avatarClass(index) {
        const colors = ['bg-blue-100 text-blue-600', 'bg-emerald-100 text-emerald-600', 'bg-amber-100 text-amber-600', 'bg-rose-100 text-rose-600'];
        return colors[index % colors.length];
    }
};
