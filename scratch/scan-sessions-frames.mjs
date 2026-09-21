// Session logs are concatenated zstd frames. Split on the zstd magic number
// (0x28 B5 2F FD) and decompress each frame, so nothing is missed.
import fs from 'node:fs';
import path from 'node:path';
import zlib from 'node:zlib';

const ROOT = 'C:/Users/TUONG NGOC VINH/AppData/Roaming/dsh-desktop/harness/sessions/--G-xampp-htdocs-tntt--';
const MAGIC = Buffer.from([0x28, 0xb5, 0x2f, 0xfd]);

function decompressAll(buf) {
  const starts = [];
  let i = buf.indexOf(MAGIC);
  while (i !== -1) {
    starts.push(i);
    i = buf.indexOf(MAGIC, i + 4);
  }
  let out = '';
  for (let k = 0; k < starts.length; k++) {
    const end = k + 1 < starts.length ? starts[k + 1] : buf.length;
    try {
      out += zlib.zstdDecompressSync(buf.subarray(starts[k], end)).toString('utf8');
    } catch {
      /* partial trailing frame */
    }
  }
  return out;
}

const dirs = fs.readdirSync(ROOT, { withFileTypes: true }).filter((d) => d.isDirectory()).map((d) => d.name);
const since = Date.now() - 3 * 60 * 60 * 1000;
let scanned = 0;

for (const d of dirs) {
  const f = path.join(ROOT, d, 'session.v3.jsonl.zstd');
  if (!fs.existsSync(f)) continue;
  const st = fs.statSync(f);
  if (st.mtimeMs < since) continue;
  scanned++;

  const text = decompressAll(fs.readFileSync(f));
  const org = (text.match(/org\.php/g) ?? []).length;
  if (org === 0) continue;

  console.log(`\n=== ${d}  mtime=${st.mtime.toISOString()}  decompressed=${text.length} chars  org.php mentions=${org} ===`);
  // Show every line that mentions org.php, trimmed.
  for (const line of text.split('\n')) {
    if (!line.includes('org.php')) continue;
    const snippet = line.length > 320 ? line.slice(0, 320) + '…' : line;
    console.log('  ' + snippet);
  }
}
console.log(`\nscanned ${scanned} recent sessions`);
