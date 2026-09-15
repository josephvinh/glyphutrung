# Thiết kế: Thư viện tài liệu online

> Dự án **TNTT Super App** (glyphutrung.top) · Ngày: 2026-09-15
> Trạng thái: **Bản thiết kế chờ duyệt** (chưa code)

## 1. Mục tiêu

Một mục **"Thư viện"** trong app để các anh chị GLV **đăng, duyệt, tìm và xem trực tiếp** tài liệu đào tạo/sinh hoạt (giáo án, tài liệu huynh trưởng, bài hát, văn kiện…). File lưu **trên máy chủ**, chỉ người đã đăng nhập mới xem được.

## 2. Người dùng & quyền (gắn vào hệ thống module sẵn có)

Thêm một module mới `thu_vien`. Dùng đúng mô hình quyền hiện tại (`none / view / edit`):

| Quyền | Ai | Được làm gì |
|---|---|---|
| `view` | Mọi GLV (mặc định) | Xem/tải tài liệu **đã duyệt**; **đăng tài liệu mới** (vào trạng thái chờ duyệt); xem/gỡ tài liệu **do chính mình** đăng |
| `edit` | BĐH, Admin | Tất cả quyền trên + **duyệt/từ chối**, **gỡ bất kỳ** tài liệu, **quản chủ đề** |

Quyết định: "đăng tài liệu" nằm trong `view` (không cần `edit`) — vì mọi GLV được đóng góp; kiểm soát chất lượng nằm ở **bước duyệt** chứ không ở bước đăng.

## 3. Mô hình dữ liệu

### Bảng `library_categories` (chủ đề)
| Cột | Kiểu | Ghi chú |
|---|---|---|
| `id` | INT PK AI | |
| `name` | VARCHAR(100) | Tên chủ đề |
| `sort_order` | INT | Thứ tự hiển thị |
| `is_active` | TINYINT(1) | Ẩn chủ đề cũ mà không xoá |

Seed khởi tạo: `Giáo án`, `Đào tạo Huynh trưởng`, `Bài hát`, `Văn kiện`, `Sinh hoạt`.

### Bảng `library_items` (tài liệu)
| Cột | Kiểu | Ghi chú |
|---|---|---|
| `id` | INT PK AI | |
| `title` | VARCHAR(200) | Tiêu đề (bắt buộc) |
| `description` | TEXT NULL | Mô tả ngắn |
| `category_id` | INT NULL | FK → library_categories (ON DELETE SET NULL) |
| `stored_name` | VARCHAR(120) | Tên file **ngẫu nhiên** trên đĩa (không đoán được) |
| `original_name` | VARCHAR(255) | Tên gốc — chỉ để hiển thị/đặt tên khi tải về |
| `mime_type` | VARCHAR(100) | Kiểu thật (finfo), quyết định xem-trực-tiếp hay tải-về |
| `size_bytes` | INT UNSIGNED | Dung lượng |
| `status` | ENUM('cho_duyet','da_duyet','tu_choi') | Mặc định `cho_duyet` |
| `uploaded_by` | INT | FK → members.id |
| `approved_by` | INT NULL | FK → members.id |
| `reject_reason` | VARCHAR(255) NULL | Lý do từ chối (hiện lại cho người đăng) |
| `created_at` | DATETIME | |
| `approved_at` | DATETIME NULL | |

Chỉ số: `INDEX(status, category_id)`, `INDEX(uploaded_by)`.

## 4. Lưu trữ & bảo mật (phần quan trọng nhất)

### Nơi lưu file
- **Ưu tiên:** thư mục **ngoài web** — `<= project_root>/storage/library/` (không nằm trong `public/`, không ai gọi URL trực tiếp được).
- **Nếu host chặn ghi ngoài docroot:** dùng `public/library/` **kèm `.htaccess` chặn truy cập trực tiếp** (`Require all denied`) + tắt thực thi PHP trong thư mục đó.
- Đây là **chi tiết triển khai theo host** — sẽ xác định khi deploy.

### Phục vụ file qua cổng PHP (không link tĩnh)
- Endpoint `api/library_file.php?id=N&mode=view|download`:
  1. `require_login()` — chưa đăng nhập thì 403.
  2. Lấy item; chỉ cho xem nếu `da_duyet` **hoặc** người xem là người đăng/`edit`.
  3. `readfile()` với header đúng:
     - `Content-Type: <mime_type>`
     - `X-Content-Type-Options: nosniff`
     - `Content-Disposition: inline` (xem) hoặc `attachment; filename="<original_name>"` (tải).
- Nhờ vậy file **riêng tư** (chỉ member), và **không bị đoán URL**.

### Kiểm tra khi upload (chống hack)
1. **Trắng danh sách phần mở rộng + MIME thật** (dùng `finfo_file`, không tin phần mở rộng người gửi):
   - Xem trực tiếp: `pdf`, `jpg/jpeg`, `png`, `webp`.
   - Cho tải về: `doc/docx`, `ppt/pptx`.
