<?php
/**
 * DATA VERIFICATION SCRIPTS
 *
 * Chạy để verify data integrity.
 * Usage: php config/verify_data.php
 *
 * Kết nối tới DB test: export TNTT_DB_* trước khi chạy.
 */

require __DIR__ . '/db.php';
require __DIR__ . '/config.php';

echo "=== DATA INTEGRITY VERIFICATION ===\n\n";

$issues = 0;

// ================================================================
// DATA-1: Verify schema sync
// So sánh schema.sql vs migrations vs actual DB
// ================================================================
echo "--- DATA-1: Schema Sync Check ---\n";

try {
    // Check students table has hidden_at and deleted_at
    $cols = db_all("SHOW COLUMNS FROM students");
    $colNames = array_column($cols, 'Field');

    if (in_array('hidden_at', $colNames)) {
        echo "✓ students.hidden_at exists\n";
    } else {
        echo "✗ students.hidden_at MISSING\n";
        $issues++;
    }

    if (in_array('deleted_at', $colNames)) {
        echo "✓ students.deleted_at exists\n";
    } else {
        echo "✗ students.deleted_at MISSING\n";
        $issues++;
    }

    // Check indexes
    $indexes = db_all("SHOW INDEX FROM students");
    $idxNames = array_column($indexes, 'Key_name');
    if (in_array('idx_students_hidden', $idxNames)) {
        echo "✓ idx_students_hidden exists\n";
    } else {
        echo "✗ idx_students_hidden MISSING\n";
        $issues++;
    }
    if (in_array('idx_students_deleted', $idxNames)) {
        echo "✓ idx_students_deleted exists\n";
    } else {
        echo "✗ idx_students_deleted MISSING\n";
        $issues++;
    }

} catch (Throwable $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    $issues++;
}

echo "\n";

// ================================================================
// DATA-3: Verify stamp balances
// Kiểm tra ví Mộc có khớp với transactions
// ================================================================
echo "--- DATA-3: Stamp Balance Reconciliation ---\n";

try {
    // PR-5 fix: Bỏ ss.current_balance < 0 vì số dư âm có thể hợp lệ
    // (khi xóa điểm danh đã tạo tem mà em đã tiêu)
    $query = "
        SELECT ss.student_id, ss.current_balance, ss.held_balance,
               COALESCE(SUM(st.amount), 0) AS sum_transactions
        FROM student_stamps ss
        LEFT JOIN stamp_transactions st ON st.student_id = ss.student_id AND st.year_id = ss.year_id
        GROUP BY ss.student_id, ss.year_id
        HAVING ss.current_balance <> COALESCE(SUM(st.amount), 0)
           OR ss.held_balance < 0
    ";

    $mismatches = db_all($query);

    if (empty($mismatches)) {
        echo "✓ All stamp balances are correct\n";
    } else {
        echo "✗ Found " . count($mismatches) . " stamp balance mismatches:\n";
        foreach ($mismatches as $m) {
            echo "  - Student #{$m['student_id']}: balance={$m['current_balance']}, held={$m['held_balance']}, tx_sum={$m['sum_transactions']}\n";
        }
        $issues += count($mismatches);
    }

} catch (Throwable $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "  (Table may not exist yet - this is OK for new installations)\n";
}

echo "\n";

// ================================================================
// DATA-4: Orphan Check
// Tìm các records tham chiếu tới records đã xóa
// ================================================================
echo "--- DATA-4: Orphan Records Check ---\n";

$orphanChecks = [
    'enrollments -> students' =>
        "SELECT COUNT(*) n FROM enrollments e LEFT JOIN students s ON s.id=e.student_id WHERE s.id IS NULL",
    'enrollments -> classes' =>
        "SELECT COUNT(*) n FROM enrollments e LEFT JOIN classes c ON c.id=e.class_id WHERE c.id IS NULL",
    'members -> roles' =>
        "SELECT COUNT(*) n FROM members m LEFT JOIN roles r ON r.code=m.role_code WHERE r.code IS NULL",
];

foreach ($orphanChecks as $name => $sql) {
    try {
        $count = (int) db_one($sql)['n'];
        if ($count === 0) {
            echo "✓ {$name}: no orphans\n";
        } else {
            echo "✗ {$name}: {$count} orphan records\n";
            $issues += $count;
        }
    } catch (Throwable $e) {
        echo "✗ {$name}: ERROR - " . $e->getMessage() . "\n";
        $issues++;
    }
}

echo "\n";

// ================================================================
// SUMMARY
// ================================================================
echo "=== SUMMARY ===\n";
if ($issues === 0) {
    echo "✓ All checks passed!\n";
    exit(0);
} else {
    echo "✗ Found {$issues} issue(s)\n";
    exit(1);
}
