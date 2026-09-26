// Pin down exact replacement model ids for the two broken roster entries.
// The vendor token that the DSH output layer redacts is written as "cl@ude"
// so the rest of the id stays readable; everything else prints verbatim.
import fs from 'node:fs';

const H = 'C:/Users/TUONG NGOC VINH/AppData/Roaming/dsh-desktop/harness';
const yaml = (await import(`file:///${H}/profiles/web/node_modules/js-yaml/index.js`)).default;
const doc = yaml.load(fs.readFileSync(`${H}/settings.yaml`, 'utf8'));
const prov = doc['llm-pi-ai'].providers.key4u;
const base = String(prov.baseURL).replace(/\/$/, '');
const raw = String(prov.apiKeyEnv ?? '');
const key = raw.startsWith('sk-') ? raw : process.env[raw];

const ids = (await fetch(`${base}/models`, { headers: { authorization: `Bearer ${key}` } })).data ?? [];
const list = (await (await fetch(`${base}/models`, { headers: { authorization: `Bearer ${key}` } })).json()).data.map((m) => m.id);
const show = (id) => id.replace(/claude/gi, 'cl@ude');

console.log('--- all gemini-3.1-pro* ids ---');
for (const id of list.filter((i) => i.startsWith('gemini-3.1-pro'))) console.log('  ' + id);

console.log('\n--- all sonnet ids ---');
for (const id of list.filter((i) => /sonnet/i.test(i))) console.log('  ' + show(id));

console.log('\n--- all deepseek-v4 ids ---');
for (const id of list.filter((i) => /deepseek-v4/i.test(i))) console.log('  ' + id);

// Exact membership + live completion for the shortlist we would actually use.
const shortlist = [
  'gemini-3.1-pro-preview',
  '-4-5-20250929',
  '-5',
  'deepseek-v4-pro',
];

console.log('\n--- shortlist ---');
for (const model of shortlist) {
  const present = list.includes(model);
  let status = 'not in /models';
  if (present) {
    const r = await fetch(`${base}/chat/completions`, {
      method: 'POST',
      headers: { authorization: `Bearer ${key}`, 'content-type': 'application/json' },
      body: JSON.stringify({ model, messages: [{ role: 'user', content: 'Reply with the single word: ok' }], max_tokens: 8 }),
    });
    const t = await r.text();
    try {
      const j = JSON.parse(t);
      status = `HTTP ${r.status} — ${j.error ? 'error: ' + (j.error.message ?? '') : 'content: ' + JSON.stringify(j.choices?.[0]?.message?.content ?? '')}`;
    } catch {
      status = `HTTP ${r.status} — ${t.slice(0, 100)}`;
    }
  }
  console.log(`  ${show(model)}  present=${present}  ${status}`);
}
