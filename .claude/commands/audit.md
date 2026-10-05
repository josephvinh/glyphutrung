---
description: Chạy audit theo quy ước trong docs/process (một loại hoặc tất cả)
argument-hint: "[all | security | functional | design | a11y | performance | privacy | data | infra | deps | code]"
---

Bạn được yêu cầu chạy **audit** cho dự án GĐGL Phú Trung theo đúng các quy ước đã
viết sẵn trong `docs/process/` và `docs/security/`. **Chỉ rà soát và báo cáo — KHÔNG
tự sửa code, KHÔNG commit/push** (việc vá đi theo quy trình riêng ở
`docs/process/FEATURE_WORKFLOW.md`).

Mục tiêu audit: `$ARGUMENTS`
(rỗng hoặc `all` = chạy **tất cả** theo thứ tự bên dưới.)

## Ánh xạ tên → tài liệu quy ước (làm theo phương pháp trong từng tài liệu)

| Tên | Tài liệu | Cần app/DB chạy? |
|-----|----------|------------------|
| security   | `docs/security/SECURITY_AUDIT.md`        | có (kiểm chứng động) |
| functional | `docs/process/FUNCTIONAL_AUDIT.md`       | có (theo từng vai) |
| design     | `docs/process/DESIGN_AUDIT.md`           | có (390px + 1366px) |
| a11y       | `docs/process/ACCESSIBILITY_AUDIT.md`    | có (`tests/e2e/axe.js`) |
| performance| `docs/process/PERFORMANCE_AUDIT.md`      | có |
| privacy    | `docs/process/PRIVACY_AUDIT.md`          | một phần (đọc + map dữ liệu) |
| data       | `docs/process/DATA_INTEGRITY_AUDIT.md`   | có (truy vấn DB) |
| infra      | `docs/process/INFRA_AUDIT.md`            | phần repo thôi (host cần người) |
| deps       | `docs/process/DEPENDENCY_AUDIT.md`       | không |
| code       | `docs/process/CODE_QUALITY_AUDIT.md`     | không |

## Cách làm

1. **Xác định phạm vi** từ `$ARGUMENTS`. Nếu rỗng/`all`: chạy tất cả theo thứ tự
   ưu tiên: security → privacy → functional → data → performance → a11y → design
   → deps → code → infra.
2. **Dựng môi trường** nếu cần (xem `docs/process/TESTING.md` mục 2: MariaDB +
   `install.php` + `ci_seed.php` + `php -S`). Dùng DB test, **không** DB thật.
3. Với mỗi loại: **đọc tài liệu quy ước tương ứng**, làm đúng phương pháp +
   checklist của nó, **kiểm chứng động** khi tài liệu yêu cầu (đừng chỉ đọc code
   rồi đoán — xem luật "chứng cứ trước khi nói" ở `AGENT_RULES.md`).
4. **Ghi báo cáo** mỗi loại ra `docs/audit/<TÊN>_<YYYY-MM>.md` theo đúng định dạng
   mục "Báo cáo" của tài liệu đó (mức độ, vị trí `file:line`, bằng chứng, cách
   sửa). Đánh dấu ✅ đã-kiểm-chứng vs 📖 từ-đọc-code.
5. **Tổng hợp cuối**: một bảng ngắn trong chat — mỗi loại audit: số finding theo
   mức + 1 dòng nổi bật + đường dẫn báo cáo. Nêu rõ phần nào **không** tự làm được
   (vd infra cần truy cập host, privacy cần quyết định tổ chức/pháp lý).

## Giới hạn & an toàn

- Không sửa code, không commit/push, không mở PR (trừ khi người dùng yêu cầu rõ
  sau khi xem báo cáo).
- Không chạy `seed_demo.php`/`install.php` trên DB thật; không đụng máy chủ host.
- Tuân thủ toàn bộ **LUẬT CỨNG** trong `CLAUDE.md`.
- Nếu một loại không chạy được trong môi trường hiện tại (thiếu host/DB/công cụ),
  nói rõ **tại sao** và chuyển sang phần đọc-code được tới đâu — đừng bỏ im.
