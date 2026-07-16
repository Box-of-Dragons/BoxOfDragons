const { expect, test } = require('@playwright/test');

test.describe('changelog page', () => {
  test('loads the generated changelog and exposes its sections', async ({ page }) => {
    await page.goto('https://craftcms.ddev.site/changelog');

    await expect(page).toHaveTitle(/Changelog/);
    await expect(page.locator('body')).toContainText('Build Snapshot');
    await expect(page.locator('body')).toContainText('Change Types');
    await expect(page.locator('body')).toContainText('Features');
    await expect(page.locator('body')).toContainText('Use shared site-footer partial on all pages');
    await expect(page.locator('body')).toContainText('Replace inline footer markup with include');
    await expect(page.locator('body')).not.toContainText('Twig Syntax Error');
  });
});
