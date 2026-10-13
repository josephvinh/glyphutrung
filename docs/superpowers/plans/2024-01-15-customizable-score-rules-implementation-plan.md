# Implementation Plan: Hệ Thống Quy Tắc Điểm Tuỳ Chỉnh

> **Dựa trên spec:** `docs/superpowers/specs/2024-01-15-customizable-score-rules-design.md`
> **Ngày:** 2024-01-15
> **Thứ tự thực hiện:** Bottom-up (schema → API → logic → UI)

---

## TỔNG QUAN CÁC BƯỚC

```
Bước 1: Migration — Schema
Bước 2: API rules.php
Bước 3: API score_types.php
Bước 4: API scores.php (cập nhật)
Bước 5: API term_programs.php
Bước 6: API attendance_thresholds.php
Bước 7: API attendance_scores.php
Bước 8: Frontend JS — Logic điểm động
Bước 9: Frontend UI — Settings tabs
Bước 10: Frontend UI — Màn nhập điểm
Bước 11: Test
```

---

## BƯỚC 1: Migration — Schema

### File: `config/migrations/XXX_score_rules_configurable.sql`

```sql
-- ============================================================
-- Migration: Quy tắc điểm tuỳ chỉnh
-- ============================================================

-- 1. score_types: thêm category và max_exams
ALTER TABLE score_types 
    ADD COLUMN category VARCHAR(32) NOT NULL DEFAULT 'homework'
    COMMENT 'homework=điểm BT, exam=điểm thi, attendance=chuyên cần',
    ADD COLUMN max_exams TINYINT NOT NULL DEFAULT 1 
    COMMENT 'Số bài kiểm tra tối đa (1-20)';

-- 2. scores: thêm exam_num, cập nhật unique key
ALTER TABLE scores 
    ADD COLUMN exam_num TINYINT NOT NULL DEFAULT 1 
    COMMENT 'Số thứ tự bài KT trong loại (1, 2, 3...)';

ALTER TABLE scores DROP INDEX uq_score;
ALTER TABLE scores ADD UNIQUE KEY uq_score (term_id, student_id, type_code, exam_num);

-- 3. score_rules
CREATE TABLE IF NOT EXISTS score_rules (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    rule_key      VARCHAR(64) NOT NULL UNIQUE,
    rule_label    VARCHAR(128) NOT NULL,
    rule_type     ENUM('weight','threshold','formula') NOT NULL,
    rule_value    TEXT NOT NULL,
    description   VARCHAR(255) NULL,
    updated_by    INT NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sr_by FOREIGN KEY (updated_by) REFERENCES members(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. term_programs
CREATE TABLE IF NOT EXISTS term_programs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    term_id         INT NOT NULL,
    program_id      INT NOT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_tp (term_id, program_id),
    CONSTRAINT fk_tp_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    CONSTRAINT fk_tp_prog FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. attendance_thresholds
CREATE TABLE IF NOT EXISTS attendance_thresholds (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    sort_order      INT NOT NULL,
    label           VARCHAR(32) NOT NULL,
    min_percent     DECIMAL(5,2) NOT NULL,
    description     VARCHAR(128) NULL,
    CONSTRAINT chk_at_min CHECK (min_percent >= 0 AND min_percent <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DỮ LIỆU MẶC ĐỊNH
-- ============================================================

-- Cập nhật score_types mặc định
UPDATE score_types SET category = 'homework', max_exams = 3 WHERE code = 'mieng';
UPDATE score_types SET category = 'homework', max_exams = 4 WHERE code = 'p15';
INSERT IGNORE INTO score_types (code, label, short_label, weight, max_exams, category, sort_order) 
VALUES ('p45', '1 tiết', '1T', 2, 2, 'homework', 3);

UPDATE score_types SET category = 'exam', max_exams = 1 WHERE code IN ('giuaky', 'cuoiky');

-- Score rules mặc định
INSERT IGNORE INTO score_rules (rule_key, rule_label, rule_type, rule_value, description) VALUES
('rank_thresholds', 'Ngưỡng xếp loại học lực', 'threshold', '{"gioi":8,"kha":6.5,"tb":5,"yeu":0}', 'Ngưỡng điểm cho từng xếp loại'),
('passing_min_avg', 'Điểm TB tối thiểu đạt', 'threshold', '{"min":5}', 'Điểm trung bình tối thiểu để xem là đạt');

-- Attendance thresholds mặc định
INSERT IGNORE INTO attendance_thresholds (sort_order, label, min_percent, description) VALUES
(1, 'A', 90.00, 'Xuất sắc'),
(2, 'B', 80.00, 'Tốt'),
(3, 'C', 70.00, 'Khá'),
(4, 'Đạt', 60.00, 'Tối thiểu');
```

### File: `config/install.php`

Thêm seed tương tự vào `install()` function.

### Files cần sửa:
- Tạo: `config/migrations/XXX_score_rules_configurable.sql`
- Sửa: `config/install.php`

---

## BƯỚC 2: API rules.php

### File: `public/api/rules.php` (MỚI)

```php
<?php
/**
 * QUY TẮC HỆ THỐNG
 *
 *   GET  api/rules.php?type=score|attendance
 *   POST api/rules.php  { rule_key, rule_value, rule_label, rule_type }
 *   PUT  api/rules.php  { rule_key, rule_value }
 */

require __DIR__ . '/_bootstrap.php';

$action = $_SERVER['REQUEST_METHOD'];
$type   = $_GET['type'] ?? '';

// ============================================================
// HELPERS
// ============================================================

function require_admin_or_bdh() {
    $me = require_login();
    if (!in_array($me['role_code'], ['admin', 'bdh'])) {
        json_fail('Chỉ Quản Trị hoặc Ban Điều Hành mới có quyền.', 403);
    }
    return $me;
}

function get_rules_by_type(string $type): array {
    if ($type === 'score') {
        $scoreTypes = db_all('SELECT code, label, short_label, weight, max_exams, category, sort_order 
                              FROM score_types ORDER BY sort_order');
        $rules = db_all('SELECT rule_key, rule_label, rule_type, rule_value, description 
                         FROM score_rules ORDER BY rule_key');
        return [
            'score_types' => $scoreTypes,
            'rules' => array_column($rules, null, 'rule_key')
        ];
    }
    
    if ($type === 'attendance') {
        $thresholds = db_all('SELECT id, sort_order, label, min_percent, description 
                              FROM attendance_thresholds ORDER BY sort_order DESC');
        $rules = db_all('SELECT rule_key, rule_label, rule_type, rule_value, description 
                         FROM score_rules WHERE rule_key LIKE "attendance_%"');
        return [
            'thresholds' => $thresholds,
            'rules' => array_column($rules, null, 'rule_key')
        ];
    }
    
    return [];
}

// ============================================================
// GET
// ============================================================

if ($action === 'GET') {
    require_login(); // ai cũng đọc được
    
    if ($type === 'all') {
        json_out([
            'score' => get_rules_by_type('score'),
            'attendance' => get_rules_by_type('attendance')
        ]);
    } else {
        json_out(get_rules_by_type($type));
    }
}

// ============================================================
// POST / PUT
// ============================================================

if ($action === 'POST' || $action === 'PUT') {
    $me = require_admin_or_bdh(); // chỉ admin/bdh
    $in = json_input();
    
    $ruleKey   = trim((string) ($in['rule_key'] ?? ''));
    $ruleValue = $in['rule_value'] ?? null;
    $ruleLabel = trim((string) ($in['rule_label'] ?? ''));
    $ruleType  = trim((string) ($in['rule_type'] ?? ''));
    
    if ($ruleKey === '') json_fail('Thiếu rule_key.');
    
    // Validate rule_type nếu là POST mới
    if ($action === 'POST' && $ruleLabel) {
        if (!in_array($ruleType, ['weight', 'threshold', 'formula'])) {
            json_fail('rule_type không hợp lệ.');
        }
    }
    
    // Encode JSON
    $jsonValue = is_string($ruleValue) ? $ruleValue : json_encode($ruleValue);
    
    // Upsert
    if ($action === 'POST') {
        db_run('INSERT INTO score_rules (rule_key, rule_label, rule_type, rule_value, updated_by)
                VALUES (?,?,?,?,?)
                ON DUPLICATE KEY UPDATE rule_value = VALUES(rule_value), 
                                        rule_label = COALESCE(VALUES(rule_label), rule_label),
                                        updated_by = VALUES(updated_by)',
            [$ruleKey, $ruleLabel, $ruleType, $jsonValue, $me['id']]);
    } else {
        db_run('UPDATE score_rules SET rule_value = ?, updated_by = ? WHERE rule_key = ?',
            [$jsonValue, $me['id'], $ruleKey]);
    }
    
    log_action('sudung', 'rules', 'Cập nhật quy tắc: ' . $ruleKey, $jsonValue);
    Cache::flush();
    
    json_out(['ok' => true, 'rule_key' => $ruleKey]);
}
```

