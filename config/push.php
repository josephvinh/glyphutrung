<?php
/**
 * GỬI THÔNG BÁO ĐẨY (Web Push) — PHP THUẦN
 *
 * Không dùng Composer hay thư viện ngoài. Cả tệp này chỉ cần openssl,
 * vốn có sẵn trên mọi hosting chạy PHP 8.
 *
 * CÁCH LÀM: gửi thông báo RỖNG, không kèm nội dung.
 *
 *   Web Push chuẩn cho phép kèm nội dung, nhưng phải mã hoá theo
 *   aes128gcm với trao khoá ECDH — viết bằng PHP thuần thì dài và dễ
 *   sai. Ở đây gửi một cú "chuông" rỗng, rồi service worker trên máy
 *   nhận được sẽ tự gọi API lấy nội dung mới nhất và hiện lên.
 *
 *   Người dùng không thấy khác biệt. Đổi lại, mã ngắn và ít chỗ hỏng.
 *   Một hệ quả tốt: nội dung thông báo KHÔNG đi qua máy chủ đẩy của
 *   Google/Apple — họ chỉ thấy một tín hiệu rỗng.
 */

/** base64 kiểu URL, bỏ dấu = ở đuôi — chuẩn mà Web Push đòi */
function push_b64(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function push_b64_decode(string $s): string
{
    return base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
}

/**
 * Chữ ký ES256 mà JWT đòi là 64 byte thô (r ‖ s).
 * openssl_sign trả về DER, phải bóc ra.
 */
function push_der_sang_raw(string $der): string
{
    $off = 0;
    if (ord($der[$off++]) !== 0x30) throw new RuntimeException('Chữ ký DER hỏng');
    if (ord($der[$off]) > 0x80) $off += (ord($der[$off]) & 0x0f) + 1; else $off++;

    $lay = function () use ($der, &$off): string {
        if (ord($der[$off++]) !== 0x02) throw new RuntimeException('Chữ ký DER hỏng');
        $len = ord($der[$off++]);
        $v   = substr($der, $off, $len);
        $off += $len;
        $v = ltrim($v, "\x00");                       // bỏ byte đệm dấu
        return str_pad($v, 32, "\x00", STR_PAD_LEFT); // mỗi nửa đúng 32 byte
    };
    return $lay() . $lay();
}

/**
 * Sinh cặp khoá VAPID. Chạy MỘT LẦN, rồi chép kết quả vào config.php.
 * Đổi khoá về sau sẽ làm mọi máy đã đăng ký ngừng nhận thông báo.
 */
function push_tao_khoa(): array
{
    $k = openssl_pkey_new([
        'curve_name'       => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    if (!$k) throw new RuntimeException('Không tạo được khoá EC: ' . openssl_error_string());

    $d = openssl_pkey_get_details($k);
    // Khoá công khai ở dạng chưa nén: 0x04 ‖ X(32) ‖ Y(32)
    $pub = "\x04" . str_pad($d['ec']['x'], 32, "\x00", STR_PAD_LEFT)
                  . str_pad($d['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    $priv = str_pad($d['ec']['d'], 32, "\x00", STR_PAD_LEFT);

    return ['public' => push_b64($pub), 'private' => push_b64($priv)];
}

/** Dựng lại khoá riêng EC từ chuỗi 32 byte đã lưu trong config */
function push_khoa_rieng(string $privB64, string $pubB64)
{
    $d   = push_b64_decode($privB64);
    $pub = push_b64_decode($pubB64);

    // Gói thành DER SEC1 rồi cho openssl đọc — cách duy nhất nạp được
    // khoá EC thô mà không cần thư viện ngoài.
    $der = "\x30\x77\x02\x01\x01\x04\x20" . $d
         . "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"
         . "\xa1\x44\x03\x42\x00" . $pub;

    $pem = "-----BEGIN EC PRIVATE KEY-----\n"
         . chunk_split(base64_encode($der), 64, "\n")
         . "-----END EC PRIVATE KEY-----\n";

    $k = openssl_pkey_get_private($pem);
    if (!$k) throw new RuntimeException('Khoá VAPID không đọc được: ' . openssl_error_string());
    return $k;
}

/* ==============================================================
   KIỂM ĐỊA CHỈ ĐĂNG KÝ (endpoint) — CHỐNG SSRF (#99)

   Endpoint do trình duyệt của người dùng gửi lên, nên phải coi là dữ
   liệu không tin cậy: nếu để máy chủ POST tới bất cứ địa chỉ nào thì
   ai có tài khoản cũng biến máy chủ thành "máy quét" mạng nội bộ, và
   còn gửi kèm chữ ký VAPID tới máy của kẻ tấn công.

   Vì vậy CHỈ nhận endpoint của bốn dịch vụ push thật. Danh sách viết
   cứng ở đây — không cho cấu hình từ CSDL hay request.
   ============================================================== */

/** Host khớp CHÍNH XÁC (chữ thường) */
const PUSH_HOST_CHINH_XAC = [
    'fcm.googleapis.com',                // Chrome, Edge Android, Samsung, Opera, Brave...
    'updates.push.services.mozilla.com', // Firefox
];

/** Host khớp theo HẬU TỐ — có dấu chấm đầu để "evilpush.apple.com" không lọt */
const PUSH_HOST_HAU_TO = [
    '.push.apple.com',       // Safari macOS 13+, iOS/iPadOS 16.4+
    '.notify.windows.com',   // Edge trên Windows (WNS)
];

/**
 * Danh sách "host:cổng" chỉ dùng khi thử trên máy dev (mock ở localhost).
 *
 * CHỈ đọc từ config.local.php và CHỈ khi 'production' => false. Không đọc
 * biến môi trường, request hay CSDL. Mặc định (không khai) là rỗng.
 *
 * @return string[]
 */
function push_host_thu(): array
{
    if (app_config('production') !== false) return [];
    $cfg = app_config('push');
    $ds  = is_array($cfg) ? ($cfg['test_hosts'] ?? []) : [];
    if (!is_array($ds)) return [];
    $ra = [];
    foreach ($ds as $h) {
        if (is_string($h) && preg_match('/\A[a-z0-9.-]+:\d{1,5}\z/', $h)) $ra[] = $h;
    }
    return $ra;
}

/** Host có nằm trong danh sách dịch vụ push được phép không (chưa tính test_hosts) */
function push_host_duoc_phep(string $host): bool
{
    if (in_array($host, PUSH_HOST_CHINH_XAC, true)) return true;
    foreach (PUSH_HOST_HAU_TO as $ht) {
        if (strlen($host) > strlen($ht) && str_ends_with($host, $ht)) return true;
    }
    return false;
}

/**
 * Kiểm endpoint — hàm thuần, không I/O.
 *
 * @param string[] $hostThu danh sách "host:cổng" được thêm vào (chỉ môi trường thử)
 * @return array{ok:bool, host?:string, port?:int, loi?:string}
 */
function push_kiem_endpoint(string $ep, array $hostThu = []): array
{
    if (strlen($ep) > 500) return ['ok' => false, 'loi' => 'dai'];

    // Chặt cả chuỗi: https chữ thường, host chữ thường, không userinfo (@),
    // không \, không #, không khoảng trắng/CR/LF, không [IPv6], bắt buộc có path.
    $re = "#\\Ahttps://([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*)"
        . "(?::(\\d{1,5}))?(/[A-Za-z0-9._~%!\$&'()*+,;=:@/?-]*)\\z#";
    if (!preg_match($re, $ep, $m)) return ['ok' => false, 'loi' => 'regex'];

    $host = $m[1];
    $cong = ($m[2] ?? '') === '' ? 443 : (int) $m[2];
    if (($m[2] ?? '') !== '' && $m[2] !== (string) $cong) return ['ok' => false, 'loi' => 'cong'];  // 0443...
    if ($cong < 1 || $cong > 65535) return ['ok' => false, 'loi' => 'cong'];

    $laHostThu = in_array($host . ':' . $cong, $hostThu, true);
    if (!$laHostThu && $cong !== 443) return ['ok' => false, 'loi' => 'cong'];
    if (!$laHostThu && !push_host_duoc_phep($host)) return ['ok' => false, 'loi' => 'host', 'host' => $host];

    // Chống lệch bộ phân tích: parse_url phải thấy đúng host này
    $u = parse_url($ep);
    if (!is_array($u) || ($u['host'] ?? null) !== $host
        || isset($u['user']) || isset($u['pass']) || isset($u['fragment'])) {
        return ['ok' => false, 'loi' => 'parse'];
    }

    return ['ok' => true, 'host' => $host, 'port' => $cong];
}

/** Kiểm endpoint theo cấu hình của máy này (kể cả test_hosts nếu đang ở máy thử) */
function push_endpoint_hop_le(string $ep): array
{
    $r = push_kiem_endpoint($ep, push_host_thu());
    if (!$r['ok'] && ($r['loi'] ?? '') === 'host') {
        // Chỉ ghi host, không ghi cả URL. Quản trị đọc log này để biết có
        // trình duyệt nào dùng máy chủ push mới mà danh sách chưa có.
        error_log('push: host không được phép: ' . $r['host']);
    }
    return $r;
}

/**
 * IP này có phải địa chỉ công khai trên Internet không?
 * Loại: loopback, riêng tư (10/8, 172.16/12, 192.168/16), link-local
 * (169.254/16, gồm cả metadata đám mây), CGNAT 100.64/10, 0.0.0.0, IPv6 nội bộ
 * và dạng ::ffff:127.0.0.1.
 */
function push_ip_cong_khai(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) return false;
    if (defined('FILTER_FLAG_GLOBAL_RANGE')) {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) !== false;
    }
    // PHP < 8.2: tự loại thêm những dải mà cờ cũ không bao
    if (filter_var($ip, FILTER_VALIDATE_IP,
                   FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) return false;
    if (str_contains($ip, ':')) {
        return !preg_match('/\A(::ffff:|::1\z|fc|fd|fe[89ab])/i', $ip);
    }
    $p = array_map('intval', explode('.', $ip));
    return !($p[0] === 100 && $p[1] >= 64 && $p[1] <= 127);
}

/**
 * Gửi một cú chuông tới một máy đã đăng ký.
 *
 * @return array [thành công?, mã HTTP, ghi chú]
 *   Mã 404 hoặc 410 nghĩa là máy đó đã gỡ app hoặc xoá đăng ký —
 *   người gọi nên xoá bản ghi đó khỏi cơ sở dữ liệu.
 */
function push_gui(string $endpoint, int $ttl = 86400): array
{
    $cfg = app_config('push');
    if (empty($cfg['public']) || empty($cfg['private'])) {
        return [false, 0, 'Chưa khai khoá VAPID trong config.php'];
    }

    $u = parse_url($endpoint);
    if (empty($u['scheme']) || empty($u['host'])) return [false, 0, 'Địa chỉ đăng ký không hợp lệ'];
    $aud = $u['scheme'] . '://' . $u['host'];

    $header  = push_b64(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $payload = push_b64(json_encode([
        'aud' => $aud,
        'exp' => time() + 12 * 3600,
        'sub' => $cfg['subject'] ?? 'mailto:admin@localhost',
    ]));

    $sig = '';
    if (!openssl_sign($header . '.' . $payload, $sig,
                      push_khoa_rieng($cfg['private'], $cfg['public']), OPENSSL_ALGO_SHA256)) {
        return [false, 0, 'Không ký được VAPID'];
    }
    $jwt = $header . '.' . $payload . '.' . push_b64(push_der_sang_raw($sig));

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => '',          // thông báo rỗng, không nội dung
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Authorization: vapid t=' . $jwt . ', k=' . $cfg['public'],
            'TTL: ' . $ttl,
            'Content-Length: 0',
            'Urgency: normal',
        ],
    ]);
    $body = curl_exec($ch);
    $ma   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $loi  = curl_error($ch);
    curl_close($ch);

    if ($ma >= 200 && $ma < 300) return [true, $ma, ''];
    return [false, $ma, $loi ?: substr((string) $body, 0, 200)];
}

/* ==============================================================
   PHẦN CẤP ỨNG DỤNG — chọn người nhận rồi rung chuông
   ============================================================== */

/**
 * Gửi một thông báo tới danh sách thành viên.
 *
 * Ghi nội dung vào push_outbox (mỗi người một dòng), rồi rung chuông
 * mọi máy họ đã đăng ký. Máy nào đã gỡ app thì xoá đăng ký luôn.
 *
 * KHÔNG BAO GIỜ NÉM LỖI. Thông báo hỏng không được phép làm hỏng
 * việc chính — lưu thông báo, duyệt đơn... vẫn phải xong.
 *
 * @param int[] $memberIds
 * @return int số máy rung được
 */
function push_bao(array $memberIds, string $title, string $body,
                  string $url = '/', string $tag = 'tntt-chung'): int
{
    try {
        $ids = array_values(array_unique(array_filter(array_map('intval', $memberIds))));
        if (!$ids) return 0;

        $cfg = app_config('push');
        if (empty($cfg['private'])) return 0;   // chưa cấu hình thì thôi

        $title = mb_substr(trim($title), 0, 120);
        $body  = mb_substr(trim($body), 0, 255);
        $now   = date('Y-m-d H:i:s');

        foreach ($ids as $mid) {
            db_run('INSERT INTO push_outbox (member_id, title, body, url, tag, created_at)
                    VALUES (?,?,?,?,?,?)', [$mid, $title, $body, $url, $tag, $now]);
        }

        $chan = implode(',', array_fill(0, count($ids), '?'));
        $dsMay = db_all("SELECT id, endpoint FROM push_subscriptions WHERE member_id IN ($chan)", $ids);

        $rung = 0;
        foreach ($dsMay as $may) {
            // Dòng cũ có endpoint ngoài danh sách cho phép (đăng ký trước bản vá) → xoá
            if (!push_endpoint_hop_le($may['endpoint'])['ok']) {
                db_run('DELETE FROM push_subscriptions WHERE id=?', [$may['id']]);
                continue;
            }
            [$ok, $ma] = push_gui($may['endpoint']);
            if ($ok) {
                $rung++;
                db_run('UPDATE push_subscriptions SET last_ok_at=? WHERE id=?', [$now, $may['id']]);
            } elseif ($ma === 404 || $ma === 410) {
                // Máy đã gỡ app hoặc xoá đăng ký — dọn đi cho sạch
                db_run('DELETE FROM push_subscriptions WHERE id=?', [$may['id']]);
            }
        }
        return $rung;
    } catch (Throwable $e) {
        error_log('push_bao: ' . $e->getMessage());
        return 0;
    }
}

/** Danh sách id các thành viên đang phục vụ, lọc theo khối hoặc lớp */
function push_nguoi_nhan(string $phamVi = 'toàn đoàn', ?int $blockId = null, ?int $classId = null): array
{
    $sql = "SELECT id FROM members WHERE status = 'đang phục vụ'";
    $ts  = [];
    if ($phamVi === 'khối' && $blockId) {
        // Có người được xếp lớp mà chưa điền khối. Bắt cả hai đường,
        // kẻo thông báo của khối không tới được chính người trong khối.
        $sql .= ' AND (block_id = ? OR class_id IN (SELECT id FROM classes WHERE block_id = ?))';
        $ts[] = $blockId; $ts[] = $blockId;
    } elseif ($phamVi === 'lớp' && $classId) {
        // Trưởng khối cũng cần biết chuyện của lớp trong khối mình
        $sql .= ' AND (class_id = ? OR block_id = (SELECT block_id FROM classes WHERE id = ?))';
        $ts[] = $classId; $ts[] = $classId;
    }
    return array_map('intval', array_column(db_all($sql, $ts), 'id'));
}

/** Ban Điều Hành và Quản trị — những người phải duyệt đơn, duyệt hồ sơ */
function push_nguoi_duyet(): array
{
    return array_map('intval', array_column(
        db_all("SELECT id FROM members WHERE status = 'đang phục vụ' AND role_code IN ('admin','bdh')"),
        'id'));
}
