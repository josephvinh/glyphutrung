/**
 * Kiểm chứng: bản DEPLOY (git HEAD) có lỗi thứ hai — thiếu cột `status`
 * trong SELECT của approveMember/rejectMember/resetPassword.
 *
 * Kỳ vọng khi mô phỏng đúng bản deploy: dù là admin (qua được cả 2 cổng quyền),
 * approveMember vẫn báo "Tài khoản này đã được duyệt rồi." -> KHÔNG AI duyệt được.
 */
import { launchChrome, CDP } from './cdp.mjs';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const BASE = 'http://127.0.0.1:8099/';
const PASS = 'tntt@2026';
const PHONE = '0999' + String(Date.now()).slice(-6);

const chrome = await launchChrome({ port: 9238, userDataDir: 'G:\\xampp\\htdocs\\tntt\\scratch\\review\\profile-sim2' });
const cdp = new CDP(chrome.wsUrl); await cdp.connect();
const { result: t } = await cdp.send('Target.createTarget', { url: 'about:blank' });
const sid = (await cdp.send('Target.attachToTarget', { targetId: t.targetId, flatten: true })).result.sessionId;
await cdp.send('Page.enable', {}, sid); await cdp.send('Runtime.enable', {}, sid);

async function ev(e) {
  const r = await cdp.send('Runtime.evaluate', { expression: e, awaitPromise: true, returnByValue: true }, sid);
  if (r.result.exceptionDetails) throw new Error('eval: ' + (r.result.exceptionDetails.exception?.description || r.result.exceptionDetails.text));
  return r.result.result.value;
}
async function gotoAndWait(ms = 3000) {
  await cdp.send('Page.navigate', { url: BASE }, sid);
  await sleep(ms);
  for (let i = 0; i < 60; i++) {
    const ready = await ev(`!!(window.TNTT && window.TNTT.csrfToken && document.querySelector('.app-shell'))`).catch(() => false);
    if (ready) return true;
    await sleep(400);
  }
  return false;
}
async function login(phone) {
  await ev(`(async()=>{ try{ await fetch('api/auth.php?action=logout',{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'}); }catch(e){} })()`).catch(() => {});
  await sleep(600);
  await cdp.send('Page.navigate', { url: BASE }, sid);
  await sleep(3000);
  if (await ev(`!!document.querySelector('input[type=password]')`).catch(() => false)) {
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
  await sleep(4000);
  // Đợi qua cơn reload sau đăng nhập rồi nạp lại trang sạch
  return gotoAndWait();
}

// ---- tạo hồ sơ chờ duyệt ----
await cdp.send('Page.navigate', { url: BASE }, sid);
await sleep(3000);
const reg = await ev(`(async () => {
  const r = await fetch('api/auth.php?action=register', { method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ holyName:'Test', fullName:'ZZ Sim Deploy', phone:${JSON.stringify(PHONE)},
      password:'test123456', birthDate:'1990-01-01', danhXung:'glv', note:'sim deploy' }) });
  return { status:r.status, body: await r.text() };
})()`);
console.log('Số ĐT thử:', PHONE);
console.log('Tạo hồ sơ chờ duyệt:', reg.status, reg.body);

// ---- admin thử duyệt ----
console.log('\n=== ADMIN thử duyệt (bản MÔ PHỎNG DEPLOY: SELECT thiếu cột status) ===');
await login('0901000001');
const who = await ev(`(() => { const d=Alpine.$data(document.querySelector('.app-shell'));
  return { role:d.user.role, org:d.permissions.org[d.user.role], staff:d.permissions.staff[d.user.role],
           tokenOk: !!(window.TNTT && window.TNTT.csrfToken) }; })()`);
console.log('   vai:', JSON.stringify(who));

const ap = await ev(`(async () => {
  const r = await fetch('api/org.php?action=approveMember', { method:'POST',
    headers:{'Content-Type':'application/json','X-CSRF-TOKEN': window.TNTT.csrfToken},
    body: JSON.stringify({ id: 0, role:'glv', className:'Khai Tâm 1' }) });
  return { status:r.status, body:(await r.text()).slice(0,240) };
})()`);
console.log(`   approveMember -> HTTP ${ap.status}  ${ap.body}`);
console.log(`\n   Số ĐT để dọn: ${PHONE}`);

cdp.close(); chrome.child.kill();
