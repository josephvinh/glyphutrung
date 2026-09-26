/**
 * Duyệt toàn bộ các trang trong menu chính của app TNTT.
 *
 * Với mỗi module:
 *   - gọi openModule(key) qua Alpine (đúng như người dùng bấm menu)
 *   - chờ mạng + render ổn định
 *   - ghi lại: exception, console error/warning, request lỗi, vi phạm CSP
 *   - chụp ảnh màn hình (desktop + mobile)
 *   - chạy các phép kiểm tra UI tự động (tràn ngang, ảnh hỏng, phần tử trống…)
 *
 * Kết quả: scratch/review/out/report.json + ảnh trong scratch/review/shots/
 */
import { launchChrome, CDP } from './cdp.mjs';
import fs from 'node:fs';
import path from 'node:path';

const BASE = process.env.TNTT_BASE || 'http://127.0.0.1:8099/';
const OUT = 'G:\\xampp\\htdocs\\tntt\\scratch\\review\\out';
const SHOTS = 'G:\\xampp\\htdocs\\tntt\\scratch\\review\\shots';
const PHONE = process.env.TNTT_PHONE || '0901000001';
const PASS = process.env.TNTT_PASS || 'tntt@2026';
const LABEL = process.env.TNTT_LABEL || 'admin';

fs.mkdirSync(OUT, { recursive: true });
fs.mkdirSync(SHOTS, { recursive: true });

const sleep = ms => new Promise(r => setTimeout(r, ms));

const VIEWPORTS = {
  desktop: { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false },
  mobile: { width: 390, height: 844, deviceScaleFactor: 2, mobile: true },
};

const chrome = await launchChrome({
  port: 9222,
  userDataDir: `G:\\xampp\\htdocs\\tntt\\scratch\\review\\profile-${LABEL}`,
  windowSize: '1440,900',
});
const cdp = new CDP(chrome.wsUrl);
await cdp.connect();

const { result: t } = await cdp.send('Target.createTarget', { url: 'about:blank' });
const sid = (await cdp.send('Target.attachToTarget', { targetId: t.targetId, flatten: true })).result.sessionId;

// ---------------------------------------------------------------- thu thập log
let bucket = [];
let net = new Map();     // requestId -> {url, status, type}
let inflight = new Set();
let recording = false;

cdp.on('Runtime.consoleAPICalled', m => {
  if (!recording) return;
  const type = m.params.type;
  if (type !== 'error' && type !== 'warning') return;
  bucket.push({
    kind: `console.${type}`,
    text: m.params.args.map(a => a.value ?? a.description ?? a.type).join(' ').slice(0, 600),
    stack: m.params.stackTrace?.callFrames?.slice(0, 3).map(f => `${f.functionName || '(anon)'} @ ${f.url}:${f.lineNumber + 1}`).join(' | ') || '',
  });
}, sid);

cdp.on('Runtime.exceptionThrown', m => {
  if (!recording) return;
  const d = m.params.exceptionDetails;
  bucket.push({
    kind: 'exception',
    text: (d.exception?.description || d.text || '').slice(0, 900),
    stack: d.stackTrace?.callFrames?.slice(0, 4).map(f => `${f.functionName || '(anon)'} @ ${f.url}:${f.lineNumber + 1}:${f.columnNumber}`).join(' | ') || '',
  });
}, sid);

cdp.on('Log.entryAdded', m => {
  if (!recording) return;
  const e = m.params.entry;
  if (e.level !== 'error' && e.level !== 'warning') return;
  bucket.push({ kind: `log.${e.level}/${e.source}`, text: e.text.slice(0, 600), stack: e.url ? `${e.url}:${e.lineNumber || 0}` : '' });
}, sid);

cdp.on('Network.requestWillBeSent', m => {
  net.set(m.params.requestId, { url: m.params.request.url, status: null, type: m.params.type });
  inflight.add(m.params.requestId);
}, sid);
cdp.on('Network.responseReceived', m => {
  const r = net.get(m.params.requestId);
  if (r) { r.status = m.params.response.status; r.mime = m.params.response.mimeType; }
}, sid);
cdp.on('Network.loadingFinished', m => { inflight.delete(m.params.requestId); }, sid);
cdp.on('Network.loadingFailed', m => {
  inflight.delete(m.params.requestId);
  if (!recording) return;
  const r = net.get(m.params.requestId);
  if (m.params.canceled) return;
  bucket.push({ kind: 'network.failed', text: `${r?.url || '?'} — ${m.params.errorText}`, stack: '' });
}, sid);

await cdp.send('Page.enable', {}, sid);
await cdp.send('Runtime.enable', {}, sid);
await cdp.send('Log.enable', {}, sid);
await cdp.send('Network.enable', {}, sid);

