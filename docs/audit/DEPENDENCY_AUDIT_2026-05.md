# Báo Cáo Audit Phụ Thuộc — GĐGL Phú Trung
**Ngày:** 2026-05-10
**Phạm vi:** Kiểm kê thư viện + CVE
**Người thực hiện:** Claude Code (agent)

---

## Tổng quan

| Mức | Số | Mã |
|-----|----|----|
| 🟠 Cao | 2 | DEP-1, DEP-NEW-1 |
| 🟡 Trung bình | 2 | DEP-2, DEP-NEW-2 |
| 🔵 Thấp / Thông tin | 1 | DEP-NEW-3 |

---

## Thư viện đang dùng

### JS Vendor (trình duyệt)

| Thư viện | Phiên bản | Nguồn | Kích thước | CVE |
|----------|-----------|--------|-------------|-----|
| **Alpine.js** | 3.15.0 | `vendor/alpine.js` | 44.8 KB | Không |
| **Alpine collapse** | — | `vendor/alpine-collapse.js` | 1.4 KB | Không |
| **SheetJS (xlsx)** | 0.20.3 | `vendor/xlsx.core.min.js` | 507 KB | ⚠️ **CVE-2024-22363**, **CVE-2023-30533** |
| **jsQR** | 1.4.0 | `vendor/jsQR.min.js` | 130 KB | Không |
| **qrcode-generator** | 1.4.4 | `vendor/qrcode.min.js` | 20 KB | ⚠️ **MAL-2026-6061** (potential) |
| **Lucide Icons** | 1.34.0 | `vendor/lucide-icons.js` | 25 KB | Không |

### PHP Vendor

| Thư viện | Bản | Nguồn | Ghi chú |
|-----------|------|--------|---------|
| **lbuchs/WebAuthn** | Unknown | `api/webauthn/` | Không rõ version |

### npm (dev dependencies)

| Thư viện | Phiên bản | Ghi chú |
|-----------|-----------|---------|
| esbuild | ^0.28.2 | Build tool |
| playwright | ^1.63.0 | E2E testing |
| eslint | ^9.15.0 | Linting |
| tailwindcss | ^3.2.7 | CSS framework |
| sharp | ^0.33.5 | Image processing |
| typescript | ^7.0.2 | Type checking |
| autoprefixer | ^10.4.16 | CSS prefixer |
| postcss | ^8.4.31 | CSS processing |

---

## Vấn đề

### 🟠 DEP-1 — SheetJS 0.20.3 có CVE chưa vá

**CVE-2024-22363** - Regular Expression Denial of Service (ReDoS)
- Mức độ: Cao
- Mô tả: Thư viện có thể bị tấn công DoS qua regular expression độc hại
- Khuyến nghị: Nâng cấp lên phiên bản mới nhất (>=0.20.4)

**CVE-2023-30533** - Prototype Pollution
- Mức độ: Cao
- Mô tả: Lỗ hổng prototype pollution trong xlsx
- Khuyến nghị: Nâng cấp lên phiên bản mới nhất

**Cách sửa:**
```bash
# Download bản mới
curl -o public/assets/js/vendor/xlsx.core.min.js https://cdn.sheetjs.com/xlsx-0.20.4/package/dist/xlsx.core.min.js
# Hoặc qua npm
npm install xlsx --save-dev
```

---

### 🟠 DEP-NEW-1 — qrcode-generator potential supply chain issue

**MAL-2026-6061** - Malicious Code / Supply Chain Attack
- Mức độ: Cao (cảnh báo)
- Mô tả: Package npm `qrcode-generator-node` bị inject mã độc
- Lưu ý: Repo sử dụng bản bundle JS từ jsDelivr. Cần xác minh file hiện tại không bị ảnh hưởng.

**Cách xác minh:**
```bash
# Kiểm tra hash của file
sha256sum public/assets/js/vendor/qrcode.min.js
```

**Cách sửa:** Cân nhắc thay bằng thư viện khác như `qrcode` (npm) hoặc tự generate.

---

### 🟡 DEP-2 — Lucide Icons phiên bản cũ

- Phiên bản 1.34.0 đã cũ (hiện tại đã có 2.x)
- Không có CVE nhưng nên cập nhật để nhận bug fixes
- **Cách sửa:** Chạy `node build/tao_lucide.cjs` để build bản mới

---

### 🟡 DEP-NEW-2 — package.json có dependencies không cần thiết

**Vấn đề:** File `package.json` có các package trong `dependencies` thực ra là phụ thuộc kéo theo của `sharp`:

```json
"dependencies": {
    "color": "^4.2.3",
    "color-convert": "^2.0.1",
    "color-name": "^1.1.4",
    "color-string": "^1.9.1",
    "detect-libc": "^2.1.2",
    "is-arrayish": "^0.3.4",
    "semver": "^7.8.5",
    "simple-swizzle": "^0.2.4"
}
```

**Ảnh hưởng:** Không ảnh hưởng production vì app chạy **không** cần node. Nhưng làm noise trong audit.

**Cách sửa:**
```bash
# Chuyển sharp về devDeps
npm install --save-dev sharp
# Xóa dependencies không cần
npm prune
```

---

### 🔵 DEP-NEW-3 — ESLint version chưa ghim trong package.json

- ESLint `^9.15.0` trong package.json nhưng CLAUDE.md ghi `eslint@9.39.5`
- Nên ghim version trong package.json để nhất quán

---

## Kiểm kê Checklist

- [x] Bảng kiểm kê khớp tệp thật
- [ ] **DEP-1:** SheetJS cần nâng cấp (CVE)
- [ ] **DEP-NEW-1:** qrcode-generator cần xác minh
- [ ] **DEP-2:** Lucide cần cập nhật
- [ ] **DEP-NEW-2:** package.json cần dọn
- [ ] **DEP-NEW-3:** ESLint version nên ghim

---

## Khuyến nghị

### Ưu tiên cao
1. **DEP-1:** Nâng cấp SheetJS lên bản mới nhất (CVE)
2. **DEP-NEW-1:** Xác minh qrcode-generator

### Ưu tiên trung bình
3. **DEP-2:** Cập nhật Lucide Icons lên phiên bản mới nhất

### Dọn dẹp
4. **DEP-NEW-2:** Dọn package.json dependencies
5. **DEP-NEW-3:** Gim ESLint version

---

## Lưu ý

- Thư viện JS được **tự nhúng** (self-host), không qua npm lúc chạy
- Dependabot **không** theo dõi được các thư viện tự nhúng này
- → Cần rà **thủ công** định kỳ (vd quý)
- Script audit: `npx --yes eslint@9.39.5 public/assets/js/ public/sw.js`
