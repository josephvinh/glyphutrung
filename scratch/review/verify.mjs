/**
 * Kiểm chứng chặt chẽ:
 *  PHA 1 — Đo đạc hình học/CSS để xác minh các tuyên bố UI (thanh nav đáy,
 *          sidebar, tràn ngang, tương phản, định dạng ngày...).
 *  PHA 2 — Click qua MỌI tab và modal trong từng module để tìm lỗi JS ẩn
 *          (SPA chỉ đổi trạng thái Alpine nên lỗi chỉ lộ khi bấm vào).
 */
import { launchChrome, CDP } from './cdp.mjs';
import fs from 'node:fs';

const BASE = process.env.TNTT_BASE || 'http://127.0.0.1:8099/';
const OUT = 'G:\\xampp\\htdocs\\tntt\\scratch\\review\\out';
const PHONE = process.env.TNTT_PHONE || '0901000001';
const PASS = process.env.TNTT_PASS || 'tntt@2026';
const LABEL = process.env.TNTT_LABEL || 'admin';
fs.mkdirSync(OUT, { recursive: true });
const sleep = ms => new Promise(r => setTimeout(r, ms));

const chrome = await launchChrome({ port: 9222, userDataDir: `G:\\xampp\\htdocs\\tntt\\scratch\\review\\profile-${LABEL}` });
const cdp = new CDP(chrome.wsUrl);
await cdp.connect();
const { result: t } = await cdp.send('Target.createTarget', { url: 'about:blank' });
const sid = (await cdp.send('Target.attachToTarget', { targetId: t.targetId, flatten: true })).result.sessionId;

let bucket = [];
let recording = false;
let inflight = new Set();

cdp.on('Runtime.consoleAPICalled', m => {
  if (!recording || (m.params.type !== 'error' && m.params.type !== 'warning')) return;
  bucket.push({ kind: `console.${m.params.type}`, text: m.params.args.map(a => a.value ?? a.description ?? a.type).join(' ').slice(0, 500) });
}, sid);
cdp.on('Runtime.exceptionThrown', m => {
  if (!recording) return;
  const d = m.params.exceptionDetails;
  bucket.push({
    kind: 'exception',
    text: (d.exception?.description || d.text || '').slice(0, 700),
    stack: d.stackTrace?.callFrames?.slice(0, 3).map(f => `${f.functionName || '(anon)'}@${f.url}:${f.lineNumber + 1}`).join(' | ') || '',
  });
}, sid);
cdp.on('Log.entryAdded', m => {
  if (!recording || (m.params.entry.level !== 'error' && m.params.entry.level !== 'warning')) return;
  const e = m.params.entry;
  if (e.source === 'network' || e.source === 'security') return; // đã bắt riêng
  bucket.push({ kind: `log.${e.level}/${e.source}`, text: e.text.slice(0, 500) });
}, sid);
cdp.on('Network.responseReceived', m => {
  if (!recording) return;
  const s = m.params.response.status;
  if (s >= 400) bucket.push({ kind: 'http', text: `${s} ${m.params.response.url.replace(BASE, '/')}` });
}, sid);
cdp.on('Network.requestWillBeSent', m => inflight.add(m.params.requestId), sid);
cdp.on('Network.loadingFinished', m => inflight.delete(m.params.requestId), sid);
cdp.on('Network.loadingFailed', m => { inflight.delete(m.params.requestId); }, sid);

await cdp.send('Page.enable', {}, sid);
await cdp.send('Runtime.enable', {}, sid);
await cdp.send('Log.enable', {}, sid);
await cdp.send('Network.enable', {}, sid);

