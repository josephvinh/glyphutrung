<?php
/**
 * TRA CỨU ĐIỂM — ĐIỂM SỐ · ĐIỂM DANH · SỔ LIÊN LẠC (công khai)
 *
 * Trang riêng, tách khỏi Sổ Mộc (somoc.php). Em/phụ huynh nhập
 * mã thiếu nhi + NGÀY SINH (mmddyyyy, làm mật mã). Đúng mã + đúng ngày sinh
 * mới thấy 3 tab:
 *   1. Điểm số        — các cột điểm theo học kỳ
 *   2. Điểm danh      — sổ điểm danh chi tiết từng buổi
 *   3. Sổ liên lạc    — CHỈ hiện phiếu các anh chị đã lập và gửi
 *
 * Gửi bằng POST (ngày sinh không nằm trên URL / log / lịch sử trình duyệt),
 * chặn cache, noindex, rate-limit theo IP dùng chung với tra cứu Sổ Mộc.
 * Một lượt gửi = một lượt throttle; 3 tab chuyển bằng JS phía trình duyệt.
 * Logic thuần: api/_tracuu.php.
 */

header('X-Robots-Tag: noindex, nofollow', true);
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

require __DIR__ . '/../config/db.php';
require __DIR__ . '/api/_tracuu.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

function e_($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function ngay_($ymd) { return $ymd ? date('d/m/Y', strtotime($ymd)) : ''; }
function diem_($v) { return $v === null ? '–' : rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',') ; }

$year = db_one('SELECT id, name FROM school_years WHERE is_current = 1 LIMIT 1');
if (!$year) { http_response_code(503); echo 'Chưa mở niên khoá.'; exit; }
$yearId = (int) $year['id'];

$ma = ''; $loi = null; $em = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $ma  = mb_substr(trim((string) ($_POST['ma'] ?? '')), 0, 32, 'UTF-8');
    $dob = (string) ($_POST['ns'] ?? '');
    if (tracuu_throttled()) {
        http_response_code(429);
        $loi = 'Bạn tra cứu quá nhiều lần. Vui lòng đợi ' . TRACUU_CUA_SO_PHUT . ' phút rồi thử lại.';
    } else {
        tracuu_attempt_record();
        $em = tracuu_auth($ma, $dob);
        // Lỗi GỘP: không cho biết là sai mã hay sai ngày sinh
        if (!$em) $loi = 'Mã thiếu nhi hoặc ngày sinh chưa đúng. Vui lòng kiểm tra lại.';
    }
}

$diem = $dd = $lienLac = null; $lop = null;
if ($em) {
    $diem    = tracuu_scores($em['id'], $yearId);
    $dd      = tracuu_attendance($em['id'], $yearId);
    $lienLac = tracuu_reports($em['id'], $yearId);
    $lop     = tracuu_class_name($em['id'], $yearId);
}

