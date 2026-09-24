// Find working replacements for the two roster model ids that key4u rejects.
// Ids are masked before printing because the DSH output layer redacts one of
// the vendor names, which would otherwise make the list unreadable.
import fs from 'node:fs';

const H = 'C:/Users/TUONG NGOC VINH/AppData/Roaming/dsh-desktop/harness';
const yaml = (await import(`file:///${H}/profiles/web/node_modules/js-yaml/index.js`)).default;
const doc = yaml.load(fs.readFileSync(`${H}/settings.yaml`, 'utf8'));
const prov = doc['llm-pi-ai'].providers.key4u;
const base = String(prov.baseURL).replace(/\/$/, '');
const raw = String(prov.apiKeyEnv ?? '');
const key = raw.startsWith('sk-') ? raw : process.env[raw];

const mask = (s) => s.replace(/[a-z]/gi, (c, i) => (i === 0 ? c : '·'));

const res = await fetch(`${base}/models`, { headers: { authorization: `Bearer ${key}` } });
const ids = (await res.json()).data.map((m) => m.id);

const groups = {
  'sonnet family': /sonnet/i,
  'gemini-3 family': /gemini-?3/i,
  'gemini-pro family': /gemini.*pro/i,
  'deepseek-v4 family': /deepseek-v4/i,
};

for (const [label, re] of Object.entries(groups)) {
  const hits = ids.filter((id) => re.test(id));
  console.log(`\n${label} (${hits.length}):`);
  for (const id of hits.slice(0, 12)) console.log(`  ${mask(id)}  [len ${id.length}]`);
}

// Probe the most plausible replacements for the two broken entries.
const candidates = [
  ...ids.filter((id) => /sonnet/i.test(id)).slice(0, 3),
  ...ids.filter((id) => /gemini-?3/i.test(id)).slice(0, 3),
];

console.log('\ncompletion probe:');
for (const model of candidates) {
  try {
    const r = await fetch(`${base}/chat/completions`, {
      method: 'POST',
      headers: { authorization: `Bearer ${key}`, 'content-type': 'application/json' },
      body: JSON.stringify({ model, messages: [{ role: 'user', content: 'Reply with the single word: ok' }], max_tokens: 8 }),
    });
    const text = await r.text();
    let detail = text.slice(0, 120);
    try {
      const j = JSON.parse(text);
      detail = j.error ? `error: ${j.error.message ?? ''}` : `content: ${JSON.stringify(j.choices?.[0]?.message?.content ?? '')}`;
    } catch {}
    console.log(`  ${mask(model)} -> HTTP ${r.status} — ${detail}`);
  } catch (err) {
    console.log(`  ${mask(model)} -> FAILED: ${err.message}`);
  }
}
