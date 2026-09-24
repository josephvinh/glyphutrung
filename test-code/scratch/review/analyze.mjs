/** Phân tích geometry-admin.json → in ra các bất thường đã ĐO ĐƯỢC. */
import fs from 'node:fs';
const g = JSON.parse(fs.readFileSync('G:\\xampp\\htdocs\\tntt\\scratch\\review\\out\\geometry-admin.json', 'utf8'));

const pages = Object.keys(g);
console.log('=== 1. THANH ĐIỀU HƯỚNG ĐÁY (mobile) ===');
for (const p of pages) {
  const m = g[p].mobile; if (!m?.bottomnav) continue;
  const b = m.bottomnav;
  console.log(`${p.padEnd(14)} visible=${b.visible} h=${b.height} bg=${b.bg} backdrop=${b.backdrop.slice(0,22)} translucent=${b.translucent} mainPadBottom=${m.mainPaddingBottom}px lastChildHiddenBehindNav=${m.lastChildHiddenBehindNav}px`);
}

console.log('\n=== 2. THANH BÊN (desktop) ===');
for (const p of pages) {
  const d = g[p].desktop; if (!d?.sidebar) continue;
  const s = d.sidebar;
  console.log(`${p.padEnd(14)} sbH=${s.height} sbBottom=${s.bottom} vh=${s.viewportH} navScrollable=${s.navScrollable} navClient=${s.navClientHeight} navScrollH=${s.navScrollHeight} lastItemClipped=${s.lastItemClipped}px reachableAfterScroll=${s.lastItemReachableAfterScroll}`);
}

console.log('\n=== 3. TRÀN NGANG (ngoài vùng cuộn hợp lệ) ===');
for (const p of pages) for (const v of ['desktop', 'mobile']) {
  const o = g[p][v]?.overflow || [];
  if (o.length) { console.log(`--- ${p} / ${v}`); o.forEach(x => console.log(`    <${x.t}> right=${x.right} vw=${x.vw} "${x.txt}" .${x.c}`)); }
}

console.log('\n=== 4. TƯƠNG PHẢN THẤP (WCAG) ===');
for (const p of pages) for (const v of ['desktop', 'mobile']) {
  const lc = g[p][v]?.lowContrast || [];
  if (lc.length) { console.log(`--- ${p} / ${v}`); lc.forEach(x => console.log(`    ${x.ratio}:1 (cần ${x.need}) "${x.txt}" color=${x.color} bg=${x.bg} ${x.fs}px .${x.cls}`)); }
}

console.log('\n=== 5. Ô NHẬP NGÀY / ĐỊNH DẠNG NGÀY ===');
for (const p of pages) for (const v of ['desktop', 'mobile']) {
  const d = g[p][v];
  if (d?.dateInputs?.length) console.log(`${p}/${v} date inputs:`, JSON.stringify(d.dateInputs));
  if (d?.dateTexts?.length) console.log(`${p}/${v} date texts:`, JSON.stringify(d.dateTexts));
}

console.log('\n=== 6. Ô TÌM KIẾM — icon đè placeholder? ===');
for (const p of pages) for (const v of ['desktop', 'mobile']) {
  const s = g[p][v]?.searchInputs || [];
  s.forEach(x => console.log(`${p}/${v} ph="${x.placeholder}" paddingLeft=${x.paddingLeft} iconRight=${x.iconRight} overlap=${x.overlap} (dương = icon đè lên chữ) fs=${x.fontSize}`));
}

console.log('\n=== 7. DẢI CUỘN NGANG (chip lọc) ===');
for (const p of pages) for (const v of ['desktop', 'mobile']) {
  const h = g[p][v]?.hScrollStrips || [];
  h.forEach(x => console.log(`${p}/${v} ẩn ${x.hidden}px (sw=${x.sw} cw=${x.cw}) thanh cuộn hiện=${x.scrollbarShown} .${x.cls}`));
}
