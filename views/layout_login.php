<?php
// Trang đăng nhập là trang RIÊNG (không đi qua bundling của index.php) nên
// tự tính dev/production: bản thật dùng CSS đã nén + xoá comment cho gọn.
$__dev = in_array(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0], ['localhost', '127.0.0.1'], true);
$__cssV = @filemtime(__DIR__ . '/../public/assets/css/bundle.min.css')
       ?: @filemtime(__DIR__ . '/../public/assets/css/app.css') ?: 0;
if (!$__dev) ob_start();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#c8203a">

    <!-- BIỂU TƯỢNG APP
         icon.svg   : tab trình duyệt, nét sắc ở mọi cỡ
         icon-180   : iOS "Thêm vào màn hình chính" (iOS không nhận SVG)
         manifest   : Android, để cài như một app riêng
         Đổi logo: thay assets/img/icon.svg rồi chạy  node build/tao_icon.cjs -->
    <link rel="preload" href="assets/img/icon.svg" as="image" type="image/svg+xml">
    <link rel="icon" href="assets/img/icon.svg" type="image/svg+xml">
    <link rel="icon" href="assets/img/icon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="assets/img/icon-180.png">
    <link rel="manifest" href="manifest.json">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>Đăng nhập · GIA ĐÌNH GIÁO LÝ PHÚ TRUNG</title>

    <?php if ($__dev): ?>
    <link rel="stylesheet" href="assets/css/tailwind.css?v=<?php echo @filemtime(__DIR__ . '/../public/assets/css/tailwind.css') ?: 0; ?>">
    <link rel="stylesheet" href="assets/css/font.css?v=<?php echo @filemtime(__DIR__ . '/../public/assets/css/font.css') ?: 0; ?>">
    <link rel="stylesheet" href="assets/css/app.css?v=<?php echo @filemtime(__DIR__ . '/../public/assets/css/app.css') ?: 0; ?>">
    <link rel="stylesheet" href="assets/css/brand.css?v=<?php echo @filemtime(__DIR__ . '/../public/assets/css/brand.css') ?: 0; ?>">
    <?php else: ?>
    <link rel="stylesheet" href="assets/css/bundle.php?v=<?php echo $__cssV; ?>">
    <?php endif; ?>
    <script defer src="assets/js/vendor/alpine.js?v=<?php echo @filemtime(__DIR__ . '/../public/assets/js/vendor/alpine.js') ?: 0; ?>"></script>
    <script defer src="assets/js/vendor/lucide-icons.js?v=<?php echo @filemtime(__DIR__ . '/../public/assets/js/vendor/lucide-icons.js') ?: 0; ?>"></script>
</head>
<body class="text-slate-800 antialiased overflow-x-hidden">

