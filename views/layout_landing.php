<?php
// TRANG CHỦ CÔNG KHAI (landing) — hiện cho khách chưa đăng nhập ở index.php.
// Chỉ là bảng điều hướng: KHÔNG nạp dữ liệu nghiệp vụ nào, mọi lối vào đều là
// trang có sẵn (đăng nhập quản lý / tra cứu Sổ Mộc / bảng thi đua).
// Tự chứa CSS nội tuyến để khỏi phụ thuộc bản dịch Tailwind.
$__cssV = @filemtime(__DIR__ . '/../public/assets/img/icon-192.png') ?: 0;
$__links = [
    ['href' => '#loichua', 'icon' => '✝️', 'title' => 'Lời Chúa Mỗi Ngày',
     'desc' => 'Nhận lời Chúa ngẫu nhiên dành riêng cho bạn.', 'cls' => 'scripture', 'id' => 'loichua'],
    ['href' => 'index.php?dangnhap=1', 'icon' => '🔐', 'title' => 'Đăng nhập quản lý',
     'desc' => 'Dành cho Giáo Lý Viên, Trưởng Khối, Ban Điều Hành và Thủ Thư: điểm danh, điểm số, thiếu nhi, đổi quà…', 'cls' => 'primary'],
    ['href' => 'somoc.php', 'icon' => '📒', 'title' => 'Sổ Mộc',
     'desc' => 'Em và phụ huynh nhập mã thiếu nhi để xem Mộc, chuỗi đi lễ và đặt trước quà.', 'cls' => ''],
    ['href' => 'tracuu.php', 'icon' => '📘', 'title' => 'Tra cứu điểm',
     'desc' => 'Xem điểm số, sổ điểm danh và sổ liên lạc của em — nhập mã thiếu nhi và ngày sinh (tháng-ngày-năm).', 'cls' => ''],
    ['href' => 'bxh.php', 'icon' => '🏆', 'title' => 'Bảng thi đua',
     'desc' => 'Xếp hạng chuyên cần &amp; học tập của các lớp, các em.', 'cls' => ''],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#c8203a">
    <link rel="icon" href="assets/img/icon.svg" type="image/svg+xml">
    <link rel="icon" href="assets/img/icon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="assets/img/icon-180.png">
    <link rel="manifest" href="manifest.json">
    <title>GIA ĐÌNH GIÁO LÝ PHÚ TRUNG</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;background:#f8fafc;color:#1e293b;
             font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;-webkit-font-smoothing:antialiased}
        .wrap{max-width:560px;margin:0 auto;padding:40px 20px calc(32px + env(safe-area-inset-bottom))}
        /* Tablet (640px+) */
        @media (min-width: 640px) {
            .wrap { max-width: 720px; padding: 60px 40px; }
        }
        /* Desktop (1024px+) */
        @media (min-width: 1024px) {
            .wrap { max-width: 800px; padding: 80px 40px; }
        }
        .brand{text-align:center;margin-bottom:28px}
        .brand img{width:96px;height:96px;object-fit:contain;border-radius:24px}
        .brand h1{font-size:1.5rem;font-weight:900;letter-spacing:-.01em;margin:16px 0 4px}
        .brand p{margin:0;font-size:.875rem;color:#94a3b8}
        .grid{display:grid;gap:14px}
        @media (min-width: 640px) {
            .grid { gap: 18px; }
        }
        /* Liquid Glass Cards */
        a.card{
            display:flex;
            align-items:center;
            gap:14px;
            padding:18px;
            border-radius:16px;
            background:rgba(255,255,255,0.72);
            backdrop-filter:blur(16px) saturate(180%);
            -webkit-backdrop-filter:blur(16px) saturate(180%);
            border:1px solid rgba(255,255,255,0.5);
            box-shadow:0 8px 32px rgba(0,0,0,0.1);
            text-decoration:none;color:inherit;
            transition:transform .12s,box-shadow .12s;
            position:relative;
            overflow:hidden
        }
        a.card::before{
            content:'';
            position:absolute;
            top:0;left:10%;right:10%;
            height:1px;
            background:linear-gradient(90deg,transparent,rgba(255,255,255,0.8),transparent)
        }
        a.card:hover{
            transform:translateY(-2px);
            box-shadow:0 12px 40px rgba(0,0,0,0.15)
        }
        a.card:active{transform:scale(.99)}
        a.card:focus-visible{outline:2px solid #c8203a;outline-offset:2px}
        a.card .ic{flex:none;width:52px;height:52px;border-radius:12px;background:rgba(241,245,249,0.8);
                   display:flex;align-items:center;justify-content:center;font-size:1.6rem}
        a.card b{display:block;font-size:1rem;font-weight:900;line-height:1.25}
        a.card small{display:block;margin-top:2px;font-size:.78rem;line-height:1.35;color:#64748b}
        a.card .go{margin-left:auto;flex:none;color:#94a3b8;font-size:1.3rem}
        /* Primary card - TNTT Red */
        a.card.primary{
            background:linear-gradient(145deg,rgba(200,32,58,0.85),rgba(200,32,58,0.72));
            border-color:rgba(200,32,58,0.3);
            box-shadow:0 8px 32px rgba(200,32,58,0.2);
        }
        a.card.primary::before{background:linear-gradient(90deg,transparent,rgba(255,255,255,0.4),transparent)}
        a.card.primary .ic{background:rgba(255,255,255,0.2)}
        a.card.primary small{color:rgba(255,255,255,0.85)}
        a.card.primary .go{color:rgba(255,255,255,0.8)}
        @media (prefers-reduced-transparency: reduce) {
            a.card{background:#fff!important;backdrop-filter:none!important}
            a.card.primary{background:#c8203a!important}
        }
        /* Scripture card - Amber/Brown Bible theme */
        a.card.scripture{
            background:linear-gradient(145deg,rgba(180,130,70,0.88),rgba(150,100,50,0.75));
            border-color:rgba(180,130,70,0.3);
            box-shadow:0 8px 32px rgba(150,100,50,0.2);
            cursor:pointer;
        }
        a.card.scripture::before{background:linear-gradient(90deg,transparent,rgba(255,255,255,0.4),transparent)}
        a.card.scripture .ic{background:rgba(255,255,255,0.2)}
        a.card.scripture small{color:rgba(255,255,255,0.9)}
        a.card.scripture .go{color:rgba(255,255,255,0.8)}
        @media (prefers-reduced-transparency: reduce) {
            a.card.scripture{background:#8B6914!important}
        }
        /* Scripture Modal */
        .scripture-modal-backdrop{
            position:fixed;inset:0;background:rgba(0,0,0,0.5);
            backdrop-filter:blur(4px);z-index:1000;
            display:flex;align-items:center;justify-content:center;
            padding:20px;opacity:0;transition:opacity .2s;pointer-events:none
        }
        .scripture-modal-backdrop.open{opacity:1;pointer-events:auto}
        .scripture-modal{
            background:linear-gradient(145deg,#fef3c7,#fde68a);
            border-radius:20px;padding:28px 24px;max-width:420px;width:100%;
            box-shadow:0 20px 60px rgba(0,0,0,0.3);
            transform:scale(.9) translateY(20px);
            transition:transform .25s cubic-bezier(.34,1.56,.64,1);position:relative
        }
        .scripture-modal-backdrop.open .scripture-modal{transform:scale(1) translateY(0)}
        .scripture-modal .cross-icon{
            font-size:2.5rem;text-align:center;margin-bottom:12px;
            text-shadow:0 2px 8px rgba(0,0,0,.1)
        }
        .scripture-modal h2{
            font-size:1.1rem;font-weight:800;text-align:center;
            color:#92400e;margin:0 0 16px;text-transform:uppercase;letter-spacing:.05em
        }
        .scripture-modal .verse-box{
            background:rgba(255,255,255,.7);border-radius:12px;
            padding:18px 16px;min-height:120px;position:relative;margin-bottom:16px
        }
        .scripture-modal .verse-text{
            font-size:.95rem;line-height:1.65;color:#451a03;
            font-style:italic;text-align:center;margin:0
        }
        .scripture-modal .verse-ref{
            display:block;text-align:right;font-size:.8rem;
            color:#b45309;font-weight:700;margin-top:10px
        }
        .scripture-modal .loading{
            display:flex;align-items:center;justify-content:center;
            min-height:80px
        }
        .scripture-modal .spinner{
            width:36px;height:36px;border:3px solid rgba(180,130,70,.2);
            border-top-color:#b45309;border-radius:50%;
            animation:spin .8s linear infinite
        }
        @keyframes spin{to{transform:rotate(360deg)}}
        .scripture-modal .close-btn{
            display:block;width:100%;padding:12px;border:none;
            background:linear-gradient(145deg,#b45309,#92400e);
            color:#fff;font-size:.95rem;font-weight:700;
            border-radius:10px;cursor:pointer;transition:transform .1s,box-shadow .1s
        }
        .scripture-modal .close-btn:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(146,64,14,.3)}
        .scripture-modal .close-btn:active{transform:scale(.98)}
        footer{margin-top:28px;text-align:center;font-size:.75rem;color:#94a3b8}
    </style>
</head>
<body>
<main class="wrap">
    <div class="brand">
        <img src="assets/img/icon-192.png?v=<?php echo $__cssV; ?>" alt="Logo Gia Đình Giáo Lý Phú Trung">
        <h1>Gia Đình Giáo Lý Phú Trung</h1>
        <p>Đoàn Thiếu Nhi Thánh Thể</p>
    </div>
    <nav class="grid" aria-label="Chọn chức năng">
        <?php foreach ($__links as $l): ?>
        <a class="card <?php echo $l['cls']; ?>" href="<?php echo $l['href']; ?>" <?php echo ($l['cls'] === 'scripture') ? 'data-scripture="true"' : ''; ?>>
            <span class="ic" aria-hidden="true"><?php echo $l['icon']; ?></span>
            <span><b><?php echo $l['title']; ?></b><small><?php echo $l['desc']; ?></small></span>
            <span class="go" aria-hidden="true">›</span>
        </a>
        <?php endforeach; ?>
    </nav>
    <footer>Quản lý &amp; tra cứu dành cho Đoàn Thiếu Nhi Thánh Thể</footer>
</main>

<!-- Scripture Modal -->
<div x-data="scriptureApp()" x-show="modalOpen" x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     @keydown.escape.window="closeModal()" class="scripture-modal-backdrop" :class="modalOpen && 'open'"
     style="display:none" role="dialog" aria-modal="true" aria-labelledby="scripture-modal-title">
    <div class="scripture-modal" @click.stop>
        <div class="cross-icon">✝️</div>
        <h2 id="scripture-modal-title">Lời Chúa Mỗi Ngày</h2>
        <div class="verse-box">
            <div x-show="loading" class="loading">
                <div class="spinner"></div>
            </div>
            <div x-show="!loading && verse" style="display:none">
                <p class="verse-text" x-text="verse"></p>
                <span class="verse-ref" x-text="'— ' + verseRef"></span>
            </div>
            <div x-show="!loading && error" style="display:none">
                <p class="verse-text" style="color:#dc2626" x-text="error"></p>
            </div>
        </div>
        <button type="button" class="close-btn" @click="closeModal()">Đóng</button>
    </div>
</div>

<script>
function scriptureApp() {
    return {
        modalOpen: false,
        verse: '',
        verseRef: '',
        error: '',
        loading: false,

        init() {
            document.addEventListener('click', (e) => {
                const card = e.target.closest('a.card.scripture');
                if (card) {
                    e.preventDefault();
                    this.openModal();
                }
            });
        },

        async openModal() {
            this.modalOpen = true;
            this.loading = true;
            this.verse = '';
            this.verseRef = '';
            this.error = '';

            try {
                const resp = await fetch('api/bible.php?action=random');
                const data = await resp.json();
                if (data.success) {
                    this.verse = data.verse;
                    this.verseRef = data.ref;
                } else {
                    this.error = 'Không thể tải lời Chúa. Vui lòng thử lại.';
                }
            } catch (e) {
                this.error = 'Lỗi kết nối. Vui lòng thử lại.';
            } finally {
                this.loading = false;
            }
        },

        closeModal() {
            this.modalOpen = false;
        }
    }
}
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

</body>
</html>

