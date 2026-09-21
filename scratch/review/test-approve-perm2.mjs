/**
 * Kiểm chứng phần 2: vai trò KHÔNG được giao duyệt (truong_khoi: org=edit, staff=view)
 * có vượt được cổng phân quyền của org.php không?
 *
 * Dùng profile Chrome MỚI (cookie sạch) để tránh lỗi CSRF như lần trước.
 * Đồng thời kiểm tra giao diện: trưởng khối có thấy nút Duyệt/Từ chối không?
 */
import { launchChrome, CDP } from './cdp.mjs';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const BASE = 'http://127.0.0.1:8099/';
const PASS = 'tntt@2026';
const TEST_PHONE = '0999999002';
const TEST_NAME = 'ZZ Kiem Thu Duyet 2';

const chrome = await launchChrome({ port: 9232, userDataDir: 'G:\\xampp\\htdocs\\tntt\\scratch\\review\\profile-perm2' });
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

// ---- tạo hồ sơ chờ duyệt mới ----
await cdp.send('Page.navigate', { url: BASE }, sid);
await sleep(2500);
const reg = await ev(`(async () => {
  const r = await fetch('api/auth.php?action=register', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ holyName:'Phaolô', fullName:${JSON.stringify(TEST_NAME)},
      phone:${JSON.stringify(TEST_PHONE)}, password:'test123456',
      birthDate:'1990-01-01', danhXung:'glv', note:'Kiem thu phan quyen 2' })
  });
  return { status:r.status, body: await r.text() };
})()`);
console.log('Tạo hồ sơ:', reg.status, reg.body);

// ---- đăng nhập trưởng khối ----
await cdp.send('Page.navigate', { url: BASE }, sid);
await sleep(2500);
await ev(`(() => {
  const inputs=[...document.querySelectorAll('input')];
  const p=inputs.find(i=>i.type==='tel'||i.autocomplete==='username');
  const w=inputs.find(i=>i.type==='password');
  const set=(el,v)=>{Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(el,v);
                     el.dispatchEvent(new Event('input',{bubbles:true}));};
  set(p,'0902000003'); set(w,${JSON.stringify(PASS)});
})()`);
await ev(`document.querySelector('form').requestSubmit()`);
await sleep(6000);

const who = await ev(`(() => {
  const el=document.querySelector('.app-shell'); if(!el||!window.Alpine) return null;
  const d=Alpine.$data(el);
  return { role:d.user.role, name:d.user.fullName, csrf:d.csrfToken,
           orgPerm:d.permissions.org[d.user.role], staffPerm:d.permissions.staff[d.user.role],
           canEditStaff:d.canEditModule('staff'), canManageOrg:d.canManageOrg,
           pendingCount:d.pendingMembers.length,
           firstClass:(d.classes&&d.classes[0])?d.classes[0].name:null };
})()`);
console.log('\n=== Vai truong_khoi ===');
console.log(JSON.stringify(who, null, 2));

// ---- thử duyệt qua API ----
const ap = await ev(`(async () => {
  const r = await fetch('api/org.php?action=approveMember', {
    method:'POST',
    headers:{'Content-Type':'application/json','X-CSRF-TOKEN':${JSON.stringify(who.csrf)}},
    body: JSON.stringify({ id:null, role:'glv', className:${JSON.stringify(who.firstClass)} })
  });
  return { status:r.status, body: await r.text() };
})()`);
console.log(`\n>> approveMember (payload id=null để chỉ thử CỔNG PHÂN QUYỀN) -> HTTP ${ap.status}`);
console.log('   ', ap.body);

// ---- kiểm tra giao diện: có thấy nút Duyệt/Từ chối không? ----
await ev(`Alpine.$data(document.querySelector('.app-shell')).openModule('staff')`);
await sleep(2500);
const ui = await ev(`(() => {
  const vis = el => { if(!el) return false; const s=getComputedStyle(el);
    return s.display!=='none' && s.visibility!=='hidden' && el.getBoundingClientRect().width>0; };
  const btns=[...document.querySelectorAll('button')];
  const findByText = t => btns.filter(b => (b.innerText||'').trim() === t && vis(b)).length;
  const pendingBlock = [...document.querySelectorAll('div')].find(d => (d.innerText||'').includes('người đăng ký chờ duyệt'));
  return {
    khoiChoDuyetHienThi: pendingBlock ? vis(pendingBlock) : false,
    soNutDuyet: findByText('Duyệt'),
    soNutTuChoi: findByText('Từ chối'),
    soNutThemThanhVien: findByText('Thêm thành viên'),
    textKhoi: pendingBlock ? (pendingBlock.innerText||'').trim().replace(/\\n+/g,' | ').slice(0,160) : null,
  };
})()`);
console.log('\n=== Giao diện trang Nhân sự khi là trưởng khối ===');
console.log(JSON.stringify(ui, null, 2));

cdp.close(); chrome.child.kill();
