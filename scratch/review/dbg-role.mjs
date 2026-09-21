import { launchChrome, CDP } from './cdp.mjs';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const chrome = await launchChrome({ port: 9230, userDataDir: 'G:\\xampp\\htdocs\\tntt\\scratch\\review\\profile-dbg2' });
const cdp = new CDP(chrome.wsUrl); await cdp.connect();
const { result: t } = await cdp.send('Target.createTarget', { url: 'about:blank' });
const sid = (await cdp.send('Target.attachToTarget', { targetId: t.targetId, flatten: true })).result.sessionId;
await cdp.send('Page.enable', {}, sid); await cdp.send('Runtime.enable', {}, sid); await cdp.send('Network.enable', {}, sid);
const lines = [];
cdp.on('Network.responseReceived', m => { if (m.params.response.url.includes('api/')) lines.push(`[net] ${m.params.response.status} ${m.params.response.url}`); }, sid);
cdp.on('Runtime.consoleAPICalled', m => lines.push(`[${m.params.type}] ` + m.params.args.map(a => a.value ?? a.description).join(' ').slice(0, 200)), sid);
async function ev(e) {
  const r = await cdp.send('Runtime.evaluate', { expression: e, awaitPromise: true, returnByValue: true }, sid);
  if (r.result.exceptionDetails) return 'ERR: ' + (r.result.exceptionDetails.exception?.description || r.result.exceptionDetails.text);
  return r.result.result.value;
}
await cdp.send('Page.navigate', { url: 'http://127.0.0.1:8099/' }, sid);
await sleep(2500);
console.log('form?', await ev(`!!document.querySelector('input[type=password]')`));
await ev(`(() => { const i=[...document.querySelectorAll('input')]; const p=i.find(x=>x.type==='tel'); const w=i.find(x=>x.type==='password'); const s=(e,v)=>{Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(e,v);e.dispatchEvent(new Event('input',{bubbles:true}))}; s(p,'0902000005'); s(w,'tntt@2026'); return 'ok'; })()`);
await ev(`document.querySelector('form').requestSubmit()`);
await sleep(6000);
console.log('title:', await ev(`document.title`));
console.log('alpine:', await ev(`typeof window.Alpine`));
console.log('shell:', await ev(`!!document.querySelector('.app-shell')`));
console.log('bodyText:', (await ev(`document.body.innerText`))?.slice(0, 300));
console.log('--- net/console ---'); lines.forEach(l => console.log(l));
cdp.close(); chrome.child.kill();
