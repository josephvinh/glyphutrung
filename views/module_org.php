<!-- MÀN HÌNH KHỐI & LỚP  (Nhân sự đã tách sang module_staff.php) -->
<div data-module="org" class="module-panel pt-6 pb-24 relative">

    <!-- 1. THANH ĐIỀU HƯỚNG -->
    <div class="flex items-center mb-5">
        <button aria-label="Quay lại trang chủ" @click="changeModule('dashboard')" class="tap-safe w-10 h-10 shrink-0 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-4">
            <i data-lucide="chevron-left" class="w-6 h-6 text-slate-600"></i>
        </button>
        <h2 class="text-xl font-black text-slate-800 tracking-tight">Khối &amp; Lớp</h2>
    </div>

    <!-- Cấp dưới BĐH chỉ được xem -->
    <div x-show="!canManageOrg" style="display: none;" class="bg-slate-100 border border-slate-200 rounded-2xl p-3 mb-4 flex items-start gap-2.5">
        <i data-lucide="eye" class="w-4 h-4 text-slate-500 shrink-0 mt-0.5"></i>
        <p class="text-micro text-slate-600 leading-snug">
            Bạn đang ở chế độ <span class="font-bold">chỉ xem</span>. Chỉ Ban Điều Hành mới thay đổi được khối, lớp và nhân sự.
        </p>
    </div>

    <!-- CÂY KHỐI - LỚP -->
    <div>

        <!-- BAN ĐIỀU HÀNH: luôn khóa, chỉ xem -->
        <div class="bg-white rounded-card p-5 shadow-sm border border-slate-100 mb-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Ban Điều Hành</h3>
                <span class="flex items-center gap-1 text-micro font-bold text-slate-500">
                    <i data-lucide="lock" class="w-3 h-3"></i> Không sửa được vai trò
                </span>
            </div>
            <div class="space-y-2.5">
                <template x-for="m in bdhMembers" :key="m.id">
                    <div class="flex items-center gap-3">
                        <div class="tap-safe w-9 h-9 shrink-0 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500">
                            <i data-lucide="shield-check" class="w-4 h-4"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-black text-slate-800 leading-snug">
                                <span class="font-normal text-slate-500" x-text="m.holyName"></span>
                                <span x-text="m.fullName"></span>
                            </p>
                            <p class="text-micro font-medium text-slate-500" x-text="titleFor(m)"></p>
                        </div>
                        <span x-show="roleLabelFor(m) !== ''" style="display: none;" class="shrink-0 text-micro font-bold uppercase tracking-wider px-2 py-1 rounded-lg border"
                              :class="roleChipClass(m.role)" x-text="roleLabelFor(m)"></span>
                    </div>
                </template>
            </div>
        </div>

        <!-- Nút thêm khối -->
        <button x-show="canManageOrg" @click="openCreateBlock()" :disabled="busyBlock" style="display: none;"
                class="w-full mb-4 py-3 bg-white border border-dashed border-slate-300 rounded-field font-bold text-sm text-slate-500 active:scale-[0.98] transition-transform flex items-center justify-center gap-2 disabled:opacity-50">
            <i data-lucide="plus" class="w-4 h-4"></i> Thêm khối mới
        </button>

        <!-- DANH SÁCH KHỐI -->
        <div class="space-y-4">
            <template x-for="b in blocks" :key="b">
                <div class="bg-white rounded-card shadow-sm border border-slate-100 overflow-hidden">

                    <!-- Đầu khối -->
                    <div class="p-5">
                        <div class="flex justify-between items-start gap-3 mb-3">
                            <button @click="expandedBlock = (expandedBlock === b ? '' : b)" type="button" class="flex-1 min-w-0 text-left">
                                <h3 class="text-base font-black text-slate-800 leading-snug flex items-center gap-2">
                                    <span x-text="b"></span>
                                    <!-- Icon đổi theo trạng thái: KHÔNG dùng :data-lucide.
                                         lucide thay thẻ <i> bằng <svg> nên binding trỏ vào thẻ
                                         đã bị gỡ, đổi mấy cũng không ăn. Cách chạy được là đặt
                                         sẵn cả hai thẻ tĩnh rồi bật tắt bằng x-show — lucide chép
                                         thuộc tính sang <svg> và Alpine nhận lại binding. -->
                                    <span x-show="expandedBlock === b" class="inline-flex items-center justify-center"><i data-lucide="chevron-up" class="w-4 h-4 text-slate-400"></i></span>
                                    <span x-show="expandedBlock !== b" class="inline-flex items-center justify-center"><i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i></span>
                                </h3>
                                <p class="text-micro font-medium text-slate-500 mt-0.5">
                                    <span x-text="classesInBlock(b).length"></span> lớp
                                    <span class="text-slate-300 mx-1">•</span>
                                    <span x-text="blockSize(b)"></span> em
                                </p>
                            </button>

                            <div x-show="canManageOrg" style="display: none;" class="flex gap-2 shrink-0">
                                <button aria-label="Sửa tên khối" @click="openEditBlock(b)" :disabled="busyBlock" class="tap-safe w-8 h-8 bg-slate-50 rounded-full flex items-center justify-center text-slate-400 active:scale-90 border border-slate-200 disabled:opacity-50">
                                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                </button>
                                <button aria-label="Xóa khối" @click="deleteBlock(b)" :disabled="busyBlock" class="tap-safe w-8 h-8 bg-rose-50 rounded-full flex items-center justify-center text-rose-400 active:scale-90 border border-rose-100 disabled:opacity-50">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Trưởng khối -->
                        <div class="bg-amber-50 border border-amber-100 rounded-2xl p-3.5">
                            <p class="text-micro font-bold text-amber-600 uppercase tracking-wide mb-1.5">Trưởng khối</p>
                            <p x-show="!canManageOrg" style="display: none;" class="text-sm font-black text-slate-800"
                               x-text="headOfBlock(b) ? memberFullName(headOfBlock(b)) : 'Chưa phân công'"></p>
                            <select x-show="canManageOrg" style="display: none;"
                                    :value="headOfBlock(b) ? headOfBlock(b).id : ''"
                                    @change="setBlockHead(b, $event.target.value)"
                                    class="w-full bg-white border border-amber-200 rounded-xl px-3 py-2 text-sm font-semibold text-slate-800">
                                <option value="">-- Chưa phân công --</option>
                                <!-- :selected trên từng option, vì :value trên select chạy
                                     trước khi x-for kịp dựng option nên không ăn -->
                                <template x-for="m in candidatesForBlock()" :key="m.id">
                                    <option :value="m.id" :selected="headOfBlock(b) && headOfBlock(b).id === m.id" x-text="memberOptionLabel(m)"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <!-- Danh sách lớp trong khối -->
                    <div x-show="expandedBlock === b" x-collapse style="display: none;">
                        <div class="border-t border-slate-100 bg-slate-50 p-4 space-y-3">

                            <template x-for="cls in classesInBlock(b)" :key="cls.name">
                                <div class="bg-white rounded-2xl p-4 border border-slate-100">
                                    <div class="flex justify-between items-start gap-3 mb-3">
                                        <div class="min-w-0">
                                            <h4 class="text-sm font-black text-slate-800 leading-snug" x-text="cls.name"></h4>
                                            <p class="text-micro font-medium text-slate-500 mt-0.5">
                                                <span x-text="classSize(cls.name)"></span> em
                                                <span class="text-slate-300 mx-1">•</span>
                                                <span x-text="membersInClass(cls.name).length"></span> GLV
                                            </p>
                                        </div>
                                        <div x-show="canManageOrg" style="display: none;" class="flex gap-2 shrink-0">
                                            <button aria-label="Sửa lớp" @click="openEditClass(cls)" :disabled="busyClass" class="tap-safe w-7 h-7 bg-slate-50 rounded-full flex items-center justify-center text-slate-400 active:scale-90 border border-slate-200 disabled:opacity-50">
                                                <i data-lucide="pencil" class="w-3 h-3"></i>
                                            </button>
                                            <button aria-label="Xóa lớp" @click="deleteClass(cls)" :disabled="busyClass" class="tap-safe w-7 h-7 bg-rose-50 rounded-full flex items-center justify-center text-rose-400 active:scale-90 border border-rose-100 disabled:opacity-50">
                                                <i data-lucide="trash-2" class="w-3 h-3"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Chủ nhiệm lớp -->
                                    <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-3 mb-3">
                                        <p class="text-micro font-bold text-emerald-700 uppercase tracking-wide mb-1.5">Chủ nhiệm lớp</p>
                                        <p x-show="!canManageOrg" style="display: none;" class="text-sm font-black text-slate-800"
                                           x-text="headOfClass(cls.name) ? memberFullName(headOfClass(cls.name)) : 'Chưa phân công'"></p>
                                        <select x-show="canManageOrg" style="display: none;"
                                                :value="headOfClass(cls.name) ? headOfClass(cls.name).id : ''"
                                                @change="setClassHead(cls.name, $event.target.value)"
                                                class="w-full bg-white border border-emerald-200 rounded-lg px-2.5 py-1.5 text-sm font-semibold text-slate-800">
                                            <option value="">-- Chưa phân công --</option>
                                            <template x-for="m in candidatesForClass()" :key="m.id">
                                                <option :value="m.id" :selected="headOfClass(cls.name) && headOfClass(cls.name).id === m.id" x-text="memberOptionLabel(m)"></option>
                                            </template>
                                        </select>
                                    </div>

                                    <!-- Thành viên của lớp (suy từ phân công kiêm nhiệm) -->
                                    <div x-show="membersInClass(cls.name).length > 0" class="space-y-2">
                                        <template x-for="m in membersInClass(cls.name)" :key="m.assignmentId">
                                            <div class="flex items-center gap-1.5">
                                                <button @click="canManageOrg && openEditMember(m)" type="button"
                                                        class="flex-1 min-w-0 text-left flex items-center gap-2.5 py-1"
                                                        :class="canManageOrg ? 'active:scale-[0.98] transition-transform' : 'cursor-default'">
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-bold text-slate-700 leading-snug truncate">
                                                            <span class="font-normal text-slate-400" x-text="m.holyName"></span>
                                                            <span x-text="m.fullName"></span>
                                                        </p>
                                                        <p class="text-micro font-medium text-slate-500" x-text="titleFor(m)"></p>
                                                    </div>
                                                    <span x-show="m.status !== 'đang phục vụ'" style="display: none;"
                                                          class="shrink-0 text-micro font-bold uppercase px-1.5 py-0.5 rounded bg-slate-100 text-slate-500" x-text="m.status"></span>
                                                    <span x-show="roleLabelFor(m) !== ''" style="display: none;" class="shrink-0 text-micro font-bold uppercase tracking-wider px-2 py-0.5 rounded-md border"
                                                          :class="roleChipClass(m.role)" x-text="roleLabelFor(m)"></span>
                                                </button>
                                                <button x-show="canManageOrg" style="display: none;" type="button"
                                                        @click="removeClassAssignment(m.assignmentId)"
                                                        aria-label="Gỡ khỏi lớp"
                                                        class="tap-safe shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-rose-400 active:scale-90 border border-rose-100">
                                                    <i data-lucide="x" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </div>
                                        </template>
                                    </div>

                                    <p x-show="membersInClass(cls.name).length === 0" style="display: none;" class="text-micro text-slate-500 italic">Chưa có GLV nào.</p>

                                    <!-- Thêm GLV / Dự Bị vào lớp (kiêm nhiệm) -->
                                    <div x-show="canManageOrg" style="display: none;" class="mt-2.5 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        <select @change="addClassMember(cls.name, $event.target.value, 'glv'); $event.target.value=''"
                                                class="w-full bg-white border border-dashed border-blue-300 rounded-lg px-2.5 py-1.5 text-sm font-semibold text-blue-600">
                                            <option value="">+ Thêm GLV vào lớp…</option>
                                            <template x-for="m in addableToClass(cls.name)" :key="m.id">
                                                <option :value="m.id" x-text="memberOptionLabel(m)"></option>
                                            </template>
                                        </select>
                                        <select @change="addClassMember(cls.name, $event.target.value, 'du_bi'); $event.target.value=''"
                                                class="w-full bg-white border border-dashed border-slate-300 rounded-lg px-2.5 py-1.5 text-sm font-semibold text-slate-600">
                                            <option value="">+ Thêm Dự Bị…</option>
                                            <template x-for="m in addableToClass(cls.name)" :key="m.id">
                                                <option :value="m.id" x-text="memberOptionLabel(m)"></option>
                                            </template>
                                        </select>
                                    </div>
                                </div>
                            </template>

                            <p x-show="classesInBlock(b).length === 0" style="display: none;" class="text-center text-xs text-slate-500 py-3">Khối này chưa có lớp nào.</p>

                            <button x-show="canManageOrg" @click="openCreateClass(b)" :disabled="busyClass" style="display: none;"
                                    class="w-full py-2.5 bg-white border border-dashed border-slate-300 rounded-xl font-bold text-xs text-slate-500 active:scale-[0.98] transition-transform flex items-center justify-center gap-1.5 disabled:opacity-50">
                                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Thêm lớp vào khối này
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <div x-show="blocks.length === 0" style="display: none;" class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
                <i data-lucide="layers" class="w-10 h-10 mx-auto text-slate-300 mb-3"></i>
                <p class="text-slate-500 font-medium text-sm">Chưa có khối nào.</p>
            </div>
        </div>
    </div>

    <!-- ============================================================
         POPUP KHỐI
         ============================================================ -->
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
                <button @click="saveBlock()" type="button" :disabled="busyBlock" class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center disabled:opacity-50">
                    <i data-lucide="save" class="w-5 h-5 mr-2"></i> Lưu khối
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================================
         POPUP LỚP
         ============================================================ -->
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
                <button @click="saveClass()" :disabled="busyClass" class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center disabled:opacity-50">
                    <i data-lucide="save" class="w-5 h-5 mr-2"></i> Lưu lớp
                </button>
            </div>
        </div>
    </div>

</div>
