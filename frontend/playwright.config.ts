import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  reporter: 'list',
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080',
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        launchOptions: {
          args: ['--unsafely-treat-insecure-origin-as-secure=http://app'],
          ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE
            ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE }
            : {}),
        },
      },
    },
  ],
})
