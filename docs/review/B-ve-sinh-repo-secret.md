# Báo cáo Đợt B — Vệ sinh repo & bí mật

Ngày: 2026-09-24 · Nhánh: `claude/web-review-plan-we2ika`

## Tóm tắt
Repo trước rà soát track **6.715 file**; phần lớn là rác không phải mã ứng dụng.
Sau khi dọn còn **273 file** (chỉ mã app thật). Ngoài ra phát hiện **secret bị commit**
trong `config/config.php` — cần bạn tự xoay khoá (xem mục 🔴 bên dưới).

## 1. Đã xử lý trong commit này (gỡ khỏi git, giữ trên đĩa)

| Nhóm | Số file | Ghi chú |
|------|--------:|---------|
| `scratch/` (script agent + profile Chromium: `.db`, `.log`, `Cookies`…) | ~6.400 | Không phải mã app |
| `NGOC VINH/`, `[working-dir] NGOC VINH/`, `.superpowers/sdd/` | vài chục | Rác workspace agent |
| `cookies.txt` | 1 | Chứa cookie phiên (dev) |
| `error_log`, `public/error_log`, `public/api/error_log` | 3 | Log lỗi runtime, có thể lộ đường dẫn/nội bộ |
| `phpunit10.phar` | 1 (~5MB) | CI đã tự `curl` tải trong `ci.yml` → không cần track |
| `Enter` | 1 | File rỗng lạc |

`.gitignore` đã bổ sung để các mục trên không bị track lại.

> **Mức độ**: Trung bình. Chủ yếu là vệ sinh & giảm dung lượng repo; riêng `cookies.txt`
> và `error_log` có yếu tố lộ thông tin nên nên gỡ.

## 2. 🔴 CẦN BẠN XỬ LÝ THỦ CÔNG — Secret bị commit (mức CAO)

File `config/config.php` (đang & vẫn lên git theo thiết kế) chứa giá trị thật:

- `push.private` = khoá **VAPID PRIVATE** của Web Push → **đã lộ trong lịch sử git**.
- `push.public` + `subject` (email) — public key lộ là bình thường, nhưng đi kèm private thì cả cặp phải đổi.
- `setup_key` = chuỗi cho phép chạy `install.php` qua trình duyệt.
- `default_password` = `tntt@2026` (mật khẩu cấp tài khoản mới).

**Vì sao xoá thôi chưa đủ**: khoá đã nằm trong lịch sử git (mọi commit trước),
ai clone/đã xem repo đều có thể đọc. Phải coi là **đã lộ**.

**Khuyến nghị (bạn thực hiện, vì liên quan vận hành máy chủ):**
1. **Xoay khoá VAPID**: tạo cặp VAPID mới, cập nhật vào `config/config.local.php` (đã ignore) trên máy chủ; cập nhật client. Khoá cũ ngừng dùng.
2. Đổi `setup_key` thành chuỗi ngẫu nhiên mới trong `config.local.php`; hoặc để rỗng trong `config.php` để **cấm** chạy install qua web.
3. Đưa `default_password` về giá trị placeholder trong `config.php`, đặt giá trị thật ở `config.local.php`.
4. Trong `config/config.php`: để mọi secret = rỗng/placeholder; nhắc rõ "điền ở config.local.php" (cơ chế override đã có sẵn, xem `config.local.example.php`).
5. (Tuỳ chọn, mạnh tay) Viết lại lịch sử git để xoá secret khỏi các commit cũ (`git filter-repo`) — chỉ làm khi phối hợp được với mọi người đã clone.

> Tôi **không** tự sửa `config/config.php` vì máy chủ đang chạy có thể phụ thuộc
> giá trị mặc định này; đổi sai có thể làm hỏng đăng nhập/push. Cần bạn xác nhận
> đã tạo `config.local.php` trên máy chủ trước khi tôi blank giá trị ở `config.php`.

## 3. Việc còn để ngỏ
- [ ] Bạn xoay khoá VAPID + setup_key + default_password (mục 2).
- [ ] Sau khi bạn xác nhận có `config.local.php`, tôi có thể blank secret trong `config.php`.
- [ ] Cân nhắc `git filter-repo` để dọn lịch sử.
