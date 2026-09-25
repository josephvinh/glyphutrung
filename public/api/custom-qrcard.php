<?php
/**
 * CUSTOM QR CARD API
 *
 * Endpoints:
 *   GET  ?action=list-presets          - Lấy danh sách presets của user
 *   POST ?action=save-preset           - Lưu preset mới
 *   POST ?action=delete-preset         - Xóa preset
 *   GET  ?action=list-logos            - Lấy danh sách logo đã upload
 *   POST ?action=upload-logo           - Upload logo mới
 *   POST ?action=delete-logo            - Xóa logo
 */

require __DIR__ . '/_bootstrap.php';

$me   = require_permission('students', 'view');
$year = current_year();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$in = $_SERVER['REQUEST_METHOD'] === 'POST' ? json_input() : $_GET;

/**
 * Lấy user_id từ session hoặc tạo anonymous ID
 */
function get_user_identifier(): string {
    global $me;
    if (!empty($me['id'])) {
        return 'user_' . $me['id'];
    }
    // Fallback: dùng session ID cho users không đăng nhập
    return 'session_' . session_id();
}

/**
 * Validate preset data
 */
function validate_preset(array $data): array {
    $errors = [];

    if (empty($data['name'])) {
        $errors[] = 'Tên preset không được trống.';
    } elseif (mb_strlen($data['name'], 'UTF-8') > 50) {
        $errors[] = 'Tên preset không được quá 50 ký tự.';
    }

    // Validate config fields
    $config = $data['config'] ?? [];
    if (!empty($config)) {
        $validTemplates = ['basic', 'classic', 'badge', 'compact', 'minimal'];
        if (!empty($config['template']) && !in_array($config['template'], $validTemplates)) {
            $errors[] = 'Template không hợp lệ.';
        }

        $validErrorLevels = ['L', 'M', 'Q', 'H'];
        if (!empty($config['errorLevel']) && !in_array($config['errorLevel'], $validErrorLevels)) {
            $errors[] = 'Error level không hợp lệ.';
        }

        $validLogoPositions = ['top', 'center', 'bottom'];
        if (!empty($config['logoPosition']) && !in_array($config['logoPosition'], $validLogoPositions)) {
            $errors[] = 'Vị trí logo không hợp lệ.';
        }

        // Validate logoId if provided
        if (!empty($config['logoId']) && !is_numeric($config['logoId'])) {
            $errors[] = 'Logo ID không hợp lệ.';
        }
    }

    return $errors;
}

