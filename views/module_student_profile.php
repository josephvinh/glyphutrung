<!-- MÀN HÌNH HỒ SƠ THIẾU NHI -->
<div data-module="student_profile" class="module-panel pt-6 pb-24 relative">

    <!-- HEADER VỚI THÔNG TIN EM -->
    <?php include __DIR__ . '/partial_student_profile_header.php'; ?>

    <!-- 5 TAB ĐIỀU HƯỚNG (Kiểu dáng Segmented Control / Cuộn ngang mượt) -->
    <div role="tablist" aria-label="Hồ sơ thiếu nhi" class="bg-slate-50/80 rounded-[20px] p-1.5 border border-slate-100 flex gap-1.5 mb-5 overflow-x-auto hide-scrollbar scroll-smooth snap-x snap-mandatory">
        <!-- INFO -->
        <button @click="profileTab = 'info'; $el.scrollIntoView({behavior: 'smooth', block: 'nearest', inline: 'center'})" type="button" role="tab" :aria-selected="profileTab === 'info' ? 'true' : 'false'"
                class="shrink-0 snap-start whitespace-nowrap px-4 py-2.5 rounded-2xl font-bold text-sm transition-all duration-200 flex items-center justify-center gap-2"
                :class="profileTab === 'info' ? 'bg-white text-blue-600 shadow-[0_2px_8px_rgba(0,0,0,0.08)] border border-slate-200/60' : 'text-slate-500 hover:text-slate-700 hover:bg-slate-100/50 border border-transparent'">
            <i data-lucide="user-circle" class="w-4 h-4"></i> Tổng quan
        </button>
        <!-- SCORES -->
        <button @click="profileTab = 'scores'; $el.scrollIntoView({behavior: 'smooth', block: 'nearest', inline: 'center'})" type="button" role="tab" :aria-selected="profileTab === 'scores' ? 'true' : 'false'"
                class="shrink-0 snap-start whitespace-nowrap px-4 py-2.5 rounded-2xl font-bold text-sm transition-all duration-200 flex items-center justify-center gap-2"
                :class="profileTab === 'scores' ? 'bg-white text-blue-600 shadow-[0_2px_8px_rgba(0,0,0,0.08)] border border-slate-200/60' : 'text-slate-500 hover:text-slate-700 hover:bg-slate-100/50 border border-transparent'">
            <i data-lucide="graduation-cap" class="w-4 h-4"></i> Điểm số
        </button>
        <!-- ATTENDANCE -->
        <button @click="profileTab = 'attendance'; $el.scrollIntoView({behavior: 'smooth', block: 'nearest', inline: 'center'})" type="button" role="tab" :aria-selected="profileTab === 'attendance' ? 'true' : 'false'"
                class="shrink-0 snap-start whitespace-nowrap px-4 py-2.5 rounded-2xl font-bold text-sm transition-all duration-200 flex items-center justify-center gap-2"
                :class="profileTab === 'attendance' ? 'bg-white text-blue-600 shadow-[0_2px_8px_rgba(0,0,0,0.08)] border border-slate-200/60' : 'text-slate-500 hover:text-slate-700 hover:bg-slate-100/50 border border-transparent'">
            <i data-lucide="check-circle" class="w-4 h-4"></i> Điểm danh
        </button>
        <!-- REPORT -->
        <button @click="profileTab = 'report'; $el.scrollIntoView({behavior: 'smooth', block: 'nearest', inline: 'center'})" type="button" role="tab" :aria-selected="profileTab === 'report' ? 'true' : 'false'"
                class="shrink-0 snap-start whitespace-nowrap px-4 py-2.5 rounded-2xl font-bold text-sm transition-all duration-200 flex items-center justify-center gap-2"
                :class="profileTab === 'report' ? 'bg-white text-blue-600 shadow-[0_2px_8px_rgba(0,0,0,0.08)] border border-slate-200/60' : 'text-slate-500 hover:text-slate-700 hover:bg-slate-100/50 border border-transparent'">
            <i data-lucide="file-text" class="w-4 h-4"></i> Phiếu liên lạc
        </button>
        <!-- QRCARD -->
        <button @click="profileTab = 'qrcard'; $el.scrollIntoView({behavior: 'smooth', block: 'nearest', inline: 'center'})" type="button" role="tab" :aria-selected="profileTab === 'qrcard' ? 'true' : 'false'"
                class="shrink-0 snap-start whitespace-nowrap px-4 py-2.5 rounded-2xl font-bold text-sm transition-all duration-200 flex items-center justify-center gap-2"
                :class="profileTab === 'qrcard' ? 'bg-white text-blue-600 shadow-[0_2px_8px_rgba(0,0,0,0.08)] border border-slate-200/60' : 'text-slate-500 hover:text-slate-700 hover:bg-slate-100/50 border border-transparent'">
            <i data-lucide="qr-code" class="w-4 h-4"></i> Thẻ mã QR
        </button>
    </div>

    <!-- ============================================================
         TAB: THÔNG TIN
         ============================================================ -->
    <div x-show="profileTab === 'info'" style="display: none;">

        <!-- SỔ MỘC: VÍ + LỬA CHUỖI -->
        <div class="bg-white rounded-card p-5 shadow-sm border border-slate-100 mb-5">
            <h3 class="text-base font-black text-slate-800 mb-4 flex items-center gap-2">
                <i data-lucide="gem" class="w-5 h-5 text-amber-500"></i> Sổ Mộc
            </h3>

            <div class="grid grid-cols-3 gap-2 mb-4">
                <div class="bg-amber-50 rounded-xl p-3 text-center">
                    <p class="text-micro font-bold text-amber-600 uppercase tracking-wide mb-1">Ví Mộc</p>
                    <p class="text-xl font-black text-amber-700" x-text="profileStampSummary.current_balance"></p>
                </div>
                <div class="bg-blue-50 rounded-xl p-3 text-center">
                    <p class="text-micro font-bold text-blue-600 uppercase tracking-wide mb-1">Tổng Mộc năm</p>
                    <p class="text-xl font-black text-blue-700" x-text="profileStampSummary.total_earned"></p>
                </div>
                <div class="bg-rose-50 rounded-xl p-3 text-center">
                    <p class="text-micro font-bold text-rose-500 uppercase tracking-wide mb-1 flex items-center justify-center gap-1">
                        🔥 Chuỗi
                    </p>
                    <p class="text-xl font-black text-rose-600">
                        <span x-text="profileStampSummary.current_streak"></span>
                        <span class="text-xs font-semibold text-rose-400">/ <span x-text="profileStampSummary.longest_streak"></span> dài nhất</span>
                    </p>
                </div>
            </div>

            <!-- Lịch sử giao dịch gần nhất -->
            <div class="border-t border-slate-100 pt-3">
                <p class="text-micro font-bold text-slate-500 uppercase tracking-wide mb-2">Giao dịch gần đây</p>
                <template x-if="profileStampSummary.recent_transactions.length === 0">
                    <p class="text-sm text-slate-400 text-center py-3">Chưa có giao dịch Mộc nào.</p>
                </template>
                <div class="space-y-1.5">
                    <template x-for="(tx, txIdx) in profileStampSummary.recent_transactions.slice(0, 5)" :key="txIdx + '-' + tx.created_at + '-' + tx.amount + '-' + tx.description">
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span class="text-slate-600 truncate" x-text="tx.description"></span>
                            <span class="shrink-0 font-bold" :class="tx.amount >= 0 ? 'text-emerald-600' : 'text-rose-500'"
                                  x-text="(tx.amount >= 0 ? '+' : '') + tx.amount"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-card p-5 shadow-sm border border-slate-100">
            <h3 class="text-base font-black text-slate-800 mb-4 flex items-center gap-2">
                <i data-lucide="user-circle" class="w-5 h-5 text-blue-600"></i> Hồ sơ đầy đủ
            </h3>

            <!-- Thông tin cá nhân -->
            <div class="space-y-3 mb-6">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <p class="text-micro font-bold text-slate-500 uppercase tracking-wide mb-1">Ngày sinh</p>
                        <p class="text-sm font-semibold text-slate-700" x-text="profileStudent ? formatDate(profileStudent.birthDate) : ''"></p>
                    </div>
                    <div>
                        <p class="text-micro font-bold text-slate-500 uppercase tracking-wide mb-1">Giới tính</p>
                        <p class="text-sm font-semibold" :class="profileStudent && profileStudent.gender === 1 ? 'text-blue-600' : 'text-rose-500'"
                           x-text="profileStudent ? (profileStudent.gender === 1 ? 'Nam' : 'Nữ') : ''"></p>
                    </div>
                </div>
                <div>
                    <p class="text-micro font-bold text-slate-500 uppercase tracking-wide mb-1">Địa chỉ</p>
                    <p class="text-sm font-semibold text-slate-700" x-text="profileStudent ? profileStudent.address : ''"></p>
                </div>
            </div>

            <!-- Thông tin cha mẹ -->
            <div class="border-t border-slate-100 pt-4">
                <h4 class="text-sm font-bold text-slate-600 mb-3 flex items-center gap-2">
                    <i data-lucide="users" class="w-4 h-4"></i> Thông tin cha mẹ
                </h4>
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-blue-50 rounded-xl p-3">
                        <p class="text-micro font-bold text-blue-400 uppercase tracking-wide mb-1">Họ tên cha</p>
                        <p class="text-sm font-semibold text-slate-700" x-text="profileStudent ? profileStudent.fatherName : ''"></p>
                        <p class="text-xs text-blue-600 mt-1 flex items-center gap-1">
                            <i data-lucide="phone" class="w-3 h-3"></i>
                            <span x-text="profileStudent ? profileStudent.fatherPhone : ''"></span>
                        </p>
                    </div>
                    <div class="bg-rose-50 rounded-xl p-3">
                        <p class="text-micro font-bold text-rose-300 uppercase tracking-wide mb-1">Họ tên mẹ</p>
                        <p class="text-sm font-semibold text-slate-700" x-text="profileStudent ? profileStudent.motherName : ''"></p>
                        <p class="text-xs text-rose-500 mt-1 flex items-center gap-1">
                            <i data-lucide="phone" class="w-3 h-3"></i>
                            <span x-text="profileStudent ? profileStudent.motherPhone : ''"></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         TAB: ĐIỂM SỐ
         ============================================================ -->
    <div x-show="profileTab === 'scores'" style="display: none;">
        <template x-if="!profileStudent">
            <div class="text-center py-8 text-slate-400">
                <i data-lucide="loader-2" class="w-8 h-8 mx-auto animate-spin mb-2"></i>
                <p class="text-sm">Đang tải...</p>
            </div>
        </template>

        <template x-if="profileStudent">
            <div>
                <!-- Chọn học kỳ -->
                <div class="mb-4">
                    <select x-model.number="scoreTermId"
                            class="w-full sm:w-auto bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        <template x-for="t in terms" :key="t.id">
                            <option :value="t.id" x-text="t.name"></option>
                        </template>
                    </select>
                </div>

                <!-- Bảng điểm -->
                <div class="bg-white rounded-card shadow-sm border border-slate-100 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 border-b border-slate-100">
                                <tr>
                                    <th class="text-left px-4 py-3 font-bold text-slate-600 text-micro uppercase tracking-wide">Học kỳ</th>
                                    <th class="text-center px-3 py-3 font-bold text-slate-600 text-micro uppercase tracking-wide">Miệng</th>
                                    <th class="text-center px-3 py-3 font-bold text-slate-600 text-micro uppercase tracking-wide">15 phút</th>
                                    <th class="text-center px-3 py-3 font-bold text-slate-600 text-micro uppercase tracking-wide">Giữa kỳ</th>
                                    <th class="text-center px-3 py-3 font-bold text-slate-600 text-micro uppercase tracking-wide">Cuối kỳ</th>
                                    <th class="text-center px-3 py-3 font-bold text-slate-600 text-micro uppercase tracking-wide">TB</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="t in terms" :key="t.id">
                                    <tr class="border-b border-slate-50 hover:bg-slate-50 transition-colors"
                                        :class="{'bg-blue-50': scoreTermId === t.id}">
                                        <td class="px-4 py-3 font-semibold text-slate-700" x-text="t.name"></td>
                                        <td class="text-center px-3 py-3 font-medium text-slate-600"
                                            x-text="scoreOf(profileStudent.id, 'mieng', t.id)"></td>
                                        <td class="text-center px-3 py-3 font-medium text-slate-600"
                                            x-text="scoreOf(profileStudent.id, 'p15', t.id)"></td>
                                        <td class="text-center px-3 py-3 font-medium text-slate-600"
                                            x-text="scoreOf(profileStudent.id, 'giuaky', t.id)"></td>
                                        <td class="text-center px-3 py-3 font-medium text-slate-600"
                                            x-text="scoreOf(profileStudent.id, 'cuoiky', t.id)"></td>
                                        <td class="text-center px-3 py-3 font-bold"
                                            :class="termAverage(profileStudent.id, t.id) !== null ? (termAverage(profileStudent.id, t.id) >= 5 ? 'text-emerald-600' : 'text-rose-500') : 'text-slate-400'"
                                            x-text="termAverage(profileStudent.id, t.id) !== null ? termAverage(profileStudent.id, t.id).toFixed(1) : '-'"></td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="bg-slate-100 border-t-2 border-slate-200">
                                <tr>
                                    <td class="px-4 py-3 font-bold text-slate-700">Cả năm</td>
                                    <td colspan="4"></td>
                                    <td class="text-center px-3 py-3 font-black text-lg"
                                        :class="yearAverage(profileStudent.id) !== null ? (yearAverage(profileStudent.id) >= 5 ? 'text-emerald-600' : 'text-rose-500') : 'text-slate-400'"
                                        x-text="yearAverage(profileStudent.id) !== null ? yearAverage(profileStudent.id).toFixed(1) : '-'"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Ghi chú xếp loại -->
                <div class="mt-4 bg-amber-50 border border-amber-200 rounded-xl p-3 flex items-start gap-2">
                    <i data-lucide="info" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
                    <div class="text-micro text-amber-800">
                        <p><strong>Ngưỡng xét lên lớp:</strong> ĐTB ≥ 5 và Chuyên cần ≥ 60%</p>
                        <p class="mt-1"><strong>Xếp loại:</strong> Giỏi ≥ 8.0 · Khá ≥ 6.5 · Trung bình ≥ 5.0 · Yếu &lt; 5.0</p>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- ============================================================
         TAB: PHIẾU LIÊN LẠC
         ============================================================ -->
    <div x-show="profileTab === 'report'" style="display: none;">
        <template x-if="!profileStudent">
            <div class="text-center py-8 text-slate-400">
                <i data-lucide="loader-2" class="w-8 h-8 mx-auto animate-spin mb-2"></i>
                <p class="text-sm">Đang tải...</p>
            </div>
        </template>

        <template x-if="profileStudent">
            <div>
                <template x-if="reports.filter(r => r.studentId === profileStudent.id).length === 0">
                    <div class="text-center py-12 bg-white rounded-card border border-slate-100 border-dashed">
                        <i data-lucide="clipboard-x" class="w-12 h-12 mx-auto text-slate-300 mb-4"></i>
                        <p class="text-slate-600 font-semibold mb-1">Chưa có phiếu liên lạc</p>
                        <p class="text-slate-400 text-sm">Phiếu sẽ xuất hiện khi được tạo từ module Phiếu liên lạc</p>
                    </div>
                </template>

                <template x-if="reports.filter(r => r.studentId === profileStudent.id).length > 0">
                    <div class="space-y-3">
                        <template x-for="r in reports.filter(r => r.studentId === profileStudent.id).sort((a,b) => b.termId - a.termId)" :key="r.id">
                            <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100">
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <h4 class="font-bold text-slate-800" x-text="terms.find(t => t.id === r.termId)?.name || 'Học kỳ ' + r.termId"></h4>
                                        <p class="text-xs text-slate-500 mt-0.5">
                                            <span x-text="r.attendance ? r.attendance.total + ' buổi' : '0 buổi'"></span> ·
                                            <span class="text-emerald-600" x-text="r.attendance ? r.attendance.present + ' có mặt' : '0 có mặt'"></span> ·
                                            <span class="text-rose-500" x-text="r.attendance ? (r.attendance.unexcused || 0) + ' vắng' : '0 vắng'"></span>
                                        </p>
                                    </div>
                                    <span class="text-micro font-bold uppercase tracking-wider px-2 py-1 rounded-lg"
                                          :class="{'bg-emerald-50 text-emerald-600': r.status === 'published', 'bg-slate-100 text-slate-500': r.status !== 'published'}"
                                          x-text="r.status === 'published' ? 'Đã xuất' : 'Nháp'"></span>
                                </div>

                                <div class="grid grid-cols-3 gap-2 mb-3 text-center">
                                    <div class="bg-slate-50 rounded-xl p-2">
                                        <p class="text-micro font-bold text-slate-500 uppercase">Học tập</p>
                                        <p class="text-sm font-black" :class="r.score >= 8 ? 'text-emerald-600' : r.score >= 6.5 ? 'text-blue-600' : r.score >= 5 ? 'text-amber-600' : 'text-rose-500'"
                                           x-text="r.score !== null ? r.score.toFixed(1) : '-'"></p>
                                    </div>
                                    <div class="bg-slate-50 rounded-xl p-2">
                                        <p class="text-micro font-bold text-slate-500 uppercase">Hạnh kiểm</p>
                                        <p class="text-sm font-black text-slate-700" x-text="r.conduct || '-'"></p>
                                    </div>
                                    <div class="bg-slate-50 rounded-xl p-2">
                                        <p class="text-micro font-bold text-slate-500 uppercase">Xếp loại</p>
                                        <p class="text-sm font-black" :class="r.rank === 'Giỏi' ? 'text-emerald-600' : r.rank === 'Khá' ? 'text-blue-600' : r.rank === 'Trung bình' ? 'text-amber-600' : 'text-rose-500'"
                                           x-text="r.rank || '-'"></p>
                                    </div>
                                </div>

                                <template x-if="r.remark">
                                    <div class="bg-blue-50 rounded-xl p-3 mb-3">
                                        <p class="text-micro font-bold text-blue-600 uppercase mb-1">Nhận xét</p>
                                        <p class="text-sm text-slate-700" x-text="r.remark"></p>
                                    </div>
                                </template>

                                <template x-if="r.status === 'published'">
                                    <div class="flex gap-2">
                                        <button @click="printReport(r)" type="button" class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 bg-blue-600 text-white rounded-xl font-bold text-xs active:scale-95 transition-transform">
                                            <i data-lucide="printer" class="w-4 h-4"></i> In phiếu
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </template>
    </div>

    <!-- ============================================================
         TAB: ĐIỂM DANH
         ============================================================ -->
    <div x-show="profileTab === 'attendance'" style="display: none;">
        <template x-if="!profileStudent">
            <div class="text-center py-8 text-slate-400">
                <i data-lucide="loader-2" class="w-8 h-8 mx-auto animate-spin mb-2"></i>
                <p class="text-sm">Đang tải...</p>
            </div>
        </template>

        <template x-if="profileStudent">
            <div>
                <!-- Thống kê tổng quan -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
                    <div class="bg-emerald-50 rounded-xl p-3 text-center">
                        <p class="text-xs font-bold text-emerald-600 uppercase mb-1">Có mặt</p>
                        <p class="text-xl font-black text-emerald-700"
                           x-text="attendances.filter(a => a.studentId === profileStudent.id && a.status === 'có mặt').length"></p>
                    </div>
                    <div class="bg-amber-50 rounded-xl p-3 text-center">
                        <p class="text-xs font-bold text-amber-600 uppercase mb-1">Đi trễ</p>
                        <p class="text-xl font-black text-amber-700"
                           x-text="attendances.filter(a => a.studentId === profileStudent.id && a.status === 'đi trễ').length"></p>
                    </div>
                    <div class="bg-rose-50 rounded-xl p-3 text-center">
                        <p class="text-xs font-bold text-rose-600 uppercase mb-1">Vắng</p>
                        <p class="text-xl font-black text-rose-700"
                           x-text="getUnexcusedAbsences(profileStudent.id)"></p>
                    </div>
                    <div class="bg-blue-50 rounded-xl p-3 text-center">
                        <p class="text-xs font-bold text-blue-600 uppercase mb-1">Tỷ lệ</p>
                        <p class="text-xl font-black text-blue-700"
                           x-text="getAttendanceRate(profileStudent.id) + '%'"></p>
                    </div>
                </div>

                <!-- Danh sách buổi điểm danh -->
                <div class="bg-white rounded-card shadow-sm border border-slate-100 overflow-hidden">
                    <div class="overflow-x-auto max-h-96 overflow-y-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 border-b border-slate-100 sticky top-0">
                                <tr>
                                    <th class="text-left px-4 py-3 font-bold text-slate-600 text-micro uppercase tracking-wide">Ngày</th>
                                    <th class="text-left px-3 py-3 font-bold text-slate-600 text-micro uppercase tracking-wide">Chương trình</th>
                                    <th class="text-center px-3 py-3 font-bold text-slate-600 text-micro uppercase tracking-wide">Trạng thái</th>
                                    <th class="text-center px-3 py-3 font-bold text-slate-600 text-micro uppercase tracking-wide">Cách</th>
                                    <th class="text-left px-3 py-3 font-bold text-slate-600 text-micro uppercase tracking-wide">Người điểm</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-if="attendances.filter(a => a.studentId === profileStudent.id).length === 0">
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-slate-400">
                                            <i data-lucide="calendar-x" class="w-8 h-8 mx-auto mb-2"></i>
                                            <p class="text-sm">Chưa có dữ liệu điểm danh</p>
                                        </td>
                                    </tr>
                                </template>
                                <template x-for="a in attendances.filter(a => a.studentId === profileStudent.id).sort((x,y) => y.date.localeCompare(x.date))" :key="a.programId + '-' + a.date">
                                    <tr class="border-b border-slate-50 hover:bg-slate-50 transition-colors">
                                        <td class="px-4 py-3 font-medium text-slate-700" x-text="formatDate(a.date)"></td>
                                        <td class="px-3 py-3 text-slate-600" x-text="programs.find(p => p.id === a.programId)?.name || '-'"></td>
                                        <td class="text-center px-3 py-3">
                                            <span class="inline-block text-micro font-bold uppercase tracking-wider px-2 py-1 rounded-lg"
                                                  :class="{'bg-emerald-50 text-emerald-600': a.status === 'có mặt', 'bg-amber-50 text-amber-600': a.status === 'đi trễ'}"
                                                  x-text="a.status === 'có mặt' ? 'Có mặt' : 'Đi trễ'"></span>
                                        </td>
                                        <td class="text-center px-3 py-3 text-slate-500 text-xs" x-text="a.method === 'qr' ? 'QR' : 'Tay'"></td>
                                        <td class="px-3 py-3 text-slate-500 text-xs" x-text="a.markedBy || '-'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- ============================================================
         TAB: QR CARD
         ============================================================ -->
    <div x-show="profileTab === 'qrcard'" style="display: none;">
        <template x-if="!profileStudent">
            <div class="text-center py-8 text-slate-400">
                <i data-lucide="loader-2" class="w-8 h-8 mx-auto animate-spin mb-2"></i>
                <p class="text-sm">Đang tải...</p>
            </div>
        </template>

        <template x-if="profileStudent">
            <div>
                <div class="bg-white rounded-card p-6 shadow-sm border border-slate-100 text-center">
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Thẻ QR</p>

                    <!-- QR Code lớn — chỉ vẽ khi bộ sinh mã đã nạp xong.
                         Nhắc qrReady trong biểu thức để Alpine vẽ lại khi nạp xong. -->
                    <div class="w-48 h-48 mx-auto bg-white rounded-2xl border-4 border-slate-200 flex items-center justify-center mb-4 overflow-hidden">
                        <div x-show="qrReady" x-html="qrReady && profileStudent ? qrSvg(profileStudent.code) : ''" class="w-full h-full flex items-center justify-center"></div>
                        <span x-show="!qrReady" style="display: none;" class="text-xs text-slate-400">Đang tải mã QR…</span>
                    </div>

                    <h3 class="text-lg font-black text-slate-800 mb-1">
                        <span x-text="profileStudent ? profileStudent.holyName : ''" class="font-normal text-slate-500"></span>
                        <span x-text="profileStudent ? profileStudent.name : ''"></span>
                    </h3>
                    <p class="text-sm font-bold text-blue-600 mb-1" x-text="profileStudent ? profileStudent.code : ''"></p>
                    <p class="text-sm text-slate-500" x-text="profileStudent ? (profileStudent.className + ' · ' + profileStudent.block) : ''"></p>
                </div>

                <!-- Nút in -->
                <div class="mt-4 flex gap-2">
                    <button @click="profileStudent && printSingleQrcard(profileStudent)"
                            class="flex-1 flex items-center justify-center gap-1.5 px-4 py-3 bg-blue-600 text-white rounded-xl font-bold text-sm active:scale-95 transition-transform shadow-md shadow-blue-200">
                        <i data-lucide="printer" class="w-5 h-5"></i> In thẻ QR
                    </button>
                </div>
            </div>
        </template>
    </div>

</div>
