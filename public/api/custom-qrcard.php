<?php
/**
 * API: Custom QR Card
 *
 * Cho phép tạo và xuất thẻ QR tùy chỉnh với nhiều template và tùy chọn.
 * Authorization: User phải có quyền trong phạm vi lớp/khối được phân công.
 *
 * Actions:
 *   - preview           : Generate preview HTML
 *   - export_png        : Export cards as PNG
 *   - export_pdf        : Export cards as PDF
 *   - export_svg_inline: Export all cards as single SVG
 *   - export_svg_zip   : Export cards as ZIP of SVG files
 *   - save_preset      : Lưu preset tùy chỉnh
 *   - list_presets     : Danh sách presets của user
 *   - delete_preset    : Xóa preset
 *   - upload_logo      : Upload logo tùy chỉnh
 *   - list_logos       : Danh sách logos của user
 *   - delete_logo      : Xóa logo
 */

require_once __DIR__ . '/_bootstrap.php';

// Lấy action từ query string hoặc body
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Các action cần POST
$writeActions = ['export_png', 'export_pdf', 'export_svg_inline', 'export_svg_zip', 'save_preset', 'delete_preset', 'upload_logo', 'delete_logo'];

// Validate request method
if (in_array($action, $writeActions, true)) {
    require_post();
    require_csrf();
}

// Xử lý từng action
switch ($action) {
    case 'preview':
        handlePreview();
        break;

    case 'export_png':
        handleExportPng();
        break;

    case 'export_pdf':
        handleExportPdf();
        break;

    case 'export_svg_inline':
        handleExportSvgInline();
        break;

    case 'export_svg_zip':
        handleExportSvgZip();
        break;

    case 'save_preset':
        handleSavePreset();
        break;

    case 'list_presets':
        handleListPresets();
        break;

    case 'delete_preset':
        handleDeletePreset();
        break;

    case 'upload_logo':
        handleUploadLogo();
        break;

    case 'list_logos':
        handleListLogos();
        break;

    case 'delete_logo':
        handleDeleteLogo();
        break;

    default:
        json_fail('Action không hợp lệ: ' . $action, 400);
}

/* ================================================================
   HÀM XỬ LÝ TỪNG ACTION
   ================================================================ */

/**
 * Preview: Trả về HTML preview cho các thẻ QR
 */
function handlePreview(): void
{
    $me = require_login();
    require_permission('qrcard', 'view');

    $input = json_input();
    $studentIds = $input['student_ids'] ?? [];
    $options = $input['options'] ?? [];

    if (empty($studentIds)) {
        json_fail('Vui lòng chọn ít nhất một học sinh.', 400);
    }

    // Validate students and check authorization
    $students = validateStudents($me, $studentIds);
    if (empty($students)) {
        json_fail('Không tìm thấy học sinh nào hợp lệ.', 400);
    }

    // Generate preview HTML
    $html = generateCardsHtml($students, $options);

    json_out([
        'ok' => true,
        'html' => $html,
        'count' => count($students)
    ]);
}

/**
 * Export PNG: Trả về file PNG
 */
function handleExportPng(): void
{
    $me = require_login();
    require_permission('qrcard', 'view');

    $input = json_input();
    $studentIds = $input['student_ids'] ?? [];
    $options = $input['options'] ?? [];

    if (empty($studentIds)) {
        json_fail('Vui lòng chọn ít nhất một học sinh.', 400);
    }

    // Validate students
    $students = validateStudents($me, $studentIds);
    if (empty($students)) {
        json_fail('Không tìm thấy học sinh nào hợp lệ.', 400);
    }

    // Giới hạn số lượng export
    $maxExport = 100;
    if (count($students) > $maxExport) {
        json_fail('Chỉ có thể export tối đa ' . $maxExport . ' thẻ một lần.', 400);
    }

    // Generate HTML để convert sang PNG (frontend sẽ xử lý)
    $html = generateCardsHtml($students, $options);

    // Return HTML để frontend convert bằng html2canvas
    json_out([
        'ok' => true,
        'html' => $html,
        'count' => count($students),
        'format' => 'png'
    ]);
}

/**
 * Export PDF: Trả về file PDF
 */
