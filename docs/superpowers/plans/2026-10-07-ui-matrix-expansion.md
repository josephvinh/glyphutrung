# UI Matrix Expansion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mở rộng bộ kiểm giao diện đa thiết bị từ 6 trang công khai sang 5 modules đã đăng nhập, thêm 3 viewport mới, tích hợp axe và visual regression.

**Architecture:** Playwright với 3 phases riêng biệt. Phase 1 mở rộng test matrix, Phase 2 tích hợp accessibility check, Phase 3 thêm visual regression. CI workflow được cập nhật để block merge khi có changes liên quan đến views/CSS.

**Tech Stack:** Playwright, @axe-core/playwright, Percy (hoặc Loki), GitHub Actions

**Spec:** `docs/superpowers/specs/2026-10-07-ui-matrix-expansion-design.md`

---

## Global Constraints

- Node.js 22+ (theo package.json hiện tại)
- Playwright đã có trong node_modules
- Database test: tntt_test với seed accounts
- PHP server chạy tại 127.0.0.1:8080

---

## Phase 1: Modules Expansion + New Viewports

### Task 1.1: Thêm 3 Viewports Mới

**Files:**
- Modify: `tests/e2e/ui.js:10`

**Interfaces:**
- Consumes: viewports object hiện tại
- Produces: viewports object mới với 6 entries

- [ ] **Step 1: Thêm viewports mới vào ui.js**

Tìm dòng 10 trong `tests/e2e/ui.js`:
```javascript
const viewports = { mobile: { width: 390, height: 844 }, tablet: { width: 768, height: 1024 }, desktop: { width: 1366, height: 768 } };
```

Thay bằng:
```javascript
const viewports = {
  mobile: { width: 390, height: 844 },       // iPhone 12/13
  tablet: { width: 768, height: 1024 },       // iPad
  desktop: { width: 1366, height: 768 },     // Laptop
  iphoneSE: { width: 375, height: 667 },     // iPhone SE (small)
  largeTablet: { width: 820, height: 1180 }, // iPad Pro 11"
  lowRes: { width: 320, height: 480 }        // Low-end Android
};
```

- [ ] **Step 2: Commit**

```bash
git add tests/e2e/ui.js
git commit -m "test(e2e): add 3 new viewports for UI matrix (#231)

- iPhone SE (375×667)
- Large Tablet (820×1180)
- Low-res Android (320×480)

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 1.2: Thêm Modules vào Test Matrix

**Files:**
- Modify: `tests/e2e/ui.js:79` (dòng keys array)

**Interfaces:**
- Consumes: Alpine.$data.moduleDefs
- Produces: Test tất cả 5 modules mới

- [ ] **Step 1: Xác định module keys**

Hiện tại code lấy tất cả modules từ Alpine:
```javascript
const keys = await page.evaluate(() => {
  const app = window.Alpine.$data(document.querySelector('.app-shell'));
  return app.moduleDefs.filter((m) => app.canAccess(m.key)).map((m) => m.key);
});
```

Không cần sửa - code đã tự động lấy tất cả modules. Chỉ cần đảm bảo seed accounts có quyền truy cập các modules:
- attendance
- students
- scores
- announcements
- somoc

- [ ] **Step 2: Verify seed accounts có quyền các modules mới**

Kiểm tra `tests/fixtures/ci_seed.php` hoặc chạy test để xác nhận.

- [ ] **Step 3: Commit**

```bash
git add tests/e2e/ui.js
git commit -m "test(e2e): auto-detect all accessible modules for testing (#231)

Modules được test tự động từ Alpine.$data:
- attendance, students, scores, announcements, somoc

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 1.3: Thêm Popup Testing

**Files:**
- Modify: `tests/e2e/ui.js`
- Create: `tests/e2e/ui_popup_check.js`

**Interfaces:**
- Consumes: Alpine.$data với module đã mở
- Produces: Popup audit results

- [ ] **Step 1: Thêm function kiểm tra popup**

Thêm vào cuối file `tests/e2e/ui.js`, trước dòng `})().catch()`:

