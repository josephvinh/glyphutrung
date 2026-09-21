// Search recent session logs for any tool call that touched public/api/org.php.
import fs from 'node:fs';
import path from 'node:path';
import zlib from 'node:zlib';

const ROOT = 'C:/Users/TUONG NGOC VINH/AppData/Roaming/dsh-desktop/harness/sessions/--G-xampp-htdocs-tntt--';
const dirs = fs.readdirSync(ROOT, { withFileTypes: true }).filter((d) => d.isDirectory()).map((d) => d.name);

const rows = [];
for (const d of dirs) {
  const f = path.join(ROOT, d, 'session.v3.jsonl.zstd');
  if (!fs.existsSync(f)) continue;
  const st = fs.statSync(f);
  if (st.mtimeMs < Date.now() - 60 * 60 * 1000) continue; // last hour only
  let text;
  try {
    text = zlib.zstdDecompressSync(fs.readFileSync(f)).toString('utf8');
  } catch {
    continue;
  }
  const touchesOrg = /org\.php/.test(text);
  const writes = (text.match(/"name":"(write|edit)"/g) ?? []).length;
  rows.push({ d, size: st.size, mtime: st.mtime.toISOString(), touchesOrg, writes, chars: text.length });
}

rows.sort((a, b) => a.mtime.localeCompare(b.mtime));
for (const r of rows) {
  console.log(`${r.mtime}  ${r.d}  bytes=${r.size} chars=${r.chars}  mentionsOrg.php=${r.touchesOrg}  write/edit calls=${r.writes}`);
}
