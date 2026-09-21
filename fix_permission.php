<?php
require __DIR__ . '/config/db.php';
$db = db();
$stmt = $db->prepare('UPDATE permissions SET level = ? WHERE module_key = ? AND role_code = ?');
$result = $stmt->execute(['edit', 'org', 'bdh']);
echo $result ? 'Đã cập nhật quyền BĐH cho module org thành edit' : 'Lỗi khi cập nhật';
