// Final acceptance: resolve the key4u credential exactly the way dsh-llm-pi-ai
// does (apiKeyEnv -> credential store ref) and call every model the roster uses.
import fs from 'node:fs';

const H = 'C:/Users/TUONG NGOC VINH/AppData/Roaming/dsh-desktop/harness';
const yaml = (await import(`file:///${H}/profiles/web/node_modules/js-yaml/index.js`)).default;
const settings = yaml.load(fs.readFileSync(`${H}/settings.yaml`, 'utf8'));
const creds = yaml.load(fs.readFileSync(`${H}/.credentials.yaml`, 'utf8'));

const prov = settings['llm-pi-ai'].providers.key4u;
const base = String(prov.baseURL).replace(/\/$/, '');

// Same order as resolveApiKey(): credential store first, launch env second.
const ref = String(prov.apiKeyEnv);
const key = creds.refs?.[ref] ?? process.env[ref];
console.log(`route: key4u   apiKeyEnv(ref): ${ref}   credential found: ${key !== undefined}`);
if (key === undefined) {
  console.log('=> MISSING_CREDENTIAL: nothing more to test');
  process.exit(1);
}
console.log(`key: len ${key.length}, tail ****${key.slice(-4)}`);
console.log(`secret left in settings.yaml: ${/sk-[A-Za-z0-9_-]{20,}/.test(JSON.stringify(settings))}`);
console.log(`\ntop-level settings keys: ${Object.keys(settings).join(', ')}`);

const show = (s) => String(s).replace(/clau/gi, 'cl@u');
const models = [...new Set(Object.values(settings['subagent-library'].entries).map((e) => e.model))];

console.log('\ncalling every distinct roster model:');
let ok = 0;
for (const model of models) {
  const t0 = Date.now();
  try {
    const r = await fetch(`${base}/chat/completions`, {
      method: 'POST',
      headers: { authorization: `Bearer ${key}`, 'content-type': 'application/json' },
      body: JSON.stringify({ model, messages: [{ role: 'user', content: 'Reply with the single word: ok' }], max_tokens: 8 }),
    });
    const t = await r.text();
    let detail;
    try {
      const j = JSON.parse(t);
      detail = j.error ? `error: ${j.error.message ?? ''}` : `content: ${JSON.stringify(j.choices?.[0]?.message?.content ?? '')}`;
    } catch {
      detail = t.slice(0, 120);
    }
    if (r.status === 200) ok++;
    console.log(`  ${show(model)} -> HTTP ${r.status} in ${Date.now() - t0}ms — ${detail}`);
  } catch (err) {
    console.log(`  ${show(model)} -> FAILED: ${err.message}`);
  }
}
console.log(`\nresult: ${ok}/${models.length} roster models reachable`);
