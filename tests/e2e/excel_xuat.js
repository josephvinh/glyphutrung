// XUẤT EXCEL: CỘT NGÀY LÀ Ô NGÀY THẬT, HIỆN dd/mm/yyyy, ĐÚNG NGÀY DÙ MÁY Ở MÚI GIỜ NÀO.
//
//   php config/install.php && php tests/fixtures/ci_seed.php && php tests/fixtures/e2e_attendance_seed.php
//   php -S 127.0.0.1:8080 -t public &
//   E2E_BASE=http://127.0.0.1:8080 node tests/e2e/excel_xuat.js
//
// Phần 1 (Node thuần): dựng sheet bằng đúng xlsx_io.buildXlsxSheet + SheetJS, ghi ra bộ nhớ, đọc lại, kiểm kiểu ô / định dạng / số ngày.
// Phần 2 (trình duyệt thật, 3 múi giờ máy): xuất danh sách thiếu nhi và bảng điểm danh chi tiết, đọc file tải về.
// Kết quả: in PASS/FAIL từng ca, thoát mã 1 nếu có ca FAIL.

const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const vm = require('vm');

const ROOT = path.resolve(__dirname, '..', '..');
const BASE = (process.env.E2E_BASE || 'http://127.0.0.1:8080').replace(/\/$/, '');
const X = require(path.join(ROOT, 'public/assets/js/vendor/xlsx.core.min.js'));
let fails = 0;
function ket(id, title, ok, detail) { if (!ok) fails++; console.log((ok ? 'PASS ' : 'FAIL ') + id + ' ' + title + (detail !== undefined ? '  -> ' + detail : '')); }

// Số ngày Excel của một ngày theo lịch (không qua múi giờ): để so với giá trị trong file.
const serial = (y, m, d, hh = 0, mm = 0) => Date.UTC(y, m - 1, d) / 86400000 + 25569 + (hh * 60 + mm) / 1440;

function nap() {
  const win = {}; win.window = win;
  const ctx = vm.createContext({ window: win, console, Math, Date, String, Number, Object, Array });
  for (const f of ['vntime.js', 'xlsx_io.js']) vm.runInContext(fs.readFileSync(path.join(ROOT, 'public/assets/js/modules', f), 'utf8'), ctx, { filename: f });
  return win.TNTT;
}

function phan1() {
  const T = nap();
  const sh = { name: 'T', rows: [
    ['Tên', 'Ngày sinh', 'Ngày giờ', 'Rỗng', 'SĐT'],
    ['A', { d: '2016-10-13' }, { dt: '2026-10-13 06:30' }, { d: '' }, '0901000001'],
    ['B', { d: '2026-02-31' }, { d: '1850-01-01' }, '', '0909'],      // không có thật / trước 1900 -> chữ, không vỡ
  ] };
  const ws = T.xlsx_io.buildXlsxSheet(X, sh);
  const wb = X.utils.book_new(); X.utils.book_append_sheet(wb, ws, 'T');
  const buf = X.write(wb, { type: 'buffer', bookType: 'xlsx' });
  const back = X.read(buf, { type: 'buffer', cellDates: false, cellNF: true });
  const w = back.Sheets.T;
  const b2 = w.B2, c2 = w.C2;
  ket('P1-1', 'ô ngày là SỐ (ô ngày thật), định dạng dd/mm/yyyy, đúng số ngày', b2 && b2.t === 'n' && b2.z === 'dd/mm/yyyy' && Math.abs(b2.v - serial(2016, 10, 13)) < 1e-9, JSON.stringify([b2 && b2.t, b2 && b2.z, b2 && b2.v]));
  ket('P1-2', 'ô ngày giờ: dd/mm/yyyy hh:mm, đúng 06:30', c2 && c2.t === 'n' && c2.z === 'dd/mm/yyyy hh:mm' && Math.abs(c2.v - serial(2026, 10, 13, 6, 30)) < 1e-6, JSON.stringify([c2 && c2.z, c2 && c2.v]));
  ket('P1-3', 'ô ngày rỗng không thành ô số; SĐT vẫn là văn bản giữ số 0 đầu', (!w.D2 || w.D2.t !== 'n') && w.E2.t === 's' && w.E2.v === '0901000001', JSON.stringify([w.D2, w.E2 && w.E2.v]));
  ket('P1-4', 'ngày không có thật / trước 1900 ghi thành chữ, không vỡ file', w.B3 && w.B3.t === 's' && w.C3 && w.C3.t === 's', JSON.stringify([w.B3 && w.B3.v, w.C3 && w.C3.v]));
  ket('P1-5', 'vùng dữ liệu phủ đủ 5 cột × 3 dòng; cột ngày đủ rộng', w['!ref'] === 'A1:E3' && ws['!cols'][1].wch >= 12 && ws['!cols'][2].wch >= 18, w['!ref'] + ' ' + JSON.stringify(ws['!cols'].map(c => c.wch)));
  // đọc lại bằng chính bộ đọc của app: ô ngày -> dd/mm/yyyy
  const docApp = T.xlsx_io.readXlsxRows.call({ loadXlsxLib: async () => X }, { arrayBuffer: async () => buf.buffer.slice(buf.byteOffset, buf.byteOffset + buf.byteLength) }, 'T');
  return docApp.then(rows => ket('P1-6', 'bộ đọc của app đọc lại ô ngày thành dd/mm/yyyy', rows[1][1] === '13/10/2016', JSON.stringify(rows[1])));
}

