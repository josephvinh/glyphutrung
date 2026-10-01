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

/* ==============================================================
   TOKEN MÁY (#107)

   Trước đây service worker nhận diện máy bằng chính endpoint — mà endpoint
   lại được trang gửi lên khi đăng ký và bị lộ qua query string của status,
   nên biết endpoint là đọc được thông báo của người khác. Nay server sinh
   một token ngẫu nhiên 256 bit khi đăng ký; chỉ lưu BĂM SHA-256 của nó.
   Endpoint chỉ còn dùng tạm cho dòng cũ chưa có token, tới hạn chuyển tiếp.
   ============================================================== */

/** Hết hạn chuyển tiếp: tới ngày này (gồm cả ngày) dòng cũ chưa có token vẫn nhận bằng endpoint.
 *  = ngày triển khai + 30 ngày. Ghi đè bằng app_config('push')['legacy_until'] (YYYY-MM-DD). */
const PUSH_ENDPOINT_CU_HET_HAN = '2026-10-30';

/** Còn trong thời hạn chuyển tiếp không? */
function push_con_nhan_endpoint_cu(): bool
{
    $cfg = app_config('push');
    $han = is_array($cfg) ? (string) ($cfg['legacy_until'] ?? '') : '';
    if (!preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $han)) $han = PUSH_ENDPOINT_CU_HET_HAN;
    return date('Y-m-d') <= $han;
}

/** Giá trị từ request → chuỗi; không phải chuỗi (mảng, số, null...) thì '' (không ép kiểu, không Warning) */
function push_chuoi($v): string
{
    return is_string($v) ? $v : '';
}

/** Token ngẫu nhiên 256 bit, base64url (43 ký tự) */
function push_sinh_token(): string
{
    return push_b64(random_bytes(32));
}

/** Giá trị lưu trong CSDL: hex SHA-256 của token */
function push_bam_token(string $token): string
{
    return hash('sha256', $token);
}

/** Đúng dạng token do push_sinh_token() sinh ra? */
function push_token_dung_dinh_dang(string $token): bool
{
    return (bool) preg_match('/\A[A-Za-z0-9_-]{43}\z/', $token);
}

/**
 * Ký JWT VAPID cho một audience (https://host, không kèm cổng).
 * Trả null nếu chưa có khoá hoặc không ký được.
 */
