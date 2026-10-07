# Kế hoạch: trang công khai "Lời Chúa hôm nay & Suy niệm"

> Trạng thái: **ĐỀ XUẤT — chưa có dòng code nào được viết.** Tài liệu này là
> kết quả khảo sát nguồn dữ liệu (ngày 07/10/2026) và thiết kế để duyệt trước
> khi làm. Theo `docs/process/FEATURE_WORKFLOW.md`: một thay đổi = một mục đích =
> một PR, nên kế hoạch chia thành nhiều PR nhỏ (mục 11).

---

## 1. Vấn đề hiện tại (đã kiểm chứng)

1. **Nguồn câu Kinh Thánh đã chết.** `public/api/bible.php` gọi
   `https://bible-api.com/api/random?translation=vietnamese`. Kiểm ngày
   07/10/2026: đường dẫn này trả **404**, và danh sách bản dịch
   `https://bible-api.com/data` **không có tiếng Việt**. Hậu quả: mọi lượt
   `action=random` đều rơi vào `FALLBACK_VERSES` mà không báo lỗi.
2. **Câu dự phòng sai chuẩn.** 10 câu trong `FALLBACK_VERSES` viết **không dấu**,
   là bản dịch tự gõ (không phải bản Công Giáo), tham chiếu bằng tiếng Anh
   (`John 3:16`, `Psalms 126:3`…). Chưa đối chiếu từng câu với tham chiếu, nhưng vài câu (ví dụ gắn `Jeremiah 32:17`) nhìn không giống lời trong sách đó — cần duyệt lại.
3. **Bốc ngẫu nhiên không hợp mục vụ.** Một lần thử bốc ngẫu nhiên trên toàn bộ
   Kinh Thánh trả về `1Sb 7,30` (một dòng gia phả). Ngẫu nhiên toàn bộ sách sẽ
   thường xuyên ra những câu như vậy.
4. **Lỗ hổng đang mở liên quan** (`docs/security/SECURITY_AUDIT.md`):
   - **S6**: `views/layout_landing.php` chèn `verse`/`ref` bằng `innerHTML`
     không escape, và lưu `localStorage` rồi render lại.
   - **S10**: `bible.php` chạy `CREATE TABLE IF NOT EXISTS` mỗi request và lưu
     IP khách (`bible_daily`) — `docs/process/PRIVACY_AUDIT.md` coi IP là dữ
     liệu cá nhân. Hiện đã có dọn 30 ngày ở `config/cron_cleanup.php`.
5. **Thẻ ở trang chủ** (`views/layout_bible_card.php`) chỉ hiện một câu, không
   dẫn tới đâu; không có trang đọc đầy đủ.

## 2. Mục tiêu

- Có **một trang công khai** (không đăng nhập) hiển thị **Lời Chúa của ngày**
  theo lịch phụng vụ Việt Nam: ngày, mùa, tuần phụng vụ, màu áo lễ, các bài đọc
  (bài đọc 1, Thánh Vịnh, Tin Mừng) bằng bản dịch Công Giáo.
- **Bấm vào thẻ Lời Chúa ở trang chủ** thì mở trang này.
- Phần **Suy niệm**: hiện **liên kết** sang nguồn gốc (không sao chép nội dung).
- Khi nguồn lỗi, **không trắng trang**: rơi về một danh sách câu dự phòng đã
  duyệt, có dấu, tham chiếu tiếng Việt.
- Không làm tăng bề mặt tấn công của một trang công khai.

### Ngoài phạm vi (cố ý không làm)

- Không sao chép/phân phối lại toàn bộ Kinh Thánh hay lịch.
- Không tự viết/lưu bài suy niệm (đã hỏi người dùng, chọn "nguồn ngoài").
- Không thay đổi cơ chế đăng nhập, phân quyền, hay dữ liệu thiếu nhi.
- Không đổi giao diện các module khác.

## 3. Các quyết định đã chốt với người dùng

| Câu hỏi | Quyết định |
|---|---|
| Ai xem được? | **Công khai**, không cần đăng nhập |
| Mở bằng cách nào? | **Bấm thẻ "Lời Chúa Hôm Nay" ở trang chủ** |
| Suy niệm lấy từ đâu? | **Nguồn ngoài** (chưa chọn nguồn — xem mục 13, đề xuất chỉ hiện liên kết) |

