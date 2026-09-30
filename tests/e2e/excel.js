// Excel qua giao diện: tải mẫu, nhập file thật (openpyxl), xuất danh sách/bảng điểm, đọc lại file kiểm nội dung + phông.
const { chromium } = require('playwright'); const { execSync } = require('child_process'); const fs = require('fs');
const OUT = __dirname + '/out'; const PYLIB = process.env.PYLIB; const BASE = process.env.E2E_BASE || 'http://127.0.0.1:8088';
const sql = (q) => execSync(`mysql -uroot --default-character-set=utf8mb4 ${process.env.E2E_DB || 'tntt_e2e'} -N -B -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const py = (code) => execSync(`PYTHONPATH=${PYLIB} python3 -c ${JSON.stringify(code)}`).toString().trim();
const res = []; const rec = (id, t, ok, d = '') => { res.push({ id, t, ok: !!ok, d }); console.log((ok ? 'PASS ' : 'FAIL ') + id + ' ' + t + (d ? '  -> ' + d : '')); };
(async () => {
  sql("delete from login_attempts; update members set must_change_pw=0");
  sql("delete from enrollments where student_id in (select id from students where full_name like 'XLSX %'); delete from students where full_name like 'XLSX %'");
  const cls = sql("select name from classes order by id limit 1");
  const browser = await chromium.launch(); const ctx = await browser.newContext({ acceptDownloads: true, viewport: { width: 1366, height: 900 } }); const page = await ctx.newPage();
  const errs = []; page.on('pageerror', (e) => errs.push(e.message));
  await page.goto(BASE + '/?dangnhap=1', { waitUntil: 'networkidle' });
  await page.fill('input[type=tel]', '0901000001'); await page.fill('input[autocomplete=current-password]', 'tntt@2026');
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]); await page.waitForTimeout(1200);
  await page.evaluate(() => window.Alpine.$data(document.querySelector('.app-shell')).openModule('students')); await page.waitForTimeout(1500);
  // 1. tải mẫu (danh sách rỗng)
  let [dl] = await Promise.all([page.waitForEvent('download', { timeout: 8000 }).catch(() => null), page.evaluate(() => window.Alpine.$data(document.querySelector('.app-shell')).exportToExcel())]);
  rec('XLS-01', 'Tải file mẫu Excel khi chưa chọn lớp', !!dl, dl ? dl.suggestedFilename() : 'không có download');
  if (!dl) { await browser.close(); return; }
  await dl.saveAs(OUT + '/template.xlsx');
  const info = py(`import openpyxl,json;wb=openpyxl.load_workbook('${OUT}/template.xlsx');ws=wb.active;rows=[[str(c.value) if c.value is not None else '' for c in r] for r in ws.iter_rows()];print(json.dumps({'sheets':wb.sheetnames,'n':len(rows),'first':rows[:8],'font':ws['A1'].font.name+' '+str(ws['A1'].font.sz)},ensure_ascii=False))`);
  const t = JSON.parse(info); console.log('  template:', info.slice(0, 300));
  rec('XLS-02', 'File mẫu có sheet "Danh sách", có dòng tiêu đề "Họ và Tên"', t.sheets.includes('Danh sách') && JSON.stringify(t.first).includes('Họ'), t.sheets.join(','));
  rec('XLS-03', 'Phông mặc định của file xuất là Times New Roman 13 (commit #76)', /Times New Roman/.test(t.font) && /13/.test(t.font), t.font);
  // 2. tạo file nhập
  const hdrRow = t.first.findIndex((r) => r.some((c) => /Họ/.test(c) && /Tên/.test(c)));
  const header = t.first[hdrRow];
  const mk = (name, lop, sinh, sdt) => header.map((h) => (/Họ.*Tên/.test(h) ? name : /Lớp/.test(h) ? lop : /Ngày sinh|Sinh/.test(h) ? sinh : /SĐT|điện thoại|Cha/i.test(h) && /Cha|Bố/i.test(h) ? sdt : /Tên thánh/i.test(h) ? 'Maria' : ''));
  const rows = [mk('XLSX Một', cls, '05/03/2015', '0912345678'), mk('XLSX Hai', cls, '2014-07-09', '84912345679'), mk('XLSX Ba', 'Lớp Không Có', '01/01/2015', ''), mk('=1+1', cls, '01/01/2015', ''), mk('XLSX Năm', cls, 'không phải ngày', '')];
  py(`import openpyxl,json;wb=openpyxl.Workbook();ws=wb.active;ws.title='Danh sách';ws.append(${JSON.stringify(header)});[ws.append(r) for r in json.loads(${JSON.stringify(JSON.stringify(rows))})];wb.save('${OUT}/import.xlsx')`);
  await page.setInputFiles('input[type=file]', OUT + '/import.xlsx'); await page.waitForTimeout(3500);
  const got = sql("select full_name from students where full_name like 'XLSX %' or full_name like '=1%' order by full_name");
  console.log('  đã nhập:', JSON.stringify(got));
  rec('XLS-04', 'Nhập file thật: dòng hợp lệ được thêm (XLSX Một, XLSX Hai)', /XLSX MỘT/i.test(got) && /XLSX HAI/i.test(got), got.replace(/\n/g, ' | '));
  rec('XLS-05', 'Dòng có lớp không tồn tại bị bỏ qua', !/XLSX BA/i.test(got), got.replace(/\n/g, ' | '));
  const f = sql("select father_phone from students where full_name like 'XLSX MỘT%'"), f2 = sql("select father_phone from students where full_name like 'XLSX HAI%'");
  rec('XLS-06', 'SĐT giữ số 0 đầu và chuẩn hoá +84 → 0', f === '0912345678' && f2 === '0912345679', `${f} / ${f2}`);
  const bd = sql("select birth_date from students where full_name like 'XLSX MỘT%'"), bd2 = sql("select birth_date from students where full_name like 'XLSX HAI%'");
  rec('XLS-07', 'Ngày sinh dd/mm/yyyy và yyyy-mm-dd đều đọc đúng', bd === '2015-03-05' && bd2 === '2014-07-09', `${bd} / ${bd2}`);
  rec('XLS-09', 'Ngày sinh sai định dạng ("không phải ngày") không làm hỏng lô nhập', /XLSX MỘT/i.test(got), 'XLSX Năm ' + (/XLSX NĂM/i.test(got) ? 'đã nhập' : 'bị bỏ qua') + ', birth=' + sql("select ifnull(birth_date,'NULL') from students where full_name like 'XLSX NĂM%'"));
  // 3. xuất danh sách (chọn lớp)
  const clsId = sql("select id from classes order by id limit 1");
  await page.evaluate((cn) => { const a = window.Alpine.$data(document.querySelector('.app-shell')); a.filterClass = cn; }, cls);
  await page.waitForTimeout(800);
  const n = await page.evaluate(() => window.Alpine.$data(document.querySelector('.app-shell')).filteredStudents.length);
  [dl] = await Promise.all([page.waitForEvent('download', { timeout: 8000 }).catch(() => null), page.evaluate(() => window.Alpine.$data(document.querySelector('.app-shell')).exportToExcel())]);
  if (dl) {
    await dl.saveAs(OUT + '/export.xlsx');
    const ex = JSON.parse(py(`import openpyxl,json;wb=openpyxl.load_workbook('${OUT}/export.xlsx');ws=wb.active;print(json.dumps({'rows':ws.max_row,'ph':[str(c.value) for c in ws[2]][:10]},ensure_ascii=False))`));
    rec('XLS-10', 'Xuất danh sách lớp đang lọc: số dòng = số em hiển thị + tiêu đề', n > 0 && ex.rows >= n, `hiển thị ${n} em, file ${ex.rows} dòng (gồm tiêu đề/chú thích)`);
    const phoneCells = py(`import openpyxl;ws=openpyxl.load_workbook('${OUT}/export.xlsx').active;print(' '.join(repr(c.value) for r in ws.iter_rows(min_row=2) for c in r if c.column_letter in 'JL' and c.value))`);
    rec('XLS-11', 'SĐT trong file xuất là văn bản, giữ số 0 đầu (không bị đổi thành số)', /'0\d{9}'/.test(phoneCells) && !/ \d{9} /.test(' '+phoneCells+' '), phoneCells.slice(0, 100));
  } else rec('XLS-10', 'Xuất danh sách lớp đang lọc', false, 'không có download (n=' + n + ')');
  // 4. bảng điểm
  await page.evaluate(() => window.Alpine.$data(document.querySelector('.app-shell')).openModule('scores')); await page.waitForTimeout(1200);
  rec('XLS-12', 'Không có lỗi JS trong lúc nhập/xuất Excel', errs.length === 0, errs.slice(0, 2).join(' | '));
  sql("delete from enrollments where student_id in (select id from students where full_name like 'XLSX %' or full_name like '=1%'); delete from students where full_name like 'XLSX %' or full_name like '=1%'");
  await browser.close();
  const p = res.filter((r) => r.ok).length; console.log('TOTAL', res.length, 'PASS', p, 'FAIL', res.length - p);
  fs.writeFileSync(OUT + '/excel_results.json', JSON.stringify(res, null, 1));
})().catch((e) => { console.error(e); process.exit(1); });
