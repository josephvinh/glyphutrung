# Báo Cáo Audit Chức Năng — GĐGL Phú Trung
**Ngày:** 2026-05-10
**Phạm vi:** Đọc tĩnh code + spec (không có DB để kiểm chứng động)
**Người thực hiện:** Claude Code (agent)

---

## Tổng quan

> ⚠️ **Giới hạn:** Không có MariaDB để kiểm chứng động. Cần chạy test
> trên bản dev để xác nhận các findings.

| Mức | Số | Mã |
|-----|----|----|
| 🟡 Trung bình | 2 | F-1, F-2 |
| 🔵 Thấp / Thông tin | 3 | F-3, F-4, F-5 |

---

## Bất biến nghiệp vụ (kiểm từ code)

### ✅ Đã kiểm và thấy đúng

| Bất biến | Kết luận |
|-----------|----------|
| Ví Mộc: current ≥ 0, held ≥ 0, held ≤ current | ✅ Kiểm trong `_rewards.php:93-95` |
| Ghi danh: mỗi (year_id, student_id) đúng 1 dòng | ✅ UNIQUE constraint |
| Điểm danh: máy chủ quyết trạng thái | ✅ Code kiểm giờ server |
| Phạm vi: dữ liệu ⊆ phạm vi phân công | ✅ `data_scope_for()` |
| Phân công là nguồn thật | ✅ `effective_assignments()` |
| Niên khoá khóa sổ | ✅ Kiểm `status='đã khóa'` |
| Mã thiếu nhi bền | ✅ Không có endpoint đổi mã |
| Điểm số ∈ [0,10] | ✅ Validation trong code |

---

## State Machine đã kiểm

### ✅ Đơn phép

| Chuyển | Kiểm |
|---------|------|
| chờ duyệt → đã duyệt / từ chối | ✅ Code có kiểm |
| Chỉ duyệt đơn lớp mình | ✅ Có scope check |

### ✅ Đơn quà

| Chuyển | Kiểm |
|---------|------|
| chờ lấy → đã giao / đã hủy / quá hạn | ✅ Code có kiểm |
| Mỗi em tối đa 1 đơn chờ lấy | ✅ Kiểm trong `rewards_place_order()` |
| Giao cần mật mã | ✅ Code có kiểm |

### ✅ Thông báo

| Chuyển | Kiểm |
|---------|------|
| nháp → đã phát | ✅ Code có kiểm |
| Thu hồi về nháp | ✅ Code có kiểm |

---

## Vấn đề cần kiểm động

### 🟡 F-1 — Attendance: validation ngày hợp lệ

- **Vị trí:** `public/api/attendance.php`
- **Mô tả:** Cần kiểm xem có chặn điểm danh ngày tương lai không
- **Cách kiểm:** POST với `session_date` là ngày mai → phải bị từ chối

---

### 🟡 F-2 — Import: validate tất cả fields

- **Vị trí:** `public/api/students.php` (import logic)
- **Mô tả:** Cần kiểm xem import có validate đầy đủ không (lớp tồn tại, ngày sinh hợp lệ, etc.)
- **Cách kiểm:** Import với dữ liệu sai → phải báo dòng cụ thể

---

### 🔵 F-3 — thi_dua.php scope

- **Vị trí:** `config/thi_dua.php`
- **Mô tả:** Bảng xếp hạng công khai, cần đảm bảo chỉ trả dữ liệu đúng scope
- **Cách kiểm:** Test bxh.php với nhiều roles

---

### 🔵 F-4 — Race condition đua

- **Vị trí:** `public/api/_rewards.php`
- **Mô tả:** Code có `SELECT ... FOR UPDATE` nhưng cần kiểm đua với nhiều request
- **Cách kiểm:** Test đua với 2 quầy giao quà cùng em cùng lúc

---

### 🔵 F-5 — Rate limiting động

- **Vị trí:** Nhiều endpoint
- **Mô tả:** Rate limiting cần được test với nhiều request liên tiếp
- **Cách kiểm:** Script gửi 100 request liên tiếp → phải bị chặn sau ngưỡng

---

## Checklist cần kiểm động

### Điểm danh
- [ ] Chạm tay trước/sau giờ chốt → `có mặt`/`đi trễ` đúng
- [ ] Quét QR lô: em lớp khác/không sinh hoạt → bị bỏ
- [ ] Buổi tương lai → chặn ghi
- [ ] Đổi giờ máy → không đổi trạng thái

### Thiếu nhi
- [ ] Thêm mới: mã do máy chủ cấp, duy nhất
- [ ] Import: dòng sai → bỏ qua, báo dòng
- [ ] Chuyển/xóa em lớp khác → chặn

### Sổ Mộc / Đổi quà
- [ ] Đặt đơn vượt Mộc → từ chối
- [ ] Giao đúng mật mã
- [ ] Race condition → không âm ví

### Nhân sự
- [ ] Duyệt chỉ đặt vai cơ sở
- [ ] Không gán/hạ vai cho admin/BĐH

### Trang công khai
- [ ] Tra cứu cần mã + ngày sinh
- [ ] Rate limit khi tra cứu quá nhiều

---

## Khuyến nghị

1. **Cần có MariaDB** để chạy functional audit đầy đủ
2. **Viết thêm PHPUnit tests** cho các state machine
3. **Chạy smoke tests** trên bản dev trước khi deploy
