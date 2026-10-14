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

// Whitelist + schema cho các rule đã biết. Mỗi rule_key có schema kiểm
// JSON (đủ khoá, đúng kiểu, giá trị trong khoảng cho phép).
$RULE_SCHEMAS = [
    'rank_thresholds' => [
        'type'  => 'threshold',
        'shape' => function (array $v): ?string {
            foreach (['gioi', 'kha', 'tb', 'yeu'] as $k) {
                if (!isset($v[$k]) || !is_numeric($v[$k])) return "thiếu/không phải số: $k";
                $n = (float) $v[$k];
                if ($n < 0 || $n > 10) return "$k phải 0..10";
            }
            $vals = [(float)$v['gioi'], (float)$v['kha'], (float)$v['tb'], (float)$v['yeu']];
            if ($vals[0] <= $vals[1] || $vals[1] <= $vals[2] || $vals[2] <= $vals[3]) {
                return 'thứ tự phải gioi > kha > tb > yeu';
            }
            return null;
        },
    ],
    'passing_min_avg' => [
        'type'  => 'threshold',
        'shape' => function (array $v): ?string {
            if (!isset($v['min']) || !is_numeric($v['min'])) return 'thiếu/không phải số: min';
            if ((float) $v['min'] < 0 || (float) $v['min'] > 10) return 'min phải 0..10';
            return null;
        },
    ],
];

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
        $rules = db_all('SELECT rule_key, rule_label, rule_type, rule_value, description
                         FROM score_rules ORDER BY rule_key');
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
                $rules = db_all('SELECT rule_key, rule_label, rule_type, rule_value, description
                                 FROM score_rules ORDER BY rule_key');
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

    if (!isset($RULE_SCHEMAS[$ruleKey])) {
        json_fail('rule_key không hợp lệ. Hỏi admin để thêm vào whitelist.', 400);
    }
    $schema = $RULE_SCHEMAS[$ruleKey];
    if ($ruleType !== '' && $ruleType !== $schema['type']) {
        json_fail("rule_type cho '$ruleKey' phải là '" . $schema['type'] . "'.", 400);
    }
    $decoded = is_string($ruleValue) ? json_decode($ruleValue, true) : (array) $ruleValue;
    if (!is_array($decoded)) {
        json_fail('rule_value phải là JSON object.', 400);
    }
    $err = ($schema['shape'])($decoded);
    if ($err !== null) {
        json_fail('rule_value không khớp schema: ' . $err, 400);
    }
    // Ép ruleType về đúng nếu caller không truyền
    if ($ruleType === '') $ruleType = $schema['type'];

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
