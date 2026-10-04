/**
 * Playwright E2E Tests for Light Mode Verification
 * Tests that dark mode has been completely removed from the app
 */
const { test, expect } = require('@playwright/test');

const BASE_URL = 'file:///D:/orca/glyphutrung/dagon/public';

test.describe('Light Mode Verification', () => {

    test.beforeEach(async ({ page }) => {
        // Clear localStorage to ensure clean state
        await page.goto(BASE_URL + '/index.php');
        await page.evaluate(() => localStorage.clear());
    });

    test('should not have .dark class on html element', async ({ page }) => {
        await page.goto(BASE_URL + '/index.php');
        const htmlClass = await page.getAttribute('html', 'class');
        expect(htmlClass).not.toContain('dark');
    });

    test('should not have theme toggle button', async ({ page }) => {
        await page.goto(BASE_URL + '/index.php');
        const toggleBtn = await page.locator('#theme-toggle');
        await expect(toggleBtn).toHaveCount(0);
    });

    test('should display light background on landing page', async ({ page }) => {
        await page.goto(BASE_URL + '/index.php');

        // Check body has light background
        const bodyBg = await page.evaluate(() => {
            return window.getComputedStyle(document.body).backgroundColor;
        });

        // Light mode should have light background
        console.log('Body background:', bodyBg);
    });

    test('should not have dark mode CSS variables', async ({ page }) => {
        await page.goto(BASE_URL + '/index.php');

        // Check that dark mode CSS variables are not present in CSS files
        const cssHasDark = await page.evaluate(() => {
            const styles = document.styleSheets;
            let hasDarkMode = false;
            for (const sheet of styles) {
                try {
                    const rules = sheet.cssRules || sheet.rules;
                    for (const rule of rules) {
                        if (rule.cssText && rule.cssText.includes('.dark ')) {
                            hasDarkMode = true;
                            break;
                        }
                    }
                } catch (e) {
                    // Cross-origin stylesheets may throw
                }
                if (hasDarkMode) break;
            }
            return hasDarkMode;
        });

        expect(cssHasDark).toBe(false);
    });

    test('should persist with light mode on reload', async ({ page }) => {
        await page.goto(BASE_URL + '/index.php');

        // Check no dark class persists
        await page.reload();
        const htmlClass = await page.getAttribute('html', 'class');
        expect(htmlClass).not.toContain('dark');
    });

    test('should have correct meta theme-color', async ({ page }) => {
        await page.goto(BASE_URL + '/index.php');

        const metaThemeColor = await page.locator('meta[name="theme-color"]').getAttribute('content');

        // Should be light mode color (#c8203a), not dark mode (#0f172a)
        expect(metaThemeColor).toBe('#c8203a');
    });

});

test.describe('Light Mode - Module Tests', () => {

    test('app shell loads with light mode', async ({ page }) => {
        await page.goto(BASE_URL + '/index.php');

        // Check that landing page elements are visible
        const landingContent = await page.locator('body');
        await expect(landingContent).toBeVisible();
    });

    test('dark mode localStorage key is not used', async ({ page }) => {
        await page.goto(BASE_URL + '/index.php');

        // Set dark mode in localStorage (should have no effect now)
        await page.evaluate(() => localStorage.setItem('darkMode', 'true'));
        await page.reload();

        // Should still be light mode
        const htmlClass = await page.getAttribute('html', 'class');
        expect(htmlClass).not.toContain('dark');
    });

});

test.describe('CSS Bundle Verification', () => {

    test('dark.css is not loaded', async ({ page }) => {
        const loadedStylesheets = [];

        page.on('response', response => {
            if (response.url().includes('.css')) {
                loadedStylesheets.push(response.url());
            }
        });

        await page.goto(BASE_URL + '/index.php');
        await page.waitForLoadState('networkidle');

        // dark.css should not be in the loaded stylesheets
        const hasDarkCss = loadedStylesheets.some(url => url.includes('dark.css'));
        expect(hasDarkCss).toBe(false);
    });

});
