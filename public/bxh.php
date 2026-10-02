<?php
/**
 * BẢNG THI ĐUA — TRANG CÔNG KHAI (không đăng nhập, CHỈ XEM)
 *
 * Tính tự động từ dữ liệu thật: điểm danh + điểm số. Không có thao tác ghi.
 * Bảo vệ: chỉ đọc, prepared statements, ép kiểu tham số, chỉ lộ tên+lớp+điểm,
 * chặn Google lập chỉ mục (noindex).
 */

header('X-Robots-Tag: noindex, nofollow', true);
header('Referrer-Policy: no-referrer');

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/thi_dua.php';

/* ---------- Niên khoá + học kỳ hiện tại ---------- */
$year = db_one("SELECT * FROM school_years WHERE is_current = 1 LIMIT 1");
if (!$year) {
    http_response_code(503);
    echo 'Chưa mở niên khoá.';
    exit;
}
$yearId = (int) $year['id'];
$today  = date('Y-m-d');
$term = db_one("SELECT * FROM terms WHERE year_id = ? AND start_date <= ? AND end_date >= ? ORDER BY sort_order LIMIT 1",
               [$yearId, $today, $today])
     ?: db_one("SELECT * FROM terms WHERE year_id = ? ORDER BY sort_order DESC LIMIT 1", [$yearId]);
$termId    = $term ? (int) $term['id'] : 0;
$termStart = $term['start_date'] ?? ($year['start_date'] ?? '2000-01-01');
$termEnd   = $term['end_date']   ?? ($year['end_date']   ?? '2100-01-01');

/* ---------- Lọc (ép kiểu an toàn) ---------- */
$period = (($_GET['period'] ?? 'ky') === 'tuan') ? 'tuan' : 'ky';
$type   = (($_GET['type']   ?? 'ca_nhan') === 'lop') ? 'lop' : 'ca_nhan';
$khoi   = isset($_GET['khoi']) ? (int) $_GET['khoi'] : 0;
$lop    = isset($_GET['lop'])  ? (int) $_GET['lop']  : 0;

/* ---------- Tuần ISO hiện tại (Thứ 2 -> Chủ nhật) ---------- */
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd   = date('Y-m-d', strtotime('sunday this week'));

/* ---------- Danh sách khối/lớp cho bộ lọc ---------- */
$blocks  = db_all("SELECT id, name FROM blocks ORDER BY sort_order, name");
$classes = db_all("SELECT id, name, block_id FROM classes ORDER BY sort_order, name");

/* ---------- Học sinh trong phạm vi ---------- */
$dk = '';
$p  = [$yearId];
if ($lop > 0)        { $dk = ' AND e.class_id = ?';  $p[] = $lop; }
elseif ($khoi > 0)   { $dk = ' AND c.block_id = ?';  $p[] = $khoi; }

$students = db_all(
    "SELECT s.id, s.holy_name, s.full_name,
            c.id AS class_id, c.name AS class_name, b.id AS block_id, b.name AS block_name
       FROM enrollments e
       JOIN students s  ON s.id = e.student_id
       LEFT JOIN classes c ON c.id = e.class_id
       LEFT JOIN blocks  b ON b.id = c.block_id
      WHERE e.year_id = ? AND e.status = 'đang sinh hoạt'{$dk}
      ORDER BY c.name, s.full_name",
    $p
);

/* ---------- Điểm danh trong kỳ (bao gồm cả tuần này) ---------- */
$attRows = db_all(
    "SELECT student_id, program_id, session_date, status FROM attendances
      WHERE year_id = ? AND session_date BETWEEN ? AND ?",
    [$yearId, $termStart, $termEnd]
);
// Lấy thêm đơn xin phép đã duyệt
$leaveRows = db_all(
    "SELECT student_id, program_id, session_date FROM leave_requests
      WHERE year_id = ? AND status = 'đã duyệt' AND session_date BETWEEN ? AND ?",
    [$yearId, $termStart, $termEnd]
);