## 4. Nguồn dữ liệu (đã kiểm chứng ngày 07/10/2026)

Repo cộng đồng `ndagnhat/gospel-data`, phục vụ qua jsDelivr. Base:
`https://cdn.jsdelivr.net/gh/ndagnhat/gospel-data@main`

### 4.1 Kinh Thánh — bản `cgkpv2011`

- "Kinh Thánh – Nhóm Phiên Dịch Các Giờ Kinh Phụng Vụ (2011)", **73 sách**, có
  dấu đầy đủ, từ vựng Công Giáo ("Thiên Chúa", "Đức Giê-su").
- `GET /bibles/cgkpv2011/index.json` — danh sách sách (`bookCode`, `name`,
  `abbreviation`, `totalChapters`, `testament`…).
- `GET /bibles/cgkpv2011/{BOOK}/{n}.json` — một chương. Ví dụ `JHN/3.json`:

```json
{
  "translation": "cgkpv2011", "bookCode": "JHN", "bookName": "Tin Mừng Gio-an",
  "abbreviation": "Ga", "chapterNumber": 3, "totalVerses": 36,
  "verses": [
    { "number": 1, "text": "Trong nhóm Pha-ri-sêu, …", "isNewParagraph": true,
      "sectionTitle": "Cuộc đối thoại với ông Ni-cô-đê-mô" },
    { "number": 2, "text": "Ông đến gặp Đức Giê-su ban đêm. …", "isNewParagraph": false }
  ]
}
```

- Header phản hồi: `access-control-allow-origin: *`,
  `cache-control: public, max-age=604800`; một chương khoảng 7,5 KB.

### 4.2 Lịch phụng vụ — `calendars/lichvn/2026`

- `GET /calendars/lichvn/{YYYY}/{MM}/{DD}.json`, mỗi ngày một file (~1,3 KB).
- Ví dụ 2026-10-07: `season: "ordinary"`, `seasonName: "Mùa Thường Niên"`,
  `week: "Tuần XXVII Thường Niên"`, `psalterWeek: 3`, `dayOfWeek: "Thứ Tư"`,
  `color: "green"`, `rank: "weekday"`, `saintOfDay: null`, `commemorations: []`,
  và mảng `readings`:

```json
[
 {"type":"first_reading","bookCode":"GAL","startChapter":2,"startVerse":1,
  "endChapter":2,"endVerse":14,"displayReference":"Gl 2, 1-2.7-14",
  "sourceCitation":"Galatians 2:1-2, 7-14","selectedVerses":[1,2,7,8,9,10,11,12,13,14]},
 {"type":"psalm","bookCode":"PSA","startChapter":1,"startVerse":2,
  "endChapter":1,"endVerse":2,"displayReference":"Tv 1, 2",
  "sourceCitation":"Psalm 117:1bc, 2"},
 {"type":"gospel","bookCode":"LUK","startChapter":11,"startVerse":1,
  "endChapter":11,"endVerse":4,"displayReference":"Lc 11, 1-4",
  "sourceCitation":"Luke 11:1-4"}
]
```

- **Chỉ có năm 2026.** Sang 2027 trang phải báo rõ "chưa có dữ liệu" (mục 8.4).

### 4.3 Suy niệm — `reflections/lichvn`

- Chỉ có **3 bài mẫu** (21–23/08/2026, `"sample": true`). **Không dùng được** cho
  các ngày khác → không làm phần suy niệm từ repo này.

### 4.4 Nguồn đã loại và lý do

| Nguồn | Lý do loại |
|---|---|
| `bible-api.com` | 404, không có tiếng Việt |
| BibleGet I/O (`/v3/`) | Chỉ có CEI2008, DRB, LUZZI, NABRE, NVBSE, VGCL, BLPD — không có tiếng Việt |
| HelloAO (`bible.helloao.org`) | Có `vie_1934`, `vie_vcb` nhưng đều **không phải bản Công Giáo**; `vie_vcb` còn bản quyền |
| `MaatheusGois/bible` | Bản Tiếng Việt là Kinh Thánh Tin Lành; chưa xác nhận giấy phép |
| Scripture.guide | Chỉ phân tích tham chiếu, không có nội dung câu |

