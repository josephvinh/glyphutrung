<?php
/**
 * Ô NHẬP NGÀY / GIỜ THEO KHUÔN (dd/mm/yyyy, hh:mm, dd/mm/yyyy hh:mm) — thay cho <input type="date|time|datetime-local">.
 *
 * Ô gốc hiển thị theo ngôn ngữ/vùng của trình duyệt (máy en-US hiện mm/dd/yyyy, giờ AM/PM) và không ép được.
 * Ở đây là ô chữ có khuôn do chỉ thị Alpine x-ngay (assets/js/modules/ngay_nhap.js) điều khiển; dữ liệu bên
 * trong vẫn là yyyy-mm-dd / HH:mm / yyyy-mm-ddTHH:mm như ô gốc nên mã còn lại không phải đổi.
 *
 *   <?php ngay_input('form.birthDate', ['class' => 'w-full ...', 'attrs' => 'required min="1900-01-01"']); ?>
 *
 * Tham số $o:
 *   kind  'date' (mặc định) | 'time' | 'datetime'
 *   class lớp CSS của ô chữ (như ô gốc trước đây)
 *   wrap  lớp CSS thêm cho khung bọc (vd. 'flex-1 min-w-0' khi ô nằm trong hàng flex)
 *   attrs thuộc tính thô, TIN CẬY (required, min="…", max="…", :disabled="…", :class="…" …) — không đưa dữ liệu người dùng vào đây.
 */
function ngay_input(string $model, array $o = []): void
{
    $kind  = in_array(($o['kind'] ?? 'date'), ['date', 'time', 'datetime'], true) ? ($o['kind'] ?? 'date') : 'date';
    $e     = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES);
    $lopO  = trim('ngay-in ' . (string) ($o['class'] ?? ''));
    $lopKh = trim('ngay-wrap ' . (string) ($o['wrap'] ?? ''));
    $attrs = (string) ($o['attrs'] ?? '');
    $dir   = $kind === 'date' ? 'x-ngay' : 'x-ngay.' . $kind;
    $nat   = $kind === 'time' ? 'time' : ($kind === 'datetime' ? 'datetime-local' : 'date');
    $nhan  = $kind === 'time' ? 'Chọn giờ' : 'Chọn ngày từ lịch';
    // Biểu tượng (nét lucide): đồng hồ cho giờ, lịch cho ngày / ngày giờ
    $icon  = $kind === 'time'
        ? '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>'
        : '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>';
    // Dùng sprintf (không ghép chuỗi trong thuộc tính class) để test độ phủ CSS không đọc nhầm.
    echo sprintf(
        '<span class="%s"><input %s="%s" type="text" class="%s"%s>'
        . '<span class="ngay-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>'
        . '<input type="%s" class="ngay-native" tabindex="-1" aria-label="%s"></span></span>',
        $e($lopKh), $dir, $e($model), $e($lopO), $attrs !== '' ? ' ' . $attrs : '', $icon, $nat, $nhan
    );
}
