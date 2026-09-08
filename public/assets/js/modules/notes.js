/* ==========================================================
   NOTES — Lịch của tôi (ghi chú cá nhân + buổi họp được mời)
   Một mảnh của component tnttApp. app.js gộp tất cả các mảnh lại.

   Lịch = ghi chú RIÊNG của mình (personal_notes) + các BUỔI HỌP mà mình
   nằm trong phạm vi nhận (announcements.isMeeting). Không nhân bản dữ liệu:
   buổi họp vẫn sống ở module Thông báo, chỉ được GHÉP vào đây khi hiển thị.
   ========================================================== */
window.TNTT = window.TNTT || {};
window.TNTT.notes = {
    // ==========================================
    // 12. LỊCH CÁ NHÂN
    // ==========================================
    notes: [],                 // máy chủ nạp qua loadData()
    showNoteModal: false,
    isEditingNote: false,
    noteForm: { id: null, title: '', note: '', date: '', time: '', allDay: false },

    openNotes() {
        this.changeModule('notes');
    },

    // ---- Buổi họp mà mình được mời (ghép từ Thông báo) ----
    get myMeetings() {
        return this.announcements.filter(a =>
            a.isMeeting && a.meetingAt && a.status === 'đã phát'
            && !this.isAnnouncementExpired(a) && this.matchesAudience(a));
    },

    // ---- Gộp mọi mục thành một dòng thời gian thống nhất ----
    // kind: 'note' | 'meeting'
    get agendaItems() {
        const items = [];
        this.notes.forEach(n => items.push({
            kind: 'note', id: n.id, title: n.title, desc: n.note || '',
            at: n.remindAt, allDay: n.allDay, done: n.done, ref: n
        }));
        this.myMeetings.forEach(a => items.push({
            kind: 'meeting', id: a.id, title: a.title, desc: a.body || '',
            at: a.meetingAt, allDay: false, place: a.meetingPlace || '',
            rsvp: a.myRsvp || '', by: a.createdBy || '', ref: a
        }));
        return items.sort((x, y) => x.at.localeCompare(y.at));
    },

    // ---- Nhóm theo ngày cho màn agenda ----
    get notesByDay() {
        const groups = [];
        this.agendaItems.forEach(it => {
            const day = it.at.slice(0, 10);
            let g = groups.find(x => x.day === day);
            if (!g) { g = { day: day, label: this.dayLabel(day), items: [] }; groups.push(g); }
            g.items.push(it);
        });
        return groups;
    },

    itemTime(it) {
        return it.allDay ? 'Cả ngày' : it.at.slice(11, 16);
    },

    // Quá hạn = đã qua giờ mà việc chưa xong (chỉ tính ghi chú)
    isItemOverdue(it) {
        if (it.kind !== 'note' || it.done) return false;
        return it.at < this.nowStamp();
    },

    // 'YYYY-MM-DD HH:MM' của lúc này — để so sánh chuỗi cho gọn
    nowStamp() {
        const d = new Date();
        const p = n => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate())
             + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
    },

    // ---- Việc SẮP TỚI (cho "Việc cần làm" + chấm đỏ trên icon) ----
    // Ghi chú chưa xong quá hạn hoặc trong 2 ngày tới; buổi họp chưa từ
    // chối, trong 2 ngày tới hoặc đang tới giờ.
    get upcomingAgenda() {
        const now = this.nowStamp();
        const hanChot = (() => {
            const d = new Date(); d.setDate(d.getDate() + 2);
            const p = n => String(n).padStart(2, '0');
            return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + ' 23:59';
        })();
        return this.agendaItems.filter(it => {
            if (it.kind === 'note') return !it.done && it.at <= hanChot;
            return it.rsvp !== 'không tham gia' && it.at >= now.slice(0, 10) + ' 00:00' && it.at <= hanChot;
        });
    },

    // Dòng "việc cần làm" sinh từ lịch cá nhân
    get noteTasks() {
        return this.upcomingAgenda.map(it => {
            const overdue = this.isItemOverdue(it);
            return {
                key: it.kind + '-' + it.id,
                icon: it.kind === 'meeting' ? 'users' : (overdue ? 'alert-triangle' : 'calendar-check'),
                cls: it.kind === 'meeting'
                    ? 'bg-teal-50 text-teal-600 border-teal-100'
                    : (overdue ? 'bg-rose-50 text-rose-600 border-rose-100'
                               : 'bg-blue-50 text-blue-600 border-blue-100'),
                text: (it.kind === 'meeting' ? 'Họp: ' : '') + it.title,
                detail: this.itemDetailLine(it),
                go: 'notes'
            };
        });
    },

    itemDetailLine(it) {
        const when = this.dayLabel(it.at.slice(0, 10)) + (it.allDay ? '' : ' · ' + it.at.slice(11, 16));
        if (it.kind === 'meeting') return when + (it.place ? ' · ' + it.place : '');
        return this.isItemOverdue(it) ? 'quá hạn · ' + when : when;
    },

    // Chấm đỏ trên icon module Lịch
    get notesBadgeCount() {
        return this.upcomingAgenda.length;
    },

    // Vài việc/họp gần nhất từ hôm nay trở đi — cho thẻ "Sắp tới" ở Trang chủ
    get homeUpcoming() {
        const today = this.nowStamp().slice(0, 10);
        return this.agendaItems
            .filter(it => it.at.slice(0, 10) >= today
                && !(it.kind === 'note' && it.done)
                && it.rsvp !== 'không tham gia')
            .slice(0, 5);
    },

    // ---- Thêm / sửa ----
    openCreateNote() {
        const d = new Date();
        const p = n => String(n).padStart(2, '0');
        const gioTron = p((d.getHours() + 1) % 24) + ':00';
        this.noteForm = {
            id: null, title: '', note: '',
            date: this.toDateInput(d), time: gioTron, allDay: false
        };
        this.isEditingNote = false;
        this.showNoteModal = true;
    },

    openEditNote(n) {
        this.noteForm = {
            id: n.id, title: n.title, note: n.note || '',
            date: n.remindAt.slice(0, 10), time: n.remindAt.slice(11, 16),
            allDay: n.allDay
        };
        this.isEditingNote = true;
        this.showNoteModal = true;
    },

    saveNote() {
        const f = this.noteForm;
        if (!f.title.trim()) { window.TNTT.toast.warning('Vui lòng nhập tên việc.'); return; }
        if (!f.date)         { window.TNTT.toast.warning('Vui lòng chọn ngày nhắc.'); return; }

        const remindAt = f.date + ' ' + (f.allDay ? '07:00' : (f.time || '07:00'));
        const payload = {
            id: this.isEditingNote ? f.id : 0,
            title: f.title.trim(), note: f.note.trim(),
            date: f.date, time: f.time, allDay: f.allDay
        };

        if (this.isEditingNote) {
            const i = this.notes.findIndex(x => x.id === f.id);
            if (i !== -1) this.notes[i] = { id: f.id, title: f.title.trim(), note: f.note.trim(), remindAt: remindAt, allDay: f.allDay, done: this.notes[i].done };
        } else {
            this.notes.push({ id: 'tmp-' + Date.now(), title: f.title.trim(), note: f.note.trim(), remindAt: remindAt, allDay: f.allDay, done: false });
        }
        this.showNoteModal = false;

        this.save('notes', 'save', payload).then(r => {
            if (r && r.ok) {
                // Gắn id thật cho bản mới + chốt lại giờ nhắc theo máy chủ
                const target = this.isEditingNote
                    ? this.notes.find(x => x.id === f.id)
                    : this.notes.find(x => String(x.id).startsWith('tmp-'));
                if (target) { target.id = r.id; if (r.remindAt) target.remindAt = r.remindAt; }
            }
        });
    },

    toggleNote(n) {
        n.done = !n.done;
        this.save('notes', 'toggle', { id: n.id });
    },

    deleteNote(n) {
        if (!confirm('Xoá việc "' + n.title + '"?')) return;
        this.notes = this.notes.filter(x => x.id !== n.id);
        this.showNoteModal = false;
        this.save('notes', 'delete', { id: n.id });
    },

    // ---- RSVP buổi họp (bấm ngay trong lịch) ----
    rsvpMeeting(a, status) {
        if (a.myRsvp === status) return;
        // Cập nhật số đếm tại chỗ cho mượt
        if (a.myRsvp === 'tham gia') a.rsvpYes = Math.max(0, (a.rsvpYes || 0) - 1);
        if (a.myRsvp === 'không tham gia') a.rsvpNo = Math.max(0, (a.rsvpNo || 0) - 1);
        if (status === 'tham gia') a.rsvpYes = (a.rsvpYes || 0) + 1;
        if (status === 'không tham gia') a.rsvpNo = (a.rsvpNo || 0) + 1;
        a.myRsvp = status;
        this.save('announcements', 'rsvp', { id: a.id, status: status });
    },

    rsvpChipClass(status) {
        if (status === 'tham gia')        return 'bg-emerald-50 text-emerald-600 border-emerald-100';
        if (status === 'không tham gia')  return 'bg-rose-50 text-rose-600 border-rose-100';
        return 'bg-slate-50 text-slate-400 border-slate-200';
    },
};
