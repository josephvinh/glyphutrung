<?php
/**
 * THƯ VIỆN TÀI LIỆU — API.
 *
 *   GET  ?action=categories         danh sách chủ đề
 *   GET  ?action=list[&category=&q=&page=] tài liệu đã duyệt (lọc/tìm/phân trang)
 *   GET  ?action=mine[&q=&page=]    tài liệu của tôi (mọi trạng thái)
 *   GET  ?action=pending[&q=&page=] hàng chờ duyệt (cần edit)
 *   POST ?action=upload             đăng mới (multipart) -> chờ duyệt
 *   POST ?action=updateItem         sửa tiêu đề/mô tả/chủ đề, thay hoặc gỡ tệp
 *   POST ?action=approve|reject     duyệt/từ chối (cần edit)
 *   POST ?action=delete             gỡ (của mình; edit gỡ bất kỳ)
 *   POST ?action=saveCategory|toggleCategory  quản chủ đề (cần edit)
 *
 * view = xem + đăng; edit = duyệt/gỡ bất kỳ/quản chủ đề.
 */
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_library.php';

$me      = require_permission('thu_vien', 'view');
$action  = $_GET['action'] ?? '';
$canEdit = permission_of('thu_vien') === 'edit';
$in      = json_input();   // multipart: trả về $_POST (các field chữ)

function library_need_edit(bool $canEdit): void
{
    if (!$canEdit) json_fail('Bạn không có quyền thực hiện việc này.', 403);
}

