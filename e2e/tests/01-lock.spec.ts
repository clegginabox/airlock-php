import {test, expect} from '@playwright/test';

const BASE_URL = process.env.BASE_URL || 'http://localhost';

test.describe.serial('01-global-lock - Double-Click Protection', () => {
  test.beforeEach(async ({request}) => {
    // Clear all Redis keys for this example
    const response = await request.get(`${BASE_URL}/reset`);
    console.log('Reset response:', await response.json());
  });

  test('should display initial state correctly', async ({page}) => {
    await page.goto('/global-lock/');

    await expect(page.locator('h1')).toHaveText('Airlock: Double-Click Protection');
    await expect(page.locator('#go')).toBeEnabled();
    await expect(page.locator('#status')).toBeEmpty();
  });

  test('should process action and show done state', async ({page}) => {
    await page.goto('/global-lock/');

    await page.locator('#go').click();

    // Should show submitting, then running
    await expect(page.locator('#status')).toContainText('Processing');

    // Wait for completion (5s work + buffer)
    await expect(page.locator('#status')).toContainText('Done', {timeout: 5000});
    await expect(page.locator('#status')).toHaveClass(/ok/);
  });

  test('should block double-click with instant rejection', async ({page}) => {
    await page.goto('/global-lock/');

    // First click - starts processing
    await page.locator('#go').click();
    await expect(page.locator('#status')).toContainText('Processing');

    // Second click while still processing - should be instantly blocked
    await page.locator('#go').click();
    await expect(page.locator('#status')).toContainText('Already processing');
    await expect(page.locator('#status')).toHaveClass(/error/);
  });

  test('second user should be blocked when lock is held', async ({browser}) => {
    // Create two browser contexts (simulating two users)
    const context1 = await browser.newContext();
    const context2 = await browser.newContext();
    const page1 = await context1.newPage();
    const page2 = await context2.newPage();

    try {
      // First user starts action
      await page1.goto('/global-lock/');
      await page1.locator('#go').click();
      await expect(page1.locator('#status')).toContainText('Processing');

      // Second user tries to start - should be instantly blocked
      await page2.goto('/global-lock/');
      await page2.locator('#go').click();
      await expect(page2.locator('#status')).toContainText('Already processing');
      await expect(page2.locator('#status')).toHaveClass(/error/);

      // Wait for first user to complete
      await expect(page1.locator('#status')).toContainText('Done', {timeout: 5000});

      // Second user can now start
      await page2.locator('#go').click();
      await expect(page2.locator('#status')).toContainText('Processing');
    } finally {
      await context1.close();
      await context2.close();
    }
  });
});
