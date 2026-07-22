import { expect, test } from '@playwright/test'

import { e2eCredentialsConfigured, loginAsE2EOperator } from './helpers/auth'

/**
 * Authenticated DLQ + pipelines operator journeys (R14 DoD).
 * Skips when E2E_EMAIL / E2E_PASSWORD unset so `tc e2e` stays green.
 * Fixture seed: see docs/deployment/e2e-operator.md
 */
test.describe('authenticated DLQ and pipelines', () => {
  test.beforeEach(({}, testInfo) => {
    testInfo.skip(
      !e2eCredentialsConfigured(),
      'Set E2E_EMAIL and E2E_PASSWORD (and seed fixtures per docs/deployment/e2e-operator.md).',
    )
  })

  test('operator can inspect and replay a dead run from DLQ', async ({ page }) => {
    await loginAsE2EOperator(page)

    await page.goto('/dlq')
    await expect(page.getByTestId('dlq-page')).toBeVisible()
    await expect(page.getByRole('heading', { name: /dead letter|dlq|fila/i })).toBeVisible()

    const empty = page.getByTestId('dlq-empty')
    const table = page.getByTestId('dlq-table')
    await expect(empty.or(table)).toBeVisible({ timeout: 15_000 })

    if (await empty.isVisible()) {
      test.skip(
        true,
        'No dead runs in DLQ. Seed a dead run (docs/deployment/e2e-operator.md) then re-run.',
      )
    }

    await expect(table).toBeVisible()
    const inspect = page.getByTestId('dlq-inspect').first()
    await expect(inspect).toBeVisible()
    await inspect.click()
    await expect(page).toHaveURL(/\/runs\//, { timeout: 10_000 })

    await page.goto('/dlq')
    await expect(table).toBeVisible()

    page.once('dialog', (dialog) => dialog.accept())
    await page.getByTestId('dlq-replay').first().click()

    // Replay removes the dead run from the list or leaves empty/table without error alert.
    await expect(page.getByRole('alert')).toHaveCount(0)
    await expect(empty.or(table)).toBeVisible({ timeout: 10_000 })
  })

  test('operator can open a pipeline instance detail', async ({ page }) => {
    await loginAsE2EOperator(page)

    await page.goto('/pipelines')
    await expect(page.getByTestId('pipelines-page')).toBeVisible()
    await expect(page.getByRole('heading', { name: /pipelines/i })).toBeVisible()

    const instanceLink = page.getByTestId('pipeline-instance-link').first()
    if (!(await instanceLink.count())) {
      test.skip(
        true,
        'No pipeline instances. Seed one (docs/deployment/e2e-operator.md) then re-run.',
      )
    }

    await instanceLink.click()
    await expect(page).toHaveURL(/\/pipelines\/.+\/instances\//, { timeout: 10_000 })
    await expect(page.getByTestId('pipeline-detail-page')).toBeVisible()
    await expect(page.getByText(/nodes|nós|template/i).first()).toBeVisible()
  })
})
