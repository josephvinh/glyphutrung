<?php
/**
 * PERSONAL SERVICE
 *
 * Quản lý hồ sơ cá nhân + lịch sử phục vụ của nhân sự (T2.2).
 * Tách logic nghiệp vụ ra khỏi personal.php để giữ API endpoint gọn nhẹ,
 * đồng thời giữ phong cách static method để nơi gọi dùng trực tiếp
 * (PersonalService::method(...)) — không cần qua constructor/instance.
 *
 * Quy ước dữ liệu (xem tổng quan trong task-2.2-brief):
 *   - school_years.name có dạng "2026 - 2027" (CÓ KHOẢNG TRẮNG quanh dấu "-"),
 *     khác với member_service_history.year_label lưu dạng "2026-2027" (KHÔNG
 *     có khoảng trắng). Hai dạng này là HAI CỘT KHÁC NHAU, đừng lẫn.
 *   - updateGlvYear() tự sinh dòng history với is_auto=1; saveServiceHistory()
 *     và deleteServiceHistory() từ chối nếu trùng năm đã có is_auto=1.
 *   - getAssignmentForYear() tra CỨU school_years THEO TÊN CÓ KHOẢNG TRẮNG,
 *     không phải year_label của history.
 */

require_once __DIR__ . '/../../config/db.php';

class PersonalService
{
    /** Lấy niên khoá hiện tại (is_current=1), hoặc null nếu chưa có. */
    private static function currentSchoolYear(): ?array
    {
        return db_one("SELECT * FROM school_years WHERE is_current = 1 LIMIT 1");
    }

    // =================================================================
    // SERVICE HISTORY
    // =================================================================

    /**
     * Lấy TOÀN BỘ lịch sử phục vụ của một thành viên, năm mới nhất trước.
     * Join sang blocks/classes/roles để giao diện hiển thị thẳng tên (đỡ phải
     * map id ở client).
     */
    public static function getServiceHistory(int $memberId): array
    {
        return db_all(
            "SELECT h.*,
                    b.name AS block_name,
                    c.name AS class_name,
                    r.label AS role_label
               FROM member_service_history h
               LEFT JOIN blocks  b ON b.id = h.block_id
               LEFT JOIN classes c ON c.id = h.class_id
               LEFT JOIN roles   r ON r.code = h.role_code
              WHERE h.member_id = ?
              ORDER BY h.year_label DESC",
            [$memberId]
        );
    }

    /**
     * Thêm hoặc cập nhật một dòng lịch sử NHẬP TAY (is_auto = 0).
     * Từ chối nếu năm đó đã là dòng do hệ thống tự sinh (is_auto = 1):
     * năm đó phản ánh phân công thật trong CSDL, không thể bị ghi đè bằng
     * tay — sửa phân công ở màn Khối & Lớp, không sửa ở đây.
     *
     * @param int   $memberId
     * @param array $data    Khóa dùng: year_label, role_code, block_id, class_id, note
     * @param int   $createdBy
     * @return array{ok: true}
     * @throws Exception Nếu year_label trùng dòng is_auto = 1
     */
    public static function saveServiceHistory(int $memberId, array $data, int $createdBy): array
    {
        $yearLabel = (string) ($data['year_label'] ?? '');
        if ($yearLabel === '') {
            throw new Exception('Thiếu năm học cho dòng lịch sử.');
        }
        // Backend phải validate (defense in depth — frontend cũng check ở
        // personal.js nhưng API có thể bị gọi từ nơi khác).
        if (!preg_match('/^\d{4}-\d{4}$/', $yearLabel)) {
            throw new Exception('Năm học phải có dạng YYYY-YYYY (ví dụ 2024-2025).');
        }
        // Bắt buộc năm sau = năm trước + 1 (ví dụ 2024-2025) để khớp
        // định dạng lưu ở school_years; tránh người dùng nhập "2024-2030".
        $parts = explode('-', $yearLabel);
        if ((int) $parts[1] !== (int) $parts[0] + 1) {
            throw new Exception('Năm học phải có dạng YYYY-(YYYY+1), ví dụ 2024-2025.');
        }

        $existing = db_one(
            "SELECT id, is_auto FROM member_service_history
              WHERE member_id = ? AND year_label = ?",
            [$memberId, $yearLabel]
        );
        if ($existing && (int) $existing['is_auto'] === 1) {
            throw new Exception('Không thể sửa năm được tự động tạo từ hệ thống.');
        }

        // ON DUPLICATE KEY UPDATE theo uk_member_year (member_id, year_label).
        db_run(
            "INSERT INTO member_service_history
                 (member_id, year_label, role_code, block_id, class_id,
                  is_auto, note, created_by)
             VALUES (?,?,?,?,?, 0, ?, ?)
             ON DUPLICATE KEY UPDATE
                 role_code  = VALUES(role_code),
                 block_id   = VALUES(block_id),
                 class_id   = VALUES(class_id),
                 is_auto    = 0,
                 note       = VALUES(note),
                 created_by = VALUES(created_by)",
            [
                $memberId,
                $yearLabel,
                $data['role_code'] ?? null,
                $data['block_id']  ?? null,
                $data['class_id']  ?? null,
                $data['note']      ?? null,
                $createdBy,
            ]
        );

        return ['ok' => true];
    }

