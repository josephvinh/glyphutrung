<?php
/**
 * TRA CỨU SỔ MỘC — TRANG CÔNG KHAI (không đăng nhập)
 *
 * Em/phụ huynh nhập mã thiếu nhi để xem Sổ Mộc của mình: Ví (Mộc còn tiêu
 * được + tổng đã kiếm), Lửa chuỗi đi lễ, lịch sử giao dịch gần đây, và (tab
 * Đổi quà) tự đặt trước quà muốn đổi bằng chính số Mộc của mình.
 *
 * Hai thao tác THẬT SỰ ghi Mộc/tồn (đặt đơn / hủy đơn) không nằm ở trang
 * này mà nằm ở JS gọi sang `api/tracuu_order.php` (endpoint public riêng,
 * có mật mã + throttle bảo vệ) — xem `assets/js/tracuu.js`. Danh mục quà
 * 'còn bán' đọc thẳng từ bảng `gifts` ngay dưới đây (prepared/không tham
 * số, chỉ lộ đúng tên/giá/tồn/ảnh) để khỏi phải mở thêm một action đọc
 * riêng. Đơn 'chờ lấy' hiện có (nếu có) cũng được ĐỌC THẲNG ở đây (gọi
 * `rewards_pending_order()`, y hệt hàm mà action=pending của
 * `tracuu_order.php` dùng) rồi truyền sang cả 2 tab qua JSON — KHÔNG để
 * mỗi component Alpine tự gọi thêm `action=pending` lúc mount nữa (review
 * round 1: double-throttle — trước đây `doiQuaApp` + `soMocPending` đều
 * tự fetch pending khi init, khiến 1 lượt xem trang tốn 2-3 lượt throttle
 * IP, dễ khoá oan cả lớp dùng chung wifi giáo xứ). `rewards_expire_due()`
 * được gọi lazy trước khi đọc (§6.5) — đây là câu UPDATE duy nhất trang
 * này chạy, có chủ đích: dọn đúng những đơn đã quá hạn, hoàn toàn
 * idempotent, không phụ thuộc input người dùng.
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
require __DIR__ . '/api/_rewards.php'; // rewards_pending_order() + rewards_expire_due() — CHỈ ĐỌC dùng ở đây

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

/* ---------- Đơn 'chờ lấy' hiện có (nếu có) — dùng CHUNG cho cả 2 tab ----------
 * Tính MỘT LẦN ở đây (trong đúng lượt request đã throttle ở trên) rồi
 * truyền xuống cả doiQuaApp lẫn soMocPending qua JSON — 2 component Alpine
 * KHÔNG tự gọi action=pending lúc init nữa (fix round 1: double-throttle). */
$pendingOut = null;
if ($ketQua) {
    $emRow = db_one('SELECT id FROM students WHERE code = ?', [$ma]);
    if ($emRow) {
        rewards_expire_due($yearId); // dọn lazy đơn đã quá hạn trước khi đọc (§6.5)
        $pendingOut = rewards_pending_order((int) $emRow['id'], $yearId);
    }
}

/* ---------- Danh mục quà 'còn bán' cho tab Đổi quà ----------
 * Đọc trực tiếp (không qua API riêng) theo đúng gợi ý "tối giản" của brief:
 * chỉ SELECT, không tham số cần bind, ép kiểu int, chỉ lấy quà 'còn bán'
 * (KHÔNG lộ quà 'ẩn'), chỉ lộ đúng 4 trường cần cho giao diện chọn quà. */
$giftsOut = [];
if ($tab === 'doi-qua') {
    $quaList = db_all(
        "SELECT id, name, stamp_cost, stock, image_url
           FROM gifts WHERE status = 'còn bán' ORDER BY sort_order, name"
    );
    foreach ($quaList as $q) {
        $giftsOut[] = [
            'id'        => (int) $q['id'],
            'name'      => $q['name'],
            'stampCost' => (int) $q['stamp_cost'],
            'stock'     => (int) $q['stock'],
            'imageUrl'  => $q['image_url'],
        ];
    }
}

