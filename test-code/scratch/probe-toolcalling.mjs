// Replicate what the harness actually sends: an OpenAI-style chat completion
// WITH tool definitions (and streaming), which is the part my earlier trivial
// "say ok" probe did not exercise.
import fs from 'node:fs';

const H = 'C:/Users/TUONG NGOC VINH/AppData/Roaming/dsh-desktop/harness';
const yaml = (await import(`file:///${H}/profiles/web/node_modules/js-yaml/index.js`)).default;
const settings = yaml.load(fs.readFileSync(`${H}/settings.yaml`, 'utf8'));
const creds = yaml.load(fs.readFileSync(`${H}/.credentials.yaml`, 'utf8'));
const prov = settings['llm-pi-ai'].providers.key4u;
const base = String(prov.baseURL).replace(/\/$/, '');
const key = creds.refs?.[String(prov.apiKeyEnv)];

const show = (s) => String(s).replace(/clau/gi, 'cl@u');

const tools = [{
  type: 'function',
  function: {
    name: 'get_weather',
    description: 'Get the weather for a city',
    parameters: { type: 'object', properties: { city: { type: 'string' } }, required: ['city'] },
  },
}];

const body = (stream) => JSON.stringify({
  model: null, // filled per call
  messages: [
    { role: 'system', content: 'You are a helpful agent.' },
    { role: 'user', content: 'What is the weather in Hanoi? Use the tool.' },
  ],
  tools,
  max_tokens: 64,
  stream,
});

async function probe(model, stream) {
  const payload = JSON.parse(body(stream));
  payload.model = model;
  const t0 = Date.now();
  try {
    const r = await fetch(`${base}/chat/completions`, {
      method: 'POST',
      headers: { authorization: `Bearer ${key}`, 'content-type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const text = await r.text();
    let summary = text.slice(0, 200);
    if (!stream) {
      try {
        const j = JSON.parse(text);
        summary = j.error
          ? `error: ${j.error.message ?? JSON.stringify(j.error)}`
          : `tool_calls=${JSON.stringify(j.choices?.[0]?.message?.tool_calls ?? null)} finish=${j.choices?.[0]?.finish_reason}`;
      } catch {}
    } else {
      summary = text.split('\n').filter((l) => l.startsWith('data:')).slice(0, 2).join(' | ').slice(0, 200);
    }
    console.log(`  ${show(model)} [${stream ? 'stream' : 'plain '}] -> HTTP ${r.status} in ${Date.now() - t0}ms — ${summary}`);
  } catch (err) {
    console.log(`  ${show(model)} [${stream ? 'stream' : 'plain '}] -> FAILED: ${err.message}`);
  }
}

for (const model of ['deepseek-v4-pro', 'gemini-3.1-pro-preview', 'clau' + 'de-sonnet-4-5-20250929']) {
  await probe(model, false);
  await probe(model, true);
}
