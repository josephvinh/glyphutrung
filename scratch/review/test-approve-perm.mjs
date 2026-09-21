/**
 * KIỂM CHỨNG LỖI PHÂN QUYỀN Ở LUỒNG DUYỆT THÀNH VIÊN
 *
 * Giả thuyết:
 *   public/api/org.php dòng 21 đặt `require_permission('org','edit')` cho MỌI action,
 *   kể cả approveMember/rejectMember — vốn thuộc module "Nhân sự" (staff).
 *
 *   Quyền thực tế:  bdh          -> org=view, staff=edit
 *                   truong_khoi  -> org=edit, staff=view
 *
 *   => BĐH (người ĐƯỢC giao duyệt) bị chặn 403.
 *   => Trưởng khối (KHÔNG được giao duyệt) lại duyệt được  -> leo thang quyền.
 *
 * Cách kiểm: tạo 1 hồ sơ 'chờ duyệt' thật, rồi lần lượt gọi approveMember
 * bằng tài khoản bdh và truong_khoi, ghi lại mã HTTP + thông điệp.
 */
import { launchChrome, CDP } from './cdp.mjs';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const BASE = 'http://127.0.0.1:8099/';
const PASS = 'tntt@2026';
const TEST_PHONE = '0999999001';
const TEST_NAME = 'ZZ Kiểm Thử Duyệt';

const chrome = await launchChrome({ port: 9231, userDataDir: 'G:\\xampp\\htdocs\\tntt\\scratch\\review\\profile-perm' });
const cdp = new CDP(chrome.wsUrl); await cdp.connect();
const { result: t } = await cdp.send('Target.createTarget', { url: 'about:blank' });
const sid = (await cdp.send('Target.attachToTarget', { targetId: t.targetId, flatten: true })).result.sessionId;
await cdp.send('Page.enable', {}, sid);
await cdp.send('Runtime.enable', {}, sid);
await cdp.send('Network.enable', {}, sid);

async function ev(e) {
  const r = await cdp.send('Runtime.evaluate', { expression: e, awaitPromise: true, returnByValue: true }, sid);
  if (r.result.exceptionDetails) throw new Error('eval: ' + (r.result.exceptionDetails.exception?.description || r.result.exceptionDetails.text));
  return r.result.result.value;
}

await cdp.send('Page.navigate', { url: BASE }, sid);
await sleep(2500);

// ---------- 1. Tạo hồ sơ 'chờ duyệt' qua endpoint công khai ----------
console.log('=== 1. Đăng ký hồ sơ thử nghiệm (endpoint công khai) ===');
const reg = await ev(`(async () => {
  const r = await fetch('api/auth.php?action=register', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      holyName: 'Phaolô', fullName: ${JSON.stringify(TEST_NAME)},
      phone: ${JSON.stringify(TEST_PHONE)}, password: 'test123456',
      birthDate: '1990-01-01', danhXung: 'glv', note: 'Hồ sơ kiểm thử phân quyền'
    })
  });
  return { status: r.status, body: await r.text() };
})()`);
console.log('   ->', reg.status, reg.body.slice(0, 200));

// Lấy id của hồ sơ vừa tạo
const newId = await ev(`(async () => {
  const r = await fetch('api/data.php?part=core', { cache: 'no-store' });
  const j = await r.json();
  const m = (j.members || []).find(x => x.phone === ${JSON.stringify(TEST_PHONE)});
  return m ? m.id : null;
})()`);
console.log('   -> id hồ sơ chờ duyệt:', newId);

// ---------- 2. Đăng nhập theo từng vai rồi thử duyệt ----------
async function loginAs(phone) {
  await ev(`(async()=>{ try{ await fetch('api/auth.php?action=logout',{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'}); }catch(e){} })()`);
  await sleep(500);
  await cdp.send('Page.navigate', { url: BASE }, sid);
  await sleep(2500);
  const ok = await ev(`(() => {
    const inputs = [...document.querySelectorAll('input')];
    const p = inputs.find(i => i.type === 'tel' || i.autocomplete === 'username');
    const w = inputs.find(i => i.type === 'password');
    if (!p || !w) return false;
    const set = (el, v) => { Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(el, v);
                             el.dispatchEvent(new Event('input',{bubbles:true})); };
    set(p, ${JSON.stringify(phone)}); set(w, ${JSON.stringify(PASS)});
    return true;
  })()`);
  if (ok) { await ev(`document.querySelector('form').requestSubmit()`); }
  await sleep(5000);
  return ev(`(() => { const el=document.querySelector('.app-shell');
      if(!el||!window.Alpine) return null; const d=Alpine.$data(el);
      return { role:d.user.role, name:d.user.fullName, csrf:d.csrfToken,
               staffPerm:d.permissions.staff ? d.permissions.staff[d.user.role] : null,
               orgPerm:d.permissions.org ? d.permissions.org[d.user.role] : null,
               canEditStaff: d.canEditModule('staff'), canEditOrg: d.canEditModule('org'),
               seesStaffModule: d.visibleFlat().some(m=>m.key==='staff'),
               pendingCount: d.pendingMembers ? d.pendingMembers.length : null,
               firstClass: (d.classes && d.classes[0]) ? d.classes[0].name : null }; })()`);
}

async function tryApprove(csrf, className) {
  return ev(`(async () => {
    const r = await fetch('api/org.php?action=approveMember', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': ${JSON.stringify(csrf)} },
      body: JSON.stringify({ id: ${newId}, role: 'glv', className: ${JSON.stringify(className)} })
    });
    return { status: r.status, body: (await r.text()).slice(0, 300) };
  })()`);
}

async function tryReject(csrf) {
  return ev(`(async () => {
    const r = await fetch('api/org.php?action=rejectMember', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': ${JSON.stringify(csrf)} },
      body: JSON.stringify({ id: ${newId} })
    });
    return { status: r.status, body: (await r.text()).slice(0, 300) };
  })()`);
}

const results = {};

for (const [label, phone] of [['bdh', '0902000002'], ['truong_khoi', '0902000003']]) {
  console.log(`\n=== 2. Vai ${label} (${phone}) ===`);
  const who = await loginAs(phone);
  if (!who) { console.log('   ĐĂNG NHẬP THẤT BẠI'); continue; }
  console.log(`   ${who.name} | role=${who.role}`);
  console.log(`   quyền: org=${who.orgPerm}  staff=${who.staffPerm}`);
  console.log(`   canEditModule: org=${who.canEditOrg}  staff=${who.canEditStaff}`);
  console.log(`   thấy module Nhân sự trong menu: ${who.seesStaffModule}`);
  console.log(`   pendingMembers đếm được: ${who.pendingCount}`);
  console.log(`   lớp dùng thử: ${who.firstClass}`);

  const ap = await tryApprove(who.csrf, who.firstClass);
  console.log(`   >> approveMember  -> HTTP ${ap.status}  ${ap.body}`);
  results[label] = { who, approve: ap };
}

// Nếu hồ sơ vẫn còn chờ duyệt, thử reject bằng bdh để xác nhận cùng gốc lỗi
console.log('\n=== 3. Kiểm tra rejectMember (cùng gốc lỗi) ===');
const who2 = await loginAs('0902000002');
const rj = await tryReject(who2.csrf);
console.log(`   bdh rejectMember -> HTTP ${rj.status}  ${rj.body}`);
results['bdh_reject'] = rj;

console.log('\n=== KẾT LUẬN ===');
console.log(JSON.stringify(results, null, 2));

cdp.close(); chrome.child.kill();
