# Quy Ước Audit Hiệu Năng / Tải

> Mục tiêu cốt lõi của app: dùng được trên **điện thoại, sóng yếu** ở sân nhà
> thờ. Audit hiệu năng kiểm app **mở nhanh, thao tác mượt, tốn ít băng thông** —
> không để "màn hình trắng mấy giây" hay điểm danh cả đoàn mà nghẽn.

Khác `TESTING.md` (đúng-sai) và `FUNCTIONAL_AUDIT.md` (chức năng): ở đây đo
**tốc độ + kích thước + số truy vấn**.

---

## 1. Khi nào

- PR thêm màn/endpoint nặng, truy vấn mới, hoặc đổi `data.php`/bundle.
- Định kỳ trước phát hành; khi người dùng than "chậm / tốn 3G / mở lâu".
- Xuất báo cáo toàn-app: `docs/audit/PERF_AUDIT_<YYYY-MM>.md`.

## 2. Baseline đã có (giữ, đừng phá)

- **Nén:** `_bootstrap.php` bật gzip (`ob_gzhandler`) + brotli; `.htaccess` nén
  CSS/JS/HTML. ⚠️ Nếu có extension brotli, hiện **nén 2 lần** (gzip bọc brotli) —
  một lỗi hiệu năng cần sửa (xem review).
- **Tải 2 bước:** `data.php?part=core` (app dùng ngay) → `part=heavy` (điểm
  danh/điểm, tải nền). Cache 60s + **ETag 304** (không tải lại nếu không đổi).
- **Phân trang** thiếu nhi (`page`/`limit`, mặc định 100, trần 500).
- **Bundle:** `bundle.php` nối toast + modules + app.js thành 1 tệp; phục vụ
  `bundle.min.js` nếu mới hơn (`build/minify.cjs`). CSS gộp tương tự.
- **Asset bất biến:** `?v=<mtime>` + cache 1 năm (`.htaccess`), SW cache-first.
- **Font tự host** (woff2, preload); **Lucide rút gọn 16KB** (76 icon) thay vì 410KB.
- **Index DB:** `schema.sql` có nhiều `ADD INDEX` (att_student_date, enr_year_status…).
- **APCu** cache cho `db_has_table/column` + RateLimiter (nếu host có).

## 3. Điểm nóng cần soi (đã thấy dấu hiệu)

- **N+1 truy vấn trong kiểm quyền:** `permission_of_role()`, `can_access_class()`,
  `assignment_covers_class()` truy vấn DB mỗi lần gọi, không nhớ kết quả — gọi
  trong vòng lặp (nhiều em/lớp) nhân số truy vấn. **Nên memo-hoá** trong một
  request.
- **`data.php` cho admin:** phạm vi toàn đoàn → payload lớn (hàng trăm em +
  điểm danh). Kiểm kích thước thật; `stamp_summaries_bulk` đã gộp (tốt) — đừng
  quay lại gọi lẻ trong vòng lặp.
- **Trang vỡ lazy (#194):** `x-if`→`x-show` khiến ~22 màn dựng sẵn (~6.100 node,
  HTML ~2.3MB). Nặng máy yếu — cân nhắc quay lại lazy-mount.
- **`lazy_modules`** (qrscan/stats/analytics) khai trong manifest nhưng **vẫn
  nằm trong bundle chính** — chưa thật sự tách tải.
- **Nén 2 lần brotli+gzip** (xem mục 2).

## 4. Checklist

**Tải trang / mạng**
- [ ] Trang chủ dùng được trong < ~3s trên 4G mô phỏng (throttle Fast/Slow 3G).
- [ ] Không tải tài nguyên thừa khi mở lại app (SW trả cache cho asset có `?v=`).
- [ ] Payload `data.php` hợp lý theo vai; GLV lớp nhẹ hơn admin rõ rệt.
- [ ] Không nén 2 lần; `Content-Encoding` đúng một giá trị.

**DB**
- [ ] Mọi truy vấn trên đường nóng (điểm danh, `data.php`, tra cứu) có index phủ.
- [ ] Không N+1: kiểm quyền/Mộc gộp hoặc memo trong request.
- [ ] `EXPLAIN` các truy vấn mới không `filesort`/`full scan` trên bảng lớn.

**Front-end**
- [ ] Bundle JS/CSS được minify phục vụ ở production (bundle.min.js mới hơn nguồn).
- [ ] Số node DOM hợp lý; màn không dùng thì không dựng sẵn (ưu tiên lazy).
- [ ] Ảnh `loading="lazy"`, dùng bản webp/avif đã tối ưu (`optimize:images`).

**PWA**
- [ ] `manifest.json` + `sw.js` hợp lệ; cài được "Thêm vào màn hình chính".
- [ ] Chạy offline tối thiểu (asset tĩnh); API luôn đi mạng (đúng thiết kế).

## 5. Công cụ & lệnh

```bash
# Lighthouse (mobile) qua Chromium headless — điểm Performance + PWA + Best Practices
npx --yes lighthouse http://127.0.0.1:8080/ --preset=desktop --quiet --chrome-flags="--headless" \
  --output=json --output-path=./lh.json   # đổi --preset / thêm --throttling cho mobile

# Đo payload data.php theo vai (đăng nhập rồi):
curl -s -b cookie.txt "http://127.0.0.1:8080/api/data.php?part=all" -o /dev/null -w "%{size_download} bytes\n"

# Đếm truy vấn: bật general_log tạm trên MariaDB test, hoặc đếm trong code
mysql -uroot tntt_test -e "SET GLOBAL general_log=1; SET GLOBAL general_log_file='/tmp/q.log'"
# ... thao tác ... rồi: grep -c "^.*Query" /tmp/q.log

# Kích thước bundle
curl -s http://127.0.0.1:8080/assets/js/bundle.php | wc -c
```

- **Playwright** (khung ở `TESTING.md`): đo `performance.timing`, số request,
  chụp timeline; throttle mạng bằng CDP (`Network.emulateNetworkConditions`).
- **Biểu đồ** (nếu báo cáo có số liệu so sánh): dùng skill `dataviz`.

## 6. Báo cáo

Mỗi finding: **mức** (🔴 chặn dùng mạng yếu / 🟠 chậm rõ / 🟡 tối ưu được / 🔵 nhỏ),
**chỗ** (endpoint/truy vấn/asset), **số đo trước** (ms/KB/#query), **cách sửa**,
**số đo sau** (bắt buộc cho PR hiệu năng — chứng minh cải thiện). Toàn-app:
`docs/audit/PERF_AUDIT_<YYYY-MM>.md`.

## 7. Gắn quy trình

PR gắn nhãn `performance` phải kèm **số đo trước/sau**. Cân nhắc thêm job CI đo
kích thước bundle + Lighthouse (ngưỡng tối thiểu) — xem `GITHUB_SETUP.md`.

---
_Cập nhật khi đổi cơ chế tải (bundle, cache, part) hoặc thêm đường nóng._