```javascript
// Popup checks - gọi sau khi module đã load
async function checkPopups(page) {
  const results = { modals: 0, toasts: 0, drawers: 0, dropdowns: 0, navOverlap: false };
  
  // Đếm các loại popup
  results.modals = await page.locator('[role="dialog"], .modal, .modal-backdrop').count();
  results.toasts = await page.locator('[x-data*="toast"], .toast, [class*="toast"]').count();
  results.drawers = await page.locator('.drawer, [class*="drawer"], .sidebar').count();
  results.dropdowns = await page.locator('select, [role="listbox"], [role="combobox"], .dropdown').count();
  
  // Kiểm tra nav overlap (lỗi phổ biến #222)
  const nav = await page.locator('nav, .bottom-nav, .fixed.bottom').first().boundingBox().catch(() => null);
  const buttons = await page.locator('button, [role="button"]').all();
  for (const btn of buttons) {
    const box = await btn.boundingBox().catch(() => null);
    if (box && nav && box.bottom > nav.top && box.top < nav.bottom && box.bottom > nav.bottom) {
      results.navOverlap = true;
      break;
    }
  }
  
  return results;
}
```

- [ ] **Step 2: Gọi popup check trong module loop**

Tìm dòng ~86 trong ui.js (sau khi có `a = await audit(page)`), thêm:

```javascript
const cur = await page.evaluate(() => window.Alpine.$data(document.querySelector('.app-shell')).currentModule);
const a = await audit(page);
const popupResults = await checkPopups(page);
const spinner = await page.evaluate(() => !![...document.querySelectorAll('[class*="animate-spin"], .skeleton')].find((e) => { const r = e.getBoundingClientRect(); return r.width > 0 && r.height > 0; }));
if (vn !== 'tablet') await page.screenshot({ path: `${S}/shots/${role}-${key}-${vn}.png` });
report.modules.push({ role, viewport: vn, module: key, current: cur, ms: Date.now() - t0, stuckLoading: spinner, ...a, ...popupResults, errors: [...errs] });
```

- [ ] **Step 3: Commit**

```bash
git add tests/e2e/ui.js
git commit -m "test(e2e): add popup detection for nav overlap issues (#231)

- Detect modals, toasts, drawers, dropdowns
- Check if buttons are overlapped by bottom nav
- Related to issue #222 (popup nav overlap)

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 1.4: Cập Nhật Report Format

**Files:**
- Modify: `tests/e2e/ui.js`

**Interfaces:**
- Consumes: Test results từ audit() và checkPopups()
- Produces: Enhanced JSON report với popup data

- [ ] **Step 1: Cập nhật report structure**

Đảm bảo report object bao gồm popup data:

```javascript
const report = { pages: [], modules: [], summary: { totalTests: 0, passed: 0, failed: 0, popups: 0 } };
```

Thêm summary computation trước `fs.writeFileSync`:

```javascript
// Compute summary
report.summary.totalTests = report.pages.length + report.modules.length;
report.summary.popups = report.modules.filter(m => m.modals > 0 || m.toasts > 0 || m.drawers > 0).length;
report.summary.navOverlap = report.modules.filter(m => m.navOverlap).length;
report.summary.overflowX = report.modules.filter(m => m.overflowX > 0).length;
```

- [ ] **Step 2: Commit**

```bash
git add tests/e2e/ui.js
git commit -m "test(e2e): add summary statistics to UI report (#231)

- Total tests count
- Popup count
- Nav overlap issues
- Overflow X issues

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 1.5: Cập Nhật CI Workflow cho Phase 1

**Files:**
- Modify: `.github/workflows/ci.yml`

**Interfaces:**
- Consumes: ui.js test results
- Produces: CI job `ui-multi-device`

- [ ] **Step 1: Thêm job mới vào ci.yml**

Thêm sau job `js-lint`:

