# PHÂN LOẠI LỖI THEO MỨC ƯU TIÊN & THỨ TỰ SỬA

**Nguồn:** agent `chuyen-gia-toi-uu` (provider key4u · model deepseek-v4-pro · chỉ đọc)
**Kiểm chứng độc lập:** tôi đã tự xác minh mọi tuyên bố quan trọng bên dưới
**Ngày:** 20/09/2026

---

## 0. HAI HIỆU ĐÍNH QUAN TRỌNG so với báo cáo gốc

### ⚠️ Hiệu đính 1 — Khuyến nghị "chạy `npm run build`" của tôi là SAI

Báo cáo kiểm tra trước khuyên *"chạy lại Tailwind build (`npm run build`)"* để bổ sung 103 lớp thiếu.
**Điều này không đúng.** Đã kiểm chứng:

| Kiểm tra | Kết quả |
|---|---|
| `package.json` → `scripts` | chỉ có `build: node build/minify.cjs` |
| `build/minify.cjs` làm gì | nối + nén các tệp CSS **đã có sẵn** theo `asset_manifest.php` |
| `minify.cjs` có gọi Tailwind? | **Không** — chỉ dùng `esbuild.transform()` |
| `tailwind.config.js` | **không tồn tại** |
| `postcss.config.*` | **không tồn tại** |
| `node_modules/tailwindcss` | **không tồn tại** |

→ Pipeline sinh Tailwind đã **mất khỏi repo**. `npm run build` chỉ nén lại đúng những gì đang có, nên **không thể** sinh thêm lớp nào. Comment trong `public/index.php` (dòng 66–68) ghi *"chạy `npm run css`"* nhưng script `css` đó **không tồn tại**.

**Muốn sửa phải tái lập pipeline trước** (xem Bước 2 bên dưới).

### ⚠️ Hiệu đính 2 — Con số "103 lớp" đã lỗi thời, hiện còn ≈98

Sau ngày kiểm thử (19/09), `app.css` đã được vá tay **6 lớp** vào lúc 20/09 04:23:12:
`grid-cols-1`, `cursor-pointer`, `inline-block`, `active:scale-[0.99]`, `sm:grid-cols-2`, `max-w-3xl`.
`bundle.min.css` được rebuild sau đó (04:23:26) nên đã chứa chúng.

→ **Còn ≈98 lớp vẫn thiếu.** Cách vá tay vào `app.css` chỉ là tình thế, không giải quyết gốc.

---

## 1. BẢNG PHÂN LOẠI

### 🔴 NGHIÊM TRỌNG

| Mã | Mô tả | Phạm vi | Bằng chứng | 
|---|---|---|---|
| **SEC-1** | **Script debug chạy được KHÔNG cần đăng nhập** — thực thi lệnh, ghi file, đọc file máy local | Toàn máy chủ | Xem bằng chứng chi tiết mục 2 |
| **SEC-2** | **`public/error_log` lộ đường dẫn production** | Toàn app | 8.952 byte, chứa `/home/ylcqukhi/tntt/...` + username hosting + `/opt/alt/php82/` |
| **HIGH-1** | **≈98 lớp Tailwind thiếu** → phá layout toàn app | Gần như mọi trang | `gridTemplateColumns=none` (lưới 1 cột), icon đè placeholder 16px, `position=static` thay `sticky`, mất `line-through` |

### 🟠 TRUNG BÌNH

| Mã | Mô tả | Phạm vi | Bằng chứng |
|---|---|---|---|
| **HIGH-2** | Dải tab/chip cuộn ngang không có chỉ dấu cuộn | Thiếu Nhi, Hướng dẫn, Nhân sự | Nhân sự ẩn **425px** (sw=815 vs cw=390), `scrollbarWidth:none` |
| **MED-1** | Sidebar desktop: mục cuối "Niên khoá" cắt 16px | Mọi trang desktop | `navScrollH=684` vs `navClient=655` |
| **MED-2** | `<input type="date">` hiện MM/DD/YYYY khi locale ≠ vi-VN | Điểm danh, Xin phép | Chrome en-US hiện `09/19/2026`, app dùng `19/09/2026` |
| **MED-3** | Tương phản thấp (WCAG AA fail) | Toàn app | `text-slate-400`/trắng = **2.56:1** (cần 4.5); amber 2.07–3.07:1; emerald 3.58:1 |

### 🟢 NHẸ

| Mã | Mô tả | Bằng chứng |
|---|---|---|
| **MED-4** | Dữ liệu mẫu trống/phản trực quan (ghim không địa chỉ, TÊN CHA/MẸ trống, nút "Đang dùng" ~2:1) | Quan sát |
| **LOW-1** | `reporthub`: "87%" vs "77%" trùng ý khác giá trị; tổng % = 101% | Đo: `87`, `77%`, `11%` |
| **LOW-2** | Ô "TRƯỞNG KHỐI" cắt chữ trên mobile | Quan sát |
| **LOW-3** | Bottom nav nền mờ lộ chữ (chủ ý frosted glass) | `rgba(255,255,255,0.85)` + `blur(12px)` |
| **LOW-4** | "Sắp tới" trống chiếm diện tích | Quan sát |

