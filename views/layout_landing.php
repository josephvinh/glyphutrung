<?php
// TRANG CHỦ CÔNG KHAI - Landing page với Lời Chúa Mỗi Ngày
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
                         align-items:center;text-align:center;cursor:pointer}
        a.card.scripture::after{content:'✝';position:absolute;top:-20px;right:-20px;
                                font-size:120px;opacity:0.05;transform:rotate(15deg);pointer-events:none}
        a.card.scripture:hover{transform:translateY(-3px);box-shadow:0 8px 32px rgba(30,58,95,0.4);
                               border-color:rgba(255,215,140,0.5)}
        a.card.scripture .ic{background:rgba(255,215,140,0.2);border-radius:50%;width:64px;height:64px;
                            border:2px solid rgba(255,215,140,0.3);margin-bottom:12px}
        a.card.scripture .ic svg{width:32px;height:32px;stroke:#ffd700;fill:none;stroke-width:2}
        a.card.scripture b{color:#fff;font-size:1.1rem;text-shadow:0 1px 2px rgba(0,0,0,0.2)}
        a.card.scripture small{color:rgba(255,255,255,0.8)}
        a.card.scripture .go{display:none}
        a.card.scripture .cta{display:inline-flex;align-items:center;gap:8px;margin-top:14px;
                              padding:10px 20px;background:linear-gradient(135deg,#ffd700,#ffb347);
                              color:#1e3a5f;font-weight:700;font-size:.85rem;border-radius:24px;
                              box-shadow:0 2px 8px rgba(255,215,0,0.3)}
        a.card.scripture .cta:hover{transform:scale(1.05);box-shadow:0 4px 16px rgba(255,215,0,0.4)}
        a.card.scripture .cta svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2}

        /* Scripture Modal */
        .modal-backdrop{position:fixed;inset:0;background:rgba(10,30,60,0.9);z-index:1000;
                        display:flex;align-items:center;justify-content:center;padding:20px;
                        opacity:0;transition:opacity .3s;pointer-events:none}
        .modal-backdrop.show{opacity:1;pointer-events:auto}
        .modal-box{background:linear-gradient(180deg,#1a365d,#0f2744);border-radius:24px;
                   max-width:480px;width:100%;padding:0;box-shadow:0 25px 80px rgba(0,0,0,0.5);
                   transform:scale(.9);transition:transform .35s;overflow:hidden}
        .modal-backdrop.show .modal-box{transform:scale(1)}
        .modal-header{background:rgba(255,215,140,0.1);padding:32px 24px;text-align:center}
        .modal-header h2{color:#ffd700;font-size:1.25rem;font-weight:800;text-transform:uppercase;
                          letter-spacing:.1em;margin:16px 0 0}
        .modal-header p{color:rgba(255,255,255,0.7);font-size:.85rem;margin:8px 0 0}
        .cross-icon{width:72px;height:72px;margin:0 auto;background:linear-gradient(135deg,#ffd700,#ffb347);
                    border-radius:50%;display:flex;align-items:center;justify-content:center;
                    box-shadow:0 4px 20px rgba(255,215,0,0.4)}
        .cross-icon svg{width:36px;height:36px;stroke:#1a365d;fill:none;stroke-width:2.5}
        .modal-body{padding:24px}
        .verse-box{background:rgba(255,255,255,0.05);border:1px solid rgba(255,215,140,0.15);
                   border-radius:16px;padding:24px 20px;min-height:140px;text-align:center}
        .verse-text{font-size:1.05rem;line-height:1.8;color:#fff;font-style:italic}
        .verse-ref{color:#ffd700;font-weight:600;margin-top:16px;display:block}
        .loading{display:flex;flex-direction:column;align-items:center;gap:12px}
        .spinner{width:40px;height:40px;border:3px solid rgba(255,215,140,0.2);border-top-color:#ffd700;
                 border-radius:50%;animation:spin 1s linear infinite}
        @keyframes spin{to{transform:rotate(360deg)}}
        .loading p{color:rgba(255,255,255,0.6);font-size:.85rem}
        .modal-footer{padding:0 24px 24px}
        .close-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;
                   padding:14px;background:rgba(255,255,255,0.1);color:#fff;font-size:.95rem;font-weight:600;
                   border:1px solid rgba(255,255,255,0.1);border-radius:12px;cursor:pointer}
        .close-btn:hover{background:rgba(255,255,255,0.15)}
        .close-btn svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2}
        footer{margin-top:28px;text-align:center;font-size:.75rem;color:#94a3b8}
        @media(prefers-reduced-motion:reduce){.spinner{animation:none}}
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
            <span><b><?php echo $l['title']; ?></b><small><?php echo $l['desc']; ?></small></span>
            <span class="cta">Nhận lời Chúa →</span>
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

<!-- Scripture Modal -->
<div id="scriptureModal" class="modal-backdrop">
    <div class="modal-box">
        <div class="modal-header">
            <div class="cross-icon">
                <svg viewBox="0 0 24 24"><path d="M12 2v20M7 7h10" stroke-linecap="round"/></svg>
            </div>
            <h2>Lời Chúa Mỗi Ngày</h2>
            <p>Hãy để Lời Chúa soi sáng con đường của bạn</p>
        </div>
        <div class="modal-body">
            <div class="verse-box" id="verseBox">
                <div class="loading" id="loadingBox">
                    <div class="spinner"></div>
                    <p>Đang tải lời Chúa...</p>
                </div>
                <p class="verse-text" id="verseText" style="display:none"></p>
                <span class="verse-ref" id="verseRef" style="display:none"></span>
            </div>
        </div>
        <div class="modal-footer">
            <button class="close-btn" onclick="closeModal()">
                <svg viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round"/></svg>
                Đóng
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('scriptureModal');
    var loadingBox = document.getElementById('loadingBox');
    var verseText = document.getElementById('verseText');
    var verseRef = document.getElementById('verseRef');

    // Click on scripture card
    var cards = document.querySelectorAll('#loichua');
    cards.forEach(function(card) {
        card.addEventListener('click', function(e) {
            e.preventDefault();
            openModal();
        });
    });

    // Also click on CTA button
    document.querySelectorAll('.cta').forEach(function(btn) {
        if (btn.textContent.includes('Nhận lời')) {
            btn.closest('a').addEventListener('click', function(e) {
                e.preventDefault();
                openModal();
            });
        }
    });

    function openModal() {
        modal.classList.add('show');
        loadingBox.style.display = 'flex';
        verseText.style.display = 'none';
        verseRef.style.display = 'none';

        fetch('api/bible.php?action=random')
            .then(function(resp) { return resp.json(); })
            .then(function(data) {
                loadingBox.style.display = 'none';
                if (data.success) {
                    verseText.textContent = data.verse;
                    verseText.style.display = 'block';
                    verseRef.textContent = '— ' + data.ref;
                    verseRef.style.display = 'block';
                } else {
                    verseText.textContent = 'Không thể tải lời Chúa. Vui lòng thử lại.';
                    verseText.style.display = 'block';
                }
            })
            .catch(function() {
                loadingBox.style.display = 'none';
                verseText.textContent = 'Lỗi kết nối. Vui lòng thử lại.';
                verseText.style.display = 'block';
            });
    }

    window.closeModal = function() {
        modal.classList.remove('show');
    };

    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });

    // Close on backdrop click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });
});
</script>

</body>
</html>
