import { expect, test } from '@playwright/test'

test.describe('auth smoke', () => {
  test('login page loads and links to forgot password', async ({ page }) => {
    await page.goto('/login')

    await expect(page.getByRole('heading', { name: /sign in|entrar/i })).toBeVisible()
    await expect(page.locator('#email, input[type="email"]').first()).toBeVisible()
    await expect(page.locator('#password, input[type="password"]').first()).toBeVisible()

    await page.getByRole('link', { name: /forgot|esqueceu/i }).click()
    await expect(page).toHaveURL(/\/forgot-password/)
    await expect(
      page.getByRole('heading', { name: /forgot|esqueceu|reset|redefinir/i }),
    ).toBeVisible()
  })

  test('authenticated operator can open dashboard and tasks when credentials are set', async ({
    page,
  }) => {
    test.skip(
      !process.env.E2E_EMAIL || !process.env.E2E_PASSWORD,
      'Set E2E_EMAIL and E2E_PASSWORD to exercise the authenticated dashboard path.',
    )

    await page.goto('/login')
    await page.locator('#email, input[type="email"]').first().fill(process.env.E2E_EMAIL!)
    await page
      .locator('#password, input[type="password"]')
      .first()
      .fill(process.env.E2E_PASSWORD!)
    await page.getByRole('button', { name: /sign in|entrar/i }).click()

    await expect(page).toHaveURL(/\/(dashboard|tenants)/, { timeout: 15_000 })
    await expect(page.getByRole('navigation')).toBeVisible()

    await page.goto('/tasks')
    await expect(page.getByRole('heading', { name: /tasks|tarefas/i })).toBeVisible()

    await page.goto('/dlq')
    await expect(page.getByRole('heading', { name: /dead letter|dlq|fila/i })).toBeVisible()

    await page.goto('/pipelines')
    await expect(page.getByRole('heading', { name: /pipelines/i })).toBeVisible()

    await page.goto('/tasks/new')
    await expect(page.getByRole('heading', { name: /create|criar|task|tarefa/i })).toBeVisible()
  })
})
