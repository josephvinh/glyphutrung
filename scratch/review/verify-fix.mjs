/**
 * KIỂM CHỨNG SAU KHI SỬA — lỗi phân quyền duyệt thành viên.
 *
 * Kỳ vọng MỚI:
 *   - bdh (staff=edit)        : approveMember -> 200 ok        (trước đây 403)
 *   - truong_khoi (staff=view): approveMember -> 403 quyền     (trước đây 200 ok)
 *   - truong_khoi             : KHÔNG còn thấy nút Duyệt/Từ chối
 *   - bdh                     : VẪN thấy nút Duyệt/Từ chối
 *   - Hồi quy: action thuộc org (saveBlock) vẫn theo quyền org
 *       + bdh (org=view)        -> 403
 *       + truong_khoi (org=edit)-> qua cổng quyền
 */
import { launchChrome, CDP } from './cdp.mjs';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const BASE = 'http://127.0.0.1:8099/';
const PASS = 'tntt@2026';

const chrome = await launchChrome({ port: 9234, userDataDir: 'G:\\xampp\\htdocs\\tntt\\scratch\\review\\profile-verify' });
const cdp = new CDP(chrome.wsUrl); await cdp.connect();
const { result: t } = await cdp.send('Target.createTarget', { url: 'about:blank' });
const sid = (await cdp.send('Target.attachToTarget', { targetId: t.targetId, flatten: true })).result.sessionId;
await cdp.send('Page.enable', {}, sid);
await cdp.send('Runtime.enable', {}, sid);
async function ev(e) {
  const r = await cdp.send('Runtime.evaluate', { expression: e, awaitPromise: true, returnByValue: true }, sid);
  if (r.result.exceptionDetails) throw new Error('eval: ' + (r.result.exceptionDetails.exception?.description || r.result.exceptionDetails.text));
  return r.result.result.value;
}

// ---- tạo 2 hồ sơ chờ duyệt (một cho mỗi vai thử) ----
await cdp.send('Page.navigate', { url: BASE }, sid);
await sleep(2500);
for (const phone of ['0999999101', '0999999102']) {
  const r = await ev(`(async () => {
    const r = await fetch('api/auth.php?action=register', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ holyName:'Test', fullName:'ZZ Verify ${phone}',
        phone:${JSON.stringify(phone)}, password:'test123456',
        birthDate:'1990-01-01', danhXung:'glv', note:'verify fix' })
    });
    return { status:r.status, body: await r.text() };
  })()`);
  console.log(`tạo hồ sơ ${phone}:`, r.status, r.body);
}

async function login(phone) {
  await ev(`(async()=>{ try{ await fetch('api/auth.php?action=logout',{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'}); }catch(e){} })()`);
  await sleep(400);
  await cdp.send('Page.navigate', { url: BASE }, sid);
  await sleep(2500);
  const ok = await ev(`(() => {
    const inputs=[...document.querySelectorAll('input')];
    const p=inputs.find(i=>i.type==='tel'||i.autocomplete==='username');
    const w=inputs.find(i=>i.type==='password');
    if(!p||!w) return false;
    const set=(el,v)=>{Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(el,v);
                       el.dispatchEvent(new Event('input',{bubbles:true}));};
    set(p,${JSON.stringify(phone)}); set(w,${JSON.stringify(PASS)}); return true;
  })()`);
  if (ok) await ev(`document.querySelector('form').requestSubmit()`);
  await sleep(5500);
  return ev(`(() => { const el=document.querySelector('.app-shell');
    if(!el||!window.Alpine) return null; const d=Alpine.$data(el);
    return { role:d.user.role, name:d.user.fullName,
             orgPerm:d.permissions.org[d.user.role], staffPerm:d.permissions.staff[d.user.role] }; })()`);
}

async function call(action, body) {
  return ev(`(async () => {
    const r = await fetch('api/org.php?action=${action}', {
      method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN': window.TNTT.csrfToken},
      body: JSON.stringify(${JSON.stringify(body)})
    });
    return { status:r.status, body:(await r.text()).slice(0,180) };
  })()`);
}

async function uiCheck() {
  await ev(`Alpine.$data(document.querySelector('.app-shell')).openModule('staff')`);
  await sleep(2200);
  return ev(`(() => {
    const vis = el => { if(!el) return false; const s=getComputedStyle(el);
      return s.display!=='none' && s.visibility!=='hidden' && el.getBoundingClientRect().width>0; };
    const btns=[...document.querySelectorAll('button')];
    const n = t => btns.filter(b => (b.innerText||'').trim()===t && vis(b)).length;
    return { soNutDuyet:n('Duyệt'), soNutTuChoi:n('Từ chối'),
             thayThongBaoChiXem: [...document.querySelectorAll('p')].some(p=>vis(p)&&(p.innerText||'').includes('chỉ xem')) };
  })()`);
}

const out = {};

// ================= bdh =================
console.log('\n========== VAI bdh (staff=edit) ==========');
let who = await login('0902000002');
console.log('  ', JSON.stringify(who));
out.bdh_ui = await uiCheck();
console.log('   UI:', JSON.stringify(out.bdh_ui));
out.bdh_approve = await call('approveMember', { id: 0, role: 'glv', className: 'Khai Tâm 1' });
console.log(`   approveMember(id=0) -> HTTP ${out.bdh_approve.status}  ${out.bdh_approve.body}`);

// ================= truong_khoi =================
console.log('\n========== VAI truong_khoi (staff=view) ==========');
who = await login('0902000003');
console.log('  ', JSON.stringify(who));
out.tk_ui = await uiCheck();
console.log('   UI:', JSON.stringify(out.tk_ui));
out.tk_approve = await call('approveMember', { id: 0, role: 'glv', className: 'Khai Tâm 1' });
console.log(`   approveMember(id=0) -> HTTP ${out.tk_approve.status}  ${out.tk_approve.body}`);

// ---- hồi quy: action org vẫn theo quyền org ----
out.tk_saveBlock = await call('saveBlock', { name: '' });
console.log(`   [hồi quy] saveBlock (org) -> HTTP ${out.tk_saveBlock.status}  ${out.tk_saveBlock.body}`);

console.log('\n========== VAI bdh: hồi quy action org ==========');
who = await login('0902000002');
out.bdh_saveBlock = await call('saveBlock', { name: '' });
console.log(`   [hồi quy] saveBlock (org) -> HTTP ${out.bdh_saveBlock.status}  ${out.bdh_saveBlock.body}`);

console.log('\n========== KẾT LUẬN ==========');
const okBdh = out.bdh_approve.status !== 403;
const okTk = out.tk_approve.status === 403;
console.log(`  bdh duyệt được (không còn 403 quyền) : ${okBdh ? 'ĐẠT' : 'HỎNG'} (HTTP ${out.bdh_approve.status})`);
console.log(`  truong_khoi bị chặn đúng            : ${okTk ? 'ĐẠT' : 'HỎNG'} (HTTP ${out.tk_approve.status})`);
console.log(`  truong_khoi KHÔNG thấy nút Duyệt     : ${out.tk_ui.soNutDuyet === 0 ? 'ĐẠT' : 'HỎNG'} (${out.tk_ui.soNutDuyet} nút)`);
console.log(`  bdh VẪN thấy nút Duyệt               : ${out.bdh_ui.soNutDuyet > 0 ? 'ĐẠT' : 'HỎNG'} (${out.bdh_ui.soNutDuyet} nút)`);

cdp.close(); chrome.child.kill();
