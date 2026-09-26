<?php
/**
 * TRA CỨU SỔ MỘC — TRANG CÔNG KHAI (không đăng nhập)
 *
 * Em/phụ huynh nhập mã thiếu nhi để xem Sổ Mộc của mình: Ví (Mộc còn tiêu
 * được + tổng đã kiếm), Lửa chuỗi đi lễ, và lịch sử giao dịch gần đây.
 * CHỈ ĐỌC — không có thao tác ghi nào ở trang này (đặt/đổi quà là Task
 * P3-3, sẽ điền vào khung tab "Đổi quà" bên dưới).
 *
 * Bảo vệ (SPEC-MOC-DIEN-TU §6.3, mẫu public/bxh.php):
 *   - chỉ nạp config/db.php + logic thuần _tracuu.php (KHÔNG _bootstrap.php,
 *     KHÔNG cần đăng nhập, KHÔNG dính Content-Type: application/json);
 *   - chặn Google lập chỉ mục (noindex) + không rò Referer sang trang khác;
 *   - prepared statements (trong _tracuu.php), ép kiểu tham số;
 *   - rate-limit theo IP (tracuu_throttle/tracuu_attempt_record) để chặn dò
 *     quét toàn bộ dải mã thiếu nhi;
 *   - tracuu_public_summary() chỉ trả đúng tên + lớp + Mộc + lịch sử của
 *     ĐÚNG em mang mã đó — không có đường nào lộ danh sách toàn bộ em.
 */

header('X-Robots-Tag: noindex, nofollow', true);
header('Referrer-Policy: no-referrer');

require __DIR__ . '/../config/db.php';
require __DIR__ . '/api/_tracuu.php';

/* ---------- Niên khoá đang mở ---------- */
$year = db_one("SELECT id FROM school_years WHERE is_current = 1 LIMIT 1");
if (!$year) {
    http_response_code(503);
    echo 'Chưa mở niên khoá.';
    exit;
}
$yearId = (int) $year['id'];

/* ---------- Tab (Sổ Mộc | Đổi quà) ---------- */
$tab = (($_GET['tab'] ?? 'so-moc') === 'doi-qua') ? 'doi-qua' : 'so-moc';

/* ---------- Xử lý tra cứu (form dùng GET, ép kiểu chuỗi an toàn) ---------- */
$ma = trim((string) ($_GET['ma'] ?? ''));
$ma = mb_substr($ma, 0, 32, 'UTF-8'); // students.code là VARCHAR(32)

$ketQua   = null;
$khongCo  = false;
if ($ma !== '') {
    tracuu_throttle();          // 429 (json_fail) nếu vượt ngưỡng — dừng tại đây
    tracuu_attempt_record();    // ghi nhận lượt tra cứu (dù tìm thấy hay không)
    $ketQua = tracuu_public_summary($ma, $yearId);
    $khongCo = ($ketQua === null);
}

