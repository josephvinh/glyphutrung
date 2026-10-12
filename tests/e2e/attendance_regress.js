// HỒI QUY TRÌNH DUYỆT CHO ĐIỂM DANH (quét QR, offline, phạm vi theo vai, đồng bộ).
//
//   php config/install.php && php tests/fixtures/ci_seed.php && php tests/fixtures/e2e_attendance_seed.php
//   php -S 127.0.0.1:8080 -t public &
//   E2E_BASE=http://127.0.0.1:8080 node tests/e2e/attendance_regress.js
//
// Chạy lại fixture e2e_attendance_seed.php trước MỖI lần chạy (script ghi điểm danh thật vào DB test).
//
// Mỗi ca bên dưới giữ một lỗi ĐÃ GẶP (xem PR #258, #259, #262, #263):
//   QR-01  Quét QR không ghi vào sổ điểm danh trên máy lúc đang quét (ghi hoãn), đóng máy quét
//          chỉ nạp lại MỘT lần và dữ liệu đúng trong DB.
//   QR-02  Đang mở máy quét thì vòng hỏi đồng bộ 4 giây KHÔNG gọi sync/data (không làm máy quét khựng).
//   QR-03  Đóng máy quét sớm (camera chưa kịp mở) không để lại camera/vòng lặp chạy ngầm.
//   QR-04  Icon lucide không tồn tại không làm trình duyệt kẹt vòng MutationObserver.
//   OFF-01 Hàng đợi offline: chạm rồi chạm lại (gỡ) khi mất mạng; online lại thì DB đúng, hàng đợi rỗng.
//   OFF-02 Quét QR lúc mất mạng: lượt quét được giữ trong hàng đợi, đồng bộ ghi method=qr và scanned_at.
//   OFF-03 Đồng bộ chỉ xoá đúng mục đã gửi xong (mục chen vào giữa chừng không mất), 401 thì dừng.
//   OFF-04 Lượt bị máy chủ từ chối khi đồng bộ được báo đích danh (tên em + lý do), không im lặng.
//   SW-01  Service worker tải sẵn jsQR vào kho STATIC để quét QR trên iPhone lúc mất mạng.
//   SCOPE  GLV chỉ quét được lớp mình; trưởng khối quét được cả khối; chữ phạm vi hiển thị đúng.
//   SYNC   Máy A ghi điểm danh: máy B chỉ nạp lại phần điểm danh (heavy), không nạp core; chính máy A không nạp lại.
//
// Kết quả: in PASS/FAIL từng ca, thoát mã 1 nếu có ca FAIL. Ảnh chụp khi lỗi lưu ở E2E_OUT.

const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const BASE = (process.env.E2E_BASE || 'http://127.0.0.1:8080').replace(/\/$/, '');
const OUT = process.env.E2E_OUT || path.join(os.tmpdir(), 'attendance-regress');
const ROOT = path.resolve(__dirname, '..', '..');
const PASSWORD = 'tntt@2026';
fs.mkdirSync(OUT, { recursive: true });

const results = [];
function rec(id, title, ok, detail = '') {
  results.push({ id, title, ok: !!ok, detail });
  console.log((ok ? 'PASS ' : 'FAIL ') + id + ' ' + title + (detail ? '  -> ' + detail : ''));
}

function dbRows(sql) {
  const out = execFileSync('php', [path.join(ROOT, 'tests/fixtures/e2e_query.php'), sql], { cwd: ROOT, encoding: 'utf8' });
  return JSON.parse(out.trim().split('\n').pop());
}
const rowOf = (code) => dbRows(
  "SELECT a.status, a.method, a.scanned_at, a.offline_marked FROM attendances a " +
  "JOIN students s ON s.id = a.student_id JOIN programs p ON p.id = a.program_id " +
  `WHERE s.code = '${code}' AND p.name = 'E2E Điểm Danh' AND a.session_date = CURDATE()`)[0] || null;

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const APP = `Alpine.$data(document.querySelector('[x-data="tnttApp"]'))`;

async function launch() {
  const opts = { args: ['--no-sandbox', '--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream'] };
  if (process.env.E2E_CHROMIUM) opts.executablePath = process.env.E2E_CHROMIUM;
  return chromium.launch(opts);
}

