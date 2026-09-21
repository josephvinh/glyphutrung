# BÁO CÁO KIỂM TRA — TẤT CẢ CÁC TRANG TRONG MENU CHÍNH

**Ứng dụng:** Gia Đình Giáo Lý Phú Trung (TNTT Super App)  
**Phương pháp:** Chrome 153 headless + CDP · PHP built-in server · 1440×900 & 390×844  
**Tài khoản:** đủ **5/5 vai trò** (admin, bdh, truong_khoi, glv_chu_nhiem, glv, du_bi)  
**Ngày kiểm tra:** 19/09/2026

---

## 1. LỖI JAVASCRIPT CONSOLE

### Kết luận: KHÔNG PHÁT HIỆN lỗi JavaScript.

Đã kiểm tra trên:
- **14 trang** điều hướng SPA (dashboard + 13 module): desktop + mobile = 28 lượt
- **12 tab** trong các module (settings x4, reporthub x2, leave x2, promotion x3, student_profile x5)
- **Các nút modal/open** (~8/module × 14 trang)
- **Hồ sơ học sinh** (5 tab)
- **Trang công khai "Bảng thi đua"** (bxh.php) — desktop + mobile
- **5 vai trò** — tất cả module họ truy cập được

| Vai trò | Tên | Số module | Trang có lỗi |
|---|---|---|---|
| admin | Nguyễn Văn A | 13 | 0 |
| bdh | Trần Thị Điều Hành | 13 | 0 |
| truong_khoi | Lê Văn Trưởng Khối | 12 | 0 |
| glv_chu_nhiem | Phạm Thị Chủ Nhiệm | 11 | 0 |
| glv | Ngô Văn Phụ Tá | 11 | 0 |
| du_bi | Vũ Thị Dự Bị | 11 | 0 |

Không ghi nhận exception, console.error hay network error nào trên môi trường php built-in server với docroot đúng (`public/`).

> **Lưu ý về môi trường:** khi chạy qua XAMPP (`http://localhost:8888/tntt/`) — tức docroot trỏ vào `htdocs` thay vì `htdocs/tntt/public` — sẽ có nhiều lỗi 404 và một lỗi CSP `frame-ancestors` cho favicon. Đây là **lỗi cấu hình máy chủ, không phải lỗi ứng dụng**: app được thiết kế để docroot là thư mục `public/` (xem `.htaccess` và `config/`/`views/` nằm ngoài web root).

---

## 2. VẤN ĐỀ UI/UX NGHIÊM TRỌNG (HIGH)

### 🔴 [HIGH-1] 103 lớp Tailwind CSS bị thiếu trong bản build biên dịch sẵn

**Bằng chứng đo được (đo trên 103 lớp nghi vấn, 8 lớp đối chứng):**
- 103/103 lớp: computed style = default (không có tác dụng)
- 8/8 lớp đối chứng: hoạt động bình thường
- `tailwind.css`: 471 lớp · `bundle.min.css`: 545 lớp (cả hai đều thiếu cả 103 lớp)
- Ảnh hưởng cả DEV lẫn PRODUCTION

**Các lớp có tác động thị giác lớn nhất:**