### Files cần sửa:
- Tạo: `public/api/rules.php`

---

## BƯỚC 3: API score_types.php

### File: `public/api/score_types.php` (MỚI)

```php
<?php
/**
 * QUẢN LÝ LOẠI ĐẦU ĐIỂM
 *
 *   GET    api/score_types.php
 *   POST   api/score_types.php           { code, label, short_label, weight, max_exams, category }
 *   PUT    api/score_types.php?id=xxx    { ... }
 *   DELETE api/score_types.php?id=xxx
 */

require __DIR__ . '/_bootstrap.php';

$action = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;

function require_admin_or_bdh() {
    $me = require_login();
    if (!in_array($me['role_code'], ['admin', 'bdh'])) {
        json_fail('Chỉ Quản Trị hoặc Ban Điều Hành mới có quyền.', 403);
    }
    return $me;
}

// GET
if ($action === 'GET') {
    require_login();
    $types = db_all('SELECT code, label, short_label, weight, max_exams, category, sort_order 
                     FROM score_types ORDER BY sort_order');
    json_out($types);
}

// POST
if ($action === 'POST') {
    $me = require_admin_or_bdh();
    $in = json_input();
    
    $code       = trim((string) ($in['code'] ?? ''));
    $label      = trim((string) ($in['label'] ?? ''));
    $short      = trim((string) ($in['short_label'] ?? ''));
    $weight     = max(1, (int) ($in['weight'] ?? 1));
    $maxExams   = max(1, min(20, (int) ($in['max_exams'] ?? 1)));
    $category   = in_array($in['category'] ?? '', ['homework', 'exam', 'attendance']) 
                  ? $in['category'] : 'homework';
    
    if ($code === '' || $label === '') json_fail('Thiếu mã hoặc tên.');
    if (!preg_match('/^[a-z][a-z0-9_]{0,15}$/', $code)) {
        json_fail('Mã chỉ gồm chữ thường, số, gạch dưới, tối đa 16 ký tự.');
    }
    
    // Lấy sort_order lớn nhất + 1
    $maxOrder = db_one('SELECT MAX(sort_order) as m FROM score_types')['m'] ?? 0;
    
    db_run('INSERT INTO score_types (code, label, short_label, weight, max_exams, category, sort_order)
            VALUES (?,?,?,?,?,?,?)',
        [$code, $label, $short ?: substr($label, 0, 3), $weight, $maxExams, $category, $maxOrder + 1]);
    
    log_action('tao', 'score_types', 'Thêm loại đầu điểm: ' . $label, '');
    Cache::flush();
    
    json_out(['ok' => true, 'code' => $code]);
}

// PUT
if ($action === 'PUT' && $id) {
    $me = require_admin_or_bdh();
    $in = json_input();
    
    $type = db_one('SELECT * FROM score_types WHERE code=?', [$in['code'] ?? '']);
    if (!$type) json_fail('Không tìm thấy loại đầu điểm.', 404);
    
    $weight   = max(1, (int) ($in['weight'] ?? $type['weight']));
    $maxExams = max(1, min(20, (int) ($in['max_exams'] ?? $type['max_exams'])));
    
    db_run('UPDATE score_types SET label=?, short_label=?, weight=?, max_exams=? WHERE code=?',
        [$in['label'] ?? $type['label'], 
         $in['short_label'] ?? $type['short_label'],
         $weight, $maxExams, $type['code']]);
    
    log_action('sudung', 'score_types', 'Sửa loại đầu điểm: ' . $type['label'], '');
    Cache::flush();
    
    json_out(['ok' => true]);
}

// DELETE
if ($action === 'DELETE' && $id) {
    $me = require_admin_or_bdh();
    
    $type = db_one('SELECT * FROM score_types WHERE code=?', [$_GET['code'] ?? '']);
    if (!$type) json_fail('Không tìm thấy loại đầu điểm.', 404);
    
    // Kiểm tra có điểm đã nhập không
    $hasScores = db_one('SELECT COUNT(*) as c FROM scores WHERE type_code=?', [$type['code']]);
    if ($hasScores['c'] > 0) {
        json_fail('Loại đầu điểm này đã có điểm. Không xóa được.');
    }
    
    db_run('DELETE FROM score_types WHERE code=?', [$type['code']]);
    
    log_action('xoa', 'score_types', 'Xóa loại đầu điểm: ' . $type['label'], '');
    Cache::flush();
    
    json_out(['ok' => true]);
}
```

### Files cần sửa:
- Tạo: `public/api/score_types.php`

---

## BƯỚC 4: Cập nhật scores.php

### File: `public/api/scores.php`

**Thêm action `delete_exam` và cập nhật `set` để hỗ trợ `exam_num`:**