## 5. Bản quyền và độ tin cậy nguồn (PHẢI giải quyết trước khi ra production)

- Bản CGKPV 2011 **thuộc bản quyền của Nhóm Phiên Dịch Các Giờ Kinh Phụng Vụ**.
  Repo `ndagnhat/gospel-data` là của cá nhân, **không có giấy phép/ghi nguồn
  rõ ràng**; không biết người đăng đã được phép chưa.
- **Hành động:** liên hệ xin phép Nhóm Phiên Dịch / Ủy ban Kinh Thánh HĐGMVN cho
  mục đích mục vụ phi lợi nhuận (gia đình giáo lý). Ghi lại văn bản chấp thuận.
- Giảm rủi ro kỹ thuật: **không** mirror cả bộ. Chỉ cache các chương/ngày được
  đọc thực tế, tối đa vài MB, nằm ngoài web root (mục 7.4). Trang luôn có dòng
  ghi nguồn: *"Bản dịch: Nhóm Phiên Dịch Các Giờ Kinh Phụng Vụ (2011). Dữ liệu
  lịch/văn bản qua `ndagnhat/gospel-data`."*
- Repo nguồn có thể **bị xóa hoặc đổi cấu trúc**: nguồn ngoài chỉ được truy cập
  qua **một lớp bọc duy nhất** (mục 7.1) để đổi nguồn mà không sửa chỗ khác;
  phiên bản ghim bằng commit SHA thay vì `@main` khi triển khai thật (mục 9).

## 6. Rủi ro dữ liệu đã phát hiện — cần xác minh khi làm

1. **Thánh Vịnh trong lịch có thể sai số.** Ngày 07/10/2026: `displayReference`
   là `Tv 1, 2` / `startChapter: 1` nhưng `sourceCitation` là
   `Psalm 117:1bc, 2`. Hai chỗ **mâu thuẫn** (Thánh Vịnh 1 hay 117?), và đáp ca
   thường là các nửa câu (`1bc`), không phải một đoạn liền. ⇒ **Không** tự
   cắt văn bản Thánh Vịnh từ `startChapter/startVerse`. Phương án:
   - v1: với `type: "psalm"` chỉ hiện **tham chiếu** (`displayReference`), không
     hiện văn bản; hoặc
   - kiểm tra thêm nhiều ngày; nếu sai hệ thống thì bỏ phần văn bản Thánh Vịnh.
   - Việc kiểm tra này là **bước 0** của PR 3, có test dữ liệu thật.
2. **Cắt câu theo `selectedVerses`.** Ví dụ bài đọc 1 `Gl 2,1-2.7-14` bỏ các câu
   3–6. Phải dùng `selectedVerses` nếu có; chỉ khi không có mới dùng
   `startVerse..endVerse`. Bài đọc **băng qua nhiều chương** (`startChapter` ≠
   `endChapter`) cũng phải xử lý.
3. **Ký hiệu số câu/số chương** (`a`, `b`, `c`) có thể không khớp 1–1 với các
   mảng `verses`. Đối chiếu ít nhất 20 ngày mẫu trước khi tin.
4. **Năm phụng vụ khác năm dương lịch.** Lịch chỉ có 2026. Không suy diễn cho 2027.

## 7. Thiết kế kỹ thuật

### 7.1 Lớp dịch vụ `config/loichua.php` (hàm thuần, dễ test)

Một file, một trách nhiệm: *lấy dữ liệu từ nguồn ngoài, chuẩn hóa, cache*. Mọi
nơi khác (API, thẻ, test) chỉ gọi lớp này.

| Hàm | Việc |
|---|---|
| `loichua_hom_nay(): string` | Ngày `Y-m-d` theo múi giờ `Asia/Ho_Chi_Minh` (không dùng `date()` mặc định của máy chủ) |
| `loichua_hop_le_ngay(string $d): bool` | `^\d{4}-\d{2}-\d{2}$` + `checkdate()` + nằm trong cửa sổ cho phép (7.3) |
| `loichua_lich(string $d): ?array` | Đọc lịch của ngày, kiểm cấu trúc (7.5), trả `null` nếu lỗi |
| `loichua_chuong(string $bookCode, int $ch): ?array` | Đọc một chương, `$bookCode` chỉ nhận `^[A-Z0-9]{3}$` và phải có trong `index.json` |
| `loichua_cat_cau(array $chuong, array $bai): array` | Cắt câu theo `selectedVerses` hoặc `start..end`, nhiều chương |
| `loichua_dung_bai(string $d): ?array` | Gộp: lịch + các bài đọc có văn bản + nguồn |
| `loichua_fallback(string $d): array` | Câu dự phòng đã duyệt (7.6) |

