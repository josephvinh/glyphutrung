<?php
/**
 * TRÌNH CÀI ĐẶT
 *
 * Chạy một lần để dựng cơ sở dữ liệu và nạp dữ liệu khởi tạo.
 * An toàn khi chạy lại: mọi lệnh đều IF NOT EXISTS hoặc INSERT IGNORE,
 * KHÔNG có lệnh nào xóa dữ liệu đang có.
 *
 *   php config/install.php
 */

require __DIR__ . '/db.php';
require __DIR__ . '/_guard_setup.php';
guard_setup('install.php');

$cli = PHP_SAPI === 'cli';
if (!$cli) header('Content-Type: text/plain; charset=utf-8');

function say(string $msg): void { echo $msg . PHP_EOL; }

$cfg = db_config();   // đã áp biến môi trường TNTT_DB_* (đúng DB đang kết nối)

// ---------------------------------------------------------------
// 1. KIỂM CƠ SỞ DỮ LIỆU ĐÃ CÓ CHƯA
//
// KHÔNG tự tạo cơ sở dữ liệu. Trên hosting dùng chung, tài khoản CSDL
// không có quyền CREATE DATABASE — cPanel mới là nơi tạo. Cố tạo chỉ
// nhận "Access denied" rồi dừng giữa chừng.
// ---------------------------------------------------------------
try {
    db();   // kết nối theo đúng cấu hình, đã chọn sẵn dbname
} catch (PDOException $e) {
    $m = $e->getMessage();
    say('LỖI: không mở được cơ sở dữ liệu "' . $cfg['name'] . '".');
    say('');
    if (str_contains($m, 'Unknown database')) {
        say('Cơ sở dữ liệu này chưa tồn tại. Vào cPanel > MySQL Databases để tạo,');
        say('rồi gán người dùng "' . $cfg['user'] . '" vào nó với ALL PRIVILEGES.');
    } elseif (str_contains($m, 'Access denied')) {
        // MySQL cố tình không phân biệt "CSDL không có" với "không có
        // quyền" — để người ngoài không dò được CSDL nào tồn tại. Nên
        // phải nêu cả ba khả năng.
        say('Một trong ba nguyên nhân sau:');
        say('  1. Cơ sở dữ liệu "' . $cfg['name'] . '" chưa được tạo');
        say('  2. Người dùng "' . $cfg['user'] . '" chưa được gán vào cơ sở dữ liệu đó');
        say('  3. Sai mật khẩu trong config/config.php');
        say('');
        say('Vào cPanel > MySQL Databases, kiểm cả ba. Nhớ tên do cPanel đặt có');
        say('tiền tố tài khoản, ví dụ "taikhoan_tntt" chứ không phải "tntt".');
        say('Gán người dùng vào CSDL ở mục Add User To Database, chọn ALL PRIVILEGES.');
    } else {
        say($m);
    }
    exit(1);
}
say('✓ Cơ sở dữ liệu `' . (db_val('SELECT DATABASE()') ?: $cfg['name']) . '` mở được');

// ---------------------------------------------------------------
// 2. CHẠY LƯỢC ĐỒ
// ---------------------------------------------------------------
$sql = file_get_contents(__DIR__ . '/schema.sql');
if ($sql === false) { say('LỖI: không đọc được schema.sql'); exit(1); }

// Bỏ dòng chú thích rồi tách theo dấu chấm phẩy
$sql   = preg_replace('/^\s*--.*$/m', '', $sql);
$parts = array_filter(array_map('trim', explode(';', $sql)), fn($s) => $s !== '');

$pdo = db();
$made = 0; $skipped = 0;
foreach ($parts as $stmt) {
    try {
        $pdo->exec($stmt);
        if (stripos($stmt, 'CREATE TABLE') !== false) $made++;
    } catch (PDOException $e) {
        // Khóa/ràng buộc đã tồn tại từ lần chạy trước thì bỏ qua, không phải lỗi.
        // So khớp không phân biệt hoa thường vì mỗi phiên bản MySQL/MariaDB
        // lại viết thông điệp một kiểu.
        $msg = strtolower($e->getMessage());
        $benign = ['duplicate key name', 'duplicate foreign key', 'duplicate check constraint',
                   'already exists', 'duplicate column name'];
        foreach ($benign as $b) {
            if (str_contains($msg, $b)) { $skipped++; continue 2; }
        }
        say('LỖI SQL: ' . $e->getMessage());
        say('Câu lệnh: ' . substr($stmt, 0, 120) . '...');
        exit(1);
    }
}
say("✓ Đã chạy lược đồ ($made lệnh tạo bảng, $skipped lệnh bỏ qua vì đã có)");

