// Phần cứng giả lập: camera QR (Chromium fake device).
// Yêu cầu: NODE_PATH trỏ tới playwright; file video Y4M chứa lần lượt QR mã em A, mã lạ, mã em B (xem README trong báo cáo).
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const OUT = __dirname + '/out';
const BASE = process.env.E2E_BASE || 'http://localhost:8088';
const Y4M = process.env.QR_Y4M;
const sql = (q) => execSync(`mysql -uroot --default-character-set=utf8mb4 ${process.env.E2E_DB || 'tntt_e2e'} -N -B -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const res = [];
const rec = (id, title, ok, detail = '') => { res.push({ id, title, ok: !!ok, detail }); console.log((ok ? 'PASS ' : 'FAIL ') + id + ' ' + title + (detail ? '  -> ' + detail : '')); };

async function login(page, phone, pw) {
  await page.goto(BASE + '/?dangnhap=1', { waitUntil: 'networkidle' });
  await page.fill('input[type=tel]', phone); await page.fill('input[autocomplete=current-password]', pw);
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);
  await page.waitForTimeout(1200);
}

(async () => {
  // ---------------- 1. QR bằng camera giả
  sql("delete from login_attempts; update members set must_change_pw=0");
  sql("delete from attendances where program_id in (select id from programs where name='Buổi Thử Quét QR')");
  sql("delete from stamp_transactions where student_id in (61,62); delete from student_stamps where student_id in (61,62)");
  sql("delete from programs where name='Buổi Thử Quét QR'");
  sql("insert into programs (year_id,name,type,status,count_for_attendance,count_for_emulation,start_time,day_of_week,days_of_week,allow_qr) values (1,'Buổi Thử Quét QR','bắt buộc','kích hoạt',1,1,'00:01:00',NULL,'0,1,2,3,4,5,6',1)");
  const pid = sql("select id from programs where name='Buổi Thử Quét QR'");
  const browser = await chromium.launch({ args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream', '--use-file-for-fake-video-capture=' + Y4M] });
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, permissions: ['camera'] });
  const page = await ctx.newPage();
  const errs = []; page.on('pageerror', (e) => errs.push(e.message)); page.on('console', (m) => { if (m.type() === 'error') errs.push(m.text()); });
  await login(page, '0901000001', 'tntt@2026');
  await page.evaluate(() => window.Alpine.$data(document.querySelector('.app-shell')).openModule('attendance'));
  await page.waitForTimeout(2500);
  const started = await page.evaluate((pid) => { const a = window.Alpine.$data(document.querySelector('.app-shell')); const p = (a.programs || []).find((x) => String(x.id) === String(pid)); if (!p) return 'no-program'; a.startSession(p); return 'ok'; }, pid);
  await page.waitForTimeout(1500);
  rec('CAM-00', 'Chọn được buổi điểm danh hôm nay và vào màn điểm danh', started === 'ok', started);
  await page.click('button:has-text("Quét QR")');
  await page.waitForTimeout(3500);
  const route = await page.evaluate(() => { const a = window.Alpine.$data(document.querySelector('.app-shell')); return { duong: a._qrDuong, mo: a.qrMo !== undefined ? a.qrMo : null, tt: a.qrTrangThai }; });
  console.log('  scanner state:', JSON.stringify(route));
  console.log('  iframes:', JSON.stringify(await page.evaluate(() => [...document.querySelectorAll('iframe')].map((f) => ({ src: f.getAttribute('src'), vis: !!f.offsetParent })))));
  await page.screenshot({ path: OUT + '/cam-scanning.png' });
  await page.waitForTimeout(9000); // đủ 1 vòng video (3 mã)
  const st = await page.evaluate(() => { const a = window.Alpine.$data(document.querySelector('.app-shell')); return { daQuet: a.qrDaQuet, hang: (a._qrHang || []).length, tt: a.qrTrangThai }; });
  console.log('  after loop:', JSON.stringify(st));
  await page.screenshot({ path: OUT + '/cam-after-loop.png' });
  await page.evaluate(() => window.Alpine.$data(document.querySelector('.app-shell')).ketThucQuet());
  await page.waitForTimeout(2500);
  const rows = sql(`select s.code, a.status, a.method from attendances a join students s on s.id=a.student_id where a.program_id=${pid} order by s.code`);
  console.log('  attendances:', JSON.stringify(rows));
  rec('CAM-01', 'Camera giả → quét được cả 2 mã em hợp lệ (A và B) và ghi điểm danh', rows.includes('GDGLPT260061') && rows.includes('GDGLPT260062'), rows.replace(/\n/g, ' | '));
  rec('CAM-02', 'Mã lạ (ZZZ999) không tạo điểm danh', sql(`select count(*) from attendances where program_id=${pid}`) === '2', 'số dòng=' + sql(`select count(*) from attendances where program_id=${pid}`));
  rec('CAM-03', 'Phương thức ghi nhận là quét QR', /qr/i.test(rows), rows.replace(/\n/g, ' | '));
  const moc = sql(`select count(*) from stamp_transactions where student_id in (61,62)`);
  rec('CAM-04', 'Buổi tính Mộc: quét QR cộng Mộc cho 2 em (mỗi em 1 giao dịch)', moc === '2', 'giao dịch Mộc=' + moc);
  rec('CAM-05', 'Không có lỗi JS trong lúc quét', errs.length === 0, errs.slice(0, 2).join(' | '));
  await ctx.close();
  await browser.close();
  const p = res.filter((r) => r.ok).length; console.log('TOTAL', res.length, 'PASS', p, 'FAIL', res.length - p);
  require('fs').writeFileSync(OUT + '/hw_results.json', JSON.stringify(res, null, 1));
})().catch((e) => { console.error(e); process.exit(1); });