try {
    switch ($action) {

        case 'list-presets':
            $uid = get_user_identifier();
            $presets = db_all(
                'SELECT id, name, config, created_at, updated_at
                 FROM qrcard_presets
                 WHERE user_identifier = ?
                 ORDER BY updated_at DESC',
                [$uid]
            );

            // Parse JSON config
            foreach ($presets as &$p) {
                $p['config'] = json_decode($p['config'] ?? '{}', true);
                $p['is_default'] = (bool) ($p['config']['isDefault'] ?? false);
            }
            unset($p);

            json_success(['presets' => $presets]);
            break;


        case 'save-preset':
            require_write();
            require_csrf();

            $name = trim($in['name'] ?? '');
            $config = $in['config'] ?? [];
            $isDefault = !empty($in['isDefault']);
            $presetId = !empty($in['id']) ? (int) $in['id'] : null;

            // Validate
            $errors = validate_preset(['name' => $name, 'config' => $config]);
            if (!empty($errors)) {
                json_fail(implode(' ', $errors), 400);
            }

            $uid = get_user_identifier();

            // Nếu là default, bỏ default của các preset khác
            if ($isDefault) {
                // Lấy config hiện tại và clear isDefault flag
                $existingPresets = db_all(
                    'SELECT id, config FROM qrcard_presets WHERE user_identifier = ? AND id != ?',
                    [$uid, $presetId ?? 0]
                );
                foreach ($existingPresets as $ep) {
                    $ec = json_decode($ep['config'] ?? '{}', true);
                    if (!empty($ec['isDefault'])) {
                        $ec['isDefault'] = false;
                        db_run(
                            'UPDATE qrcard_presets SET config = ? WHERE id = ?',
                            [json_encode($ec, JSON_UNESCAPED_UNICODE), $ep['id']]
                        );
                    }
                }
            }

            $config['isDefault'] = $isDefault;
            $configJson = json_encode($config, JSON_UNESCAPED_UNICODE);

            if ($presetId) {
                // Update existing
                $rows = db_run(
                    'UPDATE qrcard_presets SET name = ?, config = ?, updated_at = NOW() WHERE id = ? AND user_identifier = ?',
                    [$name, $configJson, $presetId, $uid]
                );
                if ($rows === 0) {
                    json_fail('Không tìm thấy preset.', 404);
                }
                $result = db_one('SELECT * FROM qrcard_presets WHERE id = ?', [$presetId]);
                $result['config'] = json_decode($result['config'] ?? '{}', true);
            } else {
                // Insert new using db_insert (returns the ID)
                $insertId = db_insert(
                    'INSERT INTO qrcard_presets (user_identifier, name, config, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())',
                    [$uid, $name, $configJson]
                );
                $result = db_one('SELECT * FROM qrcard_presets WHERE id = ?', [$insertId]);
                $result['config'] = json_decode($result['config'] ?? '{}', true);
            }

            json_success(['preset' => $result]);
            break;


        case 'delete-preset':
            require_write();
            require_csrf();

            $presetId = !empty($in['id']) ? (int) $in['id'] : 0;
            if (!$presetId) {
                json_fail('Thiếu ID preset.', 400);
            }

            $uid = get_user_identifier();

            // Check if preset exists first
            $preset = db_one(
                'SELECT id FROM qrcard_presets WHERE id = ? AND user_identifier = ?',
                [$presetId, $uid]
            );
            if (!$preset) {
                json_fail('Không tìm thấy preset.', 404);
            }

            // Delete
            db_run('DELETE FROM qrcard_presets WHERE id = ? AND user_identifier = ?', [$presetId, $uid]);

            json_success(['deleted' => true]);
            break;


        case 'list-logos':
            $uid = get_user_identifier();
            $logos = db_all(
                'SELECT id, filename, original_name, size, created_at
                 FROM qrcard_logos
                 WHERE user_identifier = ?
                 ORDER BY created_at DESC',
                [$uid]
            );

            // Generate URLs
            foreach ($logos as &$logo) {
                $logo['url'] = '/uploads/qrcard-logos/' . $uid . '/' . $logo['filename'];
                $logo['size_human'] = format_bytes($logo['size']);
            }
            unset($logo);

            json_success(['logos' => $logos]);
            break;


        case 'upload-logo':
            require_write();
            require_csrf();

            if (empty($_FILES['logo'])) {
                json_fail('Không có file được upload.', 400);
            }

            $file = $_FILES['logo'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
            $maxSize = 2 * 1024 * 1024; // 2MB

            // Validate file
            if ($file['error'] !== UPLOAD_ERR_OK) {
                json_fail('Lỗi upload: ' . $file['error'], 400);
            }

            if (!in_array($file['type'], $allowedTypes)) {
                json_fail('Chỉ chấp nhận file ảnh: JPG, PNG, GIF, WebP, SVG.', 400);
            }

            if ($file['size'] > $maxSize) {
                json_fail('File quá lớn. Tối đa 2MB.', 400);
            }

            $uid = get_user_identifier();

            // Check logo limit (max 10 logos per user)
            $count = db_val('SELECT COUNT(*) FROM qrcard_logos WHERE user_identifier = ?', [$uid]);
            if ($count >= 10) {
                json_fail('Đã đạt giới hạn 10 logo. Xóa logo cũ trước khi upload mới.', 400);
            }

            // Create directory
            $uploadDir = __DIR__ . '/../../uploads/qrcard-logos/' . $uid;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newFilename = uniqid('logo_') . '.' . $ext;
            $uploadPath = $uploadDir . '/' . $newFilename;

            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                json_fail('Không thể lưu file.', 500);
            }

            // Save to database using db_insert (returns the ID)
            $logoId = db_insert(
                'INSERT INTO qrcard_logos (user_identifier, filename, original_name, size, created_at) VALUES (?, ?, ?, ?, NOW())',
                [$uid, $newFilename, $file['name'], $file['size']]
            );

            $logo = [
                'id' => $logoId,
                'filename' => $newFilename,
                'original_name' => $file['name'],
                'size' => $file['size'],
                'url' => '/uploads/qrcard-logos/' . $uid . '/' . $newFilename,
                'size_human' => format_bytes($file['size']),
                'created_at' => date('Y-m-d H:i:s')
            ];

            json_success(['logo' => $logo]);
            break;


        case 'delete-logo':
            require_write();
            require_csrf();

            $logoId = !empty($in['id']) ? (int) $in['id'] : 0;
            if (!$logoId) {
                json_fail('Thiếu ID logo.', 400);
            }

            $uid = get_user_identifier();
            $logo = db_one('SELECT * FROM qrcard_logos WHERE id = ? AND user_identifier = ?', [$logoId, $uid]);

            if (!$logo) {
                json_fail('Không tìm thấy logo.', 404);
            }

            // Delete file
            $filePath = __DIR__ . '/../../uploads/qrcard-logos/' . $uid . '/' . $logo['filename'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Delete from database
            db_run('DELETE FROM qrcard_logos WHERE id = ?', [$logoId]);

            json_success(['deleted' => true]);
            break;


        case 'export-preview':
            // Generate preview image (PNG/PDF) - frontend sẽ handle việc convert
            $data = $in['data'] ?? [];
            json_success(['received' => true, 'timestamp' => time()]);
            break;


        default:
            json_fail('Action không hợp lệ.', 400);
    }
} catch (Exception $e) {
    error_log('Custom QR Card API Error: ' . $e->getMessage());
    json_fail('Lỗi server: ' . $e->getMessage(), 500);
}

/**
 * Format bytes to human readable
 */
function format_bytes(int $bytes): string {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}
