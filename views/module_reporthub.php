<!-- MÀN HÌNH HUB BÁO CÁO — gộp Thống kê + Phân tích qua tab -->
<div data-module="reporthub" class="module-panel pt-6 pb-10 relative">

    <!-- 1. THANH ĐIỀU HƯỚNG -->
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center min-w-0">
            <button aria-label="Quay lại trang chủ" @click="changeModule('dashboard')"
                    class="tap-safe w-10 h-10 shrink-0 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-4">
                <i data-lucide="chevron-left" class="w-6 h-6 text-slate-600"></i>
            </button>
            <h2 class="text-xl font-black text-slate-800 tracking-tight">Báo Cáo</h2>
        </div>
        <!-- Nút xuất chỉ hiện khi đang ở tab Thống kê -->
        <button x-show="reportsTab === 'stats'" @click="exportStatsCSV()"
                class="shrink-0 flex items-center gap-1.5 px-3 py-2 bg-blue-50 text-blue-600 rounded-xl font-bold text-xs active:scale-95 transition-transform border border-blue-100 shadow-sm">
            <i data-lucide="file-up" class="w-4 h-4"></i> Xuất
        </button>
    </div>

    <!-- 2. TAB THỐNG KÊ / PHÂN TÍCH -->
    <?php include __DIR__ . '/partial_reports_tabs.php'; ?>

    <!-- 3. NỘI DUNG TAB — dùng x-show trên div ngoài cùng của mỗi file con.
         stats/analytics vẫn giữ data-module để dùng standalone; x-show ghi đè display
         khi reporthub đang active (Alpine x-show dùng inline style, cao hơn CSS display).
         Khi standalone, data-module đứng độc lập nên x-show không can thiệp. -->
    <div x-show="reportsTab === 'stats'">
        <?php include __DIR__ . '/module_stats.php'; ?>
    </div>
    <div x-show="reportsTab === 'analytics'">
        <?php include __DIR__ . '/module_analytics.php'; ?>
    </div>
</div>
