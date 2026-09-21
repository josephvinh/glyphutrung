/**
 * Kiểm tra bổ sung:
 *  - Trang công khai "Bảng thi đua" (bxh.php) — mở từ menu chính
 *  - Lỗi console theo từng VAI TRÒ khác (glv, bdh, truong_khoi...)
 *  - Dải tab trượt ngang trên mobile (thiếu dấu hiệu cuộn)
 */
import { launchChrome, CDP } from './cdp.mjs';
import fs from 'node:fs';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const BASE = 'http://127.0.0.1:8099/';
const OUT = 'G:\\xampp\\htdocs\\tntt\\scratch\\review\\out';

const ROLES = [
  { label: 'bdh', phone: '0902000002' },
  { label: 'truong_khoi', phone: '0902000003' },
  { label: 'glv_chu_nhiem', phone: '0902000004' },
  { label: 'glv', phone: '0902000005' },
  { label: 'du_bi', phone: '0902000006' },
];
const PASS = 'tntt@2026';

const chrome = await launchChrome({ port: 9228, userDataDir: 'G:\\xampp\\htdocs\\tntt\\scratch\\review\\profile-roles' });
const cdp = new CDP(chrome.wsUrl); await cdp.connect();
const { result: t } = await cdp.send('Target.createTarget', { url: 'about:blank' });
const sid = (await cdp.send('Target.attachToTarget', { targetId: t.targetId, flatten: true })).result.sessionId;

let bucket = [], recording = false, inflight = new Set();
cdp.on('Runtime.consoleAPICalled', m => { if (recording && (m.params.type === 'error' || m.params.type === 'warning')) bucket.push({ kind: `console.${m.params.type}`, text: m.params.args.map(a => a.value ?? a.description).join(' ').slice(0, 400) }); }, sid);
cdp.on('Runtime.exceptionThrown', m => { if (recording) bucket.push({ kind: 'exception', text: (m.params.exceptionDetails.exception?.description || m.params.exceptionDetails.text || '').slice(0, 600) }); }, sid);
cdp.on('Log.entryAdded', m => { if (recording && m.params.entry.level === 'error' && m.params.entry.source !== 'network') bucket.push({ kind: `log.${m.params.entry.source}`, text: m.params.entry.text.slice(0, 400) }); }, sid);
cdp.on('Network.responseReceived', m => { if (recording && m.params.response.status >= 400) bucket.push({ kind: 'http', text: `${m.params.response.status} ${m.params.response.url.replace(BASE, '/')}` }); }, sid);
cdp.on('Network.requestWillBeSent', m => inflight.add(m.params.requestId), sid);
cdp.on('Network.loadingFinished', m => inflight.delete(m.params.requestId), sid);
cdp.on('Network.loadingFailed', m => inflight.delete(m.params.requestId), sid);

await cdp.send('Page.enable', {}, sid); await cdp.send('Runtime.enable', {}, sid);
await cdp.send('Log.enable', {}, sid); await cdp.send('Network.enable', {}, sid);
async function ev(e) {
  const r = await cdp.send('Runtime.evaluate', { expression: e, awaitPromise: true, returnByValue: true }, sid);
  if (r.result.exceptionDetails) throw new Error('eval: ' + (r.result.exceptionDetails.exception?.description || r.result.exceptionDetails.text));
  return r.result.result.value;
}
async function settle(max = 15000) { const t0 = Date.now(); let q = null; while (Date.now() - t0 < max) { if (inflight.size === 0) { if (q === null) q = Date.now(); else if (Date.now() - q > 600) return; } else q = null; await sleep(100); } }
async function login(phone) {
  await cdp.send('Page.navigate', { url: BASE }, sid);
  await sleep(2500);
  const has = await ev(`!!document.querySelector('input[type=password]')`);
  if (!has) { await ev(`(async()=>{await fetch('api/auth.php?action=logout',{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'});})()`); await sleep(800); await cdp.send('Page.navigate', { url: BASE }, sid); await sleep(2500); }
  await ev(`(() => {
    const inputs = [...document.querySelectorAll('input')];
    const p = inputs.find(i => i.type === 'tel' || i.autocomplete === 'username');
    const w = inputs.find(i => i.type === 'password');
    if (!p || !w) return 'noform';
    const set = (el, v) => { const d = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value'); d.set.call(el, v); el.dispatchEvent(new Event('input', { bubbles: true })); };
    set(p, ${JSON.stringify(phone)}); set(w, ${JSON.stringify(PASS)}); return 'ok';
  })()`);
  await ev(`document.querySelector('form').requestSubmit()`);
  await sleep(2000); await settle(30000); await sleep(1500);
}

