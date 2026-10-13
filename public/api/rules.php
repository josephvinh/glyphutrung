<?php
/**
 * QUY TẮC HỆ THỐNG
 *
 *   GET  api/rules.php?type=score|attendance|all
 *   POST api/rules.php  { rule_key, rule_value, rule_label?, rule_type? }
 */

require __DIR__ . '/_bootstrap.php';

// Ai cũng đọc được
require_login();

// Chỉ admin/bdh mới sửa
function require_admin_or_bdh(): array {
    global $me;
    if (!in_array($me['role_code'], ['admin', 'bdh'])) {
        json_fail('Chỉ Quản Trị hoặc Ban Điều Hành mới có quyền.', 403);
    }
    return $me;
}

$me = $me ?? require_login();
$action = $_SERVER['REQUEST_METHOD'];
$type = $_GET['type'] ?? '';

// ============================================================
// GET
// ============================================================

if ($action === 'GET') {
    if ($type === 'score' || $type === '') {
        $scoreTypes = db_all('SELECT code, label, short_label, weight, max_exams, category, sort_order
                              FROM score_types ORDER BY sort_order');
        $rules = db_all('SELECT rule_key, rule_label, rule_type, rule_value, description
                         FROM score_rules ORDER BY rule_key');
        $rulesMap = [];
        foreach ($rules as $r) {
            $rulesMap[$r['rule_key']] = $r;
        }
        json_out(['score_types' => $scoreTypes, 'rules' => $rulesMap]);
    }

    if ($type === 'attendance') {
        $thresholds = db_all('SELECT id, sort_order, label, min_percent, description
                              FROM attendance_thresholds ORDER BY sort_order DESC');
        $rules = db_all("SELECT rule_key, rule_label, rule_type, rule_value, description
                         FROM score_rules WHERE rule_key LIKE 'attendance_%'");
        $rulesMap = [];
        foreach ($rules as $r) {
            $rulesMap[$r['rule_key']] = $r;
        }
        json_out(['thresholds' => $thresholds, 'rules' => $rulesMap]);
    }

    if ($type === 'all') {
        json_out([
            'score' => (static function() {
                $scoreTypes = db_all('SELECT code, label, short_label, weight, max_exams, category, sort_order
                                      FROM score_types ORDER BY sort_order');
                $rules = db_all('SELECT rule_key, rule_label, rule_type, rule_value, description
                                 FROM score_rules ORDER BY rule_key');
                $rulesMap = [];
                foreach ($rules as $r) { $rulesMap[$r['rule_key']] = $r; }
                return ['score_types' => $scoreTypes, 'rules' => $rulesMap];
            })(),
            'attendance' => (static function() {
                $thresholds = db_all('SELECT id, sort_order, label, min_percent, description
                                      FROM attendance_thresholds ORDER BY sort_order DESC');
                $rules = db_all("SELECT rule_key, rule_label, rule_type, rule_value, description
                                 FROM score_rules WHERE rule_key LIKE 'attendance_%'");
                $rulesMap = [];
                foreach ($rules as $r) { $rulesMap[$r['rule_key']] = $r; }
                return ['thresholds' => $thresholds, 'rules' => $rulesMap];
            })(),
        ]);
    }

    json_fail('type không hợp lệ. Dùng: score, attendance, all', 400);
}

// ============================================================
// POST — tạo/cập nhật rule
// ============================================================

if ($action === 'POST') {
    $me = require_admin_or_bdh();
    $in = json_input();

    $ruleKey = trim((string) ($in['rule_key'] ?? ''));
    $ruleValue = $in['rule_value'] ?? null;
    $ruleLabel = trim((string) ($in['rule_label'] ?? ''));
    $ruleType = trim((string) ($in['rule_type'] ?? ''));
    $description = trim((string) ($in['description'] ?? ''));

    if ($ruleKey === '') json_fail('Thiếu rule_key.');

    // Encode JSON
    if (is_array($ruleValue)) {
        $jsonValue = json_encode($ruleValue);
    } elseif (is_string($ruleValue)) {
        // Thử parse, nếu là JSON string hợp lệ thì giữ nguyên
        json_decode($ruleValue);
        $jsonValue = (json_last_error() === JSON_ERROR_NONE) ? $ruleValue : json_encode($ruleValue);
    } else {
        $jsonValue = json_encode($ruleValue);
    }

    // Kiểm tra rule đã tồn tại chưa
    $existing = db_one('SELECT id FROM score_rules WHERE rule_key = ?', [$ruleKey]);

    if ($existing) {
        // UPDATE
        if ($ruleLabel !== '' || $ruleType !== '' || $description !== '') {
            db_run('UPDATE score_rules
                    SET rule_value = ?,
                        rule_label = COALESCE(NULLIF(?, ""), rule_label),
                        rule_type = COALESCE(NULLIF(?, ""), rule_type),
                        description = COALESCE(NULLIF(?, ""), description),
                        updated_by = ?
                    WHERE rule_key = ?',
                [$jsonValue, $ruleLabel, $ruleType, $description, $me['id'], $ruleKey]);
        } else {
            db_run('UPDATE score_rules SET rule_value = ?, updated_by = ? WHERE rule_key = ?',
                [$jsonValue, $me['id'], $ruleKey]);
        }
    } else {
        // INSERT — rule_type và rule_label bắt buộc cho rule mới
        if ($ruleType === '' || $ruleLabel === '') {
            json_fail('Rule mới cần có rule_type và rule_label.');
        }
        if (!in_array($ruleType, ['weight', 'threshold', 'formula'], true)) {
            json_fail('rule_type không hợp lệ. Dùng: weight, threshold, formula.');
        }
        db_run('INSERT INTO score_rules (rule_key, rule_label, rule_type, rule_value, description, updated_by)
                VALUES (?,?,?,?,?,?)',
            [$ruleKey, $ruleLabel, $ruleType, $jsonValue, $description ?: null, $me['id']]);
    }

    log_action('sudung', 'rules', 'Cập nhật quy tắc: ' . $ruleKey, $jsonValue);
    Cache::flush();

    json_out(['ok' => true, 'rule_key' => $ruleKey]);
}
