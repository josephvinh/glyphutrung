# Nâng cấp Nhật ký Hoạt động (Activity Log)

> **Ngày:** 2026-10-14
> **Tác giả:** Claude (brainstorming → spec)
> **Trạng thái:** BẢN THẢO — chờ người dùng duyệt
> **Phạm vi:** Toàn bộ hệ thống nhật ký (`activity_logs`) — ghi log, đọc log, xuất log

## 1. Vấn đề & mục tiêu

### 1.1. Trạng thái hiện tại

Đã có hệ thống nhật ký `activity_logs` (7 cột: `id, logged_at, actor_id, actor_name, action, module, what, detail`), hàm `log_action()` ở `_bootstrap.php` được **66 chỗ** gọi rải đều trong **17 file** API. UI tab "Nhật ký" ở `module_settings.php` có tìm kiếm client và gom nhóm theo ngày. Có endpoint `logs.php` đọc có phân trang, có nút "Xóa nhật ký" (`settings.php:clearLogs`).

### 1.2. Điều còn thiếu

| # | Thiếu | Hệ quả |
|---|---|---|
| 1 | Không ghi **diff trước/sau** cho thao tác sửa/xóa | Khi sửa điểm thiếu nhi từ 8 → 5, nhật ký chỉ ghi "Sửa điểm X" — không biết sửa cái gì. Audit/báo cáo sự cố gần như vô dụng. |
| 2 | Không ghi **IP, thiết bị** | Không truy vết được ai từ đâu khi bị nghi ngờ. |
| 3 | Không ghi **đối tượng bị tác động** (`target_type`, `target_id`) | Khi xóa "Nguyễn Văn A", biết ai xóa nhưng phải lần theo `what`. |
| 4 | API `logs.php` **không có filter** (actor / module / khoảng ngày / action) | Vài trăm dòng — lọc tay rất khó. |
| 5 | **Không có export** (CSV/XLSX) | Phải copy-paste hoặc viết SQL tay. Báo cáo ban điều hành muốn có file đính kèm. |
| 6 | Quyền xem chỉ giới hạn admin | BĐH / GLV chủ nhiệm không có cách nào xem việc của lớp/khối mình. |
| 7 | Client chỉ giữ 300 dòng (`LOG_LIMIT`) | Log cũ hơn bị mất khỏi UI (dù DB vẫn có). |
| 8 | Sửa tới lui (giành lại 1.6): dữ liệu cá nhân thiếu nhi nằm trong `detail` không bị lọc khi export | Rủi ro S2 / quyền riêng tư. |

### 1.3. Mục tiêu (thành công khi)

1. Mọi thao tác **sửa** ghi đầy đủ `before/after` cho các trường thay đổi; thao tác **xóa** ghi snapshot trước khi xóa.
2. Mọi log ghi kèm IP và user-agent.
3. API `logs.php` chấp nhận filter `actor, module, action, from, to` — UI hiển thị đủ các filter.
4. Có endpoint export **XLSX (tự viết)** và CSV — tôn trọng filter đang áp dụng.
5. Admin xem tất cả; GLV chỉ xem log do mình tạo **và** log liên quan tới lớp mình phụ trách.
6. Export tự động che thông tin nhạy cảm (SĐT phụ huynh, email) theo cấu hình — mặc định che một phần.
7. Tất cả thay đổi có migration + test phân quyền + test export.
8. **KHÔNG thêm composer/PhpSpreadsheet** — tự viết XLSX (zip+xml).

## 2. Phạm vi & phiên ranh giới

### 2.1. Trong phạm vi

- Đổi schema bảng `activity_logs` (migration mới, an toàn với log cũ).
- Đổi chữ ký `log_action()` để nhận `before/after` JSON (optional) — không phá chữ ký cũ.
- **Tất cả** thao tác sửa/xóa trong 17 file API (66 chỗ gọi) đều được rà và bổ sung before/after cho phần sửa/xóa.
- API mới `logs_export.php` (CSV + XLSX).
- UI: thêm filter (actor, module, khoảng ngày) + nút "Xuất".
- Quyền: mở rộng `can_view_logs()` để BĐH xem được; thêm `can_view_log_scope()` cho GLV.

