/** Đo tác động THỰC TẾ của các lớp CSS bị thiếu lên giao diện đang hiển thị. */
import { launchChrome, CDP } from './cdp.mjs';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const BASE = 'http://127.0.0.1:8099/';

const chrome = await launchChrome({ port: 9227, userDataDir: 'G:\\xampp\\htdocs\\tntt\\scratch\\review\\profile-impact' });
const cdp = new CDP(chrome.wsUrl); await cdp.connect();
const { result: t } = await cdp.send('Target.createTarget', { url: 'about:blank' });
const sid = (await cdp.send('Target.attachToTarget', { targetId: t.targetId, flatten: true })).result.sessionId;
await cdp.send('Page.enable', {}, sid); await cdp.send('Runtime.enable', {}, sid);
async function ev(e) {
  const r = await cdp.send('Runtime.evaluate', { expression: e, awaitPromise: true, returnByValue: true }, sid);
  if (r.result.exceptionDetails) throw new Error('eval: ' + (r.result.exceptionDetails.exception?.description || r.result.exceptionDetails.text));
  return r.result.result.value;
}
await cdp.send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false }, sid);
await cdp.send('Page.navigate', { url: BASE }, sid);
await sleep(3000);

// Đăng nhập nếu cần
if (await ev(`!!document.querySelector('input[type=password]')`)) {
  await ev(`(() => {
    const inputs = [...document.querySelectorAll('input')];
    const phone = inputs.find(i => i.type === 'tel' || i.autocomplete === 'username');
    const pw = inputs.find(i => i.type === 'password');
    const set = (el, v) => { const d = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value'); d.set.call(el, v); el.dispatchEvent(new Event('input', { bubbles: true })); };
    set(phone, '0901000001'); set(pw, 'tntt@2026');
  })()`);
  await ev(`document.querySelector('form').requestSubmit()`);
  await sleep(6000);
}
await sleep(2000);

const IMPACT = `(() => {
  const out = {};
  const q = (sel, label, props) => {
    const el = document.querySelector(sel);
    if (!el) { out[label] = 'KHÔNG TÌM THẤY ' + sel; return; }
    const s = getComputedStyle(el);
    out[label] = props.map(p => p + '=' + s[p]).join('  ') + '   [class="' + String(el.className).slice(0, 90) + '"]';
  };
  const byClass = (cls, label, props) => {
    const el = document.querySelector('.' + CSS.escape(cls));
    if (!el) { out[label] = '(không có phần tử nào dùng .' + cls + ')'; return; }
    const s = getComputedStyle(el);
    out[label] = props.map(p => p + '=' + s[p]).join('  ') + '   [' + el.tagName.toLowerCase() + ' "' + (el.innerText||'').trim().slice(0,30) + '"]';
  };
  return { out, byClass: null };
})()`;

// --- Trang chủ: nút làm mới (animate-spin) ---
await ev(`Alpine.$data(document.querySelector('.app-shell')).changeModule('dashboard')`);
await sleep(2500);
console.log('=== TRANG CHỦ ===');
console.log(await ev(`(() => {
  const r = {};
  const spin = document.querySelector('.animate-spin');
  if (spin) { const s = getComputedStyle(spin); r['animate-spin'] = 'animationName=' + s.animationName + ' animationDuration=' + s.animationDuration; }
  else r['animate-spin'] = '(không có phần tử)';
  const sel = [...document.querySelectorAll('*')].filter(e => e.className && String(e.className).includes('select-none'));
  r['select-none'] = sel.length + ' phần tử; userSelect của phần tử đầu=' + (sel[0] ? getComputedStyle(sel[0]).userSelect : 'n/a');
  const mlauto = [...document.querySelectorAll('*')].filter(e => e.className && String(e.className).includes('ml-auto'));
  r['ml-auto'] = mlauto.length + ' phần tử; marginLeft đầu tiên=' + (mlauto[0] ? getComputedStyle(mlauto[0]).marginLeft : 'n/a');
  const stretch = document.querySelector('.self-stretch');
  r['self-stretch'] = stretch ? 'alignSelf=' + getComputedStyle(stretch).alignSelf : '(không có)';
  const teal = document.querySelector('.bg-teal-50\\\\/50') || [...document.querySelectorAll('*')].find(e => String(e.className).includes('bg-teal-50'));
  r['bg-teal-50*'] = teal ? 'backgroundColor=' + getComputedStyle(teal).backgroundColor : '(không có)';
  return r;
})()`));

