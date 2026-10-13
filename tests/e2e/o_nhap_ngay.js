// Ô NHẬP NGÀY / GIỜ THEO KHUÔN dd/mm/yyyy · hh:mm — trên app thật, kể cả máy đặt ngôn ngữ en-US / ja-JP.
//
//   php config/install.php && php tests/fixtures/ci_seed.php && php tests/fixtures/e2e_attendance_seed.php
//   php -S 127.0.0.1:8080 -t public &
//   E2E_BASE=http://127.0.0.1:8080 node tests/e2e/o_nhap_ngay.js
//
// Kết quả: PASS/FAIL từng ca, thoát mã 1 nếu có ca FAIL.

const { chromium } = require('playwright');

const BASE = (process.env.E2E_BASE || 'http://127.0.0.1:8080').replace(/\/$/, '');
let fails = 0;
const ket = (id, title, ok, detail) => { if (!ok) fails++; console.log((ok ? 'PASS ' : 'FAIL ') + id + ' ' + title + (ok ? '' : '  -> ' + detail)); };
const APP = `Alpine.$data(document.querySelector('[x-data="tnttApp"]'))`;

async function dangNhap(page) {
  await page.goto(BASE + '/?dangnhap=1', { waitUntil: 'domcontentloaded' });
  await page.fill('input[type=tel]', '0901000001');
  await page.fill('input[autocomplete=current-password]', 'tntt@2026');
  await page.keyboard.press('Enter');
  await page.waitForFunction(() => { const el = document.querySelector('[x-data="tnttApp"]'); const a = el && window.Alpine && window.Alpine.$data(el); return !!(a && a.heavyLoaded && a.heavyFresh); }, null, { timeout: 30000 });
}

/** Gõ vào ô (xoá sạch trước), trả { chu, model, loi }. */
async function go(page, sel, text, modelExpr) {
  const el = page.locator(sel).first();
  await el.click({ clickCount: 3 });
  await page.keyboard.press('Backspace');
  if (text) await el.pressSequentially(text, { delay: 5 });
  return page.evaluate(([s, m, app]) => {
    const e = document.querySelector(s);
    const a = new Function('return ' + app)();
    return { chu: e.value, model: new Function('a', 'with(a){return ' + m + '}')(a), loi: e.classList.contains('ngay-loi'), hopLe: e.checkValidity() };
  }, [sel, modelExpr, APP]);
}
const blur = (page, sel) => page.evaluate((s) => document.querySelector(s).blur(), sel);
const doc = (page, sel, modelExpr) => page.evaluate(([s, m, app]) => ({ chu: document.querySelector(s).value, model: new Function('a', 'with(a){return ' + m + '}')(new Function('return ' + app)()) }), [sel, modelExpr, APP]);