async function evaluate(expr, awaitPromise = true) {
  const r = await cdp.send('Runtime.evaluate', { expression: expr, awaitPromise, returnByValue: true }, sid);
  const res = r.result;
  if (res.exceptionDetails) {
    throw new Error('eval: ' + (res.exceptionDetails.exception?.description || res.exceptionDetails.text));
  }
  return res.result.value;
}

async function setViewport(vp) {
  await cdp.send('Emulation.setDeviceMetricsOverride', {
    width: vp.width, height: vp.height, deviceScaleFactor: vp.deviceScaleFactor, mobile: vp.mobile,
  }, sid);
}

async function waitSettle(maxMs = 15000) {
  const start = Date.now();
  let quietSince = null;
  while (Date.now() - start < maxMs) {
    if (inflight.size === 0) {
      if (quietSince === null) quietSince = Date.now();
      else if (Date.now() - quietSince > 700) return;
    } else quietSince = null;
    await sleep(120);
  }
}

async function screenshot(name) {
  const r = await cdp.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false }, sid);
  fs.writeFileSync(path.join(SHOTS, name + '.png'), Buffer.from(r.result.data, 'base64'));
}

/** Các phép kiểm tra UI chạy trong trang. */
const UI_PROBE = `(() => {
  const vw = window.innerWidth, vh = window.innerHeight;
  const out = { scrollWidth: document.documentElement.scrollWidth, innerWidth: vw, overflowX: document.documentElement.scrollWidth - vw, issues: [] };

  const visible = el => {
    const s = getComputedStyle(el);
    if (s.display === 'none' || s.visibility === 'hidden' || parseFloat(s.opacity) === 0) return false;
    const r = el.getBoundingClientRect();
    return r.width > 0 && r.height > 0;
  };
  const label = el => {
    const t = (el.innerText || el.getAttribute('aria-label') || el.getAttribute('placeholder') || '').trim().replace(/\\s+/g,' ').slice(0, 60);
    return el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (el.className && typeof el.className === 'string' ? '.' + el.className.split(/\\s+/).slice(0,3).join('.') : '') + (t ? ' "' + t + '"' : '');
  };

  // 1. Phần tử tràn ra ngoài khung nhìn theo chiều ngang
  const overflowing = [];
  document.querySelectorAll('body *').forEach(el => {
    if (!visible(el)) return;
    const r = el.getBoundingClientRect();
    if (r.right > vw + 2 || r.left < -2) {
      // bỏ qua phần tử bên trong vùng cuộn ngang hợp lệ
      let p = el.parentElement, inScroller = false;
      while (p && p !== document.body) {
        const ov = getComputedStyle(p).overflowX;
        if (ov === 'auto' || ov === 'scroll') { inScroller = true; break; }
        p = p.parentElement;
      }
      if (!inScroller) overflowing.push({ el: label(el), left: Math.round(r.left), right: Math.round(r.right), w: Math.round(r.width) });
    }
  });
  out.issues.push(...overflowing.slice(0, 12).map(o => ({ type: 'overflow-x', detail: o.el + ' right=' + o.right + ' (vw=' + vw + ')' })));

  // 2. Ảnh hỏng
  document.querySelectorAll('img').forEach(img => {
    if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) {
      out.issues.push({ type: 'broken-image', detail: img.getAttribute('src') });
    }
  });

  // 3. Chữ bị cắt cụt (ellipsis) — dấu hiệu nhãn quá dài
  const clipped = [];
  document.querySelectorAll('body *').forEach(el => {
    if (el.children.length > 0) return;
    if (!visible(el)) return;
    if (el.scrollWidth > el.clientWidth + 2 && el.clientWidth > 0) {
      const s = getComputedStyle(el);
      if (s.overflow === 'hidden' || s.textOverflow === 'ellipsis') {
        clipped.push(label(el));
      }
    }
  });
  out.issues.push(...clipped.slice(0, 10).map(c => ({ type: 'text-clipped', detail: c })));

  // 4. Nút bấm quá nhỏ (chỉ có ý nghĩa trên mobile)
  const small = [];
  document.querySelectorAll('button, a[href], [role=button]').forEach(el => {
    if (!visible(el)) return;
    const r = el.getBoundingClientRect();
    if (r.height < 28 && r.width < 28) small.push(label(el) + ' ' + Math.round(r.width) + 'x' + Math.round(r.height));
  });
  out.issues.push(...small.slice(0, 10).map(s => ({ type: 'small-tap-target', detail: s })));

  // 5. Vùng nội dung trống
  const main = document.querySelector('.app-content') || document.body;
  out.mainTextLen = (main.innerText || '').trim().length;

  // 6. Icon chưa được render (lucide để lại thẻ i rỗng)
  const unrenderedIcons = [...document.querySelectorAll('i[data-lucide]')].filter(i => {
    if (!visible(i)) return false;
    return i.querySelector('svg') === null;
  }).map(i => i.getAttribute('data-lucide'));
  out.issues.push(...[...new Set(unrenderedIcons)].slice(0, 10).map(i => ({ type: 'icon-not-rendered', detail: i })));

  // 7. Phần tử đè lên nhau (chỉ kiểm tra các nút cấp cao, tránh nhiễu)
  out.iconCount = document.querySelectorAll('svg.lucide').length;
  out.textSample = (main.innerText || '').trim().replace(/\\n{2,}/g, ' | ').slice(0, 300);
  return out;
})()`;