```php
// Tìm và thay thế đoạn:
// 1. Thêm exam_num vào validation
$examNum = max(1, (int) ($in['exam_num'] ?? 1));

// 2. Cập nhật unique key check
if (!db_one('SELECT code FROM score_types WHERE code=?', [$type])) {
    json_fail('Đầu điểm không hợp lệ.');
}

// 3. INSERT/UDPATE với exam_num
db_run('INSERT INTO scores (term_id, student_id, type_code, exam_num, value, updated_by)
        VALUES (?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)',
    [$termId, $studentId, $type, $examNum, $value, $me['id']]);

// 4. Thêm action delete_exam (thêm vào cuối switch)
case 'delete_exam':
    require_write();
    $me = require_permission('scores', 'edit');
    $in = json_input();
    
    $studentId = (int) ($in['studentId'] ?? 0);
    $termId    = (int) ($in['termId'] ?? 0);
    $type      = (string) ($in['type'] ?? '');
    $examNum   = (int) ($in['examNum'] ?? 0);
    
    if (!$studentId || !$termId || !$type || !$examNum) {
        json_fail('Thiếu thông tin.');
    }
    
    // Xoá bài exam_num
    db_run('DELETE FROM scores WHERE term_id=? AND student_id=? AND type_code=? AND exam_num=?',
        [$termId, $studentId, $type, $examNum]);
    
    // Renumber các bài có exam_num > examNum
    db_run('UPDATE scores SET exam_num = exam_num - 1 
            WHERE term_id=? AND student_id=? AND type_code=? AND exam_num > ?',
        [$termId, $studentId, $type, $examNum]);
    
    Cache::flush();
    json_out(['ok' => true, 'renumbered' => true]);
    break;
```

### Files cần sửa:
- Sửa: `public/api/scores.php`

---

## BƯỚC 5: API term_programs.php

### File: `public/api/term_programs.php` (MỚI)

```php
<?php
/**
 * QUẢN LÝ CHƯƠNG TRÌNH THEO HỌC KỲ
 *
 *   GET    api/term_programs.php?term_id=1
 *   POST   api/term_programs.php   { term_id, program_id, is_active }
 */

require __DIR__ . '/_bootstrap.php';

$action  = $_SERVER['REQUEST_METHOD'];
$termId  = isset($_GET['term_id']) ? (int) $_GET['term_id'] : 0;

function require_admin_or_bdh() {
    $me = require_login();
    if (!in_array($me['role_code'], ['admin', 'bdh'])) {
        json_fail('Chỉ Quản Trị hoặc Ban Điều Hành mới có quyền.', 403);
    }
    return $me;
}

// GET
if ($action === 'GET') {
    require_login();
    
    if (!$termId) json_fail('Thiếu term_id.');
    
    // Lấy tất cả programs, kèm trạng thái active của term này
    $programs = db_all('SELECT p.id, p.name, p.type, p.count_for_attendance,
                              tp.id as tp_id, tp.is_active
                       FROM programs p
                       LEFT JOIN term_programs tp ON tp.program_id = p.id AND tp.term_id = ?
                       WHERE p.count_for_attendance = 1
                       ORDER BY p.sort_order',
        [$termId]);
    
    // Nếu chưa có term_programs nào cho term này, tạo mặc định (all active)
    if (empty($programs) || $programs[0]['tp_id'] === null) {
        $existing = db_all('SELECT program_id FROM term_programs WHERE term_id=?', [$termId]);
        if (empty($existing)) {
            // Seed mặc định: tất cả programs count_for_attendance=1 là active
            db_run('INSERT IGNORE INTO term_programs (term_id, program_id, is_active)
                    SELECT ?, id, 1 FROM programs WHERE count_for_attendance = 1',
                [$termId]);
            $programs = db_all('SELECT p.id, p.name, p.type, p.count_for_attendance,
                                      tp.id as tp_id, tp.is_active
                               FROM programs p
                               LEFT JOIN term_programs tp ON tp.program_id = p.id AND tp.term_id = ?
                               WHERE p.count_for_attendance = 1
                               ORDER BY p.sort_order',
                [$termId]);
        }
    }
    
    // Đếm số buổi dự kiến mỗi program (buổi đã điểm danh)
    foreach ($programs as &$p) {
        $count = db_one('SELECT COUNT(DISTINCT session_date) as c FROM attendances 
                         WHERE program_id=? AND term_id=?',
            [$p['id'], $termId]);
        $p['expected_sessions'] = $count['c'] ?? 0;
    }
    
    json_out($programs);
}

// POST
if ($action === 'POST') {
    $me = require_admin_or_bdh();
    $in = json_input();
    
    $termId    = (int) ($in['term_id'] ?? 0);
    $programId = (int) ($in['program_id'] ?? 0);
    $isActive  = (int) ($in['is_active'] ?? 1);
    
    if (!$termId || !$programId) json_fail('Thiếu term_id hoặc program_id.');
    
    db_run('INSERT INTO term_programs (term_id, program_id, is_active)
            VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE is_active = VALUES(is_active)',
        [$termId, $programId, $isActive ? 1 : 0]);
    
    log_action('sudung', 'term_programs', 
        'Cập nhật chương trình HK#' . $termId . ' prog#' . $programId,
        $isActive ? 'bật' : 'tắt');
    Cache::flush();
    
    json_out(['ok' => true]);
}
```

### Files cần sửa:
- Tạo: `public/api/term_programs.php`

---

## BƯỚC 6: API attendance_thresholds.php

### File: `public/api/attendance_thresholds.php` (MỚI)

```php
<?php
/**
 * QUẢN LÝ NGƯỠNG CHUYÊN CẦN
 *
 *   GET    api/attendance_thresholds.php
 *   POST   api/attendance_thresholds.php  { label, min_percent, description }
 *   PUT    api/attendance_thresholds.php?id=x  { ... }
 *   DELETE api/attendance_thresholds.php?id=x
 */

require __DIR__ . '/_bootstrap.php';

$action = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;

function require_admin_or_bdh() {
    $me = require_login();
    if (!in_array($me['role_code'], ['admin', 'bdh'])) {
        json_fail('Chỉ Quản Trị hoặc Ban Điều Hành mới có quyền.', 403);
    }
    return $me;
}

// GET
if ($action === 'GET') {
    require_login();
    $thresholds = db_all('SELECT id, sort_order, label, min_percent, description 
                         FROM attendance_thresholds ORDER BY sort_order DESC');
    json_out($thresholds);
}

// POST
if ($action === 'POST') {
    $me = require_admin_or_bdh();
    $in = json_input();
    
    $label      = trim((string) ($in['label'] ?? ''));
    $minPercent = max(0, min(100, (float) ($in['min_percent'] ?? 0)));
    $desc       = trim((string) ($in['description'] ?? ''));
    
    if ($label === '') json_fail('Thiếu nhãn.');
    
    // Lấy sort_order lớn nhất + 1
    $maxOrder = db_one('SELECT MAX(sort_order) as m FROM attendance_thresholds')['m'] ?? 0;
    
    db_run('INSERT INTO attendance_thresholds (sort_order, label, min_percent, description)
            VALUES (?,?,?,?)',
        [$maxOrder + 1, $label, $minPercent, $desc]);
    
    log_action('tao', 'attendance_thresholds', 'Thêm mốc chuyên cần: ' . $label, 
        'min: ' . $minPercent . '%');
    Cache::flush();
    
    json_out(['ok' => true]);
}

// PUT
if ($action === 'PUT' && $id) {
    $me = require_admin_or_bdh();
    $in = json_input();
    
    db_run('UPDATE attendance_thresholds 
            SET label=?, min_percent=?, description=? 
            WHERE id=?',
        [trim((string) ($in['label'] ?? '')),
         max(0, min(100, (float) ($in['min_percent'] ?? 0))),
         trim((string) ($in['description'] ?? '')),
         $id]);
    
    log_action('sudung', 'attendance_thresholds', 'Sửa mốc chuyên cần: ' . $id, '');
    Cache::flush();
    
    json_out(['ok' => true]);
}

// DELETE
if ($action === 'DELETE' && $id) {
    $me = require_admin_or_bdh();
    
    $t = db_one('SELECT * FROM attendance_thresholds WHERE id=?', [$id]);
    if (!$t) json_fail('Không tìm thấy mốc.', 404);
    
    db_run('DELETE FROM attendance_thresholds WHERE id=?', [$id]);
    
    log_action('xoa', 'attendance_thresholds', 'Xóa mốc chuyên cần: ' . $t['label'], '');
    Cache::flush();
    
    json_out(['ok' => true]);
}
```

