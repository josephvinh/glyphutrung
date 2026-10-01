<?php
/**
 * ORG SERVICE
 *
 * Khối & Lớp: tách logic nghiệp vụ ra khỏi org.php.
 * Giữ nguyên cấu trúc switch/case để không phá API contract.
 *
 * TODO: Chuyển sang class-based endpoints khi có thời gian refactor đầy đủ
 */

class OrgService
{
    private array $me;
    private int $yid;
    private array $in;

    public function __construct(array $me, int $yid, array $input)
    {
        $this->me = $me;
        $this->yid = $yid;
        $this->in = $input;
    }

    /**
     * Lấy giá trị input, có trim
     */
    private function in(string $key, string $default = ''): string
    {
        return trim((string) ($this->in[$key] ?? $default));
    }

    /**
     * Kiểm tra quyền quản lý khối
     */
    private function canManageBlock(int $blockId): bool
    {
        return can_manage_block($this->me, $blockId);
    }

    /**
     * Kiểm tra quyền quản lý lớp
     */
    private function canManageClass(int $classId): bool
    {
        return can_manage_class($this->me, $classId);
    }

    /**
     * Lưu khối mới hoặc đổi tên
     */
    public function saveBlock(): array
    {
        require_write();

        $name = $this->in('name');
        $old = $this->in('original');

        if ($name === '') {
            return ['ok' => false, 'error' => 'Vui lòng nhập tên khối.'];
        }
        if (mb_strlen($name) > 64) {
            return ['ok' => false, 'error' => 'Tên khối tối đa 64 ký tự.'];
        }

        $dup = db_one('SELECT id FROM blocks WHERE name = ?', [$name]);
        if ($dup && $old !== $name) {
            return ['ok' => false, 'error' => 'Tên khối này đã tồn tại.'];
        }

        if ($old === '') {
            // Tạo mới
            if (!$this->canManageBlock(0)) {
                return ['ok' => false, 'error' => 'Bạn không có quyền tạo khối mới.', 'code' => 403];
            }
            $max = db_one('SELECT COALESCE(MAX(sort_order),0) n FROM blocks');
            db_insert('INSERT INTO blocks (name, sort_order) VALUES (?,?)', [$name, $max['n'] + 1]);
            log_action('tao', 'org', 'Thêm khối ' . $name, '');
        } elseif ($old !== $name) {
            // Đổi tên
            $b = db_one('SELECT id FROM blocks WHERE name = ?', [$old]);
            if (!$b) {
                return ['ok' => false, 'error' => 'Không tìm thấy khối "' . $old . '".', 'code' => 404];
            }
            if (!$this->canManageBlock((int) $b['id'])) {
                return ['ok' => false, 'error' => 'Bạn không có quyền sửa khối "' . $old . '".', 'code' => 403];
            }
            db_run('UPDATE blocks SET name=? WHERE name=?', [$name, $old]);
            log_action('sua', 'org', 'Đổi tên khối ' . $old . ' thành ' . $name, '');
        }

        Cache::flush();
        return ['ok' => true];
    }

