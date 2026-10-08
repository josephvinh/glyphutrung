// KIỂM GIAO DIỆN ĐA THIẾT BỊ — chạy trên CI (job "UI đa thiết bị") và chạy tay được.
//
//   E2E_BASE=http://127.0.0.1:8080 node tests/e2e/ui_matrix.js
//   UI_ENGINES=chromium UI_PAGES=landing,login node tests/e2e/ui_matrix.js   # chạy một phần
//
// Vì sao có: giao diện lỗi riêng ở từng thiết bị (nút bị thanh nav đè, nút cuối bị cắt khi màn hình
// thấp, chữ trắng trên nền trắng khi bật "giảm độ trong suốt", popup vỡ trên WebKit...) mà test
// logic không thấy. Script mở các trang ở nhiều khổ/engine/chế độ trợ năng rồi kiểm trên trang THẬT.
//
// KIỂM (mỗi trang × thiết bị × chế độ):
//   1. tran-ngang   : trang rộng hơn màn hình.
//   2. bi-che / bi-cat : phần tử bấm được (nút, link, ô nhập...) bị che hoặc bị cắt: cuộn tới giữa màn hình
//                     rồi thử "chạm" vào tâm; thứ nằm trên cùng không phải chính nó thì là bị che; phải cuộn
//                     một khung overflow:hidden (người dùng không cuộn được) thì là bị cắt.
//   3. tuong-phan   : chữ của nút/link có độ tương phản với nền < 3 (bắt đúng ca chữ trắng nền trắng).
//   4. loi-trang    : pageerror / console.error (bỏ qua lỗi tải tài nguyên ngoài như phông/CDN).
//
// Trang đã đăng nhập và popup: PR sau (mở rộng mảng PAGES). WebKit ở đây là engine WebKit của
// Playwright trên Linux: rất gần Safari nhưng không phải iPhone thật (thanh công cụ động, vùng
// an toàn) — vẫn cần thử tay trên máy thật trước khi phát hành (docs/process).

const { chromium, webkit, devices } = require('playwright');
const fs = require('fs');
const os = require('os');
const path = require('path');

const BASE = (process.env.E2E_BASE || 'http://127.0.0.1:8080').replace(/\/$/, '');
const OUT = process.env.UI_OUT || path.join(os.tmpdir(), 'ui-matrix');
const ENGINES = (process.env.UI_ENGINES || 'chromium,webkit').split(',').map((s) => s.trim());
const ONLY_PAGES = process.env.UI_PAGES ? process.env.UI_PAGES.split(',').map((s) => s.trim()) : null;

const ANDROID_UA = 'Mozilla/5.0 (Linux; Android 14; SM-S911B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36';

// ---- Trang cần kiểm (mở rộng ở đây) ---------------------------------------------------------
const PAGES = [
  { id: 'landing', path: '/' },
  { id: 'login', path: '/index.php?dangnhap=1', ready: 'input[type=tel]' },
  {
    id: 'login-dang-ky', path: '/index.php?dangnhap=1', ready: 'input[type=tel]',
    // sang bước đăng ký (form dài hơn): bấm nút như người dùng
    before: async (page) => {
      await page.getByRole('button', { name: /Đăng ký làm Giáo Lý Viên/ }).click();
      await page.waitForTimeout(400);
    },
  },
  { id: 'tracuu', path: '/tracuu.php' },
  { id: 'somoc', path: '/somoc.php' },
  { id: 'bxh', path: '/bxh.php' },
].filter((p) => !ONLY_PAGES || ONLY_PAGES.includes(p.id));

// ---- Thiết bị --------------------------------------------------------------------------------
const PROFILES = [
  { id: 'android-360x640', engines: ['chromium'],
    ctx: { viewport: { width: 360, height: 640 }, userAgent: ANDROID_UA, isMobile: true, hasTouch: true, deviceScaleFactor: 2 } },
  { id: 'iphone-390x664', engines: ['webkit'], ctx: { ...devices['iPhone 13'] } },
  { id: 'laptop-1280x600', engines: ['chromium', 'webkit'], ctx: { viewport: { width: 1280, height: 600 } } },
].filter((p) => p.engines.some((e) => ENGINES.includes(e)));

