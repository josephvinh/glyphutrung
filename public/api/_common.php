<?php
/**
 * NỀN DÙNG CHUNG cho cả endpoint JSON lẫn trang HTML.
 *
 * Trước đây phần mở phiên, tiêu đề bảo mật và current_member() bị chép
 * ra hai bản trong _bootstrap.php và _bootstrap_page.php. Hai bản đã
 * lệch nhau (một bản trả $me, bản kia trả $me ?: null) — cùng một tên
 * hàm mà hành xử khác nhau tuỳ file gọi. Gộp về đây để chỉ còn một bản.
 */

require_once __DIR__ . '/../../config/db.php';

/* ---------- Phiên đăng nhập ---------- */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        // Bật secure khi chạy HTTPS trên AZDIGI
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

/* ---------- Tiêu đề bảo mật ----------
   Đặt ở tầng PHP để không lệ thuộc mod_headers của máy chủ. */
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
    if (!empty($_SERVER['HTTPS'])) {
        header('Strict-Transport-Security: max-age=15552000');
    }
}

/** Người đang đăng nhập, hoặc null. Kết quả nhớ lại trong một request. */
function current_member(): ?array
{
    if (empty($_SESSION['member_id'])) return null;

    static $me = null;
    if ($me !== null) return $me;

    $me = db_one(
        'SELECT m.*, r.label AS role_label, r.level AS role_level, r.scope AS role_scope,
                t.label AS title_label, b.name AS block_name, c.name AS class_name
           FROM members m
           JOIN roles r ON r.code = m.role_code
           LEFT JOIN titles t ON t.id = m.title_id
           LEFT JOIN blocks b ON b.id = m.block_id
           LEFT JOIN classes c ON c.id = m.class_id
          WHERE m.id = ?',
        [$_SESSION['member_id']]
    );
    return $me ?: null;
}
