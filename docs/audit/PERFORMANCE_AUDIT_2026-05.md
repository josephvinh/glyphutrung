# Báo Cáo Audit Hiệu Năng — GĐGL Phú Trung
**Ngày:** 2026-05-10
**Phạm vi:** Đọc tĩnh code (không có server để đo)
**Người thực hiện:** Claude Code (agent)

---

## Tổng quan

| Mức | Số | Mã |
|-----|----|----|
| 🟠 Cao | 1 | PERF-1 |
| 🟡 Trung bình | 2 | PERF-2, PERF-3 |
| 🔵 Thấp / Thông tin | 1 | PERF-4 |

> ⚠️ **Giới hạn:** Không có server để đo hiệu năng thực tế. Cần chạy
> Lighthouse và kiểm production logs để xác nhận.

---

## Baseline đã có

| Điểm | Trạng thái |
|-------|------------|
| Gzip/Brotli compression | ✅ |
| ETag + 304 responses | ✅ |
| Tải 2 bước (core/heavy) | ✅ |
| Phân trang thiếu nhi | ✅ |
| Bundle JS gộp | ✅ |
| Asset caching 1 năm | ✅ |
| Font tự host | ✅ |
| Lucide rút gọn 16KB | ✅ |
| DB indexes | ✅ |
| APCu cache | ✅ |

---

## Vấn đề

### 🟠 PERF-1 — Double compression

- **Vị trí:** `_bootstrap.php:54-75` + `.htaccess:154-160`
- **Mô tả:** Cả web server và PHP cùng nén → tốn CPU, có thể gây lỗi
- **Số đo:** Không đo được (cần server)
- **Cách sửa:** Chỉ dùng MỘT cơ chế nén

---

### 🟡 PERF-2 — N+1 query tiềm ẩn trong phân quyền

- **Vị trí:** `_bootstrap.php:145-176` (`permission_of()`)
- **Mô tả:** Mỗi lần gọi `permission_of()` đọc DB. Gọi trong vòng lặp → N+1.
- **Số đo:** Không đo được
- **Cách sửa:** Memoize trong request

---

### 🟡 PERF-3 — data.php payload lớn cho admin

- **Vị trí:** `public/api/data.php`
- **Mô tả:** Admin toàn đoàn nhận payload lớn (hàng trăm em + điểm danh)
- **Số đo:** Ước tính 500KB-2MB cho đoàn lớn
- **Cách sửa:** Cân nhắc lazy load hoặc streaming

---

### 🔵 PERF-4 — Chưa có lazy loading cho images

- **Vị trí:** Toàn bộ views
- **Mô tả:** Cần kiểm xem images có `loading="lazy"` không
- **Cách sửa:** Thêm `loading="lazy"` cho tất cả images không trên fold

---

## Công cụ cần chạy

```bash
# Lighthouse (mobile)
npx --yes lighthouse http://127.0.0.1:8080/ --preset=desktop --quiet --chrome-flags="--headless" \
  --output=json --output-path=./lh.json

# Đo payload data.php
curl -s -b cookie.txt "http://127.0.0.1:8080/api/data.php?part=all" -o /dev/null -w "%{size_download} bytes\n"

# Đếm queries (cần bật general_log)
mysql -uroot tntt_test -e "SET GLOBAL general_log=1; SET GLOBAL general_log_file='/tmp/q.log'"
```

---

## Checklist cần đo

- [ ] Lighthouse performance score
- [ ] Payload size data.php theo vai
- [ ] Số queries trên đường nóng
- [ ] Bundle size JS/CSS
- [ ] Thời gian tải trang (FCP, LCP, TTI)

---

## Khuyến nghị

1. **PERF-1:** Sửa double compression
2. **PERF-2:** Memoize permission checks
3. Chạy Lighthouse để có baseline metrics
