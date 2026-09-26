// Pre-restart validation: does the existing settings.yaml roster satisfy the
// dsh-subagent-library config schema?
import fs from 'node:fs';

const HARNESS = 'C:/Users/TUONG NGOC VINH/AppData/Roaming/dsh-desktop/harness';
const PROFILE = `${HARNESS}/profiles/web`;

const yaml = (await import(`file:///${PROFILE}/node_modules/js-yaml/index.js`)).default;
const { Config } = await import(`file:///${PROFILE}/node_modules/dsh-subagent-library/lib/index.js`);

const raw = fs.readFileSync(`${HARNESS}/settings.yaml`, 'utf8');
const doc = yaml.load(raw);

const section = doc?.['subagent-library'];
console.log('section present:', section !== undefined);
console.log('entry ids:', Object.keys(section?.entries ?? {}).join(', '));

try {
  const parsed = Config(section ?? {});
  const ids = Object.keys(parsed?.entries ?? {});
  console.log('SCHEMA OK — accepted entries:', ids.length);
  for (const id of ids) {
    const e = parsed.entries[id];
    console.log(
      `  - ${id}: provider=${e.provider ?? '(caller default)'} model=${e.model ?? '(caller default)'} ` +
        `persona=${e.persona ? `${e.persona.length} chars` : 'none'} ` +
        `toolFilter=${e.toolFilter ? JSON.stringify(e.toolFilter) : 'none'} ` +
        `maxDepth=${e.maxDepth ?? '-'} backgroundMode=${e.backgroundMode ?? 'one-shot'}`,
    );
  }
} catch (err) {
  console.log('SCHEMA ERROR:', err?.message ?? String(err));
  process.exitCode = 1;
}
