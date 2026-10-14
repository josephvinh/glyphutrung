/* ==========================================================
   SCORES — Điểm số (Tuỳ chỉnh)
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.

   Điểm trung bình tính theo Cách A:
   1. TB mỗi loại = avg(N bài đã chấm)
   2. ĐTB = Σ(TB_loại × hệ_số) / Σ(hệ_số)
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.scores = {
    // ==========================================
    // DATA: ĐIỂM SỐ (ĐỘNG)
    // ==========================================

    // scoreTypes từ API (động)
    scoreTypes: [],

    // Rules từ backend
    scoreRules: {
        avg_weights: {},       // { mieng: 1, p15: 1, giuaky: 2, cuoiky: 3 }
        rank_thresholds: {},  // { gioi: 8, kha: 6.5, tb: 5, yeu: 0 }
        passing_min_avg: 5
    },

    // Điểm chuyên cần
    attendanceConfig: {
        thresholds: []        // [{label:'A', min_percent:90}, ...]
    },

    scores: [],

    // Chỉ số điểm: [studentId][termId][typeCode][examNum] = value
    // Dựng ở rebuildScoreIndex(), tránh quét cả nghìn dòng
    scoreIndex: {},

    // UI state
    scoreTab: 'enter',    // 'enter' | 'table'
    scoreTermId: 1,
    scoreClass: '',
    scoreType: 'mieng',

    // ==========================================
    // INIT: Load rules khi app khởi động
    // ==========================================

    async initScoreRules() {
        try {
            const resp = await fetch('/api/rules.php?type=score');
            if (!resp.ok) return;
            const data = await resp.json();

            this.scoreTypes = data.score_types || [];

            // Build avg_weights từ score_types
            this.scoreRules.avg_weights = {};
            this.scoreTypes.forEach(t => {
                this.scoreRules.avg_weights[t.code] = t.weight;
            });

            // Parse rank_thresholds
            if (data.rules && data.rules.rank_thresholds) {
                try {
                    this.scoreRules.rank_thresholds = JSON.parse(data.rules.rank_thresholds.rule_value);
                } catch (e) {
                    this.scoreRules.rank_thresholds = { gioi: 8, kha: 6.5, tb: 5, yeu: 0 };
                }
            }
        } catch (e) {
            console.warn('Không tải được rules điểm số:', e);
        }
    },

    async initAttendanceConfig() {
        try {
            const resp = await fetch('/api/rules.php?type=attendance');
            if (!resp.ok) return;
            const data = await resp.json();
            this.attendanceConfig.thresholds = data.thresholds || [];
        } catch (e) {
            console.warn('Không tải được config chuyên cần:', e);
        }
    },

    // ==========================================
    // METHODS: Score Index
    // ==========================================

    // Dựng lại scoreIndex từ scores array
    rebuildScoreIndex() {
        this.scoreIndex = {};
        for (const s of this.scores) {
            if (!this.scoreIndex[s.studentId]) this.scoreIndex[s.studentId] = {};
            if (!this.scoreIndex[s.studentId][s.termId]) this.scoreIndex[s.studentId][s.termId] = {};
            if (!this.scoreIndex[s.studentId][s.termId][s.type]) this.scoreIndex[s.studentId][s.termId][s.type] = {};
            this.scoreIndex[s.studentId][s.termId][s.type][s.exam_num] = s.value;
        }
    },

    // Lấy điểm 1 bài cụ thể
    scoreOfExam(studentId, type, examNum, termId) {
        const t = Number(termId || this.scoreTermId);
        const si = this.scoreIndex[studentId];
        if (!si || !si[t] || !si[t][type]) return '';
        return si[t][type][examNum] ?? '';
    },

    // Lấy tất cả điểm 1 loại (trả về object { examNum: value })
    scoresOfType(studentId, type, termId) {
        const t = Number(termId || this.scoreTermId);
        return this.scoreIndex[studentId]?.[t]?.[type] || {};
    },

    // ==========================================
    // METHODS: Tính điểm (Cách A)
    // ==========================================

    // TB mỗi loại (bình quân N bài đã chấm)
    avgOfType(studentId, type, termId) {
        const typeScores = this.scoresOfType(studentId, type, termId);
        const values = Object.values(typeScores).filter(v => v !== '' && v !== null && v !== undefined);
        if (values.length === 0) return null;
        return Math.round(values.reduce((a, b) => a + b, 0) / values.length * 10) / 10;
    },

    // ĐTB học kỳ: TB loại → nhân hệ số → chia tổng
    termAverage(studentId, termId) {
        let sum = 0, w = 0;
        for (const type of this.scoreTypes) {
            const avg = this.avgOfType(studentId, type.code, termId);
            if (avg !== null) {
                sum += avg * (type.weight || 1);
                w += type.weight || 1;
            }
        }
        return w === 0 ? null : Math.round(sum / w * 100) / 100;
    },

    // ĐTB cả năm
    yearAverage(studentId) {
        const list = this.terms.map(t => this.termAverage(studentId, t.id)).filter(v => v !== null);
        if (list.length === 0) return null;
        return Math.round(list.reduce((a, b) => a + b, 0) / list.length * 10) / 10;
    },

    // Xếp loại học lực (động từ rules)
    academicRank(avg) {
        if (avg === null) return '–';
        const t = this.scoreRules.rank_thresholds;
        if (avg >= (t.gioi || 8)) return 'Giỏi';
        if (avg >= (t.kha  || 6.5)) return 'Khá';
        if (avg >= (t.tb   || 5)) return 'Trung bình';
        return 'Yếu';
    },

    scoreRankClass(avg) {
        if (avg === null) return 'bg-slate-50 text-slate-400 border-slate-200';
        if (avg >= 8) return 'bg-emerald-50 text-emerald-600 border-emerald-100';
        if (avg >= 6.5) return 'bg-blue-50 text-blue-600 border-blue-100';
        if (avg >= 5) return 'bg-amber-50 text-amber-600 border-amber-100';
        return 'bg-rose-50 text-rose-600 border-rose-100';
    },

    // ==========================================
    // METHODS: CRUD bài kiểm tra
    // ==========================================

    // Thêm 1 bài mới (lấy exam_num = max + 1)
    addExamForStudent(studentId, type, termId) {
        const typeScores = this.scoresOfType(studentId, type, termId);
        const typeConfig = this.scoreTypes.find(t => t.code === type);
        const maxExams = typeConfig?.max_exams || 1;
        const existingCount = Object.keys(typeScores).filter(k => typeScores[k] !== '').length;

        if (existingCount >= maxExams) {
            window.TNTT.toast.warning('Đã đạt số bài tối đa của loại này (' + maxExams + ').');
            return;
        }

        const nextExam = existingCount + 1;
        // Gọi setScore với giá trị rỗng để tạo bài mới
        this.setScore(studentId, type, '', termId, nextExam);
    },

    // Xoá 1 bài & renumber
    async deleteExam(studentId, type, examNum, termId) {
        const t = Number(termId || this.scoreTermId);
        await this.save('scores', 'delete_exam', {
            studentId, termId: t, type, examNum
        });
        // Reload để lấy dữ liệu đã renumber
        await this.loadData();
    },

    // Xác nhận xóa bài với student info
    confirmDeleteExam(student, type, examNum) {
        const typeLabel = this.scoreTypes.find(t => t.code === type)?.label || type;
        if (!confirm('Xóa bài ' + typeLabel + ' số ' + examNum + ' của ' + student.name + '?\nCác bài phía sau sẽ được đánh số lại.')) return;
        this.deleteExam(student.id, type, examNum);
    },

    // Cập nhật điểm
    setScore(studentId, type, raw, termId, examNum) {
        const t = Number(termId || this.scoreTermId);
        const num = Number(examNum || 1);
        const txt = String(raw).trim().replace(',', '.');

        if (txt !== '') {
            const v = Number(txt);
            if (isNaN(v) || v < 0 || v > 10) return false;
        }

        const value = txt === '' ? '' : Math.round(Number(txt) * 10) / 10;

        // Update local index
        if (!this.scoreIndex[studentId]) this.scoreIndex[studentId] = {};
        if (!this.scoreIndex[studentId][t]) this.scoreIndex[studentId][t] = {};
        if (!this.scoreIndex[studentId][t][type]) this.scoreIndex[studentId][t][type] = {};
        this.scoreIndex[studentId][t][type][num] = value;

        // Call API
        this.save('scores', 'set', {
            studentId, termId: t, type, exam_num: num, value: String(value)
        }).then(r => { if (!r || !r.ok) this.loadData(); });

        return true;
    },

    onScoreInput(studentId, type, examNum, event) {
        if (!this.setScore(studentId, type, event.target.value, null, examNum)) {
            window.TNTT.toast.warning('Điểm phải là số từ 0 đến 10.');
            event.target.value = this.scoreOfExam(studentId, type, examNum);
        }
    },

    // ==========================================
    // METHODS: UI Helpers
    // ==========================================

    // Số bài đã nhập của 1 loại
    examCountOfType(studentId, type, termId) {
        const typeScores = this.scoresOfType(studentId, type, termId);
        return Object.keys(typeScores).filter(k => typeScores[k] !== '').length;
    },

    // Có thể thêm bài không?
    canAddExam(studentId, type, termId) {
        const typeConfig = this.scoreTypes.find(t => t.code === type);
        const maxExams = typeConfig?.max_exams || 1;
        return this.examCountOfType(studentId, type, termId) < maxExams;
    },

    // Số bài hiện có của loại (bao gồm cả bài trống đang nhập)
    examNumsOfType(studentId, type, termId) {
        const typeScores = this.scoresOfType(studentId, type, termId);
        const typeConfig = this.scoreTypes.find(t => t.code === type);
        const maxExams = typeConfig?.max_exams || 1;

        // Lấy các exam_num đã có
        const existing = Object.keys(typeScores).map(Number).sort((a, b) => a - b);
        if (existing.length === 0) return [1]; // Mặc định 1 bài

        return existing.slice(0, maxExams);
    },

    // ==========================================
    // METHODS: Điểm chuyên cần
    // ==========================================

    attendanceRank(percent) {
        if (percent === null || percent === undefined) return '–';
        const thresholds = this.attendanceConfig.thresholds;
        for (const t of thresholds) {
            if (percent >= t.min_percent) return t.label;
        }
        return 'KĐ';
    },

    async getAttendanceScore(studentId, termId) {
        // Giữ cho ai cũ gọi vẫn chạy — nhưng màn điểm nên dùng
        // getAttendanceForClass() (1 request/lớp) thay vì gọi hàm
        // này cho từng em (N+1).
        try {
            const resp = await fetch('/api/attendance_scores.php?term_id=' + termId + '&student_id=' + studentId);
            if (!resp.ok) return null;
            return await resp.json();
        } catch (e) {
            return null;
        }
    },

    // Lấy điểm chuyên cần 1 em từ cache (cache được fill bởi getAttendanceForClass)
    attendanceOf(studentId) {
        return this._attendanceClassCache[this.scoreClass + ':' + this.scoreTermId]?.[studentId] ?? null;
    },

    // Lấy điểm chuyên cần cả lớp 1 lần (attendance_class.php) — tránh
    // N+1 của getAttendanceScore() khi duyệt từng em. Cache theo lớp
    // + học kỳ để gọi lại trong cùng view thì không tốn thêm request.
    _attendanceClassCache: {},
    async getAttendanceForClass(classId, termId) {
        if (!classId || !termId) return {};
        const key = classId + ':' + termId;
        if (this._attendanceClassCache[key]) return this._attendanceClassCache[key];

        try {
            const resp = await fetch('/api/attendance_class.php?term_id=' + termId + '&class_id=' + classId);
            if (!resp.ok) {
                this._attendanceClassCache[key] = {};
                return {};
            }
            const arr = await resp.json();
            const map = {};
            for (const row of (arr || [])) {
                map[row.student_id] = row;
            }
            this._attendanceClassCache[key] = map;
            return map;
        } catch (e) {
            this._attendanceClassCache[key] = {};
            return {};
        }
    },

    // ==========================================
    // UI: Open & State
    // ==========================================

    openScores() {
        // Load rules nếu chưa có (lazy load)
        if (this.scoreTypes.length === 0) {
            this.initScoreRules();
        }
        // Load attendance config nếu chưa có
        if (this.attendanceConfig.thresholds.length === 0) {
            this.initAttendanceConfig();
        }
        this.scoreClass = this.availableClasses.length === 1 ? this.availableClasses[0] : '';
        this.scoreTermId = this.currentTerm?.id || 1;
        this.scoreType = this.scoreTypes[0]?.code || 'mieng';
        // Load attendance cả lớp ngay khi mở (1 request, tránh N+1)
        if (this.scoreClass) {
            this.getAttendanceForClass(this.scoreClass, this.scoreTermId);
        }
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

    get currentScoreType() {
        return this.scoreTypes.find(t => t.code === this.scoreType) || this.scoreTypes[0];
    },

    // Tiến độ nhập cho loại hiện tại
    get scoreProgress() {
        const type = this.scoreType;
        const total = this.scoreStudents.length;
        const done = this.scoreStudents.filter(s => this.examCountOfType(s.id, type, this.scoreTermId) > 0).length;
        return { done, total, missing: total - done };
    },

    // ==========================================
    // UI: Export Excel (động theo scoreTypes)
    // ==========================================

    exportScoresExcel() {
        const list = this.scoreStudents;
        if (list.length === 0) { window.TNTT.toast.warning('Lớp này chưa có em nào!'); return; }
        const term = this.terms.find(t => t.id === Number(this.scoreTermId));

        // Header: Mã, Tên, Lớp, HK, rồi từng cột động
        const headers = ['Mã số', 'Tên Thánh', 'Họ và Tên', 'Lớp', 'Học kỳ'];
        const colWidths = [10, 12, 20, 8, 12];

        for (const type of this.scoreTypes) {
            const examNums = this.examNumsOfType(list[0].id, type.code, this.scoreTermId);
            for (let i = 0; i < examNums.length; i++) {
                headers.push(type.label + ' ' + (i + 1) + ' (HS' + type.weight + ')');
                colWidths.push(10);
            }
        }
        headers.push('ĐTB');
        colWidths.push(8);
        headers.push('Học lực');
        colWidths.push(10);

        const rows = [headers];

        list.forEach(s => {
            const avg = this.termAverage(s.id, this.scoreTermId);
            const num = (v) => (v === '' || v === null || v === undefined || isNaN(Number(v))) ? '' : Number(v);

            const row = [s.code, s.holyName, s.name, s.className, term?.name || ''];

            for (const type of this.scoreTypes) {
                const examNums = this.examNumsOfType(s.id, type.code, this.scoreTermId);
                for (let i = 0; i < examNums.length; i++) {
                    row.push(num(this.scoreOfExam(s.id, type.code, examNums[i])));
                }
            }

            row.push(avg === null ? '' : num(avg));
            row.push(this.academicRank(avg));

            rows.push(row);
        });

        this.downloadXlsx([{ name: 'Bảng điểm', rows, colWidths }],
            'Bang_Diem_' + this.scoreClass.replace(/\s+/g, '_') + '.xlsx');
    },

};