// --- Thiếu Nhi: ô tìm kiếm ---
console.log('\n=== THIẾU NHI (ô tìm kiếm) ===');
await ev(`Alpine.$data(document.querySelector('.app-shell')).openModule('students')`);
await sleep(2500);
console.log(await ev(`(() => {
  const r = {};
  const inp = document.querySelector('input[placeholder*="Tìm tên, mã số"]');
  if (!inp) return { err: 'không thấy ô tìm kiếm' };
  const s = getComputedStyle(inp);
  const ir = inp.getBoundingClientRect();
  r.input = 'paddingLeft=' + s.paddingLeft + ' paddingRight=' + s.paddingRight + ' fontSize=' + s.fontSize;
  const par = inp.parentElement;
  const icon = par.querySelector('i,svg');
  if (icon) {
    const is = getComputedStyle(icon);
    const ic = icon.getBoundingClientRect();
    r.icon = 'position=' + is.position + ' left=' + is.left + ' right=' + is.right + ' → chiếm x ' + Math.round(ic.left - ir.left) + '..' + Math.round(ic.right - ir.left) + 'px tính từ mép trái ô nhập';
    r.KET_LUAN = (ic.right > ir.left + parseFloat(s.paddingLeft)) ? 'ICON ĐÈ LÊN VÙNG CHỮ' : 'không đè';
  }
  const sticky = [...document.querySelectorAll('*')].filter(e => String(e.className).includes('sticky'));
  r['sticky'] = sticky.length + ' phần tử; position đầu tiên=' + (sticky[0] ? getComputedStyle(sticky[0]).position : 'n/a');
  const g1 = [...document.querySelectorAll('*')].filter(e => String(e.className).match(/\\bgrid-cols-1\\b/));
  r['grid-cols-1'] = g1.length + ' phần tử; gridTemplateColumns=' + (g1[0] ? getComputedStyle(g1[0]).gridTemplateColumns : 'n/a');
  const g2 = [...document.querySelectorAll('*')].filter(e => String(e.className).includes('lg:grid-cols-2') || String(e.className).includes('sm:grid-cols-3'));
  r['sm:grid-cols-3 / lg:grid-cols-2'] = g2.length + ' phần tử; gridTemplateColumns=' + (g2[0] ? getComputedStyle(g2[0]).gridTemplateColumns : 'n/a');
  const cs = [...document.querySelectorAll('*')].filter(e => String(e.className).includes('col-span-full'));
  r['col-span-full'] = cs.length + ' phần tử; gridColumn=' + (cs[0] ? getComputedStyle(cs[0]).gridColumn : 'n/a');
  return r;
})()`));

// --- Lịch của tôi: line-through, py-16, px-3.5 ---
console.log('\n=== LỊCH CỦA TÔI ===');
await ev(`Alpine.$data(document.querySelector('.app-shell')).openModule('notes')`);
await sleep(2500);
console.log(await ev(`(() => {
  const r = {};
  const lt = [...document.querySelectorAll('*')].filter(e => String(e.className).includes('line-through'));
  r['line-through'] = lt.length + ' phần tử; textDecoration đầu tiên=' + (lt[0] ? getComputedStyle(lt[0]).textDecorationLine : 'n/a');
  const py = [...document.querySelectorAll('*')].filter(e => String(e.className).match(/\\bpy-16\\b/));
  r['py-16'] = py.length + ' phần tử; padding=' + (py[0] ? getComputedStyle(py[0]).padding : 'n/a');
  const p2 = [...document.querySelectorAll('*')].filter(e => String(e.className).match(/\\bp-2\\b/));
  r['p-2'] = p2.length + ' phần tử; padding=' + (p2[0] ? getComputedStyle(p2[0]).padding : 'n/a');
  return r;
})()`));

// --- Báo cáo (reporthub) + tab Thống kê / Phân tích ---
console.log('\n=== BÁO CÁO (reporthub) ===');
await ev(`Alpine.$data(document.querySelector('.app-shell')).openModule('reporthub')`);
await sleep(3000);
console.log(await ev(`(() => {
  const r = {};
  const md4 = [...document.querySelectorAll('*')].filter(e => String(e.className).includes('md:grid-cols-4'));
  r['md:grid-cols-4'] = md4.length + ' phần tử; gridTemplateColumns=' + (md4[0] ? getComputedStyle(md4[0]).gridTemplateColumns : 'n/a');
  const pb24 = [...document.querySelectorAll('*')].filter(e => String(e.className).match(/\\bpb-24\\b/));
  r['pb-24'] = pb24.length + ' phần tử; paddingBottom=' + (pb24[0] ? getComputedStyle(pb24[0]).paddingBottom : 'n/a');
  const inline = [...document.querySelectorAll('*')].filter(e => String(e.className).match(/\\binline\\b/));
  r['inline'] = inline.length + ' phần tử; display=' + (inline[0] ? getComputedStyle(inline[0]).display : 'n/a');
  return r;
})()`));

// --- Cài đặt ---
console.log('\n=== CÀI ĐẶT ===');
await ev(`Alpine.$data(document.querySelector('.app-shell')).openSettings()`);
await sleep(2000);
console.log(await ev(`(() => {
  const r = {};
  const sel = [...document.querySelectorAll('*')].filter(e => String(e.className).includes('select-none'));
  r['select-none'] = sel.length + ' phần tử; userSelect=' + (sel[0] ? getComputedStyle(sel[0]).userSelect : 'n/a');
  const cp = [...document.querySelectorAll('*')].filter(e => String(e.className).includes('cursor-pointer'));
  r['cursor-pointer'] = cp.length + ' phần tử; cursor=' + (cp[0] ? getComputedStyle(cp[0]).cursor : 'n/a');
  return r;
})()`));

cdp.close(); chrome.child.kill();
