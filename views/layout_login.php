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
    <link rel="icon" href="/assets/img/icon.svg" type="image/svg+xml">
    <link rel="icon" href="/assets/img/icon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="/assets/img/icon-180.png">
    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
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
    <script defer src="assets/js/modules/passkey.js?v=<?php echo @filemtime(__DIR__ . '/../public/assets/js/modules/passkey.js') ?: 0; ?>"></script>
</head>
<body class="text-slate-800 antialiased overflow-x-hidden">

<div x-data="loginScreen" x-cloak class="app-shell max-w-md sm:max-w-lg flex flex-col justify-center px-6 py-10">

    <!-- Nhãn hiệu -->
    <div class="text-center mb-8">
        <img src="assets/img/icon-192.png" alt="Logo Gia Đình Giáo Lý Phú Trung"
             class="mx-auto mb-5 rounded-panel" style="width:96px;height:96px;object-fit:contain">
        <h1 class="text-2xl font-black text-slate-800 tracking-tight">Gia Đình Giáo Lý Phú Trung</h1>
        <p class="text-sm text-slate-400 mt-1">Đoàn Thiếu Nhi Thánh Thể</p>
    </div>

    <!-- ==========================================================
         FORM ĐĂNG NHẬP
         ========================================================== -->
    <form x-show="step === 'login'" @submit.prevent="submitLogin()"
          class="bg-white rounded-panel p-6 shadow-lg border border-slate-100 space-y-4">

        <div>
            <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Số điện thoại</label>
            <div class="relative">
                <i data-lucide="phone" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                <input x-model="phone" type="tel" inputmode="numeric" autocomplete="username"
                       placeholder="09xxxxxxxx" required
                       class="w-full bg-slate-50 border border-slate-200 rounded-field py-3.5 pl-11 pr-4 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 transition-all">
            </div>
        </div>

        <div>
            <label class="block text-micro font-bold text-slate-500 uppercase mb-1.5">Mật khẩu</label>
            <div class="relative">
                <i data-lucide="key-round" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                <input x-model="password" :type="showPw ? 'text' : 'password'" autocomplete="current-password"
                       placeholder="••••••••" required
                       class="w-full bg-slate-50 border border-slate-200 rounded-field py-3.5 pl-11 pr-12 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 transition-all">
                <button aria-label="Hiện hoặc ẩn mật khẩu" @click="showPw = !showPw" type="button"
                        class="tap-safe absolute right-3 top-1/2 -translate-y-1/2 w-8 h-8 flex items-center justify-center text-slate-400 active:scale-90 transition-transform">
                    <i x-show="!showPw" data-lucide="eye" class="w-4 h-4"></i>
                    <i x-show="showPw" data-lucide="eye-off" class="w-4 h-4"></i>
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
            <i x-show="!busy" data-lucide="log-in" class="w-5 h-5 mr-2"></i>
            <span x-text="busy ? 'Đang kiểm tra...' : 'Đăng nhập'"></span>
        </button>

        <!-- Đăng nhập sinh trắc học -->
        <button type="button" @click="loginPasskey()" :disabled="busy"
                class="w-full bg-slate-800 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-slate-200 flex justify-center items-center disabled:opacity-50 mt-3">
            <i data-lucide="scan-face" class="w-5 h-5 mr-2"></i>
            <span>Đăng nhập bằng FaceID/Vân tay</span>
        </button>

        <p class="text-center text-micro text-slate-500 leading-snug pt-3">
            Quên mật khẩu? Liên hệ Ban Điều Hành để được cấp lại.
        </p>

        <div class="border-t border-slate-100 pt-4 mt-3">
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
            <i data-lucide="info" class="w-3.5 h-3.5 shrink-0 mt-0.5 text-slate-400"></i>
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
            <i x-show="!busy" data-lucide="send" class="w-5 h-5 mr-2"></i>
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

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('loginScreen', () => ({
        step: 'login',      // login | register | done | changepw
        phone: '', password: '', showPw: false,
        oldPw: '', newPw: '', newPw2: '',
        rHoly: '', rName: '', rPhone: '', rBirth: '', rPw: '', rPw2: '', rNote: '', rCode: '',
        rDanhXung: 'glv',
        error: '', busy: false,

        // Chuẩn hoá SĐT: bỏ khoảng trắng/dấu, đổi +84/84/0084 -> 0
        chuanHoaSdt(s) {
            s = String(s || '').replace(/[\s.\-()]/g, '').trim();
            if (s.startsWith('+84'))  s = '0' + s.slice(3);
            else if (s.startsWith('0084')) s = '0' + s.slice(4);
            else if (s.startsWith('84') && s.length >= 11) s = '0' + s.slice(2);
            return s;
        },

        goRegister() { this.error = ''; this.step = 'register'; this.$nextTick(() => lucide.createIcons()); },
        goLogin()    { this.error = ''; this.step = 'login';    this.$nextTick(() => lucide.createIcons()); },

        async submitRegister() {
            this.error = '';
            if (this.rPw !== this.rPw2) { this.error = 'Hai lần nhập mật khẩu không khớp.'; return; }
            this.busy = true;
            try {
                const r = await this.post('register', {
                    holyName: this.rHoly.trim(), fullName: this.rName.trim(),
                    phone: this.chuanHoaSdt(this.rPhone), birthDate: this.rBirth,
                    password: this.rPw, note: this.rNote.trim(),
                    danhXung: this.rDanhXung
                });
                if (!r.ok) { this.error = r.error; return; }
                this.rCode = r.code;
                this.step = 'done';
                this.$nextTick(() => lucide.createIcons());
            } catch (e) {
                this.error = 'Không kết nối được máy chủ. Kiểm tra lại mạng rồi thử lại.';
            } finally { this.busy = false; }
        },

        async post(action, body) {
            const res = await fetch('api/auth.php?action=' + action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            return res.json();
        },

        async submitLogin() {
            this.error = ''; this.busy = true;
            try {
                const r = await this.post('login', { phone: this.chuanHoaSdt(this.phone), password: this.password });
                if (!r.ok) { this.error = r.error; return; }
                // Lần đầu đăng nhập thì bắt đổi mật khẩu ngay, không cho vào thẳng
                if (r.user.mustChangePw) {
                    this.oldPw = this.password;
                    this.step = 'changepw';
                    this.error = '';
                    this.$nextTick(() => lucide.createIcons());
                    return;
                }
                location.reload();
            } catch (e) {
                this.error = 'Không kết nối được máy chủ. Kiểm tra lại mạng rồi thử lại.';
            } finally { this.busy = false; }
        },

        async loginPasskey() {
            this.error = '';
            this.busy = true;
            try {
                const user = await window.Passkey.login();
                if (user) location.reload();
            } catch (e) {
                // lỗi đã được alert trong passkey.js
            } finally {
                this.busy = false;
            }
        },

        async submitChange() {
            this.error = '';
            if (this.newPw !== this.newPw2) { this.error = 'Hai lần nhập mật khẩu mới không khớp.'; return; }
            this.busy = true;
            try {
                const r = await this.post('password', { current: this.oldPw, new: this.newPw });
                if (!r.ok) { this.error = r.error; return; }
                location.reload();
            } catch (e) {
                this.error = 'Không kết nối được máy chủ.';
            } finally { this.busy = false; }
        },

        init() { this.$nextTick(() => lucide.createIcons()); }
    }));
});
</script>
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
