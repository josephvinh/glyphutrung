/**
 * KIỂM CHỨNG CUỐI — duyệt THẬT bằng id thật.
 *  1. bdh duyệt 1 hồ sơ thật       -> phải thành công, DB đổi trạng thái
 *  2. truong_khoi duyệt hồ sơ thật -> phải bị chặn 403, DB KHÔNG đổi
 */
import { launchChrome, CDP } from './cdp.mjs';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const BASE = 'http://127.0.0.1:8099/';
const PASS = 'tntt@2026';
const ID_BDH = Number(process.argv[2]);   // hồ sơ để bdh duyệt
const ID_TK = Number(process.argv[3]);    // hồ sơ để truong_khoi thử duyệt

const chrome = await launchChrome({ port: 9235, userDataDir: 'G:\\xampp\\htdocs\\tntt\\scratch\\review\\profile-e2e' });
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
async function login(phone) {
  await ev(`(async()=>{ try{ await fetch('api/auth.php?action=logout',{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'}); }catch(e){} })()`);
  await sleep(600);
  await cdp.send('Page.navigate', { url: BASE }, sid);
  await sleep(3000);
  const hasForm = await ev(`!!document.querySelector('input[type=password]')`);
  if (hasForm) {
    const filled = await ev(`(() => {
      const inputs=[...document.querySelectorAll('input')];
      const p=inputs.find(i=>i.type==='tel'||i.autocomplete==='username');
      const w=inputs.find(i=>i.type==='password');
      if(!p||!w) return false;
      const set=(el,v)=>{Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(el,v);
                         el.dispatchEvent(new Event('input',{bubbles:true}));};
      set(p,${JSON.stringify(phone)}); set(w,${JSON.stringify(PASS)});
      return true;
    })()`);
    if (filled) await ev(`document.querySelector('form').requestSubmit()`);
  }
  // Chờ app shell + window.TNTT sẵn sàng (tối đa 20s)
  for (let i = 0; i < 40; i++) {
    await sleep(500);
    const ready = await ev(`!!(window.TNTT && window.TNTT.csrfToken && document.querySelector('.app-shell') && window.Alpine)`).catch(() => false);
    if (ready) {
      const who = await ev(`Alpine.$data(document.querySelector('.app-shell')).user.role`).catch(() => '?');
      console.log(`    (đăng nhập ${phone} -> vai ${who})`);
      return true;
    }
  }
  console.log(`    !! KHÔNG đăng nhập được ${phone}`);
  return false;
}
async function approve(id, cls) {
  return ev(`(async () => {
    const r = await fetch('api/org.php?action=approveMember', {
      method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN': window.TNTT.csrfToken},
      body: JSON.stringify({ id:${id}, role:'glv', className:${JSON.stringify(cls)} })
    });
    return { status:r.status, body:(await r.text()).slice(0,200) };
  })()`);
}

console.log('### BƯỚC 1: truong_khoi (staff=view) thử duyệt hồ sơ thật id=' + ID_TK);
await login('0902000003');
const tk = await approve(ID_TK, 'Khai Tâm 1');
console.log(`    -> HTTP ${tk.status}  ${tk.body}`);

console.log('\n### BƯỚC 2: bdh (staff=edit) duyệt hồ sơ thật id=' + ID_BDH);
await login('0902000002');
const bd = await approve(ID_BDH, 'Khai Tâm 1');
console.log(`    -> HTTP ${bd.status}  ${bd.body}`);

cdp.close(); chrome.child.kill();