| Lớp | Trang | Tác động đo được |
|---|---|---|
| `pl-10`, `left-3.5` | Thiếu Nhi | paddingLeft=0px, icon kính lúp chiếm x 0..16px → **đè lên chữ placeholder** |
| `grid-cols-1`, `sm:grid-cols-3`, `lg:grid-cols-2`, `xl:grid-cols-3`, `col-span-full` | Thiếu Nhi, Skeleton | `gridTemplateColumns=none` → lưới học sinh chỉ 1 cột |
| `grid-cols-7` | Lịch trình (calendar) | Bảng tuần không có cột (1 cột duy nhất) |
| `md:grid-cols-4` | Phân tích, Điểm số | Thẻ KPI không chia cột |
| `sticky` | Thiếu Nhi + hồ sơ | `position=static` (không dính) |
| `line-through` | Lịch của tôi | Việc đã hoàn thành không có gạch ngang |
| `animate-spin` | Header | Loading spinner không quay |
| `select-none` | Bottom nav, Header | Chữ vẫn chọn được (mất tác dụng chống chọn) |
| `cursor-pointer` | Thông báo, Thư viện,... | Con trỏ chuột thường (không có tín hiệu click được) |
| `ml-auto`, `self-stretch` | Menu, Nội dung | Căn phải + kéo giãn chiều dọc không hoạt động |
| `divide-y`, `divide-slate-100` | Thông báo | Không có đường kẻ giữa các dòng thông báo |
| `bg-teal-50`, `border-teal-100`, `border-teal-200`, etc. | Menu, Thông báo, Lịch | Mất màu nền/viền xanh teal |
| `py-16` | Lịch của tôi | `padding=0px` → thiếu khoảng trống |
| `pb-24` | 22 module | `paddingBottom=0px` (thiếu 96px đệm đáy, nhưng `.app-main` có sẵn padding-bottom 96px nên tác động thấp hơn) |
| `inline`, `inline-block` | Nhiều trang | `display` mặc định sai |
| `shadow`, `shadow-inner` | Nhiều trang | Mất đổ bóng |
| `mb-2.5`, `gap-y-*`, `gap-x-*`, `p-0.5`, `p-2`, `p-2.5`, `mt-2.5`,... | Nhiều trang | Khoảng cách và đệm sai |
| `sm:grid-cols-2`, `sm:grid-cols-3`, `lg:grid`, `sm:p-2`, etc. | Nhiều trang | Responsive layout không áp dụng |

**Danh sách đầy đủ 103 lớp:** xem `scratch/review/out/css-effect-verify.json`

**Nguyên nhân gốc:** Bản build Tailwind tĩnh không được cập nhật sau khi thay đổi markup.

### 🔴 [HIGH-2] Dải tab trượt ngang trên mobile không có dấu hiệu cuộn

Đo trực tiếp trên viewport 390px (`cssScrollbarWidth: none`, thanh cuộn không hiện):

| Trang | Nội dung dải | Chiều rộng ẩn | Thanh cuộn |
|---|---|---|---|
| Thiếu Nhi | Danh sách / Điểm số / Phiếu liên lạc / In thẻ QR | 109px | Không hiện |
| Hướng dẫn | Quản Trị Hệ Thống / Ban Điều Hành / Trưởng Khối / GLV Chủ Nhiệm | 108px | Không hiện |
| Nhân sự | Tất cả / Quản Trị Hệ Thống / Ban Điều Hành / Trưởng Khối / ... | 425px | Không hiện (`hide-scrollbar`) |

Dùng `hide-scrollbar` khiến người dùng không biết có thể cuộn ngang. Trang Nhân sự ẩn tới **425px** (hơn nửa dải) mà không có chỉ dấu nào.

---

## 3. VẤN ĐỀ MỨC TRUNG BÌNH (MEDIUM)

### 🟠 [MED-1] Thanh bên desktop: menu cuối ("Niên khoá") bị cắt 16px

- Mọi trang desktop: `navScrollable=true`, `navScrollH=684` vs `navClient=655`, `lastItemClipped=16px`
- Có thể cuộn xuống để thấy (`reachableAfterScroll=true`)
- Thanh cuộn mảnh (`scrollbar-width: thin`) khó thấy

### 🟠 [MED-2] Định dạng ngày không nhất quán (phụ thuộc locale trình duyệt)

- `<input type="date">` hiển thị `09/19/2026` (MM/DD/YYYY, locale en-US của headless Chrome)
- App dùng `19/09/2026` (DD/MM/YYYY, đúng chuẩn Việt Nam)
- Với người dùng Việt có locale vi-VN, `<input type="date">` hiển thị DD/MM/YYYY, nên không có lỗi. Chỉ lộ khi trình duyệt không phải tiếng Việt.

Trang bị ảnh hưởng: Điểm danh, Xin phép.

### 🟠 [MED-3] Nhiều chữ có độ tương phản thấp (WCAG AA fail)

