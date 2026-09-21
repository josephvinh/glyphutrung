/** Kiểm tra hồi quy sau khi sửa: trang Nhân sự + Khối lớp, mọi vai trò, bắt lỗi console. */
import { launchChrome, CDP } from './cdp.mjs';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const BASE = 'http://127.0.0.1:8099/';
const PASS = 'tntt@2026';
const ROLES = [
  ['admin', '0901000001'], ['bdh', '0902000002'], ['truong_khoi', '0902000003'],
  ['glv_chu_nhiem', '0902000004'], ['glv', '0902000005'], ['du_bi', '0902000006'],
];

const chrome = await launchChrome({ port: 9236, userDataDir: 'G:\\xampp\\htdocs\\tntt\\scratch\\review\\profile-regress' });
const cdp = new CDP(chrome.wsUrl); await cdp.connect();
const { result: t } = await cdp.send('Target.createTarget', { url: 'about:blank' });
const sid = (await cdp.send('Target.attachToTarget', { targetId: t.targetId, flatten: true })).result.sessionId;

let bucket = [], recording = false;
cdp.on('Runtime.consoleAPICalled', m => { if (recording && (m.params.type === 'error' || m.params.type === 'warning'))
  bucket.push(`console.${m.params.type}: ` + m.params.args.map(a => a.value ?? a.description).join(' ').slice(0, 250)); }, sid);
cdp.on('Runtime.exceptionThrown', m => { if (recording)
  bucket.push('exception: ' + (m.params.exceptionDetails.exception?.description || m.params.exceptionDetails.text || '').slice(0, 300)); }, sid);
cdp.on('Log.entryAdded', m => { if (recording && m.params.entry.level === 'error' && m.params.entry.source !== 'network')
  bucket.push(`log.${m.params.entry.source}: ` + m.params.entry.text.slice(0, 250)); }, sid);
cdp.on('Network.responseReceived', m => { if (recording && m.params.response.status >= 400)
  bucket.push(`http ${m.params.response.status} ${m.params.response.url.replace(BASE, '/')}`); }, sid);

await cdp.send('Page.enable', {}, sid);
await cdp.send('Runtime.enable', {}, sid);
await cdp.send('Log.enable', {}, sid);
await cdp.send('Network.enable', {}, sid);

async function ev(e) {
  const r = await cdp.send('Runtime.evaluate', { expression: e, awaitPromise: true, returnByValue: true }, sid);
  if (r.result.exceptionDetails) throw new Error('eval: ' + (r.result.exceptionDetails.exception?.description || r.result.exceptionDetails.text));
  return r.result.result.value;
}
async function login(phone) {
  await ev(`(async()=>{ try{ await fetch('api/auth.php?action=logout',{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'}); }catch(e){} })()`);
  await sleep(500);
  await cdp.send('Page.navigate', { url: BASE }, sid);
  await sleep(3000);
  if (await ev(`!!document.querySelector('input[type=password]')`)) {
    await ev(`(() => {
      const inputs=[...document.querySelectorAll('input')];
      const p=inputs.find(i=>i.type==='tel'||i.autocomplete==='username');
      const w=inputs.find(i=>i.type==='password');
      const set=(el,v)=>{Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(el,v);
                         el.dispatchEvent(new Event('input',{bubbles:true}));};
      set(p,${JSON.stringify(phone)}); set(w,${JSON.stringify(PASS)});
    })()`);
    await ev(`document.querySelector('form').requestSubmit()`);
  }
  for (let i = 0; i < 40; i++) {
    await sleep(500);
    if (await ev(`!!(window.TNTT && window.TNTT.csrfToken && document.querySelector('.app-shell'))`).catch(() => false)) return true;
  }
  return false;
}

console.log('=== HỒI QUY: trang Nhân sự + Khối lớp theo từng vai ===\n');
for (const [label, phone] of ROLES) {
  if (!await login(phone)) { console.log(`${label.padEnd(14)} ĐĂNG NHẬP THẤT BẠI`); continue; }
  const info = await ev(`(() => { const d=Alpine.$data(document.querySelector('.app-shell'));
    return { role:d.user.role, org:d.permissions.org[d.user.role], staff:d.permissions.staff[d.user.role],
             seesStaff:d.visibleFlat().some(m=>m.key==='staff'), seesOrg:d.visibleFlat().some(m=>m.key==='org') }; })()`);

  const pageErrors = [];
  for (const key of ['staff', 'org']) {
    bucket = []; recording = true;
    await ev(`Alpine.$data(document.querySelector('.app-shell')).openModule('${key}')`);
    await sleep(1800);
    // thử mở modal duyệt nếu có nút
    await ev(`(() => { const b=[...document.querySelectorAll('button')].find(x=>(x.innerText||'').trim()==='Duyệt');
      if(b) b.click(); return !!b; })()`);
    await sleep(1200);
    recording = false;
    if (bucket.length) pageErrors.push({ key, errs: [...new Set(bucket)].slice(0, 4) });
  }
  const st = pageErrors.length ? 'CÓ LỖI' : 'sạch';
  console.log(`${label.padEnd(14)} org=${String(info.org).padEnd(5)} staff=${String(info.staff).padEnd(5)} staff-menu=${info.seesStaff?'có':'không'} org-menu=${info.seesOrg?'có':'không'}  -> ${st}`);
  pageErrors.forEach(pe => pe.errs.forEach(e => console.log(`      [${pe.key}] ${e}`)));
}

cdp.close(); chrome.child.kill();