### 2.2. NGOÀI phạm vi

- **Không** thêm composer/PhpSpreadsheet — tự viết XLSX.
- **Không** thay đổi các API công khai (`somoc_order.php`, `tracuu.php`, `push.php`, `bible.php`).
- **Không** lưu lại log cũ (giữ nguyên, chỉ thêm cột NULL).
- **Không** đụng đến các bảng khác ngoài `activity_logs` + `members` (FK actor_id có sẵn).
- **Không** ghi log cho endpoint GET (chỉ ghi cho hành động ghi — POST/PUT/DELETE).

## 3. Kiến trúc & thiết kế

### 3.1. Schema mới (migration `010_activity_log_diff.sql`)

```sql
-- Thêm cột mới. Mọi cột đều NULL mặc định để giữ nguyên log cũ.
ALTER TABLE activity_logs
    ADD COLUMN ip           VARCHAR(45)  NULL AFTER detail,         -- IPv4/IPv6
    ADD COLUMN user_agent   VARCHAR(255) NULL AFTER ip,
    ADD COLUMN target_type  VARCHAR(32)  NULL AFTER user_agent,    -- 'student','class','member',...
    ADD COLUMN target_id    BIGINT       NULL AFTER target_type,
    ADD COLUMN target_label VARCHAR(255) NULL AFTER target_id,     -- "Nguyễn Văn A · Chiên 2"
    ADD COLUMN diff_json     JSON NULL AFTER target_label;            -- {"name":{"old":"A","new":"B"}}

ALTER TABLE activity_logs
    ADD INDEX idx_log_module_time (module, logged_at),
    ADD INDEX idx_log_action_time (action, logged_at);
```

**Quyết định:**
- Dùng `JSON` (MySQL 5.7+) — không phải TEXT để có thể query sau (`JSON_EXTRACT`).
- `target_label` lưu **bản người đọc** (snapshot lúc ghi), tránh phải JOIN khi hiển thị.
- `ip` VARCHAR(45) đủ cho IPv6 đầy đủ.
- KHÔNG đổi các cột cũ — tương thích ngược với 66 chỗ gọi.

### 3.2. Hàm `log_action()` mới (mở rộng chữ ký, tương thích ngược)

```php
// public/api/_bootstrap.php

function log_action(
    string $action,
    string $module,
    string $what,
    string $detail = '',
    array  $opts = []     // ['before'=>array, 'after'=>array, 'target_type'=>string, 'target_id'=>int, 'target_label'=>string]
): void {
    $me = current_member();

    // Chỉ ghi diff cho sửa/xóa — tạo/duyệt không có before/after
    $diff = null;
    if (isset($opts['before']) || isset($opts['after'])) {
        $diff = json_encode(
            ['before' => $opts['before'] ?? null, 'after' => $opts['after'] ?? null],
            JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    db_run('INSERT INTO activity_logs
        (actor_id, actor_name, action, module, what, detail,
         ip, user_agent, target_type, target_id, target_label, diff_json)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            $me['id'] ?? null, $me['full_name'] ?? 'Hệ thống',
            $action, $module, $what, $detail,
            $ip, $ua,
            $opts['target_type'] ?? null,
            $opts['target_id'] ?? null,
            $opts['target_label'] ?? null,
            $diff
        ]);
}
```

**Tương thích ngược:** 66 chỗ gọi hiện tại truyền 3-4 tham số — không cần đổi. Chỉ thêm tham số thứ 5 khi muốn ghi diff.

### 3.3. API đọc — mở rộng filter (`public/api/logs.php`)

**Query string hỗ trợ:**
- `page`, `limit` (đã có)
- `actor` — filter theo `actor_name` (LIKE) hoặc `actor_id`
- `module` — chính xác một module (vd `students`)
- `action` — chính xác một hành động (vd `sua`)
- `from`, `to` — khoảng ngày (`YYYY-MM-DD`)