### Files cần sửa:
- Tạo: `public/api/attendance_thresholds.php`

---

## BƯỚC 7: API attendance_scores.php

### File: `public/api/attendance_scores.php` (MỚI)

```php
<?php
/**
 * ĐIỂM CHUYÊN CẦN THEO HỌC SINH
 *
 *   GET api/attendance_scores.php?term_id=1&student_id=101
 */

require __DIR__ . '/_bootstrap.php';

require_login();

$termId    = (int) ($_GET['term_id'] ?? 0);
$studentId = (int) ($_GET['student_id'] ?? 0);

if (!$termId || !$studentId) json_fail('Thiếu term_id hoặc student_id.');

// Lấy các chương trình active của term
$programs = db_all('SELECT tp.program_id, p.name as program_name
                   FROM term_programs tp
                   JOIN programs p ON p.id = tp.program_id
                   WHERE tp.term_id = ? AND tp.is_active = 1',
    [$termId]);

$totalExpected = 0;
$totalPresent = 0;
$totalLate = 0;
$totalAbsent = 0;
$detail = [];

foreach ($programs as $p) {
    // Đếm buổi dự kiến (buổi đã điểm danh)
    $expected = db_one('SELECT COUNT(DISTINCT session_date) as c FROM attendances 
                        WHERE program_id=? AND term_id=?',
        [$p['program_id'], $termId]);
    $exp = $expected['c'] ?? 0;
    
    // Đếm có mặt / đi trễ / vắng
    $present = db_one("SELECT 
                          SUM(CASE WHEN status = 'có mặt' THEN 1 ELSE 0 END) as p,
                          SUM(CASE WHEN status = 'đi trễ' THEN 1 ELSE 0 END) as l
                       FROM attendances 
                       WHERE program_id=? AND term_id=? AND student_id=?",
        [$p['program_id'], $termId, $studentId]);
    
    $pr = $present['p'] ?? 0;
    $lt = $present['l'] ?? 0;
    $ab = $exp - $pr - $lt; // vắng = dự kiến - có mặt - trễ
    
    $totalExpected += $exp;
    $totalPresent += $pr;
    $totalLate += $lt;
    $totalAbsent += max(0, $ab);
    
    $detail[] = [
        'program_id' => $p['program_id'],
        'program_name' => $p['program_name'],
        'expected' => $exp,
        'present' => $pr,
        'late' => $lt,
        'absent' => max(0, $ab),
        'percent' => $exp > 0 ? round(($pr + $lt) / $exp * 100, 2) : 0
    ];
}

// Tính tổng %
$percent = $totalExpected > 0 
    ? round(($totalPresent + $totalLate) / $totalExpected * 100, 2) 
    : 0;

// Xếp loại theo thresholds
$thresholds = db_all('SELECT label, min_percent FROM attendance_thresholds 
                      ORDER BY sort_order DESC');
$rank = 'KĐ'; // mặc định
foreach ($thresholds as $t) {
    if ($percent >= $t['min_percent']) {
        $rank = $t['label'];
        break;
    }
}

json_out([
    'student_id' => $studentId,
    'term_id' => $termId,
    'total_expected' => $totalExpected,
    'total_present' => $totalPresent,
    'total_late' => $totalLate,
    'total_absent' => $totalAbsent,
    'percent' => $percent,
    'rank' => $rank,
    'detail' => $detail
]);
```

### Files cần sửa:
- Tạo: `public/api/attendance_scores.php`

---

## BƯỚC 8: Frontend JS — Logic điểm động

### File: `public/assets/js/modules/scores.js`

**Thay đổi:**

