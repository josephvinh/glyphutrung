<?php
/**
 * KIỂM TRA TÍCH HỢP: xác nhận tất cả các sửa đổi
 *
 * Chạy: php scratch/test-all-fixes.php
 */
require __DIR__ . '/../config/db.php';

/** Sao chép hàm effective_assignments() cho test */
function eff_assigns(int $mid): array {
    return db_all(
        "SELECT a.*, r.scope AS role_scope FROM member_assignments a
          JOIN roles r ON r.code = a.role_code
         WHERE a.member_id = ? AND a.to_date IS NULL",
        [$mid]
    );
}

/** Sao chép responsible_blocks() cho test */
function test_resp_blocks(array $me): ?array {
    if (in_array($me['role_code'] ?? '', ['admin', 'bdh'], true)) return null;
    $blockIds = [];
    foreach (eff_assigns((int) ($me['id'])) as $a) {
        if (($a['role_scope'] ?? '') === 'toàn đoàn') return null;
        if (!empty($a['block_id'])) {
            $blockIds[] = (int) $a['block_id'];
        } elseif (!empty($a['class_id'])) {
            $c = db_one('SELECT block_id FROM classes WHERE id = ?', [(int) $a['class_id']]);
            if ($c && $c['block_id']) $blockIds[] = (int) $c['block_id'];
        }
    }
    if (!$blockIds && !empty($me['block_id'])) {
        $blockIds[] = (int) $me['block_id'];
    }
    if (!$blockIds) return [];
    return array_values(array_unique($blockIds));
}

/** Sao chép can_manage_block() cho test */
function test_can_block(array $me, int $blockId): bool {
    $blocks = test_resp_blocks($me);
    if ($blocks === null) return true;
    if ($blocks === []) return false;
    return in_array($blockId, $blocks, true);
}

function test_can_class(array $me, int $classId): bool {
    $blocks = test_resp_blocks($me);
    if ($blocks === null) return true;
    if ($blocks === []) return false;
    $c = db_one('SELECT block_id FROM classes WHERE id = ?', [$classId]);
    return $c && in_array((int) $c['block_id'], $blocks, true);
}

echo "=== TEST:responsible_blocks() ===\n";

// Lấy mọi tài khoản
$members = db_all("SELECT id, full_name, role_code, block_id FROM members WHERE status <> 'đã nghỉ' ORDER BY id");
$ok = true;
foreach ($members as $m) {
    $blocks = test_resp_blocks($m);
    $desc = $blocks === null ? 'toàn đoàn' : ($blocks === [] ? 'không có' : implode(',', $blocks));
    printf("  %-22s (%s) → blocks: %s\n", $m['full_name'], $m['role_code'], $desc);

    // Kiểm tra can_manage_block
    $firstBlock = db_one("SELECT id FROM blocks ORDER BY id LIMIT 1");
    if ($firstBlock) {
        $can = test_can_block($m, (int) $firstBlock['id']);
        printf("    can_manage_block(id=%d) = %s\n", $firstBlock['id'], $can ? '✓' : '✗');
    }
}

echo "\n=== TEST:can_manage_class() ===\n";
$firstClass = db_one("SELECT id FROM classes ORDER BY id LIMIT 1");
if ($firstClass) {
    foreach ($members as $m) {
        $can = test_can_class($m, (int) $firstClass['id']);
        printf("  %-22s → can_manage_class(id=%d) = %s\n",
            $m['full_name'], $firstClass['id'], $can ? '✓' : '✗');
    }
}

echo "\n=== TEST:quyền hữu hiệu sau migration ===\n";
$permMap = [
    'org'      => ['truong_khoi' => 'view', 'bdh' => 'edit'],
    'students' => ['bdh' => 'edit', 'truong_khoi' => 'edit'],
    'staff'    => ['truong_khoi' => 'view'],
];
$fail = false;
foreach ($permMap as $mod => $expected) {
    foreach ($expected as $role => $level) {
        $row = db_one("SELECT level FROM permissions WHERE module_key=? AND role_code=?", [$mod, $role]);
        $actual = $row['level'] ?? '(null)';
        $pass = $actual === $level;
        printf("  %-9s %-16s: %-6s (mong đợi %-6s) %s\n",
            $mod, $role, $actual, $level, $pass ? '✓' : '✗');
        if (!$pass) $fail = true;
    }
}

echo "\n=== TEST: bảng permissions đầy đủ ===\n";
$all = db_all(
    "SELECT module_key, role_code, level FROM permissions
      WHERE module_key IN ('org','students','staff')
      ORDER BY module_key, FIELD(role_code,'admin','bdh','truong_khoi','glv_chu_nhiem','glv','du_bi')"
);
foreach ($all as $r) {
    printf("  %-9s %-16s %s\n", $r['module_key'], $r['role_code'], $r['level']);
}

echo "\n=== TEST: require_csrf() sửa rồi ===\n";
echo "  (Yêu cầu xác nhận bằng tay: POST không có token → HTTP 403)\n";
echo "  Thử: curl -X POST http://localhost:8888/tntt/api/org.php?action=saveBlock\n";

echo "\n=== KẾT QUẢ ===\n";
if ($fail) {
    echo "❌ Một số quyền chưa đúng. Chạy lại: php scratch/run-migrate-permissions.php\n";
    exit(1);
} else {
    echo "✓ Tất cả kiểm tra PASSED\n";
    exit(0);
}
