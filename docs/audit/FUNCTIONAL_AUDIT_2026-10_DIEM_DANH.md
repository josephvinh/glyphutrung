# Audit chức năng — Điểm danh & Quét QR (2026-10)

> Phạm vi: `public/api/attendance.php`, `public/assets/js/modules/attendance.js`,
> `qrscan.js`, `qrcard.js`, `stats.js`, `public/sw.js`, `data.php` (phần điểm danh),
> `export.php?action=attendance-detail`. Theo `docs/process/FUNCTIONAL_AUDIT.md`.
> Ngày kiểm: 2026-10-08. **Chưa sửa mã nguồn nào** — báo cáo này chỉ ghi lỗi.

## 1. Cách kiểm

- DB test MariaDB 10.11 dựng bằng `config/install.php` + `tests/fixtures/ci_seed.php`
  (KHÔNG chạm DB thật). Thêm 7 tài khoản mỗi vai, 63 thiếu nhi ở nhiều lớp/khối,
  10 chương trình (giờ chốt/vắng khác nhau, chiến dịch, gắn lớp, đã đóng).
- `php -S` chạy app thật, gọi API bằng cookie + CSRF thật theo từng vai.
- Chromium (Playwright 1.56) điều khiển giao diện thật: giả lập mất mạng
  (`setOffline`), chặn/hủy phản hồi (`route.fetch()` rồi `abort`), hai phiên song
  song, và **camera giả** (`--use-file-for-fake-video-capture` phát video chứa mã QR).
- PHPUnit: **456/456 xanh** (`php phpunit.phar --testsuite "TNTT Unit Tests"`).
  Test xanh nhưng không phủ các lỗi dưới đây (chưa có test nào cho hàng đợi
  ngoại tuyến, `method` lạ, hai phiên chạm song song).

## 2. Tổng hợp

| Mức | # | Nội dung ngắn |
|---|---|---|
| 🟠 | F1 | Hàng đợi ngoại tuyến dùng `toggle` (không idempotent): bật→tắt offline vẫn ghi; gặp em đã có thì xóa |
| 🟠 | F2 | Gói QR đã ghi nhưng mất phản hồi → chuyển sang hàng đợi `toggle` → đồng bộ **xóa** em đã ghi |
| 🟠 | F3 | Mục bị máy chủ từ chối (khóa giờ vắng, 403 lớp) bị nuốt im lặng; QR hiện "đã điểm danh" nhưng DB không có |
| 🟠 | F4 | Trạng thái do giờ **đồng bộ** quyết định → em đến đúng giờ bị ghi "đi trễ" |
| 🟠 | F5 | Quét QR không bắt đầu được khi offline; jsQR (iPhone) không precache |
| 🟡 | F6 | Hai người chạm cùng em cùng lúc → HTTP 500 (`Duplicate entry uq_att`) |
| 🟡 | F7 | `method` do client gửi không được kiểm → 500 hoặc giả mạo `qr` |
| 🟡 | F8 | GLV thường/dự bị gỡ được bản ghi buổi đã qua giờ "tính vắng" |
| 🟡 | F9 | Phạm vi quét QR (khối) ≠ chạm tay (lớp); quét bị chặn sau giờ vắng còn chạm tay thì bù được |
| 🟡 | F10 | Mã QR chữ thường / có khoảng trắng đầu bị báo "không có em nào" |
| 🟡 | F11 | Giao diện không đối chiếu `skipped` của máy chủ; toast cuối đếm theo số quét |
| 🟡 | F12 | Quiet zone thẻ QR in ~2,9 mô-đun (< chuẩn 4) |
| 🟡 | F13 | Thống kê: mẫu số không biết ngày em vào; buổi ít bản ghi bị tính vắng hàng loạt |
| 🔵 | F14 | `pageerror` mỗi lần tải trang (màn Phân quyền); mã chẩn đoán QR tạm; `data.php` có đoạn thừa |

---

## 3. Chi tiết từng lỗi

### F1 🟠 Hàng đợi ngoại tuyến là `toggle`, không idempotent