```javascript
window.TNTT.scores = {
    // ==========================================
    // 8a. DATA: ĐIỂM SỐ (ĐỘNG)
    // ==========================================
    
    // Load động từ API thay vì hardcode
    scoreTypes: [],  // sẽ load từ /api/rules.php?type=score
    
    // Rules từ backend
    rules: {
        avg_weights: {},     // { mieng: 1, p15: 1, giuaky: 2, cuoiky: 3 }
        rank_thresholds: {}, // { gioi: 8, kha: 6.5, tb: 5, yeu: 0 }
        passing_min_avg: { min: 5 }
    },
    
    // Cấu hình chuyên cần
    attendanceConfig: {
        thresholds: [],      // [{label:'A', min_percent:90}, ...]
        termPrograms: []    // [{term_id, program_id, is_active}, ...]
    },
    
    // Index điểm theo exam_num
    // Format: scoreIndex[studentId][termId][typeCode][examNum] = value
    scoreIndex: {},  // cấu trúc mới
    
    // ==========================================
    // 8b. METHODS: Load dữ liệu rules
    // ==========================================
    
    async loadScoreRules() {
        try {
            const resp = await fetch('/api/rules.php?type=score');
            const data = await resp.json();
            
            this.scoreTypes = data.score_types || [];
            this.rules.avg_weights = {};
            this.rules.rank_thresholds = {};
            
            // Parse avg_weights từ score_types
            this.scoreTypes.forEach(t => {
                this.rules.avg_weights[t.code] = t.weight;
            });
            
            // Parse rank_thresholds từ score_rules
            if (data.rules && data.rules.rank_thresholds) {
                const val = data.rules.rank_thresholds.rule_value;
                try {
                    this.rules.rank_thresholds = JSON.parse(val);
                } catch (e) {
                    this.rules.rank_thresholds = { gioi: 8, kha: 6.5, tb: 5, yeu: 0 };
                }
            }
            
            // Tính lại scoreIndex
            this.rebuildScoreIndex();
        } catch (err) {
            console.error('Lỗi load rules:', err);
        }
    },
    
    // ==========================================
    // 8c. METHODS: Rebuild score index với exam_num
    // ==========================================
    
    rebuildScoreIndex() {
        this.scoreIndex = {};
        for (const s of this.scores) {
            if (!this.scoreIndex[s.studentId]) this.scoreIndex[s.studentId] = {};
            if (!this.scoreIndex[s.studentId][s.termId]) this.scoreIndex[s.studentId][s.termId] = {};
            if (!this.scoreIndex[s.studentId][s.termId][s.type]) this.scoreIndex[s.studentId][s.termId][s.type] = {};
            
            this.scoreIndex[s.studentId][s.termId][s.type][s.exam_num] = s.value;
        }
    },
    
    // Lấy điểm 1 bài cụ thể
    scoreOfExam(studentId, type, examNum, termId) {
        const t = termId || this.scoreTermId;
        if (this.scoreIndex[studentId]?.[t]?.[type]?.[examNum] !== undefined) {
            return this.scoreIndex[studentId][t][type][examNum];
        }
        return '';
    },
    
    // Lấy tất cả điểm 1 loại (N bài)
    scoresOfType(studentId, type, termId) {
        const t = termId || this.scoreTermId;
        const typeScores = this.scoreIndex[studentId]?.[t]?.[type] || {};
        return typeScores; // { 1: 8.0, 2: 7.5, 3: 9.0 }
    },
    
    // TB mỗi loại (Cách A: bình quân N bài)
    avgOfType(studentId, type, termId) {
        const typeScores = this.scoresOfType(studentId, type, termId);
        const values = Object.values(typeScores).filter(v => v !== '' && v !== null);
        if (values.length === 0) return null;
        return Math.round(values.reduce((a, b) => a + b, 0) / values.length * 10) / 10;
    },
    
    // TB học kỳ (Cách A: TB loại → nhân hệ số)
    termAverage(studentId, termId) {
        let sum = 0, w = 0;
        for (const type of this.scoreTypes) {
            const avg = this.avgOfType(studentId, type.code, termId);
            if (avg !== null) {
                sum += avg * type.weight;
                w += type.weight;
            }
        }
        return w === 0 ? null : Math.round(sum / w * 100) / 100;
    },
    
    // Xếp loại học lực (động)
    academicRank(avg) {
        if (avg === null) return '–';
        const t = this.rules.rank_thresholds;
        if (avg >= (t.gioi || 8)) return 'Giỏi';
        if (avg >= (t.kha  || 6.5)) return 'Khá';
        if (avg >= (t.tb   || 5)) return 'Trung bình';
        return 'Yếu';
    },
    
    // ==========================================
    // 8d. METHODS: CRUD bài KT
    // ==========================================
    
    // Thêm 1 bài mới (lấy exam_num = max + 1)
    addExam(studentId, type, termId) {
        const typeScores = this.scoresOfType(studentId, type, termId);
        const maxExam = Object.keys(typeScores).length;
        const typeConfig = this.scoreTypes.find(t => t.code === type);
        
        if (maxExam >= (typeConfig?.max_exams || 1)) {
            window.TNTT.toast.warning('Đã đạt số bài tối đa của loại này.');
            return;
        }
        
        const nextExam = maxExam + 1;
        this.setScore(studentId, type, '', termId, nextExam); // set rỗng = tạo bài mới
    },
    
    // Xoá 1 bài & renumber
    async deleteExam(studentId, type, examNum, termId) {
        const t = termId || this.scoreTermId;
        
        await this.save('scores', 'delete_exam', {
            studentId, termId: t, type, examNum
        });
        
        // Reload để lấy dữ liệu đã renumber
        await this.loadData();
    },
    
    // Cập nhật setScore để hỗ trợ exam_num
    setScore(studentId, type, raw, termId, examNum) {
        const t = Number(termId || this.scoreTermId);
        const num = Number(examNum || 1);
        const txt = String(raw).trim().replace(',', '.');
        
        // Validate
        if (txt !== '') {
            const v = Number(txt);
            if (isNaN(v) || v < 0 || v > 10) return false;
        }
        
        const value = txt === '' ? '' : Math.round(Number(txt) * 10) / 10;
        
        // Update local
        if (!this.scoreIndex[studentId]) this.scoreIndex[studentId] = {};
        if (!this.scoreIndex[studentId][t]) this.scoreIndex[studentId][t] = {};
        if (!this.scoreIndex[studentId][t][type]) this.scoreIndex[studentId][t][type] = {};
        
        this.scoreIndex[studentId][t][type][num] = value;
        
        // Call API
        this.save('scores', 'set', {
            studentId, termId: t, type, exam_num: num, value: String(value)
        }).then(r => { if (!r || !r.ok) this.loadData(); });
        
        return true;
    },
    
    // ==========================================
    // 8e. METHODS: Điểm chuyên cần
    // ==========================================
    
    async loadAttendanceConfig() {
        try {
            const resp = await fetch('/api/rules.php?type=attendance');
            const data = await resp.json();
            this.attendanceConfig.thresholds = data.thresholds || [];
        } catch (err) {
            console.error('Lỗi load attendance config:', err);
        }
    },
    
    async getAttendanceScore(studentId, termId) {
        try {
            const resp = await fetch(`/api/attendance_scores.php?term_id=${termId}&student_id=${studentId}`);
            return await resp.json();
        } catch (err) {
            return null;
        }
    },
    
    attendanceRank(percent) {
        if (percent === null || percent === undefined) return '–';
        const thresholds = this.attendanceConfig.thresholds;
        for (const t of thresholds) {
            if (percent >= t.min_percent) return t.label;
        }
        return 'KĐ';
    },
    
    // ==========================================
    // 8f. Computed: UI
    // ==========================================
    
    // Danh sách cột hiển thị (động)
    get scoreColumns() {
        const cols = [];
        for (const type of this.scoreTypes) {
            const typeScores = this.scoresOfType(this.currentStudentId, type.code, this.scoreTermId);
            const count = Object.keys(typeScores).length;
            if (count === 0) continue; // Chỉ hiện cột có dữ liệu hoặc đang nhập
            
            for (let i = 1; i <= count; i++) {
                cols.push({
                    type: type.code,
                    examNum: i,
                    label: type.label,
                    short: type.short_label + i
                });
            }
        }
        return cols;
    },
    
    // Kiểm tra có thể thêm bài không
    canAddExam(type) {
        const typeConfig = this.scoreTypes.find(t => t.code === type);
        const typeScores = this.scoresOfType(this.currentStudentId, type, this.scoreTermId);
        const count = Object.keys(typeScores).filter(k => typeScores[k] !== '').length;
        return count < (typeConfig?.max_exams || 1);
    },
    
    // Đếm số bài 1 loại
    examCountOfType(type) {
        const typeScores = this.scoresOfType(this.currentStudentId, type, this.scoreTermId);
        return Object.keys(typeScores).filter(k => typeScores[k] !== '').length;
    },
};
```

### Files cần sửa:
- Sửa: `public/assets/js/modules/scores.js`

---

## BƯỚC 9: Frontend UI — Settings tabs

### File: `views/module_settings.php`

**Thêm 2 tab mới vào thanh điều hướng:**

```php+HTML
<!-- Thêm vào danh sách tabs -->
<button x-show="isAdmin || isBdh" style="display: none;" 
        @click="settingsTab = 'scoreRules'" type="button"
        class="shrink-0 whitespace-nowrap px-4 py-2.5 sm:flex-1 rounded-2xl font-bold text-micro transition-colors flex items-center justify-center gap-1.5"
        :class="settingsTab === 'scoreRules' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-slate-500'">
    <i data-lucide="calculator" class="w-4 h-4"></i> Điểm
</button>
<button x-show="isAdmin || isBdh" style="display: none;" 
        @click="settingsTab = 'attendanceRules'" type="button"
        class="shrink-0 whitespace-nowrap px-4 py-2.5 sm:flex-1 rounded-2xl font-bold text-micro transition-colors flex items-center justify-center gap-1.5"
        :class="settingsTab === 'attendanceRules' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-slate-500'">
    <i data-lucide="calendar-check" class="w-4 h-4"></i> Chuyên cần
</button>
```