async function ev(expr) {
  const r = await cdp.send('Runtime.evaluate', { expression: expr, awaitPromise: true, returnByValue: true }, sid);
  if (r.result.exceptionDetails) throw new Error('eval: ' + (r.result.exceptionDetails.exception?.description || r.result.exceptionDetails.text));
  return r.result.result.value;
}
const setVP = vp => cdp.send('Emulation.setDeviceMetricsOverride', { width: vp.width, height: vp.height, deviceScaleFactor: vp.deviceScaleFactor, mobile: vp.mobile }, sid);
const VP = { desktop: { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false }, mobile: { width: 390, height: 844, deviceScaleFactor: 2, mobile: true } };

async function settle(maxMs = 12000) {
  const t0 = Date.now(); let q = null;
  while (Date.now() - t0 < maxMs) {
    if (inflight.size === 0) { if (q === null) q = Date.now(); else if (Date.now() - q > 600) return; }
    else q = null;
    await sleep(100);
  }
}

// ------------------------------------------------------------------ ĐĂNG NHẬP
await setVP(VP.desktop);
await cdp.send('Page.navigate', { url: BASE }, sid);
await sleep(2500);

// Phiên có thể còn từ lần chạy trước (profile Chrome được tái sử dụng).
// Chỉ điền form nếu form đăng nhập thực sự có mặt.
const needLogin = await ev(`(() => {
  const i = [...document.querySelectorAll('input')];
  return !!i.find(x => x.type === 'password');
})()`);
if (needLogin) {
  const filled = await ev(`(() => {
    const inputs = [...document.querySelectorAll('input')];
    const phone = inputs.find(i => i.type === 'tel' || i.autocomplete === 'username');
    const pw = inputs.find(i => i.type === 'password');
    if (!phone || !pw) return 'THIẾU Ô NHẬP';
    const set = (el, v) => {
      const d = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
      d.set.call(el, v);
      el.dispatchEvent(new Event('input', { bubbles: true }));
    };
    set(phone, ${JSON.stringify(PHONE)});
    set(pw, ${JSON.stringify(PASS)});
    return 'ok';
  })()`);
  console.log('  (điền form đăng nhập:', filled + ')');
  await ev(`document.querySelector('form').requestSubmit()`);
} else {
  console.log('  (đã có phiên đăng nhập sẵn — bỏ qua form)');
}
await sleep(1500); await settle(30000); await sleep(2000);

const who = await ev(`(()=>{const d=Alpine.$data(document.querySelector('.app-shell'));return{role:d.user.role,name:d.user.fullName,modules:d.visibleFlat().map(m=>m.key)}})()`);
console.log('Đăng nhập:', who.name, '| vai:', who.role);

