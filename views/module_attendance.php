<!-- MÀN HÌNH ĐIỂM DANH -->
<div data-module="attendance" class="module-panel pt-6 pb-24 relative">
<?php include __DIR__ . '/partial_heavy_loading.php'; ?>

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
                <input x-model="attendanceDate" type="date" min="2000-01-01" max="2100-12-31" class="flex-1 min-w-0 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <button aria-label="Tới một ngày" @click="shiftAttendanceDate(1)" class="w-10 h-10 shrink-0 bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-center text-slate-500 active:scale-90 transition-transform">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </button>
            </div>
            <p class="text-xs font-medium text-slate-500 mt-2 ml-1" x-text="formatFullDate(attendanceDate)"></p>
        </div>

        <!-- XUẤT BÁO CÁO CSV -->
        <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100 mb-5">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-700">Xuất Báo Cáo CSV</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Tải danh sách điểm danh theo lớp và khoảng thời gian</p>
                </div>
                <button @click="openExportCSVModal()" type="button"
                        class="shrink-0 px-4 py-2 bg-emerald-600 text-white rounded-xl font-bold text-xs flex items-center gap-2 active:scale-95 transition-transform">
                    <i data-lucide="download" class="w-4 h-4"></i> Xuất CSV
                </button>
            </div>
        </div>

        <!-- MODAL XUẤT CSV -->
        <div x-show="showExportCSVModal" style="display: none;"
             x-on:keydown.escape.window="showExportCSVModal = false"
             class="fixed inset-0 z-[200] bg-black/50 flex items-center justify-center p-4"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                 @click.stop>
                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                    <div>
                        <h3 class="font-black text-slate-800 text-base">Xuất Báo Cáo Điểm Danh</h3>
                        <p class="text-micro text-slate-500 mt-0.5">Mỗi dòng là một bản ghi điểm danh</p>
                    </div>
                    <button @click="showExportCSVModal = false" type="button"
                            class="w-9 h-9 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-center active:scale-90 transition-transform">
                        <i data-lucide="x" class="w-5 h-5 text-slate-500"></i>
                    </button>
                </div>

                <!-- Form -->
                <div class="px-5 py-4 space-y-4">
                    <!-- Chọn lớp -->
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-2">Lớp</label>
                        <select x-model="exportCSV.classId"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <option value="">-- Toàn đoàn --</option>
                            <template x-for="cls in availableClasses" :key="cls">
                                <option :value="cls" x-text="cls"></option>
                            </template>
                        </select>
                    </div>

                    <!-- Từ ngày -->
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-2">Từ ngày</label>
                        <input x-model="exportCSV.fromDate" type="date"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </div>

                    <!-- Đến ngày -->
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-2">Đến ngày</label>
                        <input x-model="exportCSV.toDate" type="date"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </div>

                    <!-- Validation error -->
                    <p x-show="exportCSV.error" style="display: none;"
                       class="text-xs text-rose-600 font-semibold" x-text="exportCSV.error"></p>
                </div>

                <!-- Footer -->
                <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-end gap-3"
                     style="padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0px))">
                    <button @click="showExportCSVModal = false" type="button"
                            class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl font-bold text-sm active:scale-95 transition-transform">
                        Huỷ
                    </button>
                    <button @click="exportAttendanceCSV()" type="button"
                            :disabled="exportCSV.loading"
                            :class="exportCSV.loading ? 'opacity-50' : ''"
                            class="px-5 py-2 bg-emerald-600 text-white rounded-xl font-bold text-sm active:scale-95 transition-transform flex items-center gap-2">
                        <span x-show="!exportCSV.loading"><i data-lucide="download" class="w-4 h-4"></i> Tải về</span>
                        <span x-show="exportCSV.loading" style="display: none;">Đang tải...</span>
                    </button>
                </div>
            </div>
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
                                <p class="text-sm font-black text-rose-500" x-text="cutoffOf(prog)"></p>
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

                    <button @click="startSession(prog)" type="button" :disabled="!heavyLoaded" :class="!heavyLoaded ? 'opacity-50' : ''" class="w-full bg-blue-600 text-white font-bold py-3 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center">
                        <i data-lucide="clipboard-check" class="w-5 h-5 mr-2"></i> Bắt đầu điểm danh
                    </button>
                </div>
            </template>

            <!-- BUỔI CHƯA TỚI GIỜ BẮT ĐẦU — hiện mờ, chưa mở được (tránh quét nhầm buổi) -->
            <template x-for="prog in pendingProgramsOnDate" :key="'pending-' + prog.id">
                <div class="bg-slate-50 rounded-card p-5 border border-slate-200 border-dashed opacity-80">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-micro font-bold uppercase tracking-wider px-2 py-0.5 rounded-md bg-slate-200 text-slate-500">Chưa tới giờ</span>
                        <span class="text-micro font-bold uppercase tracking-wider px-2 py-0.5 rounded-md"
                              :class="prog.type === 'bắt buộc' ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-600'"
                              x-text="prog.type"></span>
                    </div>
                    <h3 class="text-base font-black text-slate-600 leading-tight mb-3" x-text="prog.name"></h3>
                    <div class="flex items-center gap-2 text-slate-500">
                        <i data-lucide="clock" class="w-4 h-4 shrink-0"></i>
                        <p class="text-sm font-semibold">
                            Buổi sẽ mở lúc <span class="font-black text-slate-700" x-text="prog.startTime"></span>
                        </p>
                    </div>
                    <p class="text-micro text-slate-400 mt-2 leading-relaxed">Buổi chỉ mở để điểm danh khi tới giờ bắt đầu, tránh quét nhầm sang buổi khác.</p>
                </div>
            </template>

            <!-- KHÔNG CÓ BUỔI NÀO -->
            <div x-show="programsOnDate.length === 0 && pendingProgramsOnDate.length === 0" style="display: none;" class="text-center py-12 px-6 bg-white rounded-card border border-slate-100 border-dashed">
                <i data-lucide="calendar-x" class="w-12 h-12 mx-auto text-slate-300 mb-4"></i>
                <p class="text-slate-600 font-semibold text-base mb-1">Ngày này không có chương trình nào</p>
                <p class="text-slate-400 text-sm mb-4">Hầu hết chương trình rơi vào Chúa Nhật</p>
                <button @click="goToNearestSunday()" type="button" class="px-5 py-2.5 bg-blue-50 text-blue-600 rounded-full font-bold text-xs active:scale-95 transition-transform border border-blue-100 hover:bg-blue-100">
                    Xem Chúa Nhật gần nhất
                </button>
            </div>
        </div>
    </div>

    <!-- ==========================================================
         BƯỚC 2: PHIÊU ĐIỂM DANH
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
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
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
                <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Vắng</p>
            </div>
        </div>

        <!-- Hai lối điểm danh: "Quét QR" mở máy quét (một hành động, không
             phải tab), "Điểm danh tay" là màn đang xem. Trước đây gắn
             role="tab"/aria-selected cứng nên trình đọc màn hình báo sai
             trạng thái — bỏ đi, để chúng là hai nút hành động bình thường. -->
        <div class="grid grid-cols-2 gap-3 mb-4">
            <button @click="moQuetQR()" type="button"
                    class="flex items-center justify-center gap-2 py-3 rounded-2xl border shadow-sm font-bold text-xs active:scale-95 transition-transform bg-white border-slate-200 text-slate-600">
                <i data-lucide="scan-line" class="w-4 h-4"></i> Quét QR
            </button>
            <button @click="attendanceMode = 'manual'" type="button" aria-current="page"
                    class="flex items-center justify-center gap-2 py-3 bg-blue-600 rounded-2xl border border-blue-600 shadow-md shadow-blue-200 text-white font-bold text-xs">
                <i data-lucide="hand" class="w-4 h-4"></i> Điểm danh tay
            </button>
        </div>

        <!-- CHỌN LỚP + TÌM NHANH — cùng kiểu bộ lọc của Danh sách (thanh + nút phễu) -->
        <?php $scopeClassModel = 'attendanceClass'; $scopeSearchModel = 'attendanceSearch'; include __DIR__ . '/partial_scope_filter.php'; ?>

        <!-- Chưa chọn lớp: mời chọn, KHÔNG đổ cả đoàn ra (giống Danh sách).
             Quét QR vẫn dùng được vì chạy theo khối. -->
        <div x-show="attendanceClass === '' && attendanceSearch === ''" style="display: none;" class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
            <i data-lucide="filter" class="w-12 h-12 mx-auto text-slate-300 mb-4"></i>
            <p class="text-slate-600 font-semibold text-base mb-1">Chọn lớp để điểm danh</p>
            <p class="text-slate-400 text-sm">Bấm nút lọc <i data-lucide="filter" class="inline w-3.5 h-3.5 -mt-0.5"></i> để chọn lớp, hoặc gõ tên để tìm nhanh. Quét QR thì không cần chọn lớp.</p>
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
                            <span class="text-slate-400 mx-1">•</span>
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

            <div x-show="sessionStudents.length === 0 && !(attendanceClass === '' && attendanceSearch === '')" style="display: none;" class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
                <i data-lucide="users-x" class="w-12 h-12 mx-auto text-slate-300 mb-4"></i>
                <p class="text-slate-600 font-semibold text-base mb-1">Không có em nào phù hợp</p>
                <p class="text-slate-400 text-sm">Hãy kiểm tra lại phạm vi điểm danh hoặc danh sách lớp</p>
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
     ========================================================== -->
<div x-show="qrMo" style="display: none;"
     class="fixed inset-0 z-[300] bg-slate-900/95 backdrop-blur-sm flex items-center justify-center p-4">

    <div class="w-full max-w-sm bg-white rounded-sheet shadow-2xl overflow-hidden flex flex-col max-h-[90dvh]">

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
        <div class="flex-1 min-h-0 overflow-y-auto px-4 py-3 max-h-48">
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