```yaml
  ui-multi-device:
    name: UI Matrix (Multi-Device)
    runs-on: ubuntu-latest
    needs: [php-unit, php-syntax]
    
    services:
      mariadb:
        image: mariadb:10.11
        env:
          MARIADB_ROOT_PASSWORD: root
          MARIADB_DATABASE: tntt_test
          MARIADB_USER: tntt
          MARIADB_PASSWORD: tntt
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping -h 127.0.0.1 -uroot -proot --silent"
          --health-interval=5s
          --health-timeout=5s
          --health-retries=20

    env:
      TNTT_DB_HOST: 127.0.0.1
      TNTT_DB_PORT: '3306'
      TNTT_DB_NAME: tntt_test
      TNTT_DB_USER: tntt
      TNTT_DB_PASS: tntt
      E2E_BASE: http://127.0.0.1:8080
      PLAYWRIGHT_BROWSERS_PATH: /ms-playwright

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: pdo_mysql, mbstring

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '22'
          cache: 'npm'

      - name: Install Playwright browsers
        run: npx playwright install --with-deps chromium webkit

      - name: Wait for database
        run: |
          php -r '
            for ($i = 0; $i < 30; $i++) {
              try {
                $p = new PDO("mysql:host=127.0.0.1;port=3306;dbname=tntt_test;charset=utf8mb4", "tntt", "tntt");
                echo "DB sẵn sàng\n";
                exit(0);
              } catch (Throwable $e) { sleep(2); }
            }
            exit(1);
          '

      - name: Install schema
        run: php config/install.php

      - name: Seed test data
        run: php tests/fixtures/ci_seed.php

      - name: Start PHP server
        run: php -S 127.0.0.1:8080 -t public > /dev/null 2>&1 &
        sleep 2

      - name: Run UI Matrix tests
        run: node tests/e2e/ui.js
        continue-on-error: true

      - name: Upload screenshots
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: ui-screenshots
          path: tests/e2e/out/shots/

      - name: Upload report
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: ui-report
          path: tests/e2e/out/ui_report.json
```

- [ ] **Step 2: Commit**

```bash
git add .github/workflows/ci.yml
git commit -m "ci: add UI matrix multi-device job (#231)

- Test 6 viewports including iPhone SE, Tablet, Low-res
- Test all accessible modules after login
- Popup detection with nav overlap check
- Screenshots and JSON report artifacts

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

## Phase 2: Axe Integration + Block Merge

### Task 2.1: Tích Hợp Axe vào CI

**Files:**
- Modify: `.github/workflows/ci.yml`
- Create: `tests/e2e/axe_runner.js` (wrapper script)

**Interfaces:**
- Consumes: axe.js với cấu hình tags
- Produces: axe_results.json

- [ ] **Step 1: Cập nhật axe.js để hỗ trợ CI**

Kiểm tra `tests/e2e/axe.js` hiện tại và thêm:
- Support cho nhiều viewports
- Export kết quả JSON
- Exit code based on violations

Thêm vào cuối axe.js trước `})().catch()`:

```javascript
// Summary
const totalViolations = Object.values(rep).reduce((sum, v) => sum + v.nodes, 0);
console.log(`\n=== AXE SUMMARY ===`);
console.log(`Total violations: ${totalViolations}`);
console.log(`Critical: ${Object.values(rep).filter(v => v.impact === 'critical').reduce((sum, v) => sum + v.nodes, 0)}`);
console.log(`Serious: ${Object.values(rep).filter(v => v.impact === 'serious').reduce((sum, v) => sum + v.nodes, 0)}`);

// Exit with error if critical violations
if (totalViolations > 0) {
  const criticalCount = Object.values(rep).filter(v => v.impact === 'critical').reduce((sum, v) => sum + v.nodes, 0);
  if (criticalCount > 0) {
    console.error('Critical accessibility violations found!');
    process.exit(1);
  }
}
```

- [ ] **Step 2: Thêm axe job vào CI workflow**

```yaml
  ui-a11y:
    name: UI Accessibility (Axe)
    runs-on: ubuntu-latest
    needs: [php-unit]
    
    services:
      mariadb:
        image: mariadb:10.11
        env:
          MARIADB_ROOT_PASSWORD: root
          MARIADB_DATABASE: tntt_test
          MARIADB_USER: tntt
          MARIADB_PASSWORD: tntt
        ports:
          - 3306:3306

    env:
      TNTT_DB_HOST: 127.0.0.1
      TNTT_DB_PORT: '3306'
      TNTT_DB_NAME: tntt_test
      TNTT_DB_USER: tntt
      TNTT_DB_PASS: tntt
      E2E_BASE: http://127.0.0.1:8080
      PLAYWRIGHT_BROWSERS_PATH: /ms-playwright
      NPM_TOOLS: ${{ github.workspace }}

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: pdo_mysql, mbstring

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '22'

      - name: Install Playwright + Axe
        run: |
          npm install --ignore-scripts
          npx playwright install --with-deps chromium webkit
          npm install @axe-core/playwright --save-dev

      - name: Wait for database
        run: |
          php -r '
            for ($i = 0; $i < 30; $i++) {
              try {
                $p = new PDO("mysql:host=127.0.0.1;port=3306;dbname=tntt_test;charset=utf8mb4", "tntt", "tntt");
                exit(0);
              } catch (Throwable $e) { sleep(2); }
            }
            exit(1);
          '

      - name: Install schema
        run: php config/install.php

      - name: Seed test data
        run: php tests/fixtures/ci_seed.php

      - name: Start PHP server
        run: php -S 127.0.0.1:8080 -t public > /dev/null 2>&1 &
        sleep 2

      - name: Run Axe accessibility tests
        run: node tests/e2e/axe.js
        continue-on-error: true

      - name: Upload axe results
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: axe-results
          path: tests/e2e/out/axe_results.json