// ================================================================== PHA 1
const GEO = `(() => {
  const R = {};
  const px = v => Math.round(parseFloat(v) || 0);
  const cs = el => getComputedStyle(el);

  // --- Thanh điều hướng đáy ---
  const nav = document.querySelector('.app-bottomnav');
  const inner = document.querySelector('.app-bottomnav .nav-inner');
  const main = document.querySelector('.app-main');
  if (nav && inner) {
    const nr = inner.getBoundingClientRect();
    R.bottomnav = {
      visible: cs(nav).display !== 'none',
      height: Math.round(nr.height),
      bg: cs(inner).backgroundColor,
      backdrop: cs(inner).backdropFilter || cs(inner).webkitBackdropFilter || 'none',
      translucent: /rgba?\\([^)]*,\\s*0?\\.\\d+\\)/.test(cs(inner).backgroundColor),
      // phần nội dung có thể bị nav che khi KHÔNG cuộn
      coveredPx: Math.max(0, Math.round(window.innerHeight - nr.top)),
    };
  }
  if (main) {
    R.mainPaddingBottom = px(cs(main).paddingBottom);
    // Khoảng trống thực tế ở đáy vùng cuộn khi đã cuộn hết
    const prev = main.scrollTop;
    main.scrollTop = main.scrollHeight;
    const last = main.lastElementChild;
    const lr = last ? last.getBoundingClientRect() : null;
    R.scrollable = main.scrollHeight > main.clientHeight + 1;
    R.lastChildBottomVsViewport = lr ? Math.round(lr.bottom) : null;
    R.viewportH = window.innerHeight;
    R.lastChildFullyReachable = lr ? lr.bottom <= window.innerHeight + 1 : null;
    R.lastChildHiddenBehindNav = (lr && nav && cs(nav).display !== 'none') ? Math.round(Math.max(0, lr.bottom - inner.getBoundingClientRect().top)) : 0;
    main.scrollTop = prev;
  }

  // --- Thanh bên (desktop) ---
  const sb = document.querySelector('.app-sidebar');
  if (sb && cs(sb).display !== 'none') {
    const navEl = sb.querySelector('nav');
    const sbr = sb.getBoundingClientRect();
    R.sidebar = { height: Math.round(sbr.height), bottom: Math.round(sbr.bottom), viewportH: window.innerHeight };
    if (navEl) {
      R.sidebar.navScrollable = navEl.scrollHeight > navEl.clientHeight + 1;
      R.sidebar.navScrollHeight = navEl.scrollHeight;
      R.sidebar.navClientHeight = navEl.clientHeight;
      R.sidebar.navScrollTop = navEl.scrollTop;
      // Mục cuối có bị cắt không (khi chưa cuộn)
      const items = navEl.querySelectorAll('.nav-item');
      const lastIt = items[items.length - 1];
      if (lastIt) {
        const ir = lastIt.getBoundingClientRect(), nr2 = navEl.getBoundingClientRect();
        R.sidebar.lastItemClipped = Math.round(Math.max(0, ir.bottom - nr2.bottom));
      }
      // Cuộn xuống hết rồi đo lại
      navEl.scrollTop = navEl.scrollHeight;
      R.sidebar.lastItemReachableAfterScroll = lastIt ? lastIt.getBoundingClientRect().bottom <= navEl.getBoundingClientRect().bottom + 1 : null;
      navEl.scrollTop = 0;
    }
  }

  // --- Tràn ngang thật (không nằm trong vùng cuộn ngang) ---
  R.overflow = [];
  document.querySelectorAll('body *').forEach(el => {
    const s = cs(el);
    if (s.display === 'none' || s.visibility === 'hidden') return;
    const r = el.getBoundingClientRect();
    if (r.width === 0 || r.height === 0) return;
    if (r.right > window.innerWidth + 1 || r.left < -1) {
      let p = el.parentElement, ok = false;
      while (p && p !== document.body) { const o = cs(p).overflowX; if (o === 'auto' || o === 'scroll') { ok = true; break; } p = p.parentElement; }
      if (!ok) R.overflow.push({ t: el.tagName.toLowerCase(), c: String(el.className).slice(0, 70), right: Math.round(r.right), vw: window.innerWidth, txt: (el.innerText || '').trim().slice(0, 40) });
    }
  });
  R.overflow = R.overflow.slice(0, 8);

  // --- Ô nhập ngày: giá trị hiển thị vs nhãn ---
  R.dateInputs = [...document.querySelectorAll('input[type=date]')].map(i => ({ value: i.value, lang: document.documentElement.lang }));
  R.dateTexts = [...document.querySelectorAll('body *')].filter(e => e.children.length === 0 && /\\d{1,2}\\/\\d{1,2}\\/\\d{4}/.test(e.textContent || '')).map(e => (e.textContent || '').trim().slice(0, 60)).slice(0, 6);

  // --- Tương phản: tính tỉ lệ tương phản cho mọi nút có nền + chữ ---
  const lum = c => { const [r, g, b] = c.map(v => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * r + 0.7152 * g + 0.0722 * b; };
  const parse = s => { const m = s.match(/rgba?\\(([^)]+)\\)/); if (!m) return null; const p = m[1].split(',').map(x => parseFloat(x)); if (p.length > 3 && p[3] === 0) return null; return p.slice(0, 3); };
  const blend = (fg, bg, a) => fg.map((c, i) => c * a + bg[i] * (1 - a));
  const alphaOf = s => { const m = s.match(/rgba?\\(([^)]+)\\)/); if (!m) return 1; const p = m[1].split(',').map(x => parseFloat(x)); return p.length > 3 ? p[3] : 1; };
  R.lowContrast = [];
  const pageBg = [248, 250, 252];
  document.querySelectorAll('button, a, span, p, td, th, label').forEach(el => {
    const s = cs(el);
    if (s.display === 'none' || s.visibility === 'hidden') return;
    const r = el.getBoundingClientRect();
    if (r.width === 0 || r.height === 0) return;
    const txt = (el.innerText || '').trim();
    if (!txt || el.children.length > 0) return;
    const fgRaw = parse(s.color); if (!fgRaw) return;
    // tìm nền không trong suốt gần nhất
    let bg = null, p = el;
    while (p) { const bs = cs(p).backgroundColor; const ba = alphaOf(bs); const bc = parse(bs); if (bc && ba > 0.5) { bg = bc; break; } p = p.parentElement; }
    if (!bg) bg = pageBg;
    const a = alphaOf(s.color);
    const fg = a < 1 ? blend(fgRaw, bg, a) : fgRaw;
    const L1 = lum(fg), L2 = lum(bg);
    const ratio = (Math.max(L1, L2) + 0.05) / (Math.min(L1, L2) + 0.05);
    const fs = parseFloat(s.fontSize), bold = parseInt(s.fontWeight) >= 700;
    const large = fs >= 24 || (fs >= 18.66 && bold);
    const need = large ? 3 : 4.5;
    if (ratio < need) R.lowContrast.push({ txt: txt.slice(0, 40), ratio: Math.round(ratio * 100) / 100, need, fs: Math.round(fs), color: s.color, bg: 'rgb(' + bg.join(',') + ')', cls: String(el.className).slice(0, 50) });
  });
  R.lowContrast = R.lowContrast.slice(0, 10);

  // --- Ô tìm kiếm: icon có đè lên placeholder không ---
  R.searchInputs = [...document.querySelectorAll('input[type=search], input[placeholder*="ìm"], input[placeholder*="Tìm"]')].map(i => {
    const s = cs(i);
    const padL = px(s.paddingLeft);
    const rect = i.getBoundingClientRect();
    // icon tuyệt đối nằm trong khung bao quanh
    let icon = null, par = i.parentElement;
    if (par) icon = [...par.querySelectorAll('i,svg')].find(x => cs(x).position === 'absolute');
    const ir = icon ? icon.getBoundingClientRect() : null;
    return {
      placeholder: i.placeholder, paddingLeft: padL, fontSize: px(s.fontSize),
      iconRight: ir ? Math.round(ir.right - rect.left) : null,
      overlap: ir ? Math.round((ir.right - rect.left) - padL) : null,
    };
  });

  // --- Dải chip lọc trượt ngang ---
  R.hScrollStrips = [...document.querySelectorAll('*')].filter(el => {
    const s = cs(el); return (s.overflowX === 'auto' || s.overflowX === 'scroll') && el.scrollWidth > el.clientWidth + 4;
  }).map(el => ({ cls: String(el.className).slice(0, 60), sw: el.scrollWidth, cw: el.clientWidth, hidden: el.scrollWidth - el.clientWidth, scrollbarShown: el.offsetHeight - el.clientHeight > 2 }));

  R.vw = window.innerWidth;
  return R;
})()`;