// ---------------------------------------------------------------
// 2b. NÂNG CẤP LƯỢC ĐỒ CHO CSDL ĐÃ TỒN TẠI
//
// CREATE TABLE IF NOT EXISTS không đụng vào bảng đã có, nên cột mới
// phải thêm bằng ALTER. Mỗi lệnh đều bọc try/catch — chạy lại lần hai
// báo "đã có cột" thì bỏ qua, không phải lỗi.
// ---------------------------------------------------------------
$migrations = [
    "CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        endpoint VARCHAR(500) NOT NULL,
        ua VARCHAR(255) NULL,
        created_at DATETIME NOT NULL,
        last_ok_at DATETIME NULL,
        token_hash CHAR(64) NULL,
        ring_seq INT UNSIGNED NOT NULL DEFAULT 0,
        ring_done INT UNSIGNED NOT NULL DEFAULT 0,
        ring_lock_until DATETIME NULL,
        ring_tries TINYINT UNSIGNED NOT NULL DEFAULT 0,
        last_fail_code SMALLINT NULL,
        UNIQUE KEY uq_push (endpoint(255)),
        UNIQUE KEY uq_push_token (token_hash),
        KEY idx_push_member (member_id),
        CONSTRAINT fk_push_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS push_outbox (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        title VARCHAR(120) NOT NULL,
        body VARCHAR(255) NOT NULL,
        url VARCHAR(120) NOT NULL DEFAULT '/',
        tag VARCHAR(48) NOT NULL DEFAULT 'tntt-chung',
        created_at DATETIME NOT NULL,
        taken_at DATETIME NULL,
        KEY idx_outbox_cho (member_id, taken_at, id),
        CONSTRAINT fk_outbox_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "ALTER TABLE members MODIFY status
        ENUM('chờ duyệt','đang phục vụ','tạm nghỉ','đã nghỉ') NOT NULL DEFAULT 'đang phục vụ'",
    "ALTER TABLE members ADD COLUMN register_note VARCHAR(255) NULL",
    "ALTER TABLE members ADD COLUMN registered_at DATETIME NULL",
    "ALTER TABLE members ADD COLUMN birth_date DATE NULL",
    // Cột lịch/thi đua cho chương trình (buổi lặp nhiều thứ, khoảng ngày, QR,
    // và cờ count_for_emulation = tích Mộc). CSDL mới đã có sẵn từ schema.sql;
    // các ALTER này nâng cấp CSDL cũ (trùng cột thì bỏ qua theo catch bên dưới).
    "ALTER TABLE programs ADD COLUMN count_for_emulation TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE programs ADD COLUMN days_of_week VARCHAR(32) NULL",
    "ALTER TABLE programs ADD COLUMN absent_time TIME NULL",
    "ALTER TABLE programs ADD COLUMN effective_from DATE NULL",
    "ALTER TABLE programs ADD COLUMN effective_to DATE NULL",
    "ALTER TABLE programs ADD COLUMN allow_qr TINYINT(1) NOT NULL DEFAULT 1",
    "ALTER TABLE programs ADD COLUMN auto_close_after_event TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE programs ADD COLUMN color VARCHAR(48) NULL",
    "ALTER TABLE programs ADD COLUMN icon VARCHAR(48) NULL",
    "ALTER TABLE programs ADD COLUMN sort_order TINYINT NOT NULL DEFAULT 1",
    // Web Push: token máy (băm SHA-256) + hàng đợi chuông bền (#100, #107)
    "ALTER TABLE push_subscriptions ADD COLUMN token_hash CHAR(64) NULL",
    "ALTER TABLE push_subscriptions ADD UNIQUE KEY uq_push_token (token_hash)",
    "ALTER TABLE push_subscriptions ADD COLUMN ring_seq INT UNSIGNED NOT NULL DEFAULT 0",
    "ALTER TABLE push_subscriptions ADD COLUMN ring_done INT UNSIGNED NOT NULL DEFAULT 0",
    "ALTER TABLE push_subscriptions ADD COLUMN ring_lock_until DATETIME NULL",
    "ALTER TABLE push_subscriptions ADD COLUMN ring_tries TINYINT UNSIGNED NOT NULL DEFAULT 0",
    "ALTER TABLE push_subscriptions ADD COLUMN last_fail_code SMALLINT NULL",
    // PR-2: Student retention - ẩn PII sau khi nghỉ
    "ALTER TABLE students ADD COLUMN hidden_at TIMESTAMP NULL DEFAULT NULL",
    "ALTER TABLE students ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL",
    "CREATE INDEX idx_students_hidden ON students(hidden_at)",
    "CREATE INDEX idx_students_deleted ON students(deleted_at)",
];
$mig = 0;
foreach ($migrations as $sqlMig) {
    try { $pdo->exec($sqlMig); $mig++; }
    catch (PDOException $e) {
        $msg = strtolower($e->getMessage());
        if (!str_contains($msg, 'duplicate column name') && !str_contains($msg, 'already exists')
            && !str_contains($msg, 'duplicate key name')) {
            say('LỖI nâng cấp: ' . $e->getMessage());
            exit(1);
        }
    }
}
say("✓ Nâng cấp lược đồ ($mig lệnh áp dụng)");

// ---------------------------------------------------------------
// 3. DỮ LIỆU KHỞI TẠO
// ---------------------------------------------------------------

// --- Vai trò ---
$roles = [
    ['admin',         'Quản Trị Hệ Thống', 5, 'toàn đoàn', 'Toàn quyền, kể cả cấu hình hệ thống'],
    ['bdh',           'Ban Điều Hành',     4, 'toàn đoàn', 'Quản lý toàn đoàn: khối lớp, nhân sự, chương trình'],
    ['truong_khoi',   'Trưởng Khối',       3, 'khối',      'Quản lý các lớp trong khối mình'],
    ['glv_chu_nhiem', 'GLV Chủ Nhiệm',     2, 'lớp',       'Phụ trách một lớp, được duyệt đơn của lớp'],
    ['glv',           'Giáo Lý Viên',      1, 'lớp',       'Dạy và điểm danh lớp được phân công'],
    ['du_bi',         'Dự Bị',             1, 'lớp',       'Hỗ trợ tại lớp được phân công'],
    ['thu_thu',       'Thủ Thư',           1, 'toàn đoàn', 'Phục vụ đổi quà toàn đoàn'],
];
foreach ($roles as $r) {
    db_run('INSERT IGNORE INTO roles (code, label, level, scope, descr) VALUES (?,?,?,?,?)', $r);
}
say('✓ Vai trò: ' . count($roles));

// --- Chức danh ---
$titles = [
    ['admin', 'Quản trị viên', 1],
    ['bdh', 'Đoàn Trưởng', 1], ['bdh', 'Đoàn Phó', 2], ['bdh', 'Thư Ký', 3],
    ['bdh', 'Thủ Quỹ', 4], ['bdh', 'Ủy Viên', 5],
    ['truong_khoi', 'Trưởng Khối', 1], ['truong_khoi', 'Phó Khối', 2],
    ['glv_chu_nhiem', 'GLV Chủ Nhiệm', 1],
    ['glv', 'GLV Phụ Tá', 1], ['glv', 'Huynh Trưởng', 2], ['glv', 'Dự Trưởng', 3],
    ['du_bi', 'Dự Bị', 1],
];
foreach ($titles as $t) {
    db_run('INSERT IGNORE INTO titles (role_code, label, sort_order) VALUES (?,?,?)', $t);
}
say('✓ Chức danh: ' . count($titles));

// --- Module & phân quyền ---
$modules = [
    ['students',      'Danh sách',    'users',           'text-blue-600',    'glv', 1],
    ['attendance',    'Điểm danh',    'clipboard-check', 'text-blue-600',    'glv', 2],
    ['leave',         'Xin phép',     'file-text',       'text-blue-600',    'glv', 3],
    ['birthdays',     'Sinh nhật',    'cake',            'text-rose-500',    'glv', 4],
    ['reporthub',     'Báo cáo',      'bar-chart-3',     'text-emerald-600', 'glv', 5],
    ['analytics',     'Phân tích',    'bar-chart-2',     'text-purple-600',  'glv', 6],
    ['stats',         'Thống kê',     'bar-chart-3',     'text-emerald-600', 'glv', 7],
    ['org',           'Khối lớp',     'layers',          'text-indigo-600',  'glv', 8],
    ['reports',       'Sổ liên lạc',  'clipboard-list',  'text-amber-600',   'glv', 9],
    ['scores',        'Điểm số',      'graduation-cap',  'text-violet-600',  'glv', 10],
    ['notes',         'Lịch của tôi', 'calendar-check',  'text-teal-600',    'glv', 11],
    ['guide',         'Hướng dẫn',    'info',            'text-sky-600',     'glv', 12],
    ['promotion',     'Lên lớp',      'trending-up',     'text-violet-600',  'bdh', 1],
    ['programs',      'Chương trình', 'calendar-plus',   'text-amber-600',   'bdh', 2],
    ['calendar',      'Lịch trình',   'calendar-days',   'text-teal-600',    'bdh', 3],
    ['announcements', 'Thông báo',    'megaphone',       'text-rose-500',    'bdh', 4],
    ['staff',         'Nhân sự',      'user-cog',        'text-cyan-600',    'bdh', 5],
    ['years',         'Niên khoá',    'calendar-range',  'text-indigo-600',  'bdh', 6],
    ['gifts',         'Danh mục quà', 'gift',            'text-pink-600',    'bdh', 13],
    ['rewards',       'Đổi quà',      'shopping-bag',    'text-pink-600',    'bdh', 14],
];
foreach ($modules as $m) {
    db_run('INSERT IGNORE INTO modules (module_key, label, icon, color, area, sort_order)
            VALUES (?,?,?,?,?,?)', $m);
}

// none | view | edit — theo thứ tự: admin, bdh, truong_khoi, glv_chu_nhiem, glv, du_bi
$perms = [
    'students'      => ['edit','edit','edit','edit','view','view'],
    'attendance'    => ['edit','edit','edit','edit','edit','edit'],
    'leave'         => ['edit','edit','edit','edit','view','view'],
    'birthdays'     => ['view','view','view','view','view','view'],
    'reporthub'     => ['view','view','view','view','view','view'],
    'stats'         => ['view','view','view','view','view','view'],
    'analytics'     => ['view','view','view','view','view','view'],
    'calendar'      => ['view','view','view','view','view','view'],
    'org'           => ['edit','edit','edit','view','view','view'],
    // Duyệt người mới là việc của Ban Điều Hành, cấp dưới chỉ xem
    'staff'         => ['edit','edit','view','view','view','view'],
    'years'         => ['edit','edit','view','view','view','view'],
    // BĐH chỉ GIÁM SÁT điểm số + phiếu liên lạc (view); nhập/sửa là việc của
    // GVCN/GLV lớp. Admin giữ edit để xử lý sự cố.
    'reports'       => ['edit','view','edit','edit','view','view'],
    'scores'        => ['edit','view','edit','edit','edit','view'],
    'promotion'     => ['edit','edit','view','none','none','none'],
    'programs'      => ['edit','edit','none','none','none','none'],
    'announcements' => ['edit','edit','edit','edit','view','view'],
    // Lịch cá nhân: ai cũng tự quản việc của mình
    'notes'         => ['edit','edit','edit','edit','edit','edit'],
    // Hướng dẫn sử dụng: mọi vai đều xem
    'guide'         => ['view','view','view','view','view','view'],
    // Sổ Mộc: Danh mục quà do BĐH/Admin quản; Đổi quà chỉ Admin (và Thủ Thư,
    // seed riêng bên dưới vì thu_thu nằm ngoài $roleOrder).
    'gifts'         => ['edit','edit','none','none','none','none'],
    'rewards'       => ['edit','none','none','none','none','none'],
    // Thư viện: view = xem + đăng (chờ duyệt); edit = duyệt/gỡ/quản chủ đề.
    // schema.sql cũng seed khối này nhưng chạy TRƯỚC khi có roles → khóa ngoại
    // hỏng và INSERT IGNORE nuốt lỗi, nên CSDL mới không có dòng nào.
    'thu_vien'      => ['edit','edit','view','view','view','view'],
];
$roleOrder = ['admin','bdh','truong_khoi','glv_chu_nhiem','glv','du_bi'];
foreach ($perms as $mod => $levels) {
    foreach ($roleOrder as $i => $role) {
        // Không INSERT IGNORE: nó nuốt cả lỗi khóa ngoại (xem thu_vien ở trên).
        // ON DUPLICATE KEY giữ nguyên mức Quản trị đã chỉnh khi chạy lại.
        db_run('INSERT INTO permissions (module_key, role_code, level) VALUES (?,?,?)
                ON DUPLICATE KEY UPDATE level = level',
               [$mod, $role, $levels[$i]]);
    }
}
// Thủ Thư (vai kiêm nhiệm, ngoài $roleOrder) — chỉ có quyền trên gifts/rewards.
foreach (['gifts', 'rewards'] as $mod) {
    db_run('INSERT INTO permissions (module_key, role_code, level) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE level = level', [$mod, 'thu_thu', 'edit']);
}
say('✓ Module: ' . count($modules) . ' · Phân quyền: ' . (count($perms) * count($roleOrder) + 2));

// --- Đầu điểm ---
$scoreTypes = [
    ['mieng',  'Miệng',   'M',  1, 1],
    ['p15',    '15 phút', '15', 1, 2],
    ['giuaky', 'Giữa kỳ', 'GK', 2, 3],
    ['cuoiky', 'Cuối kỳ', 'CK', 3, 4],
];
foreach ($scoreTypes as $s) {
    db_run('INSERT IGNORE INTO score_types (code, label, short_label, weight, sort_order) VALUES (?,?,?,?,?)', $s);
}
say('✓ Đầu điểm: ' . count($scoreTypes));

// --- Niên khoá & học kỳ ---
$year = db_one('SELECT id FROM school_years WHERE name = ?', ['2026 - 2027']);
if (!$year) {
    $yearId = db_insert('INSERT INTO school_years (name, start_date, end_date, is_current, status)
                         VALUES (?,?,?,1,?)', ['2026 - 2027', '2026-08-01', '2027-05-31', 'đang mở']);
    db_run('INSERT INTO terms (year_id, name, start_date, end_date, sort_order) VALUES
            (?,?,?,?,1), (?,?,?,?,2)',
           [$yearId, 'Học kỳ I',  '2026-08-01', '2026-12-31',
            $yearId, 'Học kỳ II', '2027-01-01', '2027-05-31']);
    say('✓ Niên khoá 2026 - 2027 và 2 học kỳ');
} else {
    $yearId = (int) $year['id'];
    say('· Niên khoá 2026 - 2027 đã có, bỏ qua');
}

// --- Khối & lớp ---
$blocks = ['Khai Tâm' => 1, 'Rước Lễ' => 2, 'Thêm Sức' => 3, 'Bao Đồng' => 4];
foreach ($blocks as $name => $order) {
    db_run('INSERT IGNORE INTO blocks (name, sort_order) VALUES (?,?)', [$name, $order]);
}
$blockId = [];
foreach (db_all('SELECT id, name FROM blocks') as $b) $blockId[$b['name']] = (int) $b['id'];

$classes = [
    ['Khai Tâm 1A', 'Khai Tâm', 1], ['Khai Tâm 1B', 'Khai Tâm', 2], ['Khai Tâm 2A', 'Khai Tâm', 3],
    ['Rước Lễ 1A',  'Rước Lễ',  1], ['Rước Lễ 1B',  'Rước Lễ',  2],
    ['Thêm Sức 1',  'Thêm Sức', 1], ['Thêm Sức 2',  'Thêm Sức', 2],
    ['Bao Đồng 1',  'Bao Đồng', 1],
];
foreach ($classes as $c) {
    db_run('INSERT IGNORE INTO classes (name, block_id, sort_order) VALUES (?,?,?)',
           [$c[0], $blockId[$c[1]], $c[2]]);
}
$classId = [];
foreach (db_all('SELECT id, name FROM classes') as $c) $classId[$c['name']] = (int) $c['id'];

// Sơ đồ lên lớp
$path = [
    'Khai Tâm 1A' => 'Khai Tâm 2A', 'Khai Tâm 1B' => 'Khai Tâm 2A',
    'Khai Tâm 2A' => 'Rước Lễ 1A',  'Rước Lễ 1A'  => 'Rước Lễ 1B',
    'Rước Lễ 1B'  => 'Thêm Sức 1',  'Thêm Sức 1'  => 'Thêm Sức 2',
    'Thêm Sức 2'  => 'Bao Đồng 1',
];
foreach ($path as $from => $to) {
    db_run('UPDATE classes SET next_class_id = ? WHERE name = ?', [$classId[$to], $from]);
}
db_run("UPDATE classes SET is_final = 1 WHERE name = 'Bao Đồng 1'");
say('✓ Khối: ' . count($blocks) . ' · Lớp: ' . count($classes) . ' · Sơ đồ lên lớp đã khai');

// --- Tài khoản quản trị đầu tiên ---
//
// Hỏi "đã có Quản Trị nào chưa", KHÔNG hỏi "đã có số 0901000001 chưa".
// Người dùng đổi số điện thoại của tài khoản quản trị là chuyện bình
// thường; hỏi theo số thì lần chạy sau tưởng chưa có, cố tạo mới, rồi
// vỡ vì mã GLV001 đã tồn tại (UNIQUE) — trình cài đặt mất tính chạy
// lại được, mà lại vỡ ở giữa chừng nên dựng dở dang.
$vuaTaoAdmin = false;
$admin = db_one("SELECT id FROM members WHERE role_code = 'admin' LIMIT 1")
      ?: db_one('SELECT id FROM members WHERE code = ?', ['GLV001']);
if (!$admin) {
    $titleAdmin = db_one("SELECT id FROM titles WHERE role_code='admin' LIMIT 1");
    $adminId = db_insert('INSERT INTO members (code, holy_name, full_name, phone, password_hash,
                                    role_code, title_id, status, must_change_pw)
               VALUES (?,?,?,?,?,?,?,?,1)',
        ['GLV001', 'Phêrô', 'Nguyễn Văn A', '0901000001',
         password_hash(app_config('default_password'), PASSWORD_DEFAULT),
         'admin', $titleAdmin['id'], 'đang phục vụ']);
    $vuaTaoAdmin = true;
    say('✓ Tài khoản quản trị: 0901000001 / ' . app_config('default_password'));
} else {
    say('· Tài khoản quản trị đã có, bỏ qua');
}

// --- Phân công CHÍNH của Quản Trị (member_assignments) ---
//
// member_assignments mới là nguồn thật của vai trò (effective_assignments()
// đọc từ đây); tạo members mà thiếu dòng này thì admin "không có phân công
// nào". Bù cho MỌI tài khoản admin chưa có dòng admin đang hiệu lực (kể cả
// admin tạo từ bản cài cũ). Idempotent: đã có thì không tạo thêm.
// Chỉ đặt is_primary=1 khi người đó chưa có phân công chính nào khác đang
// hiệu lực, để không bao giờ có hai dòng chính.
$nAssign = 0;
foreach (db_all("SELECT id FROM members WHERE role_code = 'admin'") as $ad) {
    $mid = (int) $ad['id'];
    $coDong = db_one("SELECT id FROM member_assignments
                       WHERE member_id = ? AND role_code = 'admin' AND to_date IS NULL LIMIT 1", [$mid]);
    if ($coDong) continue;
    $coChinh = db_one('SELECT id FROM member_assignments
                        WHERE member_id = ? AND is_primary = 1 AND to_date IS NULL LIMIT 1', [$mid]);
    db_run("INSERT INTO member_assignments
                (member_id, role_code, is_primary, from_date, assigned_by, note)
            VALUES (?, 'admin', ?, CURDATE(), ?, 'Tạo bởi trình cài đặt')",
        [$mid, $coChinh ? 0 : 1, $mid]);
    $nAssign++;
}
say($nAssign > 0 ? "✓ Phân công chính cho Quản Trị: $nAssign"
                 : '· Phân công của Quản Trị đã đủ, bỏ qua');

// --- Chương trình mặc định: 3 buổi Chúa Nhật ---
$hasProg = db_one('SELECT id FROM programs WHERE year_id = ? LIMIT 1', [$yearId]);
if (!$hasProg) {
    $progs = [
        ['Thánh Lễ Thiếu Nhi', '07:00:00'],
        ['Học Giáo Lý Sáng',   '09:00:00'],
        ['Học Giáo Lý Chiều',  '15:00:00'],
    ];
    foreach ($progs as $p) {
        db_run('INSERT INTO programs (year_id, name, type, status, count_for_attendance, start_time, day_of_week)
                VALUES (?,?,?,?,1,?,0)', [$yearId, $p[0], 'bắt buộc', 'kích hoạt', $p[1]]);
    }
    say('✓ Chương trình mặc định: ' . count($progs) . ' buổi Chúa Nhật');
} else {
    say('· Chương trình đã có, bỏ qua');
}

db_run("INSERT IGNORE INTO settings (k, v) VALUES ('schema_version','1')");

say('');
say('HOÀN TẤT.');
if ($vuaTaoAdmin) {
    say('');
    say('Đăng nhập lần đầu bằng:');
    say('  Số điện thoại: 0901000001');
    say('  Mật khẩu:      ' . app_config('default_password'));
    say('');
    say('ĐỔI MẬT KHẨU NGAY sau khi vào — mật khẩu này nằm trong mã nguồn.');
} else {
    // Đừng in số điện thoại và mật khẩu mặc định khi tài khoản đã có:
    // chủ tài khoản thường đã đổi cả hai, in ra chỉ gây hiểu nhầm.
    say('Tài khoản quản trị đã có sẵn — đăng nhập bằng tài khoản đó.');
}
