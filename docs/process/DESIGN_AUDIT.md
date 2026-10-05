# Quy Ước Audit Design (Giao Diện / UX)

> Song song với `SECURITY_AUDIT.md` (bảo mật) và `TESTING.md` (đúng-sai), tài
> liệu này quy ước **cách rà soát chất lượng thiết kế**: nhất quán thị giác, khả
> năng dùng trên điện thoại, khả năng tiếp cận (a11y), và đúng bản sắc thương
> hiệu. Mục tiêu: giao diện đọc ra như **một hệ thống**, không chắp vá, dùng
> được cho Giáo Lý Viên đủ lứa tuổi trên điện thoại sóng yếu.

Audit design **không** thay cho review code hay smoke test — nó bổ sung lớp
"trông/đụng vào có đúng và dễ dùng không".

---

## 1. Khi nào audit design

- **Bắt buộc** khi PR đụng `views/*.php`, `public/assets/css/*`, hoặc JS render UI
  (`public/assets/js/modules/*.js`): làm bước **Design review** ở
  `FEATURE_WORKFLOW.md` [5], kèm **ảnh chụp trước/sau** ở cả điện thoại + máy tính.
- **Định kỳ** (toàn app) khi: thêm màn mới nhiều, đổi brand/token, hoặc trước một
  đợt phát hành lớn. Xuất báo cáo vào `docs/design/DESIGN_AUDIT_<YYYY-MM>.md`.
- Khi người dùng than "xấu / khó bấm / chữ khó đọc / lệch trên điện thoại".

---

## 2. Bản sắc & nguyên tắc của app (baseline để đối chiếu)

Mọi màn phải nhất quán với các điểm sau — lệch là một "finding":

- **Thương hiệu — tông ĐỎ.** Thang đỏ chuẩn ở `public/assets/css/brand.css`
  (chính `#e11d36`, đậm `#b81528`), **ghi đè** các lớp `blue-*` của Tailwind
  (brand.css nạp cuối). ⚠️ `index.php` đặt `<meta name="theme-color" content="#c8203a">`
  — khác màu chính `#e11d36`; thống nhất một giá trị là một việc nên rà.
- **Typography.** Hai họ tự host: **Be Vietnam Pro** (tiêu đề/điểm nhấn) +
  **Inter** (thân), weights 400/500/600/700/900 (woff2 ở `assets/font/`). Dùng
  thừa/thiếu weight (vd `font-black` mà thiếu 900) khiến trình duyệt bôi đậm giả,
  nét bệt — kiểm. Cỡ chữ token riêng: `micro` = 11px (`tailwind.config.js`).
- **Mobile-first, là PWA.** Khung co giãn `max-w-md → sm:max-w-xl → lg:max-w-6xl
  → xl:max-w-7xl`. Có lớp `ios-platform`/`android-platform`, hỗ trợ `safe-area`
  (tai thỏ), `viewport-fit=cover`. Thiết kế cho **ngón tay** trước, chuột sau.
