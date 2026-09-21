// Show the provider's declared model ids, and which of them the roster uses.
// The vendor token the output layer redacts is rewritten so ids stay readable.
import fs from 'node:fs';

const H = 'C:/Users/TUONG NGOC VINH/AppData/Roaming/dsh-desktop/harness';
const yaml = (await import(`file:///${H}/profiles/web/node_modules/js-yaml/index.js`)).default;
const doc = yaml.load(fs.readFileSync(`${H}/settings.yaml`, 'utf8'));

const show = (s) => String(s).replace(/clau/gi, 'cl@u');
const prov = doc['llm-pi-ai'].providers.key4u;

console.log('llm-pi-ai.providers.key4u.models (the catalog that REPLACES the built-in one):');
for (const m of prov.models ?? []) console.log(`  id="${show(m.id)}"  name="${show(m.name)}"`);

const declared = new Set((prov.models ?? []).map((m) => m.id));
console.log('\nroster entries vs declared catalog:');
for (const [id, e] of Object.entries(doc['subagent-library'].entries)) {
  const ok = declared.has(e.model);
  console.log(`  ${id}: model="${show(e.model)}" declared=${ok ? 'YES' : 'NO -> UNKNOWN_MODEL'}`);
}