function handleExportPdf(): void
{
    $me = require_login();
    require_permission('qrcard', 'view');

    $input = json_input();
    $studentIds = $input['student_ids'] ?? [];
    $options = $input['options'] ?? [];

    if (empty($studentIds)) {
        json_fail('Vui lòng chọn ít nhất một học sinh.', 400);
    }

    // Validate students
    $students = validateStudents($me, $studentIds);
    if (empty($students)) {
        json_fail('Không tìm thấy học sinh nào hợp lệ.', 400);
    }

    // Giới hạn
    $maxExport = 100;
    if (count($students) > $maxExport) {
        json_fail('Chỉ có thể export tối đa ' . $maxExport . ' thẻ một lần.', 400);
    }

    // Generate HTML để convert sang PDF (frontend xử lý)
    $html = generateCardsHtml($students, $options);

    json_out([
        'ok' => true,
        'html' => $html,
        'count' => count($students),
        'format' => 'pdf'
    ]);
}

/**
 * Export SVG Inline: Trả về 1 SVG chứa tất cả thẻ
 */
function handleExportSvgInline(): void
{
    $me = require_login();
    require_permission('qrcard', 'view');

    $input = json_input();
    $studentIds = $input['student_ids'] ?? [];
    $options = $input['options'] ?? [];

    if (empty($studentIds)) {
        json_fail('Vui lòng chọn ít nhất một học sinh.', 400);
    }

    $students = validateStudents($me, $studentIds);
    if (empty($students)) {
        json_fail('Không tìm thấy học sinh nào hợp lệ.', 400);
    }

    // Generate SVG
    $svg = generateCardsSvg($students, $options);

    json_out([
        'ok' => true,
        'svg' => $svg,
        'count' => count($students),
        'filename' => 'qrcards_' . date('Ymd') . '.svg'
    ]);
}

/**
 * Export SVG ZIP: Trả về ZIP chứa nhiều file SVG
 */
function handleExportSvgZip(): void
{
    $me = require_login();
    require_permission('qrcard', 'view');

    $input = json_input();
    $studentIds = $input['student_ids'] ?? [];
    $options = $input['options'] ?? [];

    if (empty($studentIds)) {
        json_fail('Vui lòng chọn ít nhất một học sinh.', 400);
    }

    $students = validateStudents($me, $studentIds);
    if (empty($students)) {
        json_fail('Không tìm thấy học sinh nào hợp lệ.', 400);
    }

    // Giới hạn
    $maxExport = 100;
    if (count($students) > $maxExport) {
        json_fail('Chỉ có thể export tối đa ' . $maxExport . ' thẻ một lần.', 400);
    }

    // Generate individual SVGs
    $svgFiles = [];
    foreach ($students as $s) {
        $svg = generateSingleCardSvg($s, $options);
        $filename = 'qrcard_' . preg_replace('/[^a-zA-Z0-9]/', '_', $s['code']) . '.svg';
        $svgFiles[$filename] = $svg;
    }

    // Return list for frontend to create ZIP
    json_out([
        'ok' => true,
        'files' => $svgFiles,
        'count' => count($students),
        'format' => 'svg_zip'
    ]);
}

/**
 * Generate cards SVG (inline - all in one SVG)
 */
function generateCardsSvg(array $students, array $options): string
{
    $qrSize = intval($options['qrSize'] ?? '25');
    $cardWidth = $qrSize + 30;
    $cardHeight = $qrSize + 40;
    $cardsPerRow = 3;
    $gap = 5;
    $padding = 10;

    $cols = $cardsPerRow;
    $rows = ceil(count($students) / $cols);
    $width = $cols * ($cardWidth + $gap) + $padding * 2;
    $height = $rows * ($cardHeight + $gap) + $padding * 2;

    $svg = '<?xml version="1.0" encoding="UTF-8"?>';
    $svg .= '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . 'mm" height="' . $height . 'mm" viewBox="0 0 ' . $width . ' ' . $height . '">';

    foreach ($students as $i => $student) {
        $col = $i % $cardsPerRow;
        $row = intdiv($i, $cardsPerRow);
        $x = $padding + $col * ($cardWidth + $gap);
        $y = $padding + $row * ($cardHeight + $gap);

        $svg .= generateCardSvgElement($student, $options, $x, $y, $cardWidth, $cardHeight, $qrSize);
    }

    $svg .= '</svg>';
    return $svg;
}