const geo = {};
for (const page of ['dashboard', 'students', 'attendance', 'leave', 'reporthub', 'thu_vien', 'org', 'notes', 'guide', 'promotion', 'programs', 'announcements', 'staff', 'years']) {
  geo[page] = {};
  for (const vpName of ['desktop', 'mobile']) {
    await setVP(VP[vpName]);
    await ev(`Alpine.$data(document.querySelector('.app-shell')).${page === 'dashboard' ? "changeModule('dashboard')" : `openModule('${page}')`}`);
    await sleep(700); await settle(15000); await sleep(900);
    try { geo[page][vpName] = await ev(GEO); } catch (e) { geo[page][vpName] = { error: String(e.message) }; }
  }
}
fs.writeFileSync(`${OUT}\\geometry-${LABEL}.json`, JSON.stringify(geo, null, 2));
console.log('→ Đã ghi geometry-' + LABEL + '.json');

// ================================================================== PHA 2
const TAB_CLICK = `(() => {
  const btns = [...document.querySelectorAll('button')].filter(b => {
    if (b.offsetParent === null) return false;
    return [...b.attributes].some(a => /^@click$|^x-on:click$/.test(a.name) && /Tab\\s*=/.test(a.value));
  });
  return btns.map((b, i) => ({ i, txt: (b.innerText || '').trim().slice(0, 40), expr: [...b.attributes].find(a => /click/.test(a.name))?.value.slice(0, 60) }));
})()`;

