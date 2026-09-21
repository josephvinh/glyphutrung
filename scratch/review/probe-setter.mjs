import { launchChrome, CDP } from './cdp.mjs';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const chrome = await launchChrome({ port: 9225, userDataDir: 'G:\\xampp\\htdocs\\tntt\\scratch\\review\\profile-probe' });
const cdp = new CDP(chrome.wsUrl); await cdp.connect();
const { result: t } = await cdp.send('Target.createTarget', { url: 'about:blank' });
const sid = (await cdp.send('Target.attachToTarget', { targetId: t.targetId, flatten: true })).result.sessionId;
await cdp.send('Page.enable', {}, sid); await cdp.send('Runtime.enable', {}, sid);
async function ev(expr) {
  const r = await cdp.send('Runtime.evaluate', { expression: expr, awaitPromise: true, returnByValue: true }, sid);
  if (r.result.exceptionDetails) return 'ERR: ' + (r.result.exceptionDetails.exception?.description || r.result.exceptionDetails.text);
  return r.result.result.value;
}
await cdp.send('Page.navigate', { url: 'http://127.0.0.1:8099/' }, sid);
await sleep(2500);

console.log('A chained:', await ev(`(()=>{const i=[...document.querySelectorAll('input')];const p=i.find(x=>x.type==='tel');try{Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(p,'X');return 'ok='+p.value}catch(e){return 'ERR '+e.message}})()`));
console.log('B variable:', await ev(`(()=>{const i=[...document.querySelectorAll('input')];const p=i.find(x=>x.type==='tel');try{const d=Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value');d.set.call(p,'Y');return 'ok='+p.value}catch(e){return 'ERR '+e.message}})()`));
console.log('C direct:', await ev(`(()=>{const i=[...document.querySelectorAll('input')];const p=i.find(x=>x.type==='tel');p.value='Z';p.dispatchEvent(new Event('input',{bubbles:true}));return 'ok='+p.value})()`));

cdp.close(); chrome.child.kill();