/**
 * Generate single card SVG
 */
function generateSingleCardSvg(array $student, array $options): string
{
    $qrSize = intval($options['qrSize'] ?? '25');
    $cardWidth = $qrSize + 30;
    $cardHeight = $qrSize + 40;

    return '<?xml version="1.0" encoding="UTF-8"?>' .
           '<svg xmlns="http://www.w3.org/2000/svg" width="' . $cardWidth . 'mm" height="' . $cardHeight . 'mm" viewBox="0 0 ' . $cardWidth . ' ' . $cardHeight . '">' .
           generateCardSvgElement($student, $options, 0, 0, $cardWidth, $cardHeight, $qrSize) .
           '</svg>';
}

/**
 * Generate SVG element for a card
 */
function generateCardSvgElement(array $student, array $options, float $x, float $y, float $w, float $h, int $qrSize): string
{
    $bgColor = $options['bgColor'] ?? '#ffffff';
    $textColor = $options['textColor'] ?? '#1e293b';
    $headerText = $options['headerText'] ?? '';
    $fields = $options['fields'] ?? ['code', 'name'];

    $svg = '';

    // Card background
    $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" fill="' . $bgColor . '" stroke="#e2e8f0" rx="2"/>';

    // Header text
    if (!empty($headerText)) {
        $svg .= '<text x="' . ($x + $w/2) . '" y="' . ($y + 5) . '" text-anchor="middle" font-size="2" fill="' . $textColor . '" font-weight="bold">' . htmlspecialchars($headerText, ENT_XML1, 'UTF-8') . '</text>';
    }

    // QR Code placeholder (will be rendered on client or we generate here)
    $qrX = $x + ($w - $qrSize) / 2;
    $qrY = $y + 8;
    $svg .= '<rect x="' . $qrX . '" y="' . $qrY . '" width="' . $qrSize . '" height="' . $qrSize . '" fill="white" stroke="#ccc"/>';
    $svg .= '<text x="' . ($x + $w/2) . '" y="' . ($qrY + $qrSize/2 + 1) . '" text-anchor="middle" font-size="3" fill="#999">QR: ' . htmlspecialchars($student['code'], ENT_XML1, 'UTF-8') . '</text>';

    // Text fields
    $textY = $qrY + $qrSize + 5;
    foreach ($fields as $field) {
        $text = '';
        switch ($field) {
            case 'code':
                $text = $student['code'];
                break;
            case 'name':
                $text = ($student['holyName'] ?? '') . ' ' . $student['name'];
                break;
            case 'className':
                $text = 'Lớp: ' . ($student['className'] ?? '');
                break;
        }
        if ($text) {
            $svg .= '<text x="' . ($x + $w/2) . '" y="' . $textY . '" text-anchor="middle" font-size="2.5" fill="' . $textColor . '">' . htmlspecialchars(trim($text), ENT_XML1, 'UTF-8') . '</text>';
            $textY += 3;
        }
    }

    return $svg;
}

/**
 * Save preset: Lưu preset tùy chỉnh
 */
function handleSavePreset(): void
{
    $me = require_login();

    $input = json_input();
    $name = trim($input['name'] ?? '');
    $options = $input['options'] ?? [];
    $isDefault = !empty($input['is_default']);

    if (empty($name)) {
        json_fail('Vui lòng nhập tên preset.', 400);
    }

    if (mb_strlen($name) > 100) {
        json_fail('Tên preset không được quá 100 ký tự.', 400);
    }

    // Validate options là JSON hợp lệ
    $optionsJson = json_encode($options);
    if ($optionsJson === false) {
        json_fail('Options không hợp lệ.', 400);
    }

    trong_giao_dich(function () use ($me, $name, $optionsJson, $isDefault) {
        // Nếu là default, bỏ default của các preset khác
        if ($isDefault) {
            db_run('UPDATE qr_card_presets SET is_default = 0 WHERE member_id = ?', [$me['id']]);
        }

        // Insert preset mới
        db_run(
            'INSERT INTO qr_card_presets (member_id, name, options, is_default) VALUES (?, ?, ?, ?)',
            [$me['id'], $name, $optionsJson, $isDefault ? 1 : 0]
        );

        return db()->lastInsertId();
    });

    log_action('tao', 'qrcard', 'Lưu preset QR Card', $name);

    json_out([
        'ok' => true,
        'message' => 'Đã lưu preset "' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"'
    ]);
}

