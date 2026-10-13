// "HÔM NAY / BÂY GIỜ" LUÔN THEO GIỜ VIỆT NAM, DÙ MÁY ĐẶT MÚI GIỜ NÀO.
//
//   php config/install.php && php tests/fixtures/ci_seed.php
//   php -S 127.0.0.1:8080 -t public &
//   E2E_BASE=http://127.0.0.1:8080 node tests/e2e/vntime.js
//
// Cố định "bây giờ" = 2026-10-12 23:30 UTC = 06:30 ngày 13/10/2026 giờ Việt Nam, rồi mở app thật trong
// nhiều múi giờ của máy. Mốc này là lúc đồng hồ máy UTC/Los Angeles còn là ngày 12/10 (đúng khung 00:00-07:00 giờ VN
// từng làm test điểm danh đỏ, xem PR #275) nên mọi chỗ còn dùng đồng hồ máy sẽ lệch ngày/giờ và bị bắt ở đây.
//
// Kết quả: in PASS/FAIL từng ca, thoát mã 1 nếu có ca FAIL.

const { chromium } = require('playwright');

const BASE = (process.env.E2E_BASE || 'http://127.0.0.1:8080').replace(/\/$/, '');
const PASSWORD = 'tntt@2026';
const FIXED_UTC = Date.UTC(2026, 9, 12, 23, 30, 0);     // 12/10/2026 23:30 UTC = 13/10/2026 06:30 VN
const ZONES = process.env.VNTIME_ZONES ? process.env.VNTIME_ZONES.split(',') : ['Asia/Ho_Chi_Minh', 'UTC', 'America/Los_Angeles', 'Pacific/Auckland', 'Europe/London'];

let fails = 0;
function ket(id, title, ok, detail) {
  if (!ok) fails++;
  console.log((ok ? 'PASS ' : 'FAIL ') + id + ' ' + title + (detail !== undefined ? '  -> ' + detail : ''));
}

// Dịch đồng hồ trình duyệt về FIXED_UTC nhưng vẫn chạy tiếp (không đóng băng): Date.now() và new Date()
// cùng dịch, còn Date.UTC / Date.parse / new Date(chuỗi) giữ nguyên.
function shiftClock(fixed) {
  return `(() => {
    const R = Date, off = ${fixed} - R.now();
    function F(...a) { if (!(this instanceof F)) return new R(R.now() + off).toString();
      return a.length === 0 ? new R(R.now() + off) : new R(...a); }
    F.now = () => R.now() + off; F.UTC = R.UTC; F.parse = R.parse; F.prototype = R.prototype;
    window.Date = F;
  })();`;
}