**Phân quyền (mới):**
- Admin: thấy tất cả.
- BĐH (`role_code IN ('admin','bdh...')`): thấy tất cả.
- GLV (`giao_ly_vien`): **chỉ thấy log do mình tạo** HOẶC log có `target_type='student'` và `target_id` thuộc lớp mình phụ trách (dùng hàm `can_access_class()` đã có).
- Khác: 403.

**Phản hồi — thêm trường:**
```json
{
  "id": 123, "at": "2026-10-14 09:30", "ts": 1734187800000,
  "actor": "Nguyễn Văn A", "action": "sua", "module": "students",
  "what": "Sửa hồ sơ", "detail": "...",
  "ip": "1.2.3.4", "user_agent": "Mozilla/5.0...",
  "target_type": "student", "target_id": 456, "target_label": "Trần Thị B",
  "diff": { "before": {...}, "after": {...} }    // null với log cũ
}
```

### 3.4. Endpoint export mới (`public/api/logs_export.php`)

```
GET api/logs_export.php?format=xlsx|csv&actor=...&module=...&action=...&from=...&to=...
```

**Phân quyền:** giống `logs.php` (admin / BĐH / GLV theo phạm vi).

**Áp dụng mask **BẮT BUỘC** khi export:**
- Trường `actor_name` để nguyên (đã là nội bộ).
- Nếu trong `before/after` có key `parent_phone` hoặc `phone` → thay bằng `0901***567` (che giữa).
- `detail` không che (admin cần biết ngữ cảnh).
- Áp dụng cùng filter với UI — nếu UI lọc theo module `students` thì export cũng chỉ lấy `students`.

**Tự viết XLSX** (xem mục 3.5) — không composer.

**Tên file:** `nhat-ky-thao-tac_<from>_<to>_<timestamp>.xlsx`

**Test:** `tests/unit/LogsExportTest.php` — kiểm tra từng vai, mask SĐT, filter.

### 3.5. Tự viết XLSX — `src/xlsx_export.php`

Đặt một helper thuần PHP, không phụ thuộc:

```php
// Xuất một mảng 2 chiều ra file .xlsx (zip+xml đúng chuẩn OOXML).
// Hỗ trợ: tiêu đề in đậm, định dạng ngày dd/mm/yyyy, xuất UTF-8.
// Giới hạn: 1 sheet, không có chart/ảnh. ĐỦ cho nhật ký.
function xlsx_export(string $path, array $headers, array $rows, array $opts = []): void;
```

**Triển khai:** tạo thư mục tạm, ghi `[Content_Types].xml`, `_rels/.rels`, `xl/workbook.xml`, `xl/styles.xml`, `xl/sharedStrings.xml`, `xl/worksheets/sheet1.xml`, nén ZIP không nén (`ZipArchive::OVERWRITE | ZipArchive::FL_NONE` để XML đọc được). Đặt vào `src/xlsx_export.php` (đã có `src/` cho helpers — kiểm tra trước).

### 3.6. UI — `views/module_settings.php` + `core.js`

**Thêm 3 filter ô trên tab Nhật ký:**
- Dropdown "Người thực hiện" — lấy từ API (top 20 người có log nhiều nhất, hoặc distinct actor_name).
- Dropdown "Module" — sinh từ `Object.keys(groupByModule)`.
- Hai ô ngày "Từ ngày" / "Đến ngày".

**Nút "Xuất"** — mở menu 2 lựa chọn: CSV / XLSX. Click → gọi `/api/logs_export.php?format=...&<cùng filter>`.

**Bấm vào 1 dòng có diff** → mở panel/modal hiển thị bảng trước/sau (chỉ các trường thay đổi).

**Giới hạn LOG_LIMIT=300** ở client vẫn giữ — server vẫn trả đủ (filter sẽ giảm số dòng).

### 3.7. Phân quyền — `public/api/_common.php`