/**
 * List presets: Danh sách presets của user
 */
function handleListPresets(): void
{
    $me = require_login();

    $presets = db_all(
        'SELECT id, name, options, is_default, created_at, updated_at
         FROM qr_card_presets
         WHERE member_id = ?
         ORDER BY is_default DESC, updated_at DESC',
        [$me['id']]
    );

    // Decode JSON options
    foreach ($presets as &$p) {
        $p['options'] = json_decode($p['options'], true);
        $p['created_at'] = formatDate($p['created_at']);
        $p['updated_at'] = formatDate($p['updated_at']);
    }
    unset($p);

    json_out([
        'ok' => true,
        'presets' => $presets
    ]);
}

/**
 * Delete preset: Xóa preset
 */
function handleDeletePreset(): void
{
    $me = require_login();

    $input = json_input();
    $presetId = (int) ($input['id'] ?? 0);

    if ($presetId <= 0) {
        json_fail('ID preset không hợp lệ.', 400);
    }

    // Chỉ xóa preset của chính mình
    $deleted = db_run(
        'DELETE FROM qr_card_presets WHERE id = ? AND member_id = ?',
        [$presetId, $me['id']]
    )->rowCount();

    if ($deleted === 0) {
        json_fail('Preset không tìm thấy hoặc bạn không có quyền xóa.', 404);
    }

    log_action('xoa', 'qrcard', 'Xóa preset QR Card', 'ID: ' . $presetId);

    json_out([
        'ok' => true,
        'message' => 'Đã xóa preset.'
    ]);
}

/**
 * Upload logo: Upload logo tùy chỉnh
 */
function handleUploadLogo(): void
{
    $me = require_login();

    // Kiểm tra file upload
    if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        json_fail('Không nhận được file upload.', 400);
    }

    $file = $_FILES['logo'];

    // Validate MIME type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes, true)) {
        json_fail('Chỉ chấp nhận file JPG, PNG, SVG, WebP.', 400);
    }

    // Validate size (max 2MB)
    $maxSize = 2 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        json_fail('File logo không được quá 2MB.', 400);
    }

    // Tạo thư mục nếu chưa có
    $uploadDir = __DIR__ . '/../../public/uploads/qr-logos';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Tạo filename unique
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $filepath = $uploadDir . '/' . $filename;

    // Di chuyển file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        json_fail('Không thể lưu file. Vui lòng thử lại.', 500);
    }

    // Generate logo ID
    $logoId = 'custom_' . substr(hash('sha256', $filename), 0, 16);

    // Lưu vào database
    db_run(
        'INSERT INTO qr_card_logos (id, member_id, filename, original_name, mime_type, size)
         VALUES (?, ?, ?, ?, ?, ?)',
        [$logoId, $me['id'], $filename, $file['name'], $mimeType, $file['size']]
    );

    json_out([
        'ok' => true,
        'logo_id' => $logoId,
        'url' => '/uploads/qr-logos/' . $filename,
        'filename' => $filename,
        'original_name' => $file['name']
    ]);
}

/**
 * List logos: Danh sách logos của user
 */
function handleListLogos(): void
{
    $me = require_login();

    $logos = db_all(
        'SELECT id, original_name, mime_type, size, uploaded_at
         FROM qr_card_logos
         WHERE member_id = ?
         ORDER BY uploaded_at DESC',
        [$me['id']]
    );

    foreach ($logos as &$l) {
        $l['url'] = '/uploads/qr-logos/' . $l['filename'];
        $l['size_formatted'] = formatBytes($l['size']);
        $l['uploaded_at'] = formatDate($l['uploaded_at']);
    }
    unset($l);

    json_out([
        'ok' => true,
        'logos' => $logos
    ]);
}

