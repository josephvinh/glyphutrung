# P5 · Web Push — Đặc tả sửa #99 (SSRF), #100 (gửi đồng bộ), #107 (chiếm subscription)

Người thiết kế: Opus 5.5 · Ngày 30/09/2026 · Người cài đặt: Sonnet 5.5 · Người duyệt: Opus 5.5 (phiên khác)
Phạm vi: CHỈ các file liệt kê ở mục 5. Không thêm Composer, không thêm dịch vụ ngoài, không bắt buộc cron/APCu.

Mã hiện tại đã đọc: `config/push.php`, `public/api/push.php`, `public/sw.js`, `public/assets/js/modules/push.js`,
`config/schema.sql` (push_subscriptions, push_outbox), `config/install.php` (`$migrations`), `public/api/_bootstrap.php`
(ob_gzhandler, brotli, session), `public/api/_http_util.php` (`json_out` gọi `exit`), các nơi gọi `push_bao()`
(`leave.php` ×2, `announcements.php`, `auth.php` register — công khai, `push.php` test, `config/nhac_diem_danh.php`, `config/nhac_lich.php`),
`tests/e2e/push.py` (nhánh audit).

---

## 0. Tóm tắt quyết định

| Issue | Chọn | Loại |
|---|---|---|
| #99 SSRF | Allowlist host push service + regex URL chặt (không `@`, `\`, `#`, IP, cổng ≠ 443) + kiểm cả khi `subscribe` VÀ khi gửi + resolve DNS rồi kiểm IP công khai + ghim IP bằng `CURLOPT_RESOLVE` + curl chỉ HTTPS, không redirect, không proxy, giới hạn phản hồi 1 KB. Dòng cũ không hợp lệ: xoá lười lúc gửi. | Chỉ chặn IP nội bộ (không allowlist); kiểm `CURLINFO_PRIMARY_IP` sau khi kết nối; migration xoá dữ liệu |
| #100 gửi đồng bộ | Hàng đợi bền trong DB (cột `ring_seq/ring_done` trên `push_subscriptions`) + gửi SAU khi đã trả phản hồi (`fastcgi_finish_request` / `litespeed_finish_request` / fallback `Content-Length`+`Connection: close`+`flush`) + `curl_multi` song song, timeout ngắn + "xả hàng" cơ hội ở `status` và ở cron nếu có | Chỉ `curl_multi` trong request; cron bắt buộc; tiến trình nền `exec()` |
| #107 | Token ngẫu nhiên 256 bit do server sinh, lưu **băm SHA-256**; `pending` nhận diện bằng token; `subscribe` KHÔNG BAO GIỜ chuyển quyền sở hữu endpoint của người khác (trả 409, client tự huỷ + đăng ký lại để lấy endpoint mới); endpoint cũ chỉ còn dùng cho dòng chưa có token, tới hạn chuyển tiếp | Chuyển quyền có xác thực bằng token cũ; token có hạn dùng; IndexedDB |

---

## 1. #99 — SSRF mù qua endpoint

### 1.1 Danh sách host được phép (chốt)

Rà soát theo tài liệu công khai của các trình duyệt (tới 2026):

| Trình duyệt | Host endpoint | Luật khớp |
|---|---|---|
| Chrome, Edge Android, Samsung Internet, Opera, Brave, Vivaldi, Chrome/Edge… dựa trên Chromium (trừ Edge desktop) | `fcm.googleapis.com` | khớp **chính xác** |
| Firefox (mọi nền tảng) | `updates.push.services.mozilla.com` | khớp chính xác |
| Safari macOS 13+, iOS/iPadOS 16.4+ (PWA màn hình chính) | `web.push.apple.com` (Apple khuyến nghị cho phép `*.push.apple.com`) | hậu tố `.push.apple.com` |
| Edge desktop Windows (WNS) | `*.notify.windows.com` (vd. `wns2-par02p.notify.windows.com`) | hậu tố `.notify.windows.com` |

Không đưa vào: `android.googleapis.com` (GCM cũ, đã tắt từ 2019 — endpoint cũ trả 404/410), `*.googleapis.com` (quá rộng, có dịch vụ khác của Google).
Khớp hậu tố luôn có dấu chấm đầu (`.push.apple.com`) để `evilpush.apple.com.attacker.net`, `fcm.googleapis.com.evil.com`, `evilfcm.googleapis.com` đều bị từ chối.
Danh sách đặt thành hằng trong `config/push.php` (`PUSH_HOST_CHINH_XAC`, `PUSH_HOST_HAU_TO`), KHÔNG cho cấu hình mở rộng từ DB/request.
Mỗi lần từ chối vì host lạ: `error_log('push: host không được phép: ' . $host)` (chỉ host, không ghi cả URL) để quản trị phát hiện nếu có trình duyệt dùng host mới.

### 1.2 Hàm kiểm (thuần, không I/O — để unit test)

```php
/** @return array{ok:bool, host?:string, loi?:string} */
function push_kiem_endpoint(string $ep, array $hostThu = []): array
```

Thứ tự kiểm (dừng ở lỗi đầu tiên):
1. `strlen($ep) <= 500`.
2. Regex toàn chuỗi (đã chạy thử với PHP 8.4 trong phiên thiết kế):
   ```
   #\Ahttps://([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*)(?::(\d{1,5}))?(/[A-Za-z0-9._~%!$&'()*+,;=:@/?-]*)\z#
   ```
   Hệ quả: bắt buộc `https` chữ thường, host chữ thường, không userinfo (`@` trước path), không `\`, không `#`, không khoảng trắng/CR/LF, không `[IPv6]`, không dấu chấm cuối host, bắt buộc có path bắt đầu bằng `/`.
   (Nhãn host đơn như `localhost` qua được regex nhưng sẽ rớt ở bước 4 trừ khi nằm trong `$hostThu`.)
