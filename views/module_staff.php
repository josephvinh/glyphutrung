<!-- ==========================================================
     MÀN HÌNH NHÂN SỰ

     Trước đây là tab thứ hai bên trong màn Khối & Lớp. Tách ra
     thành module riêng để vào thẳng từ Trang chủ, và để chấm nhắc
     "có người chờ duyệt" hiện được ngay ngoài lưới chức năng.
     ========================================================== -->
<div x-show="currentModule === 'staff'" style="display: none;" class="module-panel pt-6 pb-10 relative">

    <!-- THANH ĐIỀU HƯỚNG -->
    <div class="flex items-center mb-5">
        <button aria-label="Quay lại trang chủ" @click="changeModule('dashboard')" class="tap-safe w-10 h-10 shrink-0 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-4">
            <i data-lucide="chevron-left" class="w-6 h-6 text-slate-600"></i>
        </button>
        <h2 class="text-xl font-black text-slate-800 tracking-tight">Nhân Sự</h2>
        <span x-show="pendingMembers.length > 0" style="display: none;"
              class="ml-3 px-2.5 py-1 rounded-full bg-rose-100 text-rose-700 text-micro font-black"
              x-text="pendingMembers.length + ' chờ duyệt'"></span>
    </div>

    <!-- Cấp dưới BĐH chỉ được xem -->
    <div x-show="!canManageOrg" style="display: none;" class="bg-slate-100 border border-slate-200 rounded-2xl p-3 mb-4 flex items-start gap-2.5">
        <i data-lucide="eye" class="w-4 h-4 text-slate-500 shrink-0 mt-0.5"></i>
        <p class="text-micro text-slate-600 leading-snug">
            Bạn đang ở chế độ <span class="font-bold">chỉ xem</span>. Chỉ Ban Điều Hành mới thay đổi được nhân sự.
        </p>
    </div>



    <!-- ==========================================================
         HÀNG CHỜ DUYỆT — tài khoản tự đăng ký
         Đặt trên cùng vì đây là việc cần xử lý, không phải danh sách
         để ngắm. Duyệt là phải phân công lớp luôn.
         ========================================================== -->
    <div x-show="pendingMembers.length > 0" style="display: none;"
         class="bg-amber-50 border border-amber-200 rounded-card p-5 mb-4">
        <div class="flex items-center gap-2 mb-4">
            <div class="tap-safe w-8 h-8 shrink-0 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center">
                <i data-lucide="user-plus" class="w-4 h-4"></i>
            </div>
            <div>
                <h3 class="text-sm font-black text-amber-800 leading-tight">
                    <span x-text="pendingMembers.length"></span> người đăng ký chờ duyệt
                </h3>
                <p class="text-micro text-amber-600">Chưa vào được app cho tới khi bạn duyệt</p>
            </div>
        </div>

        <div class="space-y-2.5">
            <template x-for="m in pendingMembers" :key="m.id">
                <div class="bg-white rounded-field p-4 border border-amber-100">
                    <div class="flex items-start gap-3 mb-3">
                        <div class="w-10 h-10 shrink-0 rounded-2xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-600">
                            <i data-lucide="user" class="w-5 h-5"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-black text-slate-800 leading-snug">
                                <span class="font-normal text-slate-500" x-text="m.holyName"></span>
                                <span x-text="m.fullName"></span>
                            </p>
                            <p class="text-micro font-medium text-slate-500 mt-0.5">
                                <span x-text="m.code"></span>
                                <span class="text-slate-300 mx-1">·</span>
                                <span x-text="m.phone"></span>
                            </p>
                        </div>
                    </div>

                    <div x-show="m.registerNote" style="display: none;"
                         class="bg-slate-50 rounded-xl p-3 mb-3 flex items-start gap-2">
                        <i data-lucide="message-square" class="w-3.5 h-3.5 text-slate-400 shrink-0 mt-0.5"></i>
                        <p class="text-xs text-slate-600 leading-snug italic" x-text="m.registerNote"></p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <button @click="rejectMember(m)"
                                class="py-2.5 bg-rose-50 text-rose-600 rounded-xl font-bold text-xs border border-rose-100 active:scale-95 transition-transform flex items-center justify-center gap-1.5">
                            <i data-lucide="x" class="w-3.5 h-3.5"></i> Từ chối
                        </button>
                        <button @click="openApproveForm(m)"
                                class="py-2.5 bg-emerald-600 text-white rounded-xl font-bold text-xs shadow-md shadow-emerald-200 active:scale-95 transition-transform flex items-center justify-center gap-1.5">
                            <i data-lucide="check" class="w-3.5 h-3.5"></i> Duyệt
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <button x-show="canManageOrg" @click="openCreateMember()" style="display: none;"
            class="w-full mb-4 py-3 bg-blue-600 text-white rounded-field font-bold text-sm shadow-md shadow-blue-200 active:scale-[0.98] transition-transform flex items-center justify-center gap-2">
        <i data-lucide="user-plus" class="w-4 h-4"></i> Thêm thành viên
    </button>

    <!-- Tìm kiếm -->
    <div class="relative mb-3">
        <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400"></i>
        <input x-model="memberSearch" type="text" placeholder="Tìm tên hoặc lớp..." class="w-full bg-white border border-slate-200 rounded-field py-3.5 pl-12 pr-4 text-sm font-medium text-slate-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
    </div>

    <!-- Lọc theo vai trò -->
    <div class="flex gap-2 mb-4 overflow-x-auto hide-scrollbar bleed-x px-4 sm:px-6">
        <button @click="memberRoleFilter = ''" type="button"
                class="shrink-0 px-4 py-2 rounded-full font-bold text-xs border transition-colors"
                :class="memberRoleFilter === '' ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-500 border-slate-200'">Tất cả</button>
        <template x-for="r in roleDefs" :key="r.value">
            <button @click="memberRoleFilter = r.value" type="button"
                    class="shrink-0 px-4 py-2 rounded-full font-bold text-xs border transition-colors"
                    :class="memberRoleFilter === r.value ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-500 border-slate-200'"
                    x-text="r.label"></button>
        </template>
    </div>

    <div class="text-sm font-bold text-slate-500 mb-3 px-1">
        Tổng: <span x-text="filteredMembers.length" class="text-blue-600 text-base"></span> thành viên
    </div>

    <div class="space-y-2.5">
        <template x-for="m in filteredMembers" :key="m.id">
            <div style="content-visibility: auto; contain-intrinsic-size: auto 92px;"
                 class="bg-white rounded-field p-4 shadow-sm border border-slate-100 flex items-center gap-3">

                <div class="w-11 h-11 shrink-0 rounded-2xl flex items-center justify-center border"
                     :class="isProtectedMember(m) ? 'bg-slate-100 border-slate-200 text-slate-500' : 'bg-blue-50 border-blue-100 text-blue-500'">
                    <i x-show="isProtectedMember(m)" data-lucide="shield-check" class="w-5 h-5"></i>
                    <i x-show="!isProtectedMember(m)" data-lucide="user" class="w-5 h-5"></i>
                </div>

                <div class="flex-1 min-w-0">
                    <p class="text-sm font-black text-slate-800 leading-snug">
                        <span class="font-normal text-slate-500" x-text="m.holyName"></span>
                        <span x-text="m.fullName"></span>
                    </p>
                    <div class="flex items-center gap-1.5 flex-wrap mt-1">
                        <span x-show="roleLabelFor(m) !== ''" style="display: none;"
                                    class="text-micro font-bold uppercase tracking-wider px-2 py-0.5 rounded-md border"
                              :class="roleChipClass(m.role)" x-text="roleLabelFor(m)"></span>
                        <span class="text-micro font-semibold text-slate-500" x-text="titleFor(m)"></span>
                    </div>
                    <p class="text-micro font-medium text-slate-500 mt-1"
                       x-text="m.className || m.block || 'Toàn đoàn'"></p>

                    <!-- PHÂN CÔNG KIÊM NHIỆM -->
                    <div class="mt-3 pt-3 border-t border-slate-100" x-show="canManageOrg">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-micro font-bold text-slate-500 uppercase tracking-wider">Phân công</p>
                            <button @click="openAddAssignment(m)"
                                    class="text-micro font-bold text-blue-600 flex items-center gap-1 active:scale-95 transition-transform">
                                <i data-lucide="plus" class="w-3 h-3"></i> Thêm
                            </button>
                        </div>
                        <div class="space-y-1.5">
                            <template x-for="a in (memberAssignments[m.id] || [])" :key="a.id">
                                <div class="flex items-center gap-2 bg-slate-50 rounded-xl px-3 py-2">
                                    <span class="text-micro font-bold uppercase px-1.5 py-0.5 rounded border"
                                          :class="roleChipClass(a.role_code)" x-text="roleLabel(a.role_code)"></span>
                                    <span class="text-micro text-slate-600 truncate flex-1"
                                          x-text="(a.block_name || a.class_name || 'toàn đoàn')"></span>
                                    <span x-show="a.is_primary" class="text-micro font-black text-amber-600">★</span>
                                    <button @click="endAssignment(a)"
                                            x-show="!a.to_date"
                                            class="text-rose-500 active:scale-90"><i data-lucide="x-circle" class="w-3.5 h-3.5"></i></button>
                                    <button @click="setPrimaryAssignment(a)"
                                            x-show="!a.is_primary && !a.to_date"
                                            class="text-blue-500 active:scale-90"><i data-lucide="star" class="w-3.5 h-3.5"></i></button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5 shrink-0 items-end">
                    <button aria-label="Sửa thành viên" x-show="canManageOrg" @click="openEditMember(m)" style="display: none;"
                            class="tap-safe w-8 h-8 bg-slate-50 rounded-full flex items-center justify-center text-slate-400 active:scale-90 border border-slate-200">
                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                    </button>
                    <!-- Cấp lại mật khẩu: hiện mật khẩu tạm MỘT LẦN để BĐH đọc cho GLV -->
                    <button aria-label="Cấp lại mật khẩu" x-show="canManageOrg && m.status !== 'chờ duyệt'" @click="resetMemberPassword(m)" style="display: none;"
                            class="tap-safe w-8 h-8 bg-amber-50 rounded-full flex items-center justify-center text-amber-500 active:scale-90 border border-amber-100">
                        <i data-lucide="key-round" class="w-3.5 h-3.5"></i>
                    </button>
                    <span x-show="m.status !== 'đang phục vụ'" style="display: none;"
                          class="text-micro font-bold uppercase px-1.5 py-0.5 rounded bg-slate-100 text-slate-500" x-text="m.status"></span>
                </div>
            </div>
        </template>

        <div x-show="filteredMembers.length === 0" style="display: none;" class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
            <i data-lucide="search-x" class="w-10 h-10 mx-auto text-slate-300 mb-3"></i>
            <p class="text-slate-500 font-medium text-sm">Không tìm thấy thành viên nào.</p>
        </div>
    </div>

    <!-- Bảng giải thích hệ thống vai trò -->
    <div class="bg-white rounded-card p-5 shadow-sm border border-slate-100 mt-5">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Hệ thống vai trò</h3>
        <p class="text-micro text-slate-500 mb-4 leading-snug">
            <span class="font-bold text-slate-500">Vai trò</span> quyết định quyền trong hệ thống.
            <span class="font-bold text-slate-500">Chức danh</span> chỉ để hiển thị, không sinh ra quyền.
        </p>
        <div class="space-y-3">
            <template x-for="r in roleDefs" :key="r.value">
                <div class="flex items-start gap-3">
                    <span class="shrink-0 text-micro font-bold uppercase tracking-wider px-2 py-1 rounded-lg border w-28 text-center"
                          :class="roleChipClass(r.value)" x-text="r.label"></span>
                    <div class="min-w-0">
                        <p class="text-micro text-slate-600 leading-snug" x-text="r.desc"></p>
                        <p class="text-micro text-slate-500 mt-0.5">Phạm vi: <span class="font-bold" x-text="r.scope"></span></p>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<!-- ==========================================================
     POPUP THÊM PHÂN CÔNG
     ========================================================== -->
