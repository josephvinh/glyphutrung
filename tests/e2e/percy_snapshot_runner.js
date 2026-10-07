// tests/e2e/percy_snapshot_runner.js
const { chromium } = require('playwright');
const { runSnapshots } = require('./percy_snapshot');

async function main() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  try {
    await runSnapshots(page);
    console.log('Percy snapshots completed successfully');
  } catch (err) {
    console.error('Snapshot failed:', err);
    process.exit(1);
  } finally {
    await browser.close();
  }
}

main();
