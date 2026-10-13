// NHẬP EXCEL: Ô NGÀY ĐỌC RA ĐÚNG dd/mm/yyyy DÙ MÁY Ở MÚI GIỜ NÀO.
//
//   node tests/e2e/excel_nhap.js          (không cần máy chủ, DB hay trình duyệt)
//
// Dựng file .xlsx bằng SheetJS với đủ kiểu ô ngày mà Excel/LibreOffice thật ghi ra (ngày có định dạng dd/mm/yyyy,
// định dạng dựng sẵn m/d/yy, ngày giờ, hệ ngày 1904, chữ dd/mm/yyyy, ô chỉ có giờ, số thường...), rồi đọc lại bằng
// ĐÚNG xlsx_io.readXlsxRows. Mỗi lần chạy lặp lại trong nhiều múi giờ của máy (biến môi trường TZ) vì lỗi cũ là
// SheetJS dựng Date theo múi giờ máy. Cuối cùng chạy kết quả qua parseDate của app: dd/mm/yyyy -> yyyy-mm-dd.
// Ca N-6 chạy vòng xuất (buildXlsxSheet) -> nhập (readXlsxRows); excel_xuat.js kiểm thêm bằng trình duyệt thật.
// Kết quả: PASS/FAIL từng ca, thoát mã 1 nếu có ca FAIL.

const { spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const ROOT = path.resolve(__dirname, '..', '..');
const ZONES = ['Asia/Ho_Chi_Minh', 'UTC', 'America/Los_Angeles', 'Pacific/Auckland', 'America/Sao_Paulo', 'Europe/London', 'Pacific/Kiritimati'];

if (process.argv[2] === 'child') {
  chay().then(f => process.exit(f ? 1 : 0));
} else {
  let tong = 0;
  for (const tz of ZONES) {
    const r = spawnSync(process.execPath, [__filename, 'child'], { env: { ...process.env, TZ: tz }, encoding: 'utf8' });
    process.stdout.write(r.stdout);
    if (r.status !== 0) { tong++; if (r.stderr) process.stdout.write(r.stderr); }
  }
  console.log('\n' + (tong ? tong + ' múi giờ có ca LỖI.' : 'Tất cả ca đạt ở ' + ZONES.length + ' múi giờ.'));
  process.exit(tong ? 1 : 0);
}

async function chay() {
  const X = require(path.join(ROOT, 'public/assets/js/vendor/xlsx.core.min.js'));
  const win = {}; win.window = win;
  const ctx = vm.createContext({ window: win, console, Math, Date, String, Number, Object, Array, isFinite, RegExp });
  for (const f of ['vntime.js', 'xlsx_io.js', 'shell.js']) vm.runInContext(fs.readFileSync(path.join(ROOT, 'public/assets/js/modules', f), 'utf8'), ctx, { filename: f });
  const io = win.TNTT.xlsx_io, shell = win.TNTT.shell;
  const serial = (y, m, d, hh = 0, mm = 0) => Date.UTC(y, m - 1, d) / 86400000 + 25569 + (hh * 60 + mm) / 1440;
  const tz = process.env.TZ;
  let fails = 0;
  const ket = (id, title, ok, detail) => { if (!ok) fails++; console.log((ok ? 'PASS ' : 'FAIL ') + tz + ' ' + id + ' ' + title + (ok ? '' : '  -> ' + detail)); };

  async function doc(cells, opts) {
    const ws = { '!ref': 'A1:' + X.utils.encode_cell({ r: cells.length - 1, c: Math.max(...cells.map(r => r.length)) - 1 }) };
    cells.forEach((row, r) => row.forEach((c, ci) => { if (c !== null) ws[X.utils.encode_cell({ r: r, c: ci })] = c; }));
    const wb = X.utils.book_new(); X.utils.book_append_sheet(wb, ws, 'Danh sách');
    if (opts && opts.date1904) wb.Workbook = { WBProps: { date1904: true } };
    const buf = X.write(wb, { type: 'buffer', bookType: 'xlsx', cellStyles: true });
    const ab = buf.buffer.slice(buf.byteOffset, buf.byteOffset + buf.byteLength);
    return io.readXlsxRows.call({ loadXlsxLib: async () => X }, { arrayBuffer: async () => ab }, 'Danh sách');
  }
  const s = (v) => ({ t: 's', v: v });
  const n = (v, z) => (z ? { t: 'n', v: v, z: z } : { t: 'n', v: v });

  // 1. Mọi kiểu ô ngày
  const r1 = await doc([
    [s('Ngày')],
    [n(serial(2016, 3, 1), 'dd/mm/yyyy')],
    [n(serial(2017, 9, 4), 'm/d/yy')],                 // định dạng ngày dựng sẵn của Excel (hiện theo vùng máy)
    [n(serial(2026, 10, 13, 6, 30), 'dd/mm/yyyy hh:mm')],
    [n(serial(2024, 2, 29), 'd-mmm-yy')],
    [n(serial(2018, 11, 4), 'dd/mm/yyyy')],            // nửa đêm không tồn tại ở một số múi giờ có giờ mùa hè
    [n(serial(2026, 3, 29), 'dd/mm/yyyy')],
    [n(serial(1990, 1, 1), 'dd/mm/yyyy')],
    [n(serial(2099, 12, 31), 'dd/mm/yyyy')],
    [n(serial(2016, 3, 1, 23, 59), 'dd/mm/yyyy hh:mm')],   // gần nửa đêm: không nhảy sang ngày sau
  ]);
  const mong1 = ['Ngày', '01/03/2016', '04/09/2017', '13/10/2026', '29/02/2024', '04/11/2018', '29/03/2026', '01/01/1990', '31/12/2099', '01/03/2016'];
  ket('N-1', 'ô ngày (nhiều định dạng) đọc ra đúng dd/mm/yyyy', JSON.stringify(r1.map(r => r[0])) === JSON.stringify(mong1), JSON.stringify(r1.map(r => r[0])));

  // 2. Chữ, số, ô trống, ô giờ
  const r2 = await doc([
    [s('Cột'), s('Giá trị')],
    [s('chữ ngày'), s('05/09/2017')],
    [s('chữ ISO'), s('2017-09-05')],
    [s('chữ có khoảng trắng'), s('  5/9/2017  ')],
    [s('số thường'), n(901234567)],
    [s('SĐT chữ'), s('0901234567')],
    [s('chỉ giờ'), n(0.5, 'h:mm')],
    [null, null],                                        // dòng trống bị bỏ
    [s('số 0 ngày'), n(0)],
  ]);
  const g = (i) => (r2[i] || [])[1];
  ket('N-2', 'chữ dd/mm/yyyy và yyyy-mm-dd giữ nguyên; trim; số thường là chuỗi gọn; SĐT chữ giữ số 0 đầu',
      g(1) === '05/09/2017' && g(2) === '2017-09-05' && g(3) === '5/9/2017' && g(4) === '901234567' && g(5) === '0901234567',
      JSON.stringify(r2.map(r => r[1])));
  ket('N-3', 'ô chỉ có giờ -> HH:mm; dòng trống hoàn toàn bị bỏ', g(6) === '12:00' && r2.length === 8, JSON.stringify([g(6), r2.length]));

  // 3. Hệ ngày 1904 (Excel cho Mac đời cũ)
  const r3 = await doc([[s('Ngày')], [n(serial(2016, 3, 1) - 1462, 'dd/mm/yyyy')]], { date1904: true });
  ket('N-4', 'sổ làm việc hệ ngày 1904 đọc ra đúng 01/03/2016', r3[1] && r3[1][0] === '01/03/2016', JSON.stringify(r3[1]));

  // 4. Đường nhập của app: dd/mm/yyyy -> yyyy-mm-dd qua parseDate
  const iso = ['01/03/2016', '04/09/2017', '13/10/2026', '29/02/2024', '5/9/2017'].map(x => shell.parseDate.call({}, x));
  ket('N-5', 'parseDate: dd/mm/yyyy -> yyyy-mm-dd', JSON.stringify(iso) === JSON.stringify(['2016-03-01', '2017-09-04', '2026-10-13', '2024-02-29', '2017-09-05']), JSON.stringify(iso));

  // 5. Vòng xuất -> nhập: ô ngày thật do buildXlsxSheet (tests/e2e/excel_xuat.js, #280) ghi ra đọc lại đúng
  const sh = { name: 'Danh sách', rows: [['Mã', 'Ngày sinh'], ['A', { d: '2016-03-01' }], ['B', { d: '1999-12-31' }], ['C', { d: '' }], ['D', { dt: '2026-10-13 23:59' }]] };
  const ws = io.buildXlsxSheet.call(io, X, sh);
  const wb = X.utils.book_new(); X.utils.book_append_sheet(wb, ws, 'Danh sách');
  const buf = X.write(wb, { type: 'buffer', bookType: 'xlsx', cellStyles: true });
  const rt = await io.readXlsxRows.call({ loadXlsxLib: async () => X }, { arrayBuffer: async () => buf.buffer.slice(buf.byteOffset, buf.byteOffset + buf.byteLength) }, 'Danh sách');
  ket('N-6', 'xuất rồi nhập lại: ngày sinh nguyên vẹn, ô trống vẫn trống, ngày giờ 23:59 không nhảy sang ngày sau',
      rt[1][1] === '01/03/2016' && rt[2][1] === '31/12/1999' && rt[3][1] === '' && rt[4][1] === '13/10/2026', JSON.stringify(rt));

  console.log('');
  return fails;
}