```php
function can_view_logs(?array $me): bool {
    if (!$me) return false;
    return in_array($me['role_code'] ?? '', ['admin','bdh'], true);
}

function log_scope_where(?array $me): string {
    // Trả về mệnh đề WHERE để giới hạn log theo phạm vi.
    if (in_array($me['role_code'] ?? '', ['admin','bdh'], true)) return '1=1';

    // GLV: thấy log do mình tạo HOẶC log tác động lên học sinh thuộc lớp
    // mình CHỦ NHIỆM trong năm học hiện hành.
    $meId = (int)$me['id'];
    $year = current_year();
    $yearId = (int)($year['id'] ?? 0);
    return "(
        actor_id = $meId
        OR (target_type = 'student' AND target_id IN (
            SELECT s.id FROM students s
            JOIN classes c ON c.id = s.class_id
            WHERE c.homeroom_teacher_id = $meId
              AND c.year_id = $yearId
        ))
    )";
}
```

**Lưu ý:** GLV **được** thấy log liên quan lớp mình (Phụ lục B, B1).

**Bảng quyết định:**

| Vai trò | Xem tất cả | Xem của mình + lớp mình | 403 |
|---|---|---|---|
| admin | ✓ | — | — |
| BĐH (`bdh`) | ✓ | — | — |
| trưởng khối / GLV chủ nhiệm / GLV | — | ✓ (do mình tạo + log về HS lớp mình) | — |
| dự bị / thủ thư | — | ✓ (do mình tạo) | — |
| Chưa đăng nhập | — | — | ✓ |

## 4. Phân kỳ (nhiều PR, đúng luật "1 PR 1 mục đích")

| Phase | Nội dung | PR | Ước lượng |
|---|---|---|---|
| **0** | Migration `010_activity_log_diff.sql` (chỉ ALTER, chưa dùng) + cập nhật `install.php` + test "chạy migration không lỗi" | PR1 | ~50 dòng |
| **1** | Đổi `log_action()` hỗ trợ `before/after` + IP + UA + target_* (chữ ký mở rộng). Tất cả 66 chỗ gọi hiện tại vẫn hoạt động (không bắt buộc đổi). | PR2 | ~80 dòng |
| **2** | API `logs.php` — thêm filter `actor/module/action/from/to` + áp `log_scope_where()` (B1). UI thêm 3 ô filter + layout mobile-first (mục 9). | PR3 | ~200 dòng |
| **3** | Helper `src/xlsx_export.php` (tự viết zip+xml) + test "tạo file mở được". | PR4 | ~250 dòng |
| **4** | API `logs_export.php` + áp mask SĐT + ghi `tao logs_export` (B2) + test phân quyền export. | PR5 | ~220 dòng |
| **5** | API mở rộng `clearLogs` thêm `from/to` (B3) + UI: nút "Xuất" (CSV + XLSX) + modal xem diff khi bấm dòng. | PR6 | ~320 dòng |
| **6** | **Bổ sung diff** cho ~30 endpoint sửa/xóa: students, scores, attendance, gifts, org, programs, library, leave, reports, settings, staff, years. Mỗi nhóm 1-2 PR phụ. | PR7..PR12 | ~600 dòng/đợt |

**Quy ước mỗi PR:**
- Một PR = một phase (hoặc một nhóm nhỏ trong phase 6).
- Mỗi PR chạy được test + smoke trước khi mở.
- Phase 6 là bulk thay đổi — có thể tách: **6a students**, **6b scores/attendance**, **6c org/programs/library**, **6d còn lại**.

## 5. Test

### 5.1. Unit (PHPUnit)

| Test | File |
|---|---|
| Migration chạy được, cột mới xuất hiện | `tests/unit/ActivityLogMigrationTest.php` |
| `log_action()` cũ vẫn chạy (không truyền opts) | `tests/unit/LogActionBackwardsCompatTest.php` |
| `log_action()` mới ghi đúng JSON, IP, target | `tests/unit/LogActionDiffTest.php` |
| `logs.php` filter theo actor/module/from/to đúng | `tests/unit/LogsApiFilterTest.php` |
| `logs.php` GLV chỉ thấy log của mình | `tests/unit/LogsScopeGlvTest.php` |
| `logs_export.php` admin xuất XLSX mở được | `tests/unit/LogsExportXlsxTest.php` |
| `logs_export.php` GLV xuất chỉ log của mình | `tests/unit/LogsExportScopeTest.php` |
| `logs_export.php` mask SĐT phụ huynh | `tests/unit/LogsExportMaskTest.php` |
| Helper `xlsx_export` tạo file đúng chuẩn OOXML | `tests/unit/XlsxExportTest.php` |

