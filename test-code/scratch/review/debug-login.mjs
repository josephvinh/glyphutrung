/** Debug login flow on the PHP built-in server. */
import { launchChrome, CDP } from './cdp.mjs';
const BASE = 'http://127.0.0.1:8099/';
const sleep = ms => new Promise(r => setTimeout(r, ms));

const chrome = await launchChrome({ port: 9224, userDataDir: 'G:\\xampp\\htdocs\\tntt\\scratch\\review\\profile-dbg' });
const cdp = new CDP(chrome.wsUrl);
await cdp.connect();
const { result: t } = await cdp.send('Target.createTarget', { url: 'about:blank' });
const sid = (await cdp.send('Target.attachToTarget', { targetId: t.targetId, flatten: true })).result.sessionId;

const lines = [];
cdp.on('Runtime.consoleAPICalled', m => lines.push(`[${m.params.type}] ` + m.params.args.map(a => a.value ?? a.description).join(' ')), sid);
cdp.on('Runtime.exceptionThrown', m => lines.push('[exc] ' + (m.params.exceptionDetails.exception?.description || m.params.exceptionDetails.text)), sid);
cdp.on('Network.responseReceived', m => {
  const u = m.params.response.url;
  if (u.includes('api/')) lines.push(`[net] ${m.params.response.status} ${u}`);
}, sid);
cdp.on('Network.requestWillBeSent', m => {
  if (m.params.request.url.includes('api/') && m.params.request.method === 'POST') {
    lines.push(`[req] POST ${m.params.request.url} body=${m.params.request.postData}`);
  }
}, sid);

await cdp.send('Page.enable', {}, sid);
await cdp.send('Runtime.enable', {}, sid);
await cdp.send('Network.enable', {}, sid);

async function ev(expr) {
  const r = await cdp.send('Runtime.evaluate', { expression: expr, awaitPromise: true, returnByValue: true }, sid);
  if (r.result.exceptionDetails) return { __err: r.result.exceptionDetails.exception?.description };
  return r.result.result.value;
}

await cdp.send('Page.navigate', { url: BASE }, sid);
await sleep(2500);

console.log('has form:', await ev(`!!document.querySelector('form')`));
console.log('alpine:', await ev(`typeof window.Alpine`));

await ev(`(() => {
  const inputs = [...document.querySelectorAll('input')];
  const phone = inputs.find(i => i.type === 'tel' || i.autocomplete === 'username');
  const pw = inputs.find(i => i.type === 'password');
  const set = (el, v) => { const d = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value'); d.set.call(el, v); el.dispatchEvent(new Event('input', {bubbles:true})); };
  set(phone, '0901000001'); set(pw, 'tntt@2026'); return 'ok';
})()`);
await ev(`document.querySelector('form').requestSubmit()`);
await sleep(6000);

console.log('url:', await ev(`location.href`));
console.log('title:', await ev(`document.title`));
console.log('shell:', await ev(`!!document.querySelector('.app-shell[x-data]')`));
console.log('body:', (await ev(`document.body.innerText`))?.slice(0, 300));
console.log('\n--- log ---');
lines.forEach(l => console.log(l));

cdp.close(); chrome.child.kill();
