<?php
/**
 * NỀN CHO TRANG HTML (khác _bootstrap.php dùng cho endpoint JSON)
 *
 * Không đặt header Content-Type JSON, vì đây là trang giao diện.
 * Cung cấp page_bootstrap(): gói dữ liệu cấu hình mà app.js cần ngay
 * lúc khởi động — tài khoản, niên khoá, phân quyền, khối lớp.
 */

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/csrf.php';

/**
 * Gói dữ liệu khởi động.
 * Chỉ gồm những thứ CỐ ĐỊNH trong phiên làm việc; dữ liệu nghiệp vụ
 * (thiếu nhi, điểm danh...) sẽ gọi API riêng khi vào từng module.
 */
function page_bootstrap(array $me): array
{
    // Generate CSRF token for the session
    $csrfToken = csrf_token();

    $year  = db_one('SELECT * FROM school_years WHERE is_current = 1 LIMIT 1');
    $terms = $year ? db_all('SELECT id, name, start_date, end_date FROM terms
                              WHERE year_id = ? ORDER BY sort_order', [$year['id']]) : [];

    // Phân quyền gom theo module để giao diện tra cứu O(1)
    $perms = [];
    foreach (db_all('SELECT module_key, role_code, level FROM permissions') as $p) {
        $perms[$p['module_key']][$p['role_code']] = $p['level'];
    }

    $modules = db_all('SELECT module_key, label, icon, color, area, is_enabled
                         FROM modules ORDER BY area, sort_order');

    $enabled = [];
    foreach ($modules as $m) $enabled[$m['module_key']] = (bool) $m['is_enabled'];

    // Member assignments for the current user
    $assignments = effective_assignments((int) $me['id']);
    $primary = primary_assignment((int) $me['id']);

    return [
        'user' => [
            'memberId'      => (int) $me['id'],
            'holyName'      => $me['holy_name'],
            'fullName'      => $me['full_name'],
            'phone'         => $me['phone'],
            'birthDate'     => $me['birth_date'] ?? '',
            'role'          => $me['role_code'],
            'roleTitle'     => $me['title_label'] ?? $me['role_label'],
            'managedBlock'  => $me['block_name'] ?? '',
            'assignedClass' => $me['class_name'] ?? '',
        ],
        'assignments' => array_map(function($a) {
            return [
                'id'        => (int) $a['id'],
                'role'      => $a['role_code'],
                'roleLabel' => $a['role_label'],
                'scope'     => $a['role_scope'],
                'blockId'   => $a['block_id'] ? (int) $a['block_id'] : null,
                'blockName' => $a['block_name'] ?? '',
                'classId'   => $a['class_id'] ? (int) $a['class_id'] : null,
                'className' => $a['class_name'] ?? '',
                'isPrimary' => (bool) $a['is_primary'],
                'fromDate'  => $a['from_date'],
                'toDate'    => $a['to_date'],
                'note'      => $a['note'] ?? '',
            ];
        }, $assignments),
        'primaryAssignment' => $primary ? [
            'role'      => $primary['role_code'],
            'roleLabel' => $primary['role_label'],
            'scope'     => $primary['role_scope'],
            'className' => $primary['class_name'] ?? '',
            'blockName' => $primary['block_name'] ?? '',
        ] : null,
        'year' => $year ? [
            'id'        => (int) $year['id'],
            'name'      => $year['name'],
            'startDate' => $year['start_date'],
            'endDate'   => $year['end_date'],
            'status'    => $year['status'],
        ] : null,
        'terms' => array_map(fn($t) => [
            'id'   => (int) $t['id'],
            'name' => $t['name'],
            'from' => $t['start_date'],
            'to'   => $t['end_date'],
        ], $terms),
        'roles' => array_map(fn($r) => [
            'value' => $r['code'], 'label' => $r['label'],
            'level' => (int) $r['level'], 'scope' => $r['scope'], 'desc' => $r['descr'],
        ], db_all('SELECT code, label, level, scope, descr FROM roles ORDER BY level DESC')),
        'blocks'  => array_column(db_all('SELECT name FROM blocks ORDER BY sort_order'), 'name'),
        'classes' => array_map(fn($c) => [
            'id'        => (int) $c['id'],
            'name'      => $c['name'],
            'block'     => $c['block_name'],
            'nextClass' => $c['is_final'] ? 'RA_TRUONG' : ($c['next_name'] ?? ''),
        ], db_all(
            'SELECT c.id, c.name, c.is_final, b.name AS block_name, n.name AS next_name
               FROM classes c
               JOIN blocks b ON b.id = c.block_id
               LEFT JOIN classes n ON n.id = c.next_class_id
              ORDER BY b.sort_order, c.sort_order')),
        'permissions'   => $perms,
        'csrfToken'     => $csrfToken,
        'modules'       => array_map(fn($m) => [
            'key'   => $m['module_key'], 'label' => $m['label'], 'icon' => $m['icon'],
            'color' => $m['color'],      'area'  => $m['area'],
        ], $modules),
        'moduleEnabled' => $enabled,
        'config' => [
            'cutoffMinutes'  => app_config('cutoff_minutes'),
            'passScore'      => app_config('pass_score'),
            'passAttendance' => app_config('pass_attendance'),
        ],
    ];
}