// gộp theo học sinh: đếm cho KỲ + cho TUẦN
$att = []; // id => ['tCM','tDT','tCP','wCM','wDT','wCP','dix']
// Vắng = KHÔNG có dòng điểm danh.
// Nên "tổng số buổi" của kỳ = số buổi ĐÃ DIỄN RA = số (program_id|ngày) khác nhau có ghi nhận.
$buoiKy = []; // 'program_id|date' => 1
foreach ($attRows as $a) {
    $sid = (int) $a['student_id'];
    if (!isset($att[$sid])) $att[$sid] = ['tCM'=>0,'tDT'=>0,'tCP'=>0,'wCM'=>0,'wDT'=>0,'wCP'=>0,'dix'=>[]];
    $st = $a['status'];
    $key = $a['program_id'] . '|' . $a['session_date'];
    $buoiKy[$key] = 1;
    $att[$sid]['dix'][$key] = 1; // Đánh dấu em này đã có kết quả điểm danh
    
    if ($st === 'có mặt')      $att[$sid]['tCM']++;
    elseif ($st === 'đi trễ')  $att[$sid]['tDT']++;
    $trongTuan = ($a['session_date'] >= $weekStart && $a['session_date'] <= $weekEnd);
    if ($trongTuan) {
        if ($st === 'có mặt')     $att[$sid]['wCM']++;
        elseif ($st === 'đi trễ') $att[$sid]['wDT']++;
    }
}

// Xử lý đơn xin phép
foreach ($leaveRows as $l) {
    $sid = (int) $l['student_id'];
    if (!isset($att[$sid])) $att[$sid] = ['tCM'=>0,'tDT'=>0,'tCP'=>0,'wCM'=>0,'wDT'=>0,'wCP'=>0,'dix'=>[]];
    $key = $l['program_id'] . '|' . $l['session_date'];
    // Chỉ tính có phép nếu buổi đó THẬT SỰ CÓ DIỄN RA và em này chưa bị điểm danh đè lên
    if (isset($buoiKy[$key]) && !isset($att[$sid]['dix'][$key])) {
        $att[$sid]['tCP']++;
        $trongTuan = ($l['session_date'] >= $weekStart && $l['session_date'] <= $weekEnd);
        if ($trongTuan) {
            $att[$sid]['wCP']++;
        }
    }
}
$soBuoiKy = count($buoiKy); // tổng số buổi đã diễn ra trong kỳ

/* ---------- Điểm số trong kỳ (kèm trọng số) ----------
   KHÔNG JOIN score_types trong SQL: cột code/type_code có thể khác collation
   giữa các máy chủ -> lỗi "Illegal mix of collations". Ghép trọng số ở PHP. */
$weightOf = [];
foreach (db_all("SELECT code, weight FROM score_types") as $st) {
    $weightOf[$st['code']] = (float) $st['weight'];
}
$scoreRows = $termId ? db_all(
    "SELECT student_id, type_code, value FROM scores WHERE term_id = ?",
    [$termId]
) : [];
$scoreOf = []; // id => list ['value','weight']
foreach ($scoreRows as $r) {
    $scoreOf[(int) $r['student_id']][] = [
        'value'  => (float) $r['value'],
        'weight' => $weightOf[$r['type_code']] ?? 1.0,
    ];
}

