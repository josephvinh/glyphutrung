<!-- MÀN HÌNH QUẢN LÝ THỜI KHOÁ BIỂU LỚP -->
<div data-module="schedules" class="module-panel pt-6 pb-24 relative">

    <!-- 1. THANH ĐIỀU HƯỚNG -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center">
            <button aria-label="Quay lại trang chủ" @click="changeModule('dashboard')" class="w-10 h-10 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-4">
                <i data-lucide="chevron-left" class="w-6 h-6 text-slate-600"></i>
            </button>
            <div>
                <h2 class="text-xl font-black text-slate-800 tracking-tight">Thời Khoá Biểu</h2>
                <p class="text-xs text-slate-500 font-medium">Lịch sinh hoạt riêng theo từng lớp</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button @click="seedSchedules()" type="button" class="bg-amber-500 text-white px-3 py-2 rounded-xl font-bold text-xs shadow-md shadow-amber-200 flex items-center active:scale-95 transition-transform">
                <i data-lucide="sparkles" class="w-3.5 h-3.5 mr-1"></i> Seed
            </button>
            <button @click="openCreateSchedule()" type="button" class="bg-blue-600 text-white px-4 py-2 rounded-xl font-bold text-sm shadow-md shadow-blue-200 flex items-center active:scale-95 transition-transform">
                <i data-lucide="plus" class="w-4 h-4 mr-1"></i> Tạo mới
            </button>
        </div>
    </div>

    <!-- 2. HƯỚNG DẪN -->
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl p-4 mb-6 border border-blue-100">
        <div class="flex items-start">
            <i data-lucide="info" class="w-5 h-5 text-blue-600 mr-3 mt-0.5 shrink-0"></i>
            <div>
                <p class="text-sm font-bold text-blue-800 mb-1">Thời khóa biểu riêng cho từng lớp (Hướng B)</p>
                <p class="text-xs text-blue-600 leading-relaxed">
                    Mỗi lớp có thể có lịch riêng: ca sáng, ca chiều, lễ thứ Năm…
                    Lịch cũ dùng <strong>chương trình toàn đoàn</strong> vẫn hoạt động bình thường.
                </p>
            </div>
        </div>
    </div>

    <!-- 3. DANH SÁCH THEO LỚP -->
    <div class="space-y-4">
        <template x-if="classSchedules.length === 0">
            <div class="text-center py-12">
                <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="calendar-x" class="w-8 h-8 text-slate-400"></i>
                </div>
                <p class="text-slate-500 text-sm font-medium">Chưa có thời khóa biểu lớp nào.</p>
                <p class="text-slate-400 text-xs mt-1">Nhấn <strong>Seed</strong> để tạo lịch từ chương trình hiện tại.</p>
            </div>
        </template>

        <!-- Gom nhóm theo lớp -->
        <template x-for="(schedules, className) in groupedByClass" :key="className">
            <div class="bg-white rounded-card shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center">
                        <i data-lucide="users" class="w-4 h-4 text-slate-500 mr-2"></i>
                        <span class="font-bold text-slate-700" x-text="className"></span>
                    </div>
                    <span class="text-xs text-slate-400" x-text="schedules.length + ' slot'"></span>
                </div>

                <div class="divide-y divide-slate-50">
                    <template x-for="sch in schedules" :key="sch.id">
                        <div class="px-4 py-3 flex items-center justify-between hover:bg-slate-50/50 transition-colors">
                            <div class="flex items-center gap-4">
                                <!-- Thứ -->
                                <div class="text-center min-w-[48px]">
                                    <span class="text-micro font-bold uppercase"
                                          :class="getDayColorClass(sch.dayOfWeek)"
                                          x-text="weekdays.find(w => w.value === sch.dayOfWeek)?.label"></span>
                                </div>

                                <!-- Slot badge -->
                                <div x-show="sch.slot" class="px-2 py-1 rounded-md text-xs font-bold"
                                     :class="getSlotBadgeClass(sch.slot)">
                                    <span x-text="sch.slot"></span>
                                </div>

                                <!-- Giờ -->
                                <div class="text-sm">
                                    <span class="font-bold text-slate-700" x-text="sch.startTime"></span>
                                    <span class="text-slate-300 mx-1">→</span>
                                    <span class="text-slate-500" x-text="sch.cutoffTime || '—'"></span>
                                </div>

                                <!-- Program -->
                                <div x-show="sch.programName" class="text-xs text-slate-400">
                                    <i data-lucide="tag" class="w-3 h-3 inline mr-1"></i>
                                    <span x-text="sch.programName"></span>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <!-- Trạng thái -->
                                <span class="text-micro px-2 py-0.5 rounded-full font-medium"
                                      :class="sch.status === 'kích hoạt' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-400'"
                                      x-text="sch.status"></span>

                                <!-- Nút hành động -->
                                <button @click="openEditSchedule(sch)" class="tap-safe w-7 h-7 bg-slate-50 rounded-full flex items-center justify-center text-slate-400 active:scale-90 border border-slate-200">
                                    <i data-lucide="pencil" class="w-3 h-3"></i>
                                </button>
                                <button @click="deleteSchedule(sch.id)" class="tap-safe w-7 h-7 bg-red-50 rounded-full flex items-center justify-center text-red-400 active:scale-90 border border-red-100">
                                    <i data-lucide="trash-2" class="w-3 h-3"></i>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    <!-- 4. POPUP THÊM/SỬA THỜI KHOÁ BIỂU -->
    <div x-show="showScheduleModal" style="display: none;" class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-6">
        <div x-show="showScheduleModal" x-transition.opacity.duration.300ms @click="showScheduleModal = false" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
        <div x-show="showScheduleModal" x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0" class="modal-sheet relative w-full max-w-md sm:max-w-lg bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl flex flex-col max-h-[88dvh] overflow-y-auto">
            <div class="flex justify-center pt-3 pb-2"><div class="w-12 h-1.5 bg-slate-200 rounded-full"></div></div>
            <div class="flex justify-between items-center px-5 pb-4 border-b border-slate-100">
                <h3 class="text-lg font-black text-slate-800" x-text="isEditingSchedule ? 'Cập nhật Thời khoá biểu' : 'Tạo Thời khoá biểu mới'"></h3>
                <button aria-label="Đóng" @click="showScheduleModal = false" class="tap-safe w-8 h-8 bg-slate-100 rounded-full text-slate-500 active:scale-90 flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>

            <div class="p-5 space-y-4">
                <!-- Chọn lớp -->
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Lớp</label>
                    <select x-model.number="scheduleForm.classId" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="0">— Chọn lớp —</option>
                        <template x-for="cls in classOptions" :key="cls.id">
                            <option :value="cls.id" x-text="cls.name"></option>
                        </template>
                    </select>
                </div>

                <!-- Thứ trong tuần -->
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Ngày trong tuần</label>
                    <select x-model.number="scheduleForm.dayOfWeek" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800">
                        <template x-for="w in weekdays" :key="w.value">
                            <option :value="w.value" x-text="w.label"></option>
                        </template>
                    </select>
                </div>

                <!-- Giờ bắt đầu -->
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Giờ bắt đầu</label>
                    <input x-model="scheduleForm.startTime" type="time" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>

                <!-- Giờ chốt -->
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">
                        Giờ chốt sổ
                        <span class="font-normal text-slate-400">(mặc định lấy từ chương trình)</span>
                    </label>
                    <input x-model="scheduleForm.cutoffTime" type="time" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>

                <!-- Slot (ca) -->
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Ca sinh hoạt</label>
                    <div class="grid grid-cols-3 gap-2">
                        <template x-for="s in [{v:'sáng',l:'Ca sáng'},{v:'chiều',l:'Ca chiều'},{v:'tối',l:'Ca tối'}]" :key="s.v">
                            <button @click="scheduleForm.slot = scheduleForm.slot === s.v ? '' : s.v"
                                    type="button"
                                    class="py-2 rounded-xl text-sm font-bold border-2 transition-all"
                                    :class="scheduleForm.slot === s.v
                                        ? (s.v === 'sáng' ? 'bg-amber-100 border-amber-500 text-amber-700'
                                          : s.v === 'chiều' ? 'bg-blue-100 border-blue-500 text-blue-700'
                                          : 'bg-indigo-100 border-indigo-500 text-indigo-700')
                                        : 'bg-slate-50 border-slate-200 text-slate-500'">
                                <span x-text="s.l"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Loại buổi (program) -->
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">
                        Loại buổi
                        <span class="font-normal text-slate-400">(để trống = giáo lý thường)</span>
                    </label>
                    <select x-model.number="scheduleForm.programId" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800">
                        <option value="">— Giáo lý thường —</option>
                        <template x-for="p in programs" :key="p.id">
                            <option :value="p.id" x-text="p.name + ' (' + p.startTime + ')'"></option>
                        </template>
                    </select>
                </div>

                <!-- Thời gian hiệu lực -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Từ ngày</label>
                        <input x-model="scheduleForm.activeFrom" type="date" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Đến ngày</label>
                        <input x-model="scheduleForm.activeTo" type="date" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500">
                    </div>
                </div>

                <!-- Trạng thái -->
                <div class="flex items-center justify-between bg-slate-50 rounded-xl px-4 py-3">
                    <div>
                        <p class="text-sm font-bold text-slate-700">Kích hoạt</p>
                        <p class="text-micro text-slate-500">Tắt để tạm ngưng lịch này</p>
                    </div>
                    <button @click="scheduleForm.status = scheduleForm.status === 'kích hoạt' ? 'tạm ngưng' : 'kích hoạt'"
                            type="button"
                            class="w-12 h-7 rounded-full relative transition-colors duration-200"
                            :class="scheduleForm.status === 'kích hoạt' ? 'bg-emerald-500' : 'bg-slate-300'">
                        <div class="w-5 h-5 bg-white rounded-full absolute top-1 shadow-sm transition-transform duration-200"
                             :class="scheduleForm.status === 'kích hoạt' ? 'translate-x-6' : 'translate-x-1'"></div>
                    </button>
                </div>
            </div>

            <div class="p-4 border-t border-slate-100">
                <button @click="saveSchedule()" type="button" class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] shadow-md shadow-blue-200 flex justify-center items-center">
                    <i data-lucide="save" class="w-5 h-5 mr-2"></i> Lưu Thời Khoá Biểu
                </button>
            </div>
        </div>
    </div>
</div>