// ---------------------------------------------------------------- đăng nhập
console.log('→ Mở', BASE);
await setViewport(VIEWPORTS.desktop);
await cdp.send('Page.navigate', { url: BASE }, sid);
await sleep(2500);

await evaluate(`(() => {
  const inputs = [...document.querySelectorAll('input')];
  const phone = inputs.find(i => i.type === 'tel' || i.autocomplete === 'username');
  const pw = inputs.find(i => i.type === 'password');
  const set = (el, v) => { const d = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value'); d.set.call(el, v); el.dispatchEvent(new Event('input', {bubbles:true})); };
  set(phone, ${JSON.stringify(PHONE)});
  set(pw, ${JSON.stringify(PASS)});
  return 'filled';
})()`);
await evaluate(`document.querySelector('form').requestSubmit()`);
await sleep(1500);
await waitSettle(30000);
await sleep(2500);

const who = await evaluate(`(() => {
  const el = document.querySelector('.app-shell');
  if (!el || !window.Alpine) return { error: 'no app shell / alpine' };
  const d = Alpine.$data(el);
  return { role: d.user.role, name: d.user.fullName, year: d.year && d.year.name, modules: d.visibleFlat().map(m => ({ key: m.key, label: m.label, area: m.area, enabled: d.moduleEnabled[m.key] !== false })) };
})()`);
console.log('→ Đăng nhập:', who.name, '| vai:', who.role, '| năm:', who.year);
console.log('→ Modules:', who.modules.map(m => m.key).join(', '));

// ---------------------------------------------------------------- duyệt từng trang
const PAGES = [{ key: 'dashboard', label: 'Trang chủ' }, ...who.modules];
const results = [];

async function visit(page, vpName) {
  const vp = VIEWPORTS[vpName];
  await setViewport(vp);
  bucket = [];
  net = new Map();
  inflight = new Set();
  recording = true;

  const t0 = Date.now();
  if (page.key === 'dashboard') {
    await evaluate(`Alpine.$data(document.querySelector('.app-shell')).changeModule('dashboard')`);
  } else {
    await evaluate(`Alpine.$data(document.querySelector('.app-shell')).openModule(${JSON.stringify(page.key)})`);
  }
  await sleep(600);
  await waitSettle(20000);
  await sleep(1200);
  const loadMs = Date.now() - t0;

  let probe = null;
  try { probe = await evaluate(UI_PROBE); } catch (e) { probe = { error: String(e) }; }

  const active = await evaluate(`(() => {
    const d = Alpine.$data(document.querySelector('.app-shell'));
    return { currentModule: d.currentModule, settingsTab: d.settingsTab, reportsTab: d.reportsTab };
  })()`).catch(() => null);

  await screenshot(`${page.key}-${vpName}`);
  recording = false;

  const failed = [...net.values()].filter(r => r.status !== null && r.status >= 400)
    .map(r => `${r.status} ${r.url.replace(BASE, '')}`);
  const dead = [...net.values()].filter(r => r.status === null && r.url && !r.url.startsWith('data:'))
    .map(r => `(không phản hồi) ${r.url.replace(BASE, '')}`);

  return {
    key: page.key, label: page.label, viewport: vpName, loadMs,
    active, probe,
    errors: bucket.filter(b => b.kind === 'exception' || b.kind.startsWith('console.error') || b.kind.includes('/network') || b.kind === 'network.failed'),
    warnings: bucket.filter(b => b.kind.startsWith('console.warning') || b.kind.startsWith('log.warning')),
    httpErrors: [...failed, ...dead],
  };
}

for (const page of PAGES) {
  for (const vpName of ['desktop', 'mobile']) {
    try {
      const r = await visit(page, vpName);
      results.push(r);
      const errN = r.errors.length, httpN = r.httpErrors.length;
      const uiN = (r.probe?.issues || []).length;
      console.log(`  ${vpName.padEnd(7)} ${page.key.padEnd(14)} ${String(r.loadMs).padStart(6)}ms  err=${errN} http=${httpN} ui=${uiN}  [${r.active?.currentModule}]`);
    } catch (e) {
      console.log(`  ${vpName.padEnd(7)} ${page.key.padEnd(14)} THẤT BẠI: ${e.message}`);
      results.push({ key: page.key, label: page.label, viewport: vpName, fatal: String(e.message) });
    }
  }
}

fs.writeFileSync(path.join(OUT, `report-${LABEL}.json`), JSON.stringify({ who, results }, null, 2));
console.log('\n→ Đã ghi', path.join(OUT, `report-${LABEL}.json`));

cdp.close();
chrome.child.kill();
