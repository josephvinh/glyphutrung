/**
 * AUDIT: tìm các lớp CSS được DÙNG trong mã nguồn nhưng KHÔNG có trong
 * bản CSS đã biên dịch (tailwind.css) lẫn các tệp CSS viết tay.
 *
 * Đây chính là loại lỗi đã gây ra việc icon kính lúp đè lên placeholder
 * ở module Thiếu Nhi: markup dùng `pl-10` nhưng bản build tĩnh không có.
 */
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'G:\\xampp\\htdocs\\tntt';
const CSS_DIR = path.join(ROOT, 'public', 'assets', 'css');
const CSS_FILES = ['tailwind.css', 'app.css', 'dark.css', 'skeleton.css', 'analytics.css', 'toast.css', 'brand.css', 'font.css'];

// ------------------------------------------------------------------ 1. CSS có gì
const cssText = CSS_FILES.map(f => {
  const p = path.join(CSS_DIR, f);
  return fs.existsSync(p) ? fs.readFileSync(p, 'utf8') : '';
}).join('\n');

// Tập hợp tên lớp có mặt trong CSS (đã bỏ escape của Tailwind)
const defined = new Set();
// .abc\:def { ... }  /  .w-\[18px\] { ... }
for (const m of cssText.matchAll(/\.((?:[A-Za-z0-9_-]|\\.|\\[|\\]|\\\/|\\%|\\#|\\@|\\\.)+)(?=[\s,{:>+~[])/g)) {
  const raw = m[1].replace(/\\/g, '');
  defined.add(raw);
}
// Bỏ hậu tố giả (do bắt nhầm) — giữ nguyên, so khớp chính xác là đủ.

console.log('Số lớp định nghĩa trong CSS:', defined.size);

// ------------------------------------------------------------------ 2. Lớp dùng trong mã nguồn
const SRC_DIRS = ['views', 'public/assets/js/modules', 'public/assets/js', 'public'];
const EXTS = ['.php', '.js', '.html'];
const SKIP = /(bundle\.min|\.min\.|vendor|node_modules|scratch|\.superpowers|\.claude)/;

const usedClasses = new Map(); // class -> Set(file)

function record(cls, file) {
  if (!cls) return;
  if (!usedClasses.has(cls)) usedClasses.set(cls, new Set());
  usedClasses.get(cls).add(file);
}

/** Lấy mọi token trong các thuộc tính class / :class / x-bind:class / classList / clsx. */
function extractFromText(txt, file) {
  // class="..."  |  :class="..."  |  x-bind:class="..."  |  className="..."
  const attrRe = /(?:^|[\s"'])(?::class|x-bind:class|class|className)\s*=\s*("([^"]*)"|'([^']*)')/g;
  for (const m of txt.matchAll(attrRe)) {
    const val = m[2] ?? m[3] ?? '';
    harvestTokens(val, file);
  }
  // classList.add('a','b') / classList.toggle('x')
  for (const m of txt.matchAll(/classList\.(?:add|remove|toggle|contains)\(([^)]*)\)/g)) {
    harvestTokens(m[1], file);
  }
  // Mọi chuỗi trong JS có vẻ là danh sách lớp (chứa dấu cách + có tiền tố utility)
  for (const m of txt.matchAll(/(['"`])((?:\s*(?:[a-z@][a-z0-9@:_\-\[\]\/\.%#]*\s+){1,}[a-z@][a-z0-9@:_\-\[\]\/\.%#]*))\1/g)) {
    const s = m[2];
    if (/^[a-z0-9\-]+$/.test(s.trim())) continue;
    if (!/[a-z]-/.test(s)) continue;
    harvestTokens(s, file);
  }
}

/** Tách token lớp ra khỏi một chuỗi (bỏ biểu thức Alpine, template literal). */
function harvestTokens(val, file) {
  // bỏ ${...} và {{...}}
  let s = val.replace(/\$\{[^}]*\}/g, ' ').replace(/\{\{[^}]*\}\}/g, ' ');
  // bỏ nội dung trong nháy đơn (biểu thức JS)
  s = s.replace(/'[^']*'/g, ' ').replace(/"[^"]*"/g, ' ');
  // bỏ ternary / toán tử, giữ lại các token
  s = s.replace(/[?:=!<>&|()\[\]{},;+*]/g, ' ');
  for (const tok of s.split(/\s+/)) {
    const t = tok.trim();
    if (!t || t.length > 60) continue;
    if (t.startsWith('.') || t.startsWith('#')) continue;
    // chỉ nhận token trông như lớp utility: chữ+số, có thể có ':' '-' '[' ']' '/' '%'
    if (!/^[a-z][a-z0-9]*(?:[:\-][a-z0-9.%/\[\]#@\-]+)*$/i.test(t)) continue;
    // loại token rõ ràng không phải class
    if (/^(true|false|null|undefined|and|or|not)$/i.test(t)) continue;
    record(t, file);
  }
}

function walk(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, e.name);
    if (SKIP.test(full)) continue;
    if (e.isDirectory()) { walk(full); continue; }
    if (!EXTS.includes(path.extname(e.name))) continue;
    const txt = fs.readFileSync(full, 'utf8');
    extractFromText(txt, path.relative(ROOT, full));
  }
}
for (const d of SRC_DIRS) {
  const p = path.join(ROOT, d);
  if (fs.existsSync(p)) walk(p);
}

console.log('Số lớp dùng trong mã nguồn:', usedClasses.size);

// ------------------------------------------------------------------ 3. Đối chiếu
// Các lớp KHÔNG phải Tailwind/utility (hook JS, trạng thái Alpine, class động)
const IGNORE_PREFIX = /^(x-|@|alpine|is-|has-|js-|active$|group$|peer$|dark$)/;
const IGNORE_EXACT = new Set([
  'hidden', 'flex', 'block', 'grid', 'contents', 'inline', 'table',
]);
// Lớp có thể sinh động bằng nối chuỗi -> không kết luận
const DYNAMIC_HINT = /^(text|bg|border|from|to|via|ring|shadow|opacity|w|h|p|m|gap|rounded|grid-cols|col-span|translate|scale|rotate|z|top|left|right|bottom|inset|order|leading|tracking|font|space|divide|overflow|object|aspect|min|max|basis|grow|shrink|place|justify|items|self|fill|stroke|duration|delay|ease|animate|cursor|select|pointer|resize|appearance|outline|blur|brightness|contrast|grayscale|hue|invert|saturate|sepia|backdrop|transition|will|sr|not-sr|antialiased|truncate|uppercase|lowercase|capitalize|italic|underline|line-through|whitespace|break|list|decoration|indent|align|vertical|float|clear|columns|isolate|mix|filter|transform|origin|perspective|rotate|skew)/;

const missing = [];
for (const [cls, files] of usedClasses) {
  if (IGNORE_PREFIX.test(cls)) continue;
  if (defined.has(cls)) continue;
  // Tailwind escape: ':' -> '\:' ; '[' -> '\[' ; '/' -> '\/' ; '.' -> '\.' ; '%' -> '\%'
  const esc = cls.replace(/[:[\]/.%#@]/g, c => '\\' + c);
  if (defined.has(esc)) continue;
  // Biến thể có tiền tố màn hình mà bản build cố tình bỏ (theo HANDOFF: họ lg: bị purge)
  missing.push({ cls, files: [...files].slice(0, 4) });
}

// Ưu tiên các lớp trông như utility thật (không phải tên lớp tự đặt)
const looksUtility = c => DYNAMIC_HINT.test(c);
missing.sort((a, b) => (looksUtility(b.cls) ? 1 : 0) - (looksUtility(a.cls) ? 1 : 0) || a.cls.localeCompare(b.cls));

console.log('\n=== LỚP DÙNG NHƯNG KHÔNG CÓ TRONG CSS ĐÃ BIÊN DỊCH: ' + missing.length + ' ===\n');
for (const m of missing) {
  console.log(`  ${m.cls.padEnd(30)} ${m.files.join(', ')}`);
}

fs.writeFileSync(path.join(ROOT, 'scratch', 'review', 'out', 'missing-classes.json'), JSON.stringify(missing, null, 2));
console.log('\n→ Đã ghi scratch/review/out/missing-classes.json');
