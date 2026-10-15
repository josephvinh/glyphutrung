<?php
/**
 * PERSONAL SERVICE
 *
 * Quản lý hồ sơ cá nhân + lịch sử phục vụ của nhân sự (T2.2).
 * Tách logic nghiệp vụ ra khỏi personal.php để giữ API endpoint gọn nhẹ,
 * đồng thời giữ phong cách static method để nơi gọi dùng trực tiếp
 * (PersonalService::method(...)) — không cần qua constructor/instance.
 *
 * Cần nạp sau _bootstrap.php (trong_giao_dich, has_active_role, can_see_admin);
 * endpoint và tests/bootstrap.php đều đã làm vậy.
 *
 * Quy ước dữ liệu:
 *   - member_service_history.year_label lưu "2026-2027" (KHÔNG có khoảng trắng,
 *     hai năm liền nhau). Khác school_years.name ("2026 - 2027"). Niên khoá HIỆN TẠI
 *     lấy từ school_years.is_current (currentSchoolStartYear); còn việc một phân công
 *     thuộc năm học nào thì cắt theo lịch cố định 01/08 → 31/07 năm sau (assignmentForYear)
 *     — không phụ thuộc school_years có đủ hàng cho các năm cũ hay không, và mỗi
 *     ngày thuộc đúng MỘT năm học.
 *   - updateGlvYear() tự sinh dòng history với is_auto=1 cho dải năm học
 *     [N..năm học hiện tại]; saveServiceHistory() và deleteServiceHistory()
 *     từ chối nếu trùng năm đã có is_auto=1. updateGlvYear() cũng từ chối
 *     (throw) nếu trong dải đó đã có dòng is_auto=0 — bảo toàn dữ liệu nhập
 *     tay, yêu cầu xoá tay trước khi tái dựng. Toàn bộ chạy trong MỘT giao
 *     dịch: lỗi giữa chừng thì không mất dòng tự sinh cũ.
 *   - Lỗi nghiệp vụ (thông điệp cho người dùng) ném PersonalException; lỗi
 *     CSDL giữ nguyên PDOException để endpoint không lộ nội dung SQL ra ngoài.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/StaffService.php';

/** Lỗi nghiệp vụ có thể hiện nguyên văn cho người dùng. getCode() = mã HTTP (mặc định 400). */
class PersonalException extends \Exception
{
    public function __construct(string $message, int $httpCode = 400)
    {
        parent::__construct($message, $httpCode);
    }
}

class PersonalService
{
    /** Hạng vai trò khi một năm có nhiều phân công (giống recompute_member_primary + BĐH). */
    private const ROLE_RANK = [
        'bdh' => 5, 'truong_khoi' => 4, 'glv_chu_nhiem' => 3, 'glv' => 2, 'du_bi' => 1,
    ];

    // =================================================================
    // PHÂN QUYỀN THEO ĐỐI TƯỢNG
    // =================================================================

