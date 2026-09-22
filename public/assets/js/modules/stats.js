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

    // Khoá tra cứu điểm danh (attKey) do attendance.js định nghĩa dùng chung
    // cho cả component — KHÔNG khai lại ở đây để tránh hai bản đè lẫn nhau.

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

    // ==========================================
    // XUẤT SỔ ĐIỂM DANH (dạng lưới)
    //
    // Khác với "Xuất CSV" (một dòng tổng kết mỗi em), bản này mô phỏng
    // đúng cuốn SỔ ĐIỂM DANH giấy:
    //   - Cột đầu: Mã thiếu nhi · Tên Thánh · Họ và Tên
    //   - Mỗi TUẦN là một nhóm cột lớn, gộp bên dưới là các CHƯƠNG TRÌNH
    //     tính chuyên cần của buổi hôm đó (Thánh Lễ, Giáo Lý Sáng/Chiều...)
    //   - Ô đánh dấu:  ✓ = có mặt · T = đi trễ · P = vắng có phép ·
    //     để TRỐNG = vắng (không phép)
    //   - Vài cột tổng kết ở cuối cho dễ cộng sổ.
    // Chỉ lấy các buổi ĐÃ QUA GIỜ CHỐT trong tháng đang xem, giống hệt
    // phạm vi của màn Thống kê nên số liệu không bao giờ lệch.
    // ==========================================
    exportAttendanceGridCSV() {
        const sessions = this.statSessions.filter(s => s.countForAttendance);
        if (sessions.length === 0) {
            alert('Tháng này chưa có buổi chuyên cần nào đã qua giờ chốt để xuất!');
            return;
        }

        // Gom các buổi theo NGÀY -> mỗi ngày là một "tuần" trên sổ. Trong
        // một ngày, các chương trình giữ thứ tự theo giờ bắt đầu (statSessions
        // đã sắp sẵn), không lặp lại chương trình.
        const weeks = [];
        const byDate = new Map();
        sessions.forEach(ss => {
            let w = byDate.get(ss.date);
            if (!w) { w = { date: ss.date, programs: [] }; byDate.set(ss.date, w); weeks.push(w); }
            if (!w.programs.some(p => p.programId === ss.programId)) {
                w.programs.push({ programId: ss.programId, name: ss.name });
            }
        });
        weeks.sort((a, b) => a.date.localeCompare(b.date));

        const { att, leave } = this.buildAttendanceIndex();

        const students = this.accessibleStudents
            .filter(s => s.status === 'đang sinh hoạt')
            .slice()
            .sort((a, b) =>
                (a.className || '').localeCompare(b.className || '', 'vi') ||
                String(a.code || '').localeCompare(String(b.code || ''), 'vi'));

        const dow  = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
        const ddmm = ds => ds.slice(8, 10) + '/' + ds.slice(5, 7);
        const dayLabel = ds => dow[new Date(ds + 'T00:00:00').getDay()] + ' ' + ddmm(ds);

        const lead = ['Mã thiếu nhi', 'Tên Thánh', 'Họ và Tên'];
        const tail = ['Có mặt', 'Đi trễ', 'Vắng', 'Tỷ lệ (%)'];

        // Hàng tiêu đề TUẦN: nhãn tuần đặt ở ô đầu mỗi nhóm, các ô còn lại để
        // trống — mở bằng Excel/Sheets có thể bôi-gộp cho ra "dòng lớn".
        const rowWeek = ['', '', ''];
        weeks.forEach((w, i) => {
            rowWeek.push('Tuần ' + (i + 1) + ' — ' + dayLabel(w.date));
            for (let k = 1; k < w.programs.length; k++) rowWeek.push('');
        });
        rowWeek.push('Tổng kết', '', '', '');

        // Hàng tiêu đề CHƯƠNG TRÌNH (nằm dưới mỗi tuần).
        const rowProg = lead.slice();
        weeks.forEach(w => w.programs.forEach(p => rowProg.push(p.name)));
        tail.forEach(t => rowProg.push(t));

        const lines = [];
        lines.push(this.csvCell('SỔ ĐIỂM DANH CHUYÊN CẦN — ' + this.statMonthLabel));
        lines.push(this.csvCell('Chú thích:  ✓ = Có mặt   ·   T = Đi trễ   ·   P = Vắng có phép   ·   (để trống) = Vắng'));
        lines.push(rowWeek.map(v => this.csvCell(v)).join(','));
        lines.push(rowProg.map(v => this.csvCell(v)).join(','));

        students.forEach(s => {
            const cells = [s.code, s.holyName, s.name];
            let present = 0, late = 0, absent = 0;

            weeks.forEach(w => w.programs.forEach(p => {
                const key = p.programId + '|' + w.date + '|' + s.id;
                const rec = att.get(key);
                if (rec) {
                    if (rec.status === 'đi trễ') { cells.push('T'); late++; }
                    else                         { cells.push('✓'); present++; }
                } else if (leave.has(key)) {
                    cells.push('P'); absent++;   // vắng có phép — vẫn là buổi vắng mặt
                } else {
                    cells.push('');  absent++;   // vắng không phép — để trống
                }
            }));

            const total = present + late + absent;
            const rate  = total ? Math.round(((present + late) / total) * 100) : 0;
            cells.push(present, late, absent, rate);
            lines.push(cells.map(v => this.csvCell(v)).join(','));
        });

        const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'So_Diem_Danh_Chuyen_Can_' + this.statMonth + '.csv';
        link.click();
        URL.revokeObjectURL(url);
    },
};