/* ---------- Tính điểm mỗi em ---------- */
$rowsEm = [];
foreach ($students as $s) {
    $sid = (int) $s['id'];
    $a = $att[$sid] ?? ['tCM'=>0,'tDT'=>0,'tCP'=>0,'wCM'=>0,'wDT'=>0,'wCP'=>0];
    if ($period === 'tuan') {
        $diem = (float) td_diem_tuan($a['wCM'], $a['wDT'], $a['wCP']);
        $detail = "CM: {$a['wCM']} · ĐT: {$a['wDT']} · CP: {$a['wCP']}";
    } else {
        // Mẫu số là TỔNG số buổi đã diễn ra (không phải số dòng của em)
        $tyLe    = td_ty_le_co_mat($a['tCM'], $a['tDT'], $a['tCP'], $soBuoiKy);
        $hocTap  = td_hoc_tap_100($scoreOf[$sid] ?? []);
        $diem    = td_diem_ky($tyLe, $hocTap);
        $ht10 = rtrim(rtrim(number_format($hocTap / 10, 1), '0'), '.'); // Thang 10
        $detail = "Chuyên cần: {$tyLe}% · Học tập: {$ht10}";
    }
    $rowsEm[] = [
        'id'         => $sid,
        'ten'        => trim(($s['holy_name'] ? $s['holy_name'] . ' ' : '') . $s['full_name']),
        'class_id'   => (int) $s['class_id'],
        'class_name' => $s['class_name'] ?? '',
        'diem'       => $diem,
        'detail'     => $detail,
    ];
}

/* ---------- Cá nhân hay Lớp ---------- */
if ($type === 'lop') {
    $agg = []; // class_id => ['ten','tong','n']
    foreach ($rowsEm as $r) {
        $cid = $r['class_id'];
        if (!$cid) continue;
        if (!isset($agg[$cid])) $agg[$cid] = ['ten' => $r['class_name'], 'tong' => 0.0, 'n' => 0];
        $agg[$cid]['tong'] += $r['diem'];
        $agg[$cid]['n']++;
    }
    $rows = [];
    foreach ($agg as $cid => $g) {
        $rows[] = ['id' => $cid, 'ten' => $g['ten'], 'class_name' => '', 'diem' => $g['n'] ? round($g['tong'] / $g['n'], 1) : 0.0, 'detail' => 'Sĩ số: ' . $g['n'] . ' em'];
    }
} else {
    $rows = $rowsEm;
}

$xh = td_xep_hang($rows, 'diem');
// Giới hạn hiển thị Top 20 em (hoặc Top 20 lớp) để web nhẹ và tập trung vào nhóm dẫn đầu
$xh = array_slice($xh, 0, 20);
$top = array_slice($xh, 0, 3);

/* ---------- Nhãn động ---------- */
$tenChampion = ($type === 'lop') ? 'Lớp xuất sắc' : ($period === 'tuan' ? 'Em của tuần' : 'Quán quân');
$donVi       = ($type === 'lop') ? 'lớp' : 'em';
$tieuDeKy    = ($period === 'tuan')
    ? ('Tuần này (' . date('d/m', strtotime($weekStart)) . '–' . date('d/m', strtotime($weekEnd)) . ')')
    : (($term['name'] ?? 'Học kỳ') . ' · ' . ($year['name'] ?? ''));