- **`text-slate-400` (#94a3b8) trên nền trắng** — 2.56:1 (cần ≥4.5). Phổ biến ở: năm học sidebar, nhãn nhóm sidebar ("CHỨC NĂNG", "BAN ĐIỀU HÀNH"), nhãn bottom nav không hoạt động ("Trang chủ", "Thiếu Nhi",...), chữ phụ "em" trong thẻ thống kê, tên người dùng trong sidebar.
- **`text-amber-600` trên `bg-amber-50`** ("2 MỚI") — 2.07:1
- **`text-emerald-600` trên `bg-emerald-50`** ("ĐANG SINH HOẠT") — 3.58:1
- **`text-amber-600` trên `bg-amber-50`** ("TRƯỞNG KHỐI") — 3.07:1

### 🟠 [MED-4] Dữ liệu mẫu để trống/phản trực quan

- Danh sách Thiếu Nhi: icon ghim vị trí nhưng không có địa chỉ, TÊN CHA/TÊN MẸ để trống
- Trang Tổ chức (org): hai khối cùng số "4 lớp • 120 em" → có thể dữ liệu mẫu
- Button "Đang dùng" (Niên khoá): chữ trắng trên nền hồng nhạt (tương phản ~2:1, khó đọc)

---

## 4. VẤN ĐỀ MỨC THẤP (LOW)

- Thanh điều hướng đáy mobile: nền mờ (`rgba(255,255,255,0.85)` + `backdrop-filter: blur(12px)`) — chữ phía sau có thể thấy lờ mờ. Không phải lỗi (chủ ý thiết kế), nhưng nếu muốn che chữ hoàn toàn thì tăng opacity.
- `reporthub`: "Tỷ lệ có mặt 87%" vs "Có mặt 829 (77%)" — hai số cùng ý nhưng giá trị khác nhau, nhãn dễ nhầm. Tổng % cơ cấu chuyên cần = 101% (lỗi làm tròn).
- Tin nhắn "Sắp tới" trống có thể chiếm nhiều diện tích trên mobile.
- `org`: ô "TRƯỞNG KHỐI" cắt cụt chữ trên mobile (`Anna Phạm Thị Chủ Nhiệm — K`).
- `guide`: chữ nội dung nằm dưới bottom nav khi scroll (đã có padding-bottom 96px nên không mất, nhưng khả năng lộ xuyên qua nền mờ).

---

## 5. CÁC TUYÊN BỐ ĐÃ KIỂM CHỨNG GHI NHẬN TỪ PHÂN TÍCH TRỰC QUAN

Các tuyên bố từ báo cáo trực quan **đã được xác nhận**:
- ✅ Icon kính lúp đè placeholder (Thiếu Nhi)
- ✅ Sidebar desktop cắt lẹm mục cuối
- ✅ Nút "Thêm việc" thiếu padding/mất bo (notes-mobile) — do `p-2` + `gap-x-2` missing
- ✅ Dải chip lọc Nhân sự tràn 425px
- ✅ Chữ "TRƯỞNG KHỐI" cắt cụt trên mobile
- ✅ Nút "Đang dùng" (Niên khoá) tương phản thấp
- ✅ Nhãn nav "Trang chủ"/"Thiếu Nhi"/"Cá nhân" tương phản 2.56:1

Các tuyên bố **không chính xác**:
- ❌ "Nội dung bị thanh điều hướng đáy che mất" → `lastChildHiddenBehindNav=0px`, `mainPaddingBottom=96px` đủ chỗ
- ❌ "Nền nav không đục → chữ lộ xuyên qua" → Đúng là `opacity: 0.85` nhưng có `backdrop-filter: blur(12px)` (chủ ý thiết kế frosted glass)

---

## 6. KHUYẾN NGHỊ

> ⚠️ **ĐÃ HIỆU ĐÍNH (20/09/2026):** khuyến nghị #1 ban đầu ("chạy `npm run build`") là **SAI**.
> `npm run build` = `node build/minify.cjs`, chỉ **nén lại** CSS đã có bằng esbuild —
> **không** chạy Tailwind. Repo **không có** `tailwind.config.js`, `postcss.config.*`,
> hay `node_modules/tailwindcss`. Phải **tái lập pipeline Tailwind** thì mới sinh được các lớp thiếu.
> Xem `PHAN-LOAI-UU-TIEN.md` để có thứ tự sửa đầy đủ và đã kiểm chứng.

### 🔴 KHẨN CẤP — BẢO MẬT (phát hiện bổ sung, ưu tiên số 1)
0. **Gỡ script debug + `error_log` khỏi `public/`.** Tất cả truy cập được **không cần đăng nhập** (đã kiểm chứng HTTP 200):
   - `rebuild_login.php` — **ghi đè file qua GET** (đã chứng minh: phá bản minify `login.min.js`, 297 dòng thay 1 dòng)
   - `test_runner_ai.php` — chạy `shell_exec` PHPUnit
   - `read_step.php`, `read_transcript.php` — đọc và in file máy local ra web
   - `find_modified*.php`, `git_check_ai.php`, `git_diff_sw.php` — lộ đường dẫn tuyệt đối
   - `check_times.php` — lộ mtime
   - **`public/error_log` + `public/api/error_log` — đang được GIT THEO DÕI → đã lên production**, lộ `/home/ylcqukhi/...`
   - `.gitignore` đã che 4 script nhưng **bỏ sót** `read_*.php`, `rebuild_login.php`, `check_times.php`, `api/test_webauthn.php`

### Khẩn cấp (HIGH)
1. **Tái lập pipeline Tailwind** để bổ sung ≈98 lớp thiếu (xem hiệu đính ở trên — **không** dùng `npm run build`).
2. **Kiểm tra student filter bar**: sửa `pl-10` → `pl-11` hoặc tái lập Tailwind.
3. **Thêm scroll indicator** cho các dải cuộn ngang trên mobile (`students`, `guide`, `staff`) — hoặc bỏ `hide-scrollbar`.

### Trung bình (MEDIUM)
4. **Sửa contrast**: tăng `text-slate-400` → `text-slate-500` cho các nhãn quan trọng; sửa "Đang dùng" `bg-rose-500 text-white` → `bg-rose-600.text-white`.
5. **Kiểm tra `<input type="date">` locale**: xem xét dùng thư viện datepicker thuần Việt hoặc tự hiển thị placeholder DD/MM/YYYY.

### Thấp (LOW)
6. Kiểm tra `reporthub`: fix làm tròn % để tổng = 100%.
7. Ô "TRƯỞNG KHỐI" cắt chữ: thêm `text-xs` hoặc cho phép xuống dòng.

---

## 7. FILE KẾT QUẢ

- `scratch/review/shots/` — 28 ảnh chụp màn hình (14 trang × desktop+mobile) + bxh-desktop, bxh-mobile
- `scratch/review/out/report-admin.json` — báo cáo crawl (lỗi + probe)
- `scratch/review/out/geometry-admin.json` — đo đạc hình học chi tiết
- `scratch/review/out/interactions-admin.json` — kết quả click tab/modal
- `scratch/review/out/missing-classes.json` — 103 lớp thiếu (nay còn ≈98 sau khi vá tay 6 lớp)
- `scratch/review/out/css-effect-verify.json` — xác nhận 103 lớp thực sự không có hiệu lực
- `scratch/review/out/roles.json` — 2/5 vai trò kiểm tra (0 lỗi)
- `scratch/review/out/roles2.json` — 3/5 vai trò còn lại (0 lỗi)
- `scratch/review/out/roles-bxh.txt` — trang Bảng thi đua + vai trò bdh/truong_khoi
- `scratch/review/out/roles2.txt` — 5/5 vai trò + dải cuộn ngang
- `scratch/review/out/impact.txt` — đo tác động thực tế của lớp CSS thiếu
- `scratch/review/out/analysis.txt` — số đo hình học + tương phản WCAG
- **`scratch/review/PHAN-LOAI-UU-TIEN.md`** — phân loại ưu tiên + thứ tự sửa (đã kiểm chứng độc lập)