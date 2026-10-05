# Quy Ước Audit Quyền Riêng Tư / Bảo Vệ Dữ Liệu Cá Nhân

> App lưu **dữ liệu trẻ em** (tên, ngày sinh, địa chỉ, SĐT phụ huynh). Bảo mật
> hỏi *"ai lọt vào được"*; **quyền riêng tư** hỏi một câu khác: *"ta thu thập có
> đúng/tối thiểu không, giữ bao lâu, lộ cho ai **theo thiết kế**, có chảy ra bên
> thứ ba không, và người dân có quyền gì"*. Một hệ thống có thể "an toàn" mà vẫn
> **vi phạm riêng tư** (vd lưu IP mọi khách vô thời hạn).
>
> ⚖️ Liên quan **Nghị định 13/2023/NĐ-CP** (bảo vệ dữ liệu cá nhân tại Việt Nam):
> dữ liệu trẻ em là **dữ liệu cá nhân nhạy cảm**, cần cơ sở hợp pháp, mục đích rõ,
> tối thiểu hóa, và quyền của chủ thể. Tài liệu này là quy ước kỹ thuật, **không
> phải tư vấn pháp lý** — khi triển khai thật nên hỏi người hiểu luật.

---

## 1. Khi nào

- PR thêm/đổi **trường dữ liệu cá nhân**, endpoint lộ dữ liệu, lưu IP/log, hoặc
  gọi dịch vụ ngoài.
- Định kỳ: rà toàn bộ "bản đồ dữ liệu" → `docs/audit/PRIVACY_AUDIT_<YYYY-MM>.md`.

## 2. Bản đồ dữ liệu (cập nhật khi đổi)

**Dữ liệu cá nhân đang thu thập**

| Nhóm | Trường | Bảng | Nhạy cảm |
|------|--------|------|----------|
| Thiếu nhi | họ tên, tên thánh, ngày sinh, giới tính, địa chỉ, tên+SĐT cha/mẹ | `students` | **Cao (trẻ em)** |
| Nhân sự | họ tên, SĐT, ngày sinh, mật khẩu (băm) | `members` | Trung bình |
| Hành vi | điểm danh, điểm, Mộc, phiếu liên lạc | nhiều | Trung bình |
| Kỹ thuật | IP đăng nhập sai, IP tra cứu, IP lấy Kinh Thánh, nhật ký thao tác | `login_attempts`, `tracuu_attempts`, `tracuu_code_fails`, `bible_daily`, `activity_logs` | IP = dữ liệu cá nhân |
| Thiết bị | endpoint push, user-agent | `push_subscriptions` | Thấp |

**Thời hạn lưu (retention) — kiểm từng cái**
- ✅ `login_attempts`, `tracuu_attempts`, `tracuu_code_fails`: tự dọn theo cửa sổ
  (15/10 phút). Tốt.
- ⚠️ `bible_daily`: **lưu IP mọi khách vô thời hạn** (S10) — cần dọn định kỳ /
  lưu băm IP.
- ⚠️ `activity_logs`: giữ **mãi** tới khi admin bấm xóa — nên có chính sách xoay
  (vd giữ N tháng).
- ⚠️ Hồ sơ em **đã nghỉ / ra trường**: giữ bao lâu? cần chính sách.

## 3. Lộ dữ liệu THEO THIẾT KẾ (không phải lỗ hổng — là lựa chọn)

- **Trang công khai** `tracuu.php`/`somoc.php`/`bxh.php`: lộ **tên + lớp + điểm/
  Mộc** cho ai biết mã (+ ngày sinh ở tracuu). `bxh.php` công khai tên top 20.
  → Kiểm: có thật sự cần hiện họ tên đầy đủ trên bảng xếp hạng công khai không,
  hay viết tắt được? Có cần `noindex` (đã có) + không rò referer (đã có)?
- **`data.php`**: lộ SĐT phụ huynh cho GLV lớp. Đúng phạm vi, nhưng là dữ liệu
  nhạy cảm — chỉ gửi khi màn thật sự cần (tối thiểu hóa payload).
- **`bible.php?action=list`**: admin xem **danh sách IP** khách — cân nhắc có cần
  không.

## 4. Luồng ra bên thứ ba

- `bible-api.com` (HTTPS, verify): máy chủ gọi ra; chỉ lộ **IP máy chủ**, không
  lộ dữ liệu người dùng. Nhưng là phụ thuộc ngoài + nguồn XSS tiềm ẩn (S6). Cân
  nhắc tự chứa câu Kinh Thánh.
- **Web Push** (FCM/Apple): gửi **"chuông rỗng"**, nội dung do SW tự lấy — Google/
  Apple **không** thấy nội dung thông báo. Thiết kế tốt cho riêng tư.
- **Font/CDN:** tự host, **không** gọi Google Fonts → không rò IP sang Google. Tốt.
- **Không** có analytics/tracker bên thứ ba. Giữ nguyên.

## 5. Checklist

**Tối thiểu hóa**
- [ ] Mỗi trường cá nhân thu thập có **mục đích rõ**; bỏ trường không dùng.
- [ ] Payload/endpoint chỉ trả trường cần cho màn đó (không "gửi thừa cho tiện").
- [ ] Trang công khai lộ **ít nhất có thể** (cân nhắc viết tắt tên trên BXH).

**Thời hạn & xóa**
- [ ] Mọi bảng chứa IP/log có **chính sách dọn**; `bible_daily` được dọn/băm.
- [ ] Có đường **xóa hồ sơ** khi được yêu cầu (quyền của chủ thể dữ liệu).
- [ ] Hồ sơ em rời đoàn có vòng đời rõ (ẩn/ẩn danh/xóa sau N năm).

**Bảo vệ & lộ**
- [ ] Không lưu dữ liệu cá nhân nơi tải được công khai (cache web — S1).
- [ ] Log/thông điệp lỗi **không** chứa dữ liệu cá nhân (xem S7).
- [ ] Mật khẩu chỉ lưu băm (Argon2id ✓); không log mật khẩu/token.

**Bên thứ ba & đồng ý**
- [ ] Mỗi luồng ra ngoài được liệt kê + lý do; không thêm tracker.
- [ ] Có cách thể hiện **cơ sở hợp pháp/đồng ý** của phụ huynh cho dữ liệu trẻ em
      (quy trình tổ chức, không chỉ kỹ thuật).

## 6. Công cụ

- Rà "bản đồ dữ liệu": `grep` các trường cá nhân trong payload
  (`grep -rn "fatherPhone\|address\|birth_date\|phone" public/api`).
- Kiểm retention: liệt kê bảng có cột IP/thời gian + xem có lệnh `DELETE … WHERE
  … < moc` không.
- Kiểm lộ công khai: đọc `tracuu.php`/`somoc.php`/`bxh.php` xem chính xác trường
  nào ra HTML.

## 7. Báo cáo & quy trình

Finding: **mức** (🔴 lộ/giữ dữ liệu trẻ em sai / 🟠 thừa dữ liệu / 🟡 thiếu chính
sách / 🔵 ghi chú), **dữ liệu nào + ở đâu**, **rủi ro riêng tư**, **cách sửa**.
Toàn-app: `docs/audit/PRIVACY_AUDIT_<YYYY-MM>.md`. PR đụng dữ liệu cá nhân → đối
chiếu checklist mục 5; phần chồng với bảo mật ghi ở `SECURITY_AUDIT.md`.

---
_Không phải tư vấn pháp lý. Cập nhật "bản đồ dữ liệu" khi thêm/bớt trường cá nhân._
