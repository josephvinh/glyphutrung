<?php
// TRANG CHỦ CÔNG KHAI (landing) — hiện cho khách chưa đăng nhập ở index.php.
// Chỉ là bảng điều hướng: KHÔNG nạp dữ liệu nghiệp vụ nào, mọi lối vào đều là
// trang có sẵn (đăng nhập quản lý / tra cứu Sổ Mộc / bảng thi đua).
// Tự chứa CSS nội tuyến để khỏi phụ thuộc bản dịch Tailwind.
$__cssV = @filemtime(__DIR__ . '/../public/assets/img/icon-192.png') ?: 0;
$__links = [
    ['href' => '#loichua', 'icon' => 'cross', 'title' => 'Lời Chúa Mỗi Ngày',
     'desc' => 'Nhận lời Chúa ngẫu nhiên dành riêng cho bạn.', 'cls' => 'scripture', 'id' => 'loichua'],
    ['href' => 'index.php?dangnhap=1', 'icon' => 'lock', 'title' => 'Đăng nhập quản lý',
     'desc' => 'Dành cho Giáo Lý Viên, Trưởng Khối, Ban Điều Hành và Thủ Thư: điểm danh, điểm số, thiếu nhi, đổi quà…', 'cls' => 'primary'],
    ['href' => 'somoc.php', 'icon' => 'book', 'title' => 'Sổ Mộc',
     'desc' => 'Em và phụ huynh nhập mã thiếu nhi để xem Mộc, chuỗi đi lễ và đặt trước quà.', 'cls' => ''],
    ['href' => 'tracuu.php', 'icon' => 'search', 'title' => 'Tra cứu điểm',
     'desc' => 'Xem điểm số, sổ điểm danh và sổ liên lạc của em — nhập mã thiếu nhi và ngày sinh (tháng-ngày-năm).', 'cls' => ''],
    ['href' => 'bxh.php', 'icon' => 'trophy', 'title' => 'Bảng thi đua',
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

        /* ============================================
           SCRIPTURE CARD - Premium Bible Theme
           ============================================ */
        a.card.scripture{
            background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 50%, #1e3a5f 100%);
            border: 1px solid rgba(255, 215, 140, 0.3);
            box-shadow:
                0 4px 24px rgba(30, 58, 95, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            padding: 20px;
            flex-direction: column;
            align-items: stretch;
            text-align: center;
        }
        /* Decorative cross pattern */
        a.card.scripture::after{
            content: '✝';
            position: absolute;
            top: -20px;
            right: -20px;
            font-size: 120px;
            opacity: 0.05;
            transform: rotate(15deg);
            pointer-events: none;
        }
        /* Shine effect */
        a.card.scripture .shine{
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            animation: shine 3s ease-in-out infinite;
        }
        @keyframes shine {
            0%, 100% { left: -100%; }
            50% { left: 100%; }
        }
        a.card.scripture:hover{
            transform: translateY(-3px);
            box-shadow:
                0 8px 32px rgba(30, 58, 95, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 215, 140, 0.5);
        }
        a.card.scripture:hover .shine{
            animation-duration: 1.5s;
        }
        a.card.scripture::before{
            background: linear-gradient(90deg, transparent, rgba(255, 215, 140, 0.3), transparent);
            height: 2px;
            top: 0;
            left: 20%;
            right: 20%;
        }
        a.card.scripture .ic{
            background: rgba(255, 215, 140, 0.2);
            border-radius: 50%;
            width: 64px;
            height: 64px;
            margin: 0 auto 12px;
            border: 2px solid rgba(255, 215, 140, 0.3);
        }
        a.card.scripture .ic svg{
            width: 32px;
            height: 32px;
            stroke: #ffd700;
            fill: none;
            stroke-width: 2;
        }
        a.card.scripture b{
            font-size: 1.1rem;
            color: #fff;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
            margin-bottom: 4px;
        }
        a.card.scripture small{
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.85rem;
        }
        a.card.scripture .go{
            display: none;
        }
        /* Scripture card CTA button */
        a.card.scripture .cta{
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 14px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #ffd700, #ffb347);
            color: #1e3a5f;
            font-weight: 700;
            font-size: 0.85rem;
            border-radius: 24px;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(255, 215, 0, 0.3);
        }
        a.card.scripture .cta:hover{
            transform: scale(1.05);
            box-shadow: 0 4px 16px rgba(255, 215, 0, 0.4);
        }
        a.card.scripture .cta svg{
            width: 16px;
            height: 16px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
        }

        /* ============================================
           SCRIPTURE MODAL - Divine Design
           ============================================ */
        .scripture-modal-backdrop{
            position:fixed;inset:0;
            background: linear-gradient(180deg, rgba(10, 30, 60, 0.85) 0%, rgba(20, 50, 90, 0.9) 100%);
            backdrop-filter: blur(8px);
            z-index:1000;
            display:flex;align-items:center;justify-content:center;
            padding:20px;
            opacity:0;
            transition:opacity .3s ease;
            pointer-events:none
        }
        .scripture-modal-backdrop.open{opacity:1;pointer-events:auto}
        .scripture-modal{
            background: linear-gradient(180deg, #1a365d 0%, #0f2744 100%);
            border-radius:24px;
            padding:0;
            max-width:480px;width:100%;
            box-shadow:
                0 25px 80px rgba(0, 0, 0, 0.5),
                0 0 0 1px rgba(255, 215, 140, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            transform:scale(.9) translateY(20px);
            transition:transform .35s cubic-bezier(.34,1.56,.64,1);
            overflow: hidden;
            position: relative;
        }
        .scripture-modal-backdrop.open .scripture-modal{transform:scale(1) translateY(0)}

        /* Modal Header with Cross Icon */
        .scripture-modal .modal-header{
            background: linear-gradient(135deg, rgba(255, 215, 140, 0.15), rgba(255, 180, 80, 0.05));
            padding: 32px 24px 24px;
            text-align: center;
            position: relative;
        }
        .scripture-modal .modal-header::before{
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255, 215, 140, 0.5), transparent);
        }
        .scripture-modal .cross-icon{
            width: 72px;
            height: 72px;
            margin: 0 auto 16px;
            background: linear-gradient(135deg, #ffd700, #ffb347);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 20px rgba(255, 215, 0, 0.4);
            animation: pulse-glow 2s ease-in-out infinite;
        }
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 4px 20px rgba(255, 215, 0, 0.4); }
            50% { box-shadow: 0 4px 30px rgba(255, 215, 0, 0.6); }
        }
        .scripture-modal .cross-icon svg{
            width: 36px;
            height: 36px;
            stroke: #1a365d;
            fill: none;
            stroke-width: 2.5;
        }
        .scripture-modal .modal-header h2{
            font-size:1.25rem;font-weight:800;text-align:center;
            color:#ffd700;margin:0;
            text-transform:uppercase;letter-spacing:.1em;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        .scripture-modal .modal-header p{
            color: rgba(255,255,255,0.7);
            font-size: 0.85rem;
            margin: 8px 0 0;
        }

        /* Verse Content Area */
        .scripture-modal .modal-body{
            padding: 24px;
        }
        .scripture-modal .verse-box{
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 215, 140, 0.15);
            border-radius: 16px;
            padding: 24px 20px;
            min-height: 140px;
            position: relative;
            margin-bottom: 16px;
        }
        /* Decorative quote marks */
        .scripture-modal .verse-box::before{
            content: '"';
            position: absolute;
            top: 8px;
            left: 12px;
            font-size: 48px;
            color: rgba(255, 215, 140, 0.2);
            font-family: Georgia, serif;
            line-height: 1;
        }
        .scripture-modal .verse-text{
            font-size: 1.05rem;
            line-height: 1.8;
            color: #fff;
            font-style: italic;
            text-align: center;
            margin: 0;
            padding: 0 12px;
            position: relative;
            z-index: 1;
        }
        .scripture-modal .verse-ref{
            display: block;
            text-align: center;
            font-size: 0.9rem;
            color: #ffd700;
            font-weight: 600;
            margin-top: 16px;
            font-style: normal;
            letter-spacing: 0.05em;
        }
        .scripture-modal .verse-ref::before{
            content: '— ';
        }

        /* Loading State */
        .scripture-modal .loading{
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100px;
            gap: 12px;
        }
        .scripture-modal .spinner{
            width: 40px;
            height: 40px;
            border: 3px solid rgba(255, 215, 140, 0.2);
            border-top-color: #ffd700;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin{to{transform:rotate(360deg)}}
        .scripture-modal .loading p{
            color: rgba(255,255,255,0.6);
            font-size: 0.85rem;
            margin: 0;
        }

        /* Error State */
        .scripture-modal .error-box{
            background: rgba(220, 38, 38, 0.1);
            border: 1px solid rgba(220, 38, 38, 0.3);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        .scripture-modal .error-box p{
            color: #fca5a5;
            margin: 0;
        }

        /* Modal Footer */
        .scripture-modal .modal-footer{
            padding: 0 24px 24px;
        }
        .scripture-modal .close-btn{
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
            color: #fff;
            font-size: 0.95rem;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .scripture-modal .close-btn:hover{
            background: linear-gradient(135deg, rgba(255,255,255,0.15), rgba(255,255,255,0.1));
            border-color: rgba(255,255,255,0.2);
        }
        .scripture-modal .close-btn:active{
            transform: scale(0.98);
        }
        .scripture-modal .close-btn svg{
            width: 18px;
            height: 18px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
        }

        footer{margin-top:28px;text-align:center;font-size:.75rem;color:#94a3b8}

        /* Reduced Motion */
        @media (prefers-reduced-motion: reduce) {
            a.card.scripture .shine{animation:none}
            .scripture-modal .cross-icon{animation:none}
            .scripture-modal .spinner{animation:none}
        }
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
            <?php if ($l['cls'] === 'scripture'): ?>
            <div class="shine"></div>
            <div class="ic">
                <!-- Cross Icon SVG -->
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2v20M7 7h10" stroke-linecap="round"/>
                </svg>
            </div>
            <span><b><?php echo $l['title']; ?></b><small><?php echo $l['desc']; ?></small></span>
            <span class="cta">
                Nhận lời Chúa
                <svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <?php else: ?>
            <span class="ic" aria-hidden="true"><?php
                $icons = ['lock' => '🔐', 'book' => '📒', 'search' => '📘', 'trophy' => '🏆'];
                echo $icons[$l['icon']] ?? '📌';
            ?></span>
            <span><b><?php echo $l['title']; ?></b><small><?php echo $l['desc']; ?></small></span>
            <span class="go" aria-hidden="true">›</span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>
    <footer>Quản lý &amp; tra cứu dành cho Đoàn Thiếu Nhi Thánh Thể</footer>
</main>

<!-- Scripture Modal -->
<div x-data="scriptureApp()"
     x-show="modalOpen"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @keydown.escape.window="closeModal()"
     class="scripture-modal-backdrop"
     :class="modalOpen && 'open'"
     style="display:none"
     role="dialog"
     aria-modal="true"
     aria-labelledby="scripture-modal-title">
    <div class="scripture-modal" @click.stop>
        <!-- Header -->
        <div class="modal-header">
            <div class="cross-icon">
                <!-- Cross Icon SVG -->
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2v20M7 7h10" stroke-linecap="round"/>
                </svg>
            </div>
            <h2 id="scripture-modal-title">Lời Chúa Mỗi Ngày</h2>
            <p>Hãy để Lời Chúa soi sáng con đường của bạn</p>
        </div>

        <!-- Body -->
        <div class="modal-body">
            <div class="verse-box">
                <!-- Loading -->
                <div x-show="loading" class="loading">
                    <div class="spinner"></div>
                    <p>Đang tải lời Chúa...</p>
                </div>

                <!-- Verse Content -->
                <div x-show="!loading && verse" style="display:none">
                    <p class="verse-text" x-text="verse"></p>
                    <span class="verse-ref" x-text="verseRef"></span>
                </div>

                <!-- Error -->
                <div x-show="!loading && error" style="display:none" class="error-box">
                    <p x-text="error"></p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="modal-footer">
            <button type="button" class="close-btn" @click="closeModal()">
                <svg viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Đóng
            </button>
        </div>
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
