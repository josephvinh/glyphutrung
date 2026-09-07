<!-- MÀN HÌNH THỐNG KÊ — chỉ hiển thị trong hub Báo cáo (tab Thống kê).
     KHÔNG đặt data-module ở đây: changeModule sẽ set display:none cho mọi
     [data-module] -> nếu để, tab Thống kê trong hub bị ẩn trắng. -->
<div class="module-panel pt-6 pb-10 relative">

    <!-- 1. THANH ĐIỀU HƯỚNG -->
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center min-w-0">
            <button aria-label="Quay lại trang chủ" @click="changeModule('dashboard')" class="tap-safe w-10 h-10 shrink-0 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-4">
                <i data-lucide="chevron-left" class="w-6 h-6 text-slate-600"></i>
            </button>
            <h2 class="text-xl font-black text-slate-800 tracking-tight">Thống Kê</h2>
        </div>
        <button @click="exportStatsCSV()" class="shrink-0 flex items-center gap-1.5 px-3 py-2 bg-blue-50 text-blue-600 rounded-xl font-bold text-xs active:scale-95 transition-transform border border-blue-100 shadow-sm">
            <i data-lucide="file-up" class="w-4 h-4"></i> Xuất
        </button>
    </div>

    <!-- 2. CHỌN THÁNG -->
    <div class="bg-white rounded-card p-4 shadow-sm border border-slate-100 mb-4">
        <div class="flex items-center gap-2">
            <button aria-label="Tháng trước" @click="shiftStatMonth(-1)" class="w-10 h-10 shrink-0 bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-center text-slate-500 active:scale-90 transition-transform">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
            </button>
            <div class="flex-1 text-center">
                <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Kỳ thống kê</p>
                <p class="text-lg font-black text-slate-800 leading-tight" x-text="statMonthLabel"></p>
            </div>
            <button aria-label="Tháng sau" @click="shiftStatMonth(1)" class="w-10 h-10 shrink-0 bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-center text-slate-500 active:scale-90 transition-transform">
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </button>
        </div>
        <p class="text-micro text-slate-500 text-center mt-2 px-4 leading-snug">
            Chỉ tính các buổi đã qua giờ chốt. Buổi chưa diễn ra không bị tính là vắng.
        </p>

        <!-- Buổi không có bản ghi nào: coi như chưa điểm danh, không đưa vào phép tính -->
        <div x-show="statSummary.untakenSessions > 0" style="display: none;"
             class="mt-3 bg-amber-50 border border-amber-100 rounded-2xl p-3 flex items-start gap-2.5">
            <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-500 shrink-0 mt-0.5"></i>
            <p class="text-micro text-amber-700 leading-snug">
                Có <span class="font-black" x-text="statSummary.untakenSessions"></span> buổi chưa được điểm danh.
                Các buổi này không đưa vào phép tính để khỏi kéo tụt tỷ lệ chuyên cần.
            </p>
        </div>
    </div>

    <!-- 3. BỐN Ô TỔNG QUAN -->
    <!-- Skeleton loading state -->
    <div x-show="syncing" style="display: none;" class="grid grid-cols-2 gap-3 mb-5">
        <div class="bg-white rounded-field p-4 shadow-sm border border-slate-100">
            <div class="w-9 h-9 rounded-xl bg-slate-100 mb-2"></div>
            <div class="skeleton h-8 w-16 mb-2"></div>
            <div class="skeleton h-4 w-24"></div>
        </div>
        <div class="bg-white rounded-field p-4 shadow-sm border border-slate-100">
            <div class="w-9 h-9 rounded-xl bg-slate-100 mb-2"></div>
            <div class="skeleton h-8 w-16 mb-2"></div>
            <div class="skeleton h-4 w-24"></div>
        </div>
        <div class="bg-white rounded-field p-4 shadow-sm border border-slate-100">
            <div class="w-9 h-9 rounded-xl bg-slate-100 mb-2"></div>
            <div class="skeleton h-8 w-16 mb-2"></div>
            <div class="skeleton h-4 w-24"></div>
        </div>
        <div class="bg-white rounded-field p-4 shadow-sm border border-slate-100">
            <div class="w-9 h-9 rounded-xl bg-slate-100 mb-2"></div>
            <div class="skeleton h-8 w-16 mb-2"></div>
            <div class="skeleton h-4 w-24"></div>
        </div>
    </div>

    <!-- Actual stat cards -->
    <div x-show="!syncing" style="display: none;" class="grid grid-cols-2 gap-3 mb-5">
        <div class="bg-white rounded-field p-4 shadow-sm border border-slate-100">
            <div class="tap-safe w-9 h-9 rounded-xl bg-blue-50 text-blue-500 flex items-center justify-center mb-2">
                <i data-lucide="users" class="w-4 h-4"></i>
            </div>
            <p class="text-2xl font-black text-slate-800 leading-none" x-text="statRoster.active"></p>
            <p class="text-micro font-bold text-slate-500 uppercase tracking-wide mt-1">Đang sinh hoạt</p>
        </div>

        <div class="bg-white rounded-field p-4 shadow-sm border border-slate-100">
            <div class="tap-safe w-9 h-9 rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center mb-2">
                <i data-lucide="calendar-check" class="w-4 h-4"></i>
            </div>
            <p class="text-2xl font-black text-slate-800 leading-none" x-text="statSummary.countedSessions"></p>
            <p class="text-micro font-bold text-slate-500 uppercase tracking-wide mt-1">Buổi đã điểm danh</p>
        </div>

        <div class="bg-white rounded-field p-4 shadow-sm border border-slate-100">
            <div class="tap-safe w-9 h-9 rounded-xl bg-emerald-50 text-emerald-500 flex items-center justify-center mb-2">
                <i data-lucide="trending-up" class="w-4 h-4"></i>
            </div>
            <p class="text-2xl font-black leading-none"
               :class="statSummary.rate >= 75 ? 'text-emerald-600' : (statSummary.rate >= 50 ? 'text-amber-500' : 'text-rose-500')">
                <span x-text="statSummary.rate"></span><span class="text-base">%</span>
            </p>
            <p class="text-micro font-bold text-slate-500 uppercase tracking-wide mt-1">Tỷ lệ có mặt</p>
        </div>

        <div class="bg-white rounded-field p-4 shadow-sm border border-slate-100">
            <div class="tap-safe w-9 h-9 rounded-xl bg-amber-50 text-amber-500 flex items-center justify-center mb-2">
                <i data-lucide="file-text" class="w-4 h-4"></i>
            </div>
            <p class="text-2xl font-black text-slate-800 leading-none" x-text="statLeaveCounts.total"></p>
            <p class="text-micro font-bold text-slate-500 uppercase tracking-wide mt-1">Đơn xin phép</p>
        </div>
    </div>

    <!-- KHÔNG CÓ BUỔI NÀO -->
    <div x-show="statSummary.countedSessions === 0" style="display: none;" class="text-center py-12 px-6 bg-white rounded-card border border-slate-100 border-dashed mb-5">
        <i data-lucide="bar-chart-3" class="w-10 h-10 mx-auto text-slate-300 mb-3"></i>
        <p class="text-slate-500 font-medium text-sm mb-1">Tháng này chưa có số liệu điểm danh.</p>
        <p class="text-slate-400 text-xs"
           x-text="statSummary.untakenSessions > 0 ? 'Các buổi trong tháng đều chưa được điểm danh.' : 'Chọn tháng khác để xem số liệu.'"></p>
    </div>

    <template x-if="statSummary.countedSessions > 0">
        <div>
            <!-- 4. CƠ CẤU CHUYÊN CẦN -->
            <div class="bg-white rounded-card p-5 shadow-sm border border-slate-100 mb-5">
                <div class="flex justify-between items-baseline mb-4">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Cơ cấu chuyên cần</h3>
                    <span class="text-micro font-bold text-slate-500">
                        <span x-text="statSummary.total.total"></span> lượt
                    </span>
                </div>

                <!-- Thanh gộp: nhìn phát ra ngay tỷ trọng -->
                <div class="flex h-3 rounded-full overflow-hidden bg-slate-100 mb-4">
                    <div class="bg-emerald-500 transition-all" :style="'width:' + percent(statSummary.total.present, statSummary.total.total) + '%'"></div>
                    <div class="bg-amber-400 transition-all"   :style="'width:' + percent(statSummary.total.late, statSummary.total.total) + '%'"></div>
                    <div class="bg-blue-400 transition-all"    :style="'width:' + percent(statSummary.total.excused, statSummary.total.total) + '%'"></div>
                    <div class="bg-rose-500 transition-all"    :style="'width:' + percent(statSummary.total.unexcused, statSummary.total.total) + '%'"></div>
                </div>

                <div class="space-y-2.5">
                    <template x-for="row in [
                        {k:'present',   l:'Có mặt',          c:'bg-emerald-500'},
                        {k:'late',      l:'Đi trễ',          c:'bg-amber-400'},
                        {k:'excused',   l:'Vắng có phép',    c:'bg-blue-400'},
                        {k:'unexcused', l:'Vắng không phép', c:'bg-rose-500'}
                    ]" :key="row.k">
                        <div class="flex items-center gap-3">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="row.c"></span>
                            <span class="text-sm font-semibold text-slate-600 flex-1" x-text="row.l"></span>
                            <span class="text-sm font-black text-slate-800" x-text="statSummary.total[row.k]"></span>
                            <span class="text-micro font-bold text-slate-500 w-10 text-right"
                                  x-text="percent(statSummary.total[row.k], statSummary.total.total) + '%'"></span>
                        </div>
                    </template>
                </div>

                <div class="border-t border-slate-100 mt-4 pt-3 flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500">Tỷ lệ không bị trừ điểm</span>
                    <span class="text-sm font-black text-slate-800" x-text="dutyRate(statSummary.total) + '%'"></span>
                </div>
            </div>

            <!-- 5. SO SÁNH THEO KHỐI — chỉ Ban Điều Hành -->
            <div x-show="showBlockComparison" style="display: none;" class="bg-white rounded-card p-5 shadow-sm border border-slate-100 mb-5">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">So sánh theo khối</h3>
                <div class="space-y-3.5">
                    <template x-for="row in statSummary.byBlock" :key="row.name">
                        <div>
                            <div class="flex justify-between items-baseline mb-1.5">
                                <span class="text-sm font-bold text-slate-700" x-text="row.name"></span>
                                <span class="text-sm font-black text-slate-800" x-text="row.rate + '%'"></span>
                            </div>
                            <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                                <div class="h-full rounded-full transition-all" :class="rateBarClass(row.rate)" :style="'width:' + row.rate + '%'"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- 6. SO SÁNH THEO LỚP — Ban Điều Hành và Trưởng khối -->
            <div x-show="showClassComparison" style="display: none;" class="bg-white rounded-card p-5 shadow-sm border border-slate-100 mb-5">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">So sánh theo lớp</h3>
                <div class="space-y-3.5">
                    <template x-for="row in statSummary.byClass" :key="row.name">
                        <div>
                            <div class="flex justify-between items-baseline mb-1.5">
                                <div class="min-w-0">
                                    <span class="text-sm font-bold text-slate-700" x-text="row.name"></span>
                                    <span class="text-micro text-slate-500 ml-1.5">
                                        vắng KP <span class="font-bold text-rose-500" x-text="row.stats.unexcused"></span>
                                    </span>
                                </div>
                                <span class="text-sm font-black text-slate-800 shrink-0" x-text="row.rate + '%'"></span>
                            </div>
                            <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                                <div class="h-full rounded-full transition-all" :class="rateBarClass(row.rate)" :style="'width:' + row.rate + '%'"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- 7. EM CẦN QUAN TÂM -->
            <div class="bg-white rounded-card p-5 shadow-sm border border-slate-100 mb-5">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Em cần quan tâm</h3>
                <p class="text-micro text-slate-500 mb-4 leading-snug">Nghỉ không phép nhiều nhất trong kỳ — nên gọi hỏi thăm phụ huynh.</p>

                <div class="space-y-2.5">
                    <template x-for="item in studentsOfConcern" :key="item.student.id">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 shrink-0 rounded-2xl bg-rose-50 border border-rose-100 flex flex-col items-center justify-center text-rose-600">
                                <span class="text-sm font-black leading-none" x-text="item.stats.unexcused"></span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-black text-slate-800 leading-snug">
                                    <span class="font-normal text-slate-500" x-text="item.student.holyName"></span>
                                    <span x-text="item.student.name"></span>
                                </p>
                                <p class="text-micro font-medium text-slate-500">
                                    <span x-text="item.student.className"></span>
                                    <span class="text-slate-300 mx-1">•</span>
                                    có mặt <span class="font-bold text-slate-500" x-text="attendRate(item.stats) + '%'"></span>
                                </p>
                            </div>
                            <a :href="'tel:' + item.student.motherPhone" :aria-label="'Gọi mẹ của ' + item.student.name" class="tap-safe w-9 h-9 shrink-0 bg-rose-50 rounded-full flex items-center justify-center text-rose-500 active:scale-90 transition-transform border border-rose-100">
                                <i data-lucide="phone" class="w-4 h-4"></i>
                            </a>
                        </div>
                    </template>

                    <div x-show="studentsOfConcern.length === 0" style="display: none;" class="text-center py-6">
                        <i data-lucide="party-popper" class="tap-safe w-8 h-8 mx-auto text-emerald-300 mb-2"></i>
                        <p class="text-slate-500 font-medium text-sm">Không em nào nghỉ không phép. Tuyệt vời!</p>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <!-- 8. ĐƠN XIN PHÉP TRONG KỲ -->
    <div class="bg-white rounded-card p-5 shadow-sm border border-slate-100 mb-5">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Đơn xin phép trong kỳ</h3>
        <div class="grid grid-cols-3 gap-3">
            <div class="text-center">
                <p class="text-2xl font-black text-amber-700" x-text="statLeaveCounts.pending"></p>
                <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Chờ duyệt</p>
            </div>
            <div class="text-center border-x border-slate-100">
                <p class="text-2xl font-black text-emerald-600" x-text="statLeaveCounts.approved"></p>
                <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Đã duyệt</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-black text-rose-500" x-text="statLeaveCounts.rejected"></p>
                <p class="text-micro font-bold text-slate-500 uppercase tracking-wide">Từ chối</p>
            </div>
        </div>
    </div>

    <!-- 9. CƠ CẤU SĨ SỐ -->
    <div class="bg-white rounded-card p-5 shadow-sm border border-slate-100">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Cơ cấu sĩ số</h3>

        <div class="flex items-center gap-3 mb-4">
            <div class="flex-1 bg-blue-50 rounded-2xl p-3 text-center border border-blue-100">
                <p class="text-xl font-black text-blue-600" x-text="statRoster.male"></p>
                <p class="text-micro font-bold text-blue-400 uppercase tracking-wide">Nam</p>
            </div>
            <div class="flex-1 bg-rose-50 rounded-2xl p-3 text-center border border-rose-100">
                <p class="text-xl font-black text-rose-500" x-text="statRoster.female"></p>
                <p class="text-micro font-bold text-rose-400 uppercase tracking-wide">Nữ</p>
            </div>
        </div>

        <div class="space-y-2.5">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-slate-600">Đang sinh hoạt</span>
                <span class="text-sm font-black text-emerald-600" x-text="statRoster.active"></span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-slate-600">Dừng sinh hoạt</span>
                <span class="text-sm font-black text-slate-500" x-text="statRoster.paused"></span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-slate-600">Chuyển xứ</span>
                <span class="text-sm font-black text-slate-500" x-text="statRoster.moved"></span>
            </div>
        </div>
    </div>
</div>
