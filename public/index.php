<?php
/**
 * CỔNG VÀO
 * Chưa đăng nhập thì dừng ở màn đăng nhập, không nạp một dòng
 * dữ liệu nghiệp vụ nào ra trình duyệt.
 */
require __DIR__ . '/api/_bootstrap_page.php';
$me = current_member();
// Khách chưa đăng nhập: hiện trang chủ điều hướng; bấm "Đăng nhập quản lý"
// (?dangnhap=1) mới vào form đăng nhập. Người cần đổi mật khẩu vào thẳng form.
if (!$me && !isset($_GET['dangnhap'])) {
    include __DIR__ . '/../views/layout_landing.php';
    exit;
}
if (!$me || $me['must_change_pw']) {
    include __DIR__ . '/../views/layout_login.php';
    exit;
}
$bootData = page_bootstrap($me);

/**
 * Dấu phá cache cho tệp tĩnh.
 *   - Bản thật: ?v = filemtime (đổi khi sửa tệp -> cache được, deploy là mới).
 *   - Máy dev (localhost): thêm số ngẫu nhiên -> trình duyệt LUÔN tải bản mới,
 *     khỏi cảnh sửa code mà vẫn thấy bản cũ do cache HTTP giữ cùng ?v.
 */
$__dev = in_array(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0], ['localhost', '127.0.0.1'], true);
function asset_v(string $file): string {
    global $__dev;
    $m = @filemtime($file) ?: 0;
    return $__dev ? $m . '-' . mt_rand() : (string) $m;
}

/* Bản gộp cho production: nạp 1 tệp JS + 1 tệp CSS thay vì ~30, nhẹ hơn hẳn
   (nhất là điện thoại). Dev thì vẫn nạp lẻ để sửa file nào thấy ngay. */
