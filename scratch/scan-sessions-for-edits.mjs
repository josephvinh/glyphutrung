// Decompress ALL concatenated zstd frames (session logs append frame by frame),
// then report which sessions mention org.php and any file-writing tool calls.
import fs from 'node:fs';
import path from 'node:path';
import zlib from 'node:zlib';
import { Readable } from 'node:stream';

const ROOT = 'C:/Users/TUONG NGOC VINH/AppData/Roaming/dsh-desktop/harness/sessions/--G-xampp-htdocs-tntt--';

function decompressAll(file) {
  return new Promise((resolve) => {
    const chunks = [];
    const dec = zlib.createZstdDecompress();
    dec.on('data', (c) => chunks.push(c));
    dec.on('error', () => resolve(Buffer.concat(chunks).toString('utf8')));
    dec.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
    Readable.from(fs.readFileSync(file)).pipe(dec);
  });
}

const dirs = fs.readdirSync(ROOT, { withFileTypes: true }).filter((d) => d.isDirectory()).map((d) => d.name);
const since = Date.now() - 2 * 60 * 60 * 1000;

for (const d of dirs) {
  const f = path.join(ROOT, d, 'session.v3.jsonl.zstd');
  if (!fs.existsSync(f)) continue;
  const st = fs.statSync(f);
  if (st.mtimeMs < since) continue;

  const text = await decompressAll(f);
  const orgHits = (text.match(/org\.php/g) ?? []).length;
  // Look for tool invocations that could write: write/edit tools, or pwsh commands.
  const pwshWrites = [...text.matchAll(/"(?:command|description)":"([^"]{0,200})"/g)]
    .map((m) => m[1])
    .filter((c) => /org\.php|Set-Content|Out-File|>\s*public|sed -i|Add-Content/i.test(c));

  if (orgHits > 0 || pwshWrites.length > 0) {
    console.log(`\n=== ${d} (${st.mtime.toISOString()}, ${text.length} chars) ===`);
    console.log(`  mentions org.php: ${orgHits}`);
    for (const c of pwshWrites.slice(0, 10)) console.log(`  possible write cmd: ${c.slice(0, 180)}`);
  }
}
console.log('\ndone');