switch ($action) {

    case 'categories':
        json_out(['ok' => true, 'categories' => db_all(
            'SELECT id, name, is_active FROM library_categories ORDER BY sort_order, name')]);

    case 'list':
        // Filter đọc từ body (helper api() ở frontend luôn gửi POST kèm JSON).
        $where  = "i.status = 'da_duyet'";
        $params = [];
        if ($cat = (int) ($in['category'] ?? 0)) { $where .= ' AND i.category_id = ?'; $params[] = $cat; }
        $where .= library_search_sql((string) ($in['q'] ?? ''), $params);
        [$limit, $offset, $page] = library_page($in);
        $total = library_count($where, $params);
        $rows  = db_all(
            'SELECT i.*, c.name AS category_name, m.full_name AS uploader_name
               FROM library_items i
               LEFT JOIN library_categories c ON c.id = i.category_id
               LEFT JOIN members m ON m.id = i.uploaded_by
              WHERE ' . $where . '
              ORDER BY i.approved_at DESC, i.id DESC
              LIMIT ' . $limit . ' OFFSET ' . $offset, $params);
        json_out(['ok' => true, 'items' => array_map('library_row_out', $rows),
                  'total' => $total, 'page' => $page, 'hasMore' => ($offset + count($rows)) < $total]);

    case 'mine':
        $where  = 'i.uploaded_by = ?';
        $params = [$me['id']];
        $where .= library_search_sql((string) ($in['q'] ?? ''), $params);
        [$limit, $offset, $page] = library_page($in);
        $total = library_count($where, $params);
        $rows  = db_all(
            'SELECT i.*, c.name AS category_name
               FROM library_items i
               LEFT JOIN library_categories c ON c.id = i.category_id
              WHERE ' . $where . '
              ORDER BY i.id DESC
              LIMIT ' . $limit . ' OFFSET ' . $offset, $params);
        json_out(['ok' => true, 'items' => array_map('library_row_out', $rows),
                  'total' => $total, 'page' => $page, 'hasMore' => ($offset + count($rows)) < $total]);

    case 'pending':
        library_need_edit($canEdit);
        $where  = 'i.status = "cho_duyet"';
        $params = [];
        $where .= library_search_sql((string) ($in['q'] ?? ''), $params);
        [$limit, $offset, $page] = library_page($in);
        $total = library_count($where, $params);
        $rows  = db_all(
            'SELECT i.*, c.name AS category_name, m.full_name AS uploader_name
               FROM library_items i
               LEFT JOIN library_categories c ON c.id = i.category_id
               LEFT JOIN members m ON m.id = i.uploaded_by
              WHERE ' . $where . '
              ORDER BY i.id ASC
              LIMIT ' . $limit . ' OFFSET ' . $offset, $params);
        json_out(['ok' => true, 'items' => array_map('library_row_out', $rows),
                  'total' => $total, 'page' => $page, 'hasMore' => ($offset + count($rows)) < $total]);

    case 'upload':
        require_write();   // POST + CSRF (client gửi X-CSRF-TOKEN kèm FormData)
        $title = trim((string) ($in['title'] ?? ''));
        if ($title === '') json_fail('Vui lòng nhập tiêu đề.');
        $catId = (int) ($in['category_id'] ?? 0) ?: null;
        if ($catId && !db_one('SELECT id FROM library_categories WHERE id = ?', [$catId])) $catId = null;
        $desc  = trim((string) ($in['description'] ?? '')) ?: null;

        $file = $_FILES['file'] ?? null;
        if (!$file) json_fail('Chưa chọn file.');
        $v = library_validate_upload($file);   // ném json_fail nếu không hợp lệ

        $stored = library_random_name($v['ext']);
        $dest   = library_storage_dir() . '/' . $stored;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            json_fail('Không lưu được file lên máy chủ, vui lòng thử lại.', 500);
        }

        // BĐH/Admin đăng thì duyệt luôn — đồng bộ với saveArticle ở dưới.
        // Trước đây tệp của BĐH vẫn rơi vào "chờ duyệt" nên chính người có
        // quyền duyệt phải tự bấm duyệt bài của mình, trong khi bài viết sổ
        // tay thì không — cùng một người, hai luật khác nhau.
        $status = $canEdit ? 'da_duyet' : 'cho_duyet';
        db_run('INSERT INTO library_items
                  (title, description, category_id, stored_name, original_name,
                   mime_type, size_bytes, status, uploaded_by, approved_by, created_at, approved_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),' . ($canEdit ? 'NOW()' : 'NULL') . ')',
               [$title, $desc, $catId, $stored, library_clean_original((string) $file['name']),
                $v['mime'], (int) $file['size'], $status, $me['id'], $canEdit ? $me['id'] : null]);
        log_action('tao', 'thu_vien', 'Đăng tài liệu: ' . $title);
        Cache::flush();   // số mục chờ duyệt đổi -> chấm đỏ trên icon của BĐH cập nhật ngay
        json_out(['ok' => true, 'autoApproved' => $canEdit]);

    // Sửa MỘT MỤC đã đăng: đổi tiêu đề/mô tả/chủ đề, và với mục 'file' thì
    // có thể THAY TỆP. Người đăng sửa được mục của mình; edit sửa được mọi mục.
    case 'updateItem':
        require_write();
        $id = (int) ($in['id'] ?? 0);
        $it = db_one('SELECT * FROM library_items WHERE id = ?', [$id]);
        if (!$it) json_fail('Không tìm thấy mục cần sửa.', 404);
        if ((int) $it['uploaded_by'] !== (int) $me['id'] && !$canEdit) {
            json_fail('Bạn chỉ sửa được mục của mình.', 403);
        }

        $title = trim((string) ($in['title'] ?? ''));
        if ($title === '') json_fail('Vui lòng nhập tiêu đề.');
        $catId = (int) ($in['category_id'] ?? 0) ?: null;
        if ($catId && !db_one('SELECT id FROM library_categories WHERE id = ?', [$catId])) $catId = null;
        $desc = trim((string) ($in['description'] ?? '')) ?: null;

        // Sửa xong thì nội dung có thể đã khác bản đã duyệt -> đưa về chờ
        // duyệt lại. Riêng người có quyền duyệt thì giữ nguyên trạng thái.
        $status = $canEdit ? $it['status'] : 'cho_duyet';
        $set    = 'title=?, description=?, category_id=?, status=?';
        $args   = [$title, $desc, $catId, $status];

        // Có tệp mới? Thay tệp: lưu tệp mới TRƯỚC, chỉ xoá tệp cũ sau khi
        // ghi DB thành công — để lỡ có lỗi thì vẫn còn tệp mà phục vụ.
        $file = $_FILES['file'] ?? null;
        $oldStored = null;
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $v      = library_validate_upload($file);
            $stored = library_random_name($v['ext']);
            $dest   = library_storage_dir() . '/' . $stored;
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                json_fail('Không lưu được file lên máy chủ, vui lòng thử lại.', 500);
            }
            $oldStored = $it['stored_name'];
            $set  .= ', stored_name=?, original_name=?, mime_type=?, size_bytes=?';
            $args  = array_merge($args, [$stored, library_clean_original((string) $file['name']),
                                        $v['mime'], (int) $file['size']]);
        } elseif ((string) ($in['remove_file'] ?? '') === '1' && !empty($it['stored_name'])) {
            // Gỡ tệp khỏi mục (giữ lại phần chữ/mô tả).
            $oldStored = $it['stored_name'];
            $set  .= ', stored_name=NULL, original_name=NULL, mime_type=NULL, size_bytes=0';
        }

        $args[] = $id;
        db_run('UPDATE library_items SET ' . $set . ' WHERE id=?', $args);
        if ($oldStored) @unlink(library_storage_dir() . '/' . $oldStored);
        log_action('sua', 'thu_vien', 'Sửa mục thư viện: ' . $title);
        Cache::flush();
        json_out(['ok' => true, 'autoApproved' => $canEdit]);

    // Viết/sửa BÀI VIẾT sổ tay (nội dung chữ, không có file).
    case 'saveArticle':
        require_write();
        $title = trim((string) ($in['title'] ?? ''));
        $body  = trim((string) ($in['body'] ?? ''));
        if ($title === '') json_fail('Vui lòng nhập tiêu đề.');
        if ($body === '')  json_fail('Vui lòng nhập nội dung.');
        $catId = (int) ($in['category_id'] ?? 0) ?: null;
        if ($catId && !db_one('SELECT id FROM library_categories WHERE id = ?', [$catId])) $catId = null;
        $desc  = trim((string) ($in['description'] ?? '')) ?: null;
        $id    = (int) ($in['id'] ?? 0);

        if ($id) {
            $it = db_one('SELECT * FROM library_items WHERE id = ? AND item_type = "article"', [$id]);
            if (!$it) json_fail('Không tìm thấy bài viết.', 404);
            if ((int) $it['uploaded_by'] !== (int) $me['id'] && !$canEdit) {
                json_fail('Bạn chỉ sửa được bài của mình.', 403);
            }
            // GLV thường sửa -> quay lại chờ duyệt; BĐH sửa -> giữ nguyên trạng thái.
            $status = $canEdit ? $it['status'] : 'cho_duyet';
            db_run('UPDATE library_items SET title=?, description=?, body=?, category_id=?, status=? WHERE id=?',
                   [$title, $desc, $body, $catId, $status, $id]);
            log_action('sua', 'thu_vien', 'Sửa bài sổ tay: ' . $title);
        } else {
            // BĐH/Admin soạn sổ tay -> duyệt luôn; GLV thường -> chờ duyệt.
            $status = $canEdit ? 'da_duyet' : 'cho_duyet';
            db_run('INSERT INTO library_items
                      (title, item_type, description, body, category_id, status, uploaded_by, approved_by, created_at, approved_at)
                    VALUES (?, "article", ?, ?, ?, ?, ?, ?, NOW(), ' . ($canEdit ? 'NOW()' : 'NULL') . ')',
                   [$title, $desc, $body, $catId, $status, $me['id'], $canEdit ? $me['id'] : null]);
            log_action('tao', 'thu_vien', 'Viết bài sổ tay: ' . $title);
        }
        Cache::flush();
        json_out(['ok' => true, 'autoApproved' => $canEdit]);

    case 'approve':
        require_write();
        library_need_edit($canEdit);
        $it = db_one('SELECT * FROM library_items WHERE id = ?', [(int) ($in['id'] ?? 0)]);
        if (!$it) json_fail('Không tìm thấy tài liệu.', 404);
        db_run('UPDATE library_items SET status="da_duyet", approved_by=?, approved_at=NOW(),
                       reject_reason=NULL WHERE id=?', [$me['id'], $it['id']]);
        log_action('duyet', 'thu_vien', 'Duyệt tài liệu: ' . $it['title']);
        Cache::flush();
        json_out(['ok' => true]);

    case 'reject':
        require_write();
        library_need_edit($canEdit);
        $it = db_one('SELECT * FROM library_items WHERE id = ?', [(int) ($in['id'] ?? 0)]);
        if (!$it) json_fail('Không tìm thấy tài liệu.', 404);
        if (!empty($it['stored_name'])) @unlink(library_storage_dir() . '/' . $it['stored_name']);   // xoá file khỏi host (bài viết không có file)
        db_run('UPDATE library_items SET status="tu_choi", reject_reason=?, approved_by=?,
                       approved_at=NOW() WHERE id=?',
               [trim((string) ($in['reason'] ?? '')) ?: null, $me['id'], $it['id']]);
        log_action('sua', 'thu_vien', 'Từ chối tài liệu: ' . $it['title']);
        json_out(['ok' => true]);

    case 'delete':
        require_write();
        $it = db_one('SELECT * FROM library_items WHERE id = ?', [(int) ($in['id'] ?? 0)]);
        if (!$it) json_fail('Không tìm thấy tài liệu.', 404);
        if ((int) $it['uploaded_by'] !== (int) $me['id'] && !$canEdit) {
            json_fail('Bạn chỉ gỡ được tài liệu của mình.', 403);
        }
        if (!empty($it['stored_name'])) @unlink(library_storage_dir() . '/' . $it['stored_name']);
        db_run('DELETE FROM library_items WHERE id = ?', [$it['id']]);
        log_action('xoa', 'thu_vien', 'Gỡ tài liệu: ' . $it['title']);
        json_out(['ok' => true]);

    case 'saveCategory':
        require_write();
        library_need_edit($canEdit);
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '') json_fail('Vui lòng nhập tên chủ đề.');
        if ($id = (int) ($in['id'] ?? 0)) {
            db_run('UPDATE library_categories SET name=? WHERE id=?', [$name, $id]);
        } else {
            $next = (int) db_one('SELECT COALESCE(MAX(sort_order),0)+1 n FROM library_categories')['n'];
            db_run('INSERT INTO library_categories (name, sort_order) VALUES (?,?)', [$name, $next]);
        }
        json_out(['ok' => true]);

    case 'toggleCategory':
        require_write();
        library_need_edit($canEdit);
        db_run('UPDATE library_categories SET is_active = 1 - is_active WHERE id = ?', [(int) ($in['id'] ?? 0)]);
        json_out(['ok' => true]);
}

json_fail('Hành động không hợp lệ.', 400);