3. Cổng: rỗng hoặc `443`. Ngoại lệ duy nhất: cặp `host:port` có trong `$hostThu` (mục 1.5).
4. Host thuộc allowlist mục 1.1, HOẶC `host:port` ∈ `$hostThu`.
5. Chống lệch bộ phân tích: `parse_url($ep)` phải cho `host` bằng đúng host bắt được ở regex, và không có `user`, `pass`, `fragment`.
6. Trả `['ok'=>true,'host'=>$host,'port'=>$port?:443]`.

Kết quả đã thử (regex đúng như trên): FCM/Mozilla/Apple/WNS thật, kể cả `:443` → OK; `https://127.0.0.1:9444/…`, `fcm.googleapis.com:8443`, `localhost:9443` khi không có test_hosts → cổng; `2130706433`, `0x7f.1`, `169.254.169.254`, `fcm.googleapis.com.evil.com`, `evilfcm.googleapis.com` → host; `https://[::1]/…`, `https://[::ffff:127.0.0.1]/…`, `fcm.googleapis.com@127.0.0.1`, `fcm.googleapis.com\@127.0.0.1`, `FCM.googleapis.com`, `fcm.googleapis.com.`, `#frag`, CRLF, khoảng trắng, `http://`, thiếu path → regex.

Hàm bọc có đọc cấu hình: `push_endpoint_hop_le(string $ep): array` = `push_kiem_endpoint($ep, push_host_thu())`.

### 1.3 Kiểm tại HAI chỗ

- `subscribe`: gọi `push_endpoint_hop_le`; sai → `json_fail('Trình duyệt này dùng máy chủ thông báo chưa được hỗ trợ.', 422)` (thông điệp chung, không phản chiếu input).
- Lúc gửi (`push_xa_hang`, mục 2): kiểm lại từng dòng lấy từ DB. Sai → `DELETE` dòng đó + `error_log` host. Đây là cách dọn dữ liệu cũ (mục 1.6).

### 1.4 Kết nối an toàn (chống DNS rebinding / IP nội bộ)

Trong hàm gửi, mỗi host (cache trong một lượt xả):
1. `$ips = gethostbynamel($host)` (IPv4). Lọc `filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)` (PHP ≥ 8.2; nếu hằng không có thì dùng `FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE` và tự loại thêm `100.64.0.0/10`). Đã thử: `169.254.169.254`, `100.64.1.1`, `::ffff:127.0.0.1` đều bị loại với GLOBAL_RANGE.
2. Không còn IP nào → coi là lỗi tạm (mục 2.4), KHÔNG kết nối.
3. Ghim: `CURLOPT_RESOLVE => ["$host:443:$ipDauTien"]`, `CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4`. Curl không tự resolve lại ⇒ hết đường rebinding.
4. Host trong `$hostThu` (chỉ môi trường thử): bỏ bước lọc IP công khai (mock chạy ở 127.0.0.1), vẫn ghim.

Tuỳ chọn curl bắt buộc cho MỌI lần gửi:
```
CURLOPT_PROTOCOLS_STR => 'https' (nếu defined, PHP ≥ 8.3) ; ngược lại CURLOPT_PROTOCOLS => CURLPROTO_HTTPS
CURLOPT_REDIR_PROTOCOLS(_STR) tương tự
CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0
CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2
CURLOPT_PROXY => ''              // không dùng proxy từ biến môi trường (ghim IP vô nghĩa nếu đi proxy)
CURLOPT_CONNECTTIMEOUT_MS => 3000, CURLOPT_TIMEOUT_MS => 5000
CURLOPT_WRITEFUNCTION => gom tối đa 1024 byte, vượt thì return 0 (curl dừng tải, lỗi 23; mã HTTP vẫn đọc được)
CURLOPT_HEADER => false, CURLOPT_POST => true, CURLOPT_POSTFIELDS => ''
```
JWT VAPID: giữ nguyên cách ký, `aud` = `https://` + host (không kèm cổng — giữ tương thích PUSH-11). Cache JWT theo `aud` trong một lượt xả (đỡ ký lại).

Phương án đã loại:
- *Chỉ chặn IP nội bộ, không allowlist:* còn dùng máy chủ làm "máy quét" tới Internet bất kỳ và gửi JWT ký bằng khoá riêng tới host kẻ tấn công; nhiều cách viết IP (thập phân, hex, IPv6-mapped) dễ sót.
- *Kiểm `CURLINFO_PRIMARY_IP` sau khi gửi:* request đã đi tới đích nội bộ rồi — SSRF mù vẫn xảy ra. Chỉ dùng để ghi log phụ nếu muốn.

### 1.5 Kiểm thử trên máy dev (mock ở localhost)

Tuỳ chọn cấu hình chỉ đọc từ file:
```php
// config/config.local.php (MÁY THỬ, không bao giờ trên máy chủ thật)
'production' => false,
'push' => [ ...khoá VAPID thử..., 'test_hosts' => ['localhost:9443'] ],
```
`push_host_thu()` trả `[]` nếu `app_config('production') !== false`, ngược lại trả `push.test_hosts` (mảng chuỗi `host:port`, chữ thường). Không đọc biến môi trường, không đọc request, không đọc DB. Mặc định (không khai) = rỗng. Khi mảng khác rỗng, mỗi lượt xả ghi `error_log('push: ĐANG BẬT test_hosts …')` để không thể bị bỏ quên. Ghi chú mẫu (dạng comment) vào `config/config.local.example.php` kèm cảnh báo.

### 1.6 Subscription cũ không hợp lệ trong DB

Chốt: **xoá lười lúc gửi** (mục 1.3) + dọn chủ động trong `config/nhac_diem_danh.php` (đã có sẵn khối DELETE dọn outbox ở cuối): lặp `SELECT id, endpoint FROM push_subscriptions`, dòng nào `push_kiem_endpoint` sai (không dùng test_hosts khi CLI production) thì DELETE. KHÔNG thêm lệnh xoá vào `install.php` (nguyên tắc ghi ở đầu file: install không xoá dữ liệu). Không cần SQL REGEXP (tránh nhân đôi luật ở hai ngôn ngữ).