### 5.2. Smoke (Playwright/curl)

- Mở trang Nhật ký → chọn module `students` → bấm "Xuất XLSX" → file tải về, mở bằng LibreOffice thấy đúng.
- Đăng nhập GLV → trang Nhật ký → chỉ thấy dòng của mình (dòng admin không hiển thị).
- Bấm 1 dòng có diff → modal hiển thị bảng trước/sau.

## 6. Rủi ro & biện pháp

| # | Rủi ro | Biện pháp |
|---|---|---|
| R1 | Đổi chữ ký `log_action()` vô tình phá 66 chỗ gọi cũ | Tham số mới là **optional với giá trị mặc định**; test tương thích ngược PR2. |
| R2 | Xuất XLSX tự viết bị Excel từ chối | Test bằng LibreOffice + script python `openpyxl` đọc lại; nếu Excel từ chối thì fallback CSV. |
| R3 | Log cũ hàng trăm nghìn dòng — query chậm | Index `(module,logged)` + `(action,logged)`. Test với DB có ~10k log. |
| R4 | GLV có thể xem log về lớp mình không? | Phase 1 chỉ "thấy log của mình" (đơn giản). Phase sau (nếu cần) JOIN students. |
| R5 | Lộ SĐT phụ huynh qua export | Mask trong export (mục 3.4) — bắt buộc, không tùy chọn. |
| R6 | Không thêm composer nhưng helper `xlsx_export` phình file | Dùng `ZipArchive::FL_NONE` cho XML; ~5KB code. |

## 7. Migrations & thứ tự triển khai

1. Tạo `config/migrations/010_activity_log_diff.sql` (PR1).
2. Cập nhật `config/install.php` để đọc file mới (PR1).
3. Đẩy phase 1-5 (PR2-PR6) từng cái.
4. Phase 6 (PR7+): bổ sung diff từng nhóm endpoint.

Trên host: theo `docs/process/DEPLOY.md` — backup DB trước, chạy migration, kiểm lại, đưa code.

## 8. Mở rộng có thể (KHÔNG nằm trong đợt này)

- Lọc log theo `target_type='student' AND target_id` (xem lịch sử sửa 1 em).
- Thông báo realtime khi có thao tác nhạy cảm (vd xóa học sinh).
- Giữ log mãi (archival) — hiện có `cron_cleanup.php` xóa sau bao lâu; kiểm trước.
- Bảng dashboard cho admin: top 10 hành động, biểu đồ hoạt động theo tuần.

## 9. Layout mobile-first (áp dụng cho mọi thay đổi UI)

Tuân thủ quy ước dự án (xem `views/module_settings.php:1-30`): **mobile trước, desktop là ngoại lệ có prefix** `sm:` / `md:`. Dùng các utility class có sẵn (`rounded-field`, `rounded-card`, `text-micro`, `tap-safe`, `hide-scrollbar`, `bleed-x`) — **không tự tạo class mới** nếu có thể dùng class có sẵn.

### 9.1. Nguyên tắc cứng

| Nguyên tắc | Áp dụng |
|---|---|
| **Mặc định = mobile** | Mọi class không prefix là cho màn ≤ 640px. |
| **`sm:` trở đi mới tăng cấp** | `sm:flex-1`, `sm:px-6` (giống code cũ). |
| **Vùng chạm tối thiểu 44×44px** | Class `tap-safe` đã có — dùng cho mọi nút/ô input. |
| **Padding ngang mobile `px-4`** | Khung chính; desktop nới `sm:px-6`. |
| **Không để nội dung chạm mép** | `bleed-x` cho hàng chip/fillter tràn ngang có thể cuộn. |
| **Bảng/CSV chỉ xem trên desktop** | Trên mobile hiển thị dạng thẻ dọc (1 dòng = 1 thẻ). |
| **Modal/dialog full-screen trên mobile** | Dùng `fixed inset-0 sm:inset-auto sm:max-w-... sm:rounded-2xl`. |
| **Icon + chữ, không icon đơn** | Nút "Xuất" phải có cả icon lẫn chữ "Xuất"; thu gọn thành icon ở `sm:hidden` nếu cần. |
| **Giữ chữ tiếng Việt hiển thị 1 dòng nếu có thể** | `truncate` + `min-w-0` cho khối flex. |
| **Ảnh/QR gì được vào hàng thì co lại** | Không dùng layout 3 cột trên mobile — chỉ chuyển cột `sm:grid-cols-2`. |

