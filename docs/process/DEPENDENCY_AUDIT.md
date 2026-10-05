# Quy Ước Audit Phụ Thuộc / Chuỗi Cung Ứng

> Kiểm các thư viện bên ngoài: bản nào, có CVE không, cập nhật kỷ luật. Đặc thù
> app này: **thư viện JS được tự nhúng** (self-host, không qua npm lúc chạy),
> một **thư viện PHP WebAuthn được vendor sẵn**, và npm chỉ dùng cho build/test.
> Tự nhúng = Dependabot **không** theo dõi được → phải rà **thủ công**.

Phần thiết lập tự động (Dependabot, secret scan) ở `GITHUB_SETUP.md` §6; tài liệu
này nói **rà cái gì và cách cập nhật**.

---

## 1. Kiểm kê phụ thuộc

**JS tự nhúng — `public/assets/js/vendor/`** (chạy trên trình duyệt người dùng):

| Thư viện | Bản (đã xác minh) | Dựng lại | Ghi chú |
|----------|-------------------|----------|---------|
| Alpine.js | 3.15.0 | thay tệp | khung UI |
| Alpine collapse | — | thay tệp | plugin |
| SheetJS (xlsx) | 0.20.3 | thay tệp | xuất Excel; đã sửa font mặc định (ghi chú trong tệp) |
| jsQR | 1.4.0 | thay tệp | quét QR |
| qrcode-generator | 1.4.4 | thay tệp | in thẻ QR |
| Lucide (rút gọn) | 76 icon | `node build/tao_lucide.cjs` | thêm icon phải dựng lại |

> Tất cả bản trên **đã là bản vá tại thời điểm rà 05/10/2026.** Khi nâng cấp phải
> cập nhật bảng này + bản ghi chú trong tệp (SheetJS có sửa tay).

**PHP vendor — `public/api/webauthn/`** (lbuchs/WebAuthn, chạy trên máy chủ):
- ⚠️ **Không thấy chuỗi version** trong mã → nên ghi lại bản đang dùng (đối chiếu
  upstream `lbuchs/WebAuthn`) và theo dõi CVE của nó. Đây là code bảo mật (xác
  thực sinh trắc) nên ưu tiên.

**npm — chỉ build/test** (`package.json`, không lên host):
- devDeps thật: `esbuild, @playwright/test, playwright, eslint, tailwindcss,
  postcss, autoprefixer, sharp, typescript, @types/node`.
- ⚠️ `dependencies` liệt `color, color-convert, semver, is-arrayish…` — đây là
  **phụ thuộc kéo theo của `sharp`**, bị đưa nhầm vào `dependencies`. Nên dọn
  (chuyển `sharp` về devDeps là đủ, các gói kia tự theo).
- **PHP không dùng Composer** (không có phụ thuộc PHP qua package manager) — trừ
  lbuchs được vendor tay ở trên.

## 2. Checklist

- [ ] Bảng kiểm kê (mục 1) khớp tệp thật; mỗi thư viện ghi **bản + nguồn**.
- [ ] Không thư viện nào có **CVE chưa vá** ở bản đang dùng (tra theo tên+bản).
- [ ] lbuchs/WebAuthn: ghi bản + đã đối chiếu upstream có bản vá bảo mật nào chưa.
- [ ] `package.json`: `dependencies` dọn sạch (chỉ còn thứ thật sự cần lúc chạy —
      hiện app chạy **không** cần node nên gần như rỗng).
- [ ] Phiên bản công cụ CI **ghim** (eslint `9.39.5` đã ghim) để luật không đổi.
- [ ] Dependabot + security alerts bật (GITHUB_SETUP §6); PR Dependabot được xem.

## 3. Cập nhật kỷ luật (khi nâng một thư viện)

1. Đọc changelog + mục bảo mật của bản mới.
2. Thay tệp (JS vendor) hoặc bump (npm); **chạy lại test đầy đủ** (`TESTING.md`)
   + smoke trình duyệt — thư viện UI đổi dễ vỡ ngầm.
3. Nếu thư viện có bản "rút gọn/sửa tay" (Lucide, SheetJS): **dựng lại** bằng
   script tương ứng, đừng chép đè làm mất tùy biến.
4. Cập nhật bảng mục 1 + ghi bản trong commit. Một thư viện một PR.

## 4. Công cụ

```bash
# Tra bản vendor JS (dòng version/license đầu tệp)
head -5 public/assets/js/vendor/*.js
# npm: lỗ hổng trong devDeps
npm audit --omit=dev   # và/hoặc không cờ để xem cả dev
# Tìm CVE theo tên+bản: tra osv.dev / GitHub Advisory cho mỗi thư viện ở mục 1
```

## 5. Báo cáo & quy trình

Finding: **mức** (🔴 CVE khai thác được ở bản đang dùng / 🟠 lỗi thời nhiều bản /
🟡 thiếu ghi version / 🔵 dọn dẹp), **thư viện + bản + CVE**, **cách nâng**.
Toàn-app: `docs/audit/DEPENDENCY_AUDIT_<YYYY-MM>.md`.

---
_Vì thư viện tự nhúng không có Dependabot, đặt lịch rà thủ công định kỳ (vd quý)._