---

## 2. BẰNG CHỨNG SEC-1 (tôi đã tự kiểm chứng)

Gọi trực tiếp qua HTTP **không kèm cookie phiên**, tất cả trả **HTTP 200**:

| Tệp | Kết quả | Mức nguy hiểm |
|---|---|---|
| `rebuild_login.php` | `"Rebuilt login.min.js"` — **GHI ĐÈ FILE** | 🔴 **Ghi file qua GET** |
| `test_runner_ai.php` | `Executing: ...phpunit10.phar...` rồi chạy | 🔴 **Thực thi lệnh** |
| `read_step.php` | 2.601 byte nội dung transcript AI | 🔴 **Đọc file máy local** |
| `read_transcript.php` | 13.353 byte transcript từ `C:/Users/TUONG NGOC VINH/.gemini/...` | 🔴 **Đọc file máy local** |
| `find_modified.php` | 1.560 byte đường dẫn tuyệt đối `G:\xampp\htdocs\tntt\...` | 🟠 Liệt kê filesystem |
| `git_diff_sw.php` | Chạy `git diff` | 🟠 Thực thi lệnh |
| `check_times.php` | 896 byte mtime các tệp | 🟡 Lộ thông tin |
| `error_log` | 8.952 byte | 🔴 Lộ đường dẫn production |

### Chứng minh write primitive là THẬT

Tôi gọi `rebuild_login.php` một lần. Kết quả đo được:

```
login.min.js sửa lúc : 09/20/2026 04:44:59
Thời điểm kiểm tra   : 09/20/2026 04:45:17   (18 giây sau)
git diff --stat      : 1 file changed, 297 insertions(+), 1 deletion(-)
```

Tệp `login.min.js` (bản nén 1 dòng, phục vụ cho **mọi người dùng**) bị thay bằng bản **chưa nén 298 dòng** — tức request GET đã phá bản minify. Tôi đã khôi phục bằng `git checkout -- public/assets/js/login.min.js` (về 7.830 byte, git sạch).

### Tình trạng git của các tệp này

`.gitignore` **đã** che 4 script: `find_modified*.php`, `git_check_ai.php`, `git_diff_sw.php`, `test_runner_ai.php`
(đúng như chủ dự án đã ghi chú: *"Script debug tạm của agent — KHÔNG đưa lên host"*).

**Nhưng 5 tệp nguy hiểm sau KHÔNG được che:**

| Tệp | gitignored | tracked | Hệ quả |
|---|---|---|---|
| `public/read_step.php` | ❌ | ❌ | `git add .` sẽ đưa lên |
| `public/read_transcript.php` | ❌ | ❌ | `git add .` sẽ đưa lên |
| `public/rebuild_login.php` | ❌ | ❌ | `git add .` sẽ đưa lên |
| `public/check_times.php` | ❌ | ❌ | `git add .` sẽ đưa lên |
| `public/api/test_webauthn.php` | ❌ | ❌ | `git add .` sẽ đưa lên |
| **`public/error_log`** | ❌ | ✅ **CÓ** | **Đã nằm trong git → chắc chắn lên production** |
| **`public/api/error_log`** | ❌ | ✅ **CÓ** | **Đã nằm trong git → chắc chắn lên production** |

→ Hai tệp `error_log` **đang được git theo dõi**, nghĩa là chúng **đã và sẽ** được deploy lên hosting thật, công khai lộ `/home/ylcqukhi/...`.

---

## 3. THỨ TỰ SỬA ĐỀ XUẤT

