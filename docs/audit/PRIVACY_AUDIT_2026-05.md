# Báo Cáo Audit Quyền Riêng Tư — GĐGL Phú Trung
**Ngày:** 2026-05-10
**Phạm vi:** Đọc tĩnh code + map dữ liệu
**Người thực hiện:** Claude Code (agent)

---

## Tổng quan

| Mức | Số | Mã |
|-----|----|----|
| 🟠 Cao | 2 | PR-1, PR-2 |
| 🟡 Trung bình | 3 | PR-3, PR-4, PR-5 |
| 🔵 Thấp / Thông tin | 1 | PR-6 |

---

## Thu thập dữ liệu

### Dữ liệu cá nhân đang thu thập

| Nhóm | Trường | Bảng | Nhạy cảm | Mục đích |
|------|--------|------|----------|-----------|
| **Thiếu nhi** | họ tên, tên thánh, ngày sinh, giới tính, địa chỉ | `students` | **Cao (trẻ em)** | Định danh, liên lạc |
| | tên+SĐT cha/mẹ | `students` | **Cao (trẻ em)** | Liên hệ |
| | mã thiếu nhi | `students` | Trung bình | Định danh |
| **Nhân sự** | họ tên, SĐT, ngày sinh | `members` | Trung bình | Định danh, đăng nhập |
| | mật khẩu (băm) | `members` | Cao | Xác thực (Argon2id) |
| **Hành vi** | điểm danh, điểm, Mộc, phiếu liên lạc | nhiều | Trung bình | Theo dõi |
| **Kỹ thuật** | IP đăng nhập sai | `login_attempts` | **Cao (IP)** | Chống brute-force |
| | IP tra cứu | `tracuu_attempts`, `tracuu_code_fails` | **Cao (IP)** | Chống dò mã |
| | IP lấy Kinh Thánh | `bible_daily` | **Cao (IP)** | Rate-limit |
| | nhật ký thao tác | `activity_logs` | Trung bình | Audit trail |
| **Thiết bị** | endpoint push, user-agent | `push_subscriptions` | Thấp | Thông báo đẩy |

---

## Lộ công khai

### tracuu.php (Tra cứu điểm)

| Trường | Hiển thị? | Ghi chú |
|--------|-----------|---------|
| Họ tên đầy đủ | **Có** | Hiển thị trong thẻ hồ sơ |
| Tên thánh | **Có** | Tiền tố họ tên |
| Mã thiếu nhi | **Có** | Trên thẻ hồ sơ |
| Lớp + niên khoá | **Có** | Trên thẻ hồ sơ |
| Ngày sinh | Không | Chỉ dùng để xác thực |
| Điểm số | **Có** | Tab điểm số |
| Điểm danh | **Có** | Tab điểm danh |
| SĐT cha/mẹ | **Không** | Không hiển thị |
| Địa chỉ | **Không** | Không hiển thị |

**Bảo vệ:** `noindex`, `no-referrer`, POST body cho ngày sinh, rate-limit IP

### somoc.php (Sổ Mộc)

| Trường | Hiển thị? | Ghi chú |
|--------|-----------|---------|
| Họ tên đầy đủ | **Có** | Trên bìa sổ |
| Mã thiếu nhi | **Có** | Trên thẻ hồ sơ |
| Số Mộc (ví, tổng, chuỗi) | **Có** | Dữ liệu chính |

**Bảo vệ:** `noindex`, rate-limit IP, chỉ cần mã

### bxh.php (Bảng xếp hạng)

| Trường | Hiển thị? | Ghi chú |
|--------|-----------|---------|
| Họ tên đầy đủ Top 20 | **Có** | Công khai |
| Lớp Top 20 | **Có** | Trong detail |
| Điểm thi đua | **Có** | Công khai |

**Bảo vệ:** `noindex`, giới hạn Top 20

### data.php (API nội bộ GLV)

| Trường | Hiển thị? | Ghi chú |
|--------|-----------|---------|
| Họ tên, ngày sinh, địa chỉ | **Có** | GLV lớp |
| SĐT cha/mẹ | **Có** | GLV lớp — dữ liệu nhạy cảm |

⚠️ **Cảnh báo:** SĐT phụ huynh được gửi cho GLV lớp. Cần tối thiệu payload.

---

## Retention

### Bảng có tự dọn theo cửa sổ ✅

| Bảng | Cửa sổ | Code |
|------|---------|------|
| `login_attempts` | 5 phút | `_bootstrap.php`, `passkey.php` |
| `tracuu_attempts` | 10 phút | `_somoc.php` |
| `tracuu_code_fails` | 15 phút | `_tracuu.php` |

### Bảng KHÔNG có tự dọn ⚠️

