<!-- MÀN HÌNH PHÂN TÍCH ĐIỂM DANH — chỉ trong hub Báo cáo (tab Phân tích).
     KHÔNG đặt data-module (xem lý do ở module_stats.php). -->
<div class="module-panel pt-6 pb-24 relative">

    <!-- 1. THANH ĐIỀU HƯỚNG -->
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center min-w-0">
            <button aria-label="Quay lại trang chủ" @click="changeModule('dashboard')" class="tap-safe w-10 h-10 shrink-0 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-4">
                <i data-lucide="chevron-left" class="w-6 h-6 text-slate-600"></i>
            </button>
            <h2 class="text-xl font-black text-slate-800 tracking-tight">Phân Tích Điểm Danh</h2>
        </div>
    </div>

    <!-- 2. OVERVIEW STATS -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
        <!-- Total Sessions -->
        <div class="analytics-stat-card">
            <div class="tap-safe w-9 h-9 rounded-xl bg-blue-50 text-blue-500 flex items-center justify-center mb-2">
                <i data-lucide="calendar-check" class="w-4 h-4"></i>
            </div>
            <p class="text-2xl font-black text-slate-800 leading-none" x-text="totalSessionsDisplay">0</p>
            <p class="text-micro font-bold text-slate-500 uppercase tracking-wide mt-1">Tổng buổi</p>
        </div>

        <!-- Attendance Rate -->
        <div class="analytics-stat-card">
            <div class="tap-safe w-9 h-9 rounded-xl bg-emerald-50 text-emerald-500 flex items-center justify-center mb-2">
                <i data-lucide="trending-up" class="w-4 h-4"></i>
            </div>
            <p class="text-2xl font-black leading-none"
               :class="attendanceRate >= 75 ? 'text-emerald-600' : (attendanceRate >= 50 ? 'text-amber-500' : 'text-rose-500')">
                <span x-text="attendanceRate || 0">0</span><span class="text-base">%</span>
            </p>
            <p class="text-micro font-bold text-slate-500 uppercase tracking-wide mt-1">Có mặt</p>
        </div>

        <!-- Excused Rate -->
        <div class="analytics-stat-card">
            <div class="tap-safe w-9 h-9 rounded-xl bg-amber-50 text-amber-500 flex items-center justify-center mb-2">
                <i data-lucide="file-text" class="w-4 h-4"></i>
            </div>
            <p class="text-2xl font-black text-amber-600 leading-none">
                <span x-text="excusedRate">0</span><span class="text-base">%</span>
            </p>
            <p class="text-micro font-bold text-slate-500 uppercase tracking-wide mt-1">Nghỉ phép</p>
        </div>

        <!-- Unexcused Rate -->
        <div class="analytics-stat-card">
            <div class="tap-safe w-9 h-9 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center mb-2">
                <i data-lucide="alert-circle" class="w-4 h-4"></i>
            </div>
            <p class="text-2xl font-black text-rose-600 leading-none">
                <span x-text="unexcusedRate">0</span><span class="text-base">%</span>
            </p>
            <p class="text-micro font-bold text-slate-500 uppercase tracking-wide mt-1">Không phép</p>
        </div>
    </div>

    <!-- 3. WEEKLY ATTENDANCE CHART -->
    <div class="analytics-chart mb-5">
        <div class="flex justify-between items-baseline mb-4">
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Điểm danh theo tuần</h3>
            <span class="text-micro font-bold text-slate-500">4 tuần gần nhất</span>
        </div>

        <!-- Bar Chart -->
        <div class="analytics-bar-chart">
            <template x-for="week in weeklyData" :key="week.week">
                <div class="analytics-bar-wrapper">
                    <span class="text-xs font-bold text-slate-600 mb-1" x-text="week.rate + '%'"></span>
                    <div class="analytics-bar analytics-bar-present"
                         :style="'height:' + getBarHeight(week.present, week.total) + 'px'"></div>
                    <span class="analytics-bar-label" x-text="week.label"></span>
                </div>
            </template>
        </div>

        <!-- Legend -->
        <div class="analytics-legend">
            <span class="analytics-legend-item">
                <span class="analytics-legend-dot bg-emerald-500"></span>
                Có mặt
            </span>
            <span class="analytics-legend-item">
                <span class="analytics-legend-dot bg-amber-400"></span>
                Nghỉ phép
            </span>
            <span class="analytics-legend-item">
                <span class="analytics-legend-dot bg-rose-500"></span>
                Không phép
            </span>
        </div>
    </div>

    <!-- 4. CLASS BREAKDOWN -->
    <div class="analytics-chart mb-5">
        <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4">Điểm danh theo lớp</h3>

        <div class="space-y-4">
            <template x-for="(cls, index) in classData" :key="cls.id">
                <div>
                    <div class="flex justify-between items-baseline mb-1.5">
                        <span class="text-sm font-bold text-slate-700 truncate" x-text="cls.name"></span>
                        <span class="text-sm font-black text-slate-800 shrink-0 ml-2" x-text="cls.rate + '%'"></span>
                    </div>
                    <div class="analytics-progress">
                        <div class="analytics-progress-bar"
                             :class="progressClass(cls.rate)"
                             :style="'width:' + cls.rate + '%'"></div>
                    </div>
                </div>
            </template>

            <!-- Empty State -->
            <div x-show="classData.length === 0" style="display: none;" class="text-center py-6">
                <i data-lucide="inbox" class="w-8 h-8 mx-auto text-slate-300 mb-2"></i>
                <p class="text-slate-500 font-medium text-sm">Chưa có dữ liệu theo lớp</p>
            </div>
        </div>
    </div>

    <!-- 5. LOW ATTENDANCE STUDENTS -->
    <div class="analytics-chart">
        <div class="flex justify-between items-baseline mb-1">
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Cần chú ý</h3>
            <span class="text-micro font-bold text-rose-500" x-text="lowAttendance.length + ' em'"></span>
        </div>
        <p class="text-micro text-slate-500 mb-4 leading-snug">Học sinh có tỷ lệ có mặt dưới 70%</p>

        <div>
            <template x-for="(student, index) in lowAttendance" :key="student.id">
                <div class="analytics-student-card">
                    <div class="analytics-student-info">
                        <div class="analytics-avatar" :class="avatarClass(index)">
                            <span x-text="student.holyName ? student.holyName.charAt(0) : '?'"></span>
                        </div>
                        <div>
                            <p class="text-sm font-black text-slate-800 leading-snug">
                                <span class="font-normal text-slate-500" x-text="student.holyName"></span>
                                <span x-text="student.name"></span>
                            </p>
                            <p class="text-micro font-medium text-slate-500" x-text="student.class"></p>
                        </div>
                    </div>
                    <span class="analytics-badge bg-rose-100 text-rose-700" x-text="student.rate + '%'"></span>
                </div>
            </template>

            <!-- Empty State -->
            <div x-show="lowAttendance.length === 0" style="display: none;" class="text-center py-8">
                <i data-lucide="party-popper" class="w-10 h-10 mx-auto text-emerald-300 mb-3"></i>
                <p class="text-slate-500 font-medium text-sm">Không có học sinh nào cần chú ý</p>
                <p class="text-slate-500 text-xs mt-1">Tất cả đều có tỷ lệ điểm danh tốt!</p>
            </div>
        </div>
    </div>
</div>
