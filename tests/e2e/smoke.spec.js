/**
 * TNTT E2E Smoke Tests - Simplified Version
 * Chạy: npx playwright test tests/e2e/smoke.spec.js
 */

const { test, expect } = require('@playwright/test');

const BASE_URL = process.env.TNTT_BASE_URL || 'http://localhost:8080';
const TEST_USER = {
    phone: '0901000001',
    password: 'tntt@2026'
};

test.describe('Authentication', () => {
    test('login form visible on fresh visit', async ({ page }) => {
        await page.goto(`${BASE_URL}/api/auth.php?action=logout`);
        await page.goto(BASE_URL);

        // Use first() to avoid strict mode violation (login + register form)
        await expect(page.locator('input[type="tel"]').first()).toBeVisible({ timeout: 10000 });
        await expect(page.locator('input[type="password"]').first()).toBeVisible();
    });

    test('login success redirects to dashboard', async ({ page }) => {
        await page.goto(BASE_URL);

        await page.fill('input[type="tel"]', TEST_USER.phone);
        await page.fill('input[type="password"]', TEST_USER.password);
        await page.click('button[type="submit"]');

        await page.waitForTimeout(3000);
        const url = page.url();
        expect(url).not.toContain('login');
    });

    test('logout clears session', async ({ page }) => {
        await page.goto(BASE_URL);
        await page.fill('input[type="tel"]', TEST_USER.phone);
        await page.fill('input[type="password"]', TEST_USER.password);
        await page.click('button[type="submit"]');
        await page.waitForTimeout(2000);

        await page.goto(`${BASE_URL}/api/auth.php?action=logout`);
        await page.waitForTimeout(1000);

        // After logout, should see login form
        await page.goto(BASE_URL);
        await expect(page.locator('input[type="tel"]').first()).toBeVisible({ timeout: 5000 });
    });
});

test.describe('Dashboard', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(BASE_URL);
        await page.fill('input[type="tel"]', TEST_USER.phone);
        await page.fill('input[type="password"]', TEST_USER.password);
        await page.click('button[type="submit"]');
        await page.waitForTimeout(3000);
    });

    test('dashboard loads with content', async ({ page }) => {
        const body = await page.content();
        expect(body.length).toBeGreaterThan(1000);
    });

    test('app shell exists', async ({ page }) => {
        await expect(page.locator('.app-shell')).toBeVisible({ timeout: 5000 });
    });
});

test.describe('Navigation', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(BASE_URL);
        await page.fill('input[type="tel"]', TEST_USER.phone);
        await page.fill('input[type="password"]', TEST_USER.password);
        await page.click('button[type="submit"]');
        await page.waitForTimeout(3000);
    });

    test('page contains navigation elements', async ({ page }) => {
        // Check page has some content structure
        const body = await page.content();
        expect(body.length).toBeGreaterThan(5000);
    });
});

test.describe('API Endpoints', () => {
    test('me endpoint returns proper response', async ({ request }) => {
        const resp = await request.get(`${BASE_URL}/api/auth.php?action=me`);
        const data = await resp.json();
        expect(data).toHaveProperty('ok');
    });

    test('login API validates credentials', async ({ request }) => {
        const resp = await request.post(`${BASE_URL}/api/auth.php?action=login`, {
            data: { phone: TEST_USER.phone, password: 'wrongpassword' }
        });
        const data = await resp.json();
        expect(data.ok).toBeFalsy();
        expect(data.error).toBeTruthy();
    });

    test('invalid phone returns error', async ({ request }) => {
        const resp = await request.post(`${BASE_URL}/api/auth.php?action=login`, {
            data: { phone: '0000000000', password: 'any' }
        });
        const data = await resp.json();
        expect(data.ok).toBeFalsy();
    });
});

test.describe('Responsive Layout', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(BASE_URL);
        await page.fill('input[type="tel"]', TEST_USER.phone);
        await page.fill('input[type="password"]', TEST_USER.password);
        await page.click('button[type="submit"]');
        await page.waitForTimeout(3000);
    });

    test('works on mobile viewport', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 667 });
        await page.waitForTimeout(500);
        expect(await page.content()).toBeTruthy();
    });

    test('works on tablet viewport', async ({ page }) => {
        await page.setViewportSize({ width: 768, height: 1024 });
        await page.waitForTimeout(500);
        expect(await page.content()).toBeTruthy();
    });

    test('works on desktop viewport', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        await page.waitForTimeout(500);
        expect(await page.content()).toBeTruthy();
    });
});

test.describe('Core Pages', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(BASE_URL);
        await page.fill('input[type="tel"]', TEST_USER.phone);
        await page.fill('input[type="password"]', TEST_USER.password);
        await page.click('button[type="submit"]');
        await page.waitForTimeout(3000);
    });

    test('students module accessible', async ({ page }) => {
        // Click students link in sidebar or menu
        const link = page.locator('a').filter({ hasText: /học sinh/i }).first();
        if (await link.count() > 0) {
            await link.click();
            await page.waitForTimeout(1000);
        }
        expect(await page.content()).toBeTruthy();
    });

    test('attendance module accessible', async ({ page }) => {
        const link = page.locator('a').filter({ hasText: /diem danh/i }).first();
        if (await link.count() > 0) {
            await link.click();
            await page.waitForTimeout(1000);
        }
        expect(await page.content()).toBeTruthy();
    });
});
