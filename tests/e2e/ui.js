const { chromium } = require('playwright');
const fs = require('fs');
const S = __dirname + '/out';
if (!fs.existsSync(S)) fs.mkdirSync(S, { recursive: true });
if (!fs.existsSync(S + '/shots')) fs.mkdirSync(S + '/shots', { recursive: true });
const BASE = process.env.E2E_BASE || 'http://127.0.0.1:8088';
const roles = {
  admin: ['0901000001', 'tntt@2026'],
  glv: ['0911000004', 'Test@1234'],
  thu_thu: ['0911000006', 'Test@1234'],
};
const viewports = {
  mobile: { width: 390, height: 844 },       // iPhone 12/13
  tablet: { width: 768, height: 1024 },       // iPad
  desktop: { width: 1366, height: 768 },     // Laptop
  iphoneSE: { width: 375, height: 667 },     // iPhone SE (small)
  largeTablet: { width: 820, height: 1180 }, // iPad Pro 11"
  lowRes: { width: 320, height: 480 }        // Low-end Android
};
const report = { pages: [], modules: [], summary: { totalTests: 0, passed: 0, failed: 0, popups: 0, navOverlap: 0, overflowX: 0 } };

async function audit(page) {
  return await page.evaluate(() => {
    const vw = document.documentElement.clientWidth;
    const overflowX = document.documentElement.scrollWidth - vw;
    const visible = (el) => { const r = el.getBoundingClientRect(); const cs = getComputedStyle(el); return r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none'; };
    const offenders = [];
    document.querySelectorAll('body *').forEach((el) => {
      if (!visible(el)) return;
      const r = el.getBoundingClientRect();
      if (r.right > vw + 2 && offenders.length < 5 && !el.closest('[class*="overflow-x"], .overflow-auto, table')) {
        offenders.push((el.tagName + '.' + (el.className && el.className.toString().slice(0, 50))).slice(0, 90) + ' right=' + Math.round(r.right));
      }
    });
    const imgsNoAlt = [...document.querySelectorAll('img')].filter((i) => visible(i) && !i.hasAttribute('alt')).length;
    const btnNoName = [...document.querySelectorAll('button, a[href], [role=button]')].filter((b) => visible(b) && !(b.innerText || '').trim() && !b.getAttribute('aria-label') && !b.getAttribute('title') && !(b.querySelector('img[alt]') || {}).alt).length;
    const inputs = [...document.querySelectorAll('input:not([type=hidden]), select, textarea')].filter(visible);
    const inputNoLabel = inputs.filter((i) => !i.getAttribute('aria-label') && !i.getAttribute('placeholder') && !i.id && !i.closest('label') && !i.getAttribute('title')).length;
    const inputNoLabelStrict = inputs.filter((i) => !i.getAttribute('aria-label') && !(i.id && document.querySelector('label[for="' + i.id + '"]')) && !i.closest('label')).length;
    const small = [...document.querySelectorAll('button, a[href], [role=button], input[type=checkbox], input[type=radio], select')].filter((b) => { if (!visible(b)) return false; const r = b.getBoundingClientRect(); return r.width < 32 || r.height < 32; }).length;
    const total = [...document.querySelectorAll('button, a[href], [role=button]')].filter(visible).length;
    const fontTiny = [...document.querySelectorAll('body *')].filter((e) => visible(e) && e.childNodes.length && [...e.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim()) && parseFloat(getComputedStyle(e).fontSize) < 11).length;
    return { vw, overflowX, offenders, imgsNoAlt, btnNoName, inputNoLabel, inputNoLabelStrict, inputs: inputs.length, smallTargets: small, interactive: total, fontTiny, title: document.title, lang: document.documentElement.lang, h1: document.querySelectorAll('h1').length };
  });
}

(async () => {
  const browser = await chromium.launch();
  // Landing + login (anonymous)
  for (const [vn, vp] of Object.entries(viewports)) {
    const ctx = await browser.newContext({ viewport: vp });
    const page = await ctx.newPage();
    const errs = [];
    page.on('pageerror', (e) => errs.push('pageerror: ' + e.message));
    page.on('console', (m) => { if (m.type() === 'error') errs.push('console: ' + m.text()); });
    page.on('response', (r) => { if (r.status() >= 400) errs.push(r.status() + ' ' + r.url().replace(BASE, '')); });
    for (const [name, url] of [['landing', '/'], ['login', '/?dangnhap=1'], ['tracuu', '/tracuu.php'], ['somoc', '/somoc.php'], ['bxh', '/bxh.php']]) {
      errs.length = 0;
      await page.goto(BASE + url, { waitUntil: 'networkidle' }).catch((e) => errs.push('goto: ' + e.message));
      await page.waitForTimeout(500);
      const a = await audit(page);
      await page.screenshot({ path: `${S}/shots/${name}-${vn}.png`, fullPage: false });
      report.pages.push({ page: name, viewport: vn, ...a, errors: [...errs] });
    }
    await ctx.close();
  }

  for (const [role, [phone, pw]] of Object.entries(roles)) {
    for (const [vn, vp] of Object.entries(viewports)) {
      const ctx = await browser.newContext({ viewport: vp });
      const page = await ctx.newPage();
      const errs = [];
      page.on('pageerror', (e) => errs.push('pageerror: ' + e.message));
      page.on('console', (m) => { if (m.type() === 'error') errs.push('console: ' + m.text()); });
      page.on('response', (r) => { if (r.status() >= 400) errs.push(r.status() + ' ' + r.url().replace(BASE, '')); });
      page.on('requestfailed', (r) => errs.push('reqfailed ' + r.url().replace(BASE, '')));
      await page.goto(BASE + '/?dangnhap=1', { waitUntil: 'networkidle' });
      await page.fill('input[type=tel]', phone);
      await page.fill('input[autocomplete=current-password]', pw);
      await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);
      await page.waitForTimeout(1500);
      const loggedIn = await page.evaluate(() => !!document.querySelector('.app-shell'));
      if (!loggedIn) { report.modules.push({ role, viewport: vn, module: '(login)', error: 'không vào được app', errors: [...errs] }); await ctx.close(); continue; }
      const keys = await page.evaluate(() => {
        const app = window.Alpine.$data(document.querySelector('.app-shell'));
        return app.moduleDefs.filter((m) => app.canAccess(m.key)).map((m) => m.key);
      });
      for (const key of ['dashboard', ...keys]) {
        errs.length = 0;
        const t0 = Date.now();
        await page.evaluate((k) => { const app = window.Alpine.$data(document.querySelector('.app-shell')); k === 'dashboard' ? app.changeModule('dashboard') : app.openModule(k); }, key).catch((e) => errs.push('eval: ' + e.message));
        await page.waitForLoadState('networkidle').catch(() => {});
        await page.waitForTimeout(1200);
        const cur = await page.evaluate(() => window.Alpine.$data(document.querySelector('.app-shell')).currentModule);
        const a = await audit(page);
        const popupResults = await checkPopups(page);
        const spinner = await page.evaluate(() => !![...document.querySelectorAll('[class*="animate-spin"], .skeleton')].find((e) => { const r = e.getBoundingClientRect(); return r.width > 0 && r.height > 0; }));
        if (vn !== 'tablet') await page.screenshot({ path: `${S}/shots/${role}-${key}-${vn}.png` });
        report.modules.push({ role, viewport: vn, module: key, current: cur, ms: Date.now() - t0, stuckLoading: spinner, ...a, ...popupResults, errors: [...errs] });
      }
      await ctx.close();
    }
  }
  await browser.close();
  // Compute summary
  report.summary.totalTests = report.pages.length + report.modules.length;
  report.summary.popups = report.modules.filter(m => m.modals > 0 || m.toasts > 0 || m.drawers > 0).length;
  report.summary.navOverlap = report.modules.filter(m => m.navOverlap).length;
  report.summary.overflowX = report.modules.filter(m => m.overflowX > 0).length;
  fs.writeFileSync(S + '/ui_report.json', JSON.stringify(report, null, 1));
  console.log('done', report.pages.length, report.modules.length);
})().catch((e) => { console.error(e); process.exit(1); });

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