async function phan2() {
  const opts = { args: ['--no-sandbox'] };
  if (process.env.E2E_CHROMIUM) opts.executablePath = process.env.E2E_CHROMIUM;
  const browser = await chromium.launch(opts);
  const out = fs.mkdtempSync(path.join(os.tmpdir(), 'xuat-'));
  for (const tz of ['Asia/Ho_Chi_Minh', 'UTC', 'America/Los_Angeles']) {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, timezoneId: tz, acceptDownloads: true });
    const page = await ctx.newPage(); const errs = []; page.on('pageerror', e => errs.push(e.message));
    await page.goto(BASE + '/?dangnhap=1', { waitUntil: 'domcontentloaded' });
    await page.fill('input[type=tel]', '0901000001'); await page.fill('input[autocomplete=current-password]', 'tntt@2026'); await page.keyboard.press('Enter');
    await page.waitForFunction(() => { const el = document.querySelector('[x-data="tnttApp"]'); const a = el && window.Alpine && window.Alpine.$data(el); return !!(a && a.heavyLoaded && a.heavyFresh); }, null, { timeout: 30000 });

    // Danh sách thiếu nhi: chọn một em có ngày sinh; quản trị toàn đoàn phải gõ tìm thì danh sách mới hiện
    const sinh = await page.evaluate(async () => {
      const a = Alpine.$data(document.querySelector('[x-data="tnttApp"]'));
      const s = a.students.find(x => x.birthDate);
      if (!s) return null;
      a.changeModule('students'); a.searchQuery = s.code;
      await new Promise(r => setTimeout(r, 600));
      return { code: s.code, birth: s.birthDate, n: a.filteredStudents.length };
    });
    const [dl1] = await Promise.all([page.waitForEvent('download', { timeout: 20000 }),
      page.evaluate(() => { Alpine.$data(document.querySelector('[x-data="tnttApp"]')).exportToExcel(); })]);
    const f1 = path.join(out, tz.replace('/', '_') + '_ds.xlsx'); await dl1.saveAs(f1);
    const wb1 = X.read(fs.readFileSync(f1), { type: 'buffer', cellNF: true }); const ws1 = wb1.Sheets[wb1.SheetNames[0]];
    const rows1 = X.utils.sheet_to_json(ws1, { header: 1, raw: true });
    const ci = rows1[0].indexOf('Ngày sinh'); const ri = 1;      // đúng một em sau dòng tiêu đề
    const cell = ws1[X.utils.encode_cell({ r: ri, c: ci })];
    const [y, m, d] = (sinh ? sinh.birth : '2000-01-01').split('-').map(Number);
    ket(tz + ' DS-1', 'danh sách thiếu nhi: ngày sinh là ô ngày thật dd/mm/yyyy, đúng ngày ' + (sinh && sinh.birth),
        !!sinh && !!cell && cell.t === 'n' && cell.z === 'dd/mm/yyyy' && cell.v === serial(y, m, d), JSON.stringify([cell && cell.t, cell && cell.z, cell && cell.v, serial(y, m, d)]));
    // Đọc lại bằng bộ đọc của app trong trình duyệt (múi giờ máy đang đặt): phải ra đúng dd/mm/yyyy
    const b64 = fs.readFileSync(f1).toString('base64');
    const doc = await page.evaluate(async (b) => {
      const a = Alpine.$data(document.querySelector('[x-data="tnttApp"]'));
      const bin = Uint8Array.from(atob(b), c => c.charCodeAt(0));
      const file = new File([bin], 'x.xlsx');
      return a.readXlsxRows(file, 'Danh sách');
    }, b64);
    const dd = (sinh ? sinh.birth.split('-').reverse().join('/') : '');
    ket(tz + ' DS-2', 'nhập lại file vừa xuất: ngày sinh vẫn là ' + dd, !!sinh && doc[ri] && doc[ri][ci] === dd, JSON.stringify(doc[ri] && doc[ri][ci]));

    // Điểm danh chi tiết (máy chủ trả bảng, trình duyệt dựng file): cột "Ngày" là ô ngày thật
    const [tuNgay, denNgay] = await page.evaluate(() => { const a = Alpine.$data(document.querySelector('[x-data="tnttApp"]')); return [a.vnAddDays(a.vnToday(), -300), a.vnToday()]; });
    const [dl2] = await Promise.all([page.waitForEvent('download', { timeout: 20000 }),
      page.evaluate(([f, t]) => { Alpine.$data(document.querySelector('[x-data="tnttApp"]')).exportAttendanceDetail(null, f, t); }, [tuNgay, denNgay])]).catch(() => [null]);
    if (dl2) {
      const f2 = path.join(out, tz.replace('/', '_') + '_dd.xlsx'); await dl2.saveAs(f2);
      const wb2 = X.read(fs.readFileSync(f2), { type: 'buffer', cellNF: true }); const ws2 = wb2.Sheets[wb2.SheetNames[0]];
      const hdr = X.utils.sheet_to_json(ws2, { header: 1, raw: true })[0] || [];
      const cNgay = hdr.indexOf('Ngày'); const c2 = ws2[X.utils.encode_cell({ r: 1, c: cNgay })];
      ket(tz + ' DD-1', 'điểm danh chi tiết: cột Ngày là ô ngày thật dd/mm/yyyy', cNgay >= 0 && (!c2 || (c2.t === 'n' && c2.z === 'dd/mm/yyyy')), JSON.stringify([cNgay, c2 && c2.t, c2 && c2.z]));
      ket(tz + ' DD-2', 'tên tệp dùng dd-mm-yyyy', /_\d{2}-\d{2}-\d{4}_\d{2}-\d{2}-\d{4}\.xlsx$/.test(dl2.suggestedFilename()), dl2.suggestedFilename());
    } else ket(tz + ' DD-1', 'điểm danh chi tiết tải được file', false, 'không có tải xuống');
    ket(tz + ' ERR', 'không có pageerror', errs.length === 0, errs.join(' | '));
    await ctx.close();
  }
  await browser.close();
}

(async () => {
  try { await phan1(); await phan2(); }
  catch (e) { ket('CRASH', 'Script gặp lỗi không lường trước', false, String(e && e.stack || e).split('\n').slice(0, 3).join(' | ')); }
  console.log('\n' + (fails ? fails + ' ca LỖI.' : 'Tất cả ca đạt.'));
  process.exit(fails ? 1 : 0);
})();
