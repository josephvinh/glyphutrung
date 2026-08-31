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