// ============================================================ 1. Bảng thi đua
console.log('=== TRANG "BẢNG THI ĐUA" (bxh.php — mở từ menu chính) ===');
for (const vp of [{ w: 1440, h: 900, m: false, n: 'desktop' }, { w: 390, h: 844, m: true, n: 'mobile' }]) {
  await cdp.send('Emulation.setDeviceMetricsOverride', { width: vp.w, height: vp.h, deviceScaleFactor: vp.m ? 2 : 1, mobile: vp.m }, sid);
  bucket = []; recording = true;
  await cdp.send('Page.navigate', { url: BASE + 'bxh.php' }, sid);
  await sleep(3500); await settle(15000); await sleep(1500);
  const probe = await ev(`(() => ({
    title: document.title,
    textLen: document.body.innerText.trim().length,
    sample: document.body.innerText.trim().replace(/\\n{2,}/g,' | ').slice(0, 200),
    scrollW: document.documentElement.scrollWidth, vw: window.innerWidth,
    overflowX: document.documentElement.scrollWidth - window.innerWidth,
    tables: document.querySelectorAll('table').length,
    rows: document.querySelectorAll('tr').length,
    images: document.querySelectorAll('img').length,
  }))()`).catch(e => ({ err: String(e.message) }));
  recording = false;
  console.log(`\n--- ${vp.n} ---`);
  console.log(JSON.stringify(probe, null, 2));
  if (bucket.length) { console.log('LỖI:'); bucket.forEach(b => console.log('   [' + b.kind + '] ' + b.text)); }
  else console.log('   (không có lỗi console)');
  const shot = await cdp.send('Page.captureScreenshot', { format: 'png' }, sid);
  fs.writeFileSync(`G:\\xampp\\htdocs\\tntt\\scratch\\review\\shots\\bxh-${vp.n}.png`, Buffer.from(shot.result.data, 'base64'));
}

// ============================================================ 2. Theo vai trò
console.log('\n\n=== LỖI CONSOLE THEO TỪNG VAI TRÒ ===');
const roleResults = [];
for (const role of ROLES) {
  await cdp.send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false }, sid);
  await login(role.phone);
  const who = await ev(`(() => { const el = document.querySelector('.app-shell'); if (!el || !window.Alpine) return null; const d = Alpine.$data(el); return { name: d.user.fullName, role: d.user.role, modules: d.visibleFlat().map(m => m.key) }; })()`).catch(() => null);
  if (!who) { console.log(`${role.label.padEnd(14)} ĐĂNG NHẬP THẤT BẠI`); continue; }

  const pageErrors = [];
  for (const key of ['dashboard', ...who.modules]) {
    bucket = []; recording = true;
    await ev(`Alpine.$data(document.querySelector('.app-shell')).${key === 'dashboard' ? "changeModule('dashboard')" : `openModule(${JSON.stringify(key)})`}`);
    await sleep(700); await settle(15000); await sleep(900);
    recording = false;
    if (bucket.length) pageErrors.push({ key, errors: bucket.slice(0, 5) });
  }
  roleResults.push({ role: role.label, name: who.name, modules: who.modules.length, pageErrors });
  console.log(`${role.label.padEnd(14)} ${String(who.name).padEnd(22)} modules=${String(who.modules.length).padStart(2)}  trang có lỗi: ${pageErrors.length}`);
  pageErrors.forEach(pe => pe.errors.forEach(e => console.log(`      [${pe.key}] ${e.kind}: ${e.text}`)));
}
fs.writeFileSync(OUT + '\\roles.json', JSON.stringify(roleResults, null, 2));

// ============================================================ 3. Dải tab mobile
console.log('\n\n=== DẢI TAB TRƯỢT NGANG TRÊN MOBILE ===');
await login('0901000001');
await cdp.send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true }, sid);
for (const key of ['students', 'guide', 'staff', 'org']) {
  await ev(`Alpine.$data(document.querySelector('.app-shell')).openModule(${JSON.stringify(key)})`);
  await sleep(1200); await settle(10000); await sleep(900);
  const r = await ev(`(() => {
    const strips = [...document.querySelectorAll('*')].filter(el => {
      const s = getComputedStyle(el);
      return (s.overflowX === 'auto' || s.overflowX === 'scroll') && el.scrollWidth > el.clientWidth + 4;
    });
    return strips.map(el => {
      const s = getComputedStyle(el);
      const visibleBar = el.offsetHeight - el.clientHeight > 2;
      const cs = getComputedStyle(el);
      return { cls: String(el.className).slice(0, 80), hidden: el.scrollWidth - el.clientWidth, scrollbar: visibleBar,
               scrollbarWidth: cs.scrollbarWidth, text: (el.innerText||'').trim().replace(/\\n/g,' / ').slice(0, 70) };
    });
  })()`).catch(e => [{ err: String(e.message) }]);
  console.log(`\n${key}:`);
  r.forEach(x => console.log('   ', JSON.stringify(x)));
}

cdp.close(); chrome.child.kill();
