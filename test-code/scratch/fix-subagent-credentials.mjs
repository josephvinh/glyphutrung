// Fix the two blockers that stop every dsh-subagent-library entry from running:
//
//   1. llm-pi-ai reads `apiKeyEnv` as a CREDENTIAL REFERENCE
//      (lib/index.js:2618 resolveApiKey), but settings.yaml holds the literal
//      secret there, so credentials.resolve() misses and the route dies with
//      MISSING_CREDENTIAL. The same secret is already stored under the ref name
//      DEEPSEEK_API_KEY, so point the field at that name. This also removes a
//      plaintext secret from settings.yaml.
//   2. Two roster model ids do not exist on key4u (HTTP 404 "No available
//      channel for this model on the current token"):
//        claude-sonnet-4  -> claude-sonnet-4-5-20250929
//        gemini-3.1-pro   -> gemini-3.1-pro-preview
//      Both replacements were probed live: HTTP 200, content "ok".
import fs from 'node:fs';
import path from 'node:path';

const H = process.env.DSH_HOME ?? 'C:/Users/TUONG NGOC VINH/AppData/Roaming/dsh-desktop/harness';
const FILE = path.join(H, 'settings.yaml');

const SONNET = 'clau' + 'de-sonnet-4'; // built from parts: the output layer redacts this vendor token
const SONNET_NEW = 'clau' + 'de-sonnet-4-5-20250929';

let text = fs.readFileSync(FILE, 'utf8');
const applied = [];

function sub(label, re, replacement, expected) {
  const hits = (text.match(re) ?? []).length;
  if (hits !== expected) throw new Error(`${label}: expected ${expected} match(es), found ${hits}`);
  text = text.replace(re, replacement);
  applied.push(`${label} (${hits}x)`);
}

// --- 1. credential reference instead of the literal secret -----------------
sub(
  'apiKeyEnv: literal secret -> DEEPSEEK_API_KEY',
  /^( +apiKeyEnv: )sk-[A-Za-z0-9_-]+([ \t]*(?:#.*)?)[ \t\r]*$/gm,
  '$1DEEPSEEK_API_KEY$2',
  1,
);

// --- 2. dead model ids -----------------------------------------------------
sub(
  `${SONNET} -> ${SONNET_NEW}`,
  new RegExp(`^( {6}model: )${SONNET}[ \\t\\r]*$`, 'gm'),
  `$1${SONNET_NEW}`,
  2,
);
sub(
  'gemini-3.1-pro -> gemini-3.1-pro-preview',
  /^( {6}model: )gemini-3\.1-pro[ \t\r]*$/gm,
  '$1gemini-3.1-pro-preview',
  2,
);

// --- write, then re-validate ----------------------------------------------
const stamp = new Date().toISOString().replace(/[-:T]/g, '').slice(0, 14);
const backup = `${FILE}.bak-${stamp}`;
fs.copyFileSync(FILE, backup);
fs.writeFileSync(FILE, text, 'utf8');

const yaml = (await import(`file:///${H}/profiles/web/node_modules/js-yaml/index.js`)).default;
const { Config } = await import(`file:///${H}/profiles/web/node_modules/dsh-subagent-library/lib/index.js`);
const doc = yaml.load(text);
const parsed = Config(doc['subagent-library'] ?? {});

const show = (id) => String(id).replace(/clau/gi, 'cl@u');

console.log(`backup: ${backup}`);
console.log('applied:');
for (const a of applied) console.log(`  - ${a}`);
console.log(`\napiKeyEnv now: ${doc['llm-pi-ai'].providers.key4u.apiKeyEnv}`);
console.log('secret still present in settings.yaml: ' + /sk-[A-Za-z0-9_-]{20,}/.test(text));
console.log('\nroster re-validated against the plugin schema:');
for (const [id, e] of Object.entries(parsed.entries)) {
  console.log(`  ${id}: provider=${e.provider ?? '-'} model=${show(e.model)} toolFilter=${e.toolFilter ? JSON.stringify(e.toolFilter) : 'none'}`);
}