Quy tắc gọi ra ngoài:
- **Chỉ** tới host cố định `cdn.jsdelivr.net`, đường dẫn ghép từ giá trị đã
  kiểm tra; **không bao giờ** đưa chuỗi người dùng vào URL.
- `timeout` 5 giây, `verify_peer`/`verify_peer_name` bật, giới hạn kích thước
  phản hồi (ví dụ 256 KB/chương), không theo redirect sang host khác.
- Kiểm kiểu từng trường (số, chuỗi) và **độ dài tối đa**; loại thẻ HTML nếu có
  (`strip_tags`) trước khi lưu cache — phòng nguồn bị chiếm (xem S6).
- Không dùng `INSERT IGNORE`/`catch {}` nuốt lỗi (luật cứng #5): lỗi nguồn phải
  được `error_log` có tiền tố `[loichua]` và trả `null` để tầng trên chọn
  fallback; không im lặng.

### 7.2 Endpoint `public/api/loichua.php` (mới, công khai, CHỈ ĐỌC)

```
GET api/loichua.php               → bài của hôm nay
GET api/loichua.php?date=YYYY-MM-DD → bài của một ngày (trong cửa sổ cho phép)
```

Phản hồi thành công:

```json
{
  "success": true, "date": "2026-10-07", "weekday": "Thứ Tư",
  "season": "Mùa Thường Niên", "week": "Tuần XXVII Thường Niên",
  "color": "green", "saint": null,
  "readings": [
    {"type":"first_reading","label":"Bài đọc 1","ref":"Gl 2, 1-2.7-14",
     "verses":[{"n":1,"text":"…","section":null}]},
    {"type":"psalm","label":"Đáp ca","ref":"Tv …","verses":[]},
    {"type":"gospel","label":"Tin Mừng","ref":"Lc 11, 1-4","verses":[…]}
  ],
  "translation": "Nhóm Phiên Dịch Các Giờ Kinh Phụng Vụ (2011)",
  "source": "ndagnhat/gospel-data", "fallback": false
}
```

- Chỉ nhận `GET`; mọi method khác trả 405.
- `date` sai định dạng/ngoài cửa sổ → `400` với thông báo tiếng Việt; không echo
  lại input.
- Header: `Content-Type: application/json; charset=utf-8`,
  `X-Content-Type-Options: nosniff`, `Cache-Control: public, max-age=3600`
  (nội dung của một ngày không đổi trong ngày).
- **Không** ghi vào DB, **không** lưu IP (khác `bible.php` hiện tại) ⇒ loại bỏ
  vấn đề S10 cho tính năng mới.
- Rate-limit: dựa vào cache tệp (7.4) nên mỗi ngày chỉ gọi nguồn một lần; chống
  lạm dụng bằng giới hạn tần suất theo IP **trong bộ nhớ** (APCu nếu có, như
  `somoc_order`/`tracuu`), không ghi IP xuống DB. Kiểm tra cách `_bootstrap.php`
  hiện làm rate-limit và dùng lại, không viết cơ chế mới.

### 7.3 Cửa sổ ngày cho phép

Chỉ cho `date` trong `[hôm nay − 30 ngày, hôm nay + 7 ngày]` **và** năm có dữ
liệu lịch. Lý do: ngăn kẻ xấu quét hàng nghìn ngày để làm đầy cache/gọi nguồn.
Ngày hôm nay luôn được phép.

### 7.4 Cache — ngoài web root (luật cứng #9, S1)

- Thư mục `storage/loichua/` (ngoài `public/`), cấu hình ở `config/config.php`:
  `'loichua' => ['cache_path' => __DIR__.'/../storage/loichua', 'ttl_days' => 400]`
  (theo mẫu `'library' => ['storage_path' => …]`).
- Khóa cache = **ngày đã kiểm tra hợp lệ** (`2026-10-07.json`) và `chuong_{BOOK}_{n}.json`
  — tên tệp chỉ được ghép từ giá trị đã qua `loichua_hop_le_ngay`/whitelist
  sách, **không** từ input thô.
- Ghi nguyên tử (ghi tệp tạm + `rename`), quyền `0640`, tạo thư mục `0750`.
- Nội dung lịch/bài đọc **không chứa dữ liệu cá nhân** (đã kiểm), nhưng vẫn để
  ngoài web root cho nhất quán và để không bị tải trực tiếp.
- Thêm vào `config/cron_cleanup.php` bước xóa tệp cache cũ hơn `ttl_days`.
- Thêm `storage/loichua/` vào lịch sao lưu? **Không cần** (tái tạo được); chỉ
  ghi chú để không ai lo.

### 7.5 Kiểm tra cấu trúc dữ liệu nguồn (validation)

Trước khi dùng/ghi cache, bắt buộc:
- Lịch: có `date` khớp tham số; `readings` là mảng ≤ 8 phần tử; mỗi phần tử có
  `type ∈ {first_reading, second_reading, psalm, gospel, …}` (chỉ nhận danh
  sách đã biết, phần còn lại bỏ qua); `bookCode` khớp whitelist; số chương/câu
  là số nguyên dương hợp lý.
- Chương: `verses` là mảng, mỗi câu có `number` (int) và `text` (chuỗi ≤ 2000 ký
  tự). Số câu khớp `totalVerses` (ghi cảnh báo nếu lệch, không chặn).
- Mọi chuỗi qua `strip_tags` + `trim`; chuỗi rỗng bị bỏ.

### 7.6 Danh sách câu dự phòng (fallback)

- Thay `FALLBACK_VERSES` bằng **danh sách do người dùng duyệt**: ~30–60 câu
  Tin Mừng/Thánh Vịnh/Khôn Ngoan, **có dấu**, tham chiếu kiểu Việt (`Ga 3,16`).
- Nội dung câu lấy từ nguồn một lần bằng script
  `config/tools/loichua_sinh_fallback.php` (chạy tay, đưa kết quả vào
  `config/loichua_fallback.php` để người duyệt đọc lại từng câu). Không tự gõ
  câu Kinh Thánh từ trí nhớ.
- Chọn câu theo **ngày** (`dayOfYear % N`) thay vì `array_rand`, để cả cộng
  đoàn cùng thấy một câu trong ngày.

### 7.7 Giao diện

**Trang `public/loichua.php`** (công khai; theo mẫu `bxh.php`/`tracuu.php`):
- Header `X-Robots-Tag: noindex, nofollow` (theo mẫu `bxh.php`), `Referrer-Policy:
  no-referrer`. (Câu hỏi mở: có cho Google lập chỉ mục không? mặc định noindex.)
- Thanh ngày: ‹ Hôm qua · **Thứ Tư 07/10/2026** · Ngày mai ›, nút "Hôm nay".
  Điều hướng chỉ đổi tham số `date` trong cửa sổ cho phép.
- Khối thông tin phụng vụ: tên ngày, tuần, mùa, chip **màu áo lễ**
  (xanh/trắng/đỏ/tím/hồng — dùng màu **kèm chữ**, không chỉ dựa vào màu: a11y).
- Ba thẻ bài đọc (Bài đọc 1 · Đáp ca · Tin Mừng); mỗi thẻ có tham chiếu
  (`Lc 11, 1-4`), tiêu đề đoạn (`sectionTitle`) và đoạn văn (`isNewParagraph`).
- Nút **tăng/giảm cỡ chữ** (trẻ em/người lớn tuổi đều đọc), chế độ tối theo hệ
  thống (đã có `Chế độ tối` ở app).
- **Suy niệm:** một thẻ có nút liên kết "Đọc suy niệm hôm nay" mở **tab mới**
  (`rel="noopener noreferrer"`), tới trang nguồn đã được duyệt (mục 13). Không
  nhúng iframe, không sao chép nội dung.
- Cuối trang: ghi nguồn + bản dịch (mục 5).
- Trạng thái: đang tải (khung xương), lỗi nguồn (hiện fallback + thông báo
  "đang dùng câu dự phòng"), ngoài năm có dữ liệu ("Chưa có lịch phụng vụ cho
  ngày này").
- Mọi nội dung từ API gán bằng `textContent` / `x-text`, **tuyệt đối không**
  `innerHTML` / `x-html` (S6).
- Mobile trước (viewport nhỏ, nút ≥ 44 px, phản hồi bằng bàn tay).

**Thẻ trang chủ `views/layout_bible_card.php`:**
- Hiện Tin Mừng của hôm nay (tối đa vài câu đầu, cắt tại ranh giới câu) + tên
  ngày phụng vụ.
- Bấm vào thẻ → mở `loichua.php` (thẻ trở thành `<a>` hoặc có `role="link"`,
  focus được bằng bàn phím).
- Bỏ nút "Bốc thăm" ngẫu nhiên cũ (hoặc giữ nếu người dùng muốn — xem mục 13).
- Gọi `api/loichua.php`; lỗi thì dùng fallback; vẫn dùng `x-text`.

**Trang landing `views/layout_landing.php`:** đổi từ `api/bible.php?action=random`
sang `api/loichua.php` và sửa `innerHTML` → `textContent` (PR riêng, S6).

### 7.8 `api/bible.php` cũ

- Sau khi thẻ trang chủ + landing chuyển sang `api/loichua.php`, `random` không
  còn ai gọi. Theo luật #3: **`grep` mọi nơi gọi** (`bible.php`, `bibleCard`,
  `getVerse`, `dailyBibleVerse`) trước khi xóa; ghi rõ trong mô tả PR.
- Giữ `action=list` / `action=stats` cho admin cho tới khi quyết định bỏ bảng
  `bible_daily` (PR dọn dẹp, mục 11, PR 5). Nếu bỏ bảng ⇒ cần migration mới
  `config/migrations/008_*.sql` + cập nhật `install.php` (theo CLAUDE.md).

## 8. Bảo mật & riêng tư (trang công khai ⇒ mọi input không tin cậy)

Theo `docs/security/SECURITY_AUDIT.md` và `docs/process/PRIVACY_AUDIT.md`.

| # | Rủi ro | Biện pháp |
|---|---|---|
| 8.1 | SSRF / chèn đường dẫn qua `date`, `book` | Chỉ nhận regex + `checkdate`; whitelist `bookCode`; host cố định; không nhận URL từ người dùng |
| 8.2 | XSS qua dữ liệu nguồn bị chiếm (S6) | `strip_tags` ở máy chủ + `textContent` ở trình duyệt; CSP không nới thêm; không `innerHTML` |
| 8.3 | Lạm dụng làm đầy cache / DoS nguồn | Cửa sổ ngày (7.3), cache theo ngày, giới hạn kích thước, rate-limit theo IP trong bộ nhớ |
| 8.4 | Hiển thị nhầm bài khi hết dữ liệu | Ngày ngoài năm có dữ liệu ⇒ báo rõ; không suy diễn bài năm khác |
| 8.5 | Lộ IP khách | Không ghi IP vào DB cho tính năng mới; log lỗi không chứa IP |
| 8.6 | Cache ra `public/` | Cấm; dùng `storage/loichua/` ngoài web root, có test kiểm đường dẫn |
| 8.7 | Phụ thuộc bên thứ ba biến mất / đổi cấu trúc | Lớp bọc duy nhất, validation (7.5), fallback (7.6), ghim SHA |
| 8.8 | Header | `nosniff`, `Referrer-Policy: no-referrer`, `X-Robots-Tag` (xem 7.7), JSON `utf-8` |
| 8.9 | Link suy niệm | Chỉ liên kết tới whitelist host đã duyệt, `rel="noopener noreferrer"` |

Đây là **trang công khai mới**: theo `FEATURE_WORKFLOW.md` mục "đụng endpoint
công khai", dùng checklist bảo mật và có bước review của agent `tntt-security`.

## 9. Triển khai & vận hành

- Ghim nguồn bằng **commit SHA** (ví dụ `…/gospel-data@<sha>`) thay vì `@main`
  trong config; nâng SHA là một thay đổi có chủ đích.
- Cấu hình ở `config/config.php` (mặc định) có thể bị `config.local.php` ghi đè:
  `'loichua' => ['base_url'=>…, 'pin'=>…, 'cache_path'=>…, 'timeout'=>5]`.
  Không có secret nào (nguồn công khai) ⇒ không phải lo luật #7.
- Chạy migration (nếu có) trước khi triển khai; **không** chạy `install.php` /
  `seed_demo.php` trên DB thật (luật #8).
- Sau triển khai: mở trang thật (điện thoại + máy tính), kiểm 3 ngày liên tiếp
  và một ngày lễ trọng (có `saintOfDay`), kiểm `storage/loichua/` có tệp cache.
- Theo dõi: `error_log` có tiền tố `[loichua]`; nếu nguồn lỗi liên tục, trang vẫn
  chạy bằng fallback.
- **Mốc cuối năm:** nhắc sẵn trong tài liệu — trước 31/12/2026 phải có dữ liệu
  lịch 2027 hoặc chuyển sang nguồn khác.

## 10. Kiểm thử (viết test đỏ trước — TDD)

Đơn vị (`tests/unit/LoiChuaTest.php`, theo mẫu `SomocTest.php`):
- `loichua_hop_le_ngay`: `2026-10-07` ✅; `2026-02-30`, `07-10-2026`,
  `2026-10-07; DROP`, `../../etc/passwd`, chuỗi rỗng, năm 1999/3000 ❌; ngày
  ngoài cửa sổ ❌.
- `loichua_cat_cau`: `selectedVerses` bỏ câu 3–6 (ví dụ `Gl 2,1-2.7-14`); bài
  nhiều chương; `startVerse > endVerse`; chương rỗng.
- Validation: thiếu trường, sai kiểu, câu chứa `<script>`/`<img onerror>`
  (phải bị loại thẻ), chuỗi quá dài, `readings` quá lớn.
- Cache: khóa tên tệp chỉ từ giá trị hợp lệ; ghi nguyên tử; hết hạn; đường dẫn
  **nằm ngoài `public/`** (khẳng định bằng `realpath`).
- Fallback: chọn theo ngày, có dấu, tham chiếu tiếng Việt, cùng ngày ⇒ cùng câu.
- Múi giờ: `loichua_hom_nay()` đúng Asia/Ho_Chi_Minh khi máy chủ đặt UTC lúc
  23:30 UTC (đã sang ngày mới ở Việt Nam).

API (mẫu `QrScanApiTest`/`P1ApiHarness`, **mock** lớp mạng để không gọi ra
ngoài khi chạy CI):
- `GET` hôm nay ⇒ 200 đúng cấu trúc; `date` sai ⇒ 400; method khác ⇒ 405.
- Nguồn trả 404/timeout/JSON sai ⇒ 200 với `fallback: true`, không lộ chi tiết lỗi.
- Không có truy vấn ghi DB / không có IP trong DB.
- (Nếu viết lại thẻ) không còn `innerHTML` với dữ liệu API — test tĩnh bằng `grep`.

Dữ liệu thật (chạy tay trong PR 3, dán kết quả vào PR theo luật "Xong"):
- Duyệt ≥ 20 ngày mẫu rải rác 2026: số bài đọc, `selectedVerses`, bài nhiều
  chương, Thánh Vịnh (xác nhận hay loại — mục 6.1).

Giao diện:
- `node tests/e2e/smoke.js` + thử bằng trình duyệt thật (điện thoại + desktop):
  mở thẻ trang chủ ⇒ sang trang; điều hướng ngày; lỗi nguồn ⇒ fallback;
  chế độ tối; cỡ chữ; bàn phím (Tab/Enter); trình đọc màn hình.
- `npx --yes eslint@9.39.5 public/assets/js/ public/sw.js` (nếu có JS mới).
- Đăng ký module JS mới **chỉ** ở `public/assets/asset_manifest.php` (luật #11);
  `AssetManifestTest` phải xanh.

## 11. Chia PR (một thay đổi = một mục đích)

| PR | Mục đích | Nội dung chính |
|---|---|---|
| **1** | Hotfix: khôi phục thẻ Lời Chúa đang hỏng | Thay nguồn `bible.php` sang nguồn mới; đổi `FALLBACK_VERSES` thành tiếng Việt có dấu, đã duyệt. Nhỏ, chạy nhanh để thẻ hiện tại không chạy bằng fallback không dấu |
| **2** | Vá S6 | `layout_landing.php`: `innerHTML` → `textContent`, xóa dữ liệu `localStorage` cũ có thể chứa HTML |
| **3** | Lớp dịch vụ + endpoint | `config/loichua.php`, `public/api/loichua.php`, cache `storage/loichua/`, config, cron dọn, test + kiểm 20 ngày dữ liệu thật |
| **4** | Trang công khai + thẻ trang chủ | `public/loichua.php`, sửa `layout_bible_card.php`, ghi nguồn, liên kết suy niệm, a11y, smoke test |
| **5** | Dọn dẹp (tùy chọn) | Bỏ `action=random`, bảng `bible_daily` & `ensure_bible_table` (S10) bằng migration `008_*` — **sau khi `grep` mọi nơi gọi** |

PR 1 và 2 độc lập, có thể làm song song. PR 3 trước PR 4. PR 5 sau cùng.

## 12. Tiêu chí hoàn thành ("Xong" = có bằng chứng)

- [ ] Có văn bản/ghi nhận **được phép** dùng bản dịch (hoặc quyết định chấp nhận rủi ro có ghi lại).
- [ ] `php phpunit.phar --testsuite "TNTT Unit Tests"` xanh; dán kết quả.
- [ ] ESLint xanh (nếu có JS mới); `AssetManifestTest` xanh.
- [ ] Đã mở app thật, mở trang qua thẻ trang chủ, chụp màn hình điện thoại +
      desktop (sáng/tối), dán vào PR.
- [ ] Đã kiểm ≥ 20 ngày dữ liệu thật, ghi kết quả (đặc biệt Thánh Vịnh).
- [ ] Không còn `innerHTML`/`x-html` với dữ liệu từ API (grep).
- [ ] Không có cache trong `public/`; `storage/loichua/` ngoài web root.
- [ ] Không có secret mới; không đưa định danh model vào commit/code/PR.
- [ ] Khi bỏ code cũ: ghi rõ đã `grep` những nơi nào.
- [ ] Review `tntt-reviewer` + `tntt-security` (trang công khai mới).

## 13. Câu hỏi còn mở (cần người dùng quyết)

1. **Nguồn suy niệm cụ thể** là trang nào? Đề xuất: **chỉ hiện liên kết** sang
   một trang uy tín (ứng viên: Vatican News tiếng Việt; Tổng Giáo Phận Huế chỉ
   có suy niệm Chúa nhật, không đủ cho ngày thường). Cần chọn trang và xác nhận
   nó có URL ổn định theo ngày.
2. **Xin phép bản quyền** ai phụ trách, khi nào?
3. Có giữ nút **"Bốc thăm"** ngẫu nhiên trên thẻ trang chủ không (từ danh sách
   câu đã duyệt), hay bỏ hẳn và chỉ còn "Tin Mừng hôm nay"?
4. Danh sách **câu dự phòng**: người dùng đưa danh sách tham chiếu hay để đề
   xuất ~30 câu rồi duyệt?
5. Trang có cho **Google lập chỉ mục** không (mặc định `noindex`)?
6. Hiện **văn bản Thánh Vịnh** hay chỉ tham chiếu (phụ thuộc kết quả kiểm 6.1)?
7. Sau 2026: ai cập nhật lịch 2027, hoặc chuyển nguồn nào?

## 14. Tài liệu liên quan

- `CLAUDE.md` — luật cứng (đặc biệt #3, #5, #7, #8, #9, #10, #11).
- `docs/process/FEATURE_WORKFLOW.md` — quy trình một tính năng.
- `docs/process/TESTING.md` — cách chạy test.
- `docs/security/SECURITY_AUDIT.md` — S1 (cache), S6 (XSS Kinh Thánh), S10 (`bible_daily`).
- `docs/process/PRIVACY_AUDIT.md` — IP là dữ liệu cá nhân.
- Nguồn dữ liệu: <https://github.com/ndagnhat/gospel-data>