2. **Chặn tuyệt đối** file thực thi: `.php .phtml .phar .htaccess .cgi .pl .sh …` (kể cả đuôi kép `x.php.pdf`).
3. **Đổi tên ngẫu nhiên** khi lưu (`bin2hex(random_bytes(16))` + đuôi chuẩn hoá theo MIME). Tên gốc chỉ lưu trong DB.
4. **Giới hạn dung lượng: 15MB/file** (chặn cả ở PHP lẫn thông báo rõ cho người dùng).
5. Kiểm `upload_max_filesize`/`post_max_size` của host; nếu nhỏ hơn 15MB thì lấy mức thấp hơn và báo rõ.

## 5. Luồng nghiệp vụ

### Đăng tài liệu (GLV)
1. Bấm **"Đăng tài liệu"** → form: tiêu đề, mô tả, chủ đề, chọn file.
2. Gửi multipart → server kiểm (mục 4) → lưu file + tạo bản ghi `cho_duyet`.
3. Báo: *"Đã gửi, chờ Ban Điều Hành duyệt."*

### Duyệt (BĐH/Admin)
- **Hàng chờ duyệt**: danh sách `cho_duyet` → xem trước → **Duyệt** (`da_duyet` + ghi người/ngày) hoặc **Từ chối** (`tu_choi` + lý do; **xoá file khỏi đĩa** để khỏi phình host).

### Xem (mọi GLV)
- Màn thư viện: **tìm theo tiêu đề** + **lọc theo chủ đề**, chỉ hiện `da_duyet`.
- Mở item: PDF/ảnh → **xem nhúng trực tiếp**; Word/PPT → nút **Tải về**. Luôn có nút tải.
- Người đăng thấy thêm tài liệu của mình đang `cho_duyet`/`tu_choi` (kèm lý do).

## 6. API (đặt tại `public/api/library.php` + `library_file.php`)

| Action | Method | Quyền | Việc |
|---|---|---|---|
| `list` | GET | view | Danh sách đã duyệt (+ lọc `category_id`, `q` tìm kiếm) |
| `mine` | GET | view | Tài liệu của tôi (mọi trạng thái) |
| `pending` | GET | edit | Hàng chờ duyệt |
| `upload` | POST (multipart) | view | Đăng mới (→ cho_duyet) |
| `approve` | POST | edit | Duyệt |
| `reject` | POST | edit | Từ chối (+ lý do, xoá file) |
| `delete` | POST | view* | Gỡ (của mình); `edit` gỡ bất kỳ |
| `categories` | GET | view | Danh sách chủ đề |
| `saveCategory`/`toggleCategory` | POST | edit | Quản chủ đề |

- `library_file.php?id=&mode=` — phục vụ file (mục 4).
- Mọi action ghi dùng `require_write()` + `require_permission('thu_vien', …)`. Upload là multipart nên đọc `$_FILES`, không qua `json_input()`.

## 7. Giao diện (một module SPA, khớp phong cách hiện có)

- **Màn Thư viện**: ô tìm kiếm + hàng chip lọc chủ đề + lưới thẻ tài liệu (icon theo loại file, tiêu đề, chủ đề, người đăng). BĐH thấy **badge số lượng chờ duyệt**.
- **Xem tài liệu**: sheet/modal — PDF nhúng (`<embed>`/iframe qua `library_file.php?mode=view`), ảnh hiện thẳng, còn lại hiện thẻ "Tải về".
- **Đăng**: form đơn giản + kéo-thả/chọn file, thanh tiến trình, kiểm cỡ file ngay ở client trước khi gửi.
- **Hàng chờ duyệt** (BĐH): danh sách + xem trước + Duyệt/Từ chối.

## 8. Cấu hình (thêm vào config)
- `library.max_size_mb = 15`
- `library.allowed_view = ['pdf','jpg','jpeg','png','webp']`
- `library.allowed_download = ['doc','docx','ppt','pptx']`
- `library.storage_path` (đường dẫn thư mục lưu, theo host)

## 9. Quyết định đã chốt (từ brainstorm)
- Nội dung: tài liệu **xem trực tiếp trong app** (ưu tiên PDF/ảnh).
- Người đăng: **mọi GLV**, **BĐH duyệt** mới hiện.
- Lưu trữ: **file trên máy chủ** (không dùng link ngoài ở v1).
- Loại file: PDF + ảnh (xem) + Word/PPT (tải). Tối đa **15MB/file**.
- Chủ đề khởi tạo: Giáo án · Đào tạo Huynh trưởng · Bài hát · Văn kiện · Sinh hoạt.

## 10. Ngoài phạm vi v1 (làm sau nếu cần)
- Link ngoài (Google Drive/YouTube) song song với file host.
- Xem trực tiếp Word/PPT (cần chuyển đổi — nặng).
- Phiên bản tài liệu, đếm lượt tải, bình luận/đánh giá.
- Phân quyền theo khối/lớp (v1: mọi GLV xem chung).

## 11. Rủi ro & lưu ý
- **Dung lượng host**: file dồn lên máy chủ sẽ ăn ổ đĩa + băng thông. Cần theo dõi; từ chối thì xoá file ngay; cân nhắc dọn định kỳ. Nếu host chật, nên mở phương án "link ngoài" ở v2.
- **Bảo mật upload là rủi ro số 1** — mọi biện pháp ở mục 4 là bắt buộc, không được lược bớt.
- **Bản sao lưu**: file người dùng tải lên cần nằm trong quy trình backup của host.
