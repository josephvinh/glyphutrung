/** Giải nén session.v3.jsonl.zstd bằng zstd streaming (hỗ trợ nhiều frame). */
const z = require('node:zlib');
const fs = require('node:fs');

const p = process.argv[2];
const chunks = [];
const dec = z.createZstdDecompress();
dec.on('data', c => chunks.push(c));
dec.on('error', e => { console.log('LỖI GIẢI NÉN:', e.message); process.exit(2); });
dec.on('end', () => {
  const t = Buffer.concat(chunks).toString('utf8');
  console.log('Kích thước giải nén:', t.length);
  const names = ['list_subagents', 'delegate', 'subagent-library', 'browser_goto', 'dsh_verify', 'chuyen-gia-toi-uu', 'verify_spec'];
  for (const n of names) {
    const c = t.split(n).length - 1;
    console.log('  ' + n.padEnd(20) + ' -> ' + c + ' lần');
  }
});
fs.createReadStream(p).pipe(dec);
