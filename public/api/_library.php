<?php
/**
 * THƯ VIỆN — helper dùng chung cho library.php (API) và library_file.php (phục vụ file).
 *
 * Trọng tâm là AN TOÀN UPLOAD:
 *   - Chỉ nhận đúng loại file trong danh sách trắng, kiểm bằng MIME THẬT (finfo).
 *   - File lưu NGOÀI thư mục web, tên NGẪU NHIÊN, không ai đoán URL.
 *   - Khi phục vụ: readfile với Content-Type do MÁY CHỦ quyết (không tin file),
 *     kèm nosniff + Content-Disposition -> file không thể "chạy" như mã.
 */

/** Bản đồ MIME thật -> đuôi chuẩn. Chỉ những gì có ở đây mới được nhận. */
function library_mime_map(): array
{
    return [
        'application/pdf'  => 'pdf',
        'image/jpeg'       => 'jpg',
        'image/png'        => 'png',
        'image/webp'       => 'webp',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    ];
}

/** Đuôi -> Content-Type khi phục vụ (máy chủ tự đặt, không lấy từ file). */
function library_mime_for_ext(string $ext): string
{
    static $m = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png'  => 'image/png',  'webp' => 'image/webp',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];
    return $m[$ext] ?? 'application/octet-stream';
}

function library_config(string $key)
{
    $c = app_config('library') ?? [];
    return $c[$key] ?? null;
}

/** Thư mục lưu file, tạo nếu chưa có. */
function library_storage_dir(): string
{
    $dir = library_config('storage_path') ?: (__DIR__ . '/../../storage/library');
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return rtrim($dir, '/\\');
}

/** Đuôi được xem trực tiếp (pdf/ảnh). */
function library_is_viewable(string $ext): bool
{
    return in_array(strtolower($ext), library_config('allowed_view') ?: [], true);
}

/**
 * Kiểm & chốt loại một file vừa upload. Trả ['ext'=>, 'mime'=>].
 * Ném json_fail (dừng luôn) nếu không hợp lệ.
 *
 * @param array $file phần tử của $_FILES
 */
function library_validate_upload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        json_fail('Chưa chọn được file hợp lệ. Vui lòng thử lại.');
    }

    $maxBytes = (int) (library_config('max_size_mb') ?: 15) * 1024 * 1024;
    if (($file['size'] ?? 0) <= 0)          json_fail('File rỗng.');
    if (($file['size'] ?? 0) > $maxBytes)   json_fail('File quá lớn (tối đa ' . library_config('max_size_mb') . 'MB).');

    // MIME THẬT — không tin phần mở rộng người gửi.
    $fi   = new finfo(FILEINFO_MIME_TYPE);
    $mime = $fi->file($file['tmp_name']) ?: '';
    $map  = library_mime_map();

    $ext = $map[$mime] ?? null;

    // docx/pptx là ZIP: một số hệ thống finfo báo application/zip. Chỉ khi đó
    // mới cho phép suy từ đuôi CLIENT — và chỉ với loại TẢI-VỀ (office), không
    // bao giờ với loại xem-trực-tiếp. Office tải về nên không chạy inline.
    if ($ext === null && in_array($mime, ['application/zip', 'application/octet-stream'], true)) {
        $clientExt = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $dl = library_config('allowed_download') ?: [];
        if (in_array($clientExt, $dl, true)) $ext = $clientExt;
    }

    if ($ext === null) {
        json_fail('Loại file không được hỗ trợ. Chỉ nhận PDF, ảnh (JPG/PNG/WEBP), Word, PowerPoint.');
    }

    return ['ext' => $ext, 'mime' => $mime];
}

/** Sinh tên file lưu trên đĩa: ngẫu nhiên + đuôi chuẩn. */
function library_random_name(string $ext): string
{
    return bin2hex(random_bytes(16)) . '.' . $ext;
}

/** Làm sạch tên gốc để hiển thị / đặt khi tải về (bỏ đường dẫn, ký tự nguy hiểm). */
function library_clean_original(string $name): string
{
    $name = basename(str_replace('\\', '/', $name));            // bỏ mọi đường dẫn
    $name = preg_replace('/[\x00-\x1F"\\\\]/u', '', $name);      // bỏ ký tự điều khiển & dấu ngoặc kép
    $name = trim(preg_replace('/\s+/u', ' ', $name));
    if ($name === '') $name = 'tai-lieu';
    return mb_substr($name, 0, 200, 'UTF-8');
}

/** Tên an toàn để nhét vào header Content-Disposition filename="...". */
function library_download_filename(string $name): string
{
    // Bỏ ký tự có thể phá header; giữ tối đa tương thích.
    return preg_replace('/[\r\n"]/', '', $name);
}

/** Định dạng một dòng library_items để trả cho client. KHÔNG lộ stored_name. */
function library_row_out(array $r): array
{
    $type   = $r['item_type'] ?? 'file';
    $isFile = $type === 'file';
    $ext    = $isFile ? strtolower(pathinfo($r['stored_name'] ?? '', PATHINFO_EXTENSION)) : '';
    return [
        'id'           => (int) $r['id'],
        'type'         => $type,                        // 'file' | 'article'
        'title'        => $r['title'],
        'description'  => $r['description'] ?? null,
        'body'         => $type === 'article' ? ($r['body'] ?? '') : null,  // sổ tay: nội dung chữ
        'categoryId'   => $r['category_id'] ? (int) $r['category_id'] : null,
        'categoryName' => $r['category_name'] ?? null,
        'ext'          => $ext,
        'viewable'     => $isFile && library_is_viewable($ext),
        'sizeKb'       => (int) round(((int) ($r['size_bytes'] ?? 0)) / 1024),
        'status'       => $r['status'],
        'rejectReason' => $r['reject_reason'] ?? null,
        'uploaderName' => $r['uploader_name'] ?? null,
        'createdAt'    => $r['created_at'] ?? null,
        'fileUrl'      => $isFile ? ('api/library_file.php?id=' . (int) $r['id']) : null,
    ];
}