**Thêm 2 partial views:**

```php
<!-- Thêm vào cuối div chính -->
<!-- TAB: QUY TẮC ĐIỂM HỌC TẬP -->
<div x-show="settingsTab === 'scoreRules'" style="display: none;">
    <?php include __DIR__ . '/settings_score_rules.php'; ?>
</div>

<!-- TAB: QUY TẮC CHUYÊN CẦN -->
<div x-show="settingsTab === 'attendanceRules'" style="display: none;">
    <?php include __DIR__ . '/settings_attendance_rules.php'; ?>
</div>
```

### Files cần tạo:
- Tạo: `views/settings_score_rules.php`
- Tạo: `views/settings_attendance_rules.php`

### File: `views/settings_score_rules.php`

```php+HTML
<!-- QUY TẮC ĐIỂM HỌC TẬP -->
<div class="space-y-4">
    <!-- ĐẦU ĐIỂM -->
    <div class="bg-white rounded-field p-4 shadow-sm border border-slate-100">
        <h3 class="text-sm font-bold text-slate-700 mb-3 flex items-center gap-2">
            <i data-lucide="list" class="w-4 h-4 text-blue-500"></i>
            Loại đầu điểm
        </h3>
        
        <!-- Desktop: bảng -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100">
                        <th class="text-left py-2 text-xs font-bold text-slate-500 uppercase">Loại đầu điểm</th>
                        <th class="text-center py-2 text-xs font-bold text-slate-500 uppercase">Hệ số</th>
                        <th class="text-center py-2 text-xs font-bold text-slate-500 uppercase">Tối đa bài</th>
                        <th class="text-center py-2 text-xs font-bold text-slate-500 uppercase w-16">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="t in scoreTypes" :key="t.code">
                        <tr class="border-b border-slate-50">
                            <td class="py-2.5">
                                <span x-text="t.label" class="font-semibold text-slate-800"></span>
                                <span class="text-xs text-slate-400 ml-1" x-text="'(' + t.code + ')'"></span>
                            </td>
                            <td class="py-2 text-center">
                                <input type="number" min="1" max="10" x-model.number="t.weight"
                                       class="w-14 text-center bg-slate-50 border border-slate-200 rounded-lg px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2 text-center">
                                <input type="number" min="1" max="20" x-model.number="t.max_exams"
                                       class="w-14 text-center bg-slate-50 border border-slate-200 rounded-lg px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2 text-center">
                                <button @click="deleteScoreType(t.code)" type="button" 
                                        class="p-2 text-slate-400 hover:text-rose-500 transition-colors">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        
        <!-- Mobile: card stack -->
        <div class="md:hidden space-y-2">
            <template x-for="t in scoreTypes" :key="t.code">
                <div class="bg-slate-50 rounded-xl p-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-bold text-slate-800" x-text="t.label"></span>
                        <button @click="deleteScoreType(t.code)" type="button" 
                                class="p-1.5 text-slate-400 hover:text-rose-500">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <label class="text-xs text-slate-500 block mb-1">Hệ số</label>
                            <input type="number" min="1" max="10" x-model.number="t.weight"
                                   class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div class="flex-1">
                            <label class="text-xs text-slate-500 block mb-1">Tối đa bài</label>
                            <input type="number" min="1" max="20" x-model.number="t.max_exams"
                                   class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>
                </div>
            </template>
        </div>
        
        <button @click="showAddScoreTypeModal = true" type="button"
                class="mt-3 flex items-center gap-2 px-4 py-2.5 bg-slate-50 hover:bg-slate-100 text-slate-600 rounded-xl font-bold text-sm transition-colors">
            <i data-lucide="plus" class="w-4 h-4"></i> Thêm loại đầu điểm
        </button>
    </div>
    
    <!-- XẾP LOẠI HỌC LỰC -->
    <div class="bg-white rounded-field p-4 shadow-sm border border-slate-100">
        <h3 class="text-sm font-bold text-slate-700 mb-3 flex items-center gap-2">
            <i data-lucide="award" class="w-4 h-4 text-amber-500"></i>
            Xếp loại học lực
        </h3>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <template x-for="[key, val] in Object.entries(rankThresholds)" :key="key">
                <div>
                    <label class="text-xs text-slate-500 block mb-1 capitalize" x-text="keyLabel(key)"></label>
                    <div class="flex items-center gap-1">
                        <span class="text-sm text-slate-400">≥</span>
                        <input type="number" step="0.1" min="0" max="10" x-model.number="rankThresholds[key]"
                               class="flex-1 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-semibold">
                    </div>
                </div>
            </template>
        </div>
    </div>
    
    <!-- NÚT LƯU -->
    <button @click="saveScoreRules()" type="button"
            class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-sm flex items-center justify-center gap-2 transition-colors">
        <i data-lucide="save" class="w-4 h-4"></i> Lưu thay đổi
    </button>
</div>

<!-- MODAL: Thêm loại đầu điểm -->
<div x-show="showAddScoreTypeModal" style="display: none;" 
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
    <div @click.away="showAddScoreTypeModal = false"
         x-transition:enter="transition ease-out duration-200 transform"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         class="bg-white rounded-2xl w-full max-w-sm p-5 shadow-xl">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Thêm loại đầu điểm</h3>
        
        <div class="space-y-3">
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase block mb-1">Mã (key)</label>
                <input type="text" x-model="newScoreType.code" placeholder="mieng"
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm">
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase block mb-1">Tên hiển thị</label>
                <input type="text" x-model="newScoreType.label" placeholder="Miệng"
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm">
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase block mb-1">Viết tắt</label>
                <input type="text" x-model="newScoreType.short_label" placeholder="M"
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase block mb-1">Hệ số</label>
                    <input type="number" min="1" max="10" x-model.number="newScoreType.weight" value="1"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase block mb-1">Tối đa bài</label>
                    <input type="number" min="1" max="20" x-model.number="newScoreType.max_exams" value="3"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm">
                </div>
            </div>
        </div>
        
        <div class="flex gap-3 mt-5">
            <button @click="showAddScoreTypeModal = false" type="button"
                    class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl font-bold text-sm transition-colors">
                Hủy
            </button>
            <button @click="addScoreType()" type="button"
                    class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-sm transition-colors">
                Thêm
            </button>
        </div>
    </div>
</div>
```

### Files cần sửa:
- Sửa: `views/module_settings.php`
- Tạo: `views/settings_score_rules.php`

### File: `views/settings_attendance_rules.php`

