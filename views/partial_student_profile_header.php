<!-- ==========================================================
     HEADER HỒ SƠ THIẾU NHI
     Hiển thị khi đang xem hồ sơ 1 em: thông tin cơ bản + QR nhỏ
     ========================================================== -->
<div class="flex items-center mb-4">
    <button aria-label="Quay lại danh sách" @click="changeModule('students')"
            class="tap-safe w-10 h-10 shrink-0 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-4">
        <i data-lucide="chevron-left" class="w-6 h-6 text-slate-600"></i>
    </button>
    <h2 class="text-xl font-black text-slate-800 tracking-tight">Hồ sơ thiếu nhi</h2>
</div>

<!-- THÔNG TIN CƠ BẢN -->
<div class="bg-white rounded-card p-5 shadow-sm border border-slate-100 mb-5">
    <div class="flex gap-4">
        <!-- QR Code nhỏ -->
        <div class="shrink-0 w-20 h-20 bg-white rounded-2xl border border-slate-200 flex items-center justify-center overflow-hidden">
            <div x-show="profileStudent" style="display: none;" class="w-full h-full flex items-center justify-center">
                <img :src="'data:image/svg+xml;base64,' + btoa(qrSvg(profileStudent.code))"
                     :alt="'QR ' + profileStudent.code"
                     class="w-16 h-16 object-contain"
                     onerror="this.outerHTML='<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'64\' height=\'64\'><rect fill=\'white\' width=\'64\' height=\'64\'/><text x=\'50%\' y=\'50%\' text-anchor=\'middle\' dy=\'.3em\' font-size=\'8\' fill=\'#94a3b8\'>QR</text></svg>'">
            </div>
        </div>

        <!-- Thông tin em -->
        <div class="flex-1 min-w-0">
            <p class="text-xs font-bold text-blue-600 mb-1">
                <span x-text="profileStudent ? profileStudent.code : ''"></span>
                <span class="text-slate-300 mx-1">•</span>
                <span x-text="profileStudent ? profileStudent.className : ''"></span>
                <span class="text-slate-300 mx-1">•</span>
                <span x-text="profileStudent ? profileStudent.block : ''"></span>
            </p>
            <h3 class="text-lg font-black text-slate-800 leading-tight mb-2">
                <span x-text="profileStudent ? profileStudent.holyName : ''" class="font-normal text-slate-500"></span>
                <span x-text="profileStudent ? profileStudent.name : ''"></span>
            </h3>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-600">
                <span class="flex items-center gap-1">
                    <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i>
                    <span x-text="profileStudent ? formatDate(profileStudent.birthDate) : ''"></span>
                </span>
                <span class="text-slate-300">•</span>
                <span :class="profileStudent && profileStudent.gender === 1 ? 'text-blue-600' : 'text-rose-500'"
                      x-text="profileStudent ? (profileStudent.gender === 1 ? 'Nam' : 'Nữ') : ''"></span>
                <span class="text-slate-300">•</span>
                <span class="flex items-center gap-1">
                    <i data-lucide="map-pin" class="w-3.5 h-3.5 text-slate-400"></i>
                    <span x-text="profileStudent ? profileStudent.address : ''" class="truncate max-w-[200px]"></span>
                </span>
            </div>
        </div>
    </div>

    <!-- Thông tin cha mẹ -->
    <div class="grid grid-cols-2 gap-3 mt-4 pt-4 border-t border-slate-100">
        <div class="flex items-center justify-between bg-blue-50 rounded-xl px-3 py-2.5">
            <div>
                <p class="text-micro font-bold text-blue-400 uppercase tracking-wide">Cha</p>
                <p class="text-sm font-semibold text-slate-700 truncate" x-text="profileStudent ? profileStudent.fatherName : ''"></p>
                <p class="text-xs text-blue-600" x-text="profileStudent ? profileStudent.fatherPhone : ''"></p>
            </div>
            <a :href="'tel:' + (profileStudent ? profileStudent.fatherPhone : '')"
               class="w-9 h-9 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 active:scale-90 transition-transform">
                <i data-lucide="phone" class="w-4 h-4"></i>
            </a>
        </div>
        <div class="flex items-center justify-between bg-rose-50 rounded-xl px-3 py-2.5">
            <div>
                <p class="text-micro font-bold text-rose-300 uppercase tracking-wide">Mẹ</p>
                <p class="text-sm font-semibold text-slate-700 truncate" x-text="profileStudent ? profileStudent.motherName : ''"></p>
                <p class="text-xs text-rose-500" x-text="profileStudent ? profileStudent.motherPhone : ''"></p>
            </div>
            <a :href="'tel:' + (profileStudent ? profileStudent.motherPhone : '')"
               class="w-9 h-9 bg-rose-100 rounded-full flex items-center justify-center text-rose-500 active:scale-90 transition-transform">
                <i data-lucide="phone" class="w-4 h-4"></i>
            </a>
        </div>
    </div>

    <!-- Nút sửa hồ sơ -->
    <div class="mt-4 pt-4 border-t border-slate-100 flex justify-end">
        <button x-show="canEditModule('students')" style="display: none;"
                @click="openEdit(profileStudent)" type="button"
                class="flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white rounded-xl font-bold text-xs active:scale-95 transition-transform border border-blue-600 shadow-md shadow-blue-200">
            <i data-lucide="pencil" class="w-4 h-4"></i> Sửa hồ sơ
        </button>
    </div>
</div>