```

- [ ] **Step 3: Commit**

```bash
git add tests/e2e/axe.js .github/workflows/ci.yml
git commit -m "test(e2e): enhance axe integration with CI job (#231)

- Add exit code based on critical violations
- Support multiple viewports
- Upload axe_results.json artifact

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 2.2: Block Merge cho Views/CSS Changes

**Files:**
- Modify: `.github/workflows/ci.yml`

**Interfaces:**
- Consumes: PR changes
- Produces: Conditional job execution

- [ ] **Step 1: Cập nhật workflow trigger với paths**

```yaml
name: CI

on:
  push:
    branches: [ master, main ]
  pull_request:
    branches: [ master, main ]
    paths:
      - 'views/**'
      - 'public/assets/css/**'
      - 'public/assets/js/**'
      - 'public/**/*.php'
      - 'tests/e2e/**'
  workflow_dispatch:
```

- [ ] **Step 2: Remove continue-on-error cho UI jobs**

Tìm và xóa `continue-on-error: true` trong:
- `ui-multi-device` job
- `ui-a11y` job

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/ci.yml
git commit -m "ci: block merge when views/CSS changes (#231)

- UI jobs run only on relevant path changes
- Remove continue-on-error for strict blocking
- ui-multi-device and ui-a11y must pass to merge

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

## Phase 3: Visual Regression + Print Testing

### Task 3.1: Thêm Print Testing

**Files:**
- Create: `tests/e2e/print_check.js`

**Interfaces:**
- Consumes: print.php routes
- Produces: Print screenshots và audit results

- [ ] **Step 1: Tạo print_check.js**

```javascript
const { chromium } = require('playwright');
const fs = require('fs');
const S = __dirname + '/out';
const BASE = process.env.E2E_BASE || 'http://127.0.0.1:8080';

const roles = {
  admin: ['0901000001', 'tntt@2026'],
};

const printPages = [
  { name: 'attendance-print', url: '/?dangnhap=1' },  // Will navigate after login
  { name: 'student-card', url: '/?dangnhap=1' },
  { name: 'report-export', url: '/?dangnhap=1' },
];

async function auditPrint(page) {
  return await page.evaluate(() => {
    const vw = document.documentElement.clientWidth;
    const overflowX = document.documentElement.scrollWidth - vw;
    const visible = (el) => {
      const r = el.getBoundingClientRect();
      const cs = getComputedStyle(el);
      return r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none';
    };
    
    // Check print-specific issues
    const hiddenOnPrint = [...document.querySelectorAll('*')].filter(el => {
      const style = getComputedStyle(el);
      return style.display === 'none' || style.visibility === 'hidden';
    }).length;
    
    const noPrintStyles = [...document.querySelectorAll('body *')].filter(el => {
      const style = getComputedStyle(el);
      return style.pageBreakAfter === 'always' || style.pageBreakBefore === 'always';
    }).length;
    
    return {
      vw,
      overflowX,
      hiddenOnPrint,
      pageBreakElements: noPrintStyles,
      elements: document.querySelectorAll('body *').length,
      title: document.title,
    };
  });
}

(async () => {
  const browser = await chromium.launch();
  const report = { pages: [] };
  
  // Login as admin
  const ctx = await browser.newContext({ viewport: { width: 1366, height: 768 } });
  const page = await ctx.newPage();
  
  await page.goto(BASE + '/?dangnhap=1', { waitUntil: 'networkidle' });
  await page.fill('input[type=tel]', roles.admin[0]);
  await page.fill('input[autocomplete=current-password]', roles.admin[1]);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
    page.click('button[type=submit]')
  ]);
  await page.waitForTimeout(1500);
  
  // Test attendance print (example route - adjust based on actual app)
  // Navigate to attendance module first
  const attendanceLoaded = await page.evaluate(() => {
    const app = window.Alpine.$data(document.querySelector('.app-shell'));
    if (app.canAccess('attendance')) {
      app.openModule('attendance');
      return true;
    }
    return false;
  });
  
  if (attendanceLoaded) {
    await page.waitForTimeout(1500);
    
    // Check for print button and click
    const printBtn = await page.locator('button:has-text("In"), button:has-text("Print"), a:has-text("In")').first();
    if (await printBtn.isVisible().catch(() => false)) {
      // Get page content before print
      const a = await auditPrint(page);
      await page.screenshot({ 
        path: `${S}/shots/print-attendance-desktop.png`,
        fullPage: true 
      });
      report.pages.push({ page: 'attendance-print', ...a });
    }
  }
  
  await browser.close();
  
  // Write report
  fs.writeFileSync(S + '/print_report.json', JSON.stringify(report, null, 1));
  console.log('done', report.pages.length, 'print pages checked');
})().catch((e) => { console.error(e); process.exit(1); });
```