<div x-data="loginScreen" x-cloak class="app-shell max-w-md sm:max-w-lg flex flex-col justify-center px-6 py-10">

    <!-- Nhãn hiệu -->
    <div class="text-center mb-8">
        <img src="assets/img/icon-192.png" alt="Logo Gia Đình Giáo Lý Phú Trung"
             class="mx-auto mb-5 rounded-panel" style="width:96px;height:96px;object-fit:contain">
        <h1 class="text-2xl font-black text-slate-800 tracking-tight">Gia Đình Giáo Lý Phú Trung</h1>
        <p class="text-sm text-slate-500 mt-1">Đoàn Thiếu Nhi Thánh Thể</p>
    </div>

    <!-- ==========================================================
         FORM ĐĂNG NHẬP
         ========================================================== -->
    <form x-show="step === 'login'" @submit.prevent="submitLogin()"
          class="bg-white rounded-panel p-6 shadow-lg border border-slate-100 space-y-4">

        <div>
            <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Số điện thoại</label>
            <div class="relative">
                <i data-lucide="phone" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"></i>
                <input x-model="phone" type="tel" inputmode="numeric" autocomplete="username"
                       placeholder="09xxxxxxxx" required
                       class="w-full bg-slate-50 border border-slate-200 rounded-field py-3.5 pl-11 pr-4 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 transition-all">
            </div>
        </div>

        <div>
            <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Mật khẩu</label>
            <div class="relative">
                <i data-lucide="key-round" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"></i>
                <input x-model="password" :type="showPw ? 'text' : 'password'" autocomplete="current-password"
                       placeholder="••••••••" required
                       class="w-full bg-slate-50 border border-slate-200 rounded-field py-3.5 pl-11 pr-12 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 transition-all">
                <button aria-label="Hiện hoặc ẩn mật khẩu" @click="showPw = !showPw" type="button"
                        class="absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 flex items-center justify-center text-slate-500 active:scale-90 transition-transform">
                    <svg x-show="!showPw" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg x-show="showPw" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                </button>
            </div>
        </div>

        <!-- Báo lỗi -->
        <div x-show="error" style="display: none;"
             class="bg-rose-50 border border-rose-100 rounded-2xl p-3 flex items-start gap-2.5">
            <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-500 shrink-0 mt-0.5"></i>
            <p class="text-xs text-rose-700 leading-snug font-medium" x-text="error"></p>
        </div>

        <button type="submit" :disabled="busy"
                class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center disabled:opacity-50">
            <span x-show="!busy" class="inline-flex items-center justify-center"><i data-lucide="log-in" class="w-5 h-5 mr-2"></i></span>
            <span x-text="busy ? 'Đang kiểm tra...' : 'Đăng nhập'"></span>
        </button>

        <!-- Đăng nhập sinh trắc — chỉ icon khuôn mặt kiểu native, bấm là quét
             luôn, không popup (lỗi/huỷ xử lý im lặng hoặc hiện ô đỏ inline). -->
        <div class="login-or"><span>hoặc</span></div>

        <div class="flex justify-center">
            <button type="button" @click="loginPasskey()" :disabled="busy" class="btn-face"
                    aria-label="Đăng nhập bằng Face ID / Vân tay">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" x2="9.01" y1="9" y2="9"/><line x1="15" x2="15.01" y1="9" y2="9"/></svg>
            </button>
        </div>

        <p class="text-center text-micro text-slate-500 leading-snug pt-2">
            Quên mật khẩu? Liên hệ Ban Điều Hành để được cấp lại.
        </p>

        <div class="border-t border-slate-100 pt-4 mt-3 space-y-2">
            <a href="index.php"
               class="w-full py-3 bg-white border border-slate-200 rounded-2xl font-bold text-sm text-slate-600 active:scale-[0.98] transition-transform flex items-center justify-center gap-2">
                ‹ Về trang chủ
            </a>
            <button @click="goRegister()" type="button"
                    class="w-full py-3 bg-slate-50 border border-slate-200 rounded-2xl font-bold text-sm text-slate-600 active:scale-[0.98] transition-transform flex items-center justify-center gap-2">
                <i data-lucide="user-plus" class="w-4 h-4"></i> Đăng ký làm Giáo Lý Viên
            </button>
        </div>
    </form>

    <!-- ==========================================================
         ĐĂNG KÝ
         Tài khoản tạo ra ở trạng thái chờ duyệt, KHÔNG vào app được
         cho tới khi Ban Điều Hành duyệt và phân công lớp.
         ========================================================== -->
    <form x-show="step === 'register'" style="display: none;" @submit.prevent="submitRegister()"
          class="bg-white rounded-panel p-6 shadow-lg border border-slate-100 space-y-4">

        <div class="flex items-center gap-3 pb-1">
            <button aria-label="Quay lại đăng nhập" @click="goLogin()" type="button"
                    class="tap-safe w-9 h-9 shrink-0 bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-center text-slate-500 active:scale-90 transition-transform">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
            </button>
            <h2 class="text-lg font-black text-slate-800">Đăng ký</h2>
        </div>

        <div class="bg-blue-50 border border-blue-100 rounded-2xl p-3 flex items-start gap-2.5">
            <i data-lucide="info" class="w-4 h-4 text-blue-500 shrink-0 mt-0.5"></i>
            <p class="text-micro text-blue-700 leading-snug">
                Đăng ký xong bạn <span class="font-bold">chưa vào được ngay</span>.
                Ban Điều Hành sẽ duyệt và phân công lớp, sau đó bạn đăng nhập bình thường.
            </p>
        </div>

        <!-- Danh xưng: chỉ Giáo Lý Viên / Dự Bị. Nhiệm vụ cụ thể do BĐH gán sau -->
        <div>
            <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Bạn đăng ký làm</label>
            <div class="grid grid-cols-2 gap-3">
                <button type="button" @click="rDanhXung = 'glv'"
                        class="py-3 rounded-xl font-bold text-sm border transition-colors"
                        :class="rDanhXung === 'glv' ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-200' : 'bg-slate-50 text-slate-500 border-slate-200'">
                    Giáo Lý Viên
                </button>
                <button type="button" @click="rDanhXung = 'du_bi'"
                        class="py-3 rounded-xl font-bold text-sm border transition-colors"
                        :class="rDanhXung === 'du_bi' ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-200' : 'bg-slate-50 text-slate-500 border-slate-200'">
                    Dự Bị
                </button>
            </div>
            <p class="text-micro text-slate-500 mt-1 ml-1">Chức vụ cụ thể (Trưởng khối, Chủ nhiệm…) do Ban Điều Hành phân công sau.</p>
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div class="col-span-1">
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Tên Thánh</label>
                <input x-model="rHoly" type="text" placeholder="Maria"
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30">
            </div>
            <div class="col-span-2">
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Họ và Tên</label>
                <input x-model="rName" type="text" required placeholder="Nguyễn Thị A"
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Số điện thoại</label>
                <input x-model="rPhone" type="tel" inputmode="numeric" required placeholder="09xxxxxxxx"
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30">
                <p class="text-micro text-slate-500 mt-1 ml-1">Cũng là tên đăng nhập</p>
            </div>
            <div>
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Ngày sinh</label>
                <input x-model="rBirth" type="date" required min="1900-01-01" max="<?php echo date('Y-m-d'); ?>"
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30">
                <p class="text-micro text-slate-500 mt-1 ml-1">Để đoàn mừng sinh nhật</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Mật khẩu</label>
                <input x-model="rPw" type="password" required minlength="6"
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30">
            </div>
            <div>
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Nhập lại</label>
                <input x-model="rPw2" type="password" required
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30">
            </div>
        </div>
        <p class="text-micro text-slate-500 ml-1 flex items-start gap-1.5" style="margin-top:-0.25rem">
            <i data-lucide="info" class="w-3.5 h-3.5 shrink-0 mt-0.5 text-slate-500"></i>
            Đây là mật khẩu bạn sẽ dùng để đăng nhập sau khi được duyệt — hãy nhớ kỹ, không bị bắt đổi lại.
        </p>

        <div>
            <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Lời nhắn cho Ban Điều Hành</label>
            <textarea x-model="rNote" rows="2" placeholder="VD: Em muốn phụ trách lớp Khai Tâm 1B..."
                      class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 resize-none"></textarea>
        </div>

        <div x-show="error" style="display: none;"
             class="bg-rose-50 border border-rose-100 rounded-2xl p-3 flex items-start gap-2.5">
            <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-500 shrink-0 mt-0.5"></i>
            <p class="text-xs text-rose-700 leading-snug font-medium" x-text="error"></p>
        </div>

        <button type="submit" :disabled="busy"
                class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center disabled:opacity-50">
            <span x-show="!busy" class="inline-flex items-center justify-center"><i data-lucide="send" class="w-5 h-5 mr-2"></i></span>
            <span x-text="busy ? 'Đang gửi...' : 'Gửi đăng ký'"></span>
        </button>
    </form>

    <!-- ĐĂNG KÝ XONG -->
    <div x-show="step === 'done'" style="display: none;"
         class="bg-white rounded-panel p-6 shadow-lg border border-slate-100 text-center">
        <div class="w-16 h-16 mx-auto bg-emerald-50 border border-emerald-100 rounded-card flex items-center justify-center mb-4">
            <i data-lucide="check" class="tap-safe w-8 h-8 text-emerald-600"></i>
        </div>
        <h2 class="text-lg font-black text-slate-800 mb-2">Đã gửi đăng ký</h2>
        <p class="text-sm text-slate-500 leading-relaxed mb-1">
            Mã Giáo Lý Viên của bạn là <span class="font-black text-slate-800" x-text="rCode"></span>.
        </p>
        <p class="text-xs text-slate-500 leading-relaxed mb-5">
            Ban Điều Hành sẽ duyệt và phân công lớp cho bạn.
            Khi được duyệt, bạn đăng nhập bằng số điện thoại và mật khẩu vừa đặt.
        </p>
        <button @click="goLogin()" type="button"
                class="w-full py-3 bg-slate-50 border border-slate-200 rounded-2xl font-bold text-sm text-slate-600 active:scale-[0.98] transition-transform">
            Về màn đăng nhập
        </button>
    </div>

    <!-- ==========================================================
         BUỘC ĐỔI MẬT KHẨU LẦN ĐẦU
         ========================================================== -->
    <form x-show="step === 'changepw'" style="display: none;" @submit.prevent="submitChange()"
          class="bg-white rounded-panel p-6 shadow-lg border border-slate-100 space-y-4">

        <div class="bg-amber-50 border border-amber-100 rounded-2xl p-3 flex items-start gap-2.5">
            <i data-lucide="key-round" class="w-4 h-4 text-amber-500 shrink-0 mt-0.5"></i>
            <p class="text-xs text-amber-700 leading-snug">
                Đây là lần đăng nhập đầu tiên. Vui lòng <span class="font-bold">đổi mật khẩu</span> trước khi dùng.
            </p>
        </div>

        <div>
            <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Mật khẩu hiện tại</label>
            <input x-model="oldPw" type="password" required
                   class="w-full bg-slate-50 border border-slate-200 rounded-field py-3.5 px-4 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30">
        </div>
        <div>
            <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Mật khẩu mới</label>
            <input x-model="newPw" type="password" required minlength="6"
                   class="w-full bg-slate-50 border border-slate-200 rounded-field py-3.5 px-4 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30">
            <p class="text-micro text-slate-500 mt-1 ml-1">Ít nhất 6 ký tự</p>
        </div>
        <div>
            <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Nhập lại mật khẩu mới</label>
            <input x-model="newPw2" type="password" required
                   class="w-full bg-slate-50 border border-slate-200 rounded-field py-3.5 px-4 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30">
        </div>

        <div x-show="error" style="display: none;"
             class="bg-rose-50 border border-rose-100 rounded-2xl p-3 flex items-start gap-2.5">
            <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-500 shrink-0 mt-0.5"></i>
            <p class="text-xs text-rose-700 leading-snug font-medium" x-text="error"></p>
        </div>

        <button type="submit" :disabled="busy"
                class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center disabled:opacity-50">
            <i data-lucide="save" class="w-5 h-5 mr-2"></i>
            <span x-text="busy ? 'Đang lưu...' : 'Đổi mật khẩu và vào app'"></span>
        </button>
    </form>

    <p class="text-center text-micro text-slate-300 mt-8">
        Phiên bản 1.0 · Dữ liệu lưu trên máy chủ giáo xứ
    </p>
</div>

<?php /* Script THƯỜNG (không defer) ở cuối body: chạy khi parser tới đây,
         TRƯỚC alpine (defer) -> kịp đăng ký component loginScreen + window.Passkey. */ ?>
<?php if ($__dev): ?>
<script src="assets/js/modules/passkey.js?v=<?php echo @filemtime(__DIR__ . '/../public/assets/js/modules/passkey.js') ?: 0; ?>"></script>
<script src="assets/js/login.js?v=<?php echo @filemtime(__DIR__ . '/../public/assets/js/login.js') ?: 0; ?>"></script>
<?php else: ?>
<script src="assets/js/login.min.js?v=<?php echo @filemtime(__DIR__ . '/../public/assets/js/login.min.js') ?: 0; ?>"></script>
<?php endif; ?>
</body>
</html>
<?php
// Xoá comment HTML ở bản production (bảo vệ <script>/<style>).
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