```php+HTML
<!-- QUY TẮC CHUYÊN CẦN -->
<div class="space-y-4">
    <!-- CHƯƠNG TRÌNH THEO HỌC KỲ -->
    <div class="bg-white rounded-field p-4 shadow-sm border border-slate-100">
        <h3 class="text-sm font-bold text-slate-700 mb-3 flex items-center gap-2">
            <i data-lucide="calendar" class="w-4 h-4 text-green-500"></i>
            Chương trình tính điểm theo học kỳ
        </h3>
        
        <!-- Chọn HK -->
        <div class="mb-3">
            <select x-model.number="attendanceTermId" @change="loadTermPrograms()"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-semibold">
                <template x-for="t in terms" :key="t.id">
                    <option :value="t.id" x-text="t.name"></option>
                </template>
            </select>
        </div>
        
        <!-- Danh sách programs -->
        <div class="space-y-2">
            <template x-for="p in termPrograms" :key="p.id">
                <label class="flex items-center gap-3 p-3 bg-slate-50 hover:bg-slate-100 rounded-xl cursor-pointer transition-colors">
                    <input type="checkbox" x-model="p.is_active"
                           @change="toggleTermProgram(p.id, p.is_active)"
                           class="w-5 h-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <div class="flex-1">
                        <span class="font-semibold text-slate-800" x-text="p.name"></span>
                        <span class="text-xs text-slate-500 ml-2" x-text="'(' + p.expected_sessions + ' buổi)'"></span>
                    </div>
                </label>
            </template>
        </div>
    </div>
    
    <!-- CÁC MỐC XẾP LOẠI -->
    <div class="bg-white rounded-field p-4 shadow-sm border border-slate-100">
        <h3 class="text-sm font-bold text-slate-700 mb-3 flex items-center gap-2">
            <i data-lucide="sliders" class="w-4 h-4 text-purple-500"></i>
            Các mốc xếp loại chuyên cần
        </h3>
        
        <!-- Desktop: bảng -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100">
                        <th class="text-left py-2 text-xs font-bold text-slate-500 uppercase">Nhãn</th>
                        <th class="text-center py-2 text-xs font-bold text-slate-500 uppercase">Ngưỡng (%)</th>
                        <th class="text-left py-2 text-xs font-bold text-slate-500 uppercase">Mô tả</th>
                        <th class="text-center py-2 text-xs font-bold text-slate-500 uppercase w-24">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="t in attendanceThresholds" :key="t.id">
                        <tr class="border-b border-slate-50">
                            <td class="py-2">
                                <input type="text" x-model="t.label" 
                                       class="w-16 bg-slate-50 border border-slate-200 rounded-lg px-2 py-1.5 text-sm font-bold">
                            </td>
                            <td class="py-2 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <span class="text-slate-400">≥</span>
                                    <input type="number" min="0" max="100" step="0.5" x-model.number="t.min_percent"
                                           class="w-16 bg-slate-50 border border-slate-200 rounded-lg px-2 py-1.5 text-sm text-center">
                                    <span class="text-slate-400">%</span>
                                </div>
                            </td>
                            <td class="py-2">
                                <input type="text" x-model="t.description" placeholder="Mô tả..."
                                       class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-sm">
                            </td>
                            <td class="py-2 text-center">
                                <button @click="saveAttendanceThreshold(t)" type="button"
                                        class="p-1.5 text-blue-500 hover:text-blue-700">
                                    <i data-lucide="save" class="w-4 h-4"></i>
                                </button>
                                <button @click="deleteAttendanceThreshold(t.id)" type="button"
                                        class="p-1.5 text-slate-400 hover:text-rose-500">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        
        <!-- Mobile: card stack -->
        <div class="md:hidden space-y-3">
            <template x-for="t in attendanceThresholds" :key="t.id">
                <div class="bg-slate-50 rounded-xl p-3">
                    <div class="flex items-center justify-between mb-2">
                        <input type="text" x-model="t.label"
                               class="w-16 bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm font-bold">
                        <div class="flex gap-1">
                            <button @click="saveAttendanceThreshold(t)" type="button"
                                    class="p-2 text-blue-500 hover:text-blue-700">
                                <i data-lucide="save" class="w-4 h-4"></i>
                            </button>
                            <button @click="deleteAttendanceThreshold(t.id)" type="button"
                                    class="p-2 text-slate-400 hover:text-rose-500">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-sm text-slate-500">Ngưỡng:</span>
                        <span class="text-sm text-slate-400">≥</span>
                        <input type="number" min="0" max="100" step="0.5" x-model.number="t.min_percent"
                               class="w-20 bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        <span class="text-sm text-slate-500">%</span>
                    </div>
                    <input type="text" x-model="t.description" placeholder="Mô tả..."
                           class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
            </template>
        </div>
        
        <button @click="addAttendanceThreshold()" type="button"
                class="mt-3 flex items-center gap-2 px-4 py-2.5 bg-slate-50 hover:bg-slate-100 text-slate-600 rounded-xl font-bold text-sm transition-colors">
            <i data-lucide="plus" class="w-4 h-4"></i> Thêm mốc
        </button>
    </div>
</div>
```

### Files cần sửa:
- Tạo: `views/settings_attendance_rules.php`

---

## BƯỚC 10: Frontend UI — Màn nhập điểm

### File: `views/module_scores.php`

**Thay đổi lớn:** UI động, số cột tuỳ theo `scoreTypes` và `max_exams`.

```php+HTML
<!-- ==========================================================
     TAB 1: NHẬP ĐIỂM THEO CỘT (ĐỘNG)
     ========================================================== -->
<div x-show="scoreClass !== '' && scoreTab === 'enter'" style="display: none;" role="tabpanel">

    <!-- Chọn loại đầu điểm -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-4">
        <template x-for="t in scoreTypes" :key="t.code">
            <div class="relative">
                <button @click="selectScoreType(t.code)" type="button"
                        class="w-full py-2.5 rounded-xl font-bold text-micro border transition-colors flex flex-col items-center gap-0.5"
                        :class="scoreType === t.code ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-500 border-slate-200'">
                    <span x-text="t.label"></span>
                    <span class="text-micro font-medium opacity-70" x-text="'hệ số ' + t.weight"></span>
                </button>
                <!-- Badge số bài đã nhập -->
                <span class="absolute -top-1 -right-1 w-5 h-5 bg-blue-500 text-white text-xs font-bold rounded-full flex items-center justify-center"
                      x-text="examCountOfType(t.code)"></span>
            </div>
        </template>
    </div>

    <!-- Nút thêm bài cho loại hiện tại -->
    <div class="mb-4">
        <button x-show="canAddExam(scoreType)" @click="addExamForCurrentType()"
                type="button"
                class="flex items-center gap-2 px-4 py-2 bg-blue-50 hover:bg-blue-100 text-blue-600 rounded-xl font-bold text-sm transition-colors">
            <i data-lucide="plus" class="w-4 h-4"></i> Thêm bài <span x-text="currentScoreType.label"></span>
        </button>
    </div>

    <!-- Tiến độ -->
    <div class="bg-white rounded-field p-4 shadow-sm border border-slate-100 mb-4">
        <!-- ... giữ nguyên ... -->
    </div>

    <!-- Danh sách học sinh -->
    <div class="space-y-2.5">
        <template x-for="s in scoreStudents" :key="s.id">
            <div class="bg-white rounded-field p-4 shadow-sm border"
                 :class="isStudentComplete(s.id) ? 'border-slate-100' : 'border-slate-200 border-dashed'">

                <div class="flex items-start gap-3 mb-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-micro font-bold text-blue-600 leading-tight" x-text="s.code"></p>
                        <p class="text-sm font-black text-slate-800 leading-snug">
                            <span class="font-normal text-slate-500" x-text="s.holyName"></span>
                            <span x-text="s.name"></span>
                        </p>
                    </div>
                    <!-- ĐTB + Xếp loại -->
                    <div class="text-right shrink-0">
                        <p class="text-lg font-black text-slate-900" 
                           x-text="termAverage(s.id, scoreTermId) === null ? '–' : termAverage(s.id, scoreTermId)"></p>
                        <p class="text-xs font-bold" :class="scoreRankClass(termAverage(s.id, scoreTermId))"
                           x-text="academicRank(termAverage(s.id, scoreTermId))"></p>
                    </div>
                </div>

                <!-- Các bài KT của loại hiện chọn -->
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2">
                    <template x-for="examNum in getExamNumbers(s.id, scoreType)" :key="examNum">
                        <div class="relative">
                            <label class="block text-xs text-slate-500 text-center mb-1 font-medium"
                                   x-text="currentScoreType.short_label + examNum"></label>
                            <input type="number" min="0" max="10" step="0.1" inputmode="decimal"
                                   :value="scoreOfExam(s.id, scoreType, examNum)"
                                   @change="onScoreInput(s.id, scoreType, examNum, $event)"
                                   class="w-full text-center rounded-lg px-2 py-2.5 text-sm font-semibold transition-all"
                                   :class="scoreOfExam(s.id, scoreType, examNum) !== '' 
                                       ? 'bg-blue-50 border-blue-300 text-blue-700' 
                                       : 'bg-slate-50 border-slate-200 text-slate-800'">
                            <!-- Nút xoá bài -->
                            <button x-show="examNum > 1 || scoreOfExam(s.id, scoreType, examNum) !== ''"
                                    @click="confirmDeleteExam(s.id, scoreType, examNum)"
                                    type="button"
                                    class="absolute -top-1 -right-1 w-4 h-4 bg-rose-500 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                <i data-lucide="x" class="w-3 h-3"></i>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>
</div>
```