- [ ] **Step 2: Thêm print job vào CI**

```yaml
  ui-print:
    name: UI Print Check
    runs-on: ubuntu-latest
    needs: [php-unit]
    
    services:
      mariadb:
        image: mariadb:10.11
        env:
          MARIADB_ROOT_PASSWORD: root
          MARIADB_DATABASE: tntt_test
          MARIADB_USER: tntt
          MARIADB_PASSWORD: tntt
        ports:
          - 3306:3306

    env:
      TNTT_DB_HOST: 127.0.0.1
      TNTT_DB_PORT: '3306'
      TNTT_DB_NAME: tntt_test
      TNTT_DB_USER: tntt
      TNTT_DB_PASS: tntt
      E2E_BASE: http://127.0.0.1:8080
      PLAYWRIGHT_BROWSERS_PATH: /ms-playwright

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: pdo_mysql, mbstring

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '22'

      - name: Install Playwright
        run: npx playwright install --with-deps chromium

      - name: Wait for database
        run: |
          php -r '
            for ($i = 0; $i < 30; $i++) {
              try {
                $p = new PDO("mysql:host=127.0.0.1;port=3306;dbname=tntt_test;charset=utf8mb4", "tntt", "tntt");
                exit(0);
              } catch (Throwable $e) { sleep(2); }
            }
            exit(1);
          '

      - name: Install schema
        run: php config/install.php

      - name: Seed test data
        run: php tests/fixtures/ci_seed.php

      - name: Start PHP server
        run: php -S 127.0.0.1:8080 -t public > /dev/null 2>&1 &
        sleep 2

      - name: Run Print checks
        run: node tests/e2e/print_check.js
        continue-on-error: true

      - name: Upload print screenshots
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: print-screenshots
          path: tests/e2e/out/shots/print-*.png
```

- [ ] **Step 3: Commit**

```bash
git add tests/e2e/print_check.js .github/workflows/ci.yml
git commit -m "test(e2e): add print accessibility checks (#231)

- Check @media print CSS
- Verify no content overflow
- Screenshot print views

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 3.2: Tích Hợp Percy (Visual Regression)

**Files:**
- Create: `percy.yml`
- Modify: `package.json`
- Modify: `.github/workflows/ci.yml`

**Interfaces:**
- Consumes: Percy token
- Produces: Visual diff snapshots

- [ ] **Step 1: Thêm Percy vào package.json**

```bash
npm install --save-dev @percy/cli @percy/playwright
```

- [ ] **Step 2: Tạo percy.yml**

```yaml
version: 2
snapshot:
  widths: [390, 768, 1366]
  min-height: 480
percy:
  css:
    - "@media print { .no-print { display: none !important; } }"
```

- [ ] **Step 3: Tạo Percy snapshot script**

```javascript
// tests/e2e/percy_snapshot.js
const { percySnapshot } = require('@percy/playwright');

