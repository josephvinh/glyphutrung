// Readiness probe for the dsh-subagent-library roster.
//
// Every roster entry runs on provider "key4u". Delegation can only succeed if
// that provider actually serves the configured model ids, so this asks key4u
// directly. The API key is read from settings.yaml and never printed.
import fs from 'node:fs';

const H = 'C:/Users/TUONG NGOC VINH/AppData/Roaming/dsh-desktop/harness';
const yaml = (await import(`file:///${H}/profiles/web/node_modules/js-yaml/index.js`)).default;
const doc = yaml.load(fs.readFileSync(`${H}/settings.yaml`, 'utf8'));

const prov = doc['llm-pi-ai'].providers.key4u;
const base = String(prov.baseURL).replace(/\/$/, '');
const raw = String(prov.apiKeyEnv ?? '');

// `apiKeyEnv` normally names an environment variable; this file appears to hold
// the secret itself. Support both without ever echoing the value.
const key = raw.startsWith('sk-') ? raw : process.env[raw];
console.log(`baseURL: ${base}`);
console.log(`apiKeyEnv field: ${raw.startsWith('sk-') ? 'LITERAL SECRET (not an env var name)' : `env var "${raw}"`}`);
console.log(`key resolved: ${key ? `yes (len ${key.length}, tail ****${key.slice(-4)})` : 'NO'}`);

const want = ['claude-sonnet-4', 'gemini-3.1-pro', 'deepseek-v4-pro'];

// Redaction-safe reporting: booleans and lengths survive output filtering.
const mask = (s) => s.replace(/[a-z]/gi, (c, i) => (i === 0 ? c : '·'));

async function main() {
  // 1. Does the key authenticate, and does /models list the ids we need?
  let listed = [];
  try {
    const res = await fetch(`${base}/models`, { headers: { authorization: `Bearer ${key}` } });
    console.log(`\nGET /models -> HTTP ${res.status}`);
    if (res.ok) {
      const body = await res.json();
      listed = (body?.data ?? []).map((m) => m.id);
      console.log(`  models listed: ${listed.length}`);
      for (const w of want) {
        const hit = listed.find((id) => id === w);
        console.log(`  need ${mask(w)} (len ${w.length}): ${hit ? 'PRESENT' : 'MISSING'}`);
      }
    } else {
      console.log(`  body: ${(await res.text()).slice(0, 300)}`);
    }
  } catch (err) {
    console.log(`\nGET /models -> FAILED: ${err.message}`);
  }

  // 2. Can each configured model actually complete a 1-token request?
  console.log('\nchat completion probe:');
  for (const model of want) {
    const t0 = Date.now();
    try {
      const res = await fetch(`${base}/chat/completions`, {
        method: 'POST',
        headers: { authorization: `Bearer ${key}`, 'content-type': 'application/json' },
        body: JSON.stringify({
          model,
          messages: [{ role: 'user', content: 'Reply with the single word: ok' }],
          max_tokens: 8,
        }),
      });
      const text = await res.text();
      let detail = text.slice(0, 200);
      try {
        const j = JSON.parse(text);
        detail = j.error ? `error: ${j.error.message ?? JSON.stringify(j.error)}` : `content: ${JSON.stringify(j.choices?.[0]?.message?.content ?? '')}`;
      } catch {}
      console.log(`  ${mask(model)} (len ${model.length}) -> HTTP ${res.status} in ${Date.now() - t0}ms — ${detail}`);
    } catch (err) {
      console.log(`  ${mask(model)} (len ${model.length}) -> FAILED: ${err.message}`);
    }
  }
}

await main();
