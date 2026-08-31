# Task 4 Brief: Performance - Database Index Optimization

## Task Description
Add missing database indexes to improve query performance for common operations.

## Files to Create/Modify

### Modify: `config/schema.sql`
Add these indexes at the end of the schema file (after existing indexes):

```sql
-- ============================================================
-- Performance Indexes - Add after existing table definitions
-- ============================================================

-- Attendance: lookup by student + date (for statistics)
ALTER TABLE attendances ADD INDEX idx_att_student_date (student_id, session_date);

-- Attendance: lookup by program + date (for session management)
ALTER TABLE attendances ADD INDEX idx_att_program_date (program_id, session_date);

-- Leave requests: lookup by status + date (for approval queue)
ALTER TABLE leave_requests ADD INDEX idx_lv_status_date (status, session_date);

-- Leave requests: lookup by student (for student history)
ALTER TABLE leave_requests ADD INDEX idx_lv_student (student_id);

-- Scores: lookup by student + term (for report cards)
ALTER TABLE scores ADD INDEX idx_sc_student_term (student_id, term_id);

-- Reports: lookup by student (for student history)
ALTER TABLE reports ADD INDEX idx_rp_student (student_id);

-- Members: lookup by phone (for login - critical)
ALTER TABLE members ADD INDEX idx_member_phone (phone);

-- Members: lookup by role (for permission checks)
ALTER TABLE members ADD INDEX idx_member_role (role_code);

-- Enrollments: lookup by year + status (for roster)
ALTER TABLE enrollments ADD INDEX idx_enr_year_status (year_id, status);

-- Announcements: lookup by year + status + expiry (for live announcements)
ALTER TABLE announcements ADD INDEX idx_an_live (year_id, status, expires_at);
```

### Create: `scripts/add_indexes.php`
```php
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
```

## Requirements
- Add indexes for common query patterns
- Create migration script to safely add indexes to existing databases
- Index names must be unique per table
- Indexes should improve performance for:
  - Student attendance lookup by date
  - Leave request approval queue
  - Score lookups for reports
  - Member login by phone
  - Announcement queries

## Acceptance Criteria
1. Schema file updated with ALTER TABLE statements
2. Migration script exists and is executable
3. Script skips indexes that already exist
4. Script reports progress and summary

## Notes
- These are non-breaking changes (adding indexes to existing tables)
- Safe to run on production databases
- Indexes will improve read performance significantly
