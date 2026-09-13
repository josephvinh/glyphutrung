<?php
/* ==========================================================
   BĂNG BÁO "ĐANG TẢI SỐ LIỆU" (bước 2)
   Điểm danh + điểm được tải NỀN sau khi mở app (xem core.js loadHeavy).
   Trong lúc chờ, các màn cần số liệu này hiện băng báo để người dùng
   biết số đang về — tránh hiểu nhầm "chưa có dữ liệu".
   Nhúng ở đầu panel: include partial_heavy_loading.php
   ========================================================== */
?>
<div x-show="!heavyLoaded" style="display:none"
     class="mb-4 flex items-center gap-2.5 rounded-2xl bg-blue-50 border border-blue-100 px-4 py-3">
    <i data-lucide="refresh-cw" class="w-4 h-4 text-blue-600 shrink-0"></i>
    <p class="text-sm font-semibold text-blue-700 leading-snug">Đang tải số liệu điểm danh &amp; điểm…</p>
</div>