/**
 * Delete logo: Xóa logo
 */
function handleDeleteLogo(): void
{
    $me = require_login();

    $input = json_input();
    $logoId = trim($input['id'] ?? '');

    if (empty($logoId) || !preg_match('/^[\w-]+$/', $logoId)) {
        json_fail('ID logo không hợp lệ.', 400);
    }

    // Lấy thông tin logo
    $logo = db_one('SELECT * FROM qr_card_logos WHERE id = ? AND member_id = ?', [$logoId, $me['id']]);

    if (!$logo) {
        json_fail('Logo không tìm thấy hoặc bạn không có quyền xóa.', 404);
    }

    // Xóa file
    $filepath = __DIR__ . '/../../public/uploads/qr-logos/' . $logo['filename'];
    if (file_exists($filepath)) {
        unlink($filepath);
    }

    // Xóa database record
    db_run('DELETE FROM qr_card_logos WHERE id = ?', [$logoId]);

    json_out([
        'ok' => true,
        'message' => 'Đã xóa logo.'
    ]);
}

/* ================================================================
   HELPER FUNCTIONS
   ================================================================ */

/**
 * Validate và lấy thông tin học sinh, kiểm tra authorization
 */
function validateStudents(array $me, array $studentIds): array
{
    // Lấy danh sách lớp được phép
    $allowedClassIds = allowed_class_ids($me);

    // Lấy thông tin học sinh
    if ($allowedClassIds === null) {
        // Admin/BĐH: xem tất cả
        $students = db_all(
            'SELECT s.id, s.code, s.holy_name, s.full_name, s.birth_date,
                    c.name as class_name, c.id as class_id,
                    b.name as block_name
             FROM students s
             JOIN enrollments e ON e.student_id = s.id
             JOIN classes c ON c.id = e.class_id
             JOIN blocks b ON b.id = c.block_id
             JOIN school_years y ON y.id = e.year_id
             WHERE s.id IN (' . implode(',', array_fill(0, count($studentIds), '?')) . ')
               AND y.is_current = 1
               AND e.status = "đang ghi danh"
             ORDER BY c.sort_order, s.full_name',
            $studentIds
        );
    } else {
        // GLV: chỉ lớp được phân công
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $params = array_merge($allowedClassIds, $studentIds);

        $students = db_all(
            "SELECT s.id, s.code, s.holy_name, s.full_name, s.birth_date,
                    c.name as class_name, c.id as class_id,
                    b.name as block_name
             FROM students s
             JOIN enrollments e ON e.student_id = s.id
             JOIN classes c ON c.id = e.class_id
             JOIN blocks b ON b.id = c.block_id
             JOIN school_years y ON y.id = e.year_id
             WHERE c.id IN ($placeholders)
               AND s.id IN ($placeholders)
               AND y.is_current = 1
               AND e.status = 'đang ghi danh'
             ORDER BY c.sort_order, s.full_name",
            array_merge($params, $studentIds)
        );
    }

    // Format kết quả
    return array_map(function ($s) {
        return [
            'id' => (int) $s['id'],
            'code' => $s['code'],
            'holyName' => $s['holy_name'] ?? '',
            'name' => $s['full_name'],
            'birthDate' => $s['birth_date'] ? formatDate($s['birth_date']) : '',
            'className' => $s['class_name'],
            'classId' => (int) $s['class_id'],
            'block' => $s['block_name']
        ];
    }, $students);
}

/**
 * Generate HTML cho các thẻ QR
 */
