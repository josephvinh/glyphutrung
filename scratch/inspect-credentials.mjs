// Inspect the credential store structure WITHOUT printing secret values.
import fs from 'node:fs';

const H = 'C:/Users/TUONG NGOC VINH/AppData/Roaming/dsh-desktop/harness';
const yaml = (await import(`file:///${H}/profiles/web/node_modules/js-yaml/index.js`)).default;
const d = yaml.load(fs.readFileSync(`${H}/.credentials.yaml`, 'utf8'));

const scrub = (s) => String(s).replace(/sk-[A-Za-z0-9_-]{4,}/g, 'sk-***');
const label = (s) => {
  const t = String(s);
  return t.startsWith('sk-') ? `sk-***${t.slice(-4)} (len ${t.length})` : scrub(t);
};

console.log('refs:');
console.log(JSON.stringify(d.refs, null, 2).split('\n').map(scrub).join('\n'));

console.log('\nrecords:');
const recs = Array.isArray(d.records) ? d.records : Object.entries(d.records ?? {}).map(([k, v]) => ({ key: k, ...(v ?? {}) }));
console.log(`count: ${recs.length}`);
for (const r of recs) {
  console.log(`  fields: ${Object.keys(r).join(', ')}`);
  for (const [k, v] of Object.entries(r)) {
    if (k === 'value' || k === 'secret' || k === 'apiKey') {
      console.log(`    ${k}: present=${v !== undefined && v !== null && String(v).length > 0} len=${v ? String(v).length : 0}`);
    } else {
      console.log(`    ${k}: ${label(v)}`);
    }
  }
}
