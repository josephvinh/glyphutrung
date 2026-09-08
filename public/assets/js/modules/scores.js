/* ==========================================================
   SCORES — Điểm số
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.scores = {
    // ==========================================
    // 8a. DATA: ĐIỂM SỐ
    //
    // Mỗi em mỗi học kỳ có tối đa 4 đầu điểm, mỗi đầu một cột.
    // Điểm trung bình tính có trọng số, và chỉ tính trên những cột
    // ĐÃ CÓ điểm — chấm được tới đâu ra kết quả tới đó, không phải
    // đợi đủ 4 cột mới có ĐTB.
    // ==========================================
    scoreTypes: [
        { key: 'mieng',  label: 'Miệng',    short: 'M',  weight: 1 },
        { key: 'p15',    label: '15 phút',  short: '15', weight: 1 },
        { key: 'giuaky', label: 'Giữa kỳ',  short: 'GK', weight: 2 },
        { key: 'cuoiky', label: 'Cuối kỳ',  short: 'CK', weight: 3 }
    ],

    scores: [],
    // Chỉ số điểm O(1): 'studentId|termId|type' -> bản ghi. Dựng ở loadData,
    // tránh this.scores.find() quét cả nghìn dòng mỗi lần đọc (ĐTB, Lên lớp
    // gọi rất nhiều lần/khung hình → treo). Cùng cách với chỉ số điểm danh.
    scoreIndex: null,
    scoreTab: 'enter',        // 'enter' = nhập theo cột | 'table' = bảng điểm
    scoreTermId: 1,
    scoreClass: '',
    scoreType: 'mieng',

    // Ngưỡng xét lên lớp — hiện rõ trên màn hình để không ai phải đoán
    PASS_SCORE: 5,
    PASS_ATTENDANCE: 60,

    openScores() {
        // Bắt chọn lớp trước khi hiện điểm (giống Danh sách). Chỉ tự mở khi
        // người dùng vỏn vẹn MỘT lớp; nhiều lớp (kiêm nhiệm / Trưởng khối /
        // BĐH / Quản trị) thì để trống, chọn lớp nào xem lớp đó. Bộ chọn chỉ
        // liệt kê lớp mình được phân công (availableClasses).
        this.scoreClass = this.availableClasses.length === 1 ? this.availableClasses[0] : '';
        this.scoreTermId = this.currentTerm.id;
        this.changeModule('scores');
    },

    get canWriteScores() {
        return this.canEditModule('scores');
    },

    get scoreStudents() {
        return this.accessibleStudents
            .filter(s => s.className === this.scoreClass && s.status === 'đang sinh hoạt')
            .sort((a, b) => a.code.localeCompare(b.code));
    },

    scoreKey(studentId, termId, type) { return studentId + '|' + Number(termId) + '|' + type; },

    // Dựng lại chỉ số từ this.scores. Gọi sau loadData.
    rebuildScoreIndex() {
        const idx = new Map();
        for (const s of this.scores) idx.set(this.scoreKey(s.studentId, s.termId, s.type), s);
        this.scoreIndex = idx;
    },

    scoreOf(studentId, type, termId) {
        const t = Number(termId || this.scoreTermId);
        if (this.scoreIndex) {
            const row = this.scoreIndex.get(this.scoreKey(studentId, t, type));
            return row ? row.value : '';
        }
        const row = this.scores.find(s => s.studentId === studentId && s.termId === t && s.type === type);
        return row ? row.value : '';
    },

    // Để trống là xóa điểm, không phải ghi số 0
    setScore(studentId, type, raw, termId) {
        const t = Number(termId || this.scoreTermId);
        const txt = String(raw).trim().replace(',', '.');
        const key = this.scoreKey(studentId, t, type);
        const i = this.scores.findIndex(s => s.studentId === studentId && s.termId === t && s.type === type);

        if (txt === '') {
            if (i !== -1) this.scores.splice(i, 1);
            if (this.scoreIndex) this.scoreIndex.delete(key);
            this.save('scores', 'set', { studentId: studentId, termId: t, type: type, value: '' });
            return true;
        }
        const v = Number(txt);
        if (isNaN(v) || v < 0 || v > 10) return false;

        const value = Math.round(v * 10) / 10;
        if (i !== -1) { this.scores[i].value = value; this.scores[i].at = this.timestamp(); this.scores[i].by = this.user.fullName; }
        else {
            const rec = { studentId: studentId, termId: t, type: type, value: value, at: this.timestamp(), by: this.user.fullName };
            this.scores.push(rec);
            if (this.scoreIndex) this.scoreIndex.set(key, rec);
        }
        this.save('scores', 'set', { studentId: studentId, termId: t, type: type, value: String(value) });
        return true;
    },

    onScoreInput(studentId, type, event) {
        if (!this.setScore(studentId, type, event.target.value)) {
            alert('Điểm phải là số từ 0 đến 10.');
            event.target.value = this.scoreOf(studentId, type);
        }
    },

    // ĐTB học kỳ, có trọng số, chỉ tính trên cột đã chấm
    termAverage(studentId, termId) {
        let sum = 0, w = 0;
        this.scoreTypes.forEach(t => {
            const v = this.scoreOf(studentId, t.key, termId);
            if (v !== '') { sum += Number(v) * t.weight; w += t.weight; }
        });
        return w === 0 ? null : Math.round((sum / w) * 10) / 10;
    },

    // ĐTB cả năm: trung bình cộng các học kỳ đã có điểm
    yearAverage(studentId) {
        const list = this.terms.map(t => this.termAverage(studentId, t.id)).filter(v => v !== null);
        if (list.length === 0) return null;
        return Math.round((list.reduce((a, b) => a + b, 0) / list.length) * 10) / 10;
    },

    academicRank(avg) {
        if (avg === null) return '–';
        if (avg >= 8) return 'Giỏi';
        if (avg >= 6.5) return 'Khá';
        if (avg >= 5) return 'Trung bình';
        return 'Yếu';
    },

    scoreRankClass(avg) {
        if (avg === null) return 'bg-slate-50 text-slate-400 border-slate-200';
        if (avg >= 8) return 'bg-emerald-50 text-emerald-600 border-emerald-100';
        if (avg >= 6.5) return 'bg-blue-50 text-blue-600 border-blue-100';
        if (avg >= 5) return 'bg-amber-50 text-amber-600 border-amber-100';
        return 'bg-rose-50 text-rose-600 border-rose-100';
    },

    get scoreProgress() {
        const total = this.scoreStudents.length;
        const done = this.scoreStudents.filter(s => this.scoreOf(s.id, this.scoreType) !== '').length;
        return { done: done, total: total, missing: total - done };
    },

    get currentScoreType() {
        return this.scoreTypes.find(t => t.key === this.scoreType) || this.scoreTypes[0];
    },

    exportScoresCSV() {
        const list = this.scoreStudents;
        if (list.length === 0) { alert('Lớp này chưa có em nào!'); return; }
        const term = this.terms.find(t => t.id === Number(this.scoreTermId));
        const headers = ['Mã số', 'Tên Thánh', 'Họ và Tên', 'Lớp', 'Học kỳ']
            .concat(this.scoreTypes.map(t => t.label + ' (hệ số ' + t.weight + ')'))
            .concat(['Điểm trung bình', 'Học lực']);
        const lines = [headers.map(h => this.csvCell(h)).join(',')];

        list.forEach(s => {
            const avg = this.termAverage(s.id, this.scoreTermId);
            lines.push([s.code, s.holyName, s.name, s.className, term.name]
                .concat(this.scoreTypes.map(t => this.scoreOf(s.id, t.key)))
                .concat([avg === null ? '' : avg, this.academicRank(avg)])
                .map(v => this.csvCell(v)).join(','));
        });

        const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'Bang_Diem_' + this.scoreClass.replace(/\s+/g, '_') + '.csv';
        link.click();
        URL.revokeObjectURL(url);
    },
};