function e_($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

/** Giữ lại mã + tab khi đổi link (giống urlVoi() ở bxh.php) */
function urlVoi(array $ghi): string {
    $q = array_merge(['ma' => $_GET['ma'] ?? '', 'tab' => $_GET['tab'] ?? 'so-moc'], $ghi);
    $q = array_filter($q, fn($v) => $v !== '' && $v !== null);
    return 'tracuu.php?' . http_build_query($q);
}

/** Định dạng giao dịch: dấu +/- rõ ràng, ngày giờ Việt Nam */
function dinhDangGD(array $t): string {
    $dau = $t['amount'] > 0 ? '+' : '';
    return $dau . (int) $t['amount'];
}
?><!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Tra Cứu Sổ Mộc · Thiếu Nhi Thánh Thể</title>
<link rel="stylesheet" href="assets/css/font.css">
<style>
:root{--vang:#f6b100;--vang2:#ffd54a;--lua:#f97316;--nen:#0b1e4d;--nen2:#15347e;}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Be Vietnam Pro",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
 color:#0f172a;background:linear-gradient(160deg,#eef3ff,#f8fafc 40%);min-height:100vh;padding:0 0 48px}
.wrap{max-width:640px;margin:0 auto;padding:0 16px}
.hero{background:linear-gradient(135deg,var(--nen),var(--nen2));color:#fff;text-align:center;
 padding:34px 16px 60px;border-radius:0 0 28px 28px;position:relative}
.hero .ico{font-size:38px;line-height:1}
.hero h1{font-size:24px;font-weight:900;letter-spacing:.5px;margin:6px 0 2px}
.hero p{opacity:.85;font-size:13px}
.back-btn{position:absolute;top:16px;left:16px;width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.15);color:#fff;display:flex;align-items:center;justify-content:center;text-decoration:none;z-index:10;backdrop-filter:blur(4px)}
.back-btn:active{background:rgba(255,255,255,.3);transform:scale(0.95)}

.tabs{display:flex;background:#fff;border-radius:999px;padding:4px;box-shadow:0 8px 20px -12px rgba(15,23,42,.4);margin:-30px auto 16px;position:relative;z-index:2}
.tabs a{flex:1;text-align:center;padding:9px 10px;border-radius:999px;font-size:13.5px;font-weight:800;color:#64748b;text-decoration:none}
.tabs a.on{background:var(--nen2);color:#fff}

form.tra{display:flex;gap:8px;margin-bottom:16px}
form.tra input[type=text]{flex:1;border:1px solid #e2e8f0;border-radius:14px;padding:13px 16px;font-size:16px;font-weight:700;letter-spacing:.5px;background:#fff;color:#0f172a;text-transform:uppercase}
form.tra input[type=text]::placeholder{text-transform:none;font-weight:500;color:#94a3b8}
form.tra button{border:0;border-radius:14px;padding:0 20px;font-size:14px;font-weight:800;background:linear-gradient(135deg,var(--nen),var(--nen2));color:#fff;cursor:pointer}
form.tra button:active{transform:scale(.97)}

.thongbao{background:#fff;border:2px dashed #fca5a5;color:#b91c1c;border-radius:16px;padding:16px;text-align:center;font-weight:700;margin-bottom:16px}

.card{background:#fff;border-radius:20px;box-shadow:0 12px 30px -18px rgba(15,23,42,.3);padding:18px;margin-bottom:14px}
.hoso{display:flex;align-items:center;gap:12px;margin-bottom:14px}
.hoso .ava{width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,#eef2ff,#e0e7ff);color:#3730a3;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:18px;flex:0 0 auto}
.hoso .ten{font-weight:800;font-size:16px;line-height:1.25}
.hoso .lop{font-size:12.5px;color:#94a3b8}

.vi{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:4px}
.o{background:#f8fafc;border-radius:14px;padding:12px 14px;text-align:center}
.o .nhan{font-size:11.5px;color:#64748b;font-weight:700}
.o .so{font-size:24px;font-weight:900;color:var(--nen2);margin-top:2px}
.o.lua .so{color:var(--lua)}

.lichsu{margin-top:14px}
.lichsu h3{font-size:13px;color:#64748b;font-weight:800;margin-bottom:8px}
.dong{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px 0;border-top:1px solid #f1f5f9}
.dong:first-child{border-top:0}
.dong .mo{font-size:13px;flex:1;min-width:0}
.dong .ngay{font-size:11px;color:#94a3b8}
.dong .so{font-weight:900;font-size:14px;flex:0 0 auto}
.dong .so.am{color:#dc2626}
.dong .so.duong{color:#16a34a}
.trong{text-align:center;color:#94a3b8;padding:20px 0;font-size:13px}

.doi-qua-trong{background:#fff;border-radius:20px;padding:40px 20px;text-align:center;color:#94a3b8}
.doi-qua-trong .ico{font-size:36px;margin-bottom:8px}

.gioi-thieu{background:#fff;border-radius:18px;padding:40px 20px;text-align:center;color:#94a3b8}
.foot{text-align:center;font-size:11px;color:#94a3b8;margin-top:16px}
</style>
</head>
<body>

<div class="hero">
  <a href="index.php" class="back-btn" aria-label="Quay lại">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
  </a>
  <div class="ico">📖</div>
  <h1>SỔ MỘC ĐIỆN TỬ</h1>
  <p>Đoàn Thiếu Nhi Thánh Thể · Giáo xứ Phú Trung</p>
</div>

<div class="wrap">

  <div class="tabs">
    <a href="<?= e_(urlVoi(['tab' => 'so-moc'])) ?>" class="<?= $tab === 'so-moc' ? 'on' : '' ?>">📖 Sổ Mộc</a>
    <a href="<?= e_(urlVoi(['tab' => 'doi-qua'])) ?>" class="<?= $tab === 'doi-qua' ? 'on' : '' ?>">🎁 Đổi quà</a>
  </div>

  <?php if ($tab === 'doi-qua'): ?>

    <div class="doi-qua-trong">
      <div class="ico">🚧</div>
      Tính năng <b>Đổi quà online</b> đang được cập nhật.<br>
      Vui lòng quay lại sau nhé!
    </div>

  <?php else: ?>

    <form class="tra" method="get">
      <input type="hidden" name="tab" value="so-moc">
      <input type="text" name="ma" value="<?= e_($ma) ?>" placeholder="Nhập mã thiếu nhi (VD: HS001)" maxlength="32" autofocus required>
      <button type="submit">Tra cứu</button>
    </form>

    <?php if ($khongCo): ?>
      <div class="thongbao">Không tìm thấy thiếu nhi với mã "<?= e_($ma) ?>".<br>Vui lòng kiểm tra lại mã số.</div>
    <?php endif; ?>

    <?php if ($ketQua): ?>

      <div class="card">
        <div class="hoso">
          <div class="ava"><?= e_(mb_strtoupper(mb_substr(trim($ketQua['full_name']), 0, 1, 'UTF-8'), 'UTF-8')) ?></div>
          <div>
            <div class="ten"><?= e_($ketQua['full_name']) ?></div>
            <div class="lop">Mã: <?= e_($ketQua['code']) ?><?= $ketQua['class_name'] ? ' · ' . e_($ketQua['class_name']) : '' ?></div>
          </div>
        </div>

        <div class="vi">
          <div class="o">
            <div class="nhan">VÍ MỘC</div>
            <div class="so"><?= (int) $ketQua['current_balance'] ?></div>
          </div>
          <div class="o lua">
            <div class="nhan">🔥 CHUỖI ĐI LỄ</div>
            <div class="so"><?= (int) $ketQua['current_streak'] ?></div>
          </div>
        </div>
        <div class="vi">
          <div class="o">
            <div class="nhan">TỔNG ĐÃ KIẾM</div>
            <div class="so"><?= (int) $ketQua['total_earned'] ?></div>
          </div>
          <div class="o lua">
            <div class="nhan">🏆 KỶ LỤC CHUỖI</div>
            <div class="so"><?= (int) $ketQua['longest_streak'] ?></div>
          </div>
        </div>

        <div class="lichsu">
          <h3>LỊCH SỬ GẦN ĐÂY</h3>
          <?php if (empty($ketQua['recent_transactions'])): ?>
            <div class="trong">Chưa có giao dịch nào.</div>
          <?php else: ?>
            <?php foreach ($ketQua['recent_transactions'] as $t): ?>
              <div class="dong">
                <div class="mo">
                  <?= e_($t['description']) ?>
                  <div class="ngay"><?= e_(date('H:i · d/m/Y', strtotime($t['created_at']))) ?></div>
                </div>
                <div class="so <?= $t['amount'] < 0 ? 'am' : 'duong' ?>"><?= e_(dinhDangGD($t)) ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    <?php elseif (!$khongCo): ?>
      <div class="gioi-thieu">Nhập mã thiếu nhi ở trên để xem Sổ Mộc nhé! 📖</div>
    <?php endif; ?>

  <?php endif; ?>

  <div class="foot">Trang tra cứu công khai · Chỉ để xem, không cần đăng nhập</div>
</div>

</body>
</html>
