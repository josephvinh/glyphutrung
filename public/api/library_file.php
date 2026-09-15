<?php
/**
 * PHỤC VỤ FILE THƯ VIỆN — riêng tư, chỉ member đăng nhập.
 *   GET library_file.php?id=N&mode=view|download
 *
 * Không phải link tĩnh: file nằm ngoài web, endpoint này kiểm quyền rồi mới
 * đọc ra. Content-Type do MÁY CHỦ đặt + nosniff -> file không thể chạy như mã.
 */
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_library.php';

$me = require_login();

$id   = (int) ($_GET['id'] ?? 0);
$item = $id ? db_one('SELECT * FROM library_items WHERE id = ?', [$id]) : null;

// Dọn buffer JSON/gzip mà _bootstrap mở sẵn, để trả nhị phân sạch.
while (ob_get_level() > 0) ob_end_clean();

if (!$item) { http_response_code(404); exit; }

// Quyền xem: tài liệu đã duyệt ai cũng xem; chưa duyệt chỉ người đăng hoặc
// người có quyền duyệt (edit) mới xem.
$canEdit = permission_of('thu_vien') === 'edit';
if ($item['status'] !== 'da_duyet'
    && (int) $item['uploaded_by'] !== (int) $me['id']
    && !$canEdit) {
    http_response_code(403); exit;
}

$path = library_storage_dir() . '/' . $item['stored_name'];
if (!is_file($path)) { http_response_code(404); exit; }

$ext     = strtolower(pathinfo($item['stored_name'], PATHINFO_EXTENSION));
$mode    = ($_GET['mode'] ?? 'view') === 'download' ? 'download' : 'view';
$inline  = ($mode === 'view') && library_is_viewable($ext);

header_remove('Content-Type');
header('Content-Type: ' . library_mime_for_ext($ext));
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=300');
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
    . '; filename="' . library_download_filename($item['original_name']) . '"');

readfile($path);
exit;
