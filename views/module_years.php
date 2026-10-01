<!-- ==========================================================
     MÀN HÌNH NIÊN KHOÁ

     Tách khỏi màn Cài Đặt để vào thẳng từ Trang chủ. Đây là ranh
     giới của TOÀN BỘ dữ liệu nghiệp vụ — ghi danh, điểm danh, đơn
     phép, điểm số — nên ai cũng nên xem được mình đang ở niên khoá
     nào; chỉ Ban Điều Hành trở lên mới sửa.
     ========================================================== -->
<div data-module="years" class="module-panel pt-6 pb-24 relative">

    <!-- THANH ĐIỀU HƯỚNG -->
    <div class="flex items-center mb-5">
        <button aria-label="Quay lại trang chủ" @click="changeModule('dashboard')" class="tap-safe w-10 h-10 shrink-0 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-4">
            <i data-lucide="chevron-left" class="w-6 h-6 text-slate-600"></i>
        </button>
        <h2 class="text-xl font-black text-slate-800 tracking-tight">Niên Khoá</h2>
    </div>

    <!-- Cấp dưới Ban Điều Hành chỉ được xem -->
    <div x-show="!canEditModule('years')" style="display: none;" class="bg-slate-100 border border-slate-200 rounded-2xl p-3 mb-4 flex items-start gap-2.5">
        <i data-lucide="eye" class="w-4 h-4 text-slate-500 shrink-0 mt-0.5"></i>
        <p class="text-micro text-slate-600 leading-snug">
            Bạn đang ở chế độ <span class="font-bold">chỉ xem</span>. Chỉ Ban Điều Hành mới mở, sửa hay khoá niên khoá.
        </p>
    </div>


        <div class="bg-slate-100 border border-slate-200 rounded-2xl p-3 mb-4 flex items-start gap-2.5">
            <i data-lucide="info" class="w-4 h-4 text-slate-500 shrink-0 mt-0.5"></i>
            <p class="text-micro text-slate-600 leading-snug">
                Mọi dữ liệu — ghi danh, điểm danh, đơn phép, điểm số — đều thuộc về một niên khoá.
                Chỉ <span class="font-bold">một niên khoá được dùng</span> tại một thời điểm.
            </p>
        </div>

        <button @click="openCreateYear()"
                class="w-full mb-4 py-3 bg-blue-600 text-white rounded-field font-bold text-sm shadow-md shadow-blue-200 active:scale-[0.98] transition-transform flex items-center justify-center gap-2">
            <i data-lucide="plus" class="w-4 h-4"></i> Mở niên khoá mới
        </button>

        <div class="space-y-3">
            <template x-for="y in years" :key="y.id">
                <div class="bg-white rounded-card p-5 shadow-sm border"
                     :class="y.isCurrent ? 'border-blue-300 border-l-4 border-l-blue-600' : (y.status === 'đã khóa' ? 'border-slate-200 border-dashed' : 'border-slate-100')">

                    <div class="flex justify-between items-start gap-3 mb-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <span x-show="y.isCurrent" style="display: none;"
                                      class="text-micro font-bold uppercase tracking-wider px-2 py-0.5 rounded-md bg-blue-600 text-white">Đang dùng</span>
                                <span class="text-micro font-bold uppercase tracking-wider px-2 py-0.5 rounded-md border"
                                      :class="y.status === 'đang mở' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-slate-100 text-slate-500 border-slate-200'"
                                      x-text="y.status"></span>
                            </div>
                            <h3 class="text-base font-black leading-snug"
                                :class="y.status === 'đã khóa' ? 'text-slate-500' : 'text-slate-800'" x-text="y.name"></h3>
                            <p class="text-micro font-medium text-slate-500 mt-0.5"
                               x-text="formatDate(y.startDate) + ' → ' + formatDate(y.endDate)"></p>
                        </div>

                        <!-- Sửa được cả niên khoá ĐANG DÙNG: đầu năm hay phải
                             dời ngày khai giảng. Đã khoá sổ thì mới chặn. -->
                        <button x-show="y.status !== 'đã khóa'" style="display: none;"
                                @click="openEditYear(y)" type="button"
                                aria-label="Sửa niên khoá"
                                class="tap-safe shrink-0 w-9 h-9 rounded-xl bg-slate-50 border border-slate-200 text-slate-500 flex items-center justify-center active:scale-90 transition-transform">
                            <i data-lucide="pencil" class="w-4 h-4"></i>
                        </button>
                    </div>

                    <!-- Học kỳ -->
                    <div class="bg-slate-50 rounded-2xl p-3.5 mb-3 space-y-1.5">
                        <template x-for="t in y.terms" :key="t.id">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-slate-600" x-text="t.name"></span>
                                <span class="font-bold text-slate-700" x-text="formatDate(t.from) + ' – ' + formatDate(t.to)"></span>
                            </div>
                        </template>
                    </div>

                    <p class="text-micro text-slate-500 mb-3 ml-1" x-text="yearUsageLabel(y.usage)"></p>

                    <div class="grid grid-cols-2 gap-3">
                        <button @click="toggleYearLock(y)" :disabled="yearBusy"
                                class="py-2.5 rounded-xl font-bold text-xs border active:scale-95 transition-transform flex items-center justify-center gap-1.5 disabled:opacity-50"
                                :class="y.status === 'đang mở' ? 'bg-slate-100 text-slate-600 border-slate-200' : 'bg-emerald-50 text-emerald-600 border-emerald-100'">
                            <span x-show="y.status === 'đang mở'" class="inline-flex items-center justify-center"><i data-lucide="lock" class="w-3.5 h-3.5"></i></span>
                            <span x-show="y.status !== 'đang mở'" class="inline-flex items-center justify-center"><i data-lucide="lock-open" class="w-3.5 h-3.5"></i></span>
                            <span x-text="y.status === 'đang mở' ? 'Khoá sổ' : 'Mở lại'"></span>
                        </button>
                        <button @click="activateYear(y)" :disabled="yearBusy || y.isCurrent || y.status === 'đã khóa'"
                                class="py-2.5 rounded-xl font-bold text-xs border active:scale-95 transition-transform flex items-center justify-center gap-1.5 bg-blue-600 text-white border-blue-600 disabled:opacity-30 disabled:cursor-not-allowed">
                            <i data-lucide="check" class="w-3.5 h-3.5"></i>
                            <span x-text="y.isCurrent ? 'Đang dùng' : 'Chuyển sang'"></span>
                        </button>
                    </div>
                </div>
            </template>

            <div x-show="years.length === 0 && !yearBusy" style="display: none;" class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
                <i data-lucide="calendar-range" class="w-10 h-10 mx-auto text-slate-300 mb-3"></i>
                <p class="text-slate-500 font-medium text-sm">Chưa có niên khoá nào.</p>
            </div>
        </div>
    </div>

    <!-- POPUP MỞ NIÊN KHOÁ MỚI -->
    <div x-show="showYearModal" style="display: none;" class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-6">
        <div x-show="showYearModal" x-transition.opacity.duration.300ms @click="showYearModal = false" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
        <div x-show="showYearModal" x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0" class="modal-sheet relative w-full max-w-md sm:max-w-lg bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl flex flex-col max-h-[88dvh] overflow-y-auto">
            <div class="flex justify-center pt-3 pb-2"><div class="w-12 h-1.5 bg-slate-200 rounded-full"></div></div>
            <div class="flex justify-between items-center px-5 pb-4 border-b border-slate-100">
                <h3 class="text-lg font-black text-slate-800" x-text="yearFormTitle"></h3>
                <button aria-label="Đóng" @click="showYearModal = false" class="tap-safe w-8 h-8 bg-slate-100 rounded-full text-slate-500 active:scale-90 flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <div class="p-5 space-y-4">
                <div>
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Tên niên khoá</label>
                    <input x-model="yearForm.name" type="text" placeholder="VD: 2027 - 2028" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Khai giảng</label>
                        <input x-model="yearForm.startDate" type="date" min="2000-01-01" max="2100-12-31" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Bế giảng</label>
                        <input x-model="yearForm.endDate" type="date" min="2000-01-01" max="2100-12-31" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </div>
                </div>
                <p class="text-micro text-slate-500 leading-snug">
                    Hệ thống tự chia thành hai học kỳ theo mốc giữa. Bạn sửa lại được sau nếu giáo xứ chia khác.
                </p>
            </div>
            <div class="p-4 border-t border-slate-100">
                <button @click="saveYear()" type="button" :disabled="yearBusy" class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center disabled:opacity-50">
                    <i data-lucide="save" class="w-5 h-5 mr-2"></i>
                        <span x-text="yearBusy ? 'Đang lưu…' : (yearForm.id ? 'Lưu thay đổi' : 'Mở niên khoá')"></span>
                </button>
            </div>
        </div>
</div>