<div x-show="showAssignmentModal" style="display: none;" class="fixed inset-0 z-[210] flex items-end justify-center sm:items-center sm:p-6">
    <div @click="showAssignmentModal = false" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
    <div class="modal-sheet relative w-full max-w-md bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl flex flex-col max-h-[88dvh] overflow-y-auto">
        <div class="flex justify-center pt-3 pb-2"><div class="w-12 h-1.5 bg-slate-200 rounded-full"></div></div>
        <div class="flex justify-between items-center px-5 pb-4 border-b border-slate-100">
            <h3 class="text-lg font-black text-slate-800">Thêm phân công</h3>
            <button @click="showAssignmentModal = false" class="tap-safe w-8 h-8 bg-slate-100 rounded-full text-slate-500 active:scale-90 flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <div class="p-5 space-y-4">
            <div>
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Vai trò</label>
                <select x-model="assignmentForm.role" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
                    <template x-for="r in roleDefs" :key="r.value">
                        <option :value="r.value" x-text="r.label + ' (' + r.scope + ')'"></option>
                    </template>
                </select>
            </div>
            <div x-show="roleScope(assignmentForm.role) === 'khối'">
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Khối</label>
                <select x-model="assignmentForm.blockId" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
                    <template x-for="b in blocks" :key="b">
                        <option :value="blockIdByName(b)" x-text="b"></option>
                    </template>
                </select>
            </div>
            <div x-show="roleScope(assignmentForm.role) === 'lớp'">
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Lớp</label>
                <select x-model="assignmentForm.classId" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
                    <template x-for="c in classes" :key="c.id">
                        <option :value="c.id" x-text="c.name + ' (' + c.block + ')'"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Ghi chú</label>
                <input x-model="assignmentForm.note" type="text" placeholder="Lý do phân công..."
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" x-model="assignmentForm.isPrimary" class="w-4 h-4 rounded">
                Đặt làm phân công chính
            </label>
        </div>
        <div class="p-4 border-t border-slate-100">
            <button @click="saveAssignment()" class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center">
                <i data-lucide="save" class="w-5 h-5 mr-2"></i> Lưu phân công
            </button>
        </div>
    </div>