async function newDevice(browser, phone) {
  // Múi giờ Việt Nam như máy chủ (db.php đặt +07:00, PHP Asia/Ho_Chi_Minh). Trình duyệt chạy
  // theo UTC trên CI sẽ gửi NGÀY CŨ lúc 00:00-07:00 giờ Việt Nam (17:00-24:00 UTC), lệch với
  // CURDATE() của truy vấn kiểm DB -> các ca "DB có bản ghi" đỏ theo giờ chạy.
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 }, permissions: ['camera'], timezoneId: 'Asia/Ho_Chi_Minh',
  });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  // Ghi lại mọi luồng camera để kiểm sau khi đóng máy quét.
  await page.addInitScript(() => {
    window.__streams = [];
    const md = navigator.mediaDevices;
    if (md && md.getUserMedia) {
      const orig = md.getUserMedia.bind(md);
      md.getUserMedia = async (c) => { const s = await orig(c); window.__streams.push(s); return s; };
    }
  });
  await page.goto(BASE + '/?dangnhap=1', { waitUntil: 'domcontentloaded' });
  await page.fill('input[type=tel]', phone);
  await page.fill('input[autocomplete=current-password]', PASSWORD);
  await page.keyboard.press('Enter');
  await page.waitForFunction(() => {
    const el = document.querySelector('[x-data="tnttApp"]');
    const a = el && window.Alpine && window.Alpine.$data(el);
    return !!(a && a.heavyLoaded && a.heavyFresh);
  }, null, { timeout: 30000 });
  return { context, page, errors };
}

// Vào màn điểm danh và mở buổi E2E (client coi như đã tới giờ bắt đầu).
async function openSession(page) {
  await page.evaluate(async () => {
    const app = Alpine.$data(document.querySelector('[x-data="tnttApp"]'));
    app.changeModule('attendance');
    await new Promise((r) => setTimeout(r, 800));
    const prog = app.programs.find((p) => p.name === 'E2E Điểm Danh');
    if (!prog) throw new Error('Không thấy buổi E2E — đã chạy tests/fixtures/e2e_attendance_seed.php chưa?');
    prog.startTime = '00:00';
    for (let i = 0; i < 40 && !(app.heavyLoaded && app.heavyFresh); i++) await new Promise((r) => setTimeout(r, 250));
    app.startSession(prog);
  });
  await sleep(500);
}

// Mở máy quét (camera giả), tắt bíp/rung.
async function openScanner(page) {
  await page.evaluate(async () => {
    const app = Alpine.$data(document.querySelector('[x-data="tnttApp"]'));
    await app.moQuetQR();
    app._qrBip = () => {}; app._qrBipOk = () => {}; app._qrBipLoi = () => {}; app._qrRung = () => {};
  });
  // Đợi bảng tra mã số nạp xong rồi mới quét (quét sớm hơn bảng tra là chưa có em nào để nhận).
  try {
    await page.waitForFunction(() => {
      const el = document.querySelector('[x-data="tnttApp"]');
      const a = el && window.Alpine.$data(el);
      return !!(a && a.qrMo && a._qrTraMa && a._qrTraMa.size > 0);
    }, null, { timeout: 20000 });
  } catch (e) {
    const st = await page.evaluate(() => {
      const a = Alpine.$data(document.querySelector('[x-data="tnttApp"]'));
      return { qrMo: a.qrMo, bang: a._qrTraMa ? a._qrTraMa.size : null, tt: a.qrTrangThai, phien: !!a.activeSession, dangTai: a.qrDangTai };
    }).catch(() => ({}));
    await shot(page, 'openScanner-timeout');
    throw new Error('Máy quét không sẵn sàng: ' + JSON.stringify(st));
  }
}

const scan = (page, codes) => page.evaluate(async (cs) => {
  const app = Alpine.$data(document.querySelector('[x-data="tnttApp"]'));
  for (const c of cs) { app._qrNhan(c); await new Promise((r) => setTimeout(r, 150)); }
}, codes);

function track(page) {
  const log = [];
  page.on('request', (r) => {
    const m = r.url().match(/\/api\/(data\.php\?part=\w+|sync\.php|attendance\.php\?action=\w+)/);
    if (m) log.push(m[1]);
  });
  return log;
}

async function shot(page, name) {
  try { await page.screenshot({ path: path.join(OUT, name + '.png') }); } catch (e) { /* bỏ qua */ }
}

