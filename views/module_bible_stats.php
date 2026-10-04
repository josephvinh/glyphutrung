<!-- ==========================================================
     BIBLE STATS — Card thống kê Lời Chúa Mỗi Ngày
     Xem: api/bible.php?action=stats và ?action=list
     Premium Design với Divine Theme
     ========================================================== -->
<div x-show="isAdmin || user.role === 'bdh'" x-cloak
     class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#1e3a5f] via-[#234b6e] to-[#1e3a5f] p-5 shadow-lg"
     style="background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 50%, #1e3a5f 100%);">

    <!-- Decorative Background Elements -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <!-- Cross watermark -->
        <div class="absolute -top-8 -right-8 text-[120px] opacity-[0.03] select-none">✝</div>
        <!-- Gradient overlay -->
        <div class="absolute inset-0 bg-gradient-to-t from-black/10 to-transparent"></div>
    </div>

    <!-- Header -->
    <div class="relative flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <!-- Icon Container -->
            <div class="relative">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#ffd700] to-[#ffb347] flex items-center justify-center shadow-lg">
                    <!-- Book Icon SVG -->
                    <svg class="w-5 h-5 text-[#1e3a5f]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                    </svg>
                </div>
                <!-- Pulse indicator -->
                <div class="absolute -top-1 -right-1 w-3 h-3 bg-emerald-400 rounded-full animate-pulse"></div>
            </div>
            <div>
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    Lời Chúa Mỗi Ngày
                </h3>
                <p class="text-[11px] text-white/60">Theo dõi lượt truy cập</p>
            </div>
        </div>

        <!-- Refresh Button -->
        <button @click="refreshBibleStats()"
                type="button"
                :disabled="bibleLoading"
                class="group relative p-2 rounded-xl bg-white/10 hover:bg-white/20 transition-all duration-200 disabled:opacity-50">
            <svg class="w-4 h-4 text-white/80 group-hover:text-white transition-colors"
                 :class="{ 'animate-spin': bibleLoading }"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 2v6h-6"></path>
                <path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path>
                <path d="M3 22v-6h6"></path>
                <path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path>
            </svg>
        </button>
    </div>

    <!-- Stats Grid: 3 premium boxes -->
    <div class="relative grid grid-cols-3 gap-3 mb-4">
        <!-- Hôm nay -->
        <div class="relative group">
            <div class="absolute inset-0 bg-gradient-to-br from-[#ffd700]/20 to-transparent rounded-xl blur-sm opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative bg-white/10 backdrop-blur-sm rounded-xl p-3 text-center border border-white/10">
                <div class="w-8 h-8 mx-auto mb-1 rounded-lg bg-[#ffd700]/20 flex items-center justify-center">
                    <svg class="w-4 h-4 text-[#ffd700]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                </div>
                <p class="text-lg font-black text-[#ffd700]" x-text="bibleStats.today_unique_ips ?? 0">0</p>
                <p class="text-[10px] text-white/60 uppercase tracking-wide">Hôm nay</p>
            </div>
        </div>

        <!-- Tổng IP -->
        <div class="relative group">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-400/20 to-transparent rounded-xl blur-sm opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative bg-white/10 backdrop-blur-sm rounded-xl p-3 text-center border border-white/10">
                <div class="w-8 h-8 mx-auto mb-1 rounded-lg bg-emerald-400/20 flex items-center justify-center">
                    <svg class="w-4 h-4 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <p class="text-lg font-black text-white" x-text="bibleStats.unique_ips ?? 0">0</p>
                <p class="text-[10px] text-white/60 uppercase tracking-wide">Tổng IP</p>
            </div>
        </div>

        <!-- Lượt lấy -->
        <div class="relative group">
            <div class="absolute inset-0 bg-gradient-to-br from-sky-400/20 to-transparent rounded-xl blur-sm opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative bg-white/10 backdrop-blur-sm rounded-xl p-3 text-center border border-white/10">
                <div class="w-8 h-8 mx-auto mb-1 rounded-lg bg-sky-400/20 flex items-center justify-center">
                    <svg class="w-4 h-4 text-sky-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                </div>
                <p class="text-lg font-black text-white" x-text="bibleStats.total_requests ?? 0">0</p>
                <p class="text-[10px] text-white/60 uppercase tracking-wide">Lượt lấy</p>
            </div>
        </div>
    </div>

    <!-- Recent IPs List -->
    <div class="relative border-t border-white/10 pt-3">
        <div class="flex items-center justify-between mb-2">
            <p class="text-[11px] font-semibold text-white/50 uppercase tracking-wider">Lượt gần đây</p>
            <span class="text-[10px] text-white/40" x-text="bibleList.length + ' records'"></span>
        </div>

        <div class="space-y-1.5 max-h-36 overflow-y-auto scrollbar-thin scrollbar-thumb-white/20 scrollbar-track-transparent">
            <template x-for="row in bibleList.slice(0, 8)" :key="row.id">
                <div class="group flex items-center justify-between p-2 rounded-lg bg-white/5 hover:bg-white/10 transition-colors duration-150">
                    <div class="flex items-center gap-2 min-w-0 flex-1">
                        <!-- IP Icon -->
                        <div class="w-6 h-6 rounded bg-white/10 flex items-center justify-center shrink-0">
                            <svg class="w-3 h-3 text-white/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="2" y1="12" x2="22" y2="12"></line>
                                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                            </svg>
                        </div>
                        <span class="font-mono text-xs text-white/80 truncate" x-text="row.ip"></span>
                    </div>
                    <span class="text-[10px] text-white/40 shrink-0 ml-2 group-hover:text-white/60 transition-colors" x-text="timeAgo(row.fetched_at)"></span>
                </div>
            </template>

            <!-- Empty State -->
            <div x-show="bibleList.length === 0 && !bibleLoading" style="display: none;"
                 class="flex flex-col items-center justify-center py-6 text-center">
                <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center mb-2">
                    <svg class="w-6 h-6 text-white/30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                    </svg>
                </div>
                <p class="text-xs text-white/40">Chưa có dữ liệu</p>
            </div>

            <!-- Loading State -->
            <div x-show="bibleLoading" style="display: none;"
                 class="flex items-center justify-center py-6">
                <div class="w-6 h-6 border-2 border-white/20 border-t-white/60 rounded-full animate-spin"></div>
            </div>
        </div>
    </div>

    <!-- Footer Stats Bar -->
    <div class="relative mt-3 pt-3 border-t border-white/10">
        <div class="flex items-center justify-between text-[10px] text-white/40">
            <span>Cập nhật lần cuối:</span>
            <span x-text="bibleStats.last_request ? timeAgo(bibleStats.last_request) : '—'"></span>
        </div>
    </div>

</div>

<style>
/* Custom scrollbar for dark theme */
.scrollbar-thin::-webkit-scrollbar {
    width: 4px;
}
.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 2px;
}
.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}
</style>