// ---- Chế độ trợ năng -------------------------------------------------------------------------
const MODES = [
  { id: 'binh-thuong' },
  // Samsung: Hỗ trợ tiếp cận > Giảm độ trong suốt. Chỉ Chromium giả lập được (CDP).
  { id: 'giam-trong-suot', engines: ['chromium'],
    setup: async (ctx, page) => {
      const cdp = await ctx.newCDPSession(page);
      await cdp.send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-transparency', value: 'reduce' }] });
    } },
  // Cỡ chữ hệ thống lớn (Android): rem to ra 130%.
  { id: 'chu-lon', after: async (page) => { await page.addStyleTag({ content: 'html{font-size:130% !important}' }); await page.waitForTimeout(200); } },
];

// ---- Phần chạy TRONG trang (không dùng biến ngoài) --------------------------------------------
function auditInPage() {
  const issues = [];
  const vw = document.documentElement.clientWidth;
  const describe = (el) => {
    if (!el) return 'null';
    const cls = (typeof el.className === 'string' ? el.className : '').trim().split(/\s+/).slice(0, 3).join('.');
    return el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (cls ? '.' + cls : '');
  };
  const visible = (el) => {
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    if (r.width < 2 || r.height < 2) return false;                       // gồm cả .sr-only
    if (cs.display === 'none' || cs.visibility === 'hidden' || cs.opacity === '0') return false;
    for (let p = el; p; p = p.parentElement) {
      const s = getComputedStyle(p);
      if (s.display === 'none' || s.visibility === 'hidden') return false;
    }
    return true;
  };

  // 1. Tràn ngang
  if (document.documentElement.scrollWidth > vw + 1) {
    const off = [];
    document.querySelectorAll('body *').forEach((el) => {
      if (off.length >= 3 || !visible(el)) return;
      const r = el.getBoundingClientRect();
      if (r.right > vw + 2 && !el.closest('.overflow-x-auto, .overflow-auto, table, pre')) off.push(describe(el));
    });
    issues.push({ kind: 'tran-ngang', detail: 'scrollWidth ' + document.documentElement.scrollWidth + ' > ' + vw + (off.length ? ' (' + off.join(', ') + ')' : '') });
  }

  // 2. Phần tử bấm được có bị che/cắt không
  const sel = 'a[href], button, input:not([type=hidden]), select, textarea, [role=button], summary';
  // Tổ tiên cắt nội dung (overflow:hidden/clip) theo từng trục. Người dùng không cuộn được khung này,
  // nên phần tử nằm ngoài khung cắt là BỊ CẮT (vd. thẻ đăng nhập bị bóp lại làm mất nút cuối). Chỉ
  // đo vị trí, KHÔNG cuộn: nội dung trang trí cố ý tràn khung (hình mờ ở góc thẻ) không bị báo nhầm.
  const hiddenClippers = (el) => {
    const out = [];
    for (let p = el.parentElement; p; p = p.parentElement) {
      const s = getComputedStyle(p);
      const y = /hidden|clip/.test(s.overflowY), x = /hidden|clip/.test(s.overflowX);
      if (y || x) out.push({ p, y, x });
    }
    return out;
  };
  document.querySelectorAll(sel).forEach((el) => {
    if (!visible(el) || getComputedStyle(el).pointerEvents === 'none') return;
    const clippers = hiddenClippers(el);
    clippers.forEach(({ p, y, x }) => { if (y) p.scrollTop = 0; if (x) p.scrollLeft = 0; });   // trạng thái ban đầu
    const er = el.getBoundingClientRect();
    const clipped = clippers.find(({ p, y, x }) => {
      if (p === document.documentElement || p === document.body) return false;   // tràn ngang đã có phép đo riêng
      const pr = p.getBoundingClientRect();
      const L = pr.left + p.clientLeft, T = pr.top + p.clientTop, R = L + p.clientWidth, B = T + p.clientHeight;
      return (y && (er.top < T - 2 || er.bottom > B + 2)) || (x && (er.left < L - 2 || er.right > R + 2));
    });
    if (clipped) {
      issues.push({ kind: 'bi-cat', detail: describe(el) + ' nằm ngoài khung cắt ' + describe(clipped.p) + ' (overflow:hidden)' });
      return;
    }
    el.scrollIntoView({ block: 'center', inline: 'center' });
    const r = el.getBoundingClientRect();
    const cx = r.left + r.width / 2, cy = r.top + r.height / 2;
    if (cx < 0 || cy < 0 || cx > innerWidth || cy > innerHeight) {
      issues.push({ kind: 'bi-che', detail: describe(el) + ' nằm ngoài màn hình dù đã cuộn tới' });
      return;
    }
    const hit = document.elementFromPoint(cx, cy);
    const labels = el.labels ? Array.from(el.labels) : [];
    const ok = hit && (hit === el || el.contains(hit) || labels.some((l) => l.contains(hit)));
    if (!ok) issues.push({ kind: 'bi-che', detail: describe(el) + ' bị che/cắt bởi ' + describe(hit) });
  });

  // 3. Tương phản chữ của nút/link (chỉ bắt ca "gần như vô hình": < 3)
  const parse = (c) => {
    const n = (c.match(/-?[\d.]+/g) || []).map(Number);
    if (c.startsWith('color(')) return { r: n[0] * 255, g: n[1] * 255, b: n[2] * 255, a: n.length > 3 ? n[3] : 1 };
    return { r: n[0], g: n[1], b: n[2], a: n.length > 3 ? n[3] : 1 };
  };
  const over = (top, bottom) => {
    const a = top.a + bottom.a * (1 - top.a);
    if (a === 0) return { r: 255, g: 255, b: 255, a: 0 };
    const m = (t, b) => (t * top.a + b * bottom.a * (1 - top.a)) / a;
    return { r: m(top.r, bottom.r), g: m(top.g, bottom.g), b: m(top.b, bottom.b), a };
  };
  // Trả về MỌI màu nền có thể nằm sau chữ (gradient có nhiều điểm dừng => nhiều màu), hoặc null nếu
  // nền là ảnh thật (không đo được).
  const bgVariants = (el) => {
    const layers = [];
    for (let p = el; p; p = p.parentElement) {
      const cs = getComputedStyle(p);
      let layer = null;
      if (cs.backgroundImage && cs.backgroundImage !== 'none') {
        if (cs.backgroundImage.includes('url(')) return null;                // ảnh thật: không đo được
        const stops = cs.backgroundImage.match(/(rgba?\([^)]*\)|color\([^)]*\))/g);
        if (!stops) return null;
        layer = { stops: stops.map(parse) };
      } else {
        const c = parse(cs.backgroundColor);
        if (c.a > 0) layer = { solid: c };
      }
      if (layer) {
        layers.push(layer);
        if (layer.solid && layer.solid.a >= 1) break;
      }
    }
    let variants = [{ r: 255, g: 255, b: 255, a: 1 }];
    for (let i = layers.length - 1; i >= 0; i--) {
      const L = layers[i];
      variants = L.solid
        ? variants.map((v) => over(L.solid, v))
        : variants.flatMap((v) => L.stops.map((st) => over(st, v)));
    }
    return variants;
  };
  const lum = (c) => {
    const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
    return 0.2126 * f(c.r) + 0.7152 * f(c.g) + 0.0722 * f(c.b);
  };
  const textParents = (root) => {
    if (root.tagName === 'INPUT') return [root];
    const set = new Set();
    const w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    for (let n = w.nextNode(); n; n = w.nextNode()) {
      // Chỉ đoạn có chữ cái/chữ số: mũi tên › hay biểu tượng trang trí cố ý nhạt, không phải chữ cần đọc.
      if (/[\p{L}\p{N}]/u.test(n.nodeValue) && n.parentElement && visible(n.parentElement)) set.add(n.parentElement);
    }
    return Array.from(set);
  };
  document.querySelectorAll('button, a[href], [role=button], input[type=submit]').forEach((el) => {
    if (!visible(el)) return;
    // Đo theo từng đoạn chữ THẬT (chữ có thể do phần tử con đặt màu riêng, không phải thẻ cha).
    let worst = null;
    textParents(el).forEach((p) => {
      const variants = bgVariants(p);
      if (!variants) return;
      const textColor = parse(getComputedStyle(p).color);
      // Chỉ báo khi KHÔNG màu nền nào đủ đọc (tỉ lệ cao nhất vẫn < 3): bắt ca chữ trắng nền trắng,
      // không báo nhầm nền gradient có một điểm dừng sáng.
      const best = Math.max(...variants.map((bg) => {
        const fg = over(textColor, bg);
        const l1 = lum(fg), l2 = lum(bg);
        return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
      }));
      if (best < 3 && (!worst || best < worst.best)) worst = { best, text: (p.textContent || p.value || '').replace(/\s+/g, ' ').trim() };
    });
    if (worst) issues.push({ kind: 'tuong-phan', detail: describe(el) + ' "' + worst.text.slice(0, 24) + '" tỉ lệ ' + worst.best.toFixed(2) + ' (< 3)' });
  });

  return issues;
}