    /**
     * Người gọi ($me) có được thao tác $op trên hồ sơ của $memberId không?
     *
     *   $op = 'view' : xem hồ sơ + lịch sử phục vụ
     *   $op = 'edit' : ghi năm lên GLV / lịch sử / trạng thái
     *
     * $canEditStaff = người gọi có quyền module 'staff' = edit (Nhân sự) —
     * truyền vào (không đọc session ở đây) để kiểm thử đơn vị được. Cổng module
     * 'org' KHÔNG đủ: Trưởng khối có org=edit nhưng không được xem/sửa hồ sơ
     * nhân sự ngoài phạm vi của mình, càng không được khoá tài khoản người khác.
     *
     * Thứ tự kiểm (quan trọng — không cho dò được tài khoản qua mã lỗi/thời gian):
     *  1. Không có staff.edit: chỉ được XEM CHÍNH MÌNH. Mọi id khác (có, không có, hay
     *     là admin) trả 403 NGAY, không truy vấn gì — nên không phân biệt được 403/404.
     *  2. Không có thành viên: 404.
     *  3. Hồ sơ của chính mình: được (xem; BĐH/Quản trị tự sửa mình). Bỏ qua bước ẩn
     *     admin để người chỉ giữ phân công Quản trị vẫn xem được hồ sơ của mình.
     *  4. Mục tiêu là Quản trị (vai gốc hoặc phân công) mà người gọi không phải Quản
     *     trị: 404 GIỐNG HỆT id không tồn tại (F9).
     *  5. Xem người khác: được (đã có staff.edit). Ghi: StaffService::guardTarget($t, 'edit')
     *     (#97) — BĐH khác chỉ Quản trị mới sửa được.
     *
     * @return array{error:string,code:int}|null  null = được phép
     */
    public static function authorizeTarget(array $me, bool $canEditStaff, int $memberId, string $op): ?array
    {
        $self = $memberId === (int) $me['id'];

        if (!$canEditStaff && !($op === 'view' && $self)) {
            return $op === 'view'
                ? ['error' => 'Bạn chỉ được xem hồ sơ của chính mình.', 'code' => 403]
                : ['error' => 'Chỉ Ban Điều Hành mới cập nhật được hồ sơ phục vụ.', 'code' => 403];
        }

        $target = db_one('SELECT id, role_code FROM members WHERE id = ?', [$memberId]);
        if (!$target) {
            return ['error' => 'Không tìm thấy thành viên.', 'code' => 404];
        }
        if ($self) {
            return null;
        }

        if (($target['role_code'] === 'admin' || has_active_role($memberId, 'admin')) && !can_see_admin($me)) {
            return ['error' => 'Không tìm thấy thành viên.', 'code' => 404];
        }
        if ($op === 'view') {
            return null;
        }
        $deny = (new StaffService($me, 0, []))->guardTarget($target, 'edit');
        return $deny ? ['error' => $deny['error'], 'code' => (int) $deny['code']] : null;
    }

    // =================================================================
    // SERVICE HISTORY
    // =================================================================

