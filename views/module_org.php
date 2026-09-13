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
        <button x-show="canManageOrg" @click="openCreateBlock()" style="display: none;"
                class="w-full mb-4 py-3 bg-white border border-dashed border-slate-300 rounded-field font-bold text-sm text-slate-500 active:scale-[0.98] transition-transform flex items-center justify-center gap-2">
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
                                    <i x-show="expandedBlock === b" data-lucide="chevron-up" class="w-4 h-4 text-slate-400"></i>
                                    <i x-show="expandedBlock !== b" data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
                                </h3>
                                <p class="text-micro font-medium text-slate-500 mt-0.5">
                                    <span x-text="classesInBlock(b).length"></span> lớp
                                    <span class="text-slate-300 mx-1">•</span>
                                    <span x-text="blockSize(b)"></span> em
                                </p>
                            </button>

                            <div x-show="canManageOrg" style="display: none;" class="flex gap-2 shrink-0">
                                <button aria-label="Sửa tên khối" @click="openEditBlock(b)" class="tap-safe w-8 h-8 bg-slate-50 rounded-full flex items-center justify-center text-slate-400 active:scale-90 border border-slate-200">
                                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                </button>
                                <button aria-label="Xóa khối" @click="deleteBlock(b)" class="tap-safe w-8 h-8 bg-red-50 rounded-full flex items-center justify-center text-red-400 active:scale-90 border border-red-100">
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
                                            <button aria-label="Sửa lớp" @click="openEditClass(cls)" class="tap-safe w-7 h-7 bg-slate-50 rounded-full flex items-center justify-center text-slate-400 active:scale-90 border border-slate-200">
                                                <i data-lucide="pencil" class="w-3 h-3"></i>
                                            </button>
                                            <button aria-label="Xóa lớp" @click="deleteClass(cls)" class="tap-safe w-7 h-7 bg-red-50 rounded-full flex items-center justify-center text-red-400 active:scale-90 border border-red-100">
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

                            <button x-show="canManageOrg" @click="openCreateClass(b)" style="display: none;"
                                    class="w-full py-2.5 bg-white border border-dashed border-slate-300 rounded-xl font-bold text-xs text-slate-500 active:scale-[0.98] transition-transform flex items-center justify-center gap-1.5">
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

</div>
