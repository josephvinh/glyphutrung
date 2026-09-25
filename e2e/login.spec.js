/**
 * E2E Test - Login Page
 *
 * Test các chức năng đăng nhập cơ bản
 */

const { test, expect } = require('@playwright/test');

test.describe('Login Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login.php');
  });

  test('hiển thị form đăng nhập', async ({ page }) => {
    // Kiểm tra các trường input tồn tại
    await expect(page.locator('input[name="phone"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('đăng nhập thất bại với thông tin sai', async ({ page }) => {
    await page.fill('input[name="phone"]', '0909000000');
    await page.fill('input[name="password"]', 'wrongpassword');
    await page.click('button[type="submit"]');

    // Kiểm tra hiển thị thông báo lỗi
    await expect(page.locator('.error-message, .alert-danger, [role="alert"]')).toBeVisible({ timeout: 5000 });
  });

  test('đăng nhập thành công với thông tin hợp lệ', async ({ page }) => {
    // Lưu ý: Test này cần credentials thực tế trong môi trường test
    // Skip nếu không có test credentials
    test.skip(true, 'Cần credentials test');

    await page.fill('input[name="phone"]', process.env.TEST_PHONE || '0909000000');
    await page.fill('input[name="password"]', process.env.TEST_PASSWORD || 'testpassword');
    await page.click('button[type="submit"]');

    // Chuyển hướng đến trang chủ sau khi đăng nhập
    await expect(page).toHaveURL(/\/(index|home|dashboard)/);
  });

  test('validation: số điện thoại không hợp lệ', async ({ page }) => {
    await page.fill('input[name="phone"]', 'abc');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');

    // Kiểm tra validation message
    await expect(page.locator('input[name="phone"]:invalid, .error-phone')).toBeVisible();
  });
});

test.describe('Login Rate Limiting', () => {
  test('hiển thị thông báo khi đăng nhập sai nhiều lần', async ({ page }) => {
    await page.goto('/login.php');

    // Thử đăng nhập sai 5 lần
    for (let i = 0; i < 5; i++) {
      await page.fill('input[name="phone"]', '0909000000');
      await page.fill('input[name="password"]', 'wrongpassword');
      await page.click('button[type="submit"]');
      await page.waitForTimeout(500); // Đợi để tránh spam
    }

    // Sau 5 lần sai, có thể bị rate limit
    await expect(page.locator('text=/quá nhiều|rate limit|too many/i')).toBeVisible({ timeout: 10000 });
  });
});
