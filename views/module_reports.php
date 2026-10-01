<!-- MÀN HÌNH SỔ LIÊN LẠC -->
<div data-module="reports" class="module-panel pt-6 pb-24 relative">
<?php include __DIR__ . '/partial_heavy_loading.php'; ?>

    <!-- 1. THANH ĐIỀU HƯỚNG GỘP (Thiếu nhi) -->
    <?php include __DIR__ . '/partial_children_tabs.php'; ?>

    <div class="flex items-center justify-between gap-2 mb-4">
        <!-- Select All Checkbox -->
        <label x-show="reportClass !== ''" style="display: none;" class="flex items-center gap-2 text-sm font-semibold text-slate-700 cursor-pointer pl-1">
            <input type="checkbox" @change="toggleAllReports($event)" :checked="selectedReports.length === reportStudents.length && reportStudents.length > 0" class="w-5 h-5 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
            Chọn tất cả
        </label>
        <div x-show="reportClass === ''"></div>

        <button @click="printClassReports()" type="button" class="shrink-0 flex items-center gap-1.5 px-3 py-2 bg-blue-50 text-blue-600 rounded-xl font-bold text-xs active:scale-95 transition-transform border border-blue-100 hover:bg-blue-100">
            <i data-lucide="printer" class="w-4 h-4"></i>
            <span x-text="selectedReports.length > 0 ? ('In ' + selectedReports.length + ' phiếu') : 'In PDF cả lớp'"></span>
        </button>
    </div>

    <!-- GLV phụ tá chỉ được xem -->
    <div x-show="!canWriteReports" style="display: none;" class="bg-slate-100 border border-slate-200 rounded-2xl p-3 mb-4 flex items-start gap-2.5">
        <i data-lucide="eye" class="w-4 h-4 text-slate-500 shrink-0 mt-0.5"></i>
        <p class="text-micro text-slate-600 leading-snug">
            Bạn đang ở chế độ <span class="font-bold">chỉ xem</span>. Chỉ GLV Chủ nhiệm trở lên mới lập được phiếu.
        </p>
    </div>

    <!-- 2. CHỌN LỚP — cùng kiểu bộ lọc của Danh sách (thanh + nút phễu) -->
    <?php $scopeClassModel = 'reportClass'; include __DIR__ . '/partial_scope_filter.php'; ?>

    <!-- Học kỳ: chỉ hiện sau khi đã chọn lớp -->
    <div x-show="reportClass !== ''" style="display: none;" class="bg-white rounded-card p-4 shadow-sm border border-slate-100 mb-4">
        <label class="block text-micro font-bold text-slate-500 uppercase tracking-wide mb-1.5">Học kỳ</label>
        <select x-model.number="reportTermId" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            <template x-for="t in terms" :key="t.id">
                <option :value="t.id" x-text="t.name + ' (' + formatDate(t.from) + ' – ' + formatDate(t.to) + ')'"></option>
            </template>
        </select>
    </div>

    <!-- Chưa chọn lớp: mời chọn, KHÔNG đổ cả đoàn ra cho nhẹ (giống Danh sách) -->
    <div x-show="reportClass === ''" style="display: none;" class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
        <i data-lucide="filter" class="w-12 h-12 mx-auto text-slate-300 mb-4"></i>
        <p class="text-slate-600 font-semibold text-base mb-1">Chọn lớp để xem phiếu</p>
        <p class="text-slate-500 text-sm">Phiếu liên lạc lập theo từng lớp — bấm nút lọc <i data-lucide="filter" class="inline w-3.5 h-3.5 -mt-0.5"></i> phía trên rồi chọn lớp.</p>
    </div>

    <!-- 3. TIẾN ĐỘ LẬP PHIẾU -->
    <div x-show="reportClass !== ''" style="display: none;" class="grid grid-cols-3 gap-3 mb-4">
        <div class="bg-white rounded-2xl p-3 shadow-sm border border-slate-100 text-center">
            <p class="text-2xl font-black text-emerald-600" x-text="reportProgress.sent"></p>
            <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Đã gửi</p>
        </div>
        <div class="bg-white rounded-2xl p-3 shadow-sm border border-slate-100 text-center">
            <p class="text-2xl font-black text-amber-700" x-text="reportProgress.draft"></p>
            <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Nháp</p>
        </div>
        <div class="bg-white rounded-2xl p-3 shadow-sm border border-slate-100 text-center">
            <p class="text-2xl font-black text-slate-500" x-text="reportProgress.missing"></p>
            <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Chưa lập</p>
        </div>
    </div>

    <!-- Cảnh báo buổi chưa điểm danh, cùng quy tắc với Thống kê -->
    <div x-show="reportAttendance.untaken > 0" style="display: none;"
         class="bg-amber-50 border border-amber-100 rounded-2xl p-3 mb-4 flex items-start gap-2.5">
        <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-500 shrink-0 mt-0.5"></i>
        <p class="text-micro text-amber-700 leading-snug">
            Học kỳ này có <span class="font-black" x-text="reportAttendance.untaken"></span> buổi chưa được điểm danh,
            không đưa vào phiếu. Nên điểm danh bù trước khi gửi phụ huynh.
        </p>
    </div>

    <!-- 4. DANH SÁCH EM -->
    <div class="space-y-2.5 xl:space-y-0 xl:grid xl:grid-cols-2 xl:gap-2.5 xl:items-start">
        <!-- Skeleton khi đang nạp lại số liệu (vd đổi niên khoá) -->
        <template x-if="reportClass !== '' && syncing && reportStudents.length === 0">
            <div class="space-y-2.5 xl:contents">
                <template x-for="i in 6" :key="'sk-rp-' + i">
                    <div class="bg-white rounded-field p-4 shadow-sm border border-slate-100 flex items-center gap-3">
                        <div class="skeleton w-5 h-5 rounded shrink-0"></div>
                        <div class="skeleton w-12 h-12 rounded-2xl shrink-0"></div>
                        <div class="flex-1 min-w-0">
                            <div class="skeleton skeleton-text-sm w-24 mb-1"></div>
                            <div class="skeleton skeleton-text w-32"></div>
                        </div>
                        <div class="skeleton skeleton-badge shrink-0"></div>
                    </div>
                </template>
            </div>
        </template>
        <template x-for="student in reportStudents" :key="student.id">
            <div style="content-visibility: auto; contain-intrinsic-size: auto 92px;"
                 class="w-full text-left bg-white rounded-field p-4 shadow-sm border flex items-center gap-3 transition-all"
                 :class="selectedReports.includes(student.id) ? 'border-blue-300 bg-blue-50/30 shadow-md' : 'border-slate-100'">

                <div class="shrink-0 flex items-center h-full pr-1">
                    <input type="checkbox" :value="student.id" x-model="selectedReports" class="w-5 h-5 text-blue-600 rounded border-slate-300 focus:ring-blue-500 cursor-pointer">
                </div>

                <div class="w-12 h-12 shrink-0 rounded-2xl flex flex-col items-center justify-center border cursor-pointer select-none active:scale-95 transition-transform"
                     @click="canWriteReports ? openReportForm(student) : (reportOf(student.id) && openReportPreview(student.id))"
                     :class="reportAttendance.byStudent[student.id] && attendRate(reportAttendance.byStudent[student.id]) >= 85
                            ? 'bg-emerald-50 border-emerald-100 text-emerald-600'
                            : (reportAttendance.byStudent[student.id] && attendRate(reportAttendance.byStudent[student.id]) >= 70
                              ? 'bg-amber-50 border-amber-100 text-amber-600'
                              : 'bg-rose-50 border-rose-100 text-rose-500')">
                    <span class="text-sm font-black leading-none"
                          x-text="(reportAttendance.byStudent[student.id] ? attendRate(reportAttendance.byStudent[student.id]) : 0) + '%'"></span>
                    <span class="text-micro font-bold uppercase tracking-wide opacity-70">Có mặt</span>
                </div>

                <div class="flex-1 min-w-0 cursor-pointer"
                     @click="canWriteReports ? openReportForm(student) : (reportOf(student.id) && openReportPreview(student.id))">
                    <p class="text-micro font-bold text-blue-600 leading-tight" x-text="student.code"></p>
                    <p class="text-sm font-black text-slate-800 leading-snug">
                        <span class="font-normal text-slate-500" x-text="student.holyName"></span>
                        <span x-text="student.name"></span>
                    </p>
                    <p x-show="reportOf(student.id)" style="display: none;" class="text-micro font-medium text-slate-500 mt-0.5">
                        Xếp loại <span class="font-bold text-slate-600" x-text="reportOf(student.id) ? reportOf(student.id).rank : ''"></span>
                    </p>
                </div>

                <div class="shrink-0 flex flex-col items-end gap-1.5">
                    <span class="text-micro font-bold uppercase tracking-wider px-2 py-1 rounded-lg border cursor-pointer"
                          @click="canWriteReports ? openReportForm(student) : (reportOf(student.id) && openReportPreview(student.id))"
                          :class="reportChipClass(reportStatus(student.id))" x-text="reportStatus(student.id)"></span>
                    <button x-show="reportOf(student.id)" style="display: none;" type="button"
                            @click="openReportPreview(student.id)"
                            class="text-micro font-bold text-blue-600 flex items-center gap-1 hover:underline">
                        <i data-lucide="eye" class="w-3 h-3"></i> Xem phiếu
                    </button>
                </div>
            </div>
        </template>

        <div x-show="reportClass !== '' && reportStudents.length === 0 && !syncing" style="display: none;" class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
            <i data-lucide="users" class="w-10 h-10 mx-auto text-slate-300 mb-3"></i>
            <p class="text-slate-500 font-medium text-sm">Lớp này chưa có em nào.</p>
        </div>
    </div>

    <!-- ==========================================================
         POPUP LẬP PHIẾU
         ========================================================== -->
    <div x-show="showReportForm" style="display: none;" class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-6">
        <div x-show="showReportForm" x-transition.opacity.duration.300ms @click="showReportForm = false" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
        <div x-show="showReportForm" x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0" class="modal-sheet relative w-full max-w-md sm:max-w-lg bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl h-[88dvh] sm:h-[82dvh] flex flex-col overflow-hidden">

            <div class="flex justify-center pt-3 pb-2 bg-white"><div class="w-12 h-1.5 bg-slate-200 rounded-full"></div></div>
            <div class="flex justify-between items-center px-5 pb-4 border-b border-slate-100 bg-white">
                <div class="min-w-0">
                    <h3 class="text-lg font-black text-slate-800 leading-tight">Phiếu liên lạc</h3>
                    <p class="text-micro text-slate-500" x-text="reportTerm ? reportTerm.name : ''"></p>
                </div>
                <button aria-label="Đóng" @click="showReportForm = false" class="tap-safe w-8 h-8 shrink-0 flex items-center justify-center bg-slate-100 rounded-full text-slate-500 active:scale-90 transition-transform"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>

            <div class="flex-1 overflow-y-auto p-5 space-y-4">

                <!-- Em nào -->
                <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100">
                    <p class="text-micro font-bold text-slate-500 uppercase tracking-wide mb-1">Phiếu của</p>
                    <p class="text-base font-black text-slate-800 leading-snug">
                        <span class="font-normal text-slate-500" x-text="studentById(reportForm.studentId) ? studentById(reportForm.studentId).holyName : ''"></span>
                        <span x-text="studentById(reportForm.studentId) ? studentById(reportForm.studentId).name : ''"></span>
                    </p>
                    <p class="text-xs font-medium text-slate-500 mt-1"
                       x-text="studentById(reportForm.studentId) ? studentById(reportForm.studentId).code + ' • ' + studentById(reportForm.studentId).className : ''"></p>
                </div>

                <!-- Chuyên cần: bản chụp, không sửa tay được -->
                <div class="border border-slate-100 rounded-2xl overflow-hidden">
                    <div class="bg-slate-50 px-4 py-2.5 flex items-center justify-between border-b border-slate-100">
                        <span class="text-micro font-bold text-slate-500 uppercase tracking-wide">Chuyên cần</span>
                        <button x-show="canWriteReports" @click="recalcReportAttendance()" style="display: none;"
                                class="text-micro font-bold text-blue-600 flex items-center gap-1 active:scale-95 transition-transform">
                            <i data-lucide="rotate-ccw" class="w-3 h-3"></i> Tính lại
                        </button>
                    </div>
                    <div class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-2 text-center">
                        <div><p class="text-lg font-black text-emerald-600" x-text="reportForm.attendance ? reportForm.attendance.present : 0"></p><p class="text-micro font-bold text-slate-500 uppercase">Có mặt</p></div>
                        <div><p class="text-lg font-black text-amber-700" x-text="reportForm.attendance ? reportForm.attendance.late : 0"></p><p class="text-micro font-bold text-slate-500 uppercase">Đi trễ</p></div>
                        <div><p class="text-lg font-black text-blue-500" x-text="reportForm.attendance ? reportForm.attendance.excused : 0"></p><p class="text-micro font-bold text-slate-500 uppercase">Có phép</p></div>
                        <div><p class="text-lg font-black text-rose-500" x-text="reportForm.attendance ? reportForm.attendance.unexcused : 0"></p><p class="text-micro font-bold text-slate-500 uppercase">Không phép</p></div>
                    </div>
                    <div class="bg-slate-50 px-4 py-2.5 flex items-center justify-between border-t border-slate-100">
                        <span class="text-xs font-semibold text-slate-500">Tỷ lệ có mặt</span>
                        <span class="text-sm font-black text-slate-800"
                              x-text="(reportForm.attendance ? reportForm.attendance.rate : 0) + '% / ' + (reportForm.attendance ? reportForm.attendance.total : 0) + ' buổi'"></span>
                    </div>
                </div>

                <!-- Điểm & hạnh kiểm -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Điểm học lực</label>
                        <input x-model="reportForm.score" @input="refreshSuggestedRank()" :disabled="!canWriteReports"
                               type="number" min="0" max="10" step="0.1" placeholder="0 – 10"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 disabled:opacity-60">
                        <p class="text-micro text-slate-500 mt-1 ml-1">Để trống nếu chưa kiểm tra</p>
                    </div>
                    <div>
                        <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Hạnh kiểm</label>
                        <select x-model="reportForm.conduct" :disabled="!canWriteReports"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 disabled:opacity-60 capitalize">
                            <template x-for="c in conductOptions" :key="c">
                                <option :value="c" x-text="c"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <!-- Xếp loại: gợi ý sẵn nhưng GLV quyết -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-micro font-bold text-slate-500 uppercase">Xếp loại</label>
                        <span class="text-micro text-slate-500">Hệ thống gợi ý, chủ nhiệm quyết định</span>
                    </div>
                    <div class="grid grid-cols-4 gap-2">
                        <template x-for="rk in rankOptions" :key="rk">
                            <button @click="canWriteReports && (reportForm.rank = rk)" type="button"
                                    class="py-2.5 rounded-xl font-bold text-xs border transition-colors"
                                    :class="reportForm.rank === rk ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-200' : 'bg-slate-50 text-slate-500 border-slate-200'"
                                    x-text="rk"></button>
                        </template>
                    </div>
                </div>

                <!-- Nhận xét -->
                <div class="border-t border-slate-100 pt-4 pb-6">
                    <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Nhận xét của GLV chủ nhiệm</label>
                    <textarea x-model="reportForm.remark" :disabled="!canWriteReports" rows="4"
                              placeholder="VD: Em ngoan, thuộc bài, tích cực phát biểu. Gia đình nhắc em đi lễ đều hơn..."
                              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 resize-none disabled:opacity-60"></textarea>
                    <p class="text-micro text-slate-500 mt-1 ml-1">Bắt buộc có nhận xét mới gửi được phiếu</p>
                </div>
            </div>

            <div x-show="canWriteReports" class="p-4 border-t border-slate-100 bg-white flex gap-3">
                <button aria-label="Xóa phiếu liên lạc" x-show="reportForm.id" style="display: none;" @click="deleteReport(reportForm.studentId)"
                        class="w-14 shrink-0 bg-rose-50 text-rose-500 rounded-2xl border border-rose-100 active:scale-95 transition-transform flex justify-center items-center">
                    <i data-lucide="trash-2" class="w-5 h-5"></i>
                </button>
                <button @click="saveReport(false)" type="button" class="flex-1 bg-slate-100 text-slate-600 font-bold py-3.5 rounded-2xl border border-slate-200 active:scale-[0.98] transition-transform">
                    Lưu nháp
                </button>
                <button @click="saveReport(true)" type="button" class="flex-1 bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center">
                    <i data-lucide="send" class="w-4 h-4 mr-2"></i> Gửi
                </button>
            </div>
        </div>
    </div>

    <!-- ==========================================================
         XEM TRƯỚC PHIẾU — đúng bản mà phụ huynh nhận
         ========================================================== -->
    <div x-show="showReportPreview" style="display: none;" class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-6 print-area">
        <div x-show="showReportPreview" x-transition.opacity.duration.300ms @click="showReportPreview = false" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm no-print"></div>
        <div x-show="showReportPreview" x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0" class="modal-sheet relative w-full max-w-md sm:max-w-lg bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl h-[88dvh] sm:h-[82dvh] flex flex-col overflow-hidden">

            <div class="flex justify-center pt-3 pb-2 bg-white no-print"><div class="w-12 h-1.5 bg-slate-200 rounded-full"></div></div>
            <div class="flex justify-between items-center px-5 pb-4 border-b border-slate-100 bg-white no-print">
                <h3 class="text-lg font-black text-slate-800">Xem trước phiếu</h3>
                <button aria-label="Đóng" @click="showReportPreview = false" class="tap-safe w-8 h-8 flex items-center justify-center bg-slate-100 rounded-full text-slate-500 active:scale-90 transition-transform"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>

            <div class="flex-1 overflow-y-auto p-5" id="report-sheet">
                <template x-if="previewReport && previewStudent">
                    <div class="border border-slate-200 rounded-2xl p-5 space-y-4">

                        <div class="text-center border-b border-slate-100 pb-4">
                            <p class="text-micro font-bold text-slate-500 uppercase tracking-widest">Đoàn Thiếu Nhi Thánh Thể</p>
                            <h4 class="text-lg font-black text-slate-800 mt-1">PHIẾU LIÊN LẠC</h4>
                            <p class="text-xs font-medium text-slate-500 mt-0.5" x-text="reportTerm ? reportTerm.name + ' • ' + formatDate(reportTerm.from) + ' – ' + formatDate(reportTerm.to) : ''"></p>
                        </div>

                        <div class="space-y-1.5 text-sm">
                            <div class="flex"><span class="w-24 shrink-0 text-slate-500">Họ và tên</span><span class="font-black text-slate-800" x-text="previewStudent.holyName + ' ' + previewStudent.name"></span></div>
                            <div class="flex"><span class="w-24 shrink-0 text-slate-500">Mã số</span><span class="font-semibold text-slate-700" x-text="previewStudent.code"></span></div>
                            <div class="flex"><span class="w-24 shrink-0 text-slate-500">Lớp</span><span class="font-semibold text-slate-700" x-text="previewStudent.className"></span></div>
                            <div class="flex"><span class="w-24 shrink-0 text-slate-500">Ngày sinh</span><span class="font-semibold text-slate-700" x-text="formatDate(previewStudent.birthDate)"></span></div>
                        </div>

                        <div class="bg-slate-50 rounded-xl p-4 space-y-2">
                            <p class="text-micro font-bold text-slate-500 uppercase tracking-wide mb-1">Chuyên cần</p>
                            <div class="flex justify-between text-sm"><span class="text-slate-600">Tổng số buổi</span><span class="font-bold text-slate-800" x-text="previewReport.attendance.total"></span></div>
                            <div class="flex justify-between text-sm"><span class="text-slate-600">Có mặt</span><span class="font-bold text-emerald-600" x-text="previewReport.attendance.present"></span></div>
                            <div class="flex justify-between text-sm"><span class="text-slate-600">Đi trễ</span><span class="font-bold text-amber-500" x-text="previewReport.attendance.late"></span></div>
                            <div class="flex justify-between text-sm"><span class="text-slate-600">Vắng có phép</span><span class="font-bold text-blue-500" x-text="previewReport.attendance.excused"></span></div>
                            <div class="flex justify-between text-sm"><span class="text-slate-600">Vắng không phép</span><span class="font-bold text-rose-500" x-text="previewReport.attendance.unexcused"></span></div>
                            <div class="flex justify-between text-sm border-t border-slate-200 pt-2 mt-2"><span class="font-semibold text-slate-700">Tỷ lệ có mặt</span><span class="font-black text-slate-800" x-text="previewReport.attendance.rate + '%'"></span></div>
                        </div>

                        <div class="grid grid-cols-3 gap-3 text-center">
                            <div class="border border-slate-200 rounded-xl p-3">
                                <p class="text-lg font-black text-slate-800" x-text="previewReport.score === '' ? '–' : previewReport.score"></p>
                                <p class="text-micro font-bold text-slate-500 uppercase">Học lực</p>
                            </div>
                            <div class="border border-slate-200 rounded-xl p-3">
                                <p class="text-sm font-black text-slate-800 capitalize pt-1" x-text="previewReport.conduct"></p>
                                <p class="text-micro font-bold text-slate-500 uppercase mt-1">Hạnh kiểm</p>
                            </div>
                            <div class="border border-blue-200 bg-blue-50 rounded-xl p-3">
                                <p class="text-sm font-black text-blue-700 pt-1" x-text="previewReport.rank"></p>
                                <p class="text-micro font-bold text-blue-400 uppercase mt-1">Xếp loại</p>
                            </div>
                        </div>

                        <div>
                            <p class="text-micro font-bold text-slate-500 uppercase tracking-wide mb-1.5">Nhận xét của Giáo Lý Viên</p>
                            <p class="text-sm text-slate-700 leading-relaxed italic" x-text="previewReport.remark || '(chưa có nhận xét)'"></p>
                        </div>

                        <div class="grid grid-cols-2 gap-4 pt-4 border-t border-slate-100 text-center">
                            <div>
                                <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">GLV Chủ nhiệm</p>
                                <p class="text-sm font-bold text-slate-700 mt-6" x-text="previewReport.createdBy"></p>
                            </div>
                            <div>
                                <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Phụ huynh ký tên</p>
                                <p class="text-sm text-slate-300 mt-6">.....................</p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="p-4 border-t border-slate-100 bg-white no-print">
                <button @click="printClassReport()" type="button" class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center">
                    <i data-lucide="printer" class="w-5 h-5 mr-2"></i> In phiếu
                </button>
            </div>
        </div>
    </div>
</div>
