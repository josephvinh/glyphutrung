/**
 * Heuristic scanner for the org.php bug class:
 *   db_one/db_all with an EXPLICIT column list, then reading a key that the
 *   SELECT never fetched -> "Undefined array key" warning, and a comparison
 *   against that missing key silently takes the wrong branch.
 *
 * Deliberately conservative: only reports keys that are clearly absent from an
 * explicit column list, and ignores SELECT * and aliased columns.
 */
import fs from 'node:fs';
import path from 'node:path';

const API = 'G:/xampp/htdocs/tntt/public/api';
const files = fs.readdirSync(API).filter((f) => f.endsWith('.php'));

let total = 0;
const findings = [];

for (const file of files) {
  const full = path.join(API, file);
  const lines = fs.readFileSync(full, 'utf8').split(/\r?\n/);

  // Find `$var = db_one('SELECT cols FROM ...', [...])` possibly spanning lines.
  for (let i = 0; i < lines.length; i++) {
    const m = lines[i].match(/\$(\w+)\s*=\s*db_(?:one|all)\(\s*['"](SELECT\s+([^'"]+?)\s+FROM\b[^'"]*)['"]/i);
    if (!m) continue;
    const [, varName, , colPart] = m;
    if (colPart.trim() === '*') continue;

    const cols = colPart
      .split(',')
      .map((c) => c.trim().split(/\s+as\s+/i).pop().trim())
      .filter((c) => /^\w+$/.test(c))
      .map((c) => c.toLowerCase());
    if (cols.length === 0) continue;

    // Scan the next 25 lines for $varName['key'] reads.
    const windowEnd = Math.min(lines.length, i + 26);
    const missing = new Map();
    for (let j = i; j < windowEnd; j++) {
      const re = new RegExp(`\\$${varName}\\['(\\w+)'\\]`, 'g');
      let k;
      while ((k = re.exec(lines[j])) !== null) {
        const key = k[1].toLowerCase();
        if (!cols.includes(key) && !missing.has(key)) missing.set(key, j + 1);
      }
    }
    if (missing.size > 0) {
      total += missing.size;
      findings.push({
        file,
        line: i + 1,
        varName,
        cols: cols.join(','),
        missing: [...missing.entries()].map(([k, l]) => `${k} (read at L${l})`).join(', '),
      });
    }
  }
}

if (findings.length === 0) {
  console.log('No remaining missing-column reads found in public/api/*.php');
} else {
  console.log(`Found ${findings.length} site(s), ${total} missing key read(s):\n`);
  for (const f of findings) {
    console.log(`${f.file}:${f.line}  $${f.varName}`);
    console.log(`   SELECT columns : ${f.cols}`);
    console.log(`   READ but absent: ${f.missing}\n`);
  }
}
