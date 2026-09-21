/**
 * Kiểm chứng dứt khoát: với mỗi lớp nghi vấn, chèn một phần tử thử nghiệm
 * và đo xem lớp đó có tác dụng thật trong trình duyệt hay không.
 */
import { launchChrome, CDP } from './cdp.mjs';
import fs from 'node:fs';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const OUT = 'G:\\xampp\\htdocs\\tntt\\scratch\\review\\out';

const chrome = await launchChrome({ port: 9226, userDataDir: 'G:\\xampp\\htdocs\\tntt\\scratch\\review\\profile-css' });
const cdp = new CDP(chrome.wsUrl); await cdp.connect();
const { result: t } = await cdp.send('Target.createTarget', { url: 'about:blank' });
const sid = (await cdp.send('Target.attachToTarget', { targetId: t.targetId, flatten: true })).result.sessionId;
await cdp.send('Page.enable', {}, sid); await cdp.send('Runtime.enable', {}, sid);
async function ev(expr) {
  const r = await cdp.send('Runtime.evaluate', { expression: expr, awaitPromise: true, returnByValue: true }, sid);
  if (r.result.exceptionDetails) throw new Error('eval: ' + (r.result.exceptionDetails.exception?.description || r.result.exceptionDetails.text));
  return r.result.result.value;
}
await cdp.send('Page.navigate', { url: 'http://127.0.0.1:8099/' }, sid);
await sleep(3000);

// Danh sách lớp nghi vấn (từ audit) + vài lớp đối chứng CHẮC CHẮN có trong build
const SUSPECT = JSON.parse(fs.readFileSync(OUT + '\\missing-classes.json', 'utf8')).map(x => x.cls);
const CONTROL = ['p-5', 'mt-4', 'text-sm', 'bg-white', 'rounded-xl', 'grid-cols-4', 'px-4', 'pb-2'];

const probe = await ev(`(() => {
  const suspects = ${JSON.stringify(SUSPECT)};
  const controls = ${JSON.stringify(CONTROL)};
  const host = document.createElement('div');
  host.style.cssText = 'position:fixed;left:-9999px;top:0;width:400px;height:400px';
  document.body.appendChild(host);

  const measure = (cls) => {
    const el = document.createElement('div');
    el.className = cls;
    el.textContent = 'x';
    host.appendChild(el);
    const s = getComputedStyle(el);
    const snap = [s.padding, s.margin, s.display, s.position, s.color, s.backgroundColor,
                  s.gridTemplateColumns, s.width, s.height, s.textDecorationLine, s.cursor,
                  s.userSelect, s.borderStyle, s.borderWidth, s.fontSize, s.textAlign, s.overflowX,
                  s.objectFit, s.textTransform, s.scrollBehavior, s.boxShadow, s.borderRightWidth,
                  s.borderTopWidth, s.borderColor, s.alignSelf, s.listStyleType, s.whiteSpace,
                  s.verticalAlign, s.opacity, s.flexGrow, s.order, s.gap, s.justifyContent, s.borderRadius].join('|');
    el.remove();
    return snap;
  };

  const baseline = measure('');
  const out = { controls: {}, suspects: {} };
  for (const c of controls) out.controls[c] = measure(c) !== baseline;
  for (const c of suspects) out.suspects[c] = measure(c) !== baseline;
  host.remove();
  return out;
})()`);

const deadSuspects = Object.entries(probe.suspects).filter(([, v]) => !v).map(([k]) => k);
const liveSuspects = Object.entries(probe.suspects).filter(([, v]) => v).map(([k]) => k);
const deadControls = Object.entries(probe.controls).filter(([, v]) => !v).map(([k]) => k);

console.log('=== LỚP ĐỐI CHỨNG (phải HOẠT ĐỘNG) ===');
console.log('  hoạt động:', Object.entries(probe.controls).filter(([, v]) => v).map(([k]) => k).join(', '));
console.log('  KHÔNG hoạt động:', deadControls.join(', ') || '(không có)');

console.log('\n=== LỚP NGHI VẤN ===');
console.log('  KHÔNG có tác dụng (lỗi thật):', deadSuspects.length);
console.log('  CÓ tác dụng (audit sai):', liveSuspects.length, liveSuspects.join(', ') || '');

console.log('\n--- Danh sách lớp KHÔNG có tác dụng ---');
deadSuspects.forEach(c => console.log('   ' + c));

fs.writeFileSync(OUT + '\\css-effect-verify.json', JSON.stringify({ deadSuspects, liveSuspects, deadControls, controls: probe.controls }, null, 2));
console.log('\n→ Đã ghi css-effect-verify.json');
cdp.close(); chrome.child.kill();
