import { expect, test } from '@playwright/test';

const baseURL = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const adminEmail = 'admin@hrnexus.com';
const adminPassword = 'password';

test('admin can log in and view the dashboard', async ({ page }) => {
    await page.goto(`${baseURL}/login`);

    await page.locator('input[name="email"]').fill(adminEmail);
    await page.locator('input[name="password"]').fill(adminPassword);
    await page.getByRole('button', { name: /log in|login|sign in/i }).click();

    await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15000 });

    const postLoginMarker = page
        .getByText(/dashboard|attendance|employees|documents|calendar|chats/i)
        .first();
    await expect(postLoginMarker).toBeVisible({ timeout: 15000 });

    await page.screenshot({ path: 'artifacts/login-dashboard.png', fullPage: true });
});