(async () => {
  const browser = await launch();
  try {
    // =============== Máy A: quản trị =====================================================
    const A = await newDevice(browser, '0901000001');
    const pa = A.page;
    const reqA = track(pa);
    await openSession(pa);

    // ---- QR-01: ghi hoãn + đóng máy quét nạp lại đúng một lần + DB đúng
    await openScanner(pa);
    const trước = await pa.evaluate(`${APP}.attendances.length`);
    await scan(pa, ['E2E001', 'E2E002', 'E2E003']);
    await sleep(600);
    const giua = await pa.evaluate(`({ n: ${APP}.attendances.length, cho: ${APP}._qrCho.length })`);
    rec('QR-01a', 'Đang quét: sổ điểm danh trên máy chưa bị ghi (ghi hoãn), lượt quét nằm trong hàng chờ',
      giua.n === trước && giua.cho === 3, `sổ ${trước}->${giua.n}, chờ ${giua.cho}`);
    reqA.length = 0;
    await pa.evaluate(`${APP}.ketThucQuet()`);
    await sleep(6500);
    const coreReload = reqA.filter((x) => x === 'data.php?part=core').length;
    const dbRowsQr = ['E2E001', 'E2E002', 'E2E003'].map(rowOf);
    rec('QR-01b', 'Đóng máy quét: nạp lại dữ liệu đúng 1 lần (không nạp đôi)', coreReload === 1, `core=${coreReload}, các lượt=${JSON.stringify(reqA)}`);
    rec('QR-01c', 'Đóng máy quét: DB có đủ 3 em, method=qr, trạng thái có mặt',
      dbRowsQr.every((r) => r && r.method === 'qr' && r.status === 'có mặt'), JSON.stringify(dbRowsQr));

    // ---- QR-02: đang mở máy quét thì vòng hỏi đồng bộ im lặng
    await openScanner(pa);
    reqA.length = 0;
    await sleep(9000);   // hơn 2 nhịp hỏi 4 giây
    const khiQuet = reqA.filter((x) => x === 'sync.php' || x.startsWith('data.php')).length;
    await pa.evaluate(`${APP}.dongQuetQR()`);
    await sleep(800);
    rec('QR-02', 'Đang mở máy quét: không có lượt hỏi đồng bộ / nạp dữ liệu nào', khiQuet === 0, `số lượt=${khiQuet}`);

    // ---- QR-03: đóng sớm, camera chưa kịp mở
    await pa.evaluate(`window.__streams.length = 0`);
    await pa.evaluate(() => {
      const app = Alpine.$data(document.querySelector('[x-data="tnttApp"]'));
      app.moQuetQR();          // KHÔNG await
      app.dongQuetQR();        // đóng ngay
    });
    await sleep(3000);
    const cam = await pa.evaluate(() => ({
      mo: Alpine.$data(document.querySelector('[x-data="tnttApp"]')).qrMo,
      song: window.__streams.reduce((n, s) => n + s.getTracks().filter((t) => t.readyState === 'live').length, 0),
    }));
    rec('QR-03', 'Đóng máy quét sớm: máy quét đóng và không còn luồng camera nào đang chạy', cam.mo === false && cam.song === 0, JSON.stringify(cam));

    // ---- QR-04: icon không tồn tại: vẽ lại một lần rồi YÊN (trước đây kẹt vòng lặp vô hạn)
    const mut = await pa.evaluate(async () => {
      const el = document.createElement('i');
      el.setAttribute('data-lucide', 'icon-khong-ton-tai-xyz');
      document.body.appendChild(el);
      await new Promise((r) => setTimeout(r, 1200));        // cho phép đợt vẽ lại đầu tiên
      let n = 0;
      const mo = new MutationObserver((l) => { n += l.length; });
      mo.observe(document.body, { subtree: true, childList: true, attributes: true });
      await new Promise((r) => setTimeout(r, 2500));
      mo.disconnect();
      const conNhan = document.querySelectorAll('[data-lucide="icon-khong-ton-tai-xyz"]').length;
      el.remove();
      return { n, conNhan };
    });
    rec('QR-04', 'Icon lucide không tồn tại: sau đợt vẽ lại đầu tiên DOM yên (0 thay đổi) và nhãn icon thiếu bị gỡ',
      mut.n === 0 && mut.conNhan === 0, JSON.stringify(mut));

    // ---- OFF-01: chạm tay khi mất mạng rồi chạm lại (gỡ)
    await pa.evaluate(`localStorage.removeItem('tntt_offline_attendance_queue')`);
    await A.context.setOffline(true);
    await pa.evaluate(async () => {
      const app = Alpine.$data(document.querySelector('[x-data="tnttApp"]'));
      const st = (code) => app.students.find((s) => s.code === code);
      const wait = (ms) => new Promise((r) => setTimeout(r, ms));
      app.toggleAttendance(st('E2E004')); await wait(600);
      app.toggleAttendance(st('E2E005')); await wait(600);
      app.toggleAttendance(st('E2E004')); await wait(600);    // chạm lại = gỡ
    });
    const hang = await pa.evaluate(`${APP}.getOfflineAttendanceQueue().map(q => q.studentId + ':' + q.action)`);
    const idOf = async (code) => pa.evaluate((c) => Alpine.$data(document.querySelector('[x-data="tnttApp"]')).students.find((s) => s.code === c).id, code);
    const id4 = await idOf('E2E004'), id5 = await idOf('E2E005');
    rec('OFF-01a', 'Mất mạng: hàng đợi giữ E2E005=mark và E2E004=unmark (gỡ không biến thành ghi)',
      hang.length === 2 && hang.includes(id5 + ':mark') && hang.includes(id4 + ':unmark'), JSON.stringify(hang));

    // ---- OFF-02: quét QR lúc mất mạng (mở máy quét khi còn mạng để có bảng tra)
    await A.context.setOffline(false);
    await openScanner(pa);
    await A.context.setOffline(true);
    await scan(pa, ['E2E006']);
    await pa.evaluate(`${APP}.ketThucQuet()`);
    await sleep(2500);
    const hang2 = await pa.evaluate(`${APP}.getOfflineAttendanceQueue().map(q => ({ s: q.studentId, a: q.action, m: q.method || null, t: typeof q.scannedAt }))`);
    const id6 = await idOf('E2E006');
    const muc6 = hang2.find((q) => q.s === id6);
    rec('OFF-02a', 'Quét lúc mất mạng: lượt quét nằm trong hàng đợi với method=qr và có scannedAt',
      !!muc6 && muc6.a === 'mark' && muc6.m === 'qr' && muc6.t === 'number', JSON.stringify(hang2));

    // ---- Online lại: đồng bộ
    await A.context.setOffline(false);
    await sleep(500);
    await pa.evaluate(`${APP}.syncOfflineAttendance()`);
    await sleep(2500);
    const r5 = rowOf('E2E005'), r4 = rowOf('E2E004'), r6 = rowOf('E2E006');
    const conLai = await pa.evaluate(`${APP}.getOfflineAttendanceQueue().length`);
    rec('OFF-01b', 'Online lại: E2E005 được ghi (tay, có scanned_at), E2E004 không có bản ghi',
      !!r5 && r5.method === 'tay' && !!r5.scanned_at && r4 === null, `E2E005=${JSON.stringify(r5)} E2E004=${JSON.stringify(r4)}`);
    rec('OFF-02b', 'Online lại: E2E006 được ghi với method=qr và có scanned_at',
      !!r6 && r6.method === 'qr' && !!r6.scanned_at, JSON.stringify(r6));
    rec('OFF-01c', 'Đồng bộ xong: hàng đợi rỗng', conLai === 0, `còn ${conLai}`);

    // ---- OFF-03: mục chen vào giữa chừng không mất; lỗi nghiệp vụ bị bỏ; 401 thì dừng
    const off3 = await pa.evaluate(async () => {
      const app = Alpine.$data(document.querySelector('[x-data="tnttApp"]'));
      const item = (sid, action, t) => ({ programId: 1, date: '2000-01-01', studentId: sid, action, createdAt: t, scannedAt: 1 });
      const ok = {};
      localStorage.removeItem('tntt_offline_attendance_queue');
      app.pushOfflineAttendance(item(1, 'mark', 'A'));
      app.pushOfflineAttendance(item(2, 'mark', 'B'));
      app.pushOfflineAttendance(item(3, 'unmark', 'C'));
      const goc = app.api;
      app.api = async (f, action, body) => {
        if (body.studentId === 1) { app.pushOfflineAttendance(item(1, 'unmark', 'A2')); app.pushOfflineAttendance(item(9, 'mark', 'N')); return { ok: true }; }
        if (body.studentId === 2) return { ok: false, networkError: true };
        return { ok: false, error: 'khoá sổ' };
      };
      await app.syncOfflineAttendance();
      ok.sau = app.getOfflineAttendanceQueue().map((q) => q.studentId + ':' + q.action + ':' + q.createdAt).sort();
      localStorage.removeItem('tntt_offline_attendance_queue');
      app.pushOfflineAttendance(item(1, 'mark', 'a')); app.pushOfflineAttendance(item(2, 'mark', 'b')); app.pushOfflineAttendance(item(3, 'mark', 'c'));
      const goi = [];
      app.api = async (f, action, body) => { goi.push(body.studentId); return body.studentId === 1 ? { ok: true } : { ok: false, networkError: true, unauthorized: true }; };
      await app.syncOfflineAttendance();
      ok.goi401 = goi; ok.hang401 = app.getOfflineAttendanceQueue().map((q) => q.studentId);
      app.api = goc;
      localStorage.removeItem('tntt_offline_attendance_queue');
      app.setOfflineAttendanceQueue([]);
      return ok;
    });
    rec('OFF-03a', 'Đồng bộ: mục chen giữa chừng (1:unmark, 9) và mục lỗi mạng (2) còn lại; mục gửi xong / lỗi nghiệp vụ bị xoá',
      JSON.stringify(off3.sau) === JSON.stringify(['1:unmark:A2', '2:mark:B', '9:mark:N']), JSON.stringify(off3.sau));
    rec('OFF-03b', 'Hết phiên (401): dừng ở lượt đầu lỗi, giữ phần còn lại',
      JSON.stringify(off3.goi401) === JSON.stringify([1, 2]) && JSON.stringify(off3.hang401) === JSON.stringify([2, 3]),
      `đã gọi ${JSON.stringify(off3.goi401)}, còn ${JSON.stringify(off3.hang401)}`);

    // ---- OFF-04: lượt bị máy chủ từ chối khi đồng bộ phải được báo đích danh (không im lặng)
    const off4 = await pa.evaluate(async () => {
      const app = Alpine.$data(document.querySelector('[x-data="tnttApp"]'));
      const hop = [];
      const goc = { api: app.api, loi: window.TNTT.toast.error };
      window.TNTT.toast.error = (m) => { hop.push(String(m)); };
      localStorage.removeItem('tntt_offline_attendance_queue');
      app.pushOfflineAttendance({ programId: 1, date: '2000-01-01', studentId: 77, studentName: 'Em Bi Tu Choi', action: 'mark', createdAt: 'Z', scannedAt: 1 });
      app.api = async () => ({ ok: false, error: 'Đã quá giờ tính vắng' });
      await app.syncOfflineAttendance();
      app.api = goc.api; window.TNTT.toast.error = goc.loi;
      const con = app.getOfflineAttendanceQueue().length;
      app.setOfflineAttendanceQueue([]);
      return { hop, con };
    });
    rec('OFF-04', 'Lượt bị máy chủ từ chối: báo lỗi nêu tên em và lý do; mục bị bỏ khỏi hàng đợi',
      off4.hop.length === 1 && off4.hop[0].includes('Em Bi Tu Choi') && off4.hop[0].includes('Đã quá giờ tính vắng') && off4.con === 0,
      JSON.stringify(off4));

    // ---- SW-01: service worker tải sẵn jsQR vào kho STATIC (quét QR trên iPhone lúc mất mạng)
    const sw = await pa.evaluate(async () => {
      if (!('serviceWorker' in navigator)) return { ho_tro: false };
      await navigator.serviceWorker.ready;
      for (let i = 0; i < 40; i++) {
        const c = await caches.open('tntt-static-v1');
        if (await c.match('/assets/js/vendor/jsQR.min.js')) return { ho_tro: true, co: true };
        await new Promise((r) => setTimeout(r, 250));
      }
      return { ho_tro: true, co: false };
    });
    rec('SW-01', 'Service worker đã tải sẵn jsQR.min.js vào kho tntt-static-v1', sw.ho_tro && sw.co, JSON.stringify(sw));

    // =============== SYNC: máy B (quản trị thứ hai) ========================================
    const B = await newDevice(browser, '0901000001');
    const reqB = track(B.page);
    await openSession(B.page);
    // Vòng hỏi đồng bộ của B phải đã ghi nhận mốc đầu tiên (_syncVersion) rồi A mới ghi; nếu không, lần hỏi
    // đầu của B chỉ "nhớ" mốc mới và bỏ lỡ thay đổi (máy CI chậm hơn nên dễ gặp).
    await B.page.waitForFunction(() => {
      const el = document.querySelector('[x-data="tnttApp"]');
      const a = el && window.Alpine.$data(el);
      return !!(a && a._syncVersion);
    }, null, { timeout: 20000 });
    reqB.length = 0; reqA.length = 0;
    await pa.evaluate(async () => {
      const app = Alpine.$data(document.querySelector('[x-data="tnttApp"]'));
      await app.save('attendance', 'toggle', {
        programId: app.activeSession.programId, date: app.activeSession.date,
        studentId: app.students.find((s) => s.code === 'E2E102').id,
      });
    });
    await sleep(10000);   // ~2 nhịp hỏi 4 giây
    const bCore = reqB.filter((x) => x === 'data.php?part=core').length;
    const bHeavy = reqB.filter((x) => x === 'data.php?part=heavy').length;
    const aReload = reqA.filter((x) => x.startsWith('data.php')).length;
    rec('SYNC-01', 'Máy B chỉ nạp lại phần điểm danh (heavy) sau khi máy A ghi, không nạp core',
      bCore === 0 && bHeavy >= 1, `core=${bCore} heavy=${bHeavy}`);
    rec('SYNC-02', 'Máy A (người vừa ghi) không tự nạp lại vì chính lần ghi của mình', aReload === 0, `số lần nạp=${aReload}`);
    await B.context.close();

    rec('ERR-A', 'Máy A không có pageerror trong cả phiên', A.errors.length === 0, A.errors.slice(0, 3).join(' | '));
    await A.context.close();

    // =============== SCOPE: GLV và trưởng khối =============================================
    const G = await newDevice(browser, '0901000101');
    await openSession(G.page);
    await openScanner(G.page);
    const g = await G.page.evaluate(() => {
      const app = Alpine.$data(document.querySelector('[x-data="tnttApp"]'));
      return { phamVi: app.qrPhamVi, coLopMinh: app._qrTraMa.has('E2E001'), coLopKhac: app._qrTraMa.has('E2E101') };
    });
    rec('SCOPE-01', 'GLV: chữ "Quét được lớp …", bảng tra có em lớp mình, KHÔNG có em lớp khác cùng khối',
      /^Quét được lớp /.test(g.phamVi) && g.coLopMinh && !g.coLopKhac, JSON.stringify(g));
    await scan(G.page, ['E2E101']);          // em lớp khác: phải bị từ chối ngay trên máy
    const gCho = await G.page.evaluate(`${APP}._qrCho.length`);
    await G.page.evaluate(`${APP}.dongQuetQR()`);
    await sleep(1000);
    rec('SCOPE-02', 'GLV quét em lớp khác cùng khối: không vào hàng chờ, DB không có bản ghi', gCho === 0 && rowOf('E2E101') === null, `chờ=${gCho}`);
    rec('ERR-G', 'GLV: không có pageerror', G.errors.length === 0, G.errors.slice(0, 3).join(' | '));
    await G.context.close();

    const T = await newDevice(browser, '0901000102');
    await openSession(T.page);
    await openScanner(T.page);
    const t = await T.page.evaluate(() => {
      const app = Alpine.$data(document.querySelector('[x-data="tnttApp"]'));
      return { phamVi: app.qrPhamVi, em101: app._qrTraMa.has('E2E101'), em001: app._qrTraMa.has('E2E001') };
    });
    rec('SCOPE-03', 'Trưởng khối: chữ "cả khối …", bảng tra có em của mọi lớp trong khối',
      /cả khối/.test(t.phamVi) && t.em101 && t.em001, JSON.stringify(t));
    await scan(T.page, ['E2E101']);
    await T.page.evaluate(`${APP}.ketThucQuet()`);
    await sleep(6000);
    const r101 = rowOf('E2E101');
    rec('SCOPE-04', 'Trưởng khối quét em lớp khác trong khối: được ghi (method=qr)', !!r101 && r101.method === 'qr', JSON.stringify(r101));
    rec('ERR-T', 'Trưởng khối: không có pageerror', T.errors.length === 0, T.errors.slice(0, 3).join(' | '));
    await T.context.close();
  } catch (e) {
    rec('CRASH', 'Script gặp lỗi không lường trước', false, String(e && e.stack || e).split('\n').slice(0, 4).join(' | '));
  } finally {
    await browser.close();
  }

  const fail = results.filter((r) => !r.ok);
  fs.writeFileSync(path.join(OUT, 'attendance-regress.json'), JSON.stringify(results, null, 2));
  console.log(`\n${results.length - fail.length}/${results.length} ca đạt.`);
  process.exit(fail.length ? 1 : 0);
})();
