/**
 * Sửa lỗi cấu hình subagent-library trong settings.yaml.
 *
 * NGUYÊN NHÂN GỐC (đã xác minh ở mức byte):
 *   Hai entry `chuyen-gia-phan-tich` và `kiem-thu-tu-dong` trỏ tới model
 *   `` — nhưng provider `key4u` (mục llm-pi-ai.providers.key4u.models)
 *   KHÔNG khai báo model này. Danh sách hợp lệ chỉ có:
 *       gemini-3.1-pro-preview, deepseek-v4-pro, deepseek-v4-flash
 *   => delegate truyền model không tồn tại -> subagent chết ngay.
 *
 *   (Trước đó tưởng là `model:` để trống; thực tế giá trị là ``
 *    nên regex chỉ khớp dòng rỗng đã không sửa được gì.)
 *
 * Sửa: đặt `model: deepseek-v4-pro` (có trong danh sách khai báo).
 * Có sao lưu. Giữ nguyên CRLF và UTF-8 không BOM.
 */
const fs = require('node:fs');

const FILE = 'C:\\Users\\TUONG NGOC VINH\\AppData\\Roaming\\dsh-desktop\\harness\\settings.yaml';
const TARGETS = ['chuyen-gia-phan-tich', 'kiem-thu-tu-dong'];
const NEW_MODEL = 'deepseek-v4-pro';

const original = fs.readFileSync(FILE, 'utf8');
const eol = original.includes('\r\n') ? '\r\n' : '\n';

// ---- đọc danh sách model hợp lệ của provider key4u để tự kiểm tra ----
const declared = [];
{
  let inProvider = false;
  for (const line of original.split(/\r?\n/)) {
    if (/^llm-pi-ai:/.test(line)) { inProvider = true; continue; }
    if (inProvider && /^\S/.test(line)) { inProvider = false; }   // hết khối
    if (!inProvider) continue;
    const idm = line.match(/^\s*-\s*id:\s*(.*?)\s*(?:#.*)?$/);
    if (idm) declared.push(idm[1].trim());
  }
}
console.log('Model provider key4u khai báo:', JSON.stringify(declared));
if (!declared.includes(NEW_MODEL)) {
  console.error(`DỪNG: "${NEW_MODEL}" không nằm trong danh sách khai báo -> không sửa.`);
  process.exit(1);
}

// ---- sao lưu ----
const stamp = new Date().toISOString().replace(/[:.]/g, '-');
const backup = `${FILE}.bak-${stamp}`;
fs.copyFileSync(FILE, backup);
console.log('Đã sao lưu ->', backup);

const lines = original.split(/\r?\n/);
let currentEntry = '';
const changes = [];

for (let i = 0; i < lines.length; i++) {
  const em = lines[i].match(/^ {4}([a-z0-9][a-z0-9-]*):\s*$/);
  if (em) currentEntry = em[1];

  if (TARGETS.includes(currentEntry)) {
    const m = lines[i].match(/^(\s*)model:(\s*)(.*)$/);
    if (m) {
      const oldVal = m[3].trim();
      if (oldVal === NEW_MODEL) continue;
      lines[i] = `${m[1]}model: ${NEW_MODEL}`;
      changes.push(`  dòng ${i + 1}  [${currentEntry}]  model: "${oldVal}" -> "${NEW_MODEL}"`);
    }
  }
}

if (changes.length === 0) {
  console.log('\nKhông có gì để sửa (đã đúng sẵn).');
} else {
  fs.writeFileSync(FILE, lines.join(eol), { encoding: 'utf8' });
  console.log('\nĐã sửa ' + changes.length + ' chỗ:');
  changes.forEach(c => console.log(c));
}

// ---- kiểm chứng ở mức BYTE (đọc lại từ đĩa) ----
console.log('\n=== KIỂM CHỨNG (đọc lại từ đĩa) ===');
const after = fs.readFileSync(FILE, 'utf8').split(/\r?\n/);
let e2 = '';
const check = [];
for (let i = 0; i < after.length; i++) {
  const em = after[i].match(/^ {4}([a-z0-9][a-z0-9-]*):\s*$/);
  if (em) e2 = em[1];
  const m = after[i].match(/^\s*model:\s*(.*)$/);
  if (m && e2 && !['key4u'].includes(e2)) check.push([e2, m[1].trim(), i + 1]);
}
let bad = 0;
for (const [entry, val, ln] of check) {
  const ok = val !== '' && declared.includes(val);
  if (!ok) bad++;
  console.log(`  ${entry.padEnd(24)} dòng ${String(ln).padStart(3)}  model="${val}"  ${ok ? 'HỢP LỆ' : '*** KHÔNG HỢP LỆ ***'}`);
}
console.log(bad === 0 ? '\n=> Tất cả entry đều trỏ model hợp lệ.' : `\n=> CÒN ${bad} entry không hợp lệ.`);
