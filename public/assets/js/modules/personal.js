/* ==========================================================
   PERSONAL — Hồ sơ cá nhân + lịch sử phục vụ (T2.4 + T3.3)

   Mở rộng "thẻ cá nhân" trong module_profile.php (màn Cài đặt):
   bổ sung ô Thâm niên (năm lên GLV) + danh sách lịch sử phục vụ
   cùng modal cập nhật. Mọi method/state ở đây GỘP vào component
   tnttApp bởi app.js, nên từ template truy cập qua `this.xxx`
   chứ không phải qua component lồng.

   Phân quyền: getProfile mở ở 'org.view' (xem của mình), 4 write
   (updateGlvYear, saveServiceHistory, deleteServiceHistory, changeMemberStatus)
   cần 'org.edit' (BĐH/Quản trị) — backend ở public/api/personal.php
   đã enforce; frontend chỉ ẩn nút nếu biết mình không có quyền
   (canManageOrg đã có sẵn ở core.js).

   Quy ước service history (xem PersonalService::getServiceHistory):
     - is_auto = 0  → dòng nhập tay, sửa/xoá được
     - is_auto = 1  → dòng tự sinh từ updateGlvYear, chỉ xem (khoá)
   ========================================================== */

window.TNTT = window.TNTT || {};
window.TNTT.personal = {

    // ---------- STATE ----------
    // profile đầy đủ (đã có user rồi, nhưng thiếu glvYear/birthDate/yearsOfService
    // nên fetch thêm qua getProfile)
    profile: null,
    serviceHistory: [],

    // Modal: cập nhật năm lên GLV
    showGlvModal: false,
    glvYearForm: { year: null },
    busyGlv: false,

    // Modal: danh sách + sửa/xoá lịch sử
    showHistoryModal: false,
    editingHistoryYear: null,    // null = thêm mới, 'YYYY-YYYY' = sửa
    busyHistory: false,
    historyForm: { yearLabel: '', roleCode: 'glv', blockId: null, classId: null },

    // Danh sách 5 năm học gần nhất (dropdown chọn năm)
    get historyYearOptions() {
        const now = window.TNTT.vn.now();
        const currentStart = now.month >= 8 ? now.year : now.year - 1;
        const years = [];
        for (let i = 4; i >= 0; i--) {
            const y = currentStart - i;
            years.push(y + '-' + (y + 1));
        }
        return years;
    },

    // Lọc lớp theo blockId
    classesByBlock(blockId) {
        if (!blockId) return [];
        return (window.TNTT_BOOT?.classes || []).filter(c => c.blockId === blockId);
    },

    // ---------- LIFECYCLE ----------
    // Gọi từ module_profile.php x-init="loadProfile()" khi mở tab Cá nhân.
    async loadProfile() {
        const r = await this.api('personal', 'getProfile', {}, 'GET');
        if (r && r.ok && r.data) {
            this.profile = r.data.member;
            this.serviceHistory = r.data.history || [];
        }
    },

    // ---------- HELPERS ----------
    get yearsOfService() {
        // null khi chưa lên GLV — giao diện hiển thị "—"
        if (!this.profile || !this.profile.glvYear) return null;
        const year = window.TNTT.vn.now().year;
        return Math.max(0, year - this.profile.glvYear);
    },

    // is_auto = 0 (nhập tay) mới sửa/xoá được; is_auto = 1 khoá
    canEditHistoryEntry(entry) {
        return entry && entry.is_auto === 0;
    },

    // Nhãn vai trò cho dòng lịch sử (giống getRoleLabel trong các mảnh khác)
    historyRoleLabel(roleCode) {
        const map = {
            admin: 'Quản trị',
            bdh: 'BĐH',
            truong_khoi: 'Trưởng khối',
            glv_chu_nhiem: 'GLV CN',
            glv: 'GLV',
            du_bi: 'Dự bị',
            thu_thu: 'Thủ thư'
        };
        return map[roleCode] || roleCode || '';
    },

    // ---------- ACTIONS: GLV YEAR ----------
    openGlvModal() {
        if (!this.canManageOrg) {
            window.TNTT.toast.warning('Chỉ Ban Điều Hành mới đổi được năm lên GLV.');
            return;
        }
        this.glvYearForm.year = this.profile?.glvYear ?? window.TNTT.vn.now().year;
        this.showGlvModal = true;
    },

    async saveGlvYear() {
        if (!this.profile) return;
        const year = parseInt(this.glvYearForm.year, 10);
        if (!year || year < 1900 || year > window.TNTT.vn.now().year) {
            window.TNTT.toast.warning('Vui lòng nhập năm hợp lệ.');
            return;
        }
        this.busyGlv = true;
        const r = await this.save('personal', 'updateGlvYear', {
            member_id: this.profile.id,
            glv_year: year
        });
        this.busyGlv = false;
        if (r.ok) {
            this.showGlvModal = false;
            window.TNTT.toast.success('Đã cập nhật năm lên GLV.');
            await this.loadProfile();
        }
    },

    // Xoá năm GLV (set null) — chỉ BĐH
    async clearGlvYear() {
        if (!this.profile) return;
        if (!await window.TNTT.toast.confirm('Xoá năm lên GLV của thành viên này?', { danger: true, confirmText: 'Xoá' })) return;
        this.busyGlv = true;
        const r = await this.save('personal', 'updateGlvYear', {
            member_id: this.profile.id,
            glv_year: null
        });
        this.busyGlv = false;
        if (r.ok) {
            this.showGlvModal = false;
            window.TNTT.toast.success('Đã xoá năm lên GLV.');
            await this.loadProfile();
        }
    },

    // ---------- ACTIONS: SERVICE HISTORY ----------
    openHistoryModal() {
        this.showHistoryModal = true;
    },

    // Mở form sửa — copy entry hiện có vào historyForm
    startEditHistory(entry) {
        if (!this.canEditHistoryEntry(entry)) return;
        this.editingHistoryYear = entry.year_label;
        this.historyForm = {
            yearLabel: entry.year_label,
            roleCode: entry.role_code || 'glv',
            note: entry.note || ''
        };
    },

    // Mở form thêm mới — reset
    startAddHistory() {
        if (!this.canManageOrg) {
            window.TNTT.toast.warning('Chỉ Ban Điều Hành mới thêm được dòng lịch sử.');
            return;
        }
        this.editingHistoryYear = null;
        // Gợi ý năm học kết thúc gần nhất
        const now = window.TNTT.vn.now();
        const y = now.month >= 8 ? now.year : now.year - 1;
        this.historyForm = { yearLabel: y + '-' + (y + 1), roleCode: 'glv', blockId: null, classId: null };
    },

    async saveHistoryEntry() {
        if (!this.profile) {
            window.TNTT.toast.error('Chưa tải được hồ sơ thành viên.');
            return;
        }
        const yearLabel = (this.historyForm.yearLabel || '').trim();
        if (!/^\d{4}-\d{4}$/.test(yearLabel)) {
            window.TNTT.toast.warning('Vui lòng chọn năm học.');
            return;
        }
        this.busyHistory = true;
        console.debug('[personal] saveHistoryEntry payload', {
            member_id: this.profile.id,
            year_label: yearLabel,
            role_code: this.historyForm.roleCode,
            block_id: this.historyForm.blockId,
            class_id: this.historyForm.classId
        });
        try {
            const r = await this.save('personal', 'saveServiceHistory', {
                member_id: this.profile.id,
                year_label: yearLabel,
                role_code: this.historyForm.roleCode,
                block_id: this.historyForm.blockId,
                class_id: this.historyForm.classId
            });
            if (r && r.ok) {
                window.TNTT.toast.success(this.editingHistoryYear ? 'Đã cập nhật dòng lịch sử.' : 'Đã thêm dòng lịch sử.');
                this.editingHistoryYear = null;
                this.historyForm = { yearLabel: '', roleCode: 'glv', blockId: null, classId: null };
                await this.loadProfile();
            } else {
                window.TNTT.toast.error('Lưu thất bại: ' + (r?.error?.message || 'không rõ lỗi'));
            }
        } catch (e) {
            console.error('[personal] saveHistoryEntry exception', e);
            window.TNTT.toast.error('Lỗi khi lưu: ' + (e?.message || e));
        } finally {
            this.busyHistory = false;
        }
    },

    async deleteHistoryEntry(entry) {
        if (!this.canEditHistoryEntry(entry)) return;
        if (!await window.TNTT.toast.confirm('Xoá dòng lịch sử năm ' + entry.year_label + '?', { danger: true, confirmText: 'Xoá' })) return;
        const r = await this.save('personal', 'deleteServiceHistory', {
            member_id: this.profile.id,
            year_label: entry.year_label
        });
        if (r.ok) {
            window.TNTT.toast.success('Đã xoá dòng lịch sử.');
            await this.loadProfile();
        }
    },

    cancelHistoryForm() {
        this.editingHistoryYear = null;
        this.historyForm = { yearLabel: '', roleCode: 'glv', blockId: null, classId: null };
    }
};