**Vị trí:** `attendance.js:55-64` (`pushOfflineAttendance`), `:78-125` (`syncOfflineAttendance`),
`attendance.php` nhánh toggle.

**Mô tả:** hàng đợi lưu một lệnh `{programId,date,studentId,action:'toggle'}` cho mỗi em
(trùng khóa thì **ghi đè**, không cộng dồn). Khi đồng bộ, máy chủ chạy `toggle` =
"có thì xóa, chưa có thì thêm" — kết quả phụ thuộc trạng thái máy chủ lúc đó, không
phải ý định của GLV.

**Tái hiện A (bật→tắt):** GLV chủ nhiệm 1A, buổi P10 hôm nay, tắt mạng (`setOffline(true)`):
1. `toggleAttendance(HS001)` → UI "có mặt", hàng đợi `["1:toggle"]`.
2. Chờ >450 ms, `toggleAttendance(HS001)` lần nữa → UI "vắng", hàng đợi vẫn `["1:toggle"]` (1 mục).
3. Bật mạng, `syncOfflineAttendance()`.

*Mong đợi:* DB 0 bản ghi (GLV để em vắng). *Thực tế:* DB `attendances` có 1 bản ghi
HS001 "có mặt", UI chuyển sang "có mặt", và em được cộng Mộc nếu buổi tính Mộc.

**Tái hiện B (em đã có sẵn):** DB đã có HS001 "có mặt" (do GLV khác/QR ghi). Hàng đợi
có `toggle` cho HS001 → đồng bộ → DB **0** bản ghi, toast xanh
"Đã đồng bộ thành công 1 lượt điểm danh". Em bị xóa mà người dùng được báo thành công.

**Hướng sửa:** hàng đợi lưu **trạng thái đích** (`op: 'mark'|'unmark'`), máy chủ có
action idempotent tương ứng (hoặc `toggle` kèm `expect: present|absent` và bỏ qua nếu đã đúng).
Gộp mục cùng khóa theo trạng thái cuối; bật→tắt phải triệt tiêu (xóa mục khỏi hàng đợi).

### F2 🟠 Gói QR mất phản hồi → hàng đợi `toggle` xóa em đã ghi

**Vị trí:** `qrscan.js:518-545` (`ketThucQuet`), `:564-585` (`dongQuetQR`), `:487-` (`_qrGuiLo`).

**Mô tả:** `scan` dùng `INSERT IGNORE` nên gửi lặp an toàn. Nhưng khi còn mã chưa gửi lúc
kết thúc, code đẩy chúng vào hàng đợi ngoại tuyến với `action: 'toggle'`.

**Tái hiện (K4):** chặn `**/api/attendance.php?action=scan` bằng `route.fetch()` (máy chủ
**thực thi**) rồi `route.abort('failed')` (trình duyệt không nhận phản hồi). Quét 20 mã
`Q001..Q020`. App gửi lại tối đa 10 vòng rồi chuyển 20 mã vào hàng đợi. Bỏ chặn, đồng bộ.

*Mong đợi:* DB 20 em. *Thực tế:* DB còn **1**; UI 1; toast xanh
"Đã đồng bộ thành công 19 lượt điểm danh". 19 em bị xóa.
(Ghi chú: tại thời điểm kiểm 500 ms DB hiện 0 vì lệnh chặn còn đang chạy; kết luận
"máy chủ đã ghi" suy ra từ việc đồng bộ xóa được 19 bản ghi.)

**Hướng sửa:** mã QR chưa gửi phải gửi lại bằng `scan` (idempotent), kể cả từ hàng đợi
ngoại tuyến (`op:'scan', codes:[...]`). Không bao giờ chuyển thành `toggle`.

### F3 🟠 Mục bị máy chủ từ chối bị nuốt im lặng; QR báo OK giả

**Vị trí:** `attendance.js:107-108`; `qrscan.js:487-` (`_qrGuiLo`), `:518-`.

