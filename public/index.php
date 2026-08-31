<?php
/**
 * CỔNG VÀO
 * Chưa đăng nhập thì dừng ở màn đăng nhập, không nạp một dòng
 * dữ liệu nghiệp vụ nào ra trình duyệt.
 */
require __DIR__ . '/api/_bootstrap_page.php';
$me = current_member();
if (!$me || $me['must_change_pw']) {
    include __DIR__ . '/../views/layout_login.php';
    exit;
}
$bootData = page_bootstrap($me);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <!-- Cho phép phóng to (GLV lớn tuổi đọc chữ nhỏ) + hỗ trợ tai thỏ iPhone -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">

    <!-- BIỂU TƯỢNG APP
         icon.svg   : tab trình duyệt, nét sắc ở mọi cỡ
         icon-180   : iOS "Thêm vào màn hình chính" (iOS không nhận SVG)
         manifest   : Android, để cài như một app riêng
         Đổi logo: thay assets/img/icon.svg rồi chạy  node build/tao_icon.cjs -->
    <link rel="icon" href="/assets/img/icon.svg" type="image/svg+xml">
    <link rel="icon" href="/assets/img/icon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="/assets/img/icon-180.png">
    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="TNTT">
    <title>TNTT Super App</title>

    <!-- Tailwind biên dịch sẵn. Token thiết kế khai trong tailwind.config.js
         ở gốc dự án; chạy `npm run css` sau khi thêm lớp mới. -->
    <link rel="stylesheet" href="assets/css/tailwind.css?v=<?php echo @filemtime(__DIR__ . '/assets/css/tailwind.css') ?: 0; ?>">
    <!-- THƯ VIỆN ĐẶT NGAY TRÊN MÁY CHỦ MÌNH, KHÔNG LẤY TỪ CDN NGOÀI.
         Đo thực tế trên đường truyền tốt: lấy từ unpkg mất 356ms, từ
         jsdelivr 94ms, còn từ máy chủ mình 9ms. Trên điện thoại 4G sóng
         yếu thì khoảng cách đó giãn ra thành mấy giây, vì mỗi tên miền
         lạ phải tra DNS rồi bắt tay TLS lại từ đầu.

         lucide-icons.js là bản RÚT GỌN, 16 KB thay vì 410 KB: chỉ giữ
         76 icon app thật sự dùng thay vì cả 2.031 cái.
         Dựng lại sau khi thêm icon mới:  node build/tao_lucide.cjs

         Cả ba đều defer — thẻ lucide trước đây KHÔNG có defer, nên
         trình duyệt phải tải xong 410 KB rồi mới vẽ được gì lên màn hình. -->
    <script defer src="assets/js/vendor/alpine-collapse.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/vendor/alpine-collapse.js') ?: 0; ?>"></script>
    <script defer src="assets/js/vendor/alpine.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/vendor/alpine.js') ?: 0; ?>"></script>
    <script defer src="assets/js/vendor/lucide-icons.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/vendor/lucide-icons.js') ?: 0; ?>"></script>

    <!-- Độ đậm chữ: 400 500 600 700 900.
         Trước đây khai 800 (giao diện KHÔNG dùng chỗ nào) mà thiếu 900
         (font-black, dùng 151 chỗ) — nên mọi tiêu đề đang bị trình duyệt
         bôi đậm giả, nét bệt và hơi lệch so với thiết kế. -->
    <!-- Phông đặt tại máy chủ mình, không còn gọi fonts.googleapis.com
         và fonts.gstatic.com. Hai tên miền đó mỗi cái bắt điện thoại tra
         DNS rồi bắt tay TLS lại từ đầu, mà thẻ <link> lại chặn hiển thị.
         Dựng lại bằng:  node build/tao_font.cjs -->
    <link rel="stylesheet" href="assets/css/font.css?v=<?php echo @filemtime(__DIR__ . '/assets/css/font.css') ?: 0; ?>">
    <link rel="stylesheet" href="assets/css/app.css?v=<?php echo @filemtime(__DIR__ . '/assets/css/app.css') ?: 0; ?>">
    <link rel="stylesheet" href="assets/css/dark.css?v=<?php echo @filemtime(__DIR__ . '/assets/css/dark.css') ?: 0; ?>">
    <link rel="stylesheet" href="assets/css/skeleton.css?v=<?php echo @filemtime(__DIR__ . '/assets/css/skeleton.css') ?: 0; ?>">