async function mucApp(browser, locale) {
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, locale: locale });
  const page = await ctx.newPage(); const errs = [];
  page.on('pageerror', e => errs.push(e.message));
  await dangNhap(page);
  const T = (id) => locale + ' ' + id;

  // ---- A. Ngày: ô ngày điểm danh ----
  await page.evaluate(`${APP}.changeModule('attendance')`); await page.waitForTimeout(500);
  const SEL = '[x-ngay="attendanceDate"]';
  const kt = await page.evaluate(([s, app]) => { const e = document.querySelector(s); return { type: e.type, ph: e.placeholder, ml: e.maxLength, hoNay: new Function('return ' + app)().vnToday(), chu: e.value, nat: !!e.parentElement.querySelector('.ngay-native') }; }, [SEL, APP]);
  ket(T('A1'), 'ô là ô chữ, có gợi ý dd/mm/yyyy, hiển thị hôm nay theo khuôn dd/mm/yyyy dù máy ' + locale,
      kt.type === 'text' && kt.ph === 'dd/mm/yyyy' && kt.ml === 10 && kt.nat && kt.chu === kt.hoNay.split('-').reverse().join('/'), JSON.stringify(kt));
  let r = await go(page, SEL, '13102026', 'attendanceDate');
  ket(T('A2'), 'gõ 13102026 -> "13/10/2026", model 2026-10-13', r.chu === '13/10/2026' && r.model === '2026-10-13' && !r.loi, JSON.stringify(r));
  r = await go(page, SEL, '5/9/2026', 'attendanceDate');
  ket(T('A3'), 'gõ 5/9/2026 -> tự thêm số 0 "05/09/2026", model 2026-09-05', r.chu === '05/09/2026' && r.model === '2026-09-05', JSON.stringify(r));
  r = await go(page, SEL, '31022026', 'attendanceDate');
  ket(T('A4'), '31/02/2026 (không có thật): viền đỏ, model rỗng', r.loi && r.model === '' && !r.hopLe, JSON.stringify(r));
  // sửa dở một ngày đang có: xoá bớt cho còn "13/10"
  await go(page, SEL, '13102026', 'attendanceDate');
  await page.evaluate((s) => { const e = document.querySelector(s); e.focus(); e.setSelectionRange(e.value.length, e.value.length); }, SEL);
  for (let i = 0; i < 4; i++) await page.keyboard.press('Backspace');   // 2026 -> 202 -> 20 -> 2 -> dấu / tự mất
  const dang = await doc(page, SEL, 'attendanceDate');
  await blur(page, SEL); r = await doc(page, SEL, 'attendanceDate');
  const sauBlur = await page.evaluate((s) => document.querySelector(s).classList.contains('ngay-loi'), SEL);
  ket(T('A5'), 'sửa dở ("13/10") thì model giữ ngày cũ; rời ô mà dở dang thì model rỗng + viền đỏ', dang.chu === '13/10' && dang.model === '2026-10-13' && r.model === '' && sauBlur, JSON.stringify([dang, r, sauBlur]));
  r = await go(page, SEL, '01011999', 'attendanceDate');
  ket(T('A6'), 'ngoài min 2000-01-01 bị từ chối', r.loi && r.model === '', JSON.stringify(r));
  await go(page, SEL, '13102026', 'attendanceDate');
  const bs = await page.evaluate((s) => { const e = document.querySelector(s); e.focus(); return e.value; }, SEL);
  for (let i = 0; i < 3; i++) await page.keyboard.press('Backspace');
  r = await doc(page, SEL, 'attendanceDate');
  ket(T('A7'), 'Backspace xoá được qua dấu / ("13/10/2026" -> "13/10/2")', bs === '13/10/2026' && r.chu === '13/10/2', JSON.stringify([bs, r]));
  // dán các dạng khác nhau
  const dan = async (s) => { await page.evaluate(([sel, v]) => { const e = document.querySelector(sel); e.focus(); e.value = v; e.dispatchEvent(new InputEvent('input', { inputType: 'insertFromPaste', bubbles: true })); e.blur(); }, [SEL, s]); return doc(page, SEL, 'attendanceDate'); };
  const d1 = await dan('2026-10-13'), d2 = await dan('13-10-2026'), d3 = await dan('13/10/2026'), d4 = await dan('5.9.2026');
  ket(T('A8'), 'dán yyyy-mm-dd, dd-mm-yyyy, dd/mm/yyyy, d.m.yyyy đều ra đúng', [d1, d2, d3].every(x => x.chu === '13/10/2026' && x.model === '2026-10-13') && d4.chu === '05/09/2026' && d4.model === '2026-09-05', JSON.stringify([d1, d2, d3, d4]));
  await page.evaluate(`${APP}.attendanceDate = '2026-12-25'`); r = await doc(page, SEL, 'attendanceDate');
  ket(T('A9'), 'model đổi từ bên ngoài -> chữ cập nhật "25/12/2026"', r.chu === '25/12/2026', JSON.stringify(r));
  await page.evaluate((s) => { const n = document.querySelector(s).parentElement.querySelector('.ngay-native'); n.value = '2026-11-02'; n.dispatchEvent(new Event('change', { bubbles: true })); }, SEL);
  r = await doc(page, SEL, 'attendanceDate');
  ket(T('A10'), 'chọn từ lịch gốc (2026-11-02) -> chữ "02/11/2026", model đúng', r.chu === '02/11/2026' && r.model === '2026-11-02', JSON.stringify(r));

  // ---- B. Giờ: popup chương trình ----
  await page.evaluate(`${APP}.changeModule('programs')`); await page.waitForTimeout(400);
  await page.evaluate(`(function(){ const a = ${APP}; a.openAddProgram ? a.openAddProgram() : (a.showProgramModal = true); a.showProgramModal = true; })()`);
  await page.waitForTimeout(500);
  const SG = '[x-ngay\\.time="programForm.startTime"]';
  r = await go(page, SG, '0930', 'programForm.startTime');
  ket(T('B1'), 'giờ: gõ 0930 -> "09:30", model "09:30"', r.chu === '09:30' && r.model === '09:30' && !r.loi, JSON.stringify(r));
  r = await go(page, SG, '2561', 'programForm.startTime');
  ket(T('B2'), 'giờ 25:61 không hợp lệ: viền đỏ, model rỗng, ô bắt buộc báo chưa hợp lệ', r.loi && r.model === '' && !r.hopLe, JSON.stringify(r));
  r = await go(page, SG, '7', 'programForm.startTime'); await blur(page, SG);
  const dd = await doc(page, SG, 'programForm.startTime');
  ket(T('B3'), 'giờ dở dang (một chữ số) khi rời ô: model rỗng', dd.model === '', JSON.stringify(dd));
  r = await go(page, SG, '1830', 'programForm.startTime');
  ket(T('B4'), 'giờ 24 giờ: 18:30 (không AM/PM) dù máy ' + locale, r.chu === '18:30' && r.model === '18:30', JSON.stringify(r));

  // ---- C. Ngày giờ: thông báo họp ----
  await page.evaluate(`${APP}.changeModule('announcements')`); await page.waitForTimeout(400);
  await page.evaluate(`(function(){ const a = ${APP}; a.openAddAnnouncement ? a.openAddAnnouncement() : null; a.showAnnouncementModal = true; a.announcementForm.isMeeting = true; })()`);
  await page.waitForTimeout(500);
  const SD = '[x-ngay\\.datetime="announcementForm.meetingAt"]';
  r = await go(page, SD, '181020260800', 'announcementForm.meetingAt');
  ket(T('C1'), 'ngày giờ: gõ 181020260800 -> "18/10/2026 08:00", model "2026-10-18T08:00" (giữ dạng T như ô gốc)', r.chu === '18/10/2026 08:00' && r.model === '2026-10-18T08:00', JSON.stringify(r));
  r = await go(page, SD, '181020262560', 'announcementForm.meetingAt');
  ket(T('C2'), 'ngày giờ có giờ sai (25:60): viền đỏ, model rỗng', r.loi && r.model === '', JSON.stringify(r));
  await page.evaluate(`${APP}.announcementForm.meetingAt = '2026-10-18 09:15'`);
  r = await doc(page, SD, 'announcementForm.meetingAt');
  ket(T('C3'), 'model dạng máy chủ "2026-10-18 09:15" hiển thị "18/10/2026 09:15"', r.chu === '18/10/2026 09:15', JSON.stringify(r));

  // ---- D. Ô bị vô hiệu ("cả ngày") ----
  await page.evaluate(`(function(){ const a = ${APP}; a.changeModule('notes'); a.openCreateNote(); a.noteForm.allDay = true; })()`);
  await page.waitForTimeout(500);
  const dis = await page.evaluate(() => { const e = document.querySelector('[x-ngay\\.time="noteForm.time"]'); return { off: e.disabled, wrap: e.parentElement.classList.contains('ngay-off'), nat: e.parentElement.querySelector('.ngay-native').disabled }; });
  ket(T('D1'), 'ô giờ bị vô hiệu khi chọn "cả ngày": nút lịch cũng tắt', dis.off && dis.wrap && dis.nat, JSON.stringify(dis));

  ket(T('ERR'), 'không có pageerror', errs.length === 0, errs.join(' | '));
  await ctx.close();
}