---

## 2. #100 — Không giữ request của người dùng

### 2.1 Bối cảnh hosting

AZDIGI hosting cPanel dùng LiteSpeed Enterprise + LSPHP (LSAPI); một số gói dùng PHP-FPM; môi trường thử dùng `php -S`. Vì vậy phải chạy được ở cả 3 SAPI, không giả định hàm nào có sẵn.
Lưu ý quan trọng từ mã: `_bootstrap.php` mở `ob_gzhandler` / bộ nén brotli (và set sẵn `Content-Encoding: br`), và `json_out()` gọi `exit` ⇒ hàm shutdown chạy TRƯỚC khi PHP đẩy bộ đệm ra client; phiên PHP (`session_start` trong `_common.php`) còn khoá tới cuối request.

### 2.2 Chọn: (a) hàng đợi bền + gửi sau phản hồi, kết hợp curl_multi

**Lược đồ** (thêm vào `push_subscriptions`):
```
ring_seq        INT UNSIGNED NOT NULL DEFAULT 0   -- tăng 1 mỗi lần cần rung máy này
ring_done       INT UNSIGNED NOT NULL DEFAULT 0   -- giá trị ring_seq đã rung xong
ring_lock_until DATETIME NULL                     -- đang có tiến trình giữ / chờ thử lại tới lúc này
ring_tries      TINYINT UNSIGNED NOT NULL DEFAULT 0
last_fail_code  SMALLINT NULL                     -- mã HTTP lỗi gần nhất (0 = timeout/không kết nối)
```
"Máy cần rung" ⇔ `ring_seq > ring_done`. Nhiều thông báo dồn cho cùng một máy gộp thành MỘT cú chuông (SW đã có cơ chế "(và N việc khác)").

**`push_bao()` mới** (giữ chữ ký, giữ "không bao giờ ném lỗi"):
1. Như cũ: lọc id, cắt title/body, INSERT `push_outbox` từng người.
2. `UPDATE push_subscriptions SET ring_seq = ring_seq + 1 WHERE member_id IN (…)`; `$n = rowCount()`.
3. Nếu `PHP_SAPI === 'cli'` → `push_xa_hang(200, 20.0)` ngay (cron/CLI không có người chờ).
   Ngược lại → `push_hen_sau_phan_hoi()`.
4. Trả `$n` = **số máy đã xếp hàng** (đổi nghĩa so với "số máy rung được" — cập nhật docblock và các nơi in ra).
Hệ quả tốt: nếu `push_bao` nằm trong giao dịch bị rollback thì cả outbox lẫn `ring_seq` cùng rollback ⇒ không rung "ma".

**`push_hen_sau_phan_hoi()`**: dùng cờ `static` để chỉ `register_shutdown_function('push_sau_phan_hoi')` một lần/request.

**`push_sau_phan_hoi()`** (hàm shutdown; bọc toàn bộ trong try/catch Throwable → error_log):
1. `push_dong_phan_hoi()` → trả về `true` nếu đã tách được client.
2. `ignore_user_abort(true); @set_time_limit(30);`
3. Đã tách: `push_xa_hang(50, 8.0)`. Chưa tách (fallback cuối): `push_xa_hang(50, 1.5)` — phần chưa xong để lại cho lượt sau.

**`push_dong_phan_hoi(): bool`**:
```
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();   // bắt buộc: không giữ khoá phiên
if (function_exists('fastcgi_finish_request'))  { while (ob_get_level()>0) @ob_end_flush(); fastcgi_finish_request();  return true; }
if (function_exists('litespeed_finish_request')){ while (ob_get_level()>0) @ob_end_flush(); litespeed_finish_request(); return true; }
if (headers_sent()) return false;
// php -S, Apache mod_php, CGI: tự đóng bằng Content-Length
$phan = []; while (ob_get_level() > 0) { $phan[] = (string) ob_get_contents(); @ob_end_clean(); }
$body = implode('', array_reverse($phan));      // tầng dưới ra trước
header_remove('Content-Encoding');               // bỏ gzip/br đã hứa (brotli set sẵn header ở bootstrap)
if (function_exists('apache_setenv')) @apache_setenv('no-gzip', '1');
header('Content-Length: ' . strlen($body));
header('Connection: close');
echo $body; @flush();
return true;
```
(Ghi chú cho người cài đặt: `ob_get_contents()` của tầng gzip/brotli là dữ liệu CHƯA nén, nên Content-Length đúng khi đã bỏ Content-Encoding. Bước đầu tiên khi cài: làm spike 10 dòng xác nhận với `php -S` rằng client Python nhận xong phản hồi trước khi shutdown kết thúc — xem PUSH-22.)