    /**
     * Xoá một dòng lịch sử NHẬP TAY. Từ chối nếu dòng đó là is_auto = 1.
     *
     * @throws Exception Nếu không tìm thấy dòng HOẶC dòng là is_auto = 1
     */
    public static function deleteServiceHistory(int $memberId, string $yearLabel): array
    {
        $row = db_one(
            "SELECT id, is_auto FROM member_service_history
              WHERE member_id = ? AND year_label = ?",
            [$memberId, $yearLabel]
        );
        if (!$row) {
            throw new Exception('Không tìm thấy năm phục vụ.');
        }
        if ((int) $row['is_auto'] === 1) {
            throw new Exception('Không thể xóa năm được tự động tạo từ hệ thống.');
        }

        db_run("DELETE FROM member_service_history WHERE id = ?", [$row['id']]);
        return ['ok' => true];
    }

    // =================================================================
    // GLV YEAR
    // =================================================================

    /**
     * Cập nhật năm lên GLV (members.glv_year) và tái dựng các dòng lịch sử
     * tự động từ năm đó tới năm hiện tại.
     *
     * Dùng năm học HIỆN TẠI (is_current=1) thay vì date('Y') để tránh
     * tháng 1-8 sinh dòng cho năm học chưa bắt đầu.
     *
     * Quy tắc:
     *   - Nếu $glvYear = null: chỉ xoá mọi dòng is_auto = 1 (coi như người
     *     này không còn là GLV do hệ thống quản).
     *   - Nếu $glvYear = N: xoá mọi dòng is_auto = 1 rồi sinh lại từ
     *     N..currentYear. Năm đầu (N) ghi note 'Năm đầu làm GLV'.
     *   - Mỗi năm tra cứu phân công thật qua getAssignmentForYear() để
     *     lấy role/block/class; nếu không có thì mặc định role = 'glv'.
     *
     * Bọc trong transaction để lỗi giữa chừng rollback toàn bộ — test
     * "rollback" mới có ý nghĩa thật.
     *
     * @throws Exception Nếu dải năm bị tái dựng có dòng is_auto = 0.
     */
    public static function updateGlvYear(int $memberId, ?int $glvYear, int $updatedBy): array
    {
        // Kiểm tra thành viên tồn tại trước khi đụng đến bảng.
        $member = db_one("SELECT id, glv_year FROM members WHERE id = ?", [$memberId]);
        if (!$member) {
            throw new Exception('Không tìm thấy thành viên.');
        }

        if ($glvYear !== null) {
            // Bảo toàn dữ liệu nhập tay: nếu trong dải năm [N..hiện tại] đã
            // có dòng is_auto = 0, BÁO LỖI và KHÔNG đụng đến members.glv_year
            // cũng như các dòng lịch sử — user phải xoá tay trước rồi chạy lại.
            // Dùng niên khoá hiện tại (is_current=1) thay vì date('Y') để
            // tháng 1–8 không sinh dòng cho năm học chưa bắt đầu.
            $currentSchoolYear = self::currentSchoolYear();
            $currentYear = $currentSchoolYear
                ? (int) explode(' - ', $currentSchoolYear['name'])[0]
                : (int) date('Y');
            $conflicts = db_all(
                "SELECT year_label FROM member_service_history
                  WHERE member_id = ?
                    AND is_auto = 0
                    AND year_label BETWEEN ? AND ?
                  ORDER BY year_label",
                [
                    $memberId,
                    self::yearLabelFromYear($glvYear),
                    self::yearLabelFromYear($currentYear),
                ]
            );
            if (!empty($conflicts)) {
                $labels = array_column($conflicts, 'year_label');
                throw new Exception(
                    'Không thể tái dựng: các năm sau đã có dòng nhập tay '
                    . '(cần xóa trước khi đặt năm GLV): '
                    . implode(', ', $labels) . '.'
                );
            }

            // Không có xung đột → cập nhật glv_year, dọn dòng tự sinh cũ,
            // rồi tái dựng dải [glvYear..currentYear] với is_auto = 1.
            // Bọc trong transaction để lỗi giữa chừng rollback toàn bộ.
            db()->beginTransaction();
            try {
                db_run(
                    "UPDATE members SET glv_year = ? WHERE id = ?",
                    [$glvYear, $memberId]
                );
                db_run(
                    "DELETE FROM member_service_history
                      WHERE member_id = ? AND is_auto = 1",
                    [$memberId]
                );

                for ($year = $glvYear; $year <= $currentYear; $year++) {
                    $assignment = self::getAssignmentForYear($memberId, $year);
                    $note = ($year === $glvYear) ? 'Năm đầu làm GLV' : null;

                    db_run(
                        "INSERT INTO member_service_history
                             (member_id, year_label, role_code, block_id, class_id,
                              is_auto, note, created_by)
                         VALUES (?,?,?,?,?, 1, ?, ?)",
                        [
                            $memberId,
                            self::yearLabelFromYear($year),
                            $assignment['role_code'] ?? 'glv',
                            $assignment['block_id']  ?? null,
                            $assignment['class_id']  ?? null,
                            $note,
                            $updatedBy,
                        ]
                    );
                }
                db()->commit();
            } catch (Throwable $e) {
                db()->rollBack();
                throw $e;
            }
        } else {
            // null = xoá sạch các dòng tự sinh để history chỉ còn dòng nhập tay.
            db_run(
                "UPDATE members SET glv_year = NULL WHERE id = ?",
                [$memberId]
            );
            db_run(
                "DELETE FROM member_service_history
                  WHERE member_id = ? AND is_auto = 1",
                [$memberId]
            );
        }

        return ['ok' => true, 'glv_year' => $glvYear];
    }

