# Quy Trình Kiểm Thử

> Nguyên tắc: **không tuyên bố "chạy được" nếu chưa chạy và dán được kết quả.**
> CI hiện tại kiểm PHP + JS tĩnh nhưng **chưa** mở app trong trình duyệt — chính
> khoảng trống đó để lọt lỗi #194 (app vỡ nhưng PHPUnit + ESLint vẫn xanh). Tài
> liệu này mô tả đủ các tầng để lấp khoảng trống đó.

---

## 1. Các tầng kiểm thử

| Tầng | Công cụ | Bắt lỗi gì | Chạy ở |
|------|---------|-----------|--------|
| Cú pháp PHP | `php -l` | Lỗi parse | Máy + CI |
| Unit PHP | PHPUnit 10 (`tests/unit/`) | Logic quyền, tính toán, validate | Máy + CI |
| Lint JS | ESLint 9.39.5 | Trùng khóa, lỗi tĩnh | Máy + CI |
| **Gộp module JS** | Script Node (mục 5) | **Trùng tên thuộc tính giữa mảnh** | Máy + CI (nên thêm) |
| **Smoke trình duyệt** | Playwright + Chromium | `pageerror`, `console.error`, điều hướng, tìm kiếm | Máy + CI (nên thêm) |
| Phân quyền / bảo mật | PHPUnit + kịch bản HTTP | IDOR, leo thang, rò rỉ | Máy + CI |
| E2E (có sẵn) | `tests/e2e/*` | Luồng đầy-đuôi | Thủ công/định kỳ |

CI hiện chạy 3 job: **PHPUnit Tests**, **PHP Syntax Check**, **JavaScript Lint**
(`.github/workflows/ci.yml`). Mục tiêu: thêm **Gộp module JS** và **Smoke trình
duyệt** thành job bắt buộc (xem `docs/process/GITHUB_SETUP.md`).

---

## 2. Dựng môi trường test ở máy

Khớp CI: MariaDB 10.11, biến `TNTT_DB_*`, cài schema rồi nạp fixture.

```bash
# 1. Khởi động MariaDB (cài sẵn) và tạo DB + user test
mysqld_safe --user=mysql &           # chờ vài giây tới khi 'mysqladmin ping' OK
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS tntt_test
   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER IF NOT EXISTS 'tntt'@'127.0.0.1' IDENTIFIED BY 'tntt';
   GRANT ALL ON *.* TO 'tntt'@'127.0.0.1'; FLUSH PRIVILEGES;"

# 2. Biến môi trường DB (config/db.php đọc các biến này, ghi đè config.php)
export TNTT_DB_HOST=127.0.0.1 TNTT_DB_NAME=tntt_test TNTT_DB_USER=tntt TNTT_DB_PASS=tntt

# 3. Cài schema + nạp fixture cố định cho test
php config/install.php
php tests/fixtures/ci_seed.php        # tạo HS001..HS003, ghi danh năm 1 / lớp 1
```

Fixture (`tests/fixtures/ci_seed.php`) **idempotent**, chỉ dùng cho DB test/dev
trống — **không bao giờ** chạy trên DB thật.

---

## 3. Chạy các kiểm tra tĩnh (y hệt CI)

```bash
# Cú pháp PHP toàn repo (đỏ nếu bất kỳ file lỗi)
find . -name '*.php' -not -path './vendor/*' -not -path './node_modules/*' \
  -not -path './.claude/*' -print0 | xargs -0 -n1 php -l

# PHPUnit (cần DB ở mục 2). Tải PHAR nếu chưa có:
curl -fsSL --retry 3 https://phar.phpunit.de/phpunit-10.phar -o phpunit.phar
php phpunit.phar --testsuite "TNTT Unit Tests"
#   Kỳ vọng: "OK (NNN tests)". CI còn kiểm sàn số test + 0 test bị skip.

# Lint JS (phiên bản GHIM để luật không đổi ngoài ý muốn)
npx --yes eslint@9.39.5 public/assets/js/ public/sw.js
```

PHPUnit cấu hình ở `phpunit.xml`: `failOnRisky` + `failOnWarning` bật → test
"rủi ro"/có cảnh báo cũng làm đỏ. Đừng tắt các cờ này.

---

## 4. Smoke test trình duyệt (BẮT lỗi #194)

Chromium cài sẵn tại `/opt/pw-browsers/chromium-*`. Mục tiêu: mở app thật, đổi
qua từng màn, gõ tìm kiếm, bấm Back — và **đỏ nếu có bất kỳ `pageerror` hay
`console.error`** nào.