const SAFE_BTN = `(() => {
  const BAD = /(xoá|xóa|delete|clear|xoa|xóa toàn bộ|đăng xuất|logout|reset|khoá|khóa|nghỉ|xóa hết|gửi thông báo|phát)/i;
  const btns = [...document.querySelectorAll('button')].filter(b => {
    if (b.offsetParent === null) return false;
    const t = (b.innerText || '').trim();
    if (!t || BAD.test(t)) return false;
    return [...b.attributes].some(a => /^@click$|^x-on:click$/.test(a.name) && /(Modal|modal)\\s*=|open[A-Z]/.test(a.value));
  });
  return btns.map((b, i) => ({ i, txt: (b.innerText || '').trim().slice(0, 40) }));
})()`;

async function clickNthTab(n) {
  return ev(`(() => {
    const btns = [...document.querySelectorAll('button')].filter(b => b.offsetParent !== null &&
      [...b.attributes].some(a => /^@click$|^x-on:click$/.test(a.name) && /Tab\\s*=/.test(a.value)));
    const b = btns[${n}]; if (!b) return null;
    b.click(); return (b.innerText || '').trim().slice(0, 40);
  })()`);
}
async function clickNthSafeBtn(n) {
  return ev(`(() => {
    const BAD = /(xoá|xóa|delete|clear|đăng xuất|logout|reset|khoá|khóa|nghỉ|phát)/i;
    const btns = [...document.querySelectorAll('button')].filter(b => {
      if (b.offsetParent === null) return false;
      const t = (b.innerText || '').trim(); if (!t || BAD.test(t)) return false;
      return [...b.attributes].some(a => /^@click$|^x-on:click$/.test(a.name) && /(Modal|modal)\\s*=|open[A-Z]/.test(a.value));
    });
    const b = btns[${n}]; if (!b) return null;
    b.click(); return (b.innerText || '').trim().slice(0, 40);
  })()`);
}
async function closeOverlays() {
  await ev(`(() => {
    const d = Alpine.$data(document.querySelector('.app-shell'));
    for (const k of Object.keys(d)) if (/^show.*(Modal|Sheet|Dialog)$/i.test(k)) d[k] = false;
    document.querySelectorAll('.modal-sheet').forEach(m => { if (m.parentElement) m.parentElement.style.display = 'none'; });
    return 1;
  })()`).catch(() => {});
  await sleep(250);
}

const interactions = [];
const PAGES = ['dashboard', 'students', 'attendance', 'leave', 'reporthub', 'thu_vien', 'org', 'notes', 'guide', 'promotion', 'programs', 'announcements', 'staff', 'years'];

