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

/* Trang này KHÔNG nạp _bootstrap.php (theo mẫu bxh.php) nên phải TỰ đặt múi
   giờ VN — nếu không, date('Y-m-d') chạy theo giờ server (UTC), khiến "hôm
   nay" của lịch/băng Mộc lệch một ngày vào sáng sớm giờ Việt Nam. */
date_default_timezone_set('Asia/Ho_Chi_Minh');

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
$mocNgay = [];   // ['Y-m-d' => số Mộc đóng ngày đó] — cho LỊCH ĐÓNG MỘC
$mocKhac = 0;    // Σ Mộc 'manual_adjust' (thưởng khác, không lên lịch)
if ($ketQua) {
    $emRow = db_one('SELECT id FROM students WHERE code = ?', [$ma]);
    if ($emRow) {
        rewards_expire_due($yearId); // dọn lazy đơn đã quá hạn trước khi đọc (§6.5)
        $pendingOut = rewards_pending_order((int) $emRow['id'], $yearId);

        /* Mộc đóng theo NGÀY (cả niên khoá) + Mộc "thưởng khác" (manual_adjust).
           Logic thuần đã tách sang _tracuu.php để test được; đọc MỘT lần ở đây,
           JS dựng lịch từng tháng phía trình duyệt nên lật tháng KHÔNG tốn thêm
           lượt tra cứu. */
        $mocNgay = tracuu_moc_by_day((int) $emRow['id'], $yearId);
        $mocKhac = tracuu_moc_thuong_khac((int) $emRow['id'], $yearId);
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

// loiLaThu() đã chuyển sang public/api/_tracuu.php thành tracuu_loi_la_thu()
// (logic thuần, test được bằng PHPUnit) — trang chỉ gọi lại ở phần hiển thị.
?><!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Tra Cứu Sổ Mộc · Thiếu Nhi Thánh Thể</title>
<link rel="stylesheet" href="assets/css/font.css">
<style>
:root{--vang:#f6b100;--vang2:#ffd54a;--lua:#f97316;--nen:#e11d36;--nen2:#b81528;}
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
.tabs a{flex:1;text-align:center;white-space:nowrap;padding:9px 8px;border-radius:999px;font-size:13.5px;font-weight:800;color:#64748b;text-decoration:none}
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
   CUỐN SỔ MỘC — BẤM ĐỂ MỞ (không tự chạy).
   Đầu tiên hiện BÌA SỔ gỗ (gáy lò xo + logo Đoàn); các em CHẠM để mở:
   bìa lật lên, lộ TRANG GIẤY kẻ ô bên trong. Trên trang có "con Mộc"
   (con dấu) đóng theo chuỗi đi lễ để thấy rõ "Mộc" của mình.
   Máy bật "giảm chuyển động" thì mở thẳng, không hoạt hình.
   ============================================================ */
.so-canh{position:relative;perspective:1800px;max-width:390px;margin:8px auto 0;
 --bia1:#7d5729;--bia2:#452e15;--spine:#e11d36}   /* mặc định: bìa gỗ, gáy đỏ */
/* Màu BÌA theo NGÀNH */
.so-canh.bia-hong{--bia1:#f06fa8;--bia2:#b0246a;--spine:#db2777}   /* Khai Tâm — hồng */
.so-canh.bia-la  {--bia1:#34c98a;--bia2:#127a52;--spine:#059669}   /* Rước Lễ — xanh lá */
.so-canh.bia-xanh{--bia1:#3b62c9;--bia2:#1b2a6b;--spine:#1e40af}   /* Thêm Sức — xanh đậm */
.so-canh.bia-vang{--bia1:#f6c85f;--bia2:#d98f22;--spine:#c98a1a}   /* Bao Đồng — vàng */

/* ===== KHUNG MỘT MÀN HÌNH — sổ lấp đầy bề ngang & vừa chiều cao 1 khung ===== */
.doi-ma{text-align:center;margin-bottom:6px}
.doi-ma a{display:inline-block;font-size:12.5px;font-weight:700;color:#fff;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.35);padding:5px 14px;border-radius:999px;text-decoration:none}
.man-hinh{display:flex;align-items:flex-start;justify-content:center}
.fit{transform-origin:top center;will-change:transform;width:100%}
body:not(.khung-don) .man-hinh{display:block}

body.khung-don{height:100dvh;overflow:hidden;padding:0;display:flex;flex-direction:column}
/* Hero MẢNH để nhường chỗ cho sổ (chống ăn hết chiều cao -> khỏi phải thu nhỏ) */
body.khung-don .hero{flex:0 0 auto;padding:8px 16px 12px;border-radius:0 0 18px 18px}
body.khung-don .hero .ico{display:none}
body.khung-don .hero h1{font-size:16px;margin:0;letter-spacing:.3px}
body.khung-don .hero p{display:none}
body.khung-don .hero .back-btn{width:32px;height:32px;top:8px;left:12px}
/* wrap: KHÔNG clip (kẻo cắt hàng tab thụt âm); man-hình mới là chỗ ẩn tràn */
body.khung-don .wrap{flex:1 1 auto;min-height:0;display:flex;flex-direction:column;overflow:visible}
body.khung-don .tabs{flex:0 0 auto;margin:10px auto 8px}   /* bỏ tuck âm -> không bị hero che/cắt */
body.khung-don .doi-ma{flex:0 0 auto}
body.khung-don .foot{display:none}
body.khung-don .man-hinh{flex:1 1 auto;min-height:0;overflow:hidden}
/* Sổ lấp đầy bề ngang khung (không còn thẻ nhỏ lọt giữa) */
body.khung-don .so-canh{max-width:520px;width:100%;margin:0 auto}
/* Nén chiều cao để vừa khung, đỡ phải scale */
body.khung-don .trang-so{padding:16px 14px 12px}
body.khung-don .trang-tieu{margin:0 0 8px}
body.khung-don .hoso{margin-bottom:10px}
body.khung-don .trang-tab{margin:10px 0 10px}
body.khung-don .homnay{padding:7px 10px;margin-bottom:8px}
body.khung-don .tong-lon{margin-bottom:8px}
body.khung-don .tong-lon .num{font-size:26px}
body.khung-don .lich{padding:10px}
body.khung-don .lich-dau{margin-bottom:8px}
body.khung-don .lich-tuan{margin-bottom:4px}
body.khung-don .lich-tong{margin-top:8px}
body.khung-don .lich-kien{margin-top:8px;padding:7px 9px;font-size:11px}
body.khung-don .moc-khac{margin-top:8px;padding:7px 9px;font-size:11px}

/* Con dấu tròn = logo Đoàn */
.con-dau{border-radius:50%;background:radial-gradient(circle at 50% 40%,#fff,#ffe9ec 72%,#ffd6dc);
 border:2px solid #e11d36;box-shadow:0 6px 14px -6px rgba(192,24,47,.6), inset 0 0 0 3px rgba(192,24,47,.12);
 display:flex;align-items:center;justify-content:center;overflow:hidden}
.con-dau img{width:80%;height:80%;object-fit:contain}

/* Gáy lò xo trên cùng + ruy-băng đánh dấu — luôn hiện */
.xoan{position:absolute;top:-8px;left:0;right:0;display:flex;justify-content:space-around;padding:0 22px;z-index:9;pointer-events:none}
.xoan i{width:11px;height:16px;border-radius:6px;border:3px solid #9aa3af;border-top-color:#cbd5e1;background:#fff}
.ruy-bang{position:absolute;top:-2px;right:30px;width:26px;height:66px;z-index:8;background:linear-gradient(#e11d36,#8a1120);
 clip-path:polygon(0 0,100% 0,100% 100%,50% 80%,0 100%);box-shadow:0 6px 10px -6px rgba(138,17,32,.7)}

/* Trang giấy bên trong (kẻ ô + lề đỏ + logo mờ) */
.trang-so{position:relative;min-height:320px;
 background:repeating-linear-gradient(0deg,transparent 0 29px,rgba(21,52,126,.06) 29px 30px),linear-gradient(180deg,#fffdf7,#fff7ea);
 border:1px solid #e6dcc0;border-left:7px solid var(--spine);border-radius:6px 14px 14px 6px;
 padding:26px 18px 20px 20px;box-shadow:0 26px 50px -24px rgba(60,40,10,.5);
 transform-origin:top center;animation:trangMo .85s cubic-bezier(.2,.85,.25,1.05) both}
@keyframes trangMo{0%{opacity:0;transform:rotateX(-82deg)}60%{opacity:1}100%{opacity:1;transform:rotateX(0)}}
.trang-so .nen-logo{position:absolute;inset:0;background-position:center 64%;background-repeat:no-repeat;background-size:210px;opacity:.05;pointer-events:none}
.trang-so > *{position:relative}
.trang-tieu{text-align:center;font-size:13px;color:#8a5e08;font-weight:800;letter-spacing:.5px;margin:2px 0 14px}
.trang-so .hoso .ten{font-size:17px}
.trang-so .o{background:rgba(255,255,255,.6);border:1px solid #ece2c6}
.trang-so .o .so{font-size:26px}
/* Con dấu logo đóng ở góc trang */
.dau-so{position:absolute;right:14px;bottom:12px;width:60px;height:60px;z-index:3;transform:rotate(-8deg);
 animation:sapDong .5s .95s cubic-bezier(.3,1.4,.5,1) both}
@keyframes sapDong{0%{opacity:0;transform:scale(2) rotate(-26deg)}70%{opacity:1;transform:scale(.9) rotate(-4deg)}100%{opacity:1;transform:scale(1) rotate(-8deg)}}

/* --- BÌA SỔ (đóng) = nút bấm, phủ lên trang --- */
.bia{position:absolute;inset:0;z-index:6;border:0;cursor:pointer;padding:22px 18px;font-family:inherit;
 transform-origin:top center;backface-visibility:hidden;border-radius:6px 14px 14px 6px;
 background:radial-gradient(120% 80% at 50% 0%,rgba(255,255,255,.22),rgba(255,255,255,0) 55%),linear-gradient(135deg,var(--bia1),var(--bia2));
 box-shadow:0 22px 44px -18px rgba(15,23,42,.55), inset 0 0 0 2px rgba(255,255,255,.18), inset 0 0 40px rgba(0,0,0,.2);
 color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;transition:transform .15s}
.bia:active{transform:scale(.985)}
.bia:focus-visible{outline:3px solid var(--vang);outline-offset:4px}
.bia .khung{border:2px solid rgba(255,255,255,.45);border-radius:10px;padding:20px 18px;display:flex;flex-direction:column;align-items:center;gap:10px;width:100%;max-width:250px}
.bia .con-dau{width:78px;height:78px;border-color:rgba(246,200,95,.7);background:radial-gradient(circle at 50% 40%,#fff,#f7ead0)}
.bia .tieu{font-size:20px;font-weight:900;letter-spacing:1px;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,.35)}
.bia .phu{font-size:11.5px;opacity:.85}
.bia .cham{margin-top:6px;font-size:12.5px;font-weight:800;background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.45);color:#fff;padding:7px 14px;border-radius:999px}
.so-canh.mo .bia{animation:biaLat .85s ease-in forwards;pointer-events:none}
@keyframes biaLat{35%{opacity:1}100%{opacity:0;transform:rotateX(156deg)}}

/* --- "CON MỘC" hữu hình --- */
/* token nhỏ đứng trước số Ví */
.moc-mini{display:inline-flex;width:20px;height:20px;border-radius:50%;overflow:hidden;vertical-align:-4px;margin-right:4px;border:1.5px solid #e11d36;background:#fff}
.moc-mini img{width:82%;height:82%;object-fit:contain;margin:auto}
/* Tiêu đề mục (ngăn Trang Mộc / Trang tổng kết) */
.muc{display:flex;align-items:center;gap:8px;margin:20px 0 10px;color:#8a5e08;font-weight:900;font-size:13px;letter-spacing:.3px}
.muc::before,.muc::after{content:"";flex:1;height:1px;background:linear-gradient(90deg,transparent,#e6d6a8,transparent)}
/* Băng "hôm nay đã đóng chưa" */
.homnay{display:flex;align-items:center;gap:8px;justify-content:center;text-align:center;background:linear-gradient(135deg,#fff6d6,#ffe9a8);border:1px solid #f2cf6a;border-radius:12px;padding:9px 12px;font-size:12.5px;font-weight:800;color:#8a5e08;margin-bottom:12px}
.homnay.chua{background:#f5f0e2;border-color:#ddcea6;color:#8a7a4a}
/* Tổng số Mộc — dòng khoe gọn */
.tong-lon{text-align:center;margin-bottom:12px}
.tong-lon .num{font-size:34px;font-weight:900;color:#e11d36;line-height:1;display:inline-flex;align-items:center;gap:6px}
.tong-lon .num .moc-mini{width:24px;height:24px}
.tong-lon .cap{font-size:12px;color:#94a3b8;font-weight:700;margin-top:4px}

/* ===== LỊCH ĐÓNG MỘC — phong cách hiện đại (nền trắng, tối giản) ===== */
.lich{background:#fff;border:1px solid #eef1f5;border-radius:16px;padding:12px;box-shadow:0 10px 30px -20px rgba(15,23,42,.35)}
.lich-dau{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:12px}
.lich-dau b{font-size:clamp(14px,3.8vw,16px);font-weight:800;color:#0f172a;letter-spacing:.2px}
.lich-dau button{width:34px;height:34px;border-radius:50%;border:0;background:#f1f5f9;color:#334155;font-size:18px;font-weight:800;cursor:pointer;line-height:1;display:flex;align-items:center;justify-content:center;transition:background .15s,transform .1s}
.lich-dau button:hover:not(:disabled){background:#e2e8f0}
.lich-dau button:disabled{opacity:.35;cursor:default}
.lich-dau button:active:not(:disabled){transform:scale(.9)}
.lich-tuan,.lich-luoi{display:grid;grid-template-columns:repeat(7,1fr);gap:clamp(3px,1.2vw,6px)}
.lich-tuan{margin-bottom:6px}
.lich-tuan span{text-align:center;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.4px}
.lich-tuan span.cn{color:#e11d36}
.o-ngay{aspect-ratio:1/1;border-radius:11px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1px;
 background:#f8fafc;position:relative}
.o-ngay.trong{background:transparent}
.o-ngay .d{font-size:clamp(11px,3vw,13px);font-weight:600;color:#334155}
.o-ngay.cn .d{color:#e11d36}
/* Ngày ĐÃ đóng Mộc: nền đỏ nhạt, số đỏ đậm */
.o-ngay.co{background:linear-gradient(160deg,rgba(225,29,54,.13),rgba(225,29,54,.07))}
.o-ngay.co .d{color:#e11d36;font-weight:800}
/* Con Mộc trong ô = LOGO Đoàn + số */
.moc-day{display:inline-flex;align-items:center;gap:1px;line-height:1}
.moc-day img{width:clamp(13px,3.6vw,16px);height:clamp(13px,3.6vw,16px);object-fit:contain;display:block}
.moc-day b{font-size:clamp(9px,2.6vw,11px);font-weight:900;color:#e11d36}
/* Hôm nay: viền tròn đỏ nổi bật */
.o-ngay.today{box-shadow:inset 0 0 0 2px #e11d36}
.o-ngay.today .d{color:#e11d36}
.o-ngay.tuonglai{opacity:.35}
.lich-tong{text-align:center;font-size:12px;color:#64748b;font-weight:600;margin-top:12px}
.lich-tong b{color:#e11d36;font-weight:800}
.lich-kien{margin-top:10px;font-size:11.5px;color:#64748b;background:#f8fafc;border:1px solid #e6ebf1;border-radius:12px;padding:9px 11px;line-height:1.55}
.moc-khac{margin-top:10px;font-size:12px;color:#8a5e08;background:linear-gradient(135deg,#fff6d6,#ffe9a8);border:1px solid #f2cf6a;border-radius:12px;padding:9px 11px;line-height:1.5;text-align:center}
.moc-khac b{color:#b81528;font-weight:900}
.lich-trong{grid-column:1 / -1;text-align:center;color:#94a3b8;font-size:12px;padding:14px 0}

/* ===== TAB CHUYỂN TRANG trong sổ (Trang Mộc ↔ Trang tổng kết) ===== */
.trang-tab{display:flex;gap:6px;background:#f4ecd8;border:1px solid #e6dcc0;border-radius:999px;padding:4px;margin:14px 0 12px}
.trang-tab button{flex:1;min-width:0;white-space:nowrap;border:0;background:transparent;font-family:inherit;font-weight:800;font-size:12.5px;color:#8a7a4a;padding:8px 4px;border-radius:999px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px}
.trang-tab button.on{background:#fff;color:#e11d36;box-shadow:0 2px 6px -3px rgba(0,0,0,.25)}
.trang-tab button:active{transform:scale(.97)}
.trang-khung{position:relative;overflow:hidden}
.trang-noi{display:none}
.trang-noi.hien{display:block}
.trang-noi.vao-phai{animation:trangPhai .5s ease both}
.trang-noi.vao-trai{animation:trangTrai .5s ease both}
@keyframes trangPhai{from{opacity:0;transform:translateX(40px) rotateY(-16deg)}to{opacity:1;transform:none}}
@keyframes trangTrai{from{opacity:0;transform:translateX(-40px) rotateY(16deg)}to{opacity:1;transform:none}}

/* Lời khen + động viên + nhắc nhở */
.thu-loi{margin-top:16px;background:linear-gradient(135deg,#fff8e7,#fff2d2);border:1px solid #f4e0a3;border-radius:14px;padding:14px 16px}
.thu-loi .khen{font-size:14px;font-weight:800;color:#8a5e08;line-height:1.55}
.thu-loi .khen + .khen{margin-top:8px}
.thu-loi .nhac{font-size:13px;color:#7c5a12;margin-top:8px;line-height:1.6;font-style:italic}
.thu-cham{text-align:center;font-size:12.5px;color:var(--nen2);font-weight:700;font-style:italic;margin-top:16px;line-height:1.5;padding:0 8px}
.thu-ky{margin-top:8px;text-align:right;font-size:13px;color:#475569;font-style:italic;line-height:1.5;padding-right:64px}
.thu-ky b{color:var(--nen2);font-style:normal}

@media (prefers-reduced-motion: reduce){
 .so-canh.mo .bia{animation:none;display:none}
 .trang-so{animation:none;opacity:1;transform:none}
 .dau-so{animation:none;opacity:1;transform:rotate(-8deg)}
 .trang-noi.vao-phai,.trang-noi.vao-trai{animation:none}
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
<body class="<?= ($tab === 'so-moc' && $ketQua && !$pendingOut) ? 'khung-don' : '' ?>">

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

    <?php if (!$ketQua): ?>
    <form class="tra" method="get">
      <input type="hidden" name="tab" value="so-moc">
      <input type="text" name="ma" value="<?= e_($ma) ?>" placeholder="Nhập mã thiếu nhi (VD: HS001)" maxlength="32" autofocus required>
      <button type="submit">Tra cứu</button>
    </form>
    <?php else: ?>
    <div class="doi-ma"><a href="tracuu.php">↩︎ Tra mã khác</a></div>
    <?php endif; ?>

    <?php if ($khongCo): ?>
      <div class="thongbao">Không tìm thấy thiếu nhi với mã "<?= e_($ma) ?>".<br>Vui lòng kiểm tra lại mã số.</div>
    <?php endif; ?>

    <?php if ($ketQua): ?>

      <?php
        $loi = tracuu_loi_la_thu($ketQua);
        $logo = 'assets/img/optimized/logo.webp';   // con dấu logo (nhẹ ~82KB, cache 1 lần)
        $tongMoc   = (int) $ketQua['total_earned'];  // tổng con Mộc đã đóng (để khoe)
        $today     = date('Y-m-d');
        $mocHomNay = $mocNgay[$today] ?? 0;          // Mộc đóng HÔM NAY (theo lịch)

        // Màu BÌA SỔ theo NGÀNH (tên lớp bắt đầu bằng tên khối):
        //   Khai Tâm→hồng · Rước Lễ→xanh lá · Thêm Sức→xanh đậm · Bao Đồng→vàng
        $lop = (string) ($ketQua['class_name'] ?? '');
        $biaKey = '';
        if      (mb_stripos($lop, 'Khai Tâm', 0, 'UTF-8') !== false) $biaKey = 'hong';
        elseif  (mb_stripos($lop, 'Rước',     0, 'UTF-8') !== false) $biaKey = 'la';
        elseif  (mb_stripos($lop, 'Thêm Sức', 0, 'UTF-8') !== false) $biaKey = 'xanh';
        elseif  (mb_stripos($lop, 'Bao Đồng', 0, 'UTF-8') !== false) $biaKey = 'vang';
      ?>
      <div class="man-hinh" id="manHinh"><div class="fit" id="soWrap">
      <div class="so-canh<?= $biaKey ? ' bia-' . $biaKey : '' ?>" id="soCanh">
        <div class="xoan" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
        <div class="ruy-bang" aria-hidden="true"></div>

        <!-- TRANG TRONG (lộ ra khi mở bìa) -->
        <div class="trang-so">
        <div class="nen-logo" style="background-image:url('<?= $logo ?>')" aria-hidden="true"></div>
        <div class="dau-so con-dau" aria-hidden="true"><img src="<?= $logo ?>" alt=""></div>
        <div class="trang-tieu">✦ SỔ MỘC CỦA EM ✦</div>
        <div class="hoso">
          <div class="ava"><?= e_(mb_strtoupper(mb_substr(trim($ketQua['full_name']), 0, 1, 'UTF-8'), 'UTF-8')) ?></div>
          <div>
            <div class="ten"><?= e_($ketQua['full_name']) ?></div>
            <div class="lop">Mã: <?= e_($ketQua['code']) ?><?= $ketQua['class_name'] ? ' · ' . e_($ketQua['class_name']) : '' ?></div>
          </div>
        </div>

        <!-- Tab lật trang: Trang Mộc <-> Trang tổng kết -->
        <div class="trang-tab" id="trangTab">
          <button type="button" class="on" data-p="moc"><img src="<?= $logo ?>" alt="" style="width:16px;height:16px;object-fit:contain"> Trang Mộc</button>
          <button type="button" data-p="tk">📋 Tổng kết</button>
        </div>

        <div class="trang-khung">

        <!-- ===== TRANG 1: TRANG MỘC ===== -->
        <div class="trang-noi hien" data-p="moc">
          <?php if ($mocHomNay > 0): ?>
            <div class="homnay">✅ Hôm nay em đã được đóng <b>+<?= $mocHomNay ?></b> Mộc — giỏi quá!</div>
          <?php else: ?>
            <div class="homnay chua">🕒 Hôm nay chưa có Mộc mới — đi lễ / đi học Giáo Lý để được đóng nhé!</div>
          <?php endif; ?>
          <div class="tong-lon">
            <div class="num"><span class="moc-mini"><img src="<?= $logo ?>" alt=""></span><?= $tongMoc ?></div>
            <div class="cap">con Mộc em đã đóng được từ đầu năm</div>
          </div>
          <!-- LỊCH ĐÓNG MỘC — JS dựng từng tháng (lật tháng khỏi tải lại) -->
          <div class="lich" id="lichMoc">
            <div class="lich-dau">
              <button type="button" id="lichTruoc" aria-label="Tháng trước">‹</button>
              <b id="lichTen">—</b>
              <button type="button" id="lichSau" aria-label="Tháng sau">›</button>
            </div>
            <div class="lich-tuan"><span class="cn">CN</span><span>T2</span><span>T3</span><span>T4</span><span>T5</span><span>T6</span><span>T7</span></div>
            <div class="lich-luoi" id="lichLuoi"></div>
            <div class="lich-tong" id="lichTong"></div>
            <div class="lich-kien">🔎 Ô vàng là ngày em được đóng Mộc. Nếu em đi lễ/đi học mà ngày đó chưa có Mộc, hãy báo Huynh Trưởng để kiểm tra và chỉnh lại nhé!</div>
          </div>
          <?php if ($mocKhac > 0): ?>
            <div class="moc-khac">🎁 Mộc thưởng khác (Huynh Trưởng tặng): <b>+<?= (int) $mocKhac ?></b> — không nằm trên lịch nên đã cộng thẳng vào Ví của em.</div>
          <?php endif; ?>
        </div><!-- /trang Mộc -->

        <!-- ===== TRANG 2: TRANG TỔNG KẾT ===== -->
        <div class="trang-noi" data-p="tk">
        <div class="vi">
          <div class="o">
            <div class="nhan">VÍ MỘC</div>
            <div class="so"><span class="moc-mini"><img src="<?= $logo ?>" alt=""></span><?= (int) $ketQua['current_balance'] ?></div>
          </div>
          <div class="o lua">
            <div class="nhan">🔥 CHUỖI ĐI LỄ</div>
            <div class="so"><?= (int) $ketQua['current_streak'] ?></div>
          </div>
        </div>
        <div class="vi">
          <div class="o">
            <div class="nhan">TỔNG ĐÃ KIẾM</div>
            <div class="so"><span class="moc-mini"><img src="<?= $logo ?>" alt=""></span><?= (int) $ketQua['total_earned'] ?></div>
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
        </div><!-- /trang Tổng kết -->
        </div><!-- /.trang-khung -->
        </div><!-- /.trang-so -->

        <!-- BÌA SỔ (đóng) — bấm để mở -->
        <button type="button" class="bia" id="moSoBtn"
                aria-label="Chạm để mở Sổ Mộc của <?= e_($ketQua['full_name']) ?>">
          <span class="khung">
            <span class="con-dau"><img src="<?= $logo ?>" alt="" width="78" height="78"></span>
            <span class="tieu">SỔ MỘC</span>
            <span class="phu">Đoàn TNTT · Phú Trung</span>
            <span class="cham">📖 Chạm để mở sổ</span>
          </span>
        </button>
      </div><!-- /.so-canh -->
      </div></div><!-- /.fit /.man-hinh -->

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

<?php /* Bấm bìa sổ -> mở sổ; và dựng LỊCH ĐÓNG MỘC từ dữ liệu theo ngày. */ ?>
<script>
(function(){
  var b = document.getElementById('moSoBtn'), c = document.getElementById('soCanh');
  if (b && c) b.addEventListener('click', function(){ c.classList.add('mo'); fit(); });

  // ----- CO DÃN: thu nhỏ cuốn sổ cho vừa đúng 1 khung màn hình (khỏi cuộn) -----
  var manHinh = document.getElementById('manHinh'), soWrap = document.getElementById('soWrap');
  function fit(){
    if (!manHinh || !soWrap || !document.body.classList.contains('khung-don')) return;
    soWrap.style.transform = 'none';
    var availH = manHinh.clientHeight, availW = manHinh.clientWidth;
    var ch = soWrap.offsetHeight, cw = soWrap.offsetWidth;
    if (!ch || !cw || !availH) return;
    var k = Math.min(1, availH / ch, availW / cw);
    soWrap.style.transform = 'scale(' + k + ')';
  }
  var hen;
  function fitSoon(){ clearTimeout(hen); hen = setTimeout(fit, 60); }
  window.addEventListener('resize', fitSoon);
  window.addEventListener('orientationchange', fitSoon);
  window.addEventListener('load', fit);
  // logo tải xong có thể đổi chiều cao -> căn lại
  Array.prototype.forEach.call(document.querySelectorAll('#soCanh img'), function(im){
    if (!im.complete) im.addEventListener('load', fitSoon);
  });

  // ----- LẬT TRANG: Trang Mộc <-> Trang tổng kết -----
  var tab = document.getElementById('trangTab');
  if (tab){
    var pages = {}, order = ['moc','tk'], cur = 'moc';
    Array.prototype.forEach.call(document.querySelectorAll('.trang-noi[data-p]'), function(el){ pages[el.getAttribute('data-p')] = el; });
    Array.prototype.forEach.call(tab.querySelectorAll('button[data-p]'), function(btn){
      btn.addEventListener('click', function(){
        var p = btn.getAttribute('data-p'); if (p === cur) return;
        var sang = order.indexOf(p) > order.indexOf(cur);   // sang trang sau?
        Array.prototype.forEach.call(tab.querySelectorAll('button'), function(x){ x.classList.toggle('on', x === btn); });
        Object.keys(pages).forEach(function(k){
          var el = pages[k]; el.classList.remove('hien','vao-phai','vao-trai');
          if (k === p) el.classList.add('hien', sang ? 'vao-phai' : 'vao-trai');
        });
        cur = p;
        fit();
      });
    });
  }

  // ----- LỊCH ĐÓNG MỘC -----
  var LOGO = <?php echo json_encode($logo); ?>;
  var MOC = <?php echo json_encode($mocNgay, JSON_UNESCAPED_UNICODE); ?> || {};
  var luoi = document.getElementById('lichLuoi');
  if (!luoi) return;
  var elTen = document.getElementById('lichTen'),
      elTong = document.getElementById('lichTong'),
      btPrev = document.getElementById('lichTruoc'),
      btNext = document.getElementById('lichSau');
  var TEN_THANG = ['Một','Hai','Ba','Tư','Năm','Sáu','Bảy','Tám','Chín','Mười','Mười một','Mười hai'];

  function p2(n){ return (n<10?'0':'')+n; }
  var now = new Date(); var curY = now.getFullYear(), curM = now.getMonth();
  var todayKey = curY+'-'+p2(curM+1)+'-'+p2(now.getDate());

  // Giới hạn lật: từ tháng có Mộc sớm nhất -> tháng hiện tại (không sang tương lai)
  var minY = curY, minM = curM;
  Object.keys(MOC).forEach(function(k){
    var pr = k.split('-'); var y=+pr[0], m=+pr[1]-1;
    if (y<minY || (y===minY && m<minM)){ minY=y; minM=m; }
  });
  var viewY = curY, viewM = curM;

  function ve(){
    var first = new Date(viewY, viewM, 1);
    var batDau = first.getDay();               // 0=CN ... khớp cột CN đầu
    var soNgay = new Date(viewY, viewM+1, 0).getDate();
    elTen.textContent = 'Tháng ' + TEN_THANG[viewM] + ' / ' + viewY;
    var html = '', tongThang = 0, ngayCo = 0;
    for (var i=0;i<batDau;i++) html += '<div class="o-ngay trong"></div>';
    for (var d=1; d<=soNgay; d++){
      var key = viewY+'-'+p2(viewM+1)+'-'+p2(d);
      var moc = MOC[key] || 0;
      var col = new Date(viewY, viewM, d).getDay(); // 0=CN
      var cls = 'o-ngay' + (col===0?' cn':'') + (moc>0?' co':'') + (key===todayKey?' today':'') + (key>todayKey?' tuonglai':'');
      html += '<div class="'+cls+'"><span class="d">'+d+'</span>'
            + (moc>0 ? '<span class="moc-day"><img src="'+LOGO+'" alt=""><b>+'+moc+'</b></span>' : '') + '</div>';
      if (moc>0){ tongThang += moc; ngayCo++; }
    }
    luoi.innerHTML = html;
    elTong.innerHTML = ngayCo>0
      ? ('Tháng này: <b>'+tongThang+'</b> con Mộc · '+ngayCo+' ngày được đóng')
      : 'Tháng này chưa có con Mộc nào — cố lên nhé!';
    var truocDuoc = (viewY>minY) || (viewY===minY && viewM>minM);
    var sauDuoc   = (viewY<curY) || (viewY===curY && viewM<curM);
    btPrev.disabled = !truocDuoc; btNext.disabled = !sauDuoc;
    fit();
  }
  btPrev.addEventListener('click', function(){ if(viewM===0){viewM=11;viewY--;}else viewM--; ve(); });
  btNext.addEventListener('click', function(){ if(viewM===11){viewM=0;viewY++;}else viewM++; ve(); });
  ve();
})();
</script>

</body>
</html>
