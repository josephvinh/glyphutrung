// Decompress the small child-session logs and print the failure reason.
import fs from 'node:fs';
import zlib from 'node:zlib';
import path from 'node:path';

const S = 'C:/Users/TUONG NGOC VINH/AppData/Roaming/dsh-desktop/harness/sessions/--G-xampp-htdocs-tntt--';

const targets = process.argv.slice(2);
for (const dir of targets) {
  const file = path.join(S, dir, 'session.v3.jsonl.zstd');
  if (!fs.existsSync(file)) {
    console.log(`--- ${dir}: MISSING ---`);
    continue;
  }
  let text;
  try {
    text = zlib.zstdDecompressSync(fs.readFileSync(file)).toString('utf8');
  } catch (e) {
    console.log(`--- ${dir}: decompress failed: ${e.message} ---`);
    continue;
  }
  console.log(`\n===== ${dir} (${text.length} chars) =====`);
  for (const line of text.split('\n')) {
    if (!line.trim()) continue;
    let ev;
    try {
      ev = JSON.parse(line);
    } catch {
      console.log('  [unparsed] ' + line.slice(0, 300));
      continue;
    }
    const type = ev.type ?? ev.kind ?? '?';
    const j = JSON.stringify(ev);
    // Surface anything that looks like an error, plus the model route.
    if (/error|fail|invalid|denied|unauthor|missing|refus/i.test(j)) {
      console.log(`  [${type}] ${j.slice(0, 900)}`);
    } else if (/provider|model/i.test(j) && /route|model|provider/i.test(type)) {
      console.log(`  [${type}] ${j.slice(0, 400)}`);
    }
  }
}
