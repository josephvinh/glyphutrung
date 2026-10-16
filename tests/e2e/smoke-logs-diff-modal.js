// tests/e2e/smoke-logs-diff-modal.js
// Test modal xem diff trong màn Cài đặt → Nhật ký.
// Inject 1 log có diff_json, mở app, vào Cài đặt → Nhật ký,
// bấm vào dòng có diff → verify modal hiện với bảng Trường/Cũ/Mới.

const { chromium } = require('playwright');
const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs');

const BASE = process.env.SMOKE_BASE || 'http://127.0.0.1:8767';
const DB = process.env.E2E_DB || 'tntt_demo';
const ART = path.join(__dirname, '..', 'artifacts');

// MariaDB client: thử `mysql` trong PATH trước; nếu không có thì dùng đường
// dẫn tường minh (Windows thường cài XAMPP ở G:\xampp, không có trong PATH).
const mysqlCmd = (() => {
    try { execSync('mysql --version', { stdio: 'ignore' }); return 'mysql'; }
    catch (_) { return 'G:/xampp/mysql/bin/mysql.exe'; }
})();

const mysql = (q) => execSync(
    `"${mysqlCmd}" -uroot --default-character-set=utf8mb4 ${DB} -N -B -e "${q.replace(/"/g, '\\"')}"`
).toString().trim();

(async () => {
    fs.mkdirSync(ART, { recursive: true });

    // Đảm bảo admin không bị chặn
    try { mysql("UPDATE members SET must_change_pw=0"); } catch (_) {}

    // Tìm 1 log gần nhất có diff_json thật (do tầng API ghi) — nếu không có
    // thì inject 1 dòng test để verify modal chạy được.
    let logId = parseInt(mysql(
        "SELECT id FROM activity_logs WHERE diff_json IS NOT NULL AND diff_json <> 'null' ORDER BY id DESC LIMIT 1"
    ) || '0', 10);

    if (!logId) {
        logId = parseInt(mysql("SELECT MAX(id) FROM activity_logs") || '0', 10);
        if (!logId) throw new Error('Không có log nào trong DB để test');
        // Dùng ASCII để tránh vỡ shell encoding; UI vẫn hiển thị tiếng Việt
        // nhờ backend trả về label đã ghi DB.
        mysql(`UPDATE activity_logs SET diff_json = JSON_OBJECT('before', JSON_OBJECT('name', 'Nguyen Van A', 'parent_phone', '0901234567'), 'after', JSON_OBJECT('name', 'Nguyen Van B', 'parent_phone', '0907654321')), target_type = 'student', target_id = 1, target_label = 'Nguyen Van B (#1)' WHERE id = ${logId}`);
        console.log(`[setup] injected diff_json on log id=${logId}`);
    } else {
        console.log(`[setup] using existing diff log id=${logId}`);
    }

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    const page = await context.newPage();
    page.setDefaultTimeout(15000);

    console.log('[1] login as admin');
    await page.goto(BASE + '/?dangnhap=1', { waitUntil: 'networkidle' });
    await page.waitForSelector('input[type=tel]');
    await page.fill('input[type=tel]', '0901000001');
    await page.fill('input[type=password]', 'tntt@2026');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2500);

    console.log('[2] navigate to Cài đặt → Nhật ký');
    // Bấm nút cài đặt trên App Center — tìm bằng nhãn
    const settingsBtn = page.locator('button:has-text("Cài Đặt"), button:has-text("Cài đặt")').first();
    await settingsBtn.click();
    await page.waitForTimeout(800);

    // Bấm tab "Nhật ký"
    const logsTab = page.locator('button:has-text("Nhật ký")').first();
    await logsTab.click();
    await page.waitForTimeout(500);

    console.log('[3] find log row with diff and click');
    // Dòng có diff có class cursor-pointer + role=button. Click dòng đầu tiên có.
    const diffRow = page.locator('[role="button"][aria-label="Bấm để xem chi tiết thay đổi"]').first();
    const count = await diffRow.count();
    if (count === 0) throw new Error('Không thấy dòng nhật ký nào có diff trong UI');
    console.log(`    found ${count} clickable diff row(s)`);
    await diffRow.click();
    await page.waitForTimeout(400);

    console.log('[4] verify modal opened');
    const modal = page.locator('[role="dialog"][aria-labelledby="logDiffTitle"]');
    await modal.waitFor({ state: 'visible', timeout: 5000 });

    // Verify header (what + actor)
    const title = await modal.locator('#logDiffTitle').textContent();
    console.log(`    title="${title}"`);
    if (!title || title.length < 3) throw new Error('Modal title rỗng');

    // Verify bảng có header Trường / Cũ / Mới
    const headers = await modal.locator('.grid-cols-\\[120px_1fr_1fr\\] > div').allTextContents();
    console.log(`    headers=${JSON.stringify(headers.slice(0, 3))}`);
    if (!headers.includes('Trường') || !headers.includes('Cũ') || !headers.includes('Mới')) {
        throw new Error('Modal thiếu header Trường/Cũ/Mới');
    }

    // Verify có ít nhất 1 row diff (name, parent_phone)
    const rowsText = await modal.locator('.grid-cols-\\[120px_1fr_1fr\\]').allTextContents();
    const allText = rowsText.join(' | ');
    console.log(`    body length=${allText.length}`);
    console.log(`    body preview=${allText.slice(0, 300)}`);
    if (!allText.includes('name') || !allText.includes('parent_phone')) {
        throw new Error('Modal thiếu field name hoặc parent_phone');
    }
    // Có thể tên bị normalize (bỏ dấu) trong tiếng Việt — chấp nhận có 'Nguyen' hoặc 'Van'
    if (!/Nguyen|Van/.test(allText)) {
        throw new Error('Modal không hiển thị giá trị cũ/mới: ' + allText);
    }

    // Screenshot
    const shot = path.join(ART, 'log-diff-modal.png');
    await page.screenshot({ path: shot, fullPage: false });
    console.log(`    saved ${shot}`);

    // Đóng modal bằng ESC
    console.log('[5] close modal with ESC');
    await page.keyboard.press('Escape');
    await page.waitForTimeout(300);
    const stillOpen = await modal.isVisible().catch(() => false);
    if (stillOpen) throw new Error('Modal không đóng khi bấm ESC');
    console.log('    modal closed');

    // Mở lại lần 2 bằng click để xác nhận idempotent
    console.log('[6] reopen + close via outside click');
    await diffRow.click();
    await page.waitForTimeout(300);
    await modal.waitFor({ state: 'visible' });
    // Click nền (click.self="closeLogDiff")
    await page.locator('.fixed.inset-0.z-40').click({ position: { x: 5, y: 5 } });
    await page.waitForTimeout(300);
    const stillOpen2 = await modal.isVisible().catch(() => false);
    if (stillOpen2) throw new Error('Modal không đóng khi bấm ngoài');
    console.log('    OK');

    await browser.close();
    console.log('\nSMOKE OK');
})().catch(async (e) => {
    console.error('SMOKE FAIL:', e.message);
    // Dọn diff_json do test inject để DB về trạng thái sạch
    try {
        if (typeof logId === 'number' && logId > 0) {
            mysql(`UPDATE activity_logs SET diff_json = NULL, target_type = NULL, target_id = NULL, target_label = NULL WHERE id = ${logId}`);
            console.error(`[cleanup] cleared test diff on log id=${logId}`);
        }
    } catch (_) { /* ignore */ }
    process.exit(1);
});
