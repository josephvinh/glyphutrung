<!-- MÀN HÌNH QUẢN LÝ CHƯƠNG TRÌNH -->
<div data-module="programs" class="module-panel pt-6 pb-24 relative">
    
    <!-- 1. THANH ĐIỀU HƯỚNG -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center">
            <button aria-label="Quay lại trang chủ" @click="changeModule('dashboard')" class="w-10 h-10 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-4">
                <i data-lucide="chevron-left" class="w-6 h-6 text-slate-600"></i>
            </button>
            <h2 class="text-xl font-black text-slate-800 tracking-tight">Chương Trình</h2>
        </div>
        
        <button @click="openCreateProgram()" type="button" class="bg-blue-600 text-white px-4 py-2 rounded-xl font-bold text-sm shadow-md shadow-blue-200 flex items-center active:scale-95 transition-transform">
            <i data-lucide="plus" class="w-4 h-4 mr-1"></i> Tạo mới
        </button>
    </div>

    <!-- 2. DANH SÁCH CHƯƠNG TRÌNH -->
    <div class="space-y-4 xl:space-y-0 xl:grid xl:grid-cols-2 xl:gap-4 xl:items-start">
        <!-- Loading skeleton -->
        <template x-if="syncing">
            <template x-for="i in 2" :key="'skel-' + i">
                <div class="bg-white rounded-card p-5 shadow-sm border border-slate-100">
                    <div class="flex justify-between items-start">
                        <div class="space-y-3 flex-1">
                            <div class="flex gap-2">
                                <div class="skeleton h-5 w-20 rounded"></div>
                                <div class="skeleton h-5 w-16 rounded"></div>
                            </div>
                            <div class="skeleton h-6 w-48 rounded"></div>
                            <div class="skeleton h-4 w-32 rounded"></div>
                            <div class="skeleton h-4 w-40 rounded"></div>
                        </div>
                        <div class="flex gap-2">
                            <div class="skeleton w-8 h-8 rounded-full"></div>
                            <div class="skeleton w-8 h-8 rounded-full"></div>
                        </div>
                    </div>
                    <div class="skeleton h-20 w-full rounded-2xl mt-4"></div>
                </div>
            </template>
        </template>
        <template x-for="prog in programs" :key="prog.id">
            <div class="bg-white rounded-card p-5 shadow-sm border border-slate-100 relative overflow-hidden flex flex-col gap-4">
                
                <div class="flex justify-between items-start">
                    <div class="pr-6">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-micro font-bold uppercase tracking-wider px-2 py-0.5 rounded-md"
                                  :class="prog.type === 'bắt buộc' ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-600'"
                                  x-text="prog.type"></span>
                            <span class="text-micro font-bold uppercase tracking-wider px-2 py-0.5 rounded-md"
                                  :class="prog.status === 'kích hoạt' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500'"
                                  x-text="prog.status"></span>
                        </div>
                        <h3 class="text-base font-black text-slate-800 leading-tight" x-text="prog.name"></h3>
                        <p class="text-sm font-medium text-slate-500 mt-1 flex items-center">
                            <i data-lucide="repeat" class="w-3.5 h-3.5 mr-1.5 text-slate-400 shrink-0"></i>
                            <span class="text-slate-700 font-bold" x-text="programSchedule(prog)"></span>
                        </p>
                        <p class="text-sm font-medium text-slate-500 mt-0.5 flex items-center">
                            <i data-lucide="clock" class="w-3.5 h-3.5 mr-1.5 text-slate-400 shrink-0"></i>
                            <span class="text-slate-700 font-bold" x-text="prog.startTime"></span>
                            <span class="text-slate-300 mx-1.5">•</span>
                            <span class="text-micro">trễ <span class="font-bold text-rose-500" x-text="prog.cutoffTime || prog.startTime"></span></span>
                            <template x-if="prog.absentTime"><span><span class="text-slate-300 mx-1.5">•</span><span class="text-micro">vắng <span class="font-bold text-slate-600" x-text="prog.absentTime"></span></span></span></template>
                        </p>
                    </div>

                    <div class="flex flex-col gap-2 shrink-0">
                        <button aria-label="Sửa chương trình" @click="openEditProgram(prog)" class="tap-safe w-8 h-8 bg-slate-50 rounded-full flex items-center justify-center text-slate-400 active:scale-90 border border-slate-200">
                            <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                        </button>
                        <button aria-label="Xóa chương trình" @click="deleteProgram(prog.id)" class="tap-safe w-8 h-8 bg-rose-50 rounded-full flex items-center justify-center text-rose-400 active:scale-90 border border-rose-100">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </div>

                <div class="bg-slate-50 rounded-2xl p-3.5 flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <div class="pr-3">
                            <p class="text-xs font-semibold text-slate-600">Kích hoạt chương trình</p>
                            <p class="text-micro text-slate-500 leading-tight mt-0.5">Tắt là đóng chương trình, GLV không điểm danh được nữa</p>
                        </div>
                        <button @click="toggleProgramStatus(prog)" type="button" role="switch" aria-label="Kích hoạt chương trình" :aria-checked="prog.status === 'kích hoạt' ? 'true' : 'false'" class="w-10 h-6 shrink-0 rounded-full relative transition-colors duration-200 ease-in-out" :class="prog.status === 'kích hoạt' ? 'bg-emerald-500' : 'bg-slate-300'">
                            <div class="w-4 h-4 bg-white rounded-full absolute top-1 shadow-sm transition-transform duration-200 ease-in-out" :class="prog.status === 'kích hoạt' ? 'translate-x-5' : 'translate-x-1'"></div>
                        </button>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="pr-3">
                            <p class="text-xs font-semibold text-slate-600">Tính điểm Chuyên cần</p>
                            <p class="text-micro text-slate-500 leading-tight mt-0.5">Buổi này có được cộng vào điểm chuyên cần cuối năm không</p>
                        </div>
                        <button @click="toggleProgramAttendance(prog)" type="button" role="switch" aria-label="Tính điểm chuyên cần cho buổi này" :aria-checked="prog.countForAttendance ? 'true' : 'false'" class="w-10 h-6 shrink-0 rounded-full relative transition-colors duration-200 ease-in-out" :class="prog.countForAttendance ? 'bg-blue-600' : 'bg-slate-300'">
                            <div class="w-4 h-4 bg-white rounded-full absolute top-1 shadow-sm transition-transform duration-200 ease-in-out" :class="prog.countForAttendance ? 'translate-x-5' : 'translate-x-1'"></div>
                        </button>
                    </div>
                </div>
            </div>
        </template>
        <div x-show="programs.length === 0" style="display: none;" class="text-center py-10 text-slate-500 text-sm">Chưa có chương trình nào.</div>
    </div>

    <!-- 3. POPUP THÊM/SỬA CHƯƠNG TRÌNH -->
    <div x-show="showProgramModal" style="display: none;" class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-4 md:p-6">
        <div x-show="showProgramModal" x-transition.opacity.duration.300ms @click="showProgramModal = false" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
        <div x-show="showProgramModal" x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0" class="modal-sheet relative w-full max-w-md sm:max-w-lg bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl flex flex-col h-[88dvh] sm:h-[80dvh] prog-modal">
            <!-- Header cố định -->
            <div class="flex justify-center pt-3 pb-2 shrink-0"><div class="w-12 h-1.5 bg-slate-200 rounded-full"></div></div>
            <div class="flex justify-between items-center px-5 pb-3 border-b border-slate-100 shrink-0">
                <h3 class="text-lg font-black text-slate-800" x-text="isEditingProgram ? 'Cập nhật chương trình' : 'Tạo chương trình mới'"></h3>
                <button aria-label="Đóng" @click="showProgramModal = false" class="tap-safe w-8 h-8 bg-slate-100 rounded-full text-slate-500 active:scale-90 flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <!-- Nội dung cuộn -->
            <div class="flex-1 overflow-y-auto p-5 space-y-4 oversc-contain">
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Tên Chương trình <span class="text-rose-500">*</span></label>
                    <input x-model="programForm.name" type="text" required placeholder="VD: Lễ Chúa Nhật..."
                           aria-required="true"
                           class="w-full bg-slate-50 border rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500"
                           :class="!programForm.name.trim() && showProgramModal ? 'border-rose-300 bg-rose-50' : 'border-slate-200'">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Giờ Bắt đầu <span class="text-rose-500">*</span></label>
                        <input x-model="programForm.startTime" type="time" required
                               aria-required="true"
                               class="w-full min-w-0 bg-slate-50 border rounded-xl px-3 py-2.5 text-sm text-slate-800"
                               :class="!programForm.startTime && showProgramModal ? 'border-rose-300 bg-rose-50' : 'border-slate-200'">
                    </div>
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Loại</label>
                        <select x-model="programForm.type" class="w-full min-w-0 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800">
                            <option value="bắt buộc">Bắt buộc</option>
                            <option value="chiến dịch">Chiến dịch</option>
                        </select>
                    </div>
                </div>

                <!-- Bắt buộc thì lặp hàng tuần, chiến dịch thì gắn vào một ngày cụ thể -->
                <div x-show="programForm.type === 'bắt buộc'">
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Lặp lại vào các thứ</label>
                    <div class="grid grid-cols-4 gap-2">
                        <template x-for="w in weekdays" :key="w.value">
                            <button type="button"
                                @click="programForm.daysOfWeek = programForm.daysOfWeek.includes(w.value) ? programForm.daysOfWeek.filter(d => d !== w.value) : [...programForm.daysOfWeek, w.value]"
                                class="py-2 rounded-xl text-xs font-bold border-2 transition-colors"
                                :class="programForm.daysOfWeek.includes(w.value) ? 'bg-blue-100 border-blue-500 text-blue-700' : 'bg-slate-50 border-slate-200 text-slate-500'"
                                x-text="w.value === 0 ? 'CN' : 'T' + (w.value + 1)"></button>
                        </template>
                    </div>
                    <p class="text-micro text-slate-500 mt-1 ml-1">Chọn một hoặc nhiều thứ (VD thi đua đi lễ cả tuần).</p>
                </div>
                <div x-show="programForm.type === 'chiến dịch'" style="display: none;">
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Ngày diễn ra</label>
                    <input x-model="programForm.eventDate" type="date" min="2000-01-01" max="2100-12-31" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>

                <!-- Mốc 1: GIỜ TÍNH ĐI TRỄ (bắt buộc). Trước đây gọi "giờ chốt
                     sổ" + để trống thì ngầm +30' — gây nhầm với mốc tính vắng. -->
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Giờ tính đi trễ <span class="text-rose-500">*</span></label>
                    <input x-model="programForm.cutoffTime" type="time" required aria-required="true"
                           class="w-full bg-slate-50 border rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500"
                           :class="!programForm.cutoffTime && showProgramModal ? 'border-rose-300 bg-rose-50' : 'border-slate-200'">
                    <p class="text-micro text-slate-500 mt-1 ml-1">Đến sau giờ này là <b>đi trễ</b> (vẫn điểm danh được).</p>
                </div>

                <!-- Mốc 2: GIỜ KHOÁ SỔ / TÍNH VẮNG (bắt buộc). Sau giờ này của
                     buổi thì không ghi điểm danh được nữa (attendance.php). -->
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Giờ khoá sổ (tính vắng) <span class="text-rose-500">*</span></label>
                    <input x-model="programForm.absentTime" type="time" required aria-required="true"
                           class="w-full bg-slate-50 border rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500"
                           :class="!programForm.absentTime && showProgramModal ? 'border-rose-300 bg-rose-50' : 'border-slate-200'">
                    <p class="text-micro text-slate-500 mt-1 ml-1">Sau giờ này <b>không điểm danh được nữa</b>, em vắng tính vắng.</p>
                </div>

                <!-- Khoảng ngày áp dụng (buổi lặp) -->
                <div x-show="programForm.type === 'bắt buộc'" class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Áp dụng từ</label>
                        <input x-model="programForm.effectiveFrom" type="date" class="w-full min-w-0 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800">
                    </div>
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Đến ngày</label>
                        <input x-model="programForm.effectiveTo" type="date" class="w-full min-w-0 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800">
                    </div>
                </div>

                <!-- Lớp áp dụng (bỏ trống = toàn đoàn) -->
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Lớp áp dụng</label>
                    <div class="max-h-32 overflow-y-auto border border-slate-200 rounded-xl p-2 space-y-1">
                        <template x-for="c in classes" :key="c.id">
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" :checked="programForm.classIds.includes(c.id)"
                                    @change="programForm.classIds = $event.target.checked ? [...programForm.classIds, c.id] : programForm.classIds.filter(x => x !== c.id)">
                                <span x-text="c.name"></span>
                                <span class="text-micro text-slate-500" x-text="c.block"></span>
                            </label>
                        </template>
                        <p x-show="!classes.length" class="text-micro text-slate-500 italic">Chưa có lớp nào.</p>
                    </div>
                    <p class="text-micro text-slate-500 mt-1 ml-1">Bỏ trống = áp dụng cho toàn đoàn.</p>
                </div>

                <!-- Các công tắc tùy chọn -->
                <div class="space-y-2">
                    <label class="flex items-center justify-between bg-slate-50 rounded-xl px-3 py-2.5">
                        <span class="text-sm font-medium text-slate-700">Tính chuyên cần</span>
                        <input type="checkbox" x-model="programForm.countForAttendance">
                    </label>
                    <label class="flex items-center justify-between bg-slate-50 rounded-xl px-3 py-2.5">
                        <span class="text-sm font-medium text-slate-700">Tính vào thi đua đi lễ</span>
                        <input type="checkbox" x-model="programForm.countForEmulation">
                    </label>
                    <label class="flex items-center justify-between bg-slate-50 rounded-xl px-3 py-2.5">
                        <span class="text-sm font-medium text-slate-700">Cho phép quét QR</span>
                        <input type="checkbox" x-model="programForm.allowQr">
                    </label>
                    <label x-show="programForm.type === 'chiến dịch'" class="flex items-center justify-between bg-slate-50 rounded-xl px-3 py-2.5">
                        <span class="text-sm font-medium text-slate-700">Tự đóng sau ngày diễn ra</span>
                        <input type="checkbox" x-model="programForm.autoCloseAfterEvent">
                    </label>
                </div>

                <!-- Màu nhãn + thứ tự hiển thị -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Màu nhãn</label>
                        <select x-model="programForm.color" class="w-full min-w-0 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800">
                            <option value="">Mặc định</option>
                            <option value="rose">Đỏ</option>
                            <option value="amber">Vàng</option>
                            <option value="emerald">Xanh lá</option>
                            <option value="blue">Xanh dương</option>
                            <option value="indigo">Tím</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Thứ tự hiển thị</label>
                        <input x-model.number="programForm.sortOrder" type="number" min="1" class="w-full min-w-0 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800">
                    </div>
                </div>
            </div>
            <!-- Nút Lưu cố định bên dưới -->
            <div class="p-4 border-t border-slate-100 shrink-0">
                <button @click="saveProgram()" type="button" class="w-full bg-blue-600 text-white font-bold py-3 rounded-2xl active:scale-[0.98] shadow-md shadow-blue-200 flex justify-center items-center">
                    <i data-lucide="save" class="w-5 h-5 mr-2"></i> Lưu Chương Trình
                </button>
            </div>
        </div>
    </div>
</div>