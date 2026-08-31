<?php
/**
 * Script để thêm indexes mà không cần recreate tables
 * Chạy một lần: php scripts/add_indexes.php
 */

require_once __DIR__ . '/../config/db.php';

$indexes = [
    // Table => [index_name, columns, unique (bool)]
    'attendances' => [
        ['idx_att_student_date', 'student_id, session_date', false],
        ['idx_att_program_date', 'program_id, session_date', false],
    ],
    'leave_requests' => [
        ['idx_lv_status_date', 'status, session_date', false],
        ['idx_lv_student', 'student_id', false],
    ],
    'scores' => [
        ['idx_sc_student_term', 'student_id, term_id', false],
    ],
    'reports' => [
        ['idx_rp_student', 'student_id', false],
    ],
    'members' => [
        ['idx_member_phone', 'phone', false],
        ['idx_member_role', 'role_code', false],
    ],
    'enrollments' => [
        ['idx_enr_year_status', 'year_id, status', false],
    ],
    'announcements' => [
        ['idx_an_live', 'year_id, status, expires_at', false],
    ],
];

echo "Checking and adding indexes...\n";
$added = 0;
$skipped = 0;

foreach ($indexes as $table => $tableIndexes) {
    echo "\nTable: $table\n";

    foreach ($tableIndexes as [$idxName, $columns, $unique]) {
        // Check if index exists
        $exists = db_one("SHOW INDEX FROM `$table` WHERE Key_name = ?", [$idxName]);

        if ($exists) {
            echo "  - $idxName: already exists (skipped)\n";
            $skipped++;
            continue;
        }

        // Add index
        $uniqueStr = $unique ? 'UNIQUE' : '';
        $sql = "ALTER TABLE `$table` ADD $uniqueStr INDEX `$idxName` ($columns)";

        try {
            db_run($sql);
            echo "  + $idxName: added successfully\n";
            $added++;
        } catch (Exception $e) {
            echo "  ! $idxName: failed - " . $e->getMessage() . "\n";
        }
    }
}

echo "\n=== Summary ===\n";
echo "Added: $added\n";
echo "Skipped (already exists): $skipped\n";
echo "Done!\n";