    /**
     * Lấy TOÀN BỘ lịch sử phục vụ của một thành viên, năm mới nhất trước.
     * Join sang blocks/classes/roles để giao diện hiển thị thẳng tên (đỡ phải
     * map id ở client). Chọn cột tường minh: không trả created_by (id người nhập,
     * thường là Quản trị — lộ ra là làm lỏng lớp ẩn admin).
     */
    public static function getServiceHistory(int $memberId): array
    {
        return db_all(
            "SELECT h.id, h.member_id, h.year_label, h.role_code, h.block_id, h.class_id,
                    h.is_auto, h.note, h.created_at,
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
     * @throws PersonalException Nếu dữ liệu sai hoặc year_label trùng dòng is_auto = 1
     */
    public static function saveServiceHistory(int $memberId, array $data, int $createdBy): array
    {
        // Backend phải validate (defense in depth — frontend cũng check ở
        // personal.js nhưng API có thể bị gọi từ nơi khác).
        $yearLabel = trim((string) ($data['year_label'] ?? ''));
        if ($yearLabel === '') {
            throw new PersonalException('Thiếu năm học cho dòng lịch sử.');
        }
        if (!preg_match('/^(\d{4})-(\d{4})$/', $yearLabel, $m)) {
            throw new PersonalException('Năm học phải có dạng YYYY-YYYY (ví dụ 2024-2025).');
        }
        if ((int) $m[1] < 1900 || (int) $m[2] !== (int) $m[1] + 1) {
            throw new PersonalException('Năm học phải gồm hai năm liền nhau (ví dụ 2024-2025).');
        }

        $roleCode = trim((string) ($data['role_code'] ?? ''));
        if ($roleCode !== '' && ($roleCode === 'admin' || !db_one('SELECT 1 FROM roles WHERE code = ?', [$roleCode]))) {
            throw new PersonalException('Vai trò không hợp lệ.');
        }
        $blockId = self::existingId('blocks', $data['block_id'] ?? null, 'Khối không tồn tại.');
        $classId = self::existingId('classes', $data['class_id'] ?? null, 'Lớp không tồn tại.');
        $note    = trim((string) ($data['note'] ?? ''));
        if (mb_strlen($note) > 255) {
            throw new PersonalException('Ghi chú tối đa 255 ký tự.');
        }

        $existing = db_one(
            "SELECT id, is_auto FROM member_service_history
              WHERE member_id = ? AND year_label = ?",
            [$memberId, $yearLabel]
        );
        if ($existing && (int) $existing['is_auto'] === 1) {
            throw new PersonalException('Không thể sửa năm được tự động tạo từ hệ thống.');
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
                $roleCode === '' ? null : $roleCode,
                $blockId,
                $classId,
                $note === '' ? null : $note,
                $createdBy,
            ]
        );

        return ['ok' => true];
    }

    /**
     * Xoá một dòng lịch sử NHẬP TAY. Từ chối nếu dòng đó là is_auto = 1.
     *
     * @throws PersonalException Nếu không tìm thấy dòng HOẶC dòng là is_auto = 1
     */
    public static function deleteServiceHistory(int $memberId, string $yearLabel): array
    {
        $row = db_one(
            "SELECT id, is_auto FROM member_service_history
              WHERE member_id = ? AND year_label = ?",
            [$memberId, $yearLabel]
        );
        if (!$row) {
            throw new PersonalException('Không tìm thấy năm phục vụ.', 404);
        }
        if ((int) $row['is_auto'] === 1) {
            throw new PersonalException('Không thể xóa năm được tự động tạo từ hệ thống.');
        }

        db_run("DELETE FROM member_service_history WHERE id = ?", [$row['id']]);
        return ['ok' => true];
    }

    // =================================================================
    // GLV YEAR
    // =================================================================

    /**
     * Cập nhật năm lên GLV (members.glv_year) và tái dựng các dòng lịch sử
     * tự động từ năm đó tới NĂM HỌC hiện tại.
     *
     * Quy tắc:
     *   - Nếu $glvYear = null: đặt members.glv_year = NULL và xoá mọi dòng
     *     is_auto = 1 (coi như người này không còn là GLV do hệ thống quản).
     *   - Nếu $glvYear = N: trước khi đụng đến members.glv_year, kiểm tra
     *     dải [N..năm học hiện tại] đã có dòng is_auto = 0 nào chưa. Nếu có
     *     thì throw (không đổi gì cả) liệt kê các năm bị xung đột — bảo
     *     toàn dữ liệu nhập tay. Nếu không: cập nhật glv_year, xoá mọi
     *     dòng is_auto = 1 rồi sinh lại từ N..năm học hiện tại. Năm đầu (N)
     *     ghi note 'Năm đầu làm GLV'.
     *   - Mỗi năm tra phân công thật (assignmentForYear) để lấy role/block/
     *     class; không có thì mặc định role = 'glv'.
     *   - Tất cả trong một giao dịch: hỏng giữa chừng thì hoàn tác sạch.
     *
     * @throws PersonalException Nếu thành viên không tồn tại, năm không hợp lệ,
     *                           hoặc dải năm bị tái dựng có dòng is_auto = 0.
     */
    public static function updateGlvYear(int $memberId, ?int $glvYear, int $updatedBy): array
    {
        if (!db_one('SELECT 1 FROM members WHERE id = ?', [$memberId])) {
            throw new PersonalException('Không tìm thấy thành viên.', 404);
        }
        // Chặn trên = năm bắt đầu của NĂM HỌC hiện tại (không phải năm dương lịch): từ tháng 1 đến 7 năm
        // dương lịch đã sang nhưng năm học mới chưa bắt đầu — cho nhập thì glv_year trỏ vào năm học chưa
        // có, vòng tái dựng không sinh dòng nào (kể cả "Năm đầu làm GLV") và xoá hết dòng tự sinh cũ.
        $latest = self::currentSchoolStartYear();
        if ($glvYear !== null && ($glvYear < 1900 || $glvYear > $latest)) {
            throw new PersonalException(
                "Năm không hợp lệ: phải từ 1900 đến {$latest} (năm bắt đầu niên khoá hiện tại).");
        }

        return trong_giao_dich(function () use ($memberId, $glvYear, $updatedBy, $latest) {
            if ($glvYear === null) {
                // null = xoá sạch các dòng tự sinh để history chỉ còn dòng nhập tay.
                db_run("UPDATE members SET glv_year = NULL WHERE id = ?", [$memberId]);
                db_run("DELETE FROM member_service_history WHERE member_id = ? AND is_auto = 1", [$memberId]);
                return ['ok' => true, 'glv_year' => null];
            }

            // Bảo toàn dữ liệu nhập tay: nếu trong dải năm [N..hiện tại] đã
            // có dòng is_auto = 0, BÁO LỖI và KHÔNG đụng đến members.glv_year
            // cũng như các dòng lịch sử — user phải xoá tay trước rồi chạy lại.
            $conflicts = db_all(
                "SELECT year_label FROM member_service_history
                  WHERE member_id = ?
                    AND is_auto = 0
                    AND year_label BETWEEN ? AND ?
                  ORDER BY year_label",
                [$memberId, self::yearLabelFromYear($glvYear), self::yearLabelFromYear($latest)]
            );
            if (!empty($conflicts)) {
                throw new PersonalException(
                    'Không thể tái dựng: các năm sau đã có dòng nhập tay '
                    . '(cần xóa trước khi đặt năm GLV): '
                    . implode(', ', array_column($conflicts, 'year_label')) . '.'
                );
            }

            // Không có xung đột → cập nhật glv_year, dọn dòng tự sinh cũ,
            // rồi tái dựng dải [glvYear..năm học hiện tại] với is_auto = 1.
            db_run("UPDATE members SET glv_year = ? WHERE id = ?", [$glvYear, $memberId]);
            db_run("DELETE FROM member_service_history WHERE member_id = ? AND is_auto = 1", [$memberId]);

            $assignments = self::loadAssignments($memberId);
            for ($year = $glvYear; $year <= $latest; $year++) {
                $assignment = self::assignmentForYear($assignments, $year);
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
                        $year === $glvYear ? 'Năm đầu làm GLV' : null,
                        $updatedBy,
                    ]
                );
            }

            return ['ok' => true, 'glv_year' => $glvYear];
        });
    }

    /**
     * Năm BẮT ĐẦU của niên khoá hiện tại = niên khoá đang mở (school_years.is_current = 1,
     * cùng nguồn với current_year() ở mọi nơi trong app; tên dạng "2026 - 2027" → 2026).
     * Không dùng năm dương lịch: tháng 1–7 đã sang năm mới nhưng niên khoá vẫn là năm trước.
     * Chưa có niên khoá nào đang mở thì suy từ ngày (schoolStartYearFromDate).
     */
    public static function currentSchoolStartYear(): int
    {
        $row = db_one("SELECT name, start_date FROM school_years WHERE is_current = 1 LIMIT 1");
        if ($row) {
            if (preg_match('/^\s*(\d{4})\b/', (string) $row['name'], $m)) return (int) $m[1];
            if (preg_match('/^(\d{4})-/', (string) $row['start_date'], $m)) return (int) $m[1];
        }
        return self::schoolStartYearFromDate();
    }

    /**
     * Dự phòng khi chưa có niên khoá đang mở: năm bắt đầu niên khoá chứa ngày $now (giờ VN).
     * Từ tháng 8 là niên khoá mới ("2026-2027" bắt đầu 01/08/2026 — mốc niên khoá mặc định của
     * install.php), tháng 1–7 vẫn thuộc niên khoá bắt đầu năm trước.
     */
    public static function schoolStartYearFromDate(?\DateTimeInterface $now = null): int
    {
        $now ??= new \DateTimeImmutable('now');
        $y = (int) $now->format('Y');
        return (int) $now->format('n') >= 8 ? $y : $y - 1;
    }

    /**
     * Phân công chính của một người trong năm học bắt đầu năm $year, từ danh
     * sách phân công đã nạp (loadAssignments). Hàm thuần — không đụng CSDL.
     *
     * Năm học = 01/08/$year → 31/07/($year+1). Một phân công thuộc năm học khi
     * KHOẢNG hiệu lực của nó GIAO với năm học (from_date ≤ cuối năm học VÀ
     * (to_date rỗng HOẶC to_date ≥ đầu năm học)) — kể cả phân công bắt đầu từ
     * nhiều năm trước và còn hiệu lực.
     *
     * Nhiều phân công cùng năm:
     *  1. Bỏ phân công giao quá ngắn: số ngày giao < 50% số ngày giao của phân công giao
     *     dài nhất. Lý do: repo kết thúc phân công bằng to_date = ngày phân công lại, nên
     *     người luân chuyển đầu năm học (chủ nhiệm lớp A hết 05/09, GLV lớp B từ 05/09) có
     *     phân công cũ chỉ giao vài ngày — vai/lớp cũ không được thắng chỉ vì hạng cao hơn.
     *     Kiêm nhiệm cả năm (số ngày giao bằng nhau) vẫn qua bước này.
     *  2. Còn lại: ưu tiên vai cao hơn (kiêm nhiệm → vai cao), rồi phân công chính, rồi
     *     mới bắt đầu gần nhất.
     *
     * @param  array<int,array<string,mixed>> $assignments
     * @return array{role_code:string,block_id:?int,class_id:?int}|null
     */
    public static function assignmentForYear(array $assignments, int $year): ?array
    {
        $start = sprintf('%04d-08-01', $year);
        $end   = sprintf('%04d-07-31', $year + 1);
        $ngay  = fn(string $d) => intdiv(strtotime($d . ' 00:00:00 UTC'), 86400);

        $cands = [];
        foreach ($assignments as $a) {
            if ($a['from_date'] > $end || ($a['to_date'] !== null && $a['to_date'] < $start)) continue;
            $tu  = max($a['from_date'], $start);
            $den = $a['to_date'] === null ? $end : min($a['to_date'], $end);
            $a['_giao'] = $ngay($den) - $ngay($tu) + 1;
            $cands[] = $a;
        }
        if (!$cands) return null;

        $dai = max(array_column($cands, '_giao'));
        $cands = array_values(array_filter($cands, fn($a) => $a['_giao'] * 2 >= $dai));

        $key = fn($a) => [self::ROLE_RANK[$a['role_code']] ?? 0, (int) $a['is_primary'], $a['from_date'], (int) $a['id']];
        usort($cands, fn($a, $b) => $key($b) <=> $key($a));

        $best = $cands[0];
        return [
            'role_code' => $best['role_code'],
            'block_id'  => $best['block_id'] !== null ? (int) $best['block_id'] : null,
            'class_id'  => $best['class_id'] !== null ? (int) $best['class_id'] : null,
        ];
    }

    /**
     * Mọi phân công (cả đã kết thúc) của một thành viên, trừ vai Quản trị (F9:
     * vai Quản trị không đi vào lịch sử phục vụ). Khối lấy từ lớp nếu phân
     * công chỉ ghi lớp.
     */
    private static function loadAssignments(int $memberId): array
    {
        return db_all(
            "SELECT a.id, a.role_code, COALESCE(a.block_id, c.block_id) AS block_id,
                    a.class_id, a.is_primary, a.from_date, a.to_date
               FROM member_assignments a
               LEFT JOIN classes c ON c.id = a.class_id
              WHERE a.member_id = ? AND a.role_code <> 'admin'",
            [$memberId]
        );
    }

    /** "2024" -> "2024-2025" (định dạng year_label của member_service_history) */
    private static function yearLabelFromYear(int $year): string
    {
        return $year . '-' . ($year + 1);
    }

    /**
     * Id khối/lớp từ dữ liệu client: rỗng → null; có giá trị thì phải là hàng
     * thật của $table (nếu không FK sẽ ném lỗi CSDL thô ra ngoài).
     */
    private static function existingId(string $table, mixed $raw, string $loi): ?int
    {
        if ($raw === null || $raw === '' || $raw === 0 || $raw === '0') return null;
        $id = (int) $raw;
        if ($id <= 0 || !db_one("SELECT 1 FROM {$table} WHERE id = ?", [$id])) {
            throw new PersonalException($loi);
        }
        return $id;
    }

    /**
     * Số năm phục vụ = năm bắt đầu niên khoá hiện tại - glv_year (tăng 1 mỗi khi sang
     * niên khoá mới — cùng mốc với dải dòng lịch sử tự sinh, không theo năm dương lịch).
     * null khi chưa lên GLV. Để riêng (không dùng trong updateGlvYear) để giao diện cá
     * nhân hiển thị "Thâm niên: 5 năm" ở hồ sơ.
     */
    public static function calculateYearsOfService(?int $glvYear): ?int
    {
        if ($glvYear === null) {
            return null;
        }
        return max(0, self::currentSchoolStartYear() - $glvYear);
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
     * 'đã nghỉ' khoá hẳn tài khoản (mọi request 403), nên:
     *   - $actorId (người đang thao tác) không được tự chuyển CHÍNH MÌNH khỏi
     *     'đang phục vụ' — tránh tự khoá, và Quản trị/BĐH cuối cùng tự mất quyền.
     *   - Hồ sơ đang 'chờ duyệt' không đổi ở đây (sẽ vòng qua bước duyệt, bỏ qua
     *     việc đặt vai khởi tạo của approveMember) — dùng Duyệt/Từ chối.
     *
     * @throws PersonalException Nếu trạng thái không hợp lệ, thiếu ghi chú
     *                           khi rời 'đang phục vụ', hoặc vi phạm hai quy tắc trên.
     */
    public static function changeMemberStatus(int $memberId, string $newStatus, ?string $note, ?int $actorId = null): array
    {
        if (!in_array($newStatus, self::VALID_STATUSES, true)) {
            throw new PersonalException('Trạng thái không hợp lệ.');
        }
        $note = $note === null ? null : trim($note);
        if ($note === '') $note = null;
        if ($newStatus !== 'đang phục vụ' && $note === null) {
            throw new PersonalException('Vui lòng nhập ghi chú khi thay đổi trạng thái.');
        }
        if ($note !== null && mb_strlen($note) > 255) {
            throw new PersonalException('Ghi chú tối đa 255 ký tự.');
        }

        $cur = db_one('SELECT status FROM members WHERE id = ?', [$memberId]);
        if (!$cur) {
            throw new PersonalException('Không tìm thấy thành viên.', 404);
        }
        if ($cur['status'] === 'chờ duyệt') {
            throw new PersonalException('Tài khoản đang chờ duyệt — hãy Duyệt hoặc Từ chối ở mục chờ duyệt.', 409);
        }
        if ($actorId !== null && $actorId === $memberId && $newStatus !== 'đang phục vụ') {
            throw new PersonalException('Không thể tự chuyển trạng thái của chính mình sang "' . $newStatus . '".');
        }

        // Chỉ ghi đè register_note khi có ghi chú mới: không làm mất lời nhắn đăng ký ban đầu
        // khi đổi trạng thái mà không kèm ghi chú (vd quay lại 'đang phục vụ').
        if ($note !== null) {
            db_run("UPDATE members SET status = ?, register_note = ? WHERE id = ?", [$newStatus, $note, $memberId]);
        } else {
            db_run("UPDATE members SET status = ? WHERE id = ?", [$newStatus, $memberId]);
        }

        return ['ok' => true, 'status' => $newStatus];
    }
}
