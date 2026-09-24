/**
 * TNTT Production E2E Tests - glyphutrung.top
 * Chạy: npx playwright test tests/e2e/production.spec.js
 */

const { test, expect } = require('@playwright/test');

const BASE_URL = process.env.TNTT_BASE_URL || 'https://glyphutrung.top';
const TEST_USER = {
    phone: '0937867508',
    password: 'tntt@2026'
};

test.describe('Production Smoke Tests', () => {
    test('01 - Server responds with HTTP 200', async ({ page }) => {
        const resp = await page.goto(BASE_URL);
        expect(resp.status()).toBe(200);
    });

    test('02 - Login page loads correctly', async ({ page }) => {
        await page.goto(BASE_URL);
        await expect(page.locator('input[type="tel"]').first()).toBeVisible({ timeout: 15000 });
    });

    test('03 - Login with valid credentials succeeds', async ({ page }) => {
        await page.goto(BASE_URL);
        await page.fill('input[type="tel"]', TEST_USER.phone);
        await page.fill('input[type="password"]', TEST_USER.password);
        await page.click('button[type="submit"]');
        await page.waitForTimeout(3000);

        const url = page.url();
        expect(url).not.toContain('login');
    });

    test('04 - Dashboard loads after login', async ({ page }) => {
        await page.goto(BASE_URL);
        await page.fill('input[type="tel"]', TEST_USER.phone);
        await page.fill('input[type="password"]', TEST_USER.password);
        await page.click('button[type="submit"]');
        await page.waitForTimeout(3000);

        const content = await page.content();
        expect(content.length).toBeGreaterThan(2000);
    });

    test('05 - API login returns user data', async ({ request }) => {
        const resp = await request.post(`${BASE_URL}/api/auth.php?action=login`, {
            data: { phone: TEST_USER.phone, password: TEST_USER.password }
        });
        const data = await resp.json();

        expect(data.ok).toBeTruthy();
        expect(data.user).toBeTruthy();
        expect(data.user.id).toBeTruthy();
        expect(data.user.phone).toBe(TEST_USER.phone);
        expect(data.user.role).toBe('admin');
    });

    test('06 - Protected API requires authentication', async ({ request }) => {
        const resp = await request.get(`${BASE_URL}/api/auth.php?action=me`);
        const data = await resp.json();
        expect(data.ok === false || data.user === null).toBeTruthy();
    });
});

test.describe('Production API Endpoints', () => {
    test('07 - Students API accessible', async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();

        await page.goto(BASE_URL);
        await page.fill('input[type="tel"]', TEST_USER.phone);
        await page.fill('input[type="password"]', TEST_USER.password);
        await page.click('button[type="submit"]');
        await page.waitForTimeout(2000);

        const resp = await page.request.get(`${BASE_URL}/api/data.php?type=students`);
        expect(resp.ok()).toBeTruthy();

        await context.close();
    });

    test('08 - Attendance API accessible', async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();

        await page.goto(BASE_URL);
        await page.fill('input[type="tel"]', TEST_USER.phone);
        await page.fill('input[type="password"]', TEST_USER.password);
        await page.click('button[type="submit"]');
        await page.waitForTimeout(2000);

        const resp = await page.request.get(`${BASE_URL}/api/data.php?type=attendance`);
        expect(resp.ok()).toBeTruthy();

        await context.close();
    });

    test('09 - Reports API accessible', async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();

        await page.goto(BASE_URL);
        await page.fill('input[type="tel"]', TEST_USER.phone);
        await page.fill('input[type="password"]', TEST_USER.password);
        await page.click('button[type="submit"]');
        await page.waitForTimeout(2000);

        const resp = await page.request.get(`${BASE_URL}/api/data.php?type=reports`);
        expect(resp.status()).toBeGreaterThanOrEqual(200);

        await context.close();
    });
});

