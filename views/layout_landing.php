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
        body{min-height:100vh;background:linear-gradient(135deg,#f8fafc 0%,#e2e8f0 50%,#f1f5f9 100%);color:#1e293b;font-family:"Inter",ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;-webkit-font-smoothing:antialiased}
        .wrap{max-width:560px;margin:0 auto;padding:40px 20px calc(32px + env(safe-area-inset-bottom))}
        @media(min-width:640px){.wrap{max-width:720px;padding:60px 40px}}
        @media(min-width:1024px){.wrap{max-width:800px;padding:80px 40px}}
        .brand{text-align:center;margin-bottom:28px}
        .brand img{width:96px;height:96px;object-fit:contain;border-radius:24px;box-shadow:0 8px 32px rgba(0,0,0,0.15)}
        .brand h1{font-size:1.5rem;font-weight:900;margin:16px 0 4px;background:linear-gradient(135deg,#c8203a,#e11d48);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
        .brand p{margin:0;font-size:.875rem;color:#64748b}
        .grid{display:grid;gap:14px}
        @media(min-width:640px){.grid{gap:18px}}

        /* Base Card */
        a.card{display:flex;align-items:center;gap:14px;padding:18px;border-radius:20px;
               background:rgba(255,255,255,0.9);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.8);
               box-shadow:0 4px 24px rgba(0,0,0,0.08);text-decoration:none;color:inherit;
               transition:all .2s;position:relative;overflow:hidden}
        a.card::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,0.4),transparent);opacity:0;transition:opacity .2s}
        a.card:hover{transform:translateY(-4px);box-shadow:0 12px 40px rgba(0,0,0,0.15)}
        a.card:hover::before{opacity:1}
        a.card:active{transform:translateY(-2px) scale(.99)}
        a.card .ic{flex:none;width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,#f1f5f9,#e2e8f0);display:flex;align-items:center;justify-content:center;font-size:1.6rem;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
        a.card b{display:block;font-size:1rem;font-weight:800;color:#1e293b;line-height:1.25}
        a.card small{display:block;margin-top:3px;font-size:.78rem;color:#64748b;line-height:1.4}
        a.card .go{margin-left:auto;flex:none;color:#94a3b8;font-size:1.4rem;font-weight:300}

        /* Primary Card */
        a.card.primary{background:linear-gradient(135deg,#c8203a,#dc2626);border-color:rgba(200,32,58,0.3);box-shadow:0 4px 24px rgba(200,32,58,0.3)}
        a.card.primary .ic{background:rgba(255,255,255,0.25);box-shadow:none}
        a.card.primary b{color:#fff}
        a.card.primary small{color:rgba(255,255,255,0.85)}
        a.card.primary .go{color:rgba(255,255,255,0.8)}
        a.card.primary:hover{box-shadow:0 12px 40px rgba(200,32,58,0.4)}

        /* Scripture Card - PREMIUM */
        a.card.scripture{
            background:linear-gradient(145deg,#1a365d 0%,#2c5282 30%,#1e3a5f 70%,#0f172a 100%);
            border:2px solid rgba(255,215,0,0.4);padding:28px 24px;flex-direction:column;
            align-items:center;text-align:center;cursor:pointer;min-height:280px;
            box-shadow:0 8px 32px rgba(26,54,93,0.4),inset 0 1px 0 rgba(255,255,255,0.1);
            position:relative;overflow:hidden
        }

        /* Decorative glow */
        a.card.scripture::before{
            content:'';position:absolute;top:-50%;left:-50%;width:200%;height:200%;
            background:radial-gradient(circle,rgba(255,215,0,0.1) 0%,transparent 50%);
            animation:pulse-glow 4s ease-in-out infinite;pointer-events:none
        }
        @keyframes pulse-glow{
            0%,100%{opacity:0.5;transform:scale(1)}
            50%{opacity:1;transform:scale(1.1)}
        }

        /* Cross watermark */
        a.card.scripture::after{
            content:'✝';position:absolute;top:-30px;right:-30px;font-size:160px;
            opacity:0.06;transform:rotate(15deg);pointer-events:none;color:#ffd700;
            text-shadow:0 0 40px rgba(255,215,0,0.3)
        }

        /* Shine effect */
        .shine-effect{
            position:absolute;top:0;left:-100%;width:60%;height:100%;
            background:linear-gradient(90deg,transparent,rgba(255,255,255,0.15),transparent);
            animation:shine 3s ease-in-out infinite;pointer-events:none
        }
        @keyframes shine{
            0%,100%{left:-100%}
            50%{left:150%}
        }

        a.card.scripture:hover{
            transform:translateY(-6px) scale(1.02);
            box-shadow:0 20px 60px rgba(26,54,93,0.5),0 0 40px rgba(255,215,0,0.2);
            border-color:rgba(255,215,0,0.6)
        }
        a.card.scripture:hover .shine-effect{animation-duration:1.5s}

        /* Icon */
        a.card.scripture .ic{
            background:linear-gradient(135deg,#ffd700,#ffb347);border-radius:50%;
            width:80px;height:80px;margin-bottom:16px;border:3px solid rgba(255,255,255,0.3);
            box-shadow:0 4px 20px rgba(255,215,0,0.4),inset 0 2px 4px rgba(255,255,255,0.4);
            position:relative;z-index:1
        }
        a.card.scripture .ic svg{width:40px;height:40px;stroke:#1a365d;fill:none;stroke-width:2.5;filter:drop-shadow(0 2px 4px rgba(0,0,0,0.2))}

        /* Title & Description */
        a.card.scripture .title{color:#ffd700;font-size:1.3rem;font-weight:800;letter-spacing:.02em;text-shadow:0 2px 4px rgba(0,0,0,0.3);position:relative;z-index:1}
        a.card.scripture .desc{color:rgba(255,255,255,0.9);font-size:.9rem;margin-top:8px;line-height:1.5;position:relative;z-index:1}

        /* CTA Button */
        .cta-btn{
            display:inline-flex;align-items:center;gap:10px;margin-top:20px;padding:14px 32px;
            background:linear-gradient(135deg,#ffd700,#ffb347,#ffd700);background-size:200% 100%;
            color:#1a365d;font-weight:800;font-size:1rem;border-radius:30px;border:none;cursor:pointer;
            box-shadow:0 4px 20px rgba(255,215,0,0.5),inset 0 2px 4px rgba(255,255,255,0.4);
            transition:all .3s;position:relative;z-index:1;animation:btn-glow 2s ease-in-out infinite
        }
        @keyframes btn-glow{
            0%,100%{box-shadow:0 4px 20px rgba(255,215,0,0.5)}
            50%{box-shadow:0 4px 30px rgba(255,215,0,0.8),0 0 40px rgba(255,215,0,0.3)}
        }
        .cta-btn:hover{
            transform:scale(1.08);background-position:100% 0;
            box-shadow:0 8px 30px rgba(255,215,0,0.7);animation:none
        }
        .cta-btn:active{transform:scale(1.02)}
        .cta-btn:disabled{opacity:0.6;cursor:not-allowed;transform:none;animation:none}

        .cta-btn svg{width:20px;height:20px;stroke:currentColor;fill:none;stroke-width:2.5;transition:transform .3s}
        .cta-btn:hover svg{transform:rotate(15deg)}

        /* Verse Display */
        .verse-container{margin-top:20px;width:100%;position:relative;z-index:1}
        .verse-box{
            background:rgba(255,255,255,0.1);border:1px solid rgba(255,215,140,0.3);
            border-radius:16px;padding:20px;text-align:center;backdrop-filter:blur(10px);
            animation:fadeIn .5s ease-out
        }
        @keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

        /* Quote marks */
        .verse-box::before{content:'"';position:absolute;top:8px;left:16px;font-size:48px;color:rgba(255,215,0,0.2);font-family:Georgia,serif;line-height:1}
        .verse-box::after{content:'"';position:absolute;bottom:0;right:16px;font-size:48px;color:rgba(255,215,0,0.2);font-family:Georgia,serif;line-height:1}

        .verse-text{color:#fff;font-size:1.05rem;line-height:1.8;font-style:italic;padding:0 20px;position:relative;z-index:1}
        .verse-ref{color:#ffd700;font-weight:700;margin-top:16px;font-size:.95rem;letter-spacing:.03em;display:block}
        .verse-ref::before{content:'— '}

        /* Loading */
        .loading-container{display:flex;flex-direction:column;align-items:center;gap:12px}
        .loading-spinner{
            width:40px;height:40px;border:4px solid rgba(255,215,140,0.2);border-top-color:#ffd700;
            border-radius:50%;animation:spin 1s linear infinite;box-shadow:0 0 20px rgba(255,215,0,0.3)
        }
        @keyframes spin{to{transform:rotate(360deg)}}
        .loading-text{color:rgba(255,255,255,0.8);font-size:.9rem;animation:pulse 1.5s ease-in-out infinite}
        @keyframes pulse{0%,100%{opacity:0.6}50%{opacity:1}}

        /* Footer */
        footer{margin-top:32px;text-align:center;font-size:.8rem;color:#64748b;padding-top:20px;border-top:1px solid rgba(0,0,0,0.05)}

        /* Responsive */
        @media(max-width:480px){
            a.card.scripture{padding:24px 20px;min-height:260px}
            a.card.scripture .ic{width:70px;height:70px}
            a.card.scripture .ic svg{width:35px;height:35px}
            a.card.scripture .title{font-size:1.15rem}
            .cta-btn{padding:12px 28px;font-size:.95rem}
        }
        @media(prefers-reduced-motion:reduce){
            .shine-effect,.pulse-glow,.btn-glow,.loading-spinner{animation:none}
        }
    </style>
</head>
<body>
<main class="wrap">
    <div class="brand">
        <img src="assets/img/icon-192.png?v=<?php echo $__cssV; ?>" alt="Logo">
        <h1>Gia Đình Giáo Lý </h1>
        <p>Giáo xứ Phú Trung</p>
    </div>

    <nav class="grid">
        <?php foreach ($__links as $l): ?>
        <a class="card <?php echo $l['cls']; ?>" href="<?php echo $l['href']; ?>" id="<?php echo $l['id'] ?? ''; ?>">
            <?php if ($l['cls'] === 'scripture'): ?>
            <div class="shine-effect"></div>
            <div class="ic">
                <svg viewBox="0 0 24 24"><path d="M12 2v20M7 7h10" stroke-linecap="round"/></svg>
            </div>
            <span class="title"><?php echo $l['title']; ?></span>
            <span class="desc"><?php echo $l['desc']; ?></span>
            <div class="verse-container" id="verseArea" style="display:none"></div>
            <button class="cta-btn" id="ctaBtn" onclick="event.preventDefault();event.stopPropagation();getVerse()">
                <svg viewBox="0 0 24 24"><path d="M12 2v20M7 7h10" stroke-linecap="round"/></svg>
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

    <footer>Quản lý & tra cứu dành cho GIA ĐÌNH GIÁO LÝ GX PHÚ TRUNG</footer>
</main>

<script>
(function() {
    var verseArea = document.getElementById('verseArea');
    var ctaBtn = document.getElementById('ctaBtn');

    function getStoredVerse() {
        try {
            var stored = localStorage.getItem('dailyVerse');
            if (stored) {
                var data = JSON.parse(stored);
                var today = new Date().toDateString();
                if (data.date === today) return data;
            }
        } catch(e) {}
        return null;
    }

    function saveVerse(verse, ref) {
        try {
            localStorage.setItem('dailyVerse', JSON.stringify({
                date: new Date().toDateString(),
                verse: verse,
                ref: ref,
                timestamp: Date.now()
            }));
        } catch(e) {}
    }

    function showVerse(verse, ref) {
        // S6: XSS prevention - dùng textContent thay vì innerHTML
        verseArea.textContent = verse;
        refArea.textContent = ref;
        verseArea.style.display = 'block';
        ctaBtn.style.display = 'none';
    }

    function showLoading() {
        verseArea.innerHTML = '<div class="loading-container"><div class="loading-spinner"></div><p class="loading-text">Đang tải lời Chúa...</p></div>';
        verseArea.style.display = 'block';
        ctaBtn.disabled = true;
        ctaBtn.innerHTML = '<div class="loading-spinner" style="width:20px;height:20px;border-width:3px"></div> Đang tải...';
    }

    window.getVerse = function() {
        showLoading();
        fetch('api/bible.php?action=random')
            .then(function(resp) { return resp.json(); })
            .then(function(data) {
                if (data.success && data.verse) {
                    showVerse(data.verse, data.ref);
                    saveVerse(data.verse, data.ref);
                } else {
                    verseArea.innerHTML = '<div class="verse-box"><p class="verse-text" style="color:#fca5a5">Không thể tải lời Chúa. Vui lòng thử lại sau.</p></div>';
                }
            })
            .catch(function() {
                verseArea.innerHTML = '<div class="verse-box"><p class="verse-text" style="color:#fca5a5">Lỗi kết nối. Vui lòng kiểm tra mạng.</p></div>';
            });
    };

    var stored = getStoredVerse();
    if (stored) showVerse(stored.verse, stored.ref);
})();
</script>

</body>
</html>