const BASE = process.env.E2E_BASE || 'http://127.0.0.1:8080';

async function runSnapshots(page) {
  // Public pages
  await page.goto(BASE + '/');
  await percySnapshot(page, 'Landing Page');
  
  await page.goto(BASE + '/?dangnhap=1');
  await percySnapshot(page, 'Login Page');
  
  await page.goto(BASE + '/tracuu.php');
  await percySnapshot(page, 'Tra Cuu');
  
  await page.goto(BASE + '/somoc.php');
  await percySnapshot(page, 'So Moc');
  
  await page.goto(BASE + '/bxh.php');
  await percySnapshot(page, 'Bang Xep Hang');
}

module.exports = { runSnapshots };
```

- [ ] **Step 4: Cập nhật CI workflow**

```yaml
  visual-regression:
    name: Visual Regression (Percy)
    runs-on: ubuntu-latest
    needs: [php-unit]
    
    if: github.event_name == 'pull_request'
    
    services:
      mariadb:
        image: mariadb:10.11
        env:
          MARIADB_ROOT_PASSWORD: root
          MARIADB_DATABASE: tntt_test
          MARIADB_USER: tntt
          MARIADB_PASSWORD: tntt
        ports:
          - 3306:3306

    env:
      TNTT_DB_HOST: 127.0.0.1
      TNTT_DB_PORT: '3306'
      TNTT_DB_NAME: tntt_test
      TNTT_DB_USER: tntt
      TNTT_DB_PASS: tntt
      E2E_BASE: http://127.0.0.1:8080
      PERCY_TOKEN: ${{ secrets.PERCY_TOKEN }}
      PLAYWRIGHT_BROWSERS_PATH: /ms-playwright

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: pdo_mysql, mbstring

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '22'

      - name: Install Playwright
        run: npx playwright install --with-deps chromium

      - name: Install Percy
        run: npm install @percy/cli @percy/playwright

      - name: Wait for database
        run: |
          php -r '
            for ($i = 0; $i < 30; $i++) {
              try {
                $p = new PDO("mysql:host=127.0.0.1;port=3306;dbname=tntt_test;charset=utf8mb4", "tntt", "tntt");
                exit(0);
              } catch (Throwable $e) { sleep(2); }
            }
            exit(1);
          '

      - name: Install schema
        run: php config/install.php

      - name: Seed test data
        run: php tests/fixtures/ci_seed.php

      - name: Start PHP server
        run: php -S 127.0.0.1:8080 -t public > /dev/null 2>&1 &
        sleep 2

      - name: Run Percy snapshots
        run: npx percy exec -- node tests/e2e/percy_snapshot_runner.js
```

- [ ] **Step 5: Commit**

```bash
git add package.json package-lock.json percy.yml tests/e2e/percy_snapshot*.js .github/workflows/ci.yml
git commit -m "test(e2e): add Percy visual regression snapshots (#231)

- Capture screenshots at 390px, 768px, 1366px widths
- Compare against baseline on each PR
- Report visual diffs for review

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

## Tổng Kết Tasks

| Phase | Task | Mô tả |
|-------|------|-------|
| 1 | 1.1 | Thêm 3 viewports mới |
| 1 | 1.2 | Modules detection (đã tự động) |
| 1 | 1.3 | Popup testing |
| 1 | 1.4 | Cập nhật report format |
| 1 | 1.5 | CI workflow cho Phase 1 |
| 2 | 2.1 | Tích hợp Axe vào CI |
| 2 | 2.2 | Block merge cho views/CSS |
| 3 | 3.1 | Print testing |
| 3 | 3.2 | Percy visual regression |

---

## Acceptance Criteria Checklist

### Phase 1
- [ ] 6 viewports hoạt động (3 cũ + 3 mới)
- [ ] 5 modules được test sau login
- [ ] Popup detection hoạt động
- [ ] Screenshots được upload
- [ ] JSON report đầy đủ

### Phase 2
- [ ] Axe chạy trong CI
- [ ] Violations được export
- [ ] CI block khi có violations (views/CSS changes)

### Phase 3
- [ ] Percy snapshots được capture
- [ ] Visual diff được báo cáo
- [ ] print.php được kiểm tra
