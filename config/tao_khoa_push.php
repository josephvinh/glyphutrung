<?php
/**
 * SINH CẶP KHOÁ VAPID CHO THÔNG BÁO ĐẨY
 *
 * Chạy MỘT LẦN cho mỗi máy chủ:
 *
 *     php config/tao_khoa_push.php
 *
 * Rồi chép ba dòng nó in ra vào config/config.php.
 *
 * CẢNH BÁO: đổi khoá về sau sẽ làm MỌI máy đã đăng ký ngừng nhận
 * thông báo — ai muốn nhận lại phải vào app bật lại. Sinh một lần
 * rồi giữ nguyên.
 */

require __DIR__ . '/db.php';
require __DIR__ . '/push.php';
require __DIR__ . '/_guard_setup.php';
guard_setup('tao_khoa_push.php');

if (PHP_SAPI !== 'cli') header('Content-Type: text/plain; charset=utf-8');

$cu = app_config('push');
if (!empty($cu['private'])) {
    echo "ĐÃ CÓ khoá VAPID trong config.php.\n\n";
    echo "Nếu sinh khoá mới, mọi máy đang nhận thông báo sẽ ngừng nhận\n";
    echo "và phải vào app bật lại từ đầu.\n\n";
    echo "Muốn sinh mới thật thì xoá dòng 'push' trong config.php rồi chạy lại.\n";
    exit;
}

$k = push_tao_khoa();

echo "Chép nguyên khối này vào config/config.php,\n";
echo "đặt ngay trước dòng 'production':\n\n";
echo "    'push' => [\n";
echo "        'public'  => '" . $k['public'] . "',\n";
echo "        'private' => '" . $k['private'] . "',\n";
echo "        'subject' => 'mailto:DIA_CHI_EMAIL_CUA_BAN',\n";
echo "    ],\n\n";
echo "Ghi chú:\n";
echo "  - 'subject' phải là email thật hoặc địa chỉ web của giáo xứ.\n";
echo "    Các dịch vụ đẩy (Google, Apple) dùng nó để liên hệ khi có sự cố.\n";
echo "  - 'private' là bí mật. Nó đã nằm trong config.php vốn không\n";
echo "    lộ ra Internet, nhưng đừng chép đi đâu khác.\n";
