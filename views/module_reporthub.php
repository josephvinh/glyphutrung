<!-- MÀN HÌNH BÁO CÁO — chỉ còn Thống kê (đã bỏ Phân tích).
     Không đặt header riêng nữa: module_stats.php đã có tiêu đề + nút Xuất +
     chọn tháng của nó, khỏi trùng hai tầng tiêu đề. -->
<div data-module="reporthub" class="module-panel pt-6 pb-10 relative">
<?php include __DIR__ . '/partial_heavy_loading.php'; ?>
    <?php include __DIR__ . '/module_stats.php'; ?>
</div>
