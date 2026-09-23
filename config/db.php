<?php
/**
 * KẾT NỐI CƠ SỞ DỮ LIỆU
 *
 * Trả về một PDO duy nhất dùng chung cho cả request.
 * Bật chế độ ném ngoại lệ để lỗi SQL không âm thầm trôi qua.
 */

function app_config(?string $key = null)
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    return $key === null ? $config : ($config[$key] ?? null);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $c = app_config('db');
    // Cho phép TRỎ SANG DB KHÁC qua biến môi trường (tiện cho demo/staging/
    // seed dữ liệu) mà không phải sửa config.php. Chỉ ghi đè khi biến tồn tại.
    foreach (['host' => 'TNTT_DB_HOST', 'port' => 'TNTT_DB_PORT',
              'name' => 'TNTT_DB_NAME', 'user' => 'TNTT_DB_USER',
              'pass' => 'TNTT_DB_PASS'] as $k => $env) {
        $v = getenv($env);
        if ($v !== false) $c[$k] = $v;
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $c['host'], $c['port'], $c['name'], $c['charset']);

    try {
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Để MySQL tự ép kiểu, tránh mọi giá trị trả về đều thành chuỗi
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Ngày giờ theo múi giờ Việt Nam, không phụ thuộc cấu hình máy chủ
        $pdo->exec("SET time_zone = '+07:00'");
    } catch (PDOException $e) {
        if (app_config('production')) {
            http_response_code(500);
            exit('Không kết nối được cơ sở dữ liệu.');
        }
        throw $e;
    }

    return $pdo;
}

/**
 * CHƯA DỰNG BẢNG THÌ BÁO CHO NGƯỜI TA BIẾT
 *
 * Kết nối được nhưng chưa chạy trình cài đặt là tình huống rất hay gặp
 * lúc mới đưa lên máy chủ. Khi đó mọi truy vấn đều ném PDOException
 * "Base table or view not found", không ai bắt, và người dùng chỉ thấy
 * HTTP 500 trắng — không có manh mối nào để lần.
 *
 * Bắt riêng lỗi đó ra và nói thẳng phải làm gì.
 */
function db_bao_chua_cai_dat(Throwable $e): void
{
    $msg = $e->getMessage();
    $chuaCoBang = str_contains($msg, 'Base table or view not found')
               || str_contains($msg, 'doesn${q}t exist')
               || str_contains($msg, '42S02');
    if (!$chuaCoBang) return;

    if (PHP_SAPI === 'cli') {
        exit("Cơ sở dữ liệu chưa có bảng nào. Hãy chạy:  php config/install.php
");
    }
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Chưa cài đặt</title>'
       . '<div style="font-family:system-ui,sans-serif;max-width:34rem;margin:15vh auto;padding:0 1.5rem;line-height:1.6;color:#1e293b">'
       . '<h1 style="font-size:1.35rem;margin:0 0 .75rem">Chưa dựng cơ sở dữ liệu</h1>'
       . '<p style="color:#475569;margin:0 0 1rem">Kết nối được tới cơ sở dữ liệu, nhưng trong đó chưa có bảng nào. Cần chạy trình cài đặt một lần.</p>'
       . '<p style="color:#475569;margin:0 0 .35rem"><b>Có Terminal:</b></p>'
       . '<pre style="background:#f1f5f9;padding:.75rem 1rem;border-radius:.6rem;overflow-x:auto;margin:0 0 1rem">cd ~/tntt &amp;&amp; php config/install.php</pre>'
       . '<p style="color:#475569;margin:0 0 .35rem"><b>Không có Terminal:</b> mở</p>'
       . '<pre style="background:#f1f5f9;padding:.75rem 1rem;border-radius:.6rem;overflow-x:auto;margin:0 0 1rem">/cai-dat.php?key=SETUP_KEY</pre>'
       . '<p style="color:#64748b;font-size:.9rem;margin:0">SETUP_KEY là chuỗi bạn đặt trong <code>config/config.php</code>.</p>'
       . '</div>';
    exit;
}

/** Chạy truy vấn có tham số, trả về mọi dòng */
function db_all(string $sql, array $params = []): array
{
    try {
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (PDOException $e) {
        db_bao_chua_cai_dat($e);
        throw $e;
    }
}

/** Trả về dòng đầu tiên, hoặc null */
function db_one(string $sql, array $params = []): ?array
{
    try {
        $st = db()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    } catch (PDOException $e) {
        db_bao_chua_cai_dat($e);
        throw $e;
    }
}

/** Chạy lệnh ghi, trả về số dòng bị tác động */
function db_run(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

/** Chèn một dòng, trả về id vừa sinh */
function db_insert(string $sql, array $params = []): int
{
    db_run($sql, $params);
    return (int) db()->lastInsertId();
}

/**
 * Bảng/cột có tồn tại không — dùng cho các nhánh "chỉ chạy khi đã migrate".
 *
 * Vì sao có hàm này: vài endpoint NÓNG (điểm danh, nạp dữ liệu) hỏi schema
 * (SHOW TABLES / SHOW COLUMNS) trên MỖI request để biết tính năng đã cài
 * chưa. Bảng/cột một khi đã thêm thì không mất, nên ta CACHE kết quả DƯƠNG:
 *   - static: khỏi hỏi lại trong cùng một request (nhiều chỗ cùng hỏi).
 *   - APCu (bộ nhớ tiến trình, KHÔNG bị Cache::flush() của cache tệp xoá):
 *     khỏi hỏi lại giữa các request sau khi đã thấy "có".
 * CHỈ cache khi CÓ: nếu chưa có (chưa chạy migration) thì vẫn hỏi lại mỗi
 * lần, để bảng/cột vừa thêm được nhận ngay, không phải chờ cache hết hạn.
 * Tên bảng/cột lọc còn [A-Za-z0-9_] (chỉ dùng cho định danh nội bộ, chống
 * chèn SQL vì SHOW ... LIKE không nhận tham số ràng buộc).
 */
function db_has_table(string $name): bool
{
    static $memo = [];
    $name = preg_replace('/[^A-Za-z0-9_]/', '', $name);
    if (isset($memo[$name])) return $memo[$name];

    $apcuKey = 'tntt_has_tbl_' . $name;
    if (function_exists('apcu_fetch')) {
        $ok = false; $v = apcu_fetch($apcuKey, $ok);
        if ($ok && $v) return $memo[$name] = true;
    }

    $exists = db_one("SHOW TABLES LIKE '" . $name . "'") !== null;
    if ($exists && function_exists('apcu_store')) apcu_store($apcuKey, true, 86400);
    return $memo[$name] = $exists;
}

function db_has_column(string $table, string $col): bool
{
    static $memo = [];
    $table = preg_replace('/[^A-Za-z0-9_]/', '', $table);
    $col   = preg_replace('/[^A-Za-z0-9_]/', '', $col);
    $key   = $table . '.' . $col;
    if (isset($memo[$key])) return $memo[$key];

    $apcuKey = 'tntt_has_col_' . $key;
    if (function_exists('apcu_fetch')) {
        $ok = false; $v = apcu_fetch($apcuKey, $ok);
        if ($ok && $v) return $memo[$key] = true;
    }

    $exists = db_one("SHOW COLUMNS FROM `" . $table . "` LIKE '" . $col . "'") !== null;
    if ($exists && function_exists('apcu_store')) apcu_store($apcuKey, true, 86400);
    return $memo[$key] = $exists;
}
