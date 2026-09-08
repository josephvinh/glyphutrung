/* ==========================================================
   STATS — Thống kê
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.stats = {
    // ==========================================
    // 8. DATA: THỐNG KÊ
    //
    // Không lưu số liệu nào. Tất cả tính lại từ attendances +
    // leaveRequests theo đúng phạm vi quyền của người đăng nhập,
    // nên không bao giờ có chuyện số thống kê lệch với dữ liệu gốc.
    // ==========================================
    statMonth: '',

    openStats(khongDoiMan = false) {
        if (!this.statMonth) this.statMonth = this.toDateInput(new Date()).slice(0, 7);
        if (!khongDoiMan) this.changeModule('stats');
    },

    shiftStatMonth(delta) {
        const parts = this.statMonth.split('-');
        const d = new Date(Number(parts[0]), Number(parts[1]) - 1 + delta, 1);
        this.statMonth = this.toDateInput(d).slice(0, 7);
    },

    get statMonthLabel() {
        if (!this.statMonth) return '';
        const parts = this.statMonth.split('-');
        return 'Tháng ' + Number(parts[1]) + '/' + parts[0];
    },

    // Các buổi trong tháng ĐÃ QUA GIỜ CHỐT.
    // Buổi chưa tới thì không tính, nếu không nửa tháng còn lại sẽ bị
    // đếm thành vắng và tỷ lệ chuyên cần tụt oan.
    // Các buổi ĐÃ QUA GIỜ CHỐT trong một khoảng ngày bất kỳ.
    // Dùng chung cho Thống kê (theo tháng) và Sổ liên lạc (theo học kỳ).
    sessionsBetween(fromDate, toDate) {
        if (!fromDate || !toDate) return [];
        const out = [];
        const cursor = new Date(fromDate + 'T00:00:00');
        const end = new Date(toDate + 'T00:00:00');

        while (cursor <= end) {
            const ds = this.toDateInput(cursor);
            this.programsOn(ds).forEach(prog => {
                const session = { programId: prog.id, date: ds };
                if (this.isPastCutoffFor(session)) {
                    out.push({ programId: prog.id, date: ds, name: prog.name, countForAttendance: prog.countForAttendance });
                }
            });
            cursor.setDate(cursor.getDate() + 1);
        }
        return out;
    },

    get statSessions() {
        if (!this.statMonth) return [];
        const parts = this.statMonth.split('-');
        const y = Number(parts[0]), m = Number(parts[1]);
        const p = n => String(n).padStart(2, '0');
        const last = new Date(y, m, 0).getDate();
        return this.sessionsBetween(y + '-' + p(m) + '-01', y + '-' + p(m) + '-' + p(last));
    },

    // Bảng tra cứu nhanh cho việc tính chuyên cần: tránh find() lồng nhau.
    // TÁI DÙNG chỉ số attIndex đã dựng sẵn ở loadData (rebuildAttendanceIndex)
    // thay vì quét lại vài chục nghìn dòng mỗi lần gọi — vốn làm render treo
    // vài giây với đoàn lớn. Chạm .length để vẫn nhận thay đổi khi thêm/bớt.
    buildAttendanceIndex() {
        void this.attendances.length;
        let att = this.attIndex;
        if (!att) {
            att = new Map();
            this.attendances.forEach(a => att.set(a.programId + '|' + a.date + '|' + a.studentId, a));
        }
        const leave = new Map();
        this.leaveRequests.filter(r => r.status === 'đã duyệt')
            .forEach(r => leave.set(r.programId + '|' + r.date + '|' + r.studentId, r));
        return { att: att, leave: leave };
    },

    // Một lượt duyệt duy nhất, trả về mọi con số cần cho màn thống kê.
    // Dùng Map để tra cứu O(1) thay vì find() lồng nhau, tránh chậm khi
    // đoàn có cả ngàn em nhân với vài chục buổi.
    get statSummary() {
        const students = this.accessibleStudents.filter(s => s.status === 'đang sinh hoạt');
        const sessions = this.statSessions.filter(s => s.countForAttendance);

        // TÁI DÙNG chỉ số điểm danh dựng sẵn (O(1) tra cứu) thay vì quét lại
        // cả chục nghìn dòng mỗi lần đọc getter — nguyên nhân render treo.
        void this.attendances.length;   // vẫn tính lại khi thêm/bớt điểm danh
        let attIndex = this.attIndex;
        if (!attIndex) {
            attIndex = new Map();
            this.attendances.forEach(a => attIndex.set(a.programId + '|' + a.date + '|' + a.studentId, a));
        }
        const leaveIndex = new Map();
        this.leaveRequests.filter(r => r.status === 'đã duyệt')
            .forEach(r => leaveIndex.set(r.programId + '|' + r.date + '|' + r.studentId, r));

        const blank = () => ({ present: 0, late: 0, excused: 0, unexcused: 0, total: 0 });
        const total = blank();
        const byClass = {}, byBlock = {}, byStudent = {};
        let untaken = 0;

        students.forEach(st => { byStudent[st.id] = blank(); });

        sessions.forEach(ss => {
            // Buổi không có lấy một bản ghi nào trong phạm vi đang xem thì
            // gần như chắc chắn là GLV quên điểm danh, chứ không phải cả
            // lớp cùng nghỉ. Bỏ ra khỏi phép tính và đếm riêng, nếu không
            // một buổi bị quên sẽ kéo tỷ lệ chuyên cần xuống đáy.
            const wasTaken = students.some(st => attIndex.has(ss.programId + '|' + ss.date + '|' + st.id))
                || students.some(st => leaveIndex.has(ss.programId + '|' + ss.date + '|' + st.id));
            if (!wasTaken) { untaken++; return; }

            students.forEach(st => {
                const key = ss.programId + '|' + ss.date + '|' + st.id;
                const rec = attIndex.get(key);
                let bucket;
                if (rec) bucket = rec.status === 'đi trễ' ? 'late' : 'present';
                else if (leaveIndex.has(key)) bucket = 'excused';
                else bucket = 'unexcused';

                if (!byClass[st.className]) byClass[st.className] = blank();
                if (!byBlock[st.block]) byBlock[st.block] = blank();

                total[bucket]++;      total.total++;
                byClass[st.className][bucket]++; byClass[st.className].total++;
                byBlock[st.block][bucket]++;     byBlock[st.block].total++;
                byStudent[st.id][bucket]++;      byStudent[st.id].total++;
            });
        });

        const toRows = obj => Object.keys(obj)
            .map(k => ({ name: k, stats: obj[k], rate: this.attendRate(obj[k]) }))
            .sort((a, b) => b.rate - a.rate);

        return {
            students: students.length,
            sessionCount: this.statSessions.length,
            countedSessions: sessions.length - untaken,
            untakenSessions: untaken,
            total: total,
            rate: this.attendRate(total),
            byClass: toRows(byClass),
            byBlock: toRows(byBlock),
            byStudent: byStudent
        };
    },

    // Tỷ lệ có mặt = thực sự tới lớp (đúng giờ hoặc trễ)
    attendRate(t) {
        if (!t || t.total === 0) return 0;
        return Math.round(((t.present + t.late) / t.total) * 100);
    },

    // Tỷ lệ chuyên cần = không bị trừ điểm (tính cả vắng có phép và đi trễ)
    dutyRate(t) {
        if (!t || t.total === 0) return 0;
        return Math.round(((t.present + t.late + t.excused) / t.total) * 100);
    },

    percent(part, whole) {
        if (!whole) return 0;
        return Math.round((part / whole) * 100);
    },

    // Em nghỉ không phép nhiều nhất trong kỳ - danh sách để GLV gọi phụ huynh
    get studentsOfConcern() {
        const sum = this.statSummary;
        return this.accessibleStudents
            .filter(s => s.status === 'đang sinh hoạt')
            .map(s => ({ student: s, stats: sum.byStudent[s.id] || { unexcused: 0, total: 0 } }))
            .filter(x => x.stats.unexcused > 0)
            .sort((a, b) => b.stats.unexcused - a.stats.unexcused)
            .slice(0, 10);
    },

    // Đơn xin phép phát sinh trong tháng đang xem
    get statLeaveCounts() {
        const inMonth = this.scopedLeaveRequests.filter(r => r.date.slice(0, 7) === this.statMonth);
        return {
            pending:  inMonth.filter(r => r.status === 'chờ duyệt').length,
            approved: inMonth.filter(r => r.status === 'đã duyệt').length,
            rejected: inMonth.filter(r => r.status === 'từ chối').length,
            total: inMonth.length
        };
    },

    // Cơ cấu sĩ số trong phạm vi quyền
    get statRoster() {
        const all = this.accessibleStudents;
        const active = all.filter(s => s.status === 'đang sinh hoạt');
        return {
            active: active.length,
            paused: all.filter(s => s.status === 'dừng sinh hoạt').length,
            moved:  all.filter(s => s.status === 'chuyển xứ').length,
            male:   active.filter(s => Number(s.gender) === 1).length,
            female: active.filter(s => Number(s.gender) === 0).length
        };
    },

    get showBlockComparison() {
        return ['admin', 'bdh'].includes(this.user.role) && this.statSummary.byBlock.length > 1;
    },

    get showClassComparison() {
        return ['admin', 'bdh', 'truong_khoi'].includes(this.user.role) && this.statSummary.byClass.length > 1;
    },

    rateBarClass(rate) {
        if (rate >= 90) return 'bg-emerald-500';
        if (rate >= 75) return 'bg-blue-500';
        if (rate >= 50) return 'bg-amber-500';
        return 'bg-rose-500';
    },

    exportStatsCSV() {
        const sum = this.statSummary;
        if (sum.countedSessions === 0) {
            alert('Tháng này chưa có buổi nào để thống kê!');
            return;
        }
        const headers = ['Mã số', 'Tên Thánh', 'Họ và Tên', 'Lớp', 'Số buổi', 'Có mặt', 'Đi trễ', 'Vắng có phép', 'Vắng không phép', 'Tỷ lệ có mặt (%)'];
        const lines = [headers.map(h => this.csvCell(h)).join(',')];

        this.accessibleStudents
            .filter(s => s.status === 'đang sinh hoạt')
            .forEach(s => {
                const t = sum.byStudent[s.id];
                lines.push([s.code, s.holyName, s.name, s.className,
                            t.total, t.present, t.late, t.excused, t.unexcused, this.attendRate(t)]
                           .map(v => this.csvCell(v)).join(','));
            });

        const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'Thong_Ke_Chuyen_Can_' + this.statMonth + '.csv';
        link.click();
        URL.revokeObjectURL(url);
    },
};
