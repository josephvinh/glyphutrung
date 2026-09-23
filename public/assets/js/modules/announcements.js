/* ==========================================================
   ANNOUNCEMENTS — Thông báo
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.announcements = {
    // ==========================================
    // 7. DATA: THÔNG BÁO
    // ==========================================
    announcements: [],   // máy chủ nạp qua loadData()

    readAnnouncements: [],   // sau này lưu theo từng tài khoản dưới DB
    showAnnouncementModal: false,
    isEditingAnnouncement: false,
    announcementForm: { id: null, title: '', body: '', level: 'thường', audienceType: 'toàn đoàn', audienceValue: '', status: 'đã phát', publishedAt: '', expiresAt: '', createdBy: '', isMeeting: false, meetingAt: '', meetingPlace: '' },
    announcementLevels: ['thường', 'quan trọng', 'khẩn'],

    // ---- Kết quả họp (người phát xem) ----
    showMeetingResult: false,
    meetingResultBusy: false,
    meetingResult: { title: '', yes: 0, no: 0, pending: 0, total: 0, rows: [] },

    get canManageAnnouncements() {
        return this.canEditModule('announcements');
    },

    // Trưởng khối chỉ sửa/xóa được thông báo của khối MÌNH LÀM TRƯỞNG
    // (gồm cả khối kiêm nhiệm); thông báo toàn đoàn của BĐH thì chỉ được đọc.
    canEditAnnouncement(a) {
        if (['admin', 'bdh'].includes(this.user.role)) return true;
        if (this.user.role !== 'truong_khoi') return false;
        return a.audienceType === 'khối' && this.myHeadBlocks.includes(a.audienceValue);
    },

    isAnnouncementExpired(a) {
        if (!a.expiresAt) return false;
        return a.expiresAt < this.toDateInput(new Date());
    },

    // Đối tượng nhận: kiêm nhiệm thấy thông báo cho MỌI khối/lớp mình phụ trách.
    matchesAudience(a) {
        if (['admin', 'bdh'].includes(this.user.role)) return true;
        if (a.audienceType === 'toàn đoàn') return true;
        if (a.audienceType === 'khối') return this.myBlocks.includes(a.audienceValue);
        if (a.audienceType === 'lớp')  return this.myClasses.includes(a.audienceValue);
        return false;
    },

    // Thông báo mà người đăng nhập được đọc: đã phát, chưa hết hạn, đúng đối tượng
    get visibleAnnouncements() {
        return this.announcements
            .filter(a => a.status === 'đã phát' && !this.isAnnouncementExpired(a) && this.matchesAudience(a))
            .sort((a, b) => b.publishedAt.localeCompare(a.publishedAt));
    },

    // BĐH thấy hết kể cả bản nháp và bản đã hết hạn.
    // Trưởng khối chỉ thấy thông báo khối mình + thông báo toàn đoàn.
    get manageableAnnouncements() {
        const list = ['admin', 'bdh'].includes(this.user.role)
            ? this.announcements
            : this.announcements.filter(a => this.canEditAnnouncement(a)
                || (a.status === 'đã phát' && !this.isAnnouncementExpired(a) && this.matchesAudience(a)));
        return [...list].sort((a, b) => {
            if (a.status !== b.status) return a.status === 'nháp' ? -1 : 1;
            return (b.publishedAt || '').localeCompare(a.publishedAt || '');
        });
    },

    get unreadAnnouncementCount() {
        return this.visibleAnnouncements.filter(a => !this.readAnnouncements.includes(a.id)).length;
    },

    get latestAnnouncement() {
        return this.visibleAnnouncements.length ? this.visibleAnnouncements[0] : null;
    },

    openAnnouncements() {
        this.changeModule('announcements');
    },

    markAnnouncementRead(a) {
        if (this.readAnnouncements.includes(a.id)) return;
        this.readAnnouncements.push(a.id);
        this.save('announcements', 'read', { id: a.id });
    },

    markAllAnnouncementsRead() {
        this.visibleAnnouncements.forEach(a => {
            if (!this.readAnnouncements.includes(a.id)) this.readAnnouncements.push(a.id);
        });
        this.save('announcements', 'readall', {});
    },

    openCreateAnnouncement() {
        // Trưởng khối chỉ gửi cho khối mình; kiêm nhiều khối thì mặc định
        // khối đầu, vẫn đổi được sang khối khác mình làm trưởng.
        const locked = this.user.role === 'truong_khoi';
        this.announcementForm = {
            id: null, title: '', body: '', level: 'thường',
            audienceType: locked ? 'khối' : 'toàn đoàn',
            audienceValue: locked ? (this.myHeadBlocks[0] || '') : '',
            status: 'đã phát', publishedAt: '', expiresAt: '', createdBy: this.user.fullName,
            isMeeting: false, meetingAt: '', meetingPlace: ''
        };
        this.isEditingAnnouncement = false;
        this.showAnnouncementModal = true;
    },

    // Trưởng khối bị khoá KIỂU đối tượng (chỉ 'khối'), nhưng vẫn chọn được
    // trong các khối mình làm trưởng.
    get audienceLocked() {
        return this.user.role === 'truong_khoi';
    },

    // Khối hiện trong ô "Chọn khối" khi soạn: BĐH/Quản trị mọi khối,
    // trưởng khối chỉ khối mình làm trưởng (có thể nhiều khi kiêm nhiệm).
    get audienceBlockChoices() {
        return ['admin', 'bdh'].includes(this.user.role) ? this.blocks : this.myHeadBlocks;
    },

    openEditAnnouncement(a) {
        this.announcementForm = JSON.parse(JSON.stringify(a));
        // Ô datetime-local cần dạng 'YYYY-MM-DDTHH:MM'
        this.announcementForm.isMeeting = !!a.isMeeting;
        this.announcementForm.meetingAt = (a.meetingAt || '').replace(' ', 'T');
        this.announcementForm.meetingPlace = a.meetingPlace || '';
        this.isEditingAnnouncement = true;
        this.showAnnouncementModal = true;
    },

    // Người phát mở xem ai tham gia / không / chưa trả lời
    async openMeetingResult(a) {
        this.meetingResult = { title: a.title, yes: 0, no: 0, pending: 0, total: 0, rows: [] };
        this.meetingResultBusy = true;
        this.showMeetingResult = true;
        const r = await this.api('announcements', 'rsvpList', { id: a.id });
        this.meetingResultBusy = false;
        if (r && r.ok) {
            this.meetingResult = { title: a.title, yes: r.yes, no: r.no, pending: r.pending, total: r.total, rows: r.rows };
        } else {
            window.TNTT.toast.error((r && r.error) || 'Không tải được kết quả họp.');
            this.showMeetingResult = false;
        }
    },

    rsvpRowClass(status) {
        if (status === 'tham gia')       return 'text-emerald-600';
        if (status === 'không tham gia') return 'text-rose-500';
        return 'text-slate-400';
    },

    saveAnnouncement() {
        const f = this.announcementForm;
        if (!f.title.trim() || !f.body.trim()) {
            window.TNTT.toast.warning('Vui lòng nhập tiêu đề và nội dung thông báo!');
            return;
        }
        if (f.audienceType !== 'toàn đoàn' && !f.audienceValue) {
            window.TNTT.toast.warning('Vui lòng chọn ' + (f.audienceType === 'khối' ? 'khối' : 'lớp') + ' nhận thông báo!');
            return;
        }
        if (f.isMeeting && !f.meetingAt) {
            window.TNTT.toast.warning('Buổi họp cần chọn ngày và giờ họp!');
            return;
        }
        f.title = f.title.trim();
        f.body = f.body.trim();
        if (f.audienceType === 'toàn đoàn') f.audienceValue = '';
        // Chuẩn hoá giờ họp về 'YYYY-MM-DD HH:MM' (khớp máy chủ + màn lịch)
        if (f.isMeeting) f.meetingAt = (f.meetingAt || '').replace('T', ' ').slice(0, 16);
        else { f.meetingAt = ''; f.meetingPlace = ''; }
        // Bản mới cần sẵn số RSVP để màn lịch/thông báo hiển thị đúng
        if (!this.isEditingAnnouncement) { f.rsvpYes = 0; f.rsvpNo = 0; f.myRsvp = ''; }
        // Chuyển từ nháp sang phát thì mới đóng dấu thời gian
        if (f.status === 'đã phát' && !f.publishedAt) f.publishedAt = this.timestamp();
        if (f.status === 'nháp') f.publishedAt = '';

        if (this.isEditingAnnouncement) {
            const i = this.announcements.findIndex(x => x.id === f.id);
            if (i !== -1) this.announcements[i] = f;
        } else {
            f.id = Date.now();
            this.announcements.push(f);
        }
        this.logAction(this.isEditingAnnouncement ? 'sua' : 'tao', 'announcements',
                       (this.isEditingAnnouncement ? 'Sửa' : 'Phát') + ' thông báo "' + f.title + '"',
                       this.audienceLabel(f) + ' · ' + f.status);
        this.showAnnouncementModal = false;
        this.save('announcements', 'save', {
            id: this.isEditingAnnouncement ? f.id : 0,
            title: f.title, body: f.body, level: f.level,
            audienceType: f.audienceType, audienceValue: f.audienceValue,
            status: f.status, expiresAt: f.expiresAt,
            isMeeting: f.isMeeting, meetingAt: f.meetingAt, meetingPlace: f.meetingPlace
        }).then(r => { 
            if (!r || !r.ok) this.loadData();
            else if (r.id) f.id = r.id; 
        });
    },

    async deleteAnnouncement(id) {
        const a = this.announcements.find(x => x.id === id);
        if (!a) return;
        if (await window.TNTT.toast.confirm('Xóa thông báo "' + a.title + '"?', { danger: true, confirmText: 'Xoá' })) {
            this.announcements = this.announcements.filter(x => x.id !== id);
            this.logAction('xoa', 'announcements', 'Xóa thông báo "' + a.title + '"', '');
            this.save('announcements', 'delete', { id: id }).then(r => { if (!r || !r.ok) this.loadData(); });
        }
    },

    // Thu hồi = đưa về nháp, ai đã đọc rồi thì cũng không thấy nữa
    toggleAnnouncementStatus(a) {
        if (a.status === 'đã phát') {
            a.status = 'nháp';
            a.publishedAt = '';
            this.logAction('sua', 'announcements', 'Thu hồi thông báo "' + a.title + '"', 'về bản nháp');
        } else {
            a.status = 'đã phát';
            a.publishedAt = this.timestamp();
            this.logAction('tao', 'announcements', 'Phát thông báo "' + a.title + '"', this.audienceLabel(a));
        }
        this.save('announcements', 'toggle', { id: a.id }).then(r => { if (!r || !r.ok) this.loadData(); });
    },

    announcementLevelClass(level) {
        if (level === 'khẩn')       return 'bg-rose-50 text-rose-600 border-rose-100';
        if (level === 'quan trọng') return 'bg-amber-50 text-amber-600 border-amber-100';
        return 'bg-blue-50 text-blue-600 border-blue-100';
    },

    audienceLabel(a) {
        if (a.audienceType === 'toàn đoàn') return 'Toàn đoàn';
        return (a.audienceType === 'khối' ? 'Khối ' : 'Lớp ') + a.audienceValue;
    },
};