    /**
     * Tra cứu phân công thật của thành viên trong một năm học cụ thể.
     *
     * BƯỚC QUAN TRỌNG: bảng school_years.name CÓ KHOẢNG TRẮNG quanh dấu gạch
     * ngang — "2024 - 2025" — KHÁC với member_service_history.year_label
     * lưu "2024-2025". Hai bảng dùng HAI ĐỊNH DẠNG KHÁC NHAU; truy vấn
     * WHERE name = ? phải nộp chuỗi CÓ KHOẢNG TRẮNG, nếu không sẽ trượt
     * sang NULL và mất toàn bộ history.
     *
     * @return array{role_code:string,block_id:?int,class_id:?int}|null
     */
    private static function getAssignmentForYear(int $memberId, int $year): ?array
    {
        $schoolYearName = "{$year} - " . ($year + 1);

        $row = db_one(
            "SELECT id FROM school_years WHERE name = ?",
            [$schoolYearName]
        );
        if (!$row) {
            return null;
        }

        // Khoảng hoạt động của một phân công trong năm học: từ 01/09 đến 31/08
        // năm sau. Assignment bắt đầu TRƯỚC 01/09 (phép gán đầu năm) nhưng vẫn
        // đang hiệu lực → dùng giao khoảng: bắt đầu <= cuối năm VÀ (kết thúc
        // NULL hoặc >= đầu năm). Lấy phân công primary trước.
        // Lưu ý: bảng member_assignments hiện không có school_year_id, nên
        // lọc theo tên năm học thông qua từ khoá date chồng khoảng.
        return db_one(
            "SELECT ma.role_code, ma.block_id, ma.class_id
               FROM member_assignments ma
              WHERE ma.member_id = ?
                AND ma.from_date <= CONCAT(?, '-08-31')
                AND (ma.to_date IS NULL OR ma.to_date >= CONCAT(?, '-09-01'))
              ORDER BY ma.is_primary DESC
              LIMIT 1",
            [$memberId, $year, $year]
        );
    }

    /** "2024" -> "2024-2025" (định dạng year_label của member_service_history) */
    private static function yearLabelFromYear(int $year): string
    {
        return $year . '-' . ($year + 1);
    }

    /**
     * Số năm phục vụ = năm hiện tại - glv_year. null khi chưa lên GLV.
     * Để riêng (không dùng trong updateGlvYear) để giao diện cá nhân dùng
     * hiển thị "Thâm niên: 5 năm" ở hồ sơ.
     */
    public static function calculateYearsOfService(?int $glvYear): ?int
    {
        if ($glvYear === null) {
            return null;
        }
        return (int) date('Y') - $glvYear;
    }

    // =================================================================
    // MEMBER STATUS
    // =================================================================

    /** Trạng thái hợp lệ cho thành viên. Trùng với ENUM ở bảng members. */
    private const VALID_STATUSES = ['đang phục vụ', 'tạm nghỉ', 'đã nghỉ'];

    /**
     * Đổi trạng thái thành viên kèm ghi chú (ghi vào register_note để
     * tránh thêm cột mới — đã có sẵn cho "chờ duyệt" và phù hợp để chứa
     * lý do "tạm nghỉ thai sản" / "đã nghỉ vì chuyển công tác").
     *
     * Chỉ ghi đè register_note khi $note !== null để tránh mất lời nhắn
     * đăng ký ban đầu nếu case gọi truyền note=null (giữ tương thích
     * ngược với personal.php).
     *
     * @throws Exception Nếu $newStatus không thuộc ba giá trị hợp lệ
     */
    public static function changeMemberStatus(int $memberId, string $newStatus, ?string $note): array
    {
        if (!in_array($newStatus, self::VALID_STATUSES, true)) {
            throw new Exception('Trạng thái không hợp lệ.');
        }

        if ($note !== null) {
            db_run(
                "UPDATE members SET status = ?, register_note = ? WHERE id = ?",
                [$newStatus, $note, $memberId]
            );
        } else {
            db_run(
                "UPDATE members SET status = ? WHERE id = ?",
                [$newStatus, $memberId]
            );
        }

        return ['ok' => true, 'status' => $newStatus];
    }
}