</div>

<!-- ==========================================================
     POPUP KHỐI
     ========================================================== -->
<div x-show="showBlockModal" style="display: none;" class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-6">
    <div x-show="showBlockModal" x-transition.opacity.duration.300ms @click="showBlockModal = false" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
    <div x-show="showBlockModal" x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0" class="modal-sheet relative w-full max-w-md sm:max-w-lg bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl flex flex-col max-h-[88dvh] overflow-y-auto">
        <div class="flex justify-center pt-3 pb-2"><div class="w-12 h-1.5 bg-slate-200 rounded-full"></div></div>
        <div class="flex justify-between items-center px-5 pb-4 border-b border-slate-100">
            <h3 class="text-lg font-black text-slate-800" x-text="blockForm.original ? 'Sửa tên khối' : 'Thêm khối mới'"></h3>
            <button aria-label="Đóng" @click="showBlockModal = false" class="tap-safe w-8 h-8 bg-slate-100 rounded-full text-slate-500 active:scale-90 flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <div class="p-5">
            <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Tên khối</label>
            <input x-model="blockForm.name" type="text" placeholder="VD: Khai Tâm..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            <p x-show="blockForm.original" style="display: none;" class="text-micro text-amber-600 mt-2 leading-snug">
                Đổi tên khối sẽ cập nhật theo cho tất cả lớp, thiếu nhi, GLV và thông báo đang gắn với khối này.
            </p>
        </div>
        <div class="p-4 border-t border-slate-100">
            <button @click="saveBlock()" class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center">
                <i data-lucide="save" class="w-5 h-5 mr-2"></i> Lưu khối
            </button>
        </div>
    </div>
