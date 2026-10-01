<?php /* Popup thêm/sửa hồ sơ thiếu nhi — nằm NGOÀI các <template x-if> của từng module
   để mở được từ cả Danh sách lẫn Hồ sơ thiếu nhi. */ ?>
    <!-- POPUP CHỈNH SỬA -->
    <div x-show="showEditModal" style="display: none;" class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-6"
         @keydown.window.ctrl.s.prevent="if(showEditModal) saveEdit()"
         @input.window="if(showEditModal) scheduleDraftSave()">
        <div x-show="showEditModal" x-transition.opacity.duration.300ms @click="tryCloseEdit()" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
        <div x-show="showEditModal" x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0" class="modal-sheet relative w-full max-w-md sm:max-w-lg bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl h-[88dvh] sm:h-[80dvh] flex flex-col overflow-hidden">
            <div class="flex justify-center pt-3 pb-2 bg-white"><div class="w-12 h-1.5 bg-slate-200 rounded-full"></div></div>
            <div class="flex justify-between items-center px-5 pb-4 border-b border-slate-100 bg-white">
                <div class="flex items-center gap-2">
                    <h3 class="text-lg font-black text-slate-800" x-text="editModalTitle"></h3>
                    <!-- Draft indicator -->
                    <div x-show="hasDraft" style="display: none;" class="text-xs text-amber-600 flex items-center gap-1">
                        <i data-lucide="clock" class="w-3 h-3"></i>
                        <span>Đã lưu nháp</span>
                        <span x-text="'(' + getDraftAge() + ')'"></span>
                    </div>
                </div>
                <button aria-label="Đóng" @click="tryCloseEdit()" class="tap-safe w-8 h-8 flex items-center justify-center bg-slate-100 rounded-full text-slate-500 active:scale-90 transition-transform"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <div class="flex-1 overflow-y-auto p-5 space-y-4">
                <input type="hidden" name="_csrf" :value="window.TNTT.csrfToken">
                <!-- Mã số: máy chủ tự cấp, không sửa được -->
                <div class="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2">
                    <i data-lucide="hash" class="w-4 h-4 text-slate-500 shrink-0"></i>
                    <span class="text-micro font-bold text-slate-500 uppercase">Mã số</span>
                    <span class="ml-auto text-sm font-black text-blue-600 tracking-wide" x-text="editData.code"></span>
                    <span x-show="editData.isNew" style="display: none;" class="text-micro text-slate-500">(tự cấp)</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div><label class="block text-micro font-bold text-slate-500 uppercase mb-1">Tên Thánh</label><input x-model="editData.holyName" type="text" autocomplete="off" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></div>
                    <div class="col-span-2"><label class="block text-micro font-bold text-slate-500 uppercase mb-1">Họ và Tên</label><input x-model="editData.name" type="text" autocomplete="off" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-micro font-bold text-slate-500 uppercase mb-1">Ngày Sinh</label><input x-model="editData.birthDate" type="date" min="1900-01-01" max="<?php echo date('Y-m-d'); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></div>
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Giới tính</label>
                        <select x-model.number="editData.gender" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <option :value="1">Nam</option>
                            <option :value="0">Nữ</option>
                        </select>
                    </div>
                </div>
                <div><label class="block text-micro font-bold text-slate-500 uppercase mb-1">Địa chỉ</label><input x-model="editData.address" type="text" autocomplete="off" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></div>
                <div class="grid grid-cols-2 gap-3 border-t border-slate-100 pt-4">
                    <div><label class="block text-micro font-bold text-slate-500 uppercase mb-1">Tên Cha</label><input x-model="editData.fatherName" type="text" autocomplete="off" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800"></div>
                    <div><label class="block text-micro font-bold text-slate-500 uppercase mb-1">SĐT Cha</label><input x-model="editData.fatherPhone" type="tel" autocomplete="off" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800"></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-micro font-bold text-slate-500 uppercase mb-1">Tên Mẹ</label><input x-model="editData.motherName" type="text" autocomplete="off" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800"></div>
                    <div><label class="block text-micro font-bold text-slate-500 uppercase mb-1">SĐT Mẹ</label><input x-model="editData.motherPhone" type="tel" autocomplete="off" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800"></div>
                </div>
                <div class="grid grid-cols-2 gap-3 border-t border-slate-100 pt-4 pb-6">
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Lớp</label>
                        <select x-model="editData.className" :disabled="!canEditModule('students')" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 disabled:opacity-60">
                            <!-- Chỉ lớp mình được ghi vào. Chọn lớp ngoài phạm vi
                                 thì máy chủ cũng từ chối, liệt kê ra chỉ tổ gây hụt hẫng. -->
                            <template x-for="ten in (writableClasses === null ? classes.map(c => c.name) : writableClasses)" :key="ten">
                                <option :value="ten" x-text="ten"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Tình trạng</label>
                        <select x-model="editData.status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <option value="đang sinh hoạt">Đang sinh hoạt</option>
                            <option value="dừng sinh hoạt">Dừng sinh hoạt</option>
                            <option value="chuyển xứ">Chuyển xứ</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="shrink-0 p-4 border-t border-slate-100 flex gap-3 bg-white"
                 style="padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0px))">
                <button @click="tryCloseEdit()" type="button" class="flex-1 py-3.5 bg-slate-100 text-slate-700 font-bold rounded-2xl active:scale-[0.98] transition-transform">
                    Hủy
                </button>
                <button @click="saveEdit()" type="button" :disabled="busy" class="flex-1 py-3.5 bg-blue-600 text-white font-bold rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 disabled:opacity-50">
                    <span x-text="busy ? 'Đang lưu...' : 'Lưu thay đổi'"></span>
                </button>
            </div>
        </div>
    </div>
