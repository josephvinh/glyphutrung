<?php
// TRANG CHỦ CÔNG KHAI (landing) — hiện cho khách chưa đăng nhập ở index.php.
// Chỉ là bảng điều hướng: KHÔNG nạp dữ liệu nghiệp vụ nào, mọi lối vào đều là
// trang có sẵn (đăng nhập quản lý / tra cứu Sổ Mộc / bảng thi đua).
// Tự chứa CSS nội tuyến để khỏi phụ thuộc bản dịch Tailwind.
$__cssV = @filemtime(__DIR__ . '/../public/assets/img/icon-192.png') ?: 0;
$__links = [
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
        /* Dark mode */
        @media (prefers-color-scheme: dark) {
            body{background:#0f172a;color:#f8fafc}
            a.card{background:rgba(30,41,59,0.75);border-color:rgba(255,255,255,0.1);box-shadow:0 8px 32px rgba(0,0,0,0.25)}
            a.card .ic{background:rgba(51,65,85,0.8)}
            a.card small{color:#94a3b8}
            a.card .go{color:#64748b}
        }
        @media (prefers-reduced-transparency: reduce) {
            a.card{background:#fff!important;backdrop-filter:none!important}
            a.card.primary{background:#c8203a!important}
        }
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
        <a class="card <?php echo $l['cls']; ?>" href="<?php echo $l['href']; ?>">
            <span class="ic" aria-hidden="true"><?php echo $l['icon']; ?></span>
            <span><b><?php echo $l['title']; ?></b><small><?php echo $l['desc']; ?></small></span>
            <span class="go" aria-hidden="true">›</span>
        </a>
        <?php endforeach; ?>
    </nav>
    <footer>Quản lý &amp; tra cứu dành cho Đoàn Thiếu Nhi Thánh Thể</footer>
</main>
</body>
</html>
