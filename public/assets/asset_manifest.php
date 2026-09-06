<?php
/**
 * DANH SÁCH TỆP TĨNH — nguồn duy nhất.
 *
 * Dùng chung cho:
 *   - index.php  : nạp file lẻ khi DEV (localhost) để sửa file nào thấy ngay.
 *   - js/bundle.php, css/bundle.php : gộp thành một tệp khi PRODUCTION,
 *     giảm ~30 request còn vài request.
 *
 * Sửa thứ tự ở ĐÂY là cả nạp-lẻ lẫn bản-gộp theo cùng — không lệch.
 * (toast nạp trước các module; app.js gộp cuối — hai cái đó cố định trong
 *  bundle.php, không nằm trong danh sách này.)
 */
return [
    // Thứ tự KHÔNG đổi tuỳ tiện: nền tảng trước, shell/dashboard sau.
    'js_modules' => [
        'core', 'programs', 'students', 'student_profile', 'attendance', 'qrscan', 'qrcard',
        'leave', 'birthdays', 'announcements', 'stats', 'analytics', 'scores',
        'reports', 'promotion', 'org', 'push', 'access', 'dashboard', 'shell',
        'calendar',
    ],
    // Thứ tự CSS = thứ tự cascade: tailwind (nền) trước, phần ghi đè sau.
    'css' => ['tailwind', 'font', 'app', 'dark', 'skeleton', 'analytics', 'toast'],
];
