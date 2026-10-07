const { chromium } = require('playwright');
const fs = require('fs');
const S = __dirname + '/out';
const BASE = process.env.E2E_BASE || 'http://127.0.0.1:8080';

const roles = {
  admin: ['0901000001', 'tntt@2026'],
};

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

  // Navigate to attendance module
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

    // Check for print button
    const printBtn = await page.locator('button:has-text("In"), button:has-text("Print"), a:has-text("In")').first();
    if (await printBtn.isVisible().catch(() => false)) {
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
