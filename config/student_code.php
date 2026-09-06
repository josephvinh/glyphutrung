<?php
/**
 * SINH MÃ THIẾU NHI
 *
 * Quy ước: <PREFIX><2 số năm nhập đoàn><4 số thứ tự>  ->  GDGLPT260001
 *
 * - Năm nhập = năm bắt đầu của niên khoá em ghi danh lần đầu (2026-2027 -> 26).
 * - Số thứ tự đếm theo TỪNG năm, toàn đoàn (mọi lớp chung một chuỗi), bắt đầu
 *   lại từ 0001 mỗi năm. Vì đoạn năm khác nhau nên không bao giờ trùng.
 * - Mã KHÔNG chứa lớp -> bền suốt đời em, lên lớp không đổi, thẻ QR in một lần.
 *
 * Luôn sinh ở MÁY CHỦ (thấy toàn bộ bảng students, cấp nguyên tử) — client
 * không tự đoán để tránh trùng/đụng độ khi nhiều người thêm cùng lúc.
 *
 * Cần config/db.php đã nạp (dùng db_one) trước khi GỌI các hàm này.
 */

const STUDENT_CODE_PREFIX = 'GDGLPT';

/** 2 số cuối năm bắt đầu của một niên khoá (mảng có khoá 'name'). */
function year_two_digit(?array $year): int
{
    if ($year && preg_match('/(\d{4})/', (string) ($year['name'] ?? ''), $m)) {
        return ((int) $m[1]) % 100;
    }
    return (int) date('y');
}

/** Mã kế tiếp cho một năm nhập (2 số): PREFIX + yy + số thứ tự 4 chữ số. */
function next_student_code(int $year2): string
{
    $prefix = STUDENT_CODE_PREFIX . sprintf('%02d', $year2);
    $row = db_one(
        "SELECT MAX(CAST(SUBSTRING(code, ?) AS UNSIGNED)) AS mx
           FROM students WHERE code LIKE ?",
        [strlen($prefix) + 1, $prefix . '%']
    );
    return $prefix . sprintf('%04d', ((int) ($row['mx'] ?? 0)) + 1);
}
