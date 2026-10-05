# Quy Ước Audit Khả Năng Tiếp Cận (a11y)

> Giao diện phải dùng được cho **GLV đủ lứa tuổi** (có người lớn tuổi, mắt kém)
> và người dùng trợ năng, trên điện thoại. Mục tiêu: **WCAG 2.1 mức AA**.
>
> `DESIGN_AUDIT.md` đã gồm a11y ở mức checklist + công cụ `axe.js`; tài liệu này
> đi **sâu hơn** về cái cần kiểm và cách kiểm (bàn phím, trình đọc màn hình,
> tiếng Việt), dùng **chung** khung chạy với DESIGN_AUDIT — không dựng lại.

---

## 1. Khi nào

- PR đụng `views/*.php`, CSS, hoặc JS render UI → chạy `axe.js` + checklist dưới.
- Định kỳ toàn-app → `docs/audit/A11Y_AUDIT_<YYYY-MM>.md`.

## 2. Tự động (nền) — `tests/e2e/axe.js`

Đã có: quét WCAG 2.1 A/AA bằng `@axe-core/playwright` trên trang chủ + đăng nhập
+ **mọi màn người dùng xem được**, ở mobile 390 + desktop 1366, gộp vi phạm theo
luật và xếp theo số node.

```bash
E2E_BASE=http://127.0.0.1:8080 NPM_TOOLS=<node-tools> node tests/e2e/axe.js
```

Sửa theo `impact`: `critical → serious → moderate → minor`. **Mục tiêu:** 0 vi
phạm `critical`/`serious`. Tự động chỉ bắt được ~30–50% vấn đề a11y → **phải** làm
thêm kiểm thủ công mục 3.

## 3. Thủ công (cái máy không bắt được)

**Bàn phím (không dùng chuột)**
- [ ] Tab đi qua được mọi nút/liên kết/ô nhập theo thứ tự hợp lý.
- [ ] **Focus thấy rõ** (viền/nền) — không bị `outline:none` nuốt mất.
- [ ] Modal (xem tài liệu, sửa hồ sơ, quét QR): mở thì focus vào trong, Esc đóng,
      không "focus kẹt" sau lưng modal.
- [ ] Bottom-sheet/menu đóng được bằng bàn phím.

**Trình đọc màn hình (VoiceOver iOS / TalkBack Android)**
- [ ] Nút **chỉ-icon** có `aria-label` tiếng Việt (nhiều nút QR/đóng/sửa đã có —
      kiểm nút mới).
- [ ] Icon trang trí (`lucide`/SVG nền) có `aria-hidden="true"`.
- [ ] Ô nhập có `<label>`/`aria-label`; lỗi báo bằng text đọc được (không chỉ màu).
- [ ] Nội dung động Alpine (`x-show`/toast/badge) được thông báo (dùng
      `aria-live` cho toast/thông báo quan trọng).
- [ ] Ảnh có `alt` đúng nghĩa (hoặc `alt=""` nếu trang trí).

**Thị giác**
- [ ] **Tương phản AA**: chữ thường ≥ 4.5:1, chữ lớn ≥ 3:1 (nhớ #109: đã sửa
      `text-slate-400`→`-500`; kiểm chỗ mới). Màu đỏ brand trên nền trắng đạt.
- [ ] **Màu không phải tín hiệu duy nhất** (trạng thái điểm danh/đơn: kèm chữ/
      icon, không chỉ màu — người mù màu vẫn hiểu).
- [ ] Phóng to 200% không vỡ layout, không mất nội dung; không khóa
      `maximum-scale` (GLV lớn tuổi phóng to được).
- [ ] Vùng chạm ≥ ~44px (trùng DESIGN_AUDIT mobile).

**Chuyển động**
- [ ] Tôn trọng `prefers-reduced-motion` cho hiệu ứng chuyển màn/transition.

**Tiếng Việt / ngữ nghĩa**
- [ ] `<html lang="vi">` (đã có) để trình đọc phát âm đúng.
- [ ] Cấu trúc tiêu đề (`h1→h2→h3`) hợp lý từng màn; không nhảy cấp.
- [ ] Có "skip to content" hoặc landmark (`<main>`, `<nav>`) để nhảy nhanh.
- [ ] Nút/nhãn bằng tiếng Việt rõ nghĩa, không "click here".

## 4. Checklist gộp (nhanh cho PR)

- [ ] `axe.js`: 0 `critical`/`serious` mới.
- [ ] Nút mới chỉ-icon có `aria-label`; icon trang trí `aria-hidden`.
- [ ] Tab + focus thấy được; modal mới bẫy/nhả focus đúng.
- [ ] Tương phản AA; trạng thái không chỉ bằng màu.
- [ ] Phóng to 200% OK.

## 5. Công cụ

- `tests/e2e/axe.js` (nền). Kiểm thủ công: VoiceOver (iOS), TalkBack (Android),
  chỉ-bàn-phím trên desktop. Chrome DevTools → Lighthouse (Accessibility) +
  Rendering → "Emulate vision deficiencies" (mù màu).

## 6. Báo cáo & quy trình

Finding: **mức** (🔴 chặn người dùng trợ năng / 🟠 khó dùng rõ / 🟡 nên cải thiện /
🔵 nhỏ), **màn + phần tử**, **tiêu chí WCAG**, **cách sửa**. Toàn-app:
`docs/audit/A11Y_AUDIT_<YYYY-MM>.md`. Gắn vào bước Design review ở
`FEATURE_WORKFLOW.md`.

---
_Cập nhật khi thêm mẫu UI mới (modal, sheet, biểu đồ) cần quy tắc a11y riêng._
