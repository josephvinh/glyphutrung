<!-- MÀN HÌNH ĐIỂM DANH -->
<div x-show="currentModule === 'attendance'" style="display: none;" class="module-panel pt-6 pb-10 relative">

    <!-- ==========================================================
         BƯỚC 1: CHỌN BUỔI (chưa vào phiên điểm danh)
         ========================================================== -->
    <div x-show="activeSession === null" style="display: none;">

        <div class="flex items-center mb-6">
            <button aria-label="Quay lại trang chủ" @click="changeModule('dashboard')" class="w-10 h-10 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-4">
                <i data-lucide="chevron-left" class="w-6 h-6 text-slate-600"></i>
            </button>
            <h2 class="text-xl font-black text-slate-800 tracking-tight">Điểm Danh</h2>
        </div>

        <!-- CHỌN NGÀY -->
        <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100 mb-5">
            <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-2">Ngày điểm danh</label>
            <div class="flex items-center gap-2">
                <button aria-label="Lùi một ngày" @click="shiftAttendanceDate(-1)" class="w-10 h-10 shrink-0 bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-center text-slate-500 active:scale-90 transition-transform">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                </button>
                <input x-model="attendanceDate" type="date" class="flex-1 min-w-0 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <button aria-label="Tới một ngày" @click="shiftAttendanceDate(1)" class="w-10 h-10 shrink-0 bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-center text-slate-500 active:scale-90 transition-transform">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </button>
            </div>
            <p class="text-xs font-medium text-slate-500 mt-2 ml-1" x-text="formatFullDate(attendanceDate)"></p>
        </div>

        <!-- DANH SÁCH BUỔI TRONG NGÀY -->
        <!-- Skeleton loading state -->
        <div x-show="syncing && programs.length === 0" style="display: none;" class="space-y-4">
            <template x-for="i in 3" :key="'sk-' + i">
                <div class="bg-white rounded-card p-5 shadow-sm border border-slate-100">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="skeleton skeleton-badge"></div>
                        <div class="skeleton skeleton-badge"></div>
                    </div>
                    <div class="skeleton skeleton-title w-2/3 mb-4"></div>
                    <div class="bg-slate-50 rounded-2xl p-3.5 mb-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="skeleton w-4 h-4 rounded"></div>
                                <div>
                                    <div class="skeleton skeleton-text-sm w-16 mb-1"></div>
                                    <div class="skeleton skeleton-text w-12"></div>
                                </div>
                            </div>
                            <div class="skeleton w-px h-8"></div>
                            <div class="flex items-center gap-2">
                                <div class="skeleton w-4 h-4 rounded"></div>
                                <div>
                                    <div class="skeleton skeleton-text-sm w-16 mb-1"></div>
                                    <div class="skeleton skeleton-text w-12"></div>
                                </div>
                            </div>
                            <div class="skeleton w-px h-8"></div>
                            <div class="flex items-center gap-2">
                                <div class="skeleton w-4 h-4 rounded"></div>
                                <div>
                                    <div class="skeleton skeleton-text-sm w-16 mb-1"></div>
                                    <div class="skeleton skeleton-text w-8"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="skeleton skeleton-button"></div>
                </div>
            </template>
        </div>

        <!-- Actual program list -->
        <div x-show="!syncing || programs.length > 0" style="display: none;" class="space-y-4">
            <template x-for="prog in programsOnDate" :key="prog.id">
                <div class="bg-white rounded-card p-5 shadow-sm border border-slate-100">

                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-micro font-bold uppercase tracking-wider px-2 py-0.5 rounded-md"
                              :class="prog.type === 'bắt buộc' ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-600'"
                              x-text="prog.type"></span>
                        <span x-show="!prog.countForAttendance" style="display: none;" class="text-micro font-bold uppercase tracking-wider px-2 py-0.5 rounded-md bg-slate-100 text-slate-500">Không tính chuyên cần</span>
                    </div>

                    <h3 class="text-base font-black text-slate-800 leading-tight mb-3" x-text="prog.name"></h3>

                    <!-- Giờ bắt đầu & giờ chốt -->
                    <div class="bg-slate-50 rounded-2xl p-3.5 flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <i data-lucide="play" class="w-4 h-4 text-slate-400 mr-2"></i>
                            <div>
                                <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Bắt đầu</p>
                                <p class="text-sm font-black text-slate-700" x-text="prog.startTime"></p>
                            </div>
                        </div>
                        <div class="w-px h-8 bg-slate-200"></div>
                        <div class="flex items-center">
                            <i data-lucide="lock" class="w-4 h-4 text-slate-400 mr-2"></i>
                            <div>
                                <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Chốt sổ</p>
                                <p class="text-sm font-black text-rose-500" x-text="addMinutes(prog.startTime, CUTOFF_MINUTES)"></p>
                            </div>
                        </div>
                        <div class="w-px h-8 bg-slate-200"></div>
                        <div class="flex items-center">
                            <i data-lucide="user-check" class="w-4 h-4 text-slate-400 mr-2"></i>
                            <div>
                                <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Đã ghi</p>
                                <p class="text-sm font-black text-blue-600">
                                    <span x-text="sessionProgress(prog).done"></span><span class="text-slate-400 font-medium">/<span x-text="sessionProgress(prog).total"></span></span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <button @click="startSession(prog)" class="w-full bg-blue-600 text-white font-bold py-3 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center">
                        <i data-lucide="clipboard-check" class="w-5 h-5 mr-2"></i> Bắt đầu điểm danh
                    </button>
                </div>
            </template>
        </div>

        <!-- KHÔNG CÓ BUỔI NÀO -->
            <div x-show="programsOnDate.length === 0" style="display: none;" class="text-center py-12 px-6 bg-white rounded-card border border-slate-100 border-dashed">
                <i data-lucide="calendar-off" class="w-10 h-10 mx-auto text-slate-300 mb-3"></i>
                <p class="text-slate-500 font-medium text-sm mb-1">Ngày này không có chương trình nào.</p>
                <p class="text-slate-400 text-xs mb-4">Hầu hết chương trình rơi vào Chúa Nhật.</p>
                <button @click="goToNearestSunday()" class="px-5 py-2.5 bg-slate-100 text-slate-600 rounded-full font-bold text-xs active:scale-95 transition-transform border border-slate-200">
                    Xem Chúa Nhật gần nhất
                </button>
            </div>
        </div>
    </div>

    <!-- ==========================================================
         BƯỚC 2: PHIÊN ĐIỂM DANH
         ========================================================== -->
    <div x-show="activeSession !== null" style="display: none;">

        <!-- Đầu phiên -->
        <div class="flex items-start mb-5">
            <button aria-label="Thoát phiên điểm danh" @click="exitSession()" class="tap-safe w-10 h-10 shrink-0 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-4">
                <i data-lucide="chevron-left" class="w-6 h-6 text-slate-600"></i>
            </button>
            <div class="min-w-0">
                <h2 class="text-xl font-black text-slate-800 tracking-tight leading-tight truncate" x-text="sessionProgram ? sessionProgram.name : ''"></h2>
                <p class="text-xs font-medium text-slate-500 mt-0.5" x-text="formatFullDate(activeSession ? activeSession.date : '')"></p>
            </div>
        </div>

        <!-- Trạng thái giờ chốt -->
        <div class="rounded-card p-4 mb-4 border flex items-center"
             :class="isPastCutoff ? 'bg-rose-50 border-rose-100' : 'bg-emerald-50 border-emerald-100'">
            <div class="w-10 h-10 shrink-0 rounded-2xl flex items-center justify-center mr-3"
                 :class="isPastCutoff ? 'bg-rose-100 text-rose-600' : 'bg-emerald-100 text-emerald-600'">
                <i data-lucide="clock" class="w-5 h-5"></i>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-black leading-tight" :class="isPastCutoff ? 'text-rose-700' : 'text-emerald-700'"
                   x-text="isPastCutoff ? 'Đã quá giờ chốt (' + sessionCutoff + ')' : 'Còn trong giờ, chốt lúc ' + sessionCutoff"></p>
                <p class="text-micro leading-tight mt-0.5" :class="isPastCutoff ? 'text-rose-500' : 'text-emerald-600'"
                   x-text="isPastCutoff ? 'Chạm tên bây giờ sẽ ghi nhận Đi trễ' : 'Chạm tên để ghi nhận Có mặt'"></p>
            </div>
        </div>

        <!-- Bảng số liệu -->
        <div class="grid grid-cols-3 gap-3 mb-4">
            <div class="bg-white rounded-2xl p-3 shadow-sm border border-slate-100 text-center">
                <p class="text-2xl font-black text-emerald-600" x-text="sessionStats.present"></p>
                <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Có mặt</p>
            </div>
            <div class="bg-white rounded-2xl p-3 shadow-sm border border-slate-100 text-center">
                <p class="text-2xl font-black text-amber-700" x-text="sessionStats.late"></p>
                <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Đi trễ</p>
            </div>
            <div class="bg-white rounded-2xl p-3 shadow-sm border border-slate-100 text-center">
                <p class="text-2xl font-black text-slate-400" x-text="sessionStats.absent"></p>
                <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Chưa có</p>
            </div>
        </div>

        <!-- Hai chế độ điểm danh -->
        <div class="grid grid-cols-2 gap-3 mb-4">
            <button @click="moQuetQR()" type="button"
                    class="flex items-center justify-center gap-2 py-3 rounded-2xl border shadow-sm font-bold text-xs active:scale-95 transition-transform bg-white border-slate-200 text-slate-600">
                <i data-lucide="scan-line" class="w-4 h-4"></i> Quét QR
            </button>
            <button type="button" class="flex items-center justify-center gap-2 py-3 bg-blue-600 rounded-2xl border border-blue-600 shadow-md shadow-blue-200 text-white font-bold text-xs">
                <i data-lucide="hand" class="w-4 h-4"></i> Điểm danh tay
            </button>
        </div>

        <!-- Tìm kiếm -->
        <div class="relative mb-4">
            <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400"></i>
            <input x-model="attendanceSearch" type="text" placeholder="Gõ tên để lọc nhanh..." class="w-full bg-white border border-slate-200 rounded-field py-3.5 pl-12 pr-10 text-sm font-medium text-slate-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
            <button aria-label="Xóa ô tìm kiếm" x-show="attendanceSearch !== ''" @click="attendanceSearch = ''" style="display: none;" class="absolute right-3 top-1/2 -translate-y-1/2 w-6 h-6 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 active:scale-90 transition-transform">
                <i data-lucide="x" class="w-3.5 h-3.5"></i>
            </button>
        </div>

        <!-- DANH SÁCH ĐIỂM DANH: chạm 1 phát là đổi trạng thái -->
        <!-- Skeleton loading state -->
        <div x-show="syncing && accessibleStudents.length === 0" style="display: none;" class="space-y-2.5">
            <template x-for="i in 5" :key="'sk-' + i">
                <div class="bg-white rounded-field p-3.5 shadow-sm border border-slate-100 flex items-center gap-3">
                    <div class="skeleton w-11 h-11 rounded-2xl shrink-0"></div>
                    <div class="flex-1 min-w-0">
                        <div class="skeleton skeleton-text-sm w-24 mb-1"></div>
                        <div class="skeleton skeleton-text w-32"></div>
                    </div>
                    <div class="skeleton skeleton-badge shrink-0"></div>
                </div>
            </template>
        </div>

        <!-- Actual student list -->
        <div x-show="!syncing || accessibleStudents.length > 0" style="display: none;" class="space-y-2.5">
            <template x-for="student in sessionStudents" :key="student.id">
                <button @click="toggleAttendance(student)" type="button"
                        style="content-visibility: auto; contain-intrinsic-size: auto 84px;"
                        class="w-full text-left bg-white rounded-field p-3.5 shadow-sm border flex items-center gap-3 active:scale-[0.98] transition-all"
                        :class="attendanceRecord(student.id) ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-100'">

                    <!-- Ô tick -->
                    <div class="w-11 h-11 shrink-0 rounded-2xl flex items-center justify-center border-2 transition-colors"
                         :class="attendanceRecord(student.id)
                            ? (attendanceRecord(student.id).status === 'đi trễ' ? 'bg-amber-500 border-amber-500 text-white' : 'bg-emerald-500 border-emerald-500 text-white')
                            : 'bg-slate-50 border-slate-200 text-slate-300'">
                        <i data-lucide="check" class="w-5 h-5"></i>
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="text-micro font-bold text-blue-600 leading-tight">
                            <span x-text="student.code"></span>
                            <span class="text-slate-300 mx-1">•</span>
                            <span class="text-slate-400 font-medium" x-text="student.className"></span>
                        </p>
                        <p class="text-sm font-black text-slate-800 leading-snug">
                            <span class="font-normal text-slate-500" x-text="student.holyName"></span>
                            <span x-text="student.name"></span>
                        </p>
                    </div>

                    <div class="shrink-0 text-right">
                        <span class="text-micro font-bold uppercase tracking-wider px-2 py-1 rounded-lg border block"
                              :class="attendanceChipClass(studentSessionStatus(student.id))"
                              x-text="attendanceChipLabel(studentSessionStatus(student.id))"></span>
                        <span x-show="attendanceRecord(student.id)" style="display: none;" class="text-micro font-medium text-slate-500 mt-1 block"
                              x-text="attendanceRecord(student.id) ? attendanceRecord(student.id).markedAt : ''"></span>
                    </div>
                </button>
            </template>
        </div>

            <div x-show="sessionStudents.length === 0" style="display: none;" class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
                <i data-lucide="search-x" class="w-10 h-10 mx-auto text-slate-300 mb-3"></i>
                <p class="text-slate-500 font-medium text-sm">Không có em nào phù hợp.</p>
            </div>
        </div>

        <p class="text-center text-micro text-slate-500 mt-5 px-6 leading-relaxed">
            Chạm lần nữa vào tên đã ghi để gỡ ra nếu bấm nhầm.<br>
            Các em không được ghi nhận sẽ tự tính là vắng sau giờ chốt.
        </p>
    </div>

