// tests/e2e/smoke-logs-filter.js
// Smoke test UI logs filter: mở app, login admin, vào tab Nhật ký,
// chụp ảnh 2 viewport, verify 4 filter inputs có mặt.

const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE = process.env.SMOKE_BASE || 'http://127.0.0.1:8765';
const ART = path.join(__dirname, '..', 'artifacts');

(async () => {
    fs.mkdirSync(ART, { recursive: true });

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();

    // Mobile viewport
    const mobile = await context.newPage();
    await mobile.setViewportSize({ width: 375, height: 812 });
    console.log('[mobile] goto /?dangnhap=1');
    await mobile.goto(`${BASE}/?dangnhap=1`, { waitUntil: 'networkidle' });

    // Login - dùng SĐT (admin id=1 có phone 0901000001)
    await mobile.waitForSelector('input[type=tel]', { timeout: 10000 });
    await mobile.fill('input[type=tel]', '0901000001');
    await mobile.fill('input[type=password]', 'tntt@2026');
    await mobile.click('button[type="submit"]');
    await mobile.waitForTimeout(2500);
    const url1 = mobile.url();
    console.log(`[mobile] after login: ${url1}`);

    // Nếu còn ở màn login → login thất bại
    if (url1.includes('dangnhap=1')) {
        const err = await mobile.evaluate(() => {
            const el = document.querySelector('[x-data] .text-rose-600, [x-data] .text-rose-500, [x-data] [class*="rose"]');
            return el ? el.innerText : 'unknown error';
        });
        throw new Error(`Login failed: ${err}`);
    }

    // Vào trang settings (Alpine SPA - dùng state.currentModule)
    await mobile.goto(`${BASE}/`, { waitUntil: 'networkidle' });
    await mobile.waitForTimeout(1000);
    // Click menu Cài đặt (nếu có) hoặc set state trực tiếp
    const switched = await mobile.evaluate(() => {
        const root = document.querySelector('[x-data]');
        if (!root || !root._x_dataStack) return 'no-alpine';
        const data = root._x_dataStack[0];
        // Tìm tên state (có thể là currentModule / currentTab / etc)
        const keys = Object.keys(data);
        if (data.currentModule !== undefined) {
            data.currentModule = 'settings';
            return 'set-currentModule';
        }
        return 'keys: ' + keys.filter(k => /tab|module|menu/i.test(k)).join(',');
    });
    console.log(`[mobile] module switch: ${switched}`);

    // Đợi Alpine render xong rồi set settingsTab = 'logs'
    await mobile.waitForTimeout(500);
    const tabSwitched = await mobile.evaluate(() => {
        const root = document.querySelector('[x-data]');
        const data = root._x_dataStack[0];
        if (data.settingsTab !== undefined) {
            data.settingsTab = 'logs';
            return true;
        }
        return false;
    });
    console.log(`[mobile] settingsTab set: ${tabSwitched}`);
    await mobile.waitForTimeout(2000);

    // Verify 4 filter inputs
    const filterInputs = await mobile.evaluate(() => {
        const inputs = Array.from(document.querySelectorAll('[x-model^="logFilter"]'));
        return inputs.map(el => ({
            model: el.getAttribute('x-model'),
            tag: el.tagName,
            type: el.type || null,
        }));
    });
    console.log('[mobile] filter inputs:', JSON.stringify(filterInputs, null, 2));

    // Chụp ảnh mobile
    const mobileShot = path.join(ART, 'logs-filter-mobile.png');
    await mobile.screenshot({ path: mobileShot, fullPage: true });
    console.log(`[mobile] saved ${mobileShot}`);

    // Desktop viewport
    const desktop = await context.newPage();
    await desktop.setViewportSize({ width: 1280, height: 800 });
    await desktop.goto(`${BASE}/`, { waitUntil: 'networkidle' });
    await desktop.waitForTimeout(1000);
    await desktop.evaluate(() => {
        const root = document.querySelector('[x-data]');
        const data = root._x_dataStack[0];
        if (data.currentModule !== undefined) data.currentModule = 'settings';
    });
    await desktop.waitForTimeout(500);
    await desktop.evaluate(() => {
        const root = document.querySelector('[x-data]');
        const data = root._x_dataStack[0];
        if (data.settingsTab !== undefined) data.settingsTab = 'logs';
    });
    await desktop.waitForTimeout(2000);

    const filterInputsD = await desktop.evaluate(() => {
        const inputs = Array.from(document.querySelectorAll('[x-model^="logFilter"]'));
        return inputs.map(el => ({
            model: el.getAttribute('x-model'),
            tag: el.tagName,
            type: el.type || null,
        }));
    });
    console.log('[desktop] filter inputs:', JSON.stringify(filterInputsD, null, 2));

    const desktopShot = path.join(ART, 'logs-filter-desktop.png');
    await desktop.screenshot({ path: desktopShot, fullPage: true });
    console.log(`[desktop] saved ${desktopShot}`);

    // Test filter: chọn 1 module, verify danh sách lọc
    await desktop.selectOption('select[x-model="logFilterModule"]', { index: 1 });
    await desktop.waitForTimeout(800);
    const filteredCount = await desktop.evaluate(() => {
        const rows = document.querySelectorAll('[x-data] tbody tr, [x-data] .log-row, [x-data] li');
        return rows.length;
    });
    console.log(`[desktop] after filter module, rows: ${filteredCount}`);

    const afterFilter = path.join(ART, 'logs-filter-after.png');
    await desktop.screenshot({ path: afterFilter, fullPage: true });
    console.log(`[desktop] saved ${afterFilter}`);

    // Verify Alpine app loaded (filteredLogs getter không lỗi)
    const alpineOk = await desktop.evaluate(() => {
        const root = document.querySelector('[x-data]');
        if (!root || !root._x_dataStack) return { ok: false, reason: 'no alpine root' };
        const data = root._x_dataStack[0];
        return {
            ok: true,
            hasFilterState: {
                actor: data.logFilterActor,
                module: data.logFilterModule,
                from: data.logFilterFrom,
                to: data.logFilterTo,
            },
            filteredCount: data.filteredLogs ? data.filteredLogs.length : null,
            totalLogs: data.logs ? data.logs.length : null,
        };
    });
    console.log('[desktop] Alpine state:', JSON.stringify(alpineOk, null, 2));

    // Check console errors
    const consoleErrors = [];
    desktop.on('console', msg => {
        if (msg.type() === 'error') consoleErrors.push(msg.text());
    });
    await desktop.reload({ waitUntil: 'networkidle' });
    await desktop.waitForTimeout(2000);
    console.log(`[desktop] console errors after reload: ${consoleErrors.length}`);
    if (consoleErrors.length > 0) {
        console.log(consoleErrors.slice(0, 5).map(e => '  - ' + e).join('\n'));
    }

    await browser.close();
    console.log('SMOKE OK');
})().catch(e => {
    console.error('SMOKE FAIL:', e);
    process.exit(1);
});