$__manifest = require __DIR__ . '/assets/asset_manifest.php';
$__jsFiles = array_merge(
    [__DIR__ . '/assets/js/modules/toast.js'],
    array_map(fn($x) => __DIR__ . '/assets/js/modules/' . $x . '.js', $__manifest['js_modules']),
    [__DIR__ . '/assets/js/app.js']
);
$__cssFiles = array_map(fn($x) => __DIR__ . '/assets/css/' . $x . '.css', $__manifest['css']);
function bundle_v(array $files): int {   // ?v = mtime lớn nhất trong nhóm
    $m = 0; foreach ($files as $f) $m = max($m, @filemtime($f) ?: 0); return $m;
}
// PRODUCTION: gom cả trang rồi xoá comment HTML cho gọn (trông chuyên
// nghiệp khi mở F12). DEV giữ nguyên comment để dễ đọc lúc sửa.
if (!$__dev) ob_start();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <!-- Cho phép phóng to (GLV lớn tuổi đọc chữ nhỏ) + hỗ trợ tai thỏ iPhone -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#c8203a">
    <!-- Áp chế độ tối TRƯỚC khi vẽ trang, tránh nháy trắng -->
    <script>try{var d=localStorage.getItem('darkMode');if(d==='true')document.documentElement.classList.add('dark')}catch(e){}</script>

    <!-- BIỂU TƯỢNG APP
         icon.svg   : tab trình duyệt, nét sắc ở mọi cỡ
         icon-180   : iOS "Thêm vào màn hình chính" (iOS không nhận SVG)
         manifest   : Android, để cài như một app riêng
         Đổi logo: thay assets/img/icon.svg rồi chạy  node build/tao_icon.cjs
         
         as="image" + fetchpriority="high" cho icon SVG vì nó render-blocking
         rel="preload" + as="image" cho icon quan trọng nhất
         ============================================================== -->
    <link rel="preload" href="assets/img/icon.svg" as="image" type="image/svg+xml">
    <link rel="icon" href="assets/img/icon.svg" type="image/svg+xml">
    <link rel="icon" href="assets/img/icon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="assets/img/icon-180.png">
    <link rel="manifest" href="manifest.json">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="GĐGLPT">
    <title>GIA ĐÌNH GIÁO LÝ PHÚ TRUNG</title>

    <!-- Tailwind biên dịch sẵn. Token thiết kế khai trong tailwind.config.js
         ở gốc dự án; chạy `npm run css` sau khi thêm lớp mới.
         (Các <link> CSS gom xuống một khối bên dưới — dev nạp lẻ, prod gộp.) -->
    <!-- THƯ VIỆN ĐẶT NGAY TRÊN MÁY CHỦ MÌNH, KHÔNG LẤY TỪ CDN NGOÀI.
         
         PRECONNECT: khai báo trước các hostname cần tra DNS, giảm ~100-300ms
         cho lần đầu tiên kết nối. Khai ở <head> để trình duyệt bắt đầu
         tra DNS ngay khi thấy thẻ, không phải đợi tải xong HTML.
         ============================================================== -->
    <?php if (!$__dev): ?>
    <!-- Preconnect cho CDN nếu có -->
    <link rel="preconnect" href="https://cdn.example.com" crossorigin>
    <?php endif; ?>
    <!-- Đo thực tế trên đường truyền tốt: lấy từ unpkg mất 356ms, từ
         jsdelivr 94ms, còn từ máy chủ mình 9ms. Trên điện thoại 4G sóng
         yếu thì khoảng cách đó giãn ra thành mấy giây, vì mỗi tên miền
         lạ phải tra DNS rồi bắt tay TLS lại từ đầu.

         lucide-icons.js là bản RÚT GỌN, 16 KB thay vì 410 KB: chỉ giữ
         76 icon app thật sự dùng thay vì cả 2.031 cái.
         Dựng lại sau khi thêm icon mới:  node build/tao_lucide.cjs

         Cả ba đều defer — thẻ lucide trước đây KHÔNG có defer, nên
         trình duyệt phải tải xong 410 KB rồi mới vẽ được gì lên màn hình. -->
    <script defer src="assets/js/vendor/alpine-collapse.js?v=<?php echo asset_v(__DIR__ . '/assets/js/vendor/alpine-collapse.js'); ?>"></script>
    <script defer src="assets/js/vendor/alpine.js?v=<?php echo asset_v(__DIR__ . '/assets/js/vendor/alpine.js'); ?>"></script>
    <script defer src="assets/js/vendor/lucide-icons.js?v=<?php echo asset_v(__DIR__ . '/assets/js/vendor/lucide-icons.js'); ?>"></script>

    <!-- Độ đậm chữ: 400 500 600 700 900.
         Trước đây khai 800 (giao diện KHÔNG dùng chỗ nào) mà thiếu 900
         (font-black, dùng 151 chỗ) — nên mọi tiêu đề đang bị trình duyệt
         bôi đậm giả, nét bệt và hơi lệch so với thiết kế. -->
    <!-- Phông đặt tại máy chủ mình, không còn gọi fonts.googleapis.com
         và fonts.gstatic.com. Hai tên miền đó mỗi cái bắt điện thoại tra
         DNS rồi bắt tay TLS lại từ đầu, mà thẻ <link> lại chặn hiển thị.
         Dựng lại bằng:  node build/tao_font.cjs -->
    <?php if ($__dev): ?>
    <?php foreach ($__manifest['css'] as $c): ?>
    <link rel="stylesheet" href="assets/css/<?= $c ?>.css?v=<?php echo asset_v(__DIR__ . '/assets/css/' . $c . '.css'); ?>">
    <?php endforeach; ?>
    <?php else: ?>
    <link rel="stylesheet" href="assets/css/bundle.php?v=<?php echo bundle_v($__cssFiles); ?>">
    <?php endif; ?>
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
        <div class="app-content flex flex-col overflow-hidden">

        <!-- HEADER -->
        <?php include __DIR__ . '/../views/layout_header.php'; ?>

        <!-- Vùng nội dung: padding ngang tập trung 1 chỗ duy nhất, overflow-y-auto để cuộn -->
        <main class="app-main px-4 sm:px-6 flex-1 overflow-y-auto">


            <!-- ==========================================================
                 LAZY-MOUNT: mỗi màn chỉ NẰM TRONG DOM khi đang mở, nhờ
                 <template x-if>. Trước đây cả ~21 màn đều render sẵn (DOM
                 ~9.300 node / 623 icon) rồi ẩn bằng display:none, khiến máy
                 yếu chậm mọi thao tác và mở app khựng. Bọc trong một <div>
                 để panel nào có kèm modal (vd điểm danh có popup quét QR)
                 vẫn hợp lệ "một gốc duy nhất" mà x-if yêu cầu.
                 ========================================================== -->

            <!-- TRANG CHỦ -->
            <template x-if="currentModule==='dashboard'"><div>
                <div data-module="dashboard" class="module-panel">
                    <?php include __DIR__ . '/../views/layout_hero.php'; ?>
                    <?php include __DIR__ . '/../views/module_menu.php'; ?>
                </div>
            </div></template>

            <!-- MODULE DANH SÁCH & CHƯƠNG TRÌNH -->
            <template x-if="currentModule==='students'"><div><?php include __DIR__ . '/../views/module_students.php'; ?></div></template>
            <template x-if="currentModule==='attendance'"><div><?php include __DIR__ . '/../views/module_attendance.php'; ?></div></template>
            <template x-if="currentModule==='leave'"><div><?php include __DIR__ . '/../views/module_leave.php'; ?></div></template>
            <template x-if="currentModule==='birthdays'"><div><?php include __DIR__ . '/../views/module_birthdays.php'; ?></div></template>
            <template x-if="currentModule==='announcements'"><div><?php include __DIR__ . '/../views/module_announcements.php'; ?></div></template>
            <template x-if="currentModule==='reporthub'"><div><?php include __DIR__ . '/../views/module_reporthub.php'; ?></div></template>
            <template x-if="currentModule==='org'"><div><?php include __DIR__ . '/../views/module_org.php'; ?></div></template>
            <template x-if="currentModule==='staff'"><div><?php include __DIR__ . '/../views/module_staff.php'; ?></div></template>
            <template x-if="currentModule==='years'"><div><?php include __DIR__ . '/../views/module_years.php'; ?></div></template>
            <template x-if="currentModule==='reports'"><div><?php include __DIR__ . '/../views/module_reports.php'; ?></div></template>
            <template x-if="currentModule==='qrcard'"><div><?php include __DIR__ . '/../views/module_qrcard.php'; ?></div></template>
            <template x-if="currentModule==='settings'"><div><?php include __DIR__ . '/../views/module_settings.php'; ?></div></template>
            <template x-if="currentModule==='scores'"><div><?php include __DIR__ . '/../views/module_scores.php'; ?></div></template>
            <template x-if="currentModule==='student_profile'"><div><?php include __DIR__ . '/../views/module_student_profile.php'; ?></div></template>
            <template x-if="currentModule==='promotion'"><div><?php include __DIR__ . '/../views/module_promotion.php'; ?></div></template>
            <template x-if="currentModule==='programs'"><div><?php include __DIR__ . '/../views/module_programs.php'; ?></div></template>
            <template x-if="currentModule==='calendar'"><div><?php include __DIR__ . '/../views/module_calendar.php'; ?></div></template>
            <template x-if="currentModule==='notes'"><div><?php include __DIR__ . '/../views/module_notes.php'; ?></div></template>
            <template x-if="currentModule==='guide'"><div><?php include __DIR__ . '/../views/module_guide.php'; ?></div></template>
            <template x-if="currentModule==='thu_vien'"><div><?php include __DIR__ . '/../views/module_library.php'; ?></div></template>
            <template x-if="currentModule==='gifts'"><div><?php include __DIR__ . '/../views/module_gifts.php'; ?></div></template>
            <template x-if="currentModule==='rewards'"><div><?php include __DIR__ . '/../views/module_rewards.php'; ?></div></template>

        </main>

        </div><!-- /.app-content -->

        <!-- THANH ĐIỀU HƯỚNG DƯỚI (ẩn trên máy tính, xem app.css) -->
        <?php include __DIR__ . '/../views/layout_bottomnav.php'; ?>

    </div>

    <!-- ================= MODAL POPUP: XEM TÀI LIỆU THƯ VIỆN =================
         Bọc backdrop + thẻ popup trong MỘT scope Alpine (x-data). Trước đây
         backdrop nằm ngoài mọi x-data nên Alpine không khởi tạo: nó luôn bị
         display:none (không mờ nền) và @click đóng modal không chạy — nên bấm
         ra ngoài không tắt được. Dùng x-data rỗng là đủ vì mọi thứ đọc qua
         $store.libViewer và các hàm toàn cục (closeLibViewer, libSizeLabel). -->
    <div x-data @keydown.escape.window="closeLibViewer()">
    <!-- Backdrop -->
    <div x-show="$store.libViewer.open"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         style="display:none" class="fixed inset-0 z-[300] bg-slate-900/70 cursor-pointer" @click="closeLibViewer()"></div>

    <!-- Modal popup: giữa màn hình. Bấm ra vùng ngoài thẻ trắng cũng đóng. -->
    <div x-show="$store.libViewer.open"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         style="display:none" class="fixed inset-0 z-[301] flex items-center justify-center p-4" @click="closeLibViewer()">

        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90dvh] flex flex-col overflow-hidden" @click.stop>

            <!-- Header -->
            <div class="flex items-center gap-3 px-4 py-3 border-b border-slate-100 shrink-0">
                <button aria-label="Đóng" @click="closeLibViewer()" class="tap-safe w-8 h-8 bg-slate-100 rounded-full flex items-center justify-center active:scale-90 shrink-0 cursor-pointer">
                    <i data-lucide="x" class="w-4 h-4 text-slate-500"></i>
                </button>
                <p class="flex-1 min-w-0 truncate font-bold text-sm text-slate-800" x-text="($store.libViewer.item || {}).title || ''"></p>
                <a x-show="($store.libViewer.item || {}).type==='file'" style="display:none" :href="($store.libViewer.item || {}).fileUrl + '&mode=download'"
                   class="shrink-0 w-8 h-8 bg-slate-100 rounded-full flex items-center justify-center active:scale-90" aria-label="Tải về">
                    <i data-lucide="file-down" class="w-4 h-4 text-slate-500"></i>
                </a>
                <button x-show="($store.libViewer.item || {}).type==='article' && window.TNTT.library.libCanEdit" style="display:none"
                        @click="window.TNTT.library.editArticle($store.libViewer.item)" class="shrink-0 w-8 h-8 bg-slate-100 rounded-full flex items-center justify-center active:scale-90" aria-label="Sửa">
                    <i data-lucide="pencil" class="w-4 h-4 text-slate-500"></i>
                </button>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto">
                <!-- BÀI VIẾT sổ tay -->
                <div x-show="($store.libViewer.item || {}).type==='article'" class="p-5">
                    <p class="text-micro font-bold uppercase tracking-wide text-sky-600 mb-1" x-text="($store.libViewer.item || {}).categoryName || ''"></p>
                    <h1 class="text-lg font-black text-slate-800 mb-4" x-text="($store.libViewer.item || {}).title || ''"></h1>
                    <div class="text-slate-700 text-sm leading-relaxed" style="white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word" x-text="($store.libViewer.item || {}).body || ''"></div>
                </div>
                <!-- PDF -->
                <iframe x-show="($store.libViewer.item || {}).type==='file' && (($store.libViewer.item || {}).ext || '')==='pdf'"
                        :src="($store.libViewer.item || {}).fileUrl + '&mode=view'" class="w-full h-64 sm:h-80 border-0"></iframe>
                <!-- Ảnh: hiện thẳng, không cần tải về. -->
                <img x-show="($store.libViewer.item || {}).type==='file' && ($store.libViewer.item || {}).viewable && (($store.libViewer.item || {}).ext || '')!=='pdf'"
                     :src="($store.libViewer.item || {}).fileUrl + '&mode=view'"
                     :alt="($store.libViewer.item || {}).title || ''"
                     class="w-full max-h-[60dvh] object-contain bg-slate-50">
                <!-- Tệp không xem được -->
                <div x-show="($store.libViewer.item || {}).type==='file' && !($store.libViewer.item || {}).viewable" class="flex flex-col items-center justify-center text-center p-8">
                    <i data-lucide="file-down" class="w-12 h-12 text-slate-300 mb-3"></i>
                    <p class="text-slate-700 font-bold mb-1">Tài liệu này cần tải về để xem</p>
                    <p class="text-slate-400 text-xs mb-4" x-text="(($store.libViewer.item || {}).ext || '').toUpperCase() + ' · ' + libSizeLabel(($store.libViewer.item || {}).sizeKb || 0)"></p>
                    <a :href="($store.libViewer.item || {}).fileUrl + '&mode=download'"
                       class="bg-blue-600 text-white font-bold px-5 py-2.5 rounded-2xl active:scale-95 transition-transform inline-flex items-center gap-2 text-sm">
                        <i data-lucide="file-down" class="w-4 h-4"></i> Tải về
                    </a>
                </div>
            </div>
        </div>
    </div>
    </div><!-- /x-data wrapper của modal thư viện -->

    <!-- JS: đăng ký Alpine store + function toàn cục cho click handlers -->
    <script>
    document.addEventListener('alpine:init', function() {
        Alpine.store('libViewer', { open: false, item: null });
    });
    // Nút "Sửa" nằm trong bottom sheet — mà sheet là một scope RIÊNG
    // (x-data="tnttApp" thứ hai, đặt ngoài .app-shell). Vì vậy KHÔNG gọi
    // thẳng window.TNTT.library.editArticle được: khi gọi như vậy `this`
    // là mảnh JS thô, không có $nextTick (báo "this.$nextTick is not a
    // function"), và libCompose ghi nhầm vào mảnh chứ không vào component
    // đang sống — nên modal soạn không bao giờ mở. Phải lấy đúng component
    // gốc rồi gọi phương thức trên nó.
    function openLibArticle(item) {
        const goc = document.querySelector('.app-shell');
        const app = (goc && window.Alpine && window.Alpine.$data) ? window.Alpine.$data(goc) : null;
        if (app && app.editArticle) app.editArticle(item);
    }
    // Helper function cho modal - dùng trong Alpine x-text
    function libSizeLabel(kb) {
        return kb >= 1024 ? (kb / 1024).toFixed(1) + ' MB' : kb + ' KB';
    }
    // Đóng modal viewer
    function closeLibViewer() {
        Alpine.store('libViewer').open = false;
    }
    </script>

    <!-- Link file JS (Có Phá Cache để điện thoại luôn load mới) -->
    <!-- Dữ liệu phiên và cấu hình, nhúng sẵn để app.js không phải chờ thêm một vòng mạng -->
    <script>window.TNTT_BOOT = <?php echo json_encode($bootData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;</script>
    <!-- Danh sách mảnh để app.js gộp — cùng nguồn với nạp/gộp (asset_manifest.php) -->
    <script>window.TNTT_MODULES = <?php echo json_encode($__manifest['js_modules']); ?>;</script>
    <!-- Các mảnh của component tnttApp. Phải nạp TRƯỚC app.js vì
         app.js chỉ làm nhiệm vụ gộp chúng lại. -->
    <?php if (!$__dev): ?>
    <script>
        // PRODUCTION: Tắt các log không cần thiết để giữ Console sạch sẽ
        console.log = function() {};
        console.warn = function() {};
        console.info = function() {};
    </script>
    <?php endif; ?>

    <?php if ($__dev): ?>
    <!-- DEV (localhost): nạp lẻ từng mảnh để sửa file nào thấy ngay file đó -->
    <script src="assets/js/modules/toast.js?v=<?php echo asset_v(__DIR__ . '/assets/js/modules/toast.js'); ?>"></script>
    <?php foreach ($__manifest['js_modules'] as $m): ?>
    <script src="assets/js/modules/<?= $m ?>.js?v=<?php echo asset_v(__DIR__ . '/assets/js/modules/' . $m . '.js'); ?>"></script>
    <?php endforeach; ?>
    <script src="assets/js/app.js?v=<?php echo asset_v(__DIR__ . '/assets/js/app.js'); ?>"></script>
    <?php else: ?>
    <!-- PRODUCTION: một tệp gộp (toast + module + app.js) cho nhẹ -->
    <script src="assets/js/bundle.php?v=<?php echo bundle_v($__jsFiles); ?>"></script>
    <?php endif; ?>

    <script>document.addEventListener('DOMContentLoaded', () => { lucide.createIcons(); });</script>
</body>
</html>
<?php
// Xoá comment HTML ở bản production. Bảo vệ nội dung <script>/<style>
// (JS/CSS có thể chứa chuỗi giống comment) rồi mới xoá <!-- --> phần còn lại.
if (!$__dev) {
    $__html = ob_get_clean();
    $__keep = [];
    $__html = preg_replace_callback('#<(script|style)\b[^>]*>.*?</\1>#is', function ($m) use (&$__keep) {
        $__keep[] = $m[0];
        return "\x01K" . (count($__keep) - 1) . "\x01";
    }, $__html);
    $__html = preg_replace('/<!--(?!\[if).*?-->/s', '', $__html);
    $__html = preg_replace_callback('/\x01K(\d+)\x01/', fn($m) => $__keep[$m[1]], $__html);
    echo $__html;
}
?>