</div>

<!-- ==========================================================
     POPUP LỚP
     ========================================================== -->
<div x-show="showClassModal" style="display: none;" class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-6">
    <div x-show="showClassModal" x-transition.opacity.duration.300ms @click="showClassModal = false" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
    <div x-show="showClassModal" x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0" class="modal-sheet relative w-full max-w-md sm:max-w-lg bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl flex flex-col max-h-[88dvh] overflow-y-auto">
        <div class="flex justify-center pt-3 pb-2"><div class="w-12 h-1.5 bg-slate-200 rounded-full"></div></div>
        <div class="flex justify-between items-center px-5 pb-4 border-b border-slate-100">
            <h3 class="text-lg font-black text-slate-800" x-text="classForm.original ? 'Sửa lớp' : 'Thêm lớp mới'"></h3>
            <button aria-label="Đóng" @click="showClassModal = false" class="tap-safe w-8 h-8 bg-slate-100 rounded-full text-slate-500 active:scale-90 flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <div class="p-5 space-y-4">
            <div>
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Tên lớp</label>
                <input x-model="classForm.name" type="text" placeholder="VD: Khai Tâm 1A..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Thuộc khối</label>
                <select x-model="classForm.block" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <template x-for="b in blocks" :key="b">
                        <option :value="b" x-text="b"></option>
                    </template>
                </select>
            </div>
            <p x-show="classForm.original" style="display: none;" class="text-micro text-amber-600 leading-snug">
                Đổi tên hoặc chuyển khối sẽ cập nhật theo cho tất cả thiếu nhi và GLV của lớp này.
            </p>
        </div>
        <div class="p-4 border-t border-slate-100">
            <button @click="saveClass()" class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center">
                <i data-lucide="save" class="w-5 h-5 mr-2"></i> Lưu lớp
            </button>
        </div>
    </div>