```bash
# Dựng server PHP trỏ vào DB test (giữ nguyên biến TNTT_DB_* ở mục 2)
php -S 127.0.0.1:8080 -t public &

# Đặt must_change_pw=0 cho admin để đăng nhập thẳng trong test
mysql -uroot tntt_test -e "UPDATE members SET must_change_pw=0 WHERE role_code='admin'"
```

Khung script (lưu `tests/e2e/smoke.js`, chạy `node tests/e2e/smoke.js`):

```js
const { chromium, devices } = require('playwright');
const BASE = process.env.E2E_BASE || 'http://127.0.0.1:8080';
const EXE  = process.env.PW_CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';

(async () => {
  const browser = await chromium.launch({ executablePath: EXE }).catch(() => chromium.launch());
  let failed = false;
  for (const dev of [{ name: 'mobile', opts: { ...devices['iPhone 13'] } },
                     { name: 'desktop', opts: { viewport: { width: 1366, height: 860 } } }]) {
    const ctx = await browser.newContext({ ...dev.opts, locale: 'vi-VN' });
    const page = await ctx.newPage();
    const errs = [];
    page.on('pageerror', e => errs.push('pageerror: ' + e.message));
    page.on('console', m => { if (m.type() === 'error') errs.push('console.error: ' + m.text()); });

    // đăng nhập
    await page.goto(BASE + '/?dangnhap=1');
    await page.evaluate(async () => {
      await fetch('api/auth.php?action=login', { method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ phone: '0901000001', password: 'tntt@2026' }) });
    });
    await page.goto(BASE + '/');
    await page.waitForTimeout(2500);

    // đổi qua từng màn + kiểm currentModule đồng bộ
    const app = () => page.evaluate(() => {
      const el = document.querySelector('.app-shell');
      return el && window.Alpine ? window.Alpine.$data(el).currentModule : '?';
    });
    for (const mod of ['students', 'attendance', 'staff', 'settings', 'dashboard']) {
      await page.evaluate(m => window.Alpine.$data(document.querySelector('.app-shell')).changeModule(m), mod);
      await page.waitForTimeout(800);
      const cur = await app();
      if (cur !== mod) errs.push(`changeModule(${mod}) nhưng currentModule=${cur}`);
    }
    // tìm kiếm (chạm normalizeText)
    await page.evaluate(() => window.Alpine.$data(document.querySelector('.app-shell')).changeModule('students'));
    const search = page.locator('[data-module="students"] input').first();
    if (await search.count()) { await search.fill('an'); await page.waitForTimeout(500); }
    // nút Back trình duyệt
    await page.goBack(); await page.waitForTimeout(500);

    if (errs.length) { failed = true; console.error(`[${dev.name}]\n  ` + [...new Set(errs)].join('\n  ')); }
    else console.log(`[${dev.name}] OK`);
    await ctx.close();
  }
  await browser.close();
  process.exit(failed ? 1 : 0);   // đỏ nếu có lỗi
})();
```

> Script này chạy thử trong đợt rà soát đã bắt đúng 3 lỗi #194:
> `this.normalizeText is not a function`, `currentModule` không đổi theo router,
> và lỗi `reading 'state'` khi bấm Back.

---

## 5. Kiểm trùng tên khi gộp module JS

App gộp ~28 mảnh `modules/*.js` vào một component Alpine bằng
`Object.defineProperties` — **trùng tên thì mảnh sau đè mảnh trước, không báo**.
ESLint không bắt được vì các mảnh ở file khác nhau. Thêm kiểm này (lưu
`build/check_module_merge.cjs`, chạy `node build/check_module_merge.cjs`):

```js
const fs = require('fs'), vm = require('vm');
const manifest = require('../public/assets/asset_manifest.php'.replace(/\.php$/, '')) // nếu đọc được
  || null;
// Danh sách mảnh: đọc từ asset_manifest.php (nguồn duy nhất)
const man = fs.readFileSync('public/assets/asset_manifest.php', 'utf8');
const mods = (man.match(/'js_modules'\s*=>\s*\[([\s\S]*?)\]/)[1].match(/'([^']+)'/g) || [])
  .map(s => s.replace(/'/g, ''));
const ctx = { window: {}, document: { addEventListener() {}, querySelector: () => null },
  console, navigator: { userAgent: '' }, localStorage: { getItem() {}, setItem() {} },
  location: { href: '', pathname: '/' }, setTimeout, Intl, Date, Math, JSON };
ctx.window = ctx; vm.createContext(ctx);
for (const m of mods) {
  try { vm.runInContext(fs.readFileSync(`public/assets/js/modules/${m}.js`, 'utf8'), ctx, { filename: m }); }
  catch (e) { console.error('Lỗi nạp', m, e.message); process.exit(1); }
}
const owners = {};
for (const m of mods) {
  const o = ctx.TNTT && ctx.TNTT[m]; if (!o) { console.error('thiếu', m); process.exit(1); }
  for (const k of Object.keys(Object.getOwnPropertyDescriptors(o))) (owners[k] ||= []).push(m);
}
// Khóa được phép trùng có chủ đích (khai báo rõ ở đây):
const ALLOW = new Set(['currentModule', 'init']);
const dup = Object.entries(owners).filter(([k, v]) => v.length > 1 && !ALLOW.has(k));
if (dup.length) { console.error('TRÙNG TÊN giữa các mảnh:\n' +
  dup.map(([k, v]) => `  ${k}: ${v.join(', ')}`).join('\n')); process.exit(1); }
console.log('OK — không có trùng tên ngoài danh sách cho phép');
```

