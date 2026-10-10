// PWA / Service Worker: đăng ký, kho tệp tĩnh, mất mạng, sự kiện push. Dùng tên miền tntt.localhost vì sw.js bỏ qua hẳn 'localhost'.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const OUT = __dirname + '/out';
const ORIGIN = process.env.E2E_ORIGIN || 'http://tntt.localhost:8088';
const sql = (q) => execSync(`mysql -uroot --default-character-set=utf8mb4 ${process.env.E2E_DB || 'tntt_e2e'} -N -B -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const res = []; const rec = (id, title, ok, detail = '') => { res.push({ id, title, ok: !!ok, detail }); console.log((ok ? 'PASS ' : 'FAIL ') + id + ' ' + title + (detail ? '  -> ' + detail : '')); };
(async () => {
  sql("delete from login_attempts; update members set must_change_pw=0; delete from push_outbox");
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, permissions: ['notifications'] });
  const page = await ctx.newPage();
  const errs = []; page.on('pageerror', (e) => errs.push(e.message));
  await page.goto(ORIGIN + '/?dangnhap=1', { waitUntil: 'networkidle' });
  await page.fill('input[type=tel]', '0901000001'); await page.fill('input[autocomplete=current-password]', 'tntt@2026');
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);
  await page.waitForTimeout(1500);
  const cdp = await ctx.newCDPSession(page);
  const regs = {}; await cdp.send('ServiceWorker.enable');
  cdp.on('ServiceWorker.workerRegistrationUpdated', (e) => { for (const r of e.registrations) regs[r.registrationId] = r; });
  const reg = await page.evaluate(async () => { try { const r = await navigator.serviceWorker.register('sw.js', { type: 'module' }); await navigator.serviceWorker.ready; return { scope: r.scope, active: !!r.active }; } catch (e) { return { err: String(e) }; } });
  rec('PWA-01', 'Đăng ký service worker sw.js thành công (tên miền khác localhost)', !!reg.scope && !reg.err, JSON.stringify(reg));
  await page.waitForTimeout(2500);
  await page.reload({ waitUntil: 'networkidle' }); await page.waitForTimeout(1500);
  const ctrl = await page.evaluate(() => !!navigator.serviceWorker.controller);
  rec('PWA-02', 'Sau khi tải lại, service worker điều khiển trang', ctrl, String(ctrl));
  const cached = await page.evaluate(async () => { const names = await caches.keys(); const out = {}; for (const n of names) { const c = await caches.open(n); out[n] = (await c.keys()).map((r) => new URL(r.url).pathname); } return out; });
  console.log('  cache:', JSON.stringify(cached).slice(0, 300));
  const all = Object.values(cached).flat();
  rec('PWA-03', 'Kho tệp tĩnh chứa bundle CSS/JS (precache)', all.some((p) => /bundle\.php/.test(p)), all.join(', ').slice(0, 160));
  rec('PWA-04', 'Kho KHÔNG chứa API/dữ liệu nghiệp vụ (điểm danh, điểm số phải luôn lấy từ mạng)', !all.some((p) => /\/api\//.test(p) || /index\.php$/.test(p)), all.filter((p) => /\/api\//.test(p)).join(','));
  // offline
  await ctx.setOffline(true);
  const off = await page.reload({ waitUntil: 'load' }).then((r) => ({ status: r && r.status() })).catch((e) => ({ err: e.message.split('\n')[0] }));
  console.log('  offline reload:', JSON.stringify(off));
  rec('PWA-05', 'Mất mạng: mở lại app hiển thị trang thay thế (không màn hình lỗi trình duyệt)', !off.err, JSON.stringify(off));
  await page.screenshot({ path: OUT + '/pwa-offline.png' }).catch(() => {});
  const apiOff = await page.evaluate(async () => { try { await fetch('/api/auth.php?action=me'); return 'ok'; } catch (e) { return 'network-error'; } }).catch(() => 'page-gone');
  await ctx.setOffline(false);
  await page.goto(ORIGIN + '/', { waitUntil: 'networkidle' }).catch(() => {});
  await page.waitForTimeout(1000);
  // push event -> SW gọi pending -> nội dung được lấy
  sql("insert into push_outbox (member_id,title,body,url,tag,created_at) values (1,'Thử SW','Nội dung từ hàng đợi','/','tntt-thu',NOW())");
  const rid = Object.keys(regs)[0];
  let delivered = 'no-registration';
  if (rid) { try { await cdp.send('ServiceWorker.deliverPushMessage', { origin: ORIGIN, registrationId: rid, data: '' }); delivered = 'sent'; } catch (e) { delivered = e.message; } }
  await page.waitForTimeout(3000);
  const taken = sql("select count(*) from push_outbox where taken_at is not null");
  rec('PWA-06', 'Sự kiện push (chuông rỗng) → SW gọi api pending và lấy việc trong hàng đợi', taken === '1', `deliver=${delivered}, đã lấy=${taken}`);
  // sw.js không cache
  const swh = await page.evaluate(async () => { const r = await fetch('/sw.js', { cache: 'no-store' }); return { status: r.status, ct: r.headers.get('content-type'), cc: r.headers.get('cache-control') }; });
  rec('PWA-07', 'sw.js phục vụ đúng kiểu JavaScript', /javascript/.test(swh.ct || ''), JSON.stringify(swh));
  const man = await page.evaluate(async () => { const r = await fetch('/manifest.json'); return r.ok ? await r.json() : null; });
  const inst = await cdp.send('Page.getInstallabilityErrors').catch(() => ({ installabilityErrors: ['cdp-fail'] }));
  rec('PWA-08', 'manifest.json hợp lệ và Chromium không báo lỗi cài đặt (installability)', !!man && !!man.name && /standalone|fullscreen/.test(man.display || '') && inst.installabilityErrors.length === 0, JSON.stringify({ name: man && man.name, display: man && man.display, icons: man && (man.icons || []).map((i) => i.sizes + ':' + (i.purpose || '')), installabilityErrors: inst.installabilityErrors }));
  rec('PWA-09', 'Không có lỗi JS trong lúc chạy PWA', errs.length === 0, errs.slice(0, 2).join(' | '));
  console.log('  API khi offline:', apiOff);
  await browser.close();
  const p = res.filter((r) => r.ok).length; console.log('TOTAL', res.length, 'PASS', p, 'FAIL', res.length - p);
  require('fs').writeFileSync(OUT + '/pwa_results.json', JSON.stringify(res, null, 1));
})().catch((e) => { console.error(e); process.exit(1); });