function generateCardsHtml(array $students, array $options): string
{
    $template = $options['template'] ?? 'basic';
    $qrColor = $options['qrColor'] ?? '#000000';
    $bgColor = $options['bgColor'] ?? '#ffffff';
    $textColor = $options['textColor'] ?? '#1e293b';
    $qrSize = $options['qrSize'] ?? '25mm';
    $fontSize = $options['fontSize'] ?? 'medium';
    $fields = $options['fields'] ?? ['code', 'name'];
    $headerText = $options['headerText'] ?? '';
    $showLogo = !empty($options['showLogo']);
    $logoUrl = $options['logoUrl'] ?? '';
    $logoPosition = $options['logoPosition'] ?? 'top';

    // Font sizes
    $fontSizes = [
        'small' => ['code' => '9px', 'name' => '11px', 'detail' => '8px'],
        'medium' => ['code' => '10px', 'name' => '12px', 'detail' => '9px'],
        'large' => ['code' => '11px', 'name' => '14px', 'detail' => '10px']
    ];
    $sizes = $fontSizes[$fontSize] ?? $fontSizes['medium'];

    // Template-specific settings
    $templateSettings = [
        'basic' => [
            'border' => '1px solid #e2e8f0',
            'borderRadius' => '4mm',
            'padding' => '3mm',
            'layout' => 'vertical'
        ],
        'classic' => [
            'border' => '3px double #1e3a8a',
            'borderRadius' => '6mm',
            'padding' => '4mm',
            'layout' => 'vertical',
            'showEmblem' => true
        ],
        'badge' => [
            'border' => '2px solid #e2e8f0',
            'borderRadius' => '50%',
            'padding' => '3mm',
            'layout' => 'vertical',
            'holePunch' => true
        ],
        'compact' => [
            'border' => '1px dashed #94a3b8',
            'borderRadius' => '2mm',
            'padding' => '2mm',
            'layout' => 'horizontal'
        ],
        'minimal' => [
            'border' => 'none',
            'borderRadius' => '0',
            'padding' => '2mm',
            'layout' => 'vertical'
        ]
    ];

    $ts = $templateSettings[$template] ?? $templateSettings['basic'];

    // Build CSS
    $css = $template === 'badge' ? generateBadgeCss($ts, $qrSize, $qrColor, $bgColor, $textColor, $sizes, $headerText)
                                  : generateCardCss($ts, $qrSize, $qrColor, $bgColor, $textColor, $sizes, $headerText);

    // Build cards HTML
    $cardsHtml = '';
    foreach ($students as $s) {
        $cardsHtml .= buildCardHtml($s, $template, $options, $ts, $fields);
    }

    // Nếu là compact, wrap trong grid
    $wrapperClass = ($template === 'compact') ? 'luoi-grid' : 'luoi-col';

    return '<style>' . $css . '</style><div class="' . $wrapperClass . '">' . $cardsHtml . '</div>';
}

/**
 * Generate CSS cho card thường
 */
function generateCardCss(array $ts, string $qrSize, string $qrColor, string $bgColor, string $textColor, array $sizes, string $headerText): string
{
    $showHeader = !empty($headerText);

    $css = '*{box-sizing:border-box;margin:0;padding:0}';
    $css .= 'body{font-family:"Be Vietnam Pro",system-ui,sans-serif}';
    $css .= '.luoi-col{display:flex;flex-wrap:wrap;gap:5mm;padding:3mm}';
    $css .= '.luoi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(45mm,1fr));gap:4mm;padding:3mm}';
    $css .= '.qrcard{background:' . $bgColor . ';border:' . $ts['border'] . ';border-radius:' . $ts['borderRadius'] . ';padding:' . $ts['padding'] . ';break-inside:avoid;page-break-inside:avoid;display:flex;flex-direction:column;align-items:center;text-align:center;max-width:55mm}';
    $css .= '.qr-wrapper{display:flex;justify-content:center;align-items:center;margin-bottom:2mm}';
    $css .= '.qr-wrapper canvas{width:' . $qrSize . '!important;height:' . $qrSize . '!important}';
    $css .= '.card-header{font-size:8px;font-weight:800;color:' . $textColor . ';text-transform:uppercase;letter-spacing:.3px;margin-bottom:2mm;width:100%}';
    $css .= '.card-text{color:' . $textColor . '}';
    $css .= '.card-code{font-size:' . $sizes['code'] . ';font-weight:800;letter-spacing:.3px;margin-bottom:1px}';
    $css .= '.card-name{font-size:' . $sizes['name'] . ';font-weight:700;line-height:1.25;margin-bottom:1px;word-break:break-word}';
    $css .= '.card-detail{font-size:' . $sizes['detail'] . ';color:#64748b}';
    $css .= '.card-logo{max-width:20mm;max-height:15mm;object-fit:contain;margin-bottom:2mm}';
    $css .= '.hole-punch{width:6mm;height:6mm;border-radius:50%;background:' . $bgColor . ';border:2px solid #e2e8f0;margin-bottom:2mm}';

    return $css;
}