</head>
<body class="text-slate-800 antialiased overflow-x-hidden">

    <!-- ==========================================================
         KHUNG APP
         - Điện thoại : full màn hình, nền phẳng (y như thiết kế cũ)
         - Tablet      : giãn tới max-w-xl, thành thẻ nổi bo tròn
         - Desktop     : giãn tới max-w-2xl
         ========================================================== -->
    <div x-data="tnttApp" x-cloak class="app-shell has-sidebar max-w-md sm:max-w-xl lg:max-w-6xl xl:max-w-7xl">

        <!-- THANH BÊN (chỉ máy tính) -->
        <?php include __DIR__ . '/../views/layout_sidebar.php'; ?>

        <!-- CỘT NỘI DUNG -->
        <div class="app-content">

        <!-- HEADER -->
        <?php include __DIR__ . '/../views/layout_header.php'; ?>

        <!-- Vùng nội dung: padding ngang tập trung 1 chỗ duy nhất -->
        <main class="app-main px-4 sm:px-6">


            <!-- TRANG CHỦ -->
            <div x-show="currentModule === 'dashboard'" class="module-panel">
                <?php include __DIR__ . '/../views/layout_hero.php'; ?>
                <?php include __DIR__ . '/../views/module_menu.php'; ?>
            </div>

            <!-- MODULE DANH SÁCH & CHƯƠNG TRÌNH -->
            <?php include __DIR__ . '/../views/module_students.php'; ?>
            <?php include __DIR__ . '/../views/module_attendance.php'; ?>
            <?php include __DIR__ . '/../views/module_leave.php'; ?>
            <?php include __DIR__ . '/../views/module_birthdays.php'; ?>
            <?php include __DIR__ . '/../views/module_announcements.php'; ?>
            <?php include __DIR__ . '/../views/module_stats.php'; ?>
            <?php include __DIR__ . '/../views/module_org.php'; ?>
            <?php include __DIR__ . '/../views/module_staff.php'; ?>
            <?php include __DIR__ . '/../views/module_years.php'; ?>
            <?php include __DIR__ . '/../views/module_reports.php'; ?>
            <?php include __DIR__ . '/../views/module_settings.php'; ?>
            <?php include __DIR__ . '/../views/module_scores.php'; ?>
            <?php include __DIR__ . '/../views/module_promotion.php'; ?>
            <?php include __DIR__ . '/../views/module_programs.php'; ?>
            <?php include __DIR__ . '/../views/module_calendar.php'; ?>

        </main>

        </div><!-- /.app-content -->

        <!-- THANH ĐIỀU HƯỚNG DƯỚI (ẩn trên máy tính, xem app.css) -->
        <?php include __DIR__ . '/../views/layout_bottomnav.php'; ?>

    </div>

    <!-- Link file JS (Có Phá Cache để điện thoại luôn load mới) -->
    <!-- Dữ liệu phiên và cấu hình, nhúng sẵn để app.js không phải chờ thêm một vòng mạng -->
    <script>window.TNTT_BOOT = <?php echo json_encode($bootData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;</script>
    <!-- Các mảnh của component tnttApp. Phải nạp TRƯỚC app.js vì
         app.js chỉ làm nhiệm vụ gộp chúng lại. -->
    <?php foreach (['core', 'programs', 'students', 'attendance', 'qrscan', 'qrcard', 'leave', 'birthdays', 'announcements', 'stats', 'scores', 'reports', 'promotion', 'org', 'push', 'access', 'dashboard', 'shell', 'calendar'] as $m): ?>
    <script src="assets/js/modules/<?= $m ?>.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/modules/' . $m . '.js') ?: 0; ?>"></script>
    <?php endforeach; ?>

    <!-- Gộp các mảnh và đăng ký với Alpine -->
    <script src="assets/js/app.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/app.js') ?: 0; ?>"></script>
    <script>document.addEventListener('DOMContentLoaded', () => { lucide.createIcons(); });</script>
</body>
</html>
