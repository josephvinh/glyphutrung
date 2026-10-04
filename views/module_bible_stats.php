<!-- ==========================================================
     BIBLE STATS — Card thống kê Lời Chúa Mỗi Ngày
     Xem: api/bible.php?action=stats và ?action=list
     ========================================================== -->
<div x-show="isAdmin || user.role === 'bdh'" x-cloak class="bg-white rounded-card p-5 shadow-sm border border-amber-100">

    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-black text-slate-800 flex items-center gap-2">
            <i data-lucide="book-open" class="w-4 h-4 text-amber-500"></i> Lời Chúa Mỗi Ngày
        </h3>
        <button @click="refreshBibleStats()" type="button"
                :disabled="bibleLoading"
                class="text-micro font-bold text-amber-600 flex items-center gap-1 disabled:opacity-50">
            <i data-lucide="rotate-cw" class="w-3.5 h-3.5" :class="{ 'animate-spin': bibleLoading }"></i>
            Làm mới
        </button>
    </div>

    <!-- Stats Grid: 3 boxes -->
    <div class="grid grid-cols-3 gap-3 mb-4">
        <!-- Hôm nay -->
        <div class="bg-amber-50 rounded-xl p-3 text-center">
            <p class="text-micro text-amber-600 font-medium mb-1">Hôm nay</p>
            <p class="text-xl font-black text-amber-700" x-text="bibleStats.today_unique_ips">0</p>
            <p class="text-micro text-amber-500">lượt</p>
        </div>
        <!-- Tổng IP -->
        <div class="bg-slate-50 rounded-xl p-3 text-center">
            <p class="text-micro text-slate-500 font-medium mb-1">Tổng IP</p>
            <p class="text-xl font-black text-slate-700" x-text="bibleStats.unique_ips">0</p>
            <p class="text-micro text-slate-400">duy nhất</p>
        </div>
        <!-- Lượt lấy -->
        <div class="bg-slate-50 rounded-xl p-3 text-center">
            <p class="text-micro text-slate-500 font-medium mb-1">Lượt lấy</p>
            <p class="text-xl font-black text-slate-700" x-text="bibleStats.total_requests">0</p>
            <p class="text-micro text-slate-400">lần</p>
        </div>
    </div>

    <!-- Recent IPs List -->
    <div class="border-t border-slate-100 pt-3">
        <p class="text-micro font-semibold text-slate-500 mb-2">Lượt gần đây</p>
        <div class="space-y-1.5 max-h-40 overflow-y-auto">
            <template x-for="row in bibleList.slice(0, 10)" :key="row.id">
                <div class="flex items-center justify-between text-micro py-1 px-2 rounded-lg bg-slate-50 hover:bg-slate-100 transition-colors">
                    <div class="min-w-0 flex-1 mr-2">
                        <span class="font-mono text-slate-600 text-xs truncate block" x-text="row.ip"></span>
                    </div>
                    <span class="text-slate-400 shrink-0" x-text="timeAgo(row.fetched_at)"></span>
                </div>
            </template>
            <div x-show="bibleList.length === 0 && !bibleLoading" style="display: none;"
                 class="text-center py-4 text-micro text-slate-400">
                Chưa có dữ liệu nào.
            </div>
            <div x-show="bibleLoading" style="display: none;"
                 class="text-center py-4 text-micro text-slate-400">
                Đang tải...
            </div>
        </div>
    </div>

</div>