**`push_xa_hang(int $toiDa, float $nganSachGiay): array{rung:int, loi:int}`** — dùng chung cho web/CLI:
1. Lấy ứng viên: `SELECT id FROM push_subscriptions WHERE ring_seq > ring_done AND (ring_lock_until IS NULL OR ring_lock_until < NOW()) ORDER BY id LIMIT ?`.
2. Giành từng dòng: `UPDATE … SET ring_lock_until = NOW() + INTERVAL 60 SECOND WHERE id=? AND ring_seq > ring_done AND (ring_lock_until IS NULL OR ring_lock_until < NOW())`; `rowCount()===1` mới là của mình. Sau khi giành: `SELECT endpoint, ring_seq FROM … WHERE id=?` → nhớ `$S = ring_seq`.
3. Kiểm `push_endpoint_hop_le` → sai: DELETE, bỏ qua.
4. Resolve + lọc IP (mục 1.4) → không có IP hợp lệ: xử lý như lỗi tạm.
5. Tạo handle curl (mục 1.4), cho vào `curl_multi`; vòng `curl_multi_exec`/`curl_multi_select(…, 0.2)` tới khi xong hoặc quá `$nganSachGiay` (đo `hrtime`). Handle chưa xong khi hết ngân sách: `curl_multi_remove_handle` + close, tính như timeout.
6. Ghi kết quả từng dòng:
   - 2xx → `ring_done = GREATEST(ring_done, $S), ring_tries = 0, ring_lock_until = NULL, last_ok_at = NOW(), last_fail_code = NULL`.
   - 404, 410 → `DELETE`.
   - 400, 401, 403, 413 (lỗi vĩnh viễn: sai VAPID, endpoint hỏng) → `ring_done = GREATEST(ring_done,$S), ring_tries=0, ring_lock_until=NULL, last_fail_code=mã` (bỏ cú chuông này, giữ dòng).
   - 0 (timeout/kết nối), 429, 5xx → `ring_tries = ring_tries + 1, last_fail_code = mã, ring_lock_until = NOW() + INTERVAL LEAST(30 * POW(2, ring_tries), 1800) SECOND`; nếu `ring_tries >= 5` thì bỏ cú chuông (`ring_done = $S`, `ring_tries = 0`, lock NULL). Nội dung vẫn nằm trong outbox, sẽ hiện ở cú chuông kế tiếp.
   - Mọi UPDATE kết quả có thêm điều kiện `WHERE id = ?` (không cần khoá lạc quan vì `GREATEST` + `ring_seq` chỉ tăng — cú chuông mới đến trong lúc gửi vẫn còn `ring_seq > ring_done`).

**Xả hàng cơ hội (không cần cron):**
- `push.php?action=status` (app gọi mỗi lần mở): nếu `SELECT 1 FROM push_subscriptions WHERE ring_seq > ring_done AND (ring_lock_until IS NULL OR ring_lock_until < NOW()) LIMIT 1` có kết quả → `push_hen_sau_phan_hoi()`.
- `config/nhac_diem_danh.php` (nếu có cron): cuối script gọi `push_xa_hang(200, 20.0)`.
- Mọi request có `push_bao` tự xả luôn cả dòng tồn của người khác (giới hạn 50).

**LiteSpeed:** thêm vào `public/.htaccess` (bọc `<IfModule LiteSpeed>`, vô hại với Apache):
```
<IfModule LiteSpeed>
  RewriteEngine On
  RewriteRule ^api/ - [E=noabort:1]
</IfModule>
```
để LSWS không giết lsphp khi client đã đóng kết nối trước lúc gửi xong. (Câu hỏi Q3 — cần chủ dự án xác nhận được phép.)

### 2.3 Hành vi `action=test` đổi

- 0 máy của mình → 409 như cũ.
- Có máy → `push_bao(...)`, trả `{ok:true, devices:$n, queued:true}` NGAY (200), không chờ kết quả.
- `status` bổ sung cho máy hiện tại: `lastOkAt`, `lastFailCode` (của dòng member+endpoint) để UI hiện "Lần gửi gần nhất lỗi (mã …)".
- UI (`pushThu`): đổi câu thành "Đã xếp hàng gửi tới N máy. Nếu sau khoảng 30 giây vẫn không thấy, mở lại mục này để xem trạng thái." Có thể gọi lại `_pushDongBo` sau ~5 s để hiện lỗi.

### 2.4 Phương án đã loại
- *(b) Chỉ `curl_multi` trong request, timeout 2–3 s:* vẫn giữ request tới 2–3 s khi push service treo (PUSH-22 yêu cầu < 2 s), và mất cú chuông nếu timeout. Nhưng curl_multi vẫn được dùng BÊN TRONG phương án (a) để một lượt xả nhanh.
- *(c) cron / tiến trình nền (`exec`, `proc_open`):* cron không bắt buộc có trên mọi gói; `exec` thường bị `disable_functions` trên hosting dùng chung. Cron chỉ là đường phụ.
- *Bảng `push_queue` riêng:* thêm bảng + dọn; cột trên `push_subscriptions` tự khử trùng và đủ dùng với vài trăm máy.