/**
 * Generate CSS cho badge
 */
function generateBadgeCss(array $ts, string $qrSize, string $qrColor, string $bgColor, string $textColor, array $sizes, string $headerText): string
{
    return generateCardCss($ts, $qrSize, $qrColor, $bgColor, $textColor, $sizes, $headerText);
}

/**
 * Build HTML cho một card
 */
function buildCardHtml(array $student, string $template, array $options, array $ts, array $fields): string
{
    $qrColor = $options['qrColor'] ?? '#000000';
    $showLogo = !empty($options['showLogo']);
    $logoUrl = $options['logoUrl'] ?? '';
    $logoPosition = $options['logoPosition'] ?? 'top';
    $headerText = $options['headerText'] ?? '';
    $showEmblem = !empty($ts['showEmblem']);
    $holePunch = !empty($ts['holePunch']);

    // Build card content
    $content = '';

    // Hole punch for badge
    if ($holePunch) {
        $content .= '<div class="hole-punch"></div>';
    }

    // Header
    if (!empty($headerText)) {
        $content .= '<div class="card-header">' . htmlspecialchars($headerText, ENT_QUOTES, 'UTF-8') . '</div>';
    }

    // Logo (top)
    if ($showLogo && $logoPosition === 'top' && !empty($logoUrl)) {
        $content .= '<img class="card-logo" src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="Logo">';
    }

    // QR Code placeholder (sẽ được render bằng JS)
    $logoDataAttr = '';
    if ($showLogo && $logoPosition === 'center' && !empty($logoUrl)) {
        $logoDataAttr = ' data-logo="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" data-logo-pos="center"';
    }
    $content .= '<div class="qr-wrapper" data-code="' . htmlspecialchars($student['code'], ENT_QUOTES, 'UTF-8') . '" data-color="' . htmlspecialchars($qrColor, ENT_QUOTES, 'UTF-8') . '"' . $logoDataAttr . '></div>';

    // Logo (bottom)
    if ($showLogo && $logoPosition === 'bottom' && !empty($logoUrl)) {
        $content .= '<img class="card-logo" src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="Logo">';
    }

    // Text fields
    $content .= '<div class="card-text">';

    foreach ($fields as $field) {
        switch ($field) {
            case 'code':
                $content .= '<p class="card-code">' . htmlspecialchars($student['code'], ENT_QUOTES, 'UTF-8') . '</p>';
                break;
            case 'name':
                $holyName = !empty($student['holyName']) ? $student['holyName'] . ' ' : '';
                $content .= '<p class="card-name">' . htmlspecialchars($holyName . $student['name'], ENT_QUOTES, 'UTF-8') . '</p>';
                break;
            case 'holyName':
                if (!empty($student['holyName'])) {
                    $content .= '<p class="card-name">' . htmlspecialchars($student['holyName'], ENT_QUOTES, 'UTF-8') . '</p>';
                }
                break;
            case 'className':
                if (!empty($student['className'])) {
                    $content .= '<p class="card-detail">Lớp: ' . htmlspecialchars($student['className'], ENT_QUOTES, 'UTF-8') . '</p>';
                }
                break;
            case 'block':
                if (!empty($student['block'])) {
                    $content .= '<p class="card-detail">' . htmlspecialchars($student['block'], ENT_QUOTES, 'UTF-8') . '</p>';
                }
                break;
            case 'birthDate':
                if (!empty($student['birthDate'])) {
                    $content .= '<p class="card-detail">Sinh: ' . htmlspecialchars($student['birthDate'], ENT_QUOTES, 'UTF-8') . '</p>';
                }
                break;
        }
    }

    $content .= '</div>';

    return '<div class="qrcard">' . $content . '</div>';
}

/**
 * Format date
 */
function formatDate(string $date): string
{
    if (empty($date)) return '';
    $d = date_create($date);
    if (!$d) return $date;
    return date_format($d, 'd/m/Y');
}

/**
 * Format bytes
 */
function formatBytes(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / (1024 * 1024), 1) . ' MB';
}