function e_($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

/** Nhúng an toàn một giá trị PHP làm tham số JS trong thuộc tính HTML
 *  (vd. x-data="app(<?= j_($x) ?>)") — json_encode() rồi escape luôn dấu
 *  nháy kép để không bị "vỡ" thuộc tính đang dùng dấu nháy kép bao ngoài. */
function j_($v): string { return e_(json_encode($v, JSON_UNESCAPED_UNICODE)); }

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

/**
 * Lời trong "lá thư": một câu KHEN/động viên (đổi theo chuỗi đi lễ của em)
 * và một câu NHẮC NHỞ chung. Xưng "con" cho ấm, gọi bằng tên (từ cuối họ tên).
 */
function loiLaThu(array $k): array {
    $streak  = (int) ($k['current_streak'] ?? 0);
    $longest = (int) ($k['longest_streak'] ?? 0);
    $bal     = (int) ($k['current_balance'] ?? 0);
    $parts   = preg_split('/\s+/', trim((string) ($k['full_name'] ?? '')));
    $goi     = (is_array($parts) && $parts && end($parts) !== '') ? end($parts) : 'con';

    // (1) KHEN theo chuỗi đi lễ — có nhánh AN ỦI khi chuỗi vừa đứt.
    if ($streak >= 8) {
        $khen = "🔥 Quá tuyệt, $goi ơi! Con đã đi lễ $streak tuần liền không nghỉ — Chúa và các Huynh Trưởng tự hào về con lắm!";
    } elseif ($streak >= 4) {
        $khen = "🔥 Giỏi lắm $goi! Chuỗi đi lễ $streak tuần liền của con đang cháy rất đẹp — ráng giữ lửa nhé!";
    } elseif ($streak >= 1) {
        $khen = "🌱 $goi đang có chuỗi $streak tuần đi lễ rồi đó — cố thêm chút nữa cho ngọn lửa lớn hơn nhé!";
    } elseif ($longest >= 3) {
        // Chuỗi đang là 0 nhưng từng giữ được khá lâu -> an ủi, mời quay lại.
        $khen = "🫂 Đừng buồn nếu chuỗi bị gián đoạn nhé $goi — con từng giữ được $longest tuần liền cơ mà! Chúa Nhật này quay lại đi lễ là ngọn lửa cháy lại ngay.";
    } else {
        $khen = "🕊️ Chúa Nhật này $goi nhớ tới nhà thờ dự lễ, để nhóm lại ngọn lửa yêu Chúa nhé!";
    }

    // (2) Nhánh riêng khi Ví nhiều Mộc -> gợi ý đổi quà.
    $themVi = ($bal >= 100)
        ? "🎁 Con đã dành dụm được $bal Mộc rồi — ghé mục Đổi quà chọn một phần thưởng xứng đáng cho mình nhé!"
        : "";

    // (3) NHẮC NHỞ xoay vòng mỗi lần xem cho đỡ nhàm.
    $dsNhac = [
        "Nhớ đi lễ Chúa Nhật đều đặn, chuyên cần học Giáo Lý và luôn sống ngoan, vâng lời ông bà cha mẹ con nhé! 💛",
        "Mỗi ngày cố gắng làm một việc hy sinh nhỏ và một việc tốt cho bạn bè con nhé! 💛",
        "Nhớ đọc kinh sáng tối và siêng năng rước lễ để ở gần Chúa Giêsu hơn nhé! 💛",
        "Đi học Giáo Lý đúng giờ, mặc đồng phục gọn gàng và lễ phép với mọi người con nhé! 💛",
    ];
    $nhac = $dsNhac[array_rand($dsNhac)];

    // Khẩu hiệu / Lời Chúa theo văn phong TNTT — cũng xoay vòng.
    $dsCham = [
        "Cầu nguyện · Rước lễ · Hy sinh · Làm tông đồ",
        "“Hãy để trẻ nhỏ đến với Thầy” (Mc 10,14)",
        "“Các con là muối cho đời, là ánh sáng cho trần gian” (x. Mt 5,13-14)",
        "Sống ngày Thánh Thể: Chúa ở cùng con mọi ngày!",
    ];
    $cham = $dsCham[array_rand($dsCham)];

    return ['khen' => $khen, 'themVi' => $themVi, 'nhac' => $nhac, 'cham' => $cham];
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

[x-cloak]{display:none !important}

/* ============================================================
   LÁ THƯ SỔ MỘC — BẤM ĐỂ MỞ (không tự chạy).
   Đầu tiên hiện phong bì đóng có con dấu logo Đoàn trên nắp; các em
   CHẠM vào để mở: nắp lật lên, lá thư bay ra, con dấu logo đóng ở
   MÉP thư. Máy bật "giảm chuyển động" thì mở thẳng, không hoạt hình.
   ============================================================ */
.thu-canh{position:relative;perspective:1400px;padding-top:6px}

/* Con dấu tròn = logo Đoàn (dùng cho cả nắp phong bì lẫn mép thư) */
.con-dau{border-radius:50%;background:radial-gradient(circle at 50% 40%,#fff,#ffe9ec 72%,#ffd6dc);
 border:2px solid #c0182f;box-shadow:0 6px 14px -6px rgba(192,24,47,.6), inset 0 0 0 3px rgba(192,24,47,.12);
 display:flex;align-items:center;justify-content:center;overflow:hidden}
.con-dau img{width:80%;height:80%;object-fit:contain}

/* --- Phong bì ĐÓNG = nút bấm để mở --- */
.bao-thu{display:block;width:100%;max-width:340px;margin:6px auto 0;position:relative;aspect-ratio:7/5;
 border:0;background:transparent;cursor:pointer;padding:0;font-family:inherit;
 filter:drop-shadow(0 16px 30px rgba(180,120,10,.35));transition:transform .15s}
.bao-thu:active{transform:scale(.98)}
.bao-thu:focus-visible{outline:3px solid var(--nen2);outline-offset:4px;border-radius:16px}
.bao-thu .bt-than{position:absolute;inset:0;border-radius:14px;background:linear-gradient(135deg,#fde9b6,#f2bd50)}
.bao-thu .bt-tui{position:absolute;inset:0;border-radius:14px;background:linear-gradient(135deg,#f7d488,#e9b23c);
 clip-path:polygon(0 0,50% 46%,100% 0,100% 100%,0 100%)}
.bao-thu .bt-nap{position:absolute;left:0;top:0;width:100%;height:56%;background:linear-gradient(135deg,#f3c150,#d99a2b);
 clip-path:polygon(0 0,100% 0,50% 92%);transform-origin:top center;backface-visibility:hidden;z-index:3}
.bao-thu .bt-dau{position:absolute;left:50%;top:40%;transform:translate(-50%,-50%);z-index:4;width:72px;height:72px}
.bt-hint{position:absolute;left:0;right:0;bottom:9px;text-align:center;color:#7a4d06;font-weight:800;font-size:13px;z-index:2}
.bt-hint span{display:block;font-weight:600;font-size:11.5px;opacity:.85;margin-top:1px}
.bt-caption{text-align:center;font-size:11.5px;color:#94a3b8;margin:8px 0 2px}

/* --- Sau khi MỞ (ẩn cho tới khi bấm) --- */
.sau-mo{display:none}
.thu-canh.mo .sau-mo{display:block}
.thu-canh.mo .bao-thu{animation:baoBay .65s ease-in forwards;pointer-events:none}
@keyframes baoBay{35%{opacity:1}100%{opacity:0;transform:translateY(28px) scale(.9)}}
.thu-canh.mo .bt-nap{animation:napMo .5s ease-out forwards}
@keyframes napMo{to{transform:rotateX(172deg)}}

/* Tia lấp lánh */
.tia{position:absolute;inset:0;pointer-events:none;z-index:0;overflow:visible}
.thu-canh.mo .tia span{position:absolute;font-size:18px;opacity:0;animation:tiaBay 2.2s ease-out forwards}
@keyframes tiaBay{0%{opacity:0;transform:translateY(24px) scale(.4)}25%{opacity:1}100%{opacity:0;transform:translateY(-92px) scale(1.1) rotate(28deg)}}

/* --- Lá thư (to & đẹp hơn: giấy có vân, viền, dấu mờ logo) --- */
.la-thu{position:relative;z-index:1;transform-origin:center top;
 background:repeating-linear-gradient(0deg,transparent 0 30px,rgba(21,52,126,.045) 30px 31px),linear-gradient(180deg,#fffdf7,#fff7ea);
 border:1px solid #efe3c4;border-radius:20px;padding:24px 22px 22px;
 box-shadow:0 26px 52px -24px rgba(15,23,42,.42);
 animation:thuBay .9s cubic-bezier(.2,.85,.25,1.1) both}
.la-thu::before{content:"";position:absolute;left:22px;right:22px;top:13px;height:1px;background:linear-gradient(90deg,transparent,#e6d6a8,transparent)}
@keyframes thuBay{0%{opacity:0;transform:translateY(70px) scale(.82) rotate(-2deg)}55%{opacity:1}100%{opacity:1;transform:translateY(0) scale(1) rotate(0)}}
.la-thu .nen-logo{position:absolute;inset:0;background-position:center 62%;background-repeat:no-repeat;background-size:220px;opacity:.045;pointer-events:none;border-radius:20px}
.la-thu > *{position:relative}

.thu-tieude{text-align:center;font-size:14px;color:#64748b;font-weight:700;margin:4px 0 14px;padding:0 4px;line-height:1.5}
.thu-tieude b{color:var(--nen2)}
.la-thu .hoso .ten{font-size:17px}
.la-thu .o .so{font-size:26px}

/* Con dấu logo đóng ở MÉP THƯ (góc dưới phải) — không đè lên tên */
.dau-sap{position:absolute;right:14px;bottom:12px;width:62px;height:62px;z-index:3;transform:rotate(-8deg);
 animation:sapDong .5s .95s cubic-bezier(.3,1.4,.5,1) both}
@keyframes sapDong{0%{opacity:0;transform:scale(2) rotate(-26deg)}70%{opacity:1;transform:scale(.9) rotate(-4deg)}100%{opacity:1;transform:scale(1) rotate(-8deg)}}

/* Lời khen + động viên + nhắc nhở */
.thu-loi{margin-top:16px;background:linear-gradient(135deg,#fff8e7,#fff2d2);border:1px solid #f4e0a3;border-radius:14px;padding:14px 16px}
.thu-loi .khen{font-size:14px;font-weight:800;color:#8a5e08;line-height:1.55}
.thu-loi .khen + .khen{margin-top:8px}
.thu-loi .nhac{font-size:13px;color:#7c5a12;margin-top:8px;line-height:1.6;font-style:italic}
/* Khẩu hiệu / Lời Chúa */
.thu-cham{text-align:center;font-size:12.5px;color:var(--nen2);font-weight:700;font-style:italic;margin-top:16px;line-height:1.5;padding:0 8px}
/* Chữ ký (chừa chỗ cho con dấu ở mép) */
.thu-ky{margin-top:8px;text-align:right;font-size:13px;color:#475569;font-style:italic;line-height:1.5;padding-right:64px}
.thu-ky b{color:var(--nen2);font-style:normal}

@media (min-width:560px){ .la-thu{padding:30px 30px 26px} .bao-thu{max-width:380px} }

@media (prefers-reduced-motion: reduce){
 .tia{display:none}
 .thu-canh.mo .bao-thu{animation:none;display:none}
 .la-thu{animation:none;opacity:1;transform:none}
 .dau-sap{animation:none;opacity:1;transform:rotate(-8deg)}
}

/* ---------- Tab Đổi quà: lưới quà + giỏ ---------- */
.qua-luoi{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:24px}
.qua-the{background:#fff;border-radius:16px;padding:12px;box-shadow:0 8px 20px -14px rgba(15,23,42,.3);display:flex;flex-direction:column}
.qua-the.het{opacity:.55}
.qua-anh{width:100%;aspect-ratio:1/1;border-radius:12px;object-fit:cover;background:#f1f5f9;margin-bottom:8px}
.qua-anh-trong{width:100%;aspect-ratio:1/1;border-radius:12px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:28px;margin-bottom:8px;color:#cbd5e1}
.qua-ten{font-weight:800;font-size:13.5px;line-height:1.25;margin-bottom:2px}
.qua-gia{font-size:13px;font-weight:900;color:var(--nen2)}
.qua-ton{font-size:11px;color:#94a3b8;margin-bottom:8px}
.qua-buoc{display:flex;align-items:center;justify-content:space-between;gap:6px;margin-top:auto}
.qua-buoc button{width:30px;height:30px;border-radius:10px;border:1px solid #e2e8f0;background:#f8fafc;font-weight:900;font-size:16px;color:var(--nen2);cursor:pointer}
.qua-buoc button:disabled{opacity:.35;cursor:default}
.qua-buoc .sl{font-weight:800;font-size:14px;min-width:20px;text-align:center}

.gio-thanh{position:sticky;bottom:12px;background:#fff;box-shadow:0 12px 30px -14px rgba(15,23,42,.4);border-radius:18px;padding:12px 14px;display:flex;align-items:center;gap:12px;margin-top:4px}
.gio-thanh .tt{flex:1;font-size:12.5px;color:#475569}
.gio-thanh .tt b{font-size:16px;color:var(--nen2)}
.gio-thanh .qua-han{color:#dc2626;font-weight:700;font-size:11px;margin-top:2px}
.gio-thanh button{border:0;border-radius:14px;padding:12px 18px;font-weight:800;font-size:13.5px;background:linear-gradient(135deg,var(--nen),var(--nen2));color:#fff;cursor:pointer}
.gio-thanh button:disabled{opacity:.4;cursor:default}

.lop-mo{position:fixed;inset:0;background:rgba(15,23,42,.55);display:flex;align-items:flex-end;justify-content:center;z-index:30;padding:0}
.hop{background:#fff;border-radius:24px 24px 0 0;padding:20px;width:100%;max-width:560px;max-height:85vh;overflow:auto}
@media(min-width:640px){.lop-mo{align-items:center}.hop{border-radius:24px}}
.hop h3{font-size:16px;font-weight:900;margin-bottom:6px}
.hop input[type=password],.hop input[type=text]{width:100%;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;font-size:15px;margin-bottom:10px;font-family:inherit}
.hop .loi{color:#dc2626;font-size:12.5px;font-weight:700;margin-bottom:8px}
.hop .hang-nut{display:flex;gap:10px;margin-top:6px}
.hop .hang-nut button{flex:1;border:0;border-radius:12px;padding:12px;font-weight:800;font-size:14px;cursor:pointer}
.btn-huy{background:#f1f5f9;color:#64748b}
.btn-xn{background:linear-gradient(135deg,var(--nen),var(--nen2));color:#fff}
.btn-xn:disabled{opacity:.5;cursor:default}

.don-cho{background:#fff;border:2px solid #fde68a;border-radius:20px;padding:16px;margin-bottom:14px}
.don-cho h3{font-size:13px;color:#92650a;font-weight:900;margin-bottom:8px}
.don-cho .mon{display:flex;justify-content:space-between;font-size:13px;padding:4px 0;color:#334155}
.don-cho .tong{display:flex;justify-content:space-between;font-weight:900;font-size:14px;border-top:1px dashed #fde68a;padding-top:8px;margin-top:6px}
.don-cho .han{font-size:11.5px;color:#92650a;margin-top:6px}
.don-cho .nut-huy{margin-top:12px;width:100%;border:1px solid #fca5a5;color:#b91c1c;background:#fff;border-radius:12px;padding:10px;font-weight:800;font-size:13px;cursor:pointer}

.thanh-cong{background:#fff;border:2px solid #86efac;border-radius:20px;padding:24px 20px;text-align:center}
.thanh-cong .ico{font-size:34px;margin-bottom:8px}
.thanh-cong h3{font-weight:900;font-size:16px;color:#166534;margin-bottom:6px}
.thanh-cong .ma{font-size:20px;font-weight:900;color:var(--nen2);margin:8px 0}
.thanh-cong a{display:inline-block;margin-top:10px;padding:10px 18px;border-radius:12px;background:var(--nen2);color:#fff;font-weight:800;font-size:13px;text-decoration:none}

.canh-bao-nho{background:#fef2f2;border:1px solid #fca5a5;color:#b91c1c;border-radius:14px;padding:12px;font-size:12.5px;margin-bottom:12px;line-height:1.5}
.canh-bao-nho a{color:#b91c1c;font-weight:800;text-decoration:underline}
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

    <?php if (!$ketQua): ?>

      <form class="tra" method="get">
        <input type="hidden" name="tab" value="doi-qua">
        <input type="text" name="ma" value="<?= e_($ma) ?>" placeholder="Nhập mã thiếu nhi (VD: HS001)" maxlength="32" autofocus required>
        <button type="submit">Tra cứu</button>
      </form>

      <?php if ($khongCo): ?>
        <div class="thongbao">Không tìm thấy thiếu nhi với mã "<?= e_($ma) ?>".<br>Vui lòng kiểm tra lại mã số.</div>
      <?php else: ?>
        <div class="doi-qua-trong">
          <div class="ico">🎁</div>
          Nhập mã thiếu nhi ở trên để xem quà và đặt đổi nhé!
        </div>
      <?php endif; ?>

    <?php else: ?>

      <div class="card" style="margin-bottom:12px">
        <div class="hoso" style="margin-bottom:0">
          <div class="ava"><?= e_(mb_strtoupper(mb_substr(trim($ketQua['full_name']), 0, 1, 'UTF-8'), 'UTF-8')) ?></div>
          <div>
            <div class="ten"><?= e_($ketQua['full_name']) ?></div>
            <div class="lop">Mã: <?= e_($ketQua['code']) ?> · Ví: <?= (int) $ketQua['current_balance'] ?> Mộc</div>
          </div>
        </div>
      </div>

      <div x-data="doiQuaApp(<?= j_($ma) ?>, <?= (int) $ketQua['current_balance'] ?>, <?= j_($giftsOut) ?>, <?= j_($pendingOut) ?>)" x-cloak>

        <!-- Vừa đặt xong -->
        <template x-if="success">
          <div class="thanh-cong">
            <div class="ico">✅</div>
            <h3>Đặt quà thành công!</h3>
            <p style="color:#64748b;font-size:12.5px">Mã đơn <b>#<span x-text="success.orderId"></span></b></p>
            <div class="ma"><span x-text="success.total"></span> Mộc</div>
            <p style="font-size:12.5px;color:#64748b">Hạn lấy: <span x-text="dinhDangNgay(success.expiresAt)"></span></p>
            <p style="font-size:12px;color:#64748b;margin-top:8px">
              Nhớ kỹ mật mã đổi quà — Thủ Thư sẽ hỏi lại khi em tới lấy quà tại quầy.
            </p>
            <a href="<?= e_(urlVoi(['tab' => 'so-moc'])) ?>">Xem đơn ở tab Sổ Mộc</a>
          </div>
        </template>

        <!-- Đã có sẵn 1 đơn chờ lấy -> không cho đặt thêm (mỗi em 1 đơn) -->
        <template x-if="!success && pending">
          <div class="canh-bao-nho">
            🔒 Em đang có một đơn <b>chờ lấy</b> — mỗi em chỉ được đặt 1 đơn cùng lúc.
            Xem chi tiết hoặc hủy đơn ở tab <a href="<?= e_(urlVoi(['tab' => 'so-moc'])) ?>">Sổ Mộc</a> để đặt đơn mới.
          </div>
        </template>

        <!-- Lưới quà + giỏ -->
        <template x-if="!success && !pending">
          <div>
            <template x-if="gifts.length === 0">
              <div class="doi-qua-trong"><div class="ico">🎁</div>Hiện chưa có quà nào để đổi.</div>
            </template>

            <template x-if="gifts.length > 0">
              <div>
                <div class="qua-luoi">
                  <template x-for="g in gifts" :key="g.id">
                    <div class="qua-the" :class="{ het: g.stock <= 0 }">
                      <template x-if="urlAnhOk(g.imageUrl)"><img class="qua-anh" :src="g.imageUrl" :alt="g.name" loading="lazy"></template>
                      <template x-if="!urlAnhOk(g.imageUrl)"><div class="qua-anh-trong">🎁</div></template>
                      <div class="qua-ten" x-text="g.name"></div>
                      <div class="qua-gia" x-text="g.stampCost + ' Mộc'"></div>
                      <div class="qua-ton" x-text="g.stock > 0 ? ('Còn ' + g.stock) : 'Hết hàng'"></div>
                      <div class="qua-buoc">
                        <button type="button" @click="giam(g)" :disabled="qty(g.id) <= 0" aria-label="Bớt 1">−</button>
                        <span class="sl" x-text="qty(g.id)"></span>
                        <button type="button" @click="tang(g)" :disabled="g.stock <= 0 || qty(g.id) >= g.stock" aria-label="Thêm 1">+</button>
                      </div>
                    </div>
                  </template>
                </div>

                <div class="gio-thanh" x-show="soMon > 0" style="display:none">
                  <div class="tt">
                    Giỏ: <span x-text="soMon"></span> món · <b x-text="tongMoc"></b> / <span x-text="available"></span> Mộc
                    <template x-if="vuotQua"><div class="qua-han">Vượt số Mộc khả dụng</div></template>
                  </div>
                  <button type="button" @click="openPlace()" :disabled="!coTheDat">Đặt quà</button>
                </div>
              </div>
            </template>

            <!-- Hộp nhập mật mã đổi quà (2 lần) -->
            <div class="lop-mo" x-show="showPlace" style="display:none" @keydown.escape.window="closePlace()">
              <div class="hop" @click.outside="closePlace()">
                <h3>Đặt mật mã đổi quà</h3>
                <p style="font-size:12.5px;color:#64748b;margin-bottom:10px">
                  Mật mã này Thủ Thư sẽ hỏi lại khi em tới lấy quà — hãy nhớ kỹ, đừng cho ai khác biết.
                </p>
                <input type="password" x-model="pw1" placeholder="Đặt mật mã đổi quà" autocomplete="new-password">
                <input type="password" x-model="pw2" placeholder="Nhập lại mật mã" autocomplete="new-password"
                       @keydown.enter="submitPlace()">
                <div class="loi" x-show="error" x-text="error" style="display:none"></div>
                <div class="hang-nut">
                  <button type="button" class="btn-huy" @click="closePlace()">Hủy</button>
                  <button type="button" class="btn-xn" @click="submitPlace()" :disabled="busy">
                    <span x-text="busy ? 'Đang đặt...' : 'Xác nhận đặt'"></span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </template>

      </div>

    <?php endif; ?>

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

      <?php
        $loi = loiLaThu($ketQua);
        $p = preg_split('/\s+/', trim((string) $ketQua['full_name']));
        $goiTen = (is_array($p) && $p && end($p) !== '') ? end($p) : 'bạn';
      ?>
      <div class="thu-canh" id="thuCanh">

        <!-- PHONG BÌ ĐÓNG — bấm để mở (nút thật cho cả bàn phím) -->
        <button type="button" class="bao-thu" id="moThuBtn"
                aria-label="Chạm để mở lá thư Sổ Mộc gửi <?= e_($ketQua['full_name']) ?>">
          <span class="bt-than" aria-hidden="true"></span>
          <span class="bt-tui" aria-hidden="true"></span>
          <span class="bt-nap" aria-hidden="true"></span>
          <span class="bt-dau con-dau" aria-hidden="true"><img src="assets/img/logo.png" alt="" width="72" height="72"></span>
          <span class="bt-hint">💌 <?= e_($goiTen) ?> ơi, em có một lá thư!<span>Chạm để mở</span></span>
        </button>
        <div class="bt-caption">Con dấu của Đoàn Thiếu Nhi Thánh Thể · Phú Trung</div>

        <!-- SAU KHI MỞ -->
        <div class="sau-mo">
        <!-- Tia lấp lánh (trang trí) -->
        <div class="tia" aria-hidden="true">
          <span style="left:14%;top:44px;animation-delay:.5s">✨</span>
          <span style="left:32%;top:20px;animation-delay:.9s">🍃</span>
          <span style="left:62%;top:30px;animation-delay:.7s">✨</span>
          <span style="left:82%;top:52px;animation-delay:1.1s">⭐</span>
          <span style="left:48%;top:14px;animation-delay:1.3s">✨</span>
        </div>

        <div class="card la-thu">
        <div class="nen-logo" style="background-image:url('assets/img/logo.png')" aria-hidden="true"></div>
        <div class="dau-sap con-dau" aria-hidden="true"><img src="assets/img/logo.png" alt=""></div>
        <div class="thu-tieude">💌 Một lá thư từ Sổ Mộc gửi <b><?= e_($ketQua['full_name']) ?></b></div>
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

        <div class="thu-loi">
          <div class="khen"><?= e_($loi['khen']) ?></div>
          <?php if ($loi['themVi'] !== ''): ?>
          <div class="khen"><?= e_($loi['themVi']) ?></div>
          <?php endif; ?>
          <div class="nhac"><?= e_($loi['nhac']) ?></div>
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
        <div class="thu-cham"><?= e_($loi['cham']) ?></div>
        <div class="thu-ky">Thân mến,<br><b>Ban Huynh Trưởng · Đoàn TNTT Phú Trung</b> ✝️</div>
        </div><!-- /.card.la-thu -->
        </div><!-- /.sau-mo -->
      </div><!-- /.thu-canh -->

      <div x-data="soMocPending(<?= j_($ma) ?>, <?= j_($pendingOut) ?>)" x-cloak>
        <template x-if="pending">
          <div class="don-cho">
            <h3>🎁 ĐƠN ĐANG CHỜ LẤY <span x-text="'#' + pending.orderId"></span></h3>
            <template x-for="it in (pending.items || [])" :key="it.giftId">
              <div class="mon">
                <span x-text="it.name + ' × ' + it.qty"></span>
                <span x-text="it.lineCost + ' Mộc'"></span>
              </div>
            </template>
            <div class="tong"><span>Tổng cộng</span><span x-text="pending.total + ' Mộc'"></span></div>
            <div class="han">Hạn lấy: <span x-text="dinhDangNgay(pending.expiresAt)"></span> — quá hạn đơn sẽ tự hủy và hoàn lại Mộc.</div>
            <button type="button" class="nut-huy" @click="openCancel()">Hủy đơn</button>
          </div>
        </template>

        <!-- Hộp nhập mật mã để hủy -->
        <div class="lop-mo" x-show="showCancel" style="display:none" @keydown.escape.window="closeCancel()">
          <div class="hop" @click.outside="closeCancel()">
            <h3>Hủy đơn đặt quà</h3>
            <p style="font-size:12.5px;color:#64748b;margin-bottom:10px">Nhập lại mật mã đổi quà em đã đặt để xác nhận hủy.</p>
            <input type="password" x-model="password" placeholder="Mật mã đổi quà" autocomplete="current-password"
                   @keydown.enter="submitCancel()">
            <div class="loi" x-show="error" x-text="error" style="display:none"></div>
            <div class="hang-nut">
              <button type="button" class="btn-huy" @click="closeCancel()">Đóng</button>
              <button type="button" class="btn-xn" @click="submitCancel()" :disabled="busy">
                <span x-text="busy ? 'Đang hủy...' : 'Xác nhận hủy'"></span>
              </button>
            </div>
          </div>
        </div>
      </div>

    <?php elseif (!$khongCo): ?>
      <div class="gioi-thieu">Nhập mã thiếu nhi ở trên để xem Sổ Mộc nhé! 📖</div>
    <?php endif; ?>

  <?php endif; ?>

  <div class="foot">Trang tra cứu công khai · Chỉ để xem, không cần đăng nhập</div>
</div>

<?php /* Script THƯỜNG (không defer), TRƯỚC alpine.js (defer) -> kịp đăng ký
         soMocPending/doiQuaApp trước khi Alpine quét DOM (mẫu views/layout_login.php). */ ?>
<script src="assets/js/tracuu.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/tracuu.js') ?: 0; ?>"></script>
<script defer src="assets/js/vendor/alpine.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/vendor/alpine.js') ?: 0; ?>"></script>

<?php /* Bấm phong bì -> mở lá thư (thêm class .mo để chạy hoạt hình). */ ?>
<script>
(function(){
  var b = document.getElementById('moThuBtn'), c = document.getElementById('thuCanh');
  if (b && c) b.addEventListener('click', function(){ c.classList.add('mo'); });
})();
</script>

</body>
</html>
