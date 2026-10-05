# Quy Ước Audit Chất Lượng / Bảo Trì Code

> Kiểm code **dễ đọc, dễ sửa, không bẫy ngầm** — để lỗi mới khó lọt và người sau
> (hoặc agent) hiểu nhanh. Khác các audit kia: không săn bug chức năng hay lỗ
> hổng, mà săn **nợ kỹ thuật** làm chậm và dễ gãy.

Dùng kèm skill `/simplify` (dọn trùng lặp/đơn giản hóa) và `/code-review` (săn
bug) — tài liệu này là **checklist con người/agent** đối chiếu.

---

## 1. Khi nào

- Trong review mọi PR (phần "đọc dòng xóa" + mùi code).
- Định kỳ dọn nợ → `docs/audit/CODE_QUALITY_<YYYY-MM>.md`.

## 2. Mùi code đã biết trong repo (ưu tiên dọn)

- **Gộp module mong manh:** ~28 mảnh JS gộp bằng `Object.defineProperties` →
  **trùng tên đè nhau âm thầm** (gốc lỗi #194). Có kiểm tự động chưa?
  → `check_module_merge` (TESTING.md §5) phải nằm trong CI.
- **Ba nguồn lược đồ** (`schema.sql` + `migrations/` + `$migrations` trong
  `install.php`) — mùi "nguồn sự thật nhân đôi" (xem DATA_INTEGRITY_AUDIT).
- **Code chết:** `migrate-passkeys.php` hỏng (require sai + `$me` chưa gán);
  `src/Router.php` chỉ còn `tests/UnitTest.php` (ngoài CI) dùng. → xóa hoặc sửa,
  đừng để "code ma".
- **Hàm/tệp quá dài:** `public/somoc.php` (873), `data.php` (625),
  `_rewards.php` (643), `students.js` (1002), `core.js` (1084). Nhánh
  `data.php?classId` rối (gán lại `$params`/`$sql` nhiều lần — xem review). Cân
  nhắc tách hàm.
- **`dependencies` trong package.json** chứa phụ thuộc kéo theo của sharp (dọn —
  xem DEPENDENCY_AUDIT).
- **Comment lỗi thời:** `index.php` còn script dark-mode (đã gỡ dark mode),
  `preconnect cdn.example.com`, comment "lazy-mount" trong khi đã đổi sang x-show.

## 3. Checklist

**Rõ ràng**
- [ ] Đặt tên + comment theo lối repo (tiếng Việt, giải thích "vì sao" không chỉ
      "cái gì").
- [ ] Hàm làm một việc; tệp/hàm quá dài được tách khi hợp lý.
- [ ] Không magic number rải rác (đặt hằng có tên, như `DN_TOI_DA_SO`…).

**Không bẫy ngầm**
- [ ] Không trùng tên thuộc tính/hàm giữa các mảnh JS (có `check_module_merge`).
- [ ] Không `INSERT IGNORE`/`catch{}` nuốt lỗi quan trọng.
- [ ] Không nguồn-sự-thật thứ hai (danh sách module, trạng thái, lược đồ).

**Sạch**
- [ ] Không code chết (hàm/tệp không ai gọi — `grep` xác nhận trước khi xóa).
- [ ] Không comment/preconnect/script lỗi thời.
- [ ] Không TODO/FIXME tồn đọng không có issue (`grep -rn "TODO\|FIXME\|XXX"`).

**An toàn khi sửa**
- [ ] Trùng logic được gộp về một hàm dùng chung (vd `_http_util.php` đã gộp
      `client_ip/json_out` — giữ lối này).
- [ ] Thay đổi có test che lưng (đổi hàm quyền → test quyền).

## 4. Công cụ

```bash
# ESLint (đã ghim) — bắt no-dupe-keys, lỗi tĩnh
npx --yes eslint@9.39.5 public/assets/js/ public/sw.js
# TODO/FIXME tồn đọng
grep -rn "TODO\|FIXME\|XXX\|HACK" public/ config/ src/ | grep -v vendor
# Tệp dài (ứng viên tách)
wc -l public/api/*.php public/assets/js/modules/*.js | sort -n | tail -15
# Hàm gọi tới trước khi xóa
grep -rn "tenHam(" public/ views/
```
- Skill `/simplify` (dọn) và `/code-review` (bug). `tsc --noEmit` khi bật
  `// @ts-check` dần (dùng `src/types/tntt.d.ts`).

## 5. Báo cáo & quy trình

Finding: **mức** (🔴 bẫy dễ gây lỗi sản xuất / 🟠 nợ làm chậm rõ / 🟡 nên dọn /
🔵 nhỏ), **chỗ**, **vì sao là nợ**, **đề xuất**. Toàn-app:
`docs/audit/CODE_QUALITY_<YYYY-MM>.md`. Dọn nợ đi theo PR `refactor/` riêng,
**một mục đích**, có test che lưng.

---
_Cập nhật "mùi đã biết" khi dọn xong một mục (đánh dấu ✅) hoặc phát hiện mới._
