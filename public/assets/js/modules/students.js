/* ==========================================================
   STUDENTS — Danh sách thiếu nhi và xuất/nhập CSV
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.students = {
    // ==========================================
    // 2. DATA: DANH SÁCH LỚP & HÀM XỬ LÝ
    // gender: 1 = Nam, 0 = Nữ
    // ==========================================
    // Chỉ gồm các em trong phạm vi mình được XEM (GLV: lớp mình,
    // Trưởng Khối: khối mình, Ban Điều Hành: toàn đoàn).
    students: [],   // máy chủ nạp qua loadData()

    // Sĩ số MỌI lớp, đếm sẵn ở máy chủ: { "Khai Tâm 1A": 7, ... }
    // Dùng cho màn Khối & Lớp, vì students ở trên không đủ để đếm.
    classCounts: {},

    searchQuery: '',
    filterStatus: '',
    filterBlock: '',
    filterClass: '',
    showFilter: false,
    showEditModal: false,
    editData: {},

    displayLimit: 20,

    get displayedStudents() {
        return this.filteredStudents.slice(0, this.displayLimit);
    },

    loadMore() {
        this.displayLimit += 20;
    },

    openEdit(student) {
        this.editData = JSON.parse(JSON.stringify(student));
        this.editData.isNew = false;
        this.showEditModal = true;
    },

    /**
     * THÊM MỘT EM MỚI
     *
     * Dùng chung cửa sổ với sửa hồ sơ — cùng bộ trường, cùng chỗ lưu.
     * Điền sẵn lớp mình phụ trách: chủ nhiệm chỉ có một lớp nên khỏi
     * phải chọn, còn Ban Điều Hành thì để trống cho tự chọn.
     */
    openAddStudent() {
        const ghi = this.writableClasses;
        const lop = this.filterClass
                 || (ghi && ghi.length === 1 ? ghi[0] : '');
        const cls = this.classes.find(c => c.name === lop);

        this.editData = {
            id: null, isNew: true,
            code: 'Đang cấp…',      // máy chủ tự cấp; điền ngay bên dưới
            holyName: '', name: '',
            gender: 1, birthDate: '', address: '',
            fatherName: '', fatherPhone: '',
            motherName: '', motherPhone: '',
            className: lop, block: cls ? cls.block : '',
            status: 'đang sinh hoạt'
        };
        this.showEditModal = true;
        this.fillNextStudentCode();
    },

    // Lấy mã kế tiếp từ máy chủ để hiện sẵn (chỉ đọc). Máy chủ vẫn cấp lại
    // lúc lưu nên đây chỉ là xem trước — client không tự sinh để tránh trùng.
    async fillNextStudentCode() {
        const r = await this.api('students', 'next_code');
        if (r && r.ok && this.editData.isNew) this.editData.code = r.code;
    },

    get editModalTitle() {
        return this.editData.isNew ? 'Thêm thiếu nhi' : 'Cập nhật hồ sơ';
    },

    async saveEdit() {
        const e = this.editData;

        if (!String(e.name || '').trim())  return alert('Vui lòng nhập họ và tên.');
        if (!e.className)                 return alert('Vui lòng chọn lớp cho em.');

        e.name = String(e.name).trim();

        // Lớp đổi thì khối phải đổi theo, tránh dữ liệu mâu thuẫn
        const cls = this.classes.find(c => c.name === e.className);
        if (cls) e.block = cls.block;

        this.showEditModal = false;

        if (e.isNew) {
            this.logAction('tao', 'students', 'Thêm thiếu nhi ' + e.name, e.code + ' · ' + e.className);
        } else {
            const i = this.students.findIndex(s => s.id === e.id);
            if (i !== -1) this.students[i] = e;
            this.logAction('sua', 'students', 'Sửa hồ sơ ' + e.name, e.code + ' · ' + e.className);
        }

        const r = await this.save('students', 'save', e);
        // Thêm mới thì phải nạp lại để lấy id thật do máy chủ cấp
        if (r && r.ok && e.isNew) await this.loadData();
    },

    // ==========================================
    // 3. XUẤT / NHẬP FILE CSV
    // ==========================================
    importColumns: [
        { header: 'Mã số',      key: 'code' },
        { header: 'Tên Thánh',  key: 'holyName' },
        { header: 'Họ và Tên',  key: 'name' },
        { header: 'Giới tính',  key: 'gender' },
        { header: 'Ngày sinh',  key: 'birthDate' },
        { header: 'Khối',       key: 'block' },
        { header: 'Lớp',        key: 'className' },
        { header: 'Tình trạng', key: 'status' },
        { header: 'Tên Cha',    key: 'fatherName' },
        { header: 'SĐT Cha',    key: 'fatherPhone' },
        { header: 'Tên Mẹ',     key: 'motherName' },
        { header: 'SĐT Mẹ',     key: 'motherPhone' },
        { header: 'Địa chỉ',    key: 'address' }
    ],

    // Bọc 1 ô CSV: nhân đôi dấu nháy kép, nếu không tên kiểu Nguyễn Văn "Bo" sẽ phá vỡ cấu trúc file
    csvCell(value) {
        const str = (value === null || value === undefined) ? '' : String(value);
        return '"' + str.replace(/"/g, '""') + '"';
    },

    // ==========================================
    // XUẤT FILE
    //
    // Có dữ liệu  -> xuất danh sách như bình thường.
    // Không có gì -> xuất FILE MẪU đúng định dạng, để GLV điền rồi
    //                nhập ngược lại. Trước đây chỗ này chỉ báo "Không có
    //                dữ liệu để xuất!" rồi thôi, trong khi thông báo lỗi
    //                bên phần Nhập lại bảo người dùng "bấm Xuất để lấy
    //                file mẫu" — hứa mà không có.
    // ==========================================
    exportToCSV() {
        if (this.filteredStudents.length === 0) {
            this.exportTemplateCSV();
            return;
        }

        const lines = [this.importColumns.map(c => this.csvCell(c.header)).join(',')];
        this.filteredStudents.forEach(s => {
            lines.push(this.importColumns.map(c => {
                if (c.key === 'gender')    return this.csvCell(this.genderLabel(s.gender));
                if (c.key === 'birthDate') return this.csvCell(this.formatDate(s.birthDate));
                return this.csvCell(s[c.key]);
            }).join(','));
        });

        this.taiFileCSV(lines, 'Danh_Sach_Thieu_Nhi_' + this.todayStamp() + '.csv');
    },

    // Bỏ dấu và thay khoảng trắng, để tên file không bị vỡ trên Windows
    tenFileAnToan(s) {
        return String(s || '')
            .normalize('NFD').replace(/[̀-ͯ]/g, '')
            .replace(/đ/g, 'd').replace(/Đ/g, 'D')
            .replace(/[^A-Za-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
    },

    taiFileCSV(lines, tenFile) {
        // BOM (U+FEFF) để Excel nhận đúng tiếng Việt UTF-8
        const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = tenFile;
        link.click();
        URL.revokeObjectURL(url);
    },

    /**
     * File mẫu để nhập danh sách.
     *
     * Phần hướng dẫn nằm ở các dòng bắt đầu bằng #, và applyImport() bỏ
     * qua chúng — nên người dùng KHÔNG cần xoá gì trước khi nhập lại.
     * Danh sách lớp hợp lệ lấy từ dữ liệu sống, không chép cứng, nên
     * luôn khớp với những gì đang khai ở màn Khối & Lớp.
     */
    exportTemplateCSV() {
        // Không lọc lớp nào thì lấy lớp mình phụ trách — chủ nhiệm chỉ có
        // đúng một lớp, nên mẫu ra là đã đúng lớp, khỏi phải nhớ tên.
        const chiGhi = this.writableClasses;
        const lop   = this.filterClass
                   || (chiGhi && chiGhi.length === 1 ? chiGhi[0] : '');
        const cls   = this.classes.find(c => c.name === lop);
        const khoi  = cls ? cls.block : (this.filterBlock || '');
        // Chỉ liệt kê lớp mình được GHI vào. Liệt kê cả lớp không được ghi
        // chỉ khiến người dùng điền vào rồi bị máy chủ từ chối.
        const dsLop = chiGhi === null ? this.classes.map(c => c.name) : chiGhi;

        // Ví dụ điền sẵn lớp đang lọc, để GLV chỉ việc thay tên các em
        const viDu = {
            code: 'TN26001', holyName: 'Phêrô', name: 'Nguyễn Văn An',
            gender: 'Nam', birthDate: '05/09/2017',
            block: khoi || 'Khai Tâm', className: lop || (dsLop[0] || 'Khai Tâm 1A'),
            status: 'đang sinh hoạt',
            fatherName: 'Nguyễn Văn Bình', fatherPhone: '0901234567',
            motherName: 'Trần Thị Cúc',    motherPhone: '0907654321',
            address: '12 Nguyễn Trãi'
        };

        const bat = this.importColumns.filter(c => ['code', 'name'].includes(c.key))
                                      .map(c => c.header).join(', ');

        const lines = [];
        const ghi = (t) => lines.push('# ' + t);

        lines.push('# ' + '='.repeat(58));
        ghi('MẪU NHẬP DANH SÁCH THIẾU NHI — TNTT Super App');
        if (lop)  ghi('Lớp: ' + lop + (khoi ? '  ·  Khối: ' + khoi : ''));
        if (this.year) ghi('Niên khoá: ' + this.year.name);
        ghi('Xuất lúc: ' + this.formatDate(this.toDateInput(new Date())));
        ghi('');
        ghi('CÁCH DÙNG');
        ghi('  1. Mở file bằng Excel hoặc Google Sheet');
        ghi('  2. Điền mỗi em một dòng, ngay dưới hàng tiêu đề');
        ghi('  3. Lưu lại dạng CSV UTF-8, rồi bấm nút Nhập trên app');
        ghi('');
        ghi('Mọi dòng bắt đầu bằng dấu # đều được bỏ qua khi nhập,');
        ghi('kể cả dòng ví dụ bên dưới — cứ để nguyên, không cần xoá.');
        ghi('');
        ghi('BẮT BUỘC   : ' + bat + '  (thiếu là bỏ qua dòng đó)');
        ghi('Giới tính  : Nam | Nữ');
        ghi('Ngày sinh  : dd/mm/yyyy   ví dụ 05/09/2017');
        ghi('Tình trạng : ' + this.statusOptions.join(' | '));
        ghi('Khối       : tự suy ra từ Lớp, ghi sai cũng không ảnh hưởng');
        ghi('Lớp        : phải trùng đúng tên đã khai ở màn Khối & Lớp');
        if (dsLop.length) {
            const dong = [];
            for (let i = 0; i < dsLop.length; i += 3) dong.push(dsLop.slice(i, i + 3).join('  |  '));
            dong.forEach((d, i) => ghi('             ' + d));
        } else {
            ghi('             (chưa khai lớp nào — hãy vào Khối & Lớp tạo trước)');
        }
        ghi('');
        ghi('Mã số đã có sẵn thì hồ sơ được CẬP NHẬT, chưa có thì THÊM MỚI.');
        lines.push('# ' + '='.repeat(58));

        // Hàng tiêu đề thật — chính là hàng mà applyImport() dò cột
        lines.push(this.importColumns.map(c => this.csvCell(c.header)).join(','));

        // Dòng ví dụ, gắn # ở ô đầu nên nhập lại cũng không tạo ra em ma
        lines.push(this.importColumns.map((c, i) =>
            this.csvCell((i === 0 ? '#' : '') + (viDu[c.key] || ''))).join(','));

        this.taiFileCSV(lines,
            'Mau_Nhap_Danh_Sach' + (lop ? '_' + this.tenFileAnToan(lop) : '') + '_' + this.todayStamp() + '.csv');

        alert('Lớp này chưa có em nào.\n\n'
            + 'Đã tải về FILE MẪU đúng định dạng'
            + (lop ? ' cho lớp ' + lop : '') + '.\n'
            + 'Điền vào rồi bấm Nhập để đưa danh sách lên.');
    },

    // Tách CSV thủ công (xử lý ô bọc nháy kép, dấu phẩy và xuống dòng nằm bên trong ô)
    parseCSV(text) {
        const rows = [];
        let row = [], field = '', inQuotes = false;
        if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);

        for (let i = 0; i < text.length; i++) {
            const c = text[i];
            if (inQuotes) {
                if (c === '"') {
                    if (text[i + 1] === '"') { field += '"'; i++; }
                    else { inQuotes = false; }
                } else { field += c; }
            } else if (c === '"') {
                inQuotes = true;
            } else if (c === ',') {
                row.push(field); field = '';
            } else if (c === '\n') {
                row.push(field); rows.push(row); row = []; field = '';
            } else if (c !== '\r') {
                field += c;
            }
        }
        if (field !== '' || row.length > 0) { row.push(field); rows.push(row); }

        return rows.filter(r => r.some(cell => cell.trim() !== ''));
    },

    handleImport(event) {
        const file = event.target.files[0];
        event.target.value = ''; // reset để chọn lại đúng file đó vẫn kích hoạt được
        if (!file) return;

        if (!/\.csv$/i.test(file.name)) {
            alert('Hiện chỉ nhận file .csv.\nTrong Excel bạn chọn "Lưu dưới dạng" -> CSV UTF-8 rồi tải lên lại nhé.');
            return;
        }

        const reader = new FileReader();
        reader.onerror = () => alert('Không đọc được file. Vui lòng thử lại.');
        reader.onload = (e) => this.applyImport(e.target.result, file.name);
        reader.readAsText(file, 'UTF-8');
    },

    applyImport(text, fileName) {
        // Dòng bắt đầu bằng # là chú thích trong file mẫu (phần hướng dẫn
        // và dòng ví dụ). Bỏ chúng TRƯỚC khi dò tiêu đề, để người dùng cứ
        // để nguyên phần hướng dẫn mà nhập vẫn chạy đúng.
        const rows = this.parseCSV(text)
            .filter(r => !String(r[0] || "").trim().startsWith("#"));
        if (rows.length < 2) {
            alert('File không có dòng dữ liệu nào.');
            return;
        }

        // Dò cột theo tên tiêu đề, không phụ thuộc thứ tự cột
        const headerRow = rows[0].map(h => this.normalizeText(h));
        const colIndex = {};
        this.importColumns.forEach(c => {
            const i = headerRow.indexOf(this.normalizeText(c.header));
            if (i !== -1) colIndex[c.key] = i;
        });

        if (colIndex.name === undefined || colIndex.code === undefined) {
            alert('File thiếu cột bắt buộc "Mã số" hoặc "Họ và Tên".\nHãy bấm nút Xuất để lấy file mẫu đúng định dạng.');
            return;
        }

        let added = 0, updated = 0, skipped = 0;

        rows.slice(1).forEach(row => {
            const get = key => (colIndex[key] !== undefined ? (row[colIndex[key]] || '').trim() : '');
            const code = get('code');
            const name = get('name');
            if (!code || !name) { skipped++; return; }

            const className = get('className');
            const cls = this.classes.find(c => c.name === className);
            const rawStatus = get('status').toLowerCase();

            const record = {
                code: code,
                name: name,
                holyName:    get('holyName'),
                gender:      this.parseGender(get('gender')),
                birthDate:   this.parseDate(get('birthDate')),
                address:     get('address'),
                fatherName:  get('fatherName'),
                fatherPhone: get('fatherPhone'),
                motherName:  get('motherName'),
                motherPhone: get('motherPhone'),
                status:      this.statusOptions.includes(rawStatus) ? rawStatus : 'đang sinh hoạt',
                className:   className,
                // Ưu tiên khối suy ra từ danh mục lớp: file ghi sai khối cũng không làm lệch dữ liệu
                block:       cls ? cls.block : get('block')
            };

            const existing = this.students.findIndex(s => s.code === code);
            if (existing !== -1) {
                this.students[existing] = Object.assign({}, this.students[existing], record);
                updated++;
            } else {
                this.students.push(Object.assign({ id: Date.now() + Math.floor(Math.random() * 10000) }, record));
                added++;
            }
        });

        this.displayLimit = 20;
        this.logAction('tao', 'students', 'Nhập danh sách từ file ' + fileName,
                       'thêm ' + added + ', cập nhật ' + updated + ', bỏ qua ' + skipped);

        this.importToServer(rows.slice(1), colIndex, fileName);
    },
};