    /**
     * Xóa khối
     */
    public function deleteBlock(): array
    {
        require_write();

        $name = $this->in('name');
        $b = db_one('SELECT id FROM blocks WHERE name=?', [$name]);
        if (!$b) {
            return ['ok' => false, 'error' => 'Không tìm thấy khối.', 'code' => 404];
        }
        if (!$this->canManageBlock((int) $b['id'])) {
            return ['ok' => false, 'error' => 'Bạn không có quyền xóa khối "' . $name . '".', 'code' => 403];
        }

        $n = db_one('SELECT COUNT(*) n FROM classes WHERE block_id=?', [$b['id']])['n'];
        if ($n > 0) {
            return ['ok' => false, 'error' => 'Khối "' . $name . '" còn ' . $n . ' lớp. Hãy chuyển hoặc xóa hết lớp trước.'];
        }

        // Kết thúc phân công trưởng khối, rồi tính lại vai gốc của những người đó
        $heads = array_column(db_all(
            "SELECT DISTINCT member_id FROM member_assignments
              WHERE block_id = ? AND to_date IS NULL", [$b['id']]), 'member_id');
        db_run("UPDATE member_assignments SET to_date = CURDATE()
                  WHERE block_id = ? AND to_date IS NULL", [$b['id']]);

        db_run('DELETE FROM blocks WHERE id=?', [$b['id']]);
        foreach ($heads as $mid) {
            recompute_member_primary((int) $mid);
        }
        log_action('xoa', 'org', 'Xóa khối ' . $name, '');
        Cache::flush();
        return ['ok' => true];
    }

    /**
     * Lưu lớp mới hoặc đổi tên
     */
    public function saveClass(): array
    {
        require_write();

        $name = $this->in('name');
        $old = $this->in('original');
        $block = $this->in('block');

        if ($name === '') {
            return ['ok' => false, 'error' => 'Vui lòng nhập tên lớp.'];
        }
        if (mb_strlen($name) > 64) {
            return ['ok' => false, 'error' => 'Tên lớp tối đa 64 ký tự.'];
        }
        if ($block === '') {
            return ['ok' => false, 'error' => 'Vui lòng chọn khối cho lớp.'];
        }

        $b = db_one('SELECT id FROM blocks WHERE name=?', [$block]);
        if (!$b) {
            return ['ok' => false, 'error' => 'Không tìm thấy khối "' . $block . '".'];
        }

        // Kiểm tra quyền khi tạo mới
        if ($old === '') {
            if (!$this->canManageBlock((int) $b['id'])) {
                return ['ok' => false, 'error' => 'Bạn không có quyền tạo lớp trong khối "' . $block . '".', 'code' => 403];
            }
        }

        // Kiểm tra quyền khi sửa
        if ($old !== '') {
            $oldCls = db_one('SELECT id, block_id FROM classes WHERE name=?', [$old]);
            if (!$oldCls) {
                return ['ok' => false, 'error' => 'Không tìm thấy lớp "' . $old . '".', 'code' => 404];
            }
            if (!$this->canManageClass((int) $oldCls['id'])) {
                return ['ok' => false, 'error' => 'Bạn không có quyền sửa lớp "' . $old . '".', 'code' => 403];
            }
            // Chuyển sang khối khác: phải quản lý cả khối đích
            if ((int) $oldCls['block_id'] !== (int) $b['id'] && !$this->canManageBlock((int) $b['id'])) {
                return ['ok' => false, 'error' => 'Bạn không có quyền chuyển lớp sang khối "' . $block . '".', 'code' => 403];
            }
        }

        $dup = db_one('SELECT id FROM classes WHERE name=?', [$name]);
        if ($dup && $old !== $name) {
            return ['ok' => false, 'error' => 'Tên lớp này đã tồn tại.'];
        }

        if ($old === '') {
            db_insert('INSERT INTO classes (name, block_id, sort_order) VALUES (?,?,?)',
                      [$name, $b['id'], 1]);
            log_action('tao', 'org', 'Thêm lớp ' . $name, 'khối ' . $block);
        } else {
            db_run('UPDATE classes SET name=?, block_id=? WHERE name=?', [$name, $b['id'], $old]);
            if ((int) $oldCls['block_id'] !== (int) $b['id']) {
                // Đổi khối: phân công đang hiệu lực + cột khối hiển thị của người
                // trong lớp phải đi theo, nếu không họ vẫn "thuộc khối cũ".
                db_run('UPDATE member_assignments SET block_id=? WHERE class_id=? AND to_date IS NULL',
                       [$b['id'], $oldCls['id']]);
                db_run('UPDATE members SET block_id=? WHERE class_id=?', [$b['id'], $oldCls['id']]);
            }
            log_action('sua', 'org', 'Sửa lớp ' . $old, 'thành ' . $name . ' · khối ' . $block);
        }

        // Sơ đồ lên lớp: CHỈ đổi khi client gửi nextClass. Form sửa tên/khối lớp
        // không gửi trường này — trước đây mỗi lần lưu lớp là xóa mất sơ đồ.
        if (array_key_exists('nextClass', $this->in)) {
            $target = $this->in('nextClass');
            if ($target === 'RA_TRUONG') {
                db_run('UPDATE classes SET next_class_id=NULL, is_final=1 WHERE name=?', [$name]);
            } elseif ($target !== '') {
                $nextClass = db_one('SELECT id FROM classes WHERE name=?', [$target]);
                if ($nextClass) {
                    db_run('UPDATE classes SET next_class_id=?, is_final=0 WHERE name=?', [$nextClass['id'], $name]);
                }
            } else {
                db_run('UPDATE classes SET next_class_id=NULL, is_final=0 WHERE name=?', [$name]);
            }
        }

        Cache::flush();
        return ['ok' => true];
    }

    /**
     * Xóa lớp
     */
    public function deleteClass(): array
    {
        require_write();

        $name = $this->in('name');
        $cls = db_one('SELECT id FROM classes WHERE name=?', [$name]);
        if (!$cls) {
            return ['ok' => false, 'error' => 'Không tìm thấy lớp.', 'code' => 404];
        }
        if (!$this->canManageClass((int) $cls['id'])) {
            return ['ok' => false, 'error' => 'Bạn không có quyền xóa lớp "' . $name . '".', 'code' => 403];
        }

        // Ghi danh theo niên khoá nằm ở enrollments (students không còn class_id).
        // Chỉ xét niên khoá HIỆN TẠI; ghi danh niên khoá cũ được chặn bởi khoá ngoại
        // RESTRICT fk_enr_class (xem lệnh DELETE bên dưới) để không xoá dây chuyền lịch sử.
        $n = (int) db_one('SELECT COUNT(DISTINCT student_id) n FROM enrollments WHERE class_id=? AND year_id=?',
                          [$cls['id'], $this->yid])['n'];
        if ($n > 0) {
            return ['ok' => false, 'error' => 'Lớp "' . $name . '" còn ' . $n . ' em đang ghi danh trong niên khoá hiện tại. Hãy chuyển hoặc xóa hết em trước.'];
        }

        // Khớp với giao diện: còn người phụ trách (chủ nhiệm, GLV, Dự Bị) thì
        // không xóa. Trước đây backend âm thầm kết thúc phân công — riêng Dự Bị
        // bị sót, để lại phân công còn hiệu lực trỏ vào lớp đã xóa.
        $glv = db_one("SELECT COUNT(*) n FROM member_assignments
                        WHERE class_id = ? AND to_date IS NULL", [$cls['id']])['n'];
        if ($glv > 0) {
            return ['ok' => false, 'error' => 'Lớp "' . $name . '" còn ' . $glv . ' người được phân công (GLV/Dự Bị). Hãy gỡ hoặc chuyển họ sang lớp khác trước.'];
        }

        try {
            db_run('DELETE FROM classes WHERE id=?', [$cls['id']]);
        } catch (PDOException $e) {
            // 23000 = vi phạm khoá ngoại: lớp còn ghi danh của niên khoá cũ (fk_enr_class RESTRICT).
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'error' => 'Lớp "' . $name . '" còn dữ liệu ghi danh của các niên khoá cũ nên không xóa được (để giữ lịch sử).'];
            }
            throw $e;
        }
        log_action('xoa', 'org', 'Xóa lớp ' . $name, '');
        Cache::flush();
        return ['ok' => true];
    }
}