### 9.2. Áp dụng cho từng mảng UI

**a) Thanh filter (PR3)** — 3 ô mới (Người / Module / Từ ngày / Đến ngày):

```
Mobile (mặc định):
  ┌─────────────────────────────────┐
  │ [🔍 Tìm...          ]          │  ← đã có
  └─────────────────────────────────┘
  ┌─────────────────────────────────┐
  │ [Người: tất cả       ▼]         │
  │ [Module: tất cả      ▼]         │
  │ [Từ ngày: __/__/____ ] [Đến: ] │  ← dọc 1 cột
  └─────────────────────────────────┘
  ┌─────────────────────────────────┐
  │  [Tất cả][Tạo][Sửa]…           │  ← đã có, dùng overflow-x-auto
  └─────────────────────────────────┘

Desktop (sm: ≥ 640px):
  ┌──────────────────────────────────────────────┐
  │ [🔍 Tìm...]  [Người▼][Module▼][Từ][Đến]      │  ← 1 hàng, sm:flex-row sm:gap-2
  └──────────────────────────────────────────────┘
```

- Mỗi ô `<select>` / `<input type="date">` đặt `tap-safe` để đảm bảo vùng bấm ≥ 44px.
- Trên mobile, mỗi ô `mb-2` để tách hàng; desktop dùng `sm:mb-0`.

**c) Hàng tiêu đề có nút "Xuất" và "Xóa" (PR5-PR6):**

```
Mobile (đã có sẵn "Xóa nhật ký"):
  1234 thao tác                [🗑️ Xóa]
  1234 thao tác       [📥 Xuất▼]  [🗑️ Xóa]
                                       ↑
                              nhóm bên phải: sm:gap-2

Desktop:
  1234 thao tác     [📥 Xuất ▾]  [🗑️ Xóa nhật ký]
                     ↑ dropdown "Xuất CSV" / "Xuất Excel"
```

- Trên mobile: **dropdown chọn định dạng** (CSV / XLSX) làm menu nổi từ nút "Xuất"; tránh 2 nút cạnh nhau (quá chật).
- Trên desktop: dropdown vẫn dùng — đảm bảo 1 chỗ cho 2 lựa chọn.

**d) Modal xem diff (PR6)** — khi bấm vào 1 dòng có `before/after`:

```
Mobile:        full-screen   (fixed inset-0)
               ┌─────────────────────────┐
               │ ✕ Chi tiết sửa: Trần B  │
               │                         │
               │ Trường    | Cũ    | Mới │  ← bảng cuộn ngang
               │ Tên      | Trần A | Trần B│
               │ Điểm     | 8     | 5    │
               │                         │
               │ IP 1.2.3.4 · 14/10 09:30 │
               └─────────────────────────┘

Desktop (sm: ≥ 640px): dialog
               ┌────────────────────────────┐
               │      modal 480×600        │
               └────────────────────────────┘
```

- Bảng diff: mobile dùng `<div>` xếp dọc (`trường / cũ / mới`), desktop dùng `<table>` 3 cột.
- Nút "Đóng" full-width trên mobile (`w-full`), bên phải trên desktop.

**e) Bộ chọn "Xóa theo khoảng ngày" (PR5, B3):**

Modal hỏi trước khi xóa:
```
┌─────────────────────────────────────┐
│ ⚠ Xóa nhật ký                       │
│                                     │
│ ○ Tất cả                            │
│ ● Từ [__/__/____] đến [__/__/____]  │
│                                     │
│ Sẽ xóa khoảng 234 dòng.            │
│                                     │
│ [Hủy]              [Xóa 234 dòng]  │
└─────────────────────────────────────┘
```

