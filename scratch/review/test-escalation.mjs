/**
 * KIỂM CHỨNG LEO THANG QUYỀN — dùng ĐÚNG nguồn token của app
 * (window.TNTT.csrfToken, không phải Alpine data như lần trước).
 *
 * Câu hỏi: trưởng khối (org=edit, staff=view) — người KHÔNG được giao
 * nhiệm vụ duyệt thành viên — có duyệt được hồ sơ qua API không?
 */
import { launchChrome, CDP } from './cdp.mjs';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const BASE = 'http://127.0.0.1:8099/';
const TARGET_ID = Number(process.argv[2] || 64);
const TARGET_CLASS = process.argv[3] || 'Khai Tâm 1';

const chrome = await launchChrome({ port: 9233, userDataDir: 'G:\\xampp\\htdocs\\tntt\\scratch\\review\\profile-esc' });
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

await cdp.send('Page.navigate', { url: BASE }, sid);
await sleep(2500);
await ev(`(() => {
  const inputs=[...document.querySelectorAll('input')];
  const p=inputs.find(i=>i.type==='tel'||i.autocomplete==='username');
  const w=inputs.find(i=>i.type==='password');
  const set=(el,v)=>{Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(el,v);
                     el.dispatchEvent(new Event('input',{bubbles:true}));};
  set(p,'0902000003'); set(w,'tntt@2026');
})()`);
await ev(`document.querySelector('form').requestSubmit()`);
await sleep(6000);

const who = await ev(`(() => {
  const el=document.querySelector('.app-shell'); if(!el||!window.Alpine) return null;
  const d=Alpine.$data(el);
  return { role:d.user.role, name:d.user.fullName,
           orgPerm:d.permissions.org[d.user.role], staffPerm:d.permissions.staff[d.user.role],
           canEditStaff:d.canEditModule('staff'),
           tokenLen:(window.TNTT&&window.TNTT.csrfToken||'').length };
})()`);
console.log('=== Đăng nhập:', JSON.stringify(who));

const ap = await ev(`(async () => {
  const r = await fetch('api/org.php?action=approveMember', {
    method:'POST',
    headers:{'Content-Type':'application/json','X-CSRF-TOKEN': window.TNTT.csrfToken},
    body: JSON.stringify({ id:${TARGET_ID}, role:'glv', className:${JSON.stringify(TARGET_CLASS)} })
  });
  return { status:r.status, body: await r.text() };
})()`);
console.log(`\n=== truong_khoi gọi approveMember(id=${TARGET_ID}, lớp="${TARGET_CLASS}") ===`);
console.log(`    HTTP ${ap.status}  ${ap.body}`);

cdp.close(); chrome.child.kill();
