<!-- ==========================================================
     THANH ĐIỀU HƯỚNG GỘP: THIẾU NHI
     Dùng chung cho 3 màn Danh sách / Điểm số / Phiếu liên lạc — gom về
     một chỗ cho dễ quản lý. Mỗi thẻ vẫn là một module riêng (đổi thẻ =
     đổi module, tức thì vì dữ liệu đã nạp sẵn), chỉ khác là có chung
     thanh thẻ này ở đầu.
     ========================================================== -->
<div class="flex items-center mb-4">
    <button aria-label="Quay lại trang chủ" @click="changeModule('dashboard')"
            class="tap-safe w-10 h-10 shrink-0 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center active:scale-90 transition-transform mr-4">
        <i data-lucide="chevron-left" class="w-6 h-6 text-slate-600"></i>
    </button>
    <h2 class="text-xl font-black text-slate-800 tracking-tight">Thiếu Nhi</h2>
</div>

<div class="bg-white rounded-field p-1.5 shadow-sm border border-slate-100 flex gap-1.5 mb-5 overflow-x-auto hide-scrollbar">
    <button @click="changeModule('students')" type="button"
            class="shrink-0 whitespace-nowrap px-4 py-2.5 sm:flex-1 rounded-2xl font-bold text-micro transition-colors flex items-center justify-center gap-1.5"
            :class="currentModule === 'students' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-slate-500'">
        <i data-lucide="users" class="w-4 h-4"></i> Danh sách
    </button>
    <button x-show="canAccess('scores')" @click="openScores()" type="button"
            class="shrink-0 whitespace-nowrap px-4 py-2.5 sm:flex-1 rounded-2xl font-bold text-micro transition-colors flex items-center justify-center gap-1.5"
            :class="currentModule === 'scores' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-slate-500'">
        <i data-lucide="graduation-cap" class="w-4 h-4"></i> Điểm số
    </button>
    <button x-show="canAccess('reports')" @click="openReports()" type="button"
            class="shrink-0 whitespace-nowrap px-4 py-2.5 sm:flex-1 rounded-2xl font-bold text-micro transition-colors flex items-center justify-center gap-1.5"
            :class="currentModule === 'reports' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-slate-500'">
        <i data-lucide="clipboard-list" class="w-4 h-4"></i> Phiếu liên lạc
    </button>
</div>
