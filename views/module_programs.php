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
                            <span class="text-micro">chốt <span class="font-bold text-rose-500" x-text="prog.cutoffTime || addMinutes(prog.startTime, CUTOFF_MINUTES)"></span></span>
                        </p>
                    </div>

                    <div class="flex flex-col gap-2 shrink-0">
                        <button aria-label="Sửa chương trình" @click="openEditProgram(prog)" class="tap-safe w-8 h-8 bg-slate-50 rounded-full flex items-center justify-center text-slate-400 active:scale-90 border border-slate-200">
                            <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                        </button>
                        <button aria-label="Xóa chương trình" @click="deleteProgram(prog.id)" class="tap-safe w-8 h-8 bg-red-50 rounded-full flex items-center justify-center text-red-400 active:scale-90 border border-red-100">
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
    <div x-show="showProgramModal" style="display: none;" class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-6">
        <div x-show="showProgramModal" x-transition.opacity.duration.300ms @click="showProgramModal = false" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
        <div x-show="showProgramModal" x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0" class="modal-sheet relative w-full max-w-md sm:max-w-lg bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl flex flex-col max-h-[88dvh] overflow-y-auto">
            <div class="flex justify-center pt-3 pb-2"><div class="w-12 h-1.5 bg-slate-200 rounded-full"></div></div>
            <div class="flex justify-between items-center px-5 pb-4 border-b border-slate-100">
                <h3 class="text-lg font-black text-slate-800" x-text="isEditingProgram ? 'Cập nhật chương trình' : 'Tạo chương trình mới'"></h3>
                <button aria-label="Đóng" @click="showProgramModal = false" class="tap-safe w-8 h-8 bg-slate-100 rounded-full text-slate-500 active:scale-90 flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <div class="p-5 space-y-4">
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Tên Chương trình</label>
                    <input x-model="programForm.name" type="text" placeholder="VD: Lễ Chúa Nhật..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Giờ Bắt đầu</label>
                        <input x-model="programForm.startTime" type="time" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800">
                    </div>
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Loại</label>
                        <select x-model="programForm.type" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800">
                            <option value="bắt buộc">Bắt buộc</option>
                            <option value="chiến dịch">Chiến dịch</option>
                        </select>
                    </div>
                </div>

                <!-- Bắt buộc thì lặp hàng tuần, chiến dịch thì gắn vào một ngày cụ thể -->
                <div x-show="programForm.type === 'bắt buộc'">
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Lặp lại vào</label>
                    <select x-model.number="programForm.dayOfWeek" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800">
                        <template x-for="w in weekdays" :key="w.value">
                            <option :value="w.value" x-text="w.label + ' hàng tuần'"></option>
                        </template>
                    </select>
                </div>
                <div x-show="programForm.type === 'chiến dịch'" style="display: none;">
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Ngày diễn ra</label>
                    <input x-model="programForm.eventDate" type="date" min="2000-01-01" max="2100-12-31" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>

                <!-- Giờ chốt sổ: BĐH nhập trực tiếp. Để trống thì mặc định
                     giờ bắt đầu + CUTOFF_MINUTES phút (hiện gợi ý bên dưới). -->
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Giờ chốt sổ</label>
                    <input x-model="programForm.cutoffTime" type="time" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <p class="text-micro text-slate-500 mt-1 ml-1">
                        Để trống = giờ bắt đầu + <span x-text="CUTOFF_MINUTES"></span> phút
                        <span x-show="programForm.startTime && !programForm.cutoffTime">(<span class="font-bold text-rose-500" x-text="addMinutes(programForm.startTime, CUTOFF_MINUTES)"></span>)</span>
                    </p>
                </div>
            </div>
            <div class="p-4 border-t border-slate-100">
                <button @click="saveProgram()" type="button" class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] shadow-md shadow-blue-200 flex justify-center items-center">
                    <i data-lucide="save" class="w-5 h-5 mr-2"></i> Lưu Chương Trình
                </button>
            </div>
        </div>
    </div>
</div>