| Bảng | Vấn đề | Rủi ro |
|------|---------|--------|
| `bible_daily` | Không có DELETE | Lưu IP vô thời hạn |
| `activity_logs` | Không có DELETE tự động | Giữ mãi, chỉ admin xóa tay |
| `students` | Không có chính sách | Hồ sơ em nghỉ/ra trường giữ bao lâu? |

---

## Bên thứ ba

| Dịch vụ | Mục đích | Dữ liệu lộ | Rủi ro |
|---------|-----------|-------------|---------|
| bible-api.com | Lấy câu Kinh Thánh ngẫu nhiên | Chỉ IP máy chủ | Phụ thuộc ngoài; nguồn XSS tiềm ẩn |
| FCM/Apple Push | Web Push | Không có (chuông rỗng) | Thiết kế tốt |
| Font/CDN | Font tự host | Không | Tốt |

---

## Vấn đề

### 🟠 PR-1 — `bible_daily` không có retention policy

- **Dữ liệu:** IP khách hàng
- **Rủi ro:** Lưu vô thời hạn → vi phạm Nghị định 13/2023
- **Cách sửa:** Thêm cron job xóa bản ghi cũ > 30 ngày, hoặc băm IP trước khi lưu

---

### 🟠 PR-2 — Hồ sơ em rời đoàn không có vòng đời rõ ràng

- **Dữ liệu:** students (đầy đủ thông tin)
- **Rủi ro:** Không biết giữ bao lâu sau khi ra trường
- **Cách sửa:** Định nghĩa chính sách retention (vd ẩn sau 1 năm không hoạt động, xóa sau 5 năm)

---

### 🟡 PR-3 — `activity_logs` không có chính sách xoay

- **Dữ liệu:** actor_name, action, module, what, detail
- **Rủi ro:** Có thể tích tụ không giới hạn
- **Cách sửa:** Thêm cron job giữ N tháng (vd 6 tháng)

---

### 🟡 PR-4 — `data.php` gửi SĐT phụ huynh trong payload

- **Dữ liệu:** `fatherPhone`, `motherPhone`
- **Rủi ro:** Dữ liệu nhạy cảm của trẻ em
- **Cách sửa:** Tối thiệu hóa — chỉ gửi khi màn thật sự cần

---

### 🟡 PR-5 — `bxh.php` hiển thị họ tên đầy đủ Top 20 công khai

- **Dữ liệu:** Họ tên thiếu nhi
- **Rủi ro:** Họ tên trẻ em trên internet
- **Cách sửa:** Cân nhắc viết tắt (vd chỉ tên)

---

### 🔵 PR-6 — `bible.php?action=list` lộ danh sách IP cho admin

- **Dữ liệu:** IP khách hàng
- **Rủi ro:** Admin xem được IP người dùng
- **Cách sửa:** Băm IP khi hiển thị cho admin

---

## Checklist

### Tối thiểu hóa
- [x] tracuu.php chỉ lộ tên/lớp/điểm
- [x] somoc.php chỉ lộ tên/mã/Mộc
- [ ] **PR-4:** data.php tối thiệu payload SĐT phụ huynh
- [ ] **PR-5:** bxh.php cân nhắc viết tắt tên

### Thời hạn & xóa
- [x] login_attempts có cửa sổ 5 phút
- [x] tracuu_attempts có cửa sổ 10 phút
- [x] tracuu_code_fails có cửa sổ 15 phút
- [ ] **PR-1:** bible_daily cần cron job dọn IP
- [ ] **PR-3:** activity_logs cần chính sách xoay
- [ ] **PR-2:** students cần chính sách cho hồ sơ rời đoàn

### Bảo vệ & lộ
- [x] Mật khẩu chỉ lưu băm (Argon2id)
- [ ] S1 (cache PII trong public/) cần vá
- [ ] S7 (lộ lỗi gốc) cần vá
- [ ] **PR-6:** bible.php băm IP khi hiển thị

### Bên thứ ba & đồng ý
- [x] Web Push không gửi nội dung qua FCM
- [x] Không dùng Google Fonts
- [ ] **Cần:** Có cách thể hiện cơ sở hợp pháp của phụ huynh cho dữ liệu trẻ em

---

## Khuyến nghị

### Ưu tiên cao
1. **PR-1** — Thêm cron job dọn `bible_daily`
2. **PR-2** — Định nghĩa retention cho hồ sơ thiếu nhi

### Ưu tiên trung bình
3. **PR-3** — Thêm chính sách xoay `activity_logs`
4. **PR-4** — Tối thiệu hóa payload `data.php`
5. **PR-5** — Cân nhắc viết tắt tên trên BXH công khai

### Về tổ chức/pháp lý
- Cần có quy trình xin đồng ý phụ huynh cho dữ liệu trẻ em
- Cần có chính sách bảo vệ dữ liệu cá nhân (DPO/người phụ trách)