function e_($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function chuDau($ten) { $t = trim($ten); return $t === '' ? '?' : mb_strtoupper(mb_substr($t, 0, 1, 'UTF-8'), 'UTF-8'); }

// giữ lại lọc khác khi đổi 1 tham số (dùng cho link)
function urlVoi(array $ghi): string {
    $q = array_merge(['period'=>$_GET['period']??'ky','type'=>$_GET['type']??'ca_nhan','khoi'=>$_GET['khoi']??'','lop'=>$_GET['lop']??''], $ghi);
    $q = array_filter($q, fn($v) => $v !== '' && $v !== null);
    return 'bxh.php?' . http_build_query($q);
}
?><!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Bảng Thi Đua · Thiếu Nhi Thánh Thể</title>
<link rel="stylesheet" href="assets/css/font.css">
<style>
:root{--vang:#f6b100;--vang2:#ffd54a;--bac:#9aa7b4;--dong:#c8813e;--nen:#0b1e4d;--nen2:#15347e;}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Be Vietnam Pro",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
 color:#0f172a;background:linear-gradient(160deg,#eef3ff,#f8fafc 40%);min-height:100vh;padding:0 0 48px}
.wrap{max-width:820px;margin:0 auto;padding:0 16px}
.hero{background:linear-gradient(135deg,var(--nen),var(--nen2));color:#fff;text-align:center;
 padding:34px 16px 68px;border-radius:0 0 28px 28px;position:relative;overflow:hidden}
.hero .cup{font-size:40px;line-height:1}
.hero h1{font-size:26px;font-weight:900;letter-spacing:.5px;margin:6px 0 2px}
.hero p{opacity:.85;font-size:13px}
.hero .ky{display:inline-block;margin-top:10px;background:rgba(255,255,255,.16);
 padding:5px 14px;border-radius:999px;font-size:13px;font-weight:700}
.spark{position:absolute;top:0;left:0;right:0;bottom:0;pointer-events:none}
.back-btn{position:absolute;top:16px;left:16px;width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.15);color:#fff;display:flex;align-items:center;justify-content:center;text-decoration:none;z-index:10;backdrop-filter:blur(4px)}
.back-btn:active{background:rgba(255,255,255,.3);transform:scale(0.95)}

.filters{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin:-40px auto 8px;position:relative;z-index:2}
.seg{display:inline-flex;background:#fff;border:1px solid #e2e8f0;border-radius:999px;padding:3px;box-shadow:0 8px 20px -12px rgba(15,23,42,.4)}
.seg a{padding:7px 14px;border-radius:999px;font-size:13px;font-weight:700;color:#64748b;text-decoration:none}
.seg a.on{background:var(--nen2);color:#fff}
select{border:1px solid #e2e8f0;border-radius:999px;padding:8px 12px;font-size:13px;font-weight:600;background:#fff;color:#0f172a}

.podium{display:grid;grid-template-columns:1fr 1.25fr 1fr;align-items:end;gap:10px;margin:18px 0 8px}
.pod{background:#fff;border-radius:18px;padding:16px 8px 14px;text-align:center;box-shadow:0 12px 30px -16px rgba(15,23,42,.35);border:2px solid transparent}
.pod .ava{width:60px;height:60px;border-radius:50%;margin:0 auto 8px;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:900;color:#fff}
.pod .medal{font-size:26px;margin-bottom:2px}
.pod .ten{font-weight:800;font-size:14px;line-height:1.2}
.pod .lop{font-size:11px;color:#94a3b8}
.pod .diem{font-size:20px;font-weight:900;margin-top:4px}
.pod.p1{transform:translateY(-8px);border-color:var(--vang)} .pod.p1 .ava{background:linear-gradient(135deg,var(--vang),var(--vang2));width:74px;height:74px;font-size:30px} .pod.p1 .diem{color:#b7860b}
.pod.p2{border-color:var(--bac)} .pod.p2 .ava{background:linear-gradient(135deg,#b7c2cf,#8b98a6)}
.pod.p3{border-color:var(--dong)} .pod.p3 .ava{background:linear-gradient(135deg,#d99a5b,#b5702f)}
.champ{background:#fff;border:2px dashed var(--vang);border-radius:16px;padding:10px 14px;text-align:center;margin:6px 0 14px;font-weight:700;color:#92650a}
.champ b{color:#0f172a}

.list{background:#fff;border-radius:18px;box-shadow:0 12px 30px -18px rgba(15,23,42,.3);overflow:hidden}
.row{display:flex;align-items:center;gap:12px;padding:12px 14px;border-top:1px solid #f1f5f9}
.row:first-child{border-top:0}
.row .rk{width:30px;text-align:center;font-weight:900;color:#94a3b8;flex:0 0 auto}
.row .ava2{width:34px;height:34px;border-radius:50%;background:#eef2ff;color:#3730a3;display:flex;align-items:center;justify-content:center;font-weight:800;flex:0 0 auto}
.row .main{flex:1;min-width:0}
.row .main .t{font-weight:700;font-size:14px;line-height:1.25}
.row .main .s{font-size:11px;color:#94a3b8}
.row .bar{flex:0 0 56px;height:8px;background:#f1f5f9;border-radius:999px;overflow:hidden}
@media (min-width: 640px) { /* sm breakpoint */
    .row .bar{flex-basis:120px}
}
.row .bar>i{display:block;height:100%;background:linear-gradient(90deg,#6366f1,#22c55e);border-radius:999px}
.row .dg{flex:0 0 auto;font-weight:900;font-size:15px;width:52px;text-align:right}
.medalrow{font-size:16px;width:22px;text-align:center;flex:0 0 auto}

.ct{max-width:820px;margin:16px auto 0;padding:14px 16px;font-size:12px;color:#64748b;background:#fff;border-radius:14px;border:1px solid #eef2f7}
.ct b{color:#0f172a}
.empty{background:#fff;border-radius:18px;padding:40px 20px;text-align:center;color:#94a3b8}
.foot{text-align:center;font-size:11px;color:#94a3b8;margin-top:16px}
@keyframes pop{0%{transform:scale(.6);opacity:0}100%{transform:scale(1);opacity:1}}
.pod{animation:pop .4s ease both}.pod.p1{animation-delay:.15s}.pod.p3{animation-delay:.1s}
</style>
</head>
<body>

<div class="hero">
  <a href="index.php" class="back-btn" aria-label="Quay lại">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
  </a>
  <div class="spark" id="spark"></div>
  <div class="cup">🏆</div>
  <h1>BẢNG THI ĐUA</h1>
  <p>Đoàn Thiếu Nhi Thánh Thể · Giáo xứ Phú Trung</p>
  <div class="ky"><?= e_($tieuDeKy) ?></div>
</div>

<div class="wrap">

  <!-- Bộ lọc -->
  <div class="filters">
    <span class="seg">
      <a href="<?= e_(urlVoi(['period'=>'tuan'])) ?>" class="<?= $period==='tuan'?'on':'' ?>">Tuần</a>
      <a href="<?= e_(urlVoi(['period'=>'ky'])) ?>" class="<?= $period==='ky'?'on':'' ?>">Học kỳ</a>
    </span>
    <span class="seg">
      <a href="<?= e_(urlVoi(['type'=>'ca_nhan'])) ?>" class="<?= $type==='ca_nhan'?'on':'' ?>">Cá nhân</a>
      <a href="<?= e_(urlVoi(['type'=>'lop'])) ?>" class="<?= $type==='lop'?'on':'' ?>">Lớp</a>
    </span>
    <?php if ($type !== 'lop'): ?>
    <form method="get" style="display:inline-flex;gap:8px" id="f">
      <input type="hidden" name="period" value="<?= e_($period) ?>">
      <input type="hidden" name="type" value="<?= e_($type) ?>">
      <select name="khoi" onchange="document.getElementById('f').submit()">
        <option value="">Tất cả khối</option>
        <?php foreach ($blocks as $b): ?>
          <option value="<?= (int)$b['id'] ?>" <?= $khoi===(int)$b['id']?'selected':'' ?>><?= e_($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="lop" onchange="document.getElementById('f').submit()">
        <option value="">Tất cả lớp</option>
        <?php foreach ($classes as $c): if ($khoi>0 && (int)$c['block_id']!==$khoi) continue; ?>
          <option value="<?= (int)$c['id'] ?>" <?= $lop===(int)$c['id']?'selected':'' ?>><?= e_($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php endif; ?>
  </div>

  <?php if (empty($xh)): ?>
    <div class="empty">Chưa có dữ liệu để xếp hạng.<br>Hãy điểm danh / nhập điểm rồi quay lại nhé!</div>
  <?php else: ?>

    <?php
      // Bục vinh danh: thứ tự trưng bày 2 - 1 - 3
      $od = [];
      if (isset($top[1])) $od[] = ['p'=>'p2','d'=>$top[1]];
      if (isset($top[0])) $od[] = ['p'=>'p1','d'=>$top[0]];
      if (isset($top[2])) $od[] = ['p'=>'p3','d'=>$top[2]];
      $mej = ['vang'=>'🥇','bac'=>'🥈','dong'=>'🥉'];
    ?>
    <div class="podium">
      <?php $medalBuc=['p1'=>'🥇','p2'=>'🥈','p3'=>'🥉']; foreach ($od as $o): $d=$o['d']; ?>
        <div class="pod <?= $o['p'] ?>">
          <div class="medal"><?= $medalBuc[$o['p']] ?? '' ?></div>
          <div class="ava"><?= e_(chuDau($d['ten'])) ?></div>
          <div class="ten"><?= e_($d['ten']) ?></div>
          <?php if ($type!=='lop'): ?><div class="lop"><?= e_($d['class_name']) ?></div><?php endif; ?>
          <div class="diem"><?= rtrim(rtrim(number_format($d['diem'],1),'0'),'.') ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if (isset($top[0])): ?>
      <div class="champ"><?= e_($tenChampion) ?>: <b><?= e_($top[0]['ten']) ?></b>
        <?= $type!=='lop' && $top[0]['class_name'] ? '('.e_($top[0]['class_name']).')' : '' ?> — <?= rtrim(rtrim(number_format($top[0]['diem'],1),'0'),'.') ?> điểm 🎉</div>
    <?php endif; ?>

    <?php $maxDiem = max(1, (float)($xh[0]['diem'] ?? 1)); ?>
    <div class="list">
      <?php foreach ($xh as $d): ?>
        <div class="row">
          <div class="rk">#<?= (int)$d['rank'] ?></div>
          <div class="medalrow"><?= $mej[$d['medal']] ?? '' ?></div>
          <div class="ava2"><?= e_(chuDau($d['ten'])) ?></div>
          <div class="main">
            <div class="t"><?= e_($d['ten']) ?></div>
            <div class="s">
              <?php if ($type!=='lop'): ?><?= e_($d['class_name']) ?> · <?php endif; ?>
              <?= e_($d['detail'] ?? '') ?>
            </div>
          </div>
          <div class="bar"><i style="width:<?= max(3,min(100,round($d['diem']/$maxDiem*100))) ?>%"></i></div>
          <div class="dg"><?= rtrim(rtrim(number_format($d['diem'],1),'0'),'.') ?></div>
        </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>

  <div class="ct">
    <b>Cách tính điểm minh bạch (tự động 100%):</b><br>
    <?php if ($period==='tuan'): ?>
      Điểm <b>Tuần</b> theo chuyên cần: Có mặt +10 · Đi trễ +6 · Vắng có phép +3 · Vắng 0.
    <?php else: ?>
      Điểm <b>Học kỳ</b> (thang 100) = <b>60% Chuyên cần</b> + <b>40% Học tập</b>.<br>
      <i>* Chuyên cần: Có mặt tính 100%, Đi trễ tính 60%, Vắng có phép tính 30%.</i><br>
      <i>* Điểm của lớp: Lấy trung bình cộng điểm các em trong lớp.</i>
    <?php endif; ?>
    <br><br>
    <i>Bảng xếp hạng chỉ hiển thị <b>Top 20</b> dẫn đầu.</i>
  </div>

  <div class="foot">Cập nhật theo dữ liệu thật lúc <?= date('H:i · d/m/Y') ?> · Trang chỉ để xem</div>
</div>

<script>
// Confetti nhẹ chúc mừng (chỉ trang trí)
(function(){
  var host=document.getElementById('spark'); if(!host) return;
  var emo=['✨','⭐','🎉','🌟'];
  for(var i=0;i<18;i++){
    var s=document.createElement('div');
    s.textContent=emo[i%emo.length];
    s.style.cssText='position:absolute;top:'+(Math.random()*70)+'%;left:'+(Math.random()*100)+'%;font-size:'+(10+Math.random()*14)+'px;opacity:'+(0.25+Math.random()*0.5);
    host.appendChild(s);
  }
})();
</script>
</body>
</html>