for (const page of PAGES) {
  for (const vpName of ['desktop', 'mobile']) {
    await setVP(VP[vpName]);
    await ev(`Alpine.$data(document.querySelector('.app-shell')).${page === 'dashboard' ? "changeModule('dashboard')" : `openModule('${page}')`}`);
    await sleep(700); await settle(15000); await sleep(700);

    // --- Tabs ---
    const tabs = await ev(TAB_CLICK).catch(() => []);
    for (let i = 0; i < tabs.length; i++) {
      bucket = []; recording = true;
      const txt = await clickNthTab(i).catch(e => 'ERR ' + e.message);
      await sleep(800); await settle(10000); await sleep(700);
      recording = false;
      if (bucket.length) interactions.push({ page, viewport: vpName, action: `tab[${i}] "${tabs[i].txt}"`, expr: tabs[i].expr, errors: bucket.slice(0, 6) });
    }

    // --- Modal / nút mở ---
    const safe = await ev(SAFE_BTN).catch(() => []);
    for (let i = 0; i < Math.min(safe.length, 8); i++) {
      bucket = []; recording = true;
      const txt = await clickNthSafeBtn(i).catch(e => 'ERR ' + e.message);
      await sleep(800); await settle(10000); await sleep(600);
      recording = false;
      if (bucket.length) interactions.push({ page, viewport: vpName, action: `button[${i}] "${safe[i].txt}"`, errors: bucket.slice(0, 6) });
      await closeOverlays();
    }
    const n = interactions.filter(x => x.page === page && x.viewport === vpName).length;
    if (tabs.length || safe.length) console.log(`  ${vpName.padEnd(7)} ${page.padEnd(14)} tabs=${tabs.length} btns=${Math.min(safe.length, 8)} → lỗi: ${n}`);
  }
}

// --- Hồ sơ học sinh (mở từ danh sách) ---
await setVP(VP.desktop);
await ev(`Alpine.$data(document.querySelector('.app-shell')).openModule('students')`);
await sleep(800); await settle(15000); await sleep(1000);
const opened = await ev(`(() => {
  const d = Alpine.$data(document.querySelector('.app-shell'));
  const st = (d.accessibleStudents || d.students || [])[0];
  if (!st) return null;
  d.openStudentProfile ? d.openStudentProfile(st.id) : d.openStudent(st.id);
  return st.fullName || st.name || String(st.id);
})()`).catch(e => 'ERR ' + e.message);
await sleep(1500); await settle(10000); await sleep(1000);
const profTabs = await ev(TAB_CLICK).catch(() => []);
for (let i = 0; i < profTabs.length; i++) {
  bucket = []; recording = true;
  await clickNthTab(i).catch(() => {});
  await sleep(900); await settle(10000); await sleep(700);
  recording = false;
  if (bucket.length) interactions.push({ page: 'student_profile', viewport: 'desktop', action: `tab[${i}] "${profTabs[i].txt}"`, errors: bucket.slice(0, 6) });
}
console.log(`  Hồ sơ học sinh: ${opened} — tabs=${profTabs.length}`);

// --- Cài đặt: các tab admin ---
await ev(`Alpine.$data(document.querySelector('.app-shell')).openSettings()`);
await sleep(900); await settle(10000); await sleep(900);
const setTabs = await ev(TAB_CLICK).catch(() => []);
for (let i = 0; i < setTabs.length; i++) {
  bucket = []; recording = true;
  await clickNthTab(i).catch(() => {});
  await sleep(900); await settle(10000); await sleep(700);
  recording = false;
  if (bucket.length) interactions.push({ page: 'settings', viewport: 'desktop', action: `tab[${i}] "${setTabs[i].txt}"`, errors: bucket.slice(0, 6) });
}
console.log(`  Cài đặt: tabs=${setTabs.length}`);

fs.writeFileSync(`${OUT}\\interactions-${LABEL}.json`, JSON.stringify(interactions, null, 2));
console.log('\n→ Đã ghi interactions-' + LABEL + '.json — tổng lỗi:', interactions.length);

cdp.close(); chrome.child.kill();