</div>

<!-- ==========================================================
     POPUP THÀNH VIÊN
     ========================================================== -->
<div x-show="showMemberModal" style="display: none;" class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-6">
    <div x-show="showMemberModal" x-transition.opacity.duration.300ms @click="showMemberModal = false" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
    <div x-show="showMemberModal" x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0" class="modal-sheet relative w-full max-w-md sm:max-w-lg bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl h-[88dvh] sm:h-[80dvh] flex flex-col overflow-hidden">

        <div class="flex justify-center pt-3 pb-2 bg-white"><div class="w-12 h-1.5 bg-slate-200 rounded-full"></div></div>
        <div class="flex justify-between items-center px-5 pb-4 border-b border-slate-100 bg-white">
            <h3 class="text-lg font-black text-slate-800" x-text="isEditingMember ? 'Sửa thành viên' : 'Thêm thành viên'"></h3>
            <button aria-label="Đóng" @click="showMemberModal = false" class="tap-safe w-8 h-8 flex items-center justify-center bg-slate-100 rounded-full text-slate-500 active:scale-90 transition-transform"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>

        <div class="flex-1 overflow-y-auto p-5 space-y-4">
            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-1">
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Tên Thánh</label>
                    <input x-model="memberForm.holyName" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
                <div class="col-span-2">
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Họ và Tên</label>
                    <input x-model="memberForm.fullName" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Số điện thoại</label>
                    <input x-model="memberForm.phone" type="tel" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Ngày sinh</label>
                    <input x-model="memberForm.birthDate" type="date" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
            </div>

            <!-- VAI TRÒ: khóa với BĐH và Admin -->
            <div class="border-t border-slate-100 pt-4">
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-micro font-bold text-slate-500 uppercase">Vai trò (quyền hệ thống)</label>
                    <span x-show="isProtectedMember(memberForm)" style="display: none;" class="flex items-center gap-1 text-micro font-bold text-rose-600">
                        <i data-lucide="lock" class="w-3 h-3"></i> Đã khóa
                    </span>
                </div>
                <select x-model="memberForm.role" @change="onMemberRoleChange()"
                        :disabled="isEditingMember && isProtectedMember(memberForm)"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 disabled:opacity-60 disabled:cursor-not-allowed">
                    <template x-for="r in roleDefs" :key="r.value">
                        <option :value="r.value" x-text="r.label"></option>
                    </template>
                </select>
                <p x-show="isEditingMember && isProtectedMember(memberForm)" style="display: none;" class="text-micro text-rose-600 mt-1.5 leading-snug">
                    Không thể đổi vai trò của Ban Điều Hành và Quản trị từ màn này.
                </p>
                <p x-show="!(isEditingMember && isProtectedMember(memberForm))" class="text-micro text-slate-500 mt-1.5 leading-snug"
                   x-text="'Phạm vi: ' + roleScope(memberForm.role)"></p>
            </div>

            <!-- CHỨC DANH -->
            <div>
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Chức danh (hiển thị)</label>
                <select x-model="memberForm.title" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <template x-for="t in titleOptionsFor(memberForm.role)" :key="t">
                        <option :value="t" x-text="t"></option>
                    </template>
                </select>
            </div>

            <!-- PHÂN CÔNG: hiện đúng theo phạm vi của vai trò -->
            <div x-show="roleScope(memberForm.role) === 'khối'" style="display: none;">
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Khối phụ trách</label>
                <select x-model="memberForm.block" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="">-- Chọn khối --</option>
                    <template x-for="b in blocks" :key="b">
                        <option :value="b" x-text="b"></option>
                    </template>
                </select>
            </div>

            <div x-show="roleScope(memberForm.role) === 'lớp'" style="display: none;">
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Lớp phụ trách</label>
                <select x-model="memberForm.className" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="">-- Chọn lớp --</option>
                    <template x-for="c in classes" :key="c.name">
                        <option :value="c.name" x-text="c.name + ' (' + c.block + ')'"></option>
                    </template>
                </select>
            </div>

            <div class="border-t border-slate-100 pt-4 pb-6">
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Tình trạng</label>
                <select x-model="memberForm.status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="đang phục vụ">Đang phục vụ</option>
                    <option value="tạm nghỉ">Tạm nghỉ</option>
                </select>
            </div>
        </div>

        <div class="p-4 border-t border-slate-100 bg-white flex gap-3">
            <button aria-label="Xóa thành viên" x-show="isEditingMember && !isProtectedMember(memberForm)" style="display: none;"
                    @click="deleteMember(memberForm); showMemberModal = false"
                    class="w-14 shrink-0 bg-red-50 text-red-500 rounded-2xl border border-red-100 active:scale-95 transition-transform flex justify-center items-center">
                <i data-lucide="trash-2" class="w-5 h-5"></i>
            </button>
            <button @click="saveMember()" class="flex-1 bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center">
                <i data-lucide="save" class="w-5 h-5 mr-2"></i> Lưu thành viên
            </button>
        </div>
    </div>
