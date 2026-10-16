// tests/e2e/smoke-logs-export.js
// Test API logs_export.php: gọi với cookie admin, verify response là file CSV/XLSX.

const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');
const { execSync } = require('child_process');

const BASE = process.env.SMOKE_BASE || 'http://127.0.0.1:8766';
const ART = path.join(__dirname, '..', 'artifacts');
const DB = process.env.E2E_DB || 'tntt_demo';

const sql = (q) => execSync(
    `mysql -uroot --default-character-set=utf8mb4 ${DB} -N -B -e "${q.replace(/"/g, '\\"')}`
).toString().trim();

(async () => {
    fs.mkdirSync(ART, { recursive: true });

    // Đảm bảo admin không bị chặn bởi must_change_pw
    try { sql("UPDATE members SET must_change_pw=0"); } catch (_) { /* DB không có sẵn — bỏ qua, sẽ fail ở login */ }

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();
    page.setDefaultTimeout(30000);

    // Login
    console.log('[1] login as admin');
    await page.goto(`${BASE}/?dangnhap=1`, { waitUntil: 'networkidle' });
    await page.waitForSelector('input[type=tel]');
    await page.fill('input[type=tel]', '0901000001');
    await page.fill('input[type=password]', 'tntt@2026');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2000);

    // 2. Gọi API export CSV
    console.log('[2] GET /api/logs_export.php?format=csv');
    const csvRes = await page.evaluate(async (base) => {
        const r = await fetch(base + '/api/logs_export.php?format=csv', {
            credentials: 'include',
        });
        const ab = await r.arrayBuffer();
        const u8 = new Uint8Array(ab);
        const bytes = Array.from(u8);
        // Decode UTF-8 đúng để xem nội dung (giữ BOM để in ra cho dễ debug)
        const body = new TextDecoder('utf-8').decode(ab);
        return {
            status: r.status,
            contentType: r.headers.get('content-type'),
            disposition: r.headers.get('content-disposition'),
            body,
            bytes,
        };
    }, BASE);

    console.log(`    status=${csvRes.status}, content-type=${csvRes.contentType}`);
    console.log(`    disposition=${csvRes.disposition}`);
    console.log(`    body length=${csvRes.body.length}, first 300 chars:`);
    console.log(csvRes.body.slice(0, 300));

    if (csvRes.status !== 200) {
        throw new Error(`CSV export failed: HTTP ${csvRes.status}`);
    }
    if (!csvRes.contentType.startsWith('text/csv')) {
        throw new Error(`Wrong content-type: ${csvRes.contentType}`);
    }
    if (!csvRes.disposition || !csvRes.disposition.includes('attachment')) {
        throw new Error(`Missing Content-Disposition: ${csvRes.disposition}`);
    }
    if (csvRes.bytes[0] !== 0xEF || csvRes.bytes[1] !== 0xBB || csvRes.bytes[2] !== 0xBF) {
        throw new Error('CSV thiếu UTF-8 BOM');
    }
    if (!csvRes.body.includes('Người thực hiện')) {
        throw new Error('CSV thiếu header "Người thực hiện"');
    }

    // Lưu file CSV — dùng raw bytes để giữ nguyên BOM
    const csvPath = path.join(ART, 'nhat-ky-export.csv');
    fs.writeFileSync(csvPath, Buffer.from(csvRes.bytes));
    console.log(`    saved ${csvPath} (${csvRes.bytes.length} bytes)`);

    // 3. Gọi API export XLSX
    console.log('[3] GET /api/logs_export.php?format=xlsx');
    const xlsxBuf = await page.evaluate(async (base) => {
        const r = await fetch(base + '/api/logs_export.php?format=xlsx', {
            credentials: 'include',
        });
        const arr = await r.arrayBuffer();
        return {
            status: r.status,
            contentType: r.headers.get('content-type'),
            disposition: r.headers.get('content-disposition'),
            bytes: Array.from(new Uint8Array(arr)),
        };
    }, BASE);

    console.log(`    status=${xlsxBuf.status}, content-type=${xlsxBuf.contentType}`);
    console.log(`    bytes=${xlsxBuf.bytes.length}, first 4=${xlsxBuf.bytes.slice(0, 4).join(',')} (50 4B 3 4 = ZIP)`);

    if (xlsxBuf.status !== 200) {
        throw new Error(`XLSX export failed: HTTP ${xlsxBuf.status}`);
    }
    if (!xlsxBuf.contentType.includes('spreadsheetml')) {
        throw new Error(`Wrong content-type: ${xlsxBuf.contentType}`);
    }
    if (xlsxBuf.bytes[0] !== 80 || xlsxBuf.bytes[1] !== 75) {
        throw new Error('XLSX không phải ZIP (thiếu signature 50 4B)');
    }

    const xlsxPath = path.join(ART, 'nhat-ky-export.xlsx');
    fs.writeFileSync(xlsxPath, Buffer.from(xlsxBuf.bytes));
    console.log(`    saved ${xlsxPath}`);

    // 4. Test filter module
    console.log('[4] GET /api/logs_export.php?format=csv&module=auth');
    const filtRes = await page.evaluate(async (base) => {
        const r = await fetch(base + '/api/logs_export.php?format=csv&module=auth', {
            credentials: 'include',
        });
        return { status: r.status, body: await r.text() };
    }, BASE);
    if (filtRes.status !== 200) {
        throw new Error(`Filter export failed: HTTP ${filtRes.status}`);
    }
    const lines = filtRes.body.split('\n').filter(l => l.length > 5);
    console.log(`    filtered CSV: ${lines.length} lines (header + data)`);
    if (lines.length < 2) throw new Error('Filter export trả về < 2 dòng');

    // 5. Test 401 khi chưa login — dùng context MỚI không có cookie
    console.log('[5] GET /api/logs_export.php without auth (expect 401)');
    const ctx2 = await browser.newContext();
    const page2 = await ctx2.newPage();
    // Goto trang gốc để page có origin cho fetch CORS
    await page2.goto(BASE + '/?dangnhap=1', { waitUntil: 'domcontentloaded' });
    const noAuth = await page2.evaluate(async (base) => {
        const r = await fetch(base + '/api/logs_export.php?format=csv');
        return r.status;
    }, BASE);
    await ctx2.close();
    console.log(`    status=${noAuth} (expect 401)`);
    if (noAuth !== 401) throw new Error(`Expected 401, got ${noAuth}`);

    await browser.close();
    console.log('\nSMOKE OK');
})().catch(e => {
    console.error('SMOKE FAIL:', e.message);
    process.exit(1);
});
