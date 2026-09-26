/**
 * AUDIT (bản chặt): chỉ báo cáo lớp CSS dùng trong THUỘC TÍNH class/:class
 * mà KHÔNG có trong CSS đã biên dịch. Đây là loại lỗi đã gây việc icon
 * kính lúp đè lên placeholder ở module Thiếu Nhi (pl-10 + left-3.5 bị thiếu).
 */
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'G:\\xampp\\htdocs\\tntt';
const CSS_DIR = path.join(ROOT, 'public', 'assets', 'css');
const CSS_FILES = ['tailwind.css', 'app.css', 'dark.css', 'skeleton.css', 'analytics.css', 'toast.css', 'brand.css', 'font.css'];

const cssText = CSS_FILES.map(f => {
  const p = path.join(CSS_DIR, f);
  return fs.existsSync(p) ? fs.readFileSync(p, 'utf8') : '';
}).join('\n');

const defined = new Set();
for (const m of cssText.matchAll(/\.((?:[A-Za-z0-9_-]|\\.)+)(?=[\s,{:>+~[])/g)) {
  defined.add(m[1].replace(/\\/g, ''));
}

// ---- Tiền tố utility hợp lệ của Tailwind (đủ dùng cho dự án này) ----
const PREFIX = new Set([
  'container','sr','not-sr','pointer-events','visible','invisible','static','fixed','absolute','relative','sticky',
  'inset','inset-x','inset-y','top','right','bottom','left','isolate','z','order','col','col-span','col-start','col-end',
  'row','row-span','float','clear','m','mx','my','mt','mr','mb','ml','ms','me','box','block','inline','flex','inline-flex',
  'table','grid','inline-grid','contents','list-item','hidden','aspect','size','h','max-h','min-h','w','min-w','max-w',
  'flex-1','flex-auto','flex-initial','flex-none','flex-row','flex-col','flex-wrap','flex-nowrap','grow','shrink','basis',
  'table-auto','table-fixed','border-collapse','border-separate','origin','translate-x','translate-y','scale','scale-x','scale-y',
  'rotate','skew-x','skew-y','transform','transform-gpu','transform-none','animate','cursor','touch','select','resize','snap',
  'scroll','list','appearance','columns','break','grid-cols','grid-rows','grid-flow','auto-cols','auto-rows','gap','gap-x','gap-y',
  'justify','justify-items','justify-self','content','items','self','place-content','place-items','place-self','p','px','py','pt','pr','pb','pl','ps','pe',
  'space-x','space-y','divide-x','divide-y','divide','overflow','overflow-x','overflow-y','overscroll','object','object-position',
  'rounded','rounded-t','rounded-r','rounded-b','rounded-l','rounded-tl','rounded-tr','rounded-br','rounded-bl','rounded-s','rounded-e',
  'border','border-x','border-y','border-t','border-r','border-b','border-l','border-s','border-e','border-spacing',
  'bg','bg-gradient','from','via','to','decoration','fill','stroke','stroke-width','text','font','antialiased','subpixel-antialiased',
  'italic','not-italic','uppercase','lowercase','capitalize','normal-case','ordinal','slashed-zero','lining-nums','oldstyle-nums',
  'proportional-nums','tabular-nums','diagonal-fractions','stacked-fractions','tracking','leading','line-clamp','truncate','text-ellipsis',
  'text-clip','indent','align','whitespace','break-words','break-all','break-keep','hyphens','content-none','underline','overline',
  'line-through','no-underline','decoration-style','decoration-thickness','decoration-offset','underline-offset','opacity','mix-blend',
  'bg-blend','filter','blur','brightness','contrast','drop-shadow','grayscale','hue-rotate','invert','saturate','sepia','backdrop-blur',
  'backdrop-brightness','backdrop-contrast','backdrop-grayscale','backdrop-hue-rotate','backdrop-invert','backdrop-opacity','backdrop-saturate',
  'backdrop-sepia','transition','duration','ease','delay','will-change','accent','caret','scheme','ring','ring-offset','shadow',
  'outline','outline-offset','placeholder','placeholder-opacity','w-full','h-full',
]);

const VARIANTS = new Set(['sm','md','lg','xl','2xl','hover','focus','active','focus-visible','focus-within','group-hover','group-focus','disabled','dark','motion-safe','motion-reduce','first','last','odd','even','print','rtl','ltr','peer-checked','peer-focus','before','after','placeholder','selection','file']);

/** Token có phải một lớp utility Tailwind hợp lệ? */
function isUtility(tok) {
  const parts = tok.split(':');
  const base = parts.pop();
  for (const v of parts) if (!VARIANTS.has(v)) return false;
  // bỏ hậu tố trạng thái tuỳ biến (đã có trong CSS viết tay)
  const first = base.split('-')[0];
  if (PREFIX.has(base)) return true;
  if (PREFIX.has(first)) return true;
  // dạng số thuần như 'p-4' đã phủ; còn 'text-micro' -> first='text' ok
  return false;
}

const used = new Map(); // class -> Map(file -> count)

function add(tok, file, ctx) {
  if (!tok) return;
  if (!isUtility(tok)) return;
  if (!used.has(tok)) used.set(tok, { files: new Set(), ctx });
  used.get(tok).files.add(file);
}

function harvest(val, file, ctx) {
  let s = val.replace(/\$\{[^}]*\}/g, ' ').replace(/\{\{[^}]*\}\}/g, ' ');
  // Giữ lại nội dung trong nháy đơn (mảng class Alpine: ['a','b'])
  const quoted = [...s.matchAll(/'([^']*)'/g)].map(m => m[1]).join(' ');
  s = s.replace(/'[^']*'/g, ' ');
  for (const chunk of [s, quoted]) {
    for (const raw of chunk.split(/\s+/)) {
      let t = raw.trim().replace(/^[.,;]+|[.,;]+$/g, '');
      if (!t || t.length > 55) continue;
      if (!/^[a-zA-Z][a-zA-Z0-9]*(?:[:@][a-zA-Z0-9-]+)*(?:-[a-zA-Z0-9._/\[\]#%]+)*$/.test(t)) continue;
      add(t, file, ctx);
    }
  }
}

const SKIP = /(bundle\.min|\.min\.|vendor|node_modules|scratch|\.superpowers|\.claude|worktrees)/;
const FILES = [];
function walk(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, e.name);
    if (SKIP.test(full)) continue;
    if (e.isDirectory()) { walk(full); continue; }
    if (!['.php', '.js'].includes(path.extname(e.name))) continue;
    FILES.push(full);
  }
}
walk(path.join(ROOT, 'views'));
walk(path.join(ROOT, 'public', 'assets', 'js', 'modules'));
walk(path.join(ROOT, 'public', 'assets', 'js', 'app.js') ? path.join(ROOT, 'public', 'assets', 'js') : path.join(ROOT, 'public', 'assets', 'js'));

let attrCount = 0;
for (const f of new Set(FILES)) {
  const rel = path.relative(ROOT, f);
  if (/[\\/]vendor[\\/]/.test(rel)) continue;
  const txt = fs.readFileSync(f, 'utf8');
  // thuộc tính class / :class / x-bind:class / className
  for (const m of txt.matchAll(/(?::class|x-bind:class|class|className)\s*=\s*("([^"]*)"|'([^']*)')/g)) {
    harvest(m[2] ?? m[3] ?? '', rel, 'attr'); attrCount++;
  }
  // classList.add/remove/toggle
  for (const m of txt.matchAll(/classList\.(?:add|remove|toggle)\(([^)]*)\)/g)) harvest(m[1], rel, 'classList');
}

console.log('Số lớp utility dùng trong markup:', used.size, '| số thuộc tính class quét:', attrCount);

const missing = [];
for (const [cls, info] of used) {
  if (defined.has(cls)) continue;
  const esc = cls.replace(/[:[\]/.%#@]/g, c => '\\' + c);
  if (defined.has(esc)) continue;
  missing.push({ cls, files: [...info.files], ctx: info.ctx });
}
missing.sort((a, b) => a.cls.localeCompare(b.cls));

console.log('\n=== LỚP UTILITY DÙNG NHƯNG THIẾU TRONG CSS: ' + missing.length + ' ===\n');
const byFile = {};
for (const m of missing) {
  console.log(`  ${m.cls.padEnd(26)} ${m.files.join(', ')}`);
  for (const f of m.files) (byFile[f] ||= []).push(m.cls);
}
fs.writeFileSync(path.join(ROOT, 'scratch', 'review', 'out', 'missing-classes.json'), JSON.stringify(missing, null, 2));
console.log('\n→ Đã ghi missing-classes.json');
