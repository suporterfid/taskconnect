import { expect, test } from '@playwright/test'

test('login page shows email and password fields', async ({ page }) => {
  await page.goto('/login')

  await expect(page.locator('#email, input[type="email"]').first()).toBeVisible()
  await expect(page.locator('#password, input[type="password"]').first()).toBeVisible()
})