// ---- Chạy ------------------------------------------------------------------------------------
(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const engines = { chromium, webkit };
  const results = [];
  let totalIssues = 0;

  for (const engName of ENGINES) {
    const browser = await engines[engName].launch();
    for (const prof of PROFILES.filter((p) => p.engines.includes(engName))) {
      for (const mode of MODES.filter((m) => !m.engines || m.engines.includes(engName))) {
        for (const pg of PAGES) {
          const tag = `${engName} · ${prof.id} · ${mode.id} · ${pg.id}`;
          const ctx = await browser.newContext(prof.ctx);
          const page = await ctx.newPage();
          const errors = [];
          page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
          page.on('console', (m) => {
            if (m.type() !== 'error') return;
            const t = m.text();
            if (/Failed to load resource|net::ERR_|ERR_BLOCKED|favicon/i.test(t)) return;   // tài nguyên ngoài
            errors.push('console.error: ' + t);
          });
          let issues = [];
          try {
            if (mode.setup) await mode.setup(ctx, page);
            const res = await page.goto(BASE + pg.path, { waitUntil: 'load' });
            if (!res || res.status() >= 400) issues.push({ kind: 'loi-trang', detail: 'HTTP ' + (res ? res.status() : '???') });
            if (pg.ready) await page.waitForSelector(pg.ready, { state: 'visible', timeout: 8000 });
            await page.waitForTimeout(700);                      // chờ Alpine/biểu tượng khởi tạo
            if (pg.before) await pg.before(page);
            if (mode.after) await mode.after(page);
            if (!issues.length) issues = await page.evaluate(auditInPage);
            for (const e of errors) issues.push({ kind: 'loi-trang', detail: e.slice(0, 160) });
            if (issues.length) {
              const f = path.join(OUT, tag.replace(/[^a-z0-9_.-]+/gi, '_') + '.png');
              await page.screenshot({ path: f }).catch(() => {});
            }
          } catch (e) {
            issues.push({ kind: 'loi-trang', detail: 'không chạy được: ' + String(e.message).split('\n')[0].slice(0, 160) });
          }
          await ctx.close();
          totalIssues += issues.length;
          results.push({ tag, engine: engName, profile: prof.id, mode: mode.id, page: pg.id, issues });
          console.log((issues.length ? '✗' : '✓') + ' ' + tag + (issues.length ? '  — ' + issues.length + ' lỗi' : ''));
          issues.slice(0, 8).forEach((i) => console.log('     [' + i.kind + '] ' + i.detail));
          if (issues.length > 8) console.log('     … và ' + (issues.length - 8) + ' lỗi nữa');
        }
      }
    }
    await browser.close();
  }

  const bad = results.filter((r) => r.issues.length);
  console.log('\n=> ' + (results.length - bad.length) + '/' + results.length + ' tổ hợp đạt, ' + bad.length + ' tổ hợp có lỗi (' + totalIssues + ' lỗi). Ảnh lỗi: ' + OUT);
  fs.writeFileSync(path.join(OUT, 'report.json'), JSON.stringify(results, null, 1));

  // Tóm tắt cho tab "Summary" của GitHub Actions
  if (process.env.GITHUB_STEP_SUMMARY) {
    const lines = ['## Kiểm giao diện đa thiết bị', '', '| Kết quả | Tổ hợp | Lỗi |', '|---|---|---|'];
    results.forEach((r) => lines.push('| ' + (r.issues.length ? '❌' : '✅') + ' | ' + r.tag + ' | ' + r.issues.map((i) => '`' + i.kind + '` ' + i.detail.replace(/\|/g, '/')).slice(0, 4).join('<br>') + ' |'));
    fs.appendFileSync(process.env.GITHUB_STEP_SUMMARY, lines.join('\n') + '\n');
    bad.slice(0, 20).forEach((r) => console.log('::warning title=Giao diện lỗi::' + r.tag + ' — ' + r.issues.map((i) => i.kind).join(', ')));
  }
  process.exit(bad.length ? 1 : 0);
})();