**(a) Hàng đợi chạm tay:** nhánh `else` của `syncOfflineAttendance` chỉ `console.warn`,
mục bị bỏ khỏi hàng đợi, không toast.
*Tái hiện:* GLV thường (0911000001), hàng đợi `{programId:14,date:'2026-10-04',studentId:1}`
(P14 có `absent_time` 08:00, đã qua) → sync → hàng đợi 0, **không thông báo nào**, DB 0 bản ghi.

**(b) QR bị từ chối (K5):** admin, P12 (đã qua giờ vắng), quét 5 mã. `scan` trả 400
"Đã quá giờ 'tính vắng'". `_qrGuiLo` trả mã về hàng và **gửi lại mỗi 1,2 s mãi** trong lúc
camera mở; giao diện đã bíp OK và hiện 5 em "đã điểm danh" (UI 5, DB 0). Khi kết thúc,
5 mã vào hàng đợi với thông báo sai "do mất mạng". Với admin, `toggle` có cửa sửa nên
cuối cùng DB có 5 bản ghi — **vòng qua rào** mà `scan` đã chặn. Với GLV thường mục bị
nuốt như (a).

**(c) Em lớp bên cạnh cùng khối (suy ra từ code, chưa chạy riêng):** `scan` cho GLV quét em
khác lớp cùng khối (xem F9) nhưng `toggle` trả 403 → trong hàng đợi bị nuốt.

**Hướng sửa:** phân biệt lỗi mạng (thử lại) với lỗi nghiệp vụ (hiển thị rõ cho người dùng,
liệt kê em nào bị từ chối + lý do, rollback bản ghi tạm trên giao diện). Không retry vô hạn
lỗi 4xx.

### F4 🟠 Trạng thái do giờ đồng bộ quyết định

`attendance.php` tính `$status` theo `time()` của máy chủ lúc xử lý. Hàng đợi có
`createdAt` nhưng máy chủ không nhận/dùng. Em được chạm lúc 07:10 (trước chốt 07:30)
khi offline, đồng bộ lúc 08:00 → ghi "đi trễ". Giao diện đã hiển thị "có mặt" lúc chạm.

**Lưu ý thiết kế:** máy chủ quyết định là cố ý (chống đổi giờ điện thoại). Cần chọn
chính sách: (1) chấp nhận và cảnh báo rõ trên giao diện offline, hoặc (2) cho phép
`createdAt` nhưng máy chủ chỉ tin nếu nằm trong khoảng hợp lý và đã gắn thiết bị/phiên,
hoặc (3) buổi offline luôn ghi "có mặt" nếu lúc chạm chưa quá chốt theo giờ máy +
đánh dấu `offline=1` để BĐH rà soát.

### F5 🟠 Quét QR không bắt đầu được khi offline; jsQR không precache

**Vị trí:** `qrscan.js:105` (`_qrTaiBangTra`), `:127` (`_qrTaiJsQR`), `public/sw.js`
(precache chỉ `bundle.php` css/js).

- (suy ra từ code) `moQuetQR()` luôn gọi `api('attendance','lookup')` để tải bảng
  mã→tên. Không có mạng → báo lỗi và dừng. Hàng đợi chỉ cứu được khi mạng đứt **sau khi** đã mở quét.
- `jsQR.min.js` (127 KB) tải lười, chỉ vào kho SW sau lần tải đầu. iPhone Safari (không có
  `BarcodeDetector`) chưa từng mở máy quét mà vào sân không sóng → "Không tải được bộ giải mã QR".

**Hướng sửa:** lưu bảng tra (4 trường: mã, id, tên, lớp) vào `localStorage`/IndexedDB khi mở
buổi, theo `programId+date`, kèm thời điểm; thêm `assets/js/vendor/jsQR.min.js` vào precache
của `sw.js`.

### F6 🟡 Hai người chạm cùng em cùng lúc → HTTP 500

**Vị trí:** `attendance.php` — `SELECT` kiểm tồn tại rồi `INSERT` (không `INSERT IGNORE`,
không bắt `23000`).

