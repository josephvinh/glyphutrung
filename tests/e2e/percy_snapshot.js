// tests/e2e/percy_snapshot.js
const { percySnapshot } = require('@percy/playwright');

const BASE = process.env.E2E_BASE || 'http://127.0.0.1:8080';

async function runSnapshots(page) {
  // Public pages
  await page.goto(BASE + '/');
  await percySnapshot(page, 'Landing Page');

  await page.goto(BASE + '/?dangnhap=1');
  await percySnapshot(page, 'Login Page');

  await page.goto(BASE + '/tracuu.php');
  await percySnapshot(page, 'Tra Cuu');

  await page.goto(BASE + '/somoc.php');
  await percySnapshot(page, 'So Moc');

  await page.goto(BASE + '/bxh.php');
  await percySnapshot(page, 'Bang Xep Hang');
}

module.exports = { runSnapshots };
