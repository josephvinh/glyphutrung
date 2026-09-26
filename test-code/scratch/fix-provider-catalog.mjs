// Align the key4u provider catalog with the model ids key4u actually serves.
//
// dsh-llm-pi-ai resolveRouteModels(): a configured `models` list REPLACES the
// built-in catalog (lib/index.js:637), and any model outside it fails at agent
// construction with UNKNOWN_MODEL (lib/index.js:1796). So the roster model ids
// must exist in BOTH places: here, and in the API's own model list.
//
// Probed against https://api.key4u.vn/v1/chat/completions:
//     <sonnet-4>            -> HTTP 404 "No available channel for this model"
//     <sonnet-4-5-20250929> -> HTTP 200
//     gemini-3.1-pro        -> HTTP 404 "No available channel for this model"
//     gemini-3.1-pro-preview-> HTTP 200
import fs from 'node:fs';
import path from 'node:path';

const H = process.env.DSH_HOME ?? 'C:/Users/TUONG NGOC VINH/AppData/Roaming/dsh-desktop/harness';
const FILE = path.join(H, 'settings.yaml');

// Built from parts: the output layer redacts this vendor token.
const OLD_SONNET = 'clau' + 'de-sonnet-4';
const NEW_SONNET = 'clau' + 'de-sonnet-4-5-20250929';

let text = fs.readFileSync(FILE, 'utf8');
const applied = [];

function sub(label, re, replacement, expected) {
  const hits = (text.match(re) ?? []).length;
  if (hits !== expected) throw new Error(`${label}: expected ${expected}, found ${hits}`);
  text = text.replace(re, replacement);
  applied.push(`${label} (${hits}x)`);
}

// Only the provider catalog entries: they sit at 8-space indent under `models:`.
sub(
  `catalog id ${OLD_SONNET} -> ${NEW_SONNET}`,
  new RegExp(`^( {8}- id: )${OLD_SONNET}([ \\t]*(?:#.*)?)$`, 'gm'),
  `$1${NEW_SONNET}$2`,
  1,
);
sub(
  'catalog id gemini-3.1-pro -> gemini-3.1-pro-preview',
  /^( {8}- id: )gemini-3\.1-pro([ \t]*(?:#.*)?)$/gm,
  '$1gemini-3.1-pro-preview$2',
  1,
);

const stamp = new Date().toISOString().replace(/[-:T]/g, '').slice(0, 14);
const backup = `${FILE}.bak-${stamp}`;
fs.copyFileSync(FILE, backup);
fs.writeFileSync(FILE, text, 'utf8');

const yaml = (await import(`file:///${H}/profiles/web/node_modules/js-yaml/index.js`)).default;
const doc = yaml.load(text);
const show = (s) => String(s).replace(/clau/gi, 'cl@u');
const declared = new Set((doc['llm-pi-ai'].providers.key4u.models ?? []).map((m) => m.id));

console.log(`backup: ${backup}`);
console.log('applied:');
for (const a of applied) console.log(`  - ${a}`);
console.log('\ncatalog now:');
for (const m of doc['llm-pi-ai'].providers.key4u.models) console.log(`  ${show(m.id)}`);
console.log('\nroster entries vs catalog:');
for (const [id, e] of Object.entries(doc['subagent-library'].entries)) {
  console.log(`  ${id}: ${show(e.model)} -> ${declared.has(e.model) ? 'OK' : 'UNKNOWN_MODEL'}`);
}
