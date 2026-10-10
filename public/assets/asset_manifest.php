<?php
/**
 * DANH SÁCH TỆP TĨNH — nguồn duy nhất.
 *
 * Dùng chung cho:
 *   - index.php  : nạp file lẻ khi DEV (localhost) để sửa file nào thấy ngay,
 *     VÀ nhúng danh sách này vào window.TNTT_MODULES.
 *   - js/bundle.php, css/bundle.php : gộp thành một tệp khi PRODUCTION,
 *     giảm ~30 request còn vài request.
 *   - app.js : đọc window.TNTT_MODULES để GỘP các mảnh vào component Alpine.
 *
 * => THÊM MODULE MỚI CHỈ KHAI Ở ĐÂY. Cả nạp, gộp bundle lẫn gộp component
 *    đều theo danh sách này, không còn hai danh sách lệch nhau.
 * (toast nạp trước các module nhưng KHÔNG gộp vào component; app.js là bộ
 *  gộp nên cũng không nằm trong danh sách — hai cái đó cố định trong bundle.php.)
 */
return [
    // Thứ tự KHÔNG đổi tuỳ tiện: nền tảng trước, shell/dashboard sau.
    'js_modules' => [
        // Core modules (loaded on startup)
        'router', 'core', 'xlsx_io', 'programs', 'access', 'students', 'student_profile', 'attendance', 'qrscan', 'qrcard',
        'leave', 'birthdays', 'announcements', 'stats', 'analytics', 'scores',
        'reports', 'promotion', 'org', 'push', 'dashboard', 'shell',
        'calendar', 'notes', 'library', 'gifts', 'rewards',
        // PWA: offline queue loaded early (before shell.initOfflineSupport runs)
        'offline_queue',
    ],
    // Thứ tự CSS = thứ tự cascade: tailwind (nền) trước, phần ghi đè sau.
    'css' => ['tailwind', 'font', 'app', 'skeleton', 'analytics', 'toast', 'brand'],

    // LAZY MODULES: Heavy modules loaded on demand
    // Usage: TNTT_LAZY.load('qrscan').then(m => m.init())
    'lazy_modules' => [
        'qrscan',    // QR scanning (18KB) - only for attendance module
        'stats',    // Statistics (24KB) - only for stats page
        'analytics', // Analytics (8KB) - only for analytics page
    ],
];