### 2.5 Rủi ro còn lại
- **Mất:** tiến trình bị giết giữa chừng → dòng giữ `ring_lock_until` tối đa 60 s rồi được lượt sau nhặt (status/push_bao/cron). Nếu không ai mở app và không có cron, cú chuông chờ tới request kế tiếp có `push_bao` hoặc `status`.
- **Trùng:** timeout sau khi push service đã nhận → thử lại → máy nhận 2 chuông; SW gọi `pending` lần 2 nhận `item:null` và hiện câu chung "Có việc mới". Chấp nhận (Safari có thể thu hồi subscription nếu nhận push mà không hiện thông báo, nên SW PHẢI luôn hiện).
- **Thứ tự:** outbox vẫn lấy theo `id ASC`, nên nội dung đúng thứ tự; chỉ thời điểm chuông có thể lệch.
- **Trễ:** thông báo đến chậm vài giây (sau khi response đã trả). Fallback mod_php/`headers_sent()`: request có thể bị giữ tới 1,5 s.
- `php -S` một worker: phần gửi sau phản hồi vẫn chiếm worker ⇒ request kế tiếp phải đợi. Kịch bản thử nên chạy `PHP_CLI_SERVER_WORKERS=4`.
- `auth.php?action=register` (công khai) kích hoạt gửi nền tới BĐH — lạm dụng spam thuộc P4 (#84), không mở rộng ở đây.

---

## 3. #107 — Sở hữu subscription và token

### 3.1 Lược đồ

```
token_hash CHAR(64) NULL      -- hex SHA-256 của token; NULL = dòng cũ trước bản vá
UNIQUE KEY uq_push_token (token_hash)   -- nhiều NULL vẫn hợp lệ trong unique của InnoDB
```
Token: `push_b64(random_bytes(32))` (43 ký tự). Chỉ lưu `hash('sha256', $token)`; tra cứu `WHERE token_hash = ?` (so khớp qua chỉ mục trên giá trị băm, không có rò rỉ thời gian hữu ích). Không đặt hạn dùng theo thời gian: token sống cùng dòng subscription, "hết hạn" khi bị xoay (đăng ký lại), khi dòng bị xoá (410/404, unsubscribe, thành viên bị xoá — FK CASCADE) hoặc thành viên `đã nghỉ`.

### 3.2 Chỗ đặt migration (theo cách repo đang làm)

- `config/schema.sql`: thêm 6 cột + `UNIQUE KEY uq_push_token (token_hash)` vào `CREATE TABLE push_subscriptions` (cài mới).
- `config/install.php` mảng `$migrations` (nâng cấp CSDL cũ), thêm cuối mảng MỘT lệnh mỗi cột/khoá:
  ```
  "ALTER TABLE push_subscriptions ADD COLUMN token_hash CHAR(64) NULL",
  "ALTER TABLE push_subscriptions ADD UNIQUE KEY uq_push_token (token_hash)",
  "ALTER TABLE push_subscriptions ADD COLUMN ring_seq INT UNSIGNED NOT NULL DEFAULT 0",
  "ALTER TABLE push_subscriptions ADD COLUMN ring_done INT UNSIGNED NOT NULL DEFAULT 0",
  "ALTER TABLE push_subscriptions ADD COLUMN ring_lock_until DATETIME NULL",
  "ALTER TABLE push_subscriptions ADD COLUMN ring_tries TINYINT UNSIGNED NOT NULL DEFAULT 0",
  "ALTER TABLE push_subscriptions ADD COLUMN last_fail_code SMALLINT NULL",
  ```
  và **sửa vòng lặp `$migrations`** để coi `'duplicate key name'` là vô hại (hiện chỉ có `duplicate column name`, `already exists` — chạy lại lần 2 sẽ `exit(1)` ở lệnh ADD UNIQUE KEY). Cập nhật luôn bản `CREATE TABLE IF NOT EXISTS push_subscriptions` trong `$migrations` cho khớp schema.sql.
- KHÔNG thêm file vào `config/migrations/` (runner riêng, `install.php` không chạy nó — xem BAO_CAO_KIEM_THU #87; thêm vào đó chỉ gây hai nguồn sự thật).

### 3.3 `subscribe` (đăng nhập + CSRF như cũ)

Đầu vào `{endpoint}`.
1. Kiểm endpoint (mục 1.2).
2. `SELECT id, member_id FROM push_subscriptions WHERE endpoint = ?`.
3. Chưa có → sinh token, `INSERT (member_id, endpoint, ua, created_at, token_hash)`. Bắt lỗi trùng khoá (hai request đua) → xử lý như bước 4/5.
4. Có và `member_id == me` → sinh token mới, `UPDATE SET token_hash=?, ua=?` (xoay token; dòng cũ không token được nâng cấp tại đây).
5. Có và `member_id != me` → **từ chối**: `json_out(['ok'=>false,'code'=>'endpoint_owned','error'=>'Máy này đang nhận thông báo cho tài khoản khác. Đang đăng ký lại…'], 409)`. Không đổi gì trong DB.
6. Trả `{ok:true, token}`. Bỏ hẳn `ON DUPLICATE KEY UPDATE member_id = …`.

Vì sao từ chối thay vì "chuyển quyền có xác thực": một máy dùng chung (A đăng xuất, B đăng nhập) chỉ cần gọi `pushManager.unsubscribe()` rồi `subscribe()` là trình duyệt cấp **endpoint mới**, nên người dùng thật luôn có đường ra mà không cần chứng minh gì; kẻ tấn công biết endpoint của người khác thì không làm được gì. Dòng cũ của A trỏ tới endpoint đã huỷ sẽ nhận 410 ở lần gửi sau và tự bị xoá. Phương án "chuyển quyền nếu gửi kèm token cũ" bị loại vì thêm nhánh logic mà không thêm khả năng nào (cùng trình duyệt thì đăng ký lại là đủ), và không áp dụng được cho dòng cũ chưa có token.

### 3.4 `pending` (không cần đăng nhập, không CSRF — như cũ)

Đầu vào `{token?, endpoint?}`. Thứ tự:
1. Có `token` (chuỗi 43 ký tự base64url, sai định dạng coi như không có) → `SELECT s.member_id FROM push_subscriptions s JOIN members m ON m.id = s.member_id WHERE s.token_hash = ? AND m.status <> 'đã nghỉ'`.
2. Không có kết quả, có `endpoint`, và **đang trong thời hạn chuyển tiếp** (`push_con_nhan_endpoint_cu()`, mục 3.6) → tra `WHERE endpoint = ? AND token_hash IS NULL` (cùng JOIN members). Dòng đã có token thì endpoint KHÔNG còn là chìa khoá.
3. Vẫn chưa có → `require_login()` (phiên còn thì dùng phiên; không thì 401 như PUSH-16).
4. Phần lấy outbox giữ nguyên.
Token sai hoặc đã bị xoay → rơi xuống bước 3 → 401 nếu không có phiên (không lộ gì, không phân biệt "token sai" với "không có").

### 3.5 `status`, `unsubscribe`

- `status` (đăng nhập): như cũ + `needToken` = (dòng member+endpoint tồn tại và `token_hash IS NULL`) + `lastOkAt`, `lastFailCode` + xả hàng cơ hội (mục 2.2).
- `unsubscribe`: giữ nguyên (xoá theo member + endpoint).

### 3.6 Kế hoạch chuyển tiếp (tương thích ngược)

- Hằng `PUSH_ENDPOINT_CU_HET_HAN = 'YYYY-MM-DD'` trong `config/push.php` (đề xuất: ngày triển khai + 30 ngày; chủ dự án chốt ở Q4), ghi đè được bằng `app_config('push')['legacy_until']`. `push_con_nhan_endpoint_cu()` = hôm nay ≤ ngày đó.
- Trong thời hạn: SW cũ (gửi `endpoint`) và dòng cũ (chưa token) vẫn nhận được nội dung như trước. Ngay khi dòng có token (máy mở app một lần), endpoint của dòng đó hết giá trị.
- Nâng cấp tự động: lần mở app kế tiếp khi đang đăng nhập, `push.js` thấy trình duyệt có subscription + đã cấp quyền + (`needToken` hoặc không tìm thấy token cục bộ) ⇒ gọi lại `subscribe` cùng endpoint ⇒ nhận token ⇒ lưu cho SW.
- Hết hạn: nhánh endpoint tắt. Máy chưa từng mở lại app vẫn được rung; SW nhận 401 và hiện câu chung "Có việc mới — Mở app để xem chi tiết" (không lộ nội dung) → người dùng mở app → được nâng cấp. Có thể theo dõi tiến độ: `SELECT COUNT(*) FROM push_subscriptions WHERE token_hash IS NULL`.
- Bản phát hành sau (ngoài P5): xoá hẳn nhánh endpoint.

### 3.7 Lưu token phía trình duyệt

SW không có cookie sau khi đăng xuất và không đọc được `localStorage` ⇒ dùng **Cache Storage** (đọc/ghi được từ cả trang lẫn SW, API promise gọn, không cần xử lý nâng cấp phiên bản như IndexedDB):
- Tên kho: `'tntt-push'` (KHÔNG bắt đầu bằng `tntt-tinh-` nên `activate` không xoá).
- Khoá: `new URL('__tntt_push_token', registration.scope).href`; giá trị: `new Response(token, {headers:{'Content-Type':'text/plain'}})`.
- Rủi ro: Safari (tab thường, không phải PWA màn hình chính) xoá bộ nhớ trang sau 7 ngày không tương tác ⇒ mất token ⇒ lúc đó dựa vào phiên hoặc hiện câu chung; mở app lại là có token mới. PWA màn hình chính iOS không bị giới hạn này.
- Loại IndexedDB: tương đương về độ bền nhưng mã dài hơn (onupgradeneeded, transaction); `localStorage`: SW không đọc được.

### 3.8 Thay đổi `public/sw.js`

Trong handler `push`:
```js
const khoa = new URL('__tntt_push_token', self.registration.scope).href;
let token = '';
try { const r = await (await caches.open('tntt-push')).match(khoa); if (r) token = (await r.text()).trim(); } catch (e) {}
const dk = token ? null : await self.registration.pushManager.getSubscription();
body: JSON.stringify(token ? { token } : { endpoint: dk ? dk.endpoint : '' })
```
Giữ `credentials: 'include'` (đường phiên). Giữ hành vi LUÔN hiện thông báo. Không bắt buộc đổi `PHIEN_BAN` (trình duyệt cập nhật SW khi byte thay đổi; đổi `PHIEN_BAN` sẽ làm tải lại bundle một lần — chấp nhận được nếu người cài đặt muốn đánh dấu). Không xử lý `pushsubscriptionchange` trong P5 (ghi thành việc sau).

### 3.9 Thay đổi `public/assets/js/modules/push.js`

- Thêm `_tokenLuu(token)`, `_tokenDoc()`, `_tokenXoa()` (Cache Storage như 3.7; mọi lỗi nuốt im lặng).
- `_pushBat()`: sau `pushManager.subscribe`, gọi `subscribe`; nếu `luu.code === 'endpoint_owned'` và chưa thử lại: `await dk.unsubscribe()`, `subscribe` lại với `pushManager` (endpoint mới), gọi API lần nữa (tối đa 1 lần thử lại). Thành công → `_tokenLuu(luu.token)`.
- `pushKhoiDong()`: sau `_pushDongBo(endpoint)`, nếu `dk && Notification.permission==='granted' && this.tbDaBat && (this._canToken || !(await this._tokenDoc()))` → gọi `subscribe` với `dk.endpoint` (lặng lẽ, không toast), lưu token. `_pushDongBo` lưu `d.needToken` vào `this._canToken`.
  Nếu `status.onThisDevice === false` nhưng trình duyệt vẫn có subscription và quyền granted (vd. máy chuyển từ tài khoản khác) → KHÔNG tự đăng ký; giữ nguyên để người dùng bật bằng nút (tránh tự huỷ subscription của người khác mà không hỏi).
- `_pushTat()`: thêm `_tokenXoa()`.
- `pushThu()`: câu thông báo theo mục 2.3.
- Không xoá token khi đăng xuất (giữ tính năng #71).

---

## 4. Kiểm thử nghiệm thu

### 4.1 Chuẩn bị môi trường (thêm vào đầu `tests/e2e/push.py` và mô tả trong docstring)
- `config/config.local.php` thử: `'production' => false`, `'push' => [khoá VAPID thử, 'test_hosts' => ['localhost:9443']]`.
- Chạy `PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8088 -t public`.
- Mock TLS 9443 như cũ + listener thô 9444 (SSRF). Thêm đường `/push/redirect` trả `302 Location: https://127.0.0.1:9444/x`, `/push/big` trả thân 1 MB.
- Hàm `cho(dieu_kien, giay=6)` thăm dò (poll) vì việc gửi giờ là bất đồng bộ.
- Lưu ý nhánh: `tests/e2e/` hiện chỉ có trên nhánh `audit`. PR P5 (tách từ master) hoặc mang theo `tests/e2e/push.py` (+ `e2e.py`, `e2e2.py` mà nó exec) hoặc chạy kịch bản từ checkout nhánh audit trỏ vào mã PR — người cài đặt ghi rõ cách đã chạy trong PR.

### 4.2 Sửa các ca hiện có
| Ca | Thay đổi |
|---|---|
| PUSH-06/07 | Kiểm thêm phản hồi có `token` 43 ký tự; lần 2 (cùng người) trả token KHÁC, vẫn 1 dòng |
| PUSH-08 | `test` trả 200 `devices=1, queued=true`; `cho(len(got)==1)` |
| PUSH-13 | giữ |
| PUSH-14/15 | Đổi sang `{"token": tok}` ẩn danh. Thêm PUSH-14b: `{"endpoint":…}` ẩn danh với dòng CŨ (đặt `token_hash=NULL` bằng SQL) vẫn nhận được trong hạn chuyển tiếp |
| PUSH-16 | giữ (endpoint lạ → 401) |
| PUSH-17 | sau `test`, `cho(count==0)` |
| PUSH-18 | `test` trả 200 (đã xếp hàng); `cho(last_fail_code==500)`; dòng còn; `ring_tries=1`; `ring_lock_until` > NOW() |
| PUSH-19 | giữ |
| PUSH-20 | GLV `subscribe` endpoint của admin → HTTP 409 `code=endpoint_owned`; `member_id` vẫn = admin; `token_hash` không đổi |
| PUSH-21 | Thủ thư subscribe `https://127.0.0.1:9444/internal-admin` → 422; không có dòng; `test` → 409; `hits==0` |
| PUSH-22 | subscribe `/push/slow`; đo thời gian `test` < 2 s; sau đó `cho(len(got)>=1, 8)` chứng minh vẫn gửi ở nền |

### 4.3 Ca mới
| Mã | Mô tả | Kỳ vọng |
|---|---|---|
| PUSH-23 | Token sai (43 ký tự ngẫu nhiên) ẩn danh gọi `pending` | 401, outbox không đổi `taken_at` |
| PUSH-24 | Token cũ sau khi xoay (subscribe lần 2) | 401 |
| PUSH-25 | Dòng ĐÃ có token, gọi `pending` bằng endpoint ẩn danh | 401 (endpoint không còn là chìa khoá) |
| PUSH-26 | Dòng cũ `token_hash NULL`, đặt `push.legacy_until` = hôm qua trong config thử, gọi bằng endpoint | 401 |
| PUSH-27 | Đăng xuất (Client mới, không cookie) rồi `pending` bằng token | nhận đúng item (giữ #71) |
| PUSH-28 | Thành viên chuyển `đã nghỉ`, `pending` bằng token | 401 |
| PUSH-29 | Allowlist từ chối qua API `subscribe` (mỗi URL một lần): `https://2130706433/x`, `https://0x7f.1/x`, `https://[::1]/x`, `https://[::ffff:127.0.0.1]/x`, `https://169.254.169.254/latest`, `https://fcm.googleapis.com@127.0.0.1/x`, `https://fcm.googleapis.com.evil.com/x`, `https://evilfcm.googleapis.com/x`, `https://fcm.googleapis.com:8443/x`, `https://FCM.googleapis.com/x`, `https://localhost:9444/x` (sai cổng thử), `https://fcm.googleapis.com/x#a`, CRLF | 422 tất cả, không có dòng, `hits==0` |
| PUSH-30 | Allowlist chấp nhận (chỉ subscribe, KHÔNG gọi test — tránh gửi ra Internet): FCM, Mozilla, `web.push.apple.com`, `wns2-par02p.notify.windows.com/w/?token=…` | 200 |
| PUSH-31 | Dữ liệu cũ độc hại: INSERT thẳng SQL dòng endpoint `https://127.0.0.1:9444/x` cho admin rồi `test` | `hits==0`; `cho(dòng bị xoá)` |
| PUSH-32 | Redirect: `/push/redirect` | `hits==0`; lỗi tạm hoặc vĩnh viễn được ghi `last_fail_code=302`* |
| PUSH-33 | Phản hồi lớn `/push/big` | xử lý xong trong ngân sách, không lỗi PHP, không treo |
| PUSH-34 | Nhiều máy: 5 dòng `/push/slow` + 1 dòng `/push/ok` | `test` < 2 s; `/push/ok` được nhận (song song, không bị 5 máy chậm chặn tuần tự quá ngân sách) |
| PUSH-35 | Tắt `test_hosts` (xoá khỏi config thử) | subscribe `https://localhost:9443/push/ok` → 422 |
| PUSH-36 | `production => true` + `test_hosts` vẫn khai | subscribe `localhost:9443` → 422 (tuỳ chọn thử bị bỏ qua) |
| PUSH-37 | Xả hàng cơ hội: đặt `ring_seq=ring_done+1`, `ring_lock_until=NULL` bằng SQL cho dòng `/push/ok`, gọi `status` | `cho(len(got)==1)`, `ring_done=ring_seq` |
| PUSH-38 | Rollback: (unit/phpunit) `push_bao` trong `trong_giao_dich` ném lỗi | không có outbox, `ring_seq` không đổi |

\* 3xx: xếp vào nhóm "vĩnh viễn" (bỏ cú chuông, giữ dòng) — push service thật không redirect.

### 4.4 Unit test (chạy trong CI không cần mạng) — `tests/unit/PushEndpointTest.php`
- `push_kiem_endpoint()` với toàn bộ bảng mục 1.2 + PUSH-29/30 (không DNS).
- Hàm lọc IP (tách thành `push_ip_cong_khai(string $ip): bool`): `127.0.0.1`, `10.x`, `172.16.x`, `192.168.x`, `169.254.169.254`, `100.64.1.1`, `0.0.0.0`, `::1`, `::ffff:127.0.0.1`, `fc00::1` → false; `8.8.8.8`, `216.239.36.55` → true.
- `push_host_thu()` trả `[]` khi production true.

### 4.5 Không tự động được (kiểm thủ công trên thiết bị thật sau khi merge)
- Gửi tới FCM thật (Chrome Android), Mozilla (Firefox), Apple (iPhone PWA iOS ≥ 16.4, Safari macOS), WNS (Edge Windows): bật → test → nhận; khoá màn hình; đăng xuất rồi vẫn nhận nội dung (#71).
- Nâng cấp máy đã bật trước bản vá: mở app một lần → `token_hash` được điền; đóng app, gửi thông báo → nhận đúng nội dung.
- Máy dùng chung: A bật, đăng xuất; B đăng nhập, bật → endpoint mới, A không còn dòng hợp lệ sau lần gửi kế.
- Trên hosting AZDIGI thật: đo thời gian `leave.php create` khi có ~20 máy nhận; xác nhận SAPI và hàm `*_finish_request` (Q2).

---

## 5. File sẽ đổi và thứ tự commit

| # | Commit | File |
|---|---|---|
| 1 | `push: lược đồ token + hàng đợi chuông` | `config/schema.sql`, `config/install.php` (`$migrations` + thêm `duplicate key name` vào danh sách vô hại) |
| 2 | `push: kiểm endpoint theo allowlist, chống SSRF (#99)` | `config/push.php` (`push_kiem_endpoint`, `push_endpoint_hop_le`, `push_host_thu`, `push_ip_cong_khai`), `public/api/push.php` (subscribe dùng kiểm mới), `config/config.local.example.php` (comment `test_hosts`), `tests/unit/PushEndpointTest.php` |
| 3 | `push: gửi sau phản hồi, curl_multi, hàng đợi bền (#100)` | `config/push.php` (`push_bao`, `push_hen_sau_phan_hoi`, `push_sau_phan_hoi`, `push_dong_phan_hoi`, `push_xa_hang`; `push_gui` thay bằng dựng handle), `public/api/push.php` (test, status), `config/nhac_diem_danh.php` (xả cuối + dọn endpoint sai), `public/.htaccess` (noabort, nếu Q3 = có) |
| 4 | `push: token cho subscription, cấm chiếm endpoint (#107)` | `public/api/push.php` (subscribe/pending/status), `config/push.php` (sinh/băm token, `push_con_nhan_endpoint_cu`), `public/sw.js`, `public/assets/js/modules/push.js` |
| 5 | `test(e2e): PUSH-20…38` | `tests/e2e/push.py` |

Không đụng: `_bootstrap.php`, `_http_util.php`, `leave.php`, `announcements.php`, `auth.php`, `nhac_lich.php` (chữ ký `push_bao` giữ nguyên). Nếu gói P4 đang sửa `_bootstrap.php` thì không xung đột.
Mỗi commit phải qua `php -l` và unit test; commit 3 và 4 chạy lại toàn bộ `push.py`.

---

## 6. Câu hỏi cho chủ dự án (có/không)

1. **Q1** Chấp nhận thông báo đẩy đến chậm vài giây (gửi sau khi trả phản hồi) và nút "Gửi thử" chỉ báo "đã xếp hàng", không báo "đã rung được" ngay? (đề xuất: CÓ)
2. **Q2** Gói AZDIGI đang dùng là LiteSpeed/LSPHP (hoặc PHP-FPM), không phải Apache mod_php? — cần chủ dự án tạo tạm một file PHP in `PHP_SAPI`, `function_exists('litespeed_finish_request')`, `function_exists('fastcgi_finish_request')`, `function_exists('curl_multi_init')`, rồi xoá file.
3. **Q3** Cho phép thêm `RewriteRule ^api/ - [E=noabort:1]` (bọc `<IfModule LiteSpeed>`) vào `public/.htaccess`? (đề xuất: CÓ)
4. **Q4** Thời hạn chuyển tiếp cho SW/subscription cũ dùng endpoint là 30 ngày kể từ ngày triển khai? (CÓ = 30 ngày; KHÔNG = nêu số ngày, hoặc 0 = tắt ngay và chấp nhận máy cũ chỉ thấy "Có việc mới" tới khi mở app)
5. **Q5** Chỉ hỗ trợ 4 nhóm push service (FCM, Mozilla, Apple, Microsoft WNS); trình duyệt dùng host khác sẽ bị từ chối với thông báo "chưa được hỗ trợ"? (đề xuất: CÓ)
6. **Q6** Tự xoá subscription có endpoint ngoài allowlist đang có trong DB (thay vì chỉ bỏ qua)? (đề xuất: CÓ)
7. **Q7** Khi máy dùng chung chuyển tài khoản, client được tự huỷ đăng ký cũ và đăng ký lại (người dùng trước trên máy đó thôi nhận thông báo trên máy này) mà không hỏi thêm? (đề xuất: CÓ)
8. **Q8** Thành viên `chờ duyệt`/`tạm nghỉ` vẫn được nhận nội dung thông báo qua token (chỉ chặn `đã nghỉ`)? (đề xuất: CÓ — giữ hành vi hiện tại)
9. **Q9** Có muốn thêm giới hạn tần suất cho nút "Gửi thử" (ví dụ 1 lần/10 giây/người) trong P5, hay để P4? (đề xuất: để P4)

## 7. Việc để sau (không làm trong P5)
- `pushsubscriptionchange` trong SW (trình duyệt tự xoay endpoint).
- `UNIQUE KEY uq_push (endpoint(255))` chỉ so 255 ký tự đầu — hai endpoint WNS dài khác nhau ở phần đuôi có thể va nhau; cân nhắc cột `endpoint_hash CHAR(64)` unique.
- `status` nhận `endpoint` qua query string (lọt vào access log); sau khi hết hạn chuyển tiếp thì endpoint không còn là bí mật chức năng, có thể chuyển sang POST.
- Xoá hẳn nhánh `endpoint` ở `pending` sau hạn Q4.