*Tái hiện:* 4 phiên (admin, bdh, trưởng khối, GLV chủ nhiệm) × 6 request `toggle` song song
cùng một em, lặp 5 vòng = 120 request: 109 × 200, **11 × 500**
(`SQLSTATE[23000] 1062 Duplicate entry '10-2026-10-08-1' for key 'uq_att'`).
Dữ liệu không trùng (ràng buộc DB cứu), nhưng người dùng nhận "lỗi hệ thống".
Log còn 9 cảnh báo `Sổ Mộc: recalc_stamps lỗi ... 1452 foreign key` trong lúc đua (bị
`recalc_stamps_safe` nuốt). Sau 4 vòng đua thử, ví Mộc **khớp** điểm danh nên chưa kết
luận lỗi dữ liệu Mộc — nên có test riêng.

**Hướng sửa:** bắt `23000` → coi như "đã có" (trả `removed:false` với bản ghi hiện có), hoặc
dùng `INSERT IGNORE` + `rowCount()`, hoặc khóa `SELECT ... FOR UPDATE` trong transaction.

### F7 🟡 `method` từ client không được kiểm

**Vị trí:** `attendance.php:325` — `$in['method'] ?? 'tay'` đưa thẳng vào SQL.
Cột là `enum('tay','qr')`.

- `method:'abc'` hoặc `['x']` → HTTP 500 (`Data truncated for column 'method'`).
- `method:'qr'` trên đường chạm tay được chấp nhận → bản ghi tay giả danh QR, sai thống kê.

**Hướng sửa:** nhánh toggle luôn ghi `'tay'`; chỉ nhánh `scan` ghi `'qr'`. Bỏ đọc `method` từ client.

### F8 🟡 Gỡ bản ghi vượt rào "giờ tính vắng" cho GLV thường

**Vị trí:** `attendance.php:288-` (nhánh gỡ chạy trước kiểm `$pastAbsent` ở `:318`).

Ma trận vai: GLV thường và dự bị **ghi** buổi quá giờ vắng → 400 (đúng), nhưng **gỡ**
bản ghi của chính buổi đó → 200. Nghĩa là GLV thường xóa lùi điểm danh cũ trong lớp mình,
trong khi ghi bù thì bị khóa. Chỉ ghi nhật ký khi `$pastCutoff`, nhưng nhánh gỡ không nằm
trong `can_override_session_lock`.

**Hướng sửa:** gỡ buổi đã qua giờ vắng cũng đòi `can_override_session_lock` (giữ ngoại lệ
"không bị kẹt" cho trường hợp lớp bị gỡ khỏi chương trình bằng cách vẫn cho gỡ **nhưng ghi
nhật ký**). Luôn ghi nhật ký khi gỡ sau giờ chốt.

### F9 🟡 Phạm vi quét ≠ chạm tay; quét bị chặn sau giờ vắng trong khi chạm tay bù được

**Vị trí:** `_bootstrap.php:519` (`scan_class_ids`), `attendance.php` nhánh `scan` vs toggle.

- GLV lớp 1A quét được em lớp 1B (cùng khối): `added=2` và UI K7 ghi `HS004`. Chạm tay cùng em → 403.
  Tài liệu mô tả quét theo khối là cố ý, nhưng lệch luật cứng #6 của CLAUDE.md
  (phạm vi theo-đối-tượng `can_access_class`).
- `scan_class_ids` loại vai có quyền `none` nhưng **không** đòi mức `edit`; một phân công
  chỉ có quyền xem ở khối khác vẫn nới phạm vi quét ghi. (Chưa tái hiện — seed không có
  vai "view-only" cho attendance; cần thêm test.)
- Trưởng khối/GLV chủ nhiệm bị chặn khi quét sau `absent_time` (`scan` không có cửa sửa)
  nhưng chạm tay được bù. Không nhất quán.

**Hướng sửa:** thống nhất chính sách (quyết định sản phẩm), áp `can_access_class(...,'edit')`
hoặc ghi rõ ngoại lệ trong tài liệu + test; áp cửa sửa như nhau.

### F10 🟡 Mã QR chữ thường / khoảng trắng đầu