function push_tao_jwt(string $aud, array $cfg): ?string
{
    if (empty($cfg['public']) || empty($cfg['private'])) return null;

    $header  = push_b64(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $payload = push_b64(json_encode([
        'aud' => $aud,
        'exp' => time() + 12 * 3600,
        'sub' => $cfg['subject'] ?? 'mailto:admin@localhost',
    ]));

    $sig = '';
    if (!openssl_sign($header . '.' . $payload, $sig,
                      push_khoa_rieng($cfg['private'], $cfg['public']), OPENSSL_ALGO_SHA256)) {
        return null;
    }
    return $header . '.' . $payload . '.' . push_b64(push_der_sang_raw($sig));
}

/**
 * Phân giải host rồi chỉ giữ IP công khai (chống DNS rebinding / IP nội bộ, #99).
 * Host thuộc test_hosts (máy thử) thì không lọc — mock chạy ở 127.0.0.1.
 * Kết quả cache trong $cache suốt một lượt xả.
 *
 * @param array<string,?string> $cache
 */
function push_giai_ip(string $host, bool $laHostThu, array &$cache): ?string
{
    if (array_key_exists($host, $cache)) return $cache[$host];
    $ip  = null;
    $ips = @gethostbynamel($host) ?: [];
    foreach ($ips as $c) {
        if ($laHostThu || push_ip_cong_khai($c)) { $ip = $c; break; }
    }
    return $cache[$host] = $ip;
}

/**
 * Dựng handle curl gửi một cú chuông rỗng. Địa chỉ đã được kiểm và IP đã được
 * giải sẵn — curl KHÔNG được tự phân giải lại, không theo redirect, chỉ HTTPS.
 *
 * @param stdClass $bo nơi gom tối đa 1 KB phản hồi (thuộc tính d)
 * @return CurlHandle
 */
function push_dung_curl(string $endpoint, string $host, int $cong, string $ip,
                        string $jwt, string $pub, stdClass $bo, int $ttl = 86400)
{
    $ch  = curl_init($endpoint);
    $opt = [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => '',          // thông báo rỗng, không nội dung
        CURLOPT_HEADER         => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS      => 0,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROXY          => '',          // không đi proxy theo biến môi trường (ghim IP sẽ vô nghĩa)
        CURLOPT_RESOLVE        => ["$host:$cong:$ip"],
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_CONNECTTIMEOUT_MS => 3000,
        CURLOPT_TIMEOUT_MS     => 5000,
        CURLOPT_NOSIGNAL       => true,
        CURLOPT_WRITEFUNCTION  => function ($c, string $d) use ($bo): int {
            // Nhận tối đa 1 KB; nhiều hơn thì ngắt (mã HTTP vẫn đọc được)
            if (strlen($bo->d) + strlen($d) > 1024) return 0;
            $bo->d .= $d;
            return strlen($d);
        },
        CURLOPT_HTTPHEADER     => [
            'Authorization: vapid t=' . $jwt . ', k=' . $pub,
            'TTL: ' . $ttl,
            'Content-Length: 0',
            'Urgency: normal',
        ],
    ];
    if (defined('CURLOPT_PROTOCOLS_STR')) {
        $opt[CURLOPT_PROTOCOLS_STR]       = 'https';
        $opt[CURLOPT_REDIR_PROTOCOLS_STR] = 'https';
    } else {
        $opt[CURLOPT_PROTOCOLS]       = CURLPROTO_HTTPS;
        $opt[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
    }
    curl_setopt_array($ch, $opt);
    return $ch;
}

/**
 * Ghi kết quả một lần gửi vào dòng đăng ký.
 *
 * @param int $ma mã HTTP (0 = timeout / không kết nối được)
 * @return bool true nếu push service nhận (2xx)
 */
function push_ghi_ket_qua(int $id, int $seq, int $ma): bool
{
    if ($ma >= 200 && $ma < 300) {
        db_run('UPDATE push_subscriptions
                   SET ring_done = GREATEST(ring_done, ?), ring_tries = 0, ring_lock_until = NULL,
                       last_ok_at = NOW(), last_fail_code = NULL
                 WHERE id = ?', [$seq, $id]);
        return true;
    }
    if ($ma === 404 || $ma === 410) {
        // Máy đã gỡ app hoặc xoá đăng ký — dọn đi cho sạch
        db_run('DELETE FROM push_subscriptions WHERE id = ?', [$id]);
        return false;
    }
    if ($ma === 0 || $ma === 429 || $ma >= 500) {
        // Lỗi tạm: thử lại sau, giãn dần 30 s, 60 s, 120 s... tối đa 30 phút.
        // (MySQL tính các phép gán từ trái sang phải: ring_lock_until dùng ring_tries CŨ.)
        db_run('UPDATE push_subscriptions
                   SET ring_lock_until = DATE_ADD(NOW(), INTERVAL LEAST(30 * POW(2, ring_tries), 1800) SECOND),
                       last_fail_code = ?, ring_tries = ring_tries + 1
                 WHERE id = ?', [$ma, $id]);
        // Quá 5 lần thì bỏ cú chuông này (nội dung vẫn nằm trong outbox,
        // sẽ hiện ở cú chuông kế tiếp)
        db_run('UPDATE push_subscriptions
                   SET ring_done = GREATEST(ring_done, ?), ring_tries = 0, ring_lock_until = NULL
                 WHERE id = ? AND ring_tries >= 5', [$seq, $id]);
        return false;
    }
    // Còn lại (400, 401, 403, 413, 3xx, 4xx khác): bị từ chối vĩnh viễn — gửi lại
    // cũng vậy. Bỏ cú chuông này, giữ dòng. Push service thật không redirect nên
    // 3xx cũng xếp vào đây.
    db_run('UPDATE push_subscriptions
               SET ring_done = GREATEST(ring_done, ?), ring_tries = 0, ring_lock_until = NULL,
                   last_fail_code = ?
             WHERE id = ?', [$seq, $ma, $id]);
    return false;
}

/** Còn máy nào cần rung (và không bị khoá) không? */
function push_co_hang_doi(): bool
{
    return (bool) db_one('SELECT 1 FROM push_subscriptions
                           WHERE ring_seq > ring_done
                             AND (ring_lock_until IS NULL OR ring_lock_until < NOW()) LIMIT 1');
}

/**
 * Xả hàng đợi: rung mọi máy có ring_seq > ring_done, các máy gửi SONG SONG.
 * Dùng chung cho request web (sau khi đã trả phản hồi), cron và CLI.
 *
 * @param int   $toiDa        số máy tối đa một lượt
 * @param float $nganSachGiay tổng thời gian tối đa; máy chưa xong coi như timeout
 * @return array{rung:int, loi:int}
 */
function push_xa_hang(int $toiDa, float $nganSachGiay): array
{
    $rung = 0;
    $loi  = 0;
    try {
        $cfg = app_config('push');
        if (empty($cfg['public']) || empty($cfg['private'])) return ['rung' => 0, 'loi' => 0];

        $batDau  = hrtime(true);
        $hostThu = push_host_thu();
        if ($hostThu) {
            error_log('push: ĐANG BẬT test_hosts (' . implode(',', $hostThu) . ') — chỉ dùng trên máy thử, KHÔNG bật trên máy chủ thật');
        }

        $ung = db_all('SELECT id FROM push_subscriptions
                        WHERE ring_seq > ring_done
                          AND (ring_lock_until IS NULL OR ring_lock_until < NOW())
                        ORDER BY id LIMIT ' . max(1, $toiDa));

        $mh     = curl_multi_init();
        $muc    = [];      // spl_object_id(handle) => [id, seq, handle]
        $ipCache  = [];
        $jwtCache = [];
        foreach ($ung as $u) {
            $id = (int) $u['id'];
            // Giành dòng này: chỉ một tiến trình thắng
            $gianh = db_run('UPDATE push_subscriptions SET ring_lock_until = DATE_ADD(NOW(), INTERVAL 60 SECOND)
                              WHERE id = ? AND ring_seq > ring_done
                                AND (ring_lock_until IS NULL OR ring_lock_until < NOW())', [$id]);
            if ($gianh !== 1) continue;

            $row = db_one('SELECT endpoint, ring_seq FROM push_subscriptions WHERE id = ?', [$id]);
            if (!$row) continue;
            $seq = (int) $row['ring_seq'];

            // Kiểm lại lúc gửi: dòng cũ trước bản vá có thể chứa endpoint độc hại
            $kt = push_endpoint_hop_le($row['endpoint']);
            if (!$kt['ok']) {
                db_run('DELETE FROM push_subscriptions WHERE id = ?', [$id]);
                continue;
            }

            $laHostThu = in_array($kt['host'] . ':' . $kt['port'], $hostThu, true);
            $ip = push_giai_ip($kt['host'], $laHostThu, $ipCache);
            if ($ip === null) {            // không có IP công khai nào: coi như lỗi tạm, KHÔNG kết nối
                push_ghi_ket_qua($id, $seq, 0);
                $loi++;
                continue;
            }

            $aud = 'https://' . $kt['host'];
            if (!array_key_exists($aud, $jwtCache)) $jwtCache[$aud] = push_tao_jwt($aud, $cfg);
            if ($jwtCache[$aud] === null) { push_ghi_ket_qua($id, $seq, 0); $loi++; continue; }

            $bo = new stdClass;
            $bo->d = '';
            $ch = push_dung_curl($row['endpoint'], $kt['host'], $kt['port'], $ip,
                                 $jwtCache[$aud], $cfg['public'], $bo);
            curl_multi_add_handle($mh, $ch);
            $muc[spl_object_id($ch)] = [$id, $seq, $ch];
        }

        // Chạy song song tới khi xong hết hoặc hết ngân sách
        $xong = [];
        if ($muc) {
            $dangChay = 0;
            do {
                $st = curl_multi_exec($mh, $dangChay);
                while ($info = curl_multi_info_read($mh)) $xong[spl_object_id($info['handle'])] = true;
                if ($dangChay > 0) {
                    if ((hrtime(true) - $batDau) / 1e9 > $nganSachGiay) break;
                    if (curl_multi_select($mh, 0.2) === -1) usleep(20000);
                }
            } while ($dangChay > 0 && $st === CURLM_OK);
        }

        foreach ($muc as $oid => [$id, $seq, $ch]) {
            $ma = isset($xong[$oid]) ? (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE) : 0;
            curl_multi_remove_handle($mh, $ch);
            unset($ch);
            if (push_ghi_ket_qua($id, $seq, $ma)) $rung++; else $loi++;
        }
        curl_multi_close($mh);
    } catch (Throwable $e) {
        error_log('push_xa_hang: ' . $e->getMessage());
    }
    return ['rung' => $rung, 'loi' => $loi];
}

/**
 * Đóng phản hồi để người dùng nhận xong ngay, trong khi PHP còn chạy tiếp.
 * Chạy được trên PHP-FPM, LiteSpeed (LSAPI), và fallback Content-Length +
 * Connection: close cho `php -S`, mod_php, CGI.
 *
 * @return bool true nếu đã tách được client
 */
function push_dong_phan_hoi(): bool
{
    // Bắt buộc: không giữ khoá phiên trong lúc gửi
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

    if (function_exists('fastcgi_finish_request')) {
        while (ob_get_level() > 0) @ob_end_flush();
        fastcgi_finish_request();
        return true;
    }
    if (function_exists('litespeed_finish_request')) {
        while (ob_get_level() > 0) @ob_end_flush();
        litespeed_finish_request();
        return true;
    }
    if (headers_sent()) return false;

    // Tự đóng bằng Content-Length. Lấy nội dung CHƯA nén từ mọi tầng buffer
    // (tầng trong ra trước), bỏ header nén đã hứa ở _bootstrap.php.
    $phan = [];
    while (ob_get_level() > 0) {
        $phan[] = (string) ob_get_contents();
        @ob_end_clean();
    }
    $body = implode('', array_reverse($phan));
    header_remove('Content-Encoding');
    if (function_exists('apache_setenv')) @apache_setenv('no-gzip', '1');
    header('Content-Length: ' . strlen($body));
    header('Connection: close');
    echo $body;
    @flush();
    return true;
}

/** Hàm shutdown: trả phản hồi trước, rồi mới gửi thông báo đẩy */
function push_sau_phan_hoi(): void
{
    try {
        if (db()->inTransaction()) return;        // giao dịch còn dở (chưa commit): không gửi
        if (!push_co_hang_doi()) return;          // không có gì để gửi thì đừng đụng tới phản hồi
        $daTach = push_dong_phan_hoi();
        ignore_user_abort(true);
        @set_time_limit(30);
        // Chưa tách được client (fallback cuối) thì chỉ gửi nhanh, phần còn lại để lượt sau
        if ($daTach) push_xa_hang(50, 8.0); else push_xa_hang(50, 1.5);
    } catch (Throwable $e) {
        error_log('push_sau_phan_hoi: ' . $e->getMessage());
    }
}

/** Hẹn xả hàng đợi sau khi đã trả phản hồi (chỉ đăng ký một lần mỗi request) */
function push_hen_sau_phan_hoi(): void
{
    static $daHen = false;
    if ($daHen) return;
    $daHen = true;
    register_shutdown_function('push_sau_phan_hoi');
}

/* ==============================================================
   PHẦN CẤP ỨNG DỤNG — chọn người nhận rồi rung chuông
   ============================================================== */

/**
 * Gửi một thông báo tới danh sách thành viên.
 *
 * Ghi nội dung vào push_outbox (mỗi người một dòng), đánh dấu mọi máy của họ
 * "cần rung" (ring_seq + 1) rồi để push_xa_hang() rung sau khi đã trả phản
 * hồi cho người dùng — không giữ request chờ push service. Máy nào đã gỡ app
 * thì xoá đăng ký lúc gửi.
 *
 * KHÔNG BAO GIỜ NÉM LỖI. Thông báo hỏng không được phép làm hỏng
 * việc chính — lưu thông báo, duyệt đơn... vẫn phải xong.
 *
 * @param int[] $memberIds
 * @return int số máy đã xếp hàng chờ rung (không phải số máy đã rung xong)
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

        // Cùng giao dịch với outbox: nếu bên ngoài rollback thì cả hai cùng rollback
        $chan = implode(',', array_fill(0, count($ids), '?'));
        $n = db_run("UPDATE push_subscriptions SET ring_seq = ring_seq + 1 WHERE member_id IN ($chan)", $ids);

        // Đang trong giao dịch chưa commit thì CHƯA gửi (có thể bị rollback): để
        // lượt xả sau (cron, status, request có push_bao khác) lo.
        if (PHP_SAPI === 'cli') { if (!db()->inTransaction()) push_xa_hang(200, 20.0); }  // cron/CLI không có người chờ
        else                    push_hen_sau_phan_hoi();
        return $n;
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
