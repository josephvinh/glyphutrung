// Repair the dsh-subagent-library roster in $DSH_HOME/settings.yaml.
//
// Two defects found by validating the roster against the plugin's own
// schemastery Config and by reading its tool-filter resolution
// (lib/index.js:9132 sanitizeToolFilter — exact-name matching, no globs):
//
//   1. Tool names that do not exist in this harness are dropped. An `allow`
//      list that drops to empty makes `delegate` refuse to start the child.
//      real names here: read, write, edit, glob, grep, pwsh, dsh_verify, browser_*
//      (browser_* is NOT a wildcard the plugin understands)
//   2. `model:` left blank parses to the empty string, and the plugin passes
//      `model: ''` explicitly (lib/index.js:9559), instead of falling back to
//      the caller's session model.
import fs from 'node:fs';
import path from 'node:path';

const HARNESS = process.env.DSH_HOME ?? 'C:/Users/TUONG NGOC VINH/AppData/Roaming/dsh-desktop/harness';
const FILE = path.join(HARNESS, 'settings.yaml');
const MODE = process.argv[2] ?? 'full'; // full | tools-only

const original = fs.readFileSync(FILE, 'utf8');
let next = original;
const changes = [];

function replaceOnce(label, from, to) {
  const hits = next.split(from).length - 1;
  if (hits !== 1) throw new Error(`${label}: expected exactly 1 match, found ${hits}`);
  next = next.replace(from, to);
  changes.push(label);
}

// --- 1. tool names ---------------------------------------------------------
replaceOnce(
  'lap-trinh-vien: allow -> real tool names',
  '        allow: [ write, edit, read_file, list_files ]',
  '        allow: [ read, write, edit, glob, grep, pwsh ]',
);
replaceOnce(
  'kiem-thu-tu-dong: allow(browser_* wildcard) -> deny',
  '        allow: [ read_file, list_files, verify_spec, browser_* ]',
  '        deny: [ write, edit ]',
);
replaceOnce(
  'kiem-thu-tuong-tac: allow(browser_* wildcard) -> deny',
  '        allow: [ read_file, list_files, browser_* ]',
  '        deny: [ write, edit ]',
);

// --- 2. blank model ids ----------------------------------------------------
if (MODE === 'full') {
  const models = ['deepseek-v4-pro', 'gemini-3.1-pro']; // entry 1, then entry 3
  let i = 0;
  next = next.replace(/^ {6}model:[ \t\r]*$/gm, (match) => {
    const picked = models[i++];
    if (picked === undefined) throw new Error('more blank model: lines than expected');
    changes.push(`blank model -> ${picked}`);
    return `      model: ${picked}`;
  });
  if (i !== models.length) throw new Error(`expected ${models.length} blank model: lines, found ${i}`);
}

// --- 3. back up, write, re-validate ---------------------------------------
const stamp = new Date().toISOString().replace(/[-:T]/g, '').slice(0, 14);
const backup = `${FILE}.bak-${stamp}`;
fs.copyFileSync(FILE, backup);
fs.writeFileSync(FILE, next, 'utf8');

const yaml = (await import(
  `file:///${HARNESS}/profiles/web/node_modules/js-yaml/index.js`
)).default;
const { Config } = await import(
  `file:///${HARNESS}/profiles/web/node_modules/dsh-subagent-library/lib/index.js`
);
const parsed = Config(yaml.load(next)?.['subagent-library'] ?? {});

console.log(`backup: ${backup}`);
console.log(`changes (${changes.length}):`);
for (const c of changes) console.log(`  - ${c}`);
console.log('re-validated roster:');
for (const [id, e] of Object.entries(parsed.entries)) {
  console.log(
    `  ${id}: provider=${e.provider ?? '-'} model=${e.model === '' ? '(EMPTY)' : e.model ?? '-'} ` +
      `toolFilter=${e.toolFilter ? JSON.stringify(e.toolFilter) : 'none'}`,
  );
}