**Vị trí:** `attendance.php` nhánh `scan`: truy vấn `WHERE s.code IN (...)` dùng mã **chưa trim**,
tra `$theoMa[trim($ma)]` có phân biệt hoa/thường.

- `hs001` → "không có em nào mang mã này" (SQL ci khớp nhưng khóa PHP không khớp).
- `" HS002"` → SQL không khớp (khoảng trắng đầu), thông báo hiện `HS002` đã trim nên gây hiểu lầm.
- `"HS003 "` khớp ngẫu nhiên (PAD SPACE).
- `[["HS001"]]` hiện "Array" trong `skipped`.

**Hướng sửa:** chuẩn hóa một lần (`trim`, `strtoupper` hoặc so khớp theo `mb_strtolower`)
cho cả truy vấn lẫn bản đồ; bỏ phần tử không phải chuỗi.

### F11 🟡 Giao diện không đối chiếu `skipped`

**Vị trí:** `qrscan.js:477` (`_qrGhiTamThoi`), `:487-` (`_qrGuiLo`).

Mã bị máy chủ bỏ qua (em nghỉ, ngoài lớp của buổi) vẫn hiện "có mặt" trên máy; chỉ có dòng
trạng thái "Máy chủ bỏ qua N mã". Toast cuối "Đã điểm danh {qrDaQuet} em" đếm theo số
quét, không theo `added` của máy chủ.

**Hướng sửa:** rollback bản ghi tạm của mã nằm trong `skipped` và hiển thị danh sách lý do.

### F12 🟡 Quiet zone thẻ QR

**Vị trí:** `qrcard.js:192` — `createImgTag(coO, 0)` (lề 0), CSS `.the{padding:3mm}`,
mã 22 mm / 21 mô-đun ≈ 1,05 mm/mô-đun → lề ≈ 2,9 mô-đun (chuẩn: 4).

jsQR vẫn đọc được khi giả lập (nền xám, mờ, nhiễu), nên chưa phải lỗi chức năng,
nhưng không còn dư an toàn với viền thẻ tối hoặc ảnh chụp nghiêng. Nên đặt lề 4 mô-đun
trong ảnh hoặc `padding ≥ 4.5mm`.

### F13 🟡 Thống kê chuyên cần: mẫu số

**Vị trí:** `stats.js` `summaryFor`, `sessionsBetween`.

- Mẫu số dùng danh sách em **hiện tại**; bảng `enrollments` không có ngày vào → em vào giữa
  năm bị tính "vắng không phép" cho các buổi trước đó.
- Buổi bị loại khỏi mẫu số nếu **không có bản ghi nào** trong phạm vi xem (coi như quên điểm
  danh), nhưng buổi chỉ có vài bản ghi thì mọi em còn lại bị tính vắng không phép.
- `isPastCutoffFor` dùng đồng hồ máy khách (`nowTs`), nên số liệu phụ thuộc giờ thiết bị.

### F14 🔵 Khác

- **`pageerror` mỗi lần tải trang:** `TypeError: Cannot read properties of undefined (reading 'glv')`
  tại `views/module_settings.php:167` (`permissions[m.key][permRoleTab]` khi `permissions[m.key]`
  chưa có). Làm tiêu chí "0 pageerror" của smoke test không đạt.
- `qrscan.js` còn mã chẩn đoán camera "tạm thời" (`_qrTuKiemTra`) — ghi chú nói nên gỡ.
- `data.php` ~dòng 315-325 gán `$sql`/`$params` rồi ghi đè ngay (mã thừa).
- Cache `data.php` nằm ở `public/cache/` (trong web root); chỉ có `.htaccess` chặn (vô hiệu
  trên nginx/php -S). Luật cứng #9 yêu cầu để ngoài web root. **Chưa xác minh** file cache
  có tải được từ ngoài không. Ghi liên quan `SECURITY_AUDIT.md` S1.
- `studentId="1abc"` và `studentId=[1]` bị ép thành `1` (PHP `(int)`); nên kiểm `is_int`/`ctype_digit`.

---

