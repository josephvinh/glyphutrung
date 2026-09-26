<?php
/**
 * DANH MỤC QUÀ — đổi Mộc lấy quà.
 *
 *   POST api/gifts.php?action=list    {}                              -> { gifts: [...] }
 *   POST api/gifts.php?action=save    { id, name, stampCost, stock,
 *          imageUrl, status, sortOrder }
 *   POST api/gifts.php?action=delete  { id }
 *
 * Quyền: 'view' module gifts để xem danh mục, 'edit' để thêm/sửa/xoá
 * (mặc định: Quản trị, Ban điều hành, Thủ Từ — xem migration 003).
 */

require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_gifts.php';

$action = $_GET['action'] ?? '';
$in     = json_input();

switch ($action) {

    // -------------------------------------------------------------
    case 'list':
        require_permission('gifts', 'view');

        $rows = db_all('SELECT * FROM gifts ORDER BY sort_order, id');
        json_out(['ok' => true, 'gifts' => array_map('gift_row_out', $rows)]);

    // -------------------------------------------------------------
    case 'save':
        require_write();
        require_permission('gifts', 'edit');

        $err = gift_validate($in);
        if ($err) json_fail($err);

        $id     = (int) ($in['id'] ?? 0);
        $name   = trim((string) ($in['name'] ?? ''));
        $cost   = (int) ($in['stampCost'] ?? 0);
        $stock  = (int) ($in['stock'] ?? 0);
        $image  = trim((string) ($in['imageUrl'] ?? '')) ?: null;
        $status = ($in['status'] ?? 'còn bán') === 'ẩn' ? 'ẩn' : 'còn bán';
        $sort   = (int) ($in['sortOrder'] ?? 1);

        $cols = 'name=?, stamp_cost=?, stock=?, image_url=?, status=?, sort_order=?';
        $vals = [$name, $cost, $stock, $image, $status, $sort];

        if ($id > 0) {
            if (!db_one('SELECT id FROM gifts WHERE id=?', [$id])) json_fail('Không tìm thấy quà.', 404);
            db_run("UPDATE gifts SET $cols WHERE id=?", array_merge($vals, [$id]));
            log_action('sua', 'gifts', 'Sửa quà ' . $name, $cost . ' mộc · tồn ' . $stock);
        } else {
            $id = db_insert("INSERT INTO gifts SET $cols", $vals);
            log_action('tao', 'gifts', 'Tạo quà ' . $name, $cost . ' mộc · tồn ' . $stock);
        }

        Cache::flush();   // danh mục quà đổi -> mọi người nạp lại thấy ngay
        json_out(['ok' => true, 'id' => $id]);

    // -------------------------------------------------------------
    case 'delete':
        require_write();
        require_permission('gifts', 'edit');

        $id   = (int) ($in['id'] ?? 0);
        $gift = db_one('SELECT name FROM gifts WHERE id=?', [$id]);
        if (!$gift) json_fail('Không tìm thấy quà.', 404);

        // Quà đã có đơn đổi (gift_order_items tham chiếu tới) thì KHÔNG xoá
        // — mất dữ liệu lịch sử đổi quà của các em. Từ chối hẳn, giống hệt
        // cách programs.php từ chối xoá chương trình đã có điểm danh (chỉ
        // báo lỗi, KHÔNG tự đổi dữ liệu); muốn ẩn thì Quản trị/BĐH tự vào
        // sửa quà và chọn trạng thái "ẩn".
        if (gift_is_referenced($id)) {
            json_fail('Quà đã có trong đơn đổi — không thể xoá. Hãy ẨN quà thay vì xoá.');
        }

        db_run('DELETE FROM gifts WHERE id=?', [$id]);
        log_action('xoa', 'gifts', 'Xóa quà ' . $gift['name'], '');

        Cache::flush();
        json_out(['ok' => true]);

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 400);
}