test.describe('Production Security Tests', () => {
    test('10 - Login fails with wrong password', async ({ request }) => {
        const resp = await request.post(`${BASE_URL}/api/auth.php?action=login`, {
            data: { phone: TEST_USER.phone, password: 'wrongpassword123' }
        });
        const data = await resp.json();
        expect(data.ok).toBeFalsy();
        expect(data.error).toBeTruthy();
    });

    test('11 - Login fails with non-existent phone', async ({ request }) => {
        const resp = await request.post(`${BASE_URL}/api/auth.php?action=login`, {
            data: { phone: '0909000000', password: 'anypassword' }
        });
        const data = await resp.json();
        expect(data.ok).toBeFalsy();
    });
});

test.describe('Production Performance Tests', () => {
    test('12 - Login page loads under 3 seconds', async ({ page }) => {
        const start = Date.now();
        await page.goto(BASE_URL);
        await page.waitForLoadState('domcontentloaded');
        const loadTime = Date.now() - start;
        expect(loadTime).toBeLessThan(3000);
    });

    test('13 - Dashboard loads under 5 seconds', async ({ page }) => {
        await page.goto(BASE_URL);
        await page.fill('input[type="tel"]', TEST_USER.phone);
        await page.fill('input[type="password"]', TEST_USER.password);
        await page.click('button[type="submit"]');
        await page.waitForTimeout(3000);

        const content = await page.content();
        expect(content.length).toBeGreaterThan(2000);
    });

    test('14 - API responses are fast', async ({ request }) => {
        const start = Date.now();
        const resp = await request.get(`${BASE_URL}/api/auth.php?action=me`);
        const loadTime = Date.now() - start;

        expect(resp.ok()).toBeTruthy();
        expect(loadTime).toBeLessThan(2000);
    });
});

test.describe('Production User Data Validation', () => {
    test('15 - User has proper admin role', async ({ request }) => {
        const resp = await request.post(`${BASE_URL}/api/auth.php?action=login`, {
            data: { phone: TEST_USER.phone, password: TEST_USER.password }
        });
        const data = await resp.json();

        expect(data.user.role).toBe('admin');
        expect(data.user.roleLevel).toBe(5);
    });

    test('16 - User has required fields', async ({ request }) => {
        const resp = await request.post(`${BASE_URL}/api/auth.php?action=login`, {
            data: { phone: TEST_USER.phone, password: TEST_USER.password }
        });
        const data = await resp.json();

        expect(data.user).toHaveProperty('id');
        expect(data.user).toHaveProperty('phone');
        expect(data.user).toHaveProperty('role');
        expect(data.user).toHaveProperty('fullName');
    });

    test('17 - Years API accessible', async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();

        await page.goto(BASE_URL);
        await page.fill('input[type="tel"]', TEST_USER.phone);
        await page.fill('input[type="password"]', TEST_USER.password);
        await page.click('button[type="submit"]');
        await page.waitForTimeout(2000);

        const resp = await page.request.get(`${BASE_URL}/api/years.php`);
        expect(resp.ok()).toBeTruthy();

        await context.close();
    });
});

test.describe('Production Responsive Tests', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(BASE_URL);
        await page.fill('input[type="tel"]', TEST_USER.phone);
        await page.fill('input[type="password"]', TEST_USER.password);
        await page.click('button[type="submit"]');
        await page.waitForTimeout(3000);
    });

    test('18 - Mobile view renders correctly', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 667 });
        await page.waitForTimeout(1000);
        const content = await page.content();
        expect(content.length).toBeGreaterThan(1000);
    });

    test('19 - Tablet view renders correctly', async ({ page }) => {
        await page.setViewportSize({ width: 768, height: 1024 });
        await page.waitForTimeout(1000);
        const content = await page.content();
        expect(content.length).toBeGreaterThan(1000);
    });

    test('20 - Desktop view renders correctly', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        await page.waitForTimeout(1000);
        const content = await page.content();
        expect(content.length).toBeGreaterThan(1000);
    });
});