async function mucDangKy(browser) {
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, locale: 'en-US' });
  const page = await ctx.newPage(); const errs = [];
  page.on('pageerror', e => errs.push(e.message));
  await page.goto(BASE + '/?dangnhap=1', { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => window.Alpine && document.querySelector('[x-data="loginScreen"]') && window.Alpine.$data(document.querySelector('[x-data="loginScreen"]')), null, { timeout: 15000 });
  await page.evaluate(() => { window.Alpine.$data(document.querySelector('[x-data="loginScreen"]')).goRegister(); });
  await page.waitForSelector('[x-ngay="rBirth"]', { state: 'visible', timeout: 5000 });
  const el = page.locator('[x-ngay="rBirth"]');
  await el.click(); await el.pressSequentially('01032016', { delay: 5 });
  const r = await page.evaluate(() => { const e = document.querySelector('[x-ngay="rBirth"]'); const a = window.Alpine.$data(document.querySelector('[x-data="loginScreen"]')); return { chu: e.value, model: a.rBirth, max: e.getAttribute('max'), hopLe: e.checkValidity() }; });
  ket('login L1', 'form đăng ký: ngày sinh gõ 01032016 -> "01/03/2016", model 2016-03-01 (máy en-US)', r.chu === '01/03/2016' && r.model === '2016-03-01' && r.hopLe && /^\d{4}-\d{2}-\d{2}$/.test(r.max), JSON.stringify(r));
  await el.click({ clickCount: 3 }); await page.keyboard.press('Backspace'); await el.pressSequentially('01012999', { delay: 5 });
  const r2 = await page.evaluate(() => ({ loi: document.querySelector('[x-ngay="rBirth"]').classList.contains('ngay-loi'), model: window.Alpine.$data(document.querySelector('[x-data="loginScreen"]')).rBirth }));
  ket('login L2', 'ngày sinh ở tương lai (quá max = hôm nay) bị từ chối', r2.loi && r2.model === '', JSON.stringify(r2));
  ket('login ERR', 'không có pageerror', errs.length === 0, errs.join(' | '));
  await ctx.close();
}

(async () => {
  const opts = { args: ['--no-sandbox'] };
  if (process.env.E2E_CHROMIUM) opts.executablePath = process.env.E2E_CHROMIUM;
  const browser = await chromium.launch(opts);
  try {
    await mucApp(browser, 'en-US');
    await mucApp(browser, 'ja-JP');
    await mucDangKy(browser);
  } catch (e) { ket('CRASH', 'Script gặp lỗi không lường trước', false, String(e && e.stack || e).split('\n').slice(0, 4).join(' | ')); }
  await browser.close();
  console.log('\n' + (fails ? fails + ' ca LỖI.' : 'Tất cả ca đạt.'));
  process.exit(fails ? 1 : 0);
})();