</div>

<!-- ==========================================================
     POPUP DUYỆT TÀI KHOẢN
     ========================================================== -->
<div x-show="showApproveModal" style="display: none;" class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-6">
    <div x-show="showApproveModal" x-transition.opacity.duration.300ms @click="showApproveModal = false" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
    <div x-show="showApproveModal" x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0" class="modal-sheet relative w-full max-w-md sm:max-w-lg bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl flex flex-col max-h-[88dvh] overflow-y-auto">

        <div class="flex justify-center pt-3 pb-2"><div class="w-12 h-1.5 bg-slate-200 rounded-full"></div></div>
        <div class="flex justify-between items-center px-5 pb-4 border-b border-slate-100">
            <h3 class="text-lg font-black text-slate-800">Duyệt tài khoản</h3>
            <button aria-label="Đóng" @click="showApproveModal = false" class="tap-safe w-8 h-8 bg-slate-100 rounded-full text-slate-500 active:scale-90 flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>

        <div class="p-5 space-y-4">
            <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100">
                <p class="text-micro font-bold text-slate-500 uppercase tracking-wide mb-1">Người đăng ký</p>
                <p class="text-base font-black text-slate-800 leading-snug" x-text="approveForm.name"></p>
                <p class="text-xs font-medium text-slate-500 mt-1" x-text="approveForm.phone"></p>
                <p x-show="approveForm.note" style="display: none;" class="text-xs text-slate-600 italic mt-2 pt-2 border-t border-slate-200" x-text="approveForm.note"></p>
            </div>

            <div class="bg-blue-50 border border-blue-100 rounded-2xl p-3 flex items-start gap-2.5">
                <i data-lucide="info" class="w-4 h-4 text-blue-500 shrink-0 mt-0.5"></i>
                <p class="text-micro text-blue-700 leading-snug">
                    Duyệt là phải <span class="font-bold">phân công luôn</span>, không để tài khoản lơ lửng
                    không thuộc lớp nào.
                </p>
            </div>

            <div>
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Vai trò</label>
                <select x-model="approveForm.role" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="glv">Giáo Lý Viên</option>
                    <option value="glv_chu_nhiem">GLV Chủ Nhiệm</option>
                    <option value="truong_khoi">Trưởng Khối</option>
                </select>
                <p class="text-micro text-slate-500 mt-1 ml-1">Không duyệt thẳng lên Ban Điều Hành được</p>
            </div>

            <div x-show="roleScope(approveForm.role) === 'lớp'">
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Lớp phụ trách</label>
                <select x-model="approveForm.className" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <template x-for="c in classes" :key="c.name">
                        <option :value="c.name" x-text="c.name + ' (' + c.block + ')'"></option>
                    </template>
                </select>
            </div>

            <div x-show="roleScope(approveForm.role) === 'khối'" style="display: none;">
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Khối phụ trách</label>
                <select x-model="approveForm.block" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <template x-for="b in blocks" :key="b">
                        <option :value="b" x-text="b"></option>
                    </template>
                </select>
            </div>
        </div>

        <div class="p-4 border-t border-slate-100">
            <button @click="confirmApprove()" class="w-full bg-emerald-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-emerald-200 flex justify-center items-center">
                <i data-lucide="check" class="w-5 h-5 mr-2"></i> Duyệt và phân công
            </button>
        </div>
    </div>
