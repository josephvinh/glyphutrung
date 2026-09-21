/** Smoke test: launch Chrome, open app, log in, dump state. */
import { launchChrome, CDP } from './cdp.mjs';

const BASE = 'http://localhost:8888/tntt/public/';
const sleep = ms => new Promise(r => setTimeout(r, ms));

const chrome = await launchChrome({
  port: 9222,
  userDataDir: 'G:\\xampp\\htdocs\\tntt\\scratch\\review\\profile',
});
console.log('Chrome version:', chrome.version.Browser);

const cdp = new CDP(chrome.wsUrl);
await cdp.connect();
console.log('Connected to browser WS');

const { result: t } = await cdp.send('Target.createTarget', { url: 'about:blank' });
const targetId = t.targetId;
const { result: a } = await cdp.send('Target.attachToTarget', { targetId, flatten: true });
const sid = a.sessionId;
console.log('Attached session:', sid);

const logs = [];
cdp.on('Runtime.consoleAPICalled', m => {
  logs.push(`[console.${m.params.type}] ` + m.params.args.map(x => x.value ?? x.description ?? x.type).join(' '));
}, sid);
cdp.on('Runtime.exceptionThrown', m => {
  logs.push('[exception] ' + (m.params.exceptionDetails.exception?.description || m.params.exceptionDetails.text));
}, sid);
cdp.on('Log.entryAdded', m => {
  logs.push(`[log.${m.params.entry.level}/${m.params.entry.source}] ${m.params.entry.text}`);
}, sid);

await cdp.send('Page.enable', {}, sid);
await cdp.send('Runtime.enable', {}, sid);
await cdp.send('Log.enable', {}, sid);
await cdp.send('Network.enable', {}, sid);

async function evaluate(expr, awaitPromise = true) {
  const r = await cdp.send('Runtime.evaluate', {
    expression: expr,
    awaitPromise,
    returnByValue: true,
  }, sid);
  if (r.result.exceptionDetails) {
    throw new Error('eval error: ' + JSON.stringify(r.result.exceptionDetails));
  }
  return r.result.result.value;
}

console.log('\n--- Navigating to', BASE);
await cdp.send('Page.navigate', { url: BASE }, sid);
await sleep(3000);

const title = await evaluate('document.title');
const hasLogin = await evaluate('!!document.querySelector("form")');
console.log('title:', title, '| hasForm:', hasLogin);

// Fill login form
const filled = await evaluate(`
(() => {
  const inputs = [...document.querySelectorAll('input')];
  const phone = inputs.find(i => i.type === 'tel' || i.autocomplete === 'username');
  const pw = inputs.find(i => i.type === 'password');
  if (!phone || !pw) return 'missing inputs: ' + inputs.map(i => i.type).join(',');
  const setter = (el, v) => {
    const d = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
    d.set.call(el, v);
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
  };
  setter(phone, '0901000001');
  setter(pw, 'tntt@2026');
  return 'ok';
})()
`);
console.log('fill:', filled);

await evaluate(`document.querySelector('form').requestSubmit()`);
await sleep(6000);

const after = await evaluate(`({
  title: document.title,
  shell: !!document.querySelector('.app-shell[x-data]'),
  url: location.href,
  bodyStart: document.body.innerText.slice(0, 400)
})`);
console.log('\n--- After login:', JSON.stringify(after, null, 2));

console.log('\n--- Console log entries (' + logs.length + ') ---');
logs.forEach(l => console.log(l));

cdp.close();
chrome.child.kill();
