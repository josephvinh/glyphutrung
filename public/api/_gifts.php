<?php
/**
 * DANH MỤC QUÀ — helper dùng chung cho gifts.php.
 *
 * Tách validate + định dạng dòng trả về khỏi handler để unit test được mà
 * không phải chạy qua toàn bộ endpoint (gifts.php đọc php://input và gọi
 * require_login() ngay khi được require, không hợp để test trực tiếp).
 */

/**
 * Kiểm dữ liệu quà trước khi lưu.
 * Trả về null nếu hợp lệ, hoặc thông điệp lỗi (tiếng Việt, hiện thẳng cho người dùng).
 */
function gift_validate(array $in): ?string
{
    $name  = trim((string) ($in['name'] ?? ''));
    $cost  = (int) ($in['stampCost'] ?? 0);
    $stock = (int) ($in['stock'] ?? 0);

    if ($name === '') return 'Vui lòng nhập tên quà.';
    if ($cost <= 0)    return 'Số Mộc đổi phải lớn hơn 0.';
    if ($stock < 0)    return 'Tồn kho không được âm.';
    return null;
}

/**
 * Quà này đã có đơn đổi nào tham chiếu tới chưa (gift_order_items.gift_id)?
 * Có -> KHÔNG được xoá (mất dữ liệu lịch sử đổi quà + gãy ràng buộc khoá
 * ngoại fk_gitem_gift). Tách riêng để test được nhánh quyết định của
 * case 'delete' mà không cần chạy qua toàn bộ endpoint.
 */
function gift_is_referenced(int $giftId): bool
{
    return db_one('SELECT id FROM gift_order_items WHERE gift_id=? LIMIT 1', [$giftId]) !== null;
}

/** Định dạng một dòng bảng `gifts` để trả JSON cho giao diện (camelCase). */
function gift_row_out(array $g): array
{
    return [
        'id'        => (int) $g['id'],
        'name'      => $g['name'],
        'stampCost' => (int) $g['stamp_cost'],
        'stock'     => (int) $g['stock'],
        'imageUrl'  => $g['image_url'],
        'status'    => $g['status'],
        'sortOrder' => (int) $g['sort_order'],
    ];
}