</div>

<!-- ==========================================================
     POPUP THÊM PHÂN CÔNG
     ========================================================== -->
<div x-show="showAssignmentModal" style="display: none;" class="fixed inset-0 z-[210] flex items-end justify-center sm:items-center sm:p-6">
    <div @click="showAssignmentModal = false" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
    <div class="modal-sheet relative w-full max-w-md bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl flex flex-col max-h-[88dvh] overflow-y-auto">
        <div class="flex justify-center pt-3 pb-2"><div class="w-12 h-1.5 bg-slate-200 rounded-full"></div></div>
        <div class="flex justify-between items-center px-5 pb-4 border-b border-slate-100">
            <h3 class="text-lg font-black text-slate-800">Thêm phân công</h3>
            <button @click="showAssignmentModal = false" class="tap-safe w-8 h-8 bg-slate-100 rounded-full text-slate-500 active:scale-90 flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <div class="p-5 space-y-4">
            <div>
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Vai trò</label>
                <select x-model="assignmentForm.role" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
                    <template x-for="r in roleDefs" :key="r.value">
                        <option :value="r.value" x-text="r.label + ' (' + r.scope + ')'"></option>
                    </template>
                </select>
            </div>
            <div x-show="roleScope(assignmentForm.role) === 'khối'">
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Khối</label>
                <select x-model="assignmentForm.blockId" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
                    <template x-for="b in blocks" :key="b">
                        <option :value="blockIdByName(b)" x-text="b"></option>
                    </template>
                </select>
            </div>
            <div x-show="roleScope(assignmentForm.role) === 'lớp'">
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Lớp</label>
                <select x-model="assignmentForm.classId" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
                    <template x-for="c in classes" :key="c.id">
                        <option :value="c.id" x-text="c.name + ' (' + c.block + ')'"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Ghi chú</label>
                <input x-model="assignmentForm.note" type="text" placeholder="Lý do phân công..."
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" x-model="assignmentForm.isPrimary" class="w-4 h-4 rounded">
                Đặt làm phân công chính
            </label>
        </div>
        <div class="p-4 border-t border-slate-100">
            <button @click="saveAssignment()" class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center">
                <i data-lucide="save" class="w-5 h-5 mr-2"></i> Lưu phân công
            </button>
        </div>
    </div>
</div>
