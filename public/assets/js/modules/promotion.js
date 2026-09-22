/* ==========================================================
   PROMOTION — Lên lớp cuối năm
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};

// Bộ nhớ đệm chuyên cần cả năm theo KHỐI. yearAttendance bị gọi lại cho
// TỪNG em khi render (getter không tự nhớ), mà mỗi lần lại dựng chỉ số điểm
// danh vài chục nghìn dòng -> treo với đoàn lớn. Đệm ở đây, tính một lần cho
// mỗi khối. Đặt NGOÀI đối tượng Alpine để không dính vòng phản ứng. Khoá theo
// khối + số bản ghi điểm danh (đổi khi điểm danh thay đổi -> tự tính lại).
let _promoYearAttCache = { block: null, sig: -1, data: null };

window.TNTT.promotion = {
    // ==========================================
    // 8c. DATA: LÊN LỚP CUỐI NĂM
    //
    // Ba bước tách bạch: xét kết quả -> khai sơ đồ lớp kế tiếp ->
    // chuyển hàng loạt. Không gộp làm một, vì bước cuối là thao tác
    // đụng vào toàn bộ danh sách và không có nút hoàn tác.
    // ==========================================
    promoteTab: 'result',      // 'result' | 'map' | 'run'
    promoteBlock: '',
    promoteOverride: {},       // studentId -> 'len' | 'olai', chủ nhiệm ghi đè máy
    promoteDone: null,         // tóm tắt lần chuyển gần nhất

    openPromotion() {
        this.promoteTab = 'result';
        // Bắt chọn khối trước khi xét (giống Danh sách): một khối thì tự mở,
        // nhiều khối (BĐH / Quản trị) để trống, chọn khối nào xét khối đó.
        this.promoteBlock = this.availableBlocks.length === 1 ? this.availableBlocks[0] : '';
        this.promoteDone = null;
        this.changeModule('promotion');
    },

    get canPromote() {
        return this.canEditModule('promotion');
    },

    get promoteClasses() {
        return this.classes.filter(c => c.block === this.promoteBlock);
    },

    get promoteStudents() {
        return this.accessibleStudents
            .filter(s => s.block === this.promoteBlock && s.status === 'đang sinh hoạt')
            .sort((a, b) => (a.className + a.code).localeCompare(b.className + b.code));
    },

    // Chuyên cần cả năm, gộp mọi học kỳ. Vẫn bỏ buổi không ai điểm danh.
    // CHỈ tính cho khối đang xét (promoteStudents), không quét cả đoàn —
    // xét lên lớp luôn theo từng khối, tính cả 600 em mỗi lần gọi là thừa
    // và gây treo khi đoàn lớn.
    get yearAttendance() {
        // Trả bản đã đệm nếu vẫn đúng khối + chưa có thay đổi điểm danh.
        const sig = this.attendances.length;
        if (_promoYearAttCache.block === this.promoteBlock
            && _promoYearAttCache.sig === sig
            && _promoYearAttCache.data) {
            return _promoYearAttCache.data;
        }

        const students = this.promoteStudents;
        const blank = () => ({ present: 0, late: 0, excused: 0, unexcused: 0, total: 0 });
        const out = {};
        students.forEach(s => { out[s.id] = blank(); });
        if (this.terms.length === 0) { _promoYearAttCache = { block: this.promoteBlock, sig, data: out }; return out; }

        const idx = this.buildAttendanceIndex();
        const from = this.terms[0].from;
        const to = this.terms[this.terms.length - 1].to;
        const sessions = this.sessionsBetween(from, to).filter(s => s.countForAttendance);

        sessions.forEach(ss => {
            // HƯỚNG B: Dùng attKey hỗ trợ scheduleId
            const key = st => this.attKey({ programId: ss.programId, scheduleId: ss.scheduleId, date: ss.date, studentId: st.id });
            const taken = students.some(st => idx.att.has(key(st)) || idx.leave.has(key(st)));
            if (!taken) return;
            students.forEach(st => {
                const k = key(st);
                const rec = idx.att.get(k);
                let b;
                if (rec) b = rec.status === 'đi trễ' ? 'late' : 'present';
                else if (idx.leave.has(k)) b = 'excused';
                else b = 'unexcused';
                out[st.id][b]++; out[st.id].total++;
            });
        });
        _promoYearAttCache = { block: this.promoteBlock, sig, data: out };
        return out;
    },

    // Máy xét: đủ điểm VÀ đủ chuyên cần thì đạt. Chủ nhiệm có thể ghi đè.
    promoteVerdict(studentId) {
        const avg = this.yearAverage(studentId);
        const t = this.yearAttendance[studentId] || { total: 0 };
        const rate = this.attendRate(t);
        const auto = (avg !== null && avg >= this.PASS_SCORE && rate >= this.PASS_ATTENDANCE) ? 'len' : 'olai';
        const manual = this.promoteOverride[studentId];
        return {
            avg: avg, rate: rate, sessions: t.total,
            auto: auto,
            final: manual || auto,
            overridden: !!manual && manual !== auto,
            reason: avg === null ? 'chưa có điểm'
                  : (avg < this.PASS_SCORE ? 'điểm dưới ' + this.PASS_SCORE
                  : (rate < this.PASS_ATTENDANCE ? 'chuyên cần dưới ' + this.PASS_ATTENDANCE + '%' : 'đủ điều kiện'))
        };
    },

    toggleOverride(studentId) {
        const v = this.promoteVerdict(studentId);
        const flipped = v.final === 'len' ? 'olai' : 'len';
        if (flipped === v.auto) delete this.promoteOverride[studentId];
        else this.promoteOverride[studentId] = flipped;
    },

    get promoteSummary() {
        const list = this.promoteStudents;
        let len = 0, olai = 0, ra = 0;
        list.forEach(s => {
            const v = this.promoteVerdict(s.id);
            if (v.final === 'olai') { olai++; return; }
            const cls = this.classes.find(c => c.name === s.className);
            if (cls && cls.nextClass === 'RA_TRUONG') ra++; else len++;
        });
        return { total: list.length, up: len, stay: olai, graduate: ra,
                 overridden: list.filter(s => this.promoteVerdict(s.id).overridden).length };
    },

    setNextClass(className, target) {
        const cls = this.classes.find(c => c.name === className);
        if (!cls) return;
        cls.nextClass = target;
        this.save('org', 'saveClass', {
            original: className, name: className, block: cls.block, nextClass: target
        });
    },

    nextClassLabel(className) {
        const cls = this.classes.find(c => c.name === className);
        if (!cls || !cls.nextClass) return 'Chưa khai báo';
        return cls.nextClass === 'RA_TRUONG' ? 'Ra trường' : cls.nextClass;
    },

    // Lớp nào còn thiếu sơ đồ thì chưa cho chạy
    get unmappedClasses() {
        return this.promoteClasses.filter(c => !c.nextClass).map(c => c.name);
    },

    // Niên khoá đích: các năm khác năm đang mở và chưa khoá sổ
    get promoteTargetYears() {
        return this.years.filter(y => !y.isCurrent && y.status === 'đang mở');
    },
    promoteTargetId: '',

    async runPromotion() {
        if (this.unmappedClasses.length > 0) {
            alert('Còn lớp chưa khai báo lớp kế tiếp:\n' + this.unmappedClasses.join(', '));
            return;
        }
        const s = this.promoteSummary;

        // Chuyển các em sang một NIÊN KHOÁ KHÁC, không ghi đè năm
        // hiện tại — nếu không thì mất lịch sử học của các em.
        if (!this.promoteTargetId) {
            alert('Vui lòng chọn niên khoá đích để chuyển các em sang.\n'
                + 'Nếu chưa có, hãy mở niên khoá mới ở màn Cài đặt.');
            return;
        }
        const ty = this.years.find(y => y.id === Number(this.promoteTargetId));
        const msg = 'Chuyển lớp khối ' + this.promoteBlock + ' sang ' + (ty ? ty.name : '') + ':\n\n'
                  + '• Lên lớp: ' + s.up + ' em\n'
                  + '• Ở lại lớp: ' + s.stay + ' em\n'
                  + '• Ra trường: ' + s.graduate + ' em\n\n'
                  + 'Thao tác này KHÔNG hoàn tác được. Tiếp tục?';
        if (!confirm(msg)) return;

        const results = {};
        this.promoteStudents.forEach(st => { results[st.id] = this.promoteVerdict(st.id).final; });

        const r = await this.save('promotion', 'run', {
            block: this.promoteBlock,
            targetYearId: Number(this.promoteTargetId),
            results: results
        });
        if (!r.ok) return;

        this.promoteOverride = {};
        this.promoteDone = { block: this.promoteBlock, up: r.up, stay: r.stay,
                             graduate: r.graduate, at: r.at, targetYear: r.targetYear };
        await this.loadData();
    },
};
