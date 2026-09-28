/* ==========================================================
   BIRTHDAYS — Sinh nhật
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.birthdays = {
    // ==========================================
    // 6. DATA: SINH NHẬT
    //
    // Không lưu gì thêm, hoàn toàn suy ra từ birthDate của danh sách
    // thiếu nhi. Chỉ tính các em đang sinh hoạt, trong phạm vi quyền.
    // ==========================================
    birthdayMonth: new Date().getMonth() + 1,

    openBirthdays() {
        this.birthdayMonth = new Date().getMonth() + 1;
        this.changeModule('birthdays');
    },

    shiftBirthdayMonth(delta) {
        this.birthdayMonth = ((this.birthdayMonth - 1 + delta + 12) % 12) + 1;
    },

    birthDay(student) {
        return student.birthDate ? Number(student.birthDate.split('-')[2]) : 0;
    },

    birthMonth(student) {
        return student.birthDate ? Number(student.birthDate.split('-')[1]) : 0;
    },

    // Năm nay em tròn bao nhiêu tuổi
    turningAge(student) {
        if (!student.birthDate) return '';
        return new Date().getFullYear() - Number(student.birthDate.split('-')[0]);
    },

    isBirthdayToday(student) {
        const now = new Date();
        return this.birthMonth(student) === now.getMonth() + 1 && this.birthDay(student) === now.getDate();
    },

    // Sĩ số lớp đang phụ trách, dùng cho thẻ thống kê ngoài Trang chủ
    get myClassSize() {
        const classes = this.myClasses;
        return this.students.filter(s => classes.includes(s.className) && s.status === 'đang sinh hoạt').length;
    },

    // Lọc theo nhóm: tất cả | thiếu nhi | giáo lý viên
    birthdayKind: 'all',

    // Gộp thiếu nhi và GLV về một hình dạng chung để dùng lại toàn bộ
    // phép tính ngày tháng bên dưới. Trường `kind` giữ lại danh tính
    // để giao diện tô màu và gắn nhãn khác nhau.
    //
    // Phạm vi khác nhau có chủ ý: thiếu nhi theo quyền của người xem
    // (GLV chỉ thấy lớp mình), còn GLV thì cả đoàn đều thấy nhau —
    // đây là danh sách đồng nghiệp, mừng sinh nhật nhau là chuyện chung.
    get birthdayPool() {
        const students = this.accessibleStudents
            .filter(s => s.status === 'đang sinh hoạt' && s.birthDate)
            .map(s => ({
                kind: 'student', key: 's' + s.id,
                holyName: s.holyName, name: s.name, birthDate: s.birthDate,
                sub: s.className, phone: s.motherPhone, phoneLabel: 'Gọi mẹ'
            }));

        const members = this.members
            .filter(m => m.status === 'đang phục vụ' && m.birthDate)
            .map(m => ({
                kind: 'member', key: 'm' + m.id,
                holyName: m.holyName, name: m.fullName, birthDate: m.birthDate,
                sub: (m.title || this.roleLabel(m.role)) + (m.className ? ' · ' + m.className : ''),
                phone: m.phone, phoneLabel: 'Gọi GLV'
            }));

        return students.concat(members);
    },

    // Danh sách theo tháng có áp bộ lọc nhóm. Riêng "hôm nay" và chấm đỏ
    // ngoài App Center thì KHÔNG lọc — lọc rồi thì con số nhắc việc sai.
    get filteredBirthdayPool() {
        if (this.birthdayKind === 'student') return this.birthdayPool.filter(p => p.kind === 'student');
        if (this.birthdayKind === 'member')  return this.birthdayPool.filter(p => p.kind === 'member');
        return this.birthdayPool;
    },

    get birthdaysTodayStudents() {
        return this.birthdaysToday.filter(p => p.kind === 'student');
    },

    get birthdaysTodayMembers() {
        return this.birthdaysToday.filter(p => p.kind === 'member');
    },

    // Đếm riêng từng nhóm cho các chip lọc
    get birthdayCounts() {
        const inMonth = p => this.birthMonth(p) === this.birthdayMonth;
        const students = this.accessibleStudents
            .filter(s => s.status === 'đang sinh hoạt' && s.birthDate).filter(inMonth).length;
        const members = this.members
            .filter(m => m.status === 'đang phục vụ' && m.birthDate).filter(inMonth).length;
        return { student: students, member: members, all: students + members };
    },

    birthdayChipClass(kind) {
        return kind === 'member'
            ? 'bg-blue-50 text-blue-600 border-blue-100'
            : 'bg-rose-50 text-rose-600 border-rose-100';
    },

    birthdayKindLabel(kind) {
        return kind === 'member' ? 'GLV' : 'Thiếu nhi';
    },

    // Nhắc BĐH bổ sung: GLV thiếu ngày sinh thì không bao giờ hiện ra
    get membersWithoutBirthday() {
        return this.members.filter(m => m.status === 'đang phục vụ' && !m.birthDate).length;
    },

    get birthdaysInMonth() {
        return this.filteredBirthdayPool
            .filter(s => this.birthMonth(s) === this.birthdayMonth)
            .sort((a, b) => this.birthDay(a) - this.birthDay(b));
    },

    get birthdaysToday() {
        return this.birthdayPool.filter(s => this.isBirthdayToday(s));
    },

    // Sắp tới trong 7 ngày, tính cả trường hợp vắt qua tháng sau
    get upcomingBirthdays() {
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        return this.filteredBirthdayPool
            .map(s => {
                let next = new Date(now.getFullYear(), this.birthMonth(s) - 1, this.birthDay(s));
                if (next < today) next = new Date(now.getFullYear() + 1, this.birthMonth(s) - 1, this.birthDay(s));
                return { student: s, days: Math.round((next - today) / 86400000) };
            })
            .filter(x => x.days > 0 && x.days <= 7)
            .sort((a, b) => a.days - b.days);
    },
};
