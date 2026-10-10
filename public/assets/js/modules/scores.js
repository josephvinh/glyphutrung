/* ==========================================================
   SCORES — Điểm số
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.scores = {
    // ==========================================
    // 8a. DATA: ĐIỂM SỐ
    //
    // Mỗi loại điểm có thể có nhiều bài kiểm tra (score_exams).
    // Điểm trung bình tính: trung bình các bài cùng loại rồi nhân hệ số.
    // ==========================================
    scoreTypes: [
        { key: 'mieng',  label: 'Miệng',    short: 'M',  weight: 1 },
        { key: 'p15',    label: '15 phút',  short: '15', weight: 1 },
        { key: 'giuaky', label: 'Giữa kỳ',  short: 'GK', weight: 2 },
        { key: 'cuoiky', label: 'Cuối kỳ',  short: 'CK', weight: 3 }
    ],
    // TODO: Load scoreTypes từ API ?action=types thay vì hardcode
    // Khi nào app initialization được refactor để load metadata động

    scores: [],
    // Chỉ số điểm O(1): 'studentId|examId' -> bản ghi. Dựng ở loadData.
    scoreIndex: null,
    // Exams theo loại điểm: typeCode -> [ { id, name, examDate } ]
    scoreExams: {},
    // Exam đang được chọn để nhập điểm
    scoreTab: 'enter',        // 'enter' = nhập theo cột | 'table' = bảng điểm
    scoreTermId: 1,
    scoreClass: '',
    scoreType: 'mieng',      // Loại điểm đang chọn (mieng/p15/giuaky/cuoiky)
    scoreExamId: null,        // Bài kiểm tra đang chọn (null = tạo exam mới)

    // Modal thêm bài kiểm tra
    showAddExamModal: false,
    addingExam: false,
    newExamName: '',
    newExamDate: '',

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
        this.scoreExamId = null;
        this.scoreExams = {};
        this.changeModule('scores');
        if (this.scoreClass) {
            this.loadScoreExams();
        }
    },

    get canWriteScores() {
        return this.canEditModule('scores');
    },

    get scoreStudents() {
        return this.accessibleStudents
            .filter(s => s.className === this.scoreClass && s.status === 'đang sinh hoạt')
            .sort((a, b) => a.code.localeCompare(b.code));
    },

    // ==========================================
    // SCORE INDEX
    // ==========================================
    scoreKey(studentId, examId) { return studentId + '|' + Number(examId); },

    // Dựng lại chỉ số từ this.scores. Gọi sau loadData.
    rebuildScoreIndex() {
        const idx = new Map();
        for (const s of this.scores) idx.set(this.scoreKey(s.studentId, s.examId), s);
        this.scoreIndex = idx;
    },

    // Lấy điểm của một bài kiểm tra cụ thể
    scoreOfExam(studentId, examId) {
        if (!this.scoreIndex) return '';
        const row = this.scoreIndex.get(this.scoreKey(studentId, examId));
        return row ? row.value : '';
    },

    // Lấy điểm theo type cũ (tương thích ngược — tìm exam đầu tiên)
    scoreOf(studentId, type, termId) {
        const exams = this.scoreExams[type] || [];
        if (exams.length === 0) return '';
        // Lấy exam đầu tiên (hoặc exam đang chọn)
        const examId = this.scoreExamId && exams.find(e => e.id === this.scoreExamId)
            ? this.scoreExamId
            : exams[0].id;
        return this.scoreOfExam(studentId, examId);
    },

    // Lấy tất cả điểm của một loại điểm (mảng giá trị)
    scoresOfType(studentId, type) {
        const exams = this.scoreExams[type] || [];
        return exams.map(e => this.scoreOfExam(studentId, e.id)).filter(v => v !== '');
    },

    // ==========================================
    // EXAMS
    // ==========================================

    // Tải exams của lớp + học kỳ
    async loadScoreExams() {
        if (!this.scoreClass || !this.scoreTermId) {
            this.scoreExams = {};
            return;
        }

        // Tìm classId từ className
        const cls = this.classes.find(c => c.name === this.scoreClass);
        if (!cls) return;

        try {
            const r = await this.save('scores', 'exams', {
                termId: this.scoreTermId,
                classId: cls.id
            }, 'GET');

            if (r && r.ok && r.byType) {
                const byType = {};
                for (const t of r.byType) {
                    byType[t.code] = t.exams;
                }
                this.scoreExams = byType;
                // Chọn exam đầu tiên của loại hiện tại nếu chưa chọn
                if (!this.scoreExamId) {
                    const firstExam = this.scoreExams[this.scoreType]?.[0];
                    this.scoreExamId = firstExam ? firstExam.id : null;
                }
            }
        } catch (e) {
            console.error('loadScoreExams error:', e);
        }
    },

    // Tạo bài kiểm tra mới
    async createScoreExam(typeCode, name, examDate) {
        if (!this.canWriteScores) return false;
        if (!this.scoreTermId) return false;

        try {
            const r = await this.save('scores', 'exam', {
                action: 'exam',
                termId: this.scoreTermId,
                typeCode: typeCode,
                name: name || '',
                examDate: examDate || null
            });

            if (r && r.ok) {
                // Thêm exam vào danh sách
                if (!this.scoreExams[typeCode]) {
                    this.scoreExams[typeCode] = [];
                }
                this.scoreExams[typeCode].push({
                    id: r.examId,
                    name: r.name || '',
                    examDate: r.examDate
                });
                // Chọn exam mới
                this.scoreExamId = r.examId;
                return true;
            }
        } catch (e) {
            console.error('createScoreExam error:', e);
        }
        return false;
    },

    // Xóa bài kiểm tra
    async deleteScoreExam(examId) {
        if (!this.canWriteScores) return false;

        try {
            const r = await this.save('scores', 'exam', {
                action: 'exam',
                _method: 'DELETE',
                examId: examId
            }, 'DELETE');

            if (r && r.ok) {
                // Xóa exam khỏi danh sách
                for (const type in this.scoreExams) {
                    this.scoreExams[type] = this.scoreExams[type].filter(e => e.id !== examId);
                }
                // Nếu đang chọn exam bị xóa, chọn exam khác
                if (this.scoreExamId === examId) {
                    const exams = this.scoreExams[this.scoreType] || [];
                    this.scoreExamId = exams.length > 0 ? exams[0].id : null;
                }
                // Xóa điểm của exam này khỏi local
                this.scores = this.scores.filter(s => s.examId !== examId);
                this.rebuildScoreIndex();
                return true;
            }
        } catch (e) {
            console.error('deleteScoreExam error:', e);
        }
        return false;
    },

    // ==========================================
    // SET SCORE
    // ==========================================

    // Để trống là xóa điểm, không phải ghi số 0
    setScore(studentId, examId, raw) {
        const txt = String(raw).trim().replace(',', '.');
        const key = this.scoreKey(studentId, examId);
        const i = this.scores.findIndex(s => s.studentId === studentId && s.examId === examId);
        const oldValue = i !== -1 ? this.scores[i].value : '';

        if (txt === '') {
            if (i !== -1) this.scores.splice(i, 1);
            if (this.scoreIndex) this.scoreIndex.delete(key);
            this.save('scores', 'set', {
                studentId: studentId,
                termId: this.scoreTermId,
                examId: examId,
                value: ''
            }).then(r => { if (!r || !r.ok) this.loadData(); });
            return true;
        }

        const v = Number(txt);
        if (isNaN(v) || v < 0 || v > 10) return false;

        const value = Math.round(v * 10) / 10;
        if (i !== -1) {
            this.scores[i].value = value;
            this.scores[i].at = this.timestamp();
            this.scores[i].by = this.user.fullName;
        } else {
            const rec = { studentId: studentId, examId: examId, value: value, at: this.timestamp(), by: this.user.fullName };
            this.scores.push(rec);
            if (this.scoreIndex) this.scoreIndex.set(key, rec);
        }

        this.save('scores', 'set', {
            studentId: studentId,
            termId: this.scoreTermId,
            examId: examId,
            value: String(value)
        }).then(r => { if (!r || !r.ok) this.loadData(); });

        return true;
    },

    onScoreInput(studentId, examId, event) {
        if (!this.setScore(studentId, examId, event.target.value)) {
            window.TNTT.toast.warning('Điểm phải là số từ 0 đến 10.');
            event.target.value = this.scoreOfExam(studentId, examId);
        }
    },

    // ==========================================
    // ĐIỂM TRUNG BÌNH
    // ==========================================

    // Trung bình các bài trong một loại điểm
    typeAverageOfStudent(studentId, typeKey) {
        const vals = this.scoresOfType(studentId, typeKey).map(Number);
        if (vals.length === 0) return null;
        return Math.round(vals.reduce((a, b) => a + b, 0) / vals.length * 10) / 10;
    },

    // ĐTB học kỳ, có trọng số, chỉ tính trên cột đã chấm
    // = (TB miệng × 1 + TB 15p × 1 + TB GK × 2 + TB CK × 3) / tổng hệ số đã chấm
    termAverage(studentId, termId) {
        let sum = 0, w = 0;
        this.scoreTypes.forEach(t => {
            const avg = this.typeAverageOfStudent(studentId, t.key);
            if (avg !== null) {
                sum += avg * t.weight;
                w += t.weight;
            }
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

    // ==========================================
    // UI HELPERS
    // ==========================================

    // Tiến độ nhập điểm của exam hiện tại
    get scoreProgress() {
        const exams = this.scoreExams[this.scoreType] || [];
        const currentExam = exams.find(e => e.id === this.scoreExamId);
        if (!currentExam) return { done: 0, total: this.scoreStudents.length };

        const total = this.scoreStudents.length;
        const done = this.scoreStudents.filter(s => this.scoreOfExam(s.id, this.scoreExamId) !== '').length;
        return { done, total, missing: total - done };
    },

    get currentScoreType() {
        return this.scoreTypes.find(t => t.key === this.scoreType) || this.scoreTypes[0];
    },

    // Các exam của loại hiện tại
    get currentScoreExams() {
        return this.scoreExams[this.scoreType] || [];
    },

    // Số bài đã nhập của một loại điểm
    scoreCountOfType(typeKey) {
        const exams = this.scoreExams[typeKey] || [];
        return exams.length;
    },

    // Trung bình loại điểm để hiển thị trên bảng điểm
    get scoreTypeAverages() {
        const result = {};
        this.scoreTypes.forEach(t => {
            // Chỉ hiện nếu có ít nhất 1 bài
            const exams = this.scoreExams[t.key] || [];
            if (exams.length > 0) {
                // Trung bình của trung bình các bài
                const avgs = exams.map(e => {
                    const vals = this.scoreStudents.map(s => Number(this.scoreOfExam(s.id, e.id))).filter(v => !isNaN(v));
                    return vals.length > 0 ? vals.reduce((a, b) => a + b, 0) / vals.length : null;
                }).filter(v => v !== null);
                result[t.key] = avgs.length > 0
                    ? Math.round(avgs.reduce((a, b) => a + b, 0) / avgs.length * 10) / 10
                    : null;
            }
        });
        return result;
    },

    // Xử lý đổi lớp
    onScoreClassChange(newClass) {
        this.scoreClass = newClass;
        this.scoreExamId = null;
        this.scoreExams = {};
        if (newClass) {
            this.loadScoreExams();
        }
    },

    // Xử lý đổi học kỳ
    onScoreTermChange(newTermId) {
        this.scoreTermId = Number(newTermId);
        this.scoreExamId = null;
        this.scoreExams = {};
        if (this.scoreClass) {
            this.loadScoreExams();
        }
    },

    // Xử lý đổi loại điểm
    onScoreTypeChange(newType) {
        this.scoreType = newType;
        // Chọn exam đầu tiên của loại mới
        const exams = this.scoreExams[newType] || [];
        this.scoreExamId = exams.length > 0 ? exams[0].id : null;
    },

    // Xử lý đổi exam
    onScoreExamChange(newExamId) {
        this.scoreExamId = Number(newExamId);
    },

    // Submit form thêm bài kiểm tra
    async submitAddExam() {
        if (this.addingExam) return;
        this.addingExam = true;
        try {
            const ok = await this.createScoreExam(this.scoreType, this.newExamName, this.newExamDate);
            if (ok) {
                this.showAddExamModal = false;
                this.newExamName = '';
                this.newExamDate = '';
                window.TNTT.toast.success('Đã tạo bài kiểm tra.');
            } else {
                window.TNTT.toast.error('Không tạo được bài kiểm tra.');
            }
        } finally {
            this.addingExam = false;
        }
    },

    exportScoresExcel() {
        const list = this.scoreStudents;
        if (list.length === 0) { window.TNTT.toast.warning('Lớp này chưa có em nào!'); return; }
        const term = this.terms.find(t => t.id === Number(this.scoreTermId));

        // Build headers: mỗi loại điểm = nhiều cột (mỗi bài)
        const headers = ['Mã số', 'Tên Thánh', 'Họ và Tên', 'Lớp', 'Học kỳ'];
        const examCols = []; // { typeKey, examId, label }
        for (const t of this.scoreTypes) {
            const exams = this.scoreExams[t.key] || [];
            if (exams.length === 0) {
                headers.push(t.label + ' (hs' + t.weight + ')');
                examCols.push({ typeKey: t.key, examId: null, label: t.label });
            } else {
                for (const e of exams) {
                    const label = e.name ? e.name : t.label + ' #' + e.id;
                    headers.push(label + ' (hs' + t.weight + ')');
                    examCols.push({ typeKey: t.key, examId: e.id, label });
                }
            }
        }
        headers.push('Điểm TB loại', 'Điểm TB HK', 'Học lực');

        const rows = [headers];
        list.forEach(s => {
            const row = [s.code, s.holyName, s.name, this.scoreClass, term.name];
            let tbLoai = [];
            for (const col of examCols) {
                if (col.examId) {
                    const val = this.scoreOfExam(s.id, col.examId);
                    row.push(val === '' ? '' : Number(val));
                } else {
                    const vals = this.scoresOfType(s.id, col.typeKey).map(Number);
                    const avg = vals.length > 0 ? Math.round(vals.reduce((a, b) => a + b, 0) / vals.length * 10) / 10 : '';
                    row.push(avg);
                    tbLoai.push(avg);
                }
            }
            const avg = this.termAverage(s.id, this.scoreTermId);
            row.push(avg === null ? '' : avg);
            row.push(avg === null ? '' : avg);
            row.push(this.academicRank(avg));
            rows.push(row);
        });

        this.downloadXlsx([{ name: 'Bảng điểm', rows }],
            'Bang_Diem_' + this.scoreClass.replace(/\s+/g, '_') + '.xlsx');
    },

};
