// Kiểm tra khả năng truy cập bằng axe-core (WCAG 2.1 A/AA) trên từng module. Cần: npm i @axe-core/playwright (thư mục NPM_TOOLS).
const { chromium } = require('playwright');
const AxeBuilder = require(process.env.NPM_TOOLS + '/node_modules/@axe-core/playwright').default || require(process.env.NPM_TOOLS + '/node_modules/@axe-core/playwright').AxeBuilder;
const fs = require('fs'); const OUT = __dirname + '/out';
(async () => {
  const browser = await chromium.launch(); const rep = {};
  for (const vp of [{ n: 'mobile', w: 390, h: 844 }, { n: 'desktop', w: 1366, h: 768 }]) {
    const ctx = await browser.newContext({ viewport: { width: vp.w, height: vp.h } }); const page = await ctx.newPage();
    const scan = async (name) => {
      const r = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
      for (const v of r.violations) { const k = v.id; rep[k] = rep[k] || { impact: v.impact, help: v.help, nodes: 0, where: {}, sample: '' }; rep[k].nodes += v.nodes.length; rep[k].where[vp.n + ':' + name] = v.nodes.length; if (!rep[k].sample) rep[k].sample = v.nodes[0].html.slice(0, 140); }
    };
    await page.goto('http://127.0.0.1:8088/', { waitUntil: 'networkidle' }); await scan('landing');
    await page.goto('http://127.0.0.1:8088/?dangnhap=1', { waitUntil: 'networkidle' }); await scan('login');
    await page.fill('input[type=tel]', '0901000001'); await page.fill('input[autocomplete=current-password]', 'tntt@2026');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]); await page.waitForTimeout(1200);
    const keys = await page.evaluate(() => { const a = window.Alpine.$data(document.querySelector('.app-shell')); return a.moduleDefs.filter((m) => a.canAccess(m.key)).map((m) => m.key); });
    for (const k of ['dashboard', ...keys]) {
      await page.evaluate((k) => { const a = window.Alpine.$data(document.querySelector('.app-shell')); k === 'dashboard' ? a.changeModule('dashboard') : a.openModule(k); }, k);
      await page.waitForTimeout(1300); await scan(k);
    }
    await ctx.close();
  }
  await browser.close();
  fs.writeFileSync(OUT + '/axe_results.json', JSON.stringify(rep, null, 1));
  for (const [id, v] of Object.entries(rep).sort((a, b) => b[1].nodes - a[1].nodes)) console.log(`${v.impact.padEnd(9)} ${id.padEnd(28)} ${String(v.nodes).padStart(5)} nodes | ${v.help} | ở ${Object.keys(v.where).length} màn | ${v.sample}`);
})().catch((e) => { console.error(e); process.exit(1); });
