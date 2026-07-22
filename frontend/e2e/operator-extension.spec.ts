import { expect, test } from '@playwright/test'

test.describe('operator extension surfaces', () => {
  test('dlq and pipelines routes redirect unauthenticated users to login', async ({ page }) => {
    await page.goto('/dlq')
    await expect(page).toHaveURL(/\/login/)

    await page.goto('/pipelines')
    await expect(page).toHaveURL(/\/login/)
  })

  test('login page still loads after operator nav additions', async ({ page }) => {
    await page.goto('/login')
    await expect(page.getByRole('heading', { name: /sign in|entrar/i })).toBeVisible()
  })
})
