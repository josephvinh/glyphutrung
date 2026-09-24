/**
 * Playwright Configuration for TNTT E2E Tests
 *
 * Chạy tests:
 *   npx playwright test                    # Chạy tất cả
 *   npx playwright test smoke.spec.js     # Chỉ smoke tests
 *   npx playwright test --headed          # Hiển thị browser
 *   npx playwright test --debug           # Debug mode
 */

const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/e2e',
  timeout: 30000, // 30s per test
  expect: {
    timeout: 5000
  },
  fullyParallel: false, // Chạy tuần tự để tránh conflict session
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: [
    ['list'],
    ['html', { outputFolder: 'tests/e2e/reports', open: 'never' }]
  ],

  use: {
    baseURL: process.env.TNTT_BASE_URL || 'http://localhost:8080',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'chromium-touch',
      use: {
        ...devices['Desktop Chrome'],
        hasTouch: true,
        isMobile: true,
      },
    },
    // Mobile project đã loại bỏ - responsive tested qua chromium-touch
  ],
});
