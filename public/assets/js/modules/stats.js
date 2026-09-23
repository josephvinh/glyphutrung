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

    // MẢNG BÁO CÁO đang xem: 'chuyen_can' (buổi tính chuyên cần) hay
    // 'thi_dua' (buổi tính thi đua đi lễ). Hai cờ độc lập trên chương trình
    // (count_for_attendance / count_for_emulation) — nên hai loại buổi tách
    // biệt hoàn toàn, không đụng nhau. Mọi con số trên màn Thống kê và mọi
    // file xuất ra đều theo đúng mảng đang chọn.
    statCategory: 'chuyen_can',

    openStats(khongDoiMan = false) {
        if (!this.statMonth) this.statMonth = this.toDateInput(new Date()).slice(0, 7);
        if (!khongDoiMan) this.changeModule('stats');
    },

    // Cờ chương trình tương ứng mảng đang chọn.
    categoryFlag(cat) {
        return (cat || this.statCategory) === 'thi_dua' ? 'countForEmulation' : 'countForAttendance';
    },

    get statCategoryLabel() {
        return this.statCategory === 'thi_dua' ? 'Thi đua đi lễ' : 'Chuyên cần';
    },

    // Nhãn ngắn để đặt tên file / tiêu đề bảng.
    categoryText(cat) {
        return (cat || this.statCategory) === 'thi_dua' ? 'Thi đua đi lễ' : 'Chuyên cần';
    },
    categorySlug(cat) {
        return (cat || this.statCategory) === 'thi_dua' ? 'Thi_Dua_Di_Le' : 'Chuyen_Can';
    },

    // Các buổi trong tháng thuộc mảng đang chọn (đã qua giờ chốt).
    sessionsForCategory(cat) {
        const flag = this.categoryFlag(cat);
        return this.statSessions.filter(s => s[flag]);
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
    sessionsBetween(fromDate, toDate, className = null) {
        if (!fromDate || !toDate) return [];
        const out = [];
        const cursor = new Date(fromDate + 'T00:00:00');
        const end = new Date(toDate + 'T00:00:00');

        while (cursor <= end) {
            const ds = this.toDateInput(cursor);
            // className != null: chỉ lấy buổi ÁP DỤNG cho lớp đó (buổi gắn lớp
            // khác bị loại). programsOn đã lọc sẵn qua programAppliesToClass —
            // dùng cho Sổ liên lạc tính mẫu số theo lịch riêng của từng lớp.
            this.programsOn(ds, className).forEach(prog => {
                const session = { programId: prog.id, date: ds };
                if (this.isPastCutoffFor(session)) {
                    out.push({
                        programId: prog.id, date: ds, name: prog.name,
                        countForAttendance: prog.countForAttendance,
                        countForEmulation: prog.countForEmulation
                    });
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
        return this.summaryFor(this.statCategory);
    },

    // Tính toàn bộ số liệu cho MỘT mảng (chuyên cần / thi đua). Tách riêng
    // để màn Thống kê và các nút Xuất đều dùng chung, số liệu không bao giờ lệch.
    summaryFor(cat) {
        const students = this.accessibleStudents.filter(s => s.status === 'đang sinh hoạt');

        // Buổi gắn lớp (program_classes) chỉ tính học sinh của ĐÚNG các lớp đó
        // — khớp với màn Điểm danh (programScopeStudents). Buổi không gắn lớp =
        // toàn đoàn. Buổi gắn lớp mà không đụng em nào trong phạm vi đang xem
        // thì bỏ hẳn khỏi mẫu số, tránh đếm oan vắng cho lớp khác.
        const sessions = this.sessionsForCategory(cat)
            .map(ss => ({
                ss,
                sess: students.filter(st => this.programAppliesToClass({ id: ss.programId }, st.className))
            }))
            .filter(x => x.sess.length > 0);

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

        sessions.forEach(({ ss, sess }) => {
            // Buổi không có lấy một bản ghi nào trong phạm vi đang xem thì
            // gần như chắc chắn là GLV quên điểm danh, chứ không phải cả
            // lớp cùng nghỉ. Bỏ ra khỏi phép tính và đếm riêng, nếu không
            // một buổi bị quên sẽ kéo tỷ lệ chuyên cần xuống đáy.
            // (sess = học sinh thuộc lớp gắn của buổi.)
            const wasTaken = sess.some(st => attIndex.has(ss.programId + '|' + ss.date + '|' + st.id))
                || sess.some(st => leaveIndex.has(ss.programId + '|' + ss.date + '|' + st.id));
            if (!wasTaken) { untaken++; return; }

            sess.forEach(st => {
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
            sessionCount: sessions.length,
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

    // Xuất bảng TỔNG KẾT (CSV, mỗi em một dòng) cho mảng đang chọn — hoặc
    // truyền 'chuyen_can' / 'thi_dua' để xuất đích danh một mảng.
    exportStatsCSV(cat) {
        cat = cat || this.statCategory;
        const sum = this.summaryFor(cat);
        if (sum.countedSessions === 0) {
            window.TNTT.toast.warning('Tháng này chưa có buổi ' + this.categoryText(cat).toLowerCase() + ' nào để thống kê!');
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
        link.download = 'Tong_Ket_' + this.categorySlug(cat) + '_' + this.statMonth + '.csv';
        link.click();
        URL.revokeObjectURL(url);
    },

    // ==========================================
    // XUẤT SỔ ĐIỂM DANH (dạng lưới, có định dạng)
    //
    // Khác với "Tổng kết" (CSV, một dòng mỗi em), bản này mô phỏng đúng
    // cuốn SỔ ĐIỂM DANH giấy và giữ được MÀU/VIỀN/Ô GỘP:
    //   - Cột đầu: Mã thiếu nhi · Tên Thánh · Họ và Tên
    //   - Mỗi TUẦN là một ô lớn (gộp) đè lên các CHƯƠNG TRÌNH của mảng đang
    //     xuất (chuyên cần hoặc thi đua đi lễ) diễn ra hôm đó
    //   - Ô đánh dấu:  ✓ = có mặt · T = đi trễ · P = vắng có phép ·
    //     để TRỐNG = vắng (không phép)
    //   - Khối TỔNG KẾT (Có mặt / Đi trễ / Vắng / Tỷ lệ) ở cuối.
    //
    // Tham số cat: 'chuyen_can' | 'thi_dua' (mặc định = mảng đang chọn).
    // Kỹ thuật: xuất một BẢNG HTML rồi đặt đuôi .xls + MIME của Excel.
    // Excel/Google Sheets mở file HTML này như bảng tính bình thường, giữ
    // nguyên màu nền, viền và ô gộp — không cần thêm thư viện nào.
    // Chỉ lấy các buổi ĐÃ QUA GIỜ CHỐT trong tháng đang xem, đúng phạm vi
    // quyền như màn Thống kê nên số liệu không bao giờ lệch.
    // ==========================================
    exportAttendanceGridXLS(cat) {
        cat = cat || this.statCategory;
        const catText = this.categoryText(cat);
        const sessions = this.sessionsForCategory(cat);
        if (sessions.length === 0) {
            window.TNTT.toast.warning('Tháng này chưa có buổi ' + catText.toLowerCase() + ' nào đã qua giờ chốt để xuất!');
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

        // Bỏ cột chương trình gắn lớp mà không đụng em nào trong phạm vi đang
        // xuất (vd buổi của lớp khác), rồi bỏ luôn tuần trống sau khi lọc —
        // tránh cột toàn dấu "·" vô nghĩa.
        weeks.forEach(w => {
            w.programs = w.programs.filter(p =>
                students.some(st => this.programAppliesToClass({ id: p.programId }, st.className)));
        });
        for (let i = weeks.length - 1; i >= 0; i--) {
            if (weeks[i].programs.length === 0) weeks.splice(i, 1);
        }

        const dow  = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
        const ddmm = ds => ds.slice(8, 10) + '/' + ds.slice(5, 7);
        const dayLabel = ds => dow[new Date(ds + 'T00:00:00').getDay()] + ' ' + ddmm(ds);
        const esc = v => (v === null || v === undefined ? '' : String(v))
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

        const totalCols = 3 + weeks.reduce((n, w) => n + w.programs.length, 0) + 4;

        // Bảng màu (khớp file Excel mẫu).
        const BRD = '1px solid #b7c3d9';
        const cell = (txt, extra) => '<td style="border:' + BRD + ';padding:4px 6px;' + (extra || '') + '">' + txt + '</td>';
        const head = (txt, extra, span) =>
            '<td' + (span ? ' colspan="' + span + '"' : '') +
            ' style="border:' + BRD + ';padding:6px;font-weight:bold;text-align:center;vertical-align:middle;' + (extra || '') + '">' + txt + '</td>';

        let html = '';
        html += '<table style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:11px;color:#1f2937;">';

        // Tiêu đề + phụ đề + chú thích (gộp toàn bộ chiều ngang).
        html += '<tr><td colspan="' + totalCols + '" style="padding:8px;text-align:center;font-size:16px;font-weight:bold;color:#1f3864;">'
             + 'SỔ ĐIỂM DANH — ' + esc(catText.toUpperCase()) + '</td></tr>';
        html += '<tr><td colspan="' + totalCols + '" style="padding:2px 8px;text-align:center;font-style:italic;color:#595959;">'
             + esc(this.statMonthLabel) + '</td></tr>';
        html += '<tr><td colspan="' + totalCols + '" style="padding:6px 8px;text-align:center;font-weight:bold;color:#404040;background:#fff7e6;border:' + BRD + ';">'
             + 'Chú thích:&nbsp;&nbsp; ✓ Có mặt &nbsp;&nbsp; T Đi trễ &nbsp;&nbsp; P Vắng có phép &nbsp;&nbsp; (để trống) Vắng không phép</td></tr>';

        // Hàng tiêu đề TUẦN (ô Tuần gộp trên các chương trình; cột tên gộp 2 hàng).
        const nameHdr = 'background:#1f3864;color:#fff;';
        html += '<tr>';
        html += '<td rowspan="2" style="border:' + BRD + ';padding:6px;font-weight:bold;text-align:center;vertical-align:middle;' + nameHdr + '">Mã thiếu nhi</td>';
        html += '<td rowspan="2" style="border:' + BRD + ';padding:6px;font-weight:bold;text-align:center;vertical-align:middle;' + nameHdr + '">Tên Thánh</td>';
        html += '<td rowspan="2" style="border:' + BRD + ';padding:6px;font-weight:bold;text-align:center;vertical-align:middle;' + nameHdr + '">Họ và Tên</td>';
        weeks.forEach((w, i) => {
            html += head('Tuần ' + (i + 1) + '<br>' + dayLabel(w.date), 'background:#2e5496;color:#fff;', w.programs.length);
        });
        html += head('TỔNG KẾT', 'background:#c9a227;color:#fff;', 4);
        html += '</tr>';

        // Hàng tiêu đề CHƯƠNG TRÌNH + nhãn các cột tổng kết.
        html += '<tr>';
        weeks.forEach(w => w.programs.forEach(p => {
            html += head(esc(p.name), 'background:#d9e1f2;color:#1f3864;font-size:9px;');
        }));
        ['Có mặt', 'Đi trễ', 'Vắng', 'Tỷ lệ'].forEach(t => {
            html += head(t, 'background:#f2e2b5;color:#7a5c00;font-size:9px;');
        });
        html += '</tr>';

        // Các dòng học sinh.
        const mark = {
            'có mặt': { t: '✓', s: 'background:#c6efce;color:#1b7a3d;font-weight:bold;text-align:center;' },
            'đi trễ': { t: 'T', s: 'background:#ffeb9c;color:#9c6500;font-weight:bold;text-align:center;' },
            'phép':   { t: 'P', s: 'background:#bdd7ee;color:#1f4e78;font-weight:bold;text-align:center;' },
            'vắng':   { t: '',  s: 'background:#fbe4e6;text-align:center;' }
        };
        students.forEach((s, idx) => {
            const zebra = idx % 2 ? 'background:#f4f7fc;' : '';
            html += '<tr>';
            html += cell(esc(s.code), 'text-align:center;' + zebra);
            html += cell(esc(s.holyName), zebra);
            html += cell(esc(s.name), zebra);

            let present = 0, late = 0, absent = 0;
            weeks.forEach(w => w.programs.forEach(p => {
                // Buổi gắn lớp khác: em này không thuộc buổi -> ô trung tính,
                // KHÔNG tính là vắng, không cộng vào tổng.
                if (!this.programAppliesToClass({ id: p.programId }, s.className)) {
                    html += cell('·', 'background:#f3f4f6;color:#c7cdd6;text-align:center;');
                    return;
                }
                const key = p.programId + '|' + w.date + '|' + s.id;
                const rec = att.get(key);
                let m;
                if (rec) {
                    if (rec.status === 'đi trễ') { m = mark['đi trễ']; late++; }
                    else                         { m = mark['có mặt']; present++; }
                } else if (leave.has(key)) { m = mark['phép']; absent++; }
                else                       { m = mark['vắng']; absent++; }
                html += cell(m.t, m.s);
            }));

            const total = present + late + absent;
            const rate  = total ? Math.round(((present + late) / total) * 100) : 0;
            const sumS  = 'text-align:center;font-weight:bold;color:#1f3864;' + zebra;
            html += cell(present, sumS);
            html += cell(late, sumS);
            html += cell(absent, sumS);
            html += cell(rate + '%', sumS);
            html += '</tr>';
        });

        html += '</table>';

        const doc = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">'
            + '<head><meta charset="utf-8">'
            + '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>'
            + '<x:Name>' + esc(catText) + '</x:Name>'
            + '<x:WorksheetOptions><x:FrozenNoSplit/><x:SplitHorizontal>7</x:SplitHorizontal>'
            + '<x:TopRowBottomPane>7</x:TopRowBottomPane><x:SplitVertical>3</x:SplitVertical>'
            + '<x:LeftColumnRightPane>3</x:LeftColumnRightPane><x:ActivePane>0</x:ActivePane>'
            + '<x:Panes><x:Pane><x:Number>3</x:Number></x:Pane></x:Panes></x:WorksheetOptions>'
            + '</x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->'
            + '</head><body>' + html + '</body></html>';

        const blob = new Blob(['﻿' + doc], { type: 'application/vnd.ms-excel;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'So_Diem_Danh_' + this.categorySlug(cat) + '_' + this.statMonth + '.xls';
        link.click();
        URL.revokeObjectURL(url);
    },
};
