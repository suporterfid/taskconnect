import { expect, type Page } from '@playwright/test'

/** Login when E2E_EMAIL / E2E_PASSWORD are set; throws if missing. */
export async function loginAsE2EOperator(page: Page): Promise<void> {
  const email = process.env.E2E_EMAIL
  const password = process.env.E2E_PASSWORD
  if (!email || !password) {
    throw new Error('E2E_EMAIL and E2E_PASSWORD are required for this helper')
  }

  await page.goto('/login')
  await page.locator('#email, input[type="email"]').first().fill(email)
  await page.locator('#password, input[type="password"]').first().fill(password)
  await page.getByRole('button', { name: /sign in|entrar/i }).click()
  await expect(page).toHaveURL(/\/(dashboard|tenants)/, { timeout: 15_000 })
}

export function e2eCredentialsConfigured(): boolean {
  return Boolean(process.env.E2E_EMAIL && process.env.E2E_PASSWORD)
}