$MARK = ['P' => ['Có mặt', 'p'], 'L' => ['Đi trễ', 'l'], 'E' => ['Vắng có phép', 'e'], 'A' => ['Vắng', 'a']];
?><!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#b81528">
<link rel="manifest" href="manifest.json">
<link rel="apple-touch-icon" href="assets/img/icon-180.png">
<title>Tra Cứu Điểm · Thiếu Nhi Thánh Thể</title>
<link rel="stylesheet" href="assets/css/font.css">
<style>
:root{--nen:#e11d36;--nen2:#b81528;--ink:#0f172a;--mut:#64748b}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Be Vietnam Pro",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:var(--ink);
 background:linear-gradient(160deg,#eef3ff,#f8fafc 40%);min-height:100vh;padding:0 0 48px}
.wrap{max-width:680px;margin:0 auto;padding:0 16px}
.hero{background:linear-gradient(135deg,var(--nen),var(--nen2));color:#fff;text-align:center;
 padding:34px 16px 60px;border-radius:0 0 28px 28px;position:relative}
.hero .ico{font-size:38px;line-height:1}
.hero h1{font-size:24px;font-weight:900;margin:6px 0 2px}
.hero p{opacity:.85;font-size:13px}
.back-btn{position:absolute;top:16px;left:16px;width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.15);
 color:#fff;display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:20px}
.card{background:#fff;border-radius:20px;box-shadow:0 12px 30px -18px rgba(15,23,42,.3);padding:18px;margin-bottom:14px}
.login{margin-top:-30px;position:relative}
.login label{display:block;font-size:12.5px;font-weight:800;color:var(--mut);margin:0 0 6px}
.login input{width:100%;border:1px solid #e2e8f0;border-radius:14px;padding:13px 16px;font-size:16px;font-weight:700;
 background:#fff;color:var(--ink);margin-bottom:12px;font-family:inherit}
.login input[name=ma]{text-transform:uppercase}
.login input::placeholder{text-transform:none;font-weight:500;color:#94a3b8}
.login button{width:100%;border:0;border-radius:14px;padding:14px;font-size:15px;font-weight:800;color:#fff;cursor:pointer;
 background:linear-gradient(135deg,var(--nen),var(--nen2));font-family:inherit}
.login .goi-y{font-size:12px;color:#94a3b8;margin-top:10px;text-align:center}
.loi{background:#fff;border:2px dashed #fca5a5;color:#b91c1c;border-radius:16px;padding:14px;text-align:center;font-weight:700;margin-bottom:14px;font-size:14px}
.hoso{display:flex;align-items:center;gap:12px}
.ava{width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,#eef2ff,#e0e7ff);color:#3730a3;display:flex;
 align-items:center;justify-content:center;font-weight:900;font-size:18px;flex:0 0 auto}
.ten{font-weight:800;font-size:16px;line-height:1.25}.lop{font-size:12.5px;color:#94a3b8}
.doi{margin-left:auto;font-size:12.5px;font-weight:800;color:var(--nen2);text-decoration:none;white-space:nowrap}
.tabs{display:flex;background:#fff;border-radius:999px;padding:4px;box-shadow:0 8px 20px -12px rgba(15,23,42,.4);margin:0 0 14px;
 position:sticky;top:8px;z-index:5}
.tabs button{flex:1;border:0;background:none;white-space:nowrap;padding:10px 6px;border-radius:999px;font-size:13px;font-weight:800;
 color:var(--mut);cursor:pointer;font-family:inherit}
.tabs button[aria-selected=true]{background:var(--nen2);color:#fff}
[role=tabpanel][hidden]{display:none}
h2{font-size:14px;font-weight:800;margin-bottom:10px}
.trong{text-align:center;color:#94a3b8;padding:24px 8px;font-size:13.5px;line-height:1.5}
/* điểm số */
.tk{font-size:12.5px;color:var(--mut);margin-bottom:10px;display:flex;justify-content:space-between;align-items:baseline}
.bang{width:100%;border-collapse:collapse;font-size:14px}
.bang th{font-size:11px;color:var(--mut);font-weight:800;padding:6px 4px;text-align:center;border-bottom:2px solid #f1f5f9}
.bang th small{display:block;font-weight:600;color:#94a3b8}
.bang td{padding:12px 4px;text-align:center;font-weight:800;font-variant-numeric:tabular-nums}
.bang td.tb{color:var(--nen2);background:#fff1f2;border-radius:10px}
.bang td.chua{color:#cbd5e1;font-weight:600}
.bang .hs{font-size:10px;color:#94a3b8;font-weight:600}
/* điểm danh */
.tong{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:12px}
.tong div{background:#f8fafc;border-radius:14px;padding:10px 4px;text-align:center}
.tong b{display:block;font-size:20px;font-weight:900}.tong span{font-size:10.5px;color:var(--mut);font-weight:700}
.tong .p b{color:#16a34a}.tong .l b{color:#d97706}.tong .e b{color:#2563eb}.tong .a b{color:#dc2626}
.ty-le{display:flex;align-items:center;gap:10px;margin-bottom:12px;font-size:12.5px;font-weight:700;color:var(--mut)}
.ty-le .thanh{flex:1;height:10px;background:#f1f5f9;border-radius:99px;overflow:hidden}
.ty-le .thanh i{display:block;height:100%;background:linear-gradient(90deg,#22c55e,#16a34a);border-radius:99px}
.ty-le strong{color:var(--ink);font-size:15px}
.so-dd{border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;margin-bottom:12px;background:#fffdf7}
.so-dd .thang{background:#fef3c7;color:#92400e;font-size:12px;font-weight:800;padding:7px 12px;letter-spacing:.3px;
 display:flex;justify-content:space-between}
.hang{display:flex;align-items:center;gap:10px;padding:9px 12px;border-top:1px solid #f1e9d0;font-size:13px}
.hang .n{width:38px;flex:0 0 auto;text-align:center;line-height:1.1}
.hang .n b{display:block;font-size:17px;font-weight:900}.hang .n span{font-size:10px;color:#94a3b8;font-weight:700}
.hang .ct{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600}
.dau{flex:0 0 auto;min-width:96px;text-align:center;border-radius:99px;padding:4px 10px;font-size:11.5px;font-weight:800}
.dau.p{background:#dcfce7;color:#15803d}.dau.l{background:#fef3c7;color:#b45309}
.dau.e{background:#dbeafe;color:#1d4ed8}.dau.a{background:#fee2e2;color:#b91c1c}
/* sổ liên lạc */
.phieu{border:1px solid #e2e8f0;border-radius:16px;padding:14px;margin-bottom:12px}
.phieu h3{font-size:14px;font-weight:900;color:var(--nen2)}.phieu .kh{font-size:11.5px;color:#94a3b8;margin-bottom:10px}
.luoi{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:10px}
.luoi div{background:#f8fafc;border-radius:12px;padding:8px 4px;text-align:center}
.luoi span{display:block;font-size:10.5px;color:var(--mut);font-weight:700}.luoi b{font-size:15px;font-weight:900;text-transform:capitalize}
.nx{background:#fffbeb;border-left:4px solid #f6b100;border-radius:10px;padding:10px 12px;font-size:13.5px;line-height:1.55;white-space:pre-wrap}
.foot{text-align:center;font-size:11px;color:#94a3b8;margin-top:16px}
</style>
</head>
<body>
<div class="hero">
  <a class="back-btn" href="index.php" aria-label="Về trang chủ">‹</a>
  <div class="ico">📘</div>
  <h1>Tra Cứu Điểm</h1>
  <p>Điểm số · Điểm danh · Sổ liên lạc</p>
</div>

<div class="wrap">
<?php if (!$em): ?>
  <form class="card login" method="post" action="tracuu.php" autocomplete="off">
    <?php if ($loi): ?><div class="loi" role="alert"><?php echo e_($loi); ?></div><?php endif; ?>
    <label for="ma">Mã thiếu nhi</label>
    <input id="ma" type="text" name="ma" value="<?php echo e_($ma); ?>" placeholder="VD: HS001" maxlength="32" required autocapitalize="characters">
    <label for="ns">Mật mã = tháng ngày năm sinh</label>
    <input id="ns" type="text" name="ns" inputmode="numeric" placeholder="mmddyyyy — VD: 03152014" maxlength="10" required aria-describedby="ns-goiy">
    <button type="submit">Xem kết quả</button>
    <p class="goi-y" id="ns-goiy">⚠️ Nhập theo thứ tự <b>THÁNG – NGÀY – NĂM</b> (mmddyyyy). Ví dụ em sinh ngày 15 tháng 3 năm 2014 thì nhập <b>03152014</b>.</p>
  </form>
<?php else: ?>
  <div class="card hoso">
    <div class="ava"><?php echo e_(mb_strtoupper(mb_substr(trim($em['full_name']), -1, 1, 'UTF-8'), 'UTF-8')); ?></div>
    <div>
      <div class="ten"><?php echo e_(trim(($em['holy_name'] ? $em['holy_name'] . ' ' : '') . $em['full_name'])); ?></div>
      <div class="lop"><?php echo e_($em['code']); ?><?php echo $lop ? ' · Lớp ' . e_($lop) : ''; ?> · <?php echo e_($year['name']); ?></div>
    </div>
    <a class="doi" href="tracuu.php">Thoát</a>
  </div>

  <div class="tabs" role="tablist" aria-label="Chọn mục xem">
    <button role="tab" id="t-diem" aria-controls="p-diem" aria-selected="true">📝 Điểm số</button>
    <button role="tab" id="t-dd" aria-controls="p-dd" aria-selected="false">✅ Điểm danh</button>
    <button role="tab" id="t-ll" aria-controls="p-ll" aria-selected="false">📬 Sổ liên lạc</button>
  </div>

  <!-- TAB ĐIỂM SỐ -->
  <section class="card" role="tabpanel" id="p-diem" aria-labelledby="t-diem">
    <?php if (!$diem['terms'] || !$diem['types']): ?>
      <div class="trong">Chưa có học kỳ hoặc cột điểm nào.</div>
    <?php else: foreach ($diem['terms'] as $t): ?>
      <div style="margin-bottom:16px">
        <div class="tk"><h2 style="margin:0"><?php echo e_($t['name']); ?></h2><span><?php echo e_(ngay_($t['from'])); ?> – <?php echo e_(ngay_($t['to'])); ?></span></div>
        <table class="bang">
          <thead><tr>
            <?php foreach ($diem['types'] as $ty): ?>
              <th><?php echo e_($ty['label']); ?><small>hệ số <?php echo (int) $ty['weight']; ?></small></th>
            <?php endforeach; ?>
            <th>Trung bình</th>
          </tr></thead>
          <tbody><tr>
            <?php foreach ($t['cols'] as $c): ?>
              <td class="<?php echo $c['value'] === null ? 'chua' : ''; ?>"><?php echo e_(diem_($c['value'])); ?></td>
            <?php endforeach; ?>
            <td class="tb"><?php echo e_(diem_($t['avg'])); ?></td>
          </tr></tbody>
        </table>
      </div>
    <?php endforeach; ?>
      <p class="hs" style="font-size:11.5px;color:#94a3b8">Điểm trung bình tính theo hệ số, chỉ gồm các cột đã có điểm. Dấu – là chưa có điểm.</p>
    <?php endif; ?>
  </section>

  <!-- TAB ĐIỂM DANH -->
  <section class="card" role="tabpanel" id="p-dd" aria-labelledby="t-dd" hidden>
    <?php $s = $dd['summary']; if ($s['total'] === 0): ?>
      <div class="trong">Chưa có buổi điểm danh nào trong niên khoá này.</div>
    <?php else: ?>
      <div class="tong">
        <div class="p"><b><?php echo $s['present']; ?></b><span>Có mặt</span></div>
        <div class="l"><b><?php echo $s['late']; ?></b><span>Đi trễ</span></div>
        <div class="e"><b><?php echo $s['excused']; ?></b><span>Có phép</span></div>
        <div class="a"><b><?php echo $s['unexcused']; ?></b><span>Vắng</span></div>
      </div>
      <div class="ty-le"><span>Chuyên cần</span><div class="thanh"><i style="width:<?php echo (int) $s['rate']; ?>%"></i></div><strong><?php echo (int) $s['rate']; ?>%</strong><span>/ <?php echo $s['total']; ?> buổi</span></div>
      <?php
        $thu = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
        $nhom = [];
        foreach ($dd['sessions'] as $x) $nhom[substr($x['date'], 0, 7)][] = $x;
        krsort($nhom); // tháng mới nhất lên đầu
        foreach ($nhom as $ym => $ds):
          $ds = array_reverse($ds);
      ?>
        <div class="so-dd">
          <div class="thang"><span>Tháng <?php echo e_(substr($ym, 5, 2) . '/' . substr($ym, 0, 4)); ?></span><span><?php echo count($ds); ?> buổi</span></div>
          <?php foreach ($ds as $x): $ts = strtotime($x['date']); [$nhanM, $cls] = $MARK[$x['mark']]; ?>
            <div class="hang">
              <div class="n"><b><?php echo date('d', $ts); ?></b><span><?php echo $thu[(int) date('w', $ts)]; ?></span></div>
              <div class="ct"><?php echo e_($x['program']); ?></div>
              <div class="dau <?php echo $cls; ?>"><?php echo e_($nhanM); ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <p style="font-size:11.5px;color:#94a3b8">Chỉ tính các buổi lớp đã điểm danh. Chuyên cần = (có mặt + đi trễ) / tổng số buổi.</p>
    <?php endif; ?>
  </section>

  <!-- TAB SỔ LIÊN LẠC -->
  <section class="card" role="tabpanel" id="p-ll" aria-labelledby="t-ll" hidden>
    <?php if (!$lienLac): ?>
      <div class="trong">📭 Chưa có phiếu liên lạc nào.<br>Phiếu sẽ hiện ở đây khi các anh chị lập và gửi cho gia đình.</div>
    <?php else: foreach ($lienLac as $r): ?>
      <div class="phieu">
        <h3>Phiếu liên lạc · <?php echo e_($r['term']); ?></h3>
        <div class="kh"><?php echo e_(ngay_($r['from'])); ?> – <?php echo e_(ngay_($r['to'])); ?></div>
        <div class="luoi">
          <div><span>Điểm học tập</span><b><?php echo e_(diem_($r['score'])); ?></b></div>
          <div><span>Xếp loại</span><b><?php echo e_($r['rank']); ?></b></div>
          <div><span>Đạo đức</span><b><?php echo e_($r['conduct']); ?></b></div>
          <div><span>Có mặt / trễ</span><b><?php echo $r['present']; ?> / <?php echo $r['late']; ?></b></div>
          <div><span>Vắng (phép / không)</span><b><?php echo $r['excused']; ?> / <?php echo $r['unexcused']; ?></b></div>
          <div><span>Chuyên cần</span><b><?php echo $r['rate']; ?>%</b></div>
        </div>
        <?php if (trim((string) $r['remark']) !== ''): ?><div class="nx"><?php echo e_($r['remark']); ?></div><?php endif; ?>
      </div>
    <?php endforeach; endif; ?>
  </section>
<?php endif; ?>
  <div class="foot">Gia Đình Giáo Lý Phú Trung · Đoàn Thiếu Nhi Thánh Thể</div>
</div>

<?php if ($em): ?>
<script>
(function () {
  var tabs = document.querySelectorAll('[role=tab]');
  function chon(t) {
    tabs.forEach(function (b) {
      var on = b === t;
      b.setAttribute('aria-selected', on ? 'true' : 'false');
      document.getElementById(b.getAttribute('aria-controls')).hidden = !on;
    });
  }
  tabs.forEach(function (b) { b.addEventListener('click', function () { chon(b); }); });
})();
</script>
<?php endif; ?>
</body>
</html>