- Hai nút cuối: mobile `w-full` xếp dọc, desktop `flex-row sm:justify-end sm:gap-2`.
- Nút xóa: `bg-rose-600 text-white`; nút hủy: outline.

### 9.3. Kiểm tra mobile-first trước khi merge

Mỗi PR có UI đều **chụp màn hình 2 kích thước** dán vào mô tả PR:

| Kích thước | Bằng chứng |
|---|---|
| **375 × 812** (iPhone) | Chromium DevTools, "Pixel 5" preset |
| **1280 × 800** | Chromium DevTools, "Desktop" preset |

Kiểm:
- Không có thanh ngang ngoài ý muốn (ngoại trừ hàng chip/fillter).
- Mọi nút bấm được dễ (không cần zoom).
- Bảng diff cuộn ngang OK nếu quá rộng (mobile).

### 9.4. Tài liệu tham chiếu

- `views/module_settings.php:50-123` — tab Nhật ký hiện tại (mẫu mobile-first đang dùng).
- `tailwind.config.js` — theme mặc định (không tự đổi).
- `docs/process/DESIGN_AUDIT.md` (nếu có) — quy ước design hệ thống.

---

## Phụ lục A: Ước lượng tổng

| Mục | Số dòng thay đổi |
|---|---|
| Schema migration | ~15 |
| `log_action()` mở rộng | ~50 (so với ~10 cũ) |
| `logs.php` filter + scope | ~80 |
| `logs_export.php` + mask | ~150 |
| `xlsx_export.php` helper | ~250 |
| UI filter + nút Xuất + modal diff | ~300 |
| Sửa 30 endpoint để truyền before/after | ~600 |
| Test (unit + smoke) | ~600 |
| **Tổng ước lượng** | **~2.000 dòng** trải qua 12+ PR |

## Phụ lục B: Quyết định đã chốt (2026-10-14)

| # | Câu hỏi | Quyết định | Ghi chú triển khai |
|---|---|---|---|
| B1 | GLV có thấy log về **lớp mình phụ trách**? | **Có** | Query: `(actor_id = $me) OR (target_type='student' AND target_id IN (SELECT id FROM students WHERE class_id IN (lớp-GLV-chủ-nhiệm)))`. CHỈ lớp trong năm học hiện hành (`current_year`). |
| B2 | Export có ghi "ai-xuất-lúc-nào"? | **Có** | Ngay sau khi stream file, gọi `log_action('tao','logs_export','Xuất nhật ký','XLSX · 1.234 dòng · filter: students,2026-10-01→2026-10-14')`. |
| B3 | Xóa log theo khoảng ngày? | **Có** | Endpoint `logs: clear` thêm `from, to` (YYYY-MM-DD). Không có → xóa tất cả (giữ nguyên). Tối đa một lần chạy xóa ≤ 5.000 dòng — nếu hơn, từ chối và yêu cầu thu hẹp. Vẫn chỉ admin (can_view_logs). |
| B4 | Layout mobile-first | **Bắt buộc** | Xem mục 9. |

## Phụ lục C: Giả định (mời sửa nếu sai)

- **"Lớp GLV phụ trách"** = lớp có `homeroom_teacher_id = $me` (cột đã có ở bảng `classes`) **trong năm học hiện hành**. Không bao gồm năm cũ.
- "Trưởng khối / dự bị / thủ thư" cũng được xem log "của mình tạo" — không thấy log về lớp (vì không có lớp chủ nhiệm). Nếu muốn khác, sửa ở mục 3.7.
- Không ghi log cho `read` (GET) — chỉ cho `write` (POST/PUT/DELETE) + các endpoint sửa.
- Khi xóa log, có ghi 1 dòng log kiểu `xoa, settings, "Xóa nhật ký từ X đến Y", "1.234 dòng"` để có dấu vết.
- Khi xuất, ghi 1 dòng log kiểu `tao, logs_export, "Xuất nhật ký", "XLSX · 1.234 dòng · filter: ..."` — trong cùng bảng `activity_logs`.