## 4. Những gì đã kiểm và ĐÚNG

- Ngày: `2026-10-04abc`, `"…\n"`, `2026-02-30`, `20261004`, mảng, số → 400. Ngày không đúng thứ → 400.
- Giờ chốt/vắng: trước chốt "có mặt", sau chốt "đi trễ", sau giờ vắng bị chặn (admin/BĐH/
  trưởng khối/GLV chủ nhiệm trong phạm vi vẫn bù được). Chiến dịch đúng/sai ngày; chương trình
  đã đóng; buổi gắn lớp chặn lớp ngoài.
- Buổi tương lai: ghi mới bị chặn (chạm tay & quét), gỡ vẫn được.
- Em nghỉ, em không tồn tại, lớp khác, khối khác → chặn đúng; thủ thư 403 mọi thao tác.
- `set_status`: chỉ admin/BĐH/trưởng khối/GLV chủ nhiệm trong phạm vi; trạng thái rác 400;
  chưa có bản ghi 404.
- Đọc dữ liệu: `data.php` GLV chỉ nhận lớp mình, trưởng khối cả khối, thủ thư 0; xuất Excel
  chặn `classId` ngoài phạm vi (403).
- QR: tối đa 200 mã/lần; trùng mã; SQL injection trong mã; `allow_qr=0`; lớp ngoài buổi;
  chia lô 25 (55 mã → `[25,25,5]`); quét lại idempotent; hai thiết bị quét song song 60 mã →
  DB 60, 0 trùng, 0 `Duplicate entry` mới; Sổ Mộc khớp giao dịch (K1, K6).
- Mất mạng giữa chừng khi `scan` chưa được xử lý (K3): 20 mã vào hàng đợi, có mạng → DB đủ 30.
- Không có `x-html`/`innerHTML` ở màn điểm danh → không thấy XSS.

## 5. Công nghệ quét QR (đã đo bằng camera giả)

| Thành phần | Hiện trạng |
|---|---|
| Sinh mã | `qrcode-generator 1.4.4`, mức M, nội dung = mã em (vd `HS001`, 21×21 mô-đun) |
| Giải mã chính | `BarcodeDetector` (Chrome/Android) — **chưa chạy được trong headless Linux** |
| Giải mã dự phòng | `jsQR 1.4.0` (127 KB, tải lười, luồng chính, ~20 lần/giây) |
| Camera | `getUserMedia`, đòi HTTPS (đã kiểm `isSecureContext`); `Permissions-Policy: camera=(self)` |

Đo đường jsQR đầu-cuối (camera giả 640×480, khung sạch, 3 mã/video):

| px/mô-đun | rộng mã | đọc được | mã đầu sau |
|---|---|---|---|
| 8 | ~168 px | 3/3 → DB đủ | 0,62 s |
| 4 | ~84 px | 3/3 | 0,60 s |
| 3 | ~63 px | 3/3 | 0,66 s |
| 2 | ~42 px | 3/3 | 0,57 s |

Giới hạn: khung sạch, không rung/ngược sáng/lệch góc; chưa chứng minh độ bền ngoài sân và
chưa đo đường `BarcodeDetector`. Nên thử trên thiết bị thật (Android Chrome, iPhone Safari).

## 6. Đề xuất kế hoạch sửa

1. Viết test hàng đợi ngoại tuyến trước (Playwright + PHPUnit cho action idempotent).
2. PR 1: hàng đợi theo trạng thái đích + `scan` idempotent từ hàng đợi + báo lỗi từ chối (F1–F3, F11).
3. PR 2: lưu bảng tra QR offline + precache jsQR (F5).
4. PR 3: toggle chống đua, bỏ `method` từ client, chuẩn hóa mã QR (F6, F7, F10).
5. PR 4: thống nhất chính sách khóa/phạm vi (F8, F9) — cần quyết định sản phẩm trước.
6. Riêng: F4 (quyết định thiết kế), F12, F13, F14.

Mỗi PR một mục đích (luật cứng #4); thêm test "lớp khác → 403" khi đụng phạm vi (luật cứng #6).
