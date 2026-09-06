<!-- ==========================================================
     THANH ĐIỀU HƯỚNG GỘP: BÁO CÁO
     Dùng chung cho 2 màn Thống kê / Phân tích — gom về
     một chỗ cho dễ quản lý. Mỗi thẻ vẫn là một module riêng (đổi thẻ =
     đổi module, tức thì vì dữ liệu đã nạp sẵn), chỉ khác là có chung
     thanh thẻ này ở đầu.
     ========================================================== -->
<div class="bg-white rounded-field p-1.5 shadow-sm border border-slate-100 flex gap-1.5 mb-5">
    <button @click="reportsTab='stats'; if(openStats) openStats(true)" type="button"
            class="flex-1 py-2.5 rounded-2xl font-bold text-micro transition-colors flex items-center justify-center gap-1.5"
            :class="reportsTab === 'stats' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-slate-500'">
        <i data-lucide="bar-chart-3" class="w-4 h-4"></i> Thống kê
    </button>
    <button @click="reportsTab='analytics'; if(openAnalytics) openAnalytics(true)" type="button"
            class="flex-1 py-2.5 rounded-2xl font-bold text-micro transition-colors flex items-center justify-center gap-1.5"
            :class="reportsTab === 'analytics' ? 'bg-purple-600 text-white shadow-md shadow-purple-200' : 'text-slate-500'">
        <i data-lucide="bar-chart-2" class="w-4 h-4"></i> Phân tích
    </button>
</div>