Khi cố ý cho trùng (vd `init` ở nhiều mảnh được gộp có trật tự), thêm tên vào
`ALLOW` **kèm lý do** — để trùng ngoài ý muốn vẫn đỏ.

---

## 6. Test phân quyền / bảo mật

Dữ liệu ở đây là hồ sơ trẻ em → phân quyền là chốt sống còn. Với **mỗi** endpoint
đụng dữ liệu theo lớp, viết test kiểu "kẻ tấn công":

- Tạo GLV phụ trách lớp A, rồi gọi endpoint với `classId`/`studentId` của **lớp B**
  → phải 403 / không trả dữ liệu lớp B.
- Người **chưa** được phân lớp (phạm vi `[]`) gọi `data.php?classId=`/`?programId=`
  → không được thấy dữ liệu ngoài phạm vi (xem S3 trong SECURITY_AUDIT).
- Endpoint công khai (`somoc_order`, `tracuu`): sai mã/sai ngày sinh → lỗi gộp,
  không lộ thông tin; vượt ngưỡng → 429.
- Tài nguyên tĩnh nhạy cảm: tải `/cache/<md5>.json` **không cookie** → phải
  403/404 (hồi quy cho S1).

Repo đã có khung `tests/unit/P1ApiHarness.php` và nhiều `*Test.php` phân quyền
(`PermissionTest`, `ScopeTest`, `DataScopeTest`, `PermissionHardeningTest`…) — thêm
test mới vào cùng khuôn. Khi thêm endpoint mới, thêm ngay một ca "lớp khác → 403".

---

## 7. Checklist TRƯỚC KHI MERGE

- [ ] `php -l` toàn repo: xanh.
- [ ] `phpunit` trên DB thật: xanh, số test không giảm, 0 skip.
- [ ] `eslint@9.39.5`: xanh.
- [ ] `check_module_merge`: không trùng tên ngoài danh sách cho phép.
- [ ] `smoke.js`: xanh ở cả mobile + desktop, **0** pageerror/console.error.
- [ ] Nếu đụng quyền/dữ liệu: có test "lớp khác → 403" và đã chạy.
- [ ] Nếu đụng UI: có ảnh chụp trước/sau.
- [ ] CI xanh trên PR.

## 8. Checklist REVIEW (người duyệt)

- [ ] Đọc **từng dòng bị xóa** — có nơi nào còn gọi tới không? (`grep`)
- [ ] Endpoint ghi: có `require_write` + `require_permission` + kiểm phạm vi
      theo-đối-tượng chưa?
- [ ] Dữ liệu từ client có được coi là không tin cậy (validate, ép kiểu, escape)?
- [ ] Có `INSERT IGNORE`/`catch` nào đang nuốt lỗi thật không?
- [ ] Có thêm nguồn-sự-thật thứ hai (danh sách module, trạng thái) không?
- [ ] Output ra HTML có escape? Output ra JSON có lộ trường thừa?
- [ ] PR có đúng một mục đích không?

## 9. Checklist TRƯỚC KHI TRIỂN KHAI (host)

- [ ] `master` xanh.
- [ ] Nếu đổi schema: đã chuẩn bị `install.php`/migration chạy trên host.
- [ ] `config/config.local.php` trên host có secret thật (VAPID, DB) — **không**
      lấy từ `config.php` trong git.
- [ ] `public/cache/` không phục vụ được từ ngoài (xem S1).
- [ ] Sau deploy: mở app thật một lượt (đăng nhập, đổi màn, một thao tác ghi).

---
_Khi thêm job CI mới hay đổi cách dựng DB, cập nhật tài liệu này cùng PR._