</div>

<!-- ==========================================================
     MÀN QUÉT QR

     Khung camera vuông, vừa phải — đủ ngắm mà vẫn thấy được số đã
     quét và nút Kết thúc mà không phải cuộn. Không chiếm hết màn
     hình vì GLV cần thấy mình đã ghi được bao nhiêu em.
     ========================================================== */ -->
<div x-show="qrMo" style="display: none;"
     class="fixed inset-0 z-[300] bg-slate-900/95 backdrop-blur-sm flex items-center justify-center p-4">

    <div class="w-full max-w-sm bg-white rounded-sheet shadow-2xl overflow-hidden flex flex-col max-h-[92dvh]">

        <!-- Đầu -->
        <div class="shrink-0 flex items-center justify-between px-4 py-3 border-b border-slate-100">
            <div class="min-w-0">
                <p class="font-black text-slate-800 text-sm leading-tight">Quét thẻ điểm danh</p>
                <p class="text-micro font-semibold text-slate-500 truncate" x-text="qrPhamVi"></p>
            </div>
            <button @click="dongQuetQR()" type="button" aria-label="Đóng, không lưu thêm"
                    class="tap-safe shrink-0 w-9 h-9 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-center active:scale-90 transition-transform">
                <i data-lucide="x" class="w-5 h-5 text-slate-500"></i>
            </button>
        </div>

        <!-- Khung camera: vuông, vừa phải -->
        <div class="relative w-full aspect-square bg-slate-900 shrink-0">
            <video x-ref="qrVideo" class="absolute inset-0 w-full h-full object-cover" muted playsinline></video>

            <!-- Vùng ngắm: khớp đúng phần được giải mã (72% cạnh ngắn) -->
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <div class="w-3/4 h-3/4 rounded-2xl border-4 border-white/90 qr-toi-xung-quanh"></div>
            </div>

            <p class="absolute left-3 right-3 bottom-3 text-center text-white font-bold text-xs bg-slate-900/75 rounded-xl px-3 py-2 backdrop-blur-sm truncate"
               x-text="qrTrangThai"></p>
        </div>

        <!-- Số đếm + vài em gần nhất -->
        <div class="flex-1 min-h-0 overflow-y-auto px-4 py-3">
            <div class="flex items-center justify-between mb-2">
                <p class="text-micro font-bold text-slate-400 uppercase tracking-wider">Đã quét</p>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl font-black text-emerald-600" x-text="qrDaQuet"></span>
                    <span class="text-micro font-semibold text-slate-400">em</span>
                    <span x-show="qrDangGui > 0" style="display: none;"
                          class="ml-1 text-micro font-bold text-amber-600"
                          x-text="'· đang gửi ' + qrDangGui"></span>
                </div>
            </div>

            <div x-show="qrVuaGhi.length > 0" style="display: none;" class="space-y-1.5">
                <template x-for="v in qrVuaGhi" :key="v.id">
                    <div class="flex items-center gap-2 bg-emerald-50 border border-emerald-100 rounded-xl px-3 py-1.5">
                        <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-600 shrink-0"></i>
                        <span class="flex-1 min-w-0 truncate">
                            <span class="text-xs font-bold text-slate-700" x-text="v.ten"></span>
                            <span class="text-micro font-semibold text-slate-400" x-text="v.lop ? ' · ' + v.lop : ''"></span>
                        </span>
                        <span class="shrink-0 text-micro font-semibold text-slate-400" x-text="v.luc"></span>
                    </div>
                </template>
            </div>
            <p x-show="qrVuaGhi.length === 0" class="text-center text-slate-400 text-micro font-semibold py-3">
                Đưa thẻ của em vào khung
            </p>
        </div>

        <!-- Kết thúc -->
        <div class="shrink-0 p-3 border-t border-slate-100"
             style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px))">
            <button @click="ketThucQuet()" type="button"
                    class="w-full bg-slate-800 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform flex justify-center items-center gap-2">
                <i data-lucide="check-check" class="w-5 h-5"></i>
                Kết thúc quét
            </button>
        </div>
    </div>
</div>