| # | Bước | Làm gì | Vì sao ở vị trí này | Công sức | Rủi ro |
|---|---|---|---|---|---|
| **1** | **Gỡ script debug + error_log** | Xóa khỏi `public/`: `read_step.php`, `read_transcript.php`, `rebuild_login.php`, `check_times.php`, `test_runner_ai.php`, `git_check_ai.php`, `git_diff_sw.php`, `find_modified*.php`, `api/test_webauthn.php`. `git rm --cached public/error_log public/api/error_log` + xóa tệp + bổ sung `.gitignore` (`public/error_log`, `public/api/error_log`, `public/read_*.php`, `public/rebuild_login.php`, `public/check_times.php`). | **Đầu tiên** vì là lỗ hổng thật (ghi file + chạy lệnh + đọc file local, không cần đăng nhập). Chi phí ~0, độc lập hoàn toàn, hồi phục dễ nhất. | **Nhỏ** | **Rất thấp** |
| **2** | **Tái lập pipeline Tailwind** | ① `npm i -D tailwindcss` ② tạo `tailwind.config.js` với `content` trỏ `views/**/*.php`, `public/index.php`, `public/bxh.php` ③ thêm script `"css"` vào `package.json` ④ sinh lại `tailwind.css` ⑤ `npm run build` để cập nhật `bundle.min.css` ⑥ **xóa 6 lớp vá tay** trong `app.css` ⑦ re-test 98 lớp còn thiếu | **Thứ 2** vì là gốc của lỗi UI lớn nhất. Phải **sau** bảo mật (bảo mật rẻ + khẩn cấp hơn) và **trước** mọi tinh chỉnh CSS tay — nhiều lỗi MED/LOW sẽ tự biến mất khi `sticky`, `grid-cols-*`, `pl-10` quay lại. | **Lớn** | **Trung bình–cao** — sinh lại có thể đổi cả lớp đang chạy; **làm trên nhánh, giữ CSS cũ để rollback, re-test đủ 14 trang × 2 viewport** |
| **3** | **Cuộn ngang + sidebar** | Thêm chỉ dấu cuộn cho `.hide-scrollbar` (gradient mờ 2 đầu) hoặc bỏ nó; chỉnh để mục cuối sidebar hiện đủ | Sau bước 2 vì một phần phụ thuộc kết quả rebuild — sửa sau để không làm 2 lần | **Nhỏ–vừa** | **Thấp** |
| **4** | **Tương phản** | `text-slate-400` → `text-slate-500/600` ở nhãn quan trọng; badge amber/emerald đậm hơn; sửa "Phêrô/Nguyễn Văn A" (1.05:1) | Độc lập, nhưng nên sau khi token màu ổn định (bước 2). Ưu tiên cao vì đối tượng dùng là người lớn tuổi | **Nhỏ** | **Thấp–trung bình** (đổi theo token tập trung) |
| **5** | **Định dạng ngày** | Datepicker/placeholder thuần DD/MM/YYYY | Sau vì chỉ lộ ở trình duyệt không phải tiếng Việt (tần suất thấp) | **Vừa** | **Trung bình** — ảnh hưởng luồng nhập điểm danh/xin phép, phải test nhập + lưu |
| **6** | **Thẩm mỹ + làm tròn %** | Fix làm tròn để tổng = 100%; cho "TRƯỞNG KHỐI" xuống dòng; làm sạch dữ liệu mẫu | Cuối: thuần thẩm mỹ, không phụ thuộc, rủi ro thấp | **Nhỏ** | **Rất thấp** |

---

## 4. VẤN ĐỀ AGENT BỔ SUNG (báo cáo gốc bỏ sót)

### Bảo mật
- **SEC-1, SEC-2** — như mục 2 ở trên (đã kiểm chứng độc lập).

### Hiệu năng
- **`public/bxh.php`** (trang công khai, **không** đăng nhập, **không** rate-limit) quét toàn bộ bảng `attendances` + `scores` cả học kỳ vào PHP rồi mới cắt Top 20. Có thể tốn tài nguyên nếu bị gọi liên tục. `scripts/add_indexes.php` đã có sẵn để tạo index.
- `bxh.php` **không đặt CSP/security headers** như các trang khác (chỉ có `X-Robots-Tag`, `Referrer-Policy`).

### Đã kiểm tra và KHÔNG thấy vấn đề
- **Không có SQL injection** — mọi truy vấn qua `db_all/db_one/db_run/db_insert` dùng prepared statements, `PDO::ATTR_EMULATE_PREPARES=false`, có ép kiểu/whitelist.
- **Không có XSS khả dụng** — output dùng `htmlspecialchars`/`e_()` nhất quán; 3 chỗ `x-html` chỉ dùng cho QR (SVG sinh từ tọa độ số).

---

## 5. GIỚI HẠN & ĐỘ TIN CẬY

- Tôi đã **tự kiểm chứng độc lập**: sự vắng mặt của pipeline Tailwind, SEC-1 (gọi HTTP thật, không cookie), SEC-2, tình trạng git của từng tệp, và chứng minh write primitive bằng `git diff`.
- Agent **không chạy lại trình duyệt** để đo 6 lớp đã vá — chỉ xác nhận qua mã nguồn + nội dung `bundle.min.css`. Nên con số "≈98" là suy ra, không phải đo lại.
- Kết luận "0 lỗi JS console" của báo cáo gốc vẫn đứng vững (khớp `roles2.txt`, 5/5 vai trò).
- Ghi nhận "XAMPP docroot trỏ `htdocs` gây 404 là lỗi cấu hình, không phải lỗi app" — agent xác nhận **đúng**.

---

## 6. TÓM TẮT MỘT DÒNG

> **Sửa ngay 2 tệp `error_log` đang nằm trong git và 9 script debug chạy không cần đăng nhập (trong đó `rebuild_login.php` ghi được file qua GET) → rồi tái lập pipeline Tailwind (≈98 lớp thiếu, KHÔNG sửa được bằng `npm run build`) → rồi mới tới cuộn ngang, tương phản, ngày tháng, thẩm mỹ.**