- **KHÔNG dark mode.** Đã gỡ hẳn (#179/#180). Không thêm lại `prefers-color-scheme`
  hay lớp `.dark`; `body` phải có nền sáng rõ ràng.
- **Tiếng Việt là ngôn ngữ chính.** Dấu đầy đủ, không cắt cụt chữ có dấu, canh
  dòng đẹp với dấu thanh. Nội dung mẫu phải là tiếng Việt thật.
- **Icon:** bộ Lucide **rút gọn 76 icon** (`build/tao_lucide.cjs`). Dùng icon
  chưa có trong bộ sẽ không hiện — phải thêm vào bộ rồi dựng lại, không tự ý
  nhúng bộ đầy đủ (410KB).
- **Cảm giác nhanh:** mạng yếu là thực tế người dùng. Có skeleton/loading rõ
  ràng (`skeleton.css`), tránh "màn hình trắng", ảnh `loading="lazy"`.

Khi cần **định hướng thẩm mỹ** cho UI mới (không chỉ sửa vặt), dùng skill
`frontend-design` để chọn có chủ đích (palette, type scale, hero) thay vì mặc
định chung chung. Với biểu đồ/thống kê, dùng skill `dataviz`.

---

## 3. Phương pháp (chạy app thật, không đoán)

Audit design **phải** xem trên trình duyệt thật ở ít nhất hai bề rộng:
**điện thoại 390px** và **máy tính 1366px** (khớp `axe.js`).

```bash
# Dựng DB test + server (xem TESTING.md mục 2)
export TNTT_DB_HOST=127.0.0.1 TNTT_DB_NAME=tntt_test TNTT_DB_USER=tntt TNTT_DB_PASS=tntt
php config/install.php && php tests/fixtures/ci_seed.php
mysql -uroot tntt_test -e "UPDATE members SET must_change_pw=0 WHERE role_code='admin'"
php -S 127.0.0.1:8080 -t public &
```

1. **Chụp màn hình từng màn** ở cả hai bề rộng (dùng khung Playwright trong
   `TESTING.md` mục 4, thêm `page.screenshot({ fullPage: true })` cho mỗi màn).
   Lưu vào `tests/e2e/out/` hoặc scratchpad — **gửi kèm PR** khi có UI.
2. **So với baseline (mục 2):** mỗi màn có đúng tông đỏ, đúng font, không tràn
   ngang, chạm được bằng ngón tay.
3. **Chạy kiểm a11y tự động** (mục 4) và đọc các vi phạm theo mức impact.
4. Ghi finding theo định dạng mục 6.

---

## 4. Công cụ

- **Accessibility — `tests/e2e/axe.js`** (đã có): quét WCAG 2.1 A/AA bằng
  `@axe-core/playwright` trên trang chủ + đăng nhập + **mọi màn người dùng xem
  được**, ở mobile 390 + desktop 1366. Kết quả gộp theo luật, xếp theo số node:

  ```bash
  # Cần @axe-core/playwright trong thư mục NPM_TOOLS
  E2E_BASE=http://127.0.0.1:8080 NPM_TOOLS=<đường-dẫn-node-tools> node tests/e2e/axe.js
  ```

  Ưu tiên sửa theo `impact`: `critical` → `serious` → `moderate` → `minor`.
  Hay gặp ở app này: **tương phản chữ** (nhớ #109: `text-slate-400` → `-500`),
  thiếu `aria-label` cho nút chỉ-icon, thiếu nhãn ô nhập.
- **Smoke + screenshot:** dùng chung khung Playwright ở `TESTING.md` mục 4 —
  nhân tiện chụp màn để so trước/sau.
- **Tailwind:** sau khi thêm lớp mới trong `views`/JS, chạy `npm run build:css`
  (Tailwind quét `views/**` + `public/assets/js/**` + `index.php`). Lớp mới không
  build sẽ không có CSS → trông "vỡ".

---

## 5. Checklist audit design

**Nhất quán hệ thống**
- [ ] Màu: đúng thang đỏ brand, không còn `blue-*` lộ ra; `theme-color` khớp màu chính.
- [ ] Khoảng cách/bo góc/đổ bóng đồng nhất giữa các thẻ, nút, ô nhập.
- [ ] Icon cùng bộ Lucide rút gọn; không có icon "trống" (chưa có trong bộ).
- [ ] Không chèn `<style>` với class ngắn chung chạm màn khác (vd xem-trước QR).

**Điện thoại trước**
- [ ] Không tràn ngang (`overflow-x`) ở 390px; nội dung không bị thanh điều hướng
      dưới che (padding đáy đủ).
- [ ] Vùng chạm ≥ ~44px; nút/liên kết không sát nhau quá.
- [ ] `safe-area` tôn trọng (tai thỏ iPhone); không dính mép.
- [ ] Phóng to được (không `maximum-scale=1`) cho GLV lớn tuổi.

**Typography tiếng Việt**
- [ ] Đúng weight khai (có 900 khi dùng `font-black`); không bôi đậm giả.
- [ ] Chữ có dấu không bị cắt/đè; dòng dài hợp lý, dễ đọc.

**Trạng thái & phản hồi**
- [ ] Có trạng thái **loading** (skeleton) và **rỗng** (empty) rõ ràng — không
      để "0 em" mập mờ khi thực ra là "chưa chọn lớp".
- [ ] Lỗi hiện thân thiện (toast/thông báo), không trơ số hay JSON.
- [ ] Nút đang xử lý bị khóa, có chỉ báo.

**Khả năng tiếp cận (axe)**
- [ ] 0 vi phạm `critical`/`serious`; `moderate` có kế hoạch.
- [ ] Tương phản chữ/nền đạt AA; nút chỉ-icon có `aria-label`; ô nhập có nhãn.
- [ ] Focus thấy được khi dùng bàn phím.

**Cảm giác nhanh**
- [ ] Mở màn không "trắng" chờ mạng; ảnh `lazy`; không tải tài nguyên thừa.

---

## 6. Định dạng báo cáo

Mỗi finding ghi: **mức** (🔴 chặn dùng / 🟠 ảnh hưởng rõ / 🟡 nên sửa / 🔵 nhỏ),
**màn + vị trí** (`views/...php:line` hoặc màn + bề rộng), **ảnh chụp** minh họa,
**vì sao lệch baseline**, và **cách sửa** (lớp/token cụ thể). Báo cáo toàn-app
lưu `docs/design/DESIGN_AUDIT_<YYYY-MM>.md`; finding trong một PR để thẳng ở PR
kèm ảnh.

> Lưu ý: audit design mô tả hiện trạng + cách sửa; **sửa** đi theo quy trình tính
> năng (nhánh + PR riêng), không trộn báo cáo với bản vá.

---

## 7. Gắn vào quy trình

- `FEATURE_WORKFLOW.md` [5] — PR có UI phải qua **Design review** (người hoặc
  subagent) + ảnh trước/sau.
- `TESTING.md` — khung Playwright dùng chung; thêm `axe.js` thành job CI khuyến
  nghị (đỏ khi có vi phạm `critical`/`serious` mới).
- Subagent: có thể giao cho `tntt-reviewer` (đọc + đối chiếu baseline) hoặc dùng
  skill `frontend-design` cho định hướng thẩm mỹ — **chỉ khi người dùng yêu cầu
  dùng subagent**.

---
_Cập nhật khi đổi brand/token hoặc thêm công cụ a11y._
