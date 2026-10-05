# Báo Cáo Audit Thiết Kế & A11y — GĐGL Phú Trung
**Ngày:** 2026-05-10
**Phạm vi:** Đọc tĩnh code + views (cần chạy axe.js để đầy đủ)
**Người thực hiện:** Claude Code (agent)

---

## Tổng quan

| Mức | Số | Mã |
|-----|----|----|
| 🟡 Trung bình | 3 | DES-1, DES-2, A11Y-1 |
| 🔵 Thấp / Thông tin | 2 | DES-3, DES-4 |

> ⚠️ **Giới hạn:** Cần chạy axe.js + chụp màn hình để audit design đầy đủ.
> Phần này chỉ kiểm được từ đọc code.

---

## Baseline đã có

| Điểm | Trạng thái |
|-------|------------|
| Brand tông ĐỎ | ✅ brand.css |
| Typography Be Vietnam Pro + Inter | ✅ font.css |
| Mobile-first PWA | ✅ |
| Không dark mode | ✅ |
| Lucide icons rút gọn | ✅ |
| Skeleton loading | ✅ skeleton.css |
| safe-area iOS | ✅ |
| Tiếng Việt ngôn ngữ chính | ✅ |

---

## Vấn đề Design

### 🟡 DES-1 — theme-color khác với màu chính

- **Vị trí:** `public/index.php`
- **Mô tả:** `<meta name="theme-color" content="#c8203a">` khác với `#e11d36` (màu chính brand)
- **Cách sửa:** Thống nhất thành một giá trị

---

### 🟡 DES-2 — innerHTML trong toast.js

- **Vị trí:** `public/assets/js/modules/toast.js:59`
- **Mô tả:** `toast.innerHTML = toastSvg(type);`
- **Rủi ro:** XSS tiềm ẩn nếu `type` không validate
- **Cách sửa:** Dùng `textContent` hoặc sanitize input

---

### 🔵 DES-3 — Comment lỗi thời về Vue Router

- **Vị trí:** `public/.htaccess:19-20`
- **Mô tả:** Comment nói về Vue Router nhưng app dùng Alpine.js
- **Cách sửa:** Cập nhật comment

---

### 🔵 DES-4 — Cần kiểm tương phản AA

- **Vị trí:** Toàn bộ views
- **Mô tả:** Cần dùng DevTools để kiểm tương phản cho tất cả text
- **Cách sửa:** Chạy axe.js trên production

---

## Vấn đề Accessibility

### 🟡 A11Y-1 — aria-live cho toast

- **Vị trí:** `public/assets/js/modules/toast.js`
- **Mô tả:** Toast messages nên có `aria-live` để screen reader thông báo
- **Cách sửa:** Thêm `aria-live="polite"` vào toast container

---

## Checklist cần chạy với axe.js

```bash
E2E_BASE=http://127.0.0.1:8080 node tests/e2e/axe.js
```

### Cần kiểm thủ công

- [ ] Focus visible (không outline:none)
- [ ] Modal focus trap
- [ ] aria-label cho nút chỉ-icon
- [ ] Tương phản AA
- [ ] prefers-reduced-motion
- [ ] Phóng to 200%

---

## Khuyến nghị

1. Chạy axe.js để có audit a11y đầy đủ
2. Sửa DES-1: thống nhất theme-color
3. Sửa DES-2: dùng textContent thay vì innerHTML
4. Thêm aria-live cho toast