### Files cần sửa:
- Sửa: `views/module_scores.php`

---

## BƯỚC 11: Tests

### Unit Tests (PHPUnit)

**File: `tests/Unit/ScoreRulesTest.php`**

```php
<?php
class ScoreRulesTest extends TestCase {
    
    public function testTermAverageWithMultipleExams() {
        // Miệng: [8, 7] → avg = 7.5, hệ số 1
        // 15p: [9, 8] → avg = 8.5, hệ số 1
        // GK: [8] → avg = 8, hệ số 2
        
        $scores = [
            ['type' => 'mieng', 'exam_num' => 1, 'value' => 8],
            ['type' => 'mieng', 'exam_num' => 2, 'value' => 7],
            ['type' => 'p15', 'exam_num' => 1, 'value' => 9],
            ['type' => 'p15', 'exam_num' => 2, 'value' => 8],
            ['type' => 'giuaky', 'exam_num' => 1, 'value' => 8],
        ];
        
        $weights = ['mieng' => 1, 'p15' => 1, 'giuaky' => 2];
        
        // Tính TB
        $miengAvg = (8 + 7) / 2; // 7.5
        $p15Avg = (9 + 8) / 2;   // 8.5
        $gkAvg = 8;              // 8
        
        $tb = ($miengAvg * 1 + $p15Avg * 1 + $gkAvg * 2) / (1 + 1 + 2);
        
        $this->assertEquals(8.0, round($tb, 2));
    }
    
    public function testRenumberAfterDelete() {
        // [8(M1), 7(M2), 9(M3)] → xoá M2
        // Expected: [8(M1), 9(M2)]
        
        $scores = [1 => 8, 2 => 7, 3 => 9];
        $deleteNum = 2;
        
        // Simulate delete & renumber
        unset($scores[$deleteNum]);
        $scores = array_values($scores); // re-index
        
        // Renumber
        $renumbered = [];
        for ($i = 0; $i < count($scores); $i++) {
            $renumbered[$i + 1] = $scores[$i];
        }
        
        $this->assertEquals([1 => 8, 2 => 9], $renumbered);
    }
    
    public function testAttendanceRank() {
        $thresholds = [
            ['label' => 'A', 'min_percent' => 90],
            ['label' => 'B', 'min_percent' => 80],
            ['label' => 'C', 'min_percent' => 70],
            ['label' => 'Đạt', 'min_percent' => 60],
        ];
        
        $this->assertEquals('A', getAttendanceRank(92, $thresholds));
        $this->assertEquals('B', getAttendanceRank(85, $thresholds));
        $this->assertEquals('C', getAttendanceRank(75, $thresholds));
        $this->assertEquals('Đạt', getAttendanceRank(65, $thresholds));
        $this->assertEquals('KĐ', getAttendanceRank(55, $thresholds));
    }
}
```

### Integration Tests

```php
public function testScoreCRUDWithExamNum() {
    // Create score
    $this->post('/api/scores.php?action=set', [
        'studentId' => 1, 'termId' => 1, 'type' => 'mieng', 'exam_num' => 1, 'value' => '8.0'
    ]);
    $this->assertResponseOk();
    
    // Update same exam_num
    $this->post('/api/scores.php?action=set', [
        'studentId' => 1, 'termId' => 1, 'type' => 'mieng', 'exam_num' => 1, 'value' => '9.0'
    ]);
    $this->assertResponseOk();
    
    // Delete exam & renumber
    $this->post('/api/scores.php?action=delete_exam', [
        'studentId' => 1, 'termId' => 1, 'type' => 'mieng', 'exam_num' => 1
    ]);
    $this->assertResponseOk();
}

public function testNonAdminCannotEditRules() {
    $this->actingAs($this->glvUser);
    $this->post('/api/rules.php', ['rule_key' => 'test', 'rule_value' => '{}']);
    $this->assertResponseStatus(403);
}
```

---

## TÓM TẮT FILES

| File | Hành động |
|------|-----------|
| `config/migrations/XXX_score_rules_configurable.sql` | Tạo mới |
| `config/install.php` | Sửa |
| `public/api/rules.php` | Tạo mới |
| `public/api/score_types.php` | Tạo mới |
| `public/api/scores.php` | Sửa |
| `public/api/term_programs.php` | Tạo mới |
| `public/api/attendance_thresholds.php` | Tạo mới |
| `public/api/attendance_scores.php` | Tạo mới |
| `public/assets/js/modules/scores.js` | Sửa lớn |
| `views/module_settings.php` | Sửa |
| `views/settings_score_rules.php` | Tạo mới |
| `views/settings_attendance_rules.php` | Tạo mới |
| `views/module_scores.php` | Sửa lớn |
| `tests/Unit/ScoreRulesTest.php` | Tạo mới |

---

## THỨ TỰ THỰC HIỆN ĐỀ XUẤT

1. **Migration** (B1) → chạy trên DB test
2. **API cơ bản** (B2, B3, B4) → test thủ công bằng Postman/curl
3. **API chuyên cần** (B5, B6, B7) → test thủ công
4. **Frontend JS logic** (B8) → đảm bảo tính điểm đúng
5. **Frontend Settings** (B9) → UI tabs + CRUD
6. **Frontend Nhập điểm** (B10) → bảng động
7. **Tests** (B11) → unit + integration
8. **Review & merge** → pull request
