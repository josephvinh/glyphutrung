<!-- ==========================================================
     HEADER HỒ SƠ THIẾU NHI
     Hiển thị ngắn gọn tên, mã số, và nút thao tác
     ========================================================== -->
<div class="flex items-center justify-between mb-4">
    <div class="flex items-center">
        <button aria-label="Quay lại danh sách" @click="changeModule('students')"
                class="tap-safe w-10 h-10 shrink-0 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-4">
            <i data-lucide="chevron-left" class="w-6 h-6 text-slate-600"></i>
        </button>
        <h2 class="text-xl font-black text-slate-800 tracking-tight">Hồ sơ thiếu nhi</h2>
    </div>
    <button x-show="canEditModule('students')" style="display: none;"
            @click="openEdit(profileStudent)" type="button"
            class="tap-safe flex items-center gap-1.5 px-3 py-2 bg-blue-50 hover:bg-blue-100 text-blue-600 rounded-xl font-bold text-sm active:scale-95 transition-colors border border-blue-100">
        <i data-lucide="pencil" class="w-4 h-4"></i>
        <span class="hidden sm:inline">Sửa hồ sơ</span>
    </button>
</div>

<!-- THÔNG TIN CƠ BẢN (NGẮN GỌN) -->
<div class="bg-white rounded-card p-4 shadow-sm border border-slate-100 mb-5">
    <div class="flex gap-4 items-center">
        <!-- QR Code nhỏ -->
        <div class="shrink-0 w-16 h-16 bg-white rounded-xl border border-slate-200 flex items-center justify-center overflow-hidden">
            <div x-show="profileStudent" style="display: none;" class="w-full h-full flex items-center justify-center">
                <div x-show="qrReady" class="w-12 h-12 flex items-center justify-center" x-html="qrReady && profileStudent ? qrSvg(profileStudent.code) : ''"></div>
                <svg x-show="!qrReady" xmlns="http://www.w3.org/2000/svg" width="48" height="48" class="w-12 h-12 object-contain">
                    <rect fill="white" width="48" height="48"/>
                    <text x="50%" y="50%" text-anchor="middle" dy=".3em" font-size="8" fill="#94a3b8">QR</text>
                </svg>
            </div>
        </div>

        <!-- Thông tin định danh -->
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
                <p class="text-xs font-bold text-blue-600 truncate">
                    <span x-text="profileStudent ? profileStudent.code : ''"></span>
                    <span class="text-slate-300 mx-1">•</span>
                    <span x-text="profileStudent ? profileStudent.className : ''"></span>
                </p>
                <span class="shrink-0 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md"
                      :class="{'bg-emerald-50 text-emerald-600': profileStudent && profileStudent.status === 'đang sinh hoạt', 'bg-rose-50 text-rose-600': profileStudent && profileStudent.status === 'dừng sinh hoạt', 'bg-slate-100 text-slate-500': profileStudent && profileStudent.status === 'chuyển xứ'}"
                      x-text="profileStudent ? profileStudent.status : ''"></span>
            </div>
            <h3 class="text-lg font-black text-slate-800 leading-tight">
                <span x-text="profileStudent ? profileStudent.holyName : ''" class="font-normal text-slate-500 text-xs block mb-0.5"></span>
                <span x-text="profileStudent ? profileStudent.name : ''"></span>
            </h3>
        </div>
    </div>
</div>