async function chayMotMuiGio(browser, tz) {
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, timezoneId: tz });
  await ctx.addInitScript(shiftClock(FIXED_UTC));
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));

  await page.goto(BASE + '/?dangnhap=1', { waitUntil: 'domcontentloaded' });
  await page.fill('input[type=tel]', '0901000001');
  await page.fill('input[autocomplete=current-password]', PASSWORD);
  await page.keyboard.press('Enter');
  await page.waitForFunction(() => {
    const el = document.querySelector('[x-data="tnttApp"]');
    const a = el && window.Alpine && window.Alpine.$data(el);
    return !!(a && a.heavyLoaded && a.heavyFresh);
  }, null, { timeout: 30000 });

  const r = await page.evaluate(() => {
    const app = Alpine.$data(document.querySelector('[x-data="tnttApp"]'));
    const vn = window.TNTT.vn;
    return {
      mayHienGio: new Date().toString().slice(0, 33),
      today: vn.today(), time: vn.time(), now: vn.now(),
      dow: vn.dow('2026-10-13'), cong: vn.addDays('2026-10-31', 1), tru: vn.addDays('2026-03-01', -1),
      fmtDate: vn.fmtDate('2026-10-13'), fmtDT: vn.fmtDateTime('2026-10-13 06:30:45'), fmtD2: vn.fmtDateTime('2026-10-13'),
      parseLech: vn.parse('2026-10-13 06:30') - Date.now(),       // = 0 nếu hiểu chuỗi là giờ VN
      parseSai: Number.isNaN(vn.parse('2026-02-31')),
      tuoiChuaToi: vn.age('2016-10-14', '2026-10-13'), tuoiDungNgay: vn.age('2016-10-13', '2026-10-13'),
      appToday: app.vnToday(), appTime: app.currentTime(), appNow: app.vnNow(), appTimestamp: app.timestamp(),
      ngayDiemDanh: app.attendanceDate, ngayNghi: app.leaveDate, thangThongKe: app.statMonth,
      homNay: app.dayLabel('2026-10-13'), homQua: app.dayLabel('2026-10-12'),
      batDau0600: app.programStarted({ startTime: '06:00' }, '2026-10-13'),
      batDau0700: app.programStarted({ startTime: '07:00' }, '2026-10-13'),
      chot0600: app.vnParse('2026-10-13 06:00') <= app.nowTs,
      tuongLai: app.programStarted({ startTime: '00:00' }, '2026-10-14'),
      quaKhu: app.programStarted({ startTime: '23:59' }, '2026-10-12'),
      tuoiApp: app.calculateAge('2016-10-13'),
      truoc30: app.formatRelativeTime('2026-10-13 06:00:00'),
      truoc2h: app.formatRelativeTime('2026-10-13 04:30:00'),
      chuaNhat: app.formatFullDate('2026-10-11'),
      todayStamp: app.todayStamp(),
      lichThang: window.TNTT.vn.todayDate().getFullYear() + '-' + (window.TNTT.vn.todayDate().getMonth() + 1) + '-' + window.TNTT.vn.todayDate().getDate(),
    };
  });

  const id = tz;
  ket(id + ' CLOCK', 'đồng hồ máy đã dịch về 12/10 23:30 UTC (kiểm chứng thiết lập test)', /Oct 1[23] 2026/.test(r.mayHienGio), r.mayHienGio);
  ket(id + ' T1', 'hôm nay = 2026-10-13, giờ = 06:30 (giờ VN)', r.today === '2026-10-13' && r.time === '06:30' && r.now === '2026-10-13 06:30',
      r.today + ' ' + r.time);
  ket(id + ' T2', 'thứ / cộng trừ ngày / định dạng dd/mm/yyyy', r.dow === 2 && r.cong === '2026-11-01' && r.tru === '2026-02-28'
      && r.fmtDate === '13/10/2026' && r.fmtDT === '13/10/2026 06:30' && r.fmtD2 === '13/10/2026',
      JSON.stringify([r.dow, r.cong, r.tru, r.fmtDate, r.fmtDT, r.fmtD2]));
  ket(id + ' T3', 'chuỗi giờ máy chủ hiểu là giờ VN (lệch 0 ms); ngày không có thật bị loại', Math.abs(r.parseLech) < 5000 && r.parseSai, String(r.parseLech));
  ket(id + ' T4', 'tuổi tính theo ngày VN: sinh 14/10/2016 -> 9; sinh 13/10/2016 -> 10', r.tuoiChuaToi === 9 && r.tuoiDungNgay === 10 && r.tuoiApp === 10,
      JSON.stringify([r.tuoiChuaToi, r.tuoiDungNgay, r.tuoiApp]));
  ket(id + ' APP-1', 'app: hôm nay / giờ / mốc thời gian theo giờ VN', r.appToday === '2026-10-13' && r.appTime === '06:30' && r.appNow === '2026-10-13 06:30' && r.appTimestamp === '2026-10-13 06:30',
      JSON.stringify([r.appToday, r.appTime, r.appNow]));
  ket(id + ' APP-2', 'ngày mặc định của điểm danh / nghỉ phép / tháng thống kê = hôm nay VN', r.ngayDiemDanh === '2026-10-13' && r.ngayNghi === '2026-10-13' && r.thangThongKe === '2026-10',
      JSON.stringify([r.ngayDiemDanh, r.ngayNghi, r.thangThongKe]));
  ket(id + ' APP-3', 'nhãn "Hôm nay"/"Hôm qua" và thứ trong tuần theo ngày VN', r.homNay === 'Hôm nay' && r.homQua === 'Hôm qua' && /^Chúa Nhật, 11\/10\/2026$/.test(r.chuaNhat),
      JSON.stringify([r.homNay, r.homQua, r.chuaNhat]));
  ket(id + ' APP-4', 'buổi 06:00 đã bắt đầu, buổi 07:00 chưa (06:30 VN); mai chưa tới, hôm qua đã qua', r.batDau0600 === true && r.batDau0700 === false && r.chot0600 === true && r.tuongLai === false && r.quaKhu === true,
      JSON.stringify([r.batDau0600, r.batDau0700, r.chot0600, r.tuongLai, r.quaKhu]));
  ket(id + ' APP-5', '"X phút/giờ trước" tính từ giờ VN của máy chủ', r.truoc30 === '30 phút trước' && r.truoc2h === '2 giờ trước', JSON.stringify([r.truoc30, r.truoc2h]));
  ket(id + ' APP-6', 'tên tệp dd-mm-yyyy và ngày lịch tháng theo giờ VN', r.todayStamp === '13-10-2026' && r.lichThang === '2026-10-13', JSON.stringify([r.todayStamp, r.lichThang]));
  ket(id + ' ERR', 'không có pageerror', errors.length === 0, errors.join(' | '));

  await ctx.close();
}

(async () => {
  const opts = { args: ['--no-sandbox'] };
  if (process.env.E2E_CHROMIUM) opts.executablePath = process.env.E2E_CHROMIUM;
  const browser = await chromium.launch(opts);
  try {
    for (const tz of ZONES) await chayMotMuiGio(browser, tz);
  } catch (e) {
    ket('CRASH', 'Script gặp lỗi không lường trước', false, String(e && e.stack || e).split('\n').slice(0, 3).join(' | '));
  }
  await browser.close();
  console.log('\n' + (fails ? fails + ' ca LỖI.' : 'Tất cả ca đạt.'));
  process.exit(fails ? 1 : 0);
})();
