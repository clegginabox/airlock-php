import {test, expect} from '@playwright/test';

const BASE_URL = (process.env.BASE_URL || 'http://localhost').replace(/\/+$/, '');
const SUCCESS_PATH = /\/redis-lottery-queue\/success\/?$/;

test.describe.serial('redis-lottery-queue - Redis Lottery Queue', () => {
  test.beforeEach(async ({request}) => {
    // Clear all Redis keys for this example
    const response = await request.get(`${BASE_URL}/reset`);
    console.log('Reset response:', await response.json());
  });

  test('should display initial state correctly', async ({page}) => {
    await page.goto(`${BASE_URL}/redis-lottery-queue/`);

    await expect(page.locator('h1')).toHaveText('Lottery Queue');
    await expect(page.locator('#go')).toBeEnabled();
    await expect(page.locator('#reset')).toBeEnabled();
    await expect(page.locator('#status')).toBeEmpty();
  });

  test('should allow one user in immediately', async ({page}) => {
    await page.goto(`${BASE_URL}/redis-lottery-queue/`);

    await page.locator('#go').click();

    // Should show you got in immediately
    await expect(page.locator('#status')).toContainText('You got in immediately! Redirecting...');

    // Wait for redirect and check page title
    await expect(page).toHaveURL(SUCCESS_PATH);
    await expect(page.locator('h1')).toHaveText('You Made It!');
  });

  test('should allow one user in and make one user wait', async ({browser}) => {
    const context1 = await browser.newContext();
    const context2 = await browser.newContext();
    const page1 = await context1.newPage();
    const page2 = await context2.newPage();

    try {
      // First user gets in
      await page1.goto(`${BASE_URL}/redis-lottery-queue/`);
      await page1.locator('#go').click();
      await expect(page1.locator('#status')).toContainText('You got in immediately!');
      await expect(page1).toHaveURL(SUCCESS_PATH);

      // Second user has to wait
      await page2.goto(`${BASE_URL}/redis-lottery-queue/`);
      await page2.locator('#go').click();
      await expect(page2.locator('#status')).toContainText('Waiting in lottery queue...');

      // Release explicitly from user 1's session instead of relying on the 5s client timer.
      const releaseResponse = await context1.request.post(`${BASE_URL}/redis-lottery-queue/release`);
      expect(releaseResponse.ok()).toBeTruthy();

      // Second user redirected to the success page
      await expect(page2.locator('#status')).toContainText('Your turn! Redirecting...', {timeout: 10000});
      await expect(page2).toHaveURL(SUCCESS_PATH, {timeout: 5000});
      await expect(page2.locator('h1')).toHaveText('You Made It!');
    } finally {
      await context1.close();
      await context2.close();
    }
  });

  /*test('second user should be blocked when lock is held', async ({browser}) => {
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
  });*!/*/
});
