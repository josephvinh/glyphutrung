<?php
// TRANG CHỦ CÔNG KHAI - Landing page voi Lời Chúa Mỗi Ngày
$__cssV = @filemtime(__DIR__ . '/../public/assets/img/icon-192.png') ?: 0;
$__links = [
    ['href' => '#loichua', 'icon' => 'cross', 'title' => 'Lời Chúa Mỗi Ngày',
     'desc' => 'Nhận lời Chúa ngẫu nhiên dành riêng cho bạn.', 'cls' => 'scripture', 'id' => 'loichua'],
    ['href' => 'index.php?dangnhap=1', 'icon' => 'lock', 'title' => 'Đăng nhập quản lý',
     'desc' => 'Dành cho Giáo Lý Viên, Trưởng Khối, Ban Điều Hành và Thủ Thư.', 'cls' => 'primary'],
    ['href' => 'somoc.php', 'icon' => 'book', 'title' => 'Sổ Mộc',
     'desc' => 'Xem Mộc, chuỗi đi lễ và đặt trước quà.', 'cls' => ''],
    ['href' => 'tracuu.php', 'icon' => 'search', 'title' => 'Tra cứu điểm',
     'desc' => 'Xem điểm số và sổ điểm danh.', 'cls' => ''],
    ['href' => 'bxh.php', 'icon' => 'trophy', 'title' => 'Bảng thi đua',
     'desc' => 'Xếp hạng chuyên cần & học tập.', 'cls' => ''],
];
$icons = ['lock' => '🔐', 'book' => '📒', 'search' => '📘', 'trophy' => '🏆'];
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
        *{box-sizing:border-box;margin:0;padding:0}
        body{min-height:100vh;background:#f8fafc;color:#1e293b;font-family:system-ui,sans-serif;-webkit-font-smoothing:antialiased}
        .wrap{max-width:560px;margin:0 auto;padding:40px 20px calc(32px + env(safe-area-inset-bottom))}
        @media(min-width:640px){.wrap{max-width:720px;padding:60px 40px}}
        @media(min-width:1024px){.wrap{max-width:800px;padding:80px 40px}}
        .brand{text-align:center;margin-bottom:28px}
        .brand img{width:96px;height:96px;object-fit:contain;border-radius:24px}
        .brand h1{font-size:1.5rem;font-weight:900;margin:16px 0 4px}
        .brand p{margin:0;font-size:.875rem;color:#94a3b8}
        .grid{display:grid;gap:14px}
        @media(min-width:640px){.grid{gap:18px}}
        a.card{display:flex;align-items:center;gap:14px;padding:18px;border-radius:16px;
               background:rgba(255,255,255,0.72);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.5);
               box-shadow:0 8px 32px rgba(0,0,0,0.1);text-decoration:none;color:inherit;
               transition:transform .12s,box-shadow .12s;position:relative;overflow:hidden}
        a.card:hover{transform:translateY(-2px);box-shadow:0 12px 40px rgba(0,0,0,0.15)}
        a.card:active{transform:scale(.99)}
        a.card .ic{flex:none;width:52px;height:52px;border-radius:12px;background:rgba(241,245,249,0.8);
                   display:flex;align-items:center;justify-content:center;font-size:1.6rem}
        a.card b{display:block;font-size:1rem;font-weight:900;line-height:1.25}
        a.card small{display:block;margin-top:2px;font-size:.78rem;color:#64748b}
        a.card .go{margin-left:auto;flex:none;color:#94a3b8;font-size:1.3rem}
        a.card.primary{background:linear-gradient(145deg,rgba(200,32,58,0.85),rgba(200,32,58,0.72));
                        border-color:rgba(200,32,58,0.3);box-shadow:0 8px 32px rgba(200,32,58,0.2)}
        a.card.primary .ic{background:rgba(255,255,255,0.2)}
        a.card.primary small{color:rgba(255,255,255,0.85)}
        a.card.primary .go{color:rgba(255,255,255,0.8)}

        /* Scripture Card */
        a.card.scripture{background:linear-gradient(135deg,#1e3a5f,#2d5a87,#1e3a5f);
                         border:1px solid rgba(255,215,140,0.3);padding:20px;flex-direction:column;
                         align-items:center;text-align:center;cursor:pointer;min-height:200px}
        a.card.scripture::after{content:'✝';position:absolute;top:-20px;right:-20px;
                                font-size:120px;opacity:0.05;transform:rotate(15deg);pointer-events:none}
        a.card.scripture:hover{transform:translateY(-3px);box-shadow:0 8px 32px rgba(30,58,95,0.4);
                               border-color:rgba(255,215,140,0.5)}
        a.card.scripture .ic{background:rgba(255,215,140,0.2);border-radius:50%;width:64px;height:64px;
                            border:2px solid rgba(255,215,140,0.3);margin-bottom:12px}
        a.card.scripture .ic svg{width:32px;height:32px;stroke:#ffd700;fill:none;stroke-width:2}
        a.card.scripture .title{color:#fff;font-size:1.1rem;font-weight:700;text-shadow:0 1px 2px rgba(0,0,0,0.2)}
        a.card.scripture .desc{color:rgba(255,255,255,0.8);font-size:.85rem;margin-top:4px}
        a.card.scripture .go{display:none}

        /* CTA Button */
        .cta-btn{display:inline-flex;align-items:center;gap:8px;margin-top:14px;
                padding:10px 24px;background:linear-gradient(135deg,#ffd700,#ffb347);
                color:#1e3a5f;font-weight:700;font-size:.9rem;border-radius:24px;
                box-shadow:0 2px 8px rgba(255,215,0,0.3);cursor:pointer;border:none;
                transition:all .2s}
        .cta-btn:hover{transform:scale(1.05);box-shadow:0 4px 16px rgba(255,215,0,0.4)}
        .cta-btn:disabled{opacity:0.7;cursor:not-allowed;transform:none}

        /* Verse Display */
        .verse-container{margin-top:12px;width:100%;text-align:center}
        .verse-text{color:#fff;font-size:.95rem;line-height:1.6;font-style:italic;
                   padding:12px;background:rgba(255,255,255,0.08);border-radius:12px;
                   border:1px solid rgba(255,215,140,0.2)}
        .verse-ref{color:#ffd700;font-weight:600;margin-top:8px;font-size:.85rem}
        .verse-ref::before{content:'— '}

        /* Loading */
        .loading-spinner{width:24px;height:24px;border:3px solid rgba(255,215,140,0.3);
                        border-top-color:#ffd700;border-radius:50%;animation:spin 1s linear infinite;margin:10px auto}
        @keyframes spin{to{transform:rotate(360deg)}}

        footer{margin-top:28px;text-align:center;font-size:.75rem;color:#94a3b8}
        @media(prefers-reduced-motion:reduce){.loading-spinner{animation:none}}
    </style>
</head>
<body>
<main class="wrap">
    <div class="brand">
        <img src="assets/img/icon-192.png?v=<?php echo $__cssV; ?>" alt="Logo">
        <h1>Gia Đình Giáo Lý Phú Trung</h1>
        <p>Đoàn Thiếu Nhi Thánh Thể</p>
    </div>

    <nav class="grid">
        <?php foreach ($__links as $l): ?>
        <a class="card <?php echo $l['cls']; ?>" href="<?php echo $l['href']; ?>" id="<?php echo $l['id'] ?? ''; ?>">
            <?php if ($l['cls'] === 'scripture'): ?>
            <div class="ic">
                <svg viewBox="0 0 24 24"><path d="M12 2v20M7 7h10" stroke-linecap="round"/></svg>
            </div>
            <span class="title"><?php echo $l['title']; ?></span>
            <span class="desc"><?php echo $l['desc']; ?></span>
            <div id="verseArea" style="margin-top:12px;width:100%;text-align:center;display:none"></div>
            <button class="cta-btn" id="ctaBtn" onclick="event.preventDefault();event.stopPropagation();getVerse()">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M12 2v20M7 7h10" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/></svg>
                Nhận lời Chúa
            </button>
            <?php else: ?>
            <span class="ic"><?php echo $icons[$l['icon']] ?? '📌'; ?></span>
            <span><b><?php echo $l['title']; ?></b><small><?php echo $l['desc']; ?></small></span>
            <span class="go">›</span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <footer>Quản lý & tra cứu dành cho Đoàn Thiếu Nhi Thánh Thể</footer>
</main>

<script>
(function() {
    var verseArea = document.getElementById('verseArea');
    var ctaBtn = document.getElementById('ctaBtn');

    // Kiem tra xem da co verse hom nay chua
    function getStoredVerse() {
        try {
            var stored = localStorage.getItem('dailyVerse');
            if (stored) {
                var data = JSON.parse(stored);
                var today = new Date().toDateString();
                if (data.date === today) {
                    return data;
                }
            }
        } catch(e) {}
        return null;
    }

    // Luu verse vao localStorage
    function saveVerse(verse, ref) {
        try {
            var data = {
                date: new Date().toDateString(),
                verse: verse,
                ref: ref,
                timestamp: Date.now()
            };
            localStorage.setItem('dailyVerse', JSON.stringify(data));
        } catch(e) {}
    }

    // Hien thi verse
    function showVerse(verse, ref) {
        verseArea.innerHTML = '<div class="verse-text">' + verse + '</div><div class="verse-ref">' + ref + '</div>';
        verseArea.style.display = 'block';
        ctaBtn.style.display = 'none';
    }

    // Hien thi loading
    function showLoading() {
        verseArea.innerHTML = '<div class="loading-spinner"></div><p style="color:rgba(255,255,255,0.7);font-size:.85rem">Đang tải...</p>';
        verseArea.style.display = 'block';
        ctaBtn.disabled = true;
        ctaBtn.innerHTML = '<div class="loading-spinner" style="width:16px;height:16px;border-width:2px;margin:0"></div> Đang tải...';
    }

    // Lay verse tu API
    window.getVerse = function() {
        showLoading();

        fetch('api/bible.php?action=random')
            .then(function(resp) { return resp.json(); })
            .then(function(data) {
                if (data.success && data.verse) {
                    showVerse(data.verse, data.ref);
                    saveVerse(data.verse, data.ref);
                } else {
                    verseArea.innerHTML = '<p style="color:#fca5a5;font-size:.85rem">Không thể tải. Thử lại sau.</p>';
                }
            })
            .catch(function() {
                verseArea.innerHTML = '<p style="color:#fca5a5;font-size:.85rem">Lỗi kết nối.</p>';
            });
    };

    // Kiem tra khi load trang
    var stored = getStoredVerse();
    if (stored) {
        showVerse(stored.verse, stored.ref);
    }
})();
</script>

</body>
</html